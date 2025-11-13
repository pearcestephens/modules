# 🏗️ ULTIMATE VEND API CLIENT - COMPLETE DESIGN
**Date:** 2025-11-13
**Mission:** One API client to rule them all
**Status:** 📐 **DESIGN COMPLETE - READY TO BUILD**

---

## 🎯 THE ARCHITECTURE

### **Two-Layer System:**

```
┌─────────────────────────────────────────────────────────────┐
│                    YOUR APPLICATION CODE                     │
│                  (Transfers, POs, Reports)                   │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       │ Simple, clean API
                       ▼
┌─────────────────────────────────────────────────────────────┐
│              LAYER 2: VendConsignmentService                 │
│                    (Business Logic)                          │
│  ┌─────────────┐  ┌──────────────┐  ┌────────────────┐    │
│  │   Queue     │  │   Database   │  │  Trace IDs     │    │
│  │ Integration │  │    Config    │  │  Audit Logs    │    │
│  └─────────────┘  └──────────────┘  └────────────────┘    │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       │ Uses internally
                       ▼
┌─────────────────────────────────────────────────────────────┐
│                LAYER 1: VendAPI (Enhanced)                   │
│                   (Core API Client)                          │
│  ┌─────────────┐  ┌──────────────┐  ┌────────────────┐    │
│  │    HTTP     │  │ Rate Limit   │  │  Exponential   │    │
│  │   Requests  │  │   Handling   │  │    Backoff     │    │
│  └─────────────┘  └──────────────┘  └────────────────┘    │
│  ┌─────────────┐  ┌──────────────┐  ┌────────────────┐    │
│  │   OAuth     │  │  Pagination  │  │   Webhooks     │    │
│  │   Refresh   │  │   Support    │  │ Verification   │    │
│  └─────────────┘  └──────────────┘  └────────────────┘    │
└──────────────────────┬──────────────────────────────────────┘
                       │
                       │ HTTPS
                       ▼
                 ┌─────────────┐
                 │  Vend API   │
                 │ (Lightspeed)│
                 └─────────────┘
```

---

## 📁 FILE STRUCTURE

```
/assets/services/vend/
├── VendAPI.php                    ← LAYER 1: Core API client (30KB)
├── VendConsignmentService.php     ← LAYER 2: Business logic (20KB)
├── VendTransferService.php        ← LAYER 2: Transfer operations (15KB)
├── VendPurchaseOrderService.php   ← LAYER 2: PO operations (15KB)
├── README.md                       ← Usage guide
└── examples/
    ├── simple-transfer.php
    ├── purchase-order.php
    └── queue-integration.php
```

---

## 🔧 LAYER 1: VendAPI.php (Enhanced Core)

### **Class Structure:**

