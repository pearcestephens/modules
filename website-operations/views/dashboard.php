<?php
/**
 * Website Operations Dashboard
 *
 * Main dashboard for managing VapeShed and Ecigdis e-commerce operations
 * Production-grade UI with real-time updates
 *
 * @package    WebsiteOperations
 * @version    1.0.0
 */

require_once __DIR__ . '/../../autoload.php';
require_once __DIR__ . '/../services/WebsiteOperationsService.php';

use Modules\WebsiteOperations\Services\WebsiteOperationsService;

// Check authentication
if (!isset($_SESSION['user'])) {
    header('Location: /login.php');
    exit;
}

// Database connection
$db = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Initialize service
$config = json_decode(file_get_contents(__DIR__ . '/../module.json'), true);
$service = new WebsiteOperationsService($db, $config);

// Get dashboard data
$filters = [
    'date_range' => $_GET['date_range'] ?? '30d',
    'outlet' => $_GET['outlet'] ?? 'all'
];

$dashboardData = $service->getDashboardData($filters);

$pageTitle = "Website Operations Dashboard";
include __DIR__ . '/../../includes/header.php';
?>

<style>
.dashboard-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    border-radius: 10px;
    margin-bottom: 2rem;
}

.stat-card {
    background: white;
    border-radius: 10px;
    padding: 1.5rem;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    transition: transform 0.2s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}

.stat-value {
    font-size: 2.5rem;
    font-weight: 700;
    color: #667eea;
    margin: 0.5rem 0;
}

.stat-label {
    color: #6c757d;
    font-size: 0.9rem;
    text-transform: uppercase;
    letter-spacing: 1px;
}

.alert-card {
    border-left: 4px solid;
    padding: 1rem;
    margin-bottom: 1rem;
    border-radius: 5px;
    background: white;
}

