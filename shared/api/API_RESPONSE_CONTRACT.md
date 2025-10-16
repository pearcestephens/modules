# 📜 CIS API Response Standard Contract

**Version:** 1.0.0  
**Effective Date:** October 16, 2025  
**Status:** MANDATORY for ALL endpoints  
**Authority:** Ecigdis Limited Technical Standards

---

## 🎯 Purpose

This document defines the **MANDATORY** JSON response envelope that **ALL** CIS API endpoints must use. No exceptions. No deviations. This is the contract.

---

## ✅ Success Response Format

### Structure
```json
{
  "success": true,
  "data": <any>,
  "error": null,
  "message": "Optional success message",
  "meta": {
    "timestamp": "2025-10-16T10:30:45+00:00",
    "request_id": "a1b2c3d4e5f6...",
    "version": "1.0"
  }
}
```

### Field Definitions

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `success` | boolean | ✅ YES | Always `true` for success |
| `data` | any | ✅ YES | Response data (null if no data) |
| `error` | null | ✅ YES | Always `null` for success |
| `message` | string | ❌ NO | Optional human-readable message |
| `meta` | object | ✅ YES | Response metadata |
| `meta.timestamp` | string | ✅ YES | ISO 8601 timestamp |
| `meta.request_id` | string | ✅ YES | Unique request identifier |
| `meta.version` | string | ✅ YES | API version |

### Examples

#### Simple Success
```json
{
  "success": true,
  "data": {
    "transfer_id": 27043,
    "state": "PACKING"
  },
  "error": null,
  "meta": {
    "timestamp": "2025-10-16T10:30:45+00:00",
    "request_id": "req_1729076445_a1b2c3",
    "version": "1.0"
  }
}
```

#### Success with Message
```json
{
  "success": true,
  "data": {
    "items_processed": 45
  },
  "error": null,
  "message": "Transfer submitted successfully",
  "meta": {
    "timestamp": "2025-10-16T10:30:45+00:00",
    "request_id": "req_1729076445_a1b2c3",
    "version": "1.0"
  }
}
```

#### Success with Pagination
```json
{
  "success": true,
  "data": [
    {"id": 1, "name": "Item 1"},
    {"id": 2, "name": "Item 2"}
  ],
  "error": null,
  "meta": {
    "timestamp": "2025-10-16T10:30:45+00:00",
    "request_id": "req_1729076445_a1b2c3",
    "version": "1.0",
    "pagination": {
      "page": 1,
      "per_page": 50,
      "total": 150,
      "total_pages": 3
    }
  }
}
```

---

## ❌ Error Response Format

### Structure
```json
{
  "success": false,
  "data": null,
  "error": {
    "message": "Human-readable error message",
    "code": "MACHINE_READABLE_CODE",
    "http_code": 400,
    "details": {}
  },
  "message": "Human-readable error message",
  "meta": {
    "timestamp": "2025-10-16T10:30:45+00:00",
    "request_id": "a1b2c3d4e5f6...",
    "version": "1.0"
  }
}
```

### Field Definitions

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| `success` | boolean | ✅ YES | Always `false` for errors |
| `data` | null | ✅ YES | Always `null` for errors |
| `error` | object | ✅ YES | Error information |
| `error.message` | string | ✅ YES | Human-readable error |
| `error.code` | string | ✅ YES | Machine-readable code |
| `error.http_code` | int | ✅ YES | HTTP status code |
| `error.details` | object | ❌ NO | Additional error context |
| `message` | string | ✅ YES | Duplicate of error.message (backwards compat) |
| `meta` | object | ✅ YES | Response metadata |

### Examples

#### Validation Error
```json
{
  "success": false,
  "data": null,
  "error": {
    "message": "Validation failed",
    "code": "VALIDATION_ERROR",
    "http_code": 400,
    "details": {
      "validation_errors": {
        "transfer_id": "Transfer ID is required",
        "products": "At least one product is required"
      }
    }
  },
  "message": "Validation failed",
  "meta": {
    "timestamp": "2025-10-16T10:30:45+00:00",
    "request_id": "req_1729076445_a1b2c3",
    "version": "1.0"
  }
}
```

