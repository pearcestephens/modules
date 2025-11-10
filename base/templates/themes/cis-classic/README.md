# CIS Classic Theme - Usage Guide

## Overview

The **CIS Classic Theme** is a rebuilt version of the original CIS template system with better structure, cleaner includes, and modular components. It maintains the exact same look and feel as the original while providing a more maintainable architecture.

## Theme Structure

```
modules/base/_templates/themes/cis-classic/
├── theme.php                    # Main theme class
├── components/
│   ├── html-head.php           # HTML <head> with all CSS/JS
│   ├── header.php              # Top navbar with logo, notifications, user menu
│   ├── sidebar.php             # Dynamic navigation sidebar (database-driven)
│   ├── main-start.php          # Opening <main> tag
│   └── footer.php              # Footer with scripts and closing tags
└── README.md                    # This file
```

## Features

- ✅ **Original CIS Look & Feel** - Identical styling and layout
- ✅ **CoreUI 2.0.0** - Professional admin template framework
- ✅ **Bootstrap 4.1.1** - Responsive grid and components
- ✅ **FontAwesome 6.5.1** - Modern icon set
- ✅ **Database-Driven Navigation** - Dynamic menu from `navigation` and `permissions` tables
- ✅ **User Permissions** - Shows only menu items user has access to
- ✅ **Notification System** - Bell icon with unread count
- ✅ **Session Management** - User greeting, logout link
- ✅ **CSRF Protection** - Token exposed to JavaScript for AJAX
- ✅ **Responsive Design** - Mobile-friendly sidebar toggle

## Quick Start

### Basic Usage (Standalone Page)

```php
<?php
// Include the theme class
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/base/_templates/themes/cis-classic/theme.php';

// Create theme instance
$theme = new CISClassicTheme();

// Set page title
$theme->setTitle('My Page - The Vape Shed');

// Set current page for active menu highlighting
$theme->setCurrentPage('mypage');

// Render the page
$theme->render('html-head');
$theme->render('header');
$theme->render('sidebar');
$theme->render('main-start');
?>

<!-- YOUR PAGE CONTENT HERE -->
<div class="container-fluid">
    <h1>My Page</h1>
    <p>Your content goes here...</p>
</div>

<?php
// Close the page
$theme->render('footer');
?>
```

### Using in a Module

```php
<?php
// In your module bootstrap or controller
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/base/_templates/themes/cis-classic/theme.php';

class MyModuleController {
    private $theme;

    public function __construct() {
        $this->theme = new CISClassicTheme([
            'base_url' => 'https://staff.vapeshed.co.nz/',
        ]);
    }

    public function renderPage() {
        $this->theme->setTitle('Module Page');
        $this->theme->setCurrentPage('module-page');

        // Start page
        $this->theme->render('html-head');
        $this->theme->render('header');
        $this->theme->render('sidebar');
        $this->theme->render('main-start');

        // Include your view
        include __DIR__ . '/../views/my-view.php';

        // End page
        $this->theme->render('footer');
    }
}
```

### Adding Extra CSS/JS to Head

```php
$theme = new CISClassicTheme();

// Add custom styles
$theme->addHeadContent('
<style>
    .my-custom-class {
        color: red;
    }
</style>
');

// Add custom scripts
$theme->addHeadContent('
<script src="/path/to/custom.js"></script>
');

$theme->render('html-head');
```

### Customizing Body Class

```php
$theme = new CISClassicTheme();

// Change body class (e.g., for sidebar collapsed by default)
$theme->setBodyClass('app header-fixed sidebar-fixed sidebar-minimized');

$theme->render('header');
```

## Component Details

### html-head.php
- Outputs complete `<head>` section
- Includes all CSS (CoreUI, Bootstrap, FontAwesome, custom)
- Includes jQuery and Moment.js
- Exposes `staffID` and `CIS_CSRF` to JavaScript
- Allows custom head content injection

### header.php
- Top navigation bar with logo
- Sidebar toggle buttons (mobile and desktop)
- User greeting with first name
- Notification bell with unread count
- User avatar
- Logout link
- Opens `<div class="app-body">` for sidebar + main content

