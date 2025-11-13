# 🎯 CIS Base Module - Perfect Module Standard

**Version:** 2.0.0
**Status:** ✅ SHOWCASE MODULE - ALL MODULES MUST FOLLOW THIS PATTERN

---

## 📁 DIRECTORY STRUCTURE (STANDARD)

```
modules/base/
├── src/                          # ✅ PSR-4 namespaced code (CIS\Base\)
│   ├── Core/                     # Core services
│   │   ├── Application.php       # Application container
│   │   ├── Database.php          # Database service
│   │   ├── Logger.php            # Logging service
│   │   ├── Session.php           # Session management
│   │   ├── ErrorHandler.php      # Error handling
│   │   └── SimpleCache.php       # Caching
│   ├── Http/                     # HTTP layer
│   │   ├── Request.php           # HTTP request
│   │   ├── Response.php          # HTTP response
│   │   ├── Controllers/          # Controllers
│   │   └── Middleware/           # Middleware
│   ├── Services/                 # Business services
│   │   ├── AIChatService.php     # AI chat
│   │   ├── CacheService.php      # Cache wrapper
│   │   └── AIBusinessInsightsService.php
│   ├── View/                     # View layer
│   │   └── TemplateEngine.php    # Template rendering
│   ├── Security/                 # Security
│   └── Support/                  # Helpers
│       └── helpers.php           # Global helper functions
│
├── lib/                          # ✅ Legacy compatibility (CIS\Base\)
│   ├── BaseAPI.php               # API base class
│   ├── ThemeManager.php          # Theme system
│   ├── SecurityMiddleware.php    # Security middleware
│   ├── PerformanceMonitor.php    # Performance tracking
│   └── CacheManager.php          # Cache manager
│
├── api/                          # ✅ API endpoints (thin controllers)
│   ├── health.php                # Health check
│   └── ai-chat.php               # AI chat endpoint
│
├── views/                        # ✅ UI templates
│   └── health/                   # Health check views
│
├── assets/                       # ✅ Module-specific assets
│   ├── css/
│   ├── js/
│   └── images/
│
├── config/                       # ✅ Configuration files
│   ├── app.php                   # App config
│   ├── database.php              # DB config
│   └── logging.php               # Logging config
│
├── database/                     # ✅ Database migrations
│   └── migrations/
│
├── tests/                        # ✅ PHPUnit tests
│   ├── Unit/
│   ├── Integration/
│   └── Feature/
│
├── _kb/                          # ✅ Documentation
│   ├── README.md                 # This file
│   └── *.md                      # All docs
│
├── bootstrap.php                 # ✅ Module initialization
├── module.json                   # ✅ Module manifest
├── composer.json                 # ✅ Composer config (PSR-4)
├── .env.example                  # ✅ Config documentation
└── README.md                     # ✅ Module README

```

---

## ✅ BOOTSTRAP PATTERN (STANDARD)

**File:** `modules/base/bootstrap.php`

### Requirements:
1. ✅ NO hardcoded credentials
2. ✅ NO custom autoloaders (rely on Composer)
3. ✅ Loads config from centralized `.env`
4. ✅ Initializes services (DB, Logger, Session, etc.)
5. ✅ Provides helper functions (optional, deprecated pattern)
6. ✅ Returns Application container

### Pattern:
```php
<?php
/**
 * CIS Base Module Bootstrap
 *
 * Loads core services and initializes application container.
 * All other modules require this file.
 */

declare(strict_types=1);

// Composer autoloader (PSR-4)
require_once __DIR__ . '/../../vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../../');
$dotenv->load();

// Initialize Application container
$app = new \CIS\Base\Core\Application();

// Register services
$app->singleton('database', fn($app) => new \CIS\Base\Core\Database($app));
$app->singleton('logger', fn($app) => new \CIS\Base\Core\Logger($app));
$app->singleton('session', fn($app) => new \CIS\Base\Core\Session($app));
$app->singleton('cache', fn($app) => new \CIS\Base\Core\SimpleCache($app));

// Initialize error handler
\CIS\Base\Core\ErrorHandler::init($app);

// Start session
$app->make('session')->start();

// Return container (optional)
return $app;
```

---

## ✅ API ENDPOINT PATTERN (STANDARD)

**File:** `modules/base/api/health.php`

### Requirements:
1. ✅ Require base bootstrap FIRST
2. ✅ Use BaseAPI or controller class
3. ✅ Enforce security (auth, CSRF, rate limit)
4. ✅ Return standardized JSON envelope
5. ✅ Log all actions
6. ✅ Handle errors gracefully

