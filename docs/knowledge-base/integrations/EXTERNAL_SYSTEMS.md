# Integration Patterns & External Systems

**Purpose:** Document how modules integrate with external CIS systems

---

## 1. CIS Template System (UI Components)

### Location
```
/assets/template/
├── html-header.php       # <head>, CSS, jQuery
├── header.php            # Top navbar (logo, user dropdown)
├── sidemenu.php          # Left sidebar (dynamic navigation)
├── html-footer.php       # Scripts (Bootstrap, CoreUI)
└── footer.php            # Copyright footer
```

### Integration Pattern
```php
// In base/views/layouts/master.php
$templateRoot = $_SERVER['DOCUMENT_ROOT'] . '/assets/template';

include $templateRoot . '/html-header.php';
include $templateRoot . '/header.php';
include $templateRoot . '/sidemenu.php';

// Module content here
echo $content;

include $templateRoot . '/html-footer.php';
include $templateRoot . '/footer.php';
```

### Variables Required
```php
$pageTitle    // Browser <title> and page heading
$content      // Main module HTML
$breadcrumbs  // Optional: [['label' => 'Home', 'href' => '/']]
$moduleCSS    // Optional: ['/modules/example/assets/css/style.css']
$moduleJS     // Optional: ['/modules/example/assets/js/app.js']
```

### External Dependencies
- **Bootstrap 4.2** (via `/assets/css/`)
- **CoreUI v2.0** (via `/assets/css/`)
- **jQuery 3.7.1** (via `/assets/js/`)
- **Font Awesome 5.15** (via `/assets/css/`)

---

## 2. Database Connection (`/app.php`)

### Factory Function
```php
// Defined in /app.php (CIS core)
function cis_pdo(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO(
            'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
            DB_USER,
            DB_PASS,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]
        );
        
        // Set timezone
        if (defined('DB_TZ')) {
            $pdo->exec("SET time_zone = '" . DB_TZ . "'");
        }
    }
    return $pdo;
}
```

### Module Wrapper
```php
// consignments/lib/Db.php
namespace Transfers\Lib;

final class Db {
    private static ?PDO $pdo = null;
    
    public static function pdo(): PDO {
        if (self::$pdo === null) {
            self::$pdo = \cis_pdo();  // Use global factory
        }
        return self::$pdo;
    }
}
```

### Environment Variables
```bash
DB_HOST=localhost
DB_NAME=cis_database
DB_USER=cis_user
DB_PASS=password
DB_TZ="+13:00"        # NZ timezone
```

### Key Tables
```sql
transfers              # Transfer records
transfer_items         # Line items
vend_products          # Product catalog (synced from Vend)
vend_outlets           # Outlet list (synced from Vend)
vend_consignments      # Consignment records
```

---

## 3. Session Management (`/app.php`)

### Auto-Started Sessions
```php
// In /app.php (CIS bootstrap)
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start([
        'cookie_httponly' => true,
        'cookie_samesite' => 'Strict',
        'use_strict_mode' => true
    ]);
}
```

### Available Session Variables
```php
$_SESSION['userID']           // Current user ID (integer)
$_SESSION['csrf_token']       // CSRF protection token
$_SESSION['outletID']         // User's assigned outlet
$_SESSION['permissions']      // User permissions array
$_SESSION['username']         // Display name
```

### Authentication Check
```php
// In Kernel::boot()
if (empty($_SESSION['userID']) && !isset($_ENV['BOT_BYPASS_AUTH'])) {
    header('Location: /login.php');
    exit;
}
```

### CIS Auth Functions
```php
getUserInformation($uid)              // Get user details
getCurrentUserPermissions($uid)       // Get permission array
checkUserPermission($uid, $perm)      // Check specific permission
```

---

## 4. Vend API Integration

### Connection Details
```php
// Defined in /app.php or config
define('VEND_DOMAIN', 'yourdomain.vendhq.com');
define('VEND_TOKEN', 'your-api-token');
```

