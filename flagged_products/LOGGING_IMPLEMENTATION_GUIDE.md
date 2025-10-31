# Flagged Products Module - Logging Implementation Guide

**Version:** 1.0.0  
**Date:** October 26, 2025  
**Purpose:** Comprehensive guide to logging throughout the Flagged Products module

---

## 🎯 **Logging Philosophy: Two-Tier System**

### **Tier 1: Universal CISLogger** (Already Exists)
- **Purpose:** Cross-module analysis, AI training, audit trail
- **Table:** `cis_action_log`
- **When to use:** High-level business events

### **Tier 2: Module-Specific Logger** (NEW!)
- **Purpose:** Module-specific convenience wrapper
- **Class:** `FlaggedProducts\Lib\Logger`
- **When to use:** Everywhere in this module (wraps CISLogger)

### **Specialized Tables** (Domain-Specific)
- **Purpose:** Detailed workflow, compliance, fast queries
- **Tables:** `flagged_products_completions`, `flagged_products_audit`, etc.
- **When to use:** Detailed state tracking, regulatory requirements

---

## 📁 **What Was Implemented**

### ✅ **Created Files:**
1. `/lib/Logger.php` - Module-specific logger wrapper (500 lines)
2. Updated `/bootstrap.php` - Auto-loads Logger class
3. Updated `/api/complete-product.php` - Example implementation

### ✅ **Logger Methods Available:**

#### **Product Actions:**
- `Logger::productCompleted()` - Product completion
- `Logger::productGenerated()` - AI/bot generation
- `Logger::productQualityUpdated()` - Quality changes
- `Logger::productDeleted()` - Product removal
- `Logger::productFlagged()` - Product added to system

#### **Leaderboard & Achievements:**
- `Logger::achievementEarned()` - Achievement unlocked
- `Logger::leaderboardUpdated()` - Leaderboard refresh
- `Logger::storeStatsRefreshed()` - Store stats update

#### **AI & Insights:**
- `Logger::insightGenerated()` - AI insights
- `Logger::patternDetected()` - Pattern detection

#### **Cron Tasks:**
- `Logger::cronTaskStarted()` - Task start
- `Logger::cronTaskCompleted()` - Task completion

#### **Security:**
- `Logger::cheatDetected()` - Anti-cheat triggers
- `Logger::devToolsDetected()` - DevTools detected
- `Logger::suspiciousActivity()` - Suspicious behavior

#### **Performance:**
- `Logger::pageLoad()` - Page load times
- `Logger::slowQuery()` - Slow queries
- `Logger::apiResponse()` - API response times

#### **Errors:**
- `Logger::error()` - Errors
- `Logger::warning()` - Warnings

---

## 🚀 **Implementation Roadmap**

### **Phase 1: API Endpoints** (STARTED ✅)
**Files to update:**
- ✅ `/api/complete-product.php` - DONE
- `/api/report-violation.php` - Add Logger
- `/api/cron_monitoring.php` - Add Logger

**Pattern:**
```php
use FlaggedProducts\Lib\Logger;

$apiStartTime = microtime(true);

try {
    // Your API logic
    $result = doSomething();
    
    Logger::productCompleted($productId, 'reason', $quality, $timeSpent);
    
    $apiDuration = (microtime(true) - $apiStartTime) * 1000;
    Logger::apiResponse('endpoint-name', $apiDuration, true);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    Logger::error('operation_failed', $e->getMessage(), 'entity', $entityId);
    
    $apiDuration = (microtime(true) - $apiStartTime) * 1000;
    Logger::apiResponse('endpoint-name', $apiDuration, false, $e->getMessage());
    
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
```

---

### **Phase 2: Controllers** (TODO)
**Files to update:**
- `/controllers/ProductController.php` (if exists)
- Any MVC controllers

**Pattern:**
```php
use FlaggedProducts\Lib\Logger;

class ProductController {
    
    public function complete($productId) {
        $startTime = microtime(true);
        
        try {
            $result = $this->productModel->complete($productId);
            
            Logger::productCompleted(
                $productId,
                'user_action',
                $result['quality'],
                (microtime(true) - $startTime)
            );
            
            return $result;
            
        } catch (Exception $e) {
            Logger::error('product_completion_failed', $e->getMessage(), 'product', $productId);
            throw $e;
        }
    }
}
```

