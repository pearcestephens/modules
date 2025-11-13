<?php
/**
 * Report Delete API
 * DELETE /api/reports-delete
 *
 * Soft deletes a report (marks as archived, preserves data)
 *
 * @endpoint DELETE /api/reports-delete?report_id=123
 * @response JSON confirmation of deletion
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'DELETE') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get query parameters
$reportId = $_GET['report_id'] ?? null;
$hardDelete = filter_var($_GET['hard_delete'] ?? false, FILTER_VALIDATE_BOOLEAN);

if (!$reportId) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing required parameter: report_id']);
    exit;
}

// Get authenticated user
$userId = $_SESSION['user_id'] ?? null;

if (!$userId) {
    http_response_code(401);
    echo json_encode(['error' => 'Authentication required']);
    exit;
}

try {
    $pdo = sr_pdo();

    // Get report details
    $stmt = $pdo->prepare("
        SELECT report_id, created_by, status, outlet_id
        FROM store_reports
        WHERE report_id = ?
    ");
    $stmt->execute([$reportId]);
    $report = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$report) {
        http_response_code(404);
        echo json_encode(['error' => 'Report not found']);
        exit;
    }

    // Check permissions - only owner or manager can delete
    $isOwner = ($report['created_by'] == $userId);
    $isManager = false; // TODO: Check user role from database

    if (!$isOwner && !$isManager) {
        http_response_code(403);
        echo json_encode(['error' => 'Insufficient permissions']);
        exit;
    }

    // Hard delete only allowed for managers and only for draft reports
    if ($hardDelete && (!$isManager || $report['status'] !== 'draft')) {
        http_response_code(403);
        echo json_encode([
            'error' => 'Hard delete not allowed',
            'message' => 'Only managers can hard delete draft reports'
        ]);
        exit;
    }

    $pdo->beginTransaction();

    try {
        if ($hardDelete) {
            // HARD DELETE - Permanently remove from database

            // Delete related records first (foreign key constraints)
            $pdo->prepare("DELETE FROM store_report_items WHERE report_id = ?")->execute([$reportId]);
            $pdo->prepare("DELETE FROM store_report_autosave_checkpoints WHERE report_id = ?")->execute([$reportId]);
            $pdo->prepare("DELETE FROM store_report_history WHERE report_id = ?")->execute([$reportId]);

            // Delete AI requests and conversations
            $pdo->prepare("DELETE FROM store_report_ai_requests WHERE report_id = ?")->execute([$reportId]);
            $pdo->prepare("DELETE FROM store_report_ai_conversations WHERE report_id = ?")->execute([$reportId]);

            // Get image and voice memo file paths before deleting
            $stmt = $pdo->prepare("SELECT file_path, thumbnail_path FROM store_report_images WHERE report_id = ?");
            $stmt->execute([$reportId]);
            $imagePaths = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $stmt = $pdo->prepare("SELECT file_path FROM store_report_voice_memos WHERE report_id = ?");
            $stmt->execute([$reportId]);
            $memoPaths = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Delete image and memo records
            $pdo->prepare("DELETE FROM store_report_images WHERE report_id = ?")->execute([$reportId]);
            $pdo->prepare("DELETE FROM store_report_voice_memos WHERE report_id = ?")->execute([$reportId]);

            // Delete the report itself
            $pdo->prepare("DELETE FROM store_reports WHERE report_id = ?")->execute([$reportId]);

            $pdo->commit();

            // Delete physical files after successful database commit
            foreach ($imagePaths as $paths) {
                if ($paths['file_path'] && file_exists($_SERVER['DOCUMENT_ROOT'] . $paths['file_path'])) {
                    unlink($_SERVER['DOCUMENT_ROOT'] . $paths['file_path']);
                }
                if ($paths['thumbnail_path'] && file_exists($_SERVER['DOCUMENT_ROOT'] . $paths['thumbnail_path'])) {
                    unlink($_SERVER['DOCUMENT_ROOT'] . $paths['thumbnail_path']);
                }
            }

            foreach ($memoPaths as $path) {
                if ($path['file_path'] && file_exists($_SERVER['DOCUMENT_ROOT'] . $path['file_path'])) {
                    unlink($_SERVER['DOCUMENT_ROOT'] . $path['file_path']);
                }
            }

            $deleteType = 'hard';
            $message = 'Report permanently deleted';

        } else {
            // SOFT DELETE - Mark as archived, preserve all data

            $stmt = $pdo->prepare("
                UPDATE store_reports
                SET status = 'archived',
                    updated_at = NOW()
                WHERE report_id = ?
            ");
            $stmt->execute([$reportId]);

            // Log the soft deletion
            $stmt = $pdo->prepare("
                INSERT INTO store_report_history (
                    report_id, user_id, action, details
                ) VALUES (?, ?, 'report_archived', ?)
            ");
            $stmt->execute([
                $reportId,
                $userId,
                json_encode([
                    'archived_by' => $userId,
                    'previous_status' => $report['status']
                ])
            ]);

            $pdo->commit();

            $deleteType = 'soft';
            $message = 'Report archived successfully';
        }

        // Success response
        http_response_code(200);
        echo json_encode([
            'success' => true,
            'report_id' => (int)$reportId,
            'delete_type' => $deleteType,
            'message' => $message
        ]);

    } catch (Exception $e) {
        $pdo->rollBack();
        throw $e;
    }

} catch (Exception $e) {
    sr_log_error('reports_delete_error', [
        'report_id' => $reportId,
        'user_id' => $userId,
        'hard_delete' => $hardDelete,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to delete report',
        'debug' => sr_is_dev() ? $e->getMessage() : null
    ]);
}
