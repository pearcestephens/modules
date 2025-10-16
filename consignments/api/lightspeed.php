<?php
declare(strict_types=1);

/**
 * ========================================================================
 * VEND LIGHTSPEED API GATEWAY - ENTERPRISE EDITION
 * ========================================================================
 * 
 * CACHE BUSTER: v3.0.1 - Fixed function redeclaration conflicts
 * ========================================================================
 * 
 * Comprehensive Vend Lightspeed API 2.0 endpoint collection designed for:
 * • ENTERPRISE-GRADE reliability and performance
 * • EXTERNAL testing via cURL and API clients
 * • COMPLETE endpoint coverage with standardized responses
 * • PRODUCTION-READY error handling and logging
 * • BACKWARDS-COMPATIBLE integration with existing CIS systems
 * 
 * Authentication: PIN gate (5050) + Bearer token for Lightspeed
 * Base URL: https://vapeshed.retail.lightspeed.app/api/2.0
 * Response Format: Standardized JSON with success/error envelopes
 * 
 * @package CIS\VendAPI
 * @version 2.0.0
 * @author CIS Development Team
 * @created 2025-10-16
 * 
 * EXTERNAL USAGE EXAMPLES:
 * -------------------------
 * curl -X POST "https://staff.vapeshed.co.nz/path/to/api.php" \
 *   -H "Content-Type: application/json" \
 *   -d '{"action":"get_consignment","pin":"5050","id":"CONS123"}'
 * 
 * curl -X POST "https://staff.vapeshed.co.nz/path/to/api.php" \
 *   -H "Content-Type: application/json" \
 *   -d '{"action":"create_consignment","pin":"5050","data":{"outlet_id":"OUT001","type":"SUPPLIER"}}'
 * ========================================================================
 */

// Start session for authentication
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ========================================================================
// ENTERPRISE CONFIGURATION
// ========================================================================

const API_VERSION = '2.0.0';
const API_NAME = 'CIS Vend Lightspeed Gateway';
const PIN_CODE = '5050';
const MAX_RETRY_ATTEMPTS = 3;
const REQUEST_TIMEOUT = 30;
const RATE_LIMIT_PER_MINUTE = 60;

/**
 * Get Lightspeed API token from config table
 */
function getLightspeedApiToken(): ?string {
    global $db;
    try {
        $stmt = $db->prepare("SELECT configValue FROM config WHERE configID = 23 LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['configValue'] ?? null;
    } catch (Exception $e) {
        error_log("Failed to get Lightspeed API token: " . $e->getMessage());
        return null;
    }
}

// Lightspeed API Configuration
$LIGHTSPEED_CONFIG = [
    'domain_prefix' => 'vapeshed',
    'api_token' => getLightspeedApiToken(), // Loaded from config table ID 23
    'base_url' => 'https://vapeshed.retail.lightspeed.app/api/2.0',
    'ui_base' => 'https://vapeshed.retail.lightspeed.app/app/2.0',
    'timeout' => REQUEST_TIMEOUT,
    'retry_attempts' => MAX_RETRY_ATTEMPTS
];

// ========================================================================
// ENTERPRISE SECURITY & AUTHENTICATION
// ========================================================================

/**
 * Validates PIN authentication for external API access
 */
if (!function_exists('validateAuthentication')) {
    function validateAuthentication(array $request): bool {
        $providedPin = $request['pin'] ?? '';
        
        if (empty($providedPin)) {
            return false;
        }
        
        if (!hash_equals(PIN_CODE, $providedPin)) {
            if (function_exists('logSecurityEvent')) {
                logSecurityEvent('INVALID_PIN_ATTEMPT', ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
            }
            return false;
        }
        
        // Rate limiting check
        if (!checkRateLimit()) {
            if (function_exists('logSecurityEvent')) {
                logSecurityEvent('RATE_LIMIT_EXCEEDED', ['ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown']);
            }
            return false;
        }
        
        return true;
    }
}

/**
 * Simple rate limiting implementation
 */
if (!function_exists('checkRateLimit')) {
    function checkRateLimit(): bool {
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
        $key = "rate_limit_{$ip}";
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = ['count' => 0, 'reset_time' => time() + 60];
        }
        
        $data = $_SESSION[$key];
        
        // Reset if time window passed
        if (time() > $data['reset_time']) {
            $_SESSION[$key] = ['count' => 1, 'reset_time' => time() + 60];
            return true;
        }
        
        // Check if limit exceeded
        if ($data['count'] >= RATE_LIMIT_PER_MINUTE) {
            return false;
        }
        
        // Increment counter
        $_SESSION[$key]['count']++;
        return true;
    }
}

/**
 * Logs security events for monitoring
 */
if (!function_exists('logSecurityEvent')) {
    function logSecurityEvent(string $event, array $context = []): void {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'context' => $context,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_id' => function_exists('getRequestId') ? getRequestId() : 'unknown'
        ];
        
        error_log('SECURITY: ' . json_encode($logEntry));
    }
}

