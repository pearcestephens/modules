<?php
/**
 * Base Template Showcase & Live Preview
 *
 * Interactive demonstration of all CIS base template layouts
 * Allows you to see each layout in action before implementing
 *
 * @package CIS\Modules\AdminUI
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/base/bootstrap.php';

// Get requested layout (default: dashboard)
$selectedLayout = $_GET['layout'] ?? 'dashboard';
$validLayouts = ['dashboard', 'table', 'card', 'split', 'blank'];
if (!in_array($selectedLayout, $validLayouts)) {
    $selectedLayout = 'dashboard';
}

// Define base paths
$BASE_TEMPLATES = $_SERVER['DOCUMENT_ROOT'] . '/modules/base/_templates';
$BASE_LAYOUTS = $BASE_TEMPLATES . '/layouts';
$BASE_COMPONENTS = $BASE_TEMPLATES . '/components';

// If viewing a specific layout demo, render it using admin-ui styling
if (isset($_GET['demo']) && in_array($_GET['demo'], $validLayouts)) {
    $demoLayout = $_GET['demo'];

    // Load admin-ui components paths
    define('ADMIN_UI_COMPONENTS_PATH', $_SERVER['DOCUMENT_ROOT'] . '/modules/admin-ui/_templates/components');

    // Function to render demo with consistent template
    function renderDemo($pageParent, $pageTitle, $content) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?= $pageTitle ?> - CIS</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <link rel="stylesheet" href="/modules/admin-ui/_templates/css/theme-generated.css">
            <link rel="stylesheet" href="/modules/admin-ui/_templates/css/theme-custom.css">
            <style>
                body { background: #f5f7fa !important; }
                .page-header { margin-bottom: 2rem; }
                .page-title { font-size: 2rem; font-weight: 700; color: #111827; margin-bottom: 0.5rem; }
                .page-subtitle { font-size: 1rem; color: #6b7280; }
            </style>
        </head>
        <body>
            <?php
            include ADMIN_UI_COMPONENTS_PATH . '/header-v2.php';
            include ADMIN_UI_COMPONENTS_PATH . '/sidebar.php';
            ?>
            <div class="dashboard-main">
                <div class="container-fluid">
                    <?= $content ?>
                </div>
            </div>
            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
        </body>
        </html>
        <?php
        exit;
    }

    // Configure page variables based on layout type
    if ($demoLayout === 'dashboard') {
        $pageParent = 'Template Showcase';
        $pageTitle = 'Dashboard Demo';

        ob_start();
        ?>
        <div class="page-header mb-4">
            <h1 class="page-title">
                <i class="fas fa-th-large text-primary"></i>
                Dashboard Layout Demo
            </h1>
            <p class="page-subtitle text-muted">
                Full page layout with sidebar, header, breadcrumbs, and footer
            </p>
        </div>

        <!-- Stats Row -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title text-muted">Total Users</h5>
                        <h2 class="mb-0">1,284</h2>
                        <small class="text-success"><i class="fas fa-arrow-up"></i> 12% increase</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title text-muted">Revenue</h5>
                        <h2 class="mb-0">$45,231</h2>
                        <small class="text-success"><i class="fas fa-arrow-up"></i> 8% increase</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title text-muted">Orders</h5>
                        <h2 class="mb-0">892</h2>
                        <small class="text-warning"><i class="fas fa-minus"></i> No change</small>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title text-muted">Pending</h5>
                        <h2 class="mb-0">23</h2>
                        <small class="text-danger"><i class="fas fa-arrow-down"></i> 3% decrease</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Main Content -->
        <div class="row">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Recent Activity</h5>
                    </div>
                    <div class="card-body">
                        <p>This is the <strong>dashboard layout</strong> - perfect for main application pages.</p>
                        <ul>
                            <li>✅ Full sidebar navigation</li>
                            <li>✅ Header with search and notifications</li>
                            <li>✅ Breadcrumbs for navigation</li>
                            <li>✅ Footer with company info</li>
                            <li>✅ Responsive (sidebar collapses on mobile)</li>
                        </ul>
                        <a href="/modules/admin-ui/template-showcase.php" class="btn btn-primary">
                            <i class="fas fa-arrow-left"></i> Back to Showcase
                        </a>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="list-group">
                            <a href="#" class="list-group-item list-group-item-action">
                                <i class="fas fa-plus text-primary"></i> Create New
                            </a>
                            <a href="#" class="list-group-item list-group-item-action">
                                <i class="fas fa-download text-success"></i> Export Data
                            </a>
                            <a href="#" class="list-group-item list-group-item-action">
                                <i class="fas fa-cog text-secondary"></i> Settings
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        renderDemo($pageParent, $pageTitle, $content);

    } elseif ($demoLayout === 'table') {
        $pageParent = 'Template Showcase';
        $pageTitle = 'Table Demo';

        ob_start();
        ?>
        <div class="page-header mb-4">
            <h1 class="page-title">
                <i class="fas fa-table text-success"></i>
                Table Layout Demo
            </h1>
            <p class="page-subtitle text-muted">
                Optimized layout for displaying data tables
            </p>
        </div>

        <div class="card">
            <div class="card-body">
                <table class="table table-striped table-bordered">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php for ($i = 1; $i <= 20; $i++): ?>
                        <tr>
                            <td><?= $i ?></td>
                            <td>User <?= $i ?></td>
                            <td>user<?= $i ?>@example.com</td>
                            <td><span class="badge badge-success">Active</span></td>
                            <td><?= date('d M Y', strtotime("-$i days")) ?></td>
                            <td>
                                <button class="btn btn-sm btn-primary"><i class="fas fa-eye"></i></button>
                                <button class="btn btn-sm btn-warning"><i class="fas fa-edit"></i></button>
                                <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                            </td>
                        </tr>
                        <?php endfor; ?>
                    </tbody>
                </table>

                <div class="alert alert-info mt-4">
                    <h5><i class="fas fa-info-circle"></i> Table Layout Features</h5>
                    <ul class="mb-0">
                        <li>✅ Responsive table with Bootstrap styling</li>
                        <li>✅ Action buttons for each row</li>
                        <li>✅ Status badges</li>
                        <li>✅ Consistent header and sidebar</li>
                    </ul>
                    <a href="/modules/admin-ui/template-showcase.php" class="btn btn-primary mt-3">
                        <i class="fas fa-arrow-left"></i> Back to Showcase
                    </a>
                </div>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        renderDemo($pageParent, $pageTitle, $content);

    } elseif ($demoLayout === 'card') {
        // Card layout is for standalone pages (login, forms) - NO header/sidebar
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Login - CIS</title>
            <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
            <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
            <style>
                body {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .login-card {
                    max-width: 450px;
                    width: 100%;
                }
                .card {
                    border: none;
                    border-radius: 1rem;
                    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
                }
                .card-header {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    border-radius: 1rem 1rem 0 0 !important;
                    padding: 2rem;
                    text-align: center;
                }
                .card-body {
                    padding: 2rem;
                }
                .form-control:focus {
                    border-color: #667eea;
                    box-shadow: 0 0 0 0.2rem rgba(102, 126, 234, 0.25);
                }
                .btn-primary {
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    border: none;
                    padding: 0.75rem;
                    font-weight: 600;
                }
                .btn-primary:hover {
                    background: linear-gradient(135deg, #5568d3 0%, #6a3f8f 100%);
                }
                .demo-note {
                    background: rgba(255, 255, 255, 0.95);
                    border-radius: 0.5rem;
                    padding: 1.5rem;
                    margin-top: 2rem;
                    box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
                }
            </style>
        </head>
        <body>
            <div class="login-card">
                <div class="card">
                    <div class="card-header text-white">
                        <i class="fas fa-shield-alt fa-3x mb-3"></i>
                        <h3 class="mb-0">CIS Login</h3>
                    </div>
                    <div class="card-body">
                        <form>
                            <div class="form-group">
                                <label for="email"><i class="fas fa-envelope mr-2"></i>Email Address</label>
                                <input type="email" class="form-control form-control-lg" id="email" placeholder="Enter your email">
                            </div>
                            <div class="form-group">
                                <label for="password"><i class="fas fa-lock mr-2"></i>Password</label>
                                <input type="password" class="form-control form-control-lg" id="password" placeholder="Enter your password">
                            </div>
                            <div class="custom-control custom-checkbox mb-3">
                                <input type="checkbox" class="custom-control-input" id="remember">
                                <label class="custom-control-label" for="remember">Remember me</label>
                            </div>
                            <button type="submit" class="btn btn-primary btn-lg btn-block mb-3">
                                <i class="fas fa-sign-in-alt mr-2"></i>Sign In
                            </button>
                            <div class="text-center">
                                <a href="#" class="text-muted">Forgot password?</a>
                            </div>
                        </form>
                    </div>
                    <div class="card-footer text-center bg-light">
                        <small class="text-muted">Don't have an account? <a href="#">Sign up</a></small>
                    </div>
                </div>

                <div class="demo-note">
                    <h5><i class="fas fa-info-circle text-primary"></i> Card Layout Features</h5>
                    <ul class="mb-0">
                        <li>✅ <strong>NO</strong> header or sidebar - standalone page</li>
                        <li>✅ Centered card with gradient background</li>
                        <li>✅ Perfect for login, registration, simple forms</li>
                        <li>✅ Fully responsive design</li>
                        <li>✅ Clean, minimal interface</li>
                    </ul>
                    <a href="/modules/admin-ui/template-showcase.php" class="btn btn-primary btn-sm mt-3">
                        <i class="fas fa-arrow-left"></i> Back to Showcase
                    </a>
                </div>
            </div>

            <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
            <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>
        </body>
        </html>
        <?php
        exit;

    } elseif ($demoLayout === 'split') {
        $pageParent = 'Template Showcase';
        $pageTitle = 'Split Demo';

        ob_start();
        ?>
        <div class="page-header mb-4">
            <h1 class="page-title">
                <i class="fas fa-columns text-warning"></i>
                Split Layout Demo
            </h1>
            <p class="page-subtitle text-muted">
                Two-panel layout with resizable divider - perfect for master-detail views
            </p>
        </div>

        <div class="row">
            <div class="col-md-5">
                <div class="card h-100">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Master Panel</h5>
                    </div>
                    <div class="card-body">
                        <p>This is the left panel (master view)</p>
                        <ul class="list-group">
                            <li class="list-group-item active">Item 1</li>
                            <li class="list-group-item">Item 2</li>
                            <li class="list-group-item">Item 3</li>
                            <li class="list-group-item">Item 4</li>
                            <li class="list-group-item">Item 5</li>
                        </ul>
                    </div>
                </div>
            </div>
            <div class="col-md-7">
                <div class="card h-100">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">Detail Panel</h5>
                    </div>
                    <div class="card-body">
                        <h3>Detail View</h3>
                        <p>This is the right panel (detail view)</p>
                        <div class="alert alert-info">
                            <h5><i class="fas fa-info-circle"></i> Split Layout Features</h5>
                            <ul class="mb-0">
                                <li>✅ Two-panel layout</li>
                                <li>✅ Responsive columns</li>
                                <li>✅ Stacks vertically on mobile</li>
                                <li>✅ Perfect for master-detail views</li>
                                <li>✅ Consistent template wrapper</li>
                            </ul>
                            <a href="/modules/admin-ui/template-showcase.php" class="btn btn-primary mt-3">
                                <i class="fas fa-arrow-left"></i> Back to Showcase
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        renderDemo($pageParent, $pageTitle, $content);

    } elseif ($demoLayout === 'blank') {
        $pageParent = 'Template Showcase';
        $pageTitle = 'Blank Demo';

        ob_start();
        ?>
        <div class="page-header mb-4">
            <h1 class="page-title">
                <i class="fas fa-file text-secondary"></i>
                Blank Layout Demo
            </h1>
            <p class="page-subtitle text-muted">
                Minimal wrapper with full template - still gets header, sidebar, footer
            </p>
        </div>

        <div style="min-height: 60vh; display: flex; align-items: center; justify-content: center; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; text-align: center; padding: 2rem; border-radius: 0.5rem;">
            <div>
                <h1 class="display-3 mb-4">Full Control Layout</h1>
                <p class="lead mb-4">Even the "blank" demo has the consistent template wrapper!</p>
                <div class="alert alert-light text-dark" style="max-width: 600px; margin: 0 auto;">
                    <h5><i class="fas fa-info-circle"></i> Blank Layout Features</h5>
                    <ul class="text-left mb-0">
                        <li>✅ Still has header, sidebar, footer wrapper</li>
                        <li>✅ Full control over content design</li>
                        <li>✅ Can create custom layouts</li>
                        <li>✅ Perfect for custom pages, reports</li>
                        <li>✅ Consistent navigation and branding</li>
                    </ul>
                    <a href="/modules/admin-ui/template-showcase.php" class="btn btn-primary mt-3">
                        <i class="fas fa-arrow-left"></i> Back to Showcase
                    </a>
                </div>
            </div>
        </div>
        <?php
        $content = ob_get_clean();
        renderDemo($pageParent, $pageTitle, $content);
    }
}

// Main showcase page
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CIS Base Template Showcase</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="/modules/admin-ui/_templates/css/theme-generated.css">
    <link rel="stylesheet" href="/modules/admin-ui/_templates/css/theme-custom.css">

    <style>
        body { background: #f5f7fa; }
        .showcase-header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 3rem 0; margin-bottom: 3rem; box-shadow: 0 4px 6px rgba(0,0,0,0.1); }
        .layout-card { background: white; border-radius: 12px; padding: 2rem; margin-bottom: 2rem; box-shadow: 0 4px 6px rgba(0,0,0,0.07); transition: all 0.3s; }
        .layout-card:hover { box-shadow: 0 8px 16px rgba(0,0,0,0.12); transform: translateY(-4px); }
        .layout-preview { background: #f9fafb; border: 2px solid #e5e7eb; border-radius: 8px; padding: 1rem; margin-bottom: 1rem; font-family: 'Courier New', monospace; font-size: 0.875rem; min-height: 300px; display: flex; align-items: center; justify-content: center; }
        .layout-features { list-style: none; padding: 0; margin: 1rem 0; }
        .layout-features li { padding: 0.5rem 0; border-bottom: 1px solid #f3f4f6; }
        .layout-features li:last-child { border-bottom: none; }
        .layout-features li i { color: #10b981; margin-right: 0.5rem; }
        .btn-demo { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border: none; }
        .btn-demo:hover { background: linear-gradient(135deg, #764ba2 0%, #667eea 100%); color: white; }
        .quick-links { background: white; border-radius: 12px; padding: 1.5rem; box-shadow: 0 4px 6px rgba(0,0,0,0.07); }
        .quick-links a { display: block; padding: 0.75rem 1rem; margin-bottom: 0.5rem; border-radius: 6px; background: #f9fafb; transition: all 0.2s; text-decoration: none; }
        .quick-links a:hover { background: #667eea; color: white; transform: translateX(4px); }
    </style>
</head>
<body>

    <div class="showcase-header">
        <div class="container">
            <h1 class="display-4 mb-3"><i class="fas fa-layer-group"></i> CIS Base Template Showcase</h1>
            <p class="lead mb-4">Interactive demonstration of all base template layouts</p>
            <div>
                <a href="/modules/admin-ui/" class="btn btn-light btn-lg mr-2">
                    <i class="fas fa-palette"></i> Component Library
                </a>
                <a href="/modules/admin-ui/theme-builder.php" class="btn btn-outline-light btn-lg">
                    <i class="fas fa-paint-brush"></i> Theme Builder
                </a>
            </div>
        </div>
    </div>

    <div class="container">

        <div class="row">

            <!-- Left Column - Layout Showcases -->
            <div class="col-lg-8">

                <!-- Dashboard Layout -->
                <div class="layout-card">
                    <h3><i class="fas fa-th-large text-primary"></i> Dashboard Layout</h3>
                    <p class="text-muted">Full page layout with sidebar, header, breadcrumbs, and footer</p>

                    <div class="layout-preview">
                        <pre style="margin: 0; color: #374151;">
┌─────────────────────────────────────────────────────────┐
│              HEADER (sticky)                            │
│  [≡] [LOGO]    [🔍 Search...]    [🔔] [@User ▾]       │
├────────┬────────────────────────────────────────────────┤
│        │ Home > Section > Page                          │
│ SIDE   ├────────────────────────────────────────────────┤
│ BAR    │                                                │
│        │     MAIN CONTENT AREA                          │
│ 🏠     │  (Your page content goes here)                 │
│ 📦▾    │                                                │
│ 💵▾    │                                                │
│        │                                                │
│ v2.0   │                                                │
├────────┴────────────────────────────────────────────────┤
│              FOOTER (auto-bottom)                       │
└─────────────────────────────────────────────────────────┘</pre>
                    </div>

                    <ul class="layout-features">
                        <li><i class="fas fa-check-circle"></i> <strong>Full navigation:</strong> Sidebar + header + footer</li>
                        <li><i class="fas fa-check-circle"></i> <strong>Breadcrumbs:</strong> SEO-friendly navigation</li>
                        <li><i class="fas fa-check-circle"></i> <strong>Responsive:</strong> Sidebar collapses on mobile</li>
                        <li><i class="fas fa-check-circle"></i> <strong>Best for:</strong> Main dashboards, complex pages</li>
                    </ul>

                    <a href="?demo=dashboard" class="btn btn-demo btn-block" target="_blank">
                        <i class="fas fa-external-link-alt"></i> View Live Demo
                    </a>
                </div>

                <!-- Table Layout -->
                <div class="layout-card">
                    <h3><i class="fas fa-table text-success"></i> Table Layout</h3>
                    <p class="text-muted">Optimized for data tables with filters and actions</p>

                    <div class="layout-preview">
                        <pre style="margin: 0; color: #374151;">
┌─────────────────────────────────────────────────────────┐
│               PAGE HEADER (sticky)                      │
│  Data Table Title                                       │
│  [+ Create] [Export Excel]                             │
├─────────────────────────────────────────────────────────┤
│               FILTERS SECTION                           │
│  [Status ▾] [Search: _____] [Apply]                   │
├─────────────────────────────────────────────────────────┤
│               TABLE CONTENT                             │
│  ┌─────────┬─────────┬─────────┬──────────┐           │
│  │ ID      │ Name    │ Status  │ Actions  │           │
│  ├─────────┼─────────┼─────────┼──────────┤           │
│  │ 001     │ Item 1  │ Active  │ [Edit]   │           │
│  │ 002     │ Item 2  │ Active  │ [Edit]   │           │
│  └─────────┴─────────┴─────────┴──────────┘           │
│                                                         │
│  Showing 1-25 of 250  [Pagination]                     │
└─────────────────────────────────────────────────────────┘</pre>
                    </div>

                    <ul class="layout-features">
                        <li><i class="fas fa-check-circle"></i> <strong>Sticky header:</strong> Title + action buttons always visible</li>
                        <li><i class="fas fa-check-circle"></i> <strong>Built-in filters:</strong> Optional filter section</li>
                        <li><i class="fas fa-check-circle"></i> <strong>DataTables:</strong> Auto-pagination, sorting, search</li>
                        <li><i class="fas fa-check-circle"></i> <strong>Best for:</strong> Data tables, lists, pay runs</li>
                    </ul>

                    <a href="?demo=table" class="btn btn-demo btn-block" target="_blank">
                        <i class="fas fa-external-link-alt"></i> View Live Demo
                    </a>
                </div>

                <!-- Card Layout -->
                <div class="layout-card">
                    <h3><i class="fas fa-window-maximize text-info"></i> Card Layout</h3>
                    <p class="text-muted">Standalone login page - NO header or sidebar</p>

                    <div class="layout-preview">
                        <pre style="margin: 0; color: #374151;">
┌─────────────────────────────────────────────────────────┐
│                                                         │
│        ┌───────────────────────────────┐               │
│        │     [🛡️]                      │               │
│        │     CIS LOGIN                 │               │
│        ├───────────────────────────────┤               │
│        │  Email:    [____________]     │               │
│        │  Password: [____________]     │               │
│        │  [✓] Remember me              │               │
│        │  [ Sign In ]                  │               │
│        ├───────────────────────────────┤               │
│        │  Forgot password? | Sign up   │               │
│        └───────────────────────────────┘               │
│                                                         │
│     Gradient background (NO navigation)                 │
└─────────────────────────────────────────────────────────┘</pre>
                    </div>

                    <ul class="layout-features">
                        <li><i class="fas fa-check-circle"></i> <strong>Standalone:</strong> NO header, sidebar, or footer</li>
                        <li><i class="fas fa-check-circle"></i> <strong>Centered card:</strong> Beautiful gradient background</li>
                        <li><i class="fas fa-check-circle"></i> <strong>Clean:</strong> Minimal, focused interface</li>
                        <li><i class="fas fa-check-circle"></i> <strong>Best for:</strong> Login, registration, password reset</li>
                    </ul>

                    <a href="?demo=card" class="btn btn-demo btn-block" target="_blank">
                        <i class="fas fa-external-link-alt"></i> View Live Demo
                    </a>
                </div>

                <!-- Split Layout -->
                <div class="layout-card">
                    <h3><i class="fas fa-columns text-warning"></i> Split Layout</h3>
                    <p class="text-muted">Two-panel resizable layout for master-detail views</p>

                    <div class="layout-preview">
                        <pre style="margin: 0; color: #374151;">
┌─────────────────────────────────────────────────────────┐
│                                                         │
│  ┌──────────────┐ │ ┌────────────────────────┐        │
│  │              │ │ │                        │        │
│  │   LEFT       │▐│▌│      RIGHT             │        │
│  │   PANEL      │ │ │      PANEL             │        │
│  │  (Master)    │ │ │     (Detail)           │        │
│  │              │ │ │                        │        │
│  │              │ │ │                        │        │
│  └──────────────┘ │ └────────────────────────┘        │
│                   Resize Handle                        │
│  Default 40/60 split, draggable, saves to localStorage │
└─────────────────────────────────────────────────────────┘</pre>
                    </div>

                    <ul class="layout-features">
                        <li><i class="fas fa-check-circle"></i> <strong>Resizable:</strong> Drag handle to adjust ratio</li>
                        <li><i class="fas fa-check-circle"></i> <strong>Persistent:</strong> Saves ratio to localStorage</li>
                        <li><i class="fas fa-check-circle"></i> <strong>Limits:</strong> Min 20%, max 80% for each panel</li>
                        <li><i class="fas fa-check-circle"></i> <strong>Best for:</strong> Product browser, master-detail views</li>
                    </ul>

                    <a href="?demo=split" class="btn btn-demo btn-block" target="_blank">
                        <i class="fas fa-external-link-alt"></i> View Live Demo
                    </a>
                </div>

                <!-- Blank Layout -->
                <div class="layout-card">
                    <h3><i class="fas fa-file text-secondary"></i> Blank Layout</h3>
                    <p class="text-muted">Minimal wrapper for full control over design</p>

                    <div class="layout-preview">
                        <pre style="margin: 0; color: #374151;">
┌─────────────────────────────────────────────────────────┐
│                                                         │
│                                                         │
│                                                         │
│            FULL CONTROL OVER DESIGN                     │
│         (No header, sidebar, or footer)                 │
│                                                         │
│                                                         │
│                                                         │
│                                                         │
└─────────────────────────────────────────────────────────┘</pre>
                    </div>

                    <ul class="layout-features">
                        <li><i class="fas fa-check-circle"></i> <strong>Minimal:</strong> Just HTML + CIS Core CSS</li>
                        <li><i class="fas fa-check-circle"></i> <strong>No chrome:</strong> No header, sidebar, footer</li>
                        <li><i class="fas fa-check-circle"></i> <strong>Full control:</strong> Design anything you want</li>
                        <li><i class="fas fa-check-circle"></i> <strong>Best for:</strong> Custom layouts, print views, reports</li>
                    </ul>

                    <a href="?demo=blank" class="btn btn-demo btn-block" target="_blank">
                        <i class="fas fa-external-link-alt"></i> View Live Demo
                    </a>
                </div>

            </div>

            <!-- Right Column - Quick Links & Info -->
            <div class="col-lg-4">

                <div class="quick-links mb-4">
                    <h4 class="mb-3"><i class="fas fa-link"></i> Quick Links</h4>
                    <a href="/modules/base/BASE_TEMPLATE_VISUAL_GUIDE.md" target="_blank">
                        <i class="fas fa-file-alt"></i> Visual Guide Documentation
                    </a>
                    <a href="/modules/human_resources/payroll/TEMPLATE_REFACTORING_COMPARISON.md" target="_blank">
                        <i class="fas fa-code-branch"></i> Before/After Comparison
                    </a>
                    <a href="/modules/human_resources/payroll/COMPREHENSIVE_AUDIT_REPORT.md" target="_blank">
                        <i class="fas fa-clipboard-check"></i> Payroll Audit Report
                    </a>
                    <a href="/modules/admin-ui/" target="_blank">
                        <i class="fas fa-palette"></i> Component Showcase
                    </a>
                    <a href="/modules/admin-ui/theme-builder.php" target="_blank">
                        <i class="fas fa-paint-brush"></i> Theme Builder
                    </a>
                </div>

                <div class="quick-links mb-4">
                    <h4 class="mb-3"><i class="fas fa-info-circle"></i> Template Info</h4>
                    <div class="alert alert-info">
                        <h6><strong>Location:</strong></h6>
                        <code>/modules/base/_templates/layouts/</code>

                        <h6 class="mt-3"><strong>Components:</strong></h6>
                        <code>/modules/base/_templates/components/</code>

                        <h6 class="mt-3"><strong>Available Layouts:</strong></h6>
                        <ul class="mb-0">
                            <li>dashboard.php</li>
                            <li>table.php</li>
                            <li>card.php</li>
                            <li>split.php</li>
                            <li>blank.php</li>
                        </ul>
                    </div>
                </div>

                <div class="quick-links">
                    <h4 class="mb-3"><i class="fas fa-rocket"></i> Next Steps</h4>
                    <ol>
                        <li class="mb-2">Click "View Live Demo" to see each layout in action</li>
                        <li class="mb-2">Test responsive design (resize browser)</li>
                        <li class="mb-2">Review the documentation links</li>
                        <li class="mb-2">Decide which layouts to use for payroll</li>
                        <li class="mb-2">Proceed with refactoring (2-3 hours)</li>
                    </ol>
                </div>

            </div>

        </div>

    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
