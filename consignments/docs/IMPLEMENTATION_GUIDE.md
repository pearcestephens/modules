# 🚀 Transfer Manager API - Implementation Guide

## Overview

This guide walks you through implementing the new **TransferManagerAPI** (backend-v2.php) to replace the legacy backend.php.

**Estimated Time:** 2-4 hours
**Risk Level:** Low (backward compatible, rollback ready)
**Downtime Required:** Zero

---

## 📋 Prerequisites

### 1. Verify Files Are in Place

```bash
# Check API class exists
ls -l /modules/consignments/lib/TransferManagerAPI.php

# Check wrapper exists
ls -l /modules/consignments/TransferManager/backend-v2.php

# Check old backend still exists (for rollback)
ls -l /modules/consignments/TransferManager/backend.php

# Expected output: All 3 files present
```

### 2. Verify Database Structure

```bash
mysql -u username -p database_name << EOF
DESCRIBE transfers;
SELECT COUNT(*) as transfer_count FROM transfers;
SELECT DISTINCT consignment_category FROM transfers;
EOF

# Expected: Table exists, has data, shows all transfer types
```

### 3. Test PHP Syntax

```bash
php -l /modules/consignments/lib/TransferManagerAPI.php
php -l /modules/consignments/TransferManager/backend-v2.php

# Expected: No syntax errors
```

---

## 🎯 Phase 1: Backend Testing (No Frontend Changes)

### Step 1.1: Test New Endpoint Directly

```bash
# Test init endpoint
curl -X POST https://staff.vapeshed.co.nz/modules/consignments/TransferManager/backend-v2.php \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=your_session_id" \
  -d '{"action":"init","data":{}}'

# Expected response:
# {
#   "success": true,
#   "message": "Configuration loaded successfully",
#   "request_id": "req_...",
#   "data": {
#     "outlets": [...],
#     "suppliers": [...],
#     "csrf_token": "...",
#     "sync_enabled": true/false
#   }
# }
```

### Step 1.2: Test List Transfers

```bash
# List all transfers
curl -X POST https://staff.vapeshed.co.nz/modules/consignments/TransferManager/backend-v2.php \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=your_session_id" \
  -d '{
    "action": "list_transfers",
    "data": {
      "page": 1,
      "perPage": 10
    }
  }'

# Expected: List of transfers with pagination metadata
```

### Step 1.3: Test Transfer Detail

```bash
# Get a specific transfer (use ID from previous test)
curl -X POST https://staff.vapeshed.co.nz/modules/consignments/TransferManager/backend-v2.php \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=your_session_id" \
  -d '{
    "action": "get_transfer_detail",
    "data": {
      "id": 123
    }
  }'

# Expected: Full transfer details with items and notes
```

### Step 1.4: Run Automated Test Suite

```bash
cd /modules/consignments/
./test-transfer-manager.sh

# Expected: All tests pass
```

**✅ Phase 1 Complete When:**
- All manual curl tests return proper envelopes
- Automated test script passes
- No PHP errors in logs

---

## 🔄 Phase 2: Parallel Run (Both Endpoints Active)

### Step 2.1: Monitor Old Endpoint

```bash
# Watch logs for old backend
tail -f /var/log/apache2/error.log | grep "backend.php"

# Count requests to old endpoint
grep "backend.php" /var/log/apache2/access.log | wc -l
```

### Step 2.2: Add Logging to Track Usage

Add this to your monitoring dashboard:

```sql
-- Count requests by endpoint
SELECT
  DATE(timestamp) as date,
  COUNT(*) as requests,
  endpoint
FROM api_request_log
WHERE endpoint LIKE '%TransferManager%'
GROUP BY DATE(timestamp), endpoint
ORDER BY date DESC;
```

### Step 2.3: Compare Responses

Create a comparison script:

```bash
#!/bin/bash
# compare-endpoints.sh

TRANSFER_ID=123
SESSION="your_session_id"

echo "Testing OLD endpoint..."
curl -s -X POST \
  https://staff.vapeshed.co.nz/modules/consignments/TransferManager/backend.php \
  -H "Cookie: PHPSESSID=$SESSION" \
  -d "action=get_transfer_detail&id=$TRANSFER_ID" \
  > /tmp/old-response.json

echo "Testing NEW endpoint..."
curl -s -X POST \
  https://staff.vapeshed.co.nz/modules/consignments/TransferManager/backend-v2.php \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=$SESSION" \
  -d "{\"action\":\"get_transfer_detail\",\"data\":{\"id\":$TRANSFER_ID}}" \
  > /tmp/new-response.json

echo "Comparing data sections..."
jq '.data' /tmp/old-response.json > /tmp/old-data.json
jq '.data' /tmp/new-response.json > /tmp/new-data.json

diff /tmp/old-data.json /tmp/new-data.json
```

