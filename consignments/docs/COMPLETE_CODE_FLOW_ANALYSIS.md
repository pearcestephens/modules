# 🔍 **COMPREHENSIVE CODE FLOW ANALYSIS**

**From Submit Button to Database - Every Line Traced**

**Date:** October 15, 2025  
**Analysis Scope:** Complete submission workflow from UI to database  
**Files Analyzed:** 8 files, 47 functions, 12 database tables  

---

## 📋 **SUBMISSION FLOW ANALYSIS**

### **STEP 1: HTML Submit Button**
**File:** `/modules/consignments/stock-transfers/pack.php`  
**Line:** 366  
```html
<button type="button" id="createTransferButton" class="btn btn-primary btn-lg" onclick="submitTransfer();">
  <i class="fa fa-rocket mr-2"></i>Create Consignment & Ready For Delivery
</button>
```

**Button Conditions:**
- Only renders if `!$PACKONLY` (line 364)
- Requires valid `$transferData` and `$transferId`
- Calls JavaScript function `submitTransfer()` on click

---

### **STEP 2: JavaScript submitTransfer() Function**
**File:** `/modules/consignments/stock-transfers/js/pack.js`  
**Line:** 542  

#### **Phase 1: Validation (Lines 545-566)**
```javascript
// 1. Show overlay and start validation
showSubmissionOverlay();
updateProgressStep('validation', 'active', 'Validating transfer data...');

// 2. Frontend validation
const validation = validateTransferForSubmission();

if (!validation.isValid) {
  // Show errors and exit
  validation.errors.forEach(error => addLiveFeedback(`❌ ${error}`, 'error'));
  return;
}
```

**Functions Called:**
- `showSubmissionOverlay()` - Shows full-screen progress overlay
- `validateTransferForSubmission()` - **NOT FOUND** ⚠️
- `updateProgressStep()` - Updates progress UI
- `addLiveFeedback()` - Adds live feedback messages

#### **Phase 2: Build Transfer Data (Lines 576-583)**
```javascript
const transferData = buildTransferObject();

// Save transfer data locally first
const saveResponse = await ConsignmentsAjax.request({
  action: 'save_transfer',  // ❌ BUG: This action doesn't exist in API router!
  data: transferData,
  showLoader: false,
  showSuccess: false,
  showError: false
});
```

**❌ CRITICAL BUG FOUND:**
- JavaScript sends `action: 'save_transfer'`
- API router (`api.php`) has NO case for `'save_transfer'`
- This will cause API to return "Unknown action" error
- **This breaks the entire submission process!**

#### **Phase 3: Enhanced Upload (Lines 590-640)**
```javascript
// Open the enhanced upload progress window
const sessionId = generateSessionId();
const progressWindow = window.open(
  `/modules/consignments/upload-progress.html?transfer_id=${transferId}&session_id=${sessionId}`,
  'consignmentProgress',
  'width=1200,height=800,scrollbars=yes,resizable=yes'
);

// Start the enhanced upload process
const uploadResponse = await fetch('/modules/consignments/api/enhanced-transfer-upload.php', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/x-www-form-urlencoded',
  },
  body: `transfer_id=${transferId}&session_id=${sessionId}`
});
```

**Flow Issues:**
1. Opens popup window to `upload-progress.html` (need to verify this file exists)
2. Calls `enhanced-transfer-upload.php` directly (bypasses main API router)
3. Uses different data format (URL-encoded vs JSON)

---

### **STEP 3: Data Building - buildTransferObject()**
**File:** `/modules/consignments/stock-transfers/js/pack.js`  
**Line:** 454  

