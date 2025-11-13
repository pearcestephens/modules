<?php
/**
 * API: Sync Payrun Amendment to Xero
 *
 * Handles syncing individual or bulk payrun amendments to Xero
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../includes/XeroIntegration.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$xero = new XeroIntegration($pdo);

try {
    // Single payrun sync
    if (isset($_GET['id'])) {
        $id = (int)$_GET['id'];

        // Get amendment details
        $stmt = $pdo->prepare("
            SELECT * FROM payroll_payrun_amendments
            WHERE id = ? AND status = 'approved'
        ");
        $stmt->execute([$id]);
        $amendment = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$amendment) {
            throw new Exception('Amendment not found or not approved');
        }

        // Sync to Xero
        $result = $xero->syncPayrunAmendment($amendment);

        if ($result['success']) {
            // Log sync
            $stmt = $pdo->prepare("
                INSERT INTO integration_sync_log
                (integration_name, sync_type, item_type, item_id, external_id, status, details, created_at)
                VALUES ('xero', 'payrun', 'payrun', ?, ?, 'success', ?, NOW())
            ");
            $stmt->execute([
                $id,
                $result['xero_payrun_id'] ?? null,
                json_encode($result)
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Payrun synced to Xero successfully',
                'xero_payrun_id' => $result['xero_payrun_id'] ?? null
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
            SELECT pa.* FROM payroll_payrun_amendments pa
            LEFT JOIN integration_sync_log isl ON isl.item_type = 'payrun'
                AND isl.item_id = pa.id
                AND isl.integration_name = 'xero'
                AND isl.status = 'success'
            WHERE pa.staff_id = ?
            AND pa.status = 'approved'
            AND isl.id IS NULL
        ");
        $stmt->execute([$staffId]);
        $amendments = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $synced = 0;
        $errors = [];

        foreach ($amendments as $amendment) {
            try {
                $result = $xero->syncPayrunAmendment($amendment);

                if ($result['success']) {
                    // Log sync
                    $stmt = $pdo->prepare("
                        INSERT INTO integration_sync_log
                        (integration_name, sync_type, item_type, item_id, external_id, status, details, created_at)
                        VALUES ('xero', 'payrun', 'payrun', ?, ?, 'success', ?, NOW())
                    ");
                    $stmt->execute([
                        $amendment['id'],
                        $result['xero_payrun_id'] ?? null,
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
