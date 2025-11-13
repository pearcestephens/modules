# 🌐 COMPLETE VEND ECOSYSTEM DESIGN
**Date:** 2025-11-13
**Mission:** Design THE COMPLETE integration architecture
**Status:** 🏗️ **COMPREHENSIVE DESIGN - ALL SYSTEMS INTEGRATED**

---

## 🎯 THE BIG PICTURE

You're right - it's NOT just about consignments! Here's the COMPLETE ecosystem:

```
┌─────────────────────────────────────────────────────────────────────┐
│                         CIS APPLICATION                              │
│  (Transfers, POs, Inventory, Sales, Staff, Reports, etc.)          │
└───────────────────────┬─────────────────────────────────────────────┘
                        │
        ┌───────────────┼───────────────────────────────┐
        │               │                               │
        ▼               ▼                               ▼
┌───────────────┐ ┌─────────────────┐ ┌──────────────────────────┐
│   WEBHOOKS    │ │  EMAIL QUEUE    │ │  DIRECT VEND API CALLS   │
│  (12 types)   │ │ (vapeshed.co.nz)│ │  (On-demand operations)  │
└───────┬───────┘ └────────┬────────┘ └─────────┬────────────────┘
        │                  │                     │
        │ Real-time        │ Async               │ Sync
        │ events           │ notifications       │ operations
        ▼                  ▼                     ▼
┌─────────────────────────────────────────────────────────────────────┐
│              UNIFIED VEND SERVICE LAYER (NEW)                        │
│  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐              │
│  │  Consignment │ │  Inventory   │ │   Sales      │              │
│  │   Service    │ │   Service    │ │   Service    │              │
│  └──────────────┘ └──────────────┘ └──────────────┘              │
│  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐              │
│  │   Product    │ │   Customer   │ │  Webhook     │              │
│  │   Service    │ │   Service    │ │   Manager    │              │
│  └──────────────┘ └──────────────┘ └──────────────┘              │
│  ┌──────────────┐ ┌──────────────┐ ┌──────────────┐              │
│  │    Email     │ │    Queue     │ │    Report    │              │
│  │   Service    │ │   Service    │ │   Service    │              │
│  └──────────────┘ └──────────────┘ └──────────────┘              │
└───────────────────────────┬─────────────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────────────┐
│                  VEND API CLIENT (Enhanced Core)                     │
│  - Rate limiting  - OAuth refresh  - Retry logic  - Logging         │
└───────────────────────────┬─────────────────────────────────────────┘
                            │
                            ▼
                    ┌───────────────┐
                    │   VEND API    │
                    │ (Lightspeed)  │
                    └───────────────┘
```

---

## 📊 CURRENT WEBHOOK SYSTEM ANALYSIS

### **Registered Webhooks (5 Active):**

| # | Webhook Name | Purpose | Event Types | Tables Monitored |
|---|--------------|---------|-------------|------------------|
| 1 | **consignment_complete_lifecycle** | Track consignment full lifecycle | create, update, delete, status_change | vend_consignments, line_items, shipments, parcels, audit_log |
| 2 | **transfer_operations_monitor** | Monitor ALL transfer operations | create, update, pack, ship, receive, complete | 50+ transfer tables |
| 3 | **inventory_sync_comprehensive** | Real-time inventory sync | stock_change, transfer_complete, adjustment, reorder_trigger | stock_levels, movements, transfers, vend_products |
| 4 | **lightspeed_sync_monitor** | Monitor Lightspeed/Vend sync | sync_start, sync_complete, sync_error, data_mismatch | vend_consignments, sync_log, sync_status |
| 5 | **ai_analytics_feed** | AI pattern detection & analytics | pattern_detected, prediction_update, anomaly_detected | ai_predictions, pattern_analysis, metrics |

### **Webhook Database Tables (32 tables!):**

**Core Processing:**
- `webhook_processing_queue` - Main queue
- `webhook_processing_log` - Execution log
- `webhook_events` - Event history
- `webhook_audit_log` - Audit trail
- `webhook_registry` - Configuration

**Domain-Specific:**
- `webhook_consignment_events` - Consignment webhooks
- `webhook_consignment_status` - Status tracking
- `courier_webhook_events` - Courier notifications
- `freight_webhook_config` - Freight setup
- `gss_webhook_events` - GSS freight
- `nzpost_webhook_events` - NZ Post