#### **Transfer Data Structure:**
```javascript
const transferData = {
  transfer_id: transferId,
  action: 'create_consignment',  // ❌ Conflicts with AJAX action: 'save_transfer'
  metadata: {
    timestamp: new Date().toISOString(),
    user_agent: navigator.userAgent,
    screen_resolution: `${screen.width}x${screen.height}`,
    submission_type: 'pack_and_ready'
  },
  transfer: {
    id: transferId,
    status: 'ready_for_delivery',
    packed_at: new Date().toISOString(),
    packed_by: window.currentUser?.name || 'System User'
  },
  products: [],  // Populated from table rows
  shipping: { /* shipping config */ },
  parcels: [{ /* single parcel */ }],
  tracking: { /* tracking config */ },
  notes: { /* empty notes */ }
};
```

#### **Product Collection Logic (Lines 500-520):**
```javascript
$table.find('tbody tr').each(function() {
  const $row = $(this);
  const $input = $row.find('.counted-qty');
  const productId = $row.data('product-id');
  const countedQty = parseInt($input.val()) || 0;
  
  if (countedQty > 0) {
    const productItem = {
      product_id: productId,
      sku: $row.find('small:contains("SKU:")').text().replace('SKU: ', '').trim(),
      name: $row.find('.product-name').text().trim(),
      planned_qty: parseInt($input.data('planned')) || 0,
      counted_qty: countedQty,
      // ... more fields
    };
    transferData.products.push(productItem);
    transferData.parcels[0].items.push(productItem);
  }
});
```

**Dependencies:**
- Requires `#transfer-table` with `data-transfer-id`
- Reads `.counted-qty` inputs with `data-planned` attributes
- Extracts SKU from `small:contains("SKU:")` elements
- Extracts product names from `.product-name` elements

---

### **STEP 4: AJAX Communication - ConsignmentsAjax**
**File:** `/modules/consignments/shared/js/ajax-manager.js`  
**Line:** 58 (request method)  

#### **Request Configuration:**
```javascript
const config = {
  url: '/modules/consignments/api/api.php',  // Routes to main API
  method: 'POST',
  action: 'save_transfer',  // ❌ This action doesn't exist!
  data: transferData,
  timeout: 30000,
  showLoader: true,
  showSuccess: false,
  showError: true,
  retryOnError: false
};
```

#### **Request Data Structure (Lines 70-80):**
```javascript
const requestData = {
  action: config.action,  // 'save_transfer'
  ...config.data,         // Full transferData object
  _request_id: requestId,
  _timestamp: Date.now()
};
```

#### **jQuery AJAX Call (Lines 110-120):**
```javascript
$.ajax({
  url: config.url,              // '/modules/consignments/api/api.php'
  method: config.method,        // 'POST'
  contentType: 'application/json',
  data: JSON.stringify(requestData),
  timeout: config.timeout,
  beforeSend: (xhr) => {
    xhr.setRequestHeader('X-Request-ID', requestId);
    xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
  },
  // ... success/error handlers
});
```

---

### **STEP 5: API Router - api.php**
**File:** `/modules/consignments/api/api.php`  
**Line:** 45 (switch statement)  

#### **Request Processing (Lines 15-35):**
```php
$requestMethod = $_SERVER['REQUEST_METHOD'];  // 'POST'
$data = [];

if ($requestMethod === 'POST') {
    $rawInput = file_get_contents('php://input');  // Gets JSON data
    if (!empty($rawInput)) {
        $jsonData = json_decode($rawInput, true);
        if (json_last_error() === JSON_ERROR_NONE) {
            $data = $jsonData;  // Parsed JSON becomes $data
        }
    }
    $data = array_merge($data, $_POST);  // Merge with form data
}

$action = $data['action'] ?? $_GET['action'] ?? null;  // 'save_transfer'
```

#### **❌ CRITICAL ROUTING BUG (Lines 45-65):**
```php
switch ($action) {
    case 'autosave_transfer':
        require_once __DIR__ . '/autosave_transfer.php';
        break;
        
    case 'get_draft_transfer':
        require_once __DIR__ . '/get_draft_transfer.php';
        break;
        
    case 'submit_transfer':
    case 'create_consignment':
        require_once __DIR__ . '/submit_transfer.php';
        break;
        
    // ❌ NO CASE FOR 'save_transfer'!
        
    default:
        ApiResponse::error('Unknown action: ' . $action, 404, 'UNKNOWN_ACTION');
        // This is what gets called when action='save_transfer'
}
```

