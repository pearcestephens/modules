# 🎯 VapeUltra Usage Examples & Integration Guide

**Version:** 2.0.0
**Last Updated:** 2025-01-04
**Status:** Production Ready

## 📋 Table of Contents

1. [Quick Start](#quick-start)
2. [Master Template Usage](#master-template-usage)
3. [Breadcrumb Component](#breadcrumb-component)
4. [Sub-Navigation Component](#sub-navigation-component)
5. [Global Error Handler](#global-error-handler)
6. [AJAX Client](#ajax-client)
7. [Modal System](#modal-system)
8. [Toast Notifications](#toast-notifications)
9. [Complete Integration Examples](#complete-integration-examples)

---

## 🚀 Quick Start

### 1. Initialize VapeUltra on Page Load

Add this to your `master.php` template (already included):

```javascript
<script>
// Initialize VapeUltra
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Error Handler
    VapeUltra.ErrorHandler.init({
        debug: false,              // Set to true in development
        logToServer: true,
        showToUser: true,
        endpoint: '/api/log-error'
    });

    // Initialize AJAX Client
    VapeUltra.Ajax.init({
        baseURL: '',               // Base URL for all requests
        timeout: 30000,
        retryAttempts: 3,
        debug: false
    });

    // Modal and Toast auto-initialize
    // No manual init required

    console.log('✅ VapeUltra initialized');
});
</script>
```

---

## 📄 Master Template Usage

### Basic Page Structure

**File:** `modules/sales/views/dashboard.php`

```php
<?php
// Build your page content
ob_start();
?>
<div class="page-content">
    <h1>Sales Dashboard</h1>
    <p>Welcome to the sales dashboard</p>

    <!-- Your content here -->
</div>
<?php
$pageContent = ob_get_clean();

// Breadcrumb items
$breadcrumb = [
    ['label' => 'Home', 'url' => '/', 'icon' => 'bi bi-house'],
    ['label' => 'Sales', 'url' => '/sales'],
    ['label' => 'Dashboard', 'active' => true]
];

// Sub-navigation items
$subnav = [
    ['label' => 'Dashboard', 'url' => '/sales', 'icon' => 'bi bi-speedometer2', 'active' => true],
    ['label' => 'Invoices', 'url' => '/sales/invoices', 'icon' => 'bi bi-receipt', 'badge' => ['count' => 12, 'type' => 'primary']],
    ['label' => 'Customers', 'url' => '/sales/customers', 'icon' => 'bi bi-people'],
    ['label' => 'Reports', 'url' => '/sales/reports', 'icon' => 'bi bi-bar-chart']
];

// Render with master template
$renderer->render('master', [
    'title' => 'Sales Dashboard - CIS 2.0',
    'content' => $pageContent,

    // Navigation
    'showBreadcrumb' => true,
    'breadcrumb' => $breadcrumb,
    'showSubnav' => true,
    'subnav' => $subnav,
    'subnavStyle' => 'horizontal',  // or 'vertical'
    'subnavAlign' => 'left',        // or 'center', 'right'

    // Layout visibility
    'showHeader' => true,
    'showSidebar' => true,
    'showSidebarRight' => false,
    'showFooter' => true
]);
?>
```

### Minimal Layout (No Navigation)

```php
$renderer->render('master', [
    'title' => 'Login - CIS 2.0',
    'content' => $loginFormHTML,
    'layout' => 'minimal',
    'showHeader' => false,
    'showSidebar' => false,
    'showBreadcrumb' => false,
    'showSubnav' => false,
    'showFooter' => false
]);
```

### Full-Width Layout

```php
$renderer->render('master', [
    'title' => 'Analytics Dashboard',
    'content' => $analyticsHTML,
    'layout' => 'full',
    'showSidebar' => false,
    'showSidebarRight' => false
]);
```

---

## 🍞 Breadcrumb Component

### Simple Breadcrumb

```php
$breadcrumb = [
    ['label' => 'Home', 'url' => '/'],
    ['label' => 'Products', 'url' => '/products'],
    ['label' => 'Vape Devices', 'active' => true]
];

$renderer->render('master', [
    'title' => 'Vape Devices',
    'content' => $content,
    'showBreadcrumb' => true,
    'breadcrumb' => $breadcrumb
]);
```

### Breadcrumb with Icons

```php
$breadcrumb = [
    ['label' => 'Home', 'url' => '/', 'icon' => 'bi bi-house'],
    ['label' => 'Inventory', 'url' => '/inventory', 'icon' => 'bi bi-boxes'],
    ['label' => 'Stock Levels', 'active' => true, 'icon' => 'bi bi-graph-up']
];
```

### Deep Navigation Breadcrumb

```php
$breadcrumb = [
    ['label' => 'Home', 'url' => '/'],
    ['label' => 'Products', 'url' => '/products'],
    ['label' => 'Categories', 'url' => '/products/categories'],
    ['label' => 'Vape Devices', 'url' => '/products/categories/vape-devices'],
    ['label' => 'Pod Systems', 'active' => true]
];
```

**Note:** On mobile, only the last 2 items are shown with an ellipsis.

---

## 🧭 Sub-Navigation Component

### Horizontal Tab-Style Navigation

```php
$subnav = [
    ['label' => 'Overview', 'url' => '/inventory', 'active' => true],
    ['label' => 'Stock Levels', 'url' => '/inventory/stock'],
    ['label' => 'Transfers', 'url' => '/inventory/transfers', 'badge' => ['count' => 3, 'type' => 'warning']],
    ['label' => 'Consignments', 'url' => '/inventory/consignments'],
    ['label' => 'Settings', 'url' => '/inventory/settings']
];

$renderer->render('master', [
    'title' => 'Inventory',
    'content' => $content,
    'showSubnav' => true,
    'subnav' => $subnav,
    'subnavStyle' => 'horizontal',
    'subnavAlign' => 'left'
]);
```

### Vertical Sidebar-Style Navigation

```php
$subnav = [
    ['label' => 'Dashboard', 'url' => '/reports', 'icon' => 'bi bi-speedometer2', 'active' => true],
    ['label' => 'Sales Report', 'url' => '/reports/sales', 'icon' => 'bi bi-graph-up'],
    ['label' => 'Inventory Report', 'url' => '/reports/inventory', 'icon' => 'bi bi-boxes'],
    ['label' => 'Customer Report', 'url' => '/reports/customers', 'icon' => 'bi bi-people'],
    ['label' => 'Export Data', 'url' => '/reports/export', 'icon' => 'bi bi-download']
];

$renderer->render('master', [
    'title' => 'Reports',
    'content' => $content,
    'showSubnav' => true,
    'subnav' => $subnav,
    'subnavStyle' => 'vertical'
]);
```

### Sub-Navigation with Badges

```php
$subnav = [
    ['label' => 'Inbox', 'url' => '/messages', 'active' => true, 'badge' => ['count' => 42, 'type' => 'danger']],
    ['label' => 'Sent', 'url' => '/messages/sent', 'badge' => ['count' => 128, 'type' => 'secondary']],
    ['label' => 'Drafts', 'url' => '/messages/drafts', 'badge' => ['count' => 5, 'type' => 'warning']],
    ['label' => 'Archived', 'url' => '/messages/archived']
];
```

### Sub-Navigation with Disabled Items

```php
$subnav = [
    ['label' => 'Public', 'url' => '/products', 'active' => true],
    ['label' => 'Private', 'url' => '/products/private'],
    ['label' => 'Coming Soon', 'url' => '#', 'disabled' => true]
];
```

---

## 🚨 Global Error Handler

### Automatic Error Catching

The error handler automatically catches:
- Uncaught JavaScript errors
- Unhandled promise rejections
- `console.error()` calls
- AJAX failures (when integrated with Ajax client)

### Manual Error Reporting

```javascript
try {
    // Risky operation
    const result = riskyFunction();
} catch (error) {
    // Manually report error
    VapeUltra.ErrorHandler.catch(error, {
        context: 'user_action',
        action: 'process_payment',
        userId: 123
    });
}
```

### AJAX Error Handling

```javascript
// Automatically handled by Ajax client
VapeUltra.Ajax.get('/api/users')
    .then(data => {
        console.log('Success:', data);
    })
    .catch(error => {
        // Error automatically logged and shown to user
        console.error('Request failed:', error);
    });
```

### Get Error Log

```javascript
// Get all errors from memory
const errors = VapeUltra.ErrorHandler.getErrors();
console.log('Errors:', errors);

// Export errors as JSON
const errorJSON = VapeUltra.ErrorHandler.exportErrors();
console.log(errorJSON);

// Clear error log
VapeUltra.ErrorHandler.clearErrors();
```

---

## 🌐 AJAX Client

### GET Request

```javascript
VapeUltra.Ajax.get('/api/users')
    .then(data => {
        console.log('Users:', data);
    })
    .catch(error => {
        console.error('Error:', error);
    });
```

### GET Request with Query Parameters

```javascript
VapeUltra.Ajax.get('/api/users', {
    params: {
        page: 1,
        limit: 20,
        sort: 'name'
    }
})
    .then(data => {
        console.log('Users:', data);
    });
```

### POST Request

```javascript
VapeUltra.Ajax.post('/api/users', {
    name: 'John Doe',
    email: 'john@example.com',
    role: 'admin'
})
    .then(data => {
        VapeUltra.Toast.success('User created successfully');
        console.log('Created user:', data);
    })
    .catch(error => {
        VapeUltra.Toast.error('Failed to create user');
    });
```

### PUT Request (Update)

```javascript
VapeUltra.Ajax.put('/api/users/123', {
    name: 'Jane Doe',
    email: 'jane@example.com'
})
    .then(data => {
        VapeUltra.Toast.success('User updated successfully');
    });
```

### DELETE Request

```javascript
VapeUltra.Ajax.delete('/api/users/123')
    .then(data => {
        VapeUltra.Toast.success('User deleted successfully');
    });
```

### Cancelable Request

```javascript
const cancelRequest = VapeUltra.Ajax.get('/api/long-running-task', {
    cancelable: true
});

// Later, cancel the request
cancelRequest.cancel();
```

### Hide Loading Indicator for Specific Request

```javascript
VapeUltra.Ajax.get('/api/background-sync', {
    showLoading: false
})
    .then(data => {
        console.log('Background sync complete');
    });
```

---

## 🎨 Modal System

### Alert Dialog

```javascript
VapeUltra.Modal.alert({
    title: 'Success',
    message: 'Your changes have been saved successfully.',
    type: 'success'
}).then(() => {
    console.log('Alert closed');
});
```

### Confirm Dialog

```javascript
VapeUltra.Modal.confirm({
    title: 'Delete Item',
    message: 'Are you sure you want to delete this item? This action cannot be undone.',
    type: 'danger',
    confirmLabel: 'Delete',
    cancelLabel: 'Cancel'
}).then(result => {
    if (result) {
        // User confirmed
        deleteItem();
    } else {
        // User cancelled
        console.log('Deletion cancelled');
    }
});
```

### Prompt Dialog

```javascript
VapeUltra.Modal.prompt({
    title: 'Enter Your Name',
    message: 'Please enter your full name:',
    defaultValue: 'John Doe',
    placeholder: 'Full name'
}).then(value => {
    if (value !== null) {
        console.log('User entered:', value);
    } else {
        console.log('User cancelled');
    }
});
```

### Custom Modal with Actions

```javascript
VapeUltra.Modal.open({
    title: 'Edit User',
    content: `
        <form id="edit-user-form">
            <div class="mb-3">
                <label for="user-name" class="form-label">Name</label>
                <input type="text" class="form-control" id="user-name" value="John Doe">
            </div>
            <div class="mb-3">
                <label for="user-email" class="form-label">Email</label>
                <input type="email" class="form-control" id="user-email" value="john@example.com">
            </div>
        </form>
    `,
    size: 'md',
    buttons: [
        {
            label: 'Cancel',
            variant: 'secondary',
            onClick: (modal) => {
                modal.close();
            }
        },
        {
            label: 'Save Changes',
            variant: 'primary',
            onClick: (modal) => {
                const name = document.getElementById('user-name').value;
                const email = document.getElementById('user-email').value;

                // Save via AJAX
                VapeUltra.Ajax.put('/api/users/123', { name, email })
                    .then(() => {
                        VapeUltra.Toast.success('User updated successfully');
                        modal.close();
                    });
            }
        }
    ]
});
```

### Large Modal

```javascript
VapeUltra.Modal.open({
    title: 'Product Details',
    content: '<div>Large content here...</div>',
    size: 'lg',  // Options: sm, md, lg, xl, fullscreen
    closable: true,
    backdropClose: true,
    keyboardClose: true
});
```

### Fullscreen Modal

```javascript
VapeUltra.Modal.open({
    title: 'Image Gallery',
    content: '<div class="gallery">...</div>',
    size: 'fullscreen',
    closable: true
});
```

### Modal with Callback

```javascript
VapeUltra.Modal.alert({
    title: 'Session Expiring',
    message: 'Your session will expire in 5 minutes.',
    type: 'warning',
    onClose: () => {
        // Refresh session
        refreshSession();
    }
});
```

---

## 🍞 Toast Notifications

### Success Toast

```javascript
VapeUltra.Toast.success('Changes saved successfully!');

// With duration
VapeUltra.Toast.success('Item added to cart', {
    duration: 3000
});
```

### Error Toast

```javascript
VapeUltra.Toast.error('Failed to save changes. Please try again.');

// With longer duration
VapeUltra.Toast.error('An unexpected error occurred', {
    duration: 6000
});
```

### Warning Toast

```javascript
VapeUltra.Toast.warning('Your session will expire in 5 minutes');
```

### Info Toast

```javascript
VapeUltra.Toast.info('Welcome to CIS 2.0!');
```

### Toast with Custom Position

```javascript
// Positions: top-left, top-center, top-right (default)
//            center-left, center-center, center-right
//            bottom-left, bottom-center, bottom-right

VapeUltra.Toast.success('Saved!', {
    position: 'bottom-center'
});
```

### Toast with Action Button

```javascript
VapeUltra.Toast.show({
    message: 'Item deleted',
    type: 'info',
    duration: 5000,
    actions: [
        {
            label: 'Undo',
            onClick: (toast) => {
                // Restore deleted item
                restoreItem();
                toast.dismiss();
            }
        }
    ]
});
```

### Toast with Multiple Actions

```javascript
VapeUltra.Toast.show({
    message: 'New message from support',
    type: 'info',
    duration: 8000,
    actions: [
        {
            label: 'View',
            onClick: () => {
                window.location.href = '/messages/inbox';
            }
        },
        {
            label: 'Dismiss',
            onClick: (toast) => {
                toast.dismiss();
            }
        }
    ]
});
```

### Persistent Toast (No Auto-Dismiss)

```javascript
VapeUltra.Toast.error('Critical error occurred. Please contact support.', {
    duration: 0,  // Never auto-dismiss
    closable: true
});
```

### Toast with Title

```javascript
VapeUltra.Toast.show({
    title: 'Update Available',
    message: 'A new version of CIS is available. Please refresh to update.',
    type: 'info',
    duration: 0,
    actions: [
        {
            label: 'Refresh Now',
            onClick: () => {
                window.location.reload();
            }
        }
    ]
});
```

### Dismiss All Toasts

```javascript
// Dismiss all toasts
VapeUltra.Toast.dismissAll();

// Dismiss all toasts at specific position
VapeUltra.Toast.dismissAll('top-right');
```

---

## 🎯 Complete Integration Examples

### Example 1: User Creation Form with Validation

**File:** `modules/users/views/create.php`

```html
<form id="create-user-form">
    <div class="mb-3">
        <label for="user-name" class="form-label">Name *</label>
        <input type="text" class="form-control" id="user-name" required>
    </div>

    <div class="mb-3">
        <label for="user-email" class="form-label">Email *</label>
        <input type="email" class="form-control" id="user-email" required>
    </div>

    <div class="mb-3">
        <label for="user-role" class="form-label">Role *</label>
        <select class="form-select" id="user-role" required>
            <option value="">Select role...</option>
            <option value="admin">Admin</option>
            <option value="manager">Manager</option>
            <option value="staff">Staff</option>
        </select>
    </div>

    <button type="submit" class="btn btn-primary">Create User</button>
    <button type="button" class="btn btn-secondary" onclick="history.back()">Cancel</button>
</form>

<script>
document.getElementById('create-user-form').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = {
        name: document.getElementById('user-name').value,
        email: document.getElementById('user-email').value,
        role: document.getElementById('user-role').value
    };

    VapeUltra.Ajax.post('/api/users', formData)
        .then(data => {
            VapeUltra.Toast.success('User created successfully!');
            setTimeout(() => {
                window.location.href = '/users';
            }, 1500);
        })
        .catch(error => {
            if (error.status === 422) {
                // Validation errors
                VapeUltra.Toast.error('Please check your input and try again.');
            } else {
                VapeUltra.Toast.error('Failed to create user. Please try again.');
            }
        });
});
</script>
```

### Example 2: Confirm Delete with AJAX

```javascript
function deleteUser(userId) {
    VapeUltra.Modal.confirm({
        title: 'Delete User',
        message: 'Are you sure you want to delete this user? This action cannot be undone.',
        type: 'danger',
        confirmLabel: 'Delete',
        cancelLabel: 'Cancel'
    }).then(confirmed => {
        if (confirmed) {
            VapeUltra.Ajax.delete(`/api/users/${userId}`)
                .then(() => {
                    VapeUltra.Toast.success('User deleted successfully');
                    // Remove row from table
                    document.getElementById(`user-row-${userId}`).remove();
                })
                .catch(() => {
                    VapeUltra.Toast.error('Failed to delete user');
                });
        }
    });
}
```

### Example 3: Load Data with Loading State

```javascript
function loadDashboardData() {
    VapeUltra.Ajax.get('/api/dashboard/stats')
        .then(data => {
            // Update dashboard
            document.getElementById('total-sales').textContent = data.totalSales;
            document.getElementById('total-customers').textContent = data.totalCustomers;
            document.getElementById('pending-orders').textContent = data.pendingOrders;

            VapeUltra.Toast.success('Dashboard refreshed');
        })
        .catch(() => {
            VapeUltra.Toast.error('Failed to load dashboard data');
        });
}
```

### Example 4: Handle 401 Session Expiry

The error handler automatically handles 401 responses and redirects to login. You can also handle manually:

```javascript
VapeUltra.Ajax.get('/api/protected-resource')
    .then(data => {
        // Success
    })
    .catch(error => {
        if (error.status === 401) {
            // Session expired - automatically handled by ErrorHandler
            // User will see modal and be redirected to login
        }
    });
```

### Example 5: Advanced Modal with Dynamic Content

```javascript
function showUserDetails(userId) {
    // Show loading modal
    const modal = VapeUltra.Modal.open({
        title: 'User Details',
        content: '<div class="text-center"><div class="spinner-border"></div><p>Loading...</p></div>',
        size: 'lg',
        closable: true,
        buttons: []
    });

    // Load user data
    VapeUltra.Ajax.get(`/api/users/${userId}`)
        .then(user => {
            // Update modal content
            const content = `
                <div class="user-details">
                    <h4>${user.name}</h4>
                    <p><strong>Email:</strong> ${user.email}</p>
                    <p><strong>Role:</strong> ${user.role}</p>
                    <p><strong>Created:</strong> ${user.created_at}</p>
                </div>
            `;

            modal.element.querySelector('.modal-body').innerHTML = content;
        })
        .catch(() => {
            modal.close();
            VapeUltra.Toast.error('Failed to load user details');
        });
}
```

---

## 🎨 CSS Styling for Custom Components

All VapeUltra components use design system variables. You can customize by overriding variables:

```css
:root {
    /* Override primary color */
    --vape-primary-500: #8b5cf6;  /* Change to purple */

    /* Override toast position */
    --toast-offset-x: 24px;
    --toast-offset-y: 24px;
}
```

---

## ✅ Best Practices

### 1. **Always Use Master Template**
Never create standalone HTML pages. Always use `master.php` for consistent layout.

### 2. **Handle Errors Gracefully**
Let ErrorHandler catch errors automatically. Add manual catches only for critical flows.

### 3. **Show User Feedback**
Use Toast for success/error feedback. Use Modal for confirmations.

### 4. **Keep AJAX Simple**
Use the AJAX client for all HTTP requests. It handles CSRF, retries, and errors automatically.

### 5. **Follow Design System**
Use design system colors, spacing, and components. Don't add custom styles without good reason.

### 6. **Accessibility First**
All components are accessible. Don't remove ARIA attributes or keyboard navigation.

---

## 🚀 Next Steps

1. **Read Design System:** `DESIGN_SYSTEM.md`
2. **Review Master Template:** `layouts/master.php`
3. **Explore Components:** `components/`
4. **Check JavaScript Libraries:** `js/`
5. **Build Your First Module**

---

**Questions?** Contact the development team or check the internal wiki.

**Happy Coding!** 🎉