.alert-warning { border-color: #ffc107; background: #fff3cd; }
.alert-danger { border-color: #dc3545; background: #f8d7da; }
.alert-info { border-color: #17a2b8; background: #d1ecf1; }

.chart-container {
    background: white;
    padding: 2rem;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 2rem;
}

.badge-large {
    font-size: 1.2rem;
    padding: 0.5rem 1rem;
}

.savings-highlight {
    background: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
    color: white;
    padding: 2rem;
    border-radius: 10px;
    text-align: center;
    margin-bottom: 2rem;
}

.savings-amount {
    font-size: 3rem;
    font-weight: 700;
    margin: 1rem 0;
}

.quick-action-btn {
    width: 100%;
    padding: 1rem;
    margin-bottom: 0.5rem;
    border-radius: 8px;
    font-weight: 600;
}

.order-status-pending { color: #ffc107; }
.order-status-processing { color: #17a2b8; }
.order-status-completed { color: #28a745; }
.order-status-cancelled { color: #dc3545; }

.trending-product {
    display: flex;
    align-items: center;
    padding: 1rem;
    border-bottom: 1px solid #e9ecef;
}

.trending-product:hover {
    background: #f8f9fa;
}

.product-image {
    width: 50px;
    height: 50px;
    border-radius: 5px;
    margin-right: 1rem;
    object-fit: cover;
}

.real-time-indicator {
    display: inline-block;
    width: 10px;
    height: 10px;
    background: #28a745;
    border-radius: 50%;
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}
</style>

<div class="container-fluid">
    <!-- Dashboard Header -->
    <div class="dashboard-header">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1 class="mb-1">
                    <i class="fas fa-store"></i>
                    Website Operations Dashboard
                </h1>
                <p class="mb-0">
                    <span class="real-time-indicator"></span>
                    Live monitoring of VapeShed & Ecigdis operations
                </p>
            </div>
            <div class="col-md-4 text-end">
                <div class="btn-group">
                    <button class="btn btn-light" onclick="changeDateRange('7d')">7 Days</button>
                    <button class="btn btn-light active" onclick="changeDateRange('30d')">30 Days</button>
                    <button class="btn btn-light" onclick="changeDateRange('90d')">90 Days</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Shipping Savings Highlight -->
    <?php if (isset($dashboardData['fulfillment']['total_saved'])): ?>
    <div class="savings-highlight">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h2><i class="fas fa-piggy-bank"></i> Total Shipping Savings</h2>
                <div class="savings-amount">
                    $<?php echo number_format($dashboardData['fulfillment']['total_saved'], 2); ?>
                </div>
                <p class="mb-0">
                    Saved through intelligent shipping optimization algorithm
                    <br>
                    <small>Projected annual savings: $<?php echo number_format($dashboardData['fulfillment']['total_saved'] / 30 * 365, 2); ?></small>
                </p>
            </div>
            <div class="col-md-4">
                <div class="text-center">
                    <i class="fas fa-chart-line" style="font-size: 5rem; opacity: 0.3;"></i>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Key Metrics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-label">Total Orders</div>
                <div class="stat-value"><?php echo number_format($dashboardData['summary']['orders']['total']); ?></div>
                <div class="text-muted">
                    <i class="fas fa-arrow-up text-success"></i>
                    <?php echo $dashboardData['summary']['revenue']['growth']; ?>% growth
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-label">Revenue</div>
                <div class="stat-value">$<?php echo number_format($dashboardData['summary']['revenue']['total'], 0); ?></div>
                <div class="text-muted">
                    Avg: $<?php echo number_format($dashboardData['summary']['revenue']['average_order'], 2); ?> per order
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-label">Active Customers</div>
                <div class="stat-value"><?php echo number_format($dashboardData['summary']['customers']['active']); ?></div>
                <div class="text-muted">
                    <?php echo $dashboardData['summary']['customers']['new']; ?> new this period
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-label">Products</div>
                <div class="stat-value"><?php echo number_format($dashboardData['summary']['products']['active']); ?></div>
                <div class="text-muted">
                    <?php echo $dashboardData['summary']['products']['low_stock']; ?> low stock
                </div>
            </div>
        </div>
    </div>

    <!-- Order Status Overview -->
    <div class="row mb-4">
        <div class="col-md-8">
            <div class="chart-container">
                <h3><i class="fas fa-chart-bar"></i> Order Status Overview</h3>
                <div class="row text-center mt-4">
                    <div class="col-md-3">
                        <div class="order-status-pending">
                            <i class="fas fa-clock fa-3x mb-2"></i>
                            <h4><?php echo $dashboardData['summary']['orders']['pending']; ?></h4>
                            <p>Pending</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="order-status-processing">
                            <i class="fas fa-cog fa-3x mb-2"></i>
                            <h4><?php echo $dashboardData['summary']['orders']['processing']; ?></h4>
                            <p>Processing</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="order-status-completed">
                            <i class="fas fa-check-circle fa-3x mb-2"></i>
                            <h4><?php echo $dashboardData['summary']['orders']['completed']; ?></h4>
                            <p>Completed</p>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="order-status-cancelled">
                            <i class="fas fa-times-circle fa-3x mb-2"></i>
                            <h4><?php echo $dashboardData['summary']['orders']['cancelled']; ?></h4>
                            <p>Cancelled</p>
                        </div>
                    </div>
                </div>

                <!-- Revenue Chart Placeholder -->
                <div class="mt-4">
                    <canvas id="revenueChart" height="80"></canvas>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <!-- Alerts -->
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4 class="mb-0"><i class="fas fa-bell"></i> Alerts</h4>
                </div>
                <div class="card-body">
                    <?php if (empty($dashboardData['alerts'])): ?>
                        <p class="text-muted text-center py-3">
                            <i class="fas fa-check-circle fa-2x mb-2"></i>
                            <br>All systems operational!
                        </p>
                    <?php else: ?>
                        <?php foreach ($dashboardData['alerts'] as $alert): ?>
                            <div class="alert-card alert-<?php echo $alert['type']; ?>">
                                <strong><?php echo $alert['title']; ?></strong>
                                <p class="mb-1 small"><?php echo $alert['message']; ?></p>
                                <a href="<?php echo $alert['action']; ?>" class="btn btn-sm btn-outline-dark">View</a>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick Actions -->
            <div class="card mt-3">
                <div class="card-header bg-success text-white">
                    <h4 class="mb-0"><i class="fas fa-bolt"></i> Quick Actions</h4>
                </div>
                <div class="card-body">
                    <a href="orders.php?status=pending" class="btn btn-primary quick-action-btn">
                        <i class="fas fa-inbox"></i> Process Pending Orders
                    </a>
                    <a href="products.php?filter=low_stock" class="btn btn-warning quick-action-btn">
                        <i class="fas fa-exclamation-triangle"></i> Review Low Stock
                    </a>
                    <a href="wholesale.php?filter=pending" class="btn btn-info quick-action-btn">
                        <i class="fas fa-user-check"></i> Approve Wholesale Accounts
                    </a>
                    <a href="../api/index.php?endpoint=dashboard" class="btn btn-secondary quick-action-btn">
                        <i class="fas fa-code"></i> API Documentation
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Recent Orders & Trending Products -->
    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header bg-dark text-white">
                    <h4 class="mb-0"><i class="fas fa-shopping-cart"></i> Recent Orders</h4>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Channel</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Saved</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dashboardData['orders'] as $order): ?>
                                <tr>
                                    <td><strong><?php echo $order['order_number']; ?></strong></td>
                                    <td><?php echo $order['customer_name']; ?></td>
                                    <td><span class="badge bg-secondary"><?php echo strtoupper($order['channel']); ?></span></td>
                                    <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td>
                                        <span class="badge bg-<?php echo ['pending'=>'warning', 'processing'=>'info', 'completed'=>'success', 'cancelled'=>'danger'][$order['status']] ?? 'secondary'; ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                    <td class="text-success">$<?php echo number_format($order['shipping_cost_saved'] ?? 0, 2); ?></td>
                                    <td>
                                        <a href="order-detail.php?id=<?php echo $order['id']; ?>" class="btn btn-sm btn-primary">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h4 class="mb-0"><i class="fas fa-fire"></i> Trending Products</h4>
                </div>
                <div class="card-body p-0">
                    <?php foreach ($dashboardData['trending_products'] as $product): ?>
                    <div class="trending-product">
                        <img src="<?php echo $product['image_url'] ?? '/assets/img/placeholder.png'; ?>" class="product-image" alt="">
                        <div class="flex-grow-1">
                            <strong><?php echo $product['name']; ?></strong>
                            <div class="small text-muted">
                                <?php echo $product['units_sold']; ?> sold | $<?php echo number_format($product['revenue'], 0); ?> revenue
                            </div>
                        </div>
                        <div class="text-end">
                            <span class="badge badge-large bg-success">
                                <i class="fas fa-arrow-up"></i>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@3.9.1/dist/chart.min.js"></script>
<script>
// Revenue chart
const revenueData = <?php echo json_encode($dashboardData['revenue']); ?>;
const ctx = document.getElementById('revenueChart').getContext('2d');
new Chart(ctx, {
    type: 'line',
    data: {
        labels: revenueData.map(d => d.date),
        datasets: [{
            label: 'Revenue',
            data: revenueData.map(d => d.revenue),
            borderColor: '#667eea',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            tension: 0.4,
            fill: true
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        return '$' + value.toLocaleString();
                    }
                }
            }
        }
    }
});

// Auto-refresh every 30 seconds
setInterval(() => {
    location.reload();
}, 30000);

function changeDateRange(range) {
    window.location.href = '?date_range=' + range;
}
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