---

### **Phase 3: Models/Repositories** (TODO)
**Files to update:**
- `/models/FlaggedProductsRepository.php`

**Pattern:**
```php
use FlaggedProducts\Lib\Logger;

public static function completeProduct($productId, $userId, $outletId, $quantity, $context, $timeTaken) {
    try {
        // Your existing logic
        $result = self::doCompletion(...);
        
        // Log to specialized table (if exists)
        self::logToCompletionTable($productId, $userId, $result);
        
        // Log to universal logger (automatic via Logger class)
        // Logger is called by API/Controller layer
        
        return $result;
        
    } catch (Exception $e) {
        Logger::error('repository_error', $e->getMessage(), 'product', $productId);
        throw $e;
    }
}
```

**Important:** Models/repositories should focus on DATA operations. Let the API/Controller layer handle high-level logging.

---

### **Phase 4: Cron Tasks** (TODO)
**Files to update:**
- `/cron/generate_daily_products.php`
- `/cron/refresh_leaderboard.php`
- `/cron/refresh_store_stats.php`
- `/cron/check_achievements.php`
- `/cron/generate_ai_insights.php`

**Pattern:**
```php
use FlaggedProducts\Lib\Logger;

// At start
Logger::cronTaskStarted('generate_daily_products', ['target_count' => 20]);

try {
    // Your cron logic
    $productsGenerated = generateProducts();
    
    foreach ($productsGenerated as $product) {
        Logger::productGenerated(
            $product['id'],
            $product['strategy'],
            $product['ai_data'],
            $product['outlet_id']
        );
    }
    
    // At completion
    Logger::cronTaskCompleted('generate_daily_products', true, [
        'products_generated' => count($productsGenerated),
        'outlets_processed' => count($outlets),
        'strategies_used' => $strategyCounts
    ]);
    
} catch (Exception $e) {
    Logger::cronTaskCompleted('generate_daily_products', false, [], $e->getMessage());
    throw $e;
}
```

---

### **Phase 5: Views/Pages** (TODO)
**Files to update:**
- `/views/dashboard.php`
- `/views/product-list.php`
- Any page that loads

**Pattern:**
```php
$pageStartTime = microtime(true);

// Your page logic

// At page end (before closing </body>)
<?php
$pageLoadTime = (microtime(true) - $pageStartTime) * 1000;
if (class_exists('FlaggedProducts\Lib\Logger')) {
    FlaggedProducts\Lib\Logger::pageLoad(
        'flagged_products_dashboard',
        $pageLoadTime,
        ['user_id' => $_SESSION['userID'], 'outlet_id' => $_SESSION['outlet_id']]
    );
}
?>
```

---

### **Phase 6: AntiCheat Integration** (TODO)
**File to update:**
- `/lib/AntiCheat.php`

**Pattern:**
```php
use FlaggedProducts\Lib\Logger;

public static function detectCheat($userId, $context) {
    $cheatDetected = self::analyze($context);
    
    if ($cheatDetected) {
        Logger::cheatDetected(
            $context['cheatType'],
            $userId,
            $context['evidence'],
            'user_flagged'
        );
        
        // Your existing anti-cheat logic
        self::flagUser($userId);
    }
}

public static function detectDevTools($userId, $page) {
    Logger::devToolsDetected($userId, $page);
    // Your existing devtools handling
}
```

---

## 📊 **What Gets Logged Where**

### **Scenario: User Completes a Product**

