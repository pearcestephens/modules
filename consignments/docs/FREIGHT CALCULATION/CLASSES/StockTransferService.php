<?php
/**
 * Stock Transfer Service
 * 
 * Extends BaseTransferService with stock-specific functionality
 * Integrates with existing WeightResolution and FreightAI services
 * 
 * @package Modules\Transfers\Stock\Services
 * @version 1.0.0
 * @since 2025-10-08
 */

namespace Modules\Transfers\Stock\Services;

require_once __DIR__ . '/../../core/Services/BaseTransferService.php';
require_once __DIR__ . '/../../core/Models/Transfer.php';
require_once __DIR__ . '/../../../_shared/Util/db_helpers.php';

use Modules\Transfers\Core\Services\BaseTransferService;
use Modules\Transfers\Core\Models\Transfer;
use PDO;

class StockTransferService extends BaseTransferService
{
    /** @var array<string,array|null> */
    protected array $outletCache = [];

    /** @var array<string,mixed>|null */
    protected ?array $inventoryMeta = null;

    /**
     * Initialize stock-specific configuration
     */
    protected function initialize(): void
    {
        $this->tableName = 'StockTransfers';
        $this->itemsTableName = 'StockTransferItems';
        $this->notesTableName = 'StockTransferNotes';
        $this->auditTableName = 'StockTransferAuditLog';
        $this->moduleType = 'stock';
    }

    /**
     * Get database connection
     * Uses existing db_helpers.php function
     */
    protected function getDb(): PDO
    {
        if ($this->db === null) {
            $this->db = get_db_pdo();
        }
        return $this->db;
    }

    /**
     * Get transfer with full stock-specific data
     * Includes weight resolution, freight options, Vend data
     * 
     * @param int $transferId Transfer ID
     * @return Transfer|null Transfer object or null if not found
     */
    public function getTransferWithDetails(int $transferId): ?Transfer
    {
        $transfer = $this->getTransfer($transferId);
        if (!$transfer) {
            return null;
        }

        // Add stock-specific enrichment
        $transfer = $this->enrichWithWeights($transfer);
        $transfer = $this->enrichWithFreightOptions($transfer);
        $transfer = $this->enrichWithVendData($transfer);

        return Transfer::fromArray($transfer);
    }

    /**
     * Enrich transfer items with weight resolution data
     * Integrates with existing WeightResolutionService
     * 
     * @param array $transfer Transfer data
     * @return array Transfer with weight data
     */
    protected function enrichWithWeights(array $transfer): array
    {
        if (empty($transfer['items'])) {
            return $transfer;
        }

        // Check if WeightResolutionService exists
        $weightServicePath = __DIR__ . '/../services/WeightResolutionService.php';
        if (!file_exists($weightServicePath)) {
            return $transfer;
        }

        require_once $weightServicePath;

        try {
            foreach ($transfer['items'] as &$item) {
                // Get weight from cascade: Vend → Master Product → Manual
                $weightData = \WeightResolutionService::resolveWeight(
                    $item['product_id'] ?? null,
                    $item['sku'] ?? null
                );

                if ($weightData) {
                    $item['weight'] = $weightData['weight'] ?? 0;
                    $item['weight_source'] = $weightData['source'] ?? 'unknown';
                    $item['dimensions'] = $weightData['dimensions'] ?? null;
                    $item['cubic_measurement'] = $weightData['cubic'] ?? null;
                    $item['volumetric_weight'] = $weightData['volumetric'] ?? null;
                }
            }
            unset($item);
        } catch (\Exception $e) {
            error_log("Weight resolution failed: " . $e->getMessage());
        }

        return $transfer;
    }

    /**
     * Enrich transfer with freight options from FreightAI
     * 
     * @param array $transfer Transfer data
     * @return array Transfer with freight options
     */
    protected function enrichWithFreightOptions(array $transfer): array
    {
        // Check if FreightAI service exists
        $freightServicePath = __DIR__ . '/../services/FreightAI.php';
        if (!file_exists($freightServicePath)) {
            return $transfer;
        }

        require_once $freightServicePath;

        try {
            // Calculate total weight and dimensions
            $totalWeight = 0;
            $items = [];
            
            foreach ($transfer['items'] ?? [] as $item) {
                $totalWeight += ($item['weight'] ?? 0) * ($item['quantity'] ?? 0);
                $items[] = [
                    'weight' => $item['weight'] ?? 0,
                    'quantity' => $item['quantity'] ?? 0,
                    'dimensions' => $item['dimensions'] ?? null
                ];
            }

            if ($totalWeight > 0) {
                $freightOptions = \FreightAI::calculateOptions([
                    'from_outlet_id' => $transfer['from_outlet_id'] ?? null,
                    'to_outlet_id' => $transfer['to_outlet_id'] ?? null,
                    'total_weight' => $totalWeight,
                    'items' => $items,
                    'transfer_id' => $transfer['id'] ?? null
                ]);

                $transfer['freight_options'] = $freightOptions ?? [];
            }
        } catch (\Exception $e) {
            error_log("Freight calculation failed: " . $e->getMessage());
            $transfer['freight_options'] = [];
        }

        return $transfer;
    }

