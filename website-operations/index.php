<?php
/**
 * Enterprise Website Operations Module
 *
 * Unified web operations management for:
 * - Vape Shed retail e-commerce
 * - Vaping Kiwi e-commerce
 * - VapeHQ e-commerce
 * - Wholesale portal
 * - Multi-outlet web operations
 *
 * Version: 1.0.0
 * Last Updated: November 14, 2025
 * Status: Production Ready
 */

// Core includes
require_once __DIR__ . '/../../assets/functions/config.php';
require_once __DIR__ . '/app/Services/WebOperationsService.php';
require_once __DIR__ . '/app/Services/WholesaleService.php';
require_once __DIR__ . '/app/Services/OrderManagementService.php';
require_once __DIR__ . '/app/Services/CatalogService.php';
require_once __DIR__ . '/app/Services/AnalyticsService.php';
require_once __DIR__ . '/app/Services/CustomerService.php';
require_once __DIR__ . '/app/Services/ReviewService.php';
require_once __DIR__ . '/bootstrap.php';

// Authentication & Authorization
if (!isset($_SESSION['userID'])) {
    header('Location: /login.php');
    exit;
}

// Include templates
include("../../assets/template/html-header.php");
include("../../assets/template/header.php");

// Initialize services
$webOpsService = new WebOperationsService($pdo);
$wholesaleService = new WholesaleService($pdo);
$orderService = new OrderManagementService($pdo);
$catalogService = new CatalogService($pdo);
$analyticsService = new AnalyticsService($pdo);
$customerService = new CustomerService($pdo);
$reviewService = new ReviewService($pdo);

// Get current view
$view = $_GET['view'] ?? 'dashboard';
$website = $_GET['website'] ?? 'vapeshed';

// Validate website parameter
$validWebsites = ['vapeshed', 'vapingkiwi', 'vapehq', 'wholesale'];
if (!in_array($website, $validWebsites)) {
    $website = 'vapeshed';
}

// Get user permissions
$userPermissions = $webOpsService->getUserPermissions($_SESSION['userID']);

// Dashboard data
$dashboardData = $webOpsService->getDashboardData($website);
$ordersToday = $orderService->getOrdersForDate(date('Y-m-d'), $website);
$pendingReviews = $reviewService->getPendingReviews($website);
$performanceMetrics = $analyticsService->getPerformanceMetrics($website);

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Website Operations - <?php echo ucfirst($website); ?> | Ecigdis CIS</title>
    <link rel="stylesheet" href="assets/css/website-operations.css">
    <link rel="stylesheet" href="assets/css/responsive.css">
</head>
<body class="app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show">

<div class="app-body">
    <?php include("../../assets/template/sidemenu.php"); ?>

    <main class="main">
        <!-- Breadcrumb Navigation -->
        <ol class="breadcrumb">
            <li class="breadcrumb-item"><a href="/">Home</a></li>
            <li class="breadcrumb-item"><a href="/modules/website-operations/">Website Operations</a></li>
            <li class="breadcrumb-item active">
                <?php echo match($view) {
                    'dashboard' => 'Dashboard',
                    'orders' => 'Order Management',
                    'customers' => 'Customer Management',
                    'catalog' => 'Catalog Management',
                    'wholesale' => 'Wholesale Portal',
                    'reviews' => 'Review Management',
                    'analytics' => 'Analytics & Reporting',
                    'settings' => 'Settings',
                    default => 'Dashboard'
                }; ?>
            </li>
            <li class="breadcrumb-menu d-md-down-none">
                <span style="color: #73818f; font-size: 14px; margin-right: 20px;">
                    <?php echo date('d/m/Y g:i A'); ?>
                </span>
                <?php include(__DIR__ . '/components/website-selector.php'); ?>
            </li>
        </ol>

        <div class="container-fluid">
            <div class="animated fadeIn">
                <?php
                // Load view based on request
                switch ($view) {
                    case 'dashboard':
                        include(__DIR__ . '/views/dashboard.php');
                        break;
                    case 'orders':
                        include(__DIR__ . '/views/orders.php');
                        break;
                    case 'customers':
                        include(__DIR__ . '/views/customers.php');
                        break;
                    case 'catalog':
                        include(__DIR__ . '/views/catalog.php');
                        break;
                    case 'wholesale':
                        include(__DIR__ . '/views/wholesale.php');
                        break;
                    case 'reviews':
                        include(__DIR__ . '/views/reviews.php');
                        break;
                    case 'analytics':
                        include(__DIR__ . '/views/analytics.php');
                        break;
                    case 'settings':
                        include(__DIR__ . '/views/settings.php');
                        break;
                    default:
                        include(__DIR__ . '/views/dashboard.php');
                }
                ?>
            </div>
        </div>
    </main>

    <?php include("../../assets/template/personalisation-menu.php"); ?>
</div>

<?php include("../../assets/template/html-footer.php"); ?>
<?php include("../../assets/template/footer.php"); ?>

<script src="assets/js/website-operations.js"></script>
<script src="assets/js/charts.js"></script>
<script src="assets/js/modal-handlers.js"></script>

</body>
</html>