#### Not Found Error
```json
{
  "success": false,
  "data": null,
  "error": {
    "message": "Transfer not found",
    "code": "NOT_FOUND",
    "http_code": 404,
    "details": {
      "resource": "Transfer",
      "identifier": 99999
    }
  },
  "message": "Transfer not found",
  "meta": {
    "timestamp": "2025-10-16T10:30:45+00:00",
    "request_id": "req_1729076445_a1b2c3",
    "version": "1.0"
  }
}
```

#### Server Error (Production)
```json
{
  "success": false,
  "data": null,
  "error": {
    "message": "Internal server error",
    "code": "SERVER_ERROR",
    "http_code": 500
  },
  "message": "Internal server error",
  "meta": {
    "timestamp": "2025-10-16T10:30:45+00:00",
    "request_id": "req_1729076445_a1b2c3",
    "version": "1.0"
  }
}
```

#### Server Error (Debug Mode)
```json
{
  "success": false,
  "data": null,
  "error": {
    "message": "Internal server error",
    "code": "SERVER_ERROR",
    "http_code": 500,
    "details": {
      "exception": {
        "message": "Division by zero",
        "file": "/path/to/file.php",
        "line": 42,
        "trace": [...]
      }
    }
  },
  "message": "Internal server error",
  "meta": {
    "timestamp": "2025-10-16T10:30:45+00:00",
    "request_id": "req_1729076445_a1b2c3",
    "version": "1.0"
  }
}
```

---

## 📋 Standard Error Codes

### Client Errors (4xx)

| Code | HTTP | Description |
|------|------|-------------|
| `BAD_REQUEST` | 400 | Invalid request format |
| `UNAUTHORIZED` | 401 | Authentication required |
| `FORBIDDEN` | 403 | Insufficient permissions |
| `NOT_FOUND` | 404 | Resource not found |
| `METHOD_NOT_ALLOWED` | 405 | HTTP method not allowed |
| `CONFLICT` | 409 | Resource conflict (duplicate, state violation) |
| `VALIDATION_ERROR` | 400 | Input validation failed |
| `UNPROCESSABLE_ENTITY` | 422 | Request understood but cannot process |
| `TOO_MANY_REQUESTS` | 429 | Rate limit exceeded |

### Server Errors (5xx)

| Code | HTTP | Description |
|------|------|-------------|
| `SERVER_ERROR` | 500 | Internal server error |
| `SERVICE_UNAVAILABLE` | 503 | Service temporarily unavailable |

---

## 🔧 PHP Implementation

### Using StandardResponse Class

```php
<?php
require_once __DIR__ . '/../../shared/api/StandardResponse.php';

use CIS\API\StandardResponse;

// Success response
StandardResponse::success([
    'transfer_id' => 27043,
    'state' => 'PACKING'
], 'Transfer submitted successfully');

// Error response
StandardResponse::error(
    'Transfer not found',
    404,
    'NOT_FOUND',
    ['resource' => 'Transfer', 'identifier' => 99999]
);

// Validation error
StandardResponse::validationError([
    'transfer_id' => 'Required field',
    'products' => 'At least one product required'
]);

// Not found shorthand
StandardResponse::notFound('Transfer', 27043);

// Unauthorized shorthand
StandardResponse::unauthorized();

// Server error with exception
try {
    // ... code ...
} catch (\Exception $e) {
    StandardResponse::serverError('Operation failed', $e);
}
```

### Using Global Helper Functions

```php
<?php
require_once __DIR__ . '/../../shared/api/StandardResponse.php';

// Success
apiSuccess(['user_id' => 123], 'Login successful');

// Error
apiError('Invalid credentials', 401, 'INVALID_CREDENTIALS');

// Validation error
apiValidationError(['email' => 'Invalid format']);

// Not found
apiNotFound('User', 123);

// Parse request data
$data = getRequestData();  // Handles JSON, POST, GET
```

---

## ✅ Compliance Requirements

### ALL API Endpoints MUST:

1. ✅ **Use StandardResponse class** or helper functions
2. ✅ **Return exact JSON structure** (no variations)
3. ✅ **Set correct HTTP status codes**
4. ✅ **Include request_id** in meta
5. ✅ **Use standard error codes** from table
6. ✅ **Never expose sensitive data** in errors
7. ✅ **Log errors server-side** before responding
8. ✅ **Set Content-Type: application/json**

### Checklist for New Endpoints