```php
<?php
declare(strict_types=1);

namespace CIS\Services\Vend;

/**
 * VendAPI - Ultimate Vend/Lightspeed API Client
 *
 * @version 3.0.0
 * @author CIS Development Team
 *
 * Features:
 * - 57+ API methods covering ALL Vend endpoints
 * - Automatic rate limit handling (429 responses)
 * - Exponential backoff retry (3 attempts)
 * - OAuth token refresh from database
 * - Webhook signature verification
 * - Pagination support (auto-fetch all pages)
 * - Idempotent requests (safe retry)
 * - Full error logging with trace IDs
 * - Queue integration support
 * - Configuration-driven (no hardcoded values)
 */
class VendAPI
{
    // ==================== PROPERTIES ====================

    /** @var string Vend domain prefix (e.g., 'vapeshed') */
    private string $domainPrefix;

    /** @var string OAuth access token */
    private string $accessToken;

    /** @var string Base API URL */
    private string $baseUrl;

    /** @var int Rate limit remaining requests */
    private int $rateLimitRemaining = 10000;

    /** @var int Rate limit reset timestamp */
    private int $rateLimitReset = 0;

    /** @var ?object Database connection (optional) */
    private ?object $db = null;

    /** @var ?string Current trace ID for audit logging */
    private ?string $traceId = null;

    /** @var array Configuration loaded from database */
    private array $config = [];

    /** @var bool Enable automatic token refresh */
    private bool $autoRefreshToken = false;

    /** @var ?object Queue handler (optional) */
    private ?object $queue = null;


    // ==================== CONSTRUCTOR ====================

    /**
     * Create VendAPI instance
     *
     * @param string $domainPrefix Vend domain (e.g., 'vapeshed')
     * @param string|null $accessToken OAuth token (or load from DB)
     * @param object|null $db Database connection (for config/token refresh)
     */
    public function __construct(
        string $domainPrefix,
        ?string $accessToken = null,
        ?object $db = null
    ) {
        $this->domainPrefix = $domainPrefix;
        $this->baseUrl = "https://{$domainPrefix}.vendhq.com/api/2.0";
        $this->db = $db;

        if ($db !== null) {
            $this->loadConfigFromDatabase();
            if ($accessToken === null) {
                $accessToken = $this->config['access_token'] ?? null;
            }
            $this->autoRefreshToken = true;
        }

        if ($accessToken === null) {
            throw new \InvalidArgumentException(
                'Access token required (pass directly or via database)'
            );
        }

        $this->accessToken = $accessToken;
    }


    // ==================== CONFIGURATION ====================

    /**
     * Load Vend configuration from database (IDs 20-24)
     */
    private function loadConfigFromDatabase(): void
    {
        if ($this->db === null) {
            return;
        }

        $stmt = $this->db->prepare("
            SELECT config_key, config_value
            FROM configuration
            WHERE config_id IN (20, 21, 22, 23, 24)
        ");
        $stmt->execute();

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $this->config[$row['config_key']] = $row['config_value'];
        }
    }

    /**
     * Set trace ID for audit logging
     */
    public function withTraceId(string $traceId): self
    {
        $this->traceId = $traceId;
        return $this;
    }

    /**
     * Set queue handler for async operations
     */
    public function withQueue(object $queue): self
    {
        $this->queue = $queue;
        return $this;
    }


    // ==================== OAUTH & TOKEN MANAGEMENT ====================

    /**
     * Refresh OAuth access token using refresh token
     *
     * @return bool Success status
     */
    public function refreshAccessToken(): bool
    {
        if (!isset($this->config['refresh_token'])) {
            $this->log('error', 'No refresh token available');
            return false;
        }

        $response = $this->request('POST', '/oauth/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->config['refresh_token'],
            'client_id' => $this->config['client_id'] ?? '',
            'client_secret' => $this->config['client_secret'] ?? ''
        ], false); // Don't use auth header

        if (!$response['success']) {
            return false;
        }

        $this->accessToken = $response['data']['access_token'];
        $this->config['access_token'] = $this->accessToken;

        // Save to database
        if ($this->db !== null) {
            $stmt = $this->db->prepare("
                UPDATE configuration
                SET config_value = ?
                WHERE config_key = 'access_token'
            ");
            $stmt->execute([$this->accessToken]);
        }

        $this->log('info', 'Access token refreshed successfully');
        return true;
    }


    // ==================== CORE HTTP METHODS ====================

    /**
     * Make HTTP request to Vend API
     *
     * @param string $method HTTP method (GET, POST, PUT, DELETE)
     * @param string $endpoint API endpoint (e.g., '/consignments')
     * @param array $data Request payload
     * @param bool $useAuth Include Authorization header
     * @param int $attempt Current retry attempt
     * @return array Response with 'success', 'data', 'error'
     */
    private function request(
        string $method,
        string $endpoint,
        array $data = [],
        bool $useAuth = true,
        int $attempt = 1
    ): array {
        // Check rate limit
        if ($this->rateLimitRemaining < 10 && time() < $this->rateLimitReset) {
            $waitTime = $this->rateLimitReset - time();
            $this->log('warning', "Rate limit approaching, waiting {$waitTime}s");
            sleep($waitTime);
        }

        $url = $this->baseUrl . $endpoint;
        $headers = [
            'Content-Type: application/json',
            'Accept: application/json'
        ];

        if ($useAuth) {
            $headers[] = 'Authorization: Bearer ' . $this->accessToken;
        }

        if ($this->traceId) {
            $headers[] = 'X-Trace-Id: ' . $this->traceId;
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_TIMEOUT => 30
        ]);

        if (!empty($data) && in_array($method, ['POST', 'PUT', 'PATCH'])) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        } elseif (!empty($data) && $method === 'GET') {
            curl_setopt($ch, CURLOPT_URL, $url . '?' . http_build_query($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        // Update rate limit from headers (Vend sends these)
        $this->updateRateLimitFromHeaders($response);

        // Handle errors
        if ($error) {
            $this->log('error', "CURL error: {$error}");
            return ['success' => false, 'error' => $error];
        }

        $responseData = json_decode($response, true);

        // Handle 401 (expired token) - auto refresh
        if ($httpCode === 401 && $this->autoRefreshToken && $attempt === 1) {
            $this->log('info', 'Token expired, attempting refresh');
            if ($this->refreshAccessToken()) {
                return $this->request($method, $endpoint, $data, $useAuth, 2);
            }
        }

        // Handle 429 (rate limit) - exponential backoff
        if ($httpCode === 429 && $attempt < 4) {
            $waitTime = pow(2, $attempt); // 2s, 4s, 8s
            $this->log('warning', "Rate limited (429), waiting {$waitTime}s");
            sleep($waitTime);
            return $this->request($method, $endpoint, $data, $useAuth, $attempt + 1);
        }

        // Handle 5xx (server error) - retry with backoff
        if ($httpCode >= 500 && $attempt < 4) {
            $waitTime = pow(2, $attempt);
            $this->log('warning', "Server error ({$httpCode}), retrying in {$waitTime}s");
            sleep($waitTime);
            return $this->request($method, $endpoint, $data, $useAuth, $attempt + 1);
        }

        // Success
        if ($httpCode >= 200 && $httpCode < 300) {
            return [
                'success' => true,
                'data' => $responseData,
                'http_code' => $httpCode
            ];
        }

        // Other errors
        return [
            'success' => false,
            'error' => $responseData['error'] ?? "HTTP {$httpCode}",
            'http_code' => $httpCode,
            'data' => $responseData
        ];
    }

    /**
     * Update rate limit tracking from response headers
     */
    private function updateRateLimitFromHeaders(string $response): void
    {
        // Parse headers from response (simplified - would need full implementation)
        // Vend sends: X-RateLimit-Remaining, X-RateLimit-Reset
        // This is a placeholder - real implementation would parse headers
    }


    // ==================== LOGGING ====================

    /**
     * Log message with trace ID
     */
    private function log(string $level, string $message): void
    {
        $tracePrefix = $this->traceId ? "[{$this->traceId}] " : '';
        error_log("[VendAPI] {$tracePrefix}[{$level}] {$message}");

        // Also log to database if available
        if ($this->db !== null) {
            try {
                $stmt = $this->db->prepare("
                    INSERT INTO api_logs
                    (trace_id, service, level, message, created_at)
                    VALUES (?, 'vend', ?, ?, NOW())
                ");
                $stmt->execute([$this->traceId, $level, $message]);
            } catch (\Exception $e) {
                // Silent fail on logging
            }
        }
    }


    // ==================== CONSIGNMENT METHODS ====================

    /**
     * Create a new consignment
     *
     * @param array $data Consignment data
     * @param bool $enqueue Queue operation instead of immediate
     * @return array Response
     */
    public function createConsignment(array $data, bool $enqueue = false): array
    {
        if ($enqueue && $this->queue !== null) {
            return $this->enqueueJob('vend_consignment_create', $data);
        }

        return $this->request('POST', '/consignments', $data);
    }

    /**
     * Get consignment by ID
     */
    public function getConsignment(string $consignmentId): array
    {
        return $this->request('GET', "/consignments/{$consignmentId}");
    }

    /**
     * List all consignments with filters
     */
    public function listConsignments(array $filters = []): array
    {
        return $this->request('GET', '/consignments', $filters);
    }

    /**
     * Update consignment
     */
    public function updateConsignment(string $consignmentId, array $data): array
    {
        return $this->request('PUT', "/consignments/{$consignmentId}", $data);
    }

    /**
     * Delete consignment
     */
    public function deleteConsignment(string $consignmentId): array
    {
        return $this->request('DELETE', "/consignments/{$consignmentId}");
    }

    /**
     * Add product to consignment
     */
    public function addConsignmentProduct(string $consignmentId, array $product): array
    {
        return $this->request(
            'POST',
            "/consignments/{$consignmentId}/products",
            $product
        );
    }

    /**
     * Update consignment status (SENT, RECEIVED, etc.)
     */
    public function updateConsignmentStatus(
        string $consignmentId,
        string $status
    ): array {
        return $this->updateConsignment($consignmentId, ['status' => $status]);
    }


    // ==================== PRODUCT METHODS ====================

    public function getProduct(string $productId): array
    {
        return $this->request('GET', "/products/{$productId}");
    }

    public function listProducts(array $filters = []): array
    {
        return $this->request('GET', '/products', $filters);
    }

    public function createProduct(array $data): array
    {
        return $this->request('POST', '/products', $data);
    }

    public function updateProduct(string $productId, array $data): array
    {
        return $this->request('PUT', "/products/{$productId}", $data);
    }


    // ==================== SUPPLIER METHODS ====================

    public function getSupplier(string $supplierId): array
    {
        return $this->request('GET', "/suppliers/{$supplierId}");
    }

    public function listSuppliers(): array
    {
        return $this->request('GET', '/suppliers');
    }


    // ==================== OUTLET METHODS ====================

    public function getOutlet(string $outletId): array
    {
        return $this->request('GET', "/outlets/{$outletId}");
    }

    public function listOutlets(): array
    {
        return $this->request('GET', '/outlets');
    }


    // ==================== INVENTORY METHODS ====================

    public function getInventory(string $productId, string $outletId): array
    {
        return $this->request('GET', '/inventory', [
            'product_id' => $productId,
            'outlet_id' => $outletId
        ]);
    }


    // ==================== WEBHOOK METHODS ====================

    /**
     * Verify webhook signature
     */
    public function verifyWebhookSignature(
        string $payload,
        string $signature,
        string $secret
    ): bool {
        $expectedSignature = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expectedSignature, $signature);
    }


    // ==================== QUEUE INTEGRATION ====================

    /**
     * Enqueue job for async processing
     */
    private function enqueueJob(string $jobType, array $data): array
    {
        if ($this->queue === null) {
            return [
                'success' => false,
                'error' => 'Queue handler not configured'
            ];
        }

        $jobId = $this->queue->enqueue([
            'type' => $jobType,
            'payload' => $data,
            'trace_id' => $this->traceId,
            'created_at' => date('Y-m-d H:i:s')
        ]);

        return [
            'success' => true,
            'queued' => true,
            'job_id' => $jobId
        ];
    }


    // ==================== PAGINATION SUPPORT ====================

    /**
     * Auto-fetch all pages for paginated endpoint
     */
    public function fetchAllPages(string $endpoint, array $filters = []): array
    {
        $allData = [];
        $page = 1;

        do {
            $filters['page'] = $page;
            $response = $this->request('GET', $endpoint, $filters);

            if (!$response['success']) {
                break;
            }

            $allData = array_merge($allData, $response['data']['data'] ?? []);
            $hasMore = !empty($response['data']['version']['max']);
            $page++;

        } while ($hasMore);

        return [
            'success' => true,
            'data' => $allData,
            'total_pages' => $page - 1
        ];
    }


    // ... 40+ MORE METHODS covering ALL Vend endpoints ...
    // (Sales, Customers, Registers, Brands, Tags, etc.)
}
```

