# Vend Sync Manager - API Documentation

**Base URL:** `https://staff.vapeshed.co.nz/modules/vend/api/sync.php`
**Version:** 1.0.0
**Authentication:** Bearer token in Authorization header

---

## Authentication

All API requests require authentication via Bearer token:

```bash
Authorization: Bearer YOUR_VEND_API_TOKEN
```

The token must match the configured `vend_access_token` in the CIS configuration table or `VEND_API_TOKEN` environment variable.

### Rate Limiting
- **Limit:** 60 requests per minute per IP address
- **Response:** `429 Too Many Requests` when exceeded
- **Reset:** Automatic after 60 seconds

---

## API Endpoints

### Sync Operations

#### Sync Entity
```http
POST /modules/vend/api/sync.php?action=sync&entity=products
Authorization: Bearer YOUR_TOKEN
```

**Parameters:**
- `entity` (required): products, sales, customers, inventory, consignments, outlets, categories, registers, payment_types, taxes
- `full` (optional): Include for full sync instead of incremental
- `since` (optional): Sync from specific date (YYYY-MM-DD)

**Example:**
```bash
curl -X POST \
  'https://staff.vapeshed.co.nz/modules/vend/api/sync.php?action=sync&entity=products&since=2024-01-01' \
  -H 'Authorization: Bearer YOUR_TOKEN'
```

**Response:**
```json
{
  "success": true,
  "result": {
    "action": "sync",
    "entity": "products",
    "output": "Syncing products...\n✓ Synced 150 products\n"
  },
  "timestamp": "2024-01-15T10:30:00+00:00"
}
```

#### Sync All Entities
```http
POST /modules/vend/api/sync.php?action=sync_all
Authorization: Bearer YOUR_TOKEN
```

**Parameters:**
- `full` (optional): Include for full sync

**Example:**
```bash
curl -X POST \
  'https://staff.vapeshed.co.nz/modules/vend/api/sync.php?action=sync_all' \
  -H 'Authorization: Bearer YOUR_TOKEN'
```

---

### Queue Operations

#### Get Queue Statistics
```http
POST /modules/vend/api/sync.php?action=queue_stats
Authorization: Bearer YOUR_TOKEN
```

**Example:**
```bash
curl -X POST \
  'https://staff.vapeshed.co.nz/modules/vend/api/sync.php?action=queue_stats' \
  -H 'Authorization: Bearer YOUR_TOKEN'
```

**Response:**
```json
{
  "success": true,
  "result": {
    "action": "queue_stats",
    "stats": [
      {
        "entity_type": "product",
        "status": "success",
        "count": 45120,
        "latest": "2024-01-15 10:25:00"
      },
      {
        "entity_type": "product",
        "status": "failed",
        "count": 3,
        "latest": "2024-01-15 09:10:00"
      }
    ]
  },
  "timestamp": "2024-01-15T10:30:00+00:00"
}
```

#### Process Queue
```http
POST /modules/vend/api/sync.php?action=queue_process&limit=100
Authorization: Bearer YOUR_TOKEN
```

**Parameters:**
- `limit` (optional): Number of items to process (default: 100)

#### Process Failed Queue Items
```http
POST /modules/vend/api/sync.php?action=queue_failed&limit=50
Authorization: Bearer YOUR_TOKEN
```

**Parameters:**
- `limit` (optional): Number of failed items to retry (default: 50)

---

### Webhook Operations

#### Process Webhook
```http
POST /modules/vend/api/sync.php?action=webhook_process
Authorization: Bearer YOUR_TOKEN
Content-Type: application/json

{
  "event": "product.updated",
  "id": "webhook_123",
  "data": {
    "id": "product_456",
    "name": "Updated Product"
  }
}
```

**Supported Events:**
- `product.created`, `product.updated`, `product.deleted`
- `sale.created`, `sale.updated`
- `customer.created`, `customer.updated`
- `consignment.created`, `consignment.updated`, `consignment.sent`, `consignment.received`
- `inventory.updated`

**Example:**
```bash
curl -X POST \
  'https://staff.vapeshed.co.nz/modules/vend/api/sync.php?action=webhook_process' \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{
    "event": "consignment.sent",
    "id": "wh_789",
    "data": {
      "id": "consignment_123",
      "source_outlet_id": "outlet_1",
      "destination_outlet_id": "outlet_2"
    }
  }'
```

**Response:**
```json
{
  "success": true,
  "result": {
    "success": true,
    "result": {
      "action": "queued",
      "queue_id": 98860,
      "state_updated": true
    }
  },
  "timestamp": "2024-01-15T10:30:00+00:00"
}
```

