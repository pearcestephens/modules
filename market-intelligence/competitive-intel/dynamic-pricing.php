<?php
/**
 * Dynamic Pricing Dashboard
 *
 * Review and approve AI-generated pricing recommendations
 * Apply approved prices to Vend
 * Track pricing performance
 *
 * @package CIS\Modules\CompetitiveIntel
 * @version 1.0.0
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/services/crawlers/DynamicPricingEngine.php';

use CIS\Crawlers\DynamicPricingEngine;

// Handle actions
if (isset($_POST['action'])) {
    header('Content-Type: application/json');
    $engine = new DynamicPricingEngine($db);

    switch ($_POST['action']) {
        case 'approve':
            $recId = intval($_POST['id']);
            $userId = 1; // TODO: Get from session
            $success = $engine->approveRecommendation($recId, $userId);
            echo json_encode(['success' => $success]);
            exit;

        case 'reject':
            $recId = intval($_POST['id']);
            $stmt = $db->prepare("UPDATE dynamic_pricing_recommendations SET status = 'rejected' WHERE id = ?");
            $success = $stmt->execute([$recId]);
            echo json_encode(['success' => $success]);
            exit;
    }
}

// Get pending recommendations
$stmt = $db->query("
    SELECT * FROM dynamic_pricing_recommendations
    WHERE status = 'pending'
    ORDER BY confidence_score DESC, ABS(price_change_percent) DESC
    LIMIT 50
");
$pendingRecs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get approved (not yet applied)
$stmt = $db->query("
    SELECT * FROM dynamic_pricing_recommendations
    WHERE status = 'approved' AND applied_at IS NULL
    ORDER BY generated_at DESC
    LIMIT 20
");
$approvedRecs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get recent applied
$stmt = $db->query("
    SELECT * FROM dynamic_pricing_recommendations
    WHERE status = 'applied'
    ORDER BY applied_at DESC
    LIMIT 20
");
$appliedRecs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get stats
$stmt = $db->query("
    SELECT
        COUNT(*) as total,
        COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
        COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
        COUNT(CASE WHEN status = 'applied' THEN 1 END) as applied,
        COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected,
        AVG(confidence_score) as avg_confidence,
        AVG(CASE WHEN status = 'applied' THEN ABS(price_change_percent) END) as avg_applied_change
    FROM dynamic_pricing_recommendations
    WHERE generated_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$stats = $stmt->fetch(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dynamic Pricing - Competitive Intelligence</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #0f1419; color: #e4e6eb; }
        .card { background: #1a1f2e; border: 1px solid #2d3748; }
        .table { color: #e4e6eb; font-size: 0.9rem; }
        .table thead th { border-bottom: 2px solid #2d3748; }
        .table tbody td { border-bottom: 1px solid #2d3748; }
        .price-increase { color: #00c851; font-weight: bold; }
        .price-decrease { color: #ff4444; font-weight: bold; }
        .confidence-high { color: #00c851; }
        .confidence-medium { color: #ffc107; }
        .confidence-low { color: #ff4444; }
        .recommendation-card { border-left: 4px solid #0066ff; padding: 15px; margin-bottom: 10px; background: #242b3d; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="admin.php">
                <i class="bi bi-arrow-left"></i> Back to Control Panel
            </a>
            <span class="navbar-brand mb-0 h1">
                <i class="bi bi-currency-dollar"></i> Dynamic Pricing
            </span>
        </div>
    </nav>

    <div class="container-fluid py-4">

        <!-- Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h3><?php echo $stats['pending'] ?? 0; ?></h3>
                        <small class="text-muted">Pending</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h3><?php echo $stats['approved'] ?? 0; ?></h3>
                        <small class="text-muted">Approved</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h3><?php echo $stats['applied'] ?? 0; ?></h3>
                        <small class="text-muted">Applied</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h3><?php echo $stats['rejected'] ?? 0; ?></h3>
                        <small class="text-muted">Rejected</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h3><?php echo round($stats['avg_confidence'] ?? 0); ?>%</h3>
                        <small class="text-muted">Avg Confidence</small>
                    </div>
                </div>
            </div>
            <div class="col-md-2">
                <div class="card text-center">
                    <div class="card-body">
                        <h3><?php echo round($stats['avg_applied_change'] ?? 0, 1); ?>%</h3>
                        <small class="text-muted">Avg Change</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Pending Recommendations -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-clock"></i> Pending Recommendations (Requires Review)
                        </h5>

                        <?php if (empty($pendingRecs)): ?>
                            <div class="alert alert-info">
                                <i class="bi bi-info-circle"></i> No pending recommendations. Run pricing engine to generate new recommendations.
                            </div>
                        <?php else: ?>
                            <?php foreach ($pendingRecs as $rec): ?>
                                <div class="recommendation-card">
                                    <div class="row align-items-center">
                                        <div class="col-md-4">
                                            <h6 class="mb-1"><?php echo htmlspecialchars($rec['product_name']); ?></h6>
                                            <small class="text-muted">Product ID: <?php echo $rec['product_id']; ?></small>
                                        </div>
                                        <div class="col-md-2 text-center">
                                            <div class="mb-1">Current</div>
                                            <h5 class="mb-0">$<?php echo number_format($rec['current_price'], 2); ?></h5>
                                        </div>
                                        <div class="col-md-1 text-center">
                                            <i class="bi bi-arrow-right fs-3"></i>
                                        </div>
                                        <div class="col-md-2 text-center">
                                            <div class="mb-1">Recommended</div>
                                            <h5 class="mb-0 <?php echo $rec['price_change_percent'] > 0 ? 'price-increase' : 'price-decrease'; ?>">
                                                $<?php echo number_format($rec['recommended_price'], 2); ?>
                                            </h5>
                                            <small class="<?php echo $rec['price_change_percent'] > 0 ? 'price-increase' : 'price-decrease'; ?>">
                                                <?php echo $rec['price_change_percent'] > 0 ? '+' : ''; ?><?php echo $rec['price_change_percent']; ?>%
                                            </small>
                                        </div>
                                        <div class="col-md-2 text-center">
                                            <div class="mb-1">Confidence</div>
                                            <h5 class="mb-0 confidence-<?php
                                                echo $rec['confidence_score'] >= 80 ? 'high' : ($rec['confidence_score'] >= 60 ? 'medium' : 'low');
                                            ?>">
                                                <?php echo round($rec['confidence_score']); ?>%
                                            </h5>
                                        </div>
                                        <div class="col-md-1 text-end">
                                            <button class="btn btn-sm btn-success me-1" onclick="approve(<?php echo $rec['id']; ?>)" title="Approve">
                                                <i class="bi bi-check-lg"></i>
                                            </button>
                                            <button class="btn btn-sm btn-danger" onclick="reject(<?php echo $rec['id']; ?>)" title="Reject">
                                                <i class="bi bi-x-lg"></i>
                                            </button>
                                        </div>
                                    </div>
                                    <div class="row mt-2">
                                        <div class="col-12">
                                            <small class="text-muted">
                                                <i class="bi bi-info-circle"></i> <?php echo htmlspecialchars($rec['reason']); ?>
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>

                    </div>
                </div>
            </div>
        </div>

        <!-- Approved (Not Applied) -->
        <?php if (!empty($approvedRecs)): ?>
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-success">
                    <div class="card-body">
                        <h5 class="card-title text-success">
                            <i class="bi bi-check-circle"></i> Approved (Ready to Apply)
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Current</th>
                                        <th>New Price</th>
                                        <th>Change</th>
                                        <th>Approved</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($approvedRecs as $rec): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($rec['product_name']); ?></td>
                                        <td>$<?php echo number_format($rec['current_price'], 2); ?></td>
                                        <td><strong>$<?php echo number_format($rec['recommended_price'], 2); ?></strong></td>
                                        <td>
                                            <span class="<?php echo $rec['price_change_percent'] > 0 ? 'price-increase' : 'price-decrease'; ?>">
                                                <?php echo $rec['price_change_percent'] > 0 ? '+' : ''; ?><?php echo $rec['price_change_percent']; ?>%
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, g:i A', strtotime($rec['reviewed_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Recently Applied -->
        <?php if (!empty($appliedRecs)): ?>
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-check2-all"></i> Recently Applied
                        </h5>
                        <div class="table-responsive">
                            <table class="table table-sm">
                                <thead>
                                    <tr>
                                        <th>Product</th>
                                        <th>Old Price</th>
                                        <th>New Price</th>
                                        <th>Change</th>
                                        <th>Applied</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($appliedRecs as $rec): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($rec['product_name']); ?></td>
                                        <td>$<?php echo number_format($rec['current_price'], 2); ?></td>
                                        <td>$<?php echo number_format($rec['recommended_price'], 2); ?></td>
                                        <td>
                                            <span class="<?php echo $rec['price_change_percent'] > 0 ? 'price-increase' : 'price-decrease'; ?>">
                                                <?php echo $rec['price_change_percent'] > 0 ? '+' : ''; ?><?php echo $rec['price_change_percent']; ?>%
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, g:i A', strtotime($rec['applied_at'])); ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function approve(id) {
            if (!confirm('Approve this pricing recommendation?')) return;

            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=approve&id=${id}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Failed to approve recommendation');
                }
            });
        }

        function reject(id) {
            if (!confirm('Reject this pricing recommendation?')) return;

            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: `action=reject&id=${id}`
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    location.reload();
                } else {
                    alert('Failed to reject recommendation');
                }
            });
        }
    </script>
</body>
</html>