### sidebar.php
- Dynamic navigation from database
- Loads from `navigation` and `permissions` tables
- Only shows items user has permission for
- FontAwesome 6 icons
- Active page highlighting
- Collapsible on mobile

### main-start.php
- Opens `<main class="main">` for page content

### footer.php
- Company info and copyright
- Developer credit
- Bug report button
- Tab persistence script
- Closes all open tags (`</main>`, `</div>`, `</body>`, `</html>`)
- Loads Bootstrap JS, CoreUI JS, jQuery UI

## Database Requirements

The sidebar requires these tables:

**navigation** table:
```sql
CREATE TABLE navigation (
    id INT PRIMARY KEY,
    title VARCHAR(255),
    active TINYINT(1),
    show_title_nav_bar TINYINT(1),
    sort_order INT
);
```

**permissions** table:
```sql
CREATE TABLE permissions (
    id INT PRIMARY KEY,
    name VARCHAR(255),
    filename VARCHAR(255),
    navigation_id INT,
    show_in_sidemenu TINYINT(1)
);
```

**user_permissions** table:
```sql
CREATE TABLE user_permissions (
    user_id INT,
    permission_id INT,
    PRIMARY KEY (user_id, permission_id)
);
```

## Theme Configuration Options

```php
$theme = new CISClassicTheme([
    'theme_name' => 'CIS Classic',
    'theme_version' => '2.0.0',
    'base_url' => 'https://staff.vapeshed.co.nz/',
    'assets_url' => 'https://staff.vapeshed.co.nz/assets/',
]);
```

## Helper Methods

```php
// Get configuration value
$baseUrl = $theme->getConfig('base_url');

// Get user data
$firstName = $theme->getUserData('first_name', 'Guest');
$isLoggedIn = $theme->getUserData('logged_in', false);

// Check permission (placeholder for now)
if ($theme->hasPermission('admin')) {
    // Do admin stuff
}
```

## Migration from Old Template

If you have existing pages using the old template includes:

**Before:**
```php
include($_SERVER['DOCUMENT_ROOT'] . '/assets/template/html-header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/assets/template/header.php');
include($_SERVER['DOCUMENT_ROOT'] . '/assets/template/sidemenu.php');
echo '<div class="app-body"><main class="main">';
// content
echo '</main></div>';
include($_SERVER['DOCUMENT_ROOT'] . '/assets/template/footer.php');
```

**After:**
```php
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/base/_templates/themes/cis-classic/theme.php';
$theme = new CISClassicTheme();
$theme->setTitle('My Page');
$theme->render('html-head');
$theme->render('header');
$theme->render('sidebar');
$theme->render('main-start');
// content
$theme->render('footer');
```

## Styling and Assets

The theme uses these CSS files (already included):
- `/assets/css/style1.css` - CoreUI + Bootstrap
- `/assets/css/bootstrap-compatibility.css` - Bootstrap fixes
- `/assets/css/custom.css` - Custom CIS styles
- `/assets/css/sidebar-styling-restore.css` - Sidebar styling

No additional CSS needed - it just works!

## JavaScript Dependencies

Loaded in this order:
1. jQuery 3.7.1
2. Moment.js 2.29.4
3. Bootstrap 4.1.1 Bundle (includes Popper)
4. CoreUI 2.0.0
5. Pace Loader
6. jQuery UI 1.13.2

## Browser Support

- Chrome (latest)
- Firefox (latest)
- Safari (latest)
- Edge (latest)
- IE 11+ (limited support)

## Tips & Best Practices

1. **Always set page title** - Helps with SEO and user navigation
2. **Set current page** - Highlights active menu item
3. **Use container-fluid** - Matches CIS layout conventions
4. **Test with permissions** - Ensure menu shows correctly for different users
5. **Don't modify theme files** - Extend the class instead

## Extending the Theme

```php
class MyCustomTheme extends CISClassicTheme {

    public function __construct(array $options = []) {
        // Add custom config
        $options['custom_setting'] = 'value';
        parent::__construct($options);
    }

    public function renderCustomComponent() {
        // Your custom rendering logic
    }
}
```

## Troubleshooting

