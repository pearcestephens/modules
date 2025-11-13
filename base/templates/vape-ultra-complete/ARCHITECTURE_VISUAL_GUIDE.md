# 🎨 VapeUltra Architecture Visual Guide

**Understanding the Flow: From Module View to Rendered Page**

---

## 📊 SYSTEM ARCHITECTURE

```
┌─────────────────────────────────────────────────────────────────────┐
│                         CIS 2.0 APPLICATION                          │
└─────────────────────────────────────────────────────────────────────┘
                                    │
                    ┌───────────────┴───────────────┐
                    │                               │
            ┌───────▼────────┐              ┌──────▼───────┐
            │ Module Router  │              │  Bootstrap   │
            │ (index.php)    │              │  (config)    │
            └───────┬────────┘              └──────┬───────┘
                    │                               │
                    └───────────┬───────────────────┘
                                │
                        ┌───────▼────────┐
                        │  View File     │
                        │  (your page)   │
                        └───────┬────────┘
                                │
                ┌───────────────┼───────────────┐
                │               │               │
        ┌───────▼────────┐ ┌───▼────┐  ┌──────▼──────┐
        │ Page Content   │ │Breadcr.│  │  Sub-Nav    │
        │   (HTML)       │ │ (array)│  │  (array)    │
        └───────┬────────┘ └───┬────┘  └──────┬──────┘
                │              │               │
                └──────────────┼───────────────┘
                               │
                    ┌──────────▼──────────┐
                    │  $renderer->render  │
                    │     ('master')      │
                    └──────────┬──────────┘
                               │
                    ┌──────────▼──────────┐
                    │   MASTER.PHP        │
                    │ (layouts/master.php)│
                    └──────────┬──────────┘
                               │
        ┌──────────────────────┼──────────────────────┐
        │                      │                      │
┌───────▼────────┐   ┌─────────▼────────┐  ┌────────▼─────────┐
│  CSS Loading   │   │   HTML Structure │  │  JS Loading      │
│  (variables,   │   │   (header, nav,  │  │  (core, ajax,    │
│   components)  │   │    content, etc.)│  │   modal, toast)  │
└───────┬────────┘   └─────────┬────────┘  └────────┬─────────┘
        │                      │                      │
        └──────────────────────┼──────────────────────┘
                               │
                    ┌──────────▼──────────┐
                    │  RENDERED HTML PAGE │
                    │  (sent to browser)  │
                    └─────────────────────┘
```

---

## 🔄 PAGE RENDERING FLOW

### Step 1: User Request
```
User clicks link
     │
     ▼
Browser sends request
     │
     ▼
Server receives: /modules/sales/?route=dashboard
```

### Step 2: Module Router
```
Module index.php
     │
     ├─ Load bootstrap.php
     ├─ Check authentication
     ├─ Route to view file
     │
     ▼
Load: modules/sales/views/dashboard.php
```

### Step 3: View File Processing
```
dashboard.php
     │
     ├─ ob_start()  ← Start buffering
     ├─ Output HTML content
     ├─ ob_get_clean()  ← Capture content
     │
     ├─ Define $breadcrumb
     ├─ Define $subnav
     │
     ▼
$renderer->render('master', [...])
```

### Step 4: Master Template
```
master.php
     │
     ├─ Load <head> section
     │   ├─ Meta tags
     │   ├─ CSS files (variables.css → base.css → components.css)
     │   └─ External libraries (jQuery, Axios)
     │
     ├─ Build <body> structure
     │   ├─ Header (if showHeader = true)
     │   ├─ Sidebar (if showSidebar = true)
     │   ├─ Breadcrumb (if showBreadcrumb = true)
     │   ├─ Sub-navigation (if showSubnav = true)
     │   ├─ Content area ← YOUR CONTENT HERE
     │   ├─ Footer (if showFooter = true)
     │   └─ Modals container
     │
     ├─ Load JavaScript files
     │   ├─ Core libraries
     │   ├─ VapeUltra components (ajax-client.js, modal-system.js, etc.)
     │   └─ Initialization script
     │
     ▼
Send HTML to browser
```

### Step 5: Browser Rendering
```
Browser receives HTML
     │
     ├─ Parse HTML
     ├─ Load CSS files
     ├─ Load JavaScript files
     │
     ├─ Initialize VapeUltra
     │   ├─ ErrorHandler.init()
     │   ├─ Ajax.init()
     │   ├─ Modal.init()
     │   └─ Toast.init()
     │
     ├─ Run DOMContentLoaded event
     ├─ Execute page-specific JavaScript
     │
     ▼
Page fully rendered and interactive
```

---

## 🏗️ FILE STRUCTURE

