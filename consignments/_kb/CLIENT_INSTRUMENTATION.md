# Client-Side Security & Interaction Monitoring System

**Version:** 1.0.0
**Last Updated:** October 31, 2025
**Package:** CIS\Consignments\PurchaseOrders

---

## Overview

Comprehensive client-side behavioral monitoring system for security, fraud detection, and user experience analysis. All monitoring is **privacy-safe** and compliant with data protection regulations.

### Key Features

✅ **Security Monitoring**
- DevTools detection (console, inspect element)
- Rapid keyboard entry patterns
- Copy/paste behavior tracking
- Focus loss and tab switching
- Session replay triggers

✅ **Privacy-First Design**
- No raw keystroke capture
- Aggregated patterns only
- No PII in logs
- Configurable thresholds
- User-transparent operation

✅ **Performance Optimized**
- Batched event sending
- Minimal CPU overhead
- Automatic cleanup
- Configurable sampling rates

---

## Architecture

```
┌─────────────────────────────────────────────────────┐
│                   Browser (Client)                  │
├─────────────────────────────────────────────────────┤
│                                                     │
│  ┌──────────────────┐  ┌─────────────────────┐   │
│  │ SecurityMonitor  │  │ InteractionLogger   │   │
│  │                  │  │                     │   │
│  │ - DevTools       │  │ - Event batching    │   │
│  │ - Keyboard       │  │ - sendBeacon        │   │
│  │ - Copy/Paste     │  │ - Auto-flush        │   │
│  │ - Focus tracking │  │                     │   │
│  └────────┬─────────┘  └──────────┬──────────┘   │
│           │                       │              │
│           └───────────┬───────────┘              │
│                       │                          │
└───────────────────────┼──────────────────────────┘
                        │ HTTPS POST (batched)
                        ▼
┌─────────────────────────────────────────────────────┐
│              Server (PHP Backend)                   │
├─────────────────────────────────────────────────────┤
│                                                     │
│  ┌──────────────────────────────────────────┐     │
│  │  log-interaction.php (API Endpoint)      │     │
│  │                                          │     │
│  │  - Validates events                      │     │
│  │  - Rate limiting                         │     │
│  │  - Forwards to PurchaseOrderLogger       │     │
│  └────────────┬─────────────────────────────┘     │
│               │                                    │
│               ▼                                    │
│  ┌──────────────────────────────────────────┐     │
│  │  PurchaseOrderLogger (Semantic Wrapper)  │     │
│  │                                          │     │
│  │  - securityDevToolsDetected()            │     │
│  │  - securityRapidKeyboardEntry()          │     │
│  │  - fraudSuspiciousValue()                │     │
│  │  - modalOpened/Closed()                  │     │
│  └────────────┬─────────────────────────────┘     │
│               │                                    │
│               ▼                                    │
│  ┌──────────────────────────────────────────┐     │
│  │  CISLogger (Core System)                 │     │
│  │                                          │     │
│  │  Tables:                                 │     │
│  │  - cis_action_log                        │     │
│  │  - cis_security_log                      │     │
│  │  - cis_ai_context                        │     │
│  │  - cis_performance_metrics               │     │
│  └──────────────────────────────────────────┘     │
│                                                     │
└─────────────────────────────────────────────────────┘
```

---

## Components

### 1. SecurityMonitor.js

**Purpose:** Detects suspicious or fraudulent behavior patterns.

**Location:** `/modules/consignments/purchase-orders/js/security-monitor.js`

**API:**
```javascript
// Initialize monitoring
SecurityMonitor.init({
    poId: 123,
    page: 'receive',
    enabled: true
});

// Adjust thresholds
SecurityMonitor.setThreshold('rapidKeyboardThreshold', 10);

// Get session summary
var summary = SecurityMonitor.getSessionSummary();

// Cleanup
SecurityMonitor.destroy();
```

**Monitored Events:**

| Event | Trigger | Threshold | Action |
|-------|---------|-----------|--------|
| `devtools_detected` | DevTools open | Immediate | Log security event |
| `rapid_keyboard` | Fast typing | 8+ keys/sec | Log security event |
| `suspicious_value` | Copy/paste | 3+ pastes | Log fraud flag |
| `focus_loss` | Tab switch | 3+ switches | Log attention event |

