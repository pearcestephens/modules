# Auto Asset Loader - Complete Guide

**Location:** `/modules/shared/functions/auto-load-assets.php`  
**Features:** Automatically loads CSS and JavaScript files from module folders

---

## 🎯 What It Does

Automatically discovers and includes CSS and JS files in the correct order:

1. **Shared** assets (`/modules/shared/css/` and `/modules/shared/js/`)
2. **Module** assets (`/modules/{module}/css/` and `/modules/{module}/js/`)
3. **Page-specific** assets (`/modules/{module}/{subfolder}/css/` and `/modules/{module}/{subfolder}/js/`)
4. **Additional** custom paths you specify

---

## 🚀 Quick Start

### Load Both CSS and JS
```php
<?php
require_once __DIR__ . '/../../shared/functions/auto-load-assets.php';

list($css, $js) = autoLoadModuleAssets(__FILE__);

$page_title = 'My Page';
$page_head_extra = $css;
$page_scripts_before_footer = $js;
$page_content = '<div>Content</div>';

require __DIR__ . '/../../shared/templates/base-layout.php';
```

### Load CSS Only
```php
<?php
require_once __DIR__ . '/../../shared/functions/auto-load-assets.php';

$page_head_extra = autoLoadModuleCSS(__FILE__);
```

### Load JS Only
```php
<?php
require_once __DIR__ . '/../../shared/functions/auto-load-assets.php';

$page_scripts_before_footer = autoLoadModuleJS(__FILE__);
```

---

## 📁 How It Discovers Files

### Example Structure
```
modules/
├── shared/
│   ├── css/
│   │   ├── common.css       ← Loaded first (all pages)
│   │   └── utilities.css    ← Loaded first (all pages)
│   └── js/
│       ├── ajax-manager.js  ← Loaded first (all pages)
│       └── cis-toast.js     ← Loaded first (all pages)
│
└── consignments/
    ├── css/
    │   └── module.css        ← Loaded second (consignments pages only)
    ├── js/
    │   └── module.js         ← Loaded second (consignments pages only)
    └── stock-transfers/
        ├── css/
        │   └── pack.css      ← Loaded third (pack.php only)
        └── js/
            └── pack.js       ← Loaded third (pack.php only)
```

### Loading Order
```
1. /modules/shared/css/common.css
2. /modules/shared/css/utilities.css
3. /modules/consignments/css/module.css
4. /modules/consignments/stock-transfers/css/pack.css

Then for JS:
5. /modules/shared/js/ajax-manager.js
6. /modules/shared/js/cis-toast.js
7. /modules/consignments/js/module.js
8. /modules/consignments/stock-transfers/js/pack.js
```

---

## 🎛️ Options

### CSS Options
```php
$css = autoLoadModuleCSS(__FILE__, [
    'additional' => ['/custom/path.css'],       // Extra CSS files
    'exclude' => ['old.css', 'broken.css'],     // Skip these files
    'minified' => true,                          // Prefer .min.css
    'cache_bust' => true,                        // Add ?v=timestamp
]);
```

### JS Options
```php
$js = autoLoadModuleJS(__FILE__, [
    'additional' => ['/custom/script.js'],       // Extra JS files
    'exclude' => ['old.js', 'test.js'],         // Skip these files
    'minified' => true,                          // Prefer .min.js
    'cache_bust' => true,                        // Add ?v=timestamp
    'defer' => true,                             // Add defer attribute
    'async' => false,                            // Add async attribute
]);
```

### Both at Once
```php
list($css, $js) = autoLoadModuleAssets(__FILE__, [
    'css' => [
        'additional' => ['/extra.css'],
        'exclude' => ['old.css'],
    ],
    'js' => [
        'additional' => ['/extra.js'],
        'defer' => true,
    ]
]);
```

---

## 💡 Usage Examples

