# BaseAPI Complete Usage Guide

**Location:** `/modules/base/lib/BaseAPI.php`
**Namespace:** `CIS\Base\Lib`
**Version:** 6.0.0
**Last Updated:** November 2024

---

## Table of Contents

1. [Overview](#overview)
2. [Design Patterns](#design-patterns)
3. [Quick Start](#quick-start)
4. [Request Lifecycle](#request-lifecycle)
5. [Method Reference](#method-reference)
6. [Type Validation](#type-validation)
7. [Authentication & Authorization](#authentication--authorization)
8. [Rate Limiting](#rate-limiting)
9. [Error Handling](#error-handling)
10. [Logging](#logging)
11. [Best Practices](#best-practices)
12. [Advanced Patterns](#advanced-patterns)

---

## Overview

BaseAPI is an **abstract base class** that provides a standardized, secure, and performant foundation for all CIS API endpoints. It implements the **Template Method** design pattern to orchestrate request handling while allowing child classes to focus on business logic.

### Key Features

✅ **Standard Response Envelopes** - Consistent success/error formats
✅ **Type Validation** - 10+ validation types (int, string, email, url, bool, array, json, float, regex, etc.)
✅ **Security Hardening** - XSS protection, security headers, input sanitization
✅ **Performance Tracking** - Request duration and memory usage in all responses
✅ **Comprehensive Logging** - CIS Logger integration with fallback
✅ **Authentication Framework** - Overridable auth method with hook
✅ **Rate Limiting Framework** - Overridable rate limit method with hook
✅ **Error Recovery** - Proper HTTP status codes and detailed error messages
✅ **Request Parsing** - Auto-merge GET, POST, and JSON body data
✅ **Action Routing** - Automatic snake_case to camelCase conversion (get_user → handleGetUser)

---

## Design Patterns

### 1. Template Method Pattern

BaseAPI uses the **Template Method** pattern in `handleRequest()` to define the skeleton of request processing:

```php
public function handleRequest(): void {
    try {
        // 1. Validate HTTP method (POST/GET)
        $this->validateRequestMethod();

        // 2. Validate request size (max 10MB)
        $this->validateRequestSize();

        // 3. Authenticate (if required)
        if ($this->config['require_auth']) {
            $this->authenticate();
        }

        // 4. Check rate limit (if enabled)
        if ($this->config['rate_limit']) {
            $this->checkRateLimit();
        }

        // 5. Get action from request
        $action = $this->getAction();

        // 6. Parse request data (GET + POST + JSON body)
        $data = $this->parseRequestData();

        // 7. Get handler method (get_user → handleGetUser)
        $handlerMethod = $this->getHandlerMethod($action);

        // 8. Execute handler in child class
        if (!method_exists($this, $handlerMethod)) {
            throw new \Exception("Action not found: {$action}", 404);
        }

        $result = $this->$handlerMethod($data);

        // 9. Send JSON response
        $this->sendResponse($result);

    } catch (\Exception $e) {
        $this->handleException($e);
    }
}
```

**Benefits:**
- Consistent request flow across all APIs
- Child classes only implement business logic
- Easy to extend with hooks and middleware

### 2. Strategy Pattern

Authentication and rate limiting use the **Strategy** pattern - they're overridable hooks:

```php
/**
 * Override this method to implement authentication
 */
protected function authenticate(): void {
    // Default: no authentication
    // Override in child class to check session, JWT, API key, etc.
}

/**
 * Override this method to implement rate limiting
 */
protected function checkRateLimit(): void {
    // Default: no rate limiting
    // Override in child class to check Redis, database, etc.
}
```

**Benefits:**
- Each API can have different auth strategies (session, JWT, API key)
- Each API can have different rate limit strategies (per-user, per-IP, per-endpoint)
- No tight coupling to specific implementations

### 3. Envelope Pattern

All responses follow a standard envelope format:

**Success Response:**
```json
{
    "success": true,
    "data": { /* ... */ },
    "message": "Operation completed successfully",
    "timestamp": "2024-11-03 14:30:45",
    "request_id": "abc123def456",
    "meta": {
        "duration_ms": 45,
        "memory_usage": "2.5 MB"
    }
}
```

**Error Response:**
```json
{
    "success": false,
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "Missing required field: user_id",
        "details": {
            "field": "user_id",
            "required_fields": ["user_id", "action"]
        },
        "timestamp": "2024-11-03 14:30:45"
    },
    "request_id": "abc123def456"
}
```

**Benefits:**
- Predictable response structure
- Easy to parse in JavaScript
- Consistent error handling
- Performance metrics included

---

## Quick Start

### Step 1: Create Your API Class

```php
<?php
require_once __DIR__ . '/../lib/BaseAPI.php';

use CIS\Base\Lib\BaseAPI;

class UserAPI extends BaseAPI {

    // Constructor (optional - for custom config)
    public function __construct(array $config = []) {
        parent::__construct($config);
    }

    // Handler methods - one per action
    protected function handleGetUser(array $data): array {
        // Your business logic here
        return $this->success(['id' => 1, 'name' => 'John'], 'User found');
    }

    protected function handleCreateUser(array $data): array {
        // Validate inputs
        $this->validateRequired($data, ['name', 'email']);

        // Your business logic here
        return $this->success(['id' => 2], 'User created');
    }
}
```

### Step 2: Create Endpoint File

```php
<?php
// /modules/your-module/api/users.php

require_once __DIR__ . '/../../../base/lib/BaseAPI.php';
require_once __DIR__ . '/../lib/UserAPI.php';

$api = new UserAPI();
$api->handleRequest();
```

### Step 3: Call Your API

**JavaScript (Frontend):**
```javascript
// GET request
fetch('/modules/your-module/api/users.php?action=get_user&user_id=123')
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            console.log('User:', data.data);
        } else {
            console.error('Error:', data.error.message);
        }
    });

// POST request
fetch('/modules/your-module/api/users.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
        action: 'create_user',
        name: 'Jane Doe',
        email: 'jane@example.com'
    })
})
.then(response => response.json())
.then(data => console.log(data));
```

**cURL (CLI):**
```bash
# GET request
curl "http://localhost/modules/your-module/api/users.php?action=get_user&user_id=123"

# POST request
curl -X POST http://localhost/modules/your-module/api/users.php \
  -H "Content-Type: application/json" \
  -d '{"action": "create_user", "name": "Jane", "email": "jane@example.com"}'
```

---

## Request Lifecycle

Every API request goes through these 9 stages:

```
1. validateRequestMethod()
   ↓ Check if POST/GET is allowed

2. validateRequestSize()
   ↓ Check max 10MB

3. authenticate()
   ↓ If require_auth=true (overridable)

4. checkRateLimit()
   ↓ If rate_limit enabled (overridable)

5. getAction()
   ↓ Extract "action" parameter

6. parseRequestData()
   ↓ Merge GET + POST + JSON body

7. getHandlerMethod()
   ↓ Convert snake_case to camelCase (get_user → handleGetUser)

8. Execute Handler
   ↓ Call child class method

9. sendResponse()
   ↓ JSON output + security headers
```

### Handling Each Stage in Your API

You typically **only** need to:

1. ✅ **Implement handler methods** (handleGetUser, handleCreateUser, etc.)
2. ✅ **Optionally override authenticate()** if you need auth
3. ✅ **Optionally override checkRateLimit()** if you need rate limiting
4. ✅ **Use success() and error()** methods to return responses

All other stages are handled automatically by BaseAPI.

---

## Method Reference

### Constructor

```php
public function __construct(array $config = [])
```

**Parameters:**
- `$config` (array): Configuration overrides

**Default Config:**
```php
[
    'require_auth' => false,          // Require authentication?
    'allowed_methods' => ['POST'],    // Allowed HTTP methods
    'rate_limit' => false,            // Enable rate limiting?
    'log_requests' => true,           // Log all requests?
    'max_request_size' => 10485760,   // 10MB max
    'timezone' => 'Pacific/Auckland'  // Timezone for timestamps
]
```

**Example:**
```php
$api = new UserAPI([
    'require_auth' => true,
    'allowed_methods' => ['POST', 'GET'],
    'log_requests' => true
]);
```

---

### success()

```php
protected function success(
    mixed $data = null,
    string $message = 'Success',
    array $meta = []
): array
```

**Returns a success response envelope.**

**Parameters:**
- `$data`: Response data (any JSON-serializable type)
- `$message`: Success message
- `$meta`: Additional metadata

**Returns:** Array formatted as success response

**Example:**
```php
return $this->success(
    ['user_id' => 123, 'name' => 'John'],
    'User retrieved successfully',
    ['cached' => true, 'ttl' => 300]
);
```

**Response:**
```json
{
    "success": true,
    "data": {"user_id": 123, "name": "John"},
    "message": "User retrieved successfully",
    "timestamp": "2024-11-03 14:30:45",
    "request_id": "abc123",
    "meta": {
        "cached": true,
        "ttl": 300,
        "duration_ms": 45,
        "memory_usage": "2.5 MB"
    }
}
```

---

### error()

```php
protected function error(
    string $message,
    string $code = 'ERROR',
    array $details = [],
    int $httpStatus = 400
): array
```

**Returns an error response envelope.**

**Parameters:**
- `$message`: Human-readable error message
- `$code`: Machine-readable error code (e.g., 'VALIDATION_ERROR', 'NOT_FOUND')
- `$details`: Additional error details
- `$httpStatus`: HTTP status code (default: 400)

**Returns:** Array formatted as error response

**Example:**
```php
return $this->error(
    'User not found',
    'USER_NOT_FOUND',
    ['user_id' => 999],
    404
);
```

**Response:**
```json
{
    "success": false,
    "error": {
        "code": "USER_NOT_FOUND",
        "message": "User not found",
        "details": {"user_id": 999},
        "timestamp": "2024-11-03 14:30:45"
    },
    "request_id": "abc123"
}
```

**HTTP Status Code:** 404

---

### validateRequired()

```php
protected function validateRequired(array $data, array $required): void
```

**Validates that required fields are present.**

**Parameters:**
- `$data`: Input data array
- `$required`: Array of required field names

**Throws:** Exception if any required field is missing

**Example:**
```php
protected function handleCreateUser(array $data): array {
    // Throws exception if name or email is missing
    $this->validateRequired($data, ['name', 'email']);

    // Continue with business logic...
}
```

**Error Response (if validation fails):**
```json
{
    "success": false,
    "error": {
        "code": "VALIDATION_ERROR",
        "message": "Missing required field: email",
        "details": {
            "field": "email",
            "required_fields": ["name", "email"]
        }
    }
}
```

---

### validateTypes()

```php
protected function validateTypes(array $data, array $rules): void
```

**Validates field types.**

**Parameters:**
- `$data`: Input data array
- `$rules`: Array of field => type mappings

**Supported Types:**
- `int` - Integer
- `string` - String
- `email` - Valid email format
- `url` - Valid URL format
- `bool` - Boolean (true/false, 1/0, "true"/"false")
- `array` - Array
- `json` - Valid JSON string
- `float` / `double` - Floating point number
- `regex:pattern` - Custom regex pattern

**Example:**
```php
$this->validateTypes($data, [
    'user_id' => 'int',
    'email' => 'email',
    'age' => 'int',
    'website' => 'url',
    'active' => 'bool',
    'tags' => 'array',
    'metadata' => 'json',
    'price' => 'float',
    'phone' => 'regex:/^\d{3}-\d{3}-\d{4}$/'
]);
```

**Throws:** Exception with details if validation fails

**Error Response (example):**
```json
{
    "success": false,
    "error": {
        "code": "TYPE_VALIDATION_ERROR",
        "message": "Field 'email' must be a valid email",
        "details": {
            "field": "email",
            "expected_type": "email",
            "provided_value": "not-an-email"
        }
    }
}
```

---

### sanitize()

```php
protected function sanitize(mixed $value): mixed
```

**Sanitizes input to prevent XSS attacks.**

**Parameters:**
- `$value`: Value to sanitize (string, array, or other)

**Returns:** Sanitized value

**Example:**
```php
$safeName = $this->sanitize($data['name']);
$safeArray = $this->sanitize($data['user_info']);
```

**Before:**
```php
$data['name'] = '<script>alert("XSS")</script>John';
```

**After:**
```php
$safeName = '&lt;script&gt;alert(&quot;XSS&quot;)&lt;/script&gt;John';
```

---

### authenticate()

```php
protected function authenticate(): void
```

**Authentication hook - override in child class.**

**Default:** Does nothing (no authentication)

**Example (Session-based auth):**
```php
protected function authenticate(): void {
    session_start();

    if (empty($_SESSION['user_id'])) {
        throw new \Exception('Not authenticated', 401);
    }

    if ($_SESSION['user_status'] !== 'active') {
        throw new \Exception('Account inactive', 403);
    }

    $this->logInfo('User authenticated', [
        'user_id' => $_SESSION['user_id']
    ]);
}
```

**Example (API key auth):**
```php
protected function authenticate(): void {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? '';

    if (empty($apiKey)) {
        throw new \Exception('API key required', 401);
    }

    // Validate API key against database
    if (!$this->isValidApiKey($apiKey)) {
        throw new \Exception('Invalid API key', 403);
    }
}
```

---

### checkRateLimit()

```php
protected function checkRateLimit(): void
```

**Rate limiting hook - override in child class.**

**Default:** Does nothing (no rate limiting)

**Example (Simple in-memory rate limit):**
```php
private array $requestCounts = [];

protected function checkRateLimit(): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $now = time();
    $window = 60; // 1 minute
    $maxRequests = 100;

    // Initialize or clean old entries
    if (!isset($this->requestCounts[$ip])) {
        $this->requestCounts[$ip] = [];
    }

    // Remove requests outside time window
    $this->requestCounts[$ip] = array_filter(
        $this->requestCounts[$ip],
        fn($timestamp) => $timestamp > ($now - $window)
    );

    // Check limit
    if (count($this->requestCounts[$ip]) >= $maxRequests) {
        throw new \Exception('Rate limit exceeded', 429);
    }

    // Add current request
    $this->requestCounts[$ip][] = $now;
}
```

---

### Logging Methods

```php
protected function logInfo(string $message, array $context = []): void
protected function logError(string $message, array $context = []): void
protected function logWarning(string $message, array $context = []): void
```

**Log messages using CIS Logger.**

**Parameters:**
- `$message`: Log message
- `$context`: Additional context data

**Example:**
```php
$this->logInfo('User created', [
    'user_id' => $userId,
    'email' => $email
]);

$this->logError('Database connection failed', [
    'host' => $dbHost,
    'error' => $e->getMessage()
]);

$this->logWarning('Slow query detected', [
    'query' => $sql,
    'duration_ms' => $duration
]);
```

**Log Output (CIS Logger format):**
```
[2024-11-03 14:30:45] INFO: User created | user_id=123, email=john@example.com | request_id=abc123
```

---

## Type Validation

### Validation Types Reference

| Type | Description | Example Valid | Example Invalid |
|------|-------------|---------------|-----------------|
| `int` | Integer | `123`, `"456"`, `-789` | `"abc"`, `12.5`, `true` |
| `string` | String | `"hello"`, `"123"` | `null`, `[]`, `123` |
| `email` | Valid email | `"user@example.com"` | `"not-email"`, `"@example"` |
| `url` | Valid URL | `"https://example.com"` | `"not-url"`, `"example"` |
| `bool` | Boolean | `true`, `false`, `1`, `0`, `"true"`, `"false"` | `"yes"`, `2`, `null` |
| `array` | Array | `[]`, `[1,2,3]`, `["a"=>"b"]` | `"[]"`, `123`, `null` |
| `json` | Valid JSON | `'{"key":"value"}'`, `'[1,2,3]'` | `'{invalid}'`, `"not-json"` |
| `float` | Float | `12.5`, `"3.14"`, `100` | `"abc"`, `true`, `[]` |
| `double` | Double (alias for float) | Same as `float` | Same as `float` |
| `regex:pattern` | Custom regex | Depends on pattern | Depends on pattern |

### Advanced Validation Examples

**Phone Number:**
```php
$this->validateTypes($data, [
    'phone' => 'regex:/^\d{3}-\d{3}-\d{4}$/'
]);
// Valid: "555-123-4567"
// Invalid: "5551234567", "555-12-34567"
```

**Postal Code:**
```php
$this->validateTypes($data, [
    'postal_code' => 'regex:/^\d{5}(-\d{4})?$/'
]);
// Valid: "12345", "12345-6789"
// Invalid: "1234", "ABCDE"
```

**UUID:**
```php
$this->validateTypes($data, [
    'uuid' => 'regex:/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i'
]);
// Valid: "550e8400-e29b-41d4-a716-446655440000"
```

---

## Authentication & Authorization

### Pattern 1: Session-Based Auth

```php
class UserAPI extends BaseAPI {

    public function __construct(array $config = []) {
        $config['require_auth'] = true; // Enable auth
        parent::__construct($config);
    }

    protected function authenticate(): void {
        session_start();

        if (empty($_SESSION['user_id'])) {
            throw new \Exception('Please log in', 401);
        }
    }

    // Check permissions in handlers
    protected function handleDeleteUser(array $data): array {
        // Check if admin
        if (($_SESSION['user_role'] ?? '') !== 'admin') {
            return $this->error(
                'Admin access required',
                'FORBIDDEN',
                ['required_role' => 'admin'],
                403
            );
        }

        // Delete user logic...
    }
}
```

### Pattern 2: JWT Auth

```php
protected function authenticate(): void {
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

    if (!preg_match('/Bearer\s+(.*)$/i', $authHeader, $matches)) {
        throw new \Exception('Bearer token required', 401);
    }

    $token = $matches[1];

    try {
        $decoded = $this->verifyJWT($token);
        $this->userId = $decoded->user_id;
    } catch (\Exception $e) {
        throw new \Exception('Invalid token', 401);
    }
}
```

### Pattern 3: API Key Auth

```php
protected function authenticate(): void {
    $apiKey = $_SERVER['HTTP_X_API_KEY'] ?? $_GET['api_key'] ?? '';

    if (empty($apiKey)) {
        throw new \Exception('API key required', 401);
    }

    // Check database
    $validKey = $this->db->query(
        "SELECT * FROM api_keys WHERE key = ? AND active = 1",
        [$apiKey]
    );

    if (!$validKey) {
        throw new \Exception('Invalid API key', 403);
    }

    $this->logInfo('API key validated', ['key_id' => $validKey['id']]);
}
```

---

## Rate Limiting

### Pattern 1: Simple IP-Based (In-Memory)

```php
private static array $rateLimitCache = [];

protected function checkRateLimit(): void {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $window = 60; // 1 minute
    $maxRequests = 100;

    $key = $ip . ':' . floor(time() / $window);

    if (!isset(self::$rateLimitCache[$key])) {
        self::$rateLimitCache[$key] = 0;
    }

    self::$rateLimitCache[$key]++;

    if (self::$rateLimitCache[$key] > $maxRequests) {
        throw new \Exception(
            "Rate limit exceeded. Max {$maxRequests} requests per minute.",
            429
        );
    }
}
```

### Pattern 2: Redis-Based (Production)

```php
private $redis;

protected function checkRateLimit(): void {
    if (!$this->redis) {
        $this->redis = new Redis();
        $this->redis->connect('127.0.0.1', 6379);
    }

    $userId = $_SESSION['user_id'] ?? $_SERVER['REMOTE_ADDR'];
    $key = "rate_limit:{$userId}:" . date('YmdHi'); // Per minute

    $count = $this->redis->incr($key);

    if ($count === 1) {
        $this->redis->expire($key, 60); // TTL 60 seconds
    }

    if ($count > 100) {
        throw new \Exception('Rate limit exceeded', 429);
    }
}
```

---

## Error Handling

### HTTP Status Codes

BaseAPI defines these constants:

```php
const HTTP_OK = 200;
const HTTP_BAD_REQUEST = 400;
const HTTP_UNAUTHORIZED = 401;
const HTTP_FORBIDDEN = 403;
const HTTP_NOT_FOUND = 404;
const HTTP_METHOD_NOT_ALLOWED = 405;
const HTTP_INTERNAL_ERROR = 500;
```

### Error Response Best Practices

**1. Use Descriptive Error Codes**
```php
// ❌ Bad
return $this->error('Error occurred', 'ERROR');

// ✅ Good
return $this->error(
    'User not found',
    'USER_NOT_FOUND',
    ['user_id' => $userId],
    404
);
```

**2. Provide Helpful Details**
```php
return $this->error(
    'Validation failed',
    'VALIDATION_ERROR',
    [
        'field' => 'email',
        'expected' => 'valid email format',
        'provided' => $data['email'],
        'example' => 'user@example.com'
    ],
    400
);
```

**3. Use Appropriate HTTP Status**
```php
// 400 - Client error (bad input)
return $this->error('Invalid email', 'INVALID_EMAIL', [], 400);

// 401 - Not authenticated
return $this->error('Login required', 'UNAUTHORIZED', [], 401);

// 403 - Authenticated but not authorized
return $this->error('Admin only', 'FORBIDDEN', [], 403);

// 404 - Resource not found
return $this->error('User not found', 'NOT_FOUND', [], 404);

// 500 - Server error
return $this->error('Database error', 'DB_ERROR', [], 500);
```

---

## Logging

### Automatic Logging

BaseAPI automatically logs:
- ✅ Request start (action, method, size)
- ✅ Authentication attempts
- ✅ Errors and exceptions
- ✅ Request completion (duration, memory)

### Manual Logging

Use the three logging methods:

```php
// Info - Normal operations
$this->logInfo('User profile updated', [
    'user_id' => 123,
    'fields' => ['name', 'email']
]);

// Warning - Potential issues
$this->logWarning('Slow database query', [
    'query' => $sql,
    'duration_ms' => 2500
]);

// Error - Failures
$this->logError('Payment processing failed', [
    'order_id' => 456,
    'error' => $e->getMessage()
]);
```

### Log Format

CIS Logger format:
```
[2024-11-03 14:30:45] INFO: User profile updated | user_id=123, fields=["name","email"] | request_id=abc123
[2024-11-03 14:30:50] WARNING: Slow database query | query="SELECT * FROM...", duration_ms=2500 | request_id=def456
[2024-11-03 14:31:00] ERROR: Payment processing failed | order_id=456, error="Gateway timeout" | request_id=ghi789
```

---

## Best Practices

### 1. One Action = One Handler Method

```php
// ✅ Good - Clear separation
protected function handleGetUser(array $data): array { /* ... */ }
protected function handleCreateUser(array $data): array { /* ... */ }
protected function handleUpdateUser(array $data): array { /* ... */ }
protected function handleDeleteUser(array $data): array { /* ... */ }

// ❌ Bad - One method doing everything
protected function handleUser(array $data): array {
    $action = $data['sub_action'] ?? 'get';
    if ($action === 'get') { /* ... */ }
    elseif ($action === 'create') { /* ... */ }
    // ...
}
```

### 2. Validate Early, Fail Fast

```php
protected function handleCreateUser(array $data): array {
    // Validate first
    $this->validateRequired($data, ['name', 'email', 'password']);
    $this->validateTypes($data, [
        'name' => 'string',
        'email' => 'email',
        'age' => 'int'
    ]);

    // Business validation
    if (strlen($data['password']) < 8) {
        return $this->error(
            'Password too short',
            'WEAK_PASSWORD',
            ['min_length' => 8],
            400
        );
    }

    // Now do the work
    $userId = $this->createUser($data);
    return $this->success(['user_id' => $userId]);
}
```

### 3. Sanitize User Input

```php
// Always sanitize before storing
$name = $this->sanitize($data['name']);
$bio = $this->sanitize($data['bio']);

// Especially for output
$userProfile = [
    'name' => $this->sanitize($dbUser['name']),
    'bio' => $this->sanitize($dbUser['bio'])
];
```

### 4. Use Transactions for Multi-Step Operations

```php
protected function handleTransferFunds(array $data): array {
    $this->db->beginTransaction();

    try {
        // Debit from account
        $this->debit($data['from_account'], $data['amount']);

        // Credit to account
        $this->credit($data['to_account'], $data['amount']);

        // Log transaction
        $this->logTransaction($data);

        $this->db->commit();

        return $this->success(null, 'Transfer completed');

    } catch (\Exception $e) {
        $this->db->rollback();

        $this->logError('Transfer failed', [
            'from' => $data['from_account'],
            'to' => $data['to_account'],
            'error' => $e->getMessage()
        ]);

        return $this->error('Transfer failed', 'TRANSFER_ERROR', [], 500);
    }
}
```

### 5. Return Consistent Data Structures

```php
// ✅ Good - Consistent structure
protected function handleGetUser(array $data): array {
    return $this->success([
        'id' => 1,
        'name' => 'John',
        'email' => 'john@example.com'
    ]);
}

protected function handleListUsers(array $data): array {
    return $this->success([
        ['id' => 1, 'name' => 'John', 'email' => 'john@example.com'],
        ['id' => 2, 'name' => 'Jane', 'email' => 'jane@example.com']
    ]);
}

// ❌ Bad - Inconsistent structure
protected function handleGetUser(array $data): array {
    return $this->success($userData); // Plain object
}

protected function handleListUsers(array $data): array {
    return $this->success([
        'users' => $users, // Wrapped in "users" key
        'total' => count($users)
    ]);
}
```

---

## Advanced Patterns

### Pattern 1: Middleware System

Add middleware support for cross-cutting concerns:

```php
abstract class BaseAPI {
    protected array $middleware = [];

    protected function addMiddleware(callable $middleware): void {
        $this->middleware[] = $middleware;
    }

    public function handleRequest(): void {
        try {
            // Run middleware
            foreach ($this->middleware as $middleware) {
                $middleware($this);
            }

            // Continue with normal flow...
        } catch (\Exception $e) {
            $this->handleException($e);
        }
    }
}

// Usage in child class
class UserAPI extends BaseAPI {
    public function __construct(array $config = []) {
        parent::__construct($config);

        // Add CORS middleware
        $this->addMiddleware(function($api) {
            header('Access-Control-Allow-Origin: *');
        });

        // Add timing middleware
        $this->addMiddleware(function($api) {
            $start = microtime(true);
            register_shutdown_function(function() use ($start) {
                $duration = round((microtime(true) - $start) * 1000, 2);
                error_log("Request took {$duration}ms");
            });
        });
    }
}
```

### Pattern 2: Response Transformers

Transform responses before sending:

```php
abstract class BaseAPI {
    protected function transformResponse(array $response): array {
        // Override in child class to transform
        return $response;
    }

    protected function sendResponse(array $response): void {
        $response = $this->transformResponse($response);
        // ... rest of sendResponse
    }
}

// Usage
class UserAPI extends BaseAPI {
    protected function transformResponse(array $response): array {
        // Add API version to all responses
        if (isset($response['meta'])) {
            $response['meta']['api_version'] = '2.0.0';
        }
        return $response;
    }
}
```

### Pattern 3: Event Hooks

Add before/after hooks for handlers:

```php
abstract class BaseAPI {
    protected function beforeHandle(string $action, array $data): void {
        // Override to add pre-processing
    }

    protected function afterHandle(string $action, array $result): void {
        // Override to add post-processing
    }

    public function handleRequest(): void {
        try {
            $action = $this->getAction();
            $data = $this->parseRequestData();

            $this->beforeHandle($action, $data);

            $handlerMethod = $this->getHandlerMethod($action);
            $result = $this->$handlerMethod($data);

            $this->afterHandle($action, $result);

            $this->sendResponse($result);
        } catch (\Exception $e) {
            $this->handleException($e);
        }
    }
}

// Usage
class UserAPI extends BaseAPI {
    protected function beforeHandle(string $action, array $data): void {
        $this->logInfo("Starting action: {$action}", $data);
    }

    protected function afterHandle(string $action, array $result): void {
        if ($result['success']) {
            $this->logInfo("Action succeeded: {$action}");
        }
    }
}
```

---

## Summary

BaseAPI provides:

✅ **Consistent** - All APIs follow the same patterns
✅ **Secure** - Built-in XSS protection, validation, security headers
✅ **Performant** - Request tracking, memory monitoring
✅ **Extensible** - Override auth, rate limiting, add middleware
✅ **Well-Documented** - Comprehensive PHPDoc and examples
✅ **Production-Ready** - Used by 25+ endpoints in CIS

### Quick Reference

```php
// Extend BaseAPI
class MyAPI extends BaseAPI {
    // Override auth (optional)
    protected function authenticate(): void { /* ... */ }

    // Implement handlers
    protected function handleGetItem(array $data): array {
        $this->validateRequired($data, ['item_id']);
        $this->validateTypes($data, ['item_id' => 'int']);

        $item = $this->fetchItem($data['item_id']);

        if (!$item) {
            return $this->error('Item not found', 'NOT_FOUND', [], 404);
        }

        return $this->success($item, 'Item retrieved');
    }
}

// Use API
$api = new MyAPI(['require_auth' => true]);
$api->handleRequest();
```

---

**For more examples, see:** `/modules/base/examples/BaseAPI_Examples.php`

**Version:** 6.0.0
**Last Updated:** November 2024
