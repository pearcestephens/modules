# 🎯 VapeUltra Master Integration Guide

**Complete Procedures & Protocols for Editing, Changing, and Applying VapeUltra Theme**

---

## 📚 TABLE OF CONTENTS

1. [Overview](#overview)
2. [Quick Start for New Pages](#quick-start-for-new-pages)
3. [Converting Existing Pages](#converting-existing-pages)
4. [Automated Conversion Tool](#automated-conversion-tool)
5. [Module-by-Module Integration](#module-by-module-integration)
6. [Common Scenarios](#common-scenarios)
7. [Troubleshooting](#troubleshooting)
8. [Best Practices](#best-practices)

---

## 🎯 OVERVIEW

### What is VapeUltra?

VapeUltra is the **new production-grade theme system** for CIS 2.0. It provides:
- ✅ Beautiful, modern design (Indigo + Purple)
- ✅ Consistent UI across all modules
- ✅ Built-in error handling & AJAX client
- ✅ Accessible components (WCAG 2.1 AA)
- ✅ Mobile-first responsive design
- ✅ Enterprise-ready code quality

### Key Principles

1. **Single Template:** All pages use `master.php` (no exceptions)
2. **Content Blocks:** Pages define only their content
3. **Design System:** All styling comes from locked design system
4. **Component-Based:** Reusable breadcrumb, subnav, modals, toasts
5. **Accessibility First:** ARIA labels, keyboard nav, screen readers

---

## 🆕 QUICK START FOR NEW PAGES

### Step 1: Create Your View File

**Location:** `modules/[your-module]/views/[page-name].php`

```php
<?php
/**
 * [Page Name] - VapeUltra Theme
 * Module: [Module Name]
 * Page: [Page Name]
 */
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';

// Start output buffering to capture page content
ob_start();
?>

<!-- ============================================ -->
<!-- YOUR PAGE CONTENT STARTS HERE -->
<!-- ============================================ -->

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-12">
            <h1>Your Page Title</h1>
            <p class="lead">Page description goes here</p>
        </div>
    </div>

    <div class="row">
        <div class="col-md-6">
            <div class="card">
                <div class="card-body">
                    <h5 class="card-title">Card Title</h5>
                    <p class="card-text">Your content here</p>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ============================================ -->
<!-- YOUR PAGE CONTENT ENDS HERE -->
<!-- ============================================ -->

<?php
// Capture the output
$pageContent = ob_get_clean();

// Define breadcrumb navigation
$breadcrumb = [
    ['label' => 'Home', 'url' => '/', 'icon' => 'bi bi-house'],
    ['label' => 'Your Module', 'url' => '/modules/your-module/'],
    ['label' => 'Your Page', 'active' => true]
];

// Define sub-navigation (optional, remove if not needed)
$subnav = [
    ['label' => 'Dashboard', 'url' => '/modules/your-module/', 'icon' => 'bi bi-speedometer2'],
    ['label' => 'Your Page', 'url' => '/modules/your-module/?route=your-page', 'icon' => 'bi bi-file-text', 'active' => true],
    ['label' => 'Settings', 'url' => '/modules/your-module/?route=settings', 'icon' => 'bi bi-gear']
];

// Render with VapeUltra master template
$renderer->render('master', [
    'title' => 'Your Page Title - CIS 2.0',
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

### Step 2: Test Your Page

1. Navigate to your page URL
2. Check breadcrumb appears
3. Check sub-navigation appears (if used)
4. Verify responsive layout on mobile
5. Check browser console for errors

**Done!** ✅

---

## 🔄 CONVERTING EXISTING PAGES

### Current Structure (Before)

```php
<?php
require_once __DIR__ . '/../bootstrap.php';

$pageTitle = 'My Page';
$breadcrumbs = [...];
$pageCSS = [...];
$pageJS = [...];
ob_start();
?>

<!-- Content -->

<?php
$content = ob_get_clean();
require_once __DIR__ . '/../../base/templates/themes/modern/layouts/dashboard.php';
?>
```

### New Structure (After)

```php
<?php
/**
 * My Page - VapeUltra Theme
 * Module: My Module
 */
require_once __DIR__ . '/../bootstrap.php';
ob_start();
?>

<!-- Same content, no changes needed -->

<?php
$pageContent = ob_get_clean();

$breadcrumb = [...];  // Convert from $breadcrumbs
$subnav = [...];      // New addition

$renderer->render('master', [
    'title' => 'My Page - CIS 2.0',
    'content' => $pageContent,
    'showBreadcrumb' => true,
    'breadcrumb' => $breadcrumb,
    'showSubnav' => true,
    'subnav' => $subnav
]);
?>
```

### Conversion Steps

1. **Backup:** `cp file.php file.php.VAPEULTRA_BACKUP`
2. **Update header:** Remove old variables, add VapeUltra comment
3. **Keep content:** No changes to HTML content
4. **Convert breadcrumbs:** Change format if needed
5. **Add sub-nav:** Create `$subnav` array
6. **Update footer:** Replace old template call with `$renderer->render()`
7. **Test:** Load page and verify

### Breadcrumb Format Conversion

**Old Format:**
```php
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/', 'icon' => 'bi-house-door'],
    ['label' => 'Module', 'url' => '/module/', 'icon' => 'bi-box'],
    ['label' => 'Page', 'url' => '/module/?route=page', 'active' => true]
];
```

**New Format:**
```php
$breadcrumb = [
    ['label' => 'Home', 'url' => '/', 'icon' => 'bi bi-house'],  // Note: 'bi bi-' prefix
    ['label' => 'Module', 'url' => '/module/', 'icon' => 'bi bi-box'],
    ['label' => 'Page', 'active' => true]  // No URL for active item
];
```

---

## 🤖 AUTOMATED CONVERSION TOOL

We've created a CLI tool to automate conversions!

### Installation

```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/base/templates/vape-ultra-complete/tools
chmod +x convert-to-vapeultra.php
```

### Usage

**Scan all modules (dry run):**
```bash
php convert-to-vapeultra.php --scan --dry-run
```

**Scan and convert all modules:**
```bash
php convert-to-vapeultra.php --scan
```

**Convert specific file (dry run):**
```bash
php convert-to-vapeultra.php --module consignments --file ai-insights.php --dry-run
```

**Convert specific file:**
```bash
php convert-to-vapeultra.php --module consignments --file ai-insights.php
```

### What the Tool Does

1. ✅ Backs up original file (`.VAPEULTRA_BACKUP`)
2. ✅ Extracts page title from old file
3. ✅ Extracts breadcrumbs and converts format
4. ✅ Extracts page content
5. ✅ Generates new VapeUltra-compatible file
6. ✅ Writes new file in place

### After Conversion

1. **Test the page** - Load in browser
2. **Review sub-navigation** - Tool adds placeholder, customize it
3. **Check styling** - Ensure all styles work
4. **Test functionality** - AJAX, modals, etc.
5. **Commit changes** - Git commit with message

---

## 🎯 MODULE-BY-MODULE INTEGRATION

### Priority Order

1. **High Priority** (user-facing, frequently used)
   - Sales Dashboard
   - Inventory Management
   - Customer Management
   - Reports

2. **Medium Priority** (admin tools)
   - Consignments
   - Transfers
   - Settings
   - User Management

3. **Low Priority** (rarely used)
   - Admin tools
   - System settings
   - Developer tools

### Integration Strategy

**Option A: Big Bang (All at Once)**
- Convert all pages in one go
- Test thoroughly
- Deploy all at once
- **Risk:** High
- **Benefit:** Clean cutover

**Option B: Gradual (Module by Module)**
- Convert one module at a time
- Test each module
- Deploy incrementally
- **Risk:** Low
- **Benefit:** Safer, easier to rollback

**Option C: Page by Page**
- Convert highest traffic pages first
- Deploy as you go
- **Risk:** Lowest
- **Benefit:** Minimal disruption

### Recommended Approach: **Option B (Module by Module)**

1. Start with least critical module (test case)
2. Convert all pages in that module
3. Test thoroughly
4. Deploy to production
5. Monitor for issues
6. Move to next module
7. Repeat until complete

---

## 🎬 COMMON SCENARIOS

### Scenario 1: Simple Page (No AJAX)

```php
<?php
require_once __DIR__ . '/../bootstrap.php';
ob_start();
?>

<div class="container">
    <h1>Simple Page</h1>
    <p>Content here</p>
</div>

<?php
$pageContent = ob_get_clean();

$renderer->render('master', [
    'title' => 'Simple Page - CIS 2.0',
    'content' => $pageContent,
    'showBreadcrumb' => false,
    'showSubnav' => false
]);
?>
```

### Scenario 2: Page with AJAX

```php
<?php
require_once __DIR__ . '/../bootstrap.php';
ob_start();
?>

<div class="container">
    <h1>Dashboard</h1>
    <div id="stats"></div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    loadStats();
});

function loadStats() {
    VapeUltra.Ajax.get('/api/stats')
        .then(data => {
            document.getElementById('stats').innerHTML = `
                <p>Total Sales: $${data.totalSales}</p>
            `;
        })
        .catch(error => {
            VapeUltra.Toast.error('Failed to load stats');
        });
}
</script>

<?php
$pageContent = ob_get_clean();

$renderer->render('master', [
    'title' => 'Dashboard - CIS 2.0',
    'content' => $pageContent
]);
?>
```

### Scenario 3: Page with Form

```php
<?php
require_once __DIR__ . '/../bootstrap.php';
ob_start();
?>

<div class="container">
    <h1>Create User</h1>

    <form id="createUserForm">
        <div class="mb-3">
            <label for="name" class="form-label">Name</label>
            <input type="text" class="form-control" id="name" required>
        </div>

        <div class="mb-3">
            <label for="email" class="form-label">Email</label>
            <input type="email" class="form-control" id="email" required>
        </div>

        <button type="submit" class="btn btn-primary">Create</button>
    </form>
</div>

<script>
document.getElementById('createUserForm').addEventListener('submit', function(e) {
    e.preventDefault();

    const formData = {
        name: document.getElementById('name').value,
        email: document.getElementById('email').value
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
                VapeUltra.Toast.error('Please check your input');
            }
        });
});
</script>

<?php
$pageContent = ob_get_clean();

$renderer->render('master', [
    'title' => 'Create User - CIS 2.0',
    'content' => $pageContent
]);
?>
```

### Scenario 4: Page with Delete Confirmation

```php
<script>
function deleteItem(id) {
    VapeUltra.Modal.confirm({
        title: 'Delete Item',
        message: 'Are you sure you want to delete this item? This cannot be undone.',
        type: 'danger',
        confirmLabel: 'Delete',
        cancelLabel: 'Cancel'
    }).then(confirmed => {
        if (confirmed) {
            VapeUltra.Ajax.delete(`/api/items/${id}`)
                .then(() => {
                    VapeUltra.Toast.success('Item deleted');
                    document.getElementById(`item-${id}`).remove();
                })
                .catch(() => {
                    VapeUltra.Toast.error('Failed to delete item');
                });
        }
    });
}
</script>
```

---

## 🔧 TROUBLESHOOTING

### Problem: Page doesn't load

**Symptoms:**
- Blank page
- 500 error
- PHP fatal error

**Solutions:**
1. Check `$renderer` is available
2. Verify `master.php` path is correct
3. Check PHP error logs: `tail -f /path/to/error.log`
4. Verify `bootstrap.php` is loaded

### Problem: Styles not applying

**Symptoms:**
- Page looks unstyled
- Buttons don't have colors
- Layout is broken

**Solutions:**
1. Check browser console for 404 errors on CSS files
2. Clear browser cache (Ctrl+Shift+R)
3. Verify `variables.css` is loaded
4. Check CSS load order in `master.php`

### Problem: JavaScript not working

**Symptoms:**
- AJAX calls fail
- Modals don't open
- Toasts don't show

**Solutions:**
1. Check browser console for errors
2. Verify jQuery, Axios, VapeUltra JS files are loaded
3. Check network tab for 404s
4. Ensure code is in `DOMContentLoaded` event

### Problem: Navigation doesn't show

**Symptoms:**
- No breadcrumb
- No sub-navigation

**Solutions:**
1. Check `'showBreadcrumb' => true` is set
2. Verify `$breadcrumb` array is defined
3. Check for typos in array keys
4. Inspect HTML to see if elements exist but are hidden

### Problem: Mobile layout broken

**Symptoms:**
- Content too wide on mobile
- Elements overlap
- Horizontal scrolling

**Solutions:**
1. Check viewport meta tag exists (in `master.php`)
2. Verify Bootstrap 5 classes used correctly
3. Test responsive breakpoints
4. Check for fixed widths in custom CSS

---

## ✅ BEST PRACTICES

### 1. Always Use Master Template
Never create standalone HTML pages. Always use `$renderer->render('master', [...])`.

### 2. Follow Design System
Use design system colors, spacing, and typography. Don't add arbitrary values.

### 3. Content Only
View files should only contain page content. All layout comes from master template.

### 4. Consistent Navigation
Every page in a module should have the same sub-navigation for consistency.

### 5. Error Handling
Always handle errors gracefully. Use try-catch and show user-friendly messages.

### 6. Test Thoroughly
Test every page on desktop, tablet, and mobile before deploying.

### 7. Accessibility
Ensure keyboard navigation works, add ARIA labels, use semantic HTML.

### 8. Performance
Minimize AJAX calls, debounce/throttle events, optimize images.

### 9. Documentation
Add comments for complex logic, update module README files.

### 10. Version Control
Commit changes with descriptive messages, backup before major changes.

---

## 📚 REFERENCE DOCUMENTATION

- **Design System:** `DESIGN_SYSTEM.md`
- **Usage Examples:** `USAGE_EXAMPLES.md`
- **Quick Reference:** `QUICK_REFERENCE.md`
- **Integration Checklist:** `INTEGRATION_CHECKLIST.md`
- **Build Complete:** `BUILD_COMPLETE.md`

---

## 🆘 GETTING HELP

### 1. Check Documentation First
- Read `USAGE_EXAMPLES.md` for code examples
- Check `QUICK_REFERENCE.md` for syntax
- Review `DESIGN_SYSTEM.md` for styling

### 2. Check Browser Console
- Look for JavaScript errors
- Check network tab for failed requests
- Inspect elements to see HTML structure

### 3. Check Server Logs
- PHP error log
- Apache/Nginx error log
- Application log

### 4. Ask for Help
- Contact development team lead
- Post in team chat with:
  - What you're trying to do
  - What's happening instead
  - Error messages (if any)
  - Browser console output
  - Code snippet

---

## 🎉 SUCCESS METRICS

You've successfully integrated VapeUltra when:

✅ All pages use `master.php`
✅ Navigation is consistent across module
✅ Design system colors used everywhere
✅ No console errors
✅ Mobile responsive
✅ Accessibility compliant
✅ User feedback is positive

---

## 🚀 DEPLOYMENT PROTOCOL

### Pre-Deployment
1. Test on development
2. Test on staging
3. Run automated tests
4. Get code review approval
5. Create backup

### Deployment
1. Deploy to production during low-traffic period
2. Run smoke tests
3. Monitor error logs
4. Monitor user feedback

### Post-Deployment
1. Check error rates
2. Check page load times
3. Collect user feedback
4. Document any issues

### Rollback Plan
If issues occur:
1. Restore backup files
2. Clear cache
3. Notify users
4. Document what went wrong
5. Plan fix

---

## 📝 CONCLUSION

VapeUltra is a **complete, production-ready theme system** that makes CIS 2.0 beautiful, consistent, and professional.

**Follow this guide and you'll be able to:**
- ✅ Create new pages quickly
- ✅ Convert existing pages easily
- ✅ Maintain design consistency
- ✅ Build accessible interfaces
- ✅ Deliver impressive user experiences

**Questions?** Check the documentation files or ask the development team!

**Happy coding!** 🎉

---

_Last Updated: 2025-11-12_
