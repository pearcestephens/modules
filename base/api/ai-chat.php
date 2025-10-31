<?php
/**
 * AI Chat API Endpoint
 * 
 * Handles AI chat requests from any module's AI widget
 * 
 * POST /modules/base/api/ai-chat.php
 * 
 * Payload:
 * {
 *   "message": "User's question or command",
 *   "context": {
 *     "module": "module-name",
 *     "page": "dashboard.php",
 *     "user_id": 123,
 *     "session_id": "session_abc123",
 *     "tables": ["orders", "products"],
 *     "capabilities": {...},
 *     "history": [...]
 *   }
 * }
 * 
 * Response:
 * {
 *   "success": true,
 *   "response": "AI's answer",
 *   "suggested_function": "optional function name",
 *   "confidence": 0.95,
 *   "timestamp": "2025-10-28 10:30:00"
 * }
 */

declare(strict_types=1);

// Load Base Module
require_once dirname(__DIR__) . '/bootstrap.php';

use Base\Database;
use Base\Logger;
use Base\Session;
use Base\Response;
use Base\Services\AIChatService;

// Set headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// CORS headers (adjust for your domain)
header('Access-Control-Allow-Origin: *'); // Change to specific domain in production
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only accept POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::json([
        'success' => false,
        'error' => 'Method not allowed. Use POST.',
        'code' => 'METHOD_NOT_ALLOWED'
    ], 405);
}

// Check authentication
if (!Session::isLoggedIn()) {
    Logger::warning('AI Chat - Unauthorized access attempt', [
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
    ]);
    
    Response::json([
        'success' => false,
        'error' => 'Authentication required',
        'code' => 'UNAUTHORIZED'
    ], 401);
}

// Get request data
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    Logger::error('AI Chat - Invalid JSON', [
        'error' => json_last_error_msg(),
        'raw_input' => substr($rawInput, 0, 200)
    ]);
    
    Response::json([
        'success' => false,
        'error' => 'Invalid JSON payload',
        'code' => 'INVALID_JSON'
    ], 400);
}

// Extract request parameters
$message = trim($input['message'] ?? '');
$context = $input['context'] ?? [];
$module = $context['module'] ?? 'unknown';
$userId = Session::get('user_id');
$sessionId = $context['session_id'] ?? 'unknown';

// Validate message
if (empty($message)) {
    Response::json([
        'success' => false,
        'error' => 'Message is required',
        'code' => 'MESSAGE_REQUIRED'
    ], 400);
}

// Rate limiting check (basic implementation)
$rateLimitKey = 'ai_chat_' . $userId;
$rateLimitCount = apcu_fetch($rateLimitKey) ?: 0;
$rateLimitMax = 60; // 60 requests per minute
$rateLimitWindow = 60; // 60 seconds

if ($rateLimitCount >= $rateLimitMax) {
    Logger::warning('AI Chat - Rate limit exceeded', [
        'user_id' => $userId,
        'count' => $rateLimitCount
    ]);
    
    Response::json([
        'success' => false,
        'error' => 'Too many requests. Please wait a moment.',
        'code' => 'RATE_LIMIT_EXCEEDED'
    ], 429);
}

// Increment rate limit counter
apcu_store($rateLimitKey, $rateLimitCount + 1, $rateLimitWindow);

// Log incoming request
Logger::info('AI Chat Request', [
    'user_id' => $userId,
    'module' => $module,
    'message_length' => strlen($message),
    'session_id' => $sessionId,
    'has_context' => !empty($context),
    'has_history' => !empty($context['history'] ?? [])
]);

try {
    // Initialize AI Chat Service
    $ai = AIChatService::getInstance();
    
    // Enhance context with session data
    $enrichedContext = array_merge($context, [
        'user_id' => $userId,
        'user_name' => Session::get('user_name'),
        'user_role' => Session::get('user_role'),
        'timestamp' => date('Y-m-d H:i:s'),
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ]);
    
    // Get AI response
    $startTime = microtime(true);
    $response = $ai->chat($message, $enrichedContext);
    $responseTime = round((microtime(true) - $startTime) * 1000, 2); // milliseconds
    
    // Log successful response
    Logger::info('AI Chat Response', [
        'user_id' => $userId,
        'module' => $module,
        'session_id' => $sessionId,
        'response_length' => strlen($response['message'] ?? ''),
        'confidence' => $response['confidence'] ?? null,
        'suggested_function' => $response['suggested_function'] ?? null,
        'response_time_ms' => $responseTime
    ]);
    
    // Store conversation in database (optional - for analytics)
    try {
        $db = Database::getInstance();
        $db->insert('ai_conversations', [
            'user_id' => $userId,
            'module' => $module,
            'session_id' => $sessionId,
            'user_message' => $message,
            'ai_response' => $response['message'] ?? '',
            'confidence' => $response['confidence'] ?? null,
            'suggested_function' => $response['suggested_function'] ?? null,
            'response_time_ms' => $responseTime,
            'context' => json_encode($enrichedContext),
            'created_at' => date('Y-m-d H:i:s')
        ]);
    } catch (Exception $dbError) {
        // Don't fail the request if conversation storage fails
        Logger::warning('AI Chat - Failed to store conversation', [
            'error' => $dbError->getMessage()
        ]);
    }
    
    // Return successful response
    Response::json([
        'success' => true,
        'response' => $response['message'] ?? 'I received your message but couldn\'t generate a response.',
        'suggested_function' => $response['suggested_function'] ?? null,
        'confidence' => $response['confidence'] ?? 1.0,
        'timestamp' => date('Y-m-d H:i:s'),
        'response_time_ms' => $responseTime
    ]);
    
} catch (Exception $e) {
    // Log error
    Logger::error('AI Chat Error', [
        'user_id' => $userId,
        'module' => $module,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine(),
        'trace' => $e->getTraceAsString()
    ]);
    
    // Determine error type and response
    $errorCode = 'AI_ERROR';
    $statusCode = 500;
    $userMessage = 'Sorry, I encountered an error. Please try again.';
    
    if (strpos($e->getMessage(), 'AI Hub') !== false) {
        $errorCode = 'AI_HUB_ERROR';
        $userMessage = 'AI service is temporarily unavailable. Please try again in a moment.';
    } elseif (strpos($e->getMessage(), 'timeout') !== false) {
        $errorCode = 'TIMEOUT_ERROR';
        $userMessage = 'Request timed out. Please try a simpler question.';
    } elseif (strpos($e->getMessage(), 'database') !== false) {
        $errorCode = 'DATABASE_ERROR';
        $userMessage = 'Database error occurred. Please contact support if this persists.';
    }
    
    // Return error response
    Response::json([
        'success' => false,
        'error' => $userMessage,
        'code' => $errorCode,
        'timestamp' => date('Y-m-d H:i:s'),
        // Include technical details only in development
        'debug' => (getenv('APP_ENV') === 'development') ? [
            'exception' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ] : null
    ], $statusCode);
}
