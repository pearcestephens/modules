<?php
/**
 * AI Request Proxy
 * 
 * Internal proxy endpoint that forwards AI requests to external Intelligence Hub.
 * Provides security, rate limiting, logging, and caching layer.
 * 
 * @package CIS\Modules\Base\API
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';

header('Content-Type: application/json');

// Security: Require authentication
if (!isset($_SESSION['userID'])) {
    http_response_code(401);
    die(json_encode([
        'success' => false,
        'error' => 'Authentication required'
    ]));
}

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die(json_encode([
        'success' => false,
        'error' => 'Method not allowed'
    ]));
}

// Rate limiting: 20 requests per minute per user
$cache_key = "ai_rate_limit_" . $_SESSION['userID'];
$rate_limit = 20;
$time_window = 60; // seconds

// Check rate limit (using simple file-based cache)
$rate_file = sys_get_temp_dir() . '/' . $cache_key . '.txt';
$current_time = time();

if (file_exists($rate_file)) {
    $data = json_decode(file_get_contents($rate_file), true);
    $requests = array_filter($data['requests'], function($timestamp) use ($current_time, $time_window) {
        return ($current_time - $timestamp) < $time_window;
    });
    
    if (count($requests) >= $rate_limit) {
        http_response_code(429);
        die(json_encode([
            'success' => false,
            'error' => 'Rate limit exceeded. Please wait a moment.',
            'retry_after' => $time_window
        ]));
    }
    
    $requests[] = $current_time;
    file_put_contents($rate_file, json_encode(['requests' => $requests]));
} else {
    file_put_contents($rate_file, json_encode(['requests' => [$current_time]]));
}

// Get request data
$input = json_decode(file_get_contents('php://input'), true);
$message = $input['message'] ?? $_POST['message'] ?? '';
$context = $input['context'] ?? $_POST['context'] ?? [];
$source = $input['source'] ?? 'unknown';

// Validate input
if (empty($message)) {
    http_response_code(400);
    die(json_encode([
        'success' => false,
        'error' => 'Message is required'
    ]));
}

// Check cache for repeated requests (optional optimization)
$cache_hash = md5($message . json_encode($context));
$cache_file = sys_get_temp_dir() . '/ai_cache_' . $cache_hash . '.json';
$cache_ttl = 3600; // 1 hour

if (file_exists($cache_file) && (time() - filemtime($cache_file)) < $cache_ttl) {
    $cached_response = json_decode(file_get_contents($cache_file), true);
    $cached_response['cached'] = true;
    echo json_encode($cached_response);
    exit;
}

// External Intelligence Hub URL
$external_url = 'https://gpt.ecigdis.co.nz/api/chat';

// Prepare request payload
$payload = [
    'message' => $message,
    'context' => $context,
    'source' => $source,
    'user_id' => $_SESSION['userID'],
    'username' => $_SESSION['username'] ?? 'unknown',
    'timestamp' => date('Y-m-d H:i:s'),
    'client' => 'cis_staff_portal'
];

// Create stream context for HTTP request
$options = [
    'http' => [
        'method' => 'POST',
        'header' => [
            'Content-Type: application/json',
            'User-Agent: CIS-Staff-Portal/1.0',
            'X-CIS-User: ' . $_SESSION['userID'],
            'X-CIS-Source: ' . $source
        ],
        'content' => json_encode($payload),
        'timeout' => 30,
        'ignore_errors' => true
    ]
];

$stream_context = stream_context_create($options);

// Forward request to external hub
try {
    $response = @file_get_contents($external_url, false, $stream_context);
    
    if ($response === false) {
        // External hub unavailable - return graceful error
        http_response_code(503);
        $error_response = [
            'success' => false,
            'error' => 'AI service temporarily unavailable',
            'fallback' => true,
            'message' => 'Please try again in a moment or use manual controls.'
        ];
        
        // Log the failure
        error_log("AI Hub unreachable: {$external_url}");
        
        echo json_encode($error_response);
        exit;
    }
    
    // Parse response
    $response_data = json_decode($response, true);
    
    if ($response_data === null) {
        throw new Exception('Invalid JSON response from AI hub');
    }
    
    // Cache successful response
    if (isset($response_data['success']) && $response_data['success']) {
        file_put_contents($cache_file, $response);
    }
    
    // Log the request (for analytics)
    logAIRequest($_SESSION['userID'], $message, $source, $response_data);
    
    // Return response
    echo $response;
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Error processing AI request',
        'message' => $e->getMessage()
    ]);
    
    error_log("AI Request Error: " . $e->getMessage());
}

/**
 * Log AI request for analytics and debugging
 */
function logAIRequest($user_id, $message, $source, $response) {
    global $pdo;
    
    try {
        // Log to database if ai_requests table exists
        $stmt = $pdo->prepare("
            INSERT INTO ai_requests 
            (user_id, message, source, response_time, success, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $success = isset($response['success']) ? (int)$response['success'] : 0;
        $response_time = $response['processing_time'] ?? 0;
        
        $stmt->execute([
            $user_id,
            substr($message, 0, 500), // Truncate long messages
            $source,
            $response_time,
            $success
        ]);
        
    } catch (PDOException $e) {
        // Table doesn't exist yet - just log to file
        error_log(sprintf(
            "AI Request: user=%d, source=%s, message=%s",
            $user_id,
            $source,
            substr($message, 0, 100)
        ));
    }
}
