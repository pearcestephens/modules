<?php
/**
 * Store Reports - Auto-save API
 * Handles automatic draft saving every 30 seconds
 */
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../../../private_html/check-login.php';
require_once __DIR__ . '/../config.php';

// Security checks
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

try {
    $userId = (int)$_SESSION['user_id'];

    // Get JSON payload
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        throw new Exception('Invalid JSON payload');
    }

    $reportId = $data['reportId'] ?? null;
    $outletId = $data['outletId'] ?? null;
    $checklistVersionId = $data['checklistVersionId'] ?? null;
    $items = $data['items'] ?? [];
    $status = $data['status'] ?? 'draft';

    if (!$outletId) {
        throw new Exception('Outlet ID required');
    }

    // Database connection
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $db->beginTransaction();

    // Create or update report
    if ($reportId) {
        // Update existing report
        $stmt = $db->prepare("UPDATE store_reports SET
            last_autosave_at = NOW(),
            status = ?,
            completion_percentage = ?
        WHERE id = ? AND performed_by_user = ?");

        $completionPercent = count($items) > 0
            ? (count(array_filter($items, fn($item) => isset($item['response']))) / count($items)) * 100
            : 0;

        $stmt->execute([$status, $completionPercent, $reportId, $userId]);
    } else {
        // Create new report
        $stmt = $db->prepare("INSERT INTO store_reports (
            outlet_id,
            performed_by_user,
            report_date,
            checklist_version_id,
            status,
            last_autosave_at,
            created_at
        ) VALUES (?, ?, NOW(), ?, 'autosaved', NOW(), NOW())");

        $stmt->execute([$outletId, $userId, $checklistVersionId]);
        $reportId = (int)$db->lastInsertId();
    }

    // Save autosave checkpoint (full snapshot)
    $stmt = $db->prepare("INSERT INTO store_report_autosave_checkpoints (
        report_id,
        checkpoint_data,
        created_at
    ) VALUES (?, ?, NOW())");

    $stmt->execute([
        $reportId,
        json_encode([
            'items' => $items,
            'timestamp' => time(),
            'device_id' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ])
    ]);

    $checkpointId = (int)$db->lastInsertId();

    // Update report with checkpoint reference
    $stmt = $db->prepare("UPDATE store_reports
        SET autosave_checkpoint_id = ?
        WHERE id = ?");
    $stmt->execute([$checkpointId, $reportId]);

    $db->commit();

    echo json_encode([
        'success' => true,
        'report_id' => $reportId,
        'checkpoint_id' => $checkpointId,
        'message' => 'Auto-saved successfully'
    ]);

} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Store Reports - Auto-save DB error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error'
    ]);
} catch (Exception $e) {
    error_log("Store Reports - Auto-save error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