**✅ Phase 2 Complete When:**
- Both endpoints return same data
- Performance is equal or better
- No errors in logs for 7 days

---

## 🎨 Phase 3: Frontend Migration

### Step 3.1: Update JavaScript Constants

**File:** `/modules/consignments/assets/js/transfer-manager.js`

```javascript
// OLD
const API_ENDPOINT = '/modules/consignments/TransferManager/backend.php';

// NEW
const API_ENDPOINT = '/modules/consignments/TransferManager/backend-v2.php';
```

### Step 3.2: Update API Call Handler

**Before:**
```javascript
fetch(API_ENDPOINT, {
  method: 'POST',
  body: new FormData(form)  // Old: FormData
})
.then(response => response.json())
.then(result => {
  if (result.success) {
    console.log(result.data);
  }
});
```

**After:**
```javascript
fetch(API_ENDPOINT, {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    action: 'list_transfers',
    data: {
      page: 1,
      perPage: 25
    }
  })
})
.then(response => response.json())
.then(result => {
  if (result.success) {
    console.log('Request ID:', result.request_id);
    console.log('Performance:', result.meta.duration_ms + 'ms');
    console.log('Data:', result.data);
  } else {
    console.error(`[${result.error.code}] ${result.error.message}`);
  }
});
```

### Step 3.3: Update Response Handlers

**Pattern 1: List Transfers**

```javascript
// OLD
function loadTransfers(page) {
  fetch(API_ENDPOINT, {
    method: 'POST',
    body: new FormData(...)
  })
  .then(r => r.json())
  .then(result => {
    if (result.success) {
      renderTransfers(result.data);
    }
  });
}

// NEW
function loadTransfers(page) {
  fetch(API_ENDPOINT, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      action: 'list_transfers',
      data: { page, perPage: 25 }
    })
  })
  .then(r => r.json())
  .then(result => {
    if (result.success) {
      renderTransfers(result.data);
      updatePagination(result.meta.pagination);
    } else {
      showError(result.error.message);
    }
  });
}
```

**Pattern 2: Create Transfer**

```javascript
// OLD
function createTransfer(category, from, to) {
  const formData = new FormData();
  formData.append('action', 'create_transfer');
  formData.append('csrf', csrfToken);
  formData.append('consignment_category', category);
  formData.append('outlet_from', from);
  formData.append('outlet_to', to);

  fetch(API_ENDPOINT, {
    method: 'POST',
    body: formData
  })
  .then(r => r.json())
  .then(result => {
    if (result.success) {
      alert('Transfer created');
    }
  });
}

// NEW
function createTransfer(category, from, to) {
  fetch(API_ENDPOINT, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      action: 'create_transfer',
      data: {
        csrf: csrfToken,
        consignment_category: category,
        outlet_from: from,
        outlet_to: to
      }
    })
  })
  .then(r => r.json())
  .then(result => {
    if (result.success) {
      showSuccess(`Transfer created (ID: ${result.request_id})`);
      loadTransferDetail(result.data.id);
    } else {
      showError(`[${result.error.code}] ${result.error.message}`);
    }
  });
}
```

**Pattern 3: Add Item to Transfer**

```javascript
// NEW
function addItemToTransfer(transferId, productId, quantity) {
  fetch(API_ENDPOINT, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      action: 'add_transfer_item',
      data: {
        csrf: csrfToken,
        transfer_id: transferId,
        product_id: productId,
        quantity: quantity
      }
    })
  })
  .then(r => r.json())
  .then(result => {
    if (result.success) {
      showSuccess('Item added successfully');
      loadTransferDetail(transferId); // Reload transfer
    } else {
      showError(result.error.message);
    }
  });
}
```

### Step 3.4: Update Error Handling

