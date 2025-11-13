# VAPEULTRA THEME - PRODUCTION READINESS PLAN

**Project:** VapeUltra Theme Framework
**Version:** 2.0 (Production Grade)
**Date:** November 12, 2025
**Status:** Planning Phase

---

## 🎯 EXECUTIVE SUMMARY

Transform the VapeUltra theme from a functional prototype into an **enterprise-grade, production-ready front-end framework** that serves as the **single source of truth** for all application modules. This framework will provide:

- **Consistency:** Unified design system with zero deviations
- **Reliability:** Battle-tested components with comprehensive error handling
- **Scalability:** Modular architecture that supports unlimited growth
- **Maintainability:** Single inheritance point for effortless updates
- **Performance:** Optimized asset pipeline with <100ms load times
- **Accessibility:** WCAG 2.1 AA compliant across all components

---

## 📋 CURRENT STATE ASSESSMENT

### ✅ What We Have (Good Foundation)

1. **Base Theme Structure**
   - 13 CSS files (variables, base, layout, components, utilities, animations, themes)
   - 6 JavaScript files (core, components, utils, api, notifications, charts)
   - 11 PHP files (layouts, components, views)
   - Renderer engine for template composition
   - Configuration management

2. **Award-Winning Components**
   - Premium dashboard header with glass morphism
   - Enhanced store cards with 3D effects
   - Silver-chrome theme with professional aesthetic
   - Advanced sidebar navigation with multi-level support

3. **Alternative Themes**
   - Netflix Dark theme (entertainment aesthetic)
   - Oceanic Gradient theme (tech-forward)
   - Theme preview system

4. **Documentation**
   - README with quick start guide
   - Integration guide with examples
   - Jack collaboration history
   - File manifest with complete inventory

### ❌ What We're Missing (Critical Gaps)

1. **Design System Standardization**
   - No formal color palette specification
   - Inconsistent typography scale
   - No spacing system documentation
   - Missing component design patterns
   - No style guide for developers

2. **JavaScript Capabilities**
   - No global AJAX error handler
   - Basic modal system (needs enterprise features)
   - Limited toast notification system
   - No form validation framework
   - No loading state manager
   - No global error logger

3. **CSS Framework Completeness**
   - Missing breadcrumb component
   - No sub-navigation system
   - Limited form styling
   - No print stylesheets
   - Accessibility gaps (focus states, ARIA)

4. **Architecture**
   - Multiple layout files instead of single master template
   - No clear module inheritance pattern
   - Missing standardized page layouts
   - No content block system

5. **Production Readiness**
   - No asset pipeline (minification, concatenation)
   - No versioning system
   - No CDN deployment process
   - No testing suite
   - No performance optimization

---

## 🎨 DESIGN SYSTEM (Regulation Framework)

### Color Palette Specification

**Primary Colors**
```css
--color-primary-900: #312e81;    /* Darkest indigo */
--color-primary-700: #4338ca;    /* Dark indigo */
--color-primary-600: #4f46e5;    /* Indigo */
--color-primary-500: #6366f1;    /* Base indigo (DEFAULT) */
--color-primary-400: #818cf8;    /* Light indigo */
--color-primary-300: #a5b4fc;    /* Lighter indigo */
--color-primary-100: #e0e7ff;    /* Lightest indigo */
```

**Secondary Colors**
```css
--color-secondary-900: #581c87;  /* Darkest purple */
--color-secondary-700: #7e22ce;  /* Dark purple */
--color-secondary-600: #9333ea;  /* Purple */
--color-secondary-500: #a855f7;  /* Base purple (DEFAULT) */
--color-secondary-400: #c084fc;  /* Light purple */
--color-secondary-300: #d8b4fe;  /* Lighter purple */
--color-secondary-100: #f3e8ff;  /* Lightest purple */
```

**Semantic Colors**
```css
/* Success (Green) */
--color-success-900: #14532d;
--color-success-500: #22c55e;    /* Base success */
--color-success-100: #dcfce7;

/* Danger (Red) */
--color-danger-900: #7f1d1d;
--color-danger-500: #ef4444;     /* Base danger */
--color-danger-100: #fee2e2;

/* Warning (Amber) */
--color-warning-900: #78350f;
--color-warning-500: #f59e0b;    /* Base warning */
--color-warning-100: #fef3c7;

/* Info (Blue) */
--color-info-900: #1e3a8a;
--color-info-500: #3b82f6;       /* Base info */
--color-info-100: #dbeafe;
```

