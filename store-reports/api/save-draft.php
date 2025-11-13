<?php
/**
 * Store Reports - Save Draft API
 * Manual save with full item persistence
 */
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../../../private_html/check-login.php';
require_once __DIR__ . '/../config.php';

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

    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    if (!$data) {
        throw new Exception('Invalid JSON payload');
    }

    $reportId = $data['reportId'] ?? null;
    $outletId = $data['outletId'] ?? null;
    $checklistVersionId = $data['checklistVersionId'] ?? null;
    $items = $data['items'] ?? [];

    if (!$outletId) {
        throw new Exception('Outlet ID required');
    }

    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $db->beginTransaction();

    // Create or update report
    if ($reportId) {
        $stmt = $db->prepare("UPDATE store_reports SET
            last_autosave_at = NOW(),
            status = 'draft'
        WHERE id = ? AND performed_by_user = ?");
        $stmt->execute([$reportId, $userId]);
    } else {
        $stmt = $db->prepare("INSERT INTO store_reports (
            outlet_id,
            performed_by_user,
            report_date,
            checklist_version_id,
            status,
            created_at
        ) VALUES (?, ?, NOW(), ?, 'draft', NOW())");

        $stmt->execute([$outletId, $userId, $checklistVersionId]);
        $reportId = (int)$db->lastInsertId();
    }

    // Save individual checklist items
    $itemsCompleted = 0;
    $itemsPassed = 0;
    $itemsFailed = 0;
    $itemsNA = 0;

    foreach ($items as $itemId => $itemData) {
        $response = $itemData['response'] ?? null;
        $notes = $itemData['notes'] ?? null;

        if ($response) {
            $itemsCompleted++;

            if ($response === 'pass') $itemsPassed++;
            if ($response === 'fail') $itemsFailed++;
            if ($response === 'na') $itemsNA++;

            // Insert or update item response
            $stmt = $db->prepare("INSERT INTO store_report_items (
                report_id,
                checklist_item_id,
                response,
                notes,
                performed_by_user,
                performed_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                response = VALUES(response),
                notes = VALUES(notes),
                performed_at = NOW()");

            $stmt->execute([
                $reportId,
                $itemId,
                $response,
                $notes,
                $userId
            ]);
        }
    }

    // Update report stats
    $totalItems = count($items);
    $completionPercent = $totalItems > 0 ? ($itemsCompleted / $totalItems) * 100 : 0;

    $stmt = $db->prepare("UPDATE store_reports SET
        total_items = ?,
        items_completed = ?,
        items_passed = ?,
        items_failed = ?,
        items_na = ?,
        completion_percentage = ?
    WHERE id = ?");

    $stmt->execute([
        $totalItems,
        $itemsCompleted,
        $itemsPassed,
        $itemsFailed,
        $itemsNA,
        $completionPercent,
        $reportId
    ]);

    $db->commit();

    echo json_encode([
        'success' => true,
        'report_id' => $reportId,
        'stats' => [
            'total' => $totalItems,
            'completed' => $itemsCompleted,
            'passed' => $itemsPassed,
            'failed' => $itemsFailed,
            'na' => $itemsNA,
            'completion_percent' => round($completionPercent, 2)
        ],
        'message' => 'Draft saved successfully'
    ]);

} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Store Reports - Save draft DB error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
} catch (Exception $e) {
    error_log("Store Reports - Save draft error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
