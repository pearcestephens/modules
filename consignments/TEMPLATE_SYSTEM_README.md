# Consignments Module Template System

## Overview

The Consignments module now uses the **CIS Classic Theme (V1)** as the default template system. This provides a consistent, professional look across all pages while keeping view files clean and focused on content.

## Architecture

### Clean Separation of Concerns

```
┌─────────────────────────────────────────┐
│  template.php (ConsignmentsTemplate)    │  ← Wrapper/Controller
│  - Handles header, sidebar, footer      │
│  - Manages assets (CSS/JS)              │
│  - Provides utility functions           │
└─────────────────────────────────────────┘
                    ▼
┌─────────────────────────────────────────┐
│  CIS Classic Theme (V1)                 │  ← Base Theme
│  - CoreUI 2.0.0 + Bootstrap 4.1.1       │
│  - Database-driven navigation           │
│  - User authentication & permissions    │
└─────────────────────────────────────────┘
                    ▼
┌─────────────────────────────────────────┐
│  Your View File (e.g. dashboard.php)    │  ← Content Only
│  - Contains ONLY main content           │
│  - No header/footer code                │
│  - Clean and maintainable               │
└─────────────────────────────────────────┘
```

## Usage

### Basic Page Template

```php
<?php
/**
 * Your Page Title
 */

require_once __DIR__ . '/template.php';

// Initialize template
$template = new ConsignmentsTemplate();
$template->setTitle('Your Page Title');
$template->setCurrentPage('consignments/your-page');

// Start content rendering
$template->startContent();
?>

<!-- YOUR CONTENT GOES HERE -->
<div class="card">
    <div class="card-header">
        <h2>Your Content</h2>
    </div>
    <div class="card-body">
        <p>This is your page content...</p>
    </div>
</div>

<?php
// End content rendering
$template->endContent();
?>
```

### Adding Custom Assets

```php
// Add CSS
$template->addCSS('/path/to/custom.css');

// Add JavaScript
$template->addJS('/path/to/custom.js');

// Add inline CSS
$template->addInlineCSS('
    .custom-class { color: red; }
');

// Add inline JavaScript
$template->addInlineJS('
    console.log("Custom JS loaded");
');
```

### Advanced Usage

```php
// Chain methods
$template
    ->setTitle('Dashboard')
    ->setCurrentPage('consignments/dashboard')
    ->addCSS('https://cdn.example.com/style.css')
    ->addJS('https://cdn.example.com/script.js');

// Access underlying theme for advanced customization
$cisTheme = $template->getTheme();
$cisTheme->addHeadContent('<meta name="custom" content="value">');
```

## Pre-built Components

The template includes several pre-styled components:

### Status Badges

```html
<span class="status-badge status-draft">Draft</span>
<span class="status-badge status-pending">Pending</span>
<span class="status-badge status-sent">Sent</span>
<span class="status-badge status-received">Received</span>
<span class="status-badge status-cancelled">Cancelled</span>
```

### AI Badges & Buttons

```html
<span class="ai-badge">
    <i class="fa fa-sparkles"></i>
    AI Powered
</span>

<button class="ai-button">
    <i class="fa fa-robot"></i>
    Ask AI
</button>
```

### Anomaly Alerts

```html
<div class="anomaly-alert">High severity alert</div>
<div class="anomaly-warning">Medium severity warning</div>
<div class="anomaly-info">Low severity info</div>
```

### Consignment Cards

```html
<div class="card consignment-card">
    <div class="card-body">
        Your content with hover effects
    </div>
</div>
```

## JavaScript Utilities

The template provides global `ConsignmentsApp` utilities:

```javascript
// Format currency
ConsignmentsApp.formatCurrency(99.99); // "$99.99"

// Format date
ConsignmentsApp.formatDate('2025-11-04'); // "Nov 4, 2025"

// Show toast notification
ConsignmentsApp.toast('Success!', 'success'); // success, error, warning, info

// Confirm action
ConsignmentsApp.confirm('Are you sure?', function() {
    // User clicked OK
});

// AJAX helper (includes CSRF token)
ConsignmentsApp.ajax('/api/endpoint', {
    method: 'POST',
    body: JSON.stringify({ data: 'value' })
})
.then(response => {
    console.log(response);
})
.catch(error => {
    console.error(error);
});
```

