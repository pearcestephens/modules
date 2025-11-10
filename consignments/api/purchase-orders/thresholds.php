<?php
/**
 * Approval Thresholds API
 *
 * CRUD operations for approval threshold configuration.
 * Allows retrieval and management of default and outlet-specific thresholds.
 *
 * @endpoint GET/POST/PUT /api/purchase-orders/thresholds.php
 * @authentication Required (admin only for modifications)
 * @package CIS\Consignments\API
 * @since 1.0.0
 */

declare(strict_types=1);

// Bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/bootstrap.php';

use Consignments\Lib\Services\ApprovalService;

// Check authentication
if (!isset($_SESSION['userID'])) {
    http_response_code(401);
    echo json_encode([
        'success' => false,
        'error' => 'Unauthorized. Please log in.'
    ]);
    exit;
}

$db = getDB();
$approvalService = new ApprovalService($db);
$method = $_SERVER['REQUEST_METHOD'];

try {
    switch ($method) {
        case 'GET':
            // Get thresholds (public - any authenticated user)
            $outletId = $_GET['outlet_id'] ?? null;

            if ($outletId) {
                // Get outlet-specific thresholds
                $thresholds = $approvalService->getThresholds($outletId);
            } else {
                // Get default thresholds
                $thresholds = $approvalService->getThresholds();
            }

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'data' => [
                    'thresholds' => $thresholds,
                    'outlet_id' => $outletId
                ]
            ]);
            break;

        case 'POST':
            // Create/update default thresholds (admin only)
            if (($_SESSION['user_role'] ?? '') !== 'admin') {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => 'Admin privileges required.'
                ]);
                exit;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid JSON payload.'
                ]);
                exit;
            }

            $thresholds = $input['thresholds'] ?? null;

            if (!is_array($thresholds)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'thresholds must be an array.'
                ]);
                exit;
            }

            // Validate threshold structure
            for ($tier = 1; $tier <= 5; $tier++) {
                if (!isset($thresholds[$tier])) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'error' => "Missing configuration for tier {$tier}."
                    ]);
                    exit;
                }

                $tierData = $thresholds[$tier];
                $required = ['min_amount', 'max_amount', 'required_approvers', 'roles'];

                foreach ($required as $field) {
                    if (!isset($tierData[$field])) {
                        http_response_code(400);
                        echo json_encode([
                            'success' => false,
                            'error' => "Missing {$field} for tier {$tier}."
                        ]);
                        exit;
                    }
                }
            }

            // Save to database
            $configSQL = "
                INSERT INTO system_config (config_key, config_value, updated_by, updated_at)
                VALUES ('approval_thresholds', ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    config_value = VALUES(config_value),
                    updated_by = VALUES(updated_by),
                    updated_at = NOW()
            ";
            $stmt = $db->prepare($configSQL);
            $stmt->execute([
                json_encode($thresholds),
                $_SESSION['userID']
            ]);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Default approval thresholds saved successfully.',
                'data' => ['thresholds' => $thresholds]
            ]);
            break;

        case 'PUT':
            // Update outlet-specific override (admin only)
            if (($_SESSION['user_role'] ?? '') !== 'admin') {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => 'Admin privileges required.'
                ]);
                exit;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Invalid JSON payload.'
                ]);
                exit;
            }

            $outletId = $input['outlet_id'] ?? null;
            $thresholds = $input['thresholds'] ?? null;

            if (!$outletId) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'outlet_id is required.'
                ]);
                exit;
            }

            if (!is_array($thresholds)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'thresholds must be an array.'
                ]);
                exit;
            }

            // Validate threshold structure (same as POST)
            for ($tier = 1; $tier <= 5; $tier++) {
                if (!isset($thresholds[$tier])) {
                    http_response_code(400);
                    echo json_encode([
                        'success' => false,
                        'error' => "Missing configuration for tier {$tier}."
                    ]);
                    exit;
                }
            }

            // Save outlet override
            $overrideSQL = "
                INSERT INTO approval_threshold_overrides (outlet_id, thresholds, created_by, created_at, updated_by, updated_at)
                VALUES (?, ?, ?, NOW(), ?, NOW())
                ON DUPLICATE KEY UPDATE
                    thresholds = VALUES(thresholds),
                    updated_by = VALUES(updated_by),
                    updated_at = NOW()
            ";
            $stmt = $db->prepare($overrideSQL);
            $stmt->execute([
                $outletId,
                json_encode($thresholds),
                $_SESSION['userID'],
                $_SESSION['userID']
            ]);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Outlet-specific override saved successfully.',
                'data' => [
                    'outlet_id' => $outletId,
                    'thresholds' => $thresholds
                ]
            ]);
            break;

        case 'DELETE':
            // Delete outlet override (admin only)
            if (($_SESSION['user_role'] ?? '') !== 'admin') {
                http_response_code(403);
                echo json_encode([
                    'success' => false,
                    'error' => 'Admin privileges required.'
                ]);
                exit;
            }

            $outletId = $_GET['outlet_id'] ?? null;

            if (!$outletId) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'outlet_id is required.'
                ]);
                exit;
            }

            $deleteSQL = "DELETE FROM approval_threshold_overrides WHERE outlet_id = ?";
            $stmt = $db->prepare($deleteSQL);
            $stmt->execute([$outletId]);

            http_response_code(200);
            echo json_encode([
                'success' => true,
                'message' => 'Outlet override deleted successfully.',
                'data' => ['outlet_id' => $outletId]
            ]);
            break;

        default:
            http_response_code(405);
            echo json_encode([
                'success' => false,
                'error' => 'Method not allowed. Use GET, POST, PUT, or DELETE.'
            ]);
            break;
    }

} catch (Exception $e) {
    error_log("Thresholds API error: " . $e->getMessage());

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error.',
        'message' => $e->getMessage()
    ]);
}
