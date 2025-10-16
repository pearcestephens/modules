# CIS Shared Error Display System

**Location:** `/modules/shared/`  
**Version:** 1.0.0  
**Purpose:** Reusable error display templates and functions for all CIS modules

---

## 📁 Directory Structure

```
/modules/shared/
├── blocks/
│   └── error.php                 # Reusable error display block
├── functions/
│   └── error-page.php            # Full page error wrapper function
└── css/
    └── error-display.css         # Shared error styling
```

---

## 🚀 Quick Start

### Option 1: Full Page Error (Recommended)

```php
<?php
// Include the error page helper
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/shared/functions/error-page.php';

// Simple usage
showErrorPage("Transfer not found");

// Advanced usage with options
showErrorPage("Transfer not found", [
    'title' => 'Transfer Error',
    'type' => 'danger',
    'icon' => 'fa-file-o',
    'backUrl' => '/modules/consignments/stock-transfers/index.php',
    'backLabel' => 'Back to Transfers',
    'showDetails' => true,
    'details' => [
        'Verify the transfer ID is correct',
        'Check if the transfer has been deleted',
        'Contact support if the problem persists'
    ]
]);
```

### Option 2: Error Block Only (Within Existing Page)

```php
<?php
// Set error variables
$errorMessage = "Transfer not found";
$errorTitle = "Transfer Error";
$errorType = "danger";
$errorIcon = "fa-file-o";
$backUrl = "/modules/consignments/stock-transfers/index.php";
$backLabel = "Back to Transfers";
$showDetails = true;
$details = [
    'Verify the transfer ID is correct',
    'Check if the transfer has been deleted'
];

// Include the error block
include($_SERVER['DOCUMENT_ROOT'] . '/modules/shared/blocks/error.php');
?>
```

---

## 📖 API Reference

### `showErrorPage(string $errorMessage, array $options = []): void`

Displays a complete error page with header, sidebar, and footer. **Exits immediately.**

#### Parameters

**$errorMessage** (required)  
- Type: `string`
- The main error message to display to the user

**$options** (optional)  
- Type: `array`
- Configuration options:

| Option | Type | Default | Description |
|--------|------|---------|-------------|
| `title` | string | "Unable to Process Request" | Error page title |
| `icon` | string | "fa-exclamation-triangle" | FontAwesome icon class |
| `type` | string | "warning" | Error type: `warning`, `danger`, `info` |
| `backUrl` | string | "index.php" | URL for the back button |
| `backLabel` | string | "Back to List" | Label for the back button |
| `retryUrl` | string\|null | null | If set, shows a retry button |
| `showDetails` | bool | false | Show troubleshooting section |
| `details` | array | Generic steps | Custom troubleshooting steps |

---

## 🎨 Error Types

