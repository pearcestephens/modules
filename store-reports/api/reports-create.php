<?php
/**
 * API: Create New Report
 * POST /api/reports-create
 *
 * Creates a new store inspection report
 *
 * @author Enterprise Team
 */

declare(strict_types=1);

header('Content-Type: application/json');

// Bot bypass - allow API access
if (!defined('BOT_BYPASS')) {
    define('BOT_BYPASS', true);
}

require_once __DIR__ . '/../bootstrap.php';

// Auth required
sr_require_auth();

// CSRF check
if (!verify_csrf()) {
    sr_json(['success' => false, 'error' => 'CSRF validation failed'], 403);
}

// Rate limit
sr_rate_limit('report_create', 10); // Max 10 per minute

// Method check
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sr_json(['success' => false, 'error' => 'Method not allowed'], 405);
}

try {
    // Parse input
    $input = json_decode(file_get_contents('php://input'), true) ?? $_POST;

    // Validate required fields
    $required = ['outlet_id'];
    foreach ($required as $field) {
        if (empty($input[$field])) {
            sr_json(['success' => false, 'error' => "Missing required field: $field"], 400);
        }
    }

    // Get user ID
    $userId = current_user_id() ?? $_SESSION['user_id'] ?? $_SESSION['userID'] ?? null;

    if (!$userId) {
        sr_json(['success' => false, 'error' => 'User not authenticated'], 401);
    }

    $pdo = sr_pdo();

    if (!$pdo) {
        sr_json(['success' => false, 'error' => 'Database unavailable'], 503);
    }

    // Get latest checklist version
    $stmt = $pdo->prepare("
        SELECT id FROM store_report_checklist_versions
        WHERE status = 'active' AND is_default = 1
        ORDER BY created_at DESC LIMIT 1
    ");
    $stmt->execute();
    $version = $stmt->fetch();

    $versionId = $version['id'] ?? null;

    // Generate session/device IDs
    $sessionId = session_id() ?: bin2hex(random_bytes(16));
    $deviceId = $input['device_id'] ?? ($_SERVER['HTTP_USER_AGENT'] ? md5($_SERVER['HTTP_USER_AGENT']) : bin2hex(random_bytes(16)));

    // Create report
    $stmt = $pdo->prepare("
        INSERT INTO store_reports
        (outlet_id, performed_by_user, report_date, checklist_version_id,
         status, session_id, device_id, started_at, created_offline, created_at)
        VALUES (?, ?, NOW(), ?, 'draft', ?, ?, NOW(), ?, NOW())
    ");

    $stmt->execute([
        $input['outlet_id'],
        $userId,
        $versionId,
        $sessionId,
        $deviceId,
        !empty($input['offline']) ? 1 : 0
    ]);

    $reportId = (int)$pdo->lastInsertId();

    // Log creation
    $historyStmt = $pdo->prepare("
        INSERT INTO store_report_history
        (report_id, user_id, action_type, description, device_id, session_id, created_at)
        VALUES (?, ?, 'created', ?, ?, ?, NOW())
    ");

    $historyStmt->execute([
        $reportId,
        $userId,
        'Report created for outlet: ' . $input['outlet_id'],
        $deviceId,
        $sessionId
    ]);

    // Load checklist items for this version
    $checklistStmt = $pdo->prepare("
        SELECT id, category, name, title, description, question_type,
               max_points, weight, is_critical, photo_required, min_photos,
               display_order, help_text, options
        FROM store_report_checklist
        WHERE version_id = ? AND is_active = 1
        ORDER BY category, display_order
    ");

    $checklistStmt->execute([$versionId]);
    $checklist = $checklistStmt->fetchAll(PDO::FETCH_ASSOC);

    // Update total items
    $pdo->prepare("UPDATE store_reports SET total_items = ? WHERE id = ?")
        ->execute([count($checklist), $reportId]);

    sr_log_info("Report created: ID=$reportId, outlet={$input['outlet_id']}, user=$userId");

    sr_json([
        'success' => true,
        'report_id' => $reportId,
        'checklist' => $checklist,
        'session_id' => $sessionId,
        'device_id' => $deviceId,
        'message' => 'Report created successfully'
    ], 201);

} catch (PDOException $e) {
    sr_log_error("Database error creating report: " . $e->getMessage());
    sr_json(['success' => false, 'error' => 'Database error', 'details' => $e->getMessage()], 500);
} catch (Exception $e) {
    sr_log_error("Error creating report: " . $e->getMessage());
    sr_json(['success' => false, 'error' => $e->getMessage()], 500);
}
