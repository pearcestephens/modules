# CIS Modules - Template Architecture

**Date:** October 12, 2025  
**Status:** ✅ PRODUCTION READY

---

## 🎯 **Overview**

Modules use a **wrapper approach** - they include the real CIS template components from `/assets/template/` rather than recreating the UI.

---

## 📁 **File Structure**

```
/assets/template/          ← Main CIS templates (OUTSIDE modules)
├── html-header.php        # <head> + CSS + jQuery
├── header.php             # Top navbar (logo, user menu)
├── sidemenu.php           # Left sidebar navigation
├── html-footer.php        # Scripts (Bootstrap, CoreUI)
├── footer.php             # Copyright footer
└── (other CIS components)

/modules/base/             ← Module infrastructure
├── views/
│   └── layouts/
│       └── master.php     # ✅ ONE template (wrapper only)
└── lib/
    └── Controller/
        └── PageController.php  # Uses master.php
```

---

## 🏗️ **How It Works**

### **1. Master Template (wrapper)**
**File:** `/modules/base/views/layouts/master.php`

```php
<?php
// Simple wrapper that includes CIS components
$templateRoot = $_SERVER['DOCUMENT_ROOT'] . '/assets/template';

include $templateRoot . '/html-header.php';  // <head> + CSS
?>
<body>
    <?php include $templateRoot . '/header.php'; ?>      <!-- Navbar -->
    
    <div class="app-body">
        <?php include $templateRoot . '/sidemenu.php'; ?> <!-- Sidebar -->
        
        <main class="main">
            <ol class="breadcrumb">...</ol>               <!-- Breadcrumbs -->
            <div class="container-fluid">
                <?= $content ?>                           <!-- MODULE CONTENT HERE -->
            </div>
        </main>
    </div>
    
    <?php include $templateRoot . '/html-footer.php'; ?>  <!-- Scripts -->
    <?php include $templateRoot . '/footer.php'; ?>       <!-- Footer -->
</body>
```

---

## ✅ **Key Principles**

### **1. DRY (Don't Repeat Yourself)**
- ✅ Header/footer/sidebar defined ONCE in `/assets/template/`
- ✅ Modules just wrap content, don't recreate UI
- ❌ NO duplication of navbar/sidebar code

### **2. Single Source of Truth**
- ✅ Update `/assets/template/header.php` → ALL modules updated
- ✅ CSS/JS stays in `/assets/css/` and `/assets/js/`
- ✅ Navigation controlled by main CIS (not hardcoded in modules)

### **3. Separation of Concerns**
```
/assets/template/    = CIS UI components (navbar, sidebar, footer)
/assets/css/         = Global styles (CoreUI, Bootstrap, custom)
/assets/js/          = Global scripts (jQuery, CoreUI)
/modules/base/       = Module wrapper template
/modules/MODULE/     = Module-specific content only
```

---

## 📖 **Usage Examples**

### **Example 1: Simple Module Page**
```php
<?php
// modules/example/index.php

// 1. Define context
define('CIS_MODULE_CONTEXT', true);

// 2. Set variables
$pageTitle = 'Example Module';
$breadcrumbs = [
    ['label' => 'Home', 'href' => '/'],
    ['label' => 'Example', 'active' => true]
];

// 3. Capture content
ob_start();
?>
<div class="card">
    <div class="card-header">
        <h1><?= $pageTitle ?></h1>
    </div>
    <div class="card-body">
        <p>Your module content here...</p>
    </div>
</div>
<?php
$content = ob_get_clean();

// 4. Include template
include __DIR__ . '/../base/views/layouts/master.php';
```

---

### **Example 2: With Module-Specific CSS/JS**
```php
<?php
define('CIS_MODULE_CONTEXT', true);

$pageTitle = 'Advanced Module';
$moduleCSS = [
    '/modules/example/assets/css/custom.css',
    '/modules/example/assets/css/forms.css'
];
$moduleJS = [
    '/modules/example/assets/js/app.js'
];

ob_start();
?>
<!-- Your HTML -->
<?php
$content = ob_get_clean();
include __DIR__ . '/../base/views/layouts/master.php';
```

---

### **Example 3: Using PageController**
```php
<?php
// modules/example/controllers/ExampleController.php

namespace Modules\Example\Controllers;

use Modules\Base\Controller\PageController;

class ExampleController extends PageController
{
    public function index(): void
    {
        // Automatically uses master.php template
        echo $this->view(__DIR__ . '/../views/index.php', [
            'pageTitle' => 'Example',
            'data' => ['key' => 'value']
        ]);
    }
}
```

