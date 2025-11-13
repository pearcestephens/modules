<?php
/**
 * Store Reports - AI Chat Response API
 * Conversational AI for store managers
 */
declare(strict_types=1);

header('Content-Type: application/json');

require_once __DIR__ . '/../../../private_html/check-login.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../../../assets/services/MCPClient.php';
require_once __DIR__ . '/../../../assets/services/mcp/StoreReportsAdapter.php';

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check authentication
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'Unauthorized']);
    exit;
}

$userId = (int)$_SESSION['user_id'];

// Parse JSON input
$input = json_decode(file_get_contents('php://input'), true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
    exit;
}

// Validate required fields
$reportId = isset($input['report_id']) ? (int)$input['report_id'] : null;
$message = trim($input['message'] ?? '');

if (!$message) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Message is required']);
    exit;
}

if (strlen($message) > 2000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Message too long (max 2000 characters)']);
    exit;
}

try {
    // Database connection
    $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Get report context if provided
    $reportContext = null;
    $outletId = 0;

    if ($reportId) {
        $stmt = $db->prepare("SELECT sr.*, vo.name as outlet_name
            FROM store_reports sr
            LEFT JOIN vend_outlets vo ON sr.outlet_id = vo.id
            WHERE sr.id = ?");
        $stmt->execute([$reportId]);
        $reportContext = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($reportContext) {
            $outletId = (int)$reportContext['outlet_id'];
        }
    }

    // Load conversation history for this report
    $conversationHistory = [];
    if ($reportId) {
        $stmt = $db->prepare("SELECT role, message, created_at
            FROM store_report_ai_conversations
            WHERE report_id = ?
            ORDER BY created_at ASC
            LIMIT 20");
        $stmt->execute([$reportId]);
        $conversationHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Build conversation messages for AI
    $messages = [];

    // System message with context
    $systemMessage = "You are a helpful AI assistant for store managers completing inspection reports. ";
    $systemMessage .= "Provide clear, actionable advice about store compliance, safety, and operations. ";
    $systemMessage .= "Be concise but thorough. Use bullet points for lists.";

    if ($reportContext) {
        $systemMessage .= "\n\nCurrent Report Context:";
        $systemMessage .= "\n- Store: " . $reportContext['outlet_name'];
        $systemMessage .= "\n- Report Date: " . date('M j, Y', strtotime($reportContext['report_date']));
        $systemMessage .= "\n- Status: " . $reportContext['status'];

        if ($reportContext['overall_score']) {
            $systemMessage .= "\n- Current Score: " . round($reportContext['overall_score'], 1) . "%";
        }

        if ($reportContext['critical_issues_count'] > 0) {
            $systemMessage .= "\n- Critical Issues: " . $reportContext['critical_issues_count'];
        }
    }

    $messages[] = ['role' => 'system', 'content' => $systemMessage];

    // Add conversation history
    foreach ($conversationHistory as $turn) {
        $messages[] = [
            'role' => $turn['role'],
            'content' => $turn['message']
        ];
    }

    // Add new user message
    $messages[] = ['role' => 'user', 'content' => $message];

    // Call MCP Hub for AI response
    $adapter = new Services\MCP\Adapters\StoreReportsAdapter();
    $adapter->setUser($userId);

    if ($reportId) {
        $adapter->setReport($reportId);
    }

    $mcp = $adapter->getMCPClient();
    $mcp->setBotId('store-reports-conversation-bot')
        ->setUnitId($outletId);

    // Generate AI response
    $result = $adapter->generateText(
        json_encode($messages),
        [
            'temperature' => 0.7,
            'max_tokens' => 800,
            'model' => 'gpt-4-turbo-preview'
        ]
    );

    if (!$result['success']) {
        throw new Exception($result['error'] ?? 'AI generation failed');
    }

    $aiResponse = $result['text'];
    $tokensUsed = $result['tokens'] ?? 0;

    // Save conversation to database
    $db->beginTransaction();

    // Save user message
    $stmt = $db->prepare("INSERT INTO store_report_ai_conversations (
        report_id,
        user_id,
        role,
        message,
        created_at
    ) VALUES (?, ?, 'user', ?, NOW())");

    $stmt->execute([$reportId, $userId, $message]);

    // Save AI response
    $stmt = $db->prepare("INSERT INTO store_report_ai_conversations (
        report_id,
        user_id,
        role,
        message,
        tokens_used,
        created_at
    ) VALUES (?, ?, 'assistant', ?, ?, NOW())");

    $stmt->execute([$reportId, $userId, $aiResponse, $tokensUsed]);

    // Update report conversation count
    if ($reportId) {
        $stmt = $db->prepare("UPDATE store_reports
            SET ai_questions_asked = ai_questions_asked + 1
            WHERE id = ?");
        $stmt->execute([$reportId]);
    }

    $db->commit();

    // Success response
    echo json_encode([
        'success' => true,
        'ai_response' => $aiResponse,
        'tokens_used' => $tokensUsed,
        'timestamp' => date('c'),
        'message' => 'Response generated successfully'
    ]);

} catch (PDOException $e) {
    if (isset($db) && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log("Store Reports - AI chat DB error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Database error occurred'
    ]);
} catch (Exception $e) {
    error_log("Store Reports - AI chat error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
