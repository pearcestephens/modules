<?php
/**
 * CSRF Protection Middleware
 * 
 * Include this at the top of all API files that accept POST requests
 * 
 * Usage:
 *   require_once __DIR__ . '/../lib/csrf.php';
 *   csrf_protect(); // Call before any POST processing
 */

session_start();

/**
 * Generate CSRF token
 */
function csrf_generate_token() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Get current CSRF token
 */
function csrf_get_token() {
    return $_SESSION['csrf_token'] ?? csrf_generate_token();
}

/**
 * Validate CSRF token from request
 */
function csrf_validate_token($token) {
    if (empty($_SESSION['csrf_token'])) {
        return false;
    }
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Protect against CSRF attacks
 * 
 * Call this at the start of any API endpoint that accepts POST requests
 * 
 * @param boolean $requirePost If true, only check on POST requests
 */
function csrf_protect($requirePost = true) {
    // Only check on POST requests if $requirePost is true
    if ($requirePost && $_SERVER['REQUEST_METHOD'] !== 'POST') {
        return true;
    }
    
    // For POST requests, token is required
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $token = $_POST['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
        
        if (!csrf_validate_token($token)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => 'CSRF token validation failed'
            ]);
            exit;
        }
    }
    
    return true;
}

/**
 * Output CSRF token as hidden input field
 */
function csrf_field() {
    $token = csrf_get_token();
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
}

/**
 * Get CSRF token as meta tag (for AJAX)
 */
function csrf_meta() {
    $token = csrf_get_token();
    return '<meta name="csrf-token" content="' . htmlspecialchars($token) . '">';
}

// Generate token on load
csrf_generate_token();
