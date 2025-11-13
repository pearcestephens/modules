<?php
/**
 * Messenger API Endpoints
 *
 * Routes:
 *   GET    /api/messenger/conversations        - List user's conversations
 *   POST   /api/messenger/conversations        - Create new conversation
 *   GET    /api/messenger/conversations/:id    - Get conversation details
 *   POST   /api/messenger/conversations/:id/messages - Send message
 *   GET    /api/messenger/conversations/:id/messages - Get messages
 *   POST   /api/messenger/messages/:id/read    - Mark message as read
 *   POST   /api/messenger/messages/:id/react   - Add reaction to message
 *   POST   /api/messenger/conversations/:id/typing - Update typing indicator
 *   GET    /api/messenger/messages/search      - Search messages
 *
 * @package    CIS\API
 * @version    1.0.0
 */

require_once __DIR__ . '/../../../config/bootstrap.php';
require_once __DIR__ . '/NotificationEngine.php';

use CIS\Notifications\MessengerEngine;
use CIS\Base\Response;

// Initialize engine
$messenger = new MessengerEngine();

// Get request method and action
$method = $_SERVER['REQUEST_METHOD'];
$path = trim(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/');
$parts = explode('/', $path);

// Determine endpoint
$endpoint = isset($parts[2]) ? $parts[2] : null;
$resource = isset($parts[3]) ? $parts[3] : null;
$resourceId = isset($parts[3]) ? $parts[3] : null;
$action = isset($parts[4]) ? $parts[4] : null;
$actionId = isset($parts[5]) ? $parts[5] : null;

// Verify authentication
$userId = verifyAuth();

if (!$userId) {
    Response::error('Unauthorized', 401);
}

try {
    switch ($endpoint) {
        case 'messenger':
            handleMessengerEndpoint($method, $resource, $resourceId, $action, $actionId, $userId, $messenger);
            break;
        default:
            Response::error('Endpoint not found', 404);
    }
} catch (\Exception $e) {
    \CIS\Base\Logger::getInstance()->error('Messenger API Error', [
        'endpoint' => $endpoint,
        'error' => $e->getMessage(),
    ]);
    Response::error($e->getMessage(), 400);
}

/**
 * Handle /api/messenger/* endpoints
 */
function handleMessengerEndpoint($method, $resource, $resourceId, $action, $actionId, $userId, $messenger)
{
    if ($resource === 'conversations') {
        if ($method === 'GET') {
            if (is_numeric($resourceId)) {
                // GET /api/messenger/conversations/:id
                handleGetConversation($resourceId, $userId, $messenger);
            } else {
                // GET /api/messenger/conversations
                handleListConversations($userId, $messenger);
            }
        } elseif ($method === 'POST' && !is_numeric($resourceId)) {
            // POST /api/messenger/conversations
            handleCreateConversation($userId, $messenger);
        } elseif ($method === 'POST' && is_numeric($resourceId)) {
            // POST /api/messenger/conversations/:id/*
            if ($action === 'messages') {
                handleSendMessage($resourceId, $userId, $messenger);
            } elseif ($action === 'typing') {
                handleTypingIndicator($resourceId, $userId, $messenger);
            } else {
                Response::error('Invalid action', 404);
            }
        }
    } elseif ($resource === 'messages') {
        if ($method === 'GET' && $action === 'search') {
            // GET /api/messenger/messages/search
            handleSearchMessages($userId, $messenger);
        } elseif ($method === 'POST' && is_numeric($resourceId)) {
            // POST /api/messenger/messages/:id/*
            if ($action === 'read') {
                handleMarkMessageAsRead($resourceId, $userId, $messenger);
            } elseif ($action === 'react') {
                handleAddReaction($resourceId, $userId, $messenger);
            } else {
                Response::error('Invalid action', 404);
            }
        }
    } else {
        Response::error('Invalid resource', 404);
    }
}

/**
 * GET /api/messenger/conversations
 * List user's conversations
 */
function handleListConversations($userId, $messenger)
{
    // Query conversations where user is a member
    $db = \CIS\Base\Database::getInstance();

    $conversations = $db->query(
        "SELECT c.*,
            (SELECT COUNT(*) FROM chat_messages WHERE conversation_id = c.conversation_id) as message_count,
            (SELECT COUNT(*) FROM chat_messages m
             LEFT JOIN chat_message_read_receipts r ON m.message_id = r.message_id AND r.user_id = ?
             WHERE m.conversation_id = c.conversation_id AND r.message_id IS NULL AND m.sender_user_id != ?) as unread_count
         FROM chat_conversations c
         INNER JOIN chat_group_members gm ON c.conversation_id = gm.conversation_id
         WHERE gm.user_id = ? AND gm.is_active = TRUE
         ORDER BY c.last_message_at DESC
         LIMIT 50",
        [$userId, $userId, $userId]
    );

    Response::success([
        'conversations' => $conversations,
        'count' => count($conversations),
    ]);
}

/**
 * POST /api/messenger/conversations
 * Create new conversation
 */
function handleCreateConversation($userId, $messenger)
{
    $body = json_decode(file_get_contents('php://input'), true);

    if (!$body) {
        Response::error('Invalid JSON', 400);
    }

    if (!isset($body['type'])) {
        Response::error('Missing field: type', 400);
    }

    $type = $body['type']; // 'direct', 'group', 'broadcast'
    $participants = $body['participant_ids'] ?? []; // For group/direct
    $name = $body['name'] ?? null; // For group
    $description = $body['description'] ?? null; // For group/broadcast

    // Validate
    if ($type === 'direct' && count($participants) !== 1) {
        Response::error('Direct conversation requires exactly 1 other participant', 400);
    }

    if ($type === 'group' && empty($name)) {
        Response::error('Group conversation requires a name', 400);
    }

    $db = \CIS\Base\Database::getInstance();

    try {
        // Create conversation
        $conversationData = [
            'type' => $type,
            'name' => $name,
            'description' => $description,
            'created_by_user_id' => $userId,
        ];

        $result = $db->query(
            "INSERT INTO chat_conversations SET ?",
            [$conversationData]
        );

        $conversationId = $db->getLastInsertId();

        // Add creator as member
        $db->query(
            "INSERT INTO chat_group_members (conversation_id, user_id, joined_at, is_admin)
             VALUES (?, ?, NOW(), TRUE)",
            [$conversationId, $userId]
        );

        // Add other participants
        foreach ($participants as $participantId) {
            $db->query(
                "INSERT INTO chat_group_members (conversation_id, user_id, joined_at)
                 VALUES (?, ?, NOW())",
                [$conversationId, $participantId]
            );
        }

        Response::success([
            'conversation_id' => $conversationId,
            'type' => $type,
        ], 201);

    } catch (\Exception $e) {
        Response::error('Failed to create conversation: ' . $e->getMessage(), 400);
    }
}

/**
 * GET /api/messenger/conversations/:id
 * Get conversation details and messages
 */
function handleGetConversation($conversationId, $userId, $messenger)
{
    $db = \CIS\Base\Database::getInstance();

    // Get conversation
    $conversation = $db->query(
        "SELECT * FROM chat_conversations WHERE conversation_id = ?",
        [$conversationId]
    );

    if (empty($conversation)) {
        Response::error('Conversation not found', 404);
    }

    $conversation = $conversation[0];

    // Verify user is member
    $isMember = $db->query(
        "SELECT 1 FROM chat_group_members WHERE conversation_id = ? AND user_id = ? AND is_active = TRUE",
        [$conversationId, $userId]
    );

    if (empty($isMember)) {
        Response::error('Access denied', 403);
    }

    // Get recent messages (default last 50)
    $limit = (int) ($_GET['limit'] ?? 50);
    $offset = (int) ($_GET['offset'] ?? 0);

    if ($limit > 100) {
        $limit = 100;
    }

    $messages = $messenger->getMessages($conversationId, $limit, $offset);

    // Get group members
    $members = $db->query(
        "SELECT gm.*, u.name, u.email, u.avatar_url
         FROM chat_group_members gm
         LEFT JOIN cis_users u ON gm.user_id = u.user_id
         WHERE gm.conversation_id = ? AND gm.is_active = TRUE",
        [$conversationId]
    );

    Response::success([
        'conversation' => $conversation,
        'messages' => $messages,
        'members' => $members,
        'message_count' => count($messages),
    ]);
}

/**
 * POST /api/messenger/conversations/:id/messages
 * Send message to conversation
 */
function handleSendMessage($conversationId, $userId, $messenger)
{
    $body = json_decode(file_get_contents('php://input'), true);

    if (!$body || !isset($body['message_text'])) {
        Response::error('Missing field: message_text', 400);
    }

    // Verify user is member of conversation
    $db = \CIS\Base\Database::getInstance();
    $isMember = $db->query(
        "SELECT 1 FROM chat_group_members WHERE conversation_id = ? AND user_id = ? AND is_active = TRUE",
        [$conversationId, $userId]
    );

    if (empty($isMember)) {
        Response::error('Access denied', 403);
    }

    try {
        $messageId = $messenger->sendMessage([
            'conversation_id' => $conversationId,
            'sender_user_id' => $userId,
            'message_text' => $body['message_text'],
            'mentions' => $body['mentions'] ?? [],
            'reply_to_message_id' => $body['reply_to_message_id'] ?? null,
            'attachments' => $body['attachments'] ?? [],
        ]);

        Response::success([
            'message_id' => $messageId,
            'conversation_id' => $conversationId,
        ], 201);

    } catch (\Exception $e) {
        Response::error('Failed to send message: ' . $e->getMessage(), 400);
    }
}

/**
 * POST /api/messenger/messages/:id/read
 * Mark messages as read
 */
function handleMarkMessageAsRead($messageId, $userId, $messenger)
{
    // Get conversation for this message
    $db = \CIS\Base\Database::getInstance();
    $message = $db->query(
        "SELECT conversation_id FROM chat_messages WHERE message_id = ?",
        [$messageId]
    );

    if (empty($message)) {
        Response::error('Message not found', 404);
    }

    $conversationId = $message[0]['conversation_id'];

    // Mark as read
    $messenger->markConversationAsRead($conversationId, $userId, $messageId);

    Response::success(['message' => 'Marked as read']);
}

/**
 * POST /api/messenger/messages/:id/react
 * Add reaction to message
 */
function handleAddReaction($messageId, $userId, $messenger)
{
    $body = json_decode(file_get_contents('php://input'), true);

    if (!$body || !isset($body['emoji'])) {
        Response::error('Missing field: emoji', 400);
    }

    $add = $body['add'] ?? true;

    try {
        $messenger->addReaction($messageId, $userId, $body['emoji'], $add);
        Response::success(['message' => 'Reaction added']);
    } catch (\Exception $e) {
        Response::error($e->getMessage(), 400);
    }
}

/**
 * POST /api/messenger/conversations/:id/typing
 * Update typing indicator
 */
function handleTypingIndicator($conversationId, $userId, $messenger)
{
    $body = json_decode(file_get_contents('php://input'), true);

    if ($body === null) {
        Response::error('Invalid JSON', 400);
    }

    $isTyping = $body['is_typing'] ?? true;

    $messenger->updateTypingIndicator($conversationId, $userId, $isTyping);

    Response::success(['message' => 'Typing indicator updated']);
}

/**
 * GET /api/messenger/messages/search
 * Search messages across user's conversations
 */
function handleSearchMessages($userId, $messenger)
{
    $query = $_GET['q'] ?? null;
    $conversationId = $_GET['conversation_id'] ?? null;
    $limit = (int) ($_GET['limit'] ?? 50);

    if (!$query) {
        Response::error('Missing parameter: q', 400);
    }

    if ($limit > 100) {
        $limit = 100;
    }

    $results = $messenger->searchMessages($conversationId, $query, $limit);

    Response::success([
        'results' => $results,
        'count' => count($results),
    ]);
}

/**
 * Verify user authentication
 *
 * @return int|null User ID if authenticated, null otherwise
 */
function verifyAuth()
{
    // Check Bearer token or session
    $token = null;

    // Check Authorization header
    $headers = getallheaders();
    if (isset($headers['Authorization'])) {
        preg_match('/Bearer\s(\S+)/', $headers['Authorization'], $matches);
        $token = $matches[1] ?? null;
    }

    // Check session
    session_start();
    if (isset($_SESSION['user_id'])) {
        return $_SESSION['user_id'];
    }

    // TODO: Validate JWT token
    if ($token) {
        // Validate and return user_id from token
        // return \CIS\Auth\JWT::verify($token);
    }

    return null;
}
