<?php
/**
 * Competitive Intelligence - Master Control Panel
 *
 * Central dashboard for monitoring and controlling:
 * - Daily competitive price monitoring
 * - Dynamic pricing recommendations
 * - Crawler logs and performance
 * - Chrome session management
 *
 * @package CIS\Modules\CompetitiveIntel
 * @version 1.0.0
 */

// Database connection
require_once $_SERVER['DOCUMENT_ROOT'] . '/config/database.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/services/crawlers/CentralLogger.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/services/crawlers/CompetitiveIntelCrawler.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/services/crawlers/DynamicPricingEngine.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/services/crawlers/ChromeSessionManager.php';

use CIS\Crawlers\CentralLogger;
use CIS\Crawlers\CompetitiveIntelCrawler;
use CIS\Crawlers\DynamicPricingEngine;
use CIS\Crawlers\ChromeSessionManager;

// Handle AJAX actions
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        case 'run_competitive_scan':
            $crawler = new CompetitiveIntelCrawler($db);
            $results = $crawler->executeDailyScan();
            echo json_encode(['success' => true, 'results' => $results]);
            exit;

        case 'generate_pricing':
            $engine = new DynamicPricingEngine($db);
            $recommendations = $engine->generateRecommendations();
            echo json_encode(['success' => true, 'count' => count($recommendations)]);
            exit;

        case 'approve_recommendation':
            $engine = new DynamicPricingEngine($db);
            $recId = intval($_POST['recommendation_id']);
            $userId = 1; // TODO: Get from session
            $success = $engine->approveRecommendation($recId, $userId);
            echo json_encode(['success' => $success]);
            exit;

        case 'apply_approved':
            $engine = new DynamicPricingEngine($db);
            $results = $engine->applyApprovedRecommendations();
            echo json_encode(['success' => true, 'results' => $results]);
            exit;

        case 'get_stats':
            $stats = getSystemStats($db);
            echo json_encode(['success' => true, 'stats' => $stats]);
            exit;
    }
}