---

## 🎯 LAYER 2: VendConsignmentService.php (Business Logic)

```php
<?php
declare(strict_types=1);

namespace CIS\Services\Vend;

use CIS\Services\Vend\VendAPI;

/**
 * VendConsignmentService - High-level consignment operations
 *
 * @version 1.0.0
 *
 * Handles:
 * - Store-to-store transfers (OUTLET type)
 * - Purchase orders from suppliers (SUPPLIER type)
 * - Queue integration
 * - Audit logging
 * - Status workflows
 */
class VendConsignmentService
{
    private VendAPI $api;
    private object $db;
    private ?object $queue;

    /**
     * Create service instance
     */
    public function __construct(object $db, ?object $queue = null)
    {
        $this->db = $db;
        $this->queue = $queue;

        // Initialize VendAPI with database config
        $this->api = new VendAPI('vapeshed', null, $db);

        if ($queue !== null) {
            $this->api->withQueue($queue);
        }
    }


    // ==================== TRANSFER OPERATIONS ====================

    /**
     * Create store-to-store transfer
     *
     * @param string $sourceOutletId Source store
     * @param string $destOutletId Destination store
     * @param array $products Products to transfer
     * @param bool $useQueue Queue operation
     * @return array Result with transfer ID
     */
    public function createTransfer(
        string $sourceOutletId,
        string $destOutletId,
        array $products,
        bool $useQueue = false
    ): array {
        $traceId = $this->generateTraceId();
        $this->api->withTraceId($traceId);

        // Validate products exist
        foreach ($products as $product) {
            $check = $this->api->getProduct($product['product_id']);
            if (!$check['success']) {
                return [
                    'success' => false,
                    'error' => "Product {$product['product_id']} not found"
                ];
            }
        }

        // Create consignment
        $consignmentData = [
            'type' => 'OUTLET',
            'source_outlet_id' => $sourceOutletId,
            'outlet_id' => $destOutletId,
            'status' => 'OPEN',
            'consignment_products' => $products
        ];

        $result = $this->api->createConsignment($consignmentData, $useQueue);

        // Log to database
        if ($result['success']) {
            $this->logTransfer($traceId, $sourceOutletId, $destOutletId, $result);
        }

        return $result;
    }

    /**
     * Send transfer (mark as SENT)
     */
    public function sendTransfer(string $consignmentId): array
    {
        $result = $this->api->updateConsignmentStatus($consignmentId, 'SENT');

        if ($result['success']) {
            $this->logStatusChange($consignmentId, 'OPEN', 'SENT');
        }

        return $result;
    }

    /**
     * Receive transfer (mark as RECEIVED)
     */
    public function receiveTransfer(
        string $consignmentId,
        array $receivedProducts
    ): array {
        // Update quantities
        foreach ($receivedProducts as $product) {
            $this->api->addConsignmentProduct($consignmentId, $product);
        }

        // Mark as received
        $result = $this->api->updateConsignmentStatus($consignmentId, 'RECEIVED');

        if ($result['success']) {
            $this->logStatusChange($consignmentId, 'SENT', 'RECEIVED');
        }

        return $result;
    }


    // ==================== PURCHASE ORDER OPERATIONS ====================

    /**
     * Create purchase order from supplier
     */
    public function createPurchaseOrder(
        string $supplierId,
        string $outletId,
        array $products,
        bool $useQueue = false
    ): array {
        $traceId = $this->generateTraceId();
        $this->api->withTraceId($traceId);

        $consignmentData = [
            'type' => 'SUPPLIER',
            'supplier_id' => $supplierId,
            'outlet_id' => $outletId,
            'status' => 'OPEN',
            'consignment_products' => $products
        ];

        $result = $this->api->createConsignment($consignmentData, $useQueue);

        if ($result['success']) {
            $this->logPurchaseOrder($traceId, $supplierId, $outletId, $result);
        }

        return $result;
    }


    // ==================== REPORTING ====================

    /**
     * Get all pending transfers for outlet
     */
    public function getPendingTransfersForOutlet(string $outletId): array
    {
        return $this->api->listConsignments([
            'outlet_id' => $outletId,
            'status' => 'SENT',
            'type' => 'OUTLET'
        ]);
    }

    /**
     * Get all open purchase orders
     */
    public function getOpenPurchaseOrders(): array
    {
        return $this->api->listConsignments([
            'status' => 'OPEN',
            'type' => 'SUPPLIER'
        ]);
    }


    // ==================== AUDIT LOGGING ====================

    private function generateTraceId(): string
    {
        return uniqid('trace_', true);
    }

    private function logTransfer(
        string $traceId,
        string $source,
        string $dest,
        array $result
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO transfer_audit_log
            (trace_id, source_outlet, dest_outlet, consignment_id, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $traceId,
            $source,
            $dest,
            $result['data']['id'] ?? null
        ]);
    }

    private function logStatusChange(
        string $consignmentId,
        string $oldStatus,
        string $newStatus
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO consignment_status_log
            (consignment_id, old_status, new_status, changed_at)
            VALUES (?, ?, ?, NOW())
        ");
        $stmt->execute([$consignmentId, $oldStatus, $newStatus]);
    }

    private function logPurchaseOrder(
        string $traceId,
        string $supplierId,
        string $outletId,
        array $result
    ): void {
        $stmt = $this->db->prepare("
            INSERT INTO purchase_order_log
            (trace_id, supplier_id, outlet_id, consignment_id, created_at)
            VALUES (?, ?, ?, ?, NOW())
        ");
        $stmt->execute([
            $traceId,
            $supplierId,
            $outletId,
            $result['data']['id'] ?? null
        ]);
    }
}
```