**Sidebar not showing menu items:**
- Check database connection
- Verify `navigation`, `permissions`, and `user_permissions` tables exist
- Ensure user has permissions assigned

**Styles not loading:**
- Check `assets_url` configuration
- Verify CSS files exist in `/assets/css/`
- Check browser console for 404 errors

**User name showing as "Guest":**
- Check `$_SESSION["userID"]` is set
- Ensure `getUserInformation()` function is available
- Verify user data in database

## Action Bar Features

The action bar (located below the main header) provides contextual information and actions for each page.

### Page Subtitle

Set a prominent subtitle that appears at the start of the action bar:

```php
// Set page subtitle
$theme->setPageSubtitle('Inventory Management Dashboard');

// Get current subtitle
$subtitle = $theme->getPageSubtitle();
```

**Best Practices:**
- Keep it concise (2-5 words)
- Use title case: "Sales Dashboard" not "sales dashboard"
- Make it descriptive of the page content
- Examples: "Active Consignments", "Order Processing", "Customer Management"

### Breadcrumbs

Add breadcrumb navigation to show the user's location:

```php
// Add breadcrumbs (left to right)
$theme->addBreadcrumb('Home', '/');
$theme->addBreadcrumb('Dashboard', '/dashboard.php');
$theme->addBreadcrumb('Inventory'); // Last item has no URL (current page)
```

### Header Buttons

Add action buttons to the right side of the action bar:

```php
// Add a button
$theme->addHeaderButton('New Order', '/orders/new.php', 'primary', 'fas fa-plus');

// Add multiple buttons
$theme->addHeaderButton('Export', '/export.php', 'secondary', 'fas fa-download');
$theme->addHeaderButton('Settings', '/settings.php', 'secondary', 'fas fa-cog');

// Parameters: label, url, color (Bootstrap color), icon (FontAwesome class)
```

**Available Colors:**
- `primary` (blue)
- `secondary` (gray)
- `success` (green)
- `danger` (red)
- `warning` (yellow)
- `info` (cyan)
- `purple` (custom purple)
- `lime` (custom lime green)

### Timestamps

Show current date/time on the far right:

```php
// Show timestamp (far right of action bar)
$theme->showTimestamps(true);

// Hide timestamp
$theme->showTimestamps(false);
```

### Complete Example

```php
<?php
require_once __DIR__ . '/theme.php';
$theme = new \CIS\Theme\CISClassic();

// Set page title and subtitle
$theme->setPageTitle('Inventory - CIS');
$theme->setPageSubtitle('Active Stock Items');

// Add breadcrumbs
$theme->addBreadcrumb('Home', '/');
$theme->addBreadcrumb('Inventory', '/inventory/');
$theme->addBreadcrumb('Active Stock');

// Add action buttons
$theme->addHeaderButton('New Item', '/inventory/new.php', 'primary', 'fas fa-plus');
$theme->addHeaderButton('Import', '/inventory/import.php', 'secondary', 'fas fa-upload');
$theme->addHeaderButton('Export', '/inventory/export.php', 'secondary', 'fas fa-download');

// Show timestamp
$theme->showTimestamps(true);
?>
<!DOCTYPE html>
<html lang="en">
<?php $theme->renderHead(); ?>
<body class="<?php echo $theme->getPageData('body_class'); ?>">
  <?php $theme->renderHeader(); ?>

  <div class="app-body">
    <?php $theme->renderSidebar(); ?>

    <main class="main">
      <!-- Your page content here -->
    </main>
  </div>

  <?php $theme->renderFooter(); ?>
</body>
</html>
```

**Action Bar Layout (left to right):**
1. **Page Subtitle** - Bold, prominent text
2. **Breadcrumbs** - Navigation path (if set)
3. **Action Buttons** - Quick actions (auto-aligned right)
4. **Timestamp** - Current date/time (if enabled, far right)

See `/examples/subtitle-demo.php` for a complete demonstration.

## Support

For issues or questions:
- Check existing CIS pages for working examples
- Review `/assets/template/` for original implementation
- Contact development team

---

**Version:** 2.0.0
**Last Updated:** November 4, 2025
**Maintainer:** Pearce Stephens
