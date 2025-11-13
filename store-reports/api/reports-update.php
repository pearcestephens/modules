<?php
/**
 * API: Update Report
 * PUT /api/reports-update
 *
 * Updates report data and triggers autosave
 *
 * @author Enterprise Team
 */

declare(strict_types=1);

header('Content-Type: application/json');

if (!defined('BOT_BYPASS')) {
    define('BOT_BYPASS', true);
}

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../services/AutosaveService.php';

sr_require_auth();

if (!verify_csrf()) {
    sr_json(['success' => false, 'error' => 'CSRF validation failed'], 403);
}

sr_rate_limit('report_update', 30);

if (!in_array($_SERVER['REQUEST_METHOD'], ['POST', 'PUT', 'PATCH'])) {
    sr_json(['success' => false, 'error' => 'Method not allowed'], 405);
}

try {
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    $reportId = (int)($input['report_id'] ?? $_GET['report_id'] ?? 0);

    if (!$reportId) {
        sr_json(['success' => false, 'error' => 'Missing report_id'], 400);
    }

    $userId = current_user_id() ?? $_SESSION['user_id'] ?? $_SESSION['userID'] ?? null;

    $pdo = sr_pdo();

    if (!$pdo) {
        sr_json(['success' => false, 'error' => 'Database unavailable'], 503);
    }

    // Verify report exists and user has access
    $stmt = $pdo->prepare("
        SELECT id, outlet_id, performed_by_user, status
        FROM store_reports
        WHERE id = ? AND deleted_at IS NULL
    ");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch();

    if (!$report) {
        sr_json(['success' => false, 'error' => 'Report not found'], 404);
    }

    // Check if user can edit (owner or admin)
    $canEdit = ($report['performed_by_user'] == $userId) || is_admin();

    if (!$canEdit) {
        sr_json(['success' => false, 'error' => 'Unauthorized'], 403);
    }

    // Check if report is editable
    if (in_array($report['status'], ['completed', 'archived'])) {
        sr_json(['success' => false, 'error' => 'Report is finalized and cannot be edited'], 400);
    }

    $pdo->beginTransaction();

    try {
        // Update basic fields
        $updateFields = [];
        $updateValues = [];

        $allowedFields = ['staff_notes', 'status'];

        foreach ($allowedFields as $field) {
            if (isset($input[$field])) {
                $updateFields[] = "$field = ?";
                $updateValues[] = $input[$field];
            }
        }

        if (!empty($updateFields)) {
            $updateValues[] = $reportId;
            $sql = "UPDATE store_reports SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $pdo->prepare($sql)->execute($updateValues);
        }

        // Update/insert items
        if (isset($input['items']) && is_array($input['items'])) {
            foreach ($input['items'] as $item) {
                $checklistId = (int)($item['checklist_id'] ?? 0);

                if (!$checklistId) continue;

                // Check if item exists
                $existingStmt = $pdo->prepare("
                    SELECT id FROM store_report_items
                    WHERE report_id = ? AND checklist_id = ?
                ");
                $existingStmt->execute([$reportId, $checklistId]);
                $existing = $existingStmt->fetch();

                if ($existing) {
                    // Update
                    $pdo->prepare("
                        UPDATE store_report_items
                        SET response_value = ?, response_text = ?, is_na = ?,
                            staff_notes = ?, answered_at = NOW(), updated_at = NOW()
                        WHERE id = ?
                    ")->execute([
                        $item['response_value'] ?? null,
                        $item['response_text'] ?? null,
                        !empty($item['is_na']) ? 1 : 0,
                        $item['staff_notes'] ?? null,
                        $existing['id']
                    ]);
                } else {
                    // Insert
                    $pdo->prepare("
                        INSERT INTO store_report_items
                        (report_id, checklist_id, response_value, response_text, is_na, staff_notes, answered_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW())
                    ")->execute([
                        $reportId,
                        $checklistId,
                        $item['response_value'] ?? null,
                        $item['response_text'] ?? null,
                        !empty($item['is_na']) ? 1 : 0,
                        $item['staff_notes'] ?? null
                    ]);
                }
            }
        }

        // Calculate completion
        $completionStmt = $pdo->prepare("
            SELECT
                COUNT(*) as total,
                SUM(CASE WHEN response_value IS NOT NULL OR response_text IS NOT NULL OR is_na = 1 THEN 1 ELSE 0 END) as completed
            FROM store_report_items
            WHERE report_id = ?
        ");
        $completionStmt->execute([$reportId]);
        $completion = $completionStmt->fetch();

        $completionPercent = $completion['total'] > 0
            ? round(($completion['completed'] / $completion['total']) * 100, 2)
            : 0;

        $pdo->prepare("
            UPDATE store_reports
            SET completion_percentage = ?, items_completed = ?
            WHERE id = ?
        ")->execute([$completionPercent, $completion['completed'], $reportId]);

        // Autosave checkpoint
        if (!empty($input['autosave'])) {
            $autosaveService = new AutosaveService($pdo);
            $autosaveResult = $autosaveService->createCheckpoint(
                $reportId,
                $userId,
                $input,
                [
                    'session_id' => session_id(),
                    'device_id' => $input['device_id'] ?? null,
                    'page_url' => $input['page_url'] ?? null
                ]
            );
        }

        $pdo->commit();

        sr_log_info("Report updated: ID=$reportId, completion=$completionPercent%");

        sr_json([
            'success' => true,
            'report_id' => $reportId,
            'completion_percentage' => $completionPercent,
            'items_completed' => $completion['completed'],
            'autosave' => $autosaveResult ?? null,
            'message' => 'Report updated successfully'
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (PDOException $e) {
    sr_log_error("Database error updating report: " . $e->getMessage());
    sr_json(['success' => false, 'error' => 'Database error', 'details' => $e->getMessage()], 500);
} catch (Exception $e) {
    sr_log_error("Error updating report: " . $e->getMessage());
    sr_json(['success' => false, 'error' => $e->getMessage()], 500);
}