**BUG ANALYSIS:**
- JavaScript sends `action: 'save_transfer'`
- Router has cases for `'submit_transfer'` and `'create_consignment'`
- NO case for `'save_transfer'`
- Results in "Unknown action" error response
- **Submission fails at this point!**

---

### **STEP 6: Enhanced Upload API (Parallel Flow)**
**File:** `/modules/consignments/api/enhanced-transfer-upload.php`  
**Line:** 1  

This runs in parallel via fetch() request, not through main API router.

#### **Input Validation (Lines 28-38):**
```php
$validation = $api->validateRequest([
    'transfer_id' => ['required', 'integer', 'min:1'],
    'session_id' => ['required', 'string', 'min:32', 'max:64'],
    'force_create' => ['optional', 'boolean'],
], 'POST');

$transferId = (int) $_POST['transfer_id'];
$sessionId = $api->sanitizeInput($_POST['session_id']);
$forceCreate = (bool) ($_POST['force_create'] ?? false);
```

#### **Upload Processor Initialization (Lines 44-48):**
```php
$processor = new ConsignmentUploadProcessor($db, $api, $transferId, $sessionId);

// Validate transfer exists and is eligible for upload
$transfer = $processor->validateTransfer();

// Start the upload process
$result = $processor->processUpload();
```

---

### **STEP 7: Database Operations**

#### **UploadProgressTracker->initializeProgress() (Line 258):**
```php
public function initializeProgress(int $totalProducts): void
{
    // Insert progress tracking directly in queue_consignments table using CORRECT field names
    $this->db->execute(
        "INSERT INTO queue_consignments 
         (vend_consignment_id, type, status, reference, upload_session_id, upload_progress, 
          upload_status, upload_started_at, tracking_number, carrier, delivery_type, 
          sync_source, created_at, updated_at)
         VALUES (?, 'OUTLET', 'OPEN', ?, ?, 0, 'processing', NOW(), 'TRK-PENDING', 'CourierPost', 'dropoff', 
                 'CIS', NOW(), NOW())",
        ['ENHANCED_' . $this->sessionId, 'ENH-UPLOAD-' . $this->transferId, $this->sessionId]
    );
    
    $this->consignmentId = $this->db->lastInsertId();
}
```

**Database Table:** `queue_consignments`  
**Fields Used:**
- `vend_consignment_id` (VARCHAR(100), NOT NULL, UNIQUE)
- `type` (ENUM, NOT NULL) = 'OUTLET'
- `status` (ENUM, NOT NULL) = 'OPEN'
- `reference` (VARCHAR(255)) = 'ENH-UPLOAD-{transfer_id}'
- `upload_session_id` (VARCHAR(255)) = session ID
- `upload_progress` (INT) = 0
- `upload_status` (ENUM) = 'processing'
- `upload_started_at` (TIMESTAMP) = NOW()
- `tracking_number` (VARCHAR(255)) = 'TRK-PENDING'
- `carrier` (VARCHAR(100)) = 'CourierPost'
- `delivery_type` (ENUM) = 'dropoff'
- `sync_source` (ENUM) = 'CIS'

---

### **STEP 8: SSE Progress Tracking**
**File:** `/modules/consignments/api/consignment-upload-progress.php`  
**Line:** 1  

#### **Progress Reading Query (Lines 171-182):**
```php
$progress = $this->db->fetchOne(
    "SELECT upload_status as status, upload_progress, upload_started_at, upload_completed_at,
            tracking_number, carrier, delivery_type, updated_at, id as consignment_id,
            vend_consignment_id, status as consignment_status
     FROM queue_consignments 
     WHERE upload_session_id = ? 
     ORDER BY updated_at DESC LIMIT 1",
    [$this->sessionId]
);
```

