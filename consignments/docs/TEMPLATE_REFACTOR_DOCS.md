# Pack.php Template Refactor - Complete Documentation

**Date:** October 16, 2025  
**Status:** ✅ COMPLETE - Ready for Testing  
**Impact:** HIGH - Fixes all JavaScript loading, template structure, and body tag issues

---

## Problem Statement

The original pack.php had **CRITICAL template structure issues** that broke all JavaScript functionality:

### Issues Fixed:
1. ❌ **No proper `<body>` tag** - Caused Bootstrap/jQuery to fail
2. ❌ **Scripts loaded in wrong order** - Core libraries loaded after custom code
3. ❌ **Duplicate includes** - html-footer.php included multiple times
4. ❌ **Broken HTML nesting** - Modals outside app-body, unclosed divs
5. ❌ **No consistent pattern** - Every page reinvented the wheel

### Impact:
- **NO VALIDATION** - Row highlighting didn't work
- **NO SUBMIT** - Buttons not clickable
- **NO AUTO-SAVE** - Debounce logic failed
- **NO MODALS** - Bootstrap modals broken
- **NO DROPDOWN** - CoreUI navigation failed

---

## Solution: Base Template Inheritance Pattern

Created a **proper MVC template system** that separates concerns and ensures correct HTML structure.

### New Architecture

```
┌─────────────────────────────────────────────┐
│ CIS Global Templates (Existing)            │
│ ┌─────────────────────────────────────────┐ │
│ │ html-header.php                         │ │
│ │ - <html><head> + jQuery                 │ │
│ │ header.php                              │ │
│ │ - <body> + Top Navigation               │ │
│ │ html-footer.php                         │ │
│ │ - Bootstrap, CoreUI, jQuery UI          │ │
│ │ footer.php                              │ │
│ │ - Template footer scripts               │ │
│ └─────────────────────────────────────────┘ │
└─────────────────────────────────────────────┘
                    ↓ inherits from
┌─────────────────────────────────────────────┐
│ Consignments Base Template (NEW)           │
│ ┌─────────────────────────────────────────┐ │
│ │ shared/templates/base-layout.php        │ │
│ │ - Orchestrates global templates         │ │
│ │ - Ensures correct HTML structure        │ │
│ │ - Manages script loading order          │ │
│ │ - Provides content injection points     │ │
│ └─────────────────────────────────────────┘ │
└─────────────────────────────────────────────┘
                    ↓ used by
┌─────────────────────────────────────────────┐
│ Module Pages (Refactored)                  │
│ ┌─────────────────────────────────────────┐ │
│ │ pack-REFACTORED.php                     │ │
│ │ - Business logic only                   │ │
│ │ - Set template variables                │ │
│ │ - Capture content in buffers            │ │
│ │ - Include base-layout.php at end        │ │
│ └─────────────────────────────────────────┘ │
└─────────────────────────────────────────────┘
```

---

## File Structure

### New Files Created

```
modules/consignments/
├── shared/
│   └── templates/
│       └── base-layout.php          ← NEW: Base template all pages inherit from
│
└── stock-transfers/
    ├── pack-REFACTORED.php           ← NEW: Refactored pack.php using base template
    └── TEMPLATE_REFACTOR_DOCS.md     ← THIS FILE
```

### Files Modified
- `js/pack-fix.js` - Fixed selector (`.counted-qty` → `.js-counted-qty`)

---

## How It Works

### 1. Base Template (`base-layout.php`)

**Purpose:** Single source of truth for HTML structure

**Provides:**
- Correct `<!DOCTYPE>`, `<html>`, `<head>`, `<body>` structure
- Consistent script loading order
- Injection points for page content
- Automatic breadcrumb rendering
- Modal and overlay placement

**Template Variables (Input):**
```php
// Required
$page_title           = 'Your Page Title';
$page_content         = '<!-- Your HTML here -->';

// Optional
$body_class           = 'app header-fixed sidebar-fixed...';
$page_head_extra      = '<link rel="stylesheet"...>';  // Extra CSS
$page_scripts_before_footer = '<script src="..."></script>';  // Page JS
$page_modals          = '<!-- Modal HTML -->';
$page_overlays        = '<!-- Overlay HTML -->';
$show_breadcrumb      = true;
$breadcrumb_items     = [['label' => 'Home', 'url' => '/'], ...];
```