### Example 1: Simple Page (Auto Everything)
```php
<?php
require_once __DIR__ . '/../../shared/functions/auto-load-assets.php';

// Auto-load everything
list($css, $js) = autoLoadModuleAssets(__FILE__);

$page_title = 'Dashboard';
$page_head_extra = $css;
$page_scripts_before_footer = $js;
$page_content = '<div class="container-fluid"><h1>Dashboard</h1></div>';

require __DIR__ . '/../../shared/templates/base-layout.php';
```

**Result:** All CSS/JS from shared/ and current module auto-loaded!

---

### Example 2: Add Extra Libraries
```php
<?php
require_once __DIR__ . '/../../shared/functions/auto-load-assets.php';

// Auto-load + add DataTables
list($css, $js) = autoLoadModuleAssets(__FILE__, [
    'css' => [
        'additional' => ['https://cdn.datatables.net/1.13.4/css/jquery.dataTables.min.css']
    ],
    'js' => [
        'additional' => ['https://cdn.datatables.net/1.13.4/js/jquery.dataTables.min.js']
    ]
]);

$page_title = 'Products Table';
$page_head_extra = $css;
$page_scripts_before_footer = $js . "\n<script>$('.data-table').DataTable();</script>";
$page_content = '<table class="data-table">...</table>';

require __DIR__ . '/../../shared/templates/base-layout.php';
```

---

### Example 3: Exclude Broken Files
```php
<?php
require_once __DIR__ . '/../../shared/functions/auto-load-assets.php';

// Auto-load but skip broken files
list($css, $js) = autoLoadModuleAssets(__FILE__, [
    'js' => [
        'exclude' => ['old-broken-script.js', 'test.js']
    ]
]);

$page_title = 'Clean Page';
$page_head_extra = $css;
$page_scripts_before_footer = $js;
$page_content = '<div>Content</div>';

require __DIR__ . '/../../shared/templates/base-layout.php';
```

---

### Example 4: Defer All Scripts (Performance)
```php
<?php
require_once __DIR__ . '/../../shared/functions/auto-load-assets.php';

// Load CSS normally, defer all JS for faster page load
list($css, $js) = autoLoadModuleAssets(__FILE__, [
    'js' => [
        'defer' => true  // Adds defer to all <script> tags
    ]
]);

$page_title = 'Fast Page';
$page_head_extra = $css;
$page_scripts_before_footer = $js;
$page_content = '<div>Content loads fast!</div>';

require __DIR__ . '/../../shared/templates/base-layout.php';
```

---

### Example 5: Production (Minified + Cache Busting)
```php
<?php
require_once __DIR__ . '/../../shared/functions/auto-load-assets.php';

// Use minified files with cache busting
list($css, $js) = autoLoadModuleAssets(__FILE__, [
    'css' => [
        'minified' => true,      // Load .min.css if available
        'cache_bust' => true,    // Add ?v=1697451234
    ],
    'js' => [
        'minified' => true,
        'cache_bust' => true,
        'defer' => true,
    ]
]);

$page_title = 'Production Page';
$page_head_extra = $css;
$page_scripts_before_footer = $js;
$page_content = '<div>Optimized!</div>';

require __DIR__ . '/../../shared/templates/base-layout.php';
```

---

## 📊 What Gets Generated

### CSS Output
```html
<!-- Auto-loaded CSS (3 files) -->
<link rel="stylesheet" href="/modules/shared/css/common.css?v=1697451234" data-source="Shared CSS">
<link rel="stylesheet" href="/modules/consignments/css/module.css?v=1697451234" data-source="Module CSS">
<link rel="stylesheet" href="/modules/consignments/stock-transfers/css/pack.css?v=1697451234" data-source="Page-specific CSS">
<!-- End Auto-loaded CSS -->
```

### JS Output
```html
<!-- Auto-loaded JavaScript (3 files) -->
<script src="/modules/shared/js/ajax-manager.js?v=1697451234" data-source="Shared JavaScript"></script>
<script src="/modules/consignments/js/module.js?v=1697451234" data-source="Module JavaScript"></script>
<script src="/modules/consignments/stock-transfers/js/pack.js?v=1697451234" data-source="Page-specific JavaScript"></script>
<!-- End Auto-loaded JavaScript -->
```

