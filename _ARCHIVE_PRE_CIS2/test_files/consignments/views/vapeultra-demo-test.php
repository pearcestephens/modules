<?php
/**
 * =============================================================================
 * VAPEULTRA DEMO TEST PAGE
 * =============================================================================
 *
 * Purpose: Show you what the new VapeUltra template looks like
 * Status: DEMO/TEST - Safe to delete after review
 *
 * This demonstrates:
 * - New template structure
 * - Breadcrumb navigation
 * - Sub-navigation tabs
 * - Modern card layout
 * - AJAX client
 * - Modal system
 * - Toast notifications
 *
 * =============================================================================
 */

require_once __DIR__ . '/../bootstrap.php';

// Start output buffering to capture page content
ob_start();
?>

<!-- Demo Content - This is what YOU would write for your pages -->
<div class="container-fluid">

    <!-- Welcome Header -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="alert alert-success" role="alert">
                <i class="bi bi-check-circle-fill me-2"></i>
                <strong>Welcome to VapeUltra Template Demo!</strong>
                <p class="mb-0 mt-2">This page demonstrates the new template system. Everything you see is part of the VapeUltra design system.</p>
            </div>
        </div>
    </div>

    <!-- Stats Cards Row -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-primary bg-opacity-10 p-3">
                                <i class="bi bi-graph-up text-primary fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Total Sales</h6>
                            <h3 class="mb-0">$24,567</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-success bg-opacity-10 p-3">
                                <i class="bi bi-box-seam text-success fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Products</h6>
                            <h3 class="mb-0">1,234</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-warning bg-opacity-10 p-3">
                                <i class="bi bi-people text-warning fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Customers</h6>
                            <h3 class="mb-0">5,678</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-3">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="flex-shrink-0">
                            <div class="rounded-circle bg-info bg-opacity-10 p-3">
                                <i class="bi bi-clock-history text-info fs-4"></i>
                            </div>
                        </div>
                        <div class="flex-grow-1 ms-3">
                            <h6 class="text-muted mb-1">Pending</h6>
                            <h3 class="mb-0">42</h3>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Feature Demo Cards -->
    <div class="row g-4">
        <!-- AJAX Demo Card -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="bi bi-lightning-charge text-primary me-2"></i>
                        AJAX Client Demo
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Click the button to see the AJAX client in action with automatic loading states and error handling.</p>
                    <button type="button" class="btn btn-primary" id="demoAjaxBtn">
                        <i class="bi bi-send me-2"></i>
                        Test AJAX Request
                    </button>
                    <div id="ajaxResult" class="mt-3"></div>
                </div>
            </div>
        </div>

        <!-- Modal Demo Card -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="bi bi-window text-success me-2"></i>
                        Modal System Demo
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Try the modal system with confirm, alert, and prompt dialogs.</p>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-success" id="demoConfirmBtn">
                            <i class="bi bi-question-circle me-2"></i>
                            Confirm Dialog
                        </button>
                        <button type="button" class="btn btn-info" id="demoAlertBtn">
                            <i class="bi bi-info-circle me-2"></i>
                            Alert Dialog
                        </button>
                        <button type="button" class="btn btn-warning" id="demoPromptBtn">
                            <i class="bi bi-pencil me-2"></i>
                            Prompt Dialog
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Toast Demo Card -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="bi bi-bell text-warning me-2"></i>
                        Toast Notifications Demo
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Show toast notifications in different styles and positions.</p>
                    <div class="btn-group" role="group">
                        <button type="button" class="btn btn-success" id="demoSuccessToast">Success</button>
                        <button type="button" class="btn btn-danger" id="demoErrorToast">Error</button>
                        <button type="button" class="btn btn-warning" id="demoWarningToast">Warning</button>
                        <button type="button" class="btn btn-info" id="demoInfoToast">Info</button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Features List Card -->
        <div class="col-lg-6">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white border-bottom">
                    <h5 class="mb-0">
                        <i class="bi bi-stars text-danger me-2"></i>
                        What's Included
                    </h5>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Single master template</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Responsive design system</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Breadcrumb navigation</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Sub-navigation tabs</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> AJAX client with interceptors</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Modal system (alert/confirm/prompt)</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Toast notifications</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Global error handler</li>
                        <li class="mb-2"><i class="bi bi-check-circle-fill text-success me-2"></i> Award-winning components</li>
                        <li class="mb-0"><i class="bi bi-check-circle-fill text-success me-2"></i> Accessibility compliant</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>