**Neutral Colors (Grayscale)**
```css
--color-gray-900: #111827;       /* Almost black */
--color-gray-800: #1f2937;       /* Dark gray */
--color-gray-700: #374151;       /* Darker gray */
--color-gray-600: #4b5563;       /* Medium gray */
--color-gray-500: #6b7280;       /* Base gray */
--color-gray-400: #9ca3af;       /* Light gray */
--color-gray-300: #d1d5db;       /* Lighter gray */
--color-gray-200: #e5e7eb;       /* Very light gray */
--color-gray-100: #f3f4f6;       /* Almost white */
--color-gray-50: #f9fafb;        /* Lightest gray */
--color-white: #ffffff;          /* Pure white */
```

**Surface Colors**
```css
--color-surface-dark: #1f2937;   /* Dark mode surface */
--color-surface-light: #ffffff;  /* Light mode surface */
--color-surface-elevated: #f9fafb; /* Elevated cards */
--color-surface-overlay: rgba(0,0,0,0.5); /* Modal backdrop */
```

### Typography Scale

**Font Families**
```css
--font-family-sans: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
--font-family-serif: 'Georgia', 'Times New Roman', serif;
--font-family-mono: 'Monaco', 'Courier New', monospace;
```

**Font Sizes (with line heights)**
```css
--font-size-xs: 0.75rem;     /* 12px */ --line-height-xs: 1rem;      /* 16px */
--font-size-sm: 0.875rem;    /* 14px */ --line-height-sm: 1.25rem;   /* 20px */
--font-size-base: 1rem;      /* 16px */ --line-height-base: 1.5rem;  /* 24px */
--font-size-lg: 1.125rem;    /* 18px */ --line-height-lg: 1.75rem;   /* 28px */
--font-size-xl: 1.25rem;     /* 20px */ --line-height-xl: 1.75rem;   /* 28px */
--font-size-2xl: 1.5rem;     /* 24px */ --line-height-2xl: 2rem;     /* 32px */
--font-size-3xl: 1.875rem;   /* 30px */ --line-height-3xl: 2.25rem;  /* 36px */
--font-size-4xl: 2.25rem;    /* 36px */ --line-height-4xl: 2.5rem;   /* 40px */
--font-size-5xl: 3rem;       /* 48px */ --line-height-5xl: 1;
```

**Font Weights**
```css
--font-weight-light: 300;
--font-weight-normal: 400;
--font-weight-medium: 500;
--font-weight-semibold: 600;
--font-weight-bold: 700;
--font-weight-extrabold: 800;
```

### Spacing System (8px Base Grid)

```css
--spacing-0: 0;
--spacing-0-5: 0.125rem;   /* 2px */
--spacing-1: 0.25rem;      /* 4px */
--spacing-1-5: 0.375rem;   /* 6px */
--spacing-2: 0.5rem;       /* 8px */
--spacing-2-5: 0.625rem;   /* 10px */
--spacing-3: 0.75rem;      /* 12px */
--spacing-4: 1rem;         /* 16px */
--spacing-5: 1.25rem;      /* 20px */
--spacing-6: 1.5rem;       /* 24px */
--spacing-8: 2rem;         /* 32px */
--spacing-10: 2.5rem;      /* 40px */
--spacing-12: 3rem;        /* 48px */
--spacing-16: 4rem;        /* 64px */
--spacing-20: 5rem;        /* 80px */
--spacing-24: 6rem;        /* 96px */
```

### Border Radius

```css
--radius-none: 0;
--radius-sm: 0.125rem;     /* 2px */
--radius-base: 0.25rem;    /* 4px */
--radius-md: 0.375rem;     /* 6px */
--radius-lg: 0.5rem;       /* 8px */
--radius-xl: 0.75rem;      /* 12px */
--radius-2xl: 1rem;        /* 16px */
--radius-3xl: 1.5rem;      /* 24px */
--radius-full: 9999px;     /* Pill shape */
```

### Shadow System

```css
--shadow-xs: 0 1px 2px 0 rgba(0,0,0,0.05);
--shadow-sm: 0 1px 3px 0 rgba(0,0,0,0.1), 0 1px 2px 0 rgba(0,0,0,0.06);
--shadow-base: 0 4px 6px -1px rgba(0,0,0,0.1), 0 2px 4px -1px rgba(0,0,0,0.06);
--shadow-md: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05);
--shadow-lg: 0 20px 25px -5px rgba(0,0,0,0.1), 0 10px 10px -5px rgba(0,0,0,0.04);
--shadow-xl: 0 25px 50px -12px rgba(0,0,0,0.25);
--shadow-2xl: 0 25px 50px -12px rgba(0,0,0,0.5);
--shadow-inner: inset 0 2px 4px 0 rgba(0,0,0,0.06);
```

