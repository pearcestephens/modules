<?php
/**
 * Flagged Products - Enhanced Stock Verification System v3.0
 * Direct Lightspeed API integration with reasonable security
 *
 * @version 3.0.0
 * @date 2025-11-05
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/VendAPI.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/services/CISLogger.php';

// Security check
if (!isset($_SESSION['userID'])) {
    header('Location: /login.php');
    exit;
}

// REQUIRED: Get outlet_id from URL parameter
$outletId = $_GET['outlet_id'] ?? null;
if (empty($outletId)) {
    die("<div class='alert alert-danger m-5'><h3>Missing Outlet ID</h3><p>Please access this page with <code>?outlet_id=YOUR_OUTLET_ID</code></p></div>");
}

// Get user info
$userId = $_SESSION['userID'];
$userName = $_SESSION['user_name'] ?? 'Staff Member';

// Get outlet name
$outletName = 'Unknown Store';
try {
    $stmt = $pdo->prepare("SELECT name FROM vend_outlets WHERE id = ?");
    $stmt->execute([$outletId]);
    $outlet = $stmt->fetch(PDO::FETCH_OBJ);
    $outletName = $outlet->name ?? 'Unknown Store';
} catch (Exception $e) {
    CISLogger::error('flagged_products', 'Failed to get outlet: ' . $e->getMessage());
}

// Get flagged products for this outlet
$sql = "SELECT
            fp.id as fp_id,
            fp.product_id,
            fp.reason,
            fp.qty_before,
            fp.date_flagged,
            vp.name as product_name,
            vp.handle,
            vp.image_url,
            vp.sku,
            vi.inventory_level,
            vi.reorder_point
        FROM flagged_products fp
        INNER JOIN vend_products vp ON vp.id = fp.product_id
        INNER JOIN vend_inventory vi ON vi.product_id = vp.id AND vi.outlet_id = ?
        WHERE fp.outlet = ?
        AND fp.date_completed_stocktake IS NULL
        ORDER BY
            CASE
                WHEN vi.inventory_level < 5 THEN 1
                WHEN vi.inventory_level < 10 THEN 2
                ELSE 3
            END,
            fp.date_flagged ASC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$outletId, $outletId]);
    $products = $stmt->fetchAll(PDO::FETCH_OBJ);
} catch (Exception $e) {
    CISLogger::error('flagged_products', 'Failed to get products: ' . $e->getMessage());
    $products = [];
}

// Get user stats
$userStats = ['total_points' => 0, 'current_streak' => 0, 'accuracy_rate' => 100, 'total_completed' => 0];
try {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(points_earned), 0) as total_points, COUNT(*) as total_completed, COALESCE(AVG(accuracy_percentage), 100) as accuracy_rate FROM flagged_products_points WHERE user_id = ?");
    $stmt->execute([$userId]);
    $stats = $stmt->fetch(PDO::FETCH_OBJ);
    if ($stats) {
        $userStats['total_points'] = (int)$stats->total_points;
        $userStats['total_completed'] = (int)$stats->total_completed;
        $userStats['accuracy_rate'] = round((float)$stats->accuracy_rate, 1);
    }
} catch (Exception $e) {}

$criticalCount = 0;
foreach ($products as $p) {
    if ((int)$p->inventory_level < 5) $criticalCount++;
}

CISLogger::action('flagged_products', 'page_view', 'success', $userId, null, ['outlet_id' => $outletId, 'product_count' => count($products)]);

include($_SERVER['DOCUMENT_ROOT'] . "/assets/template/html-header.php");
include($_SERVER['DOCUMENT_ROOT'] . "/assets/template/header.php");
?>

<style>
.watermark { position: fixed; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-45deg); font-size: 80px; font-weight: bold; color: rgba(200, 200, 200, 0.15); z-index: 999; pointer-events: none; user-select: none; white-space: nowrap; text-align: center; line-height: 1.2; }
.blur-overlay { display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.85); backdrop-filter: blur(10px); z-index: 9999; justify-content: center; align-items: center; }
.blur-overlay.active { display: flex !important; }
.product-row.completed { opacity: 0.5; background: #e8f5e9 !important; }
.qty-input { font-size: 18px; font-weight: 700; text-align: center; border: 2px solid #dee2e6; border-radius: 6px; transition: all 0.2s; }
.qty-input:focus { border-color: #4CAF50; box-shadow: 0 0 0 3px rgba(76, 175, 80, 0.1); }
.qty-input.has-value { border-color: #2196F3; background: #e3f2fd; }
.timer { font-family: 'Courier New', monospace; font-weight: bold; padding: 4px 8px; background: #f8f9fa; border-radius: 4px; display: inline-block; }
.timer.fast { background: #fff3cd; color: #856404; }
.timer.slow { background: #f8d7da; color: #721c24; }
@media (max-width: 768px) { .watermark { font-size: 40px; } }

/* SPLASH SCREEN */
#splash-screen {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: linear-gradient(135deg, #1e3c72 0%, #2a5298 50%, #7e22ce 100%);
  display: flex;
  flex-direction: column;
  justify-content: center;
  align-items: center;
  z-index: 99999;
  animation: fadeOut 0.5s ease-in-out 2.5s forwards;
}
#splash-logo {
  width: 300px;
  max-width: 80vw;
  margin-bottom: 40px;
  animation: logoFloat 2s ease-in-out;
  filter: drop-shadow(0 20px 40px rgba(0,0,0,0.4));
}
#splash-text {
  font-size: 80px;
  font-weight: 900;
  color: white;
  text-transform: uppercase;
  letter-spacing: 8px;
  text-shadow: 0 5px 20px rgba(0,0,0,0.5);
  animation: textPulse 2s ease-in-out;
  text-align: center;
  line-height: 1.2;
}
#splash-subtitle {
  font-size: 24px;
  color: rgba(255,255,255,0.9);
  margin-top: 20px;
  letter-spacing: 4px;
  animation: subtitleSlide 1.5s ease-out 0.5s both;
}
@keyframes logoFloat {
  0% { transform: translateY(-100px) scale(0.5); opacity: 0; }
  60% { transform: translateY(10px) scale(1.1); opacity: 1; }
  100% { transform: translateY(0) scale(1); opacity: 1; }
}
@keyframes textPulse {
  0% { transform: scale(0.8); opacity: 0; }
  50% { transform: scale(1.1); opacity: 1; }
  100% { transform: scale(1); opacity: 1; }
}
@keyframes subtitleSlide {
  0% { transform: translateY(30px); opacity: 0; }
  100% { transform: translateY(0); opacity: 1; }
}
@keyframes zoomInOut {
  0% { transform: scale(0); opacity: 0; }
  50% { transform: scale(1.2); opacity: 1; }
  80% { transform: scale(0.95); opacity: 1; }
  100% { transform: scale(1); opacity: 1; }
}
@keyframes fadeOut {
  to { opacity: 0; pointer-events: none; }
}
@media (max-width: 768px) {
  #splash-logo { width: 200px; margin-bottom: 30px; }
  #splash-text { font-size: 48px; letter-spacing: 4px; }
  #splash-subtitle { font-size: 16px; letter-spacing: 2px; }
}
</style>

