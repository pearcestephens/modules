# CIS Base Model - Quick Reference Card

**One-Page Cheat Sheet for Developers**

---

## 🚀 Getting Started (30 Seconds)

```php
<?php
// Step 1: Just require the bootstrap (that's it!)
require_once __DIR__ . '/base/bootstrap.php';

// Step 2: Use namespace imports (optional, for convenience)
use CIS\Base\Database;
use CIS\Base\Session;
use CIS\Base\Logger;
use CIS\Base\Response;

// Step 3: Start coding! Everything is auto-initialized.
```

---

## 💾 Database (Choose Your Style)

### Option 1: PDO (Modern - Recommended for New Code)
```php
$pdo = Database::pdo();

// Prepared statement (safe from SQL injection)
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([123]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// Helper methods
$result = Database::query("SELECT * FROM products WHERE active = 1");
$rows = Database::fetchAll("SELECT * FROM categories");
$single = Database::fetchOne("SELECT * FROM settings WHERE key = ?", ['site_name']);
$lastId = Database::lastInsertId();

// Transactions
Database::beginTransaction();
try {
    // Multiple queries...
    Database::commit();
} catch (Exception $e) {
    Database::rollback();
    throw $e;
}
```

### Option 2: MySQLi (Legacy - 100% Backward Compatible)
```php
// Global variable (original CIS style)
global $con;
$result = mysqli_query($con, "SELECT * FROM users");
$row = mysqli_fetch_assoc($result);

// Or via Database class
$mysqli = Database::mysqli();
$stmt = $mysqli->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
```

**Both PDO and MySQLi use the SAME database connection!**

---

## 🔐 Session (Shared Across All CIS)

```php
// Get/Set values
Session::set('last_page', '/dashboard');
$lastPage = Session::get('last_page');
$theme = Session::get('theme', 'light'); // With default

// Check existence
if (Session::has('user_preferences')) {
    // ...
}

// Remove value
Session::remove('temporary_data');

// User helpers
$userId = Session::getUserId();        // Returns int or null
$userName = Session::getUserName();    // Returns "First Last" or null
$isLoggedIn = Session::isLoggedIn();   // Returns bool

// Flash messages (one-time messages)
Session::flash('success', 'Data saved!');
$message = Session::flash('success');  // Gets and removes

// Security
Session::regenerate(); // Call after login/privilege escalation
Session::destroy();    // Complete logout
```

---

## 📝 Logging (Automatic & Easy)

```php
// Basic logging
Logger::info('User viewed dashboard');
Logger::warning('API rate limit approaching');
Logger::error('Payment processing failed');
Logger::debug('Debug info', ['variable' => $value]);

// With context
Logger::info('User updated profile', [
    'user_id' => 123,
    'fields_changed' => ['email', 'phone']
]);

// Specialized logs
Logger::security('Failed login attempt', [
    'ip' => $_SERVER['REMOTE_ADDR'],
    'username' => $username
]);

Logger::performance('Slow query detected', [
    'query' => $sql,
    'duration' => 2.5
]);

Logger::ai('AI analysis completed', [
    'model' => 'GPT-4',
    'tokens' => 1500
]);
```

**Logs automatically include:**
- Timestamp
- User ID (if logged in)
- Request context
- IP address
- User agent

---

## 📤 Responses (API & HTML)

```php
// JSON responses (for APIs)
Response::json([
    'user' => $userData,
    'settings' => $settings
]);

// Success response (with data)
Response::success($data, 'Operation completed successfully');

// Error response (with message)
Response::error('Invalid input', 400, ['field' => 'email']);

// Redirects
Response::redirect('/dashboard');
Response::redirectBack();

// HTTP status codes
Response::notFound();       // 404
Response::unauthorized();   // 401
Response::forbidden();      // 403
Response::serverError();    // 500
```

---

## ✅ Validation (Input Safety)

```php
$validator = new Validator($_POST);

// Required fields
$validator->required(['name', 'email', 'password']);

// Email validation
$validator->email('email');

// Length validation
$validator->length('password', 8, 100);
$validator->length('username', 3, 50);

// Range validation
$validator->range('age', 18, 120);

// Custom rules
$validator->custom('username', function($value) {
    return preg_match('/^[a-zA-Z0-9_]+$/', $value);
}, 'Username can only contain letters, numbers, and underscores');

// Check if valid
if ($validator->isValid()) {
    // Process data...
} else {
    Response::error('Validation failed', 422, $validator->getErrors());
}
```

---

## 🛡️ Error Handling (Automatic)

**Errors are handled automatically!**

