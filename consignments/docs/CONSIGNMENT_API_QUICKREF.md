# Consignment API Quick Reference

## 🚀 Quick Start

### API Endpoint
```
POST https://staff.vapeshed.co.nz/modules/consignments/api.php
```

### Request Format
```json
{
  "action": "action_name",
  "data": {
    // action-specific parameters
  }
}
```

### Response Format
```json
// Success
{
  "ok": true,
  "data": { /* results */ },
  "time": "2025-10-31T10:30:00+00:00"
}

// Error
{
  "ok": false,
  "error": "Error message",
  "meta": { /* additional context */ },
  "time": "2025-10-31T10:30:00+00:00"
}
```

---

## 📖 Actions

### 🔍 Read Operations (No CSRF required)

#### `recent` - Get recent consignments
```json
{
  "action": "recent",
  "data": {
    "limit": 50  // optional, default 50, max 200
  }
}
```

**Response:**
```json
{
  "ok": true,
  "data": {
    "rows": [
      {
        "id": 123,
        "ref_code": "CON-2024-001",
        "status": "sent",
        "origin_outlet_id": 1,
        "dest_outlet_id": 5,
        "created_by": 42,
        "created_at": "2025-10-31 10:00:00",
        "updated_at": "2025-10-31 10:00:00",
        "item_count": "10"
      }
    ],
    "count": 1
  }
}
```

#### `get` - Get single consignment with items
```json
{
  "action": "get",
  "data": {
    "id": 123  // required
  }
}
```

**Response:**
```json
{
  "ok": true,
  "data": {
    "consignment": {
      "id": 123,
      "ref_code": "CON-2024-001",
      "status": "sent",
      // ... full consignment data
    },
    "items": [
      {
        "id": 1,
        "consignment_id": 123,
        "product_id": "abc123",
        "sku": "SKU-001",
        "qty": 10,
        "packed_qty": 10,
        "status": "pending",
        "created_at": "2025-10-31 10:00:00"
      }
    ]
  }
}
```

#### `search` - Search consignments
```json
{
  "action": "search",
  "data": {
    "ref_code": "CON",     // optional, partial match
    "outlet_id": 5,         // optional, filter by origin or dest
    "limit": 50             // optional, default 50, max 200
  }
}
```

**Response:** Same as `recent`

#### `stats` - Get statistics
```json
{
  "action": "stats",
  "data": {
    "outlet_id": 5  // optional, filter by outlet
  }
}
```

**Response:**
```json
{
  "ok": true,
  "data": {
    "draft": 5,
    "sent": 12,
    "receiving": 3,
    "received": 8,
    "completed": 42,
    "total": 70
  }
}
```

---

### ✏️ Write Operations (CSRF required)

#### `create` - Create new consignment
```json
{
  "action": "create",
  "data": {
    "csrf": "your_csrf_token",     // required
    "ref_code": "CON-2024-001",    // required
    "origin_outlet_id": 1,         // required
    "dest_outlet_id": 5,           // required
    "created_by": 42               // required (user ID)
  }
}
```

**Response:**
```json
{
  "ok": true,
  "data": {
    "id": 123,
    "ref_code": "CON-2024-001"
  }
}
```

#### `add_item` - Add item to consignment
```json
{
  "action": "add_item",
  "data": {
    "csrf": "your_csrf_token",     // required
    "consignment_id": 123,         // required
    "product_id": "abc123",        // required
    "sku": "SKU-001",              // required
    "qty": 10,                     // required
    "packed_qty": 10,              // optional, default 0
    "status": "pending"            // optional, default "pending"
  }
}
```

**Response:**
```json
{
  "ok": true,
  "data": {
    "id": 456
  }
}
```

#### `status` - Update consignment status
```json
{
  "action": "status",
  "data": {
    "csrf": "your_csrf_token",     // required
    "id": 123,                     // required
    "status": "sent"               // required: draft|sent|receiving|received|completed
  }
}
```

**Response:**
```json
{
  "ok": true,
  "data": {
    "updated": true,
    "id": 123,
    "status": "sent"
  }
}
```

#### `update_item_qty` - Update item packed quantity
```json
{
  "action": "update_item_qty",
  "data": {
    "csrf": "your_csrf_token",     // required
    "item_id": 456,                // required
    "packed_qty": 8                // required
  }
}
```

**Response:**
```json
{
  "ok": true,
  "data": {
    "updated": true,
    "item_id": 456,
    "packed_qty": 8
  }
}
```

---

## 📝 JavaScript Client Example

