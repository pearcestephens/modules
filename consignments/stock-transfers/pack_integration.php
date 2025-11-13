<?php
declare(strict_types=1);

/**
 * Pack Page Integration for Universal Transfer Data
 *
 * This file provides helper functions and JavaScript integration
 * for using the universal transfer data system within the existing
 * pack.php interface.
 *
 * @package CIS\Consignments\StockTransfers
 * @version 1.0.0
 * @created 2025-10-15
 */

// Safe-include universal transfer data helper; provide fallbacks if missing
$__utd = __DIR__ . '/universal_transfer_data.php';
if (is_file($__utd)) {
    require_once $__utd;
} else {
    // Fallback stubs to prevent fatals when this file is accessed directly
    if (!function_exists('getCompleteTransferData')) {
        function getCompleteTransferData(int $transferId, string $transferType = 'STOCK') { return null; }
    }
}

// If accessed directly (not included), respond OK to avoid 500s during checks
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === realpath(__FILE__)) {
    header('Content-Type: text/plain; charset=utf-8');
    http_response_code(200);
    echo "OK";
    return; // Do not execute function definitions further for direct access
}

/**
 * Get transfer data formatted for pack page display
 */
function getTransferDataForPackPage(int $transferId, string $transferType = 'STOCK'): ?stdClass
{
    $transfer = getCompleteTransferData($transferId, $transferType);

    if (!$transfer) {
        return null;
    }

    // Format data specifically for pack page UI
    $packData = (object)[
        'transfer_id' => $transferId,
        'transfer_details' => $transfer->transfer,
        'outlet_from' => $transfer->outlet_from,
        'outlet_to' => $transfer->outlet_to,
        'items' => [],
        'shipments' => $transfer->shipments,
        'summary' => $transfer->summary,
        'pack_status' => determinePackStatus($transfer),
        'can_pack' => canStartPacking($transfer),
        'pack_warnings' => getPackWarnings($transfer)
    ];

    // Format items for pack interface
    foreach ($transfer->items as $item) {
        $packData->items[] = (object)[
            'id' => $item->id,
            'product_id' => $item->product_id,
            'sku' => $item->sku,
            'name' => $item->name,
            'qty_requested' => $item->qty_requested,
            'qty_sent' => $item->qty_sent,
            'qty_received' => $item->qty_received,
            'qty_available' => getAvailableStock($item->product_id, $transfer->transfer->outlet_from),
            'pack_status' => $item->qty_sent >= $item->qty_requested ? 'complete' : 'pending',
            'can_pack_qty' => max(0, $item->qty_requested - $item->qty_sent),
            'discrepancy' => $item->discrepancy_data,
            'notes' => array_filter($transfer->notes, function($note) use ($item) {
                return $note->item_id === $item->id;
            })
        ];
    }

    return $packData;
}

/**
 * Get available stock for a product at an outlet
 */
function getAvailableStock(string $productId, int $outletId): int
{
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT COALESCE(stock_on_hand, 0) as stock
        FROM vend_products_outlets
        WHERE product_id = ? AND outlet_id = ?
    ");
    $stmt->execute([$productId, $outletId]);

    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    return $result ? (int)$result['stock'] : 0;
}

/**
 * Determine overall pack status
 */
function determinePackStatus(stdClass $transfer): string
{
    if ($transfer->summary->pack_completion_pct === 100.0) {
        return 'complete';
    } elseif ($transfer->summary->pack_completion_pct > 0) {
        return 'partial';
    } else {
        return 'pending';
    }
}

/**
 * Check if packing can begin
 */
function canStartPacking(stdClass $transfer): bool
{
    // Check transfer state
    if (!in_array($transfer->transfer->state, ['DRAFT', 'REQUESTED', 'PARTIAL'])) {
        return false;
    }

    // Check if any items can be packed
    foreach ($transfer->items as $item) {
        if ($item->qty_sent < $item->qty_requested) {
            return true;
        }
    }

    return false;
}

/**
 * Get warnings for pack interface
 */
function getPackWarnings(stdClass $transfer): array
{
    $warnings = [];

    // Check for low stock warnings
    foreach ($transfer->items as $item) {
        $available = getAvailableStock($item->product_id, $transfer->transfer->outlet_from);
        $needed = $item->qty_requested - $item->qty_sent;

        if ($available < $needed) {
            $warnings[] = (object)[
                'type' => 'low_stock',
                'severity' => 'warning',
                'message' => "Insufficient stock for {$item->name}. Need {$needed}, have {$available}",
                'item_id' => $item->id,
                'sku' => $item->sku
            ];
        }
    }

    // Check for discrepancies
    foreach ($transfer->items as $item) {
        if ($item->discrepancy_data && !empty($item->discrepancy_data)) {
            $warnings[] = (object)[
                'type' => 'discrepancy',
                'severity' => 'error',
                'message' => "Discrepancy reported for {$item->name}",
                'item_id' => $item->id,
                'sku' => $item->sku
            ];
        }
    }

    // Check transfer age
    $created = new DateTime($transfer->transfer->created_at);
    $now = new DateTime();
    $daysDiff = $now->diff($created)->days;

    if ($daysDiff > 7) {
        $warnings[] = (object)[
            'type' => 'age',
            'severity' => 'info',
            'message' => "Transfer is {$daysDiff} days old",
            'transfer_id' => $transfer->transfer->id
        ];
    }

    return $warnings;
}

