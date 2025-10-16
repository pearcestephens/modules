# Template System - Quick Reference Card

## 🎯 Creating a New Page (5 Steps)

```php
<?php
// 1. INIT
require_once __DIR__ . '/../bootstrap.php';

// 2. BUSINESS LOGIC
$data = loadYourData();

// 3. TEMPLATE CONFIG
$page_title = 'Your Page Title';
$page_scripts_before_footer = '<script src="your-script.js"></script>';

// 4. CAPTURE CONTENT
ob_start();
?>
<div class="container-fluid">
    <!-- Your HTML -->
</div>
<?php
$page_content = ob_get_clean();

// 5. RENDER
require __DIR__ . '/../shared/templates/base-layout.php';
```

**That's it!** No HTML structure to manage.

---

## 📦 Template Variables Reference

| Variable | Required | Default | Purpose |
|----------|----------|---------|---------|
| `$page_title` | ✅ Yes | 'CIS - Consignments' | Browser title + H1 |
| `$page_content` | ✅ Yes | '' | Main page HTML |
| `$body_class` | ❌ No | 'app header-fixed...' | Body CSS classes |
| `$page_head_extra` | ❌ No | '' | Extra CSS/meta tags |
| `$page_scripts_before_footer` | ❌ No | '' | Page-specific JS |
| `$page_modals` | ❌ No | '' | Modal HTML |
| `$page_overlays` | ❌ No | '' | Overlay HTML |
| `$show_breadcrumb` | ❌ No | `true` | Show/hide breadcrumb |
| `$breadcrumb_items` | ❌ No | `[]` | Breadcrumb links |

---

## 🔧 Common Patterns

### Simple Page
```php
$page_title = 'Dashboard';
$page_content = '<div class="container"><h2>Hello</h2></div>';
require __DIR__ . '/../shared/templates/base-layout.php';
```

### Page with Scripts
```php
$page_scripts_before_footer = <<<HTML
<script src="/path/to/script1.js"></script>
<script src="/path/to/script2.js"></script>
HTML;
$page_content = '...';
require __DIR__ . '/../shared/templates/base-layout.php';
```

### Page with Modal
```php
ob_start();
?><div class="modal" id="myModal">...</div><?php
$page_modals = ob_get_clean();

$page_content = '...';
require __DIR__ . '/../shared/templates/base-layout.php';
```

### Page with Custom Breadcrumb
```php
$breadcrumb_items = [
    ['label' => 'Home', 'url' => '/'],
    ['label' => 'Section', 'url' => '/section'],
    ['label' => 'Current Page']
];
$page_content = '...';
require __DIR__ . '/../shared/templates/base-layout.php';
```

### Page without Breadcrumb
```php
$show_breadcrumb = false;
$page_content = '...';
require __DIR__ . '/../shared/templates/base-layout.php';
```

---

## 📜 Script Loading Order (Guaranteed)

```
1. jQuery 3.x                    ← html-header.php
2. Your page CSS                 ← $page_head_extra
3. Your page scripts             ← $page_scripts_before_footer
4. Popper.js 1.16.1             ← html-footer.php
5. Bootstrap 4.2.0              ← html-footer.php
6. CoreUI 3.4.0                 ← html-footer.php
7. jQuery UI 1.13.2             ← html-footer.php
8. Template scripts (main.js)   ← footer.php
```

**Rule:** Your scripts load AFTER jQuery, BEFORE Bootstrap/CoreUI.

---

## 🚨 Troubleshooting

### "$ is not defined"
**Cause:** Script loading before jQuery  
**Fix:** Move script to `$page_scripts_before_footer` (not in `$page_content`)

### "Modal not showing"
**Cause:** Modal HTML in wrong place  
**Fix:** Capture modal in `$page_modals`, not in `$page_content`

### "Sidebar missing"
**Cause:** Missing `<div class="app-body">` structure  
**Fix:** Don't create `<div class="app-body">` in your content - base template does this

### "Page content not showing"
**Cause:** Forgot to set `$page_content`  
**Fix:** Use `ob_start()` → HTML → `$page_content = ob_get_clean()`

### "Scripts not loading"
**Cause:** Typo in variable name  
**Fix:** Check spelling: `$page_scripts_before_footer` (not `$page_scripts`)

---

## ✅ Do's

✅ Set all template variables BEFORE `require base-layout.php`  
✅ Use output buffering for content sections  
✅ Put modals in `$page_modals`, not `$page_content`  
✅ Keep business logic separate from HTML  
✅ Use `ROOT_PATH` for includes  
✅ Load scripts via `$page_scripts_before_footer`  

---

## ❌ Don'ts

