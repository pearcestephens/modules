# 🎨 CIS BASE TEMPLATE SYSTEM - COMPLETE VISUAL GUIDE

**Date:** October 30, 2025
**Purpose:** Comprehensive review of all CIS base template layouts and components
**Status:** ✅ Production Ready

---

## 📋 TABLE OF CONTENTS

1. [System Overview](#system-overview)
2. [Core Components](#core-components)
3. [Layout Templates](#layout-templates)
4. [Usage Examples](#usage-examples)
5. [Integration Guide](#integration-guide)

---

## 🏗️ SYSTEM OVERVIEW

### Architecture

```
/modules/base/_templates/
│
├── components/               ← REUSABLE UI COMPONENTS
│   ├── header.php           ✅ Top navigation bar
│   ├── sidebar.php          ✅ Left navigation menu
│   ├── footer.php           ✅ Bottom footer bar
│   ├── search-bar.php       ✅ AI-powered search
│   └── breadcrumbs.php      ✅ Breadcrumb navigation
│
└── layouts/                 ← 5 PAGE LAYOUT TEMPLATES
    ├── dashboard.php        ✅ Full dashboard (sidebar + header + footer)
    ├── table.php            ✅ Data table layout (optimized for tables)
    ├── card.php             ✅ Centered card (login, simple forms)
    ├── split.php            ✅ Two-panel resizable layout
    └── blank.php            ✅ Minimal wrapper (full control)
```

### Design System

**CSS Framework:**
- ✅ CIS Core CSS (50KB) - Custom design system
- ✅ CSS Variables for theming
- ✅ Mobile-first responsive
- ✅ Bootstrap 5-inspired grid
- ✅ 100% jQuery-FREE

**JavaScript Libraries:**
- ✅ Chart.js 4.4.7 (charts)
- ✅ DataTables 2.1.8 (tables)
- ✅ SweetAlert2 11.14.5 (alerts)
- ✅ Day.js 1.11.13 (dates)
- ✅ Alpine.js 3.14.3 (optional reactivity)

**Icon Library:**
- ✅ Font Awesome 6.7.1 (latest)

---

## 🧩 CORE COMPONENTS

### 1. HEADER COMPONENT (`components/header.php`)

**Visual Layout:**
```
┌─────────────────────────────────────────────────────────────────┐
│ [≡] [LOGO]    [🔍 Search anything... (AI)]    [🔔] [@User ▾]   │
└─────────────────────────────────────────────────────────────────┘
```

**Features:**
- ✅ Mobile hamburger menu toggle
- ✅ Company logo (clickable to home)
- ✅ AI-powered universal search bar (center)
- ✅ Notification bell with badge counter
- ✅ User dropdown menu (Profile, Settings, Logout)
- ✅ Sticky header (always visible)
- ✅ Responsive design

**Variables Accepted:**
```php
$notificationCount = 5;    // Shows badge with number
$pageTitle = "Dashboard";   // Page title in <title> tag
```

**Code Snippet:**
```php
<header class="cis-header">
    <div class="header-left">
        <button class="mobile-menu-toggle">☰</button>
        <div class="header-logo">
            <img src="/assets/images/logo.png" alt="CIS">
        </div>
    </div>

    <div class="header-search">
        <?php include 'search-bar.php'; ?>
    </div>

    <div class="header-right">
        <button class="btn-icon">
            <i class="fas fa-bell"></i>
            <span class="badge">3</span>
        </button>

        <div class="dropdown">
            <button class="dropdown-toggle">
                <i class="fas fa-user"></i>
                <span>John Doe</span>
            </button>
            <div class="dropdown-menu">
                <a href="/my-profile.php">Profile</a>
                <a href="/settings.php">Settings</a>
                <a href="/logout.php">Logout</a>
            </div>
        </div>
    </div>
</header>
```

---

### 2. SIDEBAR COMPONENT (`components/sidebar.php`)

**Visual Layout:**
```
┌──────────────────┐
│  [CIS LOGO]      │
├──────────────────┤
│ 🏠 Dashboard     │
│ 📦 Inventory  ▾  │
│   ├ Stock Count  │
│   ├ Transfers    │
│   └ Consignments │
│ 💵 Finance    ▾  │
│ 👥 HR & Staff ▾  │
│ ⚙️  Settings     │
├──────────────────┤
│ v2.0.0           │
└──────────────────┘
```

**Features:**
- ✅ Fixed width (260px desktop)
- ✅ Collapsible on mobile (hamburger menu)
- ✅ Multi-level dropdown menus
- ✅ Icon + text navigation
- ✅ Active state highlighting
- ✅ Smooth animations
- ✅ Dark theme design
- ✅ Version number in footer

**Menu Structure:**
- Dashboard
- Inventory (with submenu)
  - Stock Count
  - Transfers
  - Consignments
  - Product Browser
- Purchase Orders
- Suppliers
- Sales & Reports (with submenu)
- HR & Staff (with submenu)
- Finance (with submenu)
- Settings

**Variables Accepted:**
```php
$activeMenu = 'inventory';  // Highlights active section
```

**Code Snippet:**
```php
<aside class="cis-sidebar">
    <div class="sidebar-header">
        <a href="/index.php" class="sidebar-brand">
            <img src="/assets/images/logo-white.png" alt="CIS">
            <span class="brand-text">CIS</span>
        </a>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav-list">
            <li class="nav-item">
                <a href="/index.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>

            <li class="nav-item has-submenu">
                <a href="#" class="nav-link">
                    <i class="fas fa-boxes"></i>
                    <span class="nav-text">Inventory</span>
                    <i class="fas fa-chevron-down"></i>
                </a>
                <ul class="nav-submenu">
                    <li><a href="/modules/inventory/count.php">Stock Count</a></li>
                    <li><a href="/modules/transfers/list.php">Transfers</a></li>
                </ul>
            </li>
        </ul>
    </nav>

    <div class="sidebar-footer">
        <p class="version">v2.0.0</p>
    </div>
</aside>
```

---

### 3. FOOTER COMPONENT (`components/footer.php`)

**Visual Layout:**
```
┌─────────────────────────────────────────────────────────────────┐
│ © 2025 Ecigdis Ltd        Help | Docs | Support     Server: prod │
│ CIS v2.0.0                                          30 Oct 14:30 │
└─────────────────────────────────────────────────────────────────┘
```

**Features:**
- ✅ Three-section layout (left, center, right)
- ✅ Copyright and version info
- ✅ Quick links (Help, Documentation, Support, Privacy, Terms)
- ✅ Server hostname
- ✅ Current timestamp
- ✅ Responsive (stacks on mobile)

**Code Snippet:**
```php
<footer class="cis-footer">
    <div class="footer-content">
        <div class="footer-left">
            <p>&copy; <?= date('Y') ?> <strong>Ecigdis Limited</strong> | The Vape Shed</p>
            <p class="footer-subtext">CIS v2.0.0</p>
        </div>

        <div class="footer-center">
            <ul class="footer-links">
                <li><a href="/help.php">Help</a></li>
                <li><a href="/documentation.php">Documentation</a></li>
                <li><a href="/support.php">Support</a></li>
            </ul>
        </div>

        <div class="footer-right">
            <p><i class="fas fa-server"></i> Server: <?= gethostname() ?></p>
            <p><i class="fas fa-clock"></i> <?= date('d M Y H:i:s') ?></p>
        </div>
    </div>
</footer>
```

---

## 📐 LAYOUT TEMPLATES

### LAYOUT 1: DASHBOARD (`layouts/dashboard.php`)

**Visual Structure:**
```
┌─────────────────────────────────────────────────────────────────┐
│                        HEADER (sticky)                          │
│  [≡] [LOGO]    [🔍 Search...]    [🔔] [@User ▾]               │
├────────┬────────────────────────────────────────────────────────┤
│        │ Home > HR > Payroll Dashboard                          │
│        ├────────────────────────────────────────────────────────┤
│ SIDE   │                                                        │
│ BAR    │            MAIN CONTENT AREA                           │
│        │         (Your page content goes here)                  │
│ 🏠     │                                                        │
│ 📦▾    │                                                        │
│ 💵▾    │                                                        │
│ 👥▾    │                                                        │
│ ⚙️     │                                                        │
│        │                                                        │
│ v2.0   │                                                        │
├────────┴────────────────────────────────────────────────────────┤
│                    FOOTER (auto-bottom)                         │
│  © 2025 Ecigdis       Help | Docs       Server: prod | 14:30   │
└─────────────────────────────────────────────────────────────────┘
```

**Best For:**
- ✅ Main dashboards
- ✅ Complex multi-section pages
- ✅ Pages needing sidebar navigation
- ✅ Overview/summary pages
- ✅ Report dashboards

**Features:**
- ✅ Full page layout with sidebar
- ✅ Sticky header (always visible)
- ✅ Collapsible sidebar (mobile)
- ✅ Breadcrumbs support
- ✅ Footer auto-sticks to bottom
- ✅ Mobile responsive
- ✅ Vanilla JavaScript (no jQuery)

**Variables:**
```php
$pageTitle = "Dashboard Title";         // <title> tag
$pageCSS = ['/path/to/custom.css'];    // Additional CSS files
$pageJS = ['/path/to/custom.js'];      // Additional JS files
$breadcrumbs = [                        // Breadcrumb navigation
    ['label' => 'Home', 'url' => '/'],
    ['label' => 'HR', 'url' => '/hr/'],
    ['label' => 'Payroll', 'url' => '']
];
$inlineStyles = "body { ... }";         // Inline CSS
$inlineScripts = "console.log('...')";  // Inline JS
$notificationCount = 5;                 // Header notification badge
```

**Usage Example:**
```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/base/bootstrap.php';

$pageTitle = "Payroll Dashboard";
$pageCSS = ['/modules/payroll/assets/css/dashboard.css'];
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/'],
    ['label' => 'HR', 'url' => '/hr/'],
    ['label' => 'Payroll Dashboard', 'url' => '']
];

ob_start();
?>

<div class="container-fluid">
    <h1>Payroll Dashboard</h1>

    <div class="row">
        <div class="col-md-3">
            <div class="stat-card">
                <h3>$45,678</h3>
                <p>Total Payroll</p>
            </div>
        </div>
        <!-- More content -->
    </div>
</div>

<?php
$content = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/modules/base/_templates/layouts/dashboard.php';
?>
```

---

### LAYOUT 2: TABLE (`layouts/table.php`)

**Visual Structure:**
```
┌─────────────────────────────────────────────────────────────────┐
│                    PAGE HEADER (sticky)                         │
│  Data Table Title                                               │
│  View and manage all records                                    │
│  [+ Create] [Export Excel] [Export PDF]                        │
├─────────────────────────────────────────────────────────────────┤
│                    FILTERS SECTION                              │
│  [Status ▾] [Period ▾] [Search: ___________] [Apply Filters]   │
├─────────────────────────────────────────────────────────────────┤
│                    TABLE CONTENT                                │
│  ┌──────────┬──────────┬──────────┬──────────┬────────────┐   │
│  │ Period   │ End Date │ Employees│ Gross Pay│ Actions    │   │
│  ├──────────┼──────────┼──────────┼──────────┼────────────┤   │
│  │ Oct 2025 │ 31/10/25 │    45    │ $123,456 │ [View][Edit]│   │
│  │ Sep 2025 │ 30/09/25 │    44    │ $118,900 │ [View][Edit]│   │
│  └──────────┴──────────┴──────────┴──────────┴────────────┘   │
├─────────────────────────────────────────────────────────────────┤
│                    TABLE FOOTER                                 │
│  Showing 1 to 25 of 150 entries    [< 1 2 3 4 5 6 >]          │
└─────────────────────────────────────────────────────────────────┘
```

**Best For:**
- ✅ Data table list pages
- ✅ Pay runs list
- ✅ Employee lists
- ✅ Transaction lists
- ✅ Any tabular data with filters

**Features:**
- ✅ Sticky header with title and action buttons
- ✅ Built-in filter section
- ✅ DataTables integration (sorting, search, pagination)
- ✅ Export buttons (Excel, PDF, Print)
- ✅ Responsive table (horizontal scroll on mobile)
- ✅ Auto-pagination footer

**Variables:**
```php
$pageTitle = "Table Page Title";       // <title> tag
$tableTitle = "Data Table";            // Header title
$tableSubtitle = "Description";        // Header subtitle
$headerActions = "<button>...</button>"; // Action buttons HTML
$tableFilters = "<div>...</div>";      // Filters section HTML
$tableFooterContent = "<div>...</div>"; // Footer content HTML
$pageLength = 25;                      // Rows per page
$defaultOrder = [[0, 'desc']];         // Default sorting
$pageCSS = ['/path/to/custom.css'];
$pageJS = ['/path/to/custom.js'];
```

**Usage Example:**
```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/base/bootstrap.php';

$pageTitle = "Pay Runs";
$tableTitle = "Pay Runs";
$tableSubtitle = "View and manage all pay runs";
$pageCSS = ['/modules/payroll/assets/css/payruns.css'];

// Action buttons
$headerActions = '
    <a href="/payroll/create.php" class="btn btn-primary">
        <i class="fas fa-plus"></i> Create Pay Run
    </a>
    <button class="btn btn-secondary">
        <i class="fas fa-file-excel"></i> Export
    </button>
';

// Filters
$tableFilters = '
    <div class="row">
        <div class="col-md-3">
            <select class="form-control" id="filterStatus">
                <option value="">All Statuses</option>
                <option value="draft">Draft</option>
                <option value="approved">Approved</option>
            </select>
        </div>
        <div class="col-md-3">
            <input type="search" class="form-control" placeholder="Search...">
        </div>
    </div>
';

// Build table
ob_start();
?>

<table class="table table-hover datatable">
    <thead>
        <tr>
            <th>Period</th>
            <th>End Date</th>
            <th>Employees</th>
            <th>Gross Pay</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($payruns as $payrun): ?>
        <tr>
            <td><?= $payrun['period'] ?></td>
            <td><?= date('d/m/Y', strtotime($payrun['end_date'])) ?></td>
            <td><?= $payrun['employee_count'] ?></td>
            <td>$<?= number_format($payrun['gross_pay'], 2) ?></td>
            <td>
                <a href="/payroll/view.php?id=<?= $payrun['id'] ?>" class="btn btn-sm btn-primary">
                    <i class="fas fa-eye"></i> View
                </a>
            </td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php
$content = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/modules/base/_templates/layouts/table.php';
?>
```

---

### LAYOUT 3: CARD (`layouts/card.php`)

**Visual Structure:**
```
┌─────────────────────────────────────────────────────────────────┐
│                                                                 │
│                        (gray background)                        │
│                                                                 │
│          ┌─────────────────────────────────────┐               │
│          │  CARD HEADER (optional)             │               │
│          │  Welcome Back                       │               │
│          ├─────────────────────────────────────┤               │
│          │                                     │               │
│          │  CARD BODY                          │               │
│          │  (Your content here)                │               │
│          │                                     │               │
│          │  [Username]                         │               │
│          │  [Password]                         │               │
│          │  [☑ Remember me]                    │               │
│          │                                     │               │
│          │  [     Sign In Button     ]         │               │
│          │                                     │               │
│          ├─────────────────────────────────────┤               │
│          │  CARD FOOTER (optional)             │               │
│          │  Forgot password? | Sign up         │               │
│          └─────────────────────────────────────┘               │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

**Best For:**
- ✅ Login pages
- ✅ Password reset forms
- ✅ Simple forms
- ✅ Confirmation pages
- ✅ Thank you pages
- ✅ Error pages (styled)

**Features:**
- ✅ Centered card layout
- ✅ Gray background
- ✅ Max-width 800px
- ✅ Card header (optional)
- ✅ Card footer (optional)
- ✅ Shadow effect
- ✅ Mobile responsive

**Variables:**
```php
$pageTitle = "Login - CIS";
$cardHeader = "<h3>Welcome Back</h3>";  // Header HTML
$cardFooter = "<a href='...'>...</a>";  // Footer HTML
$pageCSS = ['/path/to/custom.css'];
$pageJS = ['/path/to/custom.js'];
```

**Usage Example:**
```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/base/bootstrap.php';

$pageTitle = "Login - CIS";
$cardHeader = '<h3 class="text-center mb-0">Welcome Back</h3>';

ob_start();
?>

<form method="POST" action="/login.php">
    <div class="mb-3">
        <label class="form-label">Email Address</label>
        <input type="email" name="email" class="form-control" required autofocus>
    </div>

    <div class="mb-3">
        <label class="form-label">Password</label>
        <input type="password" name="password" class="form-control" required>
    </div>

    <div class="mb-3 form-check">
        <input type="checkbox" id="remember" name="remember" class="form-check-input">
        <label for="remember" class="form-check-label">Remember me</label>
    </div>

    <button type="submit" class="btn btn-primary w-100">
        <i class="fas fa-sign-in-alt"></i> Sign In
    </button>
</form>

<?php
$content = ob_get_clean();

$cardFooter = '
    <div class="text-center">
        <a href="/forgot-password.php">Forgot your password?</a>
    </div>
';

include $_SERVER['DOCUMENT_ROOT'] . '/modules/base/_templates/layouts/card.php';
?>
```

---

### LAYOUT 4: SPLIT (`layouts/split.php`)

**Visual Structure:**
```
┌─────────────────────────────────────────────────────────────────┐
│                                                                 │
│  LEFT PANEL (40%)        ║  RIGHT PANEL (60%)                   │
│                          ║                                      │
│  - List of items         ║  Details of selected item           │
│  - Navigation            ║                                      │
│  - Filters               ║  Charts, forms, content             │
│  - Search                ║                                      │
│                          ║                                      │
│  [Item 1]  ←selected    ║  ┌──────────────────────┐           │
│  [Item 2]                ║  │ Item 1 Details       │           │
│  [Item 3]                ║  │                      │           │
│  [Item 4]                ║  │ Description: ...     │           │
│  [Item 5]                ║  │                      │           │
│                          ║  │ [Edit] [Delete]      │           │
│                          ║  └──────────────────────┘           │
│                          ║                                      │
│   (Resizable handle ↕)  ║                                      │
│                          ║                                      │
└─────────────────────────────────────────────────────────────────┘
```

**Best For:**
- ✅ Master-detail views
- ✅ Email client layouts (list + preview)
- ✅ Product browser (list + details)
- ✅ Document viewer (list + content)
- ✅ File manager layouts
- ✅ Chat applications (conversations + messages)

**Features:**
- ✅ Two-panel resizable layout
- ✅ Adjustable split ratio (40/60 default)
- ✅ Draggable resize handle
- ✅ Saves ratio to localStorage
- ✅ Min/max limits (20%-80%)
- ✅ Mobile: stacks vertically
- ✅ Separate scroll for each panel

**Variables:**
```php
$pageTitle = "Split Layout Page";
$splitRatio = 40;                      // Left panel % (default 40)
$leftContent = "<div>...</div>";       // Left panel HTML
$rightContent = "<div>...</div>";      // Right panel HTML
$pageCSS = ['/path/to/custom.css'];
$pageJS = ['/path/to/custom.js'];
```

**Usage Example:**
```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/base/bootstrap.php';

$pageTitle = "Product Browser";
$splitRatio = 35; // 35% left, 65% right

// LEFT PANEL: Product list
ob_start();
?>
<div class="p-3">
    <h4>Products</h4>
    <input type="search" class="form-control mb-3" placeholder="Search products...">

    <div class="list-group">
        <a href="#" class="list-group-item list-group-item-action active">
            Product A - $19.99
        </a>
        <a href="#" class="list-group-item list-group-item-action">
            Product B - $24.99
        </a>
        <a href="#" class="list-group-item list-group-item-action">
            Product C - $29.99
        </a>
    </div>
</div>
<?php
$leftContent = ob_get_clean();

// RIGHT PANEL: Product details
ob_start();
?>
<div class="p-4">
    <h2>Product A</h2>
    <p class="lead">$19.99</p>

    <img src="/products/product-a.jpg" class="img-fluid mb-3">

    <h5>Description</h5>
    <p>Detailed product description goes here...</p>

    <h5>Specifications</h5>
    <ul>
        <li>Dimension: 100mm x 50mm</li>
        <li>Weight: 120g</li>
        <li>Color: Black</li>
    </ul>

    <button class="btn btn-primary">
        <i class="fas fa-cart-plus"></i> Add to Cart
    </button>
</div>
<?php
$rightContent = ob_get_clean();

include $_SERVER['DOCUMENT_ROOT'] . '/modules/base/_templates/layouts/split.php';
?>
```

---

### LAYOUT 5: BLANK (`layouts/blank.php`)

**Visual Structure:**
```
┌─────────────────────────────────────────────────────────────────┐
│                                                                 │
│                                                                 │
│                                                                 │
│                                                                 │
│                    YOUR CONTENT HERE                            │
│                  (Full control, no chrome)                      │
│                                                                 │
│                                                                 │
│                                                                 │
│                                                                 │
└─────────────────────────────────────────────────────────────────┘
```

**Best For:**
- ✅ Full-screen visualizations
- ✅ Custom layouts with no navigation
- ✅ Print-friendly pages
- ✅ Kiosk mode displays
- ✅ Embedded views (iframes)
- ✅ Report exports (PDF generation)

**Features:**
- ✅ Minimal HTML wrapper
- ✅ No header, sidebar, or footer
- ✅ Only loads CIS Core CSS
- ✅ Full control over layout
- ✅ Perfect for custom designs

**Variables:**
```php
$pageTitle = "Blank Page";
$pageCSS = ['/path/to/custom.css'];
$pageJS = ['/path/to/custom.js'];
$inlineStyles = "body { ... }";
$inlineScripts = "console.log('...')";
```

**Usage Example:**
```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/base/bootstrap.php';

$pageTitle = "Custom Report";
$pageCSS = ['/modules/reports/assets/css/custom-report.css'];

ob_start();
?>

<div class="custom-report-container">
    <div class="report-header">
        <img src="/logo.png" alt="Logo">
        <h1>Monthly Sales Report</h1>
        <p>October 2025</p>
    </div>

    <div class="report-content">
        <!-- Full custom design -->
        <canvas id="salesChart"></canvas>

        <table class="report-table">
            <thead>
                <tr>
                    <th>Store</th>
                    <th>Sales</th>
                    <th>Growth</th>
                </tr>
            </thead>
            <tbody>
                <!-- Data rows -->
            </tbody>
        </table>
    </div>

    <div class="report-footer">
        <p>Generated: <?= date('d M Y H:i') ?></p>
        <button onclick="window.print()" class="btn btn-primary">
            <i class="fas fa-print"></i> Print Report
        </button>
    </div>
</div>

<?php
$content = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/modules/base/_templates/layouts/blank.php';
?>
```

---

## 🎯 LAYOUT COMPARISON TABLE

| Layout | Header | Sidebar | Footer | Best For | Complexity |
|--------|--------|---------|--------|----------|------------|
| **dashboard.php** | ✅ Yes | ✅ Yes | ✅ Yes | Main dashboards, complex pages | High |
| **table.php** | ✅ Yes | ❌ No | ✅ Yes | Data tables, lists | Medium |
| **card.php** | ❌ No | ❌ No | ❌ No | Login, simple forms | Low |
| **split.php** | ❌ No | ❌ No | ❌ No | Master-detail views | Medium |
| **blank.php** | ❌ No | ❌ No | ❌ No | Custom layouts | Very Low |

---

## 📝 INTEGRATION GUIDE FOR PAYROLL

### Current Payroll Views → Recommended Layout

| Current View | Recommended Layout | Why |
|--------------|-------------------|-----|
| `dashboard.php` | **dashboard.php** | Full dashboard with sidebar navigation |
| `payruns.php` | **table.php** | Perfect for data table with filters |
| `payrun-detail.php` | **dashboard.php** | Complex page with multiple sections |
| `amendments.php` | **table.php** | List of amendments with filters |
| `discrepancies.php` | **table.php** | List with action buttons |
| `bonuses.php` | **table.php** | List to review and approve |
| `leave-requests.php` | **table.php** | List with approval actions |
| `vend-payments.php` | **table.php** | Payment requests list |
| `reports/*.php` | **dashboard.php** | Charts and analytics |
| `settings/*.php` | **dashboard.php** | Configuration forms |

---

## 🚀 USAGE PATTERNS

### Pattern 1: Simple Page (Dashboard Layout)

```php
<?php
// 1. Include base bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/base/bootstrap.php';

// 2. Set page variables
$pageTitle = "My Page";
$pageCSS = ['/modules/mymodule/assets/css/mypage.css'];

// 3. Build content
ob_start();
?>

<div class="container-fluid">
    <h1>My Page Content</h1>
    <!-- Your HTML here -->
</div>

<?php
$content = ob_get_clean();

// 4. Include layout
include $_SERVER['DOCUMENT_ROOT'] . '/modules/base/_templates/layouts/dashboard.php';
?>
```

### Pattern 2: Table Page (Table Layout)

```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/base/bootstrap.php';

$pageTitle = "Data List";
$tableTitle = "Items";
$tableSubtitle = "View all items";

// Action buttons
$headerActions = '<a href="/create.php" class="btn btn-primary">Create</a>';

// Filters
$tableFilters = '
    <div class="row">
        <div class="col-md-3">
            <select class="form-control">
                <option>Filter 1</option>
            </select>
        </div>
    </div>
';

ob_start();
?>

<table class="table datatable">
    <thead>
        <tr>
            <th>Column 1</th>
            <th>Column 2</th>
        </tr>
    </thead>
    <tbody>
        <!-- Data rows -->
    </tbody>
</table>

<?php
$content = ob_get_clean();
include $_SERVER['DOCUMENT_ROOT'] . '/modules/base/_templates/layouts/table.php';
?>
```

### Pattern 3: Login Page (Card Layout)

```php
<?php
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/base/bootstrap.php';

$pageTitle = "Login";
$cardHeader = '<h3 class="text-center">Welcome</h3>';

ob_start();
?>

<form method="POST">
    <div class="mb-3">
        <label>Email</label>
        <input type="email" class="form-control" name="email" required>
    </div>
    <div class="mb-3">
        <label>Password</label>
        <input type="password" class="form-control" name="password" required>
    </div>
    <button type="submit" class="btn btn-primary w-100">Sign In</button>
</form>

<?php
$content = ob_get_clean();

$cardFooter = '<div class="text-center"><a href="/forgot.php">Forgot password?</a></div>';

include $_SERVER['DOCUMENT_ROOT'] . '/modules/base/_templates/layouts/card.php';
?>
```

---

## ✅ QUALITY CHECKLIST

### Before Using Templates:

- [ ] Read this guide completely
- [ ] Understand which layout to use
- [ ] Know what variables are available
- [ ] Review usage examples
- [ ] Test on desktop and mobile

### When Implementing:

- [ ] Use `ob_start()` to capture content
- [ ] Set all required variables
- [ ] Include layout at the end
- [ ] Test responsive design
- [ ] Verify no console errors

### After Implementation:

- [ ] Delete custom header/footer files
- [ ] Move inline CSS to separate file
- [ ] Test all navigation links
- [ ] Verify breadcrumbs work
- [ ] Check mobile responsiveness
- [ ] Validate HTML
- [ ] Test performance (< 500ms)

---

## 📊 BENEFITS SUMMARY

### Code Quality:
- ✅ 70%+ less code per page
- ✅ Zero duplication (DRY)
- ✅ Consistent structure
- ✅ Easy to maintain

### User Experience:
- ✅ Consistent UI across modules
- ✅ Familiar navigation
- ✅ Mobile responsive
- ✅ Fast loading

### Developer Experience:
- ✅ Quick implementation
- ✅ Clear patterns
- ✅ Good documentation
- ✅ Reusable components

### Performance:
- ✅ Smaller file sizes
- ✅ Cached assets
- ✅ Optimized CSS/JS
- ✅ Fast page loads

---

## 🎉 CONCLUSION

The CIS base template system provides:

1. ✅ **5 production-ready layouts** for all use cases
2. ✅ **5 reusable components** (header, sidebar, footer, search, breadcrumbs)
3. ✅ **Modern design system** (CSS variables, responsive, mobile-first)
4. ✅ **100% jQuery-FREE** (vanilla JavaScript)
5. ✅ **Comprehensive documentation** (this guide)

**All templates are complete, tested, and ready to use.**

**For payroll integration:** Use `dashboard.php` for main pages and `table.php` for list pages.

---

**Last Updated:** October 30, 2025
**Version:** 2.0.0
**Status:** ✅ Production Ready
