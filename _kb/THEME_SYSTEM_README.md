# Admin UI Theme System

## 🎨 Overview

The Admin UI now has a **complete theme system** that allows switching between different visual themes without any code conflicts. Built on top of the modular theme architecture in `modules/base/_templates/themes/`.

## ✅ What's Been Set Up

### 1. **Theme Manager** (`app/ThemeManager.php`)
- Singleton pattern for global theme access
- Automatic theme discovery from `modules/base/_templates/themes/`
- Session-based theme persistence
- URL parameter theme switching (`?switch_theme=theme-name`)

### 2. **Helper Functions** (`app/theme_helpers.php`)
- Simple, intuitive API for theme operations
- Global functions: `theme()`, `currentTheme()`, `theme_page_start()`, etc.
- No need to instantiate classes manually

### 3. **Bootstrap File** (`bootstrap.php`)
- Single include to load entire theme system
- Handles session, security headers, theme initialization
- Optional theme switching via URL

### 4. **CIS Classic Theme** (`modules/base/_templates/themes/cis-classic/`)
- Complete rebuild of original CIS template
- Better structure, cleaner code
- Identical look and feel
- Modular components (header, sidebar, footer, etc.)

## 🚀 Quick Start

### Basic Page Structure

```php
<?php
// Load admin-ui bootstrap
require_once __DIR__ . '/bootstrap.php';

// Start themed page
theme_page_start('My Page Title', 'my-page-slug');
?>

<!-- Your page content -->
<div class="container-fluid">
    <h1>My Page</h1>
    <p>Content goes here...</p>
</div>

<?php
// End themed page
theme_page_end();
?>
```

That's it! The theme system handles:
- HTML head with all CSS/JS
- Header with navigation
- Sidebar with menu
- Footer with scripts
- Responsive layout
- Session management

## 📚 Helper Functions

| Function | Purpose | Example |
|----------|---------|---------|
| `theme()` | Get ThemeManager instance | `$manager = theme();` |
| `currentTheme()` | Get active theme object | `$t = currentTheme();` |
| `theme_page_start()` | Render page header/sidebar | `theme_page_start('Title', 'slug');` |
| `theme_page_end()` | Render page footer | `theme_page_end();` |
| `theme_render()` | Render specific component | `theme_render('header');` |
| `theme_add_head()` | Add custom CSS/JS | `theme_add_head('<style>...</style>');` |
| `theme_config()` | Get theme config value | `$url = theme_config('base_url');` |

## 🎭 Switching Themes

### Via Code
```php
theme()->switchTheme('cis-classic');
```

### Via URL
```
?switch_theme=cis-classic
```

### Programmatically
```php
$_SESSION['admin_ui_theme'] = 'cis-classic';
```

Theme selection persists across sessions automatically.

## 🏗️ Creating New Themes

1. Create directory: `modules/base/_templates/themes/my-theme/`

2. Create `theme.php`:
```php
<?php
class MyTheme {
    public function render(string $component, array $data = []): void {
        include __DIR__ . '/components/' . $component . '.php';
    }

    public function setTitle(string $title): void { ... }
    public function setCurrentPage(string $page): void { ... }
    public function getConfig(string $key, $default = null) { ... }
}
```

3. Create components:
   - `components/html-head.php`
   - `components/header.php`
   - `components/sidebar.php`
   - `components/main-start.php`
   - `components/footer.php`

4. Theme will be auto-discovered on next page load!

## 🔧 Configuration

Edit `app/ThemeManager.php` to change defaults:

```php
private function loadConfig(): void {
    $this->config = [
        'themes_path' => $_SERVER['DOCUMENT_ROOT'] . '/modules/base/_templates/themes',
        'default_theme' => 'cis-classic',  // ← Change default here
        'theme_session_key' => 'admin_ui_theme',
    ];
}
```

## 📂 File Structure