<body class="app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show">

  <!-- SPLASH SCREEN -->
  <div id="splash-screen">
    <img id="splash-logo" src="https://www.vapeshed.co.nz/assets/template/vapeshed/images/vape-shed-logo.png" alt="Vape Shed">
    <div id="splash-text">WE'RE BACK<br>BITCHES! 🔥</div>
    <div id="splash-subtitle">Stock Verification System v3.0</div>
  </div>

  <div class="watermark"><?= strtoupper($outletName) ?>-<?= $userId ?><br><span id="watermark-time"><?= date('d/m/Y g:i:s A') ?></span></div>

  <div class="app-body">
    <?php include($_SERVER['DOCUMENT_ROOT'] . "/assets/template/sidemenu.php") ?>

    <main class="main">
      <ol class="breadcrumb">
        <li class="breadcrumb-item">Home</li>
        <li class="breadcrumb-item"><a href="#">Inventory</a></li>
        <li class="breadcrumb-item active">Flagged Products</li>
      </ol>

      <div class="container-fluid">
        <div class="card mb-3">
          <div class="card-header bg-primary text-white">
            <div class="d-flex justify-content-between align-items-center">
              <div>
                <h5 class="mb-0"><i class="icon-layers"></i> Stock Verification - <?= htmlspecialchars($outletName) ?></h5>
                <small>Verify physical inventory counts</small>
              </div>
              <div class="text-right">
                <div class="h2 mb-0"><?= count($products) ?></div>
                <small>Products Flagged</small>
              </div>
            </div>
          </div>

          <div class="card-body p-3">
            <div class="row text-center">
              <div class="col-6 col-md-2 mb-2"><div class="border rounded p-2 bg-light"><div class="h4 mb-0 text-success">🏆 <?= number_format($userStats['total_points']) ?></div><small class="text-muted">Your Points</small></div></div>
              <div class="col-6 col-md-2 mb-2"><div class="border rounded p-2 bg-light"><div class="h4 mb-0 text-warning">🔥 <?= $userStats['current_streak'] ?></div><small class="text-muted">Day Streak</small></div></div>
              <div class="col-6 col-md-2 mb-2"><div class="border rounded p-2 bg-light"><div class="h4 mb-0 text-info">🎯 <?= $userStats['accuracy_rate'] ?>%</div><small class="text-muted">Accuracy</small></div></div>
              <div class="col-6 col-md-2 mb-2"><div class="border rounded p-2 bg-light"><div class="h4 mb-0 text-secondary">📦 <?= count($products) ?></div><small class="text-muted">To Verify</small></div></div>
              <div class="col-6 col-md-2 mb-2"><div class="border rounded p-2 bg-light"><div class="h4 mb-0 text-danger">⚠️ <?= $criticalCount ?></div><small class="text-muted">Critical Stock</small></div></div>
              <div class="col-6 col-md-2 mb-2"><div class="border rounded p-2 bg-light"><div class="h4 mb-0 text-primary">✓ <?= $userStats['total_completed'] ?></div><small class="text-muted">Completed</small></div></div>
            </div>
          </div>
        </div>

        <?php if (count($products) > 0): ?>
        <div class="alert alert-warning"><strong>⚡ Action Required:</strong> Count physical stock and enter actual quantities. System will update Lightspeed automatically.</div>

        <div class="card mb-3"><div class="card-body p-2"><small class="text-muted"><strong>Stock Levels:</strong></small><span class="badge badge-danger ml-2">Critical: 0-4</span><span class="badge badge-warning ml-2">Low: 5-9</span><span class="badge badge-primary ml-2">Moderate: 10-19</span><span class="badge badge-success ml-2">Good: 20+</span></div></div>

        <div class="card">
          <div class="table-responsive">
            <table class="table table-hover mb-0">
              <thead class="thead-light">
                <tr>
                  <th width="80">Image</th>
                  <th>Product</th>
                  <th width="100" class="text-center">Current Stock</th>
                  <th width="120" class="text-center">Actual Qty</th>
                  <th width="80" class="text-center">Time</th>
                  <th width="100" class="text-center">Action</th>
                </tr>
              </thead>
              <tbody id="products-list">
                <?php foreach ($products as $product):
                  $stock = (int)$product->inventory_level;
                  $stockBadge = $stock < 5 ? 'danger' : ($stock < 10 ? 'warning' : ($stock < 20 ? 'primary' : 'success'));
                ?>
                  <tr class="product-row" id="product-<?= $product->fp_id ?>" data-product-id="<?= $product->fp_id ?>" data-start-time="<?= time() ?>">
                    <td><img src="<?= htmlspecialchars($product->image_url ?: 'https://via.placeholder.com/80') ?>" alt="<?= htmlspecialchars($product->product_name) ?>" style="width: 60px; height: 60px; object-fit: contain;" oncontextmenu="return false;"></td>
                    <td><strong><?= htmlspecialchars($product->product_name) ?></strong><br><small class="text-muted"><?= htmlspecialchars($product->handle) ?></small><?php if ($product->sku): ?><br><small class="text-muted">SKU: <?= htmlspecialchars($product->sku) ?></small><?php endif; ?><br><span class="badge badge-secondary"><?= htmlspecialchars($product->reason) ?></span></td>
                    <td class="text-center"><span class="badge badge-<?= $stockBadge ?> badge-lg"><?= $stock ?> units</span></td>
                    <td><input type="number" class="form-control qty-input" id="qty-<?= $product->fp_id ?>" data-product-id="<?= $product->product_id ?>" data-outlet-id="<?= $outletId ?>" data-current-stock="<?= $stock ?>" min="0" max="9999" placeholder="?" autocomplete="off" onchange="this.classList.add('has-value')"></td>
                    <td class="text-center"><div class="timer" id="timer-<?= $product->fp_id ?>">00:00</div></td>
                    <td class="text-center"><button class="btn btn-success btn-sm btn-block btn-complete" onclick="completeProduct(<?= $product->fp_id ?>)" id="btn-<?= $product->fp_id ?>"><i class="fa fa-check"></i> Complete</button></td>
                  </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>
        <?php else: ?>
          <div class="alert alert-success"><h4>🎉 All Products Completed!</h4><p>Great job! All flagged products for <?= htmlspecialchars($outletName) ?> have been resolved.</p></div>
        <?php endif; ?>

        <div class="card mt-3">
          <div class="card-header"><i class="fa fa-info-circle"></i> Best Practices & Tips</div>
          <div class="card-body">
            <div class="row">
              <div class="col-md-6">
                <h6 class="text-success"><i class="fa fa-check-circle"></i> DO THIS</h6>
                <ul><li>Count accurately - match physical stock exactly</li><li>Check all locations - drawers, back room, behind counter</li><li>Take your time - accuracy over speed</li><li>Complete immediately - don't leave half-counted</li></ul>
                <h6 class="text-info"><i class="fa fa-trophy"></i> Points System</h6>
                <ul><li><strong>Base:</strong> 10 points per product</li><li><strong>Accuracy Bonus:</strong> +20 points if exact match</li><li><strong>Speed Bonus:</strong> +5 points if 15-45 seconds</li><li><strong>Streak Bonus:</strong> +2 points per day (max 50)</li></ul>
              </div>
              <div class="col-md-6">
                <h6 class="text-warning"><i class="fa fa-clock-o"></i> Timing Guidelines</h6>
                <ul><li><strong>Normal:</strong> 15-45 seconds per product</li><li><strong>Too Fast:</strong> &lt;10 seconds (flagged for review)</li><li><strong>Too Slow:</strong> &gt;2 minutes (no penalty)</li></ul>
                <h6 class="text-danger"><i class="fa fa-shield"></i> Security Notes</h6>
                <ul><li>Tab switching logged but not penalized</li><li>Large discrepancies auto-reviewed by manager</li><li>All actions logged for audit trail</li></ul>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <div class="blur-overlay" id="blur-overlay">
    <div class="bg-white p-4 rounded text-center" style="max-width: 400px;">
      <h4 class="mb-3">⚠️ Tab Switch Detected</h4>
      <p class="text-muted mb-3">You switched tabs during verification. This has been logged.</p>
      <div class="h1 mb-3 text-warning" id="countdown-display">5</div>
      <p class="small text-muted mb-3">Please wait <span id="countdown-text">5</span> seconds...</p>
      <button id="continue-btn" class="btn btn-secondary" disabled onclick="closeBlurOverlay()">Continue (wait...)</button>
    </div>
  </div>

  <script>
  const OUTLET_ID = '<?= $outletId ?>';
  const USER_ID = <?= $userId ?>;
  let tabSwitchCount = 0;
  let blurCountdown = null;

  setInterval(() => {
    const now = new Date();
    const nzTime = new Date(now.toLocaleString('en-US', { timeZone: 'Pacific/Auckland' }));
    document.getElementById('watermark-time').textContent = nzTime.toLocaleDateString('en-NZ') + ' ' + nzTime.toLocaleTimeString('en-NZ');
  }, 1000);

  setInterval(() => {
    document.querySelectorAll('.product-row:not(.completed)').forEach(row => {
      const fpId = row.dataset.productId;
      const startTime = parseInt(row.dataset.startTime);
      const elapsed = Math.floor(Date.now() / 1000) - startTime;
      const minutes = Math.floor(elapsed / 60);
      const seconds = elapsed % 60;
      const display = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
      const timer = document.getElementById(`timer-${fpId}`);
      if (timer) {
        timer.textContent = display;
        timer.classList.remove('fast', 'slow');
        if (elapsed < 10) timer.classList.add('fast');
        if (elapsed > 120) timer.classList.add('slow');
      }
    });
  }, 1000);

  document.addEventListener('visibilitychange', () => {
    if (document.hidden) {
      tabSwitchCount++;
      fetch('/api/log-action.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({action: 'tab_switch', user_id: USER_ID, outlet_id: OUTLET_ID, count: tabSwitchCount})
      }).catch(e => console.error('Log failed:', e));
      showBlurOverlay();
    }
  });

  function showBlurOverlay() {
    const overlay = document.getElementById('blur-overlay');
    const continueBtn = document.getElementById('continue-btn');
    const countdownDisplay = document.getElementById('countdown-display');
    const countdownText = document.getElementById('countdown-text');
    overlay.classList.add('active');
    continueBtn.disabled = true;
    let countdown = 5;
    countdownDisplay.textContent = countdown;
    countdownText.textContent = countdown;
    if (blurCountdown) clearInterval(blurCountdown);
    blurCountdown = setInterval(() => {
      countdown--;
      countdownDisplay.textContent = countdown;
      countdownText.textContent = countdown;
      if (countdown <= 0) {
        clearInterval(blurCountdown);
        continueBtn.disabled = false;
        continueBtn.textContent = 'Continue';
        continueBtn.classList.remove('btn-secondary');
        continueBtn.classList.add('btn-primary');
      }
    }, 1000);
  }

  function closeBlurOverlay() {
    document.getElementById('blur-overlay').classList.remove('active');
    if (blurCountdown) clearInterval(blurCountdown);
  }

  async function completeProduct(fpId) {
    const qtyInput = document.getElementById(`qty-${fpId}`);
    const btn = document.getElementById(`btn-${fpId}`);
    const row = document.getElementById(`product-${fpId}`);
    const actualQty = parseInt(qtyInput.value);
    if (isNaN(actualQty) || actualQty < 0) {
      alert('Please enter a valid quantity (0 or greater)');
      qtyInput.focus();
      return;
    }
    const productId = qtyInput.dataset.productId;
    const outletId = qtyInput.dataset.outletId;
    const currentStock = parseInt(qtyInput.dataset.currentStock);
    const startTime = parseInt(row.dataset.startTime);
    const timeSpent = Math.floor(Date.now() / 1000) - startTime;
    btn.disabled = true;
    btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Processing...';
    try {
      const response = await fetch('/api/complete-flagged-product.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({fp_id: fpId, product_id: productId, outlet_id: outletId, user_id: USER_ID, qty_before: currentStock, qty_after: actualQty, time_spent: timeSpent, tab_switches: tabSwitchCount})
      });
      const result = await response.json();
      if (result.success) {
        row.classList.add('completed');
        btn.innerHTML = '<i class="fa fa-check"></i> Completed';
        btn.classList.remove('btn-success');
        btn.classList.add('btn-secondary');
        qtyInput.disabled = true;
        if (result.points_earned) {
          const toast = document.createElement('div');
          toast.className = 'alert alert-success position-fixed';
          toast.style.cssText = 'top: 70px; right: 20px; z-index: 9999; min-width: 250px;';
          toast.innerHTML = `<strong>✓ Completed!</strong><br>+${result.points_earned} points! ${result.bonus_msg || ''}`;
          document.body.appendChild(toast);
          setTimeout(() => toast.remove(), 3000);
        }
        const remaining = document.querySelectorAll('.product-row:not(.completed)').length;
        if (remaining === 0) {
          setTimeout(() => {
            alert('🎉 All products completed! Well done!');
            window.location.reload();
          }, 1500);
        }
      } else {
        throw new Error(result.error || 'Failed to complete product');
      }
    } catch (error) {
      console.error('Error:', error);
      alert('Error: ' + error.message);
      btn.disabled = false;
      btn.innerHTML = '<i class="fa fa-check"></i> Complete';
    }
  }

  fetch('/api/log-action.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({action: 'page_load', user_id: USER_ID, outlet_id: OUTLET_ID, product_count: <?= count($products) ?>})
  }).catch(e => console.error('Log failed:', e));
  </script>

  <?php include($_SERVER['DOCUMENT_ROOT'] . "/assets/template/footer.php"); ?>
</body>
</html>
