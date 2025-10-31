# 🏗️ CIS Base Module Foundation

**Version:** 1.0.0  
**Status:** PRODUCTION READY ✅  
**Last Updated:** October 27, 2025  

---

## 🎯 What Is This?

The **base/** directory is the **foundation** for ALL CIS modules. It provides:

✅ **Error Handling** - Beautiful 500 pages, JSON errors, comprehensive logging  
✅ **Logging** - Integrated with CISLogger (action, AI, security, performance)  
✅ **Security** - CSRF tokens, session management  
✅ **Database** - Simple singleton wrapper  
✅ **Responses** - Standard JSON/HTML helpers  
✅ **Validation** - Input validation & sanitization  

**One line to load everything:**
```php
require_once $_SERVER['DOCUMENT_ROOT'] . '/base/bootstrap.php';
```

That's it! Everything is initialized and ready.

---

## 📁 File Structure

```
base/
├── bootstrap.php           # ⭐ Load this in your module - that's all!
├── ErrorHandler.php        # Catches all errors, shows beautiful 500 pages
├── Logger.php              # Facade for CISLogger (success, failure, AI, security)
├── Database.php            # Simple DB wrapper
├── Response.php            # JSON/HTML response helpers
├── Session.php             # CSRF + secure session
├── Router.php              # Route helpers
├── Validator.php           # Input validation
├── _templates/
│   ├── error-pages/
│   │   └── 500.php         # Beautiful red error page with copy button
│   ├── layouts/            # (Future: header, footer, sidebar)
│   └── components/         # (Future: buttons, cards, tables)
├── _assets/
│   ├── css/                # (Future: CSS variables, components)
│   └── js/                 # (Future: error handler, CSRF, utilities)
└── _docs/
    └── README.md           # ← You are here
```

---

## 🚀 Quick Start (< 5 Minutes)

### Step 1: Create Your Module

```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules
mkdir my-new-module
cd my-new-module
```

### Step 2: Create `index.php`

```php
<?php
/**
 * My New Module
 */

// 1. Load base (this is ALL you need!)
require_once $_SERVER['DOCUMENT_ROOT'] . '/base/bootstrap.php';

// 2. Your module code starts here
use CIS\Base\Logger;
use CIS\Base\Response;

// Log page view
Logger::success(
    category: 'my-new-module',
    action: 'page_view',
    entityType: 'page',
    entityId: 'index'
);

// Your HTML
?>
<!DOCTYPE html>
<html>
<head>
    <title>My New Module</title>
</head>
<body>
    <h1>Hello from My New Module!</h1>
    <p>Error handling, logging, and security are already active.</p>
</body>
</html>
```

### Step 3: Test It!

```
Visit: https://staff.vapeshed.co.nz/modules/my-new-module/
```

**Done! Your module is live with:**
- ✅ Error handling (try: `throw new Exception('test');`)
- ✅ Logging (check `cis_action_log` table)
- ✅ CSRF protection (use `CIS\Base\Session::csrfField()` in forms)
- ✅ Standard responses (use `CIS\Base\Response::success()`)

---

## 📚 Core Classes Reference

### ErrorHandler

**Automatically initialized by bootstrap.php**

Catches all errors and exceptions. Shows:
- Beautiful red 500 page (HTML requests)
- JSON error envelope (AJAX/API requests)
- Logs to CISLogger automatically

**You don't need to do anything - it just works!**

To test: `throw new Exception('Test error');`

---

### Logger

**Usage:**
```php
use CIS\Base\Logger;

// Log success
Logger::success(
    category: 'transfers',
    action: 'create_transfer',
    entityType: 'transfer',
    entityId: '12345',
    context: ['items' => 50, 'outlet' => 'Auckland']
);

// Log failure
Logger::failure(
    category: 'transfers',
    action: 'validate_items',
    reason: 'Insufficient stock',
    context: ['product_id' => '789']
);

// Log AI decision
Logger::ai(
    contextType: 'suggestion',
    sourceSystem: 'transfer_optimizer',
    prompt: 'Which outlet should receive this transfer?',
    response: 'Auckland - highest demand',
    reasoning: 'Sales velocity 2.3x higher than Wellington'
);