// ========================================================================
// ENTERPRISE DATABASE CONNECTION
// ========================================================================

/**
 * Robust database connection with fallback resolution
 * NOTE: Only declare if not already declared in global connection.php
 */
if (!function_exists('getDatabaseConnection')) {
    function getDatabaseConnection(): mysqli {
        // Environment variable resolution with fallbacks
        $host = getenv('DB_HOST') ?: (defined('DB_HOST') ? constant('DB_HOST') : '127.0.0.1');
        $user = getenv('DB_USER') ?: (defined('DB_USERNAME') ? constant('DB_USERNAME') : 
               (defined('DB_USER') ? constant('DB_USER') : 'jcepnzzkmj'));
        $pass = getenv('DB_PASS') ?: (defined('DB_PASSWORD') ? constant('DB_PASSWORD') : 
               (defined('DB_PASS') ? constant('DB_PASS') : 'wprKh9Jq63'));
        $name = getenv('DB_NAME') ?: (defined('DB_DATABASE') ? constant('DB_DATABASE') : 
               (defined('DB_NAME') ? constant('DB_NAME') : 'jcepnzzkmj'));
        
        $connection = new mysqli($host, $user, $pass, $name);
        
        if ($connection->connect_errno) {
            logError('DATABASE_CONNECTION_FAILED', [
                'error' => $connection->connect_error,
                'host' => $host,
                'database' => $name
            ]);
            throw new Exception('Database connection failed: ' . $connection->connect_error);
        }
        
        $connection->set_charset('utf8mb4');
        return $connection;
    }
}

// ========================================================================
// ENTERPRISE LOGGING SYSTEM
// ========================================================================

/**
 * Generates unique request ID for tracing
 */
if (!function_exists('getRequestId')) {
    function getRequestId(): string {
        static $requestId = null;
        if ($requestId === null) {
            $requestId = substr(md5(uniqid('req_', true)), 0, 12);
        }
        return $requestId;
    }
}

/**
 * Enterprise logging with structured data
 */
if (!function_exists('logApiCall')) {
    function logApiCall(string $action, array $request, array $response, float $duration): void {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'request_id' => getRequestId(),
            'action' => $action,
            'duration_ms' => round($duration * 1000, 2),
            'success' => $response['success'] ?? false,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'request_size' => strlen(json_encode($request)),
            'response_size' => strlen(json_encode($response))
        ];
        
        // Remove sensitive data
        $sanitizedRequest = $request;
        unset($sanitizedRequest['pin']);
        $logEntry['request_preview'] = array_slice($sanitizedRequest, 0, 5, true);
        
        error_log('API_CALL: ' . json_encode($logEntry));
    }
}

/**
 * Error logging with context
 */
if (!function_exists('logError')) {
    function logError(string $error, array $context = []): void {
        $logEntry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'request_id' => getRequestId(),
            'error' => $error,
            'context' => $context,
            'trace' => debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS, 3)
        ];
        
        error_log('API_ERROR: ' . json_encode($logEntry));
    }
}

// ========================================================================
// ENTERPRISE HTTP CLIENT
// ========================================================================

/**
 * Enterprise-grade HTTP client for Lightspeed API
 */
class LightspeedHttpClient {
    private array $config;
    private int $requestCount = 0;

    public function __construct(array $config = null) {
        global $LIGHTSPEED_CONFIG;
        $this->config = $config ?? $LIGHTSPEED_CONFIG;
    }