### Pattern:
```php
<?php
/**
 * API: Health Check
 * Module: base
 */

declare(strict_types=1);

// Load base bootstrap (ALWAYS FIRST)
require_once __DIR__ . '/../../base/bootstrap.php';

// Security gates
requireAuth();  // Require authentication
SecurityMiddleware::csrf();  // CSRF token check
RateLimiter::check('api.base.health', 60);  // 60 req/min

try {
    // Controller or service
    $health = new \CIS\Base\Http\Controllers\HealthController();
    $result = $health->check();

    // Log action
    Logger::info('api.base.health.check', [
        'user_id' => getUserId(),
        'status' => $result['status']
    ]);

    // Success response (standardized envelope)
    Response::jsonOk($result, 'Health check complete');

} catch (\Exception $e) {
    // Error handling
    Logger::error('api.base.health.error', [
        'error' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);

    Response::jsonError($e->getMessage(), 500);
}
```

---

## ✅ SERVICE CLASS PATTERN (STANDARD)

**File:** `modules/base/src/Services/ExampleService.php`

### Requirements:
1. ✅ Declare strict types
2. ✅ Use PSR-4 namespace
3. ✅ Type-hint all parameters
4. ✅ Document with PHPDoc
5. ✅ Inject dependencies (no globals)
6. ✅ Return types declared

### Pattern:
```php
<?php
/**
 * Example Service
 *
 * @package CIS\Base\Services
 * @version 1.0.0
 */

declare(strict_types=1);

namespace CIS\Base\Services;

use CIS\Base\Core\Database;
use CIS\Base\Core\Logger;

class ExampleService
{
    private Database $db;
    private Logger $logger;

    /**
     * Constructor
     */
    public function __construct(Database $db, Logger $logger)
    {
        $this->db = $db;
        $this->logger = $logger;
    }

    /**
     * Do something
     *
     * @param array $data Input data
     * @return array Result data
     * @throws \Exception If validation fails
     */
    public function doSomething(array $data): array
    {
        // Validate input
        if (empty($data['id'])) {
            throw new \InvalidArgumentException('ID is required');
        }

        // Business logic
        $result = $this->db->query('SELECT * FROM table WHERE id = ?', [$data['id']]);

        // Log action
        $this->logger->info('example.action', [
            'id' => $data['id'],
            'result_count' => count($result)
        ]);

        return $result;
    }
}
```

---

## ✅ VIEW PATTERN (STANDARD)

**File:** `modules/base/views/example.php`

### Requirements:
1. ✅ Require base bootstrap
2. ✅ Auth & permission checks
3. ✅ Load data via service
4. ✅ Render via ThemeManager
5. ✅ No business logic in view
6. ✅ Escape all output

### Pattern:
```php
<?php
/**
 * View: Example Page
 * Module: base
 */

declare(strict_types=1);

// Load base bootstrap
require_once __DIR__ . '/../bootstrap.php';

// Security
requireAuth();
requirePermission('base.view.example');

// Page metadata
$pageTitle = 'Example Page';
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/'],
    ['label' => 'Base', 'url' => '/modules/base/'],
    ['label' => 'Example', 'url' => '']
];

// Load data (via service, NO business logic here)
$service = new \CIS\Base\Services\ExampleService($db, $logger);
$data = $service->getData();

// Capture content
ob_start();
?>

<!-- HTML CONTENT -->
<div class="container">
    <h1><?= e($pageTitle) ?></h1>

    <div class="data">
        <?php foreach ($data as $item): ?>
            <p><?= e($item['name']) ?></p>
        <?php endforeach; ?>
    </div>
</div>

<?php
$content = ob_get_clean();

// Render with theme
ThemeManager::render('dashboard', $content, [
    'pageTitle' => $pageTitle,
    'breadcrumbs' => $breadcrumbs
]);
?>
```

---

## ✅ COMPOSER.JSON (STANDARD)

**File:** `modules/base/composer.json`

### Requirements:
1. ✅ PSR-4 autoload to `src/`
2. ✅ Helper files loaded automatically
3. ✅ Dev autoload for tests
4. ✅ Version specified
5. ✅ Config optimization enabled

### Pattern:
```json
{
    "name": "cis/base",
    "description": "CIS Base Module - Core Infrastructure & Services",
    "type": "library",
    "version": "2.0.0",
    "license": "proprietary",
    "require": {
        "php": "^8.0"
    },
    "autoload": {
        "psr-4": {
            "CIS\\Base\\": "src/"
        },
        "files": [
            "src/Support/helpers.php"
        ]
    },
    "autoload-dev": {
        "psr-4": {
            "CIS\\Base\\Tests\\": "tests/"
        }
    },
    "config": {
        "optimize-autoloader": true,
        "preferred-install": "dist",
        "sort-packages": true
    },
    "minimum-stability": "stable",
    "prefer-stable": true
}
```

---

## ✅ MODULE.JSON MANIFEST (STANDARD)

**File:** `modules/base/module.json`

### Requirements:
1. ✅ Module metadata
2. ✅ Dependencies listed
3. ✅ Provides services
4. ✅ Routing config
5. ✅ Health check endpoint

