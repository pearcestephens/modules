<?php
/**
 * Flagged Products - Completion Summary
 * 
 * Beautiful summary page shown after completing all flagged products
 * Shows: personal stats, store performance, achievements, AI insights, leaderboard position
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

// MANDATORY: Get outlet_id from URL parameter
$outletId = $_GET['outlet_id'] ?? null;
if (empty($outletId)) {
    die("<div class='alert alert-danger m-5'><h3>Missing Outlet ID</h3><p>Please access this page with ?outlet_id=YOUR_OUTLET_ID</p></div>");
}

$userId = $_SESSION['userID'];
$userName = $_SESSION['first_name'] . ' ' . $_SESSION['last_name'];

// Get outlet name
$outletName = 'Your Store';
try {
    $stmt = $pdo->prepare("SELECT name FROM vend_outlets WHERE id = ?");
    $stmt->execute([$outletId]);
    $outlet = $stmt->fetch(PDO::FETCH_OBJ);
    $outletName = $outlet->name ?? 'Your Store';
} catch (Exception $e) {
    CISLogger::error('flagged_products', 'Failed to get outlet name: ' . $e->getMessage());
}

// Get comprehensive stats
$userStats = FlaggedProductsRepository::getUserStats($userId);
$storeStats = FlaggedProductsRepository::getStoreStats($outletId);
$leaderboard = FlaggedProductsRepository::getLeaderboard('weekly', 10);

// Get user's position
$position = 0;
foreach ($leaderboard as $index => $entry) {
    if ($entry->user_id == $userId) {
        $position = $index + 1;
        break;
    }
}

// Get recent achievements
$sql = "SELECT achievement_type, awarded_at 
        FROM flagged_products_achievements 
        WHERE user_id = ? 
        ORDER BY awarded_at DESC 
        LIMIT 5";
$achievements = sql_query_collection_safe($sql, [$userId]);

// Log page view
CISLogger::action('flagged_products', 'view_summary', 'success');

include($_SERVER['DOCUMENT_ROOT'] . "/assets/template/html-header.php");
include($_SERVER['DOCUMENT_ROOT'] . "/assets/template/header.php");
?>

<link rel="stylesheet" href="/modules/flagged_products/assets/css/flagged-products.css">
<style>
.summary-hero {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 60px 20px;
    text-align: center;
    border-radius: 12px;
    margin-bottom: 30px;
}

.summary-hero h1 {
    font-size: 48px;
    font-weight: bold;
    margin-bottom: 10px;
}

.summary-hero .subtitle {
    font-size: 20px;
    opacity: 0.9;
}

.stat-card {
    background: white;
    border-radius: 12px;
    padding: 25px;
    text-align: center;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    transition: transform 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 5px 20px rgba(0,0,0,0.15);
}

.stat-card .stat-icon {
    font-size: 48px;
    margin-bottom: 10px;
}

.stat-card .stat-value {
    font-size: 36px;
    font-weight: bold;
    color: #667eea;
}

.stat-card .stat-label {
    font-size: 14px;
    color: #666;
    text-transform: uppercase;
}

.achievement-badge {
    display: inline-block;
    background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
    color: white;
    padding: 15px 25px;
    border-radius: 25px;
    margin: 10px;
    font-weight: bold;
    box-shadow: 0 4px 15px rgba(245, 87, 108, 0.4);
}

.ai-insight-box {
    background: #f8f9fa;
    border-left: 4px solid #667eea;
    padding: 25px;
    border-radius: 8px;
    margin: 20px 0;
}

.ai-insight-box .ai-icon {
    font-size: 32px;
    margin-right: 10px;
}

.leaderboard-table {
    background: white;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 2px 10px rgba(0,0,0,0.1);
}

.leaderboard-table th {
    background: #667eea;
    color: white;
    padding: 15px;
}

.leaderboard-table td {
    padding: 15px;
    border-bottom: 1px solid #eee;
}

.leaderboard-table tr.highlight {
    background: #fff3cd;
    font-weight: bold;
}

.medal {
    font-size: 24px;
}

.btn-continue {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 15px 40px;
    font-size: 18px;
    border: none;
    border-radius: 30px;
    font-weight: bold;
    margin-top: 20px;
    transition: all 0.3s;
}

.btn-continue:hover {
    transform: scale(1.05);
    box-shadow: 0 5px 20px rgba(102, 126, 234, 0.4);
}

.comparison-bar {
    background: #e0e0e0;
    height: 30px;
    border-radius: 15px;
    overflow: hidden;
    position: relative;
    margin: 15px 0;
}

.comparison-bar .fill {
    background: linear-gradient(90deg, #4caf50, #8bc34a);
    height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-weight: bold;
    transition: width 1s ease-out;
}
</style>

<body class="app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show">
  <div class="app-body">
    <?php include($_SERVER['DOCUMENT_ROOT'] . "/assets/template/sidemenu.php") ?>
    <main class="main">
      <div class="container-fluid">
        
        <!-- Hero Section -->
        <div class="summary-hero">
          <h1>🎉 Great Work, <?= htmlspecialchars($_SESSION['first_name']) ?>!</h1>
          <p class="subtitle">All flagged products completed for <?= htmlspecialchars($outletName) ?></p>
        </div>

        <!-- Personal Stats -->
        <div class="row">
          <div class="col-md-3">
            <div class="stat-card">
              <div class="stat-icon">🎯</div>
              <div class="stat-value"><?= number_format($userStats->accuracy_rate ?? 0, 1) ?>%</div>
              <div class="stat-label">Accuracy Rate</div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="stat-card">
              <div class="stat-icon">⭐</div>
              <div class="stat-value"><?= number_format($userStats->total_points ?? 0) ?></div>
              <div class="stat-label">Total Points</div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="stat-card">
              <div class="stat-icon">🔥</div>
              <div class="stat-value"><?= $userStats->current_streak ?? 0 ?></div>
              <div class="stat-label">Day Streak</div>
            </div>
          </div>
          <div class="col-md-3">
            <div class="stat-card">
              <div class="stat-icon">🏆</div>
              <div class="stat-value">#<?= $position ?: '—' ?></div>
              <div class="stat-label">Weekly Rank</div>
            </div>
          </div>
        </div>

        <!-- AI Insight -->
        <div class="ai-insight-box" id="ai-insight-box">
          <h4><span class="ai-icon">🤖</span>AI Performance Analysis</h4>
          <p id="ai-insight-text">Analyzing your performance...</p>
        </div>

        <!-- Store Comparison -->
        <div class="card">
          <div class="card-header">
            <h5>📊 How You Compare to <?= htmlspecialchars($outletName) ?></h5>
          </div>
          <div class="card-body">
            <p><strong>Your Accuracy:</strong> <?= number_format($userStats->accuracy_rate ?? 0, 1) ?>%</p>
            <div class="comparison-bar">
              <div class="fill" style="width: <?= $userStats->accuracy_rate ?? 0 ?>%">
                <?= number_format($userStats->accuracy_rate ?? 0, 1) ?>%
              </div>
            </div>
            
            <p class="mt-3"><strong>Store Average:</strong> <?= number_format($storeStats->avg_accuracy ?? 0, 1) ?>%</p>
            <div class="comparison-bar">
              <div class="fill" style="width: <?= $storeStats->avg_accuracy ?? 0 ?>%">
                <?= number_format($storeStats->avg_accuracy ?? 0, 1) ?>%
              </div>
            </div>
          </div>
        </div>

        <!-- Achievements -->
        <?php if (!empty($achievements)): ?>
        <div class="card mt-4">
          <div class="card-header">
            <h5>🏆 Recent Achievements</h5>
          </div>
          <div class="card-body text-center">
            <?php foreach ($achievements as $achievement): ?>
              <div class="achievement-badge">
                <?= htmlspecialchars(ucwords(str_replace('_', ' ', $achievement->achievement_type))) ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endif; ?>

        <!-- Weekly Leaderboard -->
        <div class="card mt-4">
          <div class="card-header">
            <h5>🏅 Weekly Leaderboard - Top 10</h5>
          </div>
          <div class="card-body p-0">
            <table class="leaderboard-table table mb-0">
              <thead>
                <tr>
                  <th>Rank</th>
                  <th>Name</th>
                  <th>Store</th>
                  <th>Points</th>
                  <th>Accuracy</th>
                  <th>Completed</th>
                </tr>
              </thead>
              <tbody>
                <?php foreach ($leaderboard as $index => $entry): ?>
                <tr class="<?= $entry->user_id == $userId ? 'highlight' : '' ?>">
                  <td>
                    <?php if ($index < 3): ?>
                      <span class="medal">
                        <?= $index === 0 ? '🥇' : ($index === 1 ? '🥈' : '🥉') ?>
                      </span>
                    <?php else: ?>
                      #<?= $index + 1 ?>
                    <?php endif; ?>
                  </td>
                  <td><?= htmlspecialchars($entry->user_name) ?></td>
                  <td><?= htmlspecialchars($entry->outlet_name) ?></td>
                  <td><strong><?= number_format($entry->total_points) ?></strong></td>
                  <td><?= number_format($entry->accuracy, 1) ?>%</td>
                  <td><?= $entry->total_completed ?></td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

        <!-- Call to Action -->
        <div class="text-center mt-5 mb-5">
          <h3>Keep Up The Momentum! 💪</h3>
          <p>Your dedication helps <?= htmlspecialchars($outletName) ?> maintain perfect inventory accuracy.</p>
          <a href="/flagged-products-v2.php?outlet_id=<?= urlencode($outletId) ?>" class="btn btn-continue">Check For More Products</a>
          <a href="/" class="btn btn-outline-secondary">Back to Dashboard</a>
        </div>

      </div>
    </main>
  </div>
</body>

<script>
// Load AI insight
fetch('/modules/flagged_products/functions/api.php?action=get_completion_summary')
  .then(res => res.json())
  .then(data => {
    if (data.success && data.ai_insight) {
      document.getElementById('ai-insight-text').textContent = data.ai_insight;
      
      // Show motivational message if available
      if (data.motivational_message) {
        document.getElementById('ai-insight-box').innerHTML += 
          `<p class="mt-3"><strong>${data.motivational_message}</strong></p>`;
      }
    }
  })
  .catch(err => console.error('Failed to load AI insight:', err));

// Animate bars
setTimeout(() => {
  document.querySelectorAll('.comparison-bar .fill').forEach(bar => {
    const width = bar.style.width;
    bar.style.width = '0%';
    setTimeout(() => bar.style.width = width, 100);
  });
}, 500);
</script>

<?php include($_SERVER['DOCUMENT_ROOT'] . "/assets/template/footer.php"); ?>