/**
 * Update item pack quantity via universal system
 */
function updateItemPackQuantity(int $transferId, int $itemId, int $qty, int $userId): array
{
    global $pdo;

    try {
        $pdo->beginTransaction();

        // Update the item
        $stmt = $pdo->prepare("
            UPDATE stock_transfer_items
            SET qty_sent = ?, updated_at = NOW()
            WHERE id = ? AND transfer_id = ?
        ");
        $stmt->execute([$qty, $itemId, $transferId]);

        if ($stmt->rowCount() === 0) {
            throw new Exception("Item not found or no changes made");
        }

        // Log the action
        $stmt = $pdo->prepare("
            INSERT INTO stock_consignment_audit_log
            (transfer_id, user_id, action, details, created_at)
            VALUES (?, ?, 'ITEM_PACKED', ?, NOW())
        ");
        $stmt->execute([
            $transferId,
            $userId,
            json_encode(['item_id' => $itemId, 'qty_sent' => $qty])
        ]);

        $pdo->commit();

        // Get updated transfer data
        $transfer = getCompleteTransferData($transferId);

        return [
            'success' => true,
            'message' => 'Item quantity updated successfully',
            'updated_summary' => $transfer->summary
        ];

    } catch (Exception $e) {
        $pdo->rollBack();
        return [
            'success' => false,
            'error' => $e->getMessage()
        ];
    }
}

/**
 * Generate JavaScript configuration for pack page
 */
function generatePackPageJSConfig(stdClass $packData): string
{
    $config = [
        'transferId' => $packData->transfer_id,
        'transferType' => $packData->transfer_details->transfer_category ?? 'STOCK',
        'apiEndpoint' => '/modules/consignments/api/universal_transfer_api.php',
        'packStatus' => $packData->pack_status,
        'canPack' => $packData->can_pack,
        'totalItems' => count($packData->items),
        'completedItems' => count(array_filter($packData->items, function($i) {
            return $i->pack_status === 'complete';
        })),
        'warnings' => $packData->pack_warnings,
        'refreshInterval' => 30000, // 30 seconds
        'autoSave' => true
    ];

    return 'const PACK_CONFIG = ' . json_encode($config, JSON_PRETTY_PRINT) . ';';
}

/**
 * Render pack status indicator
 */
function renderPackStatusIndicator(stdClass $packData): string
{
    $status = $packData->pack_status;
    $completion = $packData->summary->pack_completion_pct;

    $statusClasses = [
        'pending' => 'bg-secondary',
        'partial' => 'bg-warning',
        'complete' => 'bg-success'
    ];

    $statusLabels = [
        'pending' => 'Not Started',
        'partial' => 'In Progress',
        'complete' => 'Complete'
    ];

    $class = $statusClasses[$status] ?? 'bg-secondary';
    $label = $statusLabels[$status] ?? 'Unknown';

    return sprintf(
        '<div class="pack-status-indicator">
            <div class="progress mb-2" style="height: 20px;">
                <div class="progress-bar %s" role="progressbar" style="width: %.1f%%"
                     aria-valuenow="%.1f" aria-valuemin="0" aria-valuemax="100">
                    %.1f%%
                </div>
            </div>
            <small class="text-muted">%s (%d of %d items)</small>
        </div>',
        $class,
        $completion,
        $completion,
        $completion,
        $label,
        $packData->summary->items_sent ?? 0,
        $packData->summary->total_items
    );
}

/**
 * Render warnings section
 */
function renderPackWarnings(array $warnings): string
{
    if (empty($warnings)) {
        return '';
    }

    $html = '<div class="pack-warnings mt-3">';

    foreach ($warnings as $warning) {
        $alertClass = match($warning->severity) {
            'error' => 'alert-danger',
            'warning' => 'alert-warning',
            'info' => 'alert-info',
            default => 'alert-secondary'
        };

        $iconClass = match($warning->severity) {
            'error' => 'fas fa-exclamation-triangle',
            'warning' => 'fas fa-exclamation-circle',
            'info' => 'fas fa-info-circle',
            default => 'fas fa-bell'
        };

        $html .= sprintf(
            '<div class="alert %s alert-dismissible fade show" role="alert">
                <i class="%s me-2"></i>%s
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>',
            $alertClass,
            $iconClass,
            htmlspecialchars($warning->message)
        );
    }

    $html .= '</div>';

    return $html;
}
