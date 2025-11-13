<?php
/**
 * Store Reports - Get Draft API
 * Load existing draft for resumption
 */
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../../../private_html/check-login.php';
require_once __DIR__ . '/../config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
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
    $outletId = $_GET['outlet_id'] ?? null;

    if (!$outletId) {
        throw new Exception('Outlet ID required');
    }

    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Find most recent draft for this outlet/user
    $stmt = $db->prepare("SELECT * FROM store_reports
        WHERE outlet_id = ?
        AND performed_by_user = ?
        AND status IN ('draft', 'autosaved', 'in_progress')
        ORDER BY last_autosave_at DESC
        LIMIT 1");

    $stmt->execute([$outletId, $userId]);
    $draft = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$draft) {
        echo json_encode([
            'success' => true,
            'draft' => null,
            'message' => 'No draft found'
        ]);
        exit;
    }

    // Load checklist item responses
    $stmt = $db->prepare("SELECT * FROM store_report_items WHERE report_id = ?");
    $stmt->execute([$draft['id']]);
    $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Convert to keyed array
    $itemsData = [];
    foreach ($items as $item) {
        $itemsData[$item['checklist_item_id']] = [
            'response' => $item['response'],
            'notes' => $item['notes'],
            'timestamp' => $item['performed_at']
        ];
    }

    // Load autosave checkpoint if available
    $checkpointData = null;
    if ($draft['autosave_checkpoint_id']) {
        $stmt = $db->prepare("SELECT * FROM store_report_autosave_checkpoints
            WHERE id = ?");
        $stmt->execute([$draft['autosave_checkpoint_id']]);
        $checkpoint = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($checkpoint) {
            $checkpointData = json_decode($checkpoint['checkpoint_data'], true);
        }
    }

    echo json_encode([
        'success' => true,
        'draft' => [
            'id' => (int)$draft['id'],
            'outlet_id' => $draft['outlet_id'],
            'checklist_version_id' => (int)$draft['checklist_version_id'],
            'status' => $draft['status'],
            'completion_percentage' => (float)$draft['completion_percentage'],
            'last_autosave_at' => $draft['last_autosave_at'],
            'items' => $itemsData,
            'checkpoint' => $checkpointData,
            'stats' => [
                'total_items' => (int)$draft['total_items'],
                'items_completed' => (int)$draft['items_completed'],
                'items_passed' => (int)$draft['items_passed'],
                'items_failed' => (int)$draft['items_failed'],
                'items_na' => (int)$draft['items_na']
            ]
        ],
        'message' => 'Draft loaded successfully'
    ]);

} catch (PDOException $e) {
    error_log("Store Reports - Get draft DB error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Database error']);
} catch (Exception $e) {
    error_log("Store Reports - Get draft error: " . $e->getMessage());
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => $e->getMessage()]);
}
