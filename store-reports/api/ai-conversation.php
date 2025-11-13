<?php
/**
 * AI Conversation History API
 * GET /api/ai-conversation
 *
 * Retrieves AI conversation history for a report
 * Shows threaded conversations with analysis results
 *
 * @endpoint GET /api/ai-conversation?report_id=123
 * @response JSON with conversation threads and AI responses
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

header('Content-Type: application/json');

// Validate request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Get query parameters
$reportId = $_GET['report_id'] ?? null;
$conversationId = $_GET['conversation_id'] ?? null;

if (!$reportId) {
    http_response_code(400);
    echo json_encode([
        'error' => 'Missing required parameter: report_id'
    ]);
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
        SELECT report_id, created_by, outlet_id, status
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
    $isManager = false; // TODO: Check user role from database

    if (!$isOwner && !$isManager) {
        http_response_code(403);
        echo json_encode(['error' => 'Insufficient permissions']);
        exit;
    }

    // Get AI conversations
    if ($conversationId) {
        // Get specific conversation thread
        $stmt = $pdo->prepare("
            SELECT
                c.conversation_id,
                c.conversation_thread,
                c.ai_model_used,
                c.total_tokens_used,
                c.started_at,
                c.last_message_at,
                COUNT(r.request_id) as message_count
            FROM store_report_ai_conversations c
            LEFT JOIN store_report_ai_requests r ON c.conversation_id = r.conversation_id
            WHERE c.conversation_id = ? AND c.report_id = ?
            GROUP BY c.conversation_id
        ");
        $stmt->execute([$conversationId, $reportId]);
        $conversations = [$stmt->fetch(PDO::FETCH_ASSOC)];
    } else {
        // Get all conversations for report
        $stmt = $pdo->prepare("
            SELECT
                c.conversation_id,
                c.conversation_thread,
                c.ai_model_used,
                c.total_tokens_used,
                c.started_at,
                c.last_message_at,
                COUNT(r.request_id) as message_count
            FROM store_report_ai_conversations c
            LEFT JOIN store_report_ai_requests r ON c.conversation_id = r.conversation_id
            WHERE c.report_id = ?
            GROUP BY c.conversation_id
            ORDER BY c.last_message_at DESC
        ");
        $stmt->execute([$reportId]);
        $conversations = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    // Get messages for each conversation
    foreach ($conversations as &$conversation) {
        if (!$conversation) continue;

        $stmt = $pdo->prepare("
            SELECT
                r.request_id,
                r.image_id,
                r.request_type,
                r.prompt,
                r.response,
                r.confidence_score,
                r.tokens_used,
                r.created_at,
                r.requested_by,
                i.file_path as image_path,
                i.thumbnail_path,
                u.first_name,
                u.last_name
            FROM store_report_ai_requests r
            LEFT JOIN store_report_images i ON r.image_id = i.image_id
            LEFT JOIN users u ON r.requested_by = u.user_id
            WHERE r.conversation_id = ?
            ORDER BY r.created_at ASC
        ");
        $stmt->execute([$conversation['conversation_id']]);
        $conversation['messages'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decode conversation thread JSON
        $conversation['conversation_thread'] = json_decode($conversation['conversation_thread'], true);
    }

    // Get summary statistics
    $stmt = $pdo->prepare("
        SELECT
            COUNT(DISTINCT c.conversation_id) as total_conversations,
            COUNT(r.request_id) as total_messages,
            SUM(r.tokens_used) as total_tokens,
            AVG(r.confidence_score) as avg_confidence
        FROM store_report_ai_conversations c
        LEFT JOIN store_report_ai_requests r ON c.conversation_id = r.conversation_id
        WHERE c.report_id = ?
    ");
    $stmt->execute([$reportId]);
    $stats = $stmt->fetch(PDO::FETCH_ASSOC);

    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'report_id' => (int)$reportId,
        'conversations' => $conversations ?: [],
        'statistics' => [
            'total_conversations' => (int)$stats['total_conversations'],
            'total_messages' => (int)$stats['total_messages'],
            'total_tokens' => (int)$stats['total_tokens'],
            'average_confidence' => round((float)$stats['avg_confidence'], 2)
        ],
        'message' => 'AI conversation history retrieved'
    ]);

} catch (Exception $e) {
    sr_log_error('ai_conversation_error', [
        'report_id' => $reportId,
        'user_id' => $userId,
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    http_response_code(500);
    echo json_encode([
        'error' => 'Failed to retrieve AI conversation history',
        'debug' => sr_is_dev() ? $e->getMessage() : null
    ]);
}
