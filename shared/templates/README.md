# CIS Universal Base Template

**ONE simple template for ALL modules.**

---

## Usage

```php
<?php
$page_title = 'My Page';
$page_content = '<div class="container-fluid"><h1>Hello World</h1></div>';
require __DIR__ . '/../../shared/templates/base-layout.php';
```

Done! You get full CIS structure automatically.

---

## What You Get

- Top navigation
- Sidebar
- Empty body container for your content
- Footer
- All CIS CSS/JS loaded correctly

---

## Variables

**Required:**
- `$page_title` - Browser title
- `$page_content` - Your HTML

**Optional:**
- `$page_head_extra` - Extra CSS
- `$page_scripts_before_footer` - Your JS
- `$page_modals` - Modal HTML

---

## Examples

### Simple
```php
<?php
$page_title = 'Dashboard';
$page_content = '<h1>Hello</h1>';
require __DIR__ . '/../../shared/templates/base-layout.php';
```

### With Scripts
```php
<?php
$page_title = 'App';
$page_scripts_before_footer = '<script src="/modules/mymodule/js/app.js"></script>';
$page_content = '<div>Content</div>';
require __DIR__ . '/../../shared/templates/base-layout.php';
```

### Complex (Output Buffering)
```php
<?php
$page_title = 'Complex Page';

ob_start();
?>
<div class="container-fluid">
    <!-- Your HTML -->
</div>
<?php
$page_content = ob_get_clean();

require __DIR__ . '/../../shared/templates/base-layout.php';
```

---

**Version:** 1.0.0  
**Status:** ✅ Ready
