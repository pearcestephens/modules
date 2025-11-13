<?php
declare(strict_types=1);
/**
 * Store Transfer Balance Analytics
 *
 * Real-time inventory distribution analysis across all outlets
 * - Stock level imbalances
 * - Transfer recommendations
 * - Outlet performance metrics
 * - Integration with Dynamic Pricing & Stock Engine
 *
 * @package CIS\Consignments
 * @version 2.0.0
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/CISTemplate.php';

// Initialize database connection
$db = cis_resolve_pdo();

// ============================================================================
// DATA COLLECTION
// ============================================================================

/**
 * Get all active outlets with stock levels
 */
function getOutletStockLevels(PDO $db): array {
    $query = "
        SELECT
            vo.id AS outlet_id,
            vo.name AS outlet_name,
            vo.store_code,
            vo.physical_city,
            COUNT(DISTINCT vi.product_id) AS unique_products,
            SUM(vi.current_amount) AS total_stock_units,
            SUM(vi.current_amount * vp.retail_price) AS total_stock_value
        FROM vend_outlets vo
        LEFT JOIN vend_inventory vi ON vi.outlet_id = vo.id
        LEFT JOIN vend_products vp ON vp.id = vi.product_id
        WHERE vo.deleted_at = '0000-00-00 00:00:00'
        GROUP BY vo.id
        ORDER BY vo.name ASC
    ";

    $stmt = $db->query($query);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get product distribution imbalances
 * Products that have uneven distribution across outlets
 */
function getStockImbalances(PDO $db, int $limit = 50): array {
    $query = "
        SELECT
            vp.id AS product_id,
            vp.sku,
            vp.name AS product_name,
            vp.retail_price,
            COUNT(DISTINCT vi.outlet_id) AS outlets_with_stock,
            SUM(vi.current_amount) AS total_stock,
            MAX(vi.current_amount) AS max_stock_at_outlet,
            MIN(vi.current_amount) AS min_stock_at_outlet,
            (MAX(vi.current_amount) - MIN(vi.current_amount)) AS stock_variance,
            STDDEV(vi.current_amount) AS stock_stddev
        FROM vend_products vp
        INNER JOIN vend_inventory vi ON vi.product_id = vp.id
        WHERE vp.deleted_at = '0000-00-00 00:00:00'
        AND vi.current_amount > 0
        GROUP BY vp.id
        HAVING stock_variance > 10
        ORDER BY stock_stddev DESC
        LIMIT ?
    ";

    $stmt = $db->prepare($query);
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get recent transfer activity
 */
function getRecentTransfers(PDO $db, int $limit = 20): array {
    $query = "
        SELECT
            t.id,
            t.public_id,
            t.consignment_category,
            t.state,
            t.created_at,
            t.updated_at,
            vof.name AS from_outlet,
            vot.name AS to_outlet,
            COUNT(ti.id) AS item_count
        FROM transfers t
        LEFT JOIN vend_outlets vof ON vof.id = t.outlet_from
        LEFT JOIN vend_outlets vot ON vot.id = t.outlet_to
        LEFT JOIN transfer_items ti ON ti.transfer_id = t.id
        WHERE t.consignment_category != 'PURCHASE_ORDER'
        GROUP BY t.id
        ORDER BY t.created_at DESC
        LIMIT ?
    ";

    $stmt = $db->prepare($query);
    $stmt->execute([$limit]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

/**
 * Get transfer statistics
 */
function getTransferStats(PDO $db): array {
    $query = "
        SELECT
            COUNT(*) AS total_transfers,
            SUM(CASE WHEN state IN ('OPEN', 'SENT') THEN 1 ELSE 0 END) AS pending_transfers,
            SUM(CASE WHEN state = 'RECEIVED' THEN 1 ELSE 0 END) AS completed_transfers,
            SUM(CASE WHEN state = 'CANCELLED' THEN 1 ELSE 0 END) AS cancelled_transfers,
            COUNT(DISTINCT outlet_from) AS active_source_outlets,
            COUNT(DISTINCT outlet_to) AS active_destination_outlets
        FROM transfers
        WHERE consignment_category != 'PURCHASE_ORDER'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ";

    $stmt = $db->query($query);
    return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
}

// Fetch all data
$outlets = getOutletStockLevels($db);
$imbalances = getStockImbalances($db);
$recentTransfers = getRecentTransfers($db);
$stats = getTransferStats($db);

// Calculate totals
$totalStockValue = array_sum(array_column($outlets, 'total_stock_value'));
$totalStockUnits = array_sum(array_column($outlets, 'total_stock_units'));
$avgStockPerOutlet = count($outlets) > 0 ? $totalStockUnits / count($outlets) : 0;

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Store Transfer Balance Analytics | CIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    <style>
        :root {
            --primary: #667eea;
            --secondary: #764ba2;
            --success: #43e97b;
            --warning: #fa709a;
            --danger: #dc3545;
            --info: #4facfe;
        }

        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }

        .main-container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 30px;
        }

        .stat-card {
            background: linear-gradient(135deg, var(--primary) 0%, var(--secondary) 100%);
            color: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
            transition: transform 0.3s ease;
        }

        .stat-card:hover {
            transform: translateY(-5px);
        }

        .stat-card h3 {
            font-size: 2.5rem;
            font-weight: 700;
            margin: 0;
        }

        .stat-card p {
            margin: 0;
            opacity: 0.9;
            font-size: 0.95rem;
        }

        .table-container {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }

        .table-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 20px;
        }

        .table-header h4 {
            margin: 0;
            color: var(--primary);
            font-weight: 600;
        }

        .badge-state {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }

        .badge-open { background: #ffd700; color: #000; }
        .badge-sent { background: #4facfe; color: #fff; }
        .badge-received { background: #43e97b; color: #fff; }
        .badge-cancelled { background: #dc3545; color: #fff; }

        .progress-bar-custom {
            height: 8px;
            border-radius: 10px;
            background: #e9ecef;
            overflow: hidden;
        }

        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, var(--primary), var(--secondary));
            transition: width 0.3s ease;
        }

        .outlet-card {
            border: 2px solid #e9ecef;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
            transition: all 0.3s ease;
        }

        .outlet-card:hover {
            border-color: var(--primary);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.2);
        }

        .imbalance-indicator {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }

        .imbalance-high { background: var(--danger); }
        .imbalance-medium { background: var(--warning); }
        .imbalance-low { background: var(--success); }

        .btn-gradient {
            background: linear-gradient(135deg, var(--primary), var(--secondary));
            border: none;
            color: white;
            padding: 10px 25px;
            border-radius: 25px;
            font-weight: 500;
            transition: all 0.3s ease;
        }

        .btn-gradient:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="main-container">
            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <div>
                    <h1 class="mb-2"><i class="fas fa-balance-scale me-2"></i>Store Transfer Balance</h1>
                    <p class="text-muted mb-0">Real-time inventory distribution analysis & transfer recommendations</p>
                </div>
                <div>
                    <a href="/modules/consignments/transfer-manager.php" class="btn btn-gradient">
                        <i class="fas fa-th-large me-2"></i>Transfer Manager
                    </a>
                </div>
            </div>

            <!-- Stats Cards Row -->
            <div class="row g-4 mb-4">
                <div class="col-md-3">
                    <div class="stat-card">
                        <p class="mb-1">Total Stock Value</p>
                        <h3>$<?= number_format($totalStockValue, 0) ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card" style="background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%);">
                        <p class="mb-1">Total Stock Units</p>
                        <h3><?= number_format($totalStockUnits, 0) ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card" style="background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);">
                        <p class="mb-1">Active Outlets</p>
                        <h3><?= count($outlets) ?></h3>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-card" style="background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);">
                        <p class="mb-1">Pending Transfers</p>
                        <h3><?= $stats['pending_transfers'] ?? 0 ?></h3>
                    </div>
                </div>
            </div>

            <!-- Transfer Activity Stats -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="table-container">
                        <div class="table-header">
                            <h4><i class="fas fa-chart-line me-2"></i>Transfer Activity (Last 30 Days)</h4>
                        </div>
                        <div class="row text-center">
                            <div class="col-md-3">
                                <h4 class="text-primary"><?= $stats['total_transfers'] ?? 0 ?></h4>
                                <small class="text-muted">Total Transfers</small>
                            </div>
                            <div class="col-md-3">
                                <h4 class="text-success"><?= $stats['completed_transfers'] ?? 0 ?></h4>
                                <small class="text-muted">Completed</small>
                            </div>
                            <div class="col-md-3">
                                <h4 class="text-warning"><?= $stats['pending_transfers'] ?? 0 ?></h4>
                                <small class="text-muted">Pending</small>
                            </div>
                            <div class="col-md-3">
                                <h4 class="text-danger"><?= $stats['cancelled_transfers'] ?? 0 ?></h4>
                                <small class="text-muted">Cancelled</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Outlet Stock Distribution -->
            <div class="table-container mb-4">
                <div class="table-header">
                    <h4><i class="fas fa-store me-2"></i>Outlet Stock Distribution</h4>
                    <button class="btn btn-sm btn-outline-primary" onclick="location.reload()">
                        <i class="fas fa-sync-alt me-2"></i>Refresh
                    </button>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Outlet</th>
                                <th>City</th>
                                <th>Unique Products</th>
                                <th>Total Units</th>
                                <th>Stock Value</th>
                                <th>Distribution %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($outlets as $outlet):
                                $distPct = $totalStockUnits > 0 ? ($outlet['total_stock_units'] / $totalStockUnits) * 100 : 0;
                            ?>
                            <tr>
                                <td><strong><?= htmlspecialchars($outlet['outlet_name']) ?></strong></td>
                                <td><?= htmlspecialchars($outlet['physical_city'] ?? '-') ?></td>
                                <td><?= number_format($outlet['unique_products']) ?></td>
                                <td><?= number_format($outlet['total_stock_units']) ?></td>
                                <td>$<?= number_format($outlet['total_stock_value'], 2) ?></td>
                                <td>
                                    <div class="progress-bar-custom">
                                        <div class="progress-fill" style="width: <?= $distPct ?>%"></div>
                                    </div>
                                    <small class="text-muted"><?= number_format($distPct, 1) ?>%</small>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Stock Imbalances -->
            <div class="table-container mb-4">
                <div class="table-header">
                    <h4><i class="fas fa-exclamation-triangle me-2"></i>Stock Distribution Imbalances</h4>
                    <span class="badge bg-warning text-dark"><?= count($imbalances) ?> Products</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Severity</th>
                                <th>SKU</th>
                                <th>Product</th>
                                <th>Total Stock</th>
                                <th>Max at Outlet</th>
                                <th>Min at Outlet</th>
                                <th>Variance</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($imbalances as $item):
                                $severity = $item['stock_variance'] > 50 ? 'high' : ($item['stock_variance'] > 20 ? 'medium' : 'low');
                            ?>
                            <tr>
                                <td>
                                    <span class="imbalance-indicator imbalance-<?= $severity ?>"></span>
                                    <small class="text-muted"><?= ucfirst($severity) ?></small>
                                </td>
                                <td><code><?= htmlspecialchars($item['sku']) ?></code></td>
                                <td><?= htmlspecialchars($item['product_name']) ?></td>
                                <td><?= number_format($item['total_stock']) ?></td>
                                <td><span class="badge bg-success"><?= number_format($item['max_stock_at_outlet']) ?></span></td>
                                <td><span class="badge bg-danger"><?= number_format($item['min_stock_at_outlet']) ?></span></td>
                                <td><?= number_format($item['stock_variance']) ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary" onclick="createBalancingTransfer('<?= $item['product_id'] ?>')">
                                        <i class="fas fa-exchange-alt me-1"></i>Balance
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Recent Transfers -->
            <div class="table-container">
                <div class="table-header">
                    <h4><i class="fas fa-history me-2"></i>Recent Transfers</h4>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>Transfer ID</th>
                                <th>From</th>
                                <th>To</th>
                                <th>Items</th>
                                <th>Status</th>
                                <th>Created</th>
                                <th>Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentTransfers as $transfer): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($transfer['public_id']) ?></code></td>
                                <td><?= htmlspecialchars($transfer['from_outlet'] ?? '-') ?></td>
                                <td><?= htmlspecialchars($transfer['to_outlet'] ?? '-') ?></td>
                                <td><?= $transfer['item_count'] ?></td>
                                <td>
                                    <?php
                                    $stateClass = strtolower($transfer['state']);
                                    $stateClass = in_array($stateClass, ['open', 'sent', 'received', 'cancelled']) ? $stateClass : 'open';
                                    ?>
                                    <span class="badge-state badge-<?= $stateClass ?>">
                                        <?= htmlspecialchars($transfer['state']) ?>
                                    </span>
                                </td>
                                <td><?= date('M j, Y', strtotime($transfer['created_at'])) ?></td>
                                <td>
                                    <a href="/modules/consignments/transfer-manager.php?id=<?= $transfer['id'] ?>" class="btn btn-sm btn-outline-secondary">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Footer -->
            <div class="text-center mt-4 text-muted">
                <small>Last updated: <?= date('Y-m-d H:i:s') ?> | <i class="fas fa-database me-1"></i>Connected to CIS Database</small>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function createBalancingTransfer(productId) {
            if (confirm('Create an automatic balancing transfer for this product?')) {
                // Integrate with Dynamic Pricing & Stock Engine via API
                const formData = new FormData();
                formData.append('product_id', productId);
                formData.append('action', 'create_balancing_transfer');

                fetch('/modules/consignments/api/dynamic-pricing/optimize.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        alert('✅ Balancing transfer created!\n\n' +
                              'Transfer ID: ' + data.transfer_id + '\n' +
                              'From: ' + data.from_outlet + '\n' +
                              'To: ' + data.to_outlet + '\n' +
                              'Quantity: ' + data.quantity + '\n\n' +
                              'Routing via Dynamic Pricing & Stock Engine\n' +
                              'Optimization: ' + data.optimization_notes);
                        location.reload();
                    } else {
                        alert('❌ Failed to create transfer: ' + (data.error || 'Unknown error'));
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('❌ Error creating transfer: ' + error.message);
                });
            }
        }

        // Auto-refresh every 5 minutes
        setTimeout(() => location.reload(), 300000);
    </script>
</body>
</html>