### Pattern:
```json
{
    "name": "base",
    "title": "CIS Base Module",
    "version": "2.0.0",
    "description": "Core infrastructure and services for all CIS modules",
    "namespace": "CIS\\Base",
    "status": "active",
    "type": "core",
    "author": "CIS Development Team",
    "license": "proprietary",
    "dependencies": [],
    "provides": [
        "database",
        "session",
        "logger",
        "error_handler",
        "security",
        "cache",
        "auth",
        "response"
    ],
    "bootstrap": "bootstrap.php",
    "api_prefix": "/modules/base/api",
    "health_check": {
        "endpoint": "/modules/base/api/health.php",
        "enabled": true
    }
}
```

---

## ✅ README.MD (STANDARD)

### Requirements:
1. ✅ Quick start (3 lines of code)
2. ✅ Feature list
3. ✅ API reference
4. ✅ Usage examples
5. ✅ Installation instructions
6. ✅ Testing instructions

### Sections:
- Quick Start
- What is this module?
- Core Features
- Installation
- Usage Examples
- API Reference
- Configuration
- Testing
- Troubleshooting
- License

---

## ✅ SECURITY CHECKLIST

### Every API Endpoint MUST:
- ✅ Require base bootstrap
- ✅ Call `requireAuth()` (unless public)
- ✅ Call `SecurityMiddleware::csrf()` (POST/PUT/DELETE)
- ✅ Call `RateLimiter::check()` (prevent abuse)
- ✅ Use `Response::jsonOk()` / `Response::jsonError()`
- ✅ Log all actions via Logger
- ✅ Handle exceptions gracefully

### Every View MUST:
- ✅ Require base bootstrap
- ✅ Call `requireAuth()`
- ✅ Call `requirePermission()` (if restricted)
- ✅ Escape output with `e()` function
- ✅ Use ThemeManager for rendering

---

## ✅ TESTING STANDARDS

### Test Structure:
```
tests/
├── Unit/              # Unit tests (isolated, no DB)
├── Integration/       # Integration tests (with DB)
└── Feature/           # Feature tests (end-to-end)
```

### Test Example:
```php
<?php

declare(strict_types=1);

namespace CIS\Base\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use CIS\Base\Services\ExampleService;

class ExampleServiceTest extends TestCase
{
    public function testDoSomething(): void
    {
        // Arrange
        $db = $this->createMock(Database::class);
        $logger = $this->createMock(Logger::class);
        $service = new ExampleService($db, $logger);

        // Act
        $result = $service->doSomething(['id' => 1]);

        // Assert
        $this->assertIsArray($result);
    }
}
```

### Run Tests:
```bash
# From module root
vendor/bin/phpunit

# With coverage
vendor/bin/phpunit --coverage-html coverage/
```

---

## ✅ CODE STANDARDS (PSR-12)

### Enforced Rules:
- ✅ Strict types declared
- ✅ Namespaces match directory structure
- ✅ One class per file
- ✅ Class names PascalCase
- ✅ Method names camelCase
- ✅ Constants UPPER_CASE
- ✅ Type hints on all parameters
- ✅ Return types declared
- ✅ PHPDoc on all methods

### Check Standards:
```bash
# Check code style
vendor/bin/phpcs

# Auto-fix code style
vendor/bin/phpcbf
```

---

## ✅ MIGRATION CHECKLIST (For Other Modules)

When creating or refactoring a module, ensure:

- [ ] Directory structure matches base module
- [ ] `module.json` manifest created
- [ ] Composer autoload PSR-4 to `src/`
- [ ] Bootstrap requires base bootstrap
- [ ] All API endpoints use BaseAPI pattern
- [ ] All views use ThemeManager
- [ ] All services use dependency injection
- [ ] Tests exist for critical paths
- [ ] README.md complete with examples
- [ ] No hardcoded credentials
- [ ] Security middleware on all APIs
- [ ] Logging on all actions
- [ ] PHPDoc on all public methods

---

## 📝 QUICK REFERENCE

### Module Checklist:
```
✅ src/ - PSR-4 namespaced code
✅ lib/ - Legacy compatibility layer
✅ api/ - Thin API endpoints
✅ views/ - UI templates
✅ assets/ - CSS/JS/images
✅ tests/ - PHPUnit tests
✅ _kb/ - Documentation
✅ bootstrap.php - Module initialization
✅ module.json - Module manifest
✅ composer.json - Autoload config
✅ README.md - Module docs
✅ .env.example - Config docs
```

### Code Quality:
```bash
composer test        # Run tests
composer phpcs       # Check style
composer phpcbf      # Fix style
composer analyze     # Static analysis
```

---

## 🎯 NEXT STEPS

1. **For New Modules:** Copy this structure exactly
2. **For Existing Modules:** Use migration checklist
3. **For Contributors:** Read this doc first!

---

**This is the gold standard. ALL modules must match this pattern.** ✨
