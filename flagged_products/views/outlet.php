<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Flagged Products - <?= htmlspecialchars($outlet['name'] ?? 'Unknown') ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="assets/css/styles.css" rel="stylesheet">
</head>
<body class="bg-light">

    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary shadow-sm mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="fas fa-flag-checkered me-2"></i>
                Flagged Products
            </a>
            <span class="navbar-text text-white">
                <i class="fas fa-store me-2"></i>
                <?= htmlspecialchars($outlet['name'] ?? 'Unknown Outlet') ?>
            </span>
        </div>
    </nav>

    <div class="container-fluid">

        <!-- Back Button -->
        <div class="mb-4">
            <a href="index.php" class="btn btn-outline-primary">
                <i class="fas fa-arrow-left me-2"></i>
                Back to Outlets
            </a>
        </div>

        <!-- Stats Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card stats-card shadow-sm">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            Pending Items
                        </h6>
                        <h2 class="mb-0"><?= $stats['pending_count'] ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card success shadow-sm">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">
                            <i class="fas fa-check-circle me-2"></i>
                            Completed (30 days)
                        </h6>
                        <h2 class="mb-0"><?= $stats['completed_30_days'] ?></h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card <?= $stats['accuracy'] >= 95 ? 'success' : ($stats['accuracy'] >= 85 ? 'warning' : 'danger') ?> shadow-sm">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">
                            <i class="fas fa-chart-line me-2"></i>
                            Accuracy Rate
                        </h6>
                        <h2 class="mb-0"><?= number_format($stats['accuracy'], 1) ?>%</h2>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card stats-card shadow-sm">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">
                            <i class="fas fa-cubes me-2"></i>
                            Total Products Flagged
                        </h6>
                        <h2 class="mb-0"><?= $stats['total_flagged'] ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Accuracy Visualization -->
        <?php if ($stats['completed_30_days'] > 0): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-body">
                <h5 class="card-title mb-3">
                    <i class="fas fa-bullseye me-2"></i>
                    Stock Count Accuracy (Last 30 Days)
                </h5>
                <div class="accuracy-progress">
                    <div class="accuracy-progress-bar <?= $stats['accuracy'] >= 95 ? 'high' : ($stats['accuracy'] >= 85 ? 'medium' : 'low') ?>"
                         style="width: <?= $stats['accuracy'] ?>%">
                        <?= number_format($stats['accuracy'], 1) ?>%
                    </div>
                </div>
                <div class="mt-3 text-muted small">
                    <i class="fas fa-info-circle me-1"></i>
                    <?= $stats['accurate_count'] ?> accurate / <?= $stats['total_completed'] ?> total checks
                    <?php if ($stats['accuracy'] < 95): ?>
                        <span class="ms-3 text-warning">
                            <i class="fas fa-lightbulb me-1"></i>
                            Target: 95% accuracy
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Flagged Products Table -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="fas fa-list-alt me-2"></i>
                    Flagged Products
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($products)): ?>
                <div class="table-responsive">
                    <table class="table product-table table-hover mb-0">
                        <thead>
                            <tr>
                                <th style="width: 10%">SKU</th>
                                <th style="width: 30%">Product Name</th>
                                <th style="width: 15%">Reason</th>
                                <th style="width: 10%">Qty Before</th>
                                <th style="width: 10%">Current Stock</th>
                                <th style="width: 10%">Flagged Date</th>
                                <th style="width: 15%" class="text-center">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($products as $product): ?>
                            <tr data-product-id="<?= $product['product_id'] ?>">
                                <td>
                                    <code class="text-primary"><?= htmlspecialchars($product['sku']) ?></code>
                                </td>
                                <td>
                                    <strong><?= htmlspecialchars($product['product_name']) ?></strong>
                                    <?php if ($product['dummy_product']): ?>
                                        <span class="badge bg-info ms-2">Dummy</span>
                                    <?php endif; ?>
                                    <?php if (!$product['active']): ?>
                                        <span class="badge bg-secondary ms-2">Inactive</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge badge-pending">
                                        <?= htmlspecialchars($product['reason']) ?>
                                    </span>
                                </td>
                                <td>
                                    <strong><?= number_format($product['qty_before']) ?></strong>
                                </td>
                                <td>
                                    <strong class="<?= $product['current_stock'] != $product['qty_before'] ? 'text-danger' : 'text-success' ?>">
                                        <?= number_format($product['current_stock']) ?>
                                    </strong>
                                    <?php if ($product['current_stock'] != $product['qty_before']): ?>
                                        <i class="fas fa-exclamation-circle text-warning ms-1"
                                           title="Stock level has changed"></i>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?= date('d/m/Y H:i', strtotime($product['flagged_datetime'])) ?>
                                    </small>
                                </td>
                                <td class="text-center">
                                    <button class="btn btn-sm btn-complete me-1"
                                            onclick="completeProduct(<?= $product['product_id'] ?>, <?= $product['qty_before'] ?>, <?= $product['current_stock'] ?>)"
                                            title="Mark as Complete">
                                        <i class="fas fa-check me-1"></i>
                                        Complete
                                    </button>
                                    <button class="btn btn-sm btn-delete"
                                            onclick="deleteProduct(<?= $product['product_id'] ?>)"
                                            title="Delete Flag">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-check-circle text-success fa-3x mb-3"></i>
                    <h4>No Flagged Products</h4>
                    <p class="text-muted">All products at this outlet have accurate stock counts!</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Commonly Inaccurate Products -->
        <?php if (!empty($commonly_inaccurate)): ?>
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h5 class="mb-0">
                    <i class="fas fa-exclamation-triangle me-2 text-warning"></i>
                    Commonly Inaccurate Products
                </h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>SKU</th>
                                <th>Product Name</th>
                                <th>Times Flagged</th>
                                <th>Accuracy Rate</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($commonly_inaccurate as $item): ?>
                            <tr>
                                <td><code><?= htmlspecialchars($item['sku']) ?></code></td>
                                <td><?= htmlspecialchars($item['product_name']) ?></td>
                                <td><span class="badge bg-danger"><?= $item['flag_count'] ?></span></td>
                                <td>
                                    <span class="badge <?= $item['accuracy'] >= 95 ? 'badge-accurate' : 'badge-inaccurate' ?>">
                                        <?= number_format($item['accuracy'], 1) ?>%
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="assets/js/app.js"></script>
    <script>
        // Initialize outlet view with outlet ID
        document.addEventListener('DOMContentLoaded', function() {
            const outletId = <?= json_encode($outlet['id'] ?? 0) ?>;
            if (typeof OutletView !== 'undefined') {
                OutletView.init(outletId);
            }
        });
    </script>
</body>
</html>
