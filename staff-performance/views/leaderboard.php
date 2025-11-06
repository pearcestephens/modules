<?php
/**
 * Staff Performance - Full Leaderboard View
 *
 * Complete rankings with filters
 *
 * @package CIS\Modules\StaffPerformance
 * @version 1.0.0
 */

// Get filter parameters
$timeframe = $_GET['timeframe'] ?? 'current_month';
$store = $_GET['store'] ?? 'all';

// Build query based on filters
try {
    if ($timeframe === 'current_month') {
        $query = "
            SELECT
                sa.staff_id,
                sa.full_name,
                sa.store_id,
                o.name as store_name,
                COALESCE(COUNT(DISTINCT gr.review_id), 0) as google_reviews,
                COALESCE(COUNT(DISTINCT vd.drop_id), 0) as vape_drops,
                COALESCE(COUNT(DISTINCT gr.review_id) * 10.00, 0) +
                COALESCE(COUNT(DISTINCT vd.drop_id) * 6.00, 0) as earnings,
                COALESCE(COUNT(DISTINCT gr.review_id) * 100, 0) +
                COALESCE(COUNT(DISTINCT vd.drop_id) * 50, 0) as points
            FROM staff_accounts sa
            LEFT JOIN outlets o ON sa.store_id = o.outlet_id
            LEFT JOIN google_reviews gr ON sa.staff_id = gr.staff_id
                AND gr.review_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
            LEFT JOIN vape_drops vd ON sa.staff_id = vd.staff_id
                AND vd.drop_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
            WHERE sa.is_active = 1
        ";

        if ($store !== 'all') {
            $query .= " AND sa.store_id = " . (int)$store;
        }

        $query .= "
            GROUP BY sa.staff_id
            ORDER BY points DESC, earnings DESC
        ";

        $stmt = $db->query($query);
        $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Add ranks
        foreach ($leaderboard as $index => &$row) {
            $row['rank'] = $index + 1;
        }

    } elseif ($timeframe === 'last_month') {
        $stmt = $db->prepare("
            SELECT
                sps.*,
                sa.full_name,
                sa.store_id,
                o.name as store_name,
                sps.google_reviews_count as google_reviews,
                sps.vape_drops_count as vape_drops,
                sps.total_earnings as earnings,
                sps.total_points as points,
                sps.rank_overall as rank
            FROM staff_performance_stats sps
            JOIN staff_accounts sa ON sps.staff_id = sa.staff_id
            LEFT JOIN outlets o ON sa.store_id = o.outlet_id
            WHERE sps.month_year = DATE_FORMAT(DATE_SUB(NOW(), INTERVAL 1 MONTH), '%Y-%m')
                " . ($store !== 'all' ? "AND sa.store_id = " . (int)$store : "") . "
            ORDER BY sps.rank_overall ASC
        ");
        $stmt->execute();
        $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

    } else {
        // All time
        $query = "
            SELECT
                sa.staff_id,
                sa.full_name,
                sa.store_id,
                o.name as store_name,
                COALESCE(COUNT(DISTINCT gr.review_id), 0) as google_reviews,
                COALESCE(COUNT(DISTINCT vd.drop_id), 0) as vape_drops,
                COALESCE(COUNT(DISTINCT gr.review_id) * 10.00, 0) +
                COALESCE(COUNT(DISTINCT vd.drop_id) * 6.00, 0) as earnings,
                COALESCE(COUNT(DISTINCT gr.review_id) * 100, 0) +
                COALESCE(COUNT(DISTINCT vd.drop_id) * 50, 0) as points
            FROM staff_accounts sa
            LEFT JOIN outlets o ON sa.store_id = o.outlet_id
            LEFT JOIN google_reviews gr ON sa.staff_id = gr.staff_id
            LEFT JOIN vape_drops vd ON sa.staff_id = vd.staff_id
            WHERE sa.is_active = 1
        ";

        if ($store !== 'all') {
            $query .= " AND sa.store_id = " . (int)$store;
        }

        $query .= "
            GROUP BY sa.staff_id
            ORDER BY points DESC, earnings DESC
        ";

        $stmt = $db->query($query);
        $leaderboard = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($leaderboard as $index => &$row) {
            $row['rank'] = $index + 1;
        }
    }

    // Get stores for filter
    $stmt = $db->query("SELECT outlet_id, name FROM outlets ORDER BY name");
    $stores = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Leaderboard View Error: " . $e->getMessage());
    $leaderboard = [];
    $stores = [];
}

$pageTitle = "Full Leaderboard";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Staff Performance - CIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.1/css/all.min.css" rel="stylesheet">
    <link href="<?php echo STAFF_PERFORMANCE_CSS_PATH; ?>/style.css" rel="stylesheet">

    <style>
        .rank-badge {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            font-weight: 700;
        }

        .rank-1 { background: linear-gradient(135deg, #FFD700, #FFA500); color: white; font-size: 1.2rem; }
        .rank-2 { background: linear-gradient(135deg, #C0C0C0, #A8A8A8); color: white; font-size: 1.1rem; }
        .rank-3 { background: linear-gradient(135deg, #CD7F32, #B87333); color: white; font-size: 1.1rem; }

        .leaderboard-row:hover {
            background-color: #f8f9fa;
            cursor: pointer;
        }

        .user-row {
            background-color: #e3f2fd !important;
            font-weight: 600;
        }
    </style>
</head>
<body>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/views/header.php'; ?>

<div class="container-fluid mt-4">
    <div class="row">
        <div class="col-md-3 col-lg-2">
            <?php include __DIR__ . '/partials/sidebar.php'; ?>
        </div>

        <div class="col-md-9 col-lg-10">

            <h1 class="mb-4">
                <i class="fas fa-list-ol text-primary me-2"></i>
                Full Leaderboard
            </h1>

            <!-- Filters -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <input type="hidden" name="page" value="leaderboard">

                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-calendar me-1"></i> Timeframe
                            </label>
                            <select name="timeframe" class="form-select" onchange="this.form.submit()">
                                <option value="current_month" <?php echo $timeframe === 'current_month' ? 'selected' : ''; ?>>Current Month</option>
                                <option value="last_month" <?php echo $timeframe === 'last_month' ? 'selected' : ''; ?>>Last Month</option>
                                <option value="all_time" <?php echo $timeframe === 'all_time' ? 'selected' : ''; ?>>All Time</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="fas fa-store me-1"></i> Store
                            </label>
                            <select name="store" class="form-select" onchange="this.form.submit()">
                                <option value="all" <?php echo $store === 'all' ? 'selected' : ''; ?>>All Stores</option>
                                <?php foreach ($stores as $storeOption): ?>
                                <option value="<?php echo $storeOption['outlet_id']; ?>" <?php echo $store == $storeOption['outlet_id'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($storeOption['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Leaderboard Table -->
            <?php if (!empty($leaderboard)): ?>
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th width="80">Rank</th>
                                    <th>Name</th>
                                    <th>Store</th>
                                    <th class="text-center">
                                        <i class="fas fa-star text-warning" data-bs-toggle="tooltip" title="Google Reviews"></i>
                                        <br><small>Reviews</small>
                                    </th>
                                    <th class="text-center">
                                        <i class="fas fa-box text-info" data-bs-toggle="tooltip" title="Vape Drops"></i>
                                        <br><small>Drops</small>
                                    </th>
                                    <th class="text-center">
                                        <i class="fas fa-coins text-primary" data-bs-toggle="tooltip" title="Total Points"></i>
                                        <br><small>Points</small>
                                    </th>
                                    <th class="text-end">
                                        <i class="fas fa-dollar-sign text-success" data-bs-toggle="tooltip" title="Total Earnings"></i>
                                        <br><small>Earnings</small>
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($leaderboard as $row): ?>
                                <tr class="leaderboard-row <?php echo $row['staff_id'] == $current_user_id ? 'user-row' : ''; ?>">
                                    <td>
                                        <div class="rank-badge <?php echo $row['rank'] <= 3 ? 'rank-' . $row['rank'] : ''; ?>"
                                             style="<?php echo $row['rank'] > 3 ? 'background: #e9ecef; color: #495057;' : ''; ?>">
                                            <?php if ($row['rank'] == 1): ?>
                                                🥇
                                            <?php elseif ($row['rank'] == 2): ?>
                                                🥈
                                            <?php elseif ($row['rank'] == 3): ?>
                                                🥉
                                            <?php else: ?>
                                                #<?php echo $row['rank']; ?>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <strong><?php echo htmlspecialchars($row['full_name']); ?></strong>
                                        <?php if ($row['staff_id'] == $current_user_id): ?>
                                            <span class="badge bg-primary ms-2">You</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?php echo htmlspecialchars($row['store_name'] ?? 'Unknown'); ?></small>
                                    </td>
                                    <td class="text-center">
                                        <strong><?php echo number_format($row['google_reviews']); ?></strong>
                                    </td>
                                    <td class="text-center">
                                        <strong><?php echo number_format($row['vape_drops']); ?></strong>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary" style="font-size: 0.9rem;">
                                            <?php echo number_format($row['points']); ?>
                                        </span>
                                    </td>
                                    <td class="text-end">
                                        <strong class="text-success">
                                            $<?php echo number_format($row['earnings'], 2); ?>
                                        </strong>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Stats Summary -->
            <div class="card mt-4">
                <div class="card-body">
                    <div class="row text-center">
                        <div class="col-md-3">
                            <div class="h4 text-primary"><?php echo count($leaderboard); ?></div>
                            <small class="text-muted">Total Participants</small>
                        </div>
                        <div class="col-md-3">
                            <div class="h4 text-warning"><?php echo number_format(array_sum(array_column($leaderboard, 'google_reviews'))); ?></div>
                            <small class="text-muted">Total Reviews</small>
                        </div>
                        <div class="col-md-3">
                            <div class="h4 text-info"><?php echo number_format(array_sum(array_column($leaderboard, 'vape_drops'))); ?></div>
                            <small class="text-muted">Total Drops</small>
                        </div>
                        <div class="col-md-3">
                            <div class="h4 text-success">$<?php echo number_format(array_sum(array_column($leaderboard, 'earnings')), 2); ?></div>
                            <small class="text-muted">Total Paid Out</small>
                        </div>
                    </div>
                </div>
            </div>

            <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                No leaderboard data available for the selected filters.
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/views/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Initialize tooltips
var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
    return new bootstrap.Tooltip(tooltipTriggerEl);
});

// Highlight user's row
document.addEventListener('DOMContentLoaded', function() {
    const userRow = document.querySelector('.user-row');
    if (userRow) {
        userRow.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
});
</script>

</body>
</html>