```
modules/
├── base/
│   └── templates/
│       └── vape-ultra-complete/
│           ├── layouts/
│           │   └── master.php  ← THE ONLY TEMPLATE
│           │
│           ├── components/
│           │   ├── breadcrumb.php
│           │   └── subnav.php
│           │
│           ├── css/
│           │   ├── variables.css  ← Design system
│           │   ├── base.css
│           │   ├── layout.css
│           │   └── components.css
│           │
│           ├── js/
│           │   ├── global-error-handler.js
│           │   ├── ajax-client.js
│           │   ├── modal-system.js
│           │   └── toast-system.js
│           │
│           └── docs/
│               ├── DESIGN_SYSTEM.md
│               ├── USAGE_EXAMPLES.md
│               ├── QUICK_REFERENCE.md
│               └── MASTER_INTEGRATION_GUIDE.md
│
└── sales/  ← Example module
    ├── bootstrap.php
    ├── index.php  ← Router
    └── views/
        ├── dashboard.php  ← Your page
        ├── invoices.php
        └── customers.php
```

---

## 🎯 DATA FLOW

### From View to Browser

```
┌──────────────────────────────────────────────────────────────┐
│ VIEW FILE (dashboard.php)                                     │
├──────────────────────────────────────────────────────────────┤
│ ob_start();                                                   │
│ ?>                                                            │
│ <div class="container">                                       │
│   <h1>Dashboard</h1>  ← Your HTML content                    │
│ </div>                                                        │
│ <?php                                                         │
│ $pageContent = ob_get_clean();                               │
│                                                               │
│ $breadcrumb = [...];  ← Navigation data                      │
│ $subnav = [...];                                             │
│                                                               │
│ $renderer->render('master', [                                │
│   'title' => 'Dashboard',  ← Page metadata                   │
│   'content' => $pageContent,  ← Your content                 │
│   'breadcrumb' => $breadcrumb,  ← Navigation                 │
│   'subnav' => $subnav                                        │
│ ]);                                                           │
└──────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌──────────────────────────────────────────────────────────────┐
│ MASTER TEMPLATE (master.php)                                 │
├──────────────────────────────────────────────────────────────┤
│ <!DOCTYPE html>                                               │
│ <html>                                                        │
│   <head>                                                      │
│     <title><?= $title ?></title>  ← From view                │
│     <link rel="stylesheet" href="variables.css">             │
│     <link rel="stylesheet" href="base.css">                  │
│   </head>                                                     │
│   <body>                                                      │
│     <header>...</header>                                      │
│     <nav>...</nav>                                           │
│     <?php include 'components/breadcrumb.php'; ?>  ← Uses    │
│     <?php include 'components/subnav.php'; ?>      │ $breadcr│
│     <main>                                         │ and      │
│       <?= $content ?>  ← Your content inserted     │ $subnav  │
│     </main>                                                   │
│     <footer>...</footer>                                      │
│     <script src="jquery.min.js"></script>                    │
│     <script src="ajax-client.js"></script>                   │
│     <script src="modal-system.js"></script>                  │
│   </body>                                                     │
│ </html>                                                       │
└──────────────────────────────────────────────────────────────┘
                                │
                                ▼
┌──────────────────────────────────────────────────────────────┐
│ BROWSER                                                       │
├──────────────────────────────────────────────────────────────┤
│ ┌──────────────────────────────────────────────────────────┐ │
│ │ Header                                                    │ │
│ ├──────────────────────────────────────────────────────────┤ │
│ │ Sidebar │ Breadcrumb: Home > Sales > Dashboard          │ │
│ │         ├──────────────────────────────────────────────┤ │
│ │         │ Sub-Nav: [Dashboard] Invoices Customers      │ │
│ │         ├──────────────────────────────────────────────┤ │
│ │         │                                               │ │
│ │         │ <h1>Dashboard</h1>  ← Your content           │ │
│ │         │ (rendered here)                               │ │
│ │         │                                               │ │
│ │         ├──────────────────────────────────────────────┤ │
│ │ Footer                                                   │ │
│ └──────────────────────────────────────────────────────────┘ │
└──────────────────────────────────────────────────────────────┘
```

---

## 🎨 COMPONENT RENDERING

### Breadcrumb Component

```
$breadcrumb array → breadcrumb.php → Rendered HTML
     │                   │
     │                   ├─ Loop through items
     │                   ├─ Add icons
     │                   ├─ Add separators
     │                   ├─ Highlight active
     │                   └─ Apply responsive styles
     │
     └─> [Home] > [Sales] > Dashboard
```

### Sub-Navigation Component

```
$subnav array → subnav.php → Rendered HTML
     │              │
     │              ├─ Check style (horizontal/vertical)
     │              ├─ Loop through items
     │              ├─ Add icons
     │              ├─ Add badges
     │              ├─ Highlight active
     │              └─ Add mobile toggle
     │
     └─> [Dashboard] [Invoices (12)] [Customers] [Reports]
```

