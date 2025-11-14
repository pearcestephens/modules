<?php
/**
 * Website Operations - Customer Management View
 *
 * Customer directory, order history, and account management
 *
 * @package    WebsiteOperations
 * @version    1.0.0
 * @author     Ecigdis Development Team
 * @date       2025-11-14
 */

require_once __DIR__ . '/../../includes/db_config.php';
require_once __DIR__ . '/../services/CustomerManagementService.php';
require_once __DIR__ . '/../components/stat-widget.php';

use Modules\WebsiteOperations\Services\CustomerManagementService;

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
$customerService = new CustomerManagementService($db);

// Handle filters
$filters = [
    'search' => $_GET['search'] ?? '',
    'segment' => $_GET['segment'] ?? 'all',
    'sort' => $_GET['sort'] ?? 'name',
    'limit' => $_GET['limit'] ?? 50,
    'page' => $_GET['page'] ?? 1
];

// Get customers and stats
$customers = $customerService->getCustomers($filters);
$stats = $customerService->getCustomerStats();

$pageTitle = "Customer Management";
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
        .customer-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            display: flex;
            gap: 1rem;
            align-items: center;
            transition: all 0.15s;
            cursor: pointer;
        }
        
        .customer-card:hover {
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .customer-avatar {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 1.5rem;
            font-weight: 700;
        }
        
        .customer-info {
            flex: 1;
        }
        
        .customer-name {
            font-size: 1.125rem;
            font-weight: 600;
            color: #111827;
            margin-bottom: 0.25rem;
        }
        
        .customer-email {
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .customer-stats {
            display: flex;
            gap: 2rem;
            text-align: center;
        }
        
        .customer-stat-value {
            font-size: 1.25rem;
            font-weight: 700;
            color: #111827;
        }
        
        .customer-stat-label {
            font-size: 0.75rem;
            color: #6b7280;
            text-transform: uppercase;
        }
    </style>
</head>
<body class="website-operations-module">

<!-- Header -->
<div class="webops-header">
    <div class="webops-container">
        <h1 class="webops-header-title">
            👥 Customer Management
        </h1>
        <p class="webops-header-subtitle">
            Customer directory, order history, and insights
        </p>
        
        <nav class="webops-nav">
            <a href="dashboard.php" class="webops-nav-link">📊 Dashboard</a>
            <a href="orders.php" class="webops-nav-link">📦 Orders</a>
            <a href="products.php" class="webops-nav-link">🏷️ Products</a>
            <a href="customers.php" class="webops-nav-link active">👥 Customers</a>
            <a href="wholesale.php" class="webops-nav-link">🏢 Wholesale</a>
        </nav>
    </div>
</div>

<!-- Main Content -->
<div class="webops-container">
    
    <!-- Statistics Overview -->
    <div class="webops-mb-lg">
        <?php
        $customerStats = [
            [
                'label' => 'Total Customers',
                'value' => $stats['total'] ?? 0,
                'change' => $stats['total_change'] ?? null,
                'icon' => 'customers',
                'color' => 'primary',
                'format' => 'number'
            ],
            [
                'label' => 'Active This Month',
                'value' => $stats['active_month'] ?? 0,
                'icon' => 'star',
                'color' => 'success',
                'format' => 'number'
            ],
            [
                'label' => 'Average Order Value',
                'value' => $stats['avg_order_value'] ?? 0,
                'change' => $stats['aov_change'] ?? null,
                'icon' => 'revenue',
                'color' => 'warning',
                'format' => 'currency'
            ],
            [
                'label' => 'Lifetime Value',
                'value' => $stats['lifetime_value'] ?? 0,
                'icon' => 'chart',
                'color' => 'info',
                'format' => 'currency'
            ]
        ];
        renderStatGrid($customerStats, 4);
        ?>
    </div>
    
    <!-- Filters and Search -->
    <div class="webops-card webops-mb-lg">
        <form method="GET" action="" class="webops-grid webops-grid-3">
            <!-- Segment Filter -->
            <div class="webops-form-group">
                <label class="webops-label">Customer Segment</label>
                <select name="segment" class="webops-select" onchange="this.form.submit()">
                    <option value="all" <?php echo $filters['segment'] === 'all' ? 'selected' : ''; ?>>All Customers</option>
                    <option value="vip" <?php echo $filters['segment'] === 'vip' ? 'selected' : ''; ?>>⭐ VIP</option>
                    <option value="regular" <?php echo $filters['segment'] === 'regular' ? 'selected' : ''; ?>>Regular</option>
                    <option value="new" <?php echo $filters['segment'] === 'new' ? 'selected' : ''; ?>>New (< 30 days)</option>
                    <option value="inactive" <?php echo $filters['segment'] === 'inactive' ? 'selected' : ''; ?>>Inactive (> 90 days)</option>
                </select>
            </div>
            
            <!-- Sort -->
            <div class="webops-form-group">
                <label class="webops-label">Sort By</label>
                <select name="sort" class="webops-select" onchange="this.form.submit()">
                    <option value="name" <?php echo $filters['sort'] === 'name' ? 'selected' : ''; ?>>Name A-Z</option>
                    <option value="name_desc" <?php echo $filters['sort'] === 'name_desc' ? 'selected' : ''; ?>>Name Z-A</option>
                    <option value="orders" <?php echo $filters['sort'] === 'orders' ? 'selected' : ''; ?>>Most Orders</option>
                    <option value="revenue" <?php echo $filters['sort'] === 'revenue' ? 'selected' : ''; ?>>Highest Revenue</option>
                    <option value="recent" <?php echo $filters['sort'] === 'recent' ? 'selected' : ''; ?>>Recently Active</option>
                </select>
            </div>
            
            <!-- Search -->
            <div class="webops-form-group">
                <label class="webops-label">Search</label>
                <div class="webops-flex webops-gap-sm">
                    <input type="text" name="search" class="webops-input" 
                           placeholder="Name, email, phone..." 
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
            <a href="?segment=vip" class="webops-btn webops-btn-sm webops-btn-secondary">⭐ VIP Customers</a>
            <a href="?segment=new" class="webops-btn webops-btn-sm webops-btn-secondary">🆕 New Customers</a>
            <a href="?segment=inactive" class="webops-btn webops-btn-sm webops-btn-secondary">💤 Inactive</a>
            <a href="?" class="webops-btn webops-btn-sm webops-btn-secondary">🔄 Clear</a>
        </div>
    </div>
    
    <!-- Actions -->
    <div class="webops-flex webops-justify-between webops-items-center webops-mb-md">
        <div>
            <strong><?php echo count($customers); ?></strong> customers found
        </div>
        
        <div class="webops-flex webops-gap-sm">
            <button class="webops-btn webops-btn-primary" onclick="createNewCustomer()">
                ➕ New Customer
            </button>
            <button class="webops-btn webops-btn-secondary" onclick="exportCustomers()">
                📥 Export
            </button>
            <button class="webops-btn webops-btn-secondary" onclick="sendBulkEmail()">
                ✉️ Email Campaign
            </button>
        </div>
    </div>
    
    <!-- Customer List -->
    <div class="webops-grid webops-grid-1">
        <?php if (empty($customers)): ?>
            <div class="webops-alert webops-alert-info">
                <strong>No customers found</strong>
                <p>Try adjusting your filters or search terms.</p>
            </div>
        <?php else: ?>
            <?php foreach ($customers as $customer): ?>
                <div class="customer-card" onclick="viewCustomer(<?php echo $customer['id']; ?>)">
                    <div class="customer-avatar">
                        <?php echo strtoupper(substr($customer['first_name'] ?? 'U', 0, 1)); ?>
                    </div>
                    
                    <div class="customer-info">
                        <div class="customer-name">
                            <?php echo htmlspecialchars(($customer['first_name'] ?? '') . ' ' . ($customer['last_name'] ?? '')); ?>
                            <?php if (($customer['is_vip'] ?? false)): ?>
                                <span class="webops-badge webops-badge-warning">⭐ VIP</span>
                            <?php endif; ?>
                        </div>
                        <div class="customer-email">
                            <?php echo htmlspecialchars($customer['email'] ?? 'No email'); ?>
                        </div>
                        <?php if (!empty($customer['phone'])): ?>
                            <div class="customer-email">
                                📞 <?php echo htmlspecialchars($customer['phone']); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="customer-stats">
                        <div>
                            <div class="customer-stat-value"><?php echo (int)($customer['total_orders'] ?? 0); ?></div>
                            <div class="customer-stat-label">Orders</div>
                        </div>
                        <div>
                            <div class="customer-stat-value">$<?php echo number_format($customer['total_spent'] ?? 0, 2); ?></div>
                            <div class="customer-stat-label">Spent</div>
                        </div>
                        <div>
                            <div class="customer-stat-value">$<?php echo number_format($customer['avg_order_value'] ?? 0, 2); ?></div>
                            <div class="customer-stat-label">Avg Order</div>
                        </div>
                    </div>
                    
                    <div>
                        <button class="webops-btn webops-btn-sm webops-btn-primary" onclick="event.stopPropagation(); viewCustomer(<?php echo $customer['id']; ?>)">
                            View Profile
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Pagination -->
    <?php if (count($customers) >= $filters['limit']): ?>
        <div class="webops-flex webops-justify-between webops-items-center webops-mt-lg">
            <div>
                Showing <?php echo count($customers); ?> customers
            </div>
            <div class="webops-flex webops-gap-sm">
                <?php if ($filters['page'] > 1): ?>
                    <a href="?page=<?php echo $filters['page'] - 1; ?>&segment=<?php echo $filters['segment']; ?>" 
                       class="webops-btn webops-btn-secondary">
                        ← Previous
                    </a>
                <?php endif; ?>
                
                <a href="?page=<?php echo $filters['page'] + 1; ?>&segment=<?php echo $filters['segment']; ?>" 
                   class="webops-btn webops-btn-secondary">
                    Next →
                </a>
            </div>
        </div>
    <?php endif; ?>
    
</div>

<!-- Scripts -->
<script src="../assets/js/api-client.js"></script>
<script>
// View customer profile
function viewCustomer(customerId) {
    window.location.href = `customer-profile.php?id=${customerId}`;
}

// Create new customer
function createNewCustomer() {
    window.location.href = 'customer-create.php';
}

// Export customers
function exportCustomers() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    window.location.href = '?' + params.toString();
}

// Send bulk email campaign
function sendBulkEmail() {
    window.location.href = 'customer-email-campaign.php';
}
</script>

</body>
</html>
