# 🎯 VapeUltra Quick Reference Cheat Sheet

**Print this and keep it handy!**

---

## 📝 BASIC PAGE TEMPLATE

```php
<?php
/**
 * [PAGE NAME] - VapeUltra Theme
 * Module: [MODULE NAME]
 */
require_once __DIR__ . '/../bootstrap.php';
ob_start();
?>

<!-- YOUR CONTENT HERE -->
<div class="container-fluid">
    <h1>Your Page</h1>
</div>

<?php
$pageContent = ob_get_clean();

$breadcrumb = [
    ['label' => 'Home', 'url' => '/', 'icon' => 'bi bi-house'],
    ['label' => 'Module', 'active' => true]
];

$subnav = [
    ['label' => 'Tab 1', 'url' => '/page1', 'active' => true],
    ['label' => 'Tab 2', 'url' => '/page2']
];

$renderer->render('master', [
    'title' => 'Page Title - CIS 2.0',
    'content' => $pageContent,
    'showBreadcrumb' => true,
    'breadcrumb' => $breadcrumb,
    'showSubnav' => true,
    'subnav' => $subnav
]);
?>
```

---

## 🍞 BREADCRUMB OPTIONS

```php
$breadcrumb = [
    // Basic item
    ['label' => 'Home', 'url' => '/'],

    // With icon
    ['label' => 'Sales', 'url' => '/sales', 'icon' => 'bi bi-cart'],

    // Active (current page)
    ['label' => 'Dashboard', 'active' => true],

    // With icon and active
    ['label' => 'Reports', 'active' => true, 'icon' => 'bi bi-graph-up']
];
```

---

## 🧭 SUB-NAVIGATION OPTIONS

```php
$subnav = [
    // Basic tab
    ['label' => 'Overview', 'url' => '/overview'],

    // With icon
    ['label' => 'Settings', 'url' => '/settings', 'icon' => 'bi bi-gear'],

    // Active tab
    ['label' => 'Dashboard', 'url' => '/dashboard', 'active' => true],

    // With badge
    ['label' => 'Messages', 'url' => '/messages', 'badge' => ['count' => 5, 'type' => 'danger']],

    // Disabled
    ['label' => 'Coming Soon', 'url' => '#', 'disabled' => true]
];
```

**Styles:**
- `'subnavStyle' => 'horizontal'` (tabs, default)
- `'subnavStyle' => 'vertical'` (sidebar)

**Alignment:**
- `'subnavAlign' => 'left'` (default)
- `'subnavAlign' => 'center'`
- `'subnavAlign' => 'right'`

---

## 🎨 LAYOUT OPTIONS

```php
$renderer->render('master', [
    'title' => 'Page Title',
    'content' => $pageContent,

    // Navigation
    'showBreadcrumb' => true,       // Show breadcrumb
    'breadcrumb' => $breadcrumb,
    'showSubnav' => true,           // Show sub-navigation
    'subnav' => $subnav,
    'subnavStyle' => 'horizontal',  // or 'vertical'
    'subnavAlign' => 'left',        // or 'center', 'right'

    // Layout sections
    'showHeader' => true,           // Show main header
    'showSidebar' => true,          // Show left sidebar
    'showSidebarRight' => false,    // Show right sidebar
    'showFooter' => true,           // Show footer

    // Layout variants
    'layout' => 'full'              // 'full', 'minimal', 'print', 'error'
]);
```

---

## 🌐 AJAX CLIENT

```javascript
// GET request
VapeUltra.Ajax.get('/api/users')
    .then(data => console.log(data))
    .catch(error => console.error(error));

// POST request
VapeUltra.Ajax.post('/api/users', { name: 'John' })
    .then(data => console.log('Created:', data));

// PUT request
VapeUltra.Ajax.put('/api/users/123', { name: 'Jane' })
    .then(data => console.log('Updated'));

// DELETE request
VapeUltra.Ajax.delete('/api/users/123')
    .then(() => console.log('Deleted'));

// With query parameters
VapeUltra.Ajax.get('/api/users', {
    params: { page: 1, limit: 20 }
});

// Cancelable request
const cancel = VapeUltra.Ajax.get('/api/long-task', {
    cancelable: true
});
cancel(); // Cancel it
```

---

## 🎨 MODAL SYSTEM

```javascript
// Alert
VapeUltra.Modal.alert({
    title: 'Success',
    message: 'Changes saved!',
    type: 'success'  // success, error, warning, info
});

// Confirm
VapeUltra.Modal.confirm({
    title: 'Delete Item',
    message: 'Are you sure?',
    type: 'danger'
}).then(result => {
    if (result) {
        // User confirmed
    }
});

// Prompt
VapeUltra.Modal.prompt({
    title: 'Enter Name',
    message: 'Your name:',
    defaultValue: 'John'
}).then(value => {
    if (value !== null) {
        console.log('Entered:', value);
    }
});

// Custom modal
VapeUltra.Modal.open({
    title: 'Custom Modal',
    content: '<div>Your HTML</div>',
    size: 'lg',  // sm, md, lg, xl, fullscreen
    buttons: [
        {
            label: 'Cancel',
            variant: 'secondary',
            onClick: (modal) => modal.close()
        },
        {
            label: 'Save',
            variant: 'primary',
            onClick: (modal) => {
                // Save action
            }
        }
    ]
});
```

---

## 🍞 TOAST NOTIFICATIONS

