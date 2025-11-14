<?php
/**
 * Website Operations - Wholesale B2B Management View
 *
 * Wholesale account management, bulk pricing, and B2B orders
 *
 * @package    WebsiteOperations
 * @version    1.0.0
 * @author     Ecigdis Development Team
 * @date       2025-11-14
 */

require_once __DIR__ . '/../../includes/db_config.php';
require_once __DIR__ . '/../services/WholesaleService.php';
require_once __DIR__ . '/../components/stat-widget.php';

use Modules\WebsiteOperations\Services\WholesaleService;

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
$wholesaleService = new WholesaleService($db);

// Handle filters
$filters = [
    'search' => $_GET['search'] ?? '',
    'status' => $_GET['status'] ?? 'active',
    'tier' => $_GET['tier'] ?? 'all',
    'sort' => $_GET['sort'] ?? 'name',
    'limit' => $_GET['limit'] ?? 50,
    'page' => $_GET['page'] ?? 1
];

// Get accounts and stats
$accounts = $wholesaleService->getWholesaleAccounts($filters);
$stats = $wholesaleService->getWholesaleStats();

$pageTitle = "Wholesale B2B Management";
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
        .wholesale-card {
            background: white;
            border-radius: 0.75rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
            transition: all 0.15s;
            cursor: pointer;
        }
        
        .wholesale-card:hover {
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            transform: translateY(-2px);
        }
        
        .wholesale-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid #e5e7eb;
        }
        
        .wholesale-company {
            font-size: 1.25rem;
            font-weight: 700;
            color: #111827;
            margin-bottom: 0.25rem;
        }
        
        .wholesale-contact {
            color: #6b7280;
            font-size: 0.875rem;
        }
        
        .wholesale-tier {
            display: inline-block;
            padding: 0.25rem 0.75rem;
            border-radius: 9999px;
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        .tier-bronze {
            background: #cd7f32;
            color: white;
        }
        
        .tier-silver {
            background: #c0c0c0;
            color: #333;
        }
        
        .tier-gold {
            background: #ffd700;
            color: #333;
        }
        
        .tier-platinum {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .wholesale-metrics {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 1.5rem;
            margin-top: 1rem;
        }
        
        .metric {
            text-align: center;
        }
        
        .metric-value {
            font-size: 1.5rem;
            font-weight: 700;
            color: #111827;
        }
        
        .metric-label {
            font-size: 0.75rem;
            color: #6b7280;
            text-transform: uppercase;
        }
        
        .credit-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-top: 1rem;
            padding: 0.75rem;
            background: #f9fafb;
            border-radius: 0.5rem;
        }
        
        .credit-bar {
            flex: 1;
            height: 8px;
            background: #e5e7eb;
            border-radius: 9999px;
            overflow: hidden;
        }
        
        .credit-fill {
            height: 100%;
            background: linear-gradient(90deg, #10b981, #3b82f6);
            border-radius: 9999px;
            transition: width 0.3s;
        }
    </style>
</head>
<body class="website-operations-module">

<!-- Header -->
<div class="webops-header">
    <div class="webops-container">
        <h1 class="webops-header-title">
            🏢 Wholesale B2B Management
        </h1>
        <p class="webops-header-subtitle">
            Manage wholesale accounts, pricing tiers, and bulk orders
        </p>
        
        <nav class="webops-nav">
            <a href="dashboard.php" class="webops-nav-link">📊 Dashboard</a>
            <a href="orders.php" class="webops-nav-link">📦 Orders</a>
            <a href="products.php" class="webops-nav-link">🏷️ Products</a>
            <a href="customers.php" class="webops-nav-link">👥 Customers</a>
            <a href="wholesale.php" class="webops-nav-link active">🏢 Wholesale</a>
        </nav>
    </div>
</div>

<!-- Main Content -->
<div class="webops-container">
    
    <!-- Statistics Overview -->
    <div class="webops-mb-lg">
        <?php
        $wholesaleStats = [
            [
                'label' => 'Active Accounts',
                'value' => $stats['active_accounts'] ?? 0,
                'change' => $stats['accounts_change'] ?? null,
                'icon' => 'customers',
                'color' => 'primary',
                'format' => 'number'
            ],
            [
                'label' => 'Monthly Revenue',
                'value' => $stats['monthly_revenue'] ?? 0,
                'change' => $stats['revenue_change'] ?? null,
                'icon' => 'revenue',
                'color' => 'success',
                'format' => 'currency'
            ],
            [
                'label' => 'Avg Order Size',
                'value' => $stats['avg_order_size'] ?? 0,
                'icon' => 'package',
                'color' => 'warning',
                'format' => 'currency'
            ],
            [
                'label' => 'Credit Outstanding',
                'value' => $stats['credit_outstanding'] ?? 0,
                'icon' => 'credit',
                'color' => 'info',
                'format' => 'currency'
            ]
        ];
        renderStatGrid($wholesaleStats, 4);
        ?>
    </div>
    
    <!-- Filters and Search -->
    <div class="webops-card webops-mb-lg">
        <form method="GET" action="" class="webops-grid webops-grid-3">
            <!-- Status Filter -->
            <div class="webops-form-group">
                <label class="webops-label">Account Status</label>
                <select name="status" class="webops-select" onchange="this.form.submit()">
                    <option value="all" <?php echo $filters['status'] === 'all' ? 'selected' : ''; ?>>All Accounts</option>
                    <option value="active" <?php echo $filters['status'] === 'active' ? 'selected' : ''; ?>>✅ Active</option>
                    <option value="pending" <?php echo $filters['status'] === 'pending' ? 'selected' : ''; ?>>⏳ Pending Approval</option>
                    <option value="suspended" <?php echo $filters['status'] === 'suspended' ? 'selected' : ''; ?>>⚠️ Suspended</option>
                    <option value="inactive" <?php echo $filters['status'] === 'inactive' ? 'selected' : ''; ?>>💤 Inactive</option>
                </select>
            </div>
            
            <!-- Tier Filter -->
            <div class="webops-form-group">
                <label class="webops-label">Pricing Tier</label>
                <select name="tier" class="webops-select" onchange="this.form.submit()">
                    <option value="all" <?php echo $filters['tier'] === 'all' ? 'selected' : ''; ?>>All Tiers</option>
                    <option value="bronze" <?php echo $filters['tier'] === 'bronze' ? 'selected' : ''; ?>>🥉 Bronze (5% off)</option>
                    <option value="silver" <?php echo $filters['tier'] === 'silver' ? 'selected' : ''; ?>>🥈 Silver (10% off)</option>
                    <option value="gold" <?php echo $filters['tier'] === 'gold' ? 'selected' : ''; ?>>🥇 Gold (15% off)</option>
                    <option value="platinum" <?php echo $filters['tier'] === 'platinum' ? 'selected' : ''; ?>>💎 Platinum (20% off)</option>
                </select>
            </div>
            
            <!-- Search -->
            <div class="webops-form-group">
                <label class="webops-label">Search</label>
                <div class="webops-flex webops-gap-sm">
                    <input type="text" name="search" class="webops-input" 
                           placeholder="Company name, contact..." 
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
            <a href="?tier=platinum" class="webops-btn webops-btn-sm webops-btn-secondary">💎 Platinum</a>
            <a href="?tier=gold" class="webops-btn webops-btn-sm webops-btn-secondary">🥇 Gold</a>
            <a href="?status=pending" class="webops-btn webops-btn-sm webops-btn-secondary">⏳ Pending</a>
            <a href="?" class="webops-btn webops-btn-sm webops-btn-secondary">🔄 Clear</a>
        </div>
    </div>
    
    <!-- Actions -->
    <div class="webops-flex webops-justify-between webops-items-center webops-mb-md">
        <div>
            <strong><?php echo count($accounts); ?></strong> accounts found
        </div>
        
        <div class="webops-flex webops-gap-sm">
            <button class="webops-btn webops-btn-primary" onclick="createNewAccount()">
                ➕ New Account
            </button>
            <button class="webops-btn webops-btn-secondary" onclick="exportAccounts()">
                📥 Export
            </button>
            <button class="webops-btn webops-btn-secondary" onclick="bulkPriceUpdate()">
                💰 Update Pricing
            </button>
        </div>
    </div>
    
    <!-- Account List -->
    <div class="webops-grid webops-grid-1">
        <?php if (empty($accounts)): ?>
            <div class="webops-alert webops-alert-info">
                <strong>No accounts found</strong>
                <p>Try adjusting your filters or search terms.</p>
            </div>
        <?php else: ?>
            <?php foreach ($accounts as $account): ?>
                <div class="wholesale-card" onclick="viewAccount(<?php echo $account['id']; ?>)">
                    
                    <!-- Header -->
                    <div class="wholesale-header">
                        <div>
                            <div class="wholesale-company">
                                <?php echo htmlspecialchars($account['company_name'] ?? 'Unknown Company'); ?>
                            </div>
                            <div class="wholesale-contact">
                                👤 <?php echo htmlspecialchars(($account['contact_name'] ?? 'No contact')); ?>
                                <?php if (!empty($account['email'])): ?>
                                    | ✉️ <?php echo htmlspecialchars($account['email']); ?>
                                <?php endif; ?>
                                <?php if (!empty($account['phone'])): ?>
                                    | 📞 <?php echo htmlspecialchars($account['phone']); ?>
                                <?php endif; ?>
                            </div>
                        </div>
                        
                        <div>
                            <?php
                            $tier = strtolower($account['pricing_tier'] ?? 'bronze');
                            $discounts = ['bronze' => '5%', 'silver' => '10%', 'gold' => '15%', 'platinum' => '20%'];
                            $discount = $discounts[$tier] ?? '0%';
                            ?>
                            <span class="wholesale-tier tier-<?php echo $tier; ?>">
                                <?php echo strtoupper($tier); ?> (<?php echo $discount; ?> off)
                            </span>
                            
                            <?php
                            $statusMap = [
                                'active' => ['text' => 'Active', 'class' => 'success'],
                                'pending' => ['text' => 'Pending', 'class' => 'warning'],
                                'suspended' => ['text' => 'Suspended', 'class' => 'danger'],
                                'inactive' => ['text' => 'Inactive', 'class' => 'secondary']
                            ];
                            $status = $account['status'] ?? 'active';
                            $statusInfo = $statusMap[$status] ?? ['text' => $status, 'class' => 'secondary'];
                            ?>
                            <span class="webops-badge webops-badge-<?php echo $statusInfo['class']; ?> webops-ml-sm">
                                <?php echo $statusInfo['text']; ?>
                            </span>
                        </div>
                    </div>
                    
                    <!-- Metrics -->
                    <div class="wholesale-metrics">
                        <div class="metric">
                            <div class="metric-value"><?php echo (int)($account['total_orders'] ?? 0); ?></div>
                            <div class="metric-label">Total Orders</div>
                        </div>
                        <div class="metric">
                            <div class="metric-value">$<?php echo number_format($account['total_revenue'] ?? 0, 2); ?></div>
                            <div class="metric-label">Total Revenue</div>
                        </div>
                        <div class="metric">
                            <div class="metric-value">$<?php echo number_format($account['avg_order_value'] ?? 0, 2); ?></div>
                            <div class="metric-label">Avg Order</div>
                        </div>
                        <div class="metric">
                            <div class="metric-value"><?php echo (int)($account['days_since_order'] ?? 0); ?></div>
                            <div class="metric-label">Days Since Order</div>
                        </div>
                    </div>
                    
                    <!-- Credit Status -->
                    <?php if (isset($account['credit_limit']) && $account['credit_limit'] > 0): ?>
                        <div class="credit-status">
                            <div style="flex: 0 0 auto;">
                                <strong>Credit:</strong>
                                $<?php echo number_format($account['credit_used'] ?? 0, 0); ?> / 
                                $<?php echo number_format($account['credit_limit'], 0); ?>
                            </div>
                            <div class="credit-bar">
                                <?php 
                                $creditPercent = min(100, (($account['credit_used'] ?? 0) / $account['credit_limit']) * 100);
                                ?>
                                <div class="credit-fill" style="width: <?php echo $creditPercent; ?>%"></div>
                            </div>
                            <div style="flex: 0 0 auto;">
                                <strong><?php echo number_format($creditPercent, 1); ?>%</strong> used
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <!-- Actions -->
                    <div class="webops-flex webops-gap-sm webops-mt-md">
                        <button class="webops-btn webops-btn-sm webops-btn-primary" 
                                onclick="event.stopPropagation(); viewAccount(<?php echo $account['id']; ?>)">
                            View Details
                        </button>
                        <button class="webops-btn webops-btn-sm webops-btn-secondary" 
                                onclick="event.stopPropagation(); viewOrders(<?php echo $account['id']; ?>)">
                            📦 Orders
                        </button>
                        <button class="webops-btn webops-btn-sm webops-btn-secondary" 
                                onclick="event.stopPropagation(); updatePricing(<?php echo $account['id']; ?>)">
                            💰 Pricing
                        </button>
                        <?php if ($status === 'active'): ?>
                            <button class="webops-btn webops-btn-sm webops-btn-danger" 
                                    onclick="event.stopPropagation(); suspendAccount(<?php echo $account['id']; ?>)">
                                ⚠️ Suspend
                            </button>
                        <?php elseif ($status === 'suspended'): ?>
                            <button class="webops-btn webops-btn-sm webops-btn-success" 
                                    onclick="event.stopPropagation(); reactivateAccount(<?php echo $account['id']; ?>)">
                                ✅ Reactivate
                            </button>
                        <?php endif; ?>
                    </div>
                    
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
    
    <!-- Pagination -->
    <?php if (count($accounts) >= $filters['limit']): ?>
        <div class="webops-flex webops-justify-between webops-items-center webops-mt-lg">
            <div>
                Showing <?php echo count($accounts); ?> accounts
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

<!-- Scripts -->
<script src="../assets/js/api-client.js"></script>
<script>
// View account details
function viewAccount(accountId) {
    window.location.href = `wholesale-account.php?id=${accountId}`;
}

// View account orders
function viewOrders(accountId) {
    window.location.href = `orders.php?wholesale_id=${accountId}`;
}

// Update pricing tier
function updatePricing(accountId) {
    window.location.href = `wholesale-pricing.php?id=${accountId}`;
}

// Suspend account
async function suspendAccount(accountId) {
    if (!confirm('Are you sure you want to suspend this account?')) return;
    
    try {
        await webOpsAPI.updateWholesaleAccount(accountId, { status: 'suspended' });
        webOpsAPI.showToast('Account suspended successfully', 'success');
        location.reload();
    } catch (error) {
        webOpsAPI.showToast('Failed to suspend account', 'error');
    }
}

// Reactivate account
async function reactivateAccount(accountId) {
    if (!confirm('Reactivate this account?')) return;
    
    try {
        await webOpsAPI.updateWholesaleAccount(accountId, { status: 'active' });
        webOpsAPI.showToast('Account reactivated successfully', 'success');
        location.reload();
    } catch (error) {
        webOpsAPI.showToast('Failed to reactivate account', 'error');
    }
}

// Create new account
function createNewAccount() {
    window.location.href = 'wholesale-create.php';
}

// Export accounts
function exportAccounts() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    window.location.href = '?' + params.toString();
}

// Bulk price update
function bulkPriceUpdate() {
    window.location.href = 'wholesale-bulk-pricing.php';
}
</script>

</body>
</html>