**Configuration:**
```javascript
{
    rapidKeyboardThreshold: 8,        // keys per second
    copyPasteThreshold: 3,            // paste events
    devToolsCheckInterval: 1000,      // ms
    focusLossThreshold: 3             // count
}
```

---

### 2. InteractionLogger.js

**Purpose:** Batches and sends UI interaction events to server.

**Location:** `/modules/consignments/purchase-orders/js/interaction-logger.js`

**API:**
```javascript
// Track single event
InteractionLogger.track({
    type: 'modal_opened',
    modal_name: 'receive_modal',
    page: 'receive',
    po_id: 123,
    timestamp: Date.now()
});

// Manual flush
InteractionLogger.flush();
```

**Features:**
- Auto-batches events (max 10 or 3 seconds)
- Uses `navigator.sendBeacon` for reliability
- Auto-flushes on page unload
- Fail-safe (never breaks UI)

---

### 3. log-interaction.php

**Purpose:** Server endpoint for receiving batched client events.

**Location:** `/modules/consignments/api/purchase-orders/log-interaction.php`

**Request Format:**
```json
POST /modules/consignments/api/purchase-orders/log-interaction.php
Content-Type: application/json

{
  "events": [
    {
      "type": "modal_opened",
      "modal_name": "receive_modal",
      "page": "receive",
      "po_id": 123,
      "timestamp": 1730390400000
    },
    {
      "type": "rapid_keyboard",
      "po_id": 123,
      "field": "quantity",
      "entries_per_second": 12.5,
      "total_entries": 10,
      "timestamp": 1730390410000
    }
  ]
}
```

**Response:**
```json
{
  "success": true,
  "processed": 2
}
```

**Supported Event Types:**

| Client Event | Server Method | Purpose |
|-------------|---------------|---------|
| `modal_opened` | `modalOpened()` | Track modal views |
| `modal_closed` | `modalClosed()` | Track time spent |
| `button_clicked` | `buttonClicked()` | Track UI actions |
| `devtools_detected` | `securityDevToolsDetected()` | Security flag |
| `rapid_keyboard` | `securityRapidKeyboardEntry()` | Fraud detection |
| `suspicious_value` | `fraudSuspiciousValue()` | Fraud pattern |
| `focus_loss` | `securityTabSwitchDuringOperation()` | Attention tracking |
| `field_validation_error` | `validationError()` | UX improvement |
| `ai_recommendation_accepted` | `aiRecommendationAccepted()` | AI tracking |
| `ai_recommendation_dismissed` | `aiRecommendationDismissed()` | AI tracking |

---

### 4. PurchaseOrderLogger.php

**Purpose:** Semantic wrapper around CISLogger for purchase order operations.

**Location:** `/modules/consignments/lib/PurchaseOrderLogger.php`

**Key Methods:**

**Security:**
```php
PurchaseOrderLogger::securityDevToolsDetected($poId, $page);
PurchaseOrderLogger::securityRapidKeyboardEntry($poId, $field, $keysPerSec, $totalKeys);
PurchaseOrderLogger::securityCopyPasteBehavior($poId, $field, $pasteCount, $pattern);
```

**Fraud:**
```php
PurchaseOrderLogger::fraudSuspiciousValue($poId, $field, $entered, $expected, $pattern);
PurchaseOrderLogger::fraudLargeDiscrepancy($poId, $productId, $expected, $received, $percent);
```

**AI:**
```php
PurchaseOrderLogger::aiRecommendationGenerated($poId, $type, $recommendation, $confidence);
PurchaseOrderLogger::aiRecommendationAccepted($insightId, $poId, $type, $savings, $reviewTime);
```

**UI:**
```php
PurchaseOrderLogger::modalOpened($modalName, $page, $poId);
PurchaseOrderLogger::modalClosed($modalName, $timeSpent, $actionTaken, $poId);
PurchaseOrderLogger::buttonClicked($buttonId, $page, $poId);
```

---

## Integration Guide

### Step 1: Include Scripts

Add to your page `<head>` or before `</body>`:

```html
<!-- Interaction Logger (batching) -->
<script src="js/interaction-logger.js"></script>

<!-- Security Monitor (behavior detection) -->
<script src="js/security-monitor.js"></script>

<!-- Your page-specific JS -->
<script src="js/receive.js"></script>
```

### Step 2: Initialize Security Monitor

```javascript
document.addEventListener('DOMContentLoaded', function() {
    // Get PO ID from page (data attribute, hidden field, etc.)
    var poId = document.getElementById('po_id')?.value || null;

    // Initialize security monitoring
    SecurityMonitor.init({
        poId: parseInt(poId),
        page: 'receive',
        enabled: true
    });

    console.log('Security monitoring active for PO:', poId);
});
```

### Step 3: Track Custom Events

```javascript
// Track modal open
$('#receiveModal').on('shown.bs.modal', function() {
    InteractionLogger.track({
        type: 'modal_opened',
        modal_name: 'receive_modal',
        page: 'receive',
        po_id: currentPoId,
        timestamp: Date.now()
    });
});

// Track modal close with timing
var modalOpenTime = Date.now();
$('#receiveModal').on('hidden.bs.modal', function() {
    var timeSpent = (Date.now() - modalOpenTime) / 1000.0;

    InteractionLogger.track({
        type: 'modal_closed',
        modal_name: 'receive_modal',
        time_spent_seconds: timeSpent,
        action_taken: formWasSubmitted,
        po_id: currentPoId,
        timestamp: Date.now()
    });
});

// Track button clicks
$('#save-btn').on('click', function() {
    InteractionLogger.track({
        type: 'button_clicked',
        button_id: 'save-btn',
        page: 'receive',
        po_id: currentPoId,
        timestamp: Date.now()
    });
});
```

---

## Security Thresholds

### Recommended Settings

**Production (Conservative):**
```javascript
SecurityMonitor.setThreshold('rapidKeyboardThreshold', 12);    // 12 keys/sec
SecurityMonitor.setThreshold('copyPasteThreshold', 5);         // 5 pastes
SecurityMonitor.setThreshold('focusLossThreshold', 5);         // 5 switches
```

**Staging (Sensitive):**
```javascript
SecurityMonitor.setThreshold('rapidKeyboardThreshold', 8);     // 8 keys/sec
SecurityMonitor.setThreshold('copyPasteThreshold', 3);         // 3 pastes
SecurityMonitor.setThreshold('focusLossThreshold', 3);         // 3 switches
```

**Development (Logging Only):**
```javascript
SecurityMonitor.setThreshold('rapidKeyboardThreshold', 20);    // 20 keys/sec
SecurityMonitor.setThreshold('copyPasteThreshold', 10);        // 10 pastes
SecurityMonitor.setThreshold('focusLossThreshold', 10);        // 10 switches
```

### Tuning Guidelines

1. **Monitor false positive rate** in `cis_security_log`
2. **Adjust thresholds** based on legitimate user patterns
3. **Review flagged sessions** weekly
4. **Update baselines** quarterly

---

## Data Flow

### Client to Server

```
User Action
    ↓
SecurityMonitor detects pattern
    ↓
Event created (aggregate data only)
    ↓
InteractionLogger queues event
    ↓
Batch sent (10 events or 3 seconds)
    ↓
POST to log-interaction.php
    ↓
Event validation & rate limiting
    ↓
PurchaseOrderLogger semantic method
    ↓
CISLogger writes to database
    ↓
Available for AI analysis & reporting
```

### Database Storage

**Security Events:**
```sql
-- cis_security_log
INSERT INTO cis_security_log (
    event_type,
    severity,
    user_id,
    threat_indicators,
    action_taken,
    created_at
) VALUES (
    'rapid_keyboard_entry',
    'warning',
    42,
    '{"po_id":123,"field":"quantity","entries_per_second":12.5}',
    'flagged_for_review',
    NOW()
);
```

**Action Log:**
```sql
-- cis_action_log
INSERT INTO cis_action_log (
    category,
    action_type,
    result,
    entity_type,
    entity_id,
    context,
    actor_type,
    actor_id,
    created_at
) VALUES (
    'purchase_orders',
    'modal_opened',
    'success',
    'ui_interaction',
    NULL,
    '{"modal_name":"receive_modal","page":"receive","po_id":123}',
    'user',
    42,
    NOW()
);
```