### Warning (Default)
- **Color:** Yellow (#ffc107)
- **Use Case:** Missing data, invalid parameters, non-critical issues
- **Icon:** fa-exclamation-triangle

### Danger
- **Color:** Red (#dc3545)
- **Use Case:** Critical errors, permission denied, data corruption
- **Icon:** fa-times-circle

### Info
- **Color:** Blue (#17a2b8)
- **Use Case:** Informational messages, redirects, state changes
- **Icon:** fa-info-circle

---

## 🔧 Real-World Examples

### Example 1: Missing Transfer

```php
showErrorPage("Transfer not found or has been deleted", [
    'title' => 'Transfer Not Found',
    'type' => 'danger',
    'icon' => 'fa-file-o',
    'backUrl' => '/modules/consignments/stock-transfers/index.php',
    'backLabel' => 'Back to Transfers',
    'showDetails' => true
]);
```

### Example 2: Permission Denied

```php
showErrorPage("You don't have permission to access this resource", [
    'title' => 'Access Denied',
    'type' => 'danger',
    'icon' => 'fa-lock',
    'backUrl' => '/dashboard.php',
    'backLabel' => 'Back to Dashboard',
    'showDetails' => true,
    'details' => [
        'Check if you have the required role',
        'Contact your administrator for access',
        'Verify you are logged in with the correct account'
    ]
]);
```

### Example 3: Invalid State with Retry

```php
showErrorPage("Transfer is already dispatched and cannot be modified", [
    'title' => 'Invalid Transfer State',
    'type' => 'warning',
    'icon' => 'fa-ban',
    'backUrl' => '/modules/consignments/stock-transfers/view.php?id=' . $transferId,
    'backLabel' => 'View Transfer',
    'retryUrl' => '/modules/consignments/stock-transfers/pack.php?id=' . $transferId,
    'showDetails' => true,
    'details' => [
        'Only transfers in SENT state can be packed',
        'Check the transfer status in the system',
        'If this is incorrect, contact support'
    ]
]);
```

### Example 4: Database Connection Error

```php
showErrorPage("Unable to connect to the database", [
    'title' => 'System Error',
    'type' => 'danger',
    'icon' => 'fa-database',
    'backUrl' => '/dashboard.php',
    'backLabel' => 'Back to Dashboard',
    'retryUrl' => $_SERVER['REQUEST_URI'],
    'showDetails' => true,
    'details' => [
        'The system is experiencing technical difficulties',
        'Please try again in a few moments',
        'If the problem persists, contact IT support'
    ]
]);
```

---

## 🎯 Best Practices

### ✅ DO

- **Use appropriate error types** - `danger` for critical errors, `warning` for recoverable issues
- **Provide helpful error messages** - Explain what went wrong in user-friendly language
- **Include troubleshooting steps** - Help users resolve the issue themselves
- **Set proper back URLs** - Send users to a logical place to continue their work
- **Use custom icons** - Match the icon to the error context (fa-lock for permissions, fa-file-o for missing files)

### ❌ DON'T

- **Don't expose technical details** - Never show stack traces, SQL errors, or system paths to users
- **Don't use generic messages** - "An error occurred" is not helpful
- **Don't forget error logging** - Always log the actual error before showing the user-friendly version
- **Don't mix error types** - Use consistent error types across similar situations
- **Don't create inline error displays** - Use the shared system for consistency

---

## 🔄 Migration Guide

### From Old Error Display

**Before:**
```php
include(ROOT_PATH."/assets/template/html-header.php");
include(ROOT_PATH."/assets/template/header.php");
echo '<div class="alert alert-danger">Transfer not found</div>';
include(ROOT_PATH."/assets/template/html-footer.php");
exit;
```

**After:**
```php
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/shared/functions/error-page.php';
showErrorPage("Transfer not found", [
    'type' => 'danger',
    'backUrl' => 'index.php'
]);
```

### From Consignments Shared (Old Path)

**Before:**
```php
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/shared/functions/error-page.php';
```

**After:**
```php
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/shared/functions/error-page.php';
```

---

## 🧪 Testing

### Manual Testing Checklist

- [ ] Error displays correctly with default options
- [ ] Custom title and message appear properly
- [ ] All three error types (warning, danger, info) render with correct colors
- [ ] Back button links to correct URL
- [ ] Retry button appears when `retryUrl` is set
- [ ] Troubleshooting section shows when `showDetails` is true
- [ ] Custom troubleshooting steps display correctly
- [ ] CSS loads properly (check `/modules/shared/css/error-display.css`)
- [ ] Page exits after displaying error (no code executes after `showErrorPage()`)

### Test URL

Create a test file: `/modules/shared/test-error.php`

```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/shared/functions/error-page.php';

$testType = $_GET['type'] ?? 'warning';

showErrorPage("This is a test error message", [
    'title' => 'Test Error Display',
    'type' => $testType,
    'icon' => 'fa-bug',
    'backUrl' => '/modules/shared/test-error.php',
    'backLabel' => 'Test Again',
    'retryUrl' => '/modules/shared/test-error.php?type=' . $testType,
    'showDetails' => true,
    'details' => [
        'This is a test error display',
        'Try changing the type parameter: warning, danger, info',
        'All features should work correctly'
    ]
]);
```

Test URLs:
- `/modules/shared/test-error.php?type=warning`
- `/modules/shared/test-error.php?type=danger`
- `/modules/shared/test-error.php?type=info`

---

## 📊 Usage Statistics

Track where this error system is being used:

- [x] `/modules/consignments/stock-transfers/pack.php`
- [ ] Add your module here...

---

## 🔧 Maintenance

### Adding New Error Types

To add a new error type, edit `/modules/shared/blocks/error.php`:

```php
// Add to error color mapping
$errorColors = [
    'warning' => '#ffc107',
    'danger' => '#dc3545',
    'info' => '#17a2b8',
    'success' => '#28a745',  // NEW TYPE
];
```

### Customizing Styles

Edit `/modules/shared/css/error-display.css` to customize:
- Font sizes
- Colors
- Animations
- Button styles
- Responsive breakpoints

---

## 📝 Changelog

### Version 1.0.0 (2025-10-15)
- ✅ Moved from `/modules/consignments/shared/` to `/modules/shared/`
- ✅ Made available to all CIS modules
- ✅ Updated documentation with migration guide
- ✅ Updated pack.php to use new paths

---

## 💬 Support

For questions or issues with the error display system:
1. Check this documentation first
2. Review existing implementations in other modules
3. Test with `/modules/shared/test-error.php`
4. Contact the development team

---

**Last Updated:** October 15, 2025  
**Maintained By:** CIS Development Team