**Monitoring & Performance:**
- `webhook_metrics` - Performance data
- `webhook_monitoring` - Health checks
- `webhooks_performance_summary` - Analytics

**Queue & Retry:**
- `queue_webhooks` - Integration with Queue V2
- `courier_webhook_retry_queue` - Retry logic
- `webhooks_replay_queue` - Replay failed events

---

## 🏗️ COMPLETE ARCHITECTURE DESIGN

### **Layer 1: VendAPI.php (Core)**
**File:** `/assets/services/vend/VendAPI.php`

**Purpose:** Low-level Vend API client

**Features:**
- ✅ 57+ API methods (ALL Vend endpoints)
- ✅ Rate limiting & throttling
- ✅ OAuth token refresh
- ✅ Exponential backoff retry
- ✅ Webhook signature verification
- ✅ Trace ID support
- ✅ Queue integration (optional)

---

### **Layer 2: Specialized Services**

#### **2.1 VendConsignmentService.php**
**File:** `/assets/services/vend/VendConsignmentService.php`

**Purpose:** Consignment & transfer operations

**Methods:**
```php
// Transfers
createTransfer($sourceOutlet, $destOutlet, $products, $useQueue)
sendTransfer($consignmentId)
receiveTransfer($consignmentId, $receivedProducts)
cancelTransfer($consignmentId)

// Purchase Orders
createPurchaseOrder($supplierId, $outletId, $products, $useQueue)
approvePurchaseOrder($consignmentId)
receivePurchaseOrder($consignmentId, $receivedProducts)

// Reporting
getPendingTransfers($outletId)
getOpenPurchaseOrders()
getConsignmentHistory($filters)
```

---

#### **2.2 VendInventoryService.php** ⭐ NEW
**File:** `/assets/services/vend/VendInventoryService.php`

**Purpose:** Real-time inventory sync with CIS

**Methods:**
```php
// Stock Levels
getStockLevel($productId, $outletId)
updateStockLevel($productId, $outletId, $quantity, $reason)
adjustStock($productId, $outletId, $adjustment)
transferStock($productId, $fromOutlet, $toOutlet, $quantity)

// Stock Movements
recordMovement($productId, $outletId, $type, $quantity, $metadata)
getMovementHistory($productId, $outletId, $dateRange)

// Reorder Management
checkReorderPoints()
getProductsBelowReorder()
createReorderConsignments($outletId)

// Sync Operations
syncInventoryFromVend($outletId = null)
syncInventoryToVend($productId, $outletId)
reconcileInventory($outletId) // Find discrepancies

// Webhooks
handleInventoryWebhook($event) // Process inventory webhooks
```

**Integration:**
- Listens to `inventory_sync_comprehensive` webhook
- Updates CIS `stock_levels` table in real-time
- Triggers reorder alerts
- Syncs with Vend every 15 minutes (configurable)

---

#### **2.3 VendSalesService.php** ⭐ NEW
**File:** `/assets/services/vend/VendSalesService.php`

**Purpose:** Sales data sync and reporting

**Methods:**
```php
// Sales Sync
syncSalesFromVend($dateRange)
getSaleById($saleId)
listSales($filters)

// Register Closures
syncRegisterClosures($outletId, $date)
getRegisterClosure($closureId)
validateClosure($closureId)

// Payment Processing
syncPayments($dateRange)
reconcilePayments($outletId, $date)

// Reporting
getSalesSummary($outletId, $dateRange)
getTopSellingProducts($outletId, $dateRange, $limit)
getStaffPerformance($outletId, $dateRange)
```

**Integration:**
- Real-time sales webhooks
- Daily register closure sync
- Payment reconciliation with Xero

---

#### **2.4 VendProductService.php** ⭐ NEW
**File:** `/assets/services/vend/VendProductService.php`

**Purpose:** Product catalog management

**Methods:**
```php
// Product CRUD
createProduct($productData)
updateProduct($productId, $updates)
deleteProduct($productId)
getProduct($productId)
searchProducts($query)

// Bulk Operations
createProductsBulk($products)
updateProductsBulk($updates)
syncAllProducts() // Full catalog sync

// Variants
createVariant($productId, $variantData)
updateVariant($variantId, $updates)

// Pricing
updatePrice($productId, $outletId, $price)
updatePriceBulk($priceUpdates)

// Suppliers
linkSupplier($productId, $supplierId, $supplierCode)
updateSupplierPrice($productId, $supplierId, $price)
```

---