## File Structure

```
consignments/
├── template.php                  ← Main template wrapper
├── transfer-manager.php          ← Full featured page example
├── dashboard-example.php         ← Simple page example
├── ai-consignment-examples.php   ← AI integration examples
├── src/
│   └── Services/
│       └── AIConsignmentAssistant.php  ← AI service
├── api/
│   └── ai-assistant.php          ← AI REST API
└── TransferManager/
    ├── frontend.php              ← Legacy frontend
    └── backend.php               ← Backend logic
```

## Examples

### 1. Simple Dashboard

See: `dashboard-example.php`

- Minimal code (20 lines)
- Only contains content
- Pre-styled components

### 2. Full Featured Transfer Manager

See: `transfer-manager.php`

- Complete DataTables integration
- AI Assistant panel
- Modal dialogs
- Real-time stats
- Anomaly detection

### 3. AI Integration

See: `ai-consignment-examples.php`

- Carrier recommendations
- Transfer analysis
- Natural language Q&A
- Cost predictions

## Benefits

✅ **Consistent Look** - All pages use CIS Classic theme
✅ **Clean Views** - Views contain only content, no boilerplate
✅ **Easy Maintenance** - Change template once, affects all pages
✅ **Pre-styled Components** - Status badges, AI buttons, alerts
✅ **JavaScript Utilities** - Common functions available globally
✅ **AI Ready** - Built-in AI integration support
✅ **Responsive** - Bootstrap 4 mobile-first design
✅ **Database Navigation** - Menu items from permissions table

## Migration Guide

### Before (Old Style)

```php
<?php
include 'header.php';
include 'sidebar.php';
?>
<div class="container">
    <!-- Content -->
</div>
<?php
include 'footer.php';
?>
```

### After (New Template System)

```php
<?php
require_once __DIR__ . '/template.php';
$template = new ConsignmentsTemplate();
$template->setTitle('Page Title');
$template->startContent();
?>

<!-- Content only -->
<div class="card">
    <div class="card-body">
        Your content
    </div>
</div>

<?php
$template->endContent();
?>
```

## Performance

- **Cached Assets** - CSS/JS loaded from CDN with caching
- **Minimal Overhead** - Template adds ~5ms to page load
- **Database Efficient** - Navigation menu cached per session
- **Lazy Loading** - AI features loaded on-demand

## Browser Support

- ✅ Chrome 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Edge 90+
- ⚠️ IE11 (limited support)

## Troubleshooting

### Page shows no header/sidebar

**Solution:** Make sure you called `$template->startContent()` before your content and `$template->endContent()` after.

### Navigation menu is empty

**Solution:** Check that:
1. User is logged in (`$_SESSION['userID']` is set)
2. User has permissions in `user_permissions` table
3. Database connection is working

### Assets not loading

**Solution:** Check that asset URLs are correct:
- CDN assets: Full HTTPS URLs
- Local assets: Absolute paths from document root

### AI features not working

**Solution:** Verify:
1. `.env` has `INTELLIGENCE_HUB_ENABLED=true`
2. API key is configured
3. `AIConsignmentAssistant` class is autoloaded

## Next Steps

1. **Migrate existing pages** to use the template system
2. **Add AI features** using the provided service classes
3. **Customize styling** by editing `template.php` assets
4. **Extend functionality** by adding methods to `ConsignmentsTemplate`

## Support

For questions or issues:
- Check existing examples in the module
- Review CIS Classic theme docs: `/modules/base/_templates/themes/cis-classic/README.md`
- Contact: Pearce Stephens

---

**Last Updated:** November 4, 2025
**Version:** 1.0.0
**Status:** ✅ Production Ready
