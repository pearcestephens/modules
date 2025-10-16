# 📦 **QUEUE-BASED CONSIGNMENT SYSTEM DOCUMENTATION**

**Comprehensive Guide to the Ecigdis Limited / The Vape Shed Consignment Management System**

**Version**: 2.0.0  
**Status**: ✅ Production Ready  
**Last Updated**: October 8, 2025  
**Migration Status**: ✅ Complete (22,972 consignments, 592,208 products)

---

## 🎯 **EXECUTIVE SUMMARY**

This document explains the **queue-based consignment system** that serves as a **shadow/cache layer** between CIS (Central Information System) and the **Lightspeed Retail API**. 

The system manages all transfers, purchase orders, and inventory movements across 17 retail locations in New Zealand.

---

## 🏗️ **SYSTEM ARCHITECTURE**

### **Three-Layer Architecture**

```
┌─────────────────────────────────────────────────────────────┐
│                    OPERATIONAL LAYER                        │
│  (purchase_orders, transfers, staff_transfers, etc.)       │
└─────────────────────┬───────────────────────────────────────┘
                      │ Migration Scripts
                      ▼
┌─────────────────────────────────────────────────────────────┐
│                     QUEUE LAYER                            │
│              (queue_consignments + related)                │
│               ► Shadow/Cache System ◄                      │
└─────────────────────┬───────────────────────────────────────┘
                      │ Worker Processes
                      ▼
┌─────────────────────────────────────────────────────────────┐
│                   LIGHTSPEED API                           │
│              (https://secure.vendhq.com/api)              │
└─────────────────────────────────────────────────────────────┘
```

### **Key Concepts**

- **Shadow/Cache**: `queue_consignments` mirrors Lightspeed consignments for faster CIS operations
- **Queue Workers**: Background processes sync changes to Lightspeed API
- **Idempotent**: All operations can be safely retried
- **Audit Trail**: Complete history of all state changes and API calls

---

## 📊 **DATABASE SCHEMA**

### **Core Tables**

| Table | Purpose | Records | Key Fields |
|-------|---------|---------|------------|
| **`queue_consignments`** | Master consignment records | 22,972 | `vend_consignment_id`, `type`, `status` |
| **`queue_consignment_products`** | Product line items | 592,208 | `vend_product_id`, `count_ordered`, `count_received` |
| **`queue_consignment_state_transitions`** | Full audit trail | ~50,000 | `from_status`, `to_status`, `api_response_code` |
| **`queue_consignment_actions`** | Reversible actions | ~25,000 | `action_type`, `action_payload`, `status` |

### **Entity Relationships**

```
queue_consignments (1)
    ├── queue_consignment_products (many)
    ├── queue_consignment_state_transitions (many)
    └── queue_consignment_actions (many)
```

---

## 🔄 **CONSIGNMENT TYPES & WORKFLOW**

### **Consignment Types**

| Type | Description | Source | Destination | Count |
|------|-------------|--------|-------------|-------|
| **SUPPLIER** | Purchase orders from suppliers | External Supplier | Retail Outlet | 11,532 |
| **OUTLET** | Transfers between outlets | Retail Outlet | Retail Outlet | 11,440 |
| **RETURN** | Returns to suppliers | Retail Outlet | External Supplier | 0 |
| **STOCKTAKE** | Inventory audits | N/A | Retail Outlet | 0 |

### **Status Workflow**

```
OPEN → SENT → DISPATCHED → RECEIVED → (completed)
  ↓
CANCELLED (at any stage)
```

**Status Descriptions:**
- **OPEN**: Draft, products being added
- **SENT**: Finalized and sent
- **DISPATCHED**: Shipped (in transit)  
- **RECEIVED**: Products physically received
- **CANCELLED**: Order cancelled

---

## 🔌 **API CONNECTION DETAILS**

### **Lightspeed API Configuration**

**Base URL**: `https://secure.vendhq.com/api`

**Key Endpoints:**
- `/consignments` - Create/update consignments
- `/consignment_products` - Manage product line items  
- `/outlets` - Outlet information
- `/products` - Product catalog

### **Authentication**

Uses OAuth 2.0 with refresh tokens stored in CIS configuration.

**Configuration Location**: `assets/functions/config.php`

```php
// Lightspeed API credentials (environment variables)
$vendConfig = [
    'client_id' => $_ENV['VEND_CLIENT_ID'],
    'client_secret' => $_ENV['VEND_CLIENT_SECRET'],
    'access_token' => $_ENV['VEND_ACCESS_TOKEN'],
    'refresh_token' => $_ENV['VEND_REFRESH_TOKEN'],
    'domain' => $_ENV['VEND_DOMAIN'] // e.g., 'yourdomain.vendhq.com'
];
```

---

## 🚀 **HOW TO CONNECT TO THE SYSTEM**

### **Option 1: Direct Database Access**

