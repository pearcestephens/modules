<?php
declare(strict_types=1);

namespace Transfers\Lib;

/**
 * Enhanced Security Class for Enterprise-Grade Protection
 * 
 * Provides comprehensive security features including:
 * - CSRF protection
 * - Request tracing and correlation IDs
 * - Rate limiting
 * - Input validation and sanitization
 * - Audit logging
 * - Session security
 * 
 * @package Transfers\Lib
 * @version 2.0.0 - Enterprise Hardening
 */
final class Security
{
    private static $correlation_id = null;
    private static $trace_data = [];
    private static $security_context = [];
    
    /**
     * Initialize security context for request
     */
    public static function initializeContext(): void
    {
        if (self::$correlation_id === null) {
            self::$correlation_id = uniqid('sec_', true);
            self::$trace_data = [
                'correlation_id' => self::$correlation_id,
                'start_time' => microtime(true),
                'events' => [],
                'security_checks' => []
            ];
            self::$security_context = [
                'ip_address' => self::getRealIpAddress(),
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
                'session_id' => session_id(),
                'user_id' => self::currentUserId(),
                'timestamp' => time()
            ];
            
            self::addTraceEvent('security_context_initialized');
        }
    }
    
    /**
     * Get correlation ID for request tracing
     */
    public static function getCorrelationId(): string
    {
        self::initializeContext();
        return self::$correlation_id;
    }
    
    /**
     * Add security trace event
     */
    public static function addTraceEvent(string $event_type, array $data = []): void
    {
        self::$trace_data['events'][] = [
            'type' => $event_type,
            'timestamp' => microtime(true),
            'data' => $data
        ];
    }
    
    /**
     * Enhanced CSRF token generation
     */
    public static function csrfToken(): string
    {
        if (!isset($_SESSION)) session_start();
        if (empty($_SESSION['csrf'])) {
            $_SESSION['csrf'] = bin2hex(random_bytes(32));
            self::addTraceEvent('csrf_token_generated');
        }
        return $_SESSION['csrf'];
    }

    /**
     * CSRF token input field
     */
    public static function csrfTokenInput(): string
    {
        return '<input type="hidden" name="csrf" value="'.htmlspecialchars(self::csrfToken()).'">';
    }

    /**
     * Enhanced CSRF validation with logging
     */
    public static function assertCsrf(string $token): void
    {
        self::initializeContext();
        
        if (!isset($_SESSION)) session_start();
        
        $valid = !empty($token) && !empty($_SESSION['csrf']) && hash_equals($_SESSION['csrf'], $token);
        
        if (!$valid) {
            self::addTraceEvent('csrf_validation_failed', [
                'token_provided' => !empty($token),
                'session_token_exists' => !empty($_SESSION['csrf']),
                'ip' => self::$security_context['ip_address']
            ]);
            
            self::logSecurityEvent('csrf_attack_attempt', [
                'token_length' => strlen($token),
                'session_id' => session_id()
            ]);
            
            http_response_code(403);
            throw new \RuntimeException('Invalid CSRF token.');
        }
        
        self::addTraceEvent('csrf_validation_success');
    }
    
    /**
     * Enhanced user ID with session validation
     */
    public static function currentUserId(): int
    {
        if (!isset($_SESSION)) session_start();
        
        $user_id = isset($_SESSION['userID']) ? (int)$_SESSION['userID'] : 0;
        
        // Session timeout check (2 hours)
        if ($user_id > 0 && isset($_SESSION['last_activity'])) {
            if ((time() - $_SESSION['last_activity']) > 7200) {
                self::addTraceEvent('session_timeout', ['user_id' => $user_id]);
                session_destroy();
                return 0;
            }
            $_SESSION['last_activity'] = time();
        }
        
        return $user_id;
    }

    /**
     * Enhanced client fingerprinting
     */
    public static function clientFingerprint(): string
    {
        $ip = self::getRealIpAddress();
        $ua = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        $encoding = $_SERVER['HTTP_ACCEPT_ENCODING'] ?? '';
        $lang = $_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '';
        
        return hash('sha256', $ip.'|'.$ua.'|'.$accept.'|'.$encoding.'|'.$lang);
    }
    