```
┌─────────────────────────────────────────────┐
│ User clicks "Complete" button              │
└─────────────────┬───────────────────────────┘
                  │
                  ↓
┌─────────────────────────────────────────────┐
│ API: complete-product.php                   │
│                                             │
│ 1. Logger::productCompleted()               │
│    → Logs to cis_action_log                 │
│    → Category: flagged_products             │
│    → Action: product_completed              │
│    → Entity: product ID                     │
│                                             │
│ 2. Logger::apiResponse()                    │
│    → Logs to cis_performance_metrics        │
│    → Metric: API response time              │
└─────────────────┬───────────────────────────┘
                  │
                  ↓
┌─────────────────────────────────────────────┐
│ Repository: FlaggedProductsRepository       │
│                                             │
│ 1. Updates flagged_products_completions     │
│    → Specialized table (workflow detail)    │
│    → Stores: time, quality, security        │
│                                             │
│ 2. Checks achievements                      │
│    → Logger::achievementEarned() if earned  │
└─────────────────────────────────────────────┘
```

**Result:** 3-4 log entries across 2-3 tables:
- `cis_action_log`: High-level "product completed" event
- `cis_performance_metrics`: API response time
- `flagged_products_completions`: Detailed completion data
- `cis_action_log` (maybe): Achievement earned

---

## 🎯 **Separation of Concerns: Examples**

### **Example 1: Stock Transfer**

#### ❌ **WRONG** - Log everything to universal logger:
```php
CISLogger::action('transfers', 'line_item_added', 'success', 'line_item', $itemId, [
    'product_id' => $productId,
    'quantity' => $quantity,
    'unit_cost' => $cost,
    'barcode' => $barcode,
    'weight' => $weight,
    'dimensions' => $dimensions
    // ... 20 more fields
]);
```

#### ✅ **RIGHT** - Specialized table for details, universal for events:
```php
// Detailed data → specialized table
$sql = "INSERT INTO transfer_line_items (...) VALUES (...)";
sql_query_update_or_insert_safe($sql, $params);

// High-level event → universal logger
CISLogger::action('transfers', 'item_added_to_transfer', 'success', 'transfer', $transferId, [
    'product_name' => $productName,
    'quantity' => $quantity,
    'total_items' => $itemCount
]);
```

---

### **Example 2: Consignment Workflow**

#### ✅ **Use BOTH:**
```php
// Specialized workflow table (fast status queries)
$sql = "INSERT INTO consignment_status_log 
        (consignment_id, old_status, new_status, user_id, timestamp, notes)
        VALUES (?, ?, ?, ?, NOW(), ?)";
sql_query_update_or_insert_safe($sql, [$id, 'draft', 'sent', $userId, $notes]);

// Universal logger (cross-module analysis, AI training)
CISLogger::action('consignments', 'consignment_sent', 'success', 'consignment', $id, [
    'from_status' => 'draft',
    'to_status' => 'sent',
    'item_count' => count($items),
    'destination' => $outletName
]);
```

**Why both?**
- Specialized table: Query "show all consignments in 'sent' status" (fast!)
- Universal logger: Query "what did this user do today across all modules?" (insights!)

---

### **Example 3: Financial Transaction**

#### ✅ **Specialized table is PRIMARY:**
```php
// PRIMARY: Financial audit table (regulatory requirement)
$sql = "INSERT INTO financial_transactions 
        (type, amount, user_id, timestamp, reference, metadata, audit_hash)
        VALUES (?, ?, ?, NOW(), ?, ?, ?)";
sql_query_update_or_insert_safe($sql, [...]);

// SECONDARY: Universal logger (for AI/analytics)
CISLogger::action('finance', 'transaction_recorded', 'success', 'transaction', $txId, [
    'type' => $type,
    'amount' => $amount,
    'reference' => $reference
]);
```

**Rule:** Financial/compliance data ALWAYS goes to specialized table FIRST. Universal logging is secondary.

---

## 🛡️ **Security Logging**

### **Always log:**
- Login attempts (success/failure)
- Permission violations
- Data deletion
- Security threshold breaches
- DevTools detection
- Anti-cheat triggers

### **Example:**
```php
// User tries to delete without permission
if (!$user->hasPermission('delete_product')) {
    Logger::suspiciousActivity(
        'permission_violation',
        $userId,
        [
            'attempted_action' => 'delete_product',
            'required_permission' => 'delete_product',
            'product_id' => $productId
        ]
    );
    
    http_response_code(403);
    echo json_encode(['error' => 'Insufficient permissions']);
    exit;
}
```

