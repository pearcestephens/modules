<?php
/**
 * Stock Transfer Controller
 *
 * Handles all stock transfer operations including:
 * - Creating and managing transfers between outlets
 * - Packing interface with freight integration
 * - Receiving and unpacking
 * - Label generation and tracking
 * - Weight/volume calculations
 *
 * @package CIS\Consignments\Controllers
 * @version 1.0.0
 */

declare(strict_types=1);

namespace CIS\Consignments\Controllers;

use CIS\Modules\Consignments\FreightIntegration;
use PDO;

class StockTransferController extends BaseController
{
    private FreightIntegration $freight;

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->freight = new FreightIntegration($db);
    }

    /**
     * Display list of stock transfers
     */
    public function index(): void
    {
        $this->render('stock-transfers/index', [
            'title' => 'Stock Transfers',
            'transfers' => $this->getTransfers()
        ]);
    }

    /**
     * Show packing interface for a transfer
     */
    public function pack(int $transferId): void
    {
        $transfer = $this->getTransfer($transferId);

        if (!$transfer) {
            $this->error('Transfer not found');
            return;
        }

        // Get freight metrics
        $freightMetrics = null;
        try {
            $freightMetrics = $this->freight->calculateTransferMetrics($transferId);
        } catch (\Exception $e) {
            $this->log("Freight calculation failed: " . $e->getMessage());
        }

        $this->render('stock-transfers/pack', [
            'title' => 'Pack Transfer #' . $transferId,
            'transfer' => $transfer,
            'items' => $this->getTransferItems($transferId),
            'freight' => $freightMetrics
        ]);
    }

    /**
     * Show receiving interface for a transfer
     */
    public function receive(int $transferId): void
    {
        $transfer = $this->getTransfer($transferId);

        if (!$transfer) {
            $this->error('Transfer not found');
            return;
        }

        $this->render('stock-transfers/receive', [
            'title' => 'Receive Transfer #' . $transferId,
            'transfer' => $transfer,
            'items' => $this->getTransferItems($transferId)
        ]);
    }

    /**
     * Get freight quote for a transfer
     */
    public function getFreightQuote(int $transferId): void
    {
        try {
            $rates = $this->freight->getTransferRates($transferId);
            $this->json([
                'success' => true,
                'rates' => $rates
            ]);
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Generate shipping label
     */
    public function createLabel(int $transferId, string $carrier, string $service): void
    {
        try {
            $label = $this->freight->createTransferLabel(
                $transferId,
                $carrier,
                $service,
                $_POST['auto_print'] ?? false
            );

            $this->json([
                'success' => true,
                'tracking_number' => $label['tracking_number'],
                'label_url' => $label['label_url'],
                'cost' => $label['cost']
            ]);
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Track shipment
     */
    public function trackShipment(int $transferId): void
    {
        try {
            $tracking = $this->freight->trackTransferShipment($transferId);
            $this->json([
                'success' => true,
                'tracking' => $tracking
            ]);
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Get all transfers
     */
    private function getTransfers(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                t.*,
                o_from.name AS from_outlet_name,
                o_to.name AS to_outlet_name
            FROM vend_consignments t
            LEFT JOIN outlets o_from ON t.source_outlet_id = o_from.id
            LEFT JOIN outlets o_to ON t.destination_outlet_id = o_to.id
            WHERE t.transfer_category = 'STOCK_TRANSFER'
                AND t.deleted_at IS NULL
            ORDER BY t.created_at DESC
            LIMIT 100
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get single transfer
     */
    private function getTransfer(int $transferId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                t.*,
                o_from.name AS from_outlet_name,
                o_to.name AS to_outlet_name,
                o_from.address AS from_address,
                o_to.address AS to_address
            FROM vend_consignments t
            LEFT JOIN outlets o_from ON t.source_outlet_id = o_from.id
            LEFT JOIN outlets o_to ON t.destination_outlet_id = o_to.id
            WHERE t.id = ?
                AND t.deleted_at IS NULL
        ");
        $stmt->execute([$transferId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get transfer items
     */
    private function getTransferItems(int $transferId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                li.*,
                p.name AS product_name,
                p.sku,
                pd.weight AS product_weight,
                pd.length AS product_length,
                pd.width AS product_width,
                pd.height AS product_height
            FROM vend_consignment_line_items li
            LEFT JOIN vend_products p ON li.product_id = p.id
            LEFT JOIN product_dimensions pd ON p.id = pd.product_id
            WHERE li.transfer_id = ?
                AND li.deleted_at IS NULL
            ORDER BY p.name ASC
        ");
        $stmt->execute([$transferId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