```javascript
// Success
VapeUltra.Toast.success('Saved successfully!');

// Error
VapeUltra.Toast.error('Failed to save');

// Warning
VapeUltra.Toast.warning('Please review');

// Info
VapeUltra.Toast.info('Welcome!');

// With options
VapeUltra.Toast.success('Saved!', {
    duration: 3000,           // 3 seconds
    position: 'top-right'     // top-left, top-center, etc.
});

// With action
VapeUltra.Toast.show({
    message: 'Item deleted',
    type: 'info',
    actions: [
        {
            label: 'Undo',
            onClick: (toast) => {
                // Undo action
                toast.dismiss();
            }
        }
    ]
});

// Persistent (no auto-dismiss)
VapeUltra.Toast.error('Critical error', {
    duration: 0,  // Never dismiss
    closable: true
});

// Dismiss all
VapeUltra.Toast.dismissAll();
```

---

## 🚨 ERROR HANDLING

```javascript
// Automatic (already setup)
// All errors caught automatically

// Manual error reporting
try {
    riskyFunction();
} catch (error) {
    VapeUltra.ErrorHandler.catch(error, {
        context: 'user_action',
        details: 'Additional info'
    });
}

// Get error log
const errors = VapeUltra.ErrorHandler.getErrors();

// Export errors
const errorJSON = VapeUltra.ErrorHandler.exportErrors();
```

---

## 🎨 DESIGN SYSTEM COLORS

**CSS Variables:**
```css
/* Primary (Indigo) */
var(--vape-primary-50)    /* Lightest */
var(--vape-primary-500)   /* Main: #6366f1 */
var(--vape-primary-900)   /* Darkest */

/* Secondary (Purple) */
var(--vape-secondary-500) /* Main: #a855f7 */

/* Semantic Colors */
var(--vape-success-500)   /* Green: #10b981 */
var(--vape-error-500)     /* Red: #ef4444 */
var(--vape-warning-500)   /* Amber: #f59e0b */
var(--vape-info-500)      /* Blue: #3b82f6 */

/* Neutral */
var(--vape-gray-500)      /* Gray: #6b7280 */
```

---

## 📏 SPACING

**CSS Classes:**
```css
/* Margin */
.m-0, .m-1, .m-2, .m-3, .m-4, .m-5, .m-6

/* Padding */
.p-0, .p-1, .p-2, .p-3, .p-4, .p-5, .p-6

/* Specific sides */
.mt-3  /* Margin top */
.mb-4  /* Margin bottom */
.px-5  /* Padding left & right */
.py-2  /* Padding top & bottom */
```

**Spacing Scale:**
- 0: 0px
- 1: 4px
- 2: 8px
- 3: 12px
- 4: 16px
- 5: 20px
- 6: 24px
- 8: 32px
- 10: 40px
- 12: 48px

---

## 🔤 TYPOGRAPHY

**CSS Classes:**
```css
/* Font sizes */
.text-xs    /* 12px */
.text-sm    /* 14px */
.text-base  /* 16px */
.text-lg    /* 18px */
.text-xl    /* 20px */
.text-2xl   /* 24px */

/* Font weights */
.font-light      /* 300 */
.font-normal     /* 400 */
.font-medium     /* 500 */
.font-semibold   /* 600 */
.font-bold       /* 700 */
```

---

## 🔧 COMMON PATTERNS

### Load Data with AJAX
```javascript
function loadData() {
    VapeUltra.Ajax.get('/api/data')
        .then(data => {
            // Update page
            document.getElementById('result').textContent = data.value;
            VapeUltra.Toast.success('Data loaded');
        })
        .catch(() => {
            VapeUltra.Toast.error('Failed to load data');
        });
}
```

### Delete with Confirmation
```javascript
function deleteItem(id) {
    VapeUltra.Modal.confirm({
        title: 'Delete Item',
        message: 'Are you sure?',
        type: 'danger'
    }).then(confirmed => {
        if (confirmed) {
            VapeUltra.Ajax.delete(`/api/items/${id}`)
                .then(() => {
                    VapeUltra.Toast.success('Deleted');
                    document.getElementById(`item-${id}`).remove();
                });
        }
    });
}
```

### Form Submission
```javascript
document.getElementById('myForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = {
        name: document.getElementById('name').value,
        email: document.getElementById('email').value
    };

    VapeUltra.Ajax.post('/api/users', formData)
        .then(data => {
            VapeUltra.Toast.success('User created!');
            window.location.href = '/users';
        })
        .catch(error => {
            if (error.status === 422) {
                VapeUltra.Toast.error('Please check your input');
            }
        });
});
```

---

## 📚 DOCUMENTATION FILES

- **DESIGN_SYSTEM.md** - Complete style guide
- **USAGE_EXAMPLES.md** - Code examples for everything
- **BUILD_COMPLETE.md** - Achievement summary
- **INTEGRATION_GUIDE.md** - Integration procedures

---

## 🆘 TROUBLESHOOTING

### Page Not Loading?
1. Check `$renderer` is available
2. Verify `master.php` path
3. Check browser console for errors

### Styles Not Applying?
1. Check CSS load order in `master.php`
2. Clear browser cache
3. Verify `variables.css` is loaded

### AJAX Not Working?
1. Check browser console
2. Verify VapeUltra.Ajax is initialized
3. Check network tab for request details

### Modal/Toast Not Showing?
1. Check browser console for errors
2. Verify JavaScript files are loaded
3. Check z-index conflicts

---

**Keep this handy! 📌**

For detailed examples, see `USAGE_EXAMPLES.md`