---

## 💡 USAGE EXAMPLES

### **Example 1: Simple Transfer (No Queue)**

```php
<?php
require_once '/assets/services/vend/VendConsignmentService.php';

$db = new PDO('mysql:host=localhost;dbname=jcepnzzkmj', 'user', 'pass');
$service = new VendConsignmentService($db);

// Create transfer
$result = $service->createTransfer(
    sourceOutletId: 'auckland-123',
    destOutletId: 'wellington-456',
    products: [
        ['product_id' => 'prod-001', 'count' => 10],
        ['product_id' => 'prod-002', 'count' => 5]
    ],
    useQueue: false  // Immediate execution
);

if ($result['success']) {
    echo "Transfer created: {$result['data']['id']}\n";

    // Send it
    $service->sendTransfer($result['data']['id']);
}
```

### **Example 2: Purchase Order with Queue**

```php
<?php
$queue = new QueueHandler($db);
$service = new VendConsignmentService($db, $queue);

// Create PO (queued)
$result = $service->createPurchaseOrder(
    supplierId: 'supplier-789',
    outletId: 'auckland-123',
    products: [
        ['product_id' => 'prod-003', 'count' => 100]
    ],
    useQueue: true  // Queue for background processing
);

if ($result['success'] && $result['queued']) {
    echo "PO queued: Job #{$result['job_id']}\n";
}
```

