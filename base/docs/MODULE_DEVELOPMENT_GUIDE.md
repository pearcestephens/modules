# CIS Module Development Guide

## Overview

This guide explains how to create new modules in the CIS Staff Portal using the standardized template inheritance system.

## Quick Start

Every module MUST start with:

```php
<?php
require_once __DIR__ . '/../base/bootstrap.php';
requireAuth();
requirePermission('your.permission');
```

## Module Structure (Option 2: Organized Folders)

```
modules/your-module/
├── index.php           # Dashboard (NOT a router!)
├── page.php            # Direct page files
├── another-page.php    # More pages
├── api/                # API endpoints
│   ├── create.php
│   ├── update.php
│   └── delete.php
├── lib/                # Helper functions
│   └── helpers.php
└── bootstrap.php       # Optional module-specific init
```

### ❌ DO NOT USE:
- `index.php` as a router (no `?page=` routing)
- Heavy MVC structure (no `models/`, `controllers/`, `views/` directories)
- Direct database credentials in code

### ✅ DO USE:
- Direct page files (`transfers.php`, `consignments.php`)
- `api/` folder for AJAX/API endpoints
- `lib/` folder for helper functions
- Base bootstrap for all initialization

## Base Bootstrap Features

When you load `base/bootstrap.php`, you get:

### 1. Global Objects
- `$config` - Services\Config singleton
- `$db` - Services\Database PDO singleton

### 2. Authentication Functions
```php
isAuthenticated()           // Check if user logged in
getCurrentUser()            // Get user array
getUserId()                 // Get user ID
getUserRole()               // Get user role
requireAuth()               // Redirect to login if not authenticated
```

### 3. Permission Functions
```php
hasPermission('module.view')                    // Check single permission
requirePermission('module.edit')                // Die if no permission
hasAnyPermission(['perm1', 'perm2'])           // Check if has ANY
hasAllPermissions(['perm1', 'perm2'])          // Check if has ALL
```

### 4. Template Functions
```php
render('dashboard', $content, [                 // Render with layout
    'pageTitle' => 'My Page',
    'breadcrumbs' => ['Module', 'Page']
]);

component('header', ['title' => 'Test']);       // Include component
themeAsset('css/custom.css');                   // Get theme asset URL
theme();                                         // Get active theme name
```

### 5. Helper Functions
```php
e($string)                                      // Escape HTML
asset('css/style.css')                          // Get asset URL
moduleUrl('transfers', 'index.php')             // Get module URL
redirect('/dashboard')                           // Redirect
jsonResponse(['success' => true])               // JSON response
flash('success', 'Saved!', 'success')           // Set flash message
getFlash('success')                             // Get flash message
dd($var)                                        // Dump and die (debug)
```

## Complete Example Module

### index.php (Dashboard)
```php
<?php
/**
 * Example Module Dashboard
 */

// 1. Load bootstrap
require_once __DIR__ . '/../base/bootstrap.php';

// 2. Auth & permissions
requireAuth();
requirePermission('example.view');

// 3. Your logic
$stats = [
    'total' => 100,
    'active' => 75
];

// 4. Build content
ob_start();
?>
<div class="container">
    <h1>Dashboard</h1>
    <p>Total: <?= $stats['total'] ?></p>
    <p>Active: <?= $stats['active'] ?></p>
</div>
<?php
$content = ob_get_clean();

// 5. Render
render('dashboard', $content, [
    'pageTitle' => 'Example Dashboard',
    'breadcrumbs' => ['Example', 'Dashboard']
]);
```

### api/create.php (API Endpoint)
```php
<?php
require_once __DIR__ . '/../../base/bootstrap.php';
requireAuth();

if (!hasPermission('example.create')) {
    jsonResponse(['error' => 'No permission'], 403);
}

// Your API logic
$data = json_decode(file_get_contents('php://input'), true);

// Validate, process, save...

jsonResponse([
    'success' => true,
    'id' => 123
]);
```

### lib/helpers.php (Module Helpers)
```php
<?php
/**
 * Example module helper functions
 */

function exampleCalculation($input) {
    return $input * 2;
}

function exampleFormatter($data) {
    return strtoupper($data);
}
```

## Theme System

### Available Themes
1. **cis-classic** (default) - Classic CIS style
2. **modern** - Modern, clean design
3. **legacy** - Compatibility theme

### Available Layouts
```php
render('dashboard', $content);      // Standard dashboard with sidebar
render('centered', $content);       // Centered layout (login, etc.)
render('blank', $content);          // Minimal layout (print, export)
render('print', $content);          // Print-optimized layout
```

### Theme Components
```php
component('header');                // Site header
component('sidebar');               // Navigation sidebar
component('footer');                // Site footer
component('breadcrumbs');           // Breadcrumb navigation
component('alerts');                // Flash message alerts
```

### Switch Theme
```php
\CIS\Base\ThemeManager::setActive('modern');    // Switch to modern theme
```

## Database Access

### Using Global $db
```php
global $db;

// Prepared statement (ALWAYS use prepared statements)
$stmt = $db->prepare("SELECT * FROM table WHERE id = ?");
$stmt->execute([$id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
```

