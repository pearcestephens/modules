# 🚀 INTEGRATION GUIDE
**VapeUltra Theme - Complete Package**

---

## 📖 TABLE OF CONTENTS

1. [Basic Integration](#basic-integration)
2. [Advanced Usage](#advanced-usage)
3. [Customization](#customization)
4. [Alternative Themes](#alternative-themes)
5. [Real-World Examples](#real-world-examples)
6. [Troubleshooting](#troubleshooting)

---

## 🎯 BASIC INTEGRATION

### **Step 1: Include the Renderer**

```php
<?php
require_once __DIR__ . '/modules/base/Template/Renderer.php';
use App\Template\Renderer;
```

### **Step 2: Prepare Your Content**

```php
$content = '
<div class="container-fluid">
    <h1>Welcome to My Module</h1>
    <p>Your content here...</p>
</div>
';
```

### **Step 3: Render**

```php
$renderer = new Renderer();
$renderer->render($content, [
    'title' => 'My Module - CIS Ultra'
]);
```

**That's it!** Your page is now using the full VapeUltra theme.

---

## 🔧 ADVANCED USAGE

### **Full Options Array:**

```php
$renderer->render($content, [
    // Page metadata
    'title' => 'Dashboard - CIS Ultra',
    'class' => 'page-dashboard page-my-module',

    // Layout selection
    'layout' => 'main',  // 'main' or 'minimal'

    // Custom CSS
    'styles' => [
        '/assets/css/my-module.css',
        '/assets/css/my-overrides.css'
    ],

    // Custom JS
    'scripts' => [
        '/assets/js/my-module.js'
    ],

    // Inline JavaScript (executed after page load)
    'inline_scripts' => "
        console.log('My module loaded');
        MyModule.init();
    ",

    // Sidebar configuration
    'hide_right_sidebar' => false,
    'right_sidebar' => null,  // or custom HTML

    // Navigation items
    'nav_items' => [
        'main' => [
            'title' => 'Main',
            'items' => [
                [
                    'icon' => 'grid-fill',
                    'label' => 'Dashboard',
                    'href' => '/',
                    'badge' => 5  // or null
                ],
                // More items...
            ]
        ],
        // More sections...
    ],

    // Custom widgets (for right sidebar)
    'widgets' => null  // or custom HTML
]);
```

---

## 🎨 CUSTOMIZATION EXAMPLES

### **Example 1: Custom Sidebar Navigation**

```php
'nav_items' => [
    'inventory' => [
        'title' => 'Inventory',
        'items' => [
            [
                'icon' => 'box-seam',
                'label' => 'Products',
                'href' => '/products',
                'badge' => null
            ],
            [
                'icon' => 'arrow-left-right',
                'label' => 'Transfers',
                'href' => '/transfers',
                'badge' => 12  // Pending transfers
            ],
            [
                'icon' => 'clipboard-check',
                'label' => 'Purchase Orders',
                'href' => '/purchase-orders',
                'badge' => 3  // Pending POs
            ],
        ]
    ],
    'reports' => [
        'title' => 'Reports',
        'items' => [
            [
                'icon' => 'graph-up',
                'label' => 'Sales',
                'href' => '/reports/sales',
                'badge' => null
            ],
            [
                'icon' => 'chart-bar',
                'label' => 'Analytics',
                'href' => '/reports/analytics',
                'badge' => null
            ],
        ]
    ]
]
```

### **Example 2: Custom Right Sidebar**

```php
// Generate custom sidebar content
function myCustomSidebar() {
    ob_start();
    ?>
    <div class="widget">
        <div class="widget-title">Quick Stats</div>
        <div class="quick-stat">
            <span class="quick-stat-label">Today's Sales</span>
            <span class="quick-stat-value">$12,450</span>
        </div>
        <div class="quick-stat">
            <span class="quick-stat-label">Pending Orders</span>
            <span class="quick-stat-value">23</span>
        </div>
    </div>

    <div class="widget">
        <div class="widget-title">Quick Actions</div>
        <a href="/create-order" class="nav-item">
            <i class="bi bi-plus-circle nav-icon"></i>
            <span>New Order</span>
        </a>
        <a href="/stock-check" class="nav-item">
            <i class="bi bi-search nav-icon"></i>
            <span>Stock Check</span>
        </a>
    </div>
    <?php
    return ob_get_clean();
}

// Use in render
$renderer->render($content, [
    'title' => 'Orders Module',
    'right_sidebar' => myCustomSidebar()
]);
```

### **Example 3: Minimal Layout (Auth Pages)**

```php
// For login, register, forgot password pages
$loginForm = '
<div class="auth-container">
    <div class="auth-card">
        <h2>Login to CIS</h2>
        <form method="POST">
            <input type="email" class="form-control" placeholder="Email">
            <input type="password" class="form-control" placeholder="Password">
            <button type="submit" class="btn btn-primary">Login</button>
        </form>
    </div>
</div>
';

$renderer->render($loginForm, [
    'title' => 'Login - CIS',
    'layout' => 'minimal',  // No sidebars, minimal header
    'class' => 'page-auth page-login'
]);
```

---

## 🎭 ALTERNATIVE THEMES

### **Method 1: Body Class (Recommended)**

```php
// Netflix dark theme
$renderer->render($content, [
    'title' => 'Dashboard',
    'class' => 'page-dashboard theme-netflix'
]);

// Oceanic gradient theme
$renderer->render($content, [
    'title' => 'Dashboard',
    'class' => 'page-dashboard theme-oceanic'
]);
```

### **Method 2: Load Theme CSS**

```php
$renderer->render($content, [
    'title' => 'Dashboard',
    'styles' => [
        '/modules/base/templates/vape-ultra-complete/css/theme-netflix-dark.css'
    ]
]);
```

### **Method 3: User Preference (Session-Based)**

```php
// Check user's theme preference
$userTheme = $_SESSION['theme_preference'] ?? 'default';

$themeClass = match($userTheme) {
    'netflix' => 'theme-netflix',
    'oceanic' => 'theme-oceanic',
    default => ''
};

$renderer->render($content, [
    'title' => 'Dashboard',
    'class' => "page-dashboard {$themeClass}"
]);
```

---

## 📚 REAL-WORLD EXAMPLES

### **Example: index-ultra.php (Main Dashboard)**

```php
<?php
// Load configuration and data
include("assets/functions/config.php");
require_once __DIR__ . '/autoload.php';
use App\Template\Renderer;

// Get user ID
$userID = $_SESSION["userID"] ?? 0;

// Get dashboard data
$requests = getPendingLeaveRequests();
$outlets = getAllOutletsFromDB();
$wikiAnswer = getRandomWiki();

// Build dashboard content
ob_start();
?>

<!-- Your dashboard HTML here -->
<div class="premium-dashboard-header">
    <h1>Welcome back, <?= $_SESSION['first_name'] ?>!</h1>
</div>

<div class="wiki-premium-card">
    <h5><?= $wikiAnswer->name ?></h5>
    <p><?= $wikiAnswer->text ?></p>
</div>

<!-- Store cards grid -->
<div class="row">
    <?php foreach($outlets as $outlet): ?>
    <div class="col-md-4">
        <div class="store-card">
            <h3><?= $outlet->name ?></h3>
            <!-- Store details -->
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php
$moduleContent = ob_get_clean();

// Generate custom right sidebar
function generateRightSidebar($outlets, $requests) {
    ob_start();
    ?>
    <div class="widget">
        <div class="widget-title">System Status</div>
        <div class="quick-stat">
            <span class="quick-stat-label">Total Outlets</span>
            <span class="quick-stat-value"><?= count($outlets) ?></span>
        </div>
        <div class="quick-stat">
            <span class="quick-stat-label">Pending Requests</span>
            <span class="quick-stat-value"><?= count($requests) ?></span>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

// Render with VapeUltra theme
$renderer = new Renderer();
$renderer->render($moduleContent, [
    'title' => 'CIS Ultra Dashboard',
    'class' => 'page-dashboard page-ultra',
    'layout' => 'main',
    'styles' => [
        '/assets/css/silver-chrome-theme.css',
        '/assets/css/store-cards-award-winning.css',
        '/assets/css/premium-dashboard-header.css'
    ],
    'scripts' => [
        '/assets/js/dashboard.js'
    ],
    'nav_items' => [
        'main' => [
            'title' => 'Main',
            'items' => [
                ['icon' => 'grid-fill', 'label' => 'Dashboard', 'href' => '/', 'badge' => null],
                ['icon' => 'activity', 'label' => 'Activity', 'href' => '/activity', 'badge' => count($requests)],
            ]
        ],
        'operations' => [
            'title' => 'Operations',
            'items' => [
                ['icon' => 'arrow-left-right', 'label' => 'Transfers', 'href' => '/transfers', 'badge' => null],
                ['icon' => 'box-seam', 'label' => 'Products', 'href' => '/products', 'badge' => null],
            ]
        ]
    ],
    'right_sidebar' => generateRightSidebar($outlets, $requests)
]);
```

---

## 🐛 TROUBLESHOOTING

### **Problem: Styles Not Loading**

**Solution 1**: Check file paths
```php
// Use absolute paths from web root
'styles' => [
    '/modules/base/templates/vape-ultra-complete/css/silver-chrome-theme.css'
]
// NOT relative paths like: '../css/theme.css'
```

**Solution 2**: Check config.php paths
```php
// In config/config.php, ensure paths start with /
'css' => [
    '/modules/base/templates/vape-ultra/assets/css/variables.css',
    // NOT './assets/css/variables.css'
]
```

### **Problem: Sidebar Not Showing**

**Solution**: Check layout setting
```php
// Make sure you're using 'main' layout, not 'minimal'
'layout' => 'main'
```

### **Problem: Navigation Not Active**

**Solution**: Check href matching
```php
// The active state is detected by URL matching
// Make sure your href matches the current URL
'items' => [
    ['icon' => 'grid', 'label' => 'Dashboard', 'href' => '/']
    // Will be active when current URL is exactly '/'
]
```

### **Problem: Right Sidebar Empty**

**Solution**: Provide custom content or don't hide it
```php
// Option 1: Provide custom content
'right_sidebar' => '<div class="widget">Content</div>'

// Option 2: Don't hide default sidebar
'hide_right_sidebar' => false

// Option 3: Use default (don't specify)
// ...no right_sidebar or hide_right_sidebar option
```

### **Problem: Theme Not Applying**

**Solution**: Check body class and CSS file
```php
// For alternative themes, BOTH are needed:
'class' => 'page-dashboard theme-netflix',  // Body class
'styles' => [
    '/modules/base/templates/vape-ultra-complete/css/theme-netflix-dark.css'  // CSS file
]
```

---

## 🎯 BEST PRACTICES

### **1. Always Buffer Your Content**

```php
// Good ✅
ob_start();
?>
<div>Your HTML</div>
<?php
$content = ob_get_clean();
$renderer->render($content, []);

// Bad ❌
$content = "<div>Your HTML</div>";  // Hard to maintain
```

### **2. Separate Content Generation**

```php
// Good ✅
function generateDashboardContent() {
    // Content generation logic
    return $html;
}

$content = generateDashboardContent();
$renderer->render($content, []);

// Bad ❌
$renderer->render(generateDashboardContent(), []);  // Harder to debug
```

### **3. Use Functions for Reusable Parts**

```php
// Good ✅
function generateSystemStatus($data) {
    // Generate widget HTML
    return $html;
}

'right_sidebar' => generateSystemStatus($myData)

// Bad ❌
'right_sidebar' => '<div>...</div>'  // Inline HTML in config
```

### **4. Document Your Nav Items**

```php
// Good ✅
'nav_items' => [
    'inventory' => [  // Clear section name
        'title' => 'Inventory Management',  // Clear title
        'items' => [
            [
                'icon' => 'box-seam',  // Bootstrap Icons name
                'label' => 'Products',  // Clear label
                'href' => '/products',  // Full path
                'badge' => $productCount  // Dynamic
            ],
        ]
    ]
]
```

---

## 📞 NEXT STEPS

1. ✅ Copy this theme folder to your modules/base/templates/
2. ✅ Update your pages to use Renderer.php
3. ✅ Customize colors in css/variables.css
4. ✅ Add your custom CSS and JS
5. ✅ Test on all devices
6. ✅ Deploy to production

---

**Made with ❤️ for easy integration**
