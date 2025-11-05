<?php
/**
 * Modern CIS Template - Demo Page
 *
 * This page demonstrates the new modern template with:
 * - Thin sidebar (180px)
 * - Modern header with breadcrumbs
 * - Responsive design
 * - All features showcased
 */

// Page metadata
$pageTitle = 'Modern Template Demo | CIS Portal';
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/', 'icon' => 'fa-home'],
    ['label' => 'Templates', 'url' => '/modules/base/_templates/'],
    ['label' => 'Modern Demo', 'active' => true]
];

// Simulate user session (for demo)
if (!isset($_SESSION)) {
    session_start();
}
$_SESSION['user_name'] = $_SESSION['user_name'] ?? 'Demo User';
$_SESSION['user_role'] = $_SESSION['user_role'] ?? 'Administrator';

// Notification count (for demo)
$notificationCount = 5;

// Start output buffering
ob_start();
?>

<style>
.demo-section {
    background: white;
    border-radius: 8px;
    padding: 2rem;
    margin-bottom: 1.5rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.05);
}

.demo-section h2 {
    font-size: 20px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 1rem;
    padding-bottom: 0.75rem;
    border-bottom: 2px solid #e9ecef;
}

.demo-section h3 {
    font-size: 16px;
    font-weight: 600;
    color: #495057;
    margin: 1.5rem 0 0.75rem;
}

.demo-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 1rem;
    margin: 1rem 0;
}

.demo-card {
    background: #f8f9fa;
    border: 1px solid #e9ecef;
    border-radius: 6px;
    padding: 1.25rem;
    text-align: center;
}

.demo-card-icon {
    width: 48px;
    height: 48px;
    background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
    color: white;
    border-radius: 8px;
    display: flex;
    align-items: center;
    justify-content: center;
    margin: 0 auto 0.75rem;
    font-size: 20px;
}

.demo-card-title {
    font-size: 14px;
    font-weight: 600;
    color: #2c3e50;
    margin-bottom: 0.5rem;
}

.demo-card-desc {
    font-size: 12px;
    color: #6c757d;
    line-height: 1.5;
}

.feature-list {
    list-style: none;
    padding: 0;
}

.feature-list li {
    padding: 0.5rem 0;
    display: flex;
    align-items: center;
    gap: 0.75rem;
    font-size: 14px;
}

.feature-list li i {
    color: #28a745;
    width: 20px;
}

.code-block {
    background: #1a1d29;
    color: #e9ecef;
    padding: 1rem;
    border-radius: 6px;
    font-family: 'Courier New', monospace;
    font-size: 13px;
    overflow-x: auto;
    margin: 1rem 0;
}

.kbd {
    display: inline-block;
    padding: 0.25rem 0.5rem;
    background: #e9ecef;
    border: 1px solid #ced4da;
    border-radius: 4px;
    font-size: 12px;
    font-family: monospace;
    color: #495057;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.comparison-table {
    width: 100%;
    border-collapse: collapse;
    margin: 1rem 0;
}

.comparison-table th,
.comparison-table td {
    padding: 0.75rem;
    text-align: left;
    border-bottom: 1px solid #e9ecef;
    font-size: 13px;
}

.comparison-table th {
    background: #f8f9fa;
    font-weight: 600;
    color: #495057;
}

.comparison-table .old {
    color: #dc3545;
}

.comparison-table .new {
    color: #28a745;
    font-weight: 600;
}

.alert-demo {
    padding: 1rem 1.25rem;
    border-radius: 6px;
    margin: 1rem 0;
    font-size: 14px;
}

.alert-info {
    background: #d1ecf1;
    color: #0c5460;
    border-left: 4px solid #17a2b8;
}

.alert-success {
    background: #d4edda;
    color: #155724;
    border-left: 4px solid #28a745;
}

.alert-warning {
    background: #fff3cd;
    color: #856404;
    border-left: 4px solid #ffc107;
}
</style>

<!-- Hero Section -->
<div class="demo-section">
    <h2><i class="fas fa-rocket me-2"></i>Welcome to the Modern CIS Template</h2>
    <div class="alert-success">
        <strong>✨ New Design!</strong> Experience the improved CIS interface with a thinner sidebar (180px), modern header, and enhanced user experience.
    </div>
    <p style="color: #6c757d; margin: 1rem 0;">
        This demo showcases all the features and improvements of the new modern template.
        The template maintains all existing JavaScript libraries while providing a fresh, contemporary design.
    </p>
