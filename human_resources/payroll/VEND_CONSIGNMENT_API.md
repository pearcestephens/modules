# Vend Consignment Management API

Complete REST API for Vend/Lightspeed consignment management in CIS Staff Portal.

## Overview

- **Base URL**: `https://staff.vapeshed.co.nz`
- **Authentication**: Required (session-based)
- **CSRF Protection**: Required for POST/PUT/PATCH/DELETE
- **Permission**: `payroll.view_consignments` (read) or `payroll.manage_consignments` (write)
- **Content-Type**: `application/json`

## API Endpoints (25 Total)

### 1. CONSIGNMENT CRUD OPERATIONS (6 endpoints)

#### 1.1 Create Consignment
```http
POST /api/vend/consignments/create
Content-Type: application/json
X-CSRF-Token: {token}

{
  "name": "Store Transfer #123",
  "type": "OUTLET",
  "outlet_id": "outlet123",
  "source_outlet_id": "outlet456",
  "due_at": "2025-02-01",
  "reference": "ST-2025-001",
  "status": "OPEN"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Consignment created successfully",
  "data": {
    "consignment": {
      "id": "cons_abc123",
      "name": "Store Transfer #123",
      "type": "OUTLET",
      "status": "OPEN",
      "created_at": "2025-01-30T10:30:00Z"
    }
  }
}
```

**Consignment Types:**
- `SUPPLIER` - Purchase from supplier
- `OUTLET` - Transfer between outlets
- `RETURN` - Return to supplier
- `STOCKTAKE` - Stocktake adjustment

**curl Example:**
```bash
curl -X POST https://staff.vapeshed.co.nz/api/vend/consignments/create \
  -H "Content-Type: application/json" \
  -H "X-CSRF-Token: YOUR_TOKEN" \
  -H "Cookie: PHPSESSID=YOUR_SESSION" \
  -d '{
    "name": "Store Transfer #123",
    "type": "OUTLET",
    "outlet_id": "outlet123",
    "source_outlet_id": "outlet456"
  }'
```

---

#### 1.2 Get Consignment Details
```http
GET /api/vend/consignments/{id}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "consignment": {
      "id": "cons_abc123",
      "name": "Store Transfer #123",
      "type": "OUTLET",
      "status": "OPEN",
      "outlet_id": "outlet123",
      "source_outlet_id": "outlet456",
      "created_at": "2025-01-30T10:30:00Z",
      "products": [
        {
          "id": "prod_xyz789",
          "product_id": "prod123",
          "name": "Product Name",
          "count": 10,
          "cost": 25.50,
          "received": 0
        }
      ]
    }
  }
}
```

---

#### 1.3 List Consignments
```http
GET /api/vend/consignments/list?type=OUTLET&status=OPEN&outlet_id=outlet123
```

**Query Parameters:**
- `type` - Filter by consignment type (SUPPLIER, OUTLET, RETURN, STOCKTAKE)
- `status` - Filter by status (OPEN, SENT, RECEIVED, CANCELLED)
- `outlet_id` - Filter by outlet
- `since` - ISO 8601 date (e.g., 2025-01-01T00:00:00Z)
- `page_size` - Results per page (default 50, max 200)
- `after` - Pagination cursor

**Response:**
```json
{
  "success": true,
  "data": {
    "consignments": [
      {
        "id": "cons_abc123",
        "name": "Store Transfer #123",
        "type": "OUTLET",
        "status": "OPEN",
        "outlet_id": "outlet123",
        "created_at": "2025-01-30T10:30:00Z"
      }
    ],
    "pagination": {
      "results": 1,
      "page_size": 50,
      "after": null
    }
  }
}
```

---

#### 1.4 Update Consignment
```http
PUT /api/vend/consignments/{id}
Content-Type: application/json
X-CSRF-Token: {token}

{
  "name": "Updated Store Transfer #123",
  "due_at": "2025-02-05",
  "reference": "ST-2025-001-UPDATED"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Consignment updated successfully",
  "data": {
    "consignment": {
      "id": "cons_abc123",
      "name": "Updated Store Transfer #123",
      "due_at": "2025-02-05",
      "reference": "ST-2025-001-UPDATED"
    }
  }
}
```

---

#### 1.5 Update Consignment Status
```http
PATCH /api/vend/consignments/{id}/status
Content-Type: application/json
X-CSRF-Token: {token}

{
  "status": "SENT"
}
```

