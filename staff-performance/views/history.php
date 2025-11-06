<?php
/**
 * Staff Performance - History View
 *
 * Shows past competition winners and historical leaderboards
 *
 * @package CIS\Modules\StaffPerformance
 * @version 1.0.0
 */

// Get historical data
try {
    // Get leaderboard history (monthly snapshots)
    $stmt = $db->prepare("
        SELECT
            lh.*,
            sa.full_name,
            sa.store_id
        FROM leaderboard_history lh
        JOIN staff_accounts sa ON lh.staff_id = sa.staff_id
        WHERE lh.period_type = 'monthly'
        ORDER BY lh.period_date DESC, lh.rank ASC
        LIMIT 100
    ");
    $stmt->execute();
    $monthlyHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by month
    $historyByMonth = [];
    foreach ($monthlyHistory as $record) {
        $month = date('F Y', strtotime($record['period_date']));
        if (!isset($historyByMonth[$month])) {
            $historyByMonth[$month] = [];
        }
        $historyByMonth[$month][] = $record;
    }

    // Get hall of fame (top performers across all time)
    $stmt = $db->prepare("
        SELECT
            sa.full_name,
            sa.staff_id,
            COUNT(CASE WHEN lh.rank = 1 THEN 1 END) as first_place_count,
            COUNT(CASE WHEN lh.rank <= 3 THEN 1 END) as top_three_count,
            SUM(lh.total_points) as lifetime_points,
            SUM(lh.total_earnings) as lifetime_earnings
        FROM leaderboard_history lh
        JOIN staff_accounts sa ON lh.staff_id = sa.staff_id
        WHERE lh.period_type = 'monthly'
        GROUP BY sa.staff_id
        HAVING first_place_count > 0
        ORDER BY first_place_count DESC, top_three_count DESC, lifetime_points DESC
        LIMIT 20
    ");
    $stmt->execute();
    $hallOfFame = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get competition winners
    $stmt = $db->prepare("
        SELECT
            c.name as competition_name,
            c.competition_type,
            c.end_date,
            c.prize_amount_first,
            c.prize_amount_second,
            c.prize_amount_third,
            cp1.score as first_score,
            cp2.score as second_score,
            cp3.score as third_score,
            sa1.full_name as first_name,
            sa2.full_name as second_name,
            sa3.full_name as third_name
        FROM competitions c
        LEFT JOIN competition_participants cp1 ON c.competition_id = cp1.competition_id AND cp1.rank = 1
        LEFT JOIN competition_participants cp2 ON c.competition_id = cp2.competition_id AND cp2.rank = 2
        LEFT JOIN competition_participants cp3 ON c.competition_id = cp3.competition_id AND cp3.rank = 3
        LEFT JOIN staff_accounts sa1 ON cp1.staff_id = sa1.staff_id
        LEFT JOIN staff_accounts sa2 ON cp2.staff_id = sa2.staff_id
        LEFT JOIN staff_accounts sa3 ON cp3.staff_id = sa3.staff_id
        WHERE c.status = 'completed'
        ORDER BY c.end_date DESC
        LIMIT 50
    ");
    $stmt->execute();
    $competitionWinners = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("History View Error: " . $e->getMessage());
    $historyByMonth = [];
    $hallOfFame = [];
    $competitionWinners = [];
}

$pageTitle = "History";
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
        .hall-of-fame-card {
            background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
            color: white;
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1rem;
            box-shadow: 0 4px 12px rgba(255, 215, 0, 0.3);
        }

        .winner-podium {
            display: flex;
            justify-content: center;
            align-items: flex-end;
            gap: 1rem;
            margin: 2rem 0;
        }

        .podium-place {
            text-align: center;
            padding: 1.5rem;
            border-radius: 0.5rem;
            background: white;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .podium-first {
            order: 2;
            flex-basis: 35%;
            background: linear-gradient(135deg, #FFD700 0%, #FFA500 100%);
            color: white;
            transform: scale(1.1);
        }

        .podium-second {
            order: 1;
            flex-basis: 30%;
            background: linear-gradient(135deg, #C0C0C0 0%, #A8A8A8 100%);
            color: white;
        }

        .podium-third {
            order: 3;
            flex-basis: 30%;
            background: linear-gradient(135deg, #CD7F32 0%, #B87333 100%);
            color: white;
        }

        .timeline-month {
            border-left: 3px solid #667eea;
            padding-left: 2rem;
            margin-bottom: 2rem;
            position: relative;
        }

        .timeline-month::before {
            content: '';
            width: 15px;
            height: 15px;
            background: #667eea;
            border-radius: 50%;
            position: absolute;
            left: -9px;
            top: 0;
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
                <i class="fas fa-history text-info me-2"></i>
                Performance History
            </h1>

            <!-- Hall of Fame -->
            <?php if (!empty($hallOfFame)): ?>
            <div class="mb-5">
                <h2 class="h3 mb-3">
                    <i class="fas fa-crown text-warning me-2"></i>
                    Hall of Fame
                </h2>

                <div class="row">
                    <?php foreach (array_slice($hallOfFame, 0, 3) as $index => $legend): ?>
                    <div class="col-md-4 mb-3">
                        <div class="hall-of-fame-card">
                            <div class="text-center">
                                <div class="h1 mb-2">
                                    <?php echo ['🥇', '🥈', '🥉'][$index]; ?>
                                </div>
                                <h4 class="fw-bold"><?php echo htmlspecialchars($legend['full_name']); ?></h4>
                                <hr class="bg-white">
                                <div class="row text-center">
                                    <div class="col-6">
                                        <div class="h3 mb-0"><?php echo $legend['first_place_count']; ?></div>
                                        <small>1st Places</small>
                                    </div>
                                    <div class="col-6">
                                        <div class="h3 mb-0"><?php echo $legend['top_three_count']; ?></div>
                                        <small>Top 3 Finishes</small>
                                    </div>
                                </div>
                                <hr class="bg-white">
                                <div class="row text-center">
                                    <div class="col-6">
                                        <div class="h5 mb-0"><?php echo number_format($legend['lifetime_points']); ?></div>
                                        <small>Lifetime Points</small>
                                    </div>
                                    <div class="col-6">
                                        <div class="h5 mb-0">$<?php echo number_format($legend['lifetime_earnings'], 0); ?></div>
                                        <small>Total Earnings</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Rest of Hall of Fame -->
                <?php if (count($hallOfFame) > 3): ?>
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Rank</th>
                                <th>Name</th>
                                <th>1st Places</th>
                                <th>Top 3</th>
                                <th>Lifetime Points</th>
                                <th>Total Earnings</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($hallOfFame, 3) as $index => $legend): ?>
                            <tr>
                                <td>#<?php echo $index + 4; ?></td>
                                <td><?php echo htmlspecialchars($legend['full_name']); ?></td>
                                <td><?php echo $legend['first_place_count']; ?></td>
                                <td><?php echo $legend['top_three_count']; ?></td>
                                <td><?php echo number_format($legend['lifetime_points']); ?></td>
                                <td>$<?php echo number_format($legend['lifetime_earnings'], 2); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <!-- Competition Winners -->
            <?php if (!empty($competitionWinners)): ?>
            <div class="mb-5">
                <h2 class="h3 mb-3">
                    <i class="fas fa-trophy text-warning me-2"></i>
                    Competition Winners
                </h2>

                <?php foreach ($competitionWinners as $comp): ?>
                <div class="card mb-3">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><?php echo htmlspecialchars($comp['competition_name']); ?></h5>
                            <span class="badge bg-secondary"><?php echo date('M Y', strtotime($comp['end_date'])); ?></span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="winner-podium">
                            <!-- Second Place -->
                            <?php if ($comp['second_name']): ?>
                            <div class="podium-place podium-second">
                                <div class="h1 mb-2">🥈</div>
                                <div class="fw-bold"><?php echo htmlspecialchars($comp['second_name']); ?></div>
                                <div class="h4 mt-2"><?php echo number_format($comp['second_score']); ?></div>
                                <small>points</small>
                                <div class="mt-2">
                                    <span class="badge bg-light text-dark">$<?php echo number_format($comp['prize_amount_second'], 0); ?></span>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- First Place -->
                            <?php if ($comp['first_name']): ?>
                            <div class="podium-place podium-first">
                                <div class="h1 mb-2">🥇</div>
                                <div class="fw-bold h5"><?php echo htmlspecialchars($comp['first_name']); ?></div>
                                <div class="h3 mt-2"><?php echo number_format($comp['first_score']); ?></div>
                                <small>points</small>
                                <div class="mt-2">
                                    <span class="badge bg-warning text-dark">$<?php echo number_format($comp['prize_amount_first'], 0); ?></span>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Third Place -->
                            <?php if ($comp['third_name']): ?>
                            <div class="podium-place podium-third">
                                <div class="h1 mb-2">🥉</div>
                                <div class="fw-bold"><?php echo htmlspecialchars($comp['third_name']); ?></div>
                                <div class="h4 mt-2"><?php echo number_format($comp['third_score']); ?></div>
                                <small>points</small>
                                <div class="mt-2">
                                    <span class="badge bg-light text-dark">$<?php echo number_format($comp['prize_amount_third'], 0); ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- Monthly Leaderboard History -->
            <?php if (!empty($historyByMonth)): ?>
            <div class="mb-5">
                <h2 class="h3 mb-3">
                    <i class="fas fa-calendar-alt text-primary me-2"></i>
                    Monthly Leaderboard History
                </h2>

                <?php foreach ($historyByMonth as $month => $records): ?>
                <div class="timeline-month">
                    <h4 class="text-primary"><?php echo $month; ?></h4>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Name</th>
                                    <th>Reviews</th>
                                    <th>Drops</th>
                                    <th>Points</th>
                                    <th>Earnings</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach (array_slice($records, 0, 5) as $record): ?>
                                <tr class="<?php echo $record['staff_id'] == $current_user_id ? 'table-primary' : ''; ?>">
                                    <td>
                                        <?php if ($record['rank'] == 1): ?>
                                            🥇 #1
                                        <?php elseif ($record['rank'] == 2): ?>
                                            🥈 #2
                                        <?php elseif ($record['rank'] == 3): ?>
                                            🥉 #3
                                        <?php else: ?>
                                            #<?php echo $record['rank']; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($record['full_name']); ?></td>
                                    <td><?php echo number_format($record['google_reviews_count']); ?></td>
                                    <td><?php echo number_format($record['vape_drops_count']); ?></td>
                                    <td><?php echo number_format($record['total_points']); ?></td>
                                    <td>$<?php echo number_format($record['total_earnings'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- No History -->
            <?php if (empty($historyByMonth) && empty($hallOfFame) && empty($competitionWinners)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                No historical data available yet. Keep performing to build your legacy!
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/views/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
