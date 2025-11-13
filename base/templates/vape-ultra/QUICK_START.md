# VapeUltra Template - Quick Start Guide

**TL;DR:** Your new template system is ready. Here's how to use it for every page.

---

## 🚀 Basic Usage Pattern

### Step 1: Create Your Page File
```php
<?php
// /admin/pages/your-page.php
require_once __DIR__ . '/../../modules/base/templates/vape-ultra/config.php';
require_once __DIR__ . '/../../modules/base/templates/vape-ultra/layouts/main.php';

// Your page logic here
$alerts = getYourAlerts();
$stats = getYourStats();
```

### Step 2: Define Page Content
```php
// Set page info
$page = [
    'title' => 'Page Title',
    'breadcrumbs' => [
        'Home' => '/admin/',
        'Section' => '/admin/section/',
        'Current Page' => null
    ],
    'icon' => 'fas fa-your-icon'
];

// Your HTML content
$content = <<<HTML
<div class="page-content">
    <!-- Your content here -->
</div>
HTML;
```

### Step 3: Render the Layout
```php
// Render with the main layout
renderMainLayout($page, $content);
```

**That's it!** Your page will have:
- ✅ Proper header
- ✅ Left sidebar with navigation
- ✅ Right sidebar with widgets
- ✅ Footer
- ✅ Responsive design
- ✅ All styling applied

---

## 📋 Common Components

### Alert Box
```html
<div class="alert alert-info alert-dismissible fade show" role="alert">
    <i class="fas fa-info-circle"></i> <strong>Info:</strong> Your message
    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
</div>
```

### Button Styles
```html
<!-- Primary -->
<button class="btn btn-primary">Click Me</button>

<!-- Secondary -->
<button class="btn btn-secondary">Cancel</button>

<!-- Success -->
<button class="btn btn-success">Save</button>

<!-- Danger -->
<button class="btn btn-danger">Delete</button>

<!-- With Icon -->
<button class="btn btn-primary">
    <i class="fas fa-save"></i> Save
</button>
```

### Card Layout
```html
<div class="card">
    <div class="card-header">
        <h5 class="card-title">Title</h5>
    </div>
    <div class="card-body">
        Content here
    </div>
    <div class="card-footer">
        Footer content
    </div>
</div>
```

### Grid Layout
```html
<div class="row">
    <div class="col-md-6">
        <!-- 50% on desktop, 100% on mobile -->
    </div>
    <div class="col-md-6">
        <!-- 50% on desktop, 100% on mobile -->
    </div>
</div>
```

### Form Input
```html
<div class="mb-3">
    <label for="inputField" class="form-label">Field Label</label>
    <input type="text" class="form-control" id="inputField" placeholder="Enter value">
</div>
```

---

## 🎨 Styling Classes

### Text Colors
```html
<p class="text-primary">Primary text</p>
<p class="text-success">Success text</p>
<p class="text-danger">Danger text</p>
<p class="text-warning">Warning text</p>
<p class="text-info">Info text</p>
<p class="text-muted">Muted text</p>
```

### Background Colors
```html
<div class="bg-light">Light background</div>
<div class="bg-primary text-white">Primary background</div>
<div class="bg-success text-white">Success background</div>
```

### Spacing (Margin/Padding)
```html
<!-- Margin: m-3 (24px), mt-2 (16px), mb-4 (32px), etc. -->
<div class="mt-3 mb-2 ms-4 me-1">Spacing example</div>

<!-- Padding: p-3 (24px), pt-2 (16px), pb-4 (32px), etc. -->
<div class="p-3 ps-2 pe-4">Padding example</div>
```

### Display/Visibility
```html
<!-- Hide on mobile, show on desktop -->
<div class="d-none d-md-block">Desktop only</div>

<!-- Show on mobile, hide on desktop -->
<div class="d-md-none">Mobile only</div>

<!-- Flexbox -->
<div class="d-flex justify-content-between align-items-center">
    <div>Left</div>
    <div>Right</div>
</div>
```

---

## 🔧 JavaScript Usage

### Initialize Component
```javascript
// Use VapeUltra.Core
document.addEventListener('DOMContentLoaded', function() {
    VapeUltra.Core.init();
});
```

### Show Notification
```javascript
// Success notification
VapeUltra.Notifications.show('Success!', 'Operation completed', 'success');

// Error notification
VapeUltra.Notifications.show('Error!', 'Something went wrong', 'danger');

// Info notification
VapeUltra.Notifications.show('Info', 'Important information', 'info');
```

### API Call
```javascript
// GET request
VapeUltra.API.get('/api/endpoint', function(data) {
    console.log(data);
});

// POST request
VapeUltra.API.post('/api/endpoint', {
    param1: 'value1',
    param2: 'value2'
}, function(data) {
    console.log(data);
});
```

### DOM Manipulation
```javascript
// Get element
const element = VapeUltra.Components.get('#element-id');

// Show/hide
VapeUltra.Components.show('#element-id');
VapeUltra.Components.hide('#element-id');

// Add/remove class
VapeUltra.Components.addClass('#element-id', 'active');
VapeUltra.Components.removeClass('#element-id', 'active');
```

---

## 📱 Responsive Breakpoints

When designing, keep these in mind:

```css
/* Mobile first (base styles) */
.my-element { /* Applies to all sizes */ }

/* Tablet and up */
@media (min-width: 768px) { /* 768px+ */ }

/* Desktop and up */
@media (min-width: 1200px) { /* 1200px+ */ }

/* Large desktop */
@media (min-width: 1400px) { /* 1400px+ */ }
```