</div>

<!-- Key Features -->
<div class="demo-section">
    <h2><i class="fas fa-star me-2"></i>Key Features</h2>

    <div class="demo-grid">
        <div class="demo-card">
            <div class="demo-card-icon"><i class="fas fa-compress-alt"></i></div>
            <div class="demo-card-title">Thinner Sidebar</div>
            <div class="demo-card-desc">180px width (was 260px) - 31% thinner for more content space</div>
        </div>

        <div class="demo-card">
            <div class="demo-card-icon"><i class="fas fa-bars"></i></div>
            <div class="demo-card-title">Fixed Modern Header</div>
            <div class="demo-card-desc">Clean 56px header with integrated breadcrumbs and search</div>
        </div>

        <div class="demo-card">
            <div class="demo-card-icon"><i class="fas fa-keyboard"></i></div>
            <div class="demo-card-title">Keyboard Shortcuts</div>
            <div class="demo-card-desc">Press Ctrl+K (or Cmd+K) to focus global search instantly</div>
        </div>

        <div class="demo-card">
            <div class="demo-card-icon"><i class="fas fa-mobile-alt"></i></div>
            <div class="demo-card-title">Mobile Optimized</div>
            <div class="demo-card-desc">Responsive design with smooth overlay and touch-friendly targets</div>
        </div>

        <div class="demo-card">
            <div class="demo-card-icon"><i class="fas fa-save"></i></div>
            <div class="demo-card-title">Persistent State</div>
            <div class="demo-card-desc">Sidebar collapse state saved automatically using localStorage</div>
        </div>

        <div class="demo-card">
            <div class="demo-card-icon"><i class="fas fa-paint-brush"></i></div>
            <div class="demo-card-title">Modern Styling</div>
            <div class="demo-card-desc">Smooth animations, clean colors, professional appearance</div>
        </div>
    </div>
</div>

<!-- Sidebar Features -->
<div class="demo-section">
    <h2><i class="fas fa-sidebar me-2"></i>Sidebar Features</h2>

    <ul class="feature-list">
        <li>
            <i class="fas fa-check-circle"></i>
            <span><strong>180px width</strong> - Much thinner than before (was 260px)</span>
        </li>
        <li>
            <i class="fas fa-check-circle"></i>
            <span><strong>Collapsible to 60px</strong> - Icon-only mode with hover tooltips</span>
        </li>
        <li>
            <i class="fas fa-check-circle"></i>
            <span><strong>Dark theme</strong> - Modern #1a1d29 background color</span>
        </li>
        <li>
            <i class="fas fa-check-circle"></i>
            <span><strong>Section dividers</strong> - Organized into Main Menu, Reports, People, System</span>
        </li>
        <li>
            <i class="fas fa-check-circle"></i>
            <span><strong>Icon-first design</strong> - 20px consistent icon sizing</span>
        </li>
        <li>
            <i class="fas fa-check-circle"></i>
            <span><strong>Smooth animations</strong> - Cubic-bezier easing for professional feel</span>
        </li>
        <li>
            <i class="fas fa-check-circle"></i>
            <span><strong>Auto-close submenus</strong> - Only one submenu open at a time</span>
        </li>
        <li>
            <i class="fas fa-check-circle"></i>
            <span><strong>Active state tracking</strong> - Automatically highlights current page</span>
        </li>
    </ul>

    <div class="alert-info">
        <strong>💡 Tip:</strong> Click the hamburger icon <i class="fas fa-bars"></i> in the header to toggle the sidebar.
        On desktop, it collapses to 60px. On mobile, it slides off-screen.
    </div>
</div>

<!-- Header Features -->
<div class="demo-section">
    <h2><i class="fas fa-window-maximize me-2"></i>Header Features</h2>

    <ul class="feature-list">
        <li>
            <i class="fas fa-check-circle"></i>
            <span><strong>Fixed position</strong> - Always visible at top of page</span>
        </li>
        <li>
            <i class="fas fa-check-circle"></i>
            <span><strong>Integrated breadcrumbs</strong> - Navigation path shown in header</span>
        </li>
        <li>
            <i class="fas fa-check-circle"></i>
            <span><strong>Global search</strong> - Quick access with <span class="kbd">Ctrl</span> + <span class="kbd">K</span></span>
        </li>
        <li>
            <i class="fas fa-check-circle"></i>
            <span><strong>Notification badge</strong> - Red badge shows unread count</span>
        </li>
        <li>
            <i class="fas fa-check-circle"></i>
            <span><strong>User avatar</strong> - Gradient circle with initial</span>
        </li>
        <li>
            <i class="fas fa-check-circle"></i>
            <span><strong>Responsive</strong> - Adapts to mobile with hamburger menu</span>
        </li>
    </ul>