- **For humans:** Beautiful red 500 error page with helpful message
- **For APIs:** Clean JSON error response
- **All errors:** Automatically logged to database

### Manual Error Handling:
```php
try {
    // Your code...
} catch (Exception $e) {
    Logger::error('Operation failed', [
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
    
    Response::error('Something went wrong', 500);
}
```

---

## 🧭 Router (Simple Pattern Matching)

```php
use CIS\Base\Router;

Router::get('/users/:id', function($params) {
    $userId = $params['id'];
    // Load and display user...
});

Router::post('/api/save', function($params) {
    // Process POST data...
    Response::success(['saved' => true]);
});

Router::dispatch(); // Call at end of file
```

---

## 🎯 Common Patterns

### 1. Load User Data
```php
$userId = Session::getUserId();
if ($userId) {
    $user = Database::fetchOne(
        "SELECT * FROM users WHERE id = ?",
        [$userId]
    );
}
```

### 2. Save Form Data
```php
$validator = new Validator($_POST);
$validator->required(['name', 'email']);

if (!$validator->isValid()) {
    Response::error('Invalid input', 422, $validator->getErrors());
}

$pdo = Database::pdo();
$stmt = $pdo->prepare("INSERT INTO contacts (name, email) VALUES (?, ?)");
$stmt->execute([$_POST['name'], $_POST['email']]);

Logger::info('Contact created', ['id' => Database::lastInsertId()]);
Response::success(['id' => Database::lastInsertId()]);
```

### 3. API Endpoint
```php
require_once __DIR__ . '/base/bootstrap.php';

use CIS\Base\Database;
use CIS\Base\Response;

try {
    $products = Database::fetchAll("SELECT * FROM products WHERE active = 1");
    Response::success($products);
} catch (Exception $e) {
    // Automatically logged and handled!
    Response::error('Failed to load products', 500);
}
```

### 4. Protected Page
```php
require_once __DIR__ . '/base/bootstrap.php';

use CIS\Base\Session;
use CIS\Base\Response;

if (!Session::isLoggedIn()) {
    Response::redirect('/login.php');
}

// Page content...
```

---

## 📚 File Structure

```
your-module/
├── index.php              # Just require base/bootstrap.php
├── api/
│   └── endpoint.php       # API endpoints
├── views/
│   └── page.php          # HTML pages
└── lib/
    └── helpers.php       # Module-specific helpers
```

**Every file starts with:**
```php
<?php
require_once __DIR__ . '/../base/bootstrap.php';
use CIS\Base\Database;
use CIS\Base\Session;
// ... rest of your code
```

---

## 🆘 Troubleshooting

### Database Issues?
```php
// Test PDO connection
var_dump(Database::pdo()); // Should be PDO object

// Test MySQLi connection
var_dump(Database::mysqli()); // Should be mysqli object
var_dump($con); // Should be mysqli object
```

### Session Issues?
```php
// Check if logged in
var_dump(Session::isLoggedIn()); // Should be bool

// Check user ID
var_dump(Session::getUserId()); // Should be int or null

// Check session status
var_dump(session_status()); // Should be PHP_SESSION_ACTIVE (2)
```

### Logger Issues?
```php
// Test logging
Logger::info('Test log entry', ['test' => true]);

// Check database
// SELECT * FROM logs_action ORDER BY created_at DESC LIMIT 10;
```

### Run Test Suite:
Visit: `https://staff.vapeshed.co.nz/base/test-base.php`

---

## 🎓 Learning Path

1. **Read:** `BASE_MODEL_QUICK_START.md` (5 minutes)
2. **Test:** Visit `/base/test-base.php` (2 minutes)
3. **Code:** Start building with examples above
4. **Reference:** Keep this card handy!

---

## 💡 Pro Tips

1. **Always use prepared statements** (PDO or MySQLi) - never concatenate SQL
2. **Log important actions** - helps with debugging and auditing
3. **Validate all input** - never trust user data
4. **Use flash messages** for one-time notifications
5. **Check Session::isLoggedIn()** before accessing protected pages
6. **Use Response::json()** for all API endpoints
7. **Let errors be automatic** - they're logged and displayed beautifully

---

## 🔗 Full Documentation

- **Quick Start:** `BASE_MODEL_QUICK_START.md`
- **Complete Spec:** `BASE_MODEL_INTEGRATION_SPEC.md`
- **Status:** `IMPLEMENTATION_STATUS.md`
- **Test Suite:** `/base/test-base.php`

---

**Need help? Check the full documentation or run the test suite!**

**Remember:** Just `require 'base/bootstrap.php'` and you're ready to go! 🚀
