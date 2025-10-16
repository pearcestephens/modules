# CIS Error Display System

## Overview
Comprehensive error handling system for consistent error display across all CIS modules.

## Components
1. **Error Page Helper** (`error-page.php`) - Full page error display with one function call
2. **Error Block Template** (`error.php`) - Reusable error block for custom layouts
3. **Error Display CSS** (`error-display.css`) - Shared styling

## 🚀 Quick Start (Recommended)

### Simple Error Page

```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/shared/functions/error-page.php';

// Just pass the error message - that's it!
showErrorPage("Transfer #123 not found.");
```

### With Custom Options

```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/shared/functions/error-page.php';

showErrorPage("Invalid transfer type.", [
    'title' => 'Wrong Transfer Category',
    'type' => 'danger',
    'icon' => 'fa-ban',
    'backUrl' => 'index.php',
    'backLabel' => 'Back to Transfer List',
    'retryUrl' => "?id=123",
    'showDetails' => true,
    'details' => [
        'This page only handles STOCK transfers',
        'Check the transfer category',
        'Use the correct page for this transfer type'
    ]
]);
```

## 📍 Location
- **Helper Function**: `/modules/consignments/shared/functions/error-page.php`
- **Error Block**: `/modules/consignments/shared/blocks/error.php`
- **CSS**: `/modules/consignments/shared/css/error-display.css`

## Features
- ✅ One-line error page display
- ✅ Consistent error display across all modules
- ✅ Customizable error types (warning, danger, info)
- ✅ Animated error icon with pulse effect
- ✅ Optional troubleshooting details
- ✅ Configurable action buttons
- ✅ Clean, modern design
- ✅ Fully responsive
- ✅ Full page layout with header/footer/sidemenu included

## 📖 API Reference

### `showErrorPage(string $errorMessage, array $options = [])`

Displays a complete error page and exits. Includes full CIS layout (header, footer, sidemenu).

**Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| `$errorMessage` | string | ✅ Yes | The error message to display |
| `$options` | array | ❌ No | Configuration options (see below) |

**Options Array:**

| Key | Type | Default | Description |
|-----|------|---------|-------------|
| `title` | string | "Unable to Process Request" | Error title |
| `icon` | string | "fa-exclamation-triangle" | FontAwesome icon class |
| `type` | string | "warning" | Error type: warning, danger, info |
| `backUrl` | string | "index.php" | URL for back button |
| `backLabel` | string | "Back to List" | Label for back button |
| `retryUrl` | string\|null | null | If set, shows retry button |
| `showDetails` | bool | false | Show troubleshooting section |
| `details` | array | [] | Custom troubleshooting steps |

## 💡 Usage Examples

### Example 1: Minimal (Most Common)
```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/shared/functions/error-page.php';

if (!$transferId) {
    showErrorPage("Transfer ID is required.");
}
```

### Example 2: Transfer Not Found
```php
if (!$transferData) {
    showErrorPage("Transfer #$transferId not found or you don't have access to it.", [
        'title' => 'Transfer Not Found',
        'type' => 'warning',
        'showDetails' => true
    ]);
}
```

### Example 3: Wrong Transfer Type
```php
if ($transferData->category !== 'STOCK') {
    showErrorPage(
        "Transfer #$transferId is a {$transferData->category} transfer. This page only handles STOCK transfers.",
        [
            'title' => 'Invalid Transfer Type',
            'icon' => 'fa-ban',
            'type' => 'danger',
            'backUrl' => 'index.php',
            'retryUrl' => null, // No retry button
            'showDetails' => true,
            'details' => [
                'This page only handles STOCK category transfers',
                'Check the transfer category in the system',
                'Use the appropriate page for ' . $transferData->category . ' transfers'
            ]
        ]
    );
}
```

