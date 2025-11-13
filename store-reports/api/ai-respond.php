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
    echo json_encode([
        'success' => false,
        'message' => 'Message is required'
    ]);
    exit;
}

// Validate message length
if (strlen($message) > 2000) {
    http_response_code(400);
    echo json_encode(['error' => 'Message too long (max 2000 characters)']);
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

    // Verify report exists and user has access
    $stmt = $pdo->prepare("
        SELECT report_id, created_by, outlet_id
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

    // Check permissions
    $isOwner = ($report['created_by'] == $userId);
    $isManager = false; // TODO: Check user role

    if (!$isOwner && !$isManager) {
        http_response_code(403);
        echo json_encode(['error' => 'Insufficient permissions']);
        exit;
    }

    // Get conversation context if continuing existing conversation
    $conversationThread = [];

    if ($conversationId) {
        $stmt = $pdo->prepare("
            SELECT conversation_id, conversation_thread, ai_model_used
            FROM store_report_ai_conversations
            WHERE conversation_id = ? AND report_id = ?
        ");
        $stmt->execute([$conversationId, $reportId]);
        $conversation = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($conversation) {
            $conversationThread = json_decode($conversation['conversation_thread'], true) ?: [];
        }
    }

    // Get image context if image_id provided
    $imageContext = null;

    if ($imageId) {
        $stmt = $pdo->prepare("
            SELECT file_path, caption, ai_analysis_summary
            FROM store_report_images
            WHERE image_id = ? AND report_id = ?
        ");
        $stmt->execute([$imageId, $reportId]);
        $imageContext = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$imageContext) {
            http_response_code(404);
            echo json_encode(['error' => 'Image not found']);
            exit;
        }
    }

    // =========================================================================
    // 🚀 MCP HUB CONVERSATIONAL AI - Bypass GitHub Copilot!
    // =========================================================================
    // Use MCP Hub for GPT-4 chat completions
    // Hub handles: API calls, caching, rate limiting, error handling
    // Local DB still manages conversation storage (existing schema)
    // =========================================================================

    // Build system prompt
    $systemPrompt = "You are an AI assistant helping with retail store quality inspections. ";
    $systemPrompt .= "Provide specific, actionable feedback about store conditions, cleanliness, ";
    $systemPrompt .= "organization, safety, and compliance. Be concise but thorough.";

    // Build conversation messages array
    $messages = [
        ['role' => 'system', 'content' => $systemPrompt]
    ];

    // Add conversation history
    foreach ($conversationThread as $turn) {
        $messages[] = ['role' => $turn['role'], 'content' => $turn['content']];
    }

    // Build user message with context
    $userMessage = $message;
    if ($imageContext) {
        $userMessage .= "\n\n[Context: Image shows " . ($imageContext['caption'] ?: 'store area') . "]";
        if ($imageContext['ai_analysis_summary']) {
            $userMessage .= "\n[Previous analysis: " . substr($imageContext['ai_analysis_summary'], 0, 200) . "...]";
        }
    }
    $messages[] = ['role' => 'user', 'content' => $userMessage];

    // Call MCP Hub for AI generation
    $adapter = new Services\MCP\Adapters\StoreReportsAdapter();
    $adapter->setUser($userId)->setReport($reportId);

    $mcp = $adapter->getMCPClient();
    $mcp->setBotId('store-reports-conversation-bot')
        ->setUnitId($report['outlet_id'] ?? 0);

    $chatResult = $mcp->callTool('ai-generate', [
        'prompt' => json_encode($messages), // Send full conversation
        'model' => 'gpt-4-turbo-preview',
        'temperature' => 0.7,
        'max_tokens' => 800
    ]);

    if (!isset($chatResult['result']['content'])) {
        throw new Exception('Invalid MCP Hub response structure');
    }

    $aiResponse = $chatResult['result']['content'];
    $tokensUsed = $chatResult['metadata']['tokens'] ?? 0;

    // Update or create conversation
    if ($conversationId) {
        // Add to existing conversation
        $conversationThread[] = ['role' => 'user', 'content' => $message];
        $conversationThread[] = ['role' => 'assistant', 'content' => $aiResponse];

        $stmt = $pdo->prepare("
            UPDATE store_report_ai_conversations
            SET conversation_thread = ?,
                total_tokens_used = total_tokens_used + ?,
                last_message_at = NOW()
            WHERE conversation_id = ?
        ");
        $stmt->execute([json_encode($conversationThread), $tokensUsed, $conversationId]);
    } else {
        // Create new conversation
        $conversationThread = [
            ['role' => 'user', 'content' => $message],
            ['role' => 'assistant', 'content' => $aiResponse]
        ];

        $stmt = $pdo->prepare("
            INSERT INTO store_report_ai_conversations (
                report_id, conversation_thread, ai_model_used, total_tokens_used
            ) VALUES (?, ?, ?, ?)
        ");
        $stmt->execute([
            $reportId,
            json_encode($conversationThread),
            'gpt-4-turbo-preview',
            $tokensUsed
        ]);

        $conversationId = $pdo->lastInsertId();
    }

    // Store the request/response
    $stmt = $pdo->prepare("
        INSERT INTO store_report_ai_requests (
            report_id,
            image_id,
            conversation_id,
            request_type,
            prompt,
            response,
            tokens_used,
            requested_by
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?)
    ");

    $stmt->execute([
        $reportId,
        $imageId,
        $conversationId,
        'followup',
        $message,
        $aiResponse,
        $tokensUsed,
        $userId
    ]);

    $requestId = $pdo->lastInsertId();

    // Log the interaction
    $stmt = $pdo->prepare("
        INSERT INTO store_report_history (
            report_id, user_id, action, details
        ) VALUES (?, ?, 'ai_followup', ?)
    ");
    $stmt->execute([
        $reportId,
        $userId,
        json_encode([
            'conversation_id' => $conversationId,
            'request_id' => $requestId,
            'tokens_used' => $tokensUsed
        ])
    ]);

    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'conversation_id' => (int)$conversationId,
        'request_id' => (int)$requestId,
        'ai_response' => $aiResponse,
        'tokens_used' => $tokensUsed,
        'message' => 'AI response generated via MCP Hub',
        'powered_by' => 'MCP Intelligence Hub'
    ]);

} catch (Exception $e) {
    sr_log_error('ai_respond_error', [
        'report_id' => $reportId,
        'user_id' => $userId,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    http_response_code(500);
    echo json_encode([
        'error' => 'AI response failed',
        'debug' => sr_is_dev() ? $e->getMessage() : null
    ]);
}
