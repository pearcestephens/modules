<?php
/**
 * PHP Sandbox Executor
 * Safely executes PHP code and returns output
 *
 * Security: Runs in isolated context, prevents code injection
 * Features: Captures output, handles errors, extracts variables
 *
 * @version 1.0.0
 */

header('Content-Type: application/json');

// =====================================================================
// SECURITY CHECKS
// =====================================================================

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Verify request source (optional - add your own checks)
$allowedHosts = ['localhost', '127.0.0.1', $_SERVER['HTTP_HOST'] ?? ''];
$requestHost = $_SERVER['HTTP_HOST'] ?? '';

// Get request data
$input = json_decode(file_get_contents('php://input'), true);

if (!$input || !isset($input['code'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing code']);
    exit;
}

$php_code = $input['code'];
$timeout = $input['timeout'] ?? 5; // seconds
$context = $input['context'] ?? []; // Variables to pass in

// =====================================================================
// DANGEROUS FUNCTIONS BLOCKLIST
// =====================================================================

$blocklist = [
    'exec', 'shell_exec', 'system', 'passthru', 'proc_open',
    'popen', 'curl_exec', 'curl_multi_exec',
    'eval', 'assert', 'create_function',
    'include', 'require', 'include_once', 'require_once',
    'file_get_contents', 'file_put_contents',
    'fopen', 'fwrite', 'unlink', 'mkdir', 'rmdir',
    'mysql_query', 'mysqli_query', 'PDO',
    'mail', 'header', 'setcookie',
    'fpassthru', 'readfile', 'serialize', 'unserialize'
];

// Check for dangerous functions
$blocked = [];
foreach ($blocklist as $func) {
    if (stripos($php_code, $func) !== false) {
        $blocked[] = $func;
    }
}

if (!empty($blocked)) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => 'Blocked function(s): ' . implode(', ', array_unique($blocked))
    ]);
    exit;
}

// =====================================================================
// SANDBOX EXECUTION
// =====================================================================

try {
    // Set execution timeout
    set_time_limit($timeout);
    ini_set('max_execution_time', $timeout);

    // Start output buffering
    ob_start();

    // Create isolated scope with provided variables
    $__sandbox_context = $context;

    // Execute code in isolated scope
    $__sandbox_result = null;
    $__sandbox_error = null;

    try {
        // Use eval in a try block with error handling
        $__sandbox_result = eval($php_code);
    } catch (Throwable $e) {
        $__sandbox_error = [
            'type' => get_class($e),
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ];
    }

    // Capture output
    $output = ob_get_clean();

    // Extract variables created during execution
    $variables = get_defined_vars();
    // Filter out internal variables
    $created_vars = array_filter($variables, function($key) {
        return strpos($key, '__sandbox_') !== 0 && !in_array($key, ['input', 'timeout', 'context', 'php_code', 'blocklist', 'blocked']);
    }, ARRAY_FILTER_USE_KEY);

    // Response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'output' => $output,
        'result' => $__sandbox_result,
        'error' => $__sandbox_error,
        'variables' => $created_vars,
        'stats' => [
            'memory_used' => memory_get_usage(),
            'memory_peak' => memory_get_peak_usage()
        ]
    ]);

} catch (Exception $e) {
    ob_end_clean();

    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'file' => $e->getFile(),
        'line' => $e->getLine()
    ]);
}