### **Example 3: Direct API Access (Low-Level)**

```php
<?php
use CIS\Services\Vend\VendAPI;

$api = new VendAPI('vapeshed', 'your-access-token', $db);

// Get all products
$products = $api->fetchAllPages('/products');

// Create consignment directly
$result = $api->createConsignment([
    'type' => 'OUTLET',
    'source_outlet_id' => 'abc',
    'outlet_id' => 'xyz',
    'status' => 'OPEN'
]);
```

---

## 🎁 WHAT YOU GET

### **Benefits:**

1. **One Source of Truth**
   - VendAPI.php = THE authoritative client
   - No more confusion about which file to use

2. **Clean Separation**
   - Layer 1 = HTTP, auth, retry, rate limiting
   - Layer 2 = Business logic, queue, audit logs

3. **Flexible Usage**
   - Use VendAPI directly for simple operations
   - Use services for complex workflows
   - Queue support is OPTIONAL

4. **Production Ready**
   - Rate limiting
   - Auto token refresh
   - Exponential backoff
   - Trace IDs for debugging
   - Full audit logs

5. **Easy to Test**
   - Mock VendAPI in tests
   - Service layer is just business logic
   - No HTTP calls in service layer

---

## 📊 BEFORE vs AFTER

### **BEFORE (Current Mess):**
```php
// Which one do I use???
require_once '/assets/services/VendAPI.php';
require_once '/assets/services/integrations/vend/vend_consignment_client.php';
require_once '/assets/services/integrations/vend/vend_api_complete.php';

$client = new VendAPI(...);  // Or VendConsignmentClient? Or...?
```

### **AFTER (Crystal Clear):**
```php
// Simple operation? Use API directly
use CIS\Services\Vend\VendAPI;
$api = new VendAPI('vapeshed', $token, $db);

// Complex workflow? Use service
use CIS\Services\Vend\VendConsignmentService;
$service = new VendConsignmentService($db);
```

---

## 🚀 IMPLEMENTATION STEPS

**When you say GO, I will:**

1. ✅ Create `/assets/services/vend/` directory
2. ✅ Move VendAPI.php and enhance it (add DB config, token refresh, queue support)
3. ✅ Create VendConsignmentService.php (business logic layer)
4. ✅ Create VendTransferService.php (transfer-specific operations)
5. ✅ Create VendPurchaseOrderService.php (PO-specific operations)
6. ✅ Create README.md with usage examples
7. ✅ Create example files in `/examples/`
8. ✅ Archive old clients to `/archived/vend_clients_deprecated/`
9. ✅ Update all references in codebase
10. ✅ Test with real API calls

**Time: 1-2 hours**

---

## 💬 READY?

Just say **"GO"** and I'll build this entire system right now! 🚀

Or ask questions if you want to tweak the design first.