// Log security event
Logger::security(
    eventType: 'suspicious_activity',
    severity: 'medium',
    userId: '42',
    threatData: ['multiple_failed_csrf' => 5]
);

// Log performance
Logger::performance(
    metricName: 'page_load',
    source: 'transfers_dashboard',
    value: 234.5,
    unit: 'ms'
);
```

**All logged to:** `cis_action_log`, `cis_ai_context`, `cis_security_events`, `cis_performance_metrics`

---

### Response

**Usage:**
```php
use CIS\Base\Response;

// JSON success
Response::success(
    data: ['id' => 123, 'status' => 'created'],
    message: 'Transfer created successfully'
);

// JSON error
Response::error(
    message: 'Product not found',
    code: 'PRODUCT_NOT_FOUND',
    httpCode: 404
);

// Redirect
Response::redirect('/modules/transfers/view.php?id=123');
```

---

### Session (CSRF)

**Usage:**
```php
use CIS\Base\Session;

// In your form HTML:
<form method="POST">
    <?= Session::csrfField() ?>
    <!-- Your form fields -->
</form>

// Validate on POST:
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Session::validateCsrf($_POST['csrf_token'] ?? null)) {
        Response::error('Invalid CSRF token', 'CSRF_ERROR', 403);
    }
    
    // Process form...
}
```

**CSRF is automatically initialized - you just use it!**

---

### Database

**Usage:**
```php
use CIS\Base\Database;

// Get connection
$db = Database::getInstance();

// Safe query (uses sql_query_select_safe from app.php)
$result = Database::query(
    "SELECT * FROM products WHERE id = ?",
    [$productId]
);

// Safe insert/update (uses sql_query_update_or_insert_safe)
$insertId = Database::execute(
    "INSERT INTO logs (action, timestamp) VALUES (?, NOW())",
    ['user_login']
);
```

---

### Router

**Usage:**
```php
use CIS\Base\Router;

// Get current route
$route = Router::getRoute(); // From ?endpoint=xxx or ?route=xxx

// Check method
if (Router::isPost()) {
    // Handle POST
}

if (Router::isGet()) {
    // Handle GET
}
```

---

### Validator

**Usage:**
```php
use CIS\Base\Validator;

// Require field
Validator::required($_POST['name'] ?? null, 'name');

// Validate email
if (!Validator::email($email)) {
    Response::error('Invalid email');
}

// Get integer
$id = Validator::int($_GET['id'] ?? null);

// Sanitize string
$clean = Validator::sanitize($_POST['comment']);
```

---

## 🎨 Error Pages

### 500 Internal Server Error

**Automatically shown when:**
- Uncaught exception
- Fatal PHP error
- Database error

**Features:**
- ✅ Beautiful red gradient theme
- ✅ Pulsing icon animation
- ✅ Copy debug info button
- ✅ Error ID + timestamp
- ✅ Full stack trace (dev mode only)
- ✅ JSON export of all debug data

**Location:** `base/_templates/error-pages/500.php`

**Preview:**
```
┌─────────────────────────────────────┐
│             💥 (pulsing)             │
│                500                   │
│      Internal Server Error          │
│                                      │
│  [Error message in red box]         │
│                                      │
│  Error ID: err_abc123                │
│  2025-10-27 14:23:45                 │
│                                      │
│  [📋 Copy Debug Info] [🏠 Home]     │
└─────────────────────────────────────┘
```

---

## 🔐 Security Features

### ✅ Currently Active:

1. **CSRF Protection** - `Session::csrfField()` and `Session::validateCsrf()`
2. **Secure Sessions** - Automatically configured
3. **Input Sanitization** - `Validator::sanitize()`
4. **Error Logging** - All errors logged to CISLogger
5. **SQL Injection Prevention** - Database class uses prepared statements

### 🔜 Future Enhancements (Not Yet Implemented):

1. **Rate Limiting** - Prevent brute force attacks
2. **Mouse/Keyboard Tracking** - Security monitoring
3. **Screenshot Capture** - On suspicious activity
4. **Anomaly Detection** - ML-based threat detection

---

## 📊 Logging Tables

All logging goes through CISLogger to these tables:

### `cis_action_log`
- User actions (success, failure, partial)
- Entity tracking (product, transfer, order, etc.)
- Full context (JSON)
- Performance metrics (execution time, memory)
- Request metadata (IP, user agent, URL)

### `cis_ai_context`
- AI decisions and reasoning
- Prompts and responses
- Confidence scores
- Input/output data
- Training context

### `cis_security_events`
- Security incidents
- Threat detection
- User behavior patterns
- Suspicious activity

### `cis_performance_metrics`
- Page load times
- Query performance
- Memory usage
- Custom metrics

---

## 🚫 Common Mistakes to Avoid

### ❌ DON'T: Load app.php manually

```php
// ❌ WRONG
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/base/bootstrap.php';
```

```php
// ✅ CORRECT (bootstrap loads app.php for you)
require_once $_SERVER['DOCUMENT_ROOT'] . '/base/bootstrap.php';
```

---

### ❌ DON'T: Use global $con directly

```php
// ❌ WRONG
global $con;
$result = mysqli_query($con, $sql);
```

```php
// ✅ CORRECT
use CIS\Base\Database;
$result = Database::query($sql, $params);
```

---

### ❌ DON'T: Forget CSRF in forms

```php
// ❌ WRONG
<form method="POST">
    <input name="action" value="delete">