#### List Webhook Events
```http
POST /modules/vend/api/sync.php?action=webhook_events
Authorization: Bearer YOUR_TOKEN
```

**Response:**
```json
{
  "success": true,
  "result": {
    "action": "webhook_events",
    "events": [
      "product.created",
      "product.updated",
      "product.deleted",
      "sale.created",
      "sale.updated",
      "customer.created",
      "customer.updated",
      "consignment.created",
      "consignment.updated",
      "consignment.sent",
      "consignment.received",
      "inventory.updated"
    ]
  },
  "timestamp": "2024-01-15T10:30:00+00:00"
}
```

---

### Consignment Operations

#### Validate Consignment
```http
POST /modules/vend/api/sync.php?action=consignment_validate&id=12345
Authorization: Bearer YOUR_TOKEN
```

**Parameters:**
- `id` (required): Consignment ID

**Response:**
```json
{
  "success": true,
  "result": {
    "action": "consignment_validate",
    "id": "12345",
    "output": "Current State: PACKING\nCan Transition To:\n  ✓ PACKAGED\n  ✗ CANCELLED (not allowed from PACKING)\n"
  },
  "timestamp": "2024-01-15T10:30:00+00:00"
}
```

#### Transition Consignment State
```http
POST /modules/vend/api/sync.php?action=consignment_transition&id=12345&to=PACKAGED
Authorization: Bearer YOUR_TOKEN
```

**Parameters:**
- `id` (required): Consignment ID
- `to` (required): Target state (DRAFT, OPEN, PACKING, PACKAGED, SENT, RECEIVING, PARTIAL, RECEIVED, CLOSED, CANCELLED, ARCHIVED)
- `dry_run` (optional): Include to validate without committing

**Example:**
```bash
curl -X POST \
  'https://staff.vapeshed.co.nz/modules/vend/api/sync.php?action=consignment_transition&id=12345&to=PACKAGED&dry_run=1' \
  -H 'Authorization: Bearer YOUR_TOKEN'
```

**Response:**
```json
{
  "success": true,
  "result": {
    "action": "consignment_transition",
    "id": "12345",
    "to": "PACKAGED",
    "dry_run": true,
    "output": "[DRY RUN] Would transition from PACKING → PACKAGED\n✓ Transition is valid\n"
  },
  "timestamp": "2024-01-15T10:30:00+00:00"
}
```

---

### Health Operations

#### Health Check
```http
POST /modules/vend/api/sync.php?action=health
Authorization: Bearer YOUR_TOKEN
```

**Response:**
```json
{
  "success": true,
  "result": {
    "action": "health",
    "checks": [
      "✓ Database: Connected",
      "✓ API: Connected (200 OK)",
      "✓ Queue: 98859 items",
      "✓ Disk: 45.2GB free"
    ],
    "output": "..."
  },
  "timestamp": "2024-01-15T10:30:00+00:00"
}
```

#### API Health Check
```http
POST /modules/vend/api/sync.php?action=health_api
Authorization: Bearer YOUR_TOKEN
```

---

### Audit Operations

#### Get Audit Logs
```http
POST /modules/vend/api/sync.php?action=audit_logs&entity=product&limit=100
Authorization: Bearer YOUR_TOKEN
```

**Parameters:**
- `entity` (optional): Filter by entity type
- `limit` (optional): Number of logs to return (default: 100)

**Response:**
```json
{
  "success": true,
  "result": {
    "action": "audit_logs",
    "logs": [
      {
        "id": 54321,
        "correlation_id": "sync-20240115-103000",
        "entity_type": "product",
        "action": "sync",
        "status": "success",
        "message": "Synced 150 products",
        "context": "{}",
        "duration": 1.234,
        "created_at": "2024-01-15 10:30:00"
      }
    ]
  },
  "timestamp": "2024-01-15T10:30:00+00:00"
}
```

#### Get Sync Status
```http
POST /modules/vend/api/sync.php?action=audit_status
Authorization: Bearer YOUR_TOKEN
```

**Response:**
```json
{
  "success": true,
  "result": {
    "action": "audit_status",
    "stats": [
      {
        "entity_type": "product",
        "status": "success",
        "count": 245,
        "latest": "2024-01-15 10:30:00"
      },
      {
        "entity_type": "product",
        "status": "error",
        "count": 2,
        "latest": "2024-01-15 09:15:00"
      }
    ]
  },
  "timestamp": "2024-01-15T10:30:00+00:00"
}
```

---

### Utility Operations

#### Get Version
```http
POST /modules/vend/api/sync.php?action=version
Authorization: Bearer YOUR_TOKEN
```

