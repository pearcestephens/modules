# 🏗️ CIS ARCHITECTURE STANDARDS - Option B (Custom Framework)
**Effective Date:** November 6, 2025
**Status:** ENFORCED via pre-commit hooks
**Framework:** base/ (Custom) + Traditional Modules

---

## 🎯 ARCHITECTURAL DECISION: OPTION B CHOSEN

**YOU CHOSE OPTION B:** Custom framework (`base/`) with traditional modules.

**What This Means:**
- ✅ **base/** directory is the ONLY framework (no Laravel app/)
- ✅ All 38 modules use `base/` services via `getInstance()` pattern
- ✅ Namespace convention: `Base\` for framework, `Modules\ModuleName\` for modules
- ❌ **app/** directory is BANNED (conflicts with Option B)
- ❌ **resources/** directory not needed (modules have own views/)
- ❌ Laravel-style patterns forbidden (ServiceProviders, Facades, etc.)

---

## 📁 DIRECTORY STRUCTURE (MANDATORY)

### Root Level
```
/home/129337.cloudwaysapps.com/jcepnzzkmj/
├── .env                          ← MUST BE HERE (outside public_html)
├── private_html/                 ← Non-web-accessible files
├── public_html/
│   └── modules/                  ← All application code
│       ├── base/                 ← 🏗️ CUSTOM FRAMEWORK (singleton services)
│       ├── module_name_1/        ← Individual modules
│       ├── module_name_2/
│       └── ...
```

### base/ Framework Structure (Option B Core)
```
modules/base/
├── bootstrap.php                 ← Loads .env, autoloader, registers services
├── Database.php                  ← Singleton DB connection
├── Logger.php                    ← Singleton logger
├── ErrorHandler.php              ← Global error/exception handler
├── AIService.php                 ← Singleton AI service
├── Validator.php                 ← Input validation (TO BE ADDED)
├── Auth.php                      ← Authentication (TO BE ADDED)
├── CSRF.php                      ← CSRF protection (TO BE ADDED)
├── _docs/                        ← Framework documentation
│   ├── API_REFERENCE.md
│   ├── MODULE_INTEGRATION_GUIDE.md
│   └── SECURITY.md
├── src/                          ← Framework source (PSR-4 Base\ namespace)
│   └── Core/
│       └── Database.php          ← PSR-4 version of Database
└── tests/                        ← Framework unit tests
```

### Individual Module Structure (Traditional Pattern)
```
modules/module_name/
├── bootstrap.php                 ← Loads base framework
├── controllers/                  ← Business logic
│   └── ModuleController.php
├── models/                       ← Data models
│   └── ModuleModel.php
├── views/                        ← Templates (NOT resources/views!)
│   ├── index.php
│   └── edit.php
├── api/                          ← API endpoints
│   └── endpoint.php
├── lib/                          ← Module-specific utilities
│   └── ModuleHelper.php
├── config/                       ← Module configuration
│   └── module.config.php
├── database/                     ← Module migrations
│   └── migrations/
│       └── 001_create_table.php
├── tests/                        ← Module tests
│   └── ModuleTest.php
└── README.md                     ← Module documentation
```

---

## 🔐 NAMESPACE CONVENTION (ENFORCED)

### Framework Code (base/)
```php
namespace Base;                           // Root framework namespace
namespace Base\Core;                      // Core services
namespace Base\Services;                  // Additional services
namespace Base\Exceptions;                // Framework exceptions

// Example:
namespace Base\Core;
class Database { ... }
```

### Module Code
```php
namespace Modules\ModuleName;             // Module root
namespace Modules\ModuleName\Controllers; // Controllers
namespace Modules\ModuleName\Models;      // Models
namespace Modules\ModuleName\Services;    // Module services

// Example:
namespace Modules\Consignments\Controllers;
class ConsignmentController { ... }
```

### ❌ FORBIDDEN NAMESPACES
```php
namespace App\...;                 // ❌ BLOCKED (Laravel pattern - Option A only)
namespace IntelligenceHub\...;     // ❌ BLOCKED (wrong application)
namespace CIS\...;                 // ⚠️ Legacy, migrate to Modules\
```

---

## 🚀 MODULE BOOTSTRAP PATTERN (MANDATORY)

Every module MUST have a `bootstrap.php` that follows this pattern:

### Template: modules/YOUR_MODULE/bootstrap.php
```php
<?php
/**
 * Module Bootstrap: YOUR_MODULE
 * Loads base framework and module-specific configuration
 */

// 1. Load base framework (adjust path if needed)
$baseBootstrap = __DIR__ . '/../base/bootstrap.php';
if (!file_exists($baseBootstrap)) {
    die('❌ ERROR: base/bootstrap.php not found. Option B requires base/ framework.');
}
require_once $baseBootstrap;