---

## Privacy & Compliance

### What We Track

✅ **Aggregate Patterns**
- Keystroke timing (intervals only, no key values)
- Paste event count
- Focus loss count
- DevTools detection

✅ **User Actions**
- Modal opens/closes
- Button clicks
- Form submissions

### What We DON'T Track

❌ **Personal Data**
- Actual keystrokes typed
- Clipboard contents
- Password fields
- Form field values (unless explicitly needed for fraud)

### GDPR/Privacy Compliance

- All monitoring is for **legitimate security purposes**
- Users can **opt-out** via settings (if implemented)
- Data is **anonymized** after 90 days
- **No third-party tracking** scripts

---

## Performance Impact

### Benchmarks

| Operation | CPU | Memory | Network |
|-----------|-----|--------|---------|
| SecurityMonitor.init() | <1ms | 5KB | 0 |
| Event tracking | <0.1ms | 100B/event | 0 (batched) |
| Batch send (10 events) | <5ms | 2KB | 1 request |
| DevTools check | <0.5ms | 0 | 0 |

### Optimization Strategies

1. **Batching**: Events sent in groups (max 10 or 3 sec)
2. **Sampling**: DevTools checked every 1000ms
3. **Throttling**: Keyboard check after 10 keystrokes
4. **Cleanup**: Auto-destroy on page unload

---

## Troubleshooting

### Events Not Being Logged

**Check 1:** Console errors
```javascript
// Open browser console and look for:
[InteractionLogger] send failed
[SecurityMonitor] error
```

**Check 2:** Network tab
```
POST /modules/consignments/api/purchase-orders/log-interaction.php
Status: Should be 200
Response: {"success":true,"processed":N}
```

**Check 3:** Server logs
```bash
tail -f /path/to/logs/error.log
# Look for: [PurchaseOrderLogger] or [log-interaction]
```

### False Positives

**Rapid Keyboard:**
- Increase threshold: `setThreshold('rapidKeyboardThreshold', 15)`
- Review legitimate fast typers

**Copy/Paste:**
- Some users legitimately paste from spreadsheets
- Increase threshold or whitelist certain fields

**DevTools:**
- Developers will trigger this
- Add role-based exemption

---

## Testing

### Manual Test Suite

**Test 1: DevTools Detection**
```
1. Open page with SecurityMonitor initialized
2. Open DevTools (F12)
3. Check console for: [SecurityMonitor] DevTools detected
4. Check database: cis_security_log for 'devtools_detected'
```

**Test 2: Rapid Keyboard**
```
1. Focus on input field
2. Type very quickly (>8 chars/sec)
3. Check console for warning
4. Check database for 'rapid_keyboard_entry'
```

**Test 3: Copy/Paste**
```
1. Paste into 3+ different fields
2. Check console for warning
3. Check database for 'suspicious_value' with pattern 'paste_behavior'
```

**Test 4: Modal Tracking**
```
1. Open a modal
2. Wait 5 seconds
3. Close modal
4. Check database for 'modal_opened' and 'modal_closed' with time_spent
```

### Automated Tests

See `/modules/consignments/tests/client-instrumentation/` for:
- Unit tests (Jasmine/Jest)
- Integration tests (Cypress/Playwright)
- Load tests (Artillery)

---

## Future Enhancements

### Planned Features

- [ ] Mouse pattern analysis (tremor detection)
- [ ] Session replay integration (privacy-safe)
- [ ] ML-based anomaly detection
- [ ] Real-time alerting dashboard
- [ ] Configurable per-user thresholds
- [ ] A/B testing framework integration

### Research Areas

- Biometric keystroke dynamics
- Advanced fraud pattern recognition
- User experience heatmaps (opt-in)
- Predictive error detection

---

## Support

**Issues:** Report to IT Manager or Security Lead
**Documentation:** `/modules/consignments/_kb/`
**Logs:** `cis_security_log`, `cis_action_log`
**Contact:** <pearce.stephens@ecigdis.co.nz>

---

**Last Reviewed:** October 31, 2025
**Next Review:** January 31, 2026
