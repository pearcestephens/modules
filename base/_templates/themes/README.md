# CIS Modern Theme - Modular Template System

## 📁 Directory Structure

```
themes/
├── modern/                          # Active modern theme
│   ├── components/                  # Reusable template components
│   │   ├── head.php                # <head> section with CSS
│   │   ├── sidebar.php             # Left navigation sidebar
│   │   ├── header.php              # Top header with breadcrumbs
│   │   └── scripts.php             # JavaScript libraries
│   ├── css/                        # Modular stylesheets
│   │   ├── variables.css           # CSS custom properties
│   │   ├── sidebar.css             # Sidebar-specific styles
│   │   ├── header.css              # Header-specific styles
│   │   └── content.css             # Main content area styles
│   ├── js/                         # JavaScript modules
│   │   └── template.js             # Core template interactions
│   └── layouts/                    # Page layout templates
│       └── dashboard.php           # Main dashboard layout
└── legacy/                         # Old templates (archived)
    ├── dashboard.php               # Old dashboard template
    ├── blank.php                   # Old blank template
    ├── card.php                    # Old card template
    ├── split.php                   # Old split template
    └── table.php                   # Old table template
```

## 🚀 Usage

### Basic Usage (All Modules)

```php
<?php
// 1. Capture your page content
ob_start();
?>

<div class="container-fluid">
    <h1>Your Page Content Here</h1>
    <p>Build your page normally...</p>
</div>

<?php
$content = ob_get_clean();

// 2. Set page metadata
$pageTitle = 'My Page Title';
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/', 'icon' => 'fa-home'],
    ['label' => 'My Module', 'url' => '/modules/my-module/', 'active' => true]
];

// 3. Load the template
require_once dirname(__DIR__) . '/base/_templates/themes/modern/layouts/dashboard.php';
```

### With Additional CSS/JS

```php
// Add custom CSS files
$pageCSS = [
    '/modules/my-module/css/custom.css',
    '/assets/css/special.css'
];

// Add custom JS files
$pageJS = [
    '/modules/my-module/js/app.js'
];

// Add inline JavaScript
$inlineScripts = '
    console.log("Page loaded!");
    initMyModule();
';

// Set notification count (optional)
$notificationCount = 5;

// Load template
require_once dirname(__DIR__) . '/base/_templates/themes/modern/layouts/dashboard.php';
```

## 🎨 Theme Features

### Modern Design
- Clean, minimalist interface
- Icon-first navigation
- Smooth animations and transitions
- Responsive mobile layout

### Collapsible Sidebar
- Click hamburger to collapse/expand
- Auto-remembers state (localStorage)
- Tooltips on hover when collapsed
- Mobile-friendly overlay

### Smart Header
- Breadcrumb navigation
- Global search (Ctrl+K shortcut)
- Notification badges
- User profile dropdown

### Modular Components
- Easy to customize individual sections
- CSS organized by feature
- JavaScript separated from HTML
- Reusable across all modules

## 🔧 Customization

### Change Sidebar Menu

Edit `themes/modern/components/sidebar.php`:

```php
<!-- Add new menu item -->
<div class="nav-item">
    <a href="/my-new-page.php" class="nav-link">
        <i class="fas fa-rocket nav-link-icon"></i>
        <span class="nav-link-text">New Feature</span>
        <span class="nav-tooltip">New Feature</span>
    </a>
</div>
```

### Customize Colors

Edit `themes/modern/css/variables.css`:

```css
:root {
    --cis-primary: #007bff;      /* Change primary color */
    --cis-sidebar-bg: #1a1d29;   /* Change sidebar background */
    --cis-header-bg: #ffffff;    /* Change header background */
}
```

### Add Custom Styles

Create module-specific CSS and include via `$pageCSS`:

```php
$pageCSS = ['/modules/my-module/css/custom.css'];
```

## 🏗️ Architecture Benefits

### Before (Monolithic)
- ❌ 911 lines in one file
- ❌ Hard to maintain
- ❌ CSS/JS mixed with HTML
- ❌ Difficult to customize
- ❌ Copy-paste updates required

### After (Modular)
- ✅ Components under 200 lines each
- ✅ Easy to find and edit
- ✅ CSS organized by feature
- ✅ JavaScript separated
- ✅ Update once, applies everywhere

## 📦 Migration Path

### Old Code (Still Works)
```php
require_once dirname(__DIR__) . '/base/_templates/layouts/dashboard-modern.php';
```

### New Code (Recommended)
```php
require_once dirname(__DIR__) . '/base/_templates/themes/modern/layouts/dashboard.php';
```

Both work identically! The old path redirects to the new modular structure.

## 🎯 Component Responsibilities

| Component | Purpose | Lines |
|-----------|---------|-------|
| `head.php` | HTML head, meta tags, CSS links | ~45 |
| `sidebar.php` | Navigation menu | ~160 |
| `header.php` | Top bar, breadcrumbs, search, user | ~55 |
| `scripts.php` | JavaScript libraries and initialization | ~55 |
| `dashboard.php` | Main layout orchestrator | ~50 |

Total: ~365 lines (split into 5 manageable files)

## 🚀 Performance

- CSS split into 4 small files for better caching
- JavaScript extracted for reuse
- No inline styles in components
- Modular loading for better browser optimization

## 📝 Version History

- **v3.0.0** (2025-11-06): Modular refactor, bite-size components
- **v2.0.0**: Original dashboard-modern.php (911 lines, single file)
- **v1.0.0**: Legacy CoreUI-based templates

---

**Created**: November 6, 2025
**Status**: ✅ Production Ready
**Maintained by**: Ecigdis CIS Team
