<?php
/**
 * Bulk Accept AI Insight Recommendations
 *
 * Accepts multiple AI insights in a single operation and logs via PurchaseOrderLogger.
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

    $acceptedCount = 0;
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

            // Update to ACCEPTED
            $stmt = $db->prepare("
                UPDATE consignment_ai_insights
                SET
                    status = 'ACCEPTED',
                    reviewed_by = ?,
                    reviewed_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $insightId]);

            $acceptedCount++;

        } catch (Exception $e) {
            $errors[] = "Error processing insight {$insightId}: " . $e->getMessage();
            error_log("Bulk accept error for insight {$insightId}: " . $e->getMessage());
        }
    }

    $db->commit();

    // Log bulk acceptance via PurchaseOrderLogger
    if ($loggerAvailable && $acceptedCount > 0) {
        try {
            \CIS\Consignments\Lib\PurchaseOrderLogger::aiBulkRecommendationsProcessed(
                $insightIds,
                'accept',
                $acceptedCount,
                count($errors)
            );
        } catch (Exception $e) {
            error_log("PurchaseOrderLogger failed in bulk-accept-ai-insights: " . $e->getMessage());
        }
    }

    // Success response
    echo json_encode([
        'success' => true,
        'message' => "Accepted {$acceptedCount} insights",
        'accepted_count' => $acceptedCount,
        'errors' => $errors
    ]);

} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Database error in bulk-accept-ai-insights: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
} catch (Exception $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Error in bulk-accept-ai-insights: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error'
    ]);
}