    /**
     * Enrich transfer with Vend outlet data
     * 
     * @param array $transfer Transfer data
     * @return array Transfer with Vend data
     */
    protected function enrichWithVendData(array $transfer): array
    {
        $db = $this->getDb();

        try {
            // Get outlet names from vend_outlets
            if (!empty($transfer['from_outlet_id'])) {
                $stmt = $db->prepare("SELECT outlet_name FROM vend_outlets WHERE outlet_id = ?");
                $stmt->execute([$transfer['from_outlet_id']]);
                $result = $stmt->fetch();
                $transfer['from_outlet_name'] = $result['outlet_name'] ?? 'Unknown';
            }

            if (!empty($transfer['to_outlet_id'])) {
                $stmt = $db->prepare("SELECT outlet_name FROM vend_outlets WHERE outlet_id = ?");
                $stmt->execute([$transfer['to_outlet_id']]);
                $result = $stmt->fetch();
                $transfer['to_outlet_name'] = $result['outlet_name'] ?? 'Unknown';
            }
        } catch (\Exception $e) {
            error_log("Vend data enrichment failed: " . $e->getMessage());
        }

        return $transfer;
    }

    /**
     * Create stock transfer with Vend integration
     * 
     * @param array $data Transfer data
     * @param int $userId User ID
     * @return int Transfer ID
     * @throws \InvalidArgumentException
     */
    public function createStockTransfer(array $data, int $userId): int
    {
        // Validate stock-specific fields
        if (empty($data['from_outlet_id']) || empty($data['to_outlet_id'])) {
            throw new \InvalidArgumentException('From and To outlets are required for stock transfers');
        }

        if ($data['from_outlet_id'] === $data['to_outlet_id']) {
            throw new \InvalidArgumentException('From and To outlets must be different');
        }

        // Create base transfer
        $transferId = $this->createTransfer($data, $userId);

        // Add audit log entry
        $this->addAuditLog(
            $transferId,
            'created',
            $userId,
            json_encode([
                'from_outlet' => $data['from_outlet_id'],
                'to_outlet' => $data['to_outlet_id']
            ])
        );

        return $transferId;
    }

