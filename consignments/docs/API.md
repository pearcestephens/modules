# Consignments API Documentation

**Version:** 2.0.0
**Last Updated:** November 2, 2025

## Table of Contents
- [Authentication](#authentication)
- [Transfer Services](#transfer-services)
- [Receiving Services](#receiving-services)
- [Freight Services](#freight-services)
- [Queue Management](#queue-management)
- [Webhooks](#webhooks)
- [Error Handling](#error-handling)

---

## Authentication

All API endpoints require authentication via session-based auth or API token.

```php
// Session-based (web)
$_SESSION['user_id'] = 123;
$_SESSION['role'] = 'admin';

// API token (future)
Authorization: Bearer {api_token}
```

---

## Transfer Services

### Purchase Order Service

#### Create Purchase Order
```php
POST /api/transfers/purchase-orders

Request:
{
  "supplier_id": 42,
  "outlet_id": 5,
  "expected_date": "2025-11-15",
  "notes": "Q4 stock order",
  "items": [
    {"product_id": 100, "quantity": 50},
    {"product_id": 101, "quantity": 30}
  ]
}

Response (201):
{
  "success": true,
  "transfer_id": 1234,
  "type": "PURCHASE_ORDER",
  "status": "OPEN",
  "expected_stock_updated": true
}
```

#### Send Purchase Order
```php
POST /api/transfers/purchase-orders/{id}/send

Response (200):
{
  "success": true,
  "transfer_id": 1234,
  "consignment_id": "ls_cons_xyz123",
  "status": "SENT",
  "queued": true
}
```

#### Receive Purchase Order
```php
POST /api/transfers/purchase-orders/{id}/receive

Request:
{
  "items": [
    {"item_id": 5001, "received_qty": 50},
    {"item_id": 5002, "received_qty": 28}
  ]
}

Response (200):
{
  "success": true,
  "transfer_id": 1234,
  "status": "PARTIALLY_RECEIVED",
  "fully_received": false,
  "items_updated": 2
}
```

### Stock Transfer Service

#### Create Stock Transfer
```php
POST /api/transfers/stock-transfers

Request:
{
  "source_outlet_id": 5,
  "destination_outlet_id": 8,
  "expected_date": "2025-11-10",
  "reason": "Stock rebalancing",
  "items": [
    {"product_id": 100, "quantity": 20}
  ]
}

Response (201):
{
  "success": true,
  "transfer_id": 1235,
  "type": "STOCK_TRANSFER",
  "status": "DRAFT"
}
```

### Return to Supplier Service

#### Create Return
```php
POST /api/transfers/returns

Request:
{
  "supplier_id": 42,
  "outlet_id": 5,
  "return_reason": "Damaged goods",
  "items": [
    {"product_id": 100, "quantity": 5}
  ]
}

Response (201):
{
  "success": true,
  "transfer_id": 1236,
  "type": "RETURN_TO_SUPPLIER",
  "status": "PENDING"
}
```

---

## Receiving Services

### Upload Photo Evidence
```php
POST /api/receiving/{transfer_id}/photo

Content-Type: multipart/form-data

Form Data:
- item_id: 5001
- photo: [file upload]

Response (201):
{
  "success": true,
  "evidence_id": 789,
  "file_path": "uploads/receiving/1234/photo_5001_20251102.jpg"
}
```

### Capture Signature
```php
POST /api/receiving/{transfer_id}/signature

Request:
{
  "signature_data": "data:image/png;base64,iVBORw0KGgo..."
}

Response (201):
{
  "success": true,
  "evidence_id": 790,
  "file_path": "uploads/receiving/1234/signature_20251102.png"
}
```

### Add Damage Note
```php
POST /api/receiving/{transfer_id}/damage

Request:
{
  "item_id": 5001,
  "note": "Box damaged, product intact"
}

Response (201):
{
  "success": true,
  "evidence_id": 791
}
```

---

## Freight Services

### Create Shipment
```php
POST /api/freight/shipments

Request:
{
  "transfer_id": 1234,
  "provider": "freight_now",
  "pickup_date": "2025-11-05",
  "delivery_date": "2025-11-08"
}

Response (201):
{
  "success": true,
  "shipment_id": "FN123456",
  "tracking_number": "TRACK789",
  "cost": 45.50
}
```

### Track Shipment
```php
GET /api/freight/shipments/{tracking_number}

Response (200):
{
  "success": true,
  "tracking_number": "TRACK789",
  "status": "IN_TRANSIT",
  "location": "Auckland Distribution Centre",
  "estimated_delivery": "2025-11-08 14:00:00"
}
```

---

## Queue Management

### Admin Endpoints

#### Sync Status
```php
GET /admin/api/sync-status.php

Response (200):
{
  "success": true,
  "timestamp": "2025-11-02 15:30:45",
  "queue": {
    "pending": 5,
    "processing": 2,
    "failed": 0
  },
  "webhooks": {
    "last_hour": 23,
    "success_rate": 0.98,
    "by_type": {
      "consignment.created": 15,
      "consignment.updated": 8
    }
  },
  "dlq": {
    "count": 0,
    "oldest": null
  },
  "cursor": {
    "last_processed_id": 12345,
    "updated_at": "2025-11-02 15:25:00"
  }
}
```

#### Retry Failed Job
```php
POST /admin/api/retry-job.php

Request:
{
  "dlq_id": 456
}

Response (200):
{
  "success": true,
  "message": "Job queued for retry",
  "new_job_id": 7890
}
```

---

## Webhooks

### Lightspeed Webhook Endpoint
```php
POST /public/webhooks/lightspeed.php

Headers:
- X-Lightspeed-Signature: sha256=abc123...
- Content-Type: application/json

Request:
{
  "event_id": "evt_abc123",
  "event_type": "consignment.created",
  "created_at": "2025-11-02T15:30:00Z",
  "data": {
    "consignment_id": "ls_cons_xyz"
  }
}

Response (202):
{
  "success": true,
  "message": "Webhook accepted",
  "request_id": "req_def456"
}
```

**HMAC Validation:**
- Algorithm: HMAC-SHA256
- Secret: Environment variable `LS_WEBHOOK_SECRET`
- Replay protection: 5-minute window
- Duplicate detection: Event ID uniqueness

---

## Error Handling

### Standard Error Response
```json
{
  "success": false,
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "Supplier ID is required",
    "field": "supplier_id"
  },
  "request_id": "req_abc123"
}
```

### HTTP Status Codes
- `200 OK` - Success
- `201 Created` - Resource created
- `202 Accepted` - Async processing queued
- `400 Bad Request` - Validation error
- `401 Unauthorized` - Missing/invalid auth
- `403 Forbidden` - Insufficient permissions
- `404 Not Found` - Resource not found
- `409 Conflict` - Duplicate/constraint violation
- `429 Too Many Requests` - Rate limit exceeded
- `500 Internal Server Error` - Server error

### Error Codes
- `VALIDATION_ERROR` - Input validation failed
- `NOT_FOUND` - Resource not found
- `UNAUTHORIZED` - Authentication required
- `FORBIDDEN` - Permission denied
- `DUPLICATE` - Resource already exists
- `RATE_LIMIT` - Too many requests
- `INTERNAL_ERROR` - Server error

---

## Rate Limits

- Webhooks: 100 requests/minute per IP
- API endpoints: 300 requests/minute per user
- Admin endpoints: No limit (session-based)

---

## Pagination

List endpoints support pagination:
```php
GET /api/transfers?page=2&per_page=50

Response:
{
  "success": true,
  "data": [...],
  "pagination": {
    "page": 2,
    "per_page": 50,
    "total": 234,
    "total_pages": 5
  }
}
```

---

## Idempotency

POST/PUT requests support idempotency keys:
```php
POST /api/transfers/purchase-orders
Idempotency-Key: uuid-12345

// Retry with same key returns cached response
```