// 2. Load .env from secure location (already done by base/bootstrap.php)
// Do NOT load .env again here

// 3. Get framework services via getInstance() pattern
$db = Base\Database::getInstance();
$logger = Base\Logger::getInstance();
// $auth = Base\Auth::getInstance();  // When implemented

// 4. Load module-specific config
$moduleConfig = require __DIR__ . '/config/module.config.php';

// 5. Register module autoloader (if using PSR-4)
spl_autoload_register(function ($class) {
    $prefix = 'Modules\\YourModule\\';
    $baseDir = __DIR__ . '/src/';
    
    $len = strlen($prefix);
    if (strncmp($prefix, $class, $len) !== 0) {
        return;
    }
    
    $relativeClass = substr($class, $len);
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';
    
    if (file_exists($file)) {
        require $file;
    }
});

// 6. Module is now ready
return [
    'db' => $db,
    'logger' => $logger,
    'config' => $moduleConfig
];
```

---

## 🏗️ BASE FRAMEWORK API (getInstance() Pattern)

### Database Service
```php
// Get instance
$db = Base\Database::getInstance();

// ✅ CORRECT: Prepared statements (prevents SQL injection)
$stmt = $db->prepare("SELECT * FROM users WHERE email = ?");
$stmt->execute([$email]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

// ❌ WRONG: String concatenation (SQL injection risk)
$result = $db->query("SELECT * FROM users WHERE email = '$email'");
```

### Logger Service
```php
// Get instance
$logger = Base\Logger::getInstance();

// Log levels
$logger->debug('Debug message', ['context' => 'value']);
$logger->info('Info message');
$logger->warning('Warning message');
$logger->error('Error message', ['exception' => $e]);
$logger->critical('Critical error');
```

### Error Handler
```php
// Automatically registered by base/bootstrap.php
// Catches all exceptions and logs them

// Throw exceptions instead of returning false
if (!$user) {
    throw new \Exception('User not found');
}
```

---

## 🛡️ SECURITY STANDARDS (MANDATORY)

### 1. Environment Variables (.env)
```
LOCATION: /home/129337.cloudwaysapps.com/jcepnzzkmj/.env
STATUS: OUTSIDE public_html (not web-accessible)

✅ CORRECT:
$password = $_ENV['DB_PASSWORD'] ?? throw new \RuntimeException('DB_PASSWORD not set');

❌ WRONG:
$password = $_ENV['DB_PASSWORD'] ?? 'wprKh9Jq63';  // Hardcoded fallback
$password = 'wprKh9Jq63';                          // Hardcoded
```

### 2. SQL Injection Prevention
```php
✅ CORRECT: Prepared statements
$stmt = $db->prepare("SELECT * FROM products WHERE id = ?");
$stmt->execute([$productId]);

❌ WRONG: String interpolation
$result = $db->query("SELECT * FROM products WHERE id = $productId");
$result = $db->query("SELECT * FROM products WHERE id = {$productId}");
```

### 3. XSS Prevention
```php
✅ CORRECT: HTML escaping in views
<?php echo htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8'); ?>

❌ WRONG: Raw output
<?php echo $userInput; ?>
```

### 4. CSRF Protection (TO BE IMPLEMENTED)
```php
// In forms:
<input type="hidden" name="csrf_token" value="<?php echo Base\CSRF::generateToken(); ?>">

// In controllers:
Base\CSRF::validateToken($_POST['csrf_token']);
```

### 5. Authentication (TO BE IMPLEMENTED)
```php
// Check if user is logged in
$auth = Base\Auth::getInstance();
if (!$auth->check()) {
    header('Location: /login.php');
    exit;
}

// Check permissions
if (!$auth->can('manage_users')) {
    throw new \Exception('Unauthorized');
}
```

---

## ⚠️ FORBIDDEN PATTERNS (ENFORCED BY PRE-COMMIT HOOKS)

### 1. ❌ Creating app/ Directory
```
modules/app/                      ← BLOCKED (Option B uses base/, not app/)
```

### 2. ❌ Hardcoded Passwords
```php
$password = 'wprKh9Jq63';         ← BLOCKED
$password = $_ENV['DB_PASS'] ?? 'wprKh9Jq63';  ← BLOCKED
```

### 3. ❌ .env Files in Git
```
.env                              ← BLOCKED (contains secrets)
modules/.env                      ← BLOCKED
modules/module_name/.env          ← BLOCKED
```

### 4. ❌ IntelligenceHub Namespace
```php
namespace IntelligenceHub\MCP\Tools;  ← BLOCKED (wrong application)
use IntelligenceHub\MCP\Crawler;      ← BLOCKED
```

### 5. ❌ Dangerous PHP Functions
```php
eval($code);                      ← BLOCKED
exec($command);                   ← BLOCKED
system($command);                 ← BLOCKED
shell_exec($command);             ← BLOCKED
passthru($command);               ← BLOCKED
```

---

## ✅ PRE-COMMIT HOOKS (AUTO-ENFORCEMENT)

Located at: `.git/hooks/pre-commit`

**What Gets Blocked:**
1. Hardcoded passwords (regex: `password\s*=\s*['"][^'"]+['"]`)
2. .env files in commits
3. IntelligenceHub namespace
4. app/ directory creation (Option B conflict)
5. Dangerous PHP functions (eval, exec, system, shell_exec, passthru)

**How to Test:**
```bash
# This will be BLOCKED:
git add modules/.env
git commit -m "Add config"
# ❌ BLOCKED: .env files should not be committed

# This will be ALLOWED:
git add modules/.env.example
git commit -m "Add config template"
# ✅ ALLOWED: .env.example is safe
```

---

## 📋 CODE REVIEW CHECKLIST

Before approving any PR:

- [ ] Does module have `bootstrap.php` that loads `base/bootstrap.php`?
- [ ] Are namespaces using `Base\` or `Modules\ModuleName\`?
- [ ] No `App\` or `IntelligenceHub\` namespaces?
- [ ] All database queries use prepared statements?
- [ ] No hardcoded passwords or secrets?
- [ ] XSS protection on all user input output?
- [ ] CSRF tokens on all forms? (when implemented)
- [ ] Authentication checks on protected routes? (when implemented)
- [ ] No .env files in the commit?
- [ ] No app/ directory being created?

---

## 🚀 GETTING STARTED (New Module)

### Step 1: Create Module Directory
```bash
cd /home/129337.cloudwaysapps.com/jcepnzzkmj/public_html/modules
mkdir my_new_module
cd my_new_module
```

### Step 2: Create bootstrap.php (Copy Template Above)
```bash
# Copy from CIS_ARCHITECTURE_STANDARDS.md section above
```

### Step 3: Create Directory Structure
```bash
mkdir -p controllers models views api lib config database/migrations tests
touch README.md
```

### Step 4: Create Module Config
```bash
cat > config/module.config.php << 'EOF'
<?php
return [
    'module_name' => 'my_new_module',
    'version' => '1.0.0',
    'enabled' => true,
    'dependencies' => ['base']
];
EOF
```

### Step 5: Create First Controller
```bash
cat > controllers/MyController.php << 'EOF'
<?php
namespace Modules\MyNewModule\Controllers;

class MyController {
    private $db;
    private $logger;
    
    public function __construct() {
        $this->db = \Base\Database::getInstance();
        $this->logger = \Base\Logger::getInstance();
    }
    
    public function index() {
        $this->logger->info('MyController::index called');
        // Your logic here
    }
}
EOF
```

### Step 6: Create First View
```bash
cat > views/index.php << 'EOF'
<!DOCTYPE html>
<html>
<head>
    <title>My Module</title>
</head>
<body>
    <h1>My Module</h1>
    <p>Module is working!</p>
</body>
</html>
EOF
```

### Step 7: Test Module
```bash
# Create test endpoint:
cat > api/test.php << 'EOF'
<?php
require_once __DIR__ . '/../bootstrap.php';

use Modules\MyNewModule\Controllers\MyController;

$controller = new MyController();
$controller->index();

echo json_encode(['status' => 'success', 'message' => 'Module is working']);
EOF

# Test in browser:
# https://staff.vapeshed.co.nz/modules/my_new_module/api/test.php
```

---

## �� ADDITIONAL RESOURCES

- **base/ Framework Docs:** `modules/base/_docs/API_REFERENCE.md`
- **Security Guide:** `modules/base/_docs/SECURITY.md`
- **Module Integration:** `modules/base/_docs/MODULE_INTEGRATION_GUIDE.md`
- **Security Audit:** `modules/SECURITY_AUDIT_REPORT.md`
- **Architecture Analysis:** `modules/COMPREHENSIVE_ARCHITECTURAL_ANALYSIS.md`
- **Options Comparison:** `modules/ARCHITECTURE_OPTIONS_VISUAL_GUIDE.md`

---

## 🆘 SUPPORT & ESCALATION

**Questions?**
1. Read `modules/base/_docs/` first
2. Check existing modules for examples (consignments/, admin-ui/)
3. Review `CIS_ARCHITECTURE_STANDARDS.md` (this file)
4. Escalate to: Pearce Stephens <pearce.stephens@ecigdis.co.nz>

---

**END OF STANDARDS DOCUMENT**
