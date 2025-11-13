# VAPE ULTRA COMPLETE - FILE MANIFEST

**Version:** 1.0
**Generated:** 2025-11-12
**Total Files:** 34

---

## 📋 TABLE OF CONTENTS

1. [Core Files](#core-files)
2. [CSS Files](#css-files)
3. [JavaScript Files](#javascript-files)
4. [PHP Layouts](#php-layouts)
5. [PHP Components](#php-components)
6. [PHP Views](#php-views)
7. [Configuration](#configuration)
8. [Documentation](#documentation)
9. [Dependencies](#dependencies)
10. [File Size Summary](#file-size-summary)

---

## 🎯 CORE FILES

### Renderer.php
**Location:** `/Renderer.php`
**Purpose:** Template rendering engine
**Size:** ~15KB
**Dependencies:** PHP 7.4+

**What it does:**
- Bridges between application modules and the VapeUltra template system
- Renders layouts, components, and views with data injection
- Manages CSS/JS asset loading order
- Handles template inheritance and composition
- Provides helper methods for common UI patterns

**Key Methods:**
```php
render($layout, $data)           // Render a complete page
renderComponent($name, $data)    // Render a component
renderView($name, $data)         // Render a view partial
addCSS($file)                    // Add CSS to queue
addJS($file)                     // Add JS to queue
```

**Usage Example:**
```php
use Template\Renderer;

$renderer = new Renderer();
$renderer->render('main', [
    'title' => 'Dashboard',
    'content' => $dashboardContent,
    'user' => $currentUser
]);
```

---

## 🎨 CSS FILES

### 1. Base Theme Files (6 files)

#### variables.css
**Location:** `/css/variables.css`
**Purpose:** Global CSS custom properties
**Size:** ~8KB
**Load Order:** 1 (first)

**Contains:**
- Color palette (primary, secondary, accent, neutral, semantic colors)
- Typography variables (font families, sizes, weights, line heights)
- Spacing scale (0.25rem to 6rem)
- Border radius values
- Shadow definitions
- Z-index layers
- Transition durations
- Breakpoint references

**Key Variables:**
```css
--color-primary: #6366f1;
--color-secondary: #8b5cf6;
--font-family-base: 'Inter', sans-serif;
--spacing-base: 1rem;
--border-radius-base: 0.5rem;
--shadow-base: 0 1px 3px rgba(0,0,0,0.12);
--transition-base: 0.2s ease;
```

---

#### base.css
**Location:** `/css/base.css`
**Purpose:** Global base styles and resets
**Size:** ~6KB
**Load Order:** 2

**Contains:**
- Modern CSS reset (box-sizing, margins, padding)
- Body and html base styles
- Typography defaults
- Link styles
- List resets
- Form element normalization
- Print styles

**Key Styles:**
```css
*,::before,::after { box-sizing: border-box; }
body { font-family: var(--font-family-base); }
a { color: var(--color-primary); text-decoration: none; }
```

---

#### layout.css
**Location:** `/css/layout.css`
**Purpose:** Core layout structure
**Size:** ~12KB
**Load Order:** 3

**Contains:**
- `.app-wrapper` (main container)
- `.app-sidebar` (left navigation)
- `.app-sidebar-right` (right panel)
- `.app-content` (main content area)
- `.app-header` (top navigation)
- `.app-footer` (bottom section)
- Responsive grid system
- Flexbox utilities
- Mobile/tablet breakpoint adjustments

**Key Classes:**
```css
.app-wrapper { display: flex; min-height: 100vh; }
.app-sidebar { width: 260px; position: fixed; }
.app-content { margin-left: 260px; flex: 1; }
```

**Responsive:**
- Desktop: Full sidebar (260px)
- Tablet: Collapsed sidebar (80px)
- Mobile: Overlay sidebar (hidden by default)

---

#### components.css
**Location:** `/css/components.css`
**Purpose:** Reusable UI components
**Size:** ~18KB
**Load Order:** 4

**Contains:**
- **Cards:** `.card`, `.card-header`, `.card-body`, `.card-footer`
- **Buttons:** `.btn`, `.btn-primary`, `.btn-secondary`, `.btn-sm/lg`
- **Badges:** `.badge`, `.badge-primary`, `.badge-success`, etc.
- **Alerts:** `.alert`, `.alert-success`, `.alert-danger`, etc.
- **Tables:** `.table`, `.table-striped`, `.table-hover`
- **Forms:** `.form-control`, `.form-label`, `.form-group`
- **Modals:** `.modal`, `.modal-dialog`, `.modal-content`
- **Dropdowns:** `.dropdown`, `.dropdown-menu`, `.dropdown-item`
- **Tooltips:** `.tooltip`, `.tooltip-inner`
- **Progress bars:** `.progress`, `.progress-bar`

**Key Patterns:**
```css
.card { background: var(--color-surface); border-radius: var(--border-radius-lg); }
.btn { padding: 0.5rem 1rem; border-radius: var(--border-radius-base); }
.alert { padding: 1rem; margin-bottom: 1rem; border-radius: var(--border-radius-base); }
```

---

#### utilities.css
**Location:** `/css/utilities.css`
**Purpose:** Utility classes for quick styling
**Size:** ~10KB
**Load Order:** 5

**Contains:**
- **Spacing:** `.m-{0-5}`, `.p-{0-5}`, `.mx-auto`, `.my-3`, etc.
- **Typography:** `.text-{left|center|right}`, `.text-{sm|lg|xl}`, `.font-{bold|normal}`
- **Colors:** `.text-primary`, `.bg-primary`, `.text-danger`, `.bg-success`
- **Display:** `.d-{none|block|flex|grid}`, `.d-{sm|md|lg}-{block|none}`
- **Flexbox:** `.flex-{row|column}`, `.justify-{start|center|end|between}`, `.align-{start|center|end}`
- **Sizing:** `.w-{25|50|75|100}`, `.h-{25|50|75|100}`
- **Visibility:** `.hidden`, `.visible`, `.sr-only`
- **Borders:** `.border`, `.border-{top|bottom|left|right}`, `.rounded`
- **Shadows:** `.shadow-{sm|md|lg}`

**Key Classes:**
```css
.mt-3 { margin-top: 1rem; }
.p-4 { padding: 1.5rem; }
.text-center { text-align: center; }
.d-flex { display: flex; }
.w-100 { width: 100%; }
```

---

#### animations.css
**Location:** `/css/animations.css`
**Purpose:** Animation effects and transitions
**Size:** ~5KB
**Load Order:** 6

**Contains:**
- **Fade animations:** `@keyframes fadeIn`, `@keyframes fadeOut`
- **Slide animations:** `@keyframes slideInRight`, `@keyframes slideInLeft`
- **Scale animations:** `@keyframes scaleIn`, `@keyframes scaleOut`
- **Rotate animations:** `@keyframes rotate`, `@keyframes pulse`
- **Utility classes:** `.fade-in`, `.slide-in-right`, `.pulse`, `.spin`
- **Hover effects:** `.hover-lift`, `.hover-glow`
- **Loading spinners:** `.spinner`, `.skeleton-loader`

**Key Animations:**
```css
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
.fade-in { animation: fadeIn 0.3s ease; }
.hover-lift:hover { transform: translateY(-2px); transition: transform 0.2s; }
```

---

### 2. Award-Winning Theme Files (5 files)

#### silver-chrome-theme.css
**Location:** `/css/silver-chrome-theme.css`
**Purpose:** Premium silver/chrome color scheme
**Size:** ~8KB
**Load Order:** 7

**Features:**
- Silver-gray base with chrome accents
- High-contrast text for readability
- Metallic gradient effects
- Professional corporate aesthetic

**Color Palette:**
```css
--theme-primary: #c0c0c0 (silver)
--theme-accent: #4a5568 (charcoal)
--theme-surface: #f7fafc (light gray)
--theme-text: #1a202c (almost black)
```

**Apply:**
```html
<link rel="stylesheet" href="css/silver-chrome-theme.css">
```

---

#### store-cards-award-winning.css
**Location:** `/css/store-cards-award-winning.css`
**Purpose:** Enhanced store location cards
**Size:** ~12KB
**Load Order:** 8

**Features:**
- 3D card perspective effects
- Hover animations (lift, glow, tilt)
- Store status indicators (open/closed)
- Performance metrics badges
- Responsive grid layout
- Google Maps integration styling

**Key Components:**
```css
.store-card { perspective: 1000px; }
.store-card:hover { transform: translateY(-5px); box-shadow: 0 10px 30px rgba(0,0,0,0.2); }
.store-status { position: absolute; top: 1rem; right: 1rem; }
.store-metrics { display: flex; gap: 1rem; }
```

**Usage:**
```html
<div class="store-card" data-store-id="17">
  <div class="store-status badge badge-success">Open</div>
  <div class="store-header">
    <h3>Vape Shed Auckland CBD</h3>
    <p class="store-address">123 Queen St, Auckland</p>
  </div>
  <div class="store-metrics">
    <div class="metric">
      <span class="label">Sales Today</span>
      <span class="value">$2,450</span>
    </div>
  </div>
</div>
```

---

#### award-winning-refinements.css
**Location:** `/css/award-winning-refinements.css`
**Purpose:** Polish and refinement layer
**Size:** ~6KB
**Load Order:** 9

**Features:**
- Micro-interactions (button ripples, input focus effects)
- Enhanced hover states
- Smooth transitions
- Visual feedback improvements
- Accessibility enhancements (focus outlines, ARIA support)

**Key Refinements:**
```css
.btn:active { transform: scale(0.98); }
.form-control:focus { box-shadow: 0 0 0 3px rgba(99,102,241,0.1); }
.card:hover { box-shadow: var(--shadow-lg); }
```

---

#### premium-dashboard-header.css
**Location:** `/css/premium-dashboard-header.css`
**Purpose:** Enhanced header bar
**Size:** ~10KB
**Load Order:** 10

**Features:**
- Glass morphism effect (backdrop blur)
- Sticky positioning with shadow on scroll
- Search bar with live results
- Notification badge animations
- User profile dropdown
- Quick actions toolbar
- Responsive mobile menu

**Key Components:**
```css
.app-header {
  backdrop-filter: blur(10px);
  background: rgba(255,255,255,0.8);
  border-bottom: 1px solid rgba(0,0,0,0.1);
}

.header-search { width: 400px; max-width: 100%; }
.notification-badge { animation: pulse 2s infinite; }
.quick-actions { display: flex; gap: 0.5rem; }
```

**JavaScript Integration:**
```javascript
// Auto-hide header on scroll down, show on scroll up
let lastScroll = 0;
window.addEventListener('scroll', () => {
  const currentScroll = window.pageYOffset;
  if (currentScroll > lastScroll) {
    document.querySelector('.app-header').classList.add('header-hidden');
  } else {
    document.querySelector('.app-header').classList.remove('header-hidden');
  }
  lastScroll = currentScroll;
});
```

---

#### sidebar-award-winning.css
**Location:** `/css/sidebar-award-winning.css`
**Purpose:** Enhanced sidebar navigation
**Size:** ~14KB
**Load Order:** 11

**Features:**
- Multi-level navigation (3 levels deep)
- Hover preview tooltips
- Icon animations
- Badge indicators (counts, status)
- Collapsible sections with smooth animations
- Active state highlighting
- Recent items quick access
- Keyboard navigation support

**Key Components:**
```css
.sidebar-nav { list-style: none; padding: 0; }
.sidebar-nav-item { position: relative; }
.sidebar-nav-item:hover { background: rgba(99,102,241,0.1); }
.sidebar-nav-item.active::before {
  content: '';
  position: absolute;
  left: 0;
  height: 100%;
  width: 3px;
  background: var(--color-primary);
}

.sidebar-nav-submenu {
  max-height: 0;
  overflow: hidden;
  transition: max-height 0.3s ease;
}
.sidebar-nav-item.open > .sidebar-nav-submenu {
  max-height: 500px;
}
```

**Navigation Structure:**
```html
<nav class="sidebar-nav">
  <ul>
    <li class="sidebar-nav-item active">
      <a href="/dashboard">
        <i class="bi bi-house"></i>
        <span>Dashboard</span>
      </a>
    </li>
    <li class="sidebar-nav-item has-submenu">
      <a href="#" class="sidebar-nav-toggle">
        <i class="bi bi-shop"></i>
        <span>Stores</span>
        <i class="bi bi-chevron-down sidebar-nav-arrow"></i>
      </a>
      <ul class="sidebar-nav-submenu">
        <li><a href="/stores">All Stores</a></li>
        <li><a href="/stores/map">Store Map</a></li>
        <li><a href="/stores/performance">Performance</a></li>
      </ul>
    </li>
  </ul>
</nav>
```

---

### 3. Alternative Theme Files (2 files)

#### theme-netflix-dark.css
**Location:** `/css/theme-netflix-dark.css`
**Purpose:** Netflix-inspired dark theme
**Size:** ~15KB
**Load Order:** Optional (alternative theme)

**Features:**
- Almost-black background (#141414)
- Netflix red accent (#e50914)
- High contrast (14:1 ratio)
- Premium entertainment aesthetic
- Glowing hover effects

**Color Palette:**
```css
--netflix-bg: #141414;
--netflix-surface: #1f1f1f;
--netflix-red: #e50914;
--netflix-text: #ffffff;
--netflix-gray: #808080;
```

**Activation Methods:**

**Method 1: Body Class**
```html
<body class="theme-netflix">
```

**Method 2: CSS Import**
```html
<link rel="stylesheet" href="css/theme-netflix-dark.css">
```

**Method 3: JavaScript Toggle**
```javascript
document.body.classList.toggle('theme-netflix');
localStorage.setItem('theme', 'netflix');
```

**Preview URL:**
```
https://staff.vapeshed.co.nz/theme-preview.html?theme=netflix
```

---

#### theme-oceanic-gradient.css
**Location:** `/css/theme-oceanic-gradient.css`
**Purpose:** Modern gradient theme with tech aesthetic
**Size:** ~18KB
**Load Order:** Optional (alternative theme)

**Features:**
- Gradient background (dark blue → teal)
- Cyan accent color (#00bcd4)
- Glass morphism effects
- Animated glow/pulse
- Tech-forward modern feel

**Color Palette:**
```css
--oceanic-bg-start: #0f2027;
--oceanic-bg-mid: #203a43;
--oceanic-bg-end: #2c5364;
--oceanic-accent: #00bcd4;
--oceanic-text: #e0f7fa;
```

**Gradient Background:**
```css
body.theme-oceanic {
  background: linear-gradient(135deg,
    var(--oceanic-bg-start) 0%,
    var(--oceanic-bg-mid) 50%,
    var(--oceanic-bg-end) 100%);
}
```

**Glass Morphism:**
```css
.card {
  background: rgba(255,255,255,0.05);
  backdrop-filter: blur(10px);
  border: 1px solid rgba(255,255,255,0.1);
}
```

**Activation Methods:** Same as Netflix theme above

**Preview URL:**
```
https://staff.vapeshed.co.nz/theme-preview.html?theme=oceanic
```

---

### 4. Custom Dashboard File (1 file)

#### dashboard-custom.css
**Location:** `/css/dashboard-custom.css` (if created)
**Purpose:** Page-specific dashboard styles
**Size:** Variable
**Load Order:** 13 (last, highest specificity)

**Note:** This file is loaded on the dashboard page only and can override any base/theme styles for that specific page. Create this file if you need dashboard-specific customizations that shouldn't affect other pages.

---

## ⚙️ JAVASCRIPT FILES

### 1. core.js
**Location:** `/js/core.js`
**Purpose:** Core application functionality
**Size:** ~20KB
**Load Order:** 1 (first)

**Contains:**
- Application initialization
- Global event handlers
- Router setup
- State management
- Local storage utilities
- Session handling
- Error handling

**Key Functions:**
```javascript
App.init()                    // Initialize application
App.navigate(url)             // Client-side navigation
App.setState(key, value)      // Update global state
App.getState(key)             // Get global state
App.storage.set(key, value)   // LocalStorage wrapper
App.storage.get(key)          // Get from storage
App.error(message, error)     // Global error handler
```

**Initialization:**
```javascript
document.addEventListener('DOMContentLoaded', () => {
  App.init({
    debug: false,
    apiBase: '/api/v1',
    theme: localStorage.getItem('theme') || 'default'
  });
});
```

---

### 2. components.js
**Location:** `/js/components.js`
**Purpose:** Interactive UI components
**Size:** ~25KB
**Load Order:** 2

**Contains:**
- **Sidebar:** Toggle, collapse, navigation
- **Modals:** Open, close, dynamic content
- **Dropdowns:** Toggle, positioning
- **Tooltips:** Show, hide, positioning
- **Tabs:** Switch, deep linking
- **Accordions:** Expand, collapse
- **Carousels:** Navigate, autoplay
- **Datepickers:** Select date, range
- **File uploads:** Drag & drop, preview

**Key Components:**

**Sidebar:**
```javascript
Sidebar.toggle()              // Toggle sidebar open/closed
Sidebar.collapse()            // Collapse to icons only
Sidebar.expand()              // Expand to full width
Sidebar.setActive(item)       // Highlight active nav item
```

**Modals:**
```javascript
Modal.open(id, data)          // Open modal by ID with data
Modal.close(id)               // Close modal
Modal.confirm(message)        // Show confirmation dialog
Modal.alert(message, type)    // Show alert modal
```

**Dropdowns:**
```javascript
Dropdown.init(selector)       // Initialize dropdown
Dropdown.open(el)             // Open dropdown
Dropdown.close(el)            // Close dropdown
```

---

### 3. utils.js
**Location:** `/js/utils.js`
**Purpose:** Utility functions and helpers
**Size:** ~15KB
**Load Order:** 3

**Contains:**
- **String utilities:** `formatCurrency()`, `truncate()`, `slugify()`
- **Date utilities:** `formatDate()`, `parseDate()`, `diffDays()`
- **Number utilities:** `formatNumber()`, `percentage()`, `round()`
- **Array utilities:** `unique()`, `groupBy()`, `sortBy()`
- **Object utilities:** `deepClone()`, `merge()`, `get()`, `set()`
- **DOM utilities:** `$(selector)`, `addClass()`, `removeClass()`, `on()`
- **Validation:** `isEmail()`, `isPhone()`, `isURL()`

**Key Functions:**
```javascript
// String
Utils.formatCurrency(1234.56)           // "$1,234.56"
Utils.truncate('Long text...', 20)      // "Long text..."
Utils.slugify('Hello World!')           // "hello-world"

// Date
Utils.formatDate(date, 'YYYY-MM-DD')    // "2025-11-12"
Utils.diffDays(date1, date2)            // 7

// Number
Utils.formatNumber(1234567)             // "1,234,567"
Utils.percentage(0.856)                 // "85.6%"

// Array
Utils.unique([1,2,2,3])                 // [1,2,3]
Utils.groupBy(users, 'role')            // { admin: [...], user: [...] }

// Object
Utils.deepClone(obj)                    // Deep copy
Utils.get(obj, 'user.profile.name')     // Safe nested access

// DOM
Utils.$('.btn')                         // document.querySelectorAll('.btn')
Utils.addClass(el, 'active')            // Add class
Utils.on(el, 'click', handler)          // Add event listener

// Validation
Utils.isEmail('test@example.com')       // true
Utils.isPhone('555-1234')               // true
```

---

### 4. api.js
**Location:** `/js/api.js`
**Purpose:** API communication layer
**Size:** ~18KB
**Load Order:** 4

**Contains:**
- HTTP client (GET, POST, PUT, DELETE)
- Request/response interceptors
- Error handling
- Token management
- Rate limiting
- Retry logic
- WebSocket support

**Key Functions:**
```javascript
// HTTP Methods
API.get(url, params)                    // GET request
API.post(url, data)                     // POST request
API.put(url, data)                      // PUT request
API.delete(url)                         // DELETE request

// Batch Requests
API.batch([
  { method: 'get', url: '/users' },
  { method: 'get', url: '/products' }
])

// File Upload
API.upload(url, file, onProgress)

// WebSocket
API.ws.connect(url)
API.ws.on('message', handler)
API.ws.send(data)
```

**Usage Example:**
```javascript
// Fetch dashboard data
const data = await API.get('/dashboard/stats', {
  period: '7d',
  store_id: 17
});

// Update user profile
await API.put('/user/profile', {
  name: 'John Doe',
  email: 'john@example.com'
});

// Handle errors
try {
  await API.post('/orders', orderData);
} catch (error) {
  if (error.status === 422) {
    // Validation error
    console.log(error.errors);
  }
}
```

---

### 5. notifications.js
**Location:** `/js/notifications.js`
**Purpose:** Toast notifications and alerts
**Size:** ~10KB
**Load Order:** 5

**Contains:**
- Toast notifications (success, error, warning, info)
- Position management (top-right, top-left, bottom-right, bottom-left, center)
- Auto-dismiss timers
- Stacking behavior
- Progress bars
- Action buttons
- Sound effects (optional)

**Key Functions:**
```javascript
Notify.success(message, options)        // Success toast
Notify.error(message, options)          // Error toast
Notify.warning(message, options)        // Warning toast
Notify.info(message, options)           // Info toast
Notify.confirm(message, callback)       // Confirmation dialog
```

**Options:**
```javascript
{
  duration: 3000,          // Auto-dismiss after 3s
  position: 'top-right',   // Positioning
  closable: true,          // Show close button
  progress: true,          // Show progress bar
  sound: false,            // Play sound
  actions: [               // Action buttons
    { label: 'Undo', callback: () => {} }
  ]
}
```

**Usage Examples:**
```javascript
// Simple success message
Notify.success('Product added to cart');

// Error with custom duration
Notify.error('Failed to save', { duration: 5000 });

// Confirmation dialog
Notify.confirm('Delete this item?', (confirmed) => {
  if (confirmed) {
    // Delete item
  }
});

// Notification with action
Notify.info('Item deleted', {
  actions: [
    { label: 'Undo', callback: () => restoreItem() }
  ]
});
```

---

### 6. charts.js
**Location:** `/js/charts.js`
**Purpose:** Chart rendering and data visualization
**Size:** ~22KB
**Load Order:** 6

**Contains:**
- Chart.js wrapper and configuration
- Pre-configured chart types (line, bar, pie, doughnut, radar)
- Theme-aware color palettes
- Responsive sizing
- Animation presets
- Data formatters
- Export functionality (PNG, SVG, PDF)

**Key Functions:**
```javascript
Charts.line(element, data, options)     // Line chart
Charts.bar(element, data, options)      // Bar chart
Charts.pie(element, data, options)      // Pie chart
Charts.doughnut(element, data, options) // Doughnut chart
Charts.radar(element, data, options)    // Radar chart
Charts.update(chartId, newData)         // Update chart data
Charts.destroy(chartId)                 // Destroy chart instance
Charts.export(chartId, format)          // Export as image
```

**Usage Example:**
```javascript
// Sales line chart
const salesChart = Charts.line('#sales-chart', {
  labels: ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'],
  datasets: [{
    label: 'Sales',
    data: [1200, 1900, 1500, 2100, 1800, 2400, 2200],
    borderColor: '#6366f1',
    backgroundColor: 'rgba(99,102,241,0.1)'
  }]
}, {
  responsive: true,
  maintainAspectRatio: false,
  plugins: {
    legend: { display: true },
    tooltip: { enabled: true }
  }
});

// Update chart with new data
Charts.update(salesChart.id, {
  labels: ['Week 1', 'Week 2', 'Week 3', 'Week 4'],
  datasets: [{ data: [5000, 7500, 6200, 8900] }]
});

// Export chart as PNG
Charts.export(salesChart.id, 'png');
```

**Pre-configured Chart Themes:**
```javascript
Charts.themes = {
  default: { /* Default colors */ },
  netflix: { /* Netflix red theme */ },
  oceanic: { /* Oceanic cyan theme */ },
  success: { /* Green success colors */ },
  danger: { /* Red danger colors */ }
};

// Apply theme
Charts.line('#chart', data, { theme: 'netflix' });
```

---

## 📐 PHP LAYOUTS

### 1. base.php
**Location:** `/layouts/base.php`
**Purpose:** Master HTML shell
**Size:** ~5KB
**Dependencies:** config.php

**Structure:**
```php
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= $title ?? 'Dashboard' ?></title>

  <!-- CSS -->
  <?php foreach ($cssFiles as $file): ?>
    <link rel="stylesheet" href="<?= $cdnBase ?>/css/<?= $file ?>">
  <?php endforeach; ?>
</head>
<body class="<?= $bodyClass ?? '' ?>">

  <?= $content ?>

  <!-- JS -->
  <?php foreach ($jsFiles as $file): ?>
    <script src="<?= $cdnBase ?>/js/<?= $file ?>"></script>
  <?php endforeach; ?>
</body>
</html>
```

**Variables:**
- `$title` - Page title
- `$cssFiles` - Array of CSS files to load
- `$jsFiles` - Array of JS files to load
- `$bodyClass` - Additional body classes
- `$content` - Main page content (from child layout)
- `$cdnBase` - CDN base URL (from config.php)

---

### 2. main.php
**Location:** `/layouts/main.php`
**Purpose:** Standard dashboard layout
**Size:** ~8KB
**Dependencies:** base.php, header.php, sidebar.php, sidebar-right.php, footer.php

**Structure:**
```php
<!-- Rendered inside base.php $content -->
<div class="app-wrapper">

  <!-- Left Sidebar -->
  <?php include 'components/sidebar.php'; ?>

  <!-- Main Content Area -->
  <div class="app-content">

    <!-- Header -->
    <?php include 'components/header.php'; ?>

    <!-- Page Content -->
    <main class="app-main">
      <?= $pageContent ?>
    </main>

    <!-- Footer -->
    <?php include 'components/footer.php'; ?>
  </div>

  <!-- Right Sidebar (Optional) -->
  <?php if ($showRightSidebar ?? false): ?>
    <?php include 'components/sidebar-right.php'; ?>
  <?php endif; ?>

</div>
```

**Variables:**
- `$pageContent` - Main page body
- `$showRightSidebar` - Boolean to show/hide right sidebar
- All header/sidebar variables (passed through)

**Usage:**
```php
$renderer->render('main', [
  'title' => 'Dashboard',
  'pageContent' => $dashboardHTML,
  'user' => $currentUser,
  'navigation' => $navItems
]);
```

---

### 3. minimal.php
**Location:** `/layouts/minimal.php`
**Purpose:** Minimal layout (login, auth pages)
**Size:** ~3KB
**Dependencies:** base.php, header-minimal.php

**Structure:**
```php
<!-- Rendered inside base.php $content -->
<div class="minimal-wrapper">

  <!-- Minimal Header -->
  <?php include 'components/header-minimal.php'; ?>

  <!-- Centered Content -->
  <main class="minimal-main">
    <div class="minimal-container">
      <?= $pageContent ?>
    </div>
  </main>

</div>
```

**Variables:**
- `$pageContent` - Form content (login, register, etc.)
- `$showLogo` - Boolean to show/hide logo

**Usage:**
```php
$renderer->render('minimal', [
  'title' => 'Login',
  'pageContent' => $loginFormHTML
]);
```

**Typical Use Cases:**
- Login page
- Registration page
- Password reset
- Error pages (404, 500)
- Maintenance page

---

## 🧩 PHP COMPONENTS

### 1. header.php
**Location:** `/components/header.php`
**Purpose:** Main application header
**Size:** ~6KB
**Dependencies:** None

**Structure:**
```php
<header class="app-header">
  <div class="header-left">
    <button class="sidebar-toggle" data-action="toggle-sidebar">
      <i class="bi bi-list"></i>
    </button>
    <div class="header-search">
      <input type="text" placeholder="Search..." class="form-control">
    </div>
  </div>

  <div class="header-right">
    <div class="quick-actions">
      <button class="btn btn-sm btn-primary">New Order</button>
      <button class="btn btn-sm btn-secondary">Reports</button>
    </div>

    <div class="header-notifications">
      <button class="notification-bell" data-count="5">
        <i class="bi bi-bell"></i>
        <span class="badge">5</span>
      </button>
    </div>

    <div class="header-user">
      <img src="<?= $user['avatar'] ?>" alt="<?= $user['name'] ?>" class="user-avatar">
      <span><?= $user['name'] ?></span>
    </div>
  </div>
</header>
```

**Variables:**
- `$user` - Current user object (name, avatar, role)
- `$notifications` - Array of notifications
- `$quickActions` - Array of quick action buttons

---

### 2. header-minimal.php
**Location:** `/components/header-minimal.php`
**Purpose:** Minimal header for auth pages
**Size:** ~1KB
**Dependencies:** None

**Structure:**
```php
<header class="minimal-header">
  <div class="minimal-header-container">
    <img src="/logo.png" alt="Logo" class="logo">
  </div>
</header>
```

**Variables:**
- `$logoUrl` - Logo image URL
- `$showLogo` - Boolean to show/hide logo

---

### 3. sidebar.php
**Location:** `/components/sidebar.php`
**Purpose:** Main navigation sidebar
**Size:** ~10KB
**Dependencies:** None

**Structure:**
```php
<aside class="app-sidebar">
  <div class="sidebar-header">
    <img src="/logo.png" alt="Logo" class="sidebar-logo">
    <span class="sidebar-title">Vape Shed</span>
  </div>

  <nav class="sidebar-nav">
    <ul>
      <?php foreach ($navigation as $item): ?>
        <li class="sidebar-nav-item <?= $item['active'] ? 'active' : '' ?>">
          <a href="<?= $item['url'] ?>">
            <i class="<?= $item['icon'] ?>"></i>
            <span><?= $item['label'] ?></span>
            <?php if (isset($item['badge'])): ?>
              <span class="badge badge-<?= $item['badge']['type'] ?>">
                <?= $item['badge']['count'] ?>
              </span>
            <?php endif; ?>
          </a>

          <?php if (isset($item['submenu'])): ?>
            <ul class="sidebar-nav-submenu">
              <?php foreach ($item['submenu'] as $subitem): ?>
                <li>
                  <a href="<?= $subitem['url'] ?>">
                    <?= $subitem['label'] ?>
                  </a>
                </li>
              <?php endforeach; ?>
            </ul>
          <?php endif; ?>
        </li>
      <?php endforeach; ?>
    </ul>
  </nav>

  <div class="sidebar-footer">
    <button class="btn btn-sm btn-secondary">Settings</button>
  </div>
</aside>
```

**Variables:**
- `$navigation` - Array of navigation items
  ```php
  [
    [
      'label' => 'Dashboard',
      'url' => '/dashboard',
      'icon' => 'bi bi-house',
      'active' => true,
      'badge' => ['type' => 'primary', 'count' => 5]
    ],
    [
      'label' => 'Stores',
      'url' => '/stores',
      'icon' => 'bi bi-shop',
      'submenu' => [
        ['label' => 'All Stores', 'url' => '/stores'],
        ['label' => 'Map View', 'url' => '/stores/map']
      ]
    ]
  ]
  ```

---

### 4. sidebar-right.php
**Location:** `/components/sidebar-right.php`
**Purpose:** Optional right sidebar panel
**Size:** ~5KB
**Dependencies:** None

**Structure:**
```php
<aside class="app-sidebar-right">
  <div class="sidebar-right-header">
    <h3>Activity Feed</h3>
    <button class="close-btn" data-action="close-sidebar-right">
      <i class="bi bi-x"></i>
    </button>
  </div>

  <div class="sidebar-right-content">
    <?php foreach ($activities as $activity): ?>
      <div class="activity-item">
        <img src="<?= $activity['user']['avatar'] ?>" class="activity-avatar">
        <div class="activity-content">
          <p><?= $activity['message'] ?></p>
          <span class="activity-time"><?= $activity['time'] ?></span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</aside>
```

**Variables:**
- `$activities` - Array of activity items
- `$showActivityFeed` - Boolean to show/hide activity feed

---

### 5. footer.php
**Location:** `/components/footer.php`
**Purpose:** Application footer
**Size:** ~2KB
**Dependencies:** None

**Structure:**
```php
<footer class="app-footer">
  <div class="footer-content">
    <p>&copy; <?= date('Y') ?> Ecigdis Limited. All rights reserved.</p>
    <nav class="footer-nav">
      <a href="/privacy">Privacy</a>
      <a href="/terms">Terms</a>
      <a href="/support">Support</a>
    </nav>
  </div>
</footer>
```

**Variables:**
- `$footerLinks` - Array of footer navigation links
- `$copyrightYear` - Copyright year

---

## 👁️ PHP VIEWS

### 1. dashboard-feed.php
**Location:** `/views/dashboard-feed.php`
**Purpose:** Dashboard activity feed widget
**Size:** ~4KB
**Dependencies:** None

**Structure:**
```php
<div class="dashboard-feed">
  <div class="feed-header">
    <h3>Recent Activity</h3>
    <a href="/activity" class="feed-view-all">View All</a>
  </div>

  <div class="feed-content">
    <?php foreach ($feedItems as $item): ?>
      <div class="feed-item feed-item-<?= $item['type'] ?>">
        <div class="feed-icon">
          <i class="<?= $item['icon'] ?>"></i>
        </div>
        <div class="feed-details">
          <p class="feed-message"><?= $item['message'] ?></p>
          <span class="feed-time"><?= $item['time'] ?></span>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>
```

**Variables:**
- `$feedItems` - Array of feed items
  ```php
  [
    [
      'type' => 'sale',
      'icon' => 'bi bi-cart-check',
      'message' => 'New sale: $45.99 at Auckland CBD',
      'time' => '2 minutes ago'
    ],
    [
      'type' => 'alert',
      'icon' => 'bi bi-exclamation-triangle',
      'message' => 'Low stock: Vape Juice (Strawberry)',
      'time' => '15 minutes ago'
    ]
  ]
  ```

**Feed Item Types:**
- `sale` - New sale transaction
- `alert` - System alert
- `user` - User activity
- `system` - System event
- `transfer` - Inventory transfer
- `refund` - Refund processed

---

### 2. _feed-activity.php
**Location:** `/views/_feed-activity.php`
**Purpose:** Activity feed partial (reusable)
**Size:** ~2KB
**Dependencies:** None

**Structure:**
```php
<?php foreach ($activities as $activity): ?>
  <div class="activity-item activity-type-<?= $activity['type'] ?>">
    <div class="activity-icon">
      <i class="<?= $activity['icon'] ?>"></i>
    </div>
    <div class="activity-content">
      <span class="activity-user"><?= $activity['user'] ?></span>
      <span class="activity-action"><?= $activity['action'] ?></span>
      <span class="activity-target"><?= $activity['target'] ?></span>
      <span class="activity-time"><?= $activity['time'] ?></span>
    </div>
  </div>
<?php endforeach; ?>
```

**Usage:**
```php
// In any view/component
include 'views/_feed-activity.php';
```

---

## ⚙️ CONFIGURATION

### config.php
**Location:** `/config/config.php`
**Purpose:** Theme configuration and CDN paths
**Size:** ~3KB
**Dependencies:** None

**Contents:**
```php
<?php

return [
    // CDN Configuration
    'cdn' => [
        'base_url' => 'https://cdn.vapeshed.co.nz/vape-ultra',
        'version' => '1.0.0',
        'cache_bust' => true // Append version to URLs
    ],

    // CSS Load Order
    'css_files' => [
        'variables.css',
        'base.css',
        'layout.css',
        'components.css',
        'utilities.css',
        'animations.css',
        'silver-chrome-theme.css',
        'store-cards-award-winning.css',
        'award-winning-refinements.css',
        'premium-dashboard-header.css',
        'sidebar-award-winning.css'
    ],

    // JS Load Order
    'js_files' => [
        'core.js',
        'components.js',
        'utils.js',
        'api.js',
        'notifications.js',
        'charts.js'
    ],

    // Theme Options
    'themes' => [
        'default' => 'silver-chrome',
        'available' => ['silver-chrome', 'netflix-dark', 'oceanic-gradient']
    ],

    // Responsive Breakpoints
    'breakpoints' => [
        'xs' => '0px',
        'sm' => '576px',
        'md' => '768px',
        'lg' => '992px',
        'xl' => '1200px',
        'xxl' => '1400px'
    ],

    // Feature Flags
    'features' => [
        'right_sidebar' => true,
        'dark_mode_toggle' => true,
        'theme_switcher' => true
    ]
];
```

**Usage:**
```php
$config = require 'config/config.php';
$cdnBase = $config['cdn']['base_url'];
$cssFiles = $config['css_files'];
```

---

## 📚 DOCUMENTATION

### 1. README.md
**Location:** `/README.md`
**Size:** ~30KB
**Purpose:** Complete package documentation

**Sections:**
- Quick Start Guide
- Directory Structure
- Theme System Overview
- Award-Winning Components
- Alternative Themes
- Responsive Design
- Customization Guide
- Dependencies
- Terminology

---

### 2. INTEGRATION_GUIDE.md
**Location:** `/docs/INTEGRATION_GUIDE.md`
**Size:** ~12KB
**Purpose:** Developer integration guide

**Sections:**
- Basic Integration
- Advanced Usage
- Customization Examples
- Real-World Examples
- Troubleshooting
- Best Practices

---

### 3. JACK_COLLABORATION_10_ITERATIONS.md
**Location:** `/docs/JACK_COLLABORATION_10_ITERATIONS.md`
**Size:** ~18KB
**Purpose:** Documentation of Jack collaboration process

**Contents:**
- 10 iterations of design feedback
- Evolution of theme system
- Decision rationale
- User feedback incorporation

---

### 4. JACK_STORE_CARDS_10_ITERATIONS.md
**Location:** `/docs/JACK_STORE_CARDS_10_ITERATIONS.md`
**Size:** ~25KB
**Purpose:** Store card component development history

**Contents:**
- 10 iterations of store card design
- User testing feedback
- Performance metrics
- Final implementation

---

## 📦 DEPENDENCIES

### External CDN Dependencies

**Bootstrap 5.3.2**
```html
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
```

**Bootstrap Icons 1.11.1**
```html
<link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
```

**jQuery 3.7.1**
```html
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
```

**Chart.js 4.4.0**
```html
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
```

**Axios 1.6.0**
```html
<script src="https://cdn.jsdelivr.net/npm/axios@1.6.0/dist/axios.min.js"></script>
```

**Lodash 4.17.21**
```html
<script src="https://cdn.jsdelivr.net/npm/lodash@4.17.21/lodash.min.js"></script>
```

**Moment.js 2.29.4**
```html
<script src="https://cdn.jsdelivr.net/npm/moment@2.29.4/moment.min.js"></script>
```

**SweetAlert2 11.10.0**
```html
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.0/dist/sweetalert2.all.min.js"></script>
```

---

## 📊 FILE SIZE SUMMARY

| Category | Files | Total Size | Notes |
|----------|-------|------------|-------|
| **CSS** | 13 | ~126 KB | Minified: ~65 KB |
| **JavaScript** | 6 | ~110 KB | Minified: ~55 KB |
| **PHP** | 11 | ~46 KB | Server-side only |
| **Documentation** | 4 | ~85 KB | Markdown |
| **Config** | 1 | ~3 KB | PHP array |
| **Total** | **35** | **~370 KB** | **~120 KB gzipped** |

**Performance Notes:**
- All CSS/JS files can be concatenated and minified for production
- Recommended to use HTTP/2 for parallel loading
- Enable gzip/brotli compression (reduces size by ~70%)
- Consider lazy-loading non-critical CSS/JS

---

## 🔄 VERSION HISTORY

**v1.0.0** (2025-11-12)
- Initial consolidated package
- 13 CSS files
- 6 JS files
- 11 PHP files
- 4 documentation files
- Complete theme system
- Award-winning components
- Alternative themes (Netflix, Oceanic)

---

## 🚀 QUICK REFERENCE

### Load Order Summary

**CSS (in order):**
1. variables.css
2. base.css
3. layout.css
4. components.css
5. utilities.css
6. animations.css
7. silver-chrome-theme.css
8. store-cards-award-winning.css
9. award-winning-refinements.css
10. premium-dashboard-header.css
11. sidebar-award-winning.css
12. theme-netflix-dark.css (optional)
13. theme-oceanic-gradient.css (optional)

**JS (in order):**
1. core.js
2. components.js
3. utils.js
4. api.js
5. notifications.js
6. charts.js

### File Relationships

```
index-ultra.php
  └── Renderer.php
      ├── layouts/base.php
      │   ├── config.php (CSS/JS lists)
      │   └── layouts/main.php
      │       ├── components/header.php
      │       ├── components/sidebar.php
      │       ├── components/sidebar-right.php
      │       ├── components/footer.php
      │       └── views/dashboard-feed.php
      └── All CSS/JS files
```

---

## 🎯 USAGE PATTERNS

### Pattern 1: Standard Dashboard Page
```php
use Template\Renderer;

$renderer = new Renderer();
$renderer->render('main', [
    'title' => 'Dashboard',
    'pageContent' => $content,
    'navigation' => $navItems,
    'user' => $currentUser
]);
```

### Pattern 2: Auth Page (Minimal Layout)
```php
$renderer->render('minimal', [
    'title' => 'Login',
    'pageContent' => $loginForm
]);
```

### Pattern 3: Custom Theme
```php
$renderer->render('main', [
    'title' => 'Dashboard',
    'pageContent' => $content,
    'bodyClass' => 'theme-netflix'
]);
```

### Pattern 4: Right Sidebar Enabled
```php
$renderer->render('main', [
    'title' => 'Dashboard',
    'pageContent' => $content,
    'showRightSidebar' => true,
    'activities' => $activityFeed
]);
```

---

## ✅ CHECKLIST: Using This Package

- [ ] Copy entire `vape-ultra-complete/` folder to your project
- [ ] Update `config/config.php` with your CDN URLs
- [ ] Include `Renderer.php` in your application bootstrap
- [ ] Set up autoloading or require the Renderer class
- [ ] Copy example usage from INTEGRATION_GUIDE.md
- [ ] Test on desktop, tablet, mobile devices
- [ ] Verify all CSS/JS files load correctly
- [ ] Check browser console for errors
- [ ] Test alternative themes (Netflix, Oceanic)
- [ ] Review navigation structure in sidebar.php
- [ ] Customize as needed for your project

---

**End of File Manifest**