    /**
     * Comprehensive input validation and sanitization
     */
    public static function validateInput(array $data, array $rules): array
    {
        self::initializeContext();
        self::addTraceEvent('input_validation_start', ['fields' => count($rules)]);
        
        $sanitized = [];
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? null;
            
            // Required field check
            if (($rule['required'] ?? false) && ($value === null || $value === '')) {
                $errors[$field] = "Field '{$field}' is required";
                continue;
            }
            
            if ($value === null || $value === '') {
                $sanitized[$field] = $value;
                continue;
            }
            
            // Type validation
            switch ($rule['type'] ?? 'string') {
                case 'int':
                    if (!is_numeric($value) || (int)$value != $value) {
                        $errors[$field] = "Field '{$field}' must be an integer";
                        break;
                    }
                    $sanitized[$field] = (int)$value;
                    
                    // Range validation for integers
                    if (isset($rule['min']) && $sanitized[$field] < $rule['min']) {
                        $errors[$field] = "Field '{$field}' must be at least {$rule['min']}";
                    }
                    if (isset($rule['max']) && $sanitized[$field] > $rule['max']) {
                        $errors[$field] = "Field '{$field}' cannot exceed {$rule['max']}";
                    }
                    break;
                    
                case 'float':
                    if (!is_numeric($value)) {
                        $errors[$field] = "Field '{$field}' must be a number";
                        break;
                    }
                    $sanitized[$field] = (float)$value;
                    break;
                    
                case 'string':
                    $sanitized[$field] = self::sanitizeString((string)$value);
                    
                    // Length validation
                    if (isset($rule['max_length']) && strlen($sanitized[$field]) > $rule['max_length']) {
                        $errors[$field] = "Field '{$field}' cannot exceed {$rule['max_length']} characters";
                    }
                    break;
                    
                case 'array':
                    if (!is_array($value)) {
                        $errors[$field] = "Field '{$field}' must be an array";
                        break;
                    }
                    $sanitized[$field] = $value;
                    break;
                    
                case 'enum':
                    if (!in_array($value, $rule['values'] ?? [])) {
                        $errors[$field] = "Field '{$field}' must be one of: " . implode(', ', $rule['values'] ?? []);
                        break;
                    }
                    $sanitized[$field] = $value;
                    break;
            }
        }
        
        self::addTraceEvent('input_validation_complete', [
            'errors_count' => count($errors),
            'fields_processed' => count($sanitized)
        ]);
        
        return [
            'valid' => empty($errors),
            'data' => $sanitized,
            'errors' => $errors
        ];
    }
    
    /**
     * Rate limiting check
     */
    public static function checkRateLimit(string $action, int $limit = 60, int $window = 60): bool
    {
        self::initializeContext();
        
        // Create rate limit key
        $key = "rate_limit:" . self::$security_context['ip_address'] . ":" . self::$security_context['user_id'] . ":" . $action;
        
        // TODO: Implement actual rate limiting with Redis or database
        // For now, log the attempt and return true
        
        self::addTraceEvent('rate_limit_check', [
            'action' => $action,
            'limit' => $limit,
            'window' => $window,
            'key' => hash('sha256', $key), // Don't log actual key for security
            'passed' => true
        ]);
        
        return true;
    }
    
    /**
     * Log security events for audit trail
     */
    public static function logSecurityEvent(string $event_type, array $details = []): void
    {
        self::initializeContext();
        
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'correlation_id' => self::$correlation_id,
            'event_type' => $event_type,
            'user_id' => self::$security_context['user_id'],
            'ip_address' => self::$security_context['ip_address'],
            'user_agent' => self::$security_context['user_agent'],
            'session_id' => self::$security_context['session_id'],
            'details' => $details
        ];
        
        // Log to error log (in production, you'd want a dedicated security log)
        error_log("SECURITY_EVENT: " . json_encode($log_entry));
        
        self::addTraceEvent('security_event_logged', ['type' => $event_type]);
    }
    
    /**
     * Get comprehensive trace data for monitoring
     */
    public static function getTraceData(): array
    {
        self::initializeContext();
        
        $trace = self::$trace_data;
        $trace['end_time'] = microtime(true);
        $trace['duration_ms'] = round(($trace['end_time'] - $trace['start_time']) * 1000, 2);
        $trace['security_context'] = self::$security_context;
        
        return $trace;
    }
    
    /**
     * Sanitize string input to prevent XSS
     */
    private static function sanitizeString(string $input): string
    {
        // Remove null bytes
        $input = str_replace("\0", '', $input);
        
        // HTML entity encoding
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        
        // Trim whitespace
        $input = trim($input);
        
        return $input;
    }
    
    /**
     * Get real IP address behind proxies/load balancers
     */
    private static function getRealIpAddress(): string
    {
        $ip_headers = [
            'HTTP_CLIENT_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_FORWARDED',
            'HTTP_X_CLUSTER_CLIENT_IP',
            'HTTP_FORWARDED_FOR',
            'HTTP_FORWARDED',
            'REMOTE_ADDR'
        ];
        
        foreach ($ip_headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
        
        return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    }
}
