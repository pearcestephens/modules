<?php
/**
 * Flagged Products - Standalone Leaderboard
 * 
 * Public leaderboard showing rankings with filters:
 * - Daily, Weekly, Monthly, All-Time
 * - Per-store or company-wide
 * - Achievement badges display
 * 
 * @version 2.0.0
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/services/CISLogger.php';
require_once __DIR__ . '/../models/FlaggedProductsRepository.php';

if (!isset($_SESSION['userID'])) {
    header('Location: /login.php');
    exit;
}

$userId = $_SESSION['userID'];

// Get filters
$period = $_GET['period'] ?? 'weekly';
$outletId = $_GET['outlet_id'] ?? 'all';

// Validate period
if (!in_array($period, ['daily', 'weekly', 'monthly', 'all_time'])) {
    $period = 'weekly';
}

// Get all outlets
$allOutlets = [];
try {
    $stmt = $pdo->query("SELECT id, name FROM vend_outlets WHERE deleted_at IS NULL ORDER BY name");
    $allOutlets = $stmt->fetchAll(PDO::FETCH_OBJ);
} catch (Exception $e) {
    CISLogger::error('flagged_products', 'Failed to get outlets: ' . $e->getMessage());
}

// Get leaderboard data
$leaderboard = FlaggedProductsRepository::getLeaderboard($period, 100, $outletId !== 'all' ? $outletId : null);

// Get user's position
$userPosition = 0;
$userStats = null;
foreach ($leaderboard as $index => $entry) {
    if ($entry->user_id == $userId) {
        $userPosition = $index + 1;
        $userStats = $entry;
        break;
    }
}

// Get period label
$periodLabels = [
    'daily' => 'Today',
    'weekly' => 'This Week',
    'monthly' => 'This Month',
    'all_time' => 'All Time'
];
$periodLabel = $periodLabels[$period];

// Include header
include $_SERVER['DOCUMENT_ROOT'] . '/modules/base/views/layouts/header.php';
?>

<link rel="stylesheet" href="/modules/flagged_products/assets/css/flagged-products.css">

<style>
.leaderboard-header {
    background: linear-gradient(135deg, #fbbf24 0%, #f59e0b 100%);
    color: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 25px;
    box-shadow: 0 8px 20px rgba(251, 191, 36, 0.3);
    text-align: center;
}

.leaderboard-header h1 {
    font-size: 36px;
    font-weight: 700;
    margin: 0 0 8px 0;
    text-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.leaderboard-header p {
    margin: 0;
    opacity: 0.95;
    font-size: 16px;
}

.filter-bar {
    background: white;
    padding: 20px;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    margin-bottom: 25px;
    display: flex;
    gap: 15px;
    flex-wrap: wrap;
    align-items: center;
}

.filter-bar label {
    font-weight: 600;
    font-size: 13px;
    color: #374151;
}

.filter-bar select {
    padding: 8px 15px;
    border: 2px solid #e5e7eb;
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.filter-bar select:hover {
    border-color: #fbbf24;
}

.filter-bar select:focus {
    outline: none;
    border-color: #fbbf24;
    box-shadow: 0 0 0 3px rgba(251, 191, 36, 0.1);
}

.your-position {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 20px;
    border-radius: 10px;
    margin-bottom: 25px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
}

.your-position .position-label {
    font-size: 14px;
    opacity: 0.9;
    margin-bottom: 5px;
}

.your-position .position-value {
    font-size: 48px;
    font-weight: 700;
    line-height: 1;
    text-shadow: 0 2px 4px rgba(0,0,0,0.2);
}

.your-position .stats-grid {
    display: flex;
    gap: 30px;
}

.your-position .stat {
    text-align: center;
}

.your-position .stat-value {
    font-size: 28px;
    font-weight: 700;
    line-height: 1;
}

.your-position .stat-label {
    font-size: 12px;
    opacity: 0.9;
    margin-top: 5px;
}

.leaderboard-table {
    background: white;
    border-radius: 10px;
    box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    overflow: hidden;
}

.leaderboard-table table {
    width: 100%;
    border-collapse: collapse;
}

.leaderboard-table thead {
    background: linear-gradient(135deg, #f3f4f6 0%, #e5e7eb 100%);
}

.leaderboard-table th {
    padding: 15px 20px;
    text-align: left;
    font-size: 12px;
    font-weight: 700;
    color: #374151;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.leaderboard-table td {
    padding: 15px 20px;
    border-bottom: 1px solid #f3f4f6;
}

.leaderboard-table tr:last-child td {
    border-bottom: none;
}

.leaderboard-table tr:hover {
    background: #f9fafb;
}

.leaderboard-table tr.current-user {
    background: rgba(102, 126, 234, 0.05);
    border-left: 4px solid #667eea;
}

.leaderboard-table tr.current-user:hover {
    background: rgba(102, 126, 234, 0.08);
}

.rank-cell {
    font-weight: 700;
    font-size: 24px;
    width: 80px;
}

.rank-cell.gold {
    color: #fbbf24;
    text-shadow: 0 1px 3px rgba(251, 191, 36, 0.3);
}

.rank-cell.silver {
    color: #9ca3af;
    text-shadow: 0 1px 3px rgba(156, 163, 175, 0.3);
}

.rank-cell.bronze {
    color: #f59e0b;
    text-shadow: 0 1px 3px rgba(245, 158, 11, 0.3);
}

.user-info {
    display: flex;
    align-items: center;
    gap: 12px;
}

.user-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: 700;
    font-size: 16px;
    flex-shrink: 0;
}

.user-details .user-name {
    font-weight: 600;
    font-size: 14px;
    color: #1f2937;
}

.user-details .user-outlet {
    font-size: 12px;
    color: #6b7280;
}

.badges {
    display: flex;
    gap: 5px;
    flex-wrap: wrap;
}

.badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.badge.streak {
    background: #fef3c7;
    color: #f59e0b;
}

.badge.accuracy {
    background: #d1fae5;
    color: #10b981;
}

.badge.speed {
    background: #dbeafe;
    color: #3b82f6;
}

.stat-value {
    font-weight: 700;
    font-size: 16px;
    color: #1f2937;
}

.stat-change {
    font-size: 11px;
    font-weight: 600;
    margin-left: 5px;
}

.stat-change.up {
    color: #10b981;
}

.stat-change.down {
    color: #ef4444;
}

.empty-state {
    padding: 60px 20px;
    text-align: center;
}

.empty-state .icon {
    font-size: 64px;
    margin-bottom: 20px;
    opacity: 0.3;
}

.empty-state h3 {
    font-size: 20px;
    font-weight: 700;
    color: #374151;
    margin-bottom: 10px;
}

.empty-state p {
    color: #6b7280;
    font-size: 14px;
}
</style>

<div class="container-fluid mt-4">
    <!-- Header -->
    <div class="leaderboard-header">
        <h1>🏆 Leaderboard</h1>
        <p>Top performers in stock verification</p>
    </div>

    <!-- Filter Bar -->
    <div class="filter-bar">
        <label>Period:</label>
        <select id="periodFilter" onchange="updateFilters()">
            <option value="daily" <?= $period === 'daily' ? 'selected' : '' ?>>Today</option>
            <option value="weekly" <?= $period === 'weekly' ? 'selected' : '' ?>>This Week</option>
            <option value="monthly" <?= $period === 'monthly' ? 'selected' : '' ?>>This Month</option>
            <option value="all_time" <?= $period === 'all_time' ? 'selected' : '' ?>>All Time</option>
        </select>

        <label>Store:</label>
        <select id="outletFilter" onchange="updateFilters()">
            <option value="all" <?= $outletId === 'all' ? 'selected' : '' ?>>All Stores</option>
            <?php foreach ($allOutlets as $outlet): ?>
                <option value="<?= htmlspecialchars($outlet->id) ?>" <?= $outletId === $outlet->id ? 'selected' : '' ?>>
                    <?= htmlspecialchars($outlet->name) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <!-- Your Position -->
    <?php if ($userPosition > 0 && $userStats): ?>
    <div class="your-position">
        <div>
            <div class="position-label">Your Position</div>
            <div class="position-value">#<?= $userPosition ?></div>
        </div>
        <div class="stats-grid">
            <div class="stat">
                <div class="stat-value"><?= number_format($userStats->total_points) ?></div>
                <div class="stat-label">Points</div>
            </div>
            <div class="stat">
                <div class="stat-value"><?= number_format($userStats->products_completed) ?></div>
                <div class="stat-label">Products</div>
            </div>
            <div class="stat">
                <div class="stat-value"><?= number_format($userStats->accuracy, 1) ?>%</div>
                <div class="stat-label">Accuracy</div>
            </div>
            <div class="stat">
                <div class="stat-value"><?= $userStats->current_streak ?></div>
                <div class="stat-label">Streak</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Leaderboard Table -->
    <div class="leaderboard-table">
        <?php if (empty($leaderboard)): ?>
            <div class="empty-state">
                <div class="icon">🏆</div>
                <h3>No Rankings Yet</h3>
                <p>Be the first to complete products and appear on the leaderboard!</p>
            </div>
        <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th>Rank</th>
                    <th>User</th>
                    <th>Store</th>
                    <th style="text-align: center;">Badges</th>
                    <th style="text-align: right;">Points</th>
                    <th style="text-align: right;">Products</th>
                    <th style="text-align: right;">Accuracy</th>
                    <th style="text-align: right;">Streak</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($leaderboard as $index => $entry): 
                    $rank = $index + 1;
                    $isCurrentUser = $entry->user_id == $userId;
                    $rankClass = '';
                    if ($rank === 1) $rankClass = 'gold';
                    elseif ($rank === 2) $rankClass = 'silver';
                    elseif ($rank === 3) $rankClass = 'bronze';
                    
                    // Determine badges
                    $badges = [];
                    if ($entry->current_streak >= 7) {
                        $badges[] = ['type' => 'streak', 'label' => '🔥 ' . $entry->current_streak . ' Day'];
                    }
                    if ($entry->accuracy >= 98) {
                        $badges[] = ['type' => 'accuracy', 'label' => '🎯 Perfect'];
                    }
                    if ($entry->avg_time_per_product <= 30) {
                        $badges[] = ['type' => 'speed', 'label' => '⚡ Speed'];
                    }
                    
                    // Get initials
                    $userName = $entry->user_name ?? 'Unknown';
                    $nameParts = explode(' ', $userName);
                    $initials = '';
                    foreach ($nameParts as $part) {
                        if (!empty($part)) {
                            $initials .= strtoupper($part[0]);
                        }
                    }
                    $initials = substr($initials, 0, 2);
                ?>
                <tr class="<?= $isCurrentUser ? 'current-user' : '' ?>">
                    <td class="rank-cell <?= $rankClass ?>">
                        <?php if ($rank <= 3): ?>
                            <?= $rank === 1 ? '🥇' : ($rank === 2 ? '🥈' : '🥉') ?>
                        <?php else: ?>
                            #<?= $rank ?>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div class="user-info">
                            <div class="user-avatar"><?= $initials ?></div>
                            <div class="user-details">
                                <div class="user-name">
                                    <?= htmlspecialchars($userName) ?>
                                    <?= $isCurrentUser ? '<span style="color: #667eea; font-weight: 700; margin-left: 5px;">(You)</span>' : '' ?>
                                </div>
                            </div>
                        </div>
                    </td>
                    <td>
                        <div class="user-outlet"><?= htmlspecialchars($entry->outlet_name ?? 'Unknown') ?></div>
                    </td>
                    <td style="text-align: center;">
                        <div class="badges">
                            <?php foreach ($badges as $badge): ?>
                                <span class="badge <?= $badge['type'] ?>"><?= $badge['label'] ?></span>
                            <?php endforeach; ?>
                        </div>
                    </td>
                    <td style="text-align: right;">
                        <span class="stat-value"><?= number_format($entry->total_points) ?></span>
                    </td>
                    <td style="text-align: right;">
                        <span class="stat-value"><?= number_format($entry->products_completed) ?></span>
                    </td>
                    <td style="text-align: right;">
                        <span class="stat-value"><?= number_format($entry->accuracy, 1) ?>%</span>
                    </td>
                    <td style="text-align: right;">
                        <span class="stat-value">
                            <?= $entry->current_streak ?>
                            <?= $entry->current_streak >= 7 ? '🔥' : '' ?>
                        </span>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php endif; ?>
    </div>
</div>

<script>
function updateFilters() {
    const period = document.getElementById('periodFilter').value;
    const outlet = document.getElementById('outletFilter').value;
    
    // Build URL with filters
    const url = new URL(window.location.href);
    url.searchParams.set('period', period);
    url.searchParams.set('outlet_id', outlet);
    
    // Navigate
    window.location.href = url.toString();
}
</script>

<?php include $_SERVER['DOCUMENT_ROOT'] . '/modules/base/views/layouts/footer.php'; ?>