### Common Endpoints Used
```
GET  /api/2.0/products           # Product catalog
GET  /api/2.0/outlets            # Outlet list
POST /api/2.0/consignments       # Create consignment
PUT  /api/2.0/consignments/{id}  # Update consignment
GET  /api/2.0/consignments/{id}  # Get consignment
```

### Sync Pattern
```php
// Scheduled job (cron)
// 1. Fetch from Vend API
// 2. Upsert into local DB (vend_products, vend_outlets, etc.)
// 3. Log sync timestamp
// 4. Handle errors with retry logic
```

### Tables Synced
- `vend_products` ← Products API
- `vend_outlets` ← Outlets API
- `vend_consignments` ← Consignments API

---

## 5. Navigation Menu (Sidebar)

### Dynamic Menu
```php
// Function in /app.php or /assets/functions/
getNavigationMenus($userID): array

// Returns structure:
[
    [
        'label' => 'Transfers',
        'icon' => 'fas fa-truck',
        'href' => '/modules/consignments/',
        'permission' => 'view_transfers',
        'children' => [
            ['label' => 'Pack', 'href' => '/modules/consignments/transfers/pack'],
            ['label' => 'Receive', 'href' => '/modules/consignments/transfers/receive']
        ]
    ]
]
```

### Integration
```php
// In sidemenu.php (CIS template)
$menuItems = getNavigationMenus($_SESSION['userID']);

foreach ($menuItems as $item) {
    if (checkUserPermission($_SESSION['userID'], $item['permission'])) {
        // Render menu item
    }
}
```

---

## 6. Logging System

### CIS Logger
```php
// Function in /app.php
logActivity($userID, $action, $details): void

// Example:
logActivity(
    $_SESSION['userID'],
    'transfer_packed',
    json_encode([
        'transfer_id' => 123,
        'items' => 15,
        'outlet_from' => 'Auckland',
        'outlet_to' => 'Wellington'
    ])
);
```

### Module Logger
```php
// consignments/lib/Log.php
namespace Transfers\Lib;

class Log {
    public static function info(string $message, array $context = []): void {
        \logActivity($_SESSION['userID'] ?? 0, 'transfer_info', json_encode([
            'message' => $message,
            'context' => $context
        ]));
    }
}
```

---

## 7. Error Reporting

### Production Errors
```php
// Errors logged to:
/logs/apache_phpstack-129337-518184.cloudwaysapps.com.error.log

// Custom error handler logs to:
/modules/logs/error.log
```

### Error Handler Integration
```php
// In module index.php
use Modules\Core\ErrorHandler;

ErrorHandler::register($_ENV['APP_DEBUG'] === '1');

// Adds context for debugging
ErrorHandler::addContext('transfer_id', $transferId);
ErrorHandler::addContext('user_id', $_SESSION['userID']);
```

---

## 8. Queue System (Future)

### Planned Integration
```php
// For background jobs (consignment submission, Vend sync)
use Transfers\Lib\Queue;

Queue::dispatch('SubmitConsignmentJob', [
    'transfer_id' => $transferId,
    'user_id' => $_SESSION['userID']
]);
```

**Status:** Stub implementation exists, needs full queue worker setup

---

## Integration Checklist

When building new modules, ensure integration with:

- [ ] CIS templates (`/assets/template/`)
- [ ] Database via `cis_pdo()` factory
- [ ] Sessions (already started, use `$_SESSION`)
- [ ] Authentication (`$_SESSION['userID']` check)
- [ ] Navigation menu (add entry via admin)
- [ ] Logging system (`logActivity()`)
- [ ] Error handler (`ErrorHandler::register()`)
- [ ] CSS/JS bundles (`/assets/css/`, `/assets/js/`)

---

**Last Updated:** October 12, 2025  
**Maintained By:** AI Memory System
