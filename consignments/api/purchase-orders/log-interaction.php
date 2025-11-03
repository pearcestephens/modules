<?php
declare(strict_types=1);

/**
 * Endpoint to accept batched UI interaction events from client-side instrumentation
 * POST body: { events: [ { type: 'modal_opened', page: 'receive', po_id: 123, ... }, ... ] }
 */

// Capture raw input BEFORE bootstrap (CISLogger or other services may read php://input)
$__raw_input = file_get_contents('php://input');

require_once __DIR__ . '/../../bootstrap.php';

// Fail-safe include for logger
if (file_exists(__DIR__ . '/../../lib/PurchaseOrderLogger.php')) {
    require_once __DIR__ . '/../../lib/PurchaseOrderLogger.php';
}

use CIS\Consignments\Lib\PurchaseOrderLogger;

header('Content-Type: application/json');

// Session is initialized by base bootstrap; only start if not already active
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

// Simple rate-limit: max 120 events per minute per session
if (!isset($_SESSION['log_interaction_count'])) {
    $_SESSION['log_interaction_count'] = 0;
    $_SESSION['log_interaction_reset'] = time() + 60;
}
if (time() > ($_SESSION['log_interaction_reset'] ?? 0)) {
    $_SESSION['log_interaction_count'] = 0;
    $_SESSION['log_interaction_reset'] = time() + 60;
}

if ($_SESSION['log_interaction_count'] > 120) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'Rate limit exceeded']);
    exit;
}

try {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'METHOD_NOT_ALLOWED',
                'message' => 'Only POST is allowed'
            ]
        ]);
        exit;
    }

    $raw = $__raw_input;
    $payload = is_string($raw) && $raw !== '' ? json_decode($raw, true) : null;
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($payload)) {
        // Fallback to form-encoded POST if available
        if (!empty($_POST)) {
            $payload = $_POST;
        }
    }
    if (!is_array($payload)) {
        error_log('[log-interaction] Invalid JSON payload or empty body');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => ['code' => 'INVALID_JSON']]);
        exit;
    }

    // Accept either {events:[...]} or a single event object
    $events = [];
    if (isset($payload['events']) && is_array($payload['events'])) {
        $events = $payload['events'];
    } elseif (isset($payload['type'])) {
        // Single event posted directly
        $events = [$payload];
    }

    PurchaseOrderLogger::init();

    $processed = 0;
    foreach ($events as $ev) {
        $type = $ev['type'] ?? null;
        switch ($type) {
            case 'modal_opened':
                PurchaseOrderLogger::modalOpened($ev['modal_name'] ?? 'unknown', $ev['page'] ?? 'unknown', isset($ev['po_id']) ? (int)$ev['po_id'] : null);
                $processed++;
                break;
            case 'modal_closed':
                PurchaseOrderLogger::modalClosed($ev['modal_name'] ?? 'unknown', (float)($ev['time_spent_seconds'] ?? 0), (bool)($ev['action_taken'] ?? false), isset($ev['po_id']) ? (int)$ev['po_id'] : null);
                $processed++;
                break;
            case 'button_clicked':
                PurchaseOrderLogger::buttonClicked($ev['button_id'] ?? 'unknown', $ev['page'] ?? 'unknown', isset($ev['po_id']) ? (int)$ev['po_id'] : null);
                $processed++;
                break;
            case 'field_validation_error':
                PurchaseOrderLogger::validationError($ev['field'] ?? 'unknown', $ev['message'] ?? 'validation error', $ev['value'] ?? null, isset($ev['po_id']) ? (int)$ev['po_id'] : null);
                $processed++;
                break;
            case 'suspicious_value':
                if (isset($ev['po_id'])) {
                    PurchaseOrderLogger::fraudSuspiciousValue((int)$ev['po_id'], $ev['field'] ?? 'unknown', $ev['entered_value'] ?? null, $ev['expected_value'] ?? null, $ev['pattern'] ?? null);
                }
                $processed++;
                break;
            case 'rapid_keyboard':
                PurchaseOrderLogger::securityRapidKeyboardEntry((int)($ev['po_id'] ?? 0), $ev['field'] ?? 'unknown', (float)($ev['entries_per_second'] ?? 0), (int)($ev['total_entries'] ?? 0));
                $processed++;
                break;
            case 'ai_recommendation_accepted':
                PurchaseOrderLogger::aiRecommendationAccepted(
                    (int)($ev['insight_id'] ?? 0),
                    (int)($ev['po_id'] ?? 0),
                    $ev['recommendation_type'] ?? 'unknown',
                    isset($ev['estimated_savings']) ? (float)$ev['estimated_savings'] : null,
                    isset($ev['review_time_seconds']) ? (float)$ev['review_time_seconds'] : null
                );
                $processed++;
                break;
            case 'ai_recommendation_dismissed':
                PurchaseOrderLogger::aiRecommendationDismissed(
                    (int)($ev['insight_id'] ?? 0),
                    (int)($ev['po_id'] ?? 0),
                    $ev['recommendation_type'] ?? 'unknown',
                    $ev['reason'] ?? null,
                    isset($ev['review_time_seconds']) ? (float)$ev['review_time_seconds'] : null
                );
                $processed++;
                break;
            case 'ai_bulk_accept':
                PurchaseOrderLogger::aiBulkRecommendationsProcessed($ev['insight_ids'] ?? [], 'accept', count($ev['insight_ids'] ?? []), 0);
                $processed++;
                break;
            case 'ai_bulk_dismiss':
                PurchaseOrderLogger::aiBulkRecommendationsProcessed($ev['insight_ids'] ?? [], 'dismiss', 0, count($ev['insight_ids'] ?? []));
                $processed++;
                break;
            case 'devtools_detected':
                PurchaseOrderLogger::securityDevToolsDetected(isset($ev['po_id']) ? (int)$ev['po_id'] : null, $ev['page'] ?? 'unknown');
                $processed++;
                break;
            case 'focus_loss':
                PurchaseOrderLogger::securityTabSwitchDuringOperation(
                    (int)($ev['po_id'] ?? 0),
                    $ev['page'] ?? 'unknown',
                    0 // time away not tracked yet
                );
                $processed++;
                break;
            default:
                // Unknown event type — store as generic client event (never throws)
                PurchaseOrderLogger::clientEvent(isset($ev['po_id']) ? (int)$ev['po_id'] : null, $ev);
                $processed++;
                break;
        }
        $_SESSION['log_interaction_count']++;
    }

    echo json_encode(['success' => true, 'processed' => $processed]);

} catch (Exception $e) {
    error_log('[log-interaction] ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'Internal server error'
        ]
    ]);
}