</div>

<!-- Keyboard Shortcuts -->
<div class="demo-section">
    <h2><i class="fas fa-keyboard me-2"></i>Keyboard Shortcuts</h2>

    <table class="comparison-table">
        <thead>
            <tr>
                <th>Shortcut</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td><span class="kbd">Ctrl</span> + <span class="kbd">K</span> (or <span class="kbd">Cmd</span> + <span class="kbd">K</span>)</td>
                <td>Focus global search input</td>
            </tr>
            <tr>
                <td><span class="kbd">Esc</span></td>
                <td>Close any open modal or dropdown</td>
            </tr>
            <tr>
                <td><span class="kbd">Tab</span></td>
                <td>Navigate between interactive elements</td>
            </tr>
        </tbody>
    </table>

    <div class="alert-info">
        <strong>🎯 Try it now:</strong> Press <span class="kbd">Ctrl</span> + <span class="kbd">K</span> to instantly focus the search bar!
    </div>
</div>

<!-- Comparison -->
<div class="demo-section">
    <h2><i class="fas fa-balance-scale me-2"></i>Old vs New Comparison</h2>

    <table class="comparison-table">
        <thead>
            <tr>
                <th>Feature</th>
                <th>Old Template</th>
                <th>New Template</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>Sidebar Width</td>
                <td class="old">260px</td>
                <td class="new">180px (31% thinner)</td>
            </tr>
            <tr>
                <td>Header Style</td>
                <td class="old">Basic</td>
                <td class="new">Modern with breadcrumbs</td>
            </tr>
            <tr>
                <td>Collapse State</td>
                <td class="old">Manual</td>
                <td class="new">Auto-saved (localStorage)</td>
            </tr>
            <tr>
                <td>Mobile UX</td>
                <td class="old">Basic</td>
                <td class="new">Smooth overlay + backdrop</td>
            </tr>
            <tr>
                <td>Icon Size</td>
                <td class="old">Varies</td>
                <td class="new">Consistent 20px</td>
            </tr>
            <tr>
                <td>Animations</td>
                <td class="old">Basic</td>
                <td class="new">Cubic-bezier smooth</td>
            </tr>
            <tr>
                <td>Search</td>
                <td class="old">Separate</td>
                <td class="new">Integrated + Ctrl+K</td>
            </tr>
            <tr>
                <td>Section Dividers</td>
                <td class="old">None</td>
                <td class="new">Yes (4 sections)</td>
            </tr>
            <tr>
                <td>Tooltips</td>
                <td class="old">None</td>
                <td class="new">On collapsed state</td>
            </tr>
        </tbody>
    </table>
</div>

<!-- Implementation -->
<div class="demo-section">
    <h2><i class="fas fa-code me-2"></i>Implementation</h2>

    <h3>Basic Usage</h3>
    <div class="code-block">
&lt;?php
// Page setup
$pageTitle = 'Your Page Title';
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/'],
    ['label' => 'Section', 'url' => '/section/'],
    ['label' => 'Current Page', 'active' => true]
];

// Start output buffering
ob_start();
?&gt;

&lt;!-- Your page content here --&gt;
&lt;div class="container-fluid"&gt;
    &lt;h1&gt;Welcome!&lt;/h1&gt;
&lt;/div&gt;

&lt;?php
$content = ob_get_clean();
require_once __DIR__ . '/../../base/_templates/layouts/dashboard-modern.php';
?&gt;
    </div>

    <div class="alert-success">
        <strong>✅ No code changes required!</strong> The new template uses the same variables as the old one.
    </div>
</div>