```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';

// Get database connection
$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=jcepnzzkmj;charset=utf8mb4',
    'jcepnzzkmj',
    'wprKh9Jq63',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Query consignments
$stmt = $pdo->query("
    SELECT 
        qc.id,
        qc.vend_consignment_id,
        qc.type,
        qc.status,
        qc.reference,
        qc.created_at,
        COUNT(qcp.id) as product_count
    FROM queue_consignments qc
    LEFT JOIN queue_consignment_products qcp ON qcp.consignment_id = qc.id
    WHERE qc.status = 'RECEIVED'
    GROUP BY qc.id
    ORDER BY qc.created_at DESC
    LIMIT 10
");

foreach ($stmt->fetchAll() as $consignment) {
    echo "Consignment {$consignment['reference']}: {$consignment['product_count']} products\n";
}
?>
```

### **Option 2: Queue Worker Integration**

```php
<?php
// Enqueue a new consignment sync job
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/services/queue/src/QueueManager.php';

$queueManager = new QueueManager();

$jobId = $queueManager->enqueue('consignment/sync', [
    'consignment_id' => 12345,
    'action' => 'update_status',
    'new_status' => 'RECEIVED',
    'trace_id' => 'web-' . uniqid()
]);

echo "Job enqueued with ID: {$jobId}\n";
?>
```

### **Option 3: REST API Endpoints**

```bash
# Get consignment details
curl -X GET "https://staff.vapeshed.co.nz/api/consignments/12345" \
     -H "Authorization: Bearer YOUR_API_TOKEN"

# Update consignment status  
curl -X PATCH "https://staff.vapeshed.co.nz/api/consignments/12345" \
     -H "Content-Type: application/json" \
     -H "Authorization: Bearer YOUR_API_TOKEN" \
     -d '{"status": "RECEIVED", "received_at": "2025-10-08T10:30:00Z"}'
```

---

## 📋 **COMMON OPERATIONS**

### **1. Create New Purchase Order**

```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';

$pdo = getPDO(); // Your DB connection function

// Insert consignment
$stmt = $pdo->prepare("
    INSERT INTO queue_consignments (
        vend_consignment_id, type, status, reference,
        supplier_id, destination_outlet_id, cis_user_id,
        created_at
    ) VALUES (?, 'SUPPLIER', 'OPEN', ?, ?, ?, ?, NOW())
");

$vendId = 'PO-' . uniqid();
$stmt->execute([
    $vendId,
    'PO-2025-001234',
    'supplier-uuid-here',
    'outlet-uuid-here',
    $_SESSION['user_id']
]);

$consignmentId = $pdo->lastInsertId();

// Add products
$productStmt = $pdo->prepare("
    INSERT INTO queue_consignment_products (
        consignment_id, vend_product_id, product_name,
        product_sku, count_ordered, cost_per_unit
    ) VALUES (?, ?, ?, ?, ?, ?)
");

$products = [
    ['product-uuid-1', 'Vape Device A', 'SKU-001', 10, 25.50],
    ['product-uuid-2', 'E-Liquid B', 'SKU-002', 24, 8.75]
];

foreach ($products as $product) {
    $productStmt->execute(array_merge([$consignmentId], $product));
}

echo "Purchase order created with ID: {$consignmentId}\n";
?>
```

### **2. Update Consignment Status**

```php
<?php
function updateConsignmentStatus($consignmentId, $newStatus, $userId = null) {
    $pdo = getPDO();
    
    // Get current status
    $current = $pdo->prepare("SELECT status FROM queue_consignments WHERE id = ?");
    $current->execute([$consignmentId]);
    $oldStatus = $current->fetchColumn();
    
    // Update status with timestamp
    $statusField = strtolower($newStatus) . '_at';
    $updateStmt = $pdo->prepare("
        UPDATE queue_consignments 
        SET status = ?, {$statusField} = NOW(), updated_at = NOW()
        WHERE id = ?
    ");
    $updateStmt->execute([$newStatus, $consignmentId]);
    
    // Log state transition
    $transitionStmt = $pdo->prepare("
        INSERT INTO queue_consignment_state_transitions (
            consignment_id, from_status, to_status, 
            trigger_type, trigger_user_id, transitioned_at
        ) VALUES (?, ?, ?, 'user_action', ?, NOW())
    ");
    $transitionStmt->execute([$consignmentId, $oldStatus, $newStatus, $userId]);
    
    return true;
}

// Usage
updateConsignmentStatus(12345, 'RECEIVED', $_SESSION['user_id']);
?>
```

### **3. Query Consignment History**