**Response:**
```json
{
  "success": true,
  "result": {
    "action": "version",
    "version": "1.0.0",
    "cli_path": "/path/to/vend-sync-manager.php",
    "api_path": "/path/to/sync.php"
  },
  "timestamp": "2024-01-15T10:30:00+00:00"
}
```

---

## Error Responses

### Authentication Error (401)
```json
{
  "error": "Unauthorized. Valid API token required."
}
```

### Rate Limit Error (429)
```json
{
  "error": "Rate limit exceeded. Try again later."
}
```

### Validation Error (400)
```json
{
  "error": "Entity parameter required"
}
```

### Server Error (500)
```json
{
  "success": false,
  "error": "Failed to sync products: Connection timeout",
  "timestamp": "2024-01-15T10:30:00+00:00"
}
```

### Method Not Allowed (405)
```json
{
  "error": "Method not allowed. Use POST."
}
```

---

## Integration Examples

### PHP
```php
<?php

function callVendSyncAPI(string $action, array $params = []): array
{
    $token = cis_config_get('vend_api_token');
    $baseUrl = 'https://staff.vapeshed.co.nz/modules/vend/api/sync.php';

    $url = $baseUrl . '?action=' . urlencode($action);
    foreach ($params as $key => $value) {
        $url .= '&' . urlencode($key) . '=' . urlencode($value);
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ],
        CURLOPT_TIMEOUT => 30,
    ]);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode !== 200) {
        throw new Exception("API error: HTTP $httpCode - $response");
    }

    return json_decode($response, true);
}

// Example usage
$result = callVendSyncAPI('sync', ['entity' => 'products', 'since' => '2024-01-01']);
print_r($result);
```

### JavaScript
```javascript
async function callVendSyncAPI(action, params = {}) {
  const token = 'YOUR_TOKEN';
  const baseUrl = 'https://staff.vapeshed.co.nz/modules/vend/api/sync.php';

  const queryString = new URLSearchParams({ action, ...params }).toString();
  const url = `${baseUrl}?${queryString}`;

  const response = await fetch(url, {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Content-Type': 'application/json',
    },
  });

  if (!response.ok) {
    throw new Error(`API error: ${response.status}`);
  }

  return await response.json();
}

// Example usage
callVendSyncAPI('sync', { entity: 'products', since: '2024-01-01' })
  .then(result => console.log(result))
  .catch(error => console.error(error));
```

### cURL
```bash
# Sync products
curl -X POST \
  'https://staff.vapeshed.co.nz/modules/vend/api/sync.php?action=sync&entity=products' \
  -H 'Authorization: Bearer YOUR_TOKEN'

# Get queue stats
curl -X POST \
  'https://staff.vapeshed.co.nz/modules/vend/api/sync.php?action=queue_stats' \
  -H 'Authorization: Bearer YOUR_TOKEN'

# Process webhook
curl -X POST \
  'https://staff.vapeshed.co.nz/modules/vend/api/sync.php?action=webhook_process' \
  -H 'Authorization: Bearer YOUR_TOKEN' \
  -H 'Content-Type: application/json' \
  -d '{"event":"product.updated","id":"wh_123","data":{"id":"prod_456"}}'

# Health check
curl -X POST \
  'https://staff.vapeshed.co.nz/modules/vend/api/sync.php?action=health' \
  -H 'Authorization: Bearer YOUR_TOKEN'
```

---

## Best Practices

### Security
- **Never expose your API token** in client-side code
- Store tokens in server-side environment variables or secure configuration
- Use HTTPS for all API requests
- Rotate tokens regularly

### Performance
- Use incremental syncs (`--since`) instead of full syncs when possible
- Process queue items in batches to avoid timeouts
- Monitor rate limits and implement exponential backoff
- Cache API responses when appropriate

### Error Handling
- Always check the `success` field in responses
- Log all API errors for debugging
- Implement retry logic with exponential backoff
- Handle rate limit errors gracefully (wait and retry)

### Monitoring
- Track API response times
- Monitor queue growth
- Alert on failed sync operations
- Review audit logs regularly

---

## Support

For issues or questions:
- **CLI Documentation**: `/modules/vend/cli/VEND_SYNC_USAGE.md`
- **Quick Reference**: `/modules/vend/cli/QUICK_REFERENCE.md`
- **Deployment Guide**: `/modules/vend/cli/DEPLOYMENT_CHECKLIST.md`
- **Setup Script**: `/modules/vend/cli/setup.sql`

---

*API Version: 1.0.0*
*Last Updated: 2024*
