# CIS Bootstrap - Quick Reference Card

## 🚀 Module Template (Copy & Paste)

```php
<?php
/**
 * Module Name - Page Title
 */

// 1. Load bootstrap (REQUIRED)
require_once __DIR__ . '/../base/bootstrap.php';

// 2. Authentication & Permissions (REQUIRED)
requireAuth();
requirePermission('module.action');

// 3. Your logic here
$data = [
    'items' => [],
    'stats' => []
];

// 4. Build content
ob_start();
?>
<div class="container">
    <h1>Page Title</h1>
    <p>Content here</p>
</div>
<?php
$content = ob_get_clean();

// 5. Render
render('dashboard', $content, [
    'pageTitle' => 'Page Title',
    'breadcrumbs' => ['Module', 'Page']
]);
```

## 📦 Available Helper Functions

### Authentication
```php
isAuthenticated()           // Returns true if logged in
getCurrentUser()            // Returns user array
getUserId()                 // Returns user ID
getUserRole()               // Returns user role
requireAuth()               // Redirect if not logged in
```

### Permissions
```php
hasPermission('perm')                   // Check single permission
requirePermission('perm')               // Die if no permission
hasAnyPermission(['p1', 'p2'])         // Has ANY of these
hasAllPermissions(['p1', 'p2'])        // Has ALL of these
```

### Templates
```php
render('dashboard', $content, $data)    // Render with layout
component('header', $data)              // Include component
themeAsset('css/style.css')            // Get theme asset URL
theme()                                 // Get active theme
```

### Helpers
```php
e($string)                              // Escape HTML (USE THIS!)
asset('css/style.css')                  // /assets/css/style.css
moduleUrl('module', 'page.php')         // /modules/module/page.php
redirect('/url')                        // Redirect
jsonResponse(['data' => 'value'])       // JSON response + exit
flash('key', 'Message', 'success')      // Set flash message
getFlash('key')                         // Get & clear flash
dd($var)                                // Dump and die (debug)
```

### Global Objects
```php
global $config;                         // Services\Config singleton
global $db;                             // PDO singleton

$config->get('KEY')                     // Get .env value
$config->getDatabase('host')            // Get DB config
$config->getVend('access_token')        // Get Vend config

$db->prepare("SELECT * FROM t WHERE id = ?");
$stmt->execute([$id]);
```

## 🎨 Layouts & Themes

### Layouts
```php
render('dashboard', $content);          // Standard with sidebar
render('centered', $content);           // Centered (login)
render('blank', $content);              // Minimal
render('print', $content);              // Print-optimized
```

### Themes
```php
// Available: cis-classic, modern, legacy
\CIS\Base\ThemeManager::setActive('modern');
```

## 🔐 Security Patterns

### Always Escape Output
```php
<h1><?= e($userInput) ?></h1>          // ✅ SAFE
<h1><?= $userInput ?></h1>             // ❌ DANGEROUS!
```

### Always Use Prepared Statements
```php
// ✅ SAFE
$stmt = $db->prepare("SELECT * FROM t WHERE id = ?");
$stmt->execute([$id]);

// ❌ DANGEROUS!
$result = $db->query("SELECT * FROM t WHERE id = $id");
```

### Always Check Permissions
```php
requireAuth();                          // Check login
requirePermission('module.action');     // Check permission
```

## 📊 API Endpoint Pattern

```php
<?php
require_once __DIR__ . '/../../base/bootstrap.php';
requireAuth();

// Check permission
if (!hasPermission('api.access')) {
    jsonResponse(['error' => 'Access denied'], 403);
}

// Validate input
$input = json_decode(file_get_contents('php://input'), true);
if (empty($input['id'])) {
    jsonResponse(['error' => 'ID required'], 400);
}

// Process
global $db;
$stmt = $db->prepare("SELECT * FROM table WHERE id = ?");
$stmt->execute([$input['id']]);
$data = $stmt->fetch(PDO::FETCH_ASSOC);

// Respond
jsonResponse([
    'success' => true,
    'data' => $data
]);
```

## 🎯 Flash Messages Pattern

```php
// Set flash (before redirect)
flash('success', 'Item saved!', 'success');
flash('error', 'Something failed', 'danger');
redirect('/module/index.php');

// Display flash (in template)
<?php foreach (getAllFlashes() as $key => $flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>">
        <?= e($flash['message']) ?>
    </div>
<?php endforeach; ?>
```

## 📁 Module Structure

```
modules/your-module/
├── index.php           # Dashboard (NOT router!)
├── page.php            # Direct pages
├── another.php
├── api/                # API endpoints
│   ├── create.php
│   └── update.php
└── lib/                # Helpers
    └── helpers.php
```

## ✅ Checklist for New Modules

- [ ] Load `base/bootstrap.php` first
- [ ] Call `requireAuth()`
- [ ] Call `requirePermission()`
- [ ] Use `ob_start()` / `ob_get_clean()`
- [ ] Use `render()` for output
- [ ] Escape user input with `e()`
- [ ] Use prepared statements
- [ ] No hardcoded credentials

## 🧪 Testing

**Test Bootstrap System:**
https://staff.vapeshed.co.nz/modules/base/test_bootstrap.php

**Syntax Check:**
```bash
php -l your-file.php
```

**Access Test:**
Visit your module URL and verify:
- Authentication works
- Permissions enforced
- Template renders
- No PHP errors

## 📚 Full Documentation

- Implementation: `/modules/IMPLEMENTATION_COMPLETE.md`
- Development Guide: `/modules/base/docs/MODULE_DEVELOPMENT_GUIDE.md`
- Specification: `/docs/BASE_BOOTSTRAP_SPECIFICATION.md`
- Example: `/modules/example-module/index.php`

---

**Print this card and keep it handy!** 📌