    /**
     * Complete packing and mark as ready to ship
     * 
     * @param int $transferId Transfer ID
     * @param int $userId User ID
     * @param array $packingData Additional packing data (boxes, freight selection)
     * @return bool Success
     */
    public function completePacking(int $transferId, int $userId, array $packingData = []): bool
    {
        $db = $this->getDb();
        $db->beginTransaction();

        try {
            // Update status to 'packed'
            $success = $this->updateStatus($transferId, 'packed', $userId);
            if (!$success) {
                throw new \RuntimeException('Failed to update transfer status');
            }

            // Save packing details
            if (!empty($packingData['freight_option_id'])) {
                $stmt = $db->prepare("
                    UPDATE {$this->tableName}
                    SET freight_option_id = ?,
                        packed_at = NOW(),
                        packed_by = ?
                    WHERE id = ?
                ");
                $stmt->execute([
                    $packingData['freight_option_id'],
                    $userId,
                    $transferId
                ]);
            }

            // Save box information if provided
            if (!empty($packingData['boxes'])) {
                $this->saveBoxInformation($transferId, $packingData['boxes'], $userId);
            }

            // Add audit log
            $this->addAuditLog(
                $transferId,
                'packed',
                $userId,
                json_encode([
                    'freight_option' => $packingData['freight_option_id'] ?? null,
                    'boxes' => count($packingData['boxes'] ?? [])
                ])
            );

            $db->commit();
            return true;

        } catch (\Exception $e) {
            $db->rollBack();
            error_log("Complete packing failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Save box information for packed transfer
     * 
     * @param int $transferId Transfer ID
     * @param array $boxes Box data
     * @param int $userId User ID
     */
    protected function saveBoxInformation(int $transferId, array $boxes, int $userId): void
    {
        $db = $this->getDb();

        // Delete existing boxes
        $stmt = $db->prepare("DELETE FROM StockTransferBoxes WHERE transfer_id = ?");
        $stmt->execute([$transferId]);

        // Insert new boxes
        $stmt = $db->prepare("
            INSERT INTO StockTransferBoxes 
            (transfer_id, box_number, weight, dimensions, items_json, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");

        foreach ($boxes as $index => $box) {
            $stmt->execute([
                $transferId,
                $index + 1,
                $box['weight'] ?? 0,
                $box['dimensions'] ?? null,
                json_encode($box['items'] ?? []),
                $userId
            ]);
        }
    }

    /**
     * Complete receiving and update inventory
     * 
     * @param int $transferId Transfer ID
     * @param int $userId User ID
     * @param array $receivedQuantities Item ID => received quantity
     * @return bool Success
     */
    public function completeReceiving(int $transferId, int $userId, array $receivedQuantities = []): bool
    {
        $db = $this->getDb();
        $db->beginTransaction();

        try {
            // Update status to 'received'
            $success = $this->updateStatus($transferId, 'received', $userId);
            if (!$success) {
                throw new \RuntimeException('Failed to update transfer status');
            }

            // Update received quantities
            $stmt = $db->prepare("
                UPDATE {$this->itemsTableName}
                SET received_quantity = ?,
                    received_at = NOW(),
                    received_by = ?
                WHERE id = ? AND transfer_id = ?
            ");

            foreach ($receivedQuantities as $itemId => $quantity) {
                $stmt->execute([$quantity, $userId, $itemId, $transferId]);
            }

            // Mark transfer as received
            $stmt = $db->prepare("
                UPDATE {$this->tableName}
                SET received_at = NOW(),
                    received_by = ?
                WHERE id = ?
            ");
            $stmt->execute([$userId, $transferId]);

            // Add audit log
            $this->addAuditLog(
                $transferId,
                'received',
                $userId,
                json_encode(['quantities' => $receivedQuantities])
            );

            // Trigger Vend inventory sync if service exists
            $this->triggerVendSync($transferId);

            $db->commit();
            return true;

        } catch (\Exception $e) {
            $db->rollBack();
            error_log("Complete receiving failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Trigger Vend inventory sync after receiving
     * 
     * @param int $transferId Transfer ID
     */
    protected function triggerVendSync(int $transferId): void
    {
        // Check if Vend sync service exists
        $vendSyncPath = __DIR__ . '/../services/VendSyncService.php';
        if (!file_exists($vendSyncPath)) {
            return;
        }

        try {
            require_once $vendSyncPath;
            \VendSyncService::syncTransfer($transferId);
        } catch (\Exception $e) {
            error_log("Vend sync failed for transfer {$transferId}: " . $e->getMessage());
        }
    }

    /**
     * Get transfers for outlet with filters
     * 
     * @param int $outletId Outlet ID
     * @param array $filters Additional filters
     * @param int $page Page number
     * @param int $perPage Items per page
     * @return array Paginated results
     */
    public function getTransfersForOutlet(int $outletId, array $filters = [], int $page = 1, int $perPage = 25): array
    {
        // Add outlet filter
        $filters['outlet_id'] = $outletId;
        
        return $this->listTransfers($filters, $page, $perPage);
    }

    /**
     * Get transfer statistics for dashboard
     * 
     * @param int|null $outletId Optional outlet filter
     * @return array Statistics
     */
    public function getTransferStats(?int $outletId = null): array
    {
        $db = $this->getDb();

        try {
            $where = $outletId ? "WHERE from_outlet_id = ? OR to_outlet_id = ?" : "";
            $params = $outletId ? [$outletId, $outletId] : [];

            $stmt = $db->prepare("
                SELECT 
                    status,
                    COUNT(*) as count,
                    SUM(total_items) as total_items,
                    SUM(total_weight) as total_weight
                FROM {$this->tableName}
                {$where}
                GROUP BY status
            ");
            $stmt->execute($params);
            
            $stats = [
                'by_status' => [],
                'totals' => [
                    'count' => 0,
                    'items' => 0,
                    'weight' => 0
                ]
            ];

            while ($row = $stmt->fetch()) {
                $stats['by_status'][$row['status']] = [
                    'count' => (int)$row['count'],
                    'items' => (int)$row['total_items'],
                    'weight' => (float)$row['total_weight']
                ];
                
                $stats['totals']['count'] += (int)$row['count'];
                $stats['totals']['items'] += (int)$row['total_items'];
                $stats['totals']['weight'] += (float)$row['total_weight'];
            }

            return $stats;

        } catch (\Exception $e) {
            error_log("Get transfer stats failed: " . $e->getMessage());
            return ['by_status' => [], 'totals' => ['count' => 0, 'items' => 0, 'weight' => 0]];
        }
    }

    /**
     * Get outlet metadata from vend_outlets table
     * 
     * @param string $outletUuid Outlet UUID
     * @return array|null Outlet data or null if not found
     */
    public function getOutletMeta(string $outletUuid): ?array
    {
        $outletUuid = trim($outletUuid);
        if ($outletUuid === '') {
            return null;
        }

        if (isset($this->outletCache[$outletUuid])) {
            return $this->outletCache[$outletUuid];
        }

        try {
            $stmt = $this->getDb()->prepare('
                SELECT *
                FROM vend_outlets
                WHERE outlet_id = ? OR website_outlet_id = ? OR legacy_outlet_id = ?
                LIMIT 1
            ');
            $stmt->execute([$outletUuid, $outletUuid, $outletUuid]);
            $outlet = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
        } catch (\Throwable $e) {
            error_log('StockTransferService::getOutletMeta error: ' . $e->getMessage());
            $outlet = null;
        }

        $this->outletCache[$outletUuid] = $outlet;
        return $outlet;
    }

    /**
     * Get source stock levels for product IDs at a specific outlet
     * Mirrors legacy TransfersService::getSourceStockLevels
     * 
     * @param array<int|string> $productIds Product IDs
     * @param string $outletUuid Outlet UUID
     * @return array<string,int> product_id => qty
     */
    public function getSourceStockLevels(array $productIds, string $outletUuid): array
    {
        $ids = array_values(array_unique(array_map(static fn($id) => (string)$id, $productIds)));
        if (!$ids || $outletUuid === '' || strlen($outletUuid) < 5) {
            return [];
        }

        $meta = $this->resolveInventoryMeta();
        if (!$meta['ready'] || empty($meta['column'])) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $sql = sprintf(
            'SELECT product_id, SUM(`%s`) AS qty
               FROM vend_inventory
              WHERE outlet_id = ? AND product_id IN (%s)
              GROUP BY product_id',
            $meta['column'],
            $placeholders
        );

        $stmt = $this->getDb()->prepare($sql);
        $stmt->bindValue(1, $outletUuid, PDO::PARAM_STR);
        $index = 2;
        foreach ($ids as $pid) {
            $stmt->bindValue($index++, $pid, PDO::PARAM_STR);
        }

        try {
            $stmt->execute();
        } catch (\Throwable $e) {
            error_log('StockTransferService::getSourceStockLevels error: ' . $e->getMessage());
            return [];
        }

        $map = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $pid = (string)($row['product_id'] ?? '');
            if ($pid === '') {
                continue;
            }
            $map[$pid] = (int)($row['qty'] ?? 0);
        }

        return $map;
    }

    /**
     * Resolve vend_inventory quantity column metadata
     * 
     * @return array{ready:bool,column:?string}
     */
    protected function resolveInventoryMeta(): array
    {
        if ($this->inventoryMeta !== null) {
            return $this->inventoryMeta;
        }

        $meta = ['ready' => false, 'column' => null];

        try {
            $cols = $this->getDb()->query('SHOW COLUMNS FROM vend_inventory')->fetchAll(PDO::FETCH_ASSOC);
            $colNames = [];
            foreach ($cols as $colRow) {
                $field = strtolower((string)($colRow['Field'] ?? ''));
                if ($field !== '') {
                    $colNames[$field] = (string)($colRow['Field'] ?? '');
                }
            }

            foreach (['inventory_level','quantity','qty','stock_qty','on_hand','onhand','level'] as $candidate) {
                if (isset($colNames[$candidate])) {
                    $meta['column'] = $colNames[$candidate];
                    $meta['ready'] = true;
                    break;
                }
            }
        } catch (\Throwable $e) {
            error_log('StockTransferService::resolveInventoryMeta error: ' . $e->getMessage());
        }

        $this->inventoryMeta = $meta;
        return $meta;
    }
}