```javascript
class ConsignmentsAPI {
  constructor(baseUrl = '/modules/consignments/api.php') {
    this.baseUrl = baseUrl;
    this.csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
  }

  async request(action, data = {}) {
    try {
      const response = await fetch(this.baseUrl, {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action, data})
      });

      const result = await response.json();

      if (!result.ok) {
        throw new Error(result.error || 'API request failed');
      }

      return result.data;
    } catch (error) {
      console.error('API Error:', error);
      throw error;
    }
  }

  // Read operations
  async recent(limit = 50) {
    return this.request('recent', {limit});
  }

  async get(id) {
    return this.request('get', {id});
  }

  async search(refCode = '', outletId = null, limit = 50) {
    return this.request('search', {
      ref_code: refCode,
      outlet_id: outletId,
      limit
    });
  }

  async stats(outletId = null) {
    return this.request('stats', {outlet_id: outletId});
  }

  // Write operations
  async create(payload) {
    return this.request('create', {
      ...payload,
      csrf: this.csrfToken
    });
  }

  async addItem(consignmentId, item) {
    return this.request('add_item', {
      ...item,
      consignment_id: consignmentId,
      csrf: this.csrfToken
    });
  }

  async updateStatus(id, status) {
    return this.request('status', {
      id,
      status,
      csrf: this.csrfToken
    });
  }

  async updateItemQty(itemId, packedQty) {
    return this.request('update_item_qty', {
      item_id: itemId,
      packed_qty: packedQty,
      csrf: this.csrfToken
    });
  }
}

// Usage examples
const api = new ConsignmentsAPI();

// Get recent consignments
api.recent(10).then(data => {
  console.log('Recent:', data.rows);
});

// Get single consignment
api.get(123).then(data => {
  console.log('Consignment:', data.consignment);
  console.log('Items:', data.items);
});

// Search
api.search('CON-2024').then(data => {
  console.log('Found:', data.rows);
});

// Get stats
api.stats().then(data => {
  console.log('Total:', data.total);
  console.log('By status:', data);
});

// Create consignment
api.create({
  ref_code: 'CON-2024-001',
  origin_outlet_id: 1,
  dest_outlet_id: 5,
  created_by: 42
}).then(data => {
  console.log('Created consignment:', data.id);
});

// Add item
api.addItem(123, {
  product_id: 'abc123',
  sku: 'SKU-001',
  qty: 10,
  packed_qty: 10
}).then(data => {
  console.log('Added item:', data.id);
});

// Update status
api.updateStatus(123, 'sent').then(data => {
  console.log('Status updated:', data.status);
});
```

---

## 🧪 Testing with cURL

### Read Operations
```bash
# Recent consignments
curl -X POST https://staff.vapeshed.co.nz/modules/consignments/api.php \
  -H "Content-Type: application/json" \
  -d '{"action":"recent","data":{"limit":10}}'

# Get single consignment
curl -X POST https://staff.vapeshed.co.nz/modules/consignments/api.php \
  -H "Content-Type: application/json" \
  -d '{"action":"get","data":{"id":123}}'

# Search by ref_code
curl -X POST https://staff.vapeshed.co.nz/modules/consignments/api.php \
  -H "Content-Type: application/json" \
  -d '{"action":"search","data":{"ref_code":"CON-2024"}}'

# Get statistics
curl -X POST https://staff.vapeshed.co.nz/modules/consignments/api.php \
  -H "Content-Type: application/json" \
  -d '{"action":"stats","data":{}}'
```

### Write Operations (with cookies for CSRF)
```bash
# Save cookies from logged-in session
curl -X POST https://staff.vapeshed.co.nz/login \
  -c cookies.txt \
  -d "username=user&password=pass"

# Create consignment (replace TOKEN with actual CSRF token)
curl -X POST https://staff.vapeshed.co.nz/modules/consignments/api.php \
  -H "Content-Type: application/json" \
  -b cookies.txt \
  -d '{"action":"create","data":{
    "csrf":"TOKEN",
    "ref_code":"CON-2024-001",
    "origin_outlet_id":1,
    "dest_outlet_id":5,
    "created_by":42
  }}'
```

---

## ⚠️ Error Handling

### Common Errors

#### 400 Bad Request
```json
{
  "ok": false,
  "error": "action required",
  "meta": {
    "allowed": ["recent", "get", "create", "add_item", "status", "search", "stats"]
  }
}
```

#### 403 Forbidden (CSRF)
```json
{
  "ok": false,
  "error": "Invalid CSRF token"
}
```

#### 404 Not Found
```json
{
  "ok": false,
  "error": "Consignment not found",
  "meta": {"id": 999999}
}
```

#### 405 Method Not Allowed
```json
{
  "ok": false,
  "error": "POST required"
}
```

#### 500 Server Error
```json
{
  "ok": false,
  "error": "Database error",
  "meta": {
    "code": "HY000",
    "debug": "Connection timeout"  // only in development
  }
}
```

---

## 🔐 Security Notes

1. **CSRF Protection**: All write operations require valid CSRF token
2. **POST Only**: API rejects GET requests (prevents CSRF attacks)
3. **Input Validation**: Required fields checked before processing
4. **SQL Injection**: All queries use PDO prepared statements
5. **Error Logging**: All exceptions logged to PHP error log
6. **Rate Limiting**: Consider implementing rate limits in production

---

## 📊 Performance Tips

1. **Pagination**: Use `limit` parameter to control result size
2. **Indexes**: Run migration script for optimal query performance
3. **Caching**: Consider caching frequently accessed data
4. **Monitoring**: Track response times and error rates
5. **Load Testing**: Test with concurrent requests before production

---

## 🔗 Related Documentation

- Full Status Report: `/modules/consignments/CONSIGNMENT_MODERNIZATION_STATUS.md`
- Service Layer: `/modules/consignments/ConsignmentService.php`
- Test Suite: `/modules/consignments/tests/test-consignment-api.sh`
- Migration Script: `/modules/consignments/migrations/add-consignment-indexes.sql`

---

**Version:** 1.0.0
**Last Updated:** 2025-10-31
**Support:** Development Team