### Z-Index Layers

```css
--z-index-dropdown: 1000;
--z-index-sticky: 1020;
--z-index-fixed: 1030;
--z-index-modal-backdrop: 1040;
--z-index-modal: 1050;
--z-index-popover: 1060;
--z-index-tooltip: 1070;
--z-index-notification: 1080;
```

### Transition/Animation Standards

```css
--transition-base: all 0.2s ease;
--transition-fast: all 0.1s ease;
--transition-slow: all 0.3s ease;
--transition-bounce: all 0.3s cubic-bezier(0.68, -0.55, 0.265, 1.55);

--duration-instant: 0.1s;
--duration-fast: 0.2s;
--duration-base: 0.3s;
--duration-slow: 0.5s;
--duration-slower: 0.75s;
```

### Breakpoints (Mobile-First)

```css
/* Min-width breakpoints */
--breakpoint-sm: 640px;    /* Small devices (landscape phones) */
--breakpoint-md: 768px;    /* Medium devices (tablets) */
--breakpoint-lg: 1024px;   /* Large devices (desktops) */
--breakpoint-xl: 1280px;   /* Extra large devices */
--breakpoint-2xl: 1536px;  /* 2X large devices */
```

---

## 🏗️ ARCHITECTURE REDESIGN

### Master Template System

**Current Problem:**
- Multiple layout files (base.php, main.php, minimal.php)
- No single inheritance point
- Modules recreate common structure
- Inconsistent across pages

**Solution: Single Master Template**

```
layouts/
  └── master.php           ← THE ONLY base template
      ├── blocks/
      │   ├── head.php     ← <head> section
      │   ├── header.php   ← Top navigation
      │   ├── sidebar.php  ← Left navigation
      │   ├── breadcrumb.php ← Breadcrumb trail
      │   ├── subnav.php   ← Module sub-menu
      │   ├── content.php  ← Main content area
      │   ├── sidebar-right.php ← Right panel
      │   ├── footer.php   ← Footer
      │   └── modals.php   ← Modal container
      └── variants/
          ├── full.php     ← All blocks enabled
          ├── minimal.php  ← Only header + content
          ├── print.php    ← Print-optimized
          └── error.php    ← Error pages
```

**Master Template Structure:**
```php
<!DOCTYPE html>
<html lang="en">
<?php include 'blocks/head.php'; ?>
<body class="<?= $bodyClass ?? '' ?>">

  <!-- Layout Wrapper -->
  <div class="app-wrapper layout-<?= $layout ?? 'full' ?>">

    <!-- Header Block -->
    <?php if ($showHeader ?? true): ?>
      <?php include 'blocks/header.php'; ?>
    <?php endif; ?>

    <!-- Sidebar Block -->
    <?php if ($showSidebar ?? true): ?>
      <?php include 'blocks/sidebar.php'; ?>
    <?php endif; ?>

    <!-- Main Content Area -->
    <main class="app-main">

      <!-- Breadcrumb Block -->
      <?php if ($showBreadcrumb ?? true): ?>
        <?php include 'blocks/breadcrumb.php'; ?>
      <?php endif; ?>

      <!-- Sub-Navigation Block -->
      <?php if ($showSubnav ?? false): ?>
        <?php include 'blocks/subnav.php'; ?>
      <?php endif; ?>

      <!-- Page Content Block -->
      <div class="app-content">
        <?= $content ?>
      </div>

    </main>

    <!-- Right Sidebar Block -->
    <?php if ($showSidebarRight ?? false): ?>
      <?php include 'blocks/sidebar-right.php'; ?>
    <?php endif; ?>

    <!-- Footer Block -->
    <?php if ($showFooter ?? true): ?>
      <?php include 'blocks/footer.php'; ?>
    <?php endif; ?>

  </div>

  <!-- Modal Container -->
  <?php include 'blocks/modals.php'; ?>

  <!-- Scripts -->
  <?php include 'blocks/scripts.php'; ?>

</body>
</html>
```

