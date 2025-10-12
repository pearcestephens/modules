# CIS Modules Shared Base

This directory contains the shared foundation that all CIS modules inherit from.

## Architecture

### Layout System
- **`views/layouts/cis-template-bare.php`** - The canonical layout for all modules
- Includes global CIS header/sidebar/footer from `/assets/template/`
- Renders module content in the `cis-content` slot
- Avoids duplicate `<body>` tags

### Controller Base Classes
- **`lib/Controller/PageController.php`** - Base for page rendering controllers
- **`lib/Controller/ApiController.php`** - Base for API endpoint controllers  
- **`lib/Controller/BaseController.php`** - Foundation controller with common utilities

### Core Libraries
- **`lib/Kernel.php`** - System bootstrap, autoloading, auth gates
- **`lib/Router.php`** - URL routing and dispatch
- **`lib/View.php`** - Template rendering system
- **`lib/Helpers.php`** - URL generation and common utilities
- **`lib/Validation.php`** - Input validation helpers

## How to Adopt from Other Modules

### 1. Update your module bootstrap
```php
require_once dirname(__DIR__) . '/_shared/lib/Kernel.php';
```

### 2. Extend the shared controllers
```php
use Modules\Shared\Controller\PageController;

class YourController extends PageController 
{
    public function __construct()
    {
        parent::__construct();
        $this->layout = dirname(__DIR__, 2) . '/_shared/views/layouts/cis-template-bare.php';
    }
}
```

### 3. Update namespaces in Kernel.php
Add your module to the autoloader:
```php
$prefixes = [
    'Modules\\Shared\\' => __DIR__ . '/',
    'Modules\\YourModule\\' => dirname(__DIR__, 2) . '/your-module/',
];
```

### 4. Use shared utilities
```php
use Modules\Shared\Helpers;

// Generate URLs
$cssUrl = Helpers::url('/assets/css/your-module.css');
$apiUrl = Helpers::url('/api/your-endpoint');
```

## Visual Standards

### Bootstrap/CoreUI Baseline
- **Bootstrap 4** + **CoreUI v3** (no BS5 attributes)
- Use shared CSS framework classes consistently
- Responsive design with mobile-first approach

### Asset Organization
```
your-module/
├── assets/           # Build outputs (bundles)
├── css/             # Source CSS  
├── js/              # Source ES modules
└── components/      # Reusable UI components
```

### Dev vs Production Assets
Support both development (ES modules) and production (bundles):
```php
<?php $dev = isset($_GET['dev']) && $_GET['dev'] === '1'; ?>
<?php if ($dev): ?>
  <script type="module" src="/js/your-module.js"></script>
<?php else: ?>
  <script src="/assets/js/your-module.bundle.js"></script>
<?php endif; ?>
```

## Security Features

- **CSRF Protection** - Automatic token validation
- **Authentication Gates** - Session-based auth checks  
- **Input Sanitization** - XSS prevention in views
- **Database Security** - PDO prepared statements

## Layout Inheritance

```
Base Layout (cis-template-bare.php)
├── Global CIS Header (/assets/template/header.php)
├── Global CIS Sidebar (/assets/template/sidemenu.php)  
├── Module Content Slot
└── Global CIS Footer (/assets/template/footer.php)
```

Your module views should contain **only page content** - no header/footer/layout markup.

---

**Last Updated:** 2025-10-12  
**Maintained by:** CIS Development Team