---

## 🔧 **Template Variables**

### **Required:**
- `$content` - Your module HTML (string)

### **Optional:**
```php
$pageTitle    = 'Page Title';              // Browser <title>
$pageBlurb    = 'Description';             // Meta description
$breadcrumbs  = [                          // Breadcrumb trail
    ['label' => 'Home', 'href' => '/'],
    ['label' => 'Current', 'active' => true]
];
$bodyClass    = 'custom-class';            // <body> CSS class
$moduleCSS    = ['/path/to/style.css'];    // Module CSS files
$moduleJS     = ['/path/to/script.js'];    // Module JS files
$extraHead    = '<meta name="...">';       // Extra <head> content
```

---

## 🎨 **Asset Management**

### **Global Assets (Stay in /assets/)**
```
/assets/css/
├── style1.css                    # CoreUI v2 + Bootstrap 4
├── bootstrap-compatibility.css   # Compatibility layer
├── custom.css                    # CIS custom styles
├── sidebar-fixes.css             # Sidebar adjustments
└── sidebar-dark-theme-restore.css

/assets/js/
└── (loaded by html-footer.php)
```

### **Module-Specific Assets**
```
/modules/MODULE/assets/
├── css/
│   └── module.css               # Module-specific styles
└── js/
    └── module.js                # Module-specific scripts
```

**How to include:**
```php
$moduleCSS = ['/modules/consignments/assets/css/pack.css'];
$moduleJS = ['/modules/consignments/assets/js/pack.js'];
```

---

## 🔐 **Security**

### **Context Check:**
```php
if (!defined('CIS_MODULE_CONTEXT')) {
    http_response_code(403);
    exit('Direct access forbidden');
}
```

### **Safe Variable Extraction:**
```php
$pageTitle = $pageTitle ?? $page_title ?? 'CIS Module';
$content = $content ?? $__cisContent ?? '';
```

### **Output Escaping:**
```php
<?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?>
```

---

## 📊 **Benefits**

| Aspect | Old Approach | New Approach |
|--------|-------------|--------------|
| **Lines of code** | 418 lines (2 files) | 100 lines (1 file) |
| **Header/Footer** | Recreated in modules | Includes from /assets/ |
| **Navigation** | Hardcoded | Dynamic (CIS controlled) |
| **Maintenance** | Update multiple files | Update one source |
| **Consistency** | Can drift out of sync | Always matches CIS |
| **Updates** | Manual sync needed | Automatic |

---

## ✅ **Checklist for New Modules**

- [ ] Define `CIS_MODULE_CONTEXT` constant
- [ ] Set `$pageTitle` and `$content` variables
- [ ] Use `ob_start()` / `ob_get_clean()` for content capture
- [ ] Include `/base/views/layouts/master.php`
- [ ] Test: Header/sidebar/footer match main CIS
- [ ] Verify navigation works
- [ ] Check breadcrumbs display correctly

---

## 🚫 **Anti-Patterns (DON'T DO THIS)**

### **❌ Don't recreate the UI:**
```php
// WRONG - Don't do this!
echo '<header class="app-header">...';
echo '<aside class="sidebar">...';
```

### **❌ Don't hardcode navigation:**
```php
// WRONG - Don't do this!
<ul class="nav">
    <li><a href="/page1">Page 1</a></li>
    <li><a href="/page2">Page 2</a></li>
</ul>
```

### **✅ Do this instead:**
```php
// RIGHT - Include the template
include __DIR__ . '/../base/views/layouts/master.php';
```

---

## 🎯 **Summary**

1. **One Template** - `/modules/base/views/layouts/master.php`
2. **Includes CIS Components** - From `/assets/template/`
3. **CSS/JS in /assets/** - Stays outside modules (global)
4. **Module Content Only** - Modules provide `$content`, not full HTML
5. **DRY Principle** - Don't repeat header/footer/sidebar code

---

## 📚 **Related Documentation**

- See: `TEMPLATE_FIXED.md` - Migration details
- See: `ERROR_HANDLER_GUIDE.md` - Error handling
- See: `CODING_STANDARDS.md` - Code quality

---

**Status:** ✅ **PRODUCTION READY - Use this architecture for all modules!**
