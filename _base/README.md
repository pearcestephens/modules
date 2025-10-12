# CIS Base Module

**Version:** 1.0  
**Namespace:** `Modules\Base`  
**Purpose:** Shared foundation for all CIS feature modules

## Overview

The Base Module provides a unified architecture for all CIS modules, including:

- **Routing** - URL pattern matching and controller dispatch
- **Controllers** - Base classes for pages and APIs  
- **Views** - Template rendering and layouts
- **Helpers** - URL generation, CSRF protection, utilities
- **Error Handling** - Debug-aware exception and error handlers
- **Kernel** - Bootstrap, autoloading, security headers

## Directory Structure

```
_base/
├── lib/
│   ├── Controller/
│   │   ├── BaseController.php      # Common controller functionality
│   │   ├── PageController.php      # HTML pages with layouts
│   │   └── ApiController.php       # JSON API endpoints
│   ├── Kernel.php                  # Bootstrap & autoloader
│   ├── Router.php                  # URL routing
│   ├── View.php                    # Template rendering
│   ├── Helpers.php                 # URL, CSRF, utilities
│   ├── ErrorHandler.php            # Exception handling
│   ├── Validation.php              # Input validation
│   └── Shared.php                  # Common utilities
└── views/
    ├── layouts/                    # Page templates
    │   ├── cis-template-bare.php   # Full CIS chrome (header/sidebar/footer)
    │   ├── cis-template.php        # Standard template
    │   └── base-coreui.php         # Minimal CoreUI layout
    └── partials/                   # Reusable components
        ├── head.php
        ├── topbar.php
        ├── sidebar.php
        └── footer.php
```

## Quick Start: Creating a New Module

### 1. Module Structure

```
modules/
  your_module/
    index.php                       # Entry point
    module_bootstrap.php            # Module-specific config
    controllers/                    # Your controllers
    views/                          # Your views
    api/                            # API endpoints
    assets/                         # Built assets
    css/                            # Source CSS
    js/                             # Source JS
    lib/                            # Module-specific libraries
```

### 2. Entry Point (index.php)

```php
<?php
declare(strict_types=1);

use Modules\Base\Kernel;
use Modules\Base\Router;
use Modules\Base\ErrorHandler;
use Modules\Base\Helpers;

require __DIR__ . '/module_bootstrap.php';
require_once dirname(__DIR__) . '/_base/lib/Kernel.php';

// Register error handler
$debug = ($_ENV['APP_DEBUG'] ?? '') === '1';
ErrorHandler::register($debug);

// Bootstrap (app.php, autoloaders, security headers)
Kernel::boot();

// Set module base for URL generation
Helpers::setModuleBase('/modules/your_module');

// Define routes
$router = new Router();
$router->add('GET', '/', YourModule\controllers\HomeController::class, 'index');
$router->add('GET', '/some-page', YourModule\controllers\PageController::class, 'show');

// Dispatch
$router->dispatch('/modules/your_module');
```

### 3. Page Controller

```php
<?php
declare(strict_types=1);

namespace YourModule\controllers;

use Modules\Base\Controller\PageController;
use Modules\Base\Helpers;

class HomeController extends PageController
{
    public function __construct()
    {
        parent::__construct();
        // Use the CIS chrome layout
        $this->layout = dirname(__DIR__, 2) . '/_base/views/layouts/cis-template-bare.php';
    }

    public function index(): string
    {
        return $this->view(dirname(__DIR__) . '/views/home.php', [
            'page_title' => 'Home',
            'breadcrumbs' => [
                ['label' => 'Home', 'active' => true],
            ],
        ]);
    }
}
```

### 4. API Controller

```php
<?php
declare(strict_types=1);

namespace YourModule\controllers;

use Modules\Base\Controller\ApiController;

class DataApiController extends ApiController
{
    public function getData(): void
    {
        // Verify CSRF if needed
        if (!$this->verifyCsrf($_POST['csrf'] ?? '')) {
            $this->json(['ok' => false, 'error' => 'Invalid CSRF token'], 403);
            return;
        }

        // Your logic here
        $data = ['items' => []];
        
        $this->json(['ok' => true, 'data' => $data]);
    }
}
```

## Core Classes

### Helpers

**Module-agnostic URL generation:**

```php
use Modules\Base\Helpers;

// Set module base (do once in index.php)
Helpers::setModuleBase('/modules/consignments');

// Generate URLs
$url = Helpers::url('/transfers/pack');
// → https://staff.vapeshed.co.nz/modules/consignments/transfers/pack

// CSRF protection
$token = Helpers::csrfToken();
echo Helpers::csrfTokenInput();
// → <input type="hidden" name="csrf" value="...">

if (Helpers::verifyCsrf($_POST['csrf'] ?? '')) {
    // Valid CSRF token
}
```

### Router

**Pattern-based routing:**

```php
$router = new Router();

// Simple routes
$router->add('GET', '/', Controller::class, 'index');
$router->add('POST', '/api/save', ApiController::class, 'save');

// Dispatch with base path
$router->dispatch('/modules/your_module');
```

### ErrorHandler

**Debug-aware error handling:**

```php
// In index.php
$debug = getenv('APP_DEBUG') === '1';
ErrorHandler::register($debug);

// Exceptions are caught and formatted as:
// - JSON for API requests (Content-Type: application/json)
// - HTML for browser requests
// - Full details if $debug=true, generic message if false
```