    public function request(string $method, string $endpoint, ?array $data = null, array $options = []): array {
        $startTime = microtime(true);
        $this->requestCount++;

        $url = rtrim($this->config['base_url'], '/') . '/' . ltrim($endpoint, '/');
        $requestId = getRequestId() . '_' . $this->requestCount;

        $ch = curl_init();
$headers = [
    'Authorization: Bearer ' . $this->config['api_token'], // ✅
    'Accept: application/json',
    'Content-Type: application/json',
    'User-Agent: ' . API_NAME . '/' . API_VERSION . ' (+staff.vapeshed.co.nz)',
    'X-Request-ID: ' . $requestId
];


        $curlOptions = [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_HEADER => true,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_USERAGENT => API_NAME . '/' . API_VERSION
        ];
        if ($data !== null) $curlOptions[CURLOPT_POSTFIELDS] = json_encode($data, JSON_UNESCAPED_UNICODE);
        curl_setopt_array($ch, $curlOptions);

        $attempts = 0;
        $maxAttempts = $this->config['retry_attempts'];
        do {
            $attempts++;
            $response = curl_exec($ch);
            if ($response === false) {
                $error = curl_error($ch);
                if ($attempts >= $maxAttempts) {
                    curl_close($ch);
                    return $this->buildErrorResponse('CURL_ERROR', "Request failed: {$error}", [
                        'url' => $url, 'method' => $method, 'attempts' => $attempts
                    ]);
                }
                usleep(1000000 * $attempts);
                continue;
            }
            break;
        } while ($attempts < $maxAttempts);

        $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $httpCode   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $headerStr  = substr($response, 0, $headerSize);
        $body       = substr($response, $headerSize);
        curl_close($ch);

        $headers = $this->parseHeaders($headerStr);
        $parsedBody = strlen($body) ? (json_decode($body, true) ?? $body) : null;
        $duration = microtime(true) - $startTime;

        if ($httpCode === 429 && $attempts < $maxAttempts) {
            $retryAfter = (int)($headers['retry-after'] ?? 1);
            sleep($retryAfter);
            return $this->request($method, $endpoint, $data, $options);
        }

        $this->logRequest($method, $url, $httpCode, $duration, $requestId);
        $success = $httpCode >= 200 && $httpCode < 300;

        return [
            'success' => $success,
            'status_code' => $httpCode,
            'headers' => $headers,
            'data' => $parsedBody,
            'duration' => $duration,
            'request_id' => $requestId,
            'attempts' => $attempts
        ];
    }

    private function parseHeaders(string $headerString): array {
        $headers = [];
        foreach (explode("\r\n", $headerString) as $line) {
            if (strpos($line, ':') !== false) {
                [$k, $v] = explode(':', $line, 2);
                $headers[strtolower(trim($k))] = trim($v);
            }
        }
        return $headers;
    }

    private function logRequest(string $method, string $url, int $statusCode, float $duration, string $requestId): void {
        error_log('HTTP_REQUEST: ' . json_encode([
            'timestamp' => date('Y-m-d H:i:s'),
            'request_id' => $requestId,
            'method' => $method,
            'url' => $url,
            'status_code' => $statusCode,
            'duration_ms' => round($duration * 1000, 2),
            'success' => $statusCode >= 200 && $statusCode < 300
        ]));
    }

    private function buildErrorResponse(string $code, string $msg, array $ctx = []): array {
        return [
            'success' => false,
            'error' => ['code' => $code, 'message' => $msg, 'context' => $ctx],
            'status_code' => 0,
            'data' => null,
            'duration' => 0,
            'request_id' => getRequestId(),
            'attempts' => 1
        ];
    }
}


// ========================================================================
// ENTERPRISE RESPONSE HANDLERS
// ========================================================================

/**
 * Send successful JSON response
 */
