<?php
/**
 * Bulk Dismiss AI Insight Recommendations
 *
 * Dismisses multiple AI insights in a single operation and logs via PurchaseOrderLogger.
 *
 * @package CIS\Consignments\API\PurchaseOrders
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/../../bootstrap.php';

// Fail-safe: Load PurchaseOrderLogger if available
$loggerAvailable = false;
if (file_exists(__DIR__ . '/../../lib/PurchaseOrderLogger.php')) {
    require_once __DIR__ . '/../../lib/PurchaseOrderLogger.php';
    $loggerAvailable = class_exists('\\CIS\\Consignments\\Lib\\PurchaseOrderLogger');
}

// Content-Type
header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Not authenticated'
    ]);
    exit;
}

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'Invalid JSON'
    ]);
    exit;
}

// Validate required fields
$insightIds = $data['insight_ids'] ?? [];
$poId = (int)($data['po_id'] ?? 0);
$dismissReason = $data['reason'] ?? 'Bulk dismissed';

if (empty($insightIds) || !is_array($insightIds)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'insight_ids array is required'
    ]);
    exit;
}

try {
    $db = get_db();
    $db->beginTransaction();

    $dismissedCount = 0;
    $errors = [];

    foreach ($insightIds as $insightId) {
        $insightId = (int)$insightId;
        if ($insightId <= 0) continue;

        try {
            // Get insight details
            $stmt = $db->prepare("
                SELECT
                    id,
                    po_id,
                    type,
                    category,
                    suggested_value,
                    confidence_score
                FROM consignment_ai_insights
                WHERE id = ? AND status = 'PENDING'
            ");
            $stmt->execute([$insightId]);
            $insight = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$insight) {
                $errors[] = "Insight {$insightId} not found or already processed";
                continue;
            }

            // Update to DISMISSED
            $stmt = $db->prepare("
                UPDATE consignment_ai_insights
                SET
                    status = 'DISMISSED',
                    reviewed_by = ?,
                    reviewed_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $insightId]);

            $dismissedCount++;

        } catch (Exception $e) {
            $errors[] = "Error processing insight {$insightId}: " . $e->getMessage();
            error_log("Bulk dismiss error for insight {$insightId}: " . $e->getMessage());
        }
    }

    $db->commit();

    // Log bulk dismissal via PurchaseOrderLogger
    if ($loggerAvailable && $dismissedCount > 0) {
            try {
                \CIS\Consignments\Lib\PurchaseOrderLogger::aiBulkRecommendationsProcessed(
                    $insightIds,
                    'dismiss',
                    $acceptedCount ?? 0,
                    $dismissedCount
                );
            } catch (Exception $e) {
                error_log("PurchaseOrderLogger failed in bulk-dismiss-ai-insights: " . $e->getMessage());
            }
    }

    // Success response
    echo json_encode([
        'success' => true,
        'message' => "Dismissed {$dismissedCount} insights",
        'dismissed_count' => $dismissedCount,
        'errors' => $errors
    ]);

} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Database error in bulk-dismiss-ai-insights: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error in bulk-dismiss-ai-insights: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error'
    ]);
}