### Kernel

**Bootstrap and autoloading:**

```php
Kernel::boot();
// - Loads /app.php (sessions, database, config)
// - Registers PSR-4 autoloaders for Modules\Base and your module
// - Sets security headers
// - Initializes CSRF tokens
```

## Layouts

### CIS Template (Full Chrome)

Use `_base/views/layouts/cis-template-bare.php` for pages that need:

- CIS header with navigation
- Sidebar menu
- Breadcrumbs
- Footer

**Variables:**

- `$content` - Main page content (required)
- `$page_title` - Page title (string)
- `$page_blurb` - Page description (string)
- `$breadcrumbs` - Array of breadcrumb items
- `$body_class` - Additional CSS classes for `<body>`

**Example:**

```php
return $this->view(dirname(__DIR__) . '/views/my-page.php', [
    'page_title' => 'My Page',
    'breadcrumbs' => [
        ['label' => 'Home', 'href' => Helpers::url('/')],
        ['label' => 'My Page', 'active' => true],
    ],
    'myData' => $someData,
]);
```

## Asset Management

### Bundle Strategy

- **core.bundle.js** - Shared utilities (toast, storage, table helpers)
- **feature.bundle.js** - Page-specific code (pack, receive, etc.)
- **CSS** - One main import file that pulls in feature stylesheets

### Build Process

```bash
# Build bundles and check sizes
cd modules/your_module/tools
./build.sh
```

### Size Budgets

Set limits in `tools/size_guard.php`:

```php
$budgets = [
    __DIR__.'/../assets/js/core.bundle.js'   => 30 * 1024,  // 30 KB
    __DIR__.'/../assets/js/page.bundle.js'   => 50 * 1024,  // 50 KB
];
```

### Loading Assets

```php
<!-- In your view -->
<link rel="stylesheet" href="<?= Modules\Base\Helpers::url('/assets/css/styles.css'); ?>">

<?php if (isset($_GET['dev'])): ?>
    <!-- Dev: ES modules -->
    <script type="module">
        import { init } from "<?= Modules\Base\Helpers::url('/js/init.js'); ?>";
        init();
    </script>
<?php else: ?>
    <!-- Prod: bundles -->
    <script src="<?= Modules\Base\Helpers::url('/assets/js/core.bundle.js'); ?>"></script>
    <script src="<?= Modules\Base\Helpers::url('/assets/js/page.bundle.js'); ?>"></script>
<?php endif; ?>
```

## Security

### CSRF Protection

All state-changing requests (POST/PUT/DELETE) should verify CSRF tokens:

```php
// In controller
if (!Helpers::verifyCsrf($_POST['csrf'] ?? '')) {
    return $this->json(['ok' => false, 'error' => 'Invalid token'], 403);
}
```

```html
<!-- In form -->
<form method="POST">
    <?= Modules\Base\Helpers::csrfTokenInput(); ?>
    <!-- ... -->
</form>
```

### Authentication

Authentication is handled by `Kernel::boot()` which checks `$_SESSION['userID']`.

To bypass auth for testing: set `BOT_BYPASS_AUTH=1` in `.env`

## Testing

### Smoke Test

```php
// modules/_base/tests/smoke.php
<?php
require_once dirname(__DIR__, 2) . '/your_module/index.php';
echo "OK";
```

### Manual Testing

1. Open `/modules/your_module/` in browser
2. Verify:
   - Page renders with CIS chrome
   - No duplicate headers/footers
   - Assets load correctly
   - Forms submit successfully

## Migration from Modules\Shared

The Base Module replaced the old `Modules\Shared` namespace. For backward compatibility, the autoloader includes an alias:

```php
'Modules\\Shared\\' => __DIR__ . '/',  // Maps to Modules\Base
```

**Update your code:**

```php
// Old
use Modules\Shared\Helpers;

// New
use Modules\Base\Helpers;
```

## Best Practices

1. **One layout per module** - Use `cis-template-bare.php` for consistency
2. **Module base detection** - Always call `Helpers::setModuleBase()` in index.php
3. **CSRF everywhere** - Protect all state-changing requests
4. **Size budgets** - Keep bundles small, split when necessary
5. **Error handling** - Use `ErrorHandler::register()` for consistent error pages
6. **Namespaces** - Follow PSR-4: `YourModule\controllers\`, `YourModule\lib\`

## Troubleshooting

### "Class not found" errors

- Ensure `Kernel::boot()` is called before using any module classes
- Check namespace matches directory structure
- Verify autoloader paths in `Kernel.php`

### URL generation issues

- Call `Helpers::setModuleBase()` once in your module's index.php
- Use `Helpers::url('/path')` not hardcoded paths

### Double headers/footers

- Use only ONE layout per page
- Don't include templates manually in views
- Let `PageController::view()` handle layout wrapping

### Assets not loading

- Check `Helpers::url()` generates correct full URLs
- Verify bundle files exist in `assets/` directory
- Run `tools/build.sh` to rebuild bundles

## Support

For issues or questions, see:

- Module source: `/modules/_base/`
- Example implementation: `/modules/consignments/`
- Architecture docs: `/modules/docs/architecture/`