function sendSuccess(array $data = [], array $meta = []): void {
    $response = [
        'success' => true,
        'data' => $data,
        'meta' => array_merge([
            'timestamp' => date('c'),
            'request_id' => getRequestId(),
            'api_version' => API_VERSION
        ], $meta)
    ];
    
    header('Content-Type: application/json; charset=utf-8');
    header('X-Request-ID: ' . getRequestId());
    header('X-API-Version: ' . API_VERSION);
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/**
 * Send error JSON response
 */
function sendError(string $code, string $message, array $details = [], int $httpCode = 400): void {
    $response = [
        'success' => false,
        'error' => [
            'code' => $code,
            'message' => $message,
            'details' => $details
        ],
        'meta' => [
            'timestamp' => date('c'),
            'request_id' => getRequestId(),
            'api_version' => API_VERSION
        ]
    ];
    
    header('Content-Type: application/json; charset=utf-8');
    header('X-Request-ID: ' . getRequestId());
    header('X-API-Version: ' . API_VERSION);
    http_response_code($httpCode);
    
    echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

// ========================================================================
// VEND LIGHTSPEED API ENDPOINTS COLLECTION
// ========================================================================

/**
 * Enterprise Vend API Service
 */
class VendLightspeedAPI {
    private LightspeedHttpClient $client;
    private array $config;
    
    public function __construct() {
        global $LIGHTSPEED_CONFIG;
        $this->client = new LightspeedHttpClient();
        $this->config = $LIGHTSPEED_CONFIG;
    }
    
    // ====================================================================
    // CONSIGNMENT ENDPOINTS
    // ====================================================================
    
    /**
     * Get single consignment
     * 
     * @param string $consignmentId Consignment ID
     * @return array API response
     */
    public function getConsignment(string $consignmentId): array {
        if (empty($consignmentId)) {
            return $this->buildError('INVALID_CONSIGNMENT_ID', 'Consignment ID is required');
        }
        
        $response = $this->client->request('GET', "consignments/{$consignmentId}");
        
        if ($response['success']) {
            return $this->buildSuccess($response['data'], [
                'endpoint' => 'get_consignment',
                'consignment_id' => $consignmentId,
                'ui_url' => $this->buildConsignmentUrl($consignmentId)
            ]);
        }
        
        return $this->buildError('CONSIGNMENT_NOT_FOUND', 'Consignment not found or inaccessible', $response);
    }
    
    /**
     * Create new consignment
     * 
     * @param array $data Consignment data
     * @return array API response
     */
    public function createConsignment(array $data): array {
        // Validate required fields
        $required = ['outlet_id', 'type'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return $this->buildError('MISSING_REQUIRED_FIELD', "Field '{$field}' is required");
            }
        }
        
        // Validate type
        $validTypes = ['SUPPLIER', 'OUTLET', 'RETURN'];
        if (!in_array(strtoupper($data['type']), $validTypes)) {
            return $this->buildError('INVALID_TYPE', 'Type must be one of: ' . implode(', ', $validTypes));
        }
        
        // Prepare payload
        $payload = [
            'outlet_id' => $data['outlet_id'],
            'type' => strtoupper($data['type']),
            'status' => $data['status'] ?? 'OPEN',
            'name' => $data['name'] ?? null,
            'reference' => $data['reference'] ?? null
        ];
        
        if (isset($data['source_outlet_id'])) {
            $payload['source_outlet_id'] = $data['source_outlet_id'];
        }
        
        if (isset($data['supplier_id'])) {
            $payload['supplier_id'] = $data['supplier_id'];
        }
        
        $response = $this->client->request('POST', 'consignments', $payload);
        
        if ($response['success']) {
            $consignmentId = $response['data']['id'] ?? null;
            return $this->buildSuccess($response['data'], [
                'endpoint' => 'create_consignment',
                'consignment_id' => $consignmentId,
                'ui_url' => $consignmentId ? $this->buildConsignmentUrl($consignmentId) : null
            ]);
        }
        
        return $this->buildError('CONSIGNMENT_CREATION_FAILED', 'Failed to create consignment', $response);
    }
    
    /**
     * Update consignment
     * 
     * @param string $consignmentId Consignment ID
     * @param array $data Update data
     * @return array API response
     */
    public function updateConsignment(string $consignmentId, array $data): array {
        if (empty($consignmentId)) {
            return $this->buildError('INVALID_CONSIGNMENT_ID', 'Consignment ID is required');
        }
        
        // Get current consignment data to preserve required fields
        $current = $this->client->request('GET', "consignments/{$consignmentId}");
        if (!$current['success']) {
            return $this->buildError('CONSIGNMENT_NOT_FOUND', 'Cannot update: consignment not found');
        }
        
        $currentData = $current['data'];
        
        // Prepare update payload
        $payload = [
            'type' => $data['type'] ?? $currentData['type'],
            'outlet_id' => $data['outlet_id'] ?? $currentData['outlet_id'],
            'status' => $data['status'] ?? $currentData['status'],
            'name' => $data['name'] ?? $currentData['name'],
            'reference' => $data['reference'] ?? $currentData['reference']
        ];
        
        if (isset($currentData['source_outlet_id'])) {
            $payload['source_outlet_id'] = $data['source_outlet_id'] ?? $currentData['source_outlet_id'];
        }
        
        if (isset($currentData['supplier_id'])) {
            $payload['supplier_id'] = $data['supplier_id'] ?? $currentData['supplier_id'];
        }
        
        $response = $this->client->request('PUT', "consignments/{$consignmentId}", $payload);
        
        if ($response['success']) {
            return $this->buildSuccess($response['data'], [
                'endpoint' => 'update_consignment',
                'consignment_id' => $consignmentId,
                'ui_url' => $this->buildConsignmentUrl($consignmentId)
            ]);
        }
        
        return $this->buildError('CONSIGNMENT_UPDATE_FAILED', 'Failed to update consignment', $response);
    }
    
    /**
     * Delete consignment
     * 
     * @param string $consignmentId Consignment ID
     * @return array API response
     */
    public function deleteConsignment(string $consignmentId): array {
        if (empty($consignmentId)) {
            return $this->buildError('INVALID_CONSIGNMENT_ID', 'Consignment ID is required');
        }
        
        $response = $this->client->request('DELETE', "consignments/{$consignmentId}");
        
        if ($response['success']) {
            return $this->buildSuccess(null, [
                'endpoint' => 'delete_consignment',
                'consignment_id' => $consignmentId,
                'message' => 'Consignment deleted successfully'
            ]);
        }
        
        return $this->buildError('CONSIGNMENT_DELETE_FAILED', 'Failed to delete consignment', $response);
    }
    
    /**
     * Get consignment totals
     * 
     * @param string $consignmentId Consignment ID
     * @return array API response
     */
    public function getConsignmentTotals(string $consignmentId): array {
        if (empty($consignmentId)) {
            return $this->buildError('INVALID_CONSIGNMENT_ID', 'Consignment ID is required');
        }
        
        $response = $this->client->request('GET', "consignments/{$consignmentId}/totals");
        
        if ($response['success']) {
            return $this->buildSuccess($response['data'], [
                'endpoint' => 'get_consignment_totals',
                'consignment_id' => $consignmentId
            ]);
        }
        
        return $this->buildError('TOTALS_NOT_FOUND', 'Consignment totals not found', $response);
    }
    
    // ====================================================================
    // CONSIGNMENT PRODUCT ENDPOINTS
    // ====================================================================
    
    /**
     * List consignment products
     * 
     * @param string $consignmentId Consignment ID
     * @param array $params Query parameters
     * @return array API response
     */
    public function listConsignmentProducts(string $consignmentId, array $params = []): array {
        if (empty($consignmentId)) {
            return $this->buildError('INVALID_CONSIGNMENT_ID', 'Consignment ID is required');
        }
        
        $endpoint = "consignments/{$consignmentId}/products";
        
        // Add query parameters if provided
        if (!empty($params)) {
            $endpoint .= '?' . http_build_query($params);
        }
        
        $response = $this->client->request('GET', $endpoint);
        
        if ($response['success']) {
            return $this->buildSuccess($response['data'], [
                'endpoint' => 'list_consignment_products',
                'consignment_id' => $consignmentId,
                'count' => is_array($response['data']) ? count($response['data']) : 0
            ]);
        }
        
        return $this->buildError('PRODUCTS_NOT_FOUND', 'Consignment products not found', $response);
    }
    
    /**
     * Add product to consignment
     * 
     * @param string $consignmentId Consignment ID
     * @param array $data Product data
     * @return array API response
     */
    public function addConsignmentProduct(string $consignmentId, array $data): array {
        if (empty($consignmentId)) {
            return $this->buildError('INVALID_CONSIGNMENT_ID', 'Consignment ID is required');
        }
        
        // Validate required fields
        $required = ['product_id', 'count'];
        foreach ($required as $field) {
            if (!isset($data[$field])) {
                return $this->buildError('MISSING_REQUIRED_FIELD', "Field '{$field}' is required");
            }
        }
        
        // Validate count
        if ((int)$data['count'] <= 0) {
            return $this->buildError('INVALID_COUNT', 'Count must be greater than 0');
        }
        
        $payload = [
            'product_id' => $data['product_id'],
            'count' => (int)$data['count']
        ];
        
        if (isset($data['received'])) {
            $payload['received'] = (int)$data['received'];
        }
        
        if (isset($data['cost'])) {
            $payload['cost'] = (float)$data['cost'];
        }
        
        $response = $this->client->request('POST', "consignments/{$consignmentId}/products", $payload);
        
        if ($response['success']) {
            return $this->buildSuccess($response['data'], [
                'endpoint' => 'add_consignment_product',
                'consignment_id' => $consignmentId,
                'product_id' => $data['product_id']
            ]);
        }
        
        return $this->buildError('PRODUCT_ADD_FAILED', 'Failed to add product to consignment', $response);
    }
    
    /**
     * Update consignment product
     * 
     * @param string $consignmentId Consignment ID
     * @param string $productId Product ID
     * @param array $data Update data
     * @return array API response
     */
    public function updateConsignmentProduct(string $consignmentId, string $productId, array $data): array {
        if (empty($consignmentId)) {
            return $this->buildError('INVALID_CONSIGNMENT_ID', 'Consignment ID is required');
        }
        
        if (empty($productId)) {
            return $this->buildError('INVALID_PRODUCT_ID', 'Product ID is required');
        }
        
        $payload = [];
        
        if (isset($data['count'])) {
            $payload['count'] = (int)$data['count'];
        }
        
        if (isset($data['received'])) {
            $payload['received'] = (int)$data['received'];
        }
        
        if (isset($data['cost'])) {
            $payload['cost'] = (float)$data['cost'];
        }
        
        if (empty($payload)) {
            return $this->buildError('NO_UPDATE_DATA', 'No valid update data provided');
        }
        
        $response = $this->client->request('PUT', "consignments/{$consignmentId}/products/{$productId}", $payload);
        
        if ($response['success']) {
            return $this->buildSuccess($response['data'], [
                'endpoint' => 'update_consignment_product',
                'consignment_id' => $consignmentId,
                'product_id' => $productId
            ]);
        }
        
        return $this->buildError('PRODUCT_UPDATE_FAILED', 'Failed to update consignment product', $response);
    }
    
    /**
     * Remove product from consignment
     * 
     * @param string $consignmentId Consignment ID
     * @param string $productId Product ID
     * @return array API response
     */
    public function removeConsignmentProduct(string $consignmentId, string $productId): array {
        if (empty($consignmentId)) {
            return $this->buildError('INVALID_CONSIGNMENT_ID', 'Consignment ID is required');
        }
        
        if (empty($productId)) {
            return $this->buildError('INVALID_PRODUCT_ID', 'Product ID is required');
        }
        
        $response = $this->client->request('DELETE', "consignments/{$consignmentId}/products/{$productId}");
        
        if ($response['success']) {
            return $this->buildSuccess(null, [
                'endpoint' => 'remove_consignment_product',
                'consignment_id' => $consignmentId,
                'product_id' => $productId,
                'message' => 'Product removed from consignment successfully'
            ]);
        }
        
        return $this->buildError('PRODUCT_REMOVE_FAILED', 'Failed to remove product from consignment', $response);
    }
    
    // ====================================================================
    // PRODUCT ENDPOINTS
    // ====================================================================
    
    /**
     * List products
     * 
     * @param array $params Query parameters
     * @return array API response
     */
    public function listProducts(array $params = []): array {
        $endpoint = 'products';
        
        // Add query parameters if provided
        if (!empty($params)) {
            $endpoint .= '?' . http_build_query($params);
        }
        
        $response = $this->client->request('GET', $endpoint);
        
        if ($response['success']) {
            return $this->buildSuccess($response['data'], [
                'endpoint' => 'list_products',
                'count' => is_array($response['data']) ? count($response['data']) : 0,
                'params' => $params
            ]);
        }
        
        return $this->buildError('PRODUCTS_LIST_FAILED', 'Failed to retrieve products', $response);
    }
    
    /**
     * Get single product
     * 
     * @param string $productId Product ID
     * @return array API response
     */
    public function getProduct(string $productId): array {
        if (empty($productId)) {
            return $this->buildError('INVALID_PRODUCT_ID', 'Product ID is required');
        }
        
        $response = $this->client->request('GET', "products/{$productId}");
        
        if ($response['success']) {
            return $this->buildSuccess($response['data'], [
                'endpoint' => 'get_product',
                'product_id' => $productId
            ]);
        }
        
        return $this->buildError('PRODUCT_NOT_FOUND', 'Product not found', $response);
    }
    
    // ====================================================================
    // OUTLET ENDPOINTS
    // ====================================================================
    
    /**
     * List outlets
     * 
     * @param array $params Query parameters
     * @return array API response
     */
    public function listOutlets(array $params = []): array {
        $endpoint = 'outlets';
        
        // Add query parameters if provided
        if (!empty($params)) {
            $endpoint .= '?' . http_build_query($params);
        }
        
        $response = $this->client->request('GET', $endpoint);
        
        if ($response['success']) {
            return $this->buildSuccess($response['data'], [
                'endpoint' => 'list_outlets',
                'count' => is_array($response['data']) ? count($response['data']) : 0
            ]);
        }
        
        return $this->buildError('OUTLETS_LIST_FAILED', 'Failed to retrieve outlets', $response);
    }
    
    /**
     * Get single outlet
     * 
     * @param string $outletId Outlet ID
     * @return array API response
     */
    public function getOutlet(string $outletId): array {
        if (empty($outletId)) {
            return $this->buildError('INVALID_OUTLET_ID', 'Outlet ID is required');
        }
        
        $response = $this->client->request('GET', "outlets/{$outletId}");
        
        if ($response['success']) {
            return $this->buildSuccess($response['data'], [
                'endpoint' => 'get_outlet',
                'outlet_id' => $outletId
            ]);
        }
        
        return $this->buildError('OUTLET_NOT_FOUND', 'Outlet not found', $response);
    }
    
    // ====================================================================
    // SUPPLIER ENDPOINTS
    // ====================================================================
    
    /**
     * List suppliers
     * 
     * @param array $params Query parameters
     * @return array API response
     */
    public function listSuppliers(array $params = []): array {
        $endpoint = 'suppliers';
        
        // Add query parameters if provided
        if (!empty($params)) {
            $endpoint .= '?' . http_build_query($params);
        }
        
        $response = $this->client->request('GET', $endpoint);
        
        if ($response['success']) {
            return $this->buildSuccess($response['data'], [
                'endpoint' => 'list_suppliers',
                'count' => is_array($response['data']) ? count($response['data']) : 0
            ]);
        }
        
        return $this->buildError('SUPPLIERS_LIST_FAILED', 'Failed to retrieve suppliers', $response);
    }
    
    /**
     * Get single supplier
     * 
     * @param string $supplierId Supplier ID
     * @return array API response
     */
    public function getSupplier(string $supplierId): array {
        if (empty($supplierId)) {
            return $this->buildError('INVALID_SUPPLIER_ID', 'Supplier ID is required');
        }
        
        $response = $this->client->request('GET', "suppliers/{$supplierId}");
        
        if ($response['success']) {
            return $this->buildSuccess($response['data'], [
                'endpoint' => 'get_supplier',
                'supplier_id' => $supplierId
            ]);
        }
        
        return $this->buildError('SUPPLIER_NOT_FOUND', 'Supplier not found', $response);
    }
    
    // ====================================================================
    // INVENTORY ENDPOINTS
    // ====================================================================
    
    /**
     * Get product inventory
     * 
     * @param string $productId Product ID
     * @param array $params Query parameters
     * @return array API response
     */
    public function getProductInventory(string $productId, array $params = []): array {
        if (empty($productId)) {
            return $this->buildError('INVALID_PRODUCT_ID', 'Product ID is required');
        }
        
        $endpoint = "products/{$productId}/inventory";
        
        // Add query parameters if provided
        if (!empty($params)) {
            $endpoint .= '?' . http_build_query($params);
        }
        
        $response = $this->client->request('GET', $endpoint);
        
        if ($response['success']) {
            return $this->buildSuccess($response['data'], [
                'endpoint' => 'get_product_inventory',
                'product_id' => $productId
            ]);
        }
        
        return $this->buildError('INVENTORY_NOT_FOUND', 'Product inventory not found', $response);
    }
    
    // ====================================================================
    // UTILITY METHODS
    // ====================================================================
    
    /**
     * Build success response
     */
    private function buildSuccess($data, array $meta = []): array {
        return [
            'success' => true,
            'data' => $data,
            'meta' => $meta
        ];
    }
    
    /**
     * Build error response
     */
    private function buildError(string $code, string $message, $context = null): array {
        return [
            'success' => false,
            'error' => [
                'code' => $code,
                'message' => $message,
                'context' => $context
            ]
        ];
    }
    
    /**
     * Build consignment UI URL
     */
    private function buildConsignmentUrl(string $consignmentId): string {
        return rtrim($this->config['ui_base'], '/') . '/consignments/' . rawurlencode($consignmentId);
    }
}

// ========================================================================
// API REQUEST HANDLER
// ========================================================================

/**
 * Main API request handler
 */
function handleApiRequest(): void {
    $startTime = microtime(true);
    
    // Only allow POST requests
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        sendError('METHOD_NOT_ALLOWED', 'Only POST requests are allowed', [], 405);
    }
    
    // Parse JSON input
    $rawInput = file_get_contents('php://input');
    $request = json_decode($rawInput, true);
    
    if ($request === null) {
        sendError('INVALID_JSON', 'Request body must be valid JSON');
    }
    
    // Validate authentication
    if (!validateAuthentication($request)) {
        sendError('AUTHENTICATION_FAILED', 'Invalid PIN or rate limit exceeded', [], 401);
    }
    
    // Get action
    $action = $request['action'] ?? '';
    if (empty($action)) {
        sendError('MISSING_ACTION', 'Action parameter is required');
    }
    
    // Initialize API service
    $vendApi = new VendLightspeedAPI();
    $result = null;
    
    try {
        // Route to appropriate endpoint
        switch ($action) {
            // API Information
            case 'api_info':
                $result = [
                    'success' => true,
                    'data' => [
                        'api_name' => API_NAME,
                        'version' => API_VERSION,
                        'timestamp' => date('c'),
                        'endpoints' => [
                            'consignments' => [
                                'get_consignment',
                                'create_consignment',
                                'update_consignment',
                                'delete_consignment',
                                'get_consignment_totals'
                            ],
                            'consignment_products' => [
                                'list_consignment_products',
                                'add_consignment_product',
                                'update_consignment_product',
                                'remove_consignment_product'
                            ],
                            'products' => [
                                'list_products',
                                'get_product',
                                'get_product_inventory'
                            ],
                            'outlets' => [
                                'list_outlets',
                                'get_outlet'
                            ],
                            'suppliers' => [
                                'list_suppliers',
                                'get_supplier'
                            ]
                        ]
                    ]
                ];
                break;
                
            // Consignment endpoints
            case 'get_consignment':
                $result = $vendApi->getConsignment($request['id'] ?? '');
                break;
                
            case 'create_consignment':
                $result = $vendApi->createConsignment($request['data'] ?? []);
                break;
                
            case 'update_consignment':
                $result = $vendApi->updateConsignment($request['id'] ?? '', $request['data'] ?? []);
                break;
                
            case 'delete_consignment':
                $result = $vendApi->deleteConsignment($request['id'] ?? '');
                break;
                
            case 'get_consignment_totals':
                $result = $vendApi->getConsignmentTotals($request['id'] ?? '');
                break;
                
            // Consignment product endpoints
            case 'list_consignment_products':
                $result = $vendApi->listConsignmentProducts($request['id'] ?? '', $request['params'] ?? []);
                break;
                
            case 'add_consignment_product':
                $result = $vendApi->addConsignmentProduct($request['id'] ?? '', $request['data'] ?? []);
                break;
                
            case 'update_consignment_product':
                $result = $vendApi->updateConsignmentProduct(
                    $request['id'] ?? '',
                    $request['product_id'] ?? '',
                    $request['data'] ?? []
                );
                break;
                
            case 'remove_consignment_product':
                $result = $vendApi->removeConsignmentProduct($request['id'] ?? '', $request['product_id'] ?? '');
                break;
                
            // Product endpoints
            case 'list_products':
                $result = $vendApi->listProducts($request['params'] ?? []);
                break;
                
            case 'get_product':
                $result = $vendApi->getProduct($request['id'] ?? '');
                break;
                
            case 'get_product_inventory':
                $result = $vendApi->getProductInventory($request['id'] ?? '', $request['params'] ?? []);
                break;
                
            // Outlet endpoints
            case 'list_outlets':
                $result = $vendApi->listOutlets($request['params'] ?? []);
                break;
                
            case 'get_outlet':
                $result = $vendApi->getOutlet($request['id'] ?? '');
                break;
                
            // Supplier endpoints
            case 'list_suppliers':
                $result = $vendApi->listSuppliers($request['params'] ?? []);
                break;
                
            case 'get_supplier':
                $result = $vendApi->getSupplier($request['id'] ?? '');
                break;
            
            // Transfer auto-save endpoint
            case 'autosave_transfer':
                $transferId = $request['transfer_id'] ?? null;
                $items = $request['items'] ?? [];
                
                if (!$transferId || !is_array($items)) {
                    $result = ['success' => false, 'error' => ['code' => 'INVALID_DATA', 'message' => 'Missing transfer_id or items']];
                    break;
                }
                
                try {
                    global $pdo;
                    
                    // Save draft data to transfers table
                    $stmt = $pdo->prepare("
                        UPDATE transfers 
                        SET draft_data = ?,
                            draft_updated_at = NOW()
                        WHERE id = ?
                    ");
                    
                    $draftJson = json_encode($items);
                    $stmt->execute([$draftJson, (int)$transferId]);
                    
                    // Get the updated timestamp
                    $stmt = $pdo->prepare("
                        SELECT draft_updated_at 
                        FROM transfers 
                        WHERE id = ?
                    ");
                    $stmt->execute([(int)$transferId]);
                    $row = $stmt->fetch(PDO::FETCH_ASSOC);
                    
                    $result = [
                        'success' => true,
                        'data' => [
                            'updated_at' => $row['draft_updated_at'] ?? date('Y-m-d H:i:s'),
                            'items_count' => count($items)
                        ]
                    ];
                } catch (Exception $e) {
                    $result = [
                        'success' => false,
                        'error' => [
                            'code' => 'SAVE_FAILED',
                            'message' => 'Failed to save transfer data: ' . $e->getMessage()
                        ]
                    ];
                }
                break;
                
            default:
                sendError('UNKNOWN_ACTION', "Unknown action: {$action}", ['available_actions' => [
                    'api_info', 'get_consignment', 'create_consignment', 'update_consignment',
                    'delete_consignment', 'get_consignment_totals', 'list_consignment_products',
                    'add_consignment_product', 'update_consignment_product', 'remove_consignment_product',
                    'list_products', 'get_product', 'get_product_inventory', 'list_outlets',
                    'get_outlet', 'list_suppliers', 'get_supplier'
                ]]);
        }
        
        // Calculate duration and log
        $duration = microtime(true) - $startTime;
        logApiCall($action, $request, $result, $duration);
        
        // Send response
        if ($result['success']) {
            sendSuccess($result['data'] ?? [], $result['meta'] ?? []);
        } else {
            $error = $result['error'];
            sendError($error['code'], $error['message'], $error['context'] ?? []);
        }
        
    } catch (Exception $e) {
        logError('UNEXPECTED_ERROR', [
            'action' => $action,
            'message' => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine()
        ]);
        
        sendError('INTERNAL_ERROR', 'An internal error occurred', [], 500);
    }
}

// ========================================================================
// MAIN EXECUTION
// ========================================================================

// Set error reporting for production
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Set headers for security
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Handle API request
handleApiRequest();