---

## 📈 **Performance Logging**

### **Slow Query Detection:**
```php
$queryStart = microtime(true);
$result = sql_query_read_safe($sql, $params);
$queryTime = (microtime(true) - $queryStart) * 1000;

if ($queryTime > 500) { // Slower than 500ms
    Logger::slowQuery(
        'flagged_products_leaderboard',
        $queryTime,
        $sql,
        $params
    );
}
```

### **Page Load Tracking:**
```php
// At top of page
$pageStart = microtime(true);

// At bottom of page
$pageLoadTime = (microtime(true) - $pageStart) * 1000;
Logger::pageLoad('flagged_products_dashboard', $pageLoadTime, [
    'query_count' => sql_query_count(),
    'memory_peak' => memory_get_peak_usage(true) / 1024 / 1024
]);
```

---

## ✅ **Quick Checklist: When to Log**

| Event | Logger Method | Priority |
|-------|--------------|----------|
| User completes product | `productCompleted()` | 🔴 HIGH |
| Bot generates product | `productGenerated()` | 🔴 HIGH |
| Achievement earned | `achievementEarned()` | 🟡 MEDIUM |
| Page loads | `pageLoad()` | 🟢 LOW |
| API request | `apiResponse()` | 🟡 MEDIUM |
| Slow query (>500ms) | `slowQuery()` | 🟡 MEDIUM |
| Security violation | `cheatDetected()` / `suspiciousActivity()` | 🔴 HIGH |
| DevTools detected | `devToolsDetected()` | 🟡 MEDIUM |
| Cron task runs | `cronTaskStarted()` / `cronTaskCompleted()` | 🔴 HIGH |
| Error occurs | `error()` | 🔴 HIGH |
| Data deleted | `productDeleted()` | 🔴 HIGH |

---

## 🎓 **Best Practices**

### ✅ DO:
- Log at boundaries (API entry/exit, function entry/exit)
- Include context (what led to this?)
- Log decisions, not just outcomes (WHY this happened)
- Use specialized tables for detailed workflow data
- Use universal logger for high-level events
- Log performance metrics (slow queries, page loads)
- Log security events (violations, suspicious activity)

### ❌ DON'T:
- Log sensitive data (passwords, credit cards)
- Log excessive detail in universal logger (use specialized tables)
- Duplicate identical data across logs
- Log in tight loops (batch log instead)
- Forget to log errors/exceptions
- Mix concerns (financial audit data in universal logger)

---

## 📝 **Next Steps**

1. ✅ **DONE:** Created Logger class
2. ✅ **DONE:** Updated bootstrap to auto-load
3. ✅ **DONE:** Implemented in `complete-product.php` API
4. **TODO:** Implement in `report-violation.php` API
5. **TODO:** Implement in all cron tasks (5 files)
6. **TODO:** Implement in controllers (if any)
7. **TODO:** Implement in views (page load tracking)
8. **TODO:** Integrate with AntiCheat library
9. **TODO:** Add slow query detection to repository
10. **TODO:** Create logging dashboard (view logs)

---

## 🎯 **Summary**

### **What you have now:**
- ✅ Universal CISLogger (cis_action_log, cis_ai_context, etc.)
- ✅ Module-specific Logger wrapper (FlaggedProducts\Lib\Logger)
- ✅ Specialized tables for detailed data (completions, audit, etc.)
- ✅ Example implementation (complete-product.php)
- ✅ Bootstrap auto-loading

### **Two-tier logging approach:**
1. **Universal (CISLogger):** High-level events, cross-module analysis, AI training
2. **Specialized tables:** Detailed workflow, compliance, fast queries

### **How to use:**
```php
use FlaggedProducts\Lib\Logger;

// Log any event
Logger::productCompleted($id, $reason, $quality, $time);
Logger::achievementEarned($userId, $achId, $name, $points);
Logger::error($type, $message, $entityType, $entityId, $context);

// It automatically logs to cis_action_log via CISLogger
// Plus adds module-specific conveniences
```

**Result:** Clean, consistent, comprehensive logging across your entire module! 🎉
