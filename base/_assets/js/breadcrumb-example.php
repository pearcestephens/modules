<?php
/**
 * Example: Using Breadcrumbs and Header Buttons
 *
 * This shows how to use the new breadcrumb bar (second header tier)
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/base/_templates/themes/cis-classic/theme.php';

$theme = new CISClassicTheme();
$theme->setTitle('Online Orders Overview - The Vape Shed CIS');
$theme->setCurrentPage('online-orders');

// Add breadcrumbs (appears in second header row)
$theme->addBreadcrumb('Admin', '/admin');
$theme->addBreadcrumb('Online Orders Overview'); // Last item has no URL (current page)

// Add header action buttons (appears on right side of breadcrumb bar)
$theme->addHeaderButton('Quick Product Qty Change', '#', 'purple', 'fas fa-boxes');
$theme->addHeaderButton('Store Cashup Calculator', '#', 'success', 'fas fa-calculator');

// Show timestamps (optional - shows current time twice as in screenshot)
$theme->showTimestamps(true);

$theme->render('html-head');
$theme->render('header');
$theme->render('sidebar');
$theme->render('main-start');
?>

<div class="container-fluid">
    <div class="animated fadeIn">

        <!-- Page Header -->
        <div class="row">
            <div class="col-lg-12">
                <div class="card">
                    <div class="card-header">
                        <i class="fas fa-shopping-cart"></i> Online Orders Overview
                        <div class="card-header-actions">
                            <span class="text-muted small">View & Edit Website Orders</span>
                        </div>
                    </div>
                    <div class="card-body">

                        <!-- Search bar like in screenshot -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <div class="input-group">
                                    <div class="input-group-prepend">
                                        <span class="input-group-text">Order Search</span>
                                    </div>
                                    <input type="text" class="form-control" placeholder="Customer Name, Order Number...">
                                </div>
                            </div>
                        </div>

                        <!-- Your content here -->
                        <div class="alert alert-info">
                            <strong>Example Page:</strong> This demonstrates the breadcrumb bar and header buttons.
                        </div>

                        <h5>How to Use Breadcrumbs & Header Buttons:</h5>

                        <pre><code>// Add breadcrumbs
$theme->addBreadcrumb('Home', '/');
$theme->addBreadcrumb('Admin', '/admin');
$theme->addBreadcrumb('Current Page'); // No URL = current page

// Add header buttons
$theme->addHeaderButton(
    'Button Label',    // Button text
    '/url/to/page',    // URL
    'primary',         // Color: primary, success, danger, warning, info, purple
    'fas fa-icon',     // Icon (optional)
    '_blank'           // Target (optional)
);

// Show timestamps (optional)
$theme->showTimestamps(true);</code></pre>

                        <h5 class="mt-4">Available Button Colors:</h5>
                        <p>
                            <button class="btn btn-primary btn-sm">primary</button>
                            <button class="btn btn-secondary btn-sm">secondary</button>
                            <button class="btn btn-success btn-sm">success</button>
                            <button class="btn btn-danger btn-sm">danger</button>
                            <button class="btn btn-warning btn-sm">warning</button>
                            <button class="btn btn-info btn-sm">info</button>
                            <button class="btn btn-purple btn-sm" style="background: #6f42c1; color: white;">purple</button>
                            <button class="btn btn-dark btn-sm">dark</button>
                        </p>

                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php
$theme->render('footer');
echo '</body></html>';
?>