**Module Usage Example:**
```php
// modules/sales/pages/dashboard.php

$renderer->render('master', [
    'title' => 'Sales Dashboard',
    'layout' => 'full',
    'showBreadcrumb' => true,
    'showSubnav' => true,
    'breadcrumb' => [
        ['label' => 'Home', 'url' => '/'],
        ['label' => 'Sales', 'url' => '/sales'],
        ['label' => 'Dashboard', 'url' => null]
    ],
    'subnav' => [
        ['label' => 'Dashboard', 'url' => '/sales', 'active' => true],
        ['label' => 'Reports', 'url' => '/sales/reports'],
        ['label' => 'Invoices', 'url' => '/sales/invoices']
    ],
    'content' => $dashboardContent
]);
```

### Content Block System

**Block Types:**
1. **Required Blocks** (always present)
   - `head` - HTML head section
   - `content` - Main page content
   - `scripts` - JavaScript files

2. **Optional Blocks** (conditional)
   - `header` - Top navigation bar
   - `sidebar` - Left navigation menu
   - `breadcrumb` - Navigation trail
   - `subnav` - Module-specific menu
   - `sidebar-right` - Right activity panel
   - `footer` - Page footer
   - `modals` - Modal dialogs container

3. **Special Blocks** (page-specific)
   - `toolbar` - Page action buttons
   - `filters` - Search/filter controls
   - `pagination` - List pagination
   - `fab` - Floating action button

---

## 🔧 JAVASCRIPT LIBRARY SPECIFICATION

### Core Modules (Required)

#### 1. Global Error Handler (`js/global-error-handler.js`)

**Purpose:** Catch and handle all JavaScript errors, AJAX failures, and promise rejections globally.

**Features:**
- Window.onerror listener
- Promise rejection handler
- Console error capture
- Error logging to backend
- User-friendly error display
- Developer debug mode
- Error grouping/deduplication
- Retry mechanisms

**API:**
```javascript
ErrorHandler.init({
    debug: false,
    logToServer: true,
    showToUser: true,
    endpoint: '/api/log-error'
});

ErrorHandler.catch(error, context);
ErrorHandler.report(error);
ErrorHandler.notify(error, type); // Show to user
```

#### 2. AJAX Client (`js/ajax-client.js`)

**Purpose:** Centralized AJAX communication with interceptors and error handling.

**Features:**
- Request/response interceptors
- Global error handling (401, 403, 404, 422, 500)
- Automatic token injection
- Retry logic with exponential backoff
- Request cancellation
- Request queuing
- Progress tracking
- CSRF protection

**API:**
```javascript
Ajax.init({
    baseUrl: '/api/v1',
    timeout: 30000,
    retries: 3,
    headers: { 'X-Requested-With': 'XMLHttpRequest' }
});

// HTTP Methods
Ajax.get(url, params, options);
Ajax.post(url, data, options);
Ajax.put(url, data, options);
Ajax.delete(url, options);
Ajax.patch(url, data, options);

// Interceptors
Ajax.interceptor.request.use((config) => {
    config.headers.Authorization = `Bearer ${getToken()}`;
    return config;
});

Ajax.interceptor.response.use((response) => response, (error) => {
    if (error.status === 401) {
        window.location.href = '/login';
    }
    return Promise.reject(error);
});

// File Upload
Ajax.upload(url, file, {
    onProgress: (percent) => console.log(percent)
});
```

#### 3. Modal System (`js/modal-system.js`)

**Purpose:** Enterprise-grade modal dialog system.

**Features:**
- Confirm dialogs (customizable buttons)
- Alert dialogs (OK button)
- Prompt dialogs (text input)
- Custom content modals
- Size variants (sm, md, lg, xl, fullscreen)
- Nested modal support
- Keyboard navigation (ESC, TAB trap)
- Backdrop options (static, dismiss)
- Promise-based API
- Animation options
- Accessibility (ARIA, focus management)

**API:**
```javascript
// Confirm dialog
const confirmed = await Modal.confirm({
    title: 'Delete Item',
    message: 'Are you sure you want to delete this item?',
    confirmText: 'Delete',
    confirmClass: 'btn-danger',
    cancelText: 'Cancel',
    icon: 'trash'
});

if (confirmed) {
    // Delete item
}

// Alert dialog
await Modal.alert({
    title: 'Success',
    message: 'Item saved successfully',
    type: 'success'
});

// Prompt dialog
const name = await Modal.prompt({
    title: 'Enter Name',
    message: 'Please enter your name:',
    defaultValue: '',
    placeholder: 'John Doe',
    required: true
});

// Custom modal
Modal.open({
    id: 'custom-modal',
    title: 'Custom Modal',
    content: '<p>Custom HTML content</p>',
    size: 'lg',
    backdrop: 'static',
    buttons: [
        { label: 'Save', class: 'btn-primary', callback: () => save() },
        { label: 'Cancel', class: 'btn-secondary', callback: () => Modal.close('custom-modal') }
    ]
});

// Close modal
Modal.close('custom-modal');
```

