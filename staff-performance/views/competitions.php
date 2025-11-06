<?php
/**
 * Staff Performance - Competitions View
 *
 * Shows:
 * - Active weekly/monthly competitions
 * - Current standings
 * - Prize information
 * - Past competition results
 *
 * @package CIS\Modules\StaffPerformance
 * @version 1.0.0
 */

// Initialize widgets
$widgets = new \CIS\StaffPerformance\PerformanceWidgets($db);

// Get competitions data
try {
    // Active competitions
    $stmt = $db->prepare("
        SELECT c.*,
               COUNT(cp.participant_id) as participant_count,
               cp2.score as user_score,
               cp2.rank as user_rank
        FROM competitions c
        LEFT JOIN competition_participants cp ON c.competition_id = cp.competition_id
        LEFT JOIN competition_participants cp2 ON c.competition_id = cp2.competition_id AND cp2.staff_id = ?
        WHERE c.status = 'active' AND c.start_date <= NOW() AND c.end_date >= NOW()
        GROUP BY c.competition_id
        ORDER BY c.end_date ASC
    ");
    $stmt->execute([$current_user_id]);
    $activeCompetitions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Upcoming competitions
    $stmt = $db->prepare("
        SELECT * FROM competitions
        WHERE status = 'pending' AND start_date > NOW()
        ORDER BY start_date ASC
        LIMIT 5
    ");
    $stmt->execute();
    $upcomingCompetitions = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Recently completed competitions
    $stmt = $db->prepare("
        SELECT c.*,
               cp.staff_id, cp.score, cp.rank,
               sa.full_name as winner_name
        FROM competitions c
        LEFT JOIN competition_participants cp ON c.competition_id = cp.competition_id AND cp.rank = 1
        LEFT JOIN staff_accounts sa ON cp.staff_id = sa.staff_id
        WHERE c.status = 'completed'
        ORDER BY c.end_date DESC
        LIMIT 10
    ");
    $stmt->execute();
    $completedCompetitions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Competitions View Error: " . $e->getMessage());
    $activeCompetitions = [];
    $upcomingCompetitions = [];
    $completedCompetitions = [];
}

$pageTitle = "Competitions";
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
                <i class="fas fa-trophy text-warning me-2"></i>
                Competitions
            </h1>

            <!-- Active Competitions -->
            <?php if (!empty($activeCompetitions)): ?>
            <div class="mb-5">
                <h2 class="h4 mb-3">
                    <i class="fas fa-fire text-danger me-2"></i>
                    Active Competitions
                </h2>

                <?php foreach ($activeCompetitions as $comp): ?>
                <div class="card mb-3 shadow-sm">
                    <div class="card-header" style="background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); color: white;">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><?php echo htmlspecialchars($comp['name']); ?></h5>
                            <span class="badge bg-light text-dark">
                                <i class="fas fa-users me-1"></i>
                                <?php echo $comp['participant_count']; ?> participants
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <p class="card-text"><?php echo htmlspecialchars($comp['description']); ?></p>

                        <!-- Competition Details -->
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <strong>Type:</strong> <?php echo ucfirst($comp['competition_type']); ?><br>
                                <strong>Metric:</strong> <?php echo ucfirst(str_replace('_', ' ', $comp['metric'])); ?><br>
                                <strong>Target:</strong> <?php echo $comp['target_value'] ? number_format($comp['target_value']) : 'Highest score wins'; ?>
                            </div>
                            <div class="col-md-6">
                                <strong>Started:</strong> <?php echo date('M j, Y', strtotime($comp['start_date'])); ?><br>
                                <strong>Ends:</strong> <?php echo date('M j, Y g:ia', strtotime($comp['end_date'])); ?><br>
                                <strong>Time Remaining:</strong>
                                <?php
                                $timeRemaining = strtotime($comp['end_date']) - time();
                                $days = floor($timeRemaining / 86400);
                                $hours = floor(($timeRemaining % 86400) / 3600);
                                echo $days > 0 ? "$days days, $hours hrs" : "$hours hrs";
                                ?>
                            </div>
                        </div>

                        <!-- Prizes -->
                        <div class="alert alert-warning mb-3">
                            <strong><i class="fas fa-trophy me-1"></i> Prizes:</strong>
                            🥇 $<?php echo number_format($comp['prize_amount_first'], 0); ?> •
                            🥈 $<?php echo number_format($comp['prize_amount_second'], 0); ?> •
                            🥉 $<?php echo number_format($comp['prize_amount_third'], 0); ?>
                        </div>

                        <!-- User's Standing -->
                        <?php if ($comp['user_score'] !== null): ?>
                        <div class="alert alert-info">
                            <strong>Your Standing:</strong>
                            Rank #<?php echo $comp['user_rank']; ?> with <?php echo number_format($comp['user_score']); ?> points
                        </div>
                        <?php endif; ?>

                        <!-- Top 5 Leaderboard -->
                        <?php
                        $stmt = $db->prepare("
                            SELECT cp.*, sa.full_name
                            FROM competition_participants cp
                            JOIN staff_accounts sa ON cp.staff_id = sa.staff_id
                            WHERE cp.competition_id = ?
                            ORDER BY cp.rank ASC
                            LIMIT 5
                        ");
                        $stmt->execute([$comp['competition_id']]);
                        $topParticipants = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        ?>

                        <?php if (!empty($topParticipants)): ?>
                        <h6 class="mt-3">Current Standings:</h6>
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Rank</th>
                                    <th>Name</th>
                                    <th>Score</th>
                                    <th>Prize</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($topParticipants as $participant): ?>
                                <tr class="<?php echo $participant['staff_id'] == $current_user_id ? 'table-primary' : ''; ?>">
                                    <td>
                                        <?php if ($participant['rank'] == 1): ?>
                                            🥇
                                        <?php elseif ($participant['rank'] == 2): ?>
                                            🥈
                                        <?php elseif ($participant['rank'] == 3): ?>
                                            🥉
                                        <?php else: ?>
                                            #<?php echo $participant['rank']; ?>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($participant['full_name']); ?></td>
                                    <td><?php echo number_format($participant['score']); ?></td>
                                    <td>
                                        <?php if ($participant['rank'] == 1): ?>
                                            $<?php echo number_format($comp['prize_amount_first'], 0); ?>
                                        <?php elseif ($participant['rank'] == 2): ?>
                                            $<?php echo number_format($comp['prize_amount_second'], 0); ?>
                                        <?php elseif ($participant['rank'] == 3): ?>
                                            $<?php echo number_format($comp['prize_amount_third'], 0); ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                        <?php endif; ?>

                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="alert alert-info mb-5">
                <i class="fas fa-info-circle me-2"></i>
                No active competitions right now. Check back soon!
            </div>
            <?php endif; ?>

            <!-- Upcoming Competitions -->
            <?php if (!empty($upcomingCompetitions)): ?>
            <div class="mb-5">
                <h2 class="h4 mb-3">
                    <i class="fas fa-calendar-alt text-primary me-2"></i>
                    Coming Soon
                </h2>

                <div class="row">
                    <?php foreach ($upcomingCompetitions as $comp): ?>
                    <div class="col-md-6 mb-3">
                        <div class="card h-100">
                            <div class="card-body">
                                <h5 class="card-title"><?php echo htmlspecialchars($comp['name']); ?></h5>
                                <p class="card-text small"><?php echo htmlspecialchars($comp['description']); ?></p>
                                <p class="mb-1">
                                    <strong>Starts:</strong> <?php echo date('M j, Y', strtotime($comp['start_date'])); ?>
                                </p>
                                <p class="mb-1">
                                    <strong>Prize Pool:</strong> $<?php echo number_format($comp['prize_amount_first'] + $comp['prize_amount_second'] + $comp['prize_amount_third'], 0); ?>
                                </p>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent Winners -->
            <?php if (!empty($completedCompetitions)): ?>
            <div class="mb-5">
                <h2 class="h4 mb-3">
                    <i class="fas fa-history text-success me-2"></i>
                    Recent Winners
                </h2>

                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Competition</th>
                                <th>Winner</th>
                                <th>Score</th>
                                <th>Prize</th>
                                <th>Completed</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($completedCompetitions as $comp): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($comp['name']); ?></td>
                                <td>
                                    🥇 <?php echo htmlspecialchars($comp['winner_name'] ?? 'N/A'); ?>
                                </td>
                                <td><?php echo number_format($comp['score'] ?? 0); ?></td>
                                <td>$<?php echo number_format($comp['prize_amount_first'], 0); ?></td>
                                <td><?php echo date('M j, Y', strtotime($comp['end_date'])); ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/views/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>