**Valid Statuses:**
- `OPEN` - Consignment created, can be edited
- `SENT` - Consignment dispatched/sent
- `RECEIVED` - Consignment received/completed
- `STOCKTAKE` - Stocktake in progress
- `CANCELLED` - Consignment cancelled

**Response:**
```json
{
  "success": true,
  "message": "Consignment status updated successfully",
  "data": {
    "status": "SENT"
  }
}
```

---

#### 1.6 Delete Consignment
```http
DELETE /api/vend/consignments/{id}
X-CSRF-Token: {token}
```

**Response:**
```json
{
  "success": true,
  "message": "Consignment deleted successfully"
}
```

---

### 2. CONSIGNMENT PRODUCT MANAGEMENT (6 endpoints)

#### 2.1 Add Product to Consignment
```http
POST /api/vend/consignments/{id}/products
Content-Type: application/json
X-CSRF-Token: {token}

{
  "product_id": "prod_xyz789",
  "count": 10,
  "cost": 25.50,
  "received": 0
}
```

**Response:**
```json
{
  "success": true,
  "message": "Product added to consignment successfully",
  "data": {
    "product": {
      "id": "consprod_123",
      "product_id": "prod_xyz789",
      "count": 10,
      "cost": 25.50,
      "received": 0
    }
  }
}
```

---

#### 2.2 List Consignment Products
```http
GET /api/vend/consignments/{id}/products
```

**Response:**
```json
{
  "success": true,
  "data": {
    "products": [
      {
        "id": "consprod_123",
        "product_id": "prod_xyz789",
        "name": "Product Name",
        "sku": "SKU-123",
        "count": 10,
        "cost": 25.50,
        "received": 0
      }
    ]
  }
}
```

---

#### 2.3 Update Product in Consignment
```http
PUT /api/vend/consignments/{id}/products/{pid}
Content-Type: application/json
X-CSRF-Token: {token}

{
  "count": 15,
  "cost": 24.00,
  "received": 5
}
```

**Response:**
```json
{
  "success": true,
  "message": "Product updated successfully",
  "data": {
    "product": {
      "id": "consprod_123",
      "count": 15,
      "cost": 24.00,
      "received": 5
    }
  }
}
```

---

#### 2.4 Delete Product from Consignment
```http
DELETE /api/vend/consignments/{id}/products/{pid}
X-CSRF-Token: {token}
```

**Response:**
```json
{
  "success": true,
  "message": "Product removed from consignment successfully"
}
```

---

#### 2.5 Bulk Add Products
```http
POST /api/vend/consignments/{id}/products/bulk
Content-Type: application/json
X-CSRF-Token: {token}

{
  "products": [
    {
      "product_id": "prod_abc",
      "count": 10,
      "cost": 25.50
    },
    {
      "product_id": "prod_def",
      "count": 5,
      "cost": 15.00
    },
    {
      "product_id": "prod_ghi",
      "count": 20,
      "cost": 30.00
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "message": "Products added to consignment successfully",
  "data": {
    "added_count": 3,
    "results": [
      {"product_id": "prod_abc", "success": true},
      {"product_id": "prod_def", "success": true},
      {"product_id": "prod_ghi", "success": true}
    ]
  }
}
```

---

### 3. SYNC OPERATIONS (3 endpoints)

#### 3.1 Sync Consignment to Lightspeed
```http
POST /api/vend/consignments/{id}/sync
Content-Type: application/json
X-CSRF-Token: {token}

{
  "async": true
}
```

**Parameters:**
- `async` - Boolean (default: `true`)
  - `true`: Queue background job, returns immediately with job_id
  - `false`: Sync immediately (may take time), returns consignment_id

**Response (async=true):**
```json
{
  "success": true,
  "message": "Sync job queued successfully",
  "data": {
    "job_id": 12345,
    "status": "queued"
  }
}
```

**Response (async=false):**
```json
{
  "success": true,
  "message": "Consignment synced successfully",
  "data": {
    "consignment_id": "cons_vend_xyz"
  }
}
```

---

#### 3.2 Get Sync Status
```http
GET /api/vend/consignments/{id}/sync/status
```

**Response:**
```json
{
  "success": true,
  "data": {
    "sync_status": "completed",
    "vend_consignment_id": "cons_vend_xyz",
    "last_sync": "2025-01-30T11:00:00Z",
    "logs": [
      {
        "id": 123,
        "status": "completed",
        "message": "Successfully synced consignment",
        "created_at": "2025-01-30T11:00:00Z"
      }
    ]
  }
}
```

**Sync Statuses:**
- `not_synced` - Never synced
- `pending` - Sync queued
- `in_progress` - Sync running
- `completed` - Sync successful
- `failed` - Sync failed (see logs)

