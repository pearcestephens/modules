# PurchaseOrderLogger API Reference

**Version:** 1.0.0
**Last Updated:** October 31, 2025
**Module:** CIS\Consignments

Complete reference for all PurchaseOrderLogger semantic methods with examples and best practices.

---

## Table of Contents

1. [Overview](#overview)
2. [Purchase Order Operations](#purchase-order-operations)
3. [Freight Operations](#freight-operations)
4. [AI Recommendations](#ai-recommendations)
5. [Security & Fraud Detection](#security--fraud-detection)
6. [UI Interactions](#ui-interactions)
7. [Performance Tracking](#performance-tracking)
8. [Error Handling](#error-handling)
9. [Best Practices](#best-practices)
10. [Examples](#examples)

---

## Overview

`PurchaseOrderLogger` is a comprehensive semantic logging wrapper around the core `CISLogger` service, specifically designed for the Purchase Orders module. It provides 40+ methods organized by domain context, ensuring consistent, structured logging across all PO workflows.

### Key Features

- **Fail-Safe Design**: All methods wrapped in try/catch, never break application flow
- **Semantic Organization**: Methods grouped by business domain (PO, Freight, AI, Security, etc.)
- **Rich Context**: Automatically captures user, timestamp, session, request details
- **AI-Optimized**: Structured for AI analysis and pattern detection
- **Performance Aware**: Minimal overhead, async-capable
- **GDPR Compliant**: No PII capture beyond user_id (which is already logged separately)

### Architecture

```
PurchaseOrderLogger (Module-Specific Wrapper)
    ↓
CISLogger (Core Service)
    ↓
Database Tables:
    - cis_action_log (general actions)
    - cis_ai_context (AI decisions)
    - cis_security_log (security events)
    - cis_performance_metrics (timing/performance)
    - cis_bot_pipeline_log (automation)
```

### Basic Usage Pattern

```php
// Fail-safe import check
if (file_exists(__DIR__ . '/path/to/PurchaseOrderLogger.php')) {
    require_once __DIR__ . '/path/to/PurchaseOrderLogger.php';
}

// Use fully qualified name
use CIS\Consignments\PurchaseOrderLogger;

// Call semantic method
PurchaseOrderLogger::poCreated($poId, $supplierName, $outletName, $totalCost);
```

---

## Purchase Order Operations

Core purchase order lifecycle events.

### `poCreated()`

Logs when a new purchase order is created (DRAFT state).

**Signature:**
```php
public static function poCreated(
    int $poId,
    string $supplierName,
    string $outletName,
    float $totalCost
): void
```

**Parameters:**
- `$poId` (int, required): Purchase order ID
- `$supplierName` (string, required): Supplier company name
- `$outletName` (string, required): Destination outlet name
- `$totalCost` (float, required): Total order value

**Example:**
```php
PurchaseOrderLogger::poCreated(
    $poId: 12345,
    $supplierName: 'VaporHub Ltd',
    $outletName: 'Auckland Central',
    $totalCost: 2450.75
);
```

**Logged To:** `cis_action_log`
**Action Type:** `purchase_order.created`

---

### `poApproved()`

Logs when a purchase order is approved (PENDING_APPROVAL → APPROVED).

**Signature:**
```php
public static function poApproved(
    int $poId,
    ?int $approverId = null,
    ?string $comments = null
): void
```

**Parameters:**
- `$poId` (int, required): Purchase order ID
- `$approverId` (int, optional): User ID of approver (defaults to current session user)
- `$comments` (string, optional): Approval comments/notes

**Example:**
```php
PurchaseOrderLogger::poApproved(
    $poId: 12345,
    $approverId: 42,
    $comments: 'Approved - within budget and necessary for stock replenishment'
);
```

**Logged To:** `cis_action_log`
**Action Type:** `purchase_order.approved`

---

### `poRejected()`

Logs when a purchase order is rejected during approval process.

**Signature:**
```php
public static function poRejected(
    int $poId,
    string $reason,
    ?int $rejectedById = null
): void
```

**Parameters:**
- `$poId` (int, required): Purchase order ID
- `$reason` (string, required): Rejection reason
- `$rejectedById` (int, optional): User ID of rejector

**Example:**
```php
PurchaseOrderLogger::poRejected(
    $poId: 12345,
    $reason: 'Over budget - need cost reduction before approval',
    $rejectedById: 42
);
```

**Logged To:** `cis_action_log`
**Action Type:** `purchase_order.rejected`

---

### `poSentToSupplier()`

Logs when a purchase order is sent to the supplier (APPROVED → SENT).

**Signature:**
```php
public static function poSentToSupplier(
    int $poId,
    string $method,
    ?string $emailAddress = null
): void
```

**Parameters:**
- `$poId` (int, required): Purchase order ID
- `$method` (string, required): Send method ('email', 'fax', 'portal', 'manual')
- `$emailAddress` (string, optional): Supplier email if sent via email

**Example:**
```php
PurchaseOrderLogger::poSentToSupplier(
    $poId: 12345,
    $method: 'email',
    $emailAddress: 'orders@vaporhub.co.nz'
);
```

**Logged To:** `cis_action_log`
**Action Type:** `purchase_order.sent_to_supplier`

---

### `poReceivingStarted()`

Logs when receiving process begins (SENT → RECEIVING).

**Signature:**
```php
public static function poReceivingStarted(
    int $poId,
    int $expectedItems,
    ?string $location = null
): void
```

**Parameters:**
- `$poId` (int, required): Purchase order ID
- `$expectedItems` (int, required): Number of line items expected
- `$location` (string, optional): Physical receiving location/dock

**Example:**
```php
PurchaseOrderLogger::poReceivingStarted(
    $poId: 12345,
    $expectedItems: 45,
    $location: 'Warehouse Dock 2'
);
```

**Logged To:** `cis_action_log`
**Action Type:** `purchase_order.receiving_started`

---

### `poReceivingCompleted()`

Logs when receiving process completes (RECEIVING → RECEIVED).

**Signature:**
```php
public static function poReceivingCompleted(
    int $poId,
    int $itemsReceived,
    int $discrepancies,
    float $durationMinutes
): void
```

**Parameters:**
- `$poId` (int, required): Purchase order ID
- `$itemsReceived` (int, required): Actual items received
- `$discrepancies` (int, required): Number of discrepancies found
- `$durationMinutes` (float, required): Time taken for receiving

**Example:**
```php
PurchaseOrderLogger::poReceivingCompleted(
    $poId: 12345,
    $itemsReceived: 43,
    $discrepancies: 2,
    $durationMinutes: 28.5
);
```

**Logged To:** `cis_action_log`
**Action Type:** `purchase_order.receiving_completed`

**Note:** This often triggers TransferReviewService to generate coaching/metrics.

---

## Freight Operations

Freight quote generation, carrier selection, and label creation.

### `freightQuoteGenerated()`

Logs when freight quotes are generated from carriers.

**Signature:**
```php
public static function freightQuoteGenerated(
    int $poId,
    int $quoteCount,
    string $strategy,
    float $responseTimeSeconds
): void
```

**Parameters:**
- `$poId` (int, required): Purchase order ID
- `$quoteCount` (int, required): Number of quotes received
- `$strategy` (string, required): Quote strategy ('fastest', 'cheapest', 'balanced')
- `$responseTimeSeconds` (float, required): API response time

**Example:**
```php
PurchaseOrderLogger::freightQuoteGenerated(
    $poId: 12345,
    $quoteCount: 4,
    $strategy: 'balanced',
    $responseTimeSeconds: 2.3
);
```

**Logged To:** `cis_action_log`
**Action Type:** `freight.quote_generated`

---

### `carrierSelected()`

Logs when user selects a freight carrier.

**Signature:**
```php
public static function carrierSelected(
    int $poId,
    string $carrier,
    string $service,
    float $cost,
    ?string $reason = null
): void
```

**Parameters:**
- `$poId` (int, required): Purchase order ID
- `$carrier` (string, required): Carrier name ('CourierPost', 'Mainfreight', etc.)
- `$service` (string, required): Service level ('Standard', 'Express', 'Overnight')
- `$cost` (float, required): Selected quote cost
- `$reason` (string, optional): Selection reason ('cheapest', 'fastest', 'preferred_carrier', 'ai_recommended')

**Example:**
```php
PurchaseOrderLogger::carrierSelected(
    $poId: 12345,
    $carrier: 'CourierPost',
    $service: 'Overnight',
    $cost: 45.80,
    $reason: 'ai_recommended'
);
```

**Logged To:** `cis_action_log`
**Action Type:** `freight.carrier_selected`

---

### `freightLabelGenerated()`

Logs when a shipping label is created.

**Signature:**
```php
public static function freightLabelGenerated(
    int $poId,
    string $trackingNumber,
    string $labelFormat,
    bool $success
): void
```

**Parameters:**
- `$poId` (int, required): Purchase order ID
- `$trackingNumber` (string, required): Carrier tracking number
- `$labelFormat` (string, required): Label format ('PDF', 'PNG', 'ZPL')
- `$success` (bool, required): Whether generation succeeded

**Example:**
```php
PurchaseOrderLogger::freightLabelGenerated(
    $poId: 12345,
    $trackingNumber: 'CP123456789NZ',
    $labelFormat: 'PDF',
    $success: true
);
```

**Logged To:** `cis_action_log`
**Action Type:** `freight.label_generated`

---

## AI Recommendations

AI-generated insights and user decisions.

### `aiRecommendationGenerated()`

Logs when AI generates a recommendation for a PO.

**Signature:**
```php
public static function aiRecommendationGenerated(
    int $poId,
    string $type,
    string $category,
    $suggestedValue,
    float $confidence
): void
```

**Parameters:**
- `$poId` (int, required): Purchase order ID
- `$type` (string, required): Recommendation type ('pricing_adjustment', 'quantity_change', 'alternative_product', 'freight_selection')
- `$category` (string, required): Category ('cost_optimization', 'inventory_management', 'fraud_detection', 'efficiency')
- `$suggestedValue` (mixed, required): The recommended value (string, number, JSON)
- `$confidence` (float, required): AI confidence score (0.0 - 1.0)

**Example:**
```php
PurchaseOrderLogger::aiRecommendationGenerated(
    $poId: 12345,
    $type: 'pricing_adjustment',
    $category: 'cost_optimization',
    $suggestedValue: json_encode(['product_id' => 789, 'suggested_price' => 12.50, 'current_price' => 15.00]),
    $confidence: 0.92
);
```

**Logged To:** `cis_ai_context`
**Action Type:** `ai.recommendation_generated`

---

### `aiRecommendationAccepted()`

Logs when user accepts an AI recommendation.

**Signature:**
```php
public static function aiRecommendationAccepted(
    int $poId,
    string $type,
    string $category,
    $appliedValue,
    float $confidence,
    int $reviewTimeSeconds
): void
```

**Parameters:**
- `$poId` (int, required): Purchase order ID
- `$type` (string, required): Recommendation type
- `$category` (string, required): Recommendation category
- `$appliedValue` (mixed, required): The value actually applied
- `$confidence` (float, required): Original AI confidence
- `$reviewTimeSeconds` (int, required): Time user spent reviewing before accepting

**Example:**
```php
PurchaseOrderLogger::aiRecommendationAccepted(
    $poId: 12345,
    $type: 'pricing_adjustment',
    $category: 'cost_optimization',
    $appliedValue: 12.50,
    $confidence: 0.92,
    $reviewTimeSeconds: 45
);
```

**Logged To:** `cis_ai_context`
**Action Type:** `ai.recommendation_accepted`

**Metrics Tracked:**
- Acceptance rate by type/category
- Average review time before acceptance
- Confidence threshold trends

---

### `aiRecommendationDismissed()`

Logs when user dismisses/rejects an AI recommendation.

**Signature:**
```php
public static function aiRecommendationDismissed(
    int $poId,
    string $type,
    string $category,
    ?string $dismissReason,
    int $reviewTimeSeconds
): void
```

**Parameters:**
- `$poId` (int, required): Purchase order ID
- `$type` (string, required): Recommendation type
- `$category` (string, required): Recommendation category
- `$dismissReason` (string, optional): Why user dismissed ('not_applicable', 'too_risky', 'prefer_manual', 'incorrect_data')
- `$reviewTimeSeconds` (int, required): Time spent reviewing before dismissing

**Example:**
```php
PurchaseOrderLogger::aiRecommendationDismissed(
    $poId: 12345,
    $type: 'pricing_adjustment',
    $category: 'cost_optimization',
    $dismissReason: 'prefer_manual',
    $reviewTimeSeconds: 15
);
```

**Logged To:** `cis_ai_context`
**Action Type:** `ai.recommendation_dismissed`

**Metrics Tracked:**
- Dismissal rate by type/category
- Common dismissal reasons
- Fast dismissals (< 10 seconds) vs thoughtful (> 30 seconds)

---

### `aiBulkAccept()`

Logs when user accepts multiple AI recommendations at once.

**Signature:**
```php
public static function aiBulkAccept(
    int $poId,
    int $count
): void
```

**Parameters:**
- `$poId` (int, required): Purchase order ID
- `$count` (int, required): Number of insights accepted

**Example:**
```php
PurchaseOrderLogger::aiBulkAccept(
    $poId: 12345,
    $count: 12
);
```

**Logged To:** `cis_ai_context`
**Action Type:** `ai.bulk_accept`

---

### `aiBulkDismiss()`

Logs when user dismisses multiple AI recommendations at once.

**Signature:**
```php
public static function aiBulkDismiss(
    int $poId,
    int $count,
    ?string $reason = null
): void
```

**Parameters:**
- `$poId` (int, required): Purchase order ID
- `$count` (int, required): Number of insights dismissed
- `$reason` (string, optional): Bulk dismissal reason

**Example:**
```php
PurchaseOrderLogger::aiBulkDismiss(
    $poId: 12345,
    $count: 8,
    $reason: 'Already reviewed manually'
);
```

**Logged To:** `cis_ai_context`
**Action Type:** `ai.bulk_dismiss`

---

## Security & Fraud Detection

Client-side security monitoring events.

### `securityDevToolsDetected()`

Logs when browser DevTools are detected open during PO operations.

**Signature:**
```php
public static function securityDevToolsDetected(
    int $poId,
    string $page,
    int $detectionCount
): void
```

**Parameters:**
- `$poId` (int, required): Purchase order ID
- `$page` (string, required): Page where detected ('view', 'edit', 'ai_insights', 'freight_quote')
- `$detectionCount` (int, required): Number of times detected in session

**Example:**
```php
PurchaseOrderLogger::securityDevToolsDetected(
    $poId: 12345,
    $page: 'ai_insights',
    $detectionCount: 3
);
```

**Logged To:** `cis_security_log`
**Action Type:** `security.devtools_detected`

**Context:** Detects potential script injection attempts or automated manipulation.

---

### `securityRapidKeyboardEntry()`

Logs when rapid keyboard typing is detected (potential bot behavior).

**Signature:**
```php
public static function securityRapidKeyboardEntry(
    int $poId,
    string $field,
    float $keysPerSecond
): void
```

**Parameters:**
- `$poId` (int, required): Purchase order ID
- `$field` (string, required): Field where detected
- `$keysPerSecond` (float, required): Measured keystroke rate

**Example:**
```php
PurchaseOrderLogger::securityRapidKeyboardEntry(
    $poId: 12345,
    $field: 'supplier_reference',
    $keysPerSecond: 12.5
);
```

**Logged To:** `cis_security_log`
**Action Type:** `security.rapid_keyboard`

**Threshold:** Default 8 keys/sec (configurable)
**Context:** Human typing rarely exceeds 8 keys/sec; bots often 15+

---

### `securityExcessiveCopyPaste()`

Logs when excessive copy/paste operations detected in session.

**Signature:**
```php
public static function securityExcessiveCopyPaste(
    int $poId,
    string $page,
    int $pasteCount
): void
```

**Parameters:**
- `$poId` (int, required): Purchase order ID
- `$page` (string, required): Page where detected
- `$pasteCount` (int, required): Number of paste operations

**Example:**
```php
PurchaseOrderLogger::securityExcessiveCopyPaste(
    $poId: 12345,
    $page: 'edit',
    $pasteCount: 5
);
```

**Logged To:** `cis_security_log`
**Action Type:** `security.excessive_copy_paste`

**Threshold:** Default 3 pastes (configurable)
**Context:** May indicate bulk data manipulation or automated entry

---

### `securityTabSwitchDuringOperation()`

Logs when user frequently switches tabs/loses focus during critical operation.

**Signature:**
```php
public static function securityTabSwitchDuringOperation(
    int $poId,
    string $operation,
    int $switchCount
): void
```

**Parameters:**
- `$poId` (int, required): Purchase order ID
- `$operation` (string, required): Operation during switches ('approval', 'receiving', 'freight_selection')
- `$switchCount` (int, required): Number of focus losses

**Example:**
```php
PurchaseOrderLogger::securityTabSwitchDuringOperation(
    $poId: 12345,
    $operation: 'approval',
    $switchCount: 4
);
```

**Logged To:** `cis_security_log`
**Action Type:** `security.focus_loss`

**Threshold:** Default 3 switches (configurable)
**Context:** May indicate multitasking, distraction, or following external script

---

### `fraudSuspiciousValue()`

Logs when a suspicious value is entered (e.g., unusually high quantity).

**Signature:**
```php
public static function fraudSuspiciousValue(
    int $poId,
    string $field,
    $value,
    string $reason
): void
```

**Parameters:**
- `$poId` (int, required): Purchase order ID
- `$field` (string, required): Field name ('quantity', 'unit_price', 'discount')
- `$value` (mixed, required): The suspicious value
- `$reason` (string, required): Why flagged ('exceeds_threshold', 'unusual_pattern', 'out_of_range')

**Example:**
```php
PurchaseOrderLogger::fraudSuspiciousValue(
    $poId: 12345,
    $field: 'quantity',
    $value: 99999,
    $reason: 'exceeds_threshold'
);
```

**Logged To:** `cis_security_log`
**Action Type:** `fraud.suspicious_value`

---

### `fraudLargeDiscrepancy()`

Logs when large discrepancy found during receiving.

**Signature:**
```php
public static function fraudLargeDiscrepancy(
    int $poId,
    int $lineItemId,
    int $expected,
    int $actual,
    float $percentageDiff
): void
```

**Parameters:**
- `$poId` (int, required): Purchase order ID
- `$lineItemId` (int, required): Line item with discrepancy
- `$expected` (int, required): Expected quantity
- `$actual` (int, required): Actual received quantity
- `$percentageDiff` (float, required): Percentage difference

**Example:**
```php
PurchaseOrderLogger::fraudLargeDiscrepancy(
    $poId: 12345,
    $lineItemId: 789,
    $expected: 100,
    $actual: 45,
    $percentageDiff: 55.0
);
```

**Logged To:** `cis_security_log`
**Action Type:** `fraud.large_discrepancy`

**Threshold:** Typically > 20% variance

---

## UI Interactions

User interface interactions and modal tracking.

### `modalOpened()`

Logs when a modal/dialog is opened.

**Signature:**
```php
public static function modalOpened(
    int $poId,
    string $modalType,
    string $page
): void
```

**Parameters:**
- `$poId` (int, required): Purchase order ID
- `$modalType` (string, required): Modal identifier ('approval', 'ai_insights', 'freight_quote', 'line_item_edit')
- `$page` (string, required): Parent page

**Example:**
```php
PurchaseOrderLogger::modalOpened(
    $poId: 12345,
    $modalType: 'ai_insights',
    $page: 'view'
);
```

**Logged To:** `cis_action_log`
**Action Type:** `ui.modal_opened`

---

### `modalClosed()`

Logs when a modal is closed.

**Signature:**
```php
public static function modalClosed(
    int $poId,
    string $modalType,
    int $durationSeconds,
    ?string $action = null
): void
```

**Parameters:**
- `$poId` (int, required): Purchase order ID
- `$modalType` (string, required): Modal identifier
- `$durationSeconds` (int, required): How long modal was open
- `$action` (string, optional): Action taken ('saved', 'cancelled', 'dismissed')

**Example:**
```php
PurchaseOrderLogger::modalClosed(
    $poId: 12345,
    $modalType: 'ai_insights',
    $durationSeconds: 45,
    $action: 'saved'
);
```

**Logged To:** `cis_action_log`
**Action Type:** `ui.modal_closed`

**Metrics Tracked:**
- Average modal duration
- Completion rate (saved vs cancelled)
- Quick closes (< 5 seconds) vs engaged (> 30 seconds)

---

### `buttonClicked()`

Logs button clicks for important actions.

**Signature:**
```php
public static function buttonClicked(
    int $poId,
    string $buttonId,
    string $page,
    ?array $context = null
): void
```

**Parameters:**
- `$poId` (int, required): Purchase order ID
- `$buttonId` (string, required): Button identifier
- `$page` (string, required): Page where clicked
- `$context` (array, optional): Additional context (form data, etc.)

**Example:**
```php
PurchaseOrderLogger::buttonClicked(
    $poId: 12345,
    $buttonId: 'submitApproval',
    $page: 'view',
    $context: ['action' => 'APPROVED', 'has_comments' => true]
);
```

**Logged To:** `cis_action_log`
**Action Type:** `ui.button_clicked`

---

### `fieldValidationError()`

Logs when form field validation fails.

**Signature:**
```php
public static function fieldValidationError(
    int $poId,
    string $field,
    $value,
    string $errorMessage
): void
```

**Parameters:**
- `$poId` (int, required): Purchase order ID
- `$field` (string, required): Field name
- `$value` (mixed, required): Invalid value submitted
- `$errorMessage` (string, required): Validation error message

**Example:**
```php
PurchaseOrderLogger::fieldValidationError(
    $poId: 12345,
    $field: 'expected_date',
    $value: '2025-02-30',
    $errorMessage: 'Invalid date'
);
```

**Logged To:** `cis_action_log`
**Action Type:** `ui.validation_error`

**Context:** Helps identify usability issues and data entry problems

---

## Performance Tracking

Page load, API call, and operation timing.

### `pageLoad()`

Logs page load performance metrics.

**Signature:**
```php
public static function pageLoad(
    string $page,
    float $loadTimeSeconds,
    ?int $poId = null
): void
```

**Parameters:**
- `$page` (string, required): Page identifier
- `$loadTimeSeconds` (float, required): Total load time
- `$poId` (int, optional): Related PO ID if applicable

**Example:**
```php
PurchaseOrderLogger::pageLoad(
    $page: 'freight_quote',
    $loadTimeSeconds: 1.2,
    $poId: 12345
);
```

**Logged To:** `cis_performance_metrics`
**Action Type:** `performance.page_load`

---

### `apiCall()`

Logs API call performance.

**Signature:**
```php
public static function apiCall(
    string $endpoint,
    string $method,
    float $durationSeconds,
    int $statusCode,
    ?int $poId = null
): void
```

**Parameters:**
- `$endpoint` (string, required): API endpoint path
- `$method` (string, required): HTTP method ('GET', 'POST', 'PUT', 'DELETE')
- `$durationSeconds` (float, required): API response time
- `$statusCode` (int, required): HTTP status code
- `$poId` (int, optional): Related PO ID

**Example:**
```php
PurchaseOrderLogger::apiCall(
    $endpoint: '/api/purchase-orders/freight-quote.php',
    $method: 'GET',
    $durationSeconds: 2.3,
    $statusCode: 200,
    $poId: 12345
);
```

**Logged To:** `cis_performance_metrics`
**Action Type:** `performance.api_call`

---

### `databaseQuery()`

Logs slow database queries for optimization.

**Signature:**
```php
public static function databaseQuery(
    string $queryType,
    float $durationSeconds,
    ?string $tableName = null,
    ?int $poId = null
): void
```

**Parameters:**
- `$queryType` (string, required): Query type ('SELECT', 'INSERT', 'UPDATE', 'DELETE', 'JOIN')
- `$durationSeconds` (float, required): Query execution time
- `$tableName` (string, optional): Primary table queried
- `$poId` (int, optional): Related PO ID

**Example:**
```php
PurchaseOrderLogger::databaseQuery(
    $queryType: 'SELECT_JOIN',
    $durationSeconds: 0.8,
    $tableName: 'vend_consignments',
    $poId: 12345
);
```

**Logged To:** `cis_performance_metrics`
**Action Type:** `performance.database_query`

**Note:** Only log queries > 300ms threshold

---

## Error Handling

Application and user errors.

### `userError()`

Logs user-facing errors (not system errors).

**Signature:**
```php
public static function userError(
    int $poId,
    string $errorType,
    string $message,
    ?array $context = null
): void
```

**Parameters:**
- `$poId` (int, required): Purchase order ID
- `$errorType` (string, required): Error category ('validation', 'permission', 'workflow', 'data')
- `$message` (string, required): User-friendly error message
- `$context` (array, optional): Additional error context

**Example:**
```php
PurchaseOrderLogger::userError(
    $poId: 12345,
    $errorType: 'permission',
    $message: 'You do not have permission to approve this purchase order',
    $context: ['required_role' => 'APPROVER', 'user_role' => 'STAFF']
);
```

**Logged To:** `cis_action_log`
**Action Type:** `error.user`

---

### `systemError()`

Logs system/application errors.

**Signature:**
```php
public static function systemError(
    int $poId,
    string $component,
    string $message,
    ?string $trace = null
): void
```

**Parameters:**
- `$poId` (int, required): Purchase order ID
- `$component` (string, required): Component where error occurred ('freight_api', 'database', 'email', 'file_system')
- `$message` (string, required): Error message
- `$trace` (string, optional): Stack trace (truncated for security)

**Example:**
```php
PurchaseOrderLogger::systemError(
    $poId: 12345,
    $component: 'freight_api',
    $message: 'CourierPost API timeout after 30 seconds',
    $trace: substr($exception->getTraceAsString(), 0, 500)
);
```

**Logged To:** `cis_action_log`
**Action Type:** `error.system`

---

## Best Practices

### 1. Always Use Fail-Safe Pattern

```php
// ✅ CORRECT: Fail-safe import
if (file_exists(__DIR__ . '/../../lib/PurchaseOrderLogger.php')) {
    require_once __DIR__ . '/../../lib/PurchaseOrderLogger.php';
    if (class_exists('\\CIS\\Consignments\\PurchaseOrderLogger')) {
        PurchaseOrderLogger::poCreated($poId, $supplier, $outlet, $total);
    }
}

// ❌ WRONG: Direct call without check
PurchaseOrderLogger::poCreated($poId, $supplier, $outlet, $total);
```

### 2. Log at Key Decision Points

```php
// ✅ CORRECT: Log before and after critical operations
PurchaseOrderLogger::poApprovalRequested($poId, $requestedBy);

try {
    $approvalService->processApproval($poId);
    PurchaseOrderLogger::poApproved($poId, $approvedBy, $comments);
} catch (Exception $e) {
    PurchaseOrderLogger::systemError($poId, 'approval_service', $e->getMessage());
}
```

### 3. Include Timing for Performance Analysis

```php
// ✅ CORRECT: Track operation duration
$startTime = microtime(true);

// ... perform operation ...

$duration = round((microtime(true) - $startTime) * 1000) / 1000; // seconds
PurchaseOrderLogger::apiCall($endpoint, 'POST', $duration, 200, $poId);
```

### 4. Use Rich Context for AI Analysis

```php
// ✅ CORRECT: Provide detailed context
PurchaseOrderLogger::aiRecommendationDismissed(
    $poId,
    'pricing_adjustment',
    'cost_optimization',
    'prefer_manual', // Specific reason
    $reviewTimeSeconds
);

// ❌ WRONG: Vague context
PurchaseOrderLogger::aiRecommendationDismissed($poId, 'recommendation', 'general', null, 0);
```

### 5. Batch Logging for Bulk Operations

```php
// ✅ CORRECT: Use bulk methods
PurchaseOrderLogger::aiBulkAccept($poId, count($insightIds));

// ❌ WRONG: Log each individually
foreach ($insightIds as $id) {
    PurchaseOrderLogger::aiRecommendationAccepted(...); // Creates N log entries
}
```

### 6. Never Log Sensitive Data

```php
// ✅ CORRECT: Log aggregates only
PurchaseOrderLogger::securityExcessiveCopyPaste($poId, 'edit', 5);

// ❌ WRONG: Log raw input
PurchaseOrderLogger::someMethod($poId, $_POST); // May contain passwords, PII
```

### 7. Log User Actions, Not System State

```php
// ✅ CORRECT: Action-focused
PurchaseOrderLogger::poCreated($poId, $supplier, $outlet, $total);

// ❌ WRONG: State-focused
PurchaseOrderLogger::poStateChanged($poId, 'DRAFT', 'from system');
```

---

## Examples

### Complete PO Lifecycle Logging

```php
<?php
// 1. Creation
PurchaseOrderLogger::poCreated(
    $poId: 12345,
    $supplierName: 'VaporHub Ltd',
    $outletName: 'Auckland Central',
    $totalCost: 2450.75
);

// 2. Approval Request
PurchaseOrderLogger::modalOpened(12345, 'approval', 'view');
// ... user reviews ...
PurchaseOrderLogger::poApproved(12345, 42, 'Approved - within budget');
PurchaseOrderLogger::modalClosed(12345, 'approval', 45, 'saved');

// 3. Freight Quote
$startTime = microtime(true);
$quotes = $freightService->getQuotes(12345);
$duration = microtime(true) - $startTime;

PurchaseOrderLogger::freightQuoteGenerated(12345, count($quotes), 'balanced', $duration);

// 4. AI Recommendation
PurchaseOrderLogger::aiRecommendationGenerated(
    12345,
    'freight_selection',
    'cost_optimization',
    json_encode(['carrier' => 'CourierPost', 'cost' => 45.80]),
    0.92
);

// User accepts
PurchaseOrderLogger::aiRecommendationAccepted(
    12345,
    'freight_selection',
    'cost_optimization',
    'CourierPost',
    0.92,
    30 // seconds
);

// 5. Carrier Selected
PurchaseOrderLogger::carrierSelected(
    12345,
    'CourierPost',
    'Overnight',
    45.80,
    'ai_recommended'
);

// 6. Label Generated
PurchaseOrderLogger::freightLabelGenerated(
    12345,
    'CP123456789NZ',
    'PDF',
    true
);

// 7. Sent to Supplier
PurchaseOrderLogger::poSentToSupplier(
    12345,
    'email',
    'orders@vaporhub.co.nz'
);

// 8. Receiving Started
PurchaseOrderLogger::poReceivingStarted(12345, 45, 'Warehouse Dock 2');

// 9. Receiving Completed
PurchaseOrderLogger::poReceivingCompleted(12345, 43, 2, 28.5);

// 10. Transfer Review Generated (automatic)
// TransferReviewService logs internally
```

### Security Event Detection

```php
<?php
// Client-side: SecurityMonitor.js detects rapid keyboard
// Sends event via InteractionLogger
// Server-side: log-interaction.php receives event

// In log-interaction.php:
case 'rapid_keyboard':
    PurchaseOrderLogger::securityRapidKeyboardEntry(
        $eventData['po_id'],
        $eventData['field'] ?? 'unknown',
        $eventData['keys_per_second'] ?? 0
    );
    break;

case 'devtools_detected':
    PurchaseOrderLogger::securityDevToolsDetected(
        $eventData['po_id'],
        $eventData['page'] ?? 'unknown',
        $eventData['detection_count'] ?? 1
    );
    break;

case 'focus_loss':
    PurchaseOrderLogger::securityTabSwitchDuringOperation(
        $eventData['po_id'],
        $eventData['operation'] ?? 'unknown',
        $eventData['switch_count'] ?? 1
    );
    break;
```

### AI Insights Dashboard Integration

```php
<?php
// In ai.js modal opened handler:
InteractionLogger.track({
    event_type: 'modal_opened',
    event_data: {
        modal_type: 'ai_insights',
        po_id: poId
    },
    page: 'view'
});

// User clicks "Accept All"
fetch('/api/purchase-orders/bulk-accept-ai-insights.php', {
    method: 'POST',
    body: JSON.stringify({
        insight_ids: selectedIds,
        po_id: poId
    })
});

// Server logs:
PurchaseOrderLogger::aiBulkAccept($poId, count($selectedIds));
```

---

## Summary

PurchaseOrderLogger provides **40+ semantic methods** organized by domain:

- **8 methods** for PO lifecycle operations
- **3 methods** for freight operations
- **5 methods** for AI recommendations
- **6 methods** for security/fraud detection
- **4 methods** for UI interactions
- **3 methods** for performance tracking
- **2 methods** for error handling
- **+ more** specialized methods

### Key Principles

1. **Fail-Safe**: Never break application flow
2. **Semantic**: Clear, domain-specific method names
3. **Contextual**: Rich metadata for AI analysis
4. **Privacy-Safe**: No PII, aggregate patterns only
5. **Performance-Aware**: Minimal overhead, async-capable
6. **Consistent**: Standardized logging patterns across module

### Database Tables

- `cis_action_log` - General actions, UI events, errors
- `cis_ai_context` - AI decisions, recommendations, learning data
- `cis_security_log` - Security events, fraud detection, anomalies
- `cis_performance_metrics` - Timing, performance, optimization data
- `cis_bot_pipeline_log` - Automation, scheduled tasks, background jobs

---

**Questions?** See CLIENT_INSTRUMENTATION.md for client-side integration examples.

**Last Updated:** October 31, 2025
**Version:** 1.0.0
**Maintainer:** CIS Development Team
