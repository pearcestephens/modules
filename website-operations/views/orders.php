<?php
/**
 * Website Operations - Orders Management View
 *
 * Complete order management interface with filtering, search, and bulk operations
 *
 * @package    WebsiteOperations
 * @version    1.0.0
 * @author     Ecigdis Development Team
 * @date       2025-11-14
 */

require_once __DIR__ . '/../../includes/db_config.php';
require_once __DIR__ . '/../services/OrderManagementService.php';
require_once __DIR__ . '/../components/order-card.php';
require_once __DIR__ . '/../components/stat-widget.php';

use Modules\WebsiteOperations\Services\OrderManagementService;

// Check authentication
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: /login.php');
    exit;
}

// Database connection
try {
    $db = getDBConnection();
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Initialize service
$orderService = new OrderManagementService($db);

// Handle filters
$filters = [
    'status' => $_GET['status'] ?? 'all',
    'date_from' => $_GET['date_from'] ?? date('Y-m-01'),
    'date_to' => $_GET['date_to'] ?? date('Y-m-d'),
    'search' => $_GET['search'] ?? '',
    'limit' => $_GET['limit'] ?? 50,
    'page' => $_GET['page'] ?? 1
];

// Get orders and stats
$orders = $orderService->getOrders($filters);
$stats = $orderService->getOrderStats();

$pageTitle = "Order Management";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Website Operations</title>

    <!-- Styles -->
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/website-operations.css">

    <style>
        .order-filters {
            background: white;
            padding: 1.5rem;
            border-radius: 0.75rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            margin-bottom: 1.5rem;
        }

        .order-list {
            display: grid;
            gap: 1rem;
        }

        .view-toggle {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 1rem;
        }

        .view-toggle button {
            padding: 0.5rem 1rem;
            border: 1px solid #e5e7eb;
            background: white;
            border-radius: 0.5rem;
            cursor: pointer;
            transition: all 0.15s;
        }

        .view-toggle button.active {
            background: #2563eb;
            color: white;
            border-color: #2563eb;
        }
    </style>
</head>
<body class="website-operations-module">

<!-- Header -->
<div class="webops-header">
    <div class="webops-container">
        <h1 class="webops-header-title">
            📦 Order Management
        </h1>
        <p class="webops-header-subtitle">
            Manage all orders across VapeShed and Ecigdis platforms
        </p>

        <nav class="webops-nav">
            <a href="dashboard.php" class="webops-nav-link">📊 Dashboard</a>
            <a href="orders.php" class="webops-nav-link active">📦 Orders</a>
            <a href="products.php" class="webops-nav-link">🏷️ Products</a>
            <a href="customers.php" class="webops-nav-link">👥 Customers</a>
            <a href="wholesale.php" class="webops-nav-link">🏢 Wholesale</a>
        </nav>
    </div>
</div>

<!-- Main Content -->
<div class="webops-container">

    <!-- Statistics Overview -->
    <div class="webops-mb-lg">
        <?php renderOrderStats($stats); ?>
    </div>

    <!-- Filters and Search -->
    <div class="order-filters">
        <form method="GET" action="" class="webops-grid webops-grid-4">
            <!-- Status Filter -->
            <div class="webops-form-group">
                <label class="webops-label">Status</label>
                <select name="status" class="webops-select" onchange="this.form.submit()">
                    <option value="all" <?php echo $filters['status'] === 'all' ? 'selected' : ''; ?>>All Orders</option>
                    <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                    <option value="processing" <?php echo $filters['status'] === 'processing' ? 'selected' : ''; ?>>Processing</option>
                    <option value="completed" <?php echo $filters['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                    <option value="cancelled" <?php echo $filters['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                    <option value="shipped" <?php echo $filters['status'] === 'shipped' ? 'selected' : ''; ?>>Shipped</option>
                </select>
            </div>

            <!-- Date From -->
            <div class="webops-form-group">
                <label class="webops-label">From Date</label>
                <input type="date" name="date_from" class="webops-input"
                       value="<?php echo htmlspecialchars($filters['date_from']); ?>">
            </div>

            <!-- Date To -->
            <div class="webops-form-group">
                <label class="webops-label">To Date</label>
                <input type="date" name="date_to" class="webops-input"
                       value="<?php echo htmlspecialchars($filters['date_to']); ?>">
            </div>

            <!-- Search -->
            <div class="webops-form-group">
                <label class="webops-label">Search</label>
                <div class="webops-flex webops-gap-sm">
                    <input type="text" name="search" class="webops-input"
                           placeholder="Order #, customer name..."
                           value="<?php echo htmlspecialchars($filters['search']); ?>">
                    <button type="submit" class="webops-btn webops-btn-primary">
                        🔍 Search
                    </button>
                </div>
            </div>
        </form>

        <!-- Quick Filters -->
        <div class="webops-flex webops-gap-sm webops-mt-md">
            <span class="webops-label">Quick Filters:</span>
            <a href="?status=pending" class="webops-btn webops-btn-sm webops-btn-secondary">⏳ Pending</a>
            <a href="?status=processing" class="webops-btn webops-btn-sm webops-btn-secondary">⚙️ Processing</a>
            <a href="?date_from=<?php echo date('Y-m-d'); ?>&date_to=<?php echo date('Y-m-d'); ?>"
               class="webops-btn webops-btn-sm webops-btn-secondary">📅 Today</a>
            <a href="?date_from=<?php echo date('Y-m-d', strtotime('-7 days')); ?>"
               class="webops-btn webops-btn-sm webops-btn-secondary">📊 Last 7 Days</a>
            <a href="?" class="webops-btn webops-btn-sm webops-btn-secondary">🔄 Clear</a>
        </div>
    </div>

    <!-- View Toggle and Actions -->
    <div class="webops-flex webops-justify-between webops-items-center webops-mb-md">
        <div class="view-toggle">
            <button class="active" onclick="setView('cards')">🗂️ Cards</button>
            <button onclick="setView('table')">📋 Table</button>
            <button onclick="setView('compact')">📄 Compact</button>
        </div>

        <div class="webops-flex webops-gap-sm">
            <button class="webops-btn webops-btn-primary" onclick="createNewOrder()">
                ➕ New Order
            </button>
            <button class="webops-btn webops-btn-secondary" onclick="exportOrders()">
                📥 Export
            </button>
            <button class="webops-btn webops-btn-secondary" onclick="printOrders()">
                🖨️ Print
            </button>
        </div>
    </div>

    <!-- Orders List -->
    <div id="orders-view-cards" class="order-list">
        <?php if (empty($orders)): ?>
            <div class="webops-alert webops-alert-info">
                <strong>No orders found</strong>
                <p>Try adjusting your filters or search terms.</p>
            </div>
        <?php else: ?>
            <?php foreach ($orders as $order): ?>
                <?php renderOrderCard($order, ['show_actions' => true]); ?>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if (count($orders) >= $filters['limit']): ?>
        <div class="webops-flex webops-justify-between webops-items-center webops-mt-lg">
            <div>
                Showing <?php echo count($orders); ?> orders
            </div>
            <div class="webops-flex webops-gap-sm">
                <?php if ($filters['page'] > 1): ?>
                    <a href="?page=<?php echo $filters['page'] - 1; ?>&status=<?php echo $filters['status']; ?>"
                       class="webops-btn webops-btn-secondary">
                        ← Previous
                    </a>
                <?php endif; ?>

                <a href="?page=<?php echo $filters['page'] + 1; ?>&status=<?php echo $filters['status']; ?>"
                   class="webops-btn webops-btn-secondary">
                    Next →
                </a>
            </div>
        </div>
    <?php endif; ?>

</div>

<!-- Loading Overlay -->
<div id="loading-overlay" class="webops-hidden">
    <div class="webops-loading">
        <div class="webops-spinner"></div>
    </div>
</div>

<!-- Scripts -->
<script src="../assets/js/api-client.js"></script>
<script>
// View switching
function setView(viewType) {
    // Update active button
    document.querySelectorAll('.view-toggle button').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');

    // TODO: Implement different view types
    console.log('Switching to view:', viewType);
}

// View order details
function viewOrder(orderId) {
    window.location.href = `order-details.php?id=${orderId}`;
}

// Update order status
async function updateOrderStatus(orderId, newStatus) {
    if (!confirm(`Change order status to "${newStatus}"?`)) {
        return;
    }

    try {
        await webOpsAPI.updateOrder(orderId, { status: newStatus });
        webOpsAPI.showToast('Order status updated', 'success');
        location.reload();
    } catch (error) {
        webOpsAPI.showToast('Failed to update order: ' + error.message, 'danger');
    }
}

// Create new order
function createNewOrder() {
    window.location.href = 'order-create.php';
}

// Export orders
function exportOrders() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    window.location.href = '?' + params.toString();
}

// Print orders
function printOrders() {
    window.print();
}

// Show order menu
function showOrderMenu(orderId) {
    // TODO: Implement context menu
    console.log('Show menu for order:', orderId);
}

// Auto-refresh orders
setInterval(() => {
    // Only refresh if on first page
    const urlParams = new URLSearchParams(window.location.search);
    if (!urlParams.get('page') || urlParams.get('page') === '1') {
        location.reload();
    }
}, 60000); // 60 seconds
</script>

</body>
</html>
