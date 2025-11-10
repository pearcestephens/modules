<?php
/**
 * Toggle Auto-Pilot API
 * Enables or disables AI auto-approval system
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../config.php';

// Check authentication
session_start();
if (!isset($_SESSION['userID'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Validate input
$input = json_decode(file_get_contents('php://input'), true);
if (!isset($input['enabled'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing enabled parameter']);
    exit;
}

try {
    // Initialize
    $pdo = new PDO("mysql:host=127.0.0.1;dbname=jcepnzzkmj", "jcepnzzkmj", "wprKh9Jq63");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $enabled = (bool)$input['enabled'];

    // Update config
    $stmt = $pdo->prepare("
        UPDATE payroll_bot_config
        SET config_value = ?
        WHERE config_key = 'auto_pilot_enabled'
    ");
    $stmt->execute([$enabled ? '1' : '0']);

    // If no row was updated, insert it
    if ($stmt->rowCount() === 0) {
        $stmt = $pdo->prepare("
            INSERT INTO payroll_bot_config (config_key, config_value, config_type)
            VALUES ('auto_pilot_enabled', ?, 'boolean')
        ");
        $stmt->execute([$enabled ? '1' : '0']);
    }

    // Log the change
    $stmt = $pdo->prepare("
        INSERT INTO payroll_audit_log
        (action_type, user_id, notes, ip_address, created_at)
        VALUES ('autopilot_toggle', ?, ?, ?, NOW())
    ");
    $stmt->execute([
        $_SESSION['userID'],
        $enabled ? 'Auto-pilot enabled' : 'Auto-pilot disabled',
        $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);

    echo json_encode([
        'success' => true,
        'enabled' => $enabled,
        'message' => $enabled ? 'Auto-pilot enabled' : 'Auto-pilot disabled'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Failed to toggle auto-pilot',
        'message' => $e->getMessage()
    ]);
}