---

## 🔄 JAVASCRIPT INITIALIZATION FLOW

```
Page Load
    │
    ▼
DOMContentLoaded event fires
    │
    ├─ VapeUltra.ErrorHandler.init()
    │   └─ Attach global error listeners
    │
    ├─ VapeUltra.Ajax.init()
    │   └─ Setup Axios interceptors
    │
    ├─ VapeUltra.Modal.init()
    │   └─ Attach keyboard listeners
    │
    ├─ VapeUltra.Toast.init()
    │   └─ Ready for notifications
    │
    └─ Page-specific initialization
        └─ Your custom JavaScript runs
```

---

## 🌐 AJAX REQUEST FLOW

```
User Action (click button, submit form)
    │
    ▼
VapeUltra.Ajax.post('/api/endpoint', data)
    │
    ├─ REQUEST INTERCEPTOR
    │   ├─ Add CSRF token
    │   ├─ Add timestamp
    │   ├─ Check for duplicates
    │   ├─ Show loading indicator
    │   └─ Log request
    │
    ▼
Server processes request
    │
    ├─ Success → Response
    ├─ Error → Error response
    │
    ▼
VapeUltra.Ajax receives response
    │
    ├─ RESPONSE INTERCEPTOR
    │   ├─ Calculate duration
    │   ├─ Log response
    │   ├─ Hide loading indicator
    │   └─ Unwrap data
    │
    ├─ On Success
    │   ├─ Return data to .then()
    │   └─ Show success toast
    │
    └─ On Error
        ├─ Check status code
        ├─ Handle specific errors (401, 403, 422, 5xx)
        ├─ Retry if needed (exponential backoff)
        ├─ Log error
        └─ Show error message
```

---

## 🎨 CSS LOADING ORDER

```
1. variables.css  ← Design system (colors, spacing, typography)
    │
    ├─ --vape-primary-500: #6366f1
    ├─ --vape-secondary-500: #a855f7
    ├─ --spacing-4: 16px
    └─ --font-size-base: 16px
    │
    ▼
2. base.css  ← Base styles (reset, typography, forms)
    │
    ├─ body { font-family: var(--font-primary); }
    ├─ h1 { font-size: var(--font-size-3xl); }
    └─ .btn { padding: var(--spacing-2); }
    │
    ▼
3. layout.css  ← Layout structure (header, sidebar, footer)
    │
    ├─ .sidebar { width: 250px; }
    ├─ .main-content { margin-left: 250px; }
    └─ .header { height: 60px; }
    │
    ▼
4. components.css  ← Component styles (cards, buttons, etc.)
    │
    ├─ .card { border-radius: var(--radius-lg); }
    ├─ .btn-primary { background: var(--vape-primary-500); }
    └─ .badge { padding: var(--spacing-1); }
    │
    ▼
5. Page-specific CSS (if any)
```

---

## 🛠️ DEBUGGING VISUAL MAP

```
Problem: Page not loading
    │
    ├─ Check: Browser Console
    │   └─ JavaScript errors?
    │
    ├─ Check: Network Tab
    │   ├─ 404 on CSS/JS files?
    │   └─ AJAX calls failing?
    │
    ├─ Check: PHP Error Log
    │   ├─ Fatal errors?
    │   └─ Warnings?
    │
    └─ Check: File Paths
        ├─ master.php exists?
        ├─ bootstrap.php loaded?
        └─ $renderer available?

Problem: Styles not applying
    │
    ├─ Check: Browser Console
    │   └─ CSS file 404s?
    │
    ├─ Check: Network Tab
    │   └─ CSS files loading?
    │
    ├─ Check: Computed Styles
    │   ├─ CSS variables set?
    │   └─ Classes applied?
    │
    └─ Clear: Browser Cache
        └─ Ctrl+Shift+R

Problem: AJAX not working
    │
    ├─ Check: Browser Console
    │   └─ VapeUltra.Ajax defined?
    │
    ├─ Check: Network Tab
    │   ├─ Request sent?
    │   ├─ Response received?
    │   └─ Status code?
    │
    └─ Check: Error Handler
        └─ Errors logged?
```

---

## 📝 SUMMARY

**Key Takeaways:**

1. **Single Template:** `master.php` is the only template file
2. **Content Separation:** View files contain only content
3. **Data Passing:** Arrays pass navigation and metadata
4. **Component Rendering:** Components receive data and render HTML
5. **JavaScript Initialization:** Happens automatically on page load
6. **Design System:** CSS variables provide consistency
7. **Error Handling:** Automatic and graceful
8. **AJAX Client:** Centralized with interceptors

**The Flow is Simple:**
```
View File → master.php → Browser → User Sees Beautiful Page ✨
```

---

_Last Updated: 2025-11-12_
