<?php
/**
 * Bank Transactions API - Settings
 *
 * Handles module settings configuration
 *
 * @package BankTransactions\API
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

try {
    // Get method
    $method = $_SERVER['REQUEST_METHOD'];
    $input = json_decode(file_get_contents('php://input'), true) ?? $_REQUEST;

    // Validate CSRF token for POST/PUT
    if ($method !== 'GET' && (!isset($input['csrf_token']) || $input['csrf_token'] !== ($_SESSION['csrf_token'] ?? ''))) {
        http_response_code(403);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'CSRF_VALIDATION_FAILED',
                'message' => 'Invalid CSRF token'
            ]
        ]);
        exit;
    }

    global $con;

    // GET - Retrieve current settings
    if ($method === 'GET') {
        $stmt = $con->prepare("SELECT setting_key, setting_value FROM module_settings WHERE module = 'bank-transactions'");
        $stmt->execute();
        $result = $stmt->get_result();

        $settings = [];
        while ($row = $result->fetch_assoc()) {
            $key = $row['setting_key'];
            $value = $row['setting_value'];

            // Decode JSON values if applicable
            if (strpos($value, '{') === 0 || strpos($value, '[') === 0) {
                $value = json_decode($value, true);
            }

            $settings[$key] = $value;
        }

        echo json_encode([
            'success' => true,
            'data' => [
                'auto_match_enabled' => (bool)($settings['auto_match_enabled'] ?? true),
                'auto_match_threshold' => (int)($settings['auto_match_threshold'] ?? 85),
                'confidence_scorer_type' => $settings['confidence_scorer_type'] ?? 'fuzzy',
                'max_batch_size' => (int)($settings['max_batch_size'] ?? 100),
                'enable_audit_logging' => (bool)($settings['enable_audit_logging'] ?? true),
                'notification_email' => $settings['notification_email'] ?? '',
                'settings' => $settings,
            ],
            'timestamp' => date('Y-m-d H:i:s'),
        ], JSON_PRETTY_PRINT);
    }

    // POST - Update settings
    elseif ($method === 'POST') {
        $autoMatchEnabled = isset($input['auto_match_enabled']) ? (bool)$input['auto_match_enabled'] : null;
        $autoMatchThreshold = isset($input['auto_match_threshold']) ? (int)$input['auto_match_threshold'] : null;
        $confidenceScorerType = $input['confidence_scorer_type'] ?? null;
        $maxBatchSize = isset($input['max_batch_size']) ? (int)$input['max_batch_size'] : null;
        $enableAuditLogging = isset($input['enable_audit_logging']) ? (bool)$input['enable_audit_logging'] : null;
        $notificationEmail = $input['notification_email'] ?? null;

        // Validate threshold
        if ($autoMatchThreshold !== null && ($autoMatchThreshold < 0 || $autoMatchThreshold > 100)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_THRESHOLD',
                    'message' => 'Auto-match threshold must be between 0 and 100'
                ]
            ]);
            exit;
        }

        // Begin transaction
        $con->begin_transaction();

        try {
            $settings = [
                'auto_match_enabled' => $autoMatchEnabled,
                'auto_match_threshold' => $autoMatchThreshold,
                'confidence_scorer_type' => $confidenceScorerType,
                'max_batch_size' => $maxBatchSize,
                'enable_audit_logging' => $enableAuditLogging,
                'notification_email' => $notificationEmail,
            ];

            foreach ($settings as $key => $value) {
                if ($value === null) continue;

                $settingValue = is_bool($value) ? ($value ? '1' : '0') : (string)$value;

                $stmt = $con->prepare(
                    "INSERT INTO module_settings (module, setting_key, setting_value, updated_at)
                     VALUES ('bank-transactions', ?, ?, NOW())
                     ON DUPLICATE KEY UPDATE setting_value = ?, updated_at = NOW()"
                );

                $stmt->bind_param(
                    'sss',
                    $key,
                    $settingValue,
                    $settingValue
                );
                $stmt->execute();
            }

            $con->commit();

            echo json_encode([
                'success' => true,
                'data' => [
                    'message' => 'Settings updated successfully',
                    'settings' => $settings,
                ],
                'timestamp' => date('Y-m-d H:i:s'),
            ], JSON_PRETTY_PRINT);

        } catch (Exception $e) {
            $con->rollback();
            throw $e;
        }
    }

    // DELETE - Reset settings to defaults
    elseif ($method === 'DELETE') {
        $con->begin_transaction();

        try {
            $stmt = $con->prepare("DELETE FROM module_settings WHERE module = 'bank-transactions'");
            $stmt->execute();

            $con->commit();

            echo json_encode([
                'success' => true,
                'data' => [
                    'message' => 'Settings reset to defaults',
                ],
                'timestamp' => date('Y-m-d H:i:s'),
            ], JSON_PRETTY_PRINT);

        } catch (Exception $e) {
            $con->rollback();
            throw $e;
        }
    }

    else {
        http_response_code(405);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'METHOD_NOT_ALLOWED',
                'message' => 'Method not allowed'
            ]
        ]);
    }

} catch (Exception $e) {
    error_log("Settings API Error: " . $e->getMessage());
    error_log($e->getTraceAsString());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'INTERNAL_ERROR',
            'message' => 'Failed to process request'
        ]
    ]);
}
?>
