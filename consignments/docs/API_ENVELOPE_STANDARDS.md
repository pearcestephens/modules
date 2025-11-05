# 📋 Consignments API - BASE Envelope Standards

## Overview

The Consignments API has been refactored to follow the **BASE module envelope design pattern** for consistent, enterprise-grade API responses across all CIS modules.

**Version:** 2.0.0
**Updated:** November 4, 2025
**Standards:** BASE Module API Guidelines

---

## 🎯 Key Changes

### ✅ What's New

1. **Standardized Response Envelopes** - All responses follow BASE module format
2. **Extended BaseAPI Class** - Inherits comprehensive logging, validation, error handling
3. **Request ID Tracking** - Every request gets unique ID for tracing
4. **Performance Metrics** - Duration and memory usage in every response
5. **Consistent Error Codes** - Predictable error code patterns
6. **Enhanced Security** - CSRF validation, rate limiting support, request size limits
7. **CIS Logger Integration** - Automatic logging to unified system
8. **Better Validation** - Built-in validators for common types

### 📦 Response Envelope Structure

#### Success Response

```json
{
  "success": true,
  "message": "Operation successful",
  "timestamp": "2025-11-04 12:34:56",
  "request_id": "req_1730700896_a1b2c3d4",
  "data": {
    // Your actual response data here
  },
  "meta": {
    "duration_ms": 45.23,
    "memory_usage": "2.5 MB",
    // Additional metadata specific to operation
  }
}
```

#### Error Response

```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Missing required field: email",
    "timestamp": "2025-11-04 12:34:56",
    "details": {
      "field": "email",
      "value": null
    }
  },
  "request_id": "req_1730700896_a1b2c3d4"
}
```

---

## 🚀 API Endpoints

### Main Consignments API

**Endpoint:** `/modules/consignments/api.php`

All requests must be **POST** with JSON body:

```json
{
  "action": "get_recent",
  "data": {
    "limit": 50
  }
}
```

#### Available Actions

| Action | Method | Auth | CSRF | Description |
|--------|--------|------|------|-------------|
| `get_recent` | POST | No | No | Get recent consignments |
| `get_consignment` | POST | No | No | Get single consignment with items |
| `search_consignments` | POST | No | No | Search consignments |
| `get_stats` | POST | No | No | Get consignment statistics |
| `create_consignment` | POST | No | **Yes** | Create new consignment |
| `add_item` | POST | No | **Yes** | Add item to consignment |
| `update_status` | POST | No | **Yes** | Update consignment status |
| `update_item_qty` | POST | No | **Yes** | Update item quantity |

### Purchase Orders API

**Endpoint:** `/modules/consignments/api/purchase-orders/*.php`

Individual endpoint files using PurchaseOrdersAPI class.

---

## 📖 Usage Examples

### Example 1: Get Recent Consignments

**Request:**
```javascript
fetch('/modules/consignments/api.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    action: 'get_recent',
    data: {
      limit: 25
    }
  })
})
.then(response => response.json())
.then(result => {
  if (result.success) {
    console.log(`Found ${result.data.rows.length} consignments`);
    console.log(`Request ID: ${result.request_id}`);
    console.log(`Duration: ${result.meta.duration_ms}ms`);
  } else {
    console.error(`Error: ${result.error.message} (${result.error.code})`);
  }
});
```

**Response:**
```json
{
  "success": true,
  "message": "Recent consignments retrieved successfully",
  "timestamp": "2025-11-04 12:34:56",
  "request_id": "req_1730700896_a1b2c3d4",
  "data": {
    "rows": [
      {
        "id": 123,
        "ref_code": "CONS-2025-001",
        "status": "sent",
        "created_at": "2025-11-04 10:00:00"
      }
    ],
    "count": 1
  },
  "meta": {
    "duration_ms": 12.45,
    "memory_usage": "2.1 MB"
  }
}
```

### Example 2: Create Consignment (with CSRF)