### Example 4: Permission Denied
```php
if (!hasPermission('view_transfer')) {
    showErrorPage("You don't have permission to access this resource.", [
        'title' => 'Access Denied',
        'icon' => 'fa-lock',
        'type' => 'danger',
        'backUrl' => '/dashboard.php',
        'backLabel' => 'Back to Dashboard',
        'showDetails' => false
    ]);
}
```

### Example 5: System Error with Retry
```php
try {
    $data = fetchTransferData($id);
} catch (Exception $e) {
    error_log("Transfer fetch error: " . $e->getMessage());
    showErrorPage("An unexpected error occurred while loading the transfer.", [
        'title' => 'System Error',
        'icon' => 'fa-exclamation-circle',
        'type' => 'danger',
        'retryUrl' => $_SERVER['REQUEST_URI'],
        'showDetails' => true,
        'details' => [
            'Try refreshing the page',
            'Clear your browser cache',
            'Contact support if the problem persists'
        ]
    ]);
}
```

## 🎨 Error Types

### Warning (Default)
- **Color**: Yellow (#ffc107)
- **Use for**: Missing parameters, validation errors, not found errors

```php
showErrorPage($message, ['type' => 'warning']);
```

### Danger
- **Color**: Red (#dc3545)
- **Use for**: Critical errors, permission denied, system failures

```php
showErrorPage($message, ['type' => 'danger']);
```

### Info
- **Color**: Blue (#17a2b8)
- **Use for**: Informational messages, maintenance notices, redirects

```php
showErrorPage($message, ['type' => 'info']);
```

## 🔧 Advanced: Using Error Block Directly

If you need to embed the error within a custom page layout (not full page):

```php
<?php
// Set variables
$errorMessage = "Something went wrong";
$errorTitle = "Error Title";
$errorType = "warning";
$showDetails = true;

// Include just the error block (not full page)
include($_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/shared/blocks/error.php');
?>
```

## 📝 Real-World Implementation

### pack.php Example
```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/config.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/shared/functions/transfers.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/shared/functions/error-page.php';

// Validate transfer ID
$transferId = (int)($_GET['id'] ?? 0);
if (!$transferId) {
    showErrorPage("Transfer ID is required. Please provide 'id' parameter in URL (e.g., ?id=123)");
}

// Load transfer
$transferData = getUniversalTransfer($transferId);
if (!$transferData) {
    showErrorPage("Transfer #$transferId not found or you don't have access to it.", [
        'title' => 'Transfer Not Found',
        'retryUrl' => "?id=$transferId"
    ]);
}

// Validate transfer type
if ($transferData->transfer->transfer_category !== 'STOCK') {
    showErrorPage(
        "Transfer #$transferId is a {$transferData->transfer->transfer_category} transfer. This page only handles STOCK transfers.",
        [
            'title' => 'Invalid Transfer Type',
            'type' => 'danger',
            'showDetails' => true,
            'details' => [
                'Verify the transfer ID is correct',
                'Ensure this is a STOCK category transfer',
                'Use the appropriate page for this transfer type'
            ]
        ]
    );
}

// Continue with normal page rendering...
include(ROOT_PATH."/assets/template/html-header.php");
// ... rest of page
```

## Styling

The error display is styled via `/modules/consignments/shared/css/error-display.css` which includes:

- Centered layout with flexbox
- Animated pulse effect on icon
- Gradient background for icon
- Responsive design for mobile
- Hover effects on buttons
- Clean, modern typography

## Benefits

✅ **Consistency**: All errors look the same across modules
✅ **Maintainability**: Update one file to change all error displays
✅ **Flexibility**: Easy to customize per use case
✅ **Professional**: Modern design with animations
✅ **Reusable**: Drop-in template for any page
✅ **User-Friendly**: Clear messaging and helpful troubleshooting

## Migration Guide

### Before (Old Way)
```php
if ($error) {
    echo "<div class='alert alert-danger'>$error</div>";
}
```

### After (New Way)
```php
if ($error) {
    $errorMessage = $error;
    include($_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/shared/blocks/error.php');
    exit();
}
```
