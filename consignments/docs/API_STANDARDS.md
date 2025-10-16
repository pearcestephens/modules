# Consignments Module API Standards

**Version:** 2.0.0  
**Last Updated:** December 2024  
**Purpose:** Enterprise-level AJAX communication protocol for all Consignments API operations

---

## Table of Contents

1. [Overview](#overview)
2. [Request Format](#request-format)
3. [Response Format](#response-format)
4. [Error Handling](#error-handling)
5. [Client-Side Usage](#client-side-usage)
6. [Available Endpoints](#available-endpoints)
7. [Error Logging](#error-logging)
8. [Best Practices](#best-practices)
9. [Examples](#examples)

---

## Overview

The Consignments module enforces a **consistent envelope-style AJAX communication protocol** across all API operations. This ensures:

- ✅ **Standardized responses** - Every API response follows the same structure
- ✅ **Comprehensive error handling** - Errors are logged and handled consistently
- ✅ **Request tracking** - Every request has a unique ID for debugging
- ✅ **Type safety** - Responses are validated before use
- ✅ **Monitoring** - All errors are logged to the database for analysis

### Architecture

```
┌─────────────────┐
│   Client-Side   │
│   (pack.js)     │
└────────┬────────┘
         │
         ├─ ConsignmentsAjax.request()
         │  (Enterprise AJAX Manager)
         │
         ↓
┌─────────────────┐
│  Central Router │
│  (api.php)      │
└────────┬────────┘
         │
         ├─ Routes to specific endpoint
         │  based on 'action' parameter
         │
         ↓
┌─────────────────┐
│    Endpoint     │
│ (autosave.php)  │
└────────┬────────┘
         │
         ├─ Uses ApiResponse class
         │  for consistent envelopes
         │
         ↓
┌─────────────────┐
│  JSON Response  │
│  (Envelope)     │
└─────────────────┘
```

---

## Request Format

All API requests must be sent to the central router:

**Endpoint:** `/modules/consignments/api/api.php`  
**Method:** `POST`  
**Content-Type:** `application/json`

### Required Parameters

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `action` | string | **Yes** | The API endpoint to call (e.g., `autosave_transfer`) |
| `_request_id` | string | Auto | Generated automatically by AjaxManager |
| `_timestamp` | integer | Auto | Generated automatically by AjaxManager |

### Additional Parameters

Each endpoint may require additional parameters. See [Available Endpoints](#available-endpoints) for details.

### Example Request

```javascript
ConsignmentsAjax.request({
  action: 'autosave_transfer',
  data: {
    transfer_id: 12345,
    draft_data: [
      { product_id: 1, counted_qty: 10 },
      { product_id: 2, counted_qty: 5 }
    ]
  }
});
```

**Generated HTTP Request:**
```json
POST /modules/consignments/api/api.php
Content-Type: application/json

{
  "action": "autosave_transfer",
  "transfer_id": 12345,
  "draft_data": [
    { "product_id": 1, "counted_qty": 10 },
    { "product_id": 2, "counted_qty": 5 }
  ],
  "_request_id": "req_1701234567890_abc123",
  "_timestamp": 1701234567890
}
```

---

## Response Format

All API responses follow the **ApiResponse envelope** format.

### Success Response

```json
{
  "success": true,
  "data": {
    // Endpoint-specific data
  },
  "message": "Optional success message",
  "meta": {
    "timestamp": "2024-12-15 14:30:00",
    "request_id": "req_1701234567890_abc123"
  }
}
```

### Error Response

```json
{
  "success": false,
  "error": {
    "message": "Human-readable error message",
    "code": "ERROR_CODE",
    "details": {
      // Additional error context
    },
    "http_code": 400
  },
  "meta": {
    "timestamp": "2024-12-15 14:30:00",
    "request_id": "req_1701234567890_abc123"
  }
}
```

### HTTP Status Codes

| Code | Meaning | When Used |
|------|---------|-----------|
| 200 | OK | Successful request |
| 400 | Bad Request | Invalid parameters |
| 401 | Unauthorized | Not authenticated |
| 403 | Forbidden | No permission |
| 404 | Not Found | Endpoint or resource not found |
| 422 | Unprocessable Entity | Validation failed |
| 500 | Internal Server Error | Server-side error |

---

## Error Handling

### Error Codes

All errors include a machine-readable code for programmatic handling:

| Code | Description |
|------|-------------|
| `MISSING_ACTION` | No action parameter provided |
| `UNKNOWN_ACTION` | Invalid action parameter |
| `VALIDATION_ERROR` | Input validation failed |
| `MISSING_FIELDS` | Required fields missing |
| `NOT_FOUND` | Resource not found |
| `UNAUTHORIZED` | User not authenticated |
| `FORBIDDEN` | User lacks permission |
| `DB_ERROR` | Database operation failed |
| `SERVER_ERROR` | General server error |

### Client-Side Error Handling

The `ConsignmentsAjax` manager automatically:

1. ✅ **Validates response structure** - Ensures envelope format
2. ✅ **Shows error toasts** - Visual feedback to user
3. ✅ **Logs to console** - Developer visibility
4. ✅ **Logs to server** - Persistent error tracking
5. ✅ **Retries on timeout** - Automatic retry for network issues

### Catching Errors

```javascript
ConsignmentsAjax.request({
  action: 'some_action',
  data: { ... }
})
.then(response => {
  // Success - response.data contains your data
  console.log('Success:', response.data);
})
.catch(error => {
  // Error - already logged and shown to user
  console.error('Failed:', error.message);
  
  // Access error details
  if (error.code === 'VALIDATION_ERROR') {
    // Handle validation error
  }
});
```

---

## Client-Side Usage

### Basic Request

```javascript
ConsignmentsAjax.request({
  action: 'autosave_transfer',
  data: {
    transfer_id: 12345,
    draft_data: [...]
  }
})
.then(response => {
  console.log('Saved at:', response.data.updated_at);
})
.catch(error => {
  console.error('Save failed:', error.message);
});
```

### Advanced Options

```javascript
ConsignmentsAjax.request({
  action: 'some_action',
  data: { ... },
  
  // UI Options
  showLoader: true,      // Show full-screen loader (default: true)
  showSuccess: true,     // Show success toast (default: false)
  showError: true,       // Show error toast (default: true)
  
  // Retry Options
  retryOnError: true,    // Retry on timeout (default: false)
  timeout: 30000,        // Request timeout in ms (default: 30000)
  
  // Advanced
  method: 'POST',        // HTTP method (default: POST)
  url: '/custom/url'     // Custom URL (default: api.php)
});
```

### Without Loader

For background operations like auto-save:

```javascript
ConsignmentsAjax.request({
  action: 'autosave_transfer',
  data: { ... },
  showLoader: false,    // No full-screen loader
  showSuccess: false,   // No success toast
  showError: true       // Still show errors
});
```

### Manual Notifications

```javascript
// Show success toast
ConsignmentsAjax.showSuccess('Transfer saved successfully');

// Show error toast
ConsignmentsAjax.showError('Failed to save transfer');

// Show warning toast
ConsignmentsAjax.showWarning('Transfer already packed');

// Show info toast
ConsignmentsAjax.showInfo('Auto-save enabled');
```

### Request Logging

```javascript
// Get request log
const log = ConsignmentsAjax.getLog();
console.table(log);

// Clear log
ConsignmentsAjax.clearLog();
```

### Aborting Requests

```javascript
// Abort all pending requests (e.g., on page unload)
ConsignmentsAjax.abortAll();
```

---

## Available Endpoints

### 1. Auto-Save Transfer

**Action:** `autosave_transfer`  
**Purpose:** Save draft counted quantities for a transfer

**Request:**
```json
{
  "action": "autosave_transfer",
  "transfer_id": 12345,
  "draft_data": [
    { "product_id": 1, "counted_qty": 10 },
    { "product_id": 2, "counted_qty": 5 }
  ]
}
```

**Success Response:**
```json
{
  "success": true,
  "data": {
    "transfer_id": 12345,
    "updated_at": "2024-12-15 14:30:00"
  },
  "message": "Draft saved successfully"
}
```

**Errors:**
- `MISSING_FIELDS` - Missing transfer_id or draft_data
- `VALIDATION_ERROR` - Invalid data format
- `DB_ERROR` - Database save failed

---

### 2. Lightspeed Sync

**Action:** `lightspeed` | `vend` | `sync`  
**Purpose:** Sync data with Lightspeed/Vend API

**Request:**
```json
{
  "action": "lightspeed",
  "sync_type": "products",
  "outlet_id": 1
}
```

*(See existing lightspeed.php for full documentation)*

---

### 3. Universal Transfer

**Action:** `universal_transfer` | `get_transfer` | `update_transfer`  
**Purpose:** CRUD operations for transfers

**Request:**
```json
{
  "action": "get_transfer",
  "transfer_id": 12345
}
```

*(See existing universal_transfer_api.php for full documentation)*

---

### 4. Log Error

**Action:** `log_error`  
**Purpose:** Log client-side errors to server for monitoring

**Request:**
```json
{
  "action": "log_error",
  "level": "ERROR",
  "message": "Auto-save failed: Network timeout",
  "context": {
    "transfer_id": 12345,
    "error_code": "TIMEOUT"
  },
  "url": "https://example.com/pack.php?id=12345"
}
```

**Success Response:**
```json
{
  "success": true,
  "data": {
    "log_id": 789,
    "logged_at": "2024-12-15 14:30:00"
  },
  "message": "Error logged successfully"
}
```

**Log Levels:**
- `ERROR` - Critical errors
- `WARNING` - Non-critical issues
- `INFO` - Informational messages
- `DEBUG` - Debug information

---

## Error Logging

All client-side errors are automatically logged to the database for monitoring and debugging.

### Log Table Structure

```sql
CREATE TABLE client_error_log (
  id INT AUTO_INCREMENT PRIMARY KEY,
  level ENUM('ERROR','WARNING','INFO','DEBUG'),
  message VARCHAR(500),
  context_json TEXT,
  url VARCHAR(500),
  user_id INT,
  username VARCHAR(100),
  user_agent VARCHAR(255),
  ip_address VARCHAR(45),
  created_at DATETIME
);
```

### Viewing Logs

```sql
-- Recent errors
SELECT * FROM client_error_log 
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY created_at DESC;

-- Error frequency
SELECT level, COUNT(*) as count 
FROM client_error_log
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY level;

-- Most common errors
SELECT message, COUNT(*) as count
FROM client_error_log
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY message
ORDER BY count DESC
LIMIT 20;
```

### Manual Logging

```javascript
// Log error manually
ConsignmentsAjax.request({
  action: 'log_error',
  data: {
    level: 'ERROR',
    message: 'Custom error message',
    context: {
      custom_field: 'value'
    },
    url: window.location.href
  },
  showLoader: false,
  showError: false
});
```

---

## Best Practices

### ✅ DO

1. **Always use ConsignmentsAjax.request()** for API calls
   ```javascript
   // Good
   ConsignmentsAjax.request({ action: 'some_action', data: {...} });
   
   // Bad
   $.ajax({ url: '/api.php', data: {...} });
   ```

2. **Handle both success and error cases**
   ```javascript
   ConsignmentsAjax.request(...)
     .then(response => { /* Success */ })
     .catch(error => { /* Error */ });
   ```

3. **Use descriptive action names**
   ```javascript
   // Good
   action: 'autosave_transfer'
   
   // Bad
   action: 'save'
   ```

4. **Include context in error logs**
   ```javascript
   context: {
     transfer_id: 12345,
     operation: 'pack',
     user_action: 'manual_save'
   }
   ```

5. **Test error scenarios**
   - Invalid data
   - Network timeout
   - Server errors
   - Missing permissions

### ❌ DON'T

1. **Don't bypass the AJAX manager**
   ```javascript
   // Don't do this
   $.ajax({ url: '/api.php', ... });
   ```

2. **Don't ignore promise rejections**
   ```javascript
   // Don't do this
   ConsignmentsAjax.request(...); // No .catch()
   ```

3. **Don't hardcode error messages**
   ```javascript
   // Don't do this
   catch(error => alert('Error'));
   
   // Do this
   catch(error => ConsignmentsAjax.showError(error.message));
   ```

4. **Don't send sensitive data in context**
   ```javascript
   // Don't do this
   context: {
     password: '...',  // Never log passwords
     api_key: '...'    // Never log keys
   }
   ```

5. **Don't create custom response formats**
   - Always use ApiResponse methods on server-side
   - Never return raw arrays or strings

---

## Examples

### Example 1: Auto-Save with Visual Feedback

```javascript
function saveTransfer() {
  const $indicator = $('#save-indicator');
  $indicator.text('Saving...').show();
  
  ConsignmentsAjax.request({
    action: 'autosave_transfer',
    data: {
      transfer_id: 12345,
      draft_data: collectData()
    },
    showLoader: false,  // Use custom indicator
    showSuccess: false
  })
  .then(response => {
    $indicator.text('Saved ✓').addClass('success');
    setTimeout(() => $indicator.fadeOut(), 2000);
  })
  .catch(error => {
    $indicator.text('Failed ✗').addClass('error');
  });
}
```

### Example 2: Form Submission with Validation

```javascript
$('#submit-form').on('click', function() {
  const formData = {
    name: $('#name').val(),
    email: $('#email').val(),
    quantity: parseInt($('#quantity').val())
  };
  
  ConsignmentsAjax.request({
    action: 'submit_order',
    data: formData,
    showLoader: true,   // Show full-screen loader
    showSuccess: true,  // Show success toast
    showError: true     // Show error toast
  })
  .then(response => {
    // Redirect to success page
    window.location = '/success?id=' + response.data.order_id;
  })
  .catch(error => {
    // Error already shown as toast
    // Handle specific error codes
    if (error.code === 'VALIDATION_ERROR') {
      highlightInvalidFields(error.response.error.details);
    }
  });
});
```

### Example 3: Retry on Network Error

```javascript
ConsignmentsAjax.request({
  action: 'critical_operation',
  data: { ... },
  retryOnError: true,  // Auto-retry on timeout
  timeout: 10000       // 10 second timeout
})
.then(response => {
  console.log('Operation succeeded (possibly after retry)');
})
.catch(error => {
  console.log('Operation failed after all retries');
});
```

### Example 4: Background Sync

```javascript
// Silent background sync
setInterval(() => {
  ConsignmentsAjax.request({
    action: 'sync_status',
    data: { transfer_id: 12345 },
    showLoader: false,
    showSuccess: false,
    showError: false  // Don't bother user with background errors
  })
  .then(response => {
    updateStatusIndicator(response.data.status);
  })
  .catch(error => {
    // Log but don't show to user
    console.warn('Background sync failed:', error);
  });
}, 30000); // Every 30 seconds
```

---

## Migration Guide

### Migrating Existing AJAX Calls

**Before:**
```javascript
$.ajax({
  url: '/modules/consignments/api/api.php',
  method: 'POST',
  contentType: 'application/json',
  data: JSON.stringify({
    action: 'autosave_transfer',
    transfer_id: 12345,
    draft_data: [...]
  }),
  success: function(response) {
    if (response.success) {
      console.log('Saved');
    } else {
      alert('Error: ' + response.error.message);
    }
  },
  error: function(xhr, status, error) {
    alert('Request failed');
  }
});
```

**After:**
```javascript
ConsignmentsAjax.request({
  action: 'autosave_transfer',
  data: {
    transfer_id: 12345,
    draft_data: [...]
  }
})
.then(response => {
  console.log('Saved');
})
.catch(error => {
  // Error automatically shown to user
});
```

**Benefits:**
- ✅ 70% less code
- ✅ Automatic error handling
- ✅ Built-in retry logic
- ✅ Consistent error logging
- ✅ Request tracking
- ✅ Toast notifications

---

## Support

For issues or questions:

1. Check the request log: `ConsignmentsAjax.getLog()`
2. Check server error log: `SELECT * FROM client_error_log ORDER BY created_at DESC LIMIT 50`
3. Check browser console for detailed error messages
4. Review this documentation

---

**Version:** 2.0.0  
**Last Updated:** December 2024  
**Maintained By:** CIS Development Team