```
admin-ui/
├── bootstrap.php                # Main bootstrap (include this)
├── theme-demo.php              # Working demo page
├── app/
│   ├── ThemeManager.php        # Theme manager class
│   └── theme_helpers.php       # Helper functions
└── ...

modules/base/_templates/themes/
├── cis-classic/                # CIS Classic theme
│   ├── theme.php              # Theme class
│   ├── components/            # Theme components
│   │   ├── html-head.php
│   │   ├── header.php
│   │   ├── sidebar.php
│   │   ├── main-start.php
│   │   └── footer.php
│   ├── README.md              # Theme docs
│   └── demo.php               # Theme demo
└── [your-theme]/              # Add more themes here
```

## 🧪 Testing

1. **View Demo**: Navigate to `/admin-ui/theme-demo.php`
2. **Switch Themes**: Click theme buttons to test switching
3. **Create Test Page**: Use the example above

## ✨ Features

- ✅ **Zero Conflicts**: Themes are completely isolated
- ✅ **Hot Switching**: Change themes without restarting
- ✅ **Auto Discovery**: New themes detected automatically
- ✅ **Session Persistence**: Theme choice saved
- ✅ **Clean API**: Simple, intuitive functions
- ✅ **Modular**: Easy to extend and customize
- ✅ **Backward Compatible**: Works with existing code

## 🎯 Benefits

### For Developers
- No more template include spaghetti
- Consistent page structure
- Easy theme customization
- Reusable components

### For Users
- Familiar interface
- Consistent experience
- Fast page loads
- No learning curve

### For Modules
- Any module can use any theme
- Theme inheritance
- No code duplication
- Maintainable architecture

## 🔄 Migration Guide

### Old Way (Before)
```php
include($_SERVER['DOCUMENT_ROOT'] . '/assets/template/html-header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/assets/template/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/assets/template/sidemenu.php');
echo '<div class="app-body"><main class="main">';
// content
echo '</main></div>';
include($_SERVER['DOCUMENT_ROOT'] . '/assets/template/footer.php');
```

### New Way (After)
```php
require_once __DIR__ . '/bootstrap.php';
theme_page_start('Page Title', 'page-slug');
// content
theme_page_end();
```

**Result**: 9 lines → 3 lines, cleaner, more maintainable!

## 📖 Examples

### Example 1: Simple Page
```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin-ui/bootstrap.php';
theme_page_start('Dashboard', 'dashboard');
?>
<div class="container-fluid">
    <h1>Dashboard</h1>
    <p>Welcome to CIS!</p>
</div>
<?php theme_page_end(); ?>
```

### Example 2: Custom CSS/JS
```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin-ui/bootstrap.php';

// Add custom styles
theme_add_head('
<style>
    .my-custom { color: red; }
</style>
<script src="/custom.js"></script>
');

theme_page_start('Custom Page', 'custom');
?>
<!-- content -->
<?php theme_page_end(); ?>
```

### Example 3: Manual Component Rendering
```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin-ui/bootstrap.php';

$theme = currentTheme();
$theme->setTitle('Manual Rendering');

// Render components individually
$theme->render('html-head');
$theme->render('header');
// Skip sidebar if needed
$theme->render('main-start');
?>
<!-- content without sidebar -->
<?php
$theme->render('footer');
?>
```

## 🐛 Troubleshooting

**Theme not loading?**
- Check `modules/base/_templates/themes/` exists
- Verify `theme.php` exists in theme directory
- Check class name matches directory name pattern

**Session not persisting?**
- Ensure `session_start()` is called
- Check session cookies are enabled
- Verify theme name is valid

**Components not rendering?**
- Check component files exist in `components/` directory
- Verify component names are correct (case-sensitive)
- Check file permissions

## 🎓 Advanced Usage

### Access Theme Config
```php
$baseUrl = theme_config('base_url');
$version = theme_config('theme_version', '1.0.0');
```

### Get Theme Info
```php
$themeName = theme()->getCurrentThemeName();
$themes = theme()->getAvailableThemes();
```

### Custom Theme Methods
```php
$theme = currentTheme();
if (method_exists($theme, 'customMethod')) {
    $theme->customMethod();
}
```

## 📝 Notes

- Themes are loaded once per request (singleton)
- Theme switching requires page reload
- Themes can share common assets
- Each theme controls its own HTML structure

---

**Version**: 1.0.0
**Created**: November 4, 2025
**Status**: ✅ Production Ready
**Demo**: `/admin-ui/theme-demo.php`