**Or use Bootstrap utility classes:**
```html
<!-- Extra small (mobile) -->
<div class="col-12">Full width</div>

<!-- Small and up (tablets) -->
<div class="col-sm-6">50% on tablet+</div>

<!-- Medium and up (desktop) -->
<div class="col-md-4">33% on desktop+</div>

<!-- Large and up (large desktop) -->
<div class="col-lg-3">25% on large+</div>
```

---

## 🎯 Create a New Alert Component

Here's how to add a new alert type to the sidebar:

### 1. Create the Alert HTML
```html
<!-- In sidebar.php, add new alert group -->
<div class="alert-group">
    <h6><i class="fas fa-icon"></i> Alert Title</h6>
    <div class="alert-items">
        <!-- Items will be loaded here -->
    </div>
</div>
```

### 2. Add the Data Fetching
```php
// In your page logic
$newAlerts = $db->query("
    SELECT * FROM your_table
    WHERE user_id = ? AND status = 'pending'
    ORDER BY created_at DESC
    LIMIT 5
", [$userId]);
```

### 3. Render the Items
```php
foreach ($newAlerts as $alert) {
    echo <<<HTML
    <div class="alert-item">
        <div class="alert-text">
            <strong>{$alert['title']}</strong><br>
            <small>{$alert['description']}</small>
        </div>
        <div class="alert-time">{$alert['created_at']}</div>
    </div>
    HTML;
}
```

### 4. Add Navigation Badge
```php
// Update sidebar count
$alertCount = count($newAlerts);
// Show count in nav badge: <span class="badge bg-danger"><?= $alertCount ?></span>
```

---

## 🔐 Security Best Practices

### Always Escape Output
```php
// ❌ DON'T
echo $userInput;

// ✅ DO
echo htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8');

// ✅ Or use shorthand
echo esc($userInput);
```

### Validate Input
```php
// Always validate and sanitize
$email = filter_var($_POST['email'], FILTER_SANITIZE_EMAIL);
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    die('Invalid email');
}
```

### Use Prepared Statements
```php
// ❌ DON'T (SQL injection vulnerability)
$result = $db->query("SELECT * FROM users WHERE id = " . $_GET['id']);

// ✅ DO (Safe)
$result = $db->query("SELECT * FROM users WHERE id = ?", [$_GET['id']]);
```

### Check Permissions
```php
// Always verify user has permission
if ($user['role'] !== 'admin') {
    die('Unauthorized');
}
```

---

## 🐛 Troubleshooting

### Issue: Page doesn't load template
**Solution:** Check that config.php path is correct:
```php
require_once __DIR__ . '/../../modules/base/templates/vape-ultra/config.php';
```

### Issue: Styling doesn't apply
**Solution:** Ensure CSS is linked in main.php:
```php
<link rel="stylesheet" href="/modules/base/templates/vape-ultra/assets/css/variables.css">
<link rel="stylesheet" href="/modules/base/templates/vape-ultra/assets/css/base.css">
```

### Issue: Sidebar doesn't show
**Solution:** Pass correct breadcrumbs array:
```php
$page = [
    'breadcrumbs' => [
        'Home' => '/admin/',
        'Current' => null
    ]
];
```

### Issue: Mobile layout broken
**Solution:** Check viewport meta tag in base.php:
```html
<meta name="viewport" content="width=device-width, initial-scale=1.0">
```

### Issue: JavaScript not working
**Solution:** Ensure JavaScript files are included before closing `</body>`:
```php
<script src="/modules/base/templates/vape-ultra/assets/js/core.js"></script>
<script src="/modules/base/templates/vape-ultra/assets/js/api.js"></script>
```

---

## 📚 File Structure Reference

```
vape-ultra/
├── config.php                    ← Load this first!
├── layouts/
│   ├── base.php                 ← Foundation HTML
│   ├── main.php                 ← Standard layout
│   └── minimal.php              ← Centered layout
├── components/
│   ├── header.php               ← Top nav
│   ├── sidebar.php              ← Left nav
│   ├── sidebar-right.php        ← Right widgets
│   └── footer.php               ← Footer
└── assets/
    ├── css/
    │   ├── variables.css        ← Colors & spacing
    │   ├── base.css             ← Reset & base
    │   ├── layout.css           ← Grid system
    │   ├── components.css       ← Component styles
    │   └── utilities.css        ← Helper classes
    └── js/
        ├── core.js              ← Main namespace
        ├── api.js               ← API calls
        ├── notifications.js     ← Toast alerts
        └── utils.js             ← Helper functions
```

---

## ✨ Tips & Tricks

1. **Use Badge Counters:** Show alert counts in navigation
2. **Toast Notifications:** Use for temporary messages
3. **Breadcrumbs:** Always show user location
4. **Mobile First:** Design for mobile, enhance for desktop
5. **Consistent Spacing:** Use the spacing system (8px units)
6. **Accessible Colors:** Use semantic colors (success=green, danger=red)
7. **Icon Usage:** Use FontAwesome for consistency
8. **Loading States:** Show spinners for long operations
9. **Error Messages:** Be specific and helpful
10. **Responsive Tables:** Use horizontal scroll on mobile

---

## 🎓 Next Steps

1. **Create your first page** using the template
2. **Use the alert components** for notifications
3. **Customize colors** in css/variables.css
4. **Add your custom JavaScript** in assets/js/
5. **Test on mobile** at every step

---

**Happy building! Your VapeUltra template system is ready to power the entire CIS dashboard!** 🚀
