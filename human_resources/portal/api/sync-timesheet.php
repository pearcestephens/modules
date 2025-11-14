<?php
/**
 * API: Sync Timesheet Amendment to Deputy
 *
 * Handles syncing individual or bulk timesheet amendments to Deputy
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../includes/DeputyIntegration.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$deputy = new DeputyIntegration($pdo);

try {
    // Single timesheet sync
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];

        // Get amendment details
        $stmt = $pdo->prepare("
            SELECT * FROM payroll_timesheet_amendments
            WHERE id = ? AND status = 'approved'
        ");
        $stmt->execute([$id]);
        $amendment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$amendment) {
            throw new Exception('Amendment not found or not approved');
        }

        // Sync to Deputy
        $result = $deputy->syncTimesheetAmendment($amendment);

        if ($result['success']) {
            // Log sync
            $stmt = $pdo->prepare("
                INSERT INTO integration_sync_log
                (integration_name, sync_type, item_type, item_id, external_id, status, details, created_at)
                VALUES ('deputy', 'timesheet', 'timesheet', ?, ?, 'success', ?, NOW())
            ");
            $stmt->execute([
                $id,
                $result['deputy_timesheet_id'] ?? null,
                json_encode($result)
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Timesheet synced to Deputy successfully',
                'deputy_timesheet_id' => $result['deputy_timesheet_id'] ?? null
            ]);
        } else {
            throw new Exception($result['error'] ?? 'Unknown sync error');
        }
    }
    // Bulk sync for staff member
    elseif (isset($_GET['staff_id']) && isset($_GET['sync_all'])) {
        $staffId = (int)$_GET['staff_id'];

        // Get all approved, unsynced amendments for this staff
        $stmt = $pdo->prepare("
            SELECT ta.* FROM payroll_timesheet_amendments ta
            LEFT JOIN integration_sync_log isl ON isl.item_type = 'timesheet'
                AND isl.item_id = ta.id
                AND isl.integration_name = 'deputy'
                AND isl.status = 'success'
            WHERE ta.staff_id = ?
            AND ta.status = 'approved'
            AND isl.id IS NULL
        ");
        $stmt->execute([$staffId]);
        $amendments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $synced = 0;
        $errors = [];

        foreach ($amendments as $amendment) {
            try {
                $result = $deputy->syncTimesheetAmendment($amendment);

                if ($result['success']) {
                    // Log sync
                    $stmt = $pdo->prepare("
                        INSERT INTO integration_sync_log
                        (integration_name, sync_type, item_type, item_id, external_id, status, details, created_at)
                        VALUES ('deputy', 'timesheet', 'timesheet', ?, ?, 'success', ?, NOW())
                    ");
                    $stmt->execute([
                        $amendment['id'],
                        $result['deputy_timesheet_id'] ?? null,
                        json_encode($result)
                    ]);
                    $synced++;
                } else {
                    $errors[] = "Amendment {$amendment['id']}: " . ($result['error'] ?? 'Unknown error');
                }
            } catch (Exception $e) {
                $errors[] = "Amendment {$amendment['id']}: " . $e->getMessage();
            }
        }

        echo json_encode([
            'success' => true,
            'synced' => $synced,
            'total' => count($amendments),
            'errors' => $errors
        ]);
    }
    else {
        throw new Exception('Missing required parameters: id or staff_id with sync_all');
    }

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