❌ Don't include html-header.php or header.php manually  
❌ Don't create `<body>` tags in your content  
❌ Don't close `</body></html>` in your page  
❌ Don't load jQuery yourself  
❌ Don't put modals inside `<main>` tag  
❌ Don't include html-footer.php multiple times  

---

## 🎨 HTML Structure (Automatic)

```html
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- CIS CSS + jQuery -->
    <!-- $page_head_extra goes here -->
</head>
<body class="<?php echo $body_class; ?>">
    <!-- Top navigation -->
    <div class="app-body">
        <!-- Sidebar -->
        <main class="main">
            <!-- Breadcrumb (optional) -->
            <!-- $page_content goes here -->
        </main>
        <!-- Personalisation menu -->
    </div>
    <!-- $page_scripts_before_footer goes here -->
    <!-- Bootstrap, CoreUI, jQuery UI -->
    <!-- Template scripts -->
    <!-- $page_modals goes here -->
    <!-- $page_overlays goes here -->
</body>
</html>
```

You provide `$page_content`, base template does the rest.

---

## 📁 File Locations

```
modules/consignments/
├── shared/
│   └── templates/
│       └── base-layout.php       ← Base template (use this)
│
└── your-module/
    └── your-page.php             ← Your page (create here)
```

**Include path:**
```php
require __DIR__ . '/../shared/templates/base-layout.php';
```

---

## 🔍 Debug Checklist

If page doesn't work:

1. **Check console (F12)** - Any JavaScript errors?
2. **View source** - Is HTML structure correct?
3. **Check variables** - Did you set `$page_title` and `$page_content`?
4. **Check paths** - Are script `src=""` paths correct?
5. **Check buffering** - Did you use `ob_start()` and `ob_get_clean()`?
6. **Check template** - Did you `require base-layout.php` at end?

---

## 💡 Pro Tips

### Reusable Content Blocks
```php
// Create reusable function
function renderActionButtons($id) {
    return <<<HTML
    <button class="btn btn-primary" data-id="$id">Edit</button>
    <button class="btn btn-danger" data-id="$id">Delete</button>
HTML;
}

// Use in content
ob_start();
?>
<div><?php echo renderActionButtons(123); ?></div>
<?php
$page_content = ob_get_clean();
```

### Multiple Script Files
```php
$scripts = [
    '/modules/consignments/shared/js/ajax-manager.js',
    '/modules/consignments/shared/js/cis-toast.js',
    '/modules/consignments/stock-transfers/js/pack.js',
];
$page_scripts_before_footer = implode("\n", array_map(
    fn($s) => "<script src=\"$s\"></script>",
    $scripts
));
```

### Conditional Modals
```php
$modals = [];
if ($showEditModal) {
    $modals[] = '<div class="modal" id="editModal">...</div>';
}
if ($showDeleteModal) {
    $modals[] = '<div class="modal" id="deleteModal">...</div>';
}
$page_modals = implode("\n", $modals);
```

---

## 📊 Template System Benefits

| Before | After |
|--------|-------|
| 685 lines mixed code | 491 lines separated code |
| Body tag in wrong place | Body tag correct |
| Scripts load wrong order | Scripts load right order |
| Duplicate includes | Single includes |
| Modals in wrong place | Modals placed correctly |
| $ undefined errors | jQuery always available |
| Broken validation | Validation works |
| Broken modals | Modals work |
| Broken auto-save | Auto-save works |
| Unmaintainable | Maintainable |

---

## 🚀 Performance

- ✅ **No overhead** - Simple variable substitution
- ✅ **No parsing** - Plain PHP includes
- ✅ **No caching needed** - Fast by default
- ✅ **Minimal memory** - Output buffering is efficient

---

## 📚 Further Reading

- `TEMPLATE_REFACTOR_DOCS.md` - Complete documentation
- `TEMPLATE_VISUAL_GUIDE.md` - Before/after visual guide
- `base-layout.php` - Base template source code
- `pack-REFACTORED.php` - Full example implementation

---

## ✨ One-Liner Examples

### Minimal Page
```php
<?php $page_title='Test'; $page_content='<h1>Test</h1>'; require __DIR__.'/../shared/templates/base-layout.php';
```

### With Modal
```php
<?php $page_content='<div>Main</div>'; $page_modals='<div class="modal">...</div>'; require __DIR__.'/../shared/templates/base-layout.php';
```

### With Scripts
```php
<?php $page_scripts_before_footer='<script src="app.js"></script>'; $page_content='<div>App</div>'; require __DIR__.'/../shared/templates/base-layout.php';
```

---

**Last Updated:** October 16, 2025  
**Status:** ✅ Production Ready  
**Version:** 1.0

---

## 🎯 Remember

**The Golden Rule:**
> Set variables → Capture content → Include base-layout.php

**That's all you need to know!**
