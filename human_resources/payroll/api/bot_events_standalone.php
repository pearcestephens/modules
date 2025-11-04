<?php
declare(strict_types=1);

/**
 * BOT EVENTS API - Standalone Version (No CIS Bootstrap)
 *
 * Lightweight event stream for autonomous payroll bot
 * Uses direct database connection to avoid session requirements
 *
 * @package PayrollModule\API
 * @version 1.0.1
 */

// Database connection
function getBotDb(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=127.0.0.1;dbname=jcepnzzkmj;charset=utf8mb4',
            'jcepnzzkmj',
            'wprKh9Jq63',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
    }
    return $pdo;
}

// Bot token validation
function validateBotToken(string $token): bool {
    $validTokens = [
        'test_bot_token_12345',
        'ci_automation_token',
        hash('sha256', 'payroll_bot_' . date('Y-m-d'))
    ];
    return in_array($token, $validTokens, true);
}

// Bot authentication
function requireBotAuth(): void {
    $botToken = $_SERVER['HTTP_X_BOT_TOKEN'] ?? $_GET['bot_token'] ?? null;

    if (!$botToken) {
        http_response_code(401);
        echo json_encode(['success' => false, 'error' => 'Bot token required']);
        exit;
    }

    if (!validateBotToken($botToken)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid bot token']);
        exit;
    }
}

// Require auth
requireBotAuth();

// Get action
$action = $_GET['action'] ?? 'pending_events';

header('Content-Type: application/json');

try {
    $db = getBotDb();

    switch ($action) {

        // ================================================================
        // HEALTH CHECK
        // ================================================================
        case 'health_check':
            echo json_encode([
                'success' => true,
                'status' => 'operational',
                'timestamp' => date('Y-m-d H:i:s'),
                'endpoints' => [
                    'pending_events' => 'GET ?action=pending_events',
                    'report_status' => 'POST ?action=report_status',
                    'event_details' => 'GET ?action=event_details&event_id=X'
                ]
            ], JSON_PRETTY_PRINT);
            break;

        // ================================================================
        // GET PENDING EVENTS
        // ================================================================
        case 'pending_events':
            $events = [];

            // 1. Pending wage amendments
            $stmt = $db->query("
                SELECT
                    a.id,
                    'amendment' as event_type,
                    a.staff_id,
                    CONCAT(u.fname, ' ', u.lname) as staff_name,
                    a.old_value,
                    a.new_value,
                    a.reason,
                    a.created_at,
                    'high' as priority
                FROM wage_amendments a
                LEFT JOIN users u ON a.staff_id = u.id
                WHERE a.status = 'pending'
                ORDER BY a.created_at ASC
                LIMIT 10
            ");
            foreach ($stmt->fetchAll() as $row) {
                $events[] = $row;
            }

            // 2. Pending leave requests
            $stmt = $db->query("
                SELECT
                    lr.id,
                    'leave' as event_type,
                    lr.user_id as staff_id,
                    CONCAT(u.fname, ' ', u.lname) as staff_name,
                    lr.leave_type,
                    lr.start_date,
                    lr.end_date,
                    lr.reason,
                    lr.created_at,
                    'medium' as priority
                FROM leave_requests lr
                LEFT JOIN users u ON lr.user_id = u.id
                WHERE lr.status = 'pending'
                ORDER BY lr.start_date ASC
                LIMIT 10
            ");
            foreach ($stmt->fetchAll() as $row) {
                $events[] = $row;
            }

            // 3. Deputy timesheets needing approval
            $stmt = $db->query("
                SELECT
                    ts.deputy_timesheet_id as id,
                    'timesheet' as event_type,
                    ts.user_id as staff_id,
                    CONCAT(u.fname, ' ', u.lname) as staff_name,
                    ts.week_start,
                    ts.week_end,
                    ts.total_hours,
                    ts.last_synced_at as created_at,
                    'medium' as priority
                FROM deputy_timesheets ts
                LEFT JOIN users u ON ts.user_id = u.id
                WHERE ts.status = 'pending_approval'
                ORDER BY ts.week_start DESC
                LIMIT 10
            ");
            foreach ($stmt->fetchAll() as $row) {
                $events[] = $row;
            }

            // 4. Wage discrepancies detected
            $stmt = $db->query("
                SELECT
                    wd.id,
                    'discrepancy' as event_type,
                    wd.staff_id,
                    CONCAT(u.fname, ' ', u.lname) as staff_name,
                    wd.expected_wage,
                    wd.actual_wage,
                    wd.difference,
                    wd.detected_at as created_at,
                    'high' as priority
                FROM wage_discrepancies wd
                LEFT JOIN users u ON wd.staff_id = u.id
                WHERE wd.status = 'detected' OR wd.status = 'pending_fix'
                ORDER BY ABS(wd.difference) DESC
                LIMIT 10
            ");
            foreach ($stmt->fetchAll() as $row) {
                $events[] = $row;
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'events' => $events,
                    'total_count' => count($events),
                    'polled_at' => date('Y-m-d H:i:s')
                ]
            ], JSON_PRETTY_PRINT);
            break;

        // ================================================================
        // REPORT BOT STATUS (Heartbeat)
        // ================================================================
        case 'report_status':
            $input = json_decode(file_get_contents('php://input'), true);

            $botId = $input['bot_id'] ?? 'payroll_bot_001';
            $status = $input['status'] ?? 'active';
            $eventsProcessed = (int)($input['events_processed'] ?? 0);
            $decisionsMade = (int)($input['decisions_made'] ?? 0);
            $errorsCount = (int)($input['errors_count'] ?? 0);
            $systemHealth = json_encode($input['system_health'] ?? []);

            $stmt = $db->prepare("
                INSERT INTO payroll_bot_heartbeat
                (bot_id, status, events_processed, decisions_made, errors_count, system_health, last_seen)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    events_processed = VALUES(events_processed),
                    decisions_made = VALUES(decisions_made),
                    errors_count = VALUES(errors_count),
                    system_health = VALUES(system_health),
                    last_seen = NOW()
            ");

            $stmt->execute([
                $botId,
                $status,
                $eventsProcessed,
                $decisionsMade,
                $errorsCount,
                $systemHealth
            ]);

            echo json_encode([
                'success' => true,
                'message' => 'Bot status recorded',
                'bot_id' => $botId,
                'recorded_at' => date('Y-m-d H:i:s')
            ], JSON_PRETTY_PRINT);
            break;

        // ================================================================
        // GET EVENT DETAILS
        // ================================================================
        case 'event_details':
            $eventId = (int)($_GET['event_id'] ?? 0);
            $eventType = $_GET['event_type'] ?? '';

            if (!$eventId || !$eventType) {
                throw new Exception('Missing event_id or event_type');
            }

            // For now, redirect to bot_context.php for full context
            echo json_encode([
                'success' => true,
                'message' => 'Use bot_context.php for full context',
                'redirect' => "bot_context.php?action=get_context&event_type={$eventType}&event_id={$eventId}"
            ], JSON_PRETTY_PRINT);
            break;

        default:
            throw new Exception('Invalid action');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ], JSON_PRETTY_PRINT);
}