### With Defer
```html
<script src="/modules/shared/js/ajax-manager.js?v=1697451234" defer data-source="Shared JavaScript"></script>
```

---

## 🎯 Benefits

### For Developers
✅ **No manual includes** - Just call the function  
✅ **Automatic discovery** - Finds all CSS/JS in your module  
✅ **Correct order** - Shared → Module → Page  
✅ **Easy to debug** - `data-source` shows where files came from  
✅ **Flexible** - Add extra files or exclude broken ones  

### For Performance
✅ **Cache busting** - Auto-adds timestamps  
✅ **Minified support** - Prefers .min files in production  
✅ **Defer/Async** - Control script loading behavior  

### For Maintenance
✅ **One place to update** - Change loading logic once  
✅ **No hardcoded paths** - Adapts to file structure  
✅ **Clear naming** - HTML comments show what loaded  

---

## 🔍 Debugging

### See What Files Were Found
Add this temporarily:
```php
<?php
require_once __DIR__ . '/../../shared/functions/auto-load-assets.php';

list($css, $js) = autoLoadModuleAssets(__FILE__);

// Debug output
echo "<!-- CSS FILES:\n" . $css . "\n-->";
echo "<!-- JS FILES:\n" . $js . "\n-->";

$page_head_extra = $css;
$page_scripts_before_footer = $js;
```

View page source and check HTML comments.

### Check Browser Console
Files will have `data-source` attribute:
```html
<script src="..." data-source="Shared JavaScript"></script>
```

Right-click → Inspect to see which files loaded.

---

## ✅ Best Practices

### DO:
✅ Call auto-loader early in your page  
✅ Use output buffering for complex pages  
✅ Organize files in css/ and js/ folders  
✅ Use meaningful filenames (pack.js, module.css)  
✅ Test with browser DevTools Network tab  

### DON'T:
❌ Don't mix auto-loader with manual includes (pick one)  
❌ Don't put CSS in js/ folder or vice versa  
❌ Don't use spaces in filenames  
❌ Don't include files that aren't needed  

---

## 🚀 Migration Guide

### Before (Manual Includes)
```php
<?php
$page_head_extra = '
<link rel="stylesheet" href="/modules/shared/css/common.css">
<link rel="stylesheet" href="/modules/consignments/css/module.css">
<link rel="stylesheet" href="/modules/consignments/stock-transfers/css/pack.css">
';

$page_scripts_before_footer = '
<script src="/modules/shared/js/ajax-manager.js"></script>
<script src="/modules/consignments/js/module.js"></script>
<script src="/modules/consignments/stock-transfers/js/pack.js"></script>
';
```

### After (Auto-Loaded)
```php
<?php
require_once __DIR__ . '/../../shared/functions/auto-load-assets.php';

list($css, $js) = autoLoadModuleAssets(__FILE__);

$page_head_extra = $css;
$page_scripts_before_footer = $js;
```

**3 lines instead of 15!**

---

## 📝 Summary

**Functions Available:**
- `autoLoadModuleCSS(__FILE__)` - CSS only
- `autoLoadModuleJS(__FILE__)` - JS only
- `autoLoadModuleAssets(__FILE__)` - Both at once

**What It Finds:**
- `/modules/shared/css/` and `/modules/shared/js/`
- `/modules/{your-module}/css/` and `/modules/{your-module}/js/`
- `/modules/{your-module}/{subfolder}/css/` and `/modules/{your-module}/{subfolder}/js/`

**Loading Order:**
1. Shared assets (all pages)
2. Module assets (module pages only)
3. Page assets (specific page only)
4. Additional assets (your custom adds)

---

**Version:** 2.0.0  
**Status:** ✅ Production Ready  
**Location:** `/modules/shared/functions/auto-load-assets.php`