### Using Config
```php
global $config;

$dbHost = $config->getDatabase('host');
$vendToken = $config->getVend('access_token');
$sendgridKey = $config->getSendGrid('api_key');
```

## Security Best Practices

### 1. Always Authenticate
```php
requireAuth();  // FIRST LINE after bootstrap
```

### 2. Check Permissions
```php
requirePermission('module.specific_action');
```

### 3. Escape Output
```php
<h1><?= e($userInput) ?></h1>  // ALWAYS escape user input
```

### 4. Use Prepared Statements
```php
// ❌ NEVER DO THIS
$result = $db->query("SELECT * FROM table WHERE id = $id");

// ✅ ALWAYS DO THIS
$stmt = $db->prepare("SELECT * FROM table WHERE id = ?");
$stmt->execute([$id]);
```

### 5. Validate Input
```php
if (!isset($_POST['id']) || !is_numeric($_POST['id'])) {
    jsonResponse(['error' => 'Invalid ID'], 400);
}
```

## Flash Messages

### Set Flash
```php
flash('success', 'Item saved successfully!', 'success');
flash('error', 'Something went wrong', 'danger');
redirect('/module/index.php');
```

### Display Flash (in template)
```php
<?php foreach (getAllFlashes() as $key => $flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>">
        <?= e($flash['message']) ?>
    </div>
<?php endforeach; ?>
```

## Error Handling

### Development Mode
Set in `.env`:
```env
APP_DEBUG=true
```

Errors will display on screen.

### Production Mode
```env
APP_DEBUG=false
```

Errors logged to `logs/php_errors.log`.

### Custom Error Pages
```php
if ($error) {
    http_response_code(404);
    render('error-404', '<h1>Not Found</h1>');
}
```

## Testing Your Module

### 1. Syntax Check
```bash
php -l index.php
```

### 2. Access Test
Visit: `https://staff.vapeshed.co.nz/modules/your-module/index.php`

### 3. Permission Test
- Test as admin (should work)
- Test as regular user (should check permissions)

### 4. API Test
```bash
curl -X POST https://staff.vapeshed.co.nz/modules/your-module/api/test.php \
  -H "Content-Type: application/json" \
  -d '{"test": "data"}'
```

## Migration from Old Modules

### Old Way (DON'T USE)
```php
<?php
require_once '../../config/database.php';
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$db = new PDO("mysql:host=localhost;dbname=db", "user", "hardcoded_password");
?>
<html>
<head>...</head>
<body>
    Content here
</body>
</html>
```

### New Way (USE THIS)
```php
<?php
require_once __DIR__ . '/../base/bootstrap.php';
requireAuth();
requirePermission('module.view');

ob_start();
?>
<div>Content here</div>
<?php
$content = ob_get_clean();

render('dashboard', $content, ['pageTitle' => 'My Module']);
```

## Checklist for New Modules

- [ ] Load `base/bootstrap.php` at top
- [ ] Call `requireAuth()`
- [ ] Call `requirePermission()`
- [ ] Use `ob_start()` / `ob_get_clean()` for content
- [ ] Use `render()` for output
- [ ] Escape ALL user input with `e()`
- [ ] Use prepared statements for database
- [ ] No hardcoded credentials
- [ ] Follow Option 2 structure (index.php, api/, lib/)
- [ ] Test with different user roles

## Common Patterns

### AJAX Data Table
```php
<?php
require_once __DIR__ . '/../base/bootstrap.php';
requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    requirePermission('module.view');
    
    global $db;
    $stmt = $db->prepare("SELECT * FROM table WHERE active = 1");
    $stmt->execute();
    
    jsonResponse([
        'success' => true,
        'data' => $stmt->fetchAll(PDO::FETCH_ASSOC)
    ]);
}

// HTML page for non-AJAX
ob_start();
?>
<div id="dataTable"></div>
<script>
fetch('/modules/your-module/index.php', { method: 'POST' })
    .then(r => r.json())
    .then(data => console.log(data));
</script>
<?php
$content = ob_get_clean();
render('dashboard', $content);
```

### Form Processing
```php
<?php
require_once __DIR__ . '/../base/bootstrap.php';
requireAuth();
requirePermission('module.edit');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Validate
    if (empty($_POST['name'])) {
        flash('error', 'Name is required', 'danger');
        redirect($_SERVER['PHP_SELF']);
    }
    
    // Save
    global $db;
    $stmt = $db->prepare("INSERT INTO table (name) VALUES (?)");
    $stmt->execute([e($_POST['name'])]);
    
    flash('success', 'Item saved!', 'success');
    redirect('/modules/your-module/index.php');
}

// Show form
ob_start();
?>
<form method="POST">
    <input type="text" name="name" required>
    <button type="submit">Save</button>
</form>
<?php
$content = ob_get_clean();
render('dashboard', $content);
```

## Support

For questions or issues:
1. Check this guide
2. Review example-module
3. Check BASE_BOOTSTRAP_SPECIFICATION.md
4. Ask in #dev-support

## Version History

- v1.0 (2025-01-07) - Initial guide with Option 2 structure