```javascript
// Global error handler
function handleApiError(result) {
  if (!result.success) {
    console.error('API Error:', {
      code: result.error.code,
      message: result.error.message,
      details: result.error.details,
      request_id: result.request_id
    });

    // Log to monitoring
    logToMonitoring({
      type: 'api_error',
      request_id: result.request_id,
      error_code: result.error.code,
      endpoint: 'transfer_manager'
    });

    // Show user-friendly message
    showErrorToast(result.error.message);

    return false;
  }
  return true;
}

// Usage
fetch(API_ENDPOINT, {...})
  .then(r => r.json())
  .then(result => {
    if (handleApiError(result)) {
      // Success path
      processData(result.data);
    }
  });
```

### Step 3.5: Add Request ID Logging

```javascript
// Log all API requests with request_id
function apiCall(action, data) {
  return fetch(API_ENDPOINT, {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ action, data })
  })
  .then(r => r.json())
  .then(result => {
    // Log request_id for debugging
    console.log(`[${result.request_id}] ${action}:`,
                result.success ? 'success' : 'failed');

    if (result.meta) {
      console.log(`  Performance: ${result.meta.duration_ms}ms`);
    }

    return result;
  });
}

// Usage
apiCall('list_transfers', { page: 1, perPage: 25 })
  .then(result => {
    if (result.success) {
      renderTransfers(result.data);
    }
  });
```

**✅ Phase 3 Complete When:**
- All frontend calls use new endpoint
- Error handling shows proper messages
- Request IDs logged for debugging
- User testing confirms all features work

---

## 📊 Phase 4: Monitoring & Validation

### Step 4.1: Monitor Performance

```sql
-- Average response times by action
SELECT
  JSON_EXTRACT(log_data, '$.action') as action,
  AVG(JSON_EXTRACT(log_data, '$.meta.duration_ms')) as avg_ms,
  COUNT(*) as count
FROM api_logs
WHERE endpoint = 'transfer_manager'
  AND created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY action
ORDER BY avg_ms DESC;
```

### Step 4.2: Monitor Errors

```sql
-- Error rate by type
SELECT
  JSON_EXTRACT(log_data, '$.error.code') as error_code,
  COUNT(*) as occurrences,
  GROUP_CONCAT(DISTINCT JSON_EXTRACT(log_data, '$.request_id')) as sample_request_ids
FROM api_logs
WHERE endpoint = 'transfer_manager'
  AND success = 0
  AND created_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY error_code
ORDER BY occurrences DESC;
```

### Step 4.3: User Acceptance Testing

**Test Checklist:**

- [ ] **Create Transfer**
  - [ ] STOCK transfer
  - [ ] JUICE transfer
  - [ ] PURCHASE_ORDER transfer
  - [ ] INTERNAL transfer
  - [ ] RETURN transfer
  - [ ] STAFF transfer

- [ ] **Edit Transfer**
  - [ ] Add product
  - [ ] Update quantity
  - [ ] Remove product
  - [ ] Add note

- [ ] **Status Changes**
  - [ ] Mark as sent
  - [ ] Mark as receiving
  - [ ] Complete receiving
  - [ ] Cancel transfer
  - [ ] Revert status

- [ ] **Filtering**
  - [ ] Filter by type
  - [ ] Filter by status
  - [ ] Filter by outlet
  - [ ] Search by text

- [ ] **Pagination**
  - [ ] Next page
  - [ ] Previous page
  - [ ] Jump to page

**✅ Phase 4 Complete When:**
- All tests pass
- Performance metrics acceptable
- Error rate < 0.1%
- User feedback positive

---

## 🗑️ Phase 5: Deprecation & Cleanup

### Step 5.1: Verify Zero Traffic to Old Endpoint

```bash
# Check access logs
grep "backend.php" /var/log/apache2/access.log | \
  awk '{print $4}' | \
  sort | uniq -c | tail -n 7

# Expected: No recent requests (0 in last 7 days)
```

### Step 5.2: Backup Old Backend

```bash
cd /modules/consignments/TransferManager/
mv backend.php backend.php.deprecated_$(date +%Y%m%d)
```

### Step 5.3: Optional: Redirect Old Endpoint

```php
<?php
// backend.php (redirect to new)
header('HTTP/1.1 301 Moved Permanently');
header('Location: backend-v2.php');
exit;
?>
```

Or create a wrapper:

```php
<?php
// backend.php (wrapper)
error_log('[DEPRECATED] Transfer Manager old endpoint called. Redirecting to backend-v2.php');

// Forward to new endpoint
require_once __DIR__ . '/backend-v2.php';
?>
```

### Step 5.4: Update Documentation

```bash
# Update any references
grep -r "backend.php" /docs/ | grep TransferManager
# Replace with backend-v2.php
```

**✅ Phase 5 Complete When:**
- Old endpoint backed up
- No production traffic to old endpoint
- Documentation updated
- Team notified

---

## 🔙 Rollback Procedures

### If Issues Arise in Phase 3 (Frontend):

**Instant Rollback (1 minute):**

```javascript
// In transfer-manager.js, change back:
const API_ENDPOINT = '/modules/consignments/TransferManager/backend.php';

// Clear browser cache
// Ctrl+F5 or Cmd+Shift+R
```

### If Issues Arise in Backend:

**Disable new endpoint:**

```bash
cd /modules/consignments/TransferManager/
mv backend-v2.php backend-v2.php.disabled
```

**Check logs:**

```bash
tail -n 100 /var/log/apache2/error.log | grep TransferManager
```

**Restore old endpoint:**

```bash
# If you renamed it
mv backend.php.deprecated_YYYYMMDD backend.php
```

---

## 📈 Success Metrics

### Performance Targets

| Metric | Target | How to Measure |
|--------|--------|----------------|
| Average Response Time | < 100ms | `result.meta.duration_ms` |
| Error Rate | < 0.1% | API logs analysis |
| Uptime | 99.9% | Monitoring dashboard |
| User Satisfaction | > 95% | User surveys |

### Monitoring Dashboard

Create a dashboard showing:
- Request volume (by action)
- Average response time (by action)
- Error rate (by error code)
- Request IDs for recent errors
- Performance trends (7-day rolling)

---

## 🆘 Troubleshooting

### Issue: "Invalid action" error

**Cause:** Action name mismatch
**Fix:** Check action name is exact match (case-sensitive)

```javascript
// Wrong
action: 'listTransfers'

// Correct
action: 'list_transfers'
```

### Issue: "CSRF validation failed"

**Cause:** Missing or invalid CSRF token
**Fix:** Get fresh token from init endpoint

```javascript
// Get CSRF token first
fetch(API_ENDPOINT, {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ action: 'init', data: {} })
})
.then(r => r.json())
.then(result => {
  csrfToken = result.data.csrf_token;
  // Now make other requests
});
```

### Issue: Response missing pagination

**Cause:** Using old response structure
**Fix:** Access pagination via meta object

```javascript
// Wrong
const totalPages = result.data.total_pages;

// Correct
const totalPages = result.meta.pagination.total_pages;
```

### Issue: Database connection errors

**Check:**
```bash
# Test database connection
php -r "
\$db = new mysqli('localhost', 'user', 'pass', 'database');
if (\$db->connect_error) {
    die('Connection failed: ' . \$db->connect_error);
}
echo 'Database OK';
"
```

---

## 📞 Support

### Getting Help

1. **Check logs first:**
   ```bash
   tail -f /var/log/apache2/error.log | grep TransferManager
   ```

2. **Find request_id from error:**
   - Look in browser console
   - Check error message
   - Search logs for request_id

3. **Review documentation:**
   - API_ENVELOPE_STANDARDS.md
   - TRANSFER_MANAGER_ENDPOINT_MAPPING.md
   - TRANSFER_TYPES_COMPLETE.md

4. **Contact team:**
   - Include request_id
   - Include error code
   - Include steps to reproduce

---

## ✅ Final Checklist

Before marking complete:

- [ ] Phase 1: Backend testing complete
- [ ] Phase 2: Parallel run successful (7 days)
- [ ] Phase 3: Frontend migration complete
- [ ] Phase 4: Monitoring confirms stability
- [ ] Phase 5: Old endpoint deprecated
- [ ] Documentation updated
- [ ] Team trained on new system
- [ ] Rollback procedure documented
- [ ] Success metrics tracked

---

## 🎉 Success!

Once all phases are complete:
- New API is primary endpoint
- All features working
- Performance improved
- Error handling enhanced
- Request tracking enabled
- Team comfortable with new system

**Congratulations on successful migration!** 🚀
