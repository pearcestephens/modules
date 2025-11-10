<?php
/**
 * Price Monitor Dashboard
 *
 * Real-time competitive price tracking and comparison
 * Shows price history, competitor positioning, and special offers
 *
 * @package CIS\Modules\CompetitiveIntel
 * @version 1.0.0
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';

// Get latest competitive prices
$stmt = $db->query("
    SELECT
        competitor_name,
        COUNT(*) as product_count,
        COUNT(CASE WHEN special_offer = TRUE THEN 1 END) as specials_count,
        AVG(price) as avg_price,
        MAX(scraped_at) as last_updated
    FROM competitive_prices
    WHERE scraped_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    GROUP BY competitor_name
    ORDER BY product_count DESC
");
$competitors = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent specials
$stmt = $db->query("
    SELECT * FROM competitive_specials
    WHERE detected_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
    ORDER BY discount_percent DESC, detected_at DESC
    LIMIT 20
");
$specials = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Price Monitor - Competitive Intelligence</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #0f1419; color: #e4e6eb; }
        .card { background: #1a1f2e; border: 1px solid #2d3748; }
        .table { color: #e4e6eb; }
        .table thead th { border-bottom: 2px solid #2d3748; }
        .table tbody td { border-bottom: 1px solid #2d3748; }
        .badge-special { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .price-up { color: #00c851; }
        .price-down { color: #ff4444; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="admin.php">
                <i class="bi bi-arrow-left"></i> Back to Control Panel
            </a>
            <span class="navbar-brand mb-0 h1">
                <i class="bi bi-graph-up-arrow"></i> Price Monitor
            </span>
        </div>
    </nav>

    <div class="container-fluid py-4">

        <!-- Competitor Overview -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-building"></i> Competitor Overview (Last 24 Hours)
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Competitor</th>
                                        <th>Products Tracked</th>
                                        <th>Specials</th>
                                        <th>Avg Price</th>
                                        <th>Last Updated</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($competitors as $comp): ?>
                                    <tr>
                                        <td>
                                            <strong><?php echo htmlspecialchars($comp['competitor_name']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary"><?php echo $comp['product_count']; ?></span>
                                        </td>
                                        <td>
                                            <?php if ($comp['specials_count'] > 0): ?>
                                                <span class="badge badge-special"><?php echo $comp['specials_count']; ?> Specials</span>
                                            <?php else: ?>
                                                <span class="text-muted">None</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>$<?php echo number_format($comp['avg_price'], 2); ?></td>
                                        <td><?php echo date('g:i A', strtotime($comp['last_updated'])); ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-info" onclick="viewProducts('<?php echo htmlspecialchars($comp['competitor_name']); ?>')">
                                                <i class="bi bi-eye"></i> View Products
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Specials -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-star-fill"></i> Recent Special Offers
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Competitor</th>
                                        <th>Product</th>
                                        <th>Original Price</th>
                                        <th>Sale Price</th>
                                        <th>Discount</th>
                                        <th>Detected</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($specials as $special): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($special['competitor_name']); ?></td>
                                        <td><?php echo htmlspecialchars($special['title']); ?></td>
                                        <td>
                                            <span class="text-decoration-line-through text-muted">
                                                $<?php echo number_format($special['original_price'], 2); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <strong class="text-success">
                                                $<?php echo number_format($special['price'], 2); ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <span class="badge bg-danger">
                                                <?php echo $special['discount_percent']; ?>% OFF
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, g:i A', strtotime($special['detected_at'])); ?></td>
                                        <td>
                                            <?php if ($special['product_url']): ?>
                                                <a href="<?php echo htmlspecialchars($special['product_url']); ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                    <i class="bi bi-box-arrow-up-right"></i> View
                                                </a>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function viewProducts(competitor) {
            // TODO: Implement product detail view
            alert('Product detail view for ' + competitor + ' coming soon!');
        }
    </script>
</body>
</html>
