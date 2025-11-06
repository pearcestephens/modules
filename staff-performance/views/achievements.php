<?php
/**
 * Staff Performance - Achievements View
 *
 * Badge gallery showing unlocked and locked achievements
 *
 * @package CIS\Modules\StaffPerformance
 * @version 1.0.0
 */

// Get all achievements with user's unlock status
try {
    $stmt = $db->prepare("
        SELECT
            a.*,
            sa.unlocked_at,
            sa.progress_current,
            sa.progress_total,
            CASE WHEN sa.unlocked_at IS NOT NULL THEN 1 ELSE 0 END as is_unlocked
        FROM achievements a
        LEFT JOIN staff_achievements sa ON a.achievement_id = sa.achievement_id AND sa.staff_id = ?
        ORDER BY a.category, a.difficulty, a.achievement_id
    ");
    $stmt->execute([$current_user_id]);
    $achievements = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Group by category
    $achievementsByCategory = [];
    foreach ($achievements as $achievement) {
        $category = $achievement['category'];
        if (!isset($achievementsByCategory[$category])) {
            $achievementsByCategory[$category] = [];
        }
        $achievementsByCategory[$category][] = $achievement;
    }

    // Count unlocked
    $unlockedCount = count(array_filter($achievements, fn($a) => $a['is_unlocked']));
    $totalCount = count($achievements);
    $progressPercent = $totalCount > 0 ? round(($unlockedCount / $totalCount) * 100) : 0;

} catch (Exception $e) {
    error_log("Achievements View Error: " . $e->getMessage());
    $achievementsByCategory = [];
    $unlockedCount = 0;
    $totalCount = 0;
    $progressPercent = 0;
}

$pageTitle = "Achievements";
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
        .achievement-card {
            border: 2px solid #dee2e6;
            border-radius: 1rem;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s;
            height: 100%;
        }

        .achievement-card.unlocked {
            background: linear-gradient(135deg, #fff9e6 0%, #fff 100%);
            border-color: #ffc107;
            box-shadow: 0 4px 12px rgba(255, 193, 7, 0.3);
        }

        .achievement-card.locked {
            opacity: 0.5;
            filter: grayscale(0.8);
        }

        .achievement-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }

        .achievement-icon {
            font-size: 4rem;
            margin-bottom: 1rem;
        }

        .achievement-icon.bronze { color: #CD7F32; }
        .achievement-icon.silver { color: #C0C0C0; }
        .achievement-icon.gold { color: #FFD700; }
        .achievement-icon.platinum { color: #E5E4E2; }
        .achievement-icon.diamond { color: #B9F2FF; }

        .progress-ring {
            width: 60px;
            height: 60px;
            margin: 0 auto;
        }

        .category-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 0.5rem;
            margin: 2rem 0 1rem 0;
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

            <!-- Header -->
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h1>
                    <i class="fas fa-award text-warning me-2"></i>
                    Achievements
                </h1>
                <div class="text-end">
                    <div class="h3 mb-0"><?php echo $unlockedCount; ?> / <?php echo $totalCount; ?></div>
                    <small class="text-muted">Unlocked</small>
                </div>
            </div>

            <!-- Overall Progress -->
            <div class="card mb-4">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <h5 class="mb-0">Overall Progress</h5>
                        <span class="badge bg-primary"><?php echo $progressPercent; ?>%</span>
                    </div>
                    <div class="progress" style="height: 30px;">
                        <div class="progress-bar progress-bar-striped progress-bar-animated"
                             role="progressbar"
                             style="width: <?php echo $progressPercent; ?>%"
                             aria-valuenow="<?php echo $progressPercent; ?>"
                             aria-valuemin="0"
                             aria-valuemax="100">
                            <?php echo $unlockedCount; ?> of <?php echo $totalCount; ?> achievements
                        </div>
                    </div>
                </div>
            </div>

            <!-- Achievements by Category -->
            <?php foreach ($achievementsByCategory as $category => $categoryAchievements): ?>

            <div class="category-header">
                <h3 class="mb-0">
                    <i class="fas fa-<?php
                        echo match($category) {
                            'reviews' => 'star',
                            'drops' => 'box',
                            'competitions' => 'trophy',
                            'milestones' => 'flag-checkered',
                            default => 'award'
                        };
                    ?> me-2"></i>
                    <?php echo ucfirst($category); ?> Achievements
                </h3>
            </div>

            <div class="row mb-4">
                <?php foreach ($categoryAchievements as $achievement): ?>
                <div class="col-md-6 col-lg-4 col-xl-3 mb-3">
                    <div class="achievement-card <?php echo $achievement['is_unlocked'] ? 'unlocked' : 'locked'; ?>">

                        <?php if ($achievement['is_unlocked']): ?>
                            <!-- Unlocked Badge -->
                            <div class="position-absolute top-0 end-0 p-2">
                                <i class="fas fa-check-circle text-success fa-2x"></i>
                            </div>
                        <?php endif; ?>

                        <!-- Icon -->
                        <div class="achievement-icon <?php echo strtolower($achievement['difficulty']); ?>">
                            <?php echo $achievement['icon']; ?>
                        </div>

                        <!-- Name -->
                        <h5 class="fw-bold"><?php echo htmlspecialchars($achievement['name']); ?></h5>

                        <!-- Description -->
                        <p class="small text-muted mb-3">
                            <?php echo htmlspecialchars($achievement['description']); ?>
                        </p>

                        <!-- Progress (if not unlocked) -->
                        <?php if (!$achievement['is_unlocked'] && $achievement['progress_current'] !== null): ?>
                        <div class="progress mb-2" style="height: 20px;">
                            <div class="progress-bar"
                                 role="progressbar"
                                 style="width: <?php echo ($achievement['progress_current'] / $achievement['progress_total']) * 100; ?>%">
                                <?php echo $achievement['progress_current']; ?> / <?php echo $achievement['progress_total']; ?>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Difficulty Badge -->
                        <span class="badge bg-<?php
                            echo match($achievement['difficulty']) {
                                'bronze' => 'secondary',
                                'silver' => 'light text-dark',
                                'gold' => 'warning',
                                'platinum' => 'info',
                                'diamond' => 'primary',
                                default => 'secondary'
                            };
                        ?>">
                            <?php echo ucfirst($achievement['difficulty']); ?>
                        </span>

                        <!-- Points Badge -->
                        <span class="badge bg-success ms-1">
                            <?php echo $achievement['points_value']; ?> pts
                        </span>

                        <!-- Unlock Date -->
                        <?php if ($achievement['is_unlocked']): ?>
                        <div class="mt-2 small text-muted">
                            <i class="fas fa-calendar me-1"></i>
                            Unlocked <?php echo date('M j, Y', strtotime($achievement['unlocked_at'])); ?>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php endforeach; ?>

            <!-- No Achievements -->
            <?php if (empty($achievementsByCategory)): ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle me-2"></i>
                No achievements available yet. Keep performing well to unlock badges!
            </div>
            <?php endif; ?>

        </div>
    </div>
</div>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/views/footer.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
// Add tooltips to locked achievements
document.addEventListener('DOMContentLoaded', function() {
    const lockedCards = document.querySelectorAll('.achievement-card.locked');
    lockedCards.forEach(card => {
        card.setAttribute('data-bs-toggle', 'tooltip');
        card.setAttribute('title', 'Keep performing to unlock this achievement!');
    });

    // Initialize Bootstrap tooltips
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
});

// Add animation when scrolling into view
const observer = new IntersectionObserver((entries) => {
    entries.forEach(entry => {
        if (entry.isIntersecting) {
            entry.target.style.animation = 'fadeInUp 0.5s ease-out';
        }
    });
});

document.querySelectorAll('.achievement-card').forEach(card => {
    observer.observe(card);
});
</script>

<style>
@keyframes fadeInUp {
    from {
        opacity: 0;
        transform: translateY(20px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

</body>
</html>