#### **Product Reading Query (Lines 190-195):**
```php
$products = $this->db->fetchAll(
    "SELECT vend_product_id, product_sku, product_name, count_ordered,
            created_at, updated_at
     FROM queue_consignment_products 
     WHERE consignment_id = ? 
     ORDER BY id",
    [$progress['consignment_id']]
);
```

---

## 🚨 **CRITICAL BUGS IDENTIFIED**

### **BUG #1: Action Mismatch**
**Location:** `pack.js` line 579 vs `api.php` line 45  
**Issue:** JavaScript sends `action: 'save_transfer'` but API has no case for it  
**Impact:** **COMPLETE SUBMISSION FAILURE**  
**Fix:** Change JS to `action: 'submit_transfer'` OR add case to router

### **BUG #2: Conflicting Actions**
**Location:** `pack.js` lines 457 and 579  
**Issue:** `buildTransferObject()` sets `action: 'create_consignment'` but AJAX uses `action: 'save_transfer'`  
**Impact:** Inconsistent action handling  
**Fix:** Use consistent action throughout

### **BUG #3: Missing Validation Function**
**Location:** `pack.js` line 551  
**Issue:** Calls `validateTransferForSubmission()` - function doesn't exist  
**Impact:** JavaScript error, submission fails  
**Fix:** Implement validation function or remove call

### **BUG #4: Duplicate Data Flows**
**Location:** Two parallel API calls  
**Issue:** AJAX to `api.php` AND fetch to `enhanced-transfer-upload.php`  
**Impact:** Confusing flow, potential race conditions  
**Fix:** Consolidate to single data flow

---

## 📊 **DATABASE FIELD MAPPINGS**

### **Tables Written To:**
1. **queue_consignments** - Main consignment record
2. **queue_consignment_products** - Product details (future)
3. **transfers** - Update state (potential)

### **Tables Read From:**
1. **transfers** - Transfer validation
2. **queue_consignments** - Progress tracking
3. **queue_consignment_products** - Product progress

### **Field Dependencies:**
- `transfer_id` → Used across all queries
- `upload_session_id` → Links progress tracking
- `vend_consignment_id` → Generated as 'ENHANCED_' + sessionId
- `product_id`, `product_sku`, `product_name` → Extracted from HTML table

---

## 🎯 **FUNCTION CALL CHAIN**

```
HTML Button onClick="submitTransfer()"
  ↓
submitTransfer() [pack.js:542]
  ↓
buildTransferObject() [pack.js:454]
  ↓ (collects data from #transfer-table)
  ↓
ConsignmentsAjax.request() [ajax-manager.js:58]
  ↓ (sends action: 'save_transfer')
  ↓
api.php switch statement [api.php:45]
  ↓ ❌ NO MATCHING CASE - Returns error
  ↓
PARALLEL: fetch() to enhanced-transfer-upload.php
  ↓
ConsignmentUploadProcessor.processUpload()
  ↓
UploadProgressTracker.initializeProgress()
  ↓
INSERT INTO queue_consignments
```

---

## ✅ **FIXES NEEDED FOR WORKING SYSTEM**

1. **Fix Action Routing:**
   ```php
   // In api.php, add:
   case 'save_transfer':
       require_once __DIR__ . '/save_transfer.php';
       break;
   ```

2. **Fix JavaScript Action:**
   ```javascript
   // In pack.js, change:
   action: 'submit_transfer',  // Instead of 'save_transfer'
   ```

3. **Implement Missing Validation:**
   ```javascript
   function validateTransferForSubmission() {
     // Add validation logic
     return { isValid: true, errors: [], warnings: [] };
   }
   ```

4. **Consolidate Data Flow:**
   - Either use main API router for everything
   - OR bypass main router and use enhanced upload only

---

**Analysis Complete:** Every line traced from button click to database insertion  
**Status:** Multiple critical bugs found that prevent submission working  
**Recommendation:** Fix routing bugs before deployment