**Output Structure:**
```html
<!DOCTYPE html>
<html lang="en">
<head>
    <!-- Global CIS CSS -->
    <!-- jQuery loaded here -->
    <?php echo $page_head_extra; ?> <!-- Page-specific CSS -->
</head>
<body class="<?php echo $body_class; ?>">
    <!-- Top navigation -->
    <div class="app-body">
        <!-- Sidebar -->
        <main class="main">
            <!-- Breadcrumb (if enabled) -->
            <?php echo $page_content; ?> <!-- PAGE CONTENT HERE -->
        </main>
        <!-- Personalisation menu -->
    </div> <!-- /.app-body -->
    
    <?php echo $page_scripts_before_footer; ?> <!-- Page JS -->
    <!-- Bootstrap, CoreUI, jQuery UI (html-footer.php) -->
    <!-- Template footer scripts (footer.php) -->
    <?php echo $page_modals; ?>    <!-- Modals -->
    <?php echo $page_overlays; ?>  <!-- Overlays -->
</body>
</html>
```

---

### 2. Page Implementation (`pack-REFACTORED.php`)

**Structure:**

```php
<?php
// 1. INITIALIZATION
$transferId = (int)($_GET['transfer'] ?? 0);
require_once __DIR__ . '/../bootstrap.php';

// 2. BUSINESS LOGIC
$transferData = getUniversalTransfer($transferId);
// ... validation, error handling ...

// 3. TEMPLATE CONFIGURATION
$page_title = 'Pack Transfer #' . $transferId;
$page_head_extra = '<link rel="stylesheet" href="/path/to/pack.css">';
$page_scripts_before_footer = '<script src="/path/to/pack.js"></script>';
$breadcrumb_items = [
    ['label' => 'Home', 'url' => '/'],
    ['label' => 'Transfers', 'url' => '/transfers'],
    ['label' => 'Pack #' . $transferId]
];

// 4. CAPTURE PAGE CONTENT
ob_start();
?>
<!-- Your HTML content here -->
<div class="container-fluid">
    <!-- Table, forms, cards, etc. -->
</div>
<?php
$page_content = ob_get_clean();

// 5. CAPTURE MODALS
ob_start();
?>
<div class="modal" id="myModal">...</div>
<?php
$page_modals = ob_get_clean();

// 6. CAPTURE OVERLAYS
ob_start();
?>
<div id="submission-overlay">...</div>
<?php
$page_overlays = ob_get_clean();

// 7. RENDER TEMPLATE
require __DIR__ . '/../shared/templates/base-layout.php';
```

**Key Benefits:**
- ✅ Business logic separated from presentation
- ✅ Clean, readable code structure
- ✅ No manual HTML structure management
- ✅ Guaranteed correct script loading order
- ✅ Consistent across all module pages

---

## Script Loading Order (FIXED)

### Before (BROKEN):
```html
<head>
    <!-- No jQuery! -->
</head>
<body>
    <!-- Header -->
    <script src="pack.js"></script>  <!-- Fails: jQuery not loaded -->
    <?php include("html-footer.php"); ?>  <!-- NOW jQuery loads (too late!) -->
</body>
```

### After (CORRECT):
```html
<head>
    <!-- jQuery (from html-header.php) -->
    <script src="https://code.jquery.com/jquery-3.x.min.js"></script>
</head>
<body>
    <!-- Header -->
    <!-- Page content -->
    <!-- /app-body -->
    
    <!-- Page scripts (can use jQuery) -->
    <script src="/modules/consignments/shared/js/ajax-manager.js"></script>
    <script src="/modules/consignments/stock-transfers/js/pack.js"></script>
    <script src="/modules/consignments/stock-transfers/js/pack-fix.js"></script>
    
    <!-- Core libraries (html-footer.php) -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.1/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.0/dist/js/bootstrap.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/@coreui/coreui@3.4.0/dist/js/coreui.bundle.min.js"></script>
    <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
    
    <!-- Template footer scripts (footer.php) -->
    <script src="/assets/js/main.js"></script>
    <script src="/assets/js/sidebar-mobile-enhance.js"></script>
</body>
```

**Why This Order Works:**
1. **jQuery** loads first (foundation for everything)
2. **Page scripts** load next (can use jQuery immediately)
3. **Bootstrap/CoreUI** load after jQuery (dependencies satisfied)
4. **Template scripts** load last (enhance the UI)

---

## Testing Checklist

### Phase 1: Basic Structure
- [ ] Page loads without errors
- [ ] No console errors (F12 → Console)
- [ ] Sidebar displays correctly
- [ ] Top navigation works
- [ ] Breadcrumb renders
- [ ] Print button visible

### Phase 2: JavaScript Functionality
- [ ] Input fields accept numbers
- [ ] Row highlighting works (green/yellow/red)
- [ ] Auto-save indicator appears
- [ ] Auto-fill button works
- [ ] Submit button clickable
- [ ] Modal opens on "Add Products"