```php
<?php
// ✅ Include StandardResponse
require_once __DIR__ . '/../../shared/api/StandardResponse.php';

use CIS\API\StandardResponse;

// ✅ Set content type (StandardResponse does this automatically)

try {
    // ✅ Parse request data
    $data = StandardResponse::getRequestData();
    
    // ✅ Validate input
    if (empty($data['transfer_id'])) {
        StandardResponse::validationError(['transfer_id' => 'Required field']);
    }
    
    // ✅ Business logic
    $result = processTransfer($data['transfer_id']);
    
    // ✅ Success response
    StandardResponse::success($result, 'Transfer processed successfully');
    
} catch (\Exception $e) {
    // ✅ Error response
    StandardResponse::serverError('Processing failed', $e);
}
```

---

## 🧪 Testing Contract Compliance

### Response Validation Schema (JSON Schema)

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "type": "object",
  "required": ["success", "data", "error", "meta"],
  "properties": {
    "success": {
      "type": "boolean"
    },
    "data": {
      "type": ["object", "array", "string", "number", "boolean", "null"]
    },
    "error": {
      "type": ["object", "null"],
      "properties": {
        "message": {"type": "string"},
        "code": {"type": "string"},
        "http_code": {"type": "integer"}
      }
    },
    "message": {
      "type": "string"
    },
    "meta": {
      "type": "object",
      "required": ["timestamp", "request_id", "version"],
      "properties": {
        "timestamp": {"type": "string", "format": "date-time"},
        "request_id": {"type": "string"},
        "version": {"type": "string"}
      }
    }
  }
}
```

### PHP Test

```php
<?php
function validateApiResponse($response): bool {
    $required = ['success', 'data', 'error', 'meta'];
    foreach ($required as $field) {
        if (!array_key_exists($field, $response)) {
            return false;
        }
    }
    
    // Meta validation
    $metaRequired = ['timestamp', 'request_id', 'version'];
    foreach ($metaRequired as $field) {
        if (!array_key_exists($field, $response['meta'])) {
            return false;
        }
    }
    
    // Success/error mutual exclusivity
    if ($response['success'] === true && $response['error'] !== null) {
        return false;
    }
    if ($response['success'] === false && $response['data'] !== null) {
        return false;
    }
    
    return true;
}
```

---

## 🚫 Anti-Patterns (DO NOT DO)

### ❌ Inconsistent Structure
```json
{
  "status": "ok",  // ❌ Use 'success' boolean
  "result": {...}  // ❌ Use 'data'
}
```

### ❌ Missing Fields
```json
{
  "success": true,
  "data": {...}
  // ❌ Missing 'error' and 'meta'
}
```

### ❌ Wrong HTTP Status
```php
// ❌ Returning 200 OK with success=false
http_response_code(200);
echo json_encode(['success' => false, 'error' => '...']);

// ✅ Correct: Match HTTP code to success state
StandardResponse::error('Not found', 404, 'NOT_FOUND');
```

### ❌ Exposing Sensitive Data
```json
{
  "success": false,
  "error": {
    "message": "Database connection failed: mysql://root:password123@localhost"  // ❌ NEVER
  }
}
```

---

## 📊 Migration Plan for Existing Endpoints

### Phase 1: Audit (Day 1)
- Identify all API endpoints
- Check current response format
- Flag non-compliant endpoints

### Phase 2: Wrapper (Day 2-3)
- Add StandardResponse to bootstrap
- Create compatibility layer for old ApiResponse

### Phase 3: Migration (Week 1)
- Update endpoints one-by-one
- Test each endpoint
- Update client-side code

### Phase 4: Enforcement (Week 2)
- Add response validation middleware
- Reject non-compliant responses in staging
- Deploy to production

---

## 📈 Benefits

✅ **Predictable** - Clients know exact structure  
✅ **Debuggable** - Request IDs trace through logs  
✅ **Typed** - TypeScript interfaces can be generated  
✅ **Backwards Compatible** - Includes `message` at root  
✅ **Extensible** - `meta` allows custom fields  
✅ **Standard** - Follows REST API best practices

---

## 📝 Change Log

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | 2025-10-16 | Initial standard contract |

---

**Authority:** Ecigdis Limited Technical Standards  
**Approved By:** Pearce Stephens (Director)  
**Effective:** Immediately  
**Review Date:** 2026-01-16