<!-- JavaScript Libraries -->
<div class="demo-section">
    <h2><i class="fas fa-book me-2"></i>Included JavaScript Libraries</h2>

    <div class="alert-warning">
        <strong>📦 All existing libraries preserved!</strong> The new template includes all the same JavaScript libraries as before.
    </div>

    <div class="row">
        <div class="col-md-6">
            <h3>Core Libraries</h3>
            <ul class="feature-list">
                <li><i class="fas fa-check-circle"></i> jQuery 3.7.1</li>
                <li><i class="fas fa-check-circle"></i> Bootstrap 5.3.2</li>
                <li><i class="fas fa-check-circle"></i> Font Awesome 6.7.1</li>
            </ul>

            <h3>Data & Tables</h3>
            <ul class="feature-list">
                <li><i class="fas fa-check-circle"></i> DataTables 1.13.7</li>
                <li><i class="fas fa-check-circle"></i> DataTables Buttons</li>
                <li><i class="fas fa-check-circle"></i> DataTables Responsive</li>
            </ul>
        </div>

        <div class="col-md-6">
            <h3>Forms & Inputs</h3>
            <ul class="feature-list">
                <li><i class="fas fa-check-circle"></i> Select2 4.1.0</li>
                <li><i class="fas fa-check-circle"></i> Flatpickr 4.6.13</li>
            </ul>

            <h3>UI & Utilities</h3>
            <ul class="feature-list">
                <li><i class="fas fa-check-circle"></i> Chart.js 4.4.0</li>
                <li><i class="fas fa-check-circle"></i> SweetAlert2 11.10.1</li>
                <li><i class="fas fa-check-circle"></i> Toastr 2.1.4</li>
                <li><i class="fas fa-check-circle"></i> Axios 1.6.2</li>
                <li><i class="fas fa-check-circle"></i> Moment.js 2.29.4</li>
                <li><i class="fas fa-check-circle"></i> Lodash 4.17.21</li>
            </ul>
        </div>
    </div>
</div>

<!-- Browser Support -->
<div class="demo-section">
    <h2><i class="fas fa-browser me-2"></i>Browser Support</h2>

    <div class="demo-grid">
        <div class="demo-card">
            <div class="demo-card-icon" style="background: linear-gradient(135deg, #4285F4 0%, #34A853 100%);"><i class="fab fa-chrome"></i></div>
            <div class="demo-card-title">Chrome 90+</div>
            <div class="demo-card-desc">Full support</div>
        </div>

        <div class="demo-card">
            <div class="demo-card-icon" style="background: linear-gradient(135deg, #FF9500 0%, #FF5E00 100%);"><i class="fab fa-firefox"></i></div>
            <div class="demo-card-title">Firefox 88+</div>
            <div class="demo-card-desc">Full support</div>
        </div>

        <div class="demo-card">
            <div class="demo-card-icon" style="background: linear-gradient(135deg, #006CFF 0%, #007AFF 100%);"><i class="fab fa-safari"></i></div>
            <div class="demo-card-title">Safari 14+</div>
            <div class="demo-card-desc">Full support</div>
        </div>

        <div class="demo-card">
            <div class="demo-card-icon" style="background: linear-gradient(135deg, #0078D7 0%, #5C2D91 100%);"><i class="fab fa-edge"></i></div>
            <div class="demo-card-title">Edge 90+</div>
            <div class="demo-card-desc">Full support</div>
        </div>
    </div>
</div>

<!-- Documentation -->
<div class="demo-section">
    <h2><i class="fas fa-book-open me-2"></i>Documentation</h2>

    <p style="color: #6c757d; margin-bottom: 1rem;">
        Complete documentation is available in the template guide:
    </p>

    <a href="MODERN_TEMPLATE_GUIDE.md" target="_blank" class="btn btn-primary">
        <i class="fas fa-book me-2"></i>View Full Documentation
    </a>
</div>

<!-- Footer -->
<div class="demo-section">
    <h2><i class="fas fa-info-circle me-2"></i>Summary</h2>

    <div class="alert-success">
        <strong>🎉 Ready to use!</strong> The modern CIS template is production-ready and provides:
        <ul style="margin: 0.75rem 0 0 1.5rem;">
            <li>30% thinner sidebar (180px vs 260px)</li>
            <li>Modern fixed header with integrated breadcrumbs</li>
            <li>Better UX with keyboard shortcuts and tooltips</li>
            <li>Smooth animations with cubic-bezier easing</li>
            <li>Mobile-optimized with touch-friendly overlay</li>
            <li>100% backward compatible - no code changes required</li>
            <li>All JS libraries preserved - same stack, better design</li>
        </ul>
    </div>

    <p style="color: #6c757d; margin-top: 1rem;">
        <strong>Migration:</strong> Simply change the template path from <code>dashboard.php</code> to <code>dashboard-modern.php</code> and enjoy the new design!
    </p>
</div>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/dashboard-modern.php';
?>
