<?php
/**
 * Flagged Products - Main Index View
 *
 * @package CIS\Modules\FlaggedProducts
 */

$pageTitle = 'Flagged Products - Stock Accuracy Tracking';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($pageTitle); ?> - CIS</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">

    <!-- Module CSS -->
    <link rel="stylesheet" href="/modules/flagged_products/assets/css/styles.css">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="/modules/flagged_products/">
                <i class="fas fa-flag-checkered me-2"></i>Flagged Products
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/modules/flagged_products/?action=cron-dashboard">
                            <i class="fas fa-chart-line me-1"></i>Cron Dashboard
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/"><i class="fas fa-home me-1"></i>Back to CIS</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container-fluid py-4">
        <div class="row mb-4">
            <div class="col-12">
                <h1 class="display-5 mb-2">
                    <i class="fas fa-flag-checkered text-primary me-2"></i>
                    Stock Accuracy Tracking
                </h1>
                <p class="lead text-muted">
                    Track and verify stock discrepancies to maintain accurate inventory levels
                </p>
            </div>
        </div>

        <!-- Cron Dashboard Quick Access -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-primary shadow-sm">
                    <div class="card-body">
                        <div class="row align-items-center">
                            <div class="col-md-8">
                                <h5 class="card-title mb-2">
                                    <i class="fas fa-chart-line text-primary me-2"></i>
                                    Automation Dashboard
                                </h5>
                                <p class="card-text text-muted mb-0">
                                    Monitor cron job performance, view execution history, and track system health in real-time
                                </p>
                            </div>
                            <div class="col-md-4 text-md-end mt-3 mt-md-0">
                                <a href="/modules/flagged_products/?action=cron-dashboard" class="btn btn-primary btn-lg">
                                    <i class="fas fa-tachometer-alt me-2"></i>View Dashboard
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Outlet Selection Grid -->
        <div class="row g-4">
            <?php foreach ($outlets as $outlet): ?>
                <div class="col-md-6 col-lg-4 col-xl-3">
                    <div class="card outlet-card h-100 shadow-sm">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-3">
                                <div>
                                    <h5 class="card-title mb-1">
                                        <?php echo htmlspecialchars($outlet['name']); ?>
                                    </h5>
                                    <p class="text-muted small mb-0">
                                        <i class="fas fa-store me-1"></i>
                                        <?php echo htmlspecialchars($outlet['store_code'] ?? 'N/A'); ?>
                                    </p>
                                </div>
                                <div class="outlet-icon">
                                    <i class="fas fa-store-alt fa-2x text-primary opacity-25"></i>
                                </div>
                            </div>

                            <div class="d-grid">
                                <a href="/modules/flagged_products/?action=outlet&id=<?php echo urlencode($outlet['id']); ?>"
                                   class="btn btn-primary">
                                    <i class="fas fa-arrow-right me-2"></i>View Flagged Items
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>

            <?php if (empty($outlets)): ?>
                <div class="col-12">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        No outlets found. Please contact your administrator.
                    </div>
                </div>
            <?php endif; ?>
        </div>

        <!-- Info Section -->
        <div class="row mt-5">
            <div class="col-lg-4">
                <div class="card border-primary">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-question-circle text-primary me-2"></i>
                            What are Flagged Products?
                        </h5>
                        <p class="card-text">
                            Products flagged during daily stock takes when physical counts don't match system records.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-success">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-clipboard-check text-success me-2"></i>
                            How to Use
                        </h5>
                        <p class="card-text">
                            Select an outlet to view pending items, verify counts, and mark as complete when resolved.
                        </p>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card border-info">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="fas fa-chart-line text-info me-2"></i>
                            Track Accuracy
                        </h5>
                        <p class="card-text">
                            Monitor stock accuracy trends and identify products that frequently have discrepancies.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Module JS -->
    <script src="/modules/flagged_products/assets/js/app.js"></script>
</body>
</html>