---

#### 3.3 Retry Failed Sync
```http
POST /api/vend/consignments/{id}/sync/retry
X-CSRF-Token: {token}
```

**Response:**
```json
{
  "success": true,
  "message": "Sync retry queued successfully",
  "data": {
    "job_id": 12346
  }
}
```

---

### 4. WORKFLOW OPERATIONS (3 endpoints)

#### 4.1 Send Consignment
```http
POST /api/vend/consignments/{id}/send
X-CSRF-Token: {token}
```

**Purpose:** Mark consignment as SENT (dispatched/in transit)

**Response:**
```json
{
  "success": true,
  "message": "Consignment sent successfully",
  "data": {
    "status": "SENT",
    "sent_at": "2025-01-30T12:00:00Z"
  }
}
```

---

#### 4.2 Receive Consignment
```http
POST /api/vend/consignments/{id}/receive
Content-Type: application/json
X-CSRF-Token: {token}

{
  "received_quantities": [
    {
      "product_id": "consprod_123",
      "received": 10
    },
    {
      "product_id": "consprod_456",
      "received": 5
    }
  ]
}
```

**Purpose:** Mark consignment as RECEIVED with actual received quantities

**Response:**
```json
{
  "success": true,
  "message": "Consignment received successfully",
  "data": {
    "status": "RECEIVED",
    "received_at": "2025-01-30T14:00:00Z",
    "products_updated": 2
  }
}
```

---

#### 4.3 Cancel Consignment
```http
POST /api/vend/consignments/{id}/cancel
X-CSRF-Token: {token}
```