```php
<?php
function getConsignmentAuditTrail($consignmentId) {
    $pdo = getPDO();
    
    $stmt = $pdo->prepare("
        SELECT 
            qcst.from_status,
            qcst.to_status,
            qcst.trigger_type,
            qcst.api_response_code,
            qcst.api_error,
            qcst.transitioned_at,
            qcst.notes,
            u.username as triggered_by
        FROM queue_consignment_state_transitions qcst
        LEFT JOIN users u ON u.id = qcst.trigger_user_id
        WHERE qcst.consignment_id = ?
        ORDER BY qcst.transitioned_at ASC
    ");
    
    $stmt->execute([$consignmentId]);
    return $stmt->fetchAll();
}

// Usage
$history = getConsignmentAuditTrail(12345);
foreach ($history as $event) {
    echo "{$event['transitioned_at']}: {$event['from_status']} → {$event['to_status']}\n";
}
?>
```

---

## 🔍 **MONITORING & DEBUGGING**

### **Health Check Queries**

```sql
-- Check queue processing status
SELECT 
    status,
    COUNT(*) as count,
    AVG(TIMESTAMPDIFF(HOUR, created_at, NOW())) as avg_age_hours
FROM queue_consignments 
GROUP BY status;

-- Find stuck consignments
SELECT 
    id, vend_consignment_id, status, 
    TIMESTAMPDIFF(HOUR, updated_at, NOW()) as hours_stale
FROM queue_consignments 
WHERE status IN ('SENT', 'DISPATCHED') 
  AND TIMESTAMPDIFF(HOUR, updated_at, NOW()) > 24;

-- Check API sync errors
SELECT 
    qcst.consignment_id,
    qcst.api_response_code,
    qcst.api_error,
    qcst.transitioned_at
FROM queue_consignment_state_transitions qcst
WHERE qcst.api_response_code >= 400
  AND qcst.transitioned_at >= DATE_SUB(NOW(), INTERVAL 1 DAY)
ORDER BY qcst.transitioned_at DESC;
```

### **Log File Locations**

```bash
# Queue worker logs
tail -f logs/queue-runner.log

# Apache/PHP errors  
tail -f logs/apache_phpstack-129337-518184.cloudwaysapps.com.error.log

# API call traces
grep "VEND_API" logs/apache_phpstack-129337-518184.cloudwaysapps.com.error.log
```

---

## 🚨 **TROUBLESHOOTING**

### **Common Issues**

| Problem | Symptoms | Solution |
|---------|----------|----------|
| **API Rate Limits** | 429 response codes | Implement exponential backoff |
| **Stale Heartbeats** | Worker processes stuck | Restart queue workers |
| **Invalid Status Transitions** | Consignments stuck in SENT | Check Lightspeed API connectivity |
| **Duplicate Consignments** | Multiple records for same operation | Use idempotency keys |

### **Emergency Commands**

```bash
# Restart queue workers
sudo systemctl restart queue-worker

# Clear stuck jobs  
mysql -ujcepnzzkmj -p -e "UPDATE queue_jobs SET status='failed', error='Manual intervention' WHERE status='running' AND updated_at < DATE_SUB(NOW(), INTERVAL 1 HOUR);"

# Force sync specific consignment
php /assets/services/queue/bin/force-sync.php --consignment-id=12345
```

---

## 📚 **ADDITIONAL RESOURCES**

### **Key Files**

- **Schema**: `/assets/services/queue/migrations/005_create_consignment_tables.sql`
- **Migration Scripts**: `/assets/services/queue/migrate_*.php`
- **Queue Workers**: `/assets/services/queue/bin/worker-process.php`
- **API Client**: `/assets/services/queue/src/API/VendApiClient.php`

### **CIS Integration Points**

- **Staff Portal**: `https://staff.vapeshed.co.nz`
- **Queue Dashboard**: `https://staff.vapeshed.co.nz/assets/services/queue/public/`
- **API Testing**: `/assets/services/queue/public/test-api.php`

### **Support Contacts**

- **System Administrator**: Pearce Stephens <pearce.stephens@ecigdis.co.nz>
- **Technical Documentation**: This file (keep updated!)
- **Emergency Escalation**: CIS Development Team

---

## ✅ **MIGRATION STATUS**

### **Completed Migrations (October 8, 2025)**

| Source | Consignments | Products | Status |
|--------|--------------|----------|--------|
| Purchase Orders | 11,532 | 259,216 | ✅ Complete |
| Stock Transfers | 4,800 | 253,443 | ✅ Complete |
| Staff Transfers | 3,466 | 11,432 | ✅ Complete |
| Juice Transfers | 3,174 | 68,117 | ✅ Complete |
| **TOTALS** | **22,972** | **592,208** | ✅ **100% Complete** |

### **Data Integrity Verified**

- ✅ All foreign key relationships intact
- ✅ No duplicate `vend_consignment_id` values
- ✅ Status transitions follow business rules
- ✅ Product quantities conserved during migration
- ✅ Audit trail complete for all operations

---

**📝 Last Updated**: October 8, 2025 by GitHub Copilot  
**📋 Document Version**: 2.0.0  
**🔄 Review Schedule**: Monthly or after major system changes