<?php
/**
 * Accept AI Insight Recommendation
 *
 * Updates consignment_ai_insights status to ACCEPTED and logs the action
 * via PurchaseOrderLogger for comprehensive tracking.
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
if (!isset($_SESSION['userID'])) {
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
$insightId = (int)($data['insight_id'] ?? 0);
$poId = (int)($data['po_id'] ?? 0);
$reviewTimeSeconds = (int)($data['review_time_seconds'] ?? 0);

if ($insightId <= 0) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => 'insight_id is required'
    ]);
    exit;
}

try {
    $db = get_db();

    // Get insight details before updating
    $stmt = $db->prepare("
        SELECT
            id,
            po_id,
            type,
            category,
            priority,
            suggested_value,
            confidence_score
        FROM consignment_ai_insights
        WHERE id = ?
    ");
    $stmt->execute([$insightId]);
    $insight = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$insight) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => 'Insight not found'
        ]);
        exit;
    }

    // Update insight status to ACCEPTED
    $stmt = $db->prepare("
        UPDATE consignment_ai_insights
        SET
            status = 'ACCEPTED',
            reviewed_by = ?,
            reviewed_at = NOW(),
            updated_at = NOW()
        WHERE id = ?
    ");
    $stmt->execute([$_SESSION['userID'], $insightId]);

    // Log acceptance via PurchaseOrderLogger
    if ($loggerAvailable) {
        try {
            \CIS\Consignments\Lib\PurchaseOrderLogger::aiRecommendationAccepted(
                $insightId,
                (int)($insight['po_id'] ?? $poId),
                $insight['type'] ?? 'unknown',
                isset($insight['suggested_value']) ? (float)$insight['suggested_value'] : null,
                $reviewTimeSeconds,
                []
            );
        } catch (Exception $e) {
            error_log("PurchaseOrderLogger failed in accept-ai-insight: " . $e->getMessage());
        }
    }

    // Success response
    echo json_encode([
        'success' => true,
        'message' => 'AI insight accepted',
        'insight_id' => $insightId
    ]);

} catch (PDOException $e) {
    error_log("Database error in accept-ai-insight: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Database error'
    ]);
} catch (Exception $e) {
    error_log("Error in accept-ai-insight: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Server error'
    ]);
}