**Purpose:** Cancel consignment (can't be undone)

**Response:**
```json
{
  "success": true,
  "message": "Consignment cancelled successfully",
  "data": {
    "status": "CANCELLED",
    "cancelled_at": "2025-01-30T15:00:00Z"
  }
}
```

---

### 5. REPORTING (2 endpoints)

#### 5.1 Get Statistics
```http
GET /api/vend/consignments/statistics?period=week
```

**Query Parameters:**
- `period` - Time period (`today`, `week`, `month`, default: `month`)

**Response:**
```json
{
  "success": true,
  "data": {
    "period": "week",
    "total": 45,
    "by_status": {
      "OPEN": 12,
      "SENT": 8,
      "RECEIVED": 20,
      "CANCELLED": 5
    },
    "sync_stats": {
      "synced": 35,
      "not_synced": 5,
      "errors": 5
    }
  }
}
```

---

#### 5.2 Get Sync History
```http
GET /api/vend/consignments/sync-history?status=failed&limit=50
```

**Query Parameters:**
- `limit` - Results limit (default: 100, max: 200)
- `status` - Filter by status (`completed`, `failed`, `in_progress`)

**Response:**
```json
{
  "success": true,
  "data": {
    "logs": [
      {
        "id": 123,
        "purchase_order_id": 456,
        "vend_consignment_id": "cons_vend_xyz",
        "status": "completed",
        "started_at": "2025-01-30T10:00:00Z",
        "completed_at": "2025-01-30T10:05:00Z",
        "duration_seconds": 300,
        "message": "Successfully synced consignment",
        "error_message": null
      },
      {
        "id": 124,
        "purchase_order_id": 457,
        "vend_consignment_id": null,
        "status": "failed",
        "started_at": "2025-01-30T11:00:00Z",
        "completed_at": "2025-01-30T11:02:00Z",
        "duration_seconds": 120,
        "message": "Sync failed",
        "error_message": "API rate limit exceeded"
      }
    ],
    "total": 2
  }
}
```

---

## Error Handling

All endpoints return consistent error responses:

```json
{
  "success": false,
  "message": "Error message here",
  "data": {
    "error_code": "VALIDATION_ERROR",
    "details": {}
  }
}
```

**HTTP Status Codes:**
- `200` - Success
- `400` - Bad request (validation error)
- `401` - Unauthorized (not logged in)
- `403` - Forbidden (insufficient permissions)
- `404` - Not found
- `422` - Unprocessable entity (missing required fields)
- `500` - Internal server error

**Common Error Codes:**
- `VALIDATION_ERROR` - Invalid input data
- `NOT_FOUND` - Resource not found
- `PERMISSION_DENIED` - Insufficient permissions
- `VEND_API_ERROR` - Vend API error
- `SYNC_ERROR` - Sync operation failed
- `RATE_LIMIT` - API rate limit exceeded

---

## Authentication

All endpoints require authentication. Include session cookie:

```bash
curl -H "Cookie: PHPSESSID=your_session_id" \
  https://staff.vapeshed.co.nz/api/vend/consignments/list
```

For POST/PUT/PATCH/DELETE operations, also include CSRF token:

```bash
curl -X POST \
  -H "Cookie: PHPSESSID=your_session_id" \
  -H "X-CSRF-Token: your_csrf_token" \
  -H "Content-Type: application/json" \
  -d '{"name":"Test"}' \
  https://staff.vapeshed.co.nz/api/vend/consignments/create
```

---

## Permissions

Required permissions:
- **Read Operations** (GET): `payroll.view_consignments`
- **Write Operations** (POST/PUT/PATCH/DELETE): `payroll.manage_consignments`

---

## Integration with Existing Services

The API integrates with:

1. **VendAPI** (`/assets/services/VendAPI.php`) - Vend/Lightspeed REST API wrapper
2. **LightspeedSyncService** (`/assets/services/LightspeedSyncService.php`) - Sync orchestration
3. **QueueService** (`/assets/services/QueueService.php`) - Background job processing

**Queue Worker:**
For async sync operations, ensure queue worker is running:
```bash
php /modules/consignments/lightspeed-cli.php queue:work
```

---

## Quick Start Examples

### Create and Sync Consignment (Full Workflow)

```bash
# 1. Create consignment
CONSIGNMENT_ID=$(curl -s -X POST \
  https://staff.vapeshed.co.nz/api/vend/consignments/create \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=$SESSION" \
  -H "X-CSRF-Token: $CSRF" \
  -d '{
    "name": "Store Transfer #001",
    "type": "OUTLET",
    "outlet_id": "outlet123",
    "source_outlet_id": "outlet456"
  }' | jq -r '.data.consignment.id')

# 2. Add products
curl -X POST \
  https://staff.vapeshed.co.nz/api/vend/consignments/$CONSIGNMENT_ID/products/bulk \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=$SESSION" \
  -H "X-CSRF-Token: $CSRF" \
  -d '{
    "products": [
      {"product_id": "prod_abc", "count": 10, "cost": 25.50},
      {"product_id": "prod_def", "count": 5, "cost": 15.00}
    ]
  }'

# 3. Sync to Lightspeed
JOB_ID=$(curl -s -X POST \
  https://staff.vapeshed.co.nz/api/vend/consignments/$CONSIGNMENT_ID/sync \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=$SESSION" \
  -H "X-CSRF-Token: $CSRF" \
  -d '{"async": true}' | jq -r '.data.job_id')

# 4. Check sync status
curl https://staff.vapeshed.co.nz/api/vend/consignments/$CONSIGNMENT_ID/sync/status \
  -H "Cookie: PHPSESSID=$SESSION"

# 5. Send consignment
curl -X POST \
  https://staff.vapeshed.co.nz/api/vend/consignments/$CONSIGNMENT_ID/send \
  -H "Cookie: PHPSESSID=$SESSION" \
  -H "X-CSRF-Token: $CSRF"

# 6. Receive consignment
curl -X POST \
  https://staff.vapeshed.co.nz/api/vend/consignments/$CONSIGNMENT_ID/receive \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=$SESSION" \
  -H "X-CSRF-Token: $CSRF" \
  -d '{
    "received_quantities": [
      {"product_id": "consprod_123", "received": 10},
      {"product_id": "consprod_456", "received": 5}
    ]
  }'
```

---

## Testing

Test with comprehensive test suite:

```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/human_resources/payroll
php comprehensive-test.php
```

The test suite covers all 25 endpoints with:
- Auth verification
- CSRF protection
- Permission checks
- Input validation
- Error handling
- Response format validation

---

## Support

For issues or questions:
- Check logs: `/modules/human_resources/payroll/logs/payroll.log`
- Review sync logs: `/modules/consignments/logs/sync.log`
- Check queue status: `php lightspeed-cli.php queue:stats`
- Contact: IT Manager

---

## Related Documentation

- **Lightspeed Sync System**: `/modules/consignments/VEND_LIGHTSPEED_SYNC_LOCATION.md`
- **CLI Tool**: `/modules/consignments/LIGHTSPEED_QUICK_REF.md`
- **VendAPI Reference**: `/assets/services/VendAPI.php` (inline docs)
- **Phase 4 Complete**: `/modules/consignments/PHASE_4_COMPLETE.md`

---

**Version:** 1.0.0
**Last Updated:** 2025-01-30
**Maintainer:** CIS Development Team