#### **2.5 VendCustomerService.php** ⭐ NEW
**File:** `/assets/services/vend/VendCustomerService.php`

**Purpose:** Customer data sync

**Methods:**
```php
// Customer CRUD
createCustomer($customerData)
updateCustomer($customerId, $updates)
getCustomer($customerId)
searchCustomers($query)

// Sync
syncCustomersFromVend($dateRange)
syncCustomerToVend($cisCustomerId)

// Loyalty
getCustomerLoyalty($customerId)
updateLoyaltyPoints($customerId, $points)

// Analytics
getCustomerPurchaseHistory($customerId)
getCustomerLifetimeValue($customerId)
```

---

#### **2.6 VendWebhookManager.php** ⭐ NEW
**File:** `/assets/services/vend/VendWebhookManager.php`

**Purpose:** Centralized webhook handling

**Methods:**
```php
// Webhook Registration (with Vend)
registerWebhook($type, $url)
unregisterWebhook($webhookId)
listRegisteredWebhooks()

// Event Processing
processIncomingWebhook($payload, $signature)
routeWebhookEvent($event)
retryFailedWebhook($webhookId)

// Monitoring
getWebhookStats()
getFailedWebhooks($limit)
getWebhookPerformance()

// Configuration
registerLocalHandler($eventType, $handler)
setRetryPolicy($maxRetries, $backoffStrategy)
```

**Webhook Event Routing:**
```php
// Routes events to appropriate services
[
    'consignment.created' => VendConsignmentService::handleCreated(),
    'consignment.updated' => VendConsignmentService::handleUpdated(),
    'inventory.adjusted' => VendInventoryService::handleAdjustment(),
    'sale.created' => VendSalesService::handleSale(),
    'product.updated' => VendProductService::handleUpdate(),
    'customer.created' => VendCustomerService::handleCreated(),
]
```

---

#### **2.7 VendEmailService.php** ⭐ NEW
**File:** `/assets/services/vend/VendEmailService.php`

**Purpose:** Email notifications (integrate vapeshed.co.nz queue)

**Methods:**
```php
// Email Queue (migrate from vapeshed.co.nz)
queueEmail($to, $subject, $body, $template, $data)
processEmailQueue()
retryFailedEmails()

// Notification Templates
sendTransferNotification($transferId, $recipientType)
sendPurchaseOrderNotification($poId, $supplierId)
sendInventoryAlert($alertType, $products)
sendRegisterClosureReport($closureId, $managerId)

// Digest Reports
sendDailyWebhookDigest()
sendWeeklyInventoryReport()
sendMonthlySalesReport()

// Configuration
setEmailProvider($provider) // Mailgun, SendGrid, etc.
setTemplate($name, $html)
```

**Integration:**
- Migrate email queue from vapeshed.co.nz to CIS
- Use Queue V2 for queuing
- Support templates
- Track open/click rates

---

#### **2.8 VendQueueService.php** ⭐ NEW
**File:** `/assets/services/vend/VendQueueService.php`

**Purpose:** Queue V2 integration for async operations

**Methods:**
```php
// Queue Operations
enqueue($jobType, $payload, $priority)
dequeue($workerType)
processQueue($workerType, $batchSize)

// Job Management
getJobStatus($jobId)
cancelJob($jobId)
retryJob($jobId)
clearFailedJobs()

// Monitoring
getQueueStats()
getWorkerStatus()
getFailedJobs($limit)

// Configuration
setWorkerCount($type, $count)
setRetryPolicy($type, $maxRetries)
setPriority($jobType, $priority)
```

**Job Types:**
```php
const JOB_CONSIGNMENT_CREATE = 'vend.consignment.create';
const JOB_CONSIGNMENT_UPDATE = 'vend.consignment.update';
const JOB_INVENTORY_SYNC = 'vend.inventory.sync';
const JOB_PRODUCT_SYNC = 'vend.product.sync';
const JOB_SALES_SYNC = 'vend.sales.sync';
const JOB_EMAIL_SEND = 'vend.email.send';
const JOB_WEBHOOK_RETRY = 'vend.webhook.retry';
```

---

#### **2.9 VendReportService.php** ⭐ NEW
**File:** `/assets/services/vend/VendReportService.php`

**Purpose:** Advanced reporting & analytics