**Request:**
```javascript
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

fetch('/modules/consignments/api.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    action: 'create_consignment',
    data: {
      csrf: csrfToken,
      ref_code: 'CONS-2025-002',
      origin_outlet_id: 1,
      dest_outlet_id: 5,
      created_by: 42
    }
  })
})
.then(response => response.json())
.then(result => {
  if (result.success) {
    console.log('Created consignment:', result.data);
  } else {
    console.error('Error:', result.error);
  }
});
```

**Success Response:**
```json
{
  "success": true,
  "message": "Consignment created successfully",
  "timestamp": "2025-11-04 12:35:00",
  "request_id": "req_1730700900_xyz123",
  "data": {
    "id": 124,
    "ref_code": "CONS-2025-002",
    "origin_outlet_id": 1,
    "dest_outlet_id": 5,
    "status": "draft",
    "created_at": "2025-11-04 12:35:00"
  },
  "meta": {
    "duration_ms": 34.21,
    "memory_usage": "2.3 MB",
    "http_code": 201
  }
}
```

### Example 3: Error Handling

**Request with Missing Field:**
```javascript
fetch('/modules/consignments/api.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    action: 'create_consignment',
    data: {
      csrf: csrfToken,
      ref_code: 'CONS-2025-003'
      // Missing origin_outlet_id, dest_outlet_id, created_by
    }
  })
})
.then(response => response.json())
.then(result => {
  console.error('Error:', result.error.message);
  console.log('Missing fields:', result.error.details);
});
```

**Error Response:**
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Missing required fields: origin_outlet_id, dest_outlet_id, created_by",
    "timestamp": "2025-11-04 12:36:00",
    "details": {
      "missing": ["origin_outlet_id", "dest_outlet_id", "created_by"]
    }
  },
  "request_id": "req_1730700960_abc789"
}
```

---

## 🔒 Security Features

### CSRF Protection

All write operations require CSRF token:

```javascript
const csrfToken = document.querySelector('meta[name="csrf-token"]').content;

// Include in data object
data: {
  csrf: csrfToken,
  // ... other fields
}
```

### Authentication

Purchase Orders API requires authentication:
- User must be logged in (`$_SESSION['user_id']` set)
- Returns 401 if not authenticated

### Request Validation

- **Method Validation**: Only allowed HTTP methods accepted
- **Size Limits**: Requests limited to 10MB by default
- **JSON Validation**: Malformed JSON rejected immediately
- **Field Validation**: Type checking, min/max lengths, ranges

---

## 📊 Error Codes

| Code | HTTP | Description |
|------|------|-------------|
| `VALIDATION_ERROR` | 400/422 | Invalid input data |
| `NOT_FOUND` | 404 | Resource not found |
| `UNAUTHORIZED` | 401 | Authentication required |
| `FORBIDDEN` | 403 | CSRF validation failed |
| `CREATE_FAILED` | 500 | Resource creation failed |
| `UPDATE_FAILED` | 500 | Resource update failed |
| `DELETE_FAILED` | 422 | Resource deletion failed |
| `API_ERROR` | 500 | Generic server error |
| `INVALID_STATUS_TRANSITION` | 422 | Status change not allowed |

---

## 🛠️ Developer Guide

### Creating a New API Endpoint

1. **Extend BaseAPI:**

```php
<?php
namespace CIS\Consignments\Lib;

use CIS\Base\Lib\BaseAPI;

class MyNewAPI extends BaseAPI {

    public function __construct(array $config = []) {
        parent::__construct(array_merge([
            'require_auth' => true,
            'allowed_methods' => ['POST'],
        ], $config));
    }

    protected function handleMyAction(array $data): array {
        // Validate
        $this->validateRequired($data, ['field1', 'field2']);

        // Process
        $result = $this->processData($data);

        // Return
        return $this->success($result, 'Action completed successfully');
    }
}
```

2. **Create Endpoint File:**

```php
<?php
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/lib/MyNewAPI.php';

use CIS\Consignments\Lib\MyNewAPI;

