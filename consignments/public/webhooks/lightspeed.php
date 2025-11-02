<?php
/**
 * Lightspeed Webhook Endpoint
 *
 * Public endpoint for receiving Lightspeed webhook events.
 * Returns 202 Accepted immediately (async processing via queue).
 *
 * Security:
 * - HMAC-SHA256 signature verification
 * - Replay attack protection
 * - Rate limiting (100 requests/minute per IP)
 * - No sensitive data in logs
 *
 * @package Consignments
 */

declare(strict_types=1);

// Prevent direct access check (this IS a public endpoint)
if (php_sapi_name() === 'cli') {
    die("This script must be run via web server\n");
}

require_once __DIR__ . '/../../bootstrap.php';

use Consignments\Infra\Webhooks\LightspeedWebhookHandler;
use Consignments\Infra\Webhooks\WebhookException;

// ============================================================================
// Rate Limiting
// ============================================================================

function checkRateLimit(PDO $pdo, string $ip): void
{
    $stmt = $pdo->prepare("
        SELECT COUNT(*) as request_count
        FROM webhook_events
        WHERE source_ip = ? AND received_at > DATE_SUB(NOW(), INTERVAL 1 MINUTE)
    ");
    $stmt->execute([$ip]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);

    $requestCount = (int)($result['request_count'] ?? 0);

    if ($requestCount >= 100) {
        http_response_code(429);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Rate limit exceeded (100 requests/minute)',
            'retry_after' => 60
        ]);
        exit;
    }
}

// ============================================================================
// Main Handler
// ============================================================================

try {
    // Only accept POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        header('Allow: POST');
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Method not allowed. Use POST.'
        ]);
        exit;
    }

    // Load dependencies
    $pdo = require __DIR__ . '/../../config/database.php';
    $logger = require __DIR__ . '/../../config/logger.php';

    // Get client IP (check for proxy headers)
    $clientIp = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    if (strpos($clientIp, ',') !== false) {
        $clientIp = trim(explode(',', $clientIp)[0]); // First IP in chain
    }

    // Rate limiting
    checkRateLimit($pdo, $clientIp);

    // Get raw request body (needed for HMAC verification)
    $rawPayload = file_get_contents('php://input');

    if (empty($rawPayload)) {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode([
            'success' => false,
            'error' => 'Empty request body'
        ]);
        exit;
    }

    // Get headers (normalize keys)
    $headers = [];
    foreach ($_SERVER as $key => $value) {
        if (strpos($key, 'HTTP_') === 0) {
            $headerName = str_replace(' ', '-', ucwords(strtolower(str_replace('_', ' ', substr($key, 5)))));
            $headers[$headerName] = $value;
        }
    }

    // Handle webhook
    $handler = new LightspeedWebhookHandler($pdo, $logger);
    $result = $handler->handle($rawPayload, $headers);

    // Log source IP in webhook_events
    if ($result['success'] && isset($result['job_id'])) {
        $stmt = $pdo->prepare("UPDATE webhook_events SET source_ip = ? WHERE id = (SELECT webhook_event_id FROM queue_jobs WHERE id = ? LIMIT 1)");
        $stmt->execute([$clientIp, $result['job_id']]);
    }

    // Return 202 Accepted (async processing)
    if ($result['success']) {
        http_response_code(202);
    } else {
        // Determine error code from exception
        $errorCode = 400; // Default bad request

        if (isset($result['error'])) {
            if (strpos($result['error'], 'signature') !== false) {
                $errorCode = 401; // Unauthorized
            } elseif (strpos($result['error'], 'Duplicate') !== false) {
                $errorCode = 409; // Conflict
            }
        }

        http_response_code($errorCode);
    }

    header('Content-Type: application/json');
    echo json_encode($result, JSON_PRETTY_PRINT);

} catch (\Throwable $e) {
    // Log error but don't expose details
    error_log("Webhook endpoint error: " . $e->getMessage());

    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error',
        'request_id' => isset($result['request_id']) ? $result['request_id'] : null
    ]);
}