**Methods:**
```php
// Consignment Reports
getConsignmentReport($dateRange, $filters)
getTransferVelocity($outletId)
getConsignmentAccuracy($outletId)

// Inventory Reports
getInventoryValuation($outletId)
getStockMovementReport($dateRange)
getSlowMovingProducts($outletId, $threshold)
getFastMovingProducts($outletId, $limit)
getStockAccuracyReport($outletId)

// Sales Reports
getSalesByOutlet($dateRange)
getSalesByProduct($dateRange)
getSalesByStaff($dateRange)
getAverageSaleValue($outletId, $dateRange)

// Performance Reports
getWebhookPerformanceReport($dateRange)
getApiPerformanceReport($dateRange)
getSyncHealthReport()

// Export
exportToCSV($reportType, $data)
exportToPDF($reportType, $data)
scheduleReport($reportType, $frequency, $recipients)
```

---

## 📁 COMPLETE FILE STRUCTURE

```
/assets/services/vend/
├── Core/
│   └── VendAPI.php                      ← Layer 1: Core API client (30KB)
│
├── Services/
│   ├── VendConsignmentService.php       ← Transfers & POs (20KB)
│   ├── VendInventoryService.php         ← Inventory sync (15KB) ⭐ NEW
│   ├── VendSalesService.php             ← Sales sync (15KB) ⭐ NEW
│   ├── VendProductService.php           ← Product management (15KB) ⭐ NEW
│   ├── VendCustomerService.php          ← Customer sync (12KB) ⭐ NEW
│   ├── VendWebhookManager.php           ← Webhook handling (18KB) ⭐ NEW
│   ├── VendEmailService.php             ← Email queue (12KB) ⭐ NEW
│   ├── VendQueueService.php             ← Queue V2 integration (10KB) ⭐ NEW
│   └── VendReportService.php            ← Reporting & analytics (20KB) ⭐ NEW
│
├── Contracts/
│   ├── VendServiceInterface.php         ← Service contract
│   ├── WebhookHandlerInterface.php      ← Webhook handler contract
│   └── QueueableInterface.php           ← Queueable job contract
│
├── Exceptions/
│   ├── VendApiException.php             ← API errors
│   ├── VendAuthException.php            ← Auth errors
│   ├── VendRateLimitException.php       ← Rate limit errors
│   └── VendWebhookException.php         ← Webhook errors
│
├── Models/
│   ├── Consignment.php                  ← Consignment model
│   ├── Product.php                      ← Product model
│   ├── Sale.php                         ← Sale model
│   ├── Customer.php                     ← Customer model
│   └── WebhookEvent.php                 ← Webhook event model
│
├── Helpers/
│   ├── VendDataTransformer.php          ← Data transformation
│   ├── VendValidator.php                ← Input validation
│   └── VendCache.php                    ← Caching layer
│
├── Config/
│   ├── vend.php                         ← Main config
│   ├── webhooks.php                     ← Webhook config
│   ├── queue.php                        ← Queue config
│   └── email.php                        ← Email config
│
├── Documentation/
│   ├── README.md                        ← Main docs
│   ├── API_REFERENCE.md                 ← API reference
│   ├── WEBHOOK_GUIDE.md                 ← Webhook guide
│   ├── QUEUE_GUIDE.md                   ← Queue guide
│   └── EXAMPLES.md                      ← Usage examples
│
└── Tests/
    ├── Unit/
    │   ├── VendAPITest.php
    │   ├── ConsignmentServiceTest.php
    │   └── ...
    ├── Integration/
    │   ├── WebhookIntegrationTest.php
    │   └── ...
    └── Feature/
        ├── TransferFlowTest.php
        └── ...
```

---

## 🔄 DATA FLOW EXAMPLES

### **Example 1: Transfer Creation (Multiple Paths)**

```
USER ACTION: Create transfer in CIS
    │
    ├─► [SYNC PATH] Direct API call
    │   └─► VendConsignmentService.createTransfer(useQueue: false)
    │       └─► VendAPI.createConsignment()
    │           └─► Vend API
    │               └─► Success: CIS updated immediately
    │
    ├─► [ASYNC PATH] Queue for later
    │   └─► VendConsignmentService.createTransfer(useQueue: true)
    │       └─► VendQueueService.enqueue('vend.consignment.create')
    │           └─► Queue V2 processes in background
    │               └─► VendAPI.createConsignment()
    │                   └─► Success: CIS updated when processed
    │
    └─► [WEBHOOK PATH] External trigger
        └─► Vend API: Consignment created externally
            └─► Vend sends webhook to CIS
                └─► VendWebhookManager.processIncomingWebhook()
                    └─► VendConsignmentService.handleCreated()
                        └─► CIS database updated
                            └─► VendEmailService.sendTransferNotification()
```