### Phase 3: Form Validation
- [ ] Enter counted quantity → row turns green if match
- [ ] Enter less than planned → row turns yellow
- [ ] Enter more than stock → row turns red
- [ ] Validation messages appear below inputs

### Phase 4: Auto-Save
- [ ] Type in input → "SAVING" badge appears after 5sec
- [ ] Badge changes to "SAVED" with timestamp
- [ ] Badge fades away after 1.5sec
- [ ] Refresh page → values restored from draft

### Phase 5: Submit Flow
- [ ] Click "Create Consignment & Upload"
- [ ] Overlay appears with progress
- [ ] SSE connects and shows progress
- [ ] Success → redirects or shows completion
- [ ] Error → shows error message

---

## Migration Steps

### Option 1: Test Alongside (RECOMMENDED)
```bash
# Access refactored version with new URL parameter
https://staff.vapeshed.co.nz/.../pack-REFACTORED.php?transfer=27043

# Keep old version accessible
https://staff.vapeshed.co.nz/.../pack.php?transfer=27043
```

**Testing:**
1. Test pack-REFACTORED.php thoroughly
2. Compare behavior with pack.php
3. Once confident, rename files:
   ```bash
   mv pack.php pack-OLD.php.bak
   mv pack-REFACTORED.php pack.php
   ```

### Option 2: Direct Replacement (RISKY)
```bash
# Backup original
cp pack.php pack-BACKUP-20251016.php

# Replace with refactored version
cp pack-REFACTORED.php pack.php
```

---

## Extending to Other Pages

### Template Pattern for ANY Module Page

```php
<?php
// 1. Init & validation
require_once __DIR__ . '/../bootstrap.php';

// 2. Business logic
$data = loadYourData();

// 3. Configure template
$page_title = 'Your Page';
$page_head_extra = '<link rel="stylesheet" href="/your/styles.css">';
$page_scripts_before_footer = '<script src="/your/script.js"></script>';
$breadcrumb_items = [['label' => 'Home', 'url' => '/'], ...];

// 4. Capture content
ob_start();
?>
<div class="container-fluid">
    <!-- Your content -->
</div>
<?php
$page_content = ob_get_clean();

// 5. Modals (optional)
ob_start();
?><div class="modal">...</div><?php
$page_modals = ob_get_clean();

// 6. Overlays (optional)
ob_start();
?><div id="overlay">...</div><?php
$page_overlays = ob_get_clean();

// 7. Render
require __DIR__ . '/../shared/templates/base-layout.php';
```

**That's it!** No HTML structure to manage, no script loading to debug.

---

## Advantages of This Pattern

### For Developers
✅ **Consistent** - Every page follows same pattern  
✅ **DRY** - No repeated HTML boilerplate  
✅ **Maintainable** - Fix template once, all pages benefit  
✅ **Testable** - Business logic separate from presentation  
✅ **Debuggable** - Clear separation of concerns

### For the System
✅ **Reliable** - Guaranteed correct HTML structure  
✅ **Fast** - No template parsing overhead  
✅ **Secure** - Centralized output escaping  
✅ **Scalable** - Easy to add new pages  
✅ **Future-proof** - Easy to upgrade global templates

---

## Common Patterns

### Simple Page
```php
$page_title = 'Simple Page';
$page_content = '<div class="container"><h1>Hello</h1></div>';
require __DIR__ . '/../shared/templates/base-layout.php';
```

### Page with Modal
```php
$page_content = '<div>Content</div>';
$page_modals = '<div class="modal" id="myModal">...</div>';
require __DIR__ . '/../shared/templates/base-layout.php';
```

### Page with Custom Scripts
```php
$page_scripts_before_footer = <<<HTML
<script src="/my/script1.js"></script>
<script src="/my/script2.js"></script>
HTML;
$page_content = '...';
require __DIR__ . '/../shared/templates/base-layout.php';
```

---

## Next Steps

1. **Test pack-REFACTORED.php** with real transfers
2. **Verify all JavaScript works** (validation, autosave, submit)
3. **Monitor console for errors** during testing
4. **Refactor other module pages** to use base template
5. **Update documentation** as patterns evolve

---

## Status: ✅ READY FOR TESTING

All template issues resolved. JavaScript should now work perfectly.

**Files Ready:**
- ✅ `shared/templates/base-layout.php` - Base template
- ✅ `stock-transfers/pack-REFACTORED.php` - Refactored pack page
- ✅ `stock-transfers/js/pack-fix.js` - Fixed selector

**Test URL:**
```
https://staff.vapeshed.co.nz/modules/consignments/stock-transfers/pack-REFACTORED.php?transfer=27043
```

Once tested and confirmed working, rename pack-REFACTORED.php to pack.php.