#### 4. Toast Notification System (`js/toast-system.js`)

**Purpose:** Non-blocking notification system.

**Features:**
- Types (success, error, warning, info, custom)
- Positions (8 positions)
- Auto-dismiss with countdown
- Manual dismiss
- Progress bar
- Action buttons
- Stacking behavior
- Queue management
- Sound effects (optional)
- Persistence option

**API:**
```javascript
// Success toast
Toast.success('Item saved successfully', {
    duration: 3000,
    position: 'top-right'
});

// Error toast
Toast.error('Failed to save item', {
    duration: 5000,
    action: {
        label: 'Retry',
        callback: () => retry()
    }
});

// Warning toast
Toast.warning('Low stock alert', {
    duration: 0, // Never auto-dismiss
    closable: true
});

// Info toast
Toast.info('New message received');

// Custom toast
Toast.show({
    type: 'custom',
    title: 'Custom Toast',
    message: 'Custom message',
    icon: 'bi bi-star',
    duration: 4000,
    position: 'bottom-center',
    progress: true,
    sound: true
});

// Clear all toasts
Toast.clear();
```

#### 5. Form Validation (`js/form-validator.js`)

**Purpose:** Client-side form validation framework.

**Features:**
- Built-in validators (required, email, phone, URL, min/max, pattern)
- Custom validators
- Real-time validation (on blur, on input)
- Async validation (unique username, etc.)
- Error message display (inline, summary)
- Form state tracking (pristine, dirty, valid, invalid)
- Submit button disable until valid
- Integration with backend validation errors
- Multi-step form support

**API:**
```javascript
// Initialize validator
const validator = new FormValidator('#my-form', {
    rules: {
        email: {
            required: true,
            email: true,
            async: async (value) => {
                const response = await Ajax.get('/check-email', { email: value });
                return response.available ? true : 'Email already exists';
            }
        },
        password: {
            required: true,
            minLength: 8,
            pattern: /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)/,
            message: 'Password must contain uppercase, lowercase, and number'
        },
        confirm_password: {
            required: true,
            matches: 'password',
            message: 'Passwords must match'
        }
    },
    validateOn: 'blur',
    showErrors: 'inline'
});

// Validate form
const isValid = await validator.validate();

// Get errors
const errors = validator.getErrors();

// Clear errors
validator.clearErrors();

// Add backend errors
validator.setErrors({
    email: ['Email is already taken'],
    password: ['Password is too weak']
});
```

#### 6. Loading State Manager (`js/loading-manager.js`)

**Purpose:** Centralized loading state management.

**Features:**
- Global page loader (overlay with spinner)
- Button loading states
- Skeleton loaders
- Progress bars
- Lazy loading
- Infinite scroll
- Loading placeholders
- Timeout handling

**API:**
```javascript
// Show page loader
Loading.show('Loading data...');

// Hide page loader
Loading.hide();

// Button loading
const btn = document.querySelector('#save-btn');
Loading.button(btn, true); // Show spinner in button
await save();
Loading.button(btn, false); // Hide spinner

// Progress bar
Loading.progress(50); // 0-100

// Skeleton loader
Loading.skeleton('#content', {
    rows: 5,
    height: 20
});

// Remove skeleton
Loading.removeSkeleton('#content');
```

### Utility Modules (Optional but Recommended)

#### 7. Event Bus (`js/event-bus.js`)
```javascript
EventBus.on('user.login', (user) => console.log(user));
EventBus.emit('user.login', { id: 1, name: 'John' });
EventBus.off('user.login');
```

#### 8. Storage Utilities (`js/storage.js`)
```javascript
Storage.set('key', { data: 'value' }); // Auto JSON.stringify
const data = Storage.get('key'); // Auto JSON.parse
Storage.remove('key');
Storage.clear();
```

#### 9. Debounce/Throttle (`js/performance.js`)
```javascript
const debouncedSearch = Perf.debounce((query) => search(query), 300);
const throttledScroll = Perf.throttle(() => handleScroll(), 100);
```

---