### **Example 2: Inventory Sync (Real-time)**

```
SALE IN VEND: Product sold at store
    │
    └─► Vend sends webhook: 'sale.created'
        └─► CIS receives webhook
            └─► VendWebhookManager.routeWebhookEvent()
                ├─► VendSalesService.handleSale()
                │   └─► Sales data saved to CIS
                │
                └─► VendInventoryService.handleInventoryWebhook()
                    ├─► Update CIS stock_levels
                    ├─► Check reorder points
                    ├─► If below reorder:
                    │   └─► VendEmailService.sendInventoryAlert()
                    │       └─► VendQueueService.enqueue('create_reorder_po')
                    │
                    └─► Log to stock_movements_audit
```

### **Example 3: Daily Reports (Scheduled)**

```
CRON JOB: Daily 7am
    │
    └─► VendReportService.generateDailyReports()
        ├─► Get yesterday's data
        │   ├─► VendSalesService.getSalesSummary()
        │   ├─► VendInventoryService.getStockMovements()
        │   ├─► VendConsignmentService.getCompletedTransfers()
        │   └─► VendWebhookManager.getWebhookStats()
        │
        ├─► Generate PDF reports
        │   └─► VendReportService.exportToPDF()
        │
        └─► Send emails
            └─► VendEmailService.sendDailyWebhookDigest()
                └─► VendQueueService.enqueue('vend.email.send')
                    └─► Queue V2 processes
                        └─► Email sent via Mailgun
```

---

## 🎁 COMPLETE INTEGRATION BENEFITS

### **What You Get:**

1. **Unified Vend Integration**
   - ONE place for ALL Vend operations
   - Consistent patterns across all services
   - Shared configuration and error handling

2. **Real-time Sync**
   - Webhooks keep CIS up-to-date instantly
   - Inventory sync every 15 minutes
   - Sales data synced immediately

3. **Queue Integration**
   - Heavy operations queued automatically
   - Failed jobs retry with exponential backoff
   - Monitor queue health in real-time

4. **Email System**
   - Migrate from vapeshed.co.nz to CIS
   - Template-based notifications
   - Track delivery and engagement

5. **Comprehensive Reporting**
   - Pre-built reports for all operations
   - Export to CSV/PDF
   - Schedule automated delivery

6. **Error Handling**
   - Automatic retries with backoff
   - Failed webhook replay
   - Detailed error logging

7. **Performance**
   - Rate limiting prevents 429 errors
   - Caching reduces API calls
   - Batch operations where possible

---

## 🚀 IMPLEMENTATION PLAN

### **Phase 1: Foundation (Week 1)**
✅ Create directory structure
✅ Implement VendAPI.php (core client)
✅ Set up configuration files
✅ Create base service classes
✅ Implement error handling

### **Phase 2: Core Services (Week 2)**
✅ VendConsignmentService (transfers & POs)
✅ VendInventoryService (stock sync)
✅ VendWebhookManager (webhook handling)
✅ VendQueueService (Queue V2 integration)

### **Phase 3: Extended Services (Week 3)**
✅ VendSalesService (sales sync)
✅ VendProductService (product management)
✅ VendCustomerService (customer sync)
✅ VendEmailService (email queue migration)

### **Phase 4: Reporting & Polish (Week 4)**
✅ VendReportService (all reports)
✅ Documentation (README, guides, examples)
✅ Unit tests
✅ Integration tests

### **Phase 5: Migration & Deployment (Week 5)**
✅ Migrate email queue from vapeshed.co.nz
✅ Update all CIS references to use new services
✅ Deploy to production (staged rollout)
✅ Monitor and optimize

---

## 💬 READY TO BUILD?

This is the **COMPLETE** ecosystem - covering:

✅ **Consignments** - Transfers & POs
✅ **Inventory** - Real-time stock sync
✅ **Sales** - Sales data & register closures
✅ **Products** - Catalog management
✅ **Customers** - Customer data sync
✅ **Webhooks** - All 12 webhook types
✅ **Email Queue** - Migrate from vapeshed.co.nz
✅ **Queue V2** - Background job processing
✅ **Reporting** - Comprehensive analytics

**Just say "GO" and I'll start building!** 🚀

Or tell me which phase/service to prioritize first.