</div>

<?php
// Capture page content
$pageContent = ob_get_clean();

// Define breadcrumb navigation
$breadcrumb = [
    ['label' => 'Home', 'url' => '/', 'icon' => 'house-door'],
    ['label' => 'Consignments', 'url' => '/modules/consignments/', 'icon' => 'box-seam'],
    ['label' => 'VapeUltra Demo', 'active' => true, 'icon' => 'stars']
];

// Define sub-navigation tabs
$subnav = [
    ['label' => 'Overview', 'url' => '#', 'icon' => 'grid', 'active' => true],
    ['label' => 'Components', 'url' => '#', 'icon' => 'puzzle'],
    ['label' => 'Features', 'url' => '#', 'icon' => 'lightning', 'badge' => 'New'],
    ['label' => 'Documentation', 'url' => '#', 'icon' => 'book']
];

// Render using VapeUltra template (correct API)
$renderer->render($pageContent, [
    'title' => 'VapeUltra Demo - CIS 2.0',
    'class' => 'page-demo',
    'layout' => 'main',
    'scripts' => [],
    'styles' => []
]);
?>

<!-- Page-Specific JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('✅ VapeUltra Demo Page Loaded');

    // AJAX Demo Button
    document.getElementById('demoAjaxBtn')?.addEventListener('click', function() {
        const resultDiv = document.getElementById('ajaxResult');
        resultDiv.innerHTML = '<div class="alert alert-info"><i class="bi bi-hourglass-split me-2"></i>Making AJAX request...</div>';

        // Simulate AJAX call (replace with real endpoint)
        setTimeout(() => {
            resultDiv.innerHTML = '<div class="alert alert-success"><i class="bi bi-check-circle-fill me-2"></i>AJAX request successful! Data received.</div>';
            VapeUltra.Toast.success('AJAX request completed!');
        }, 1000);
    });

    // Modal Demos
    document.getElementById('demoConfirmBtn')?.addEventListener('click', function() {
        VapeUltra.Modal.confirm({
            title: 'Confirm Action',
            message: 'Are you sure you want to proceed?',
            confirmText: 'Yes, proceed',
            cancelText: 'Cancel'
        }).then(result => {
            if (result) {
                VapeUltra.Toast.success('You clicked Confirm!');
            } else {
                VapeUltra.Toast.info('You clicked Cancel');
            }
        });
    });

    document.getElementById('demoAlertBtn')?.addEventListener('click', function() {
        VapeUltra.Modal.alert({
            title: 'Information',
            message: 'This is an alert dialog. It only has one button.',
            type: 'info'
        });
    });

    document.getElementById('demoPromptBtn')?.addEventListener('click', function() {
        VapeUltra.Modal.prompt({
            title: 'Enter Your Name',
            message: 'Please enter your name:',
            placeholder: 'John Doe'
        }).then(result => {
            if (result) {
                VapeUltra.Toast.success('Hello, ' + result + '!');
            }
        });
    });

    // Toast Demos
    document.getElementById('demoSuccessToast')?.addEventListener('click', function() {
        VapeUltra.Toast.success('Operation completed successfully!');
    });

    document.getElementById('demoErrorToast')?.addEventListener('click', function() {
        VapeUltra.Toast.error('An error occurred!');
    });

    document.getElementById('demoWarningToast')?.addEventListener('click', function() {
        VapeUltra.Toast.warning('This is a warning message!');
    });

    document.getElementById('demoInfoToast')?.addEventListener('click', function() {
        VapeUltra.Toast.info('Here is some information!');
    });
});
</script>