## 🎨 CSS FRAMEWORK SPECIFICATION

### Component Library

#### Buttons
```css
.btn { /* Base styles */ }
.btn-primary { /* Primary button */ }
.btn-secondary { /* Secondary button */ }
.btn-success { /* Success button */ }
.btn-danger { /* Danger button */ }
.btn-sm { /* Small button */ }
.btn-lg { /* Large button */ }
.btn-block { /* Full width */ }
.btn-loading { /* Loading state */ }
```

#### Forms
```css
.form-group { /* Form field group */ }
.form-label { /* Label */ }
.form-control { /* Input, select, textarea */ }
.form-control-sm { /* Small input */ }
.form-control-lg { /* Large input */ }
.form-error { /* Error message */ }
.form-help { /* Help text */ }
.form-inline { /* Inline form */ }
```

#### Cards
```css
.card { /* Base card */ }
.card-header { /* Card header */ }
.card-body { /* Card body */ }
.card-footer { /* Card footer */ }
.card-elevated { /* Elevated card */ }
.card-outlined { /* Outlined card */ }
```

#### Breadcrumbs
```css
.breadcrumb { /* Breadcrumb container */ }
.breadcrumb-item { /* Breadcrumb item */ }
.breadcrumb-item.active { /* Current page */ }
.breadcrumb-separator { /* Separator icon */ }
```

#### Sub-Navigation
```css
.subnav { /* Sub-nav container */ }
.subnav-horizontal { /* Horizontal tabs */ }
.subnav-vertical { /* Vertical sidebar */ }
.subnav-item { /* Nav item */ }
.subnav-item.active { /* Active item */ }
```

---

## 📦 DELIVERABLES

### Phase 1: Foundation (Week 1)
1. **DESIGN_SYSTEM.md** - Complete style guide
2. **layouts/master.php** - Single master template
3. **components/breadcrumb.php** - Breadcrumb component
4. **components/subnav.php** - Sub-navigation component
5. **js/global-error-handler.js** - Error handling
6. **js/ajax-client.js** - AJAX client with interceptors

### Phase 2: Components (Week 2)
7. **js/modal-system.js** - Modal library
8. **js/toast-system.js** - Toast notifications
9. **js/form-validator.js** - Form validation
10. **js/loading-manager.js** - Loading states
11. **css/components-enhanced.css** - Complete component library
12. **css/breadcrumb.css** - Breadcrumb styles
13. **css/subnav.css** - Sub-nav styles

### Phase 3: Documentation (Week 3)
14. **COMPONENTS.md** - Component usage guide
15. **MODULE_INTEGRATION.md** - Integration guide
16. **PAGE_LAYOUTS.md** - Layout templates guide
17. **ACCESSIBILITY.md** - A11y compliance guide
18. **TESTING.md** - Testing procedures

### Phase 4: Production (Week 4)
19. **Asset Pipeline** - Build/minify/concatenate scripts
20. **Testing Suite** - Automated tests
21. **Performance Audit** - Lighthouse optimization
22. **Final QA** - Cross-browser, cross-device testing

---

## 🚀 ROLLOUT STRATEGY

### Phase 1: Pilot (1 Module)
- Choose one module (e.g., Sales Dashboard)
- Migrate to new master template
- Test all components
- Gather feedback
- Iterate

### Phase 2: Expansion (3-5 Modules)
- Migrate core modules
- Train developers
- Document common patterns
- Address edge cases

### Phase 3: Full Rollout (All Modules)
- Migrate all remaining modules
- Deprecate old templates
- Archive legacy code
- Celebrate! 🎉

---

## ✅ SUCCESS CRITERIA

- [ ] All pages use layouts/master.php
- [ ] Zero design inconsistencies across modules
- [ ] Lighthouse scores >90 (Performance, Accessibility, Best Practices, SEO)
- [ ] WCAG 2.1 AA compliant
- [ ] Zero JavaScript errors in production
- [ ] <100ms first paint
- [ ] All AJAX errors handled gracefully
- [ ] All components documented
- [ ] All developers trained
- [ ] Full test coverage (>80%)

---

## 📞 NEXT STEPS

1. **Review this plan** - Confirm approach and priorities
2. **Approve design system** - Lock in colors, typography, spacing
3. **Start Phase 1** - Build foundation (master template, error handling, AJAX client)
4. **Iterate** - Test, gather feedback, refine
5. **Scale** - Roll out to all modules

---

**Let's build something amazing! 🚀**
