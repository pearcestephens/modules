<?php
/**
 * Client-Side Error Logger
 * 
 * Receives and stores JavaScript errors and AJAX failures
 * for debugging and monitoring
 * 
 * @package CIS\Consignments\API
 * @version 1.0.0
 */

declare(strict_types=1);

// This file is included by api.php, so bootstrap and ApiResponse are already available

header('Content-Type: application/json');

try {
    // Get request data
    $input = ApiResponse::getRequestData();
    
    // Validate required fields
    $requiredFields = ['level', 'message'];
    $missingFields = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($input[$field]) || $input[$field] === '') {
            $missingFields[] = $field;
        }
    }
    
    if (!empty($missingFields)) {
        echo ApiResponse::validationError([
            'missing_fields' => $missingFields
        ]);
        exit;
    }
    
    // Sanitize and prepare data
    $level = strtoupper(substr($input['level'], 0, 20)); // ERROR, WARNING, INFO
    $message = substr($input['message'], 0, 500);
    $context = $input['context'] ?? [];
    $url = $input['url'] ?? $_SERVER['HTTP_REFERER'] ?? '';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ip_address = $_SERVER['REMOTE_ADDR'] ?? '';
    
    // Get user info if available
    $user_id = null;
    $username = null;
    
    if (isset($_SESSION['user_id'])) {
        $user_id = (int)$_SESSION['user_id'];
        $username = $_SESSION['username'] ?? null;
    }
    
    // Connect to database
    $db = new PDO(
        'mysql:host=' . $_ENV['DB_HOST'] . ';dbname=' . $_ENV['DB_NAME'] . ';charset=utf8mb4',
        $_ENV['DB_USER'],
        $_ENV['DB_PASS'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
    
    // Insert log entry
    $sql = "INSERT INTO client_error_log (
        level,
        message,
        context_json,
        url,
        user_id,
        username,
        user_agent,
        ip_address,
        created_at
    ) VALUES (
        :level,
        :message,
        :context_json,
        :url,
        :user_id,
        :username,
        :user_agent,
        :ip_address,
        NOW()
    )";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        ':level' => $level,
        ':message' => $message,
        ':context_json' => json_encode($context),
        ':url' => substr($url, 0, 500),
        ':user_id' => $user_id,
        ':username' => $username,
        ':user_agent' => substr($user_agent, 0, 255),
        ':ip_address' => $ip_address
    ]);
    
    $logId = (int)$db->lastInsertId();
    
    echo ApiResponse::success([
        'log_id' => $logId,
        'logged_at' => date('Y-m-d H:i:s')
    ], 'Error logged successfully');
    
} catch (PDOException $e) {
    // Database error
    error_log('Client error log DB error: ' . $e->getMessage());
    
    echo ApiResponse::serverError(
        'Failed to store error log',
        'DB_ERROR',
        ['db_error' => $e->getMessage()]
    );
    
} catch (Exception $e) {
    // General error
    error_log('Client error log error: ' . $e->getMessage());
    
    echo ApiResponse::serverError(
        'Failed to process error log',
        'PROCESSING_ERROR',
        ['error' => $e->getMessage()]
    );
}