</form>
```

```php
// ✅ CORRECT
use CIS\Base\Session;

<form method="POST">
    <?= Session::csrfField() ?>
    <input name="action" value="delete">
</form>
```

---

### ❌ DON'T: Use echo for JSON responses

```php
// ❌ WRONG
echo json_encode(['success' => true]);
```

```php
// ✅ CORRECT
use CIS\Base\Response;
Response::success(data: ['result' => 'ok']);
```

---

## 📝 Migration Guide (Existing Modules)

### Old Way (shared/bootstrap.php):
```php
require_once $_SERVER['DOCUMENT_ROOT'] . '/shared/bootstrap.php';
```

### New Way (base/bootstrap.php):
```php
require_once $_SERVER['DOCUMENT_ROOT'] . '/base/bootstrap.php';
```

**That's literally it! One line change.**

Then optionally:
1. Replace manual logging with `CIS\Base\Logger`
2. Add `Session::csrfField()` to forms
3. Replace `echo json_encode()` with `Response::success()`

---

## 🎉 Benefits

### Before (shared/):
- ❌ 207-line bootstrap
- ❌ CLI detection
- ❌ .env parsing
- ❌ Complex fallbacks
- ❌ Unclear error handling
- ❌ Manual logging everywhere

### After (base/):
- ✅ 30-line bootstrap
- ✅ Simple & clear
- ✅ Beautiful error pages
- ✅ Integrated logging
- ✅ Security by default
- ✅ Standard patterns

---

## 📞 Support

**Questions?**
- Read `BASE_MODEL_INTEGRATION_SPEC.md` - Complete specification
- Check `cis_action_log` table - See what's being logged
- Test error handling - `throw new Exception('test');`

**Issues?**
- Check logs: `logs/apache_*.error.log`
- Verify bootstrap loaded: Look for `[CIS\Base]` in logs
- Test CSRF: Submit form without token (should fail)

---

## ✅ Checklist for New Modules

- [ ] Load `base/bootstrap.php` (one line!)
- [ ] Log actions with `Logger::success()`/`Logger::failure()`
- [ ] Add `Session::csrfField()` to all forms
- [ ] Validate CSRF on POST: `Session::validateCsrf()`
- [ ] Use `Response::success()`/`Response::error()` for JSON
- [ ] Use `Database::query()` for safe SQL
- [ ] Sanitize input with `Validator::sanitize()`
- [ ] Test error page: `throw new Exception('test');`

---

**That's all you need to know! The base model handles everything else automatically.**

---

**Version:** 1.0.0  
**Last Updated:** October 27, 2025  
**Status:** PRODUCTION READY ✅  
**Maintainer:** CIS Development Team