// Get system stats
function getSystemStats($db) {
    $stats = [];

    // Crawler stats
    $stmt = $db->query("
        SELECT
            COUNT(DISTINCT session_id) as total_sessions,
            COUNT(*) as total_logs,
            COUNT(CASE WHEN level = 'error' THEN 1 END) as error_count,
            MAX(timestamp) as last_run
        FROM crawler_logs
        WHERE crawler_type = 'competitive_intel'
        AND timestamp > DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stats['crawler'] = $stmt->fetch(PDO::FETCH_ASSOC);

    // Price tracking stats
    $stmt = $db->query("
        SELECT
            COUNT(*) as total_products,
            COUNT(DISTINCT competitor_name) as competitors,
            COUNT(CASE WHEN special_offer = TRUE THEN 1 END) as specials,
            MAX(scraped_at) as last_scraped
        FROM competitive_prices
        WHERE scraped_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $stats['prices'] = $stmt->fetch(PDO::FETCH_ASSOC);

    // Pricing recommendations
    $stmt = $db->query("
        SELECT
            COUNT(*) as total,
            COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending,
            COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved,
            COUNT(CASE WHEN status = 'applied' THEN 1 END) as applied,
            AVG(confidence_score) as avg_confidence
        FROM dynamic_pricing_recommendations
        WHERE generated_at > DATE_SUB(NOW(), INTERVAL 7 DAY)
    ");
    $stats['pricing'] = $stmt->fetch(PDO::FETCH_ASSOC);

    // Chrome sessions
    $stmt = $db->query("
        SELECT
            COUNT(*) as total_profiles,
            COUNT(CASE WHEN banned = FALSE THEN 1 END) as active,
            AVG(success_rate) as avg_success_rate
        FROM chrome_sessions
    ");
    $stats['chrome'] = $stmt->fetch(PDO::FETCH_ASSOC);

    return $stats;
}

$stats = getSystemStats($db);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Competitive Intelligence - Control Panel</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body { background: #0f1419; color: #e4e6eb; }
        .card { background: #1a1f2e; border: 1px solid #2d3748; }
        .stat-card { transition: transform 0.2s; }
        .stat-card:hover { transform: translateY(-5px); }
        .btn-primary { background: #0066ff; border: none; }
        .btn-success { background: #00c851; border: none; }
        .btn-danger { background: #ff4444; border: none; }
        .badge-pending { background: #ffc107; }
        .badge-approved { background: #28a745; }
        .badge-applied { background: #17a2b8; }
        .progress { background: #2d3748; }
        .spinner-border { width: 1rem; height: 1rem; border-width: 0.15em; }
    </style>
</head>
<body>
    <nav class="navbar navbar-dark bg-dark">
        <div class="container-fluid">
            <span class="navbar-brand mb-0 h1">
                <i class="bi bi-graph-up"></i> Competitive Intelligence Control Panel
            </span>
            <span class="text-light">
                <i class="bi bi-clock"></i> <?php echo date('l, F j, Y g:i A'); ?>
            </span>
        </div>
    </nav>

    <div class="container-fluid py-4">

        <!-- System Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">
                            <i class="bi bi-gear-fill"></i> Crawler Status
                        </h6>
                        <h2 class="mb-0"><?php echo $stats['crawler']['total_sessions'] ?? 0; ?></h2>
                        <small class="text-muted">Sessions (7 days)</small>
                        <?php if ($stats['crawler']['error_count'] > 0): ?>
                            <div class="mt-2">
                                <span class="badge bg-danger"><?php echo $stats['crawler']['error_count']; ?> Errors</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">
                            <i class="bi bi-tags-fill"></i> Products Tracked
                        </h6>
                        <h2 class="mb-0"><?php echo $stats['prices']['total_products'] ?? 0; ?></h2>
                        <small class="text-muted">Last 24 hours</small>
                        <div class="mt-2">
                            <span class="badge bg-info"><?php echo $stats['prices']['competitors'] ?? 0; ?> Competitors</span>
                            <?php if (($stats['prices']['specials'] ?? 0) > 0): ?>
                                <span class="badge bg-warning"><?php echo $stats['prices']['specials']; ?> Specials</span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">
                            <i class="bi bi-currency-dollar"></i> Price Recommendations
                        </h6>
                        <h2 class="mb-0"><?php echo $stats['pricing']['pending'] ?? 0; ?></h2>
                        <small class="text-muted">Pending Review</small>
                        <div class="mt-2">
                            <span class="badge badge-approved"><?php echo $stats['pricing']['approved'] ?? 0; ?> Approved</span>
                            <span class="badge badge-applied"><?php echo $stats['pricing']['applied'] ?? 0; ?> Applied</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card stat-card">
                    <div class="card-body">
                        <h6 class="text-muted mb-2">
                            <i class="bi bi-browser-chrome"></i> Chrome Sessions
                        </h6>
                        <h2 class="mb-0"><?php echo $stats['chrome']['active'] ?? 0; ?></h2>
                        <small class="text-muted">Active Profiles</small>
                        <?php if (isset($stats['chrome']['avg_success_rate'])): ?>
                            <div class="mt-2">
                                <small>Success Rate: <?php echo round($stats['chrome']['avg_success_rate'], 1); ?>%</small>
                                <div class="progress" style="height: 5px;">
                                    <div class="progress-bar bg-success" style="width: <?php echo $stats['chrome']['avg_success_rate']; ?>%"></div>
                                </div>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row g-3 mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title mb-3">
                            <i class="bi bi-lightning-fill"></i> Quick Actions
                        </h5>
                        <div class="btn-group" role="group">
                            <button type="button" class="btn btn-primary" onclick="runCompetitiveScan()">
                                <i class="bi bi-play-circle"></i> Run Competitive Scan Now
                            </button>
                            <button type="button" class="btn btn-success" onclick="generatePricing()">
                                <i class="bi bi-calculator"></i> Generate Pricing Recommendations
                            </button>
                            <button type="button" class="btn btn-info" onclick="applyApproved()">
                                <i class="bi bi-check-circle"></i> Apply Approved Prices
                            </button>
                            <button type="button" class="btn btn-warning" onclick="window.location.href='price-monitor.php'">
                                <i class="bi bi-graph-up-arrow"></i> View Price Monitor
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="window.location.href='crawler-logs.php'">
                                <i class="bi bi-file-text"></i> View Logs
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recent Activity -->
        <div class="row g-3">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-activity"></i> Recent Crawler Activity
                        </h5>
                        <div id="recentActivity">
                            <div class="text-center py-4">
                                <div class="spinner-border text-primary" role="status"></div>
                                <p class="mt-2 mb-0">Loading...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title">
                            <i class="bi bi-exclamation-triangle"></i> Top Price Opportunities
                        </h5>
                        <div id="topOpportunities">
                            <div class="text-center py-4">
                                <div class="spinner-border text-primary" role="status"></div>
                                <p class="mt-2 mb-0">Loading...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Run competitive scan
        function runCompetitiveScan() {
            if (!confirm('This will scan ALL competitors and may take 10-15 minutes. Continue?')) return;

            const btn = event.target.closest('button');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Scanning...';

            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=run_competitive_scan'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(`Scan completed!\n\nProducts found: ${data.results.products_found}\nSpecials: ${data.results.specials_found}`);
                    location.reload();
                } else {
                    alert('Scan failed. Check logs for details.');
                }
            })
            .catch(err => {
                alert('Error: ' + err.message);
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-play-circle"></i> Run Competitive Scan Now';
            });
        }

        // Generate pricing recommendations
        function generatePricing() {
            const btn = event.target.closest('button');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Generating...';

            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=generate_pricing'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(`Generated ${data.count} pricing recommendations!`);
                    location.reload();
                } else {
                    alert('Failed to generate recommendations.');
                }
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-calculator"></i> Generate Pricing Recommendations';
            });
        }

        // Apply approved prices
        function applyApproved() {
            if (!confirm('This will update prices in Vend for all approved recommendations. Continue?')) return;

            const btn = event.target.closest('button');
            btn.disabled = true;
            btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Applying...';

            fetch('', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=apply_approved'
            })
            .then(r => r.json())
            .then(data => {
                if (data.success) {
                    alert(`Applied: ${data.results.applied}\nFailed: ${data.results.failed}`);
                    location.reload();
                } else {
                    alert('Failed to apply prices.');
                }
            })
            .finally(() => {
                btn.disabled = false;
                btn.innerHTML = '<i class="bi bi-check-circle"></i> Apply Approved Prices';
            });
        }

        // Auto-refresh stats every 30 seconds
        setInterval(() => {
            location.reload();
        }, 30000);
    </script>
</body>
</html>