$api = new MyNewAPI();
$api->handleRequest();
```

3. **Action Naming Convention:**

- Handler methods: `handleGetUser`, `handleCreateOrder`, `handleUpdateStatus`
- Action names: `get_user`, `create_order`, `update_status` (snake_case in request)

### Built-in Validation Methods

```php
// Validate required fields
$this->validateRequired($data, ['field1', 'field2']);

// Validate integer
$id = $this->validateInt($data, 'id', null, 1, 9999);

// Validate string
$name = $this->validateString($data, 'name', '', 1, 100);

// Validate CSRF
$this->validateCSRF($data);
```

### Response Helpers

```php
// Success with data
return $this->success($data, 'Operation successful');

// Success with metadata
return $this->success($data, 'Operation successful', [
    'custom_meta' => 'value'
]);

// Error
return $this->error(
    'Error message',
    'ERROR_CODE',
    ['detail1' => 'value'],
    400 // HTTP status code
);
```

---

## 🔄 Migration Guide

### Updating Existing Code

**Old Pattern:**
```php
json_ok(['rows' => $rows], 200);
json_fail('Error message', 400, ['meta' => 'data']);
```

**New Pattern:**
```php
return $this->success(['rows' => $rows], 'Success message');
return $this->error('Error message', 'ERROR_CODE', ['meta' => 'data'], 400);
```

### Frontend JavaScript Updates

**Old:**
```javascript
if (response.ok) {
  console.log(response.data);
}
```

**New:**
```javascript
if (response.success) {
  console.log(response.data);
  console.log('Request ID:', response.request_id);
  console.log('Duration:', response.meta.duration_ms + 'ms');
}
```

---

## 📈 Monitoring & Debugging

### Request Tracking

Every response includes `request_id` for log correlation:

```json
{
  "request_id": "req_1730700896_a1b2c3d4"
}
```

Search CIS logs:
```bash
grep "req_1730700896_a1b2c3d4" /var/log/cis/*.log
```

### Performance Metrics

Every response includes performance data:

```json
{
  "meta": {
    "duration_ms": 45.23,
    "memory_usage": "2.5 MB"
  }
}
```

Monitor slow requests:
```bash
grep "duration_ms.*[5-9][0-9]{2}" api.log
```

---

## ✅ Testing

### Test Success Response

```bash
curl -X POST http://localhost/modules/consignments/api.php \
  -H "Content-Type: application/json" \
  -d '{"action":"get_recent","data":{"limit":5}}'
```

**Expected:**
```json
{
  "success": true,
  "message": "Recent consignments retrieved successfully",
  "timestamp": "2025-11-04 12:34:56",
  "request_id": "req_...",
  "data": { ... },
  "meta": { ... }
}
```

### Test Error Response

```bash
curl -X POST http://localhost/modules/consignments/api.php \
  -H "Content-Type: application/json" \
  -d '{"action":"get_consignment","data":{}}'
```

**Expected:**
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Missing required parameter: id",
    "timestamp": "2025-11-04 12:34:56",
    "details": {}
  },
  "request_id": "req_..."
}
```

---

## 📚 Related Documentation

- [BASE Module API Guidelines](../../base/docs/API_GUIDELINES.md)
- [BaseAPI Class Reference](../../base/lib/BaseAPI.php)
- [CIS Logger Documentation](../../base/docs/LOGGER.md)
- [Consignments Module README](../README.md)

---

## 🎉 Summary

The Consignments API now provides:

✅ **Consistent** - Same response structure across all endpoints
✅ **Traceable** - Request IDs for debugging
✅ **Performant** - Built-in metrics tracking
✅ **Secure** - CSRF protection, validation, authentication
✅ **Logged** - Automatic integration with CIS Logger
✅ **Maintainable** - Clean inheritance from BaseAPI
✅ **Documented** - Comprehensive inline and external docs

**Next Steps:**
1. Update frontend JavaScript to use new envelope structure
2. Monitor `request_id` usage in logs
3. Review performance metrics for optimization opportunities
4. Gradually migrate remaining API endpoints to new pattern
