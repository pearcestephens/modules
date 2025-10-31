<?php
/**
 * Flagged Products - Anti-Cheat Edition
 * 
 * Modern, gamified product flagging system with maximum security
 * Features: DevTools detection, tab switching alerts, mouse tracking,
 *          points/achievements, leaderboards, AI insights
 * 
 * @version 2.0.0
 * @security MAXIMUM
 */

// Initialize CIS
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once 'assets/services/CISLogger.php';
require_once 'modules/flagged_products/models/FlaggedProductsRepository.php';
require_once 'modules/flagged_products/lib/AntiCheat.php';

// Bot bypass is handled in config.php via BOT_BYPASS_AUTH constant
// For testing with ?bot=1, set a test user ID
if (defined('BOT_BYPASS_AUTH') && BOT_BYPASS_AUTH && !isset($_SESSION['userID'])) {
    $_SESSION['userID'] = 18; // Test user
    $_SESSION['user_name'] = 'Test Bot';
}

// Security check
if (!isset($_SESSION['userID'])) {
    header('Location: /login.php');
    exit;
}

// MANDATORY: Get outlet_id from URL parameter
$outletId = $_GET['outlet_id'] ?? null;
if (empty($outletId)) {
    die("<div class='alert alert-danger m-5'><h3>Missing Outlet ID</h3><p>Please access this page with ?outlet_id=YOUR_OUTLET_ID</p></div>");
}

// Get outlet name
$outletName = 'Unknown Store';
try {
    $stmt = $pdo->prepare("SELECT name FROM vend_outlets WHERE id = ?");
    $stmt->execute([$outletId]);
    $outlet = $stmt->fetch(PDO::FETCH_OBJ);
    $outletName = $outlet->name ?? 'Unknown Store';
} catch (Exception $e) {
    CISLogger::error('flagged_products', 'Failed to get outlet name: ' . $e->getMessage());
}

// Log page visit
CISLogger::action('flagged_products', 'page_view', 'success', null, null, [
    'security_active' => true,
    'anti_cheat_enabled' => true,
    'outlet_id' => $outletId
]);

// Get user ID (use test user ID 18 if bot bypass active and no session)
$userId = $_SESSION['userID'] ?? (defined('BOT_BYPASS_AUTH') && BOT_BYPASS_AUTH ? 18 : null);
if (!$userId) {
    die("Error: No user ID available");
}

// Get user stats
$userStats = FlaggedProductsRepository::getUserStats($userId);
$securityScore = $userStats->security_score ?? 100;
$currentStreak = $userStats->current_streak ?? 0;
$totalPoints = $userStats->total_points ?? 0;

// Check if user is blocked (BYPASS for testing: add ?bypass_security=1)
if (!isset($_GET['bypass_security'])) {
    $blockCheck = AntiCheat::shouldBlockUser($userId);
    if ($blockCheck['blocked']) {
        $blockReason = $blockCheck['reason'];
        include("assets/template/html-header.php");
        echo "<div class='alert alert-danger m-5'><h3>Access Denied</h3><p>$blockReason</p></div>";
        echo "<p class='text-muted text-center'>For testing, add ?bypass_security=1 to URL</p>";
        exit;
    }
}

// Get flagged products
$products = FlaggedProductsRepository::getFlaggedForOutlet($outletId, [
    'user_id' => $userId,
    'action' => 'page_load',
    'source' => 'flagged_products_v2'
]);
$totalProducts = count($products);

// Include template header
include("assets/template/html-header.php");
include("assets/template/header.php");
?>

<!-- Module Styles -->
<link rel="stylesheet" href="/modules/flagged_products/assets/css/flagged-products.css">

<body class="app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show">
  
  <!-- Anti-Screenshot Watermark -->
  <?php
  date_default_timezone_set('Pacific/Auckland');
  $nzTimestamp = date('d/m/Y g:i:s A');
  ?>
  <div class="watermark">
    <?= strtoupper($outletName) ?>-<?= $_SESSION['userid'] ?? 'UNKNOWN' ?><br>
    <?= $nzTimestamp ?>
  </div>
  
  <div class="app-body">
    <?php include("assets/template/sidemenu.php") ?>
    <main class="main">
      <!-- Breadcrumb -->
      <ol class="breadcrumb">
        <li class="breadcrumb-item">Home</li>
        <li class="breadcrumb-item"><a href="#">Inventory</a></li>
        <li class="breadcrumb-item active">Flagged Products</li>
        <li class="breadcrumb-menu d-md-down-none">
          <div class="btn-group" role="group">
            <a class="btn" href="./"><i class="icon-graph"></i> Dashboard</a>
            <a class="btn" href="/modules/flagged_products/views/leaderboard.php"><i class="icon-trophy"></i> Leaderboard</a>
          </div>
        </li>
      </ol>

      <div class="container-fluid">
        <!-- BUSINESS-FOCUSED HEADER -->
        <div style="background: white; border: 2px solid #e5e7eb; border-radius: 8px; padding: 16px 20px; margin-bottom: 16px; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
          <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; padding-bottom: 12px; border-bottom: 2px solid #f3f4f6;">
            <div>
              <h5 style="margin: 0; font-size: 16px; font-weight: 700; color: #1f2937;">
                � Stock Discrepancy Verification - <span style="color: #2563eb;"><?= htmlspecialchars($outletName) ?></span>
              </h5>
              <div style="margin-top: 4px; font-size: 12px; color: #6b7280;">
                <strong>Purpose:</strong> Routine audit detected inventory mismatches - verify actual physical counts
              </div>
            </div>
            <div style="text-align: right;">
              <div style="font-size: 24px; font-weight: 800; color: #dc2626; line-height: 1;"><?= count($products) ?></div>
              <div style="font-size: 10px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 2px;">Products Flagged</div>
            </div>
          </div>
          
          <!-- Enhanced Stats Grid with Context -->
          <div style="display: grid; grid-template-columns: repeat(6, 1fr); gap: 10px;">
            <!-- Your Performance -->
            <div style="text-align: center; padding: 10px; background: #f0fdf4; border-radius: 6px; border: 1px solid #bbf7d0;">
              <div style="font-size: 18px; font-weight: 800; color: #16a34a;">🏆 <?= number_format($totalPoints) ?></div>
              <div style="font-size: 9px; color: #15803d; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Your Points</div>
            </div>
            <div style="text-align: center; padding: 10px; background: #fef3c7; border-radius: 6px; border: 1px solid #fde68a;">
              <div style="font-size: 18px; font-weight: 800; color: #d97706;">🔥 <?= $currentStreak ?></div>
              <div style="font-size: 9px; color: #b45309; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Day Streak</div>
            </div>
            <div style="text-align: center; padding: 10px; background: #dbeafe; border-radius: 6px; border: 1px solid #bfdbfe;">
              <div style="font-size: 18px; font-weight: 800; color: #2563eb;">🎯 <?= number_format($userStats->accuracy_rate ?? 100, 1) ?>%</div>
              <div style="font-size: 9px; color: #1e40af; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Accuracy</div>
            </div>
            
            <!-- System Info -->
            <div style="text-align: center; padding: 10px; background: #f9fafb; border-radius: 6px; border: 1px solid #e5e7eb;">
              <div style="font-size: 18px; font-weight: 800; color: #374151;">📦 <?= $totalProducts ?></div>
              <div style="font-size: 9px; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">To Verify</div>
            </div>
            <div style="text-align: center; padding: 10px; background: #fef2f2; border-radius: 6px; border: 1px solid #fecaca;">
              <div style="font-size: 18px; font-weight: 800; color: #dc2626;">
                <?php 
                $criticalCount = 0;
                foreach($products as $p) {
                  if((int)$p->inventory_level < 5) $criticalCount++;
                }
                echo $criticalCount;
                ?>
              </div>
              <div style="font-size: 9px; color: #b91c1c; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Critical Stock</div>
            </div>
            <div style="text-align: center; padding: 10px; background: #eff6ff; border-radius: 6px; border: 1px solid #dbeafe;">
              <div style="font-size: 18px; font-weight: 800; color: #1d4ed8;">
                <?php 
                $avgTime = $userStats->avg_completion_time ?? 45;
                echo number_format($avgTime, 0);
                ?>s
              </div>
              <div style="font-size: 9px; color: #1e40af; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600;">Avg Time</div>
            </div>
          </div>
        </div>
        
        <!-- Action Alert -->
        <div style="background: #fef9c3; border-left: 4px solid #facc15; padding: 10px 16px; margin-bottom: 16px; font-size: 12px; border-radius: 4px; color: #713f12; display: flex; justify-content: space-between; align-items: center;">
          <div>
            <strong>⚡ Action Required:</strong> Count physical stock and enter actual quantities below. System will sync with Lightspeed automatically.
          </div>
          <a href="#faq-section" style="color: #713f12; text-decoration: underline; font-weight: 700; white-space: nowrap; margin-left: 12px;" onclick="document.getElementById('faq-section').scrollIntoView({behavior: 'smooth'}); return false;">How it Works →</a>
        </div>

        <!-- Products Grid/List -->
        <div class="animated fadeIn">
          <?php if (empty($products)): ?>
            <div class="alert alert-success">
              <h3>🎉 All Products Completed!</h3>
              <p>Great job! All flagged products for <?= htmlspecialchars($outletName) ?> have been resolved.</p>
            </div>
          <?php else: ?>
            
            <!-- Stock Level Legend -->
            <div style="background: white; border: 1px solid #e5e7eb; border-radius: 6px; padding: 12px 16px; margin-bottom: 12px; box-shadow: 0 1px 2px rgba(0,0,0,0.05);">
              <div style="font-size: 11px; font-weight: 700; color: #374151; margin-bottom: 8px; text-transform: uppercase; letter-spacing: 0.5px;">📊 Stock Level Guide</div>
              <div style="display: flex; gap: 16px; flex-wrap: wrap;">
                <div style="display: flex; align-items: center; gap: 6px;">
                  <div style="width: 12px; height: 12px; background: #ef4444; border-radius: 3px;"></div>
                  <span style="font-size: 11px; color: #374151;"><strong>Critical:</strong> 0-4 units</span>
                </div>
                <div style="display: flex; align-items: center; gap: 6px;">
                  <div style="width: 12px; height: 12px; background: #f59e0b; border-radius: 3px;"></div>
                  <span style="font-size: 11px; color: #374151;"><strong>Low:</strong> 5-9 units</span>
                </div>
                <div style="display: flex; align-items: center; gap: 6px;">
                  <div style="width: 12px; height: 12px; background: #3b82f6; border-radius: 3px;"></div>
                  <span style="font-size: 11px; color: #374151;"><strong>Moderate:</strong> 10-19 units</span>
                </div>
                <div style="display: flex; align-items: center; gap: 6px;">
                  <div style="width: 12px; height: 12px; background: #10b981; border-radius: 3px;"></div>
                  <span style="font-size: 11px; color: #374151;"><strong>Good:</strong> 20+ units</span>
                </div>
              </div>
            </div>

            <!-- Desktop Compact List View (for many products) -->
            <div class="d-none d-md-block">
              <div style="background: white; border: 1px solid #e5e7eb; border-radius: 8px; overflow: hidden; box-shadow: 0 1px 3px rgba(0,0,0,0.08);">
                <table class="table table-hover products-table" style="margin-bottom: 0;">
                  <thead style="background: #f8f9fa; border-bottom: 2px solid #e5e7eb;">
                    <tr style="font-size: 10px; text-transform: uppercase; color: #6b7280; letter-spacing: 0.3px;">
                      <th style="width: 60px; padding: 10px; font-weight: 700; border: none;">Image</th>
                      <th style="padding: 10px; font-weight: 700; border: none;">Product</th>
                      <th style="width: 100px; padding: 10px; font-weight: 700; text-align: center; border: none;">Stock</th>
                      <th style="width: 120px; padding: 10px; font-weight: 700; text-align: center; border: none;">Actual Qty</th>
                      <th style="width: 80px; padding: 10px; font-weight: 700; text-align: center; border: none;">Time</th>
                      <th style="width: 100px; padding: 10px; font-weight: 700; text-align: center; border: none;">Action</th>
                    </tr>
                  </thead>
                  <tbody id="products-list">
                    <?php foreach ($products as $index => $product): ?>
                      <tr class="product-row" id="product-<?= $product->fp_id ?>" data-product-id="<?= $product->fp_id ?>" style="font-size: 13px;">
                        <td style="padding: 8px;">
                          <img src="<?= htmlspecialchars($product->image_url ?: 'https://via.placeholder.com/80') ?>" 
                               alt="<?= htmlspecialchars($product->product_name) ?>" 
                               style="width: 50px; height: 50px; object-fit: contain;"
                               oncontextmenu="return false;">
                        </td>
                        <td style="padding: 8px;">
                          <strong style="font-size: 13px; color: #333;"><?= htmlspecialchars($product->product_name) ?></strong><br>
                          <small class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($product->handle) ?></small><br>
                          <span class="badge badge-warning" style="font-size: 10px; padding: 3px 6px; margin-top: 3px;"><?= htmlspecialchars($product->reason) ?></span>
                        </td>
                        <td class="text-center" style="padding: 8px;">
                          <?php
                          $stock = (int)$product->inventory_level;
                          $stockColor = '#10b981'; // Green default
                          $stockRange = '20+';
                          $stockBg = '#ecfdf5';
                          
                          if ($stock < 5) {
                            $stockColor = '#ef4444'; // Red
                            $stockRange = '0-4';
                            $stockBg = '#fef2f2';
                          } elseif ($stock < 10) {
                            $stockColor = '#f59e0b'; // Orange
                            $stockRange = '5-9';
                            $stockBg = '#fffbeb';
                          } elseif ($stock < 20) {
                            $stockColor = '#3b82f6'; // Blue
                            $stockRange = '10-19';
                            $stockBg = '#eff6ff';
                          }
                          ?>
                          <div class="current-stock text-center" style="background: <?= $stockBg ?>; padding: 6px; border-radius: 6px; border-left: 3px solid <?= $stockColor ?>;">
                            <div style="width: 32px; height: 32px; background: <?= $stockColor ?>; border-radius: 5px; margin: 0 auto 4px; display: flex; align-items: center; justify-content: center;">
                              <div style="width: 20px; height: 20px; background: white; border-radius: 3px; opacity: 0.3;"></div>
                            </div>
                            <div style="font-size: 9px; font-weight: 600; color: #6b7280; text-transform: uppercase; letter-spacing: 0.5px;"><?= $stockRange ?> units</div>
                          </div>
                        </td>
                        <td style="padding: 8px;">
                          <input type="number" 
                                 class="form-control qty-input text-center" 
                                 id="qty-<?= $product->fp_id ?>"
                                 min="0"
                                 max="9999"
                                 placeholder=""
                                 style="font-size: 16px; font-weight: bold; padding: 6px; height: auto;"
                                 autocomplete="off">
                        </td>
                        <td class="text-center" style="padding: 8px;">
                          <div class="timer text-center" data-start="<?= time() ?>" style="font-size: 14px; font-weight: bold; color: #333;" title="Timer helps identify unusual patterns - take your time and be accurate">
                            00:00
                          </div>
                          <small class="text-muted" style="font-size: 9px;">🎯 Accuracy</small>
                        </td>
                        <td class="text-center" style="padding: 8px;">
                          <button class="btn btn-success btn-sm btn-complete" 
                                  onclick="completeProduct(<?= $product->fp_id ?>)"
                                  style="width: 100%; padding: 6px 10px; font-size: 13px;">
                            ✓ Complete
                          </button>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>
            </div>

            <!-- Mobile Card View -->
            <div class="row d-md-none" id="products-grid">
              <?php foreach ($products as $index => $product): ?>
                <div class="col-12 mb-4" data-product-id="<?= $product->fp_id ?>">
                  <div class="product-card" id="product-mobile-<?= $product->fp_id ?>">
                    <!-- Product Image -->
                    <img src="<?= htmlspecialchars($product->image_url ?: 'https://via.placeholder.com/200') ?>" 
                         alt="<?= htmlspecialchars($product->product_name) ?>" 
                         class="product-image"
                         oncontextmenu="return false;">
                    
                    <!-- Product Info -->
                    <div class="product-info">
                      <h6><?= htmlspecialchars($product->product_name) ?></h6>
                      <small class="text-muted"><?= htmlspecialchars($product->handle) ?></small>
                      <p class="mt-2"><span class="badge badge-warning"><?= htmlspecialchars($product->reason) ?></span></p>
                      
                      <!-- Current Stock -->
                      <div class="alert alert-info mb-2">
                        <strong>Current Stock:</strong> <?= $product->inventory_level ?> units
                      </div>
                      
                      <!-- Timer -->
                      <div class="timer" data-start="<?= time() ?>">00:00</div>
                      
                      <!-- Quantity Input -->
                      <div class="qty-input-group">
                        <label class="mb-0">Actual Quantity:</label>
                        <input type="number" 
                               class="form-control qty-input" 
                               id="qty-mobile-<?= $product->fp_id ?>"
                               min="0"
                               max="9999"
                               placeholder="?"
                               autocomplete="off">
                      </div>
                      
                      <!-- Complete Button -->
                      <button class="btn btn-success btn-complete" 
                              onclick="completeProduct(<?= $product->fp_id ?>, true)">
                        ✓ Complete
                      </button>
                    </div>
                  </div>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
        
        <!-- FAQ / Best Practices Section -->
        <div class="card mt-3" id="faq-section" style="border: 1px solid #dee2e6;">
          <div class="card-header" style="background: #f8f9fa; padding: 10px 15px; border-bottom: 1px solid #dee2e6;">
            <h6 style="margin: 0; font-weight: 600; font-size: 14px; color: #495057;">
              <i class="fa fa-info-circle"></i> Best Practices & FAQ
            </h6>
          </div>
          <div class="card-body" style="padding: 15px;">
            <div class="row">
              <!-- DO's Column -->
              <div class="col-md-6">
                <h6 class="text-success" style="font-size: 13px; margin-bottom: 8px;">
                  <i class="fa fa-check-circle"></i> <strong>DO THIS</strong>
                </h6>
                <ul style="font-size: 12px; line-height: 1.6; margin-bottom: 15px; padding-left: 18px;">
                  <li>Count accurately - match physical stock exactly</li>
                  <li>Take your time - accuracy over speed</li>
                  <li>Check all locations - drawers, back room, behind counter</li>
                  <li>Verify system count - note if way off</li>
                  <li>Complete immediately - don't leave half-counted</li>
                  <li>Ask if unsure - better to check than guess</li>
                </ul>
                
                <h6 class="text-primary" style="font-size: 13px; margin-bottom: 8px;">
                  <i class="fa fa-clock-o"></i> <strong>How Timing Works</strong>
                </h6>
                <ul style="font-size: 12px; line-height: 1.6; margin-bottom: 0; padding-left: 18px;">
                  <li>Timer starts when product loads</li>
                  <li>Normal: 15-45s per product</li>
                  <li>Too fast (&lt;10s): May trigger review</li>
                  <li>Too slow (&gt;2min): No penalty</li>
                  <li>Points: Accuracy + reasonable time</li>
                </ul>
              </div>
              
              <!-- Points & Streaks Column -->
              <div class="col-md-6">
                <h6 class="text-warning" style="font-size: 13px; margin-bottom: 8px;">
                  <i class="fa fa-trophy"></i> <strong>Points & Streaks</strong>
                </h6>
                <ul style="font-size: 12px; line-height: 1.6; margin-bottom: 0; padding-left: 18px;">
                  <li>Base: 10 points per product</li>
                  <li>Accuracy: +5 points for exact match</li>
                  <li>Speed: +2 points if under 30s</li>
                  <li>Streak: Complete daily to maintain</li>
                  <li>Leaderboard: Top performers recognized</li>
                </ul>
              </div>
            </div>
            
            <hr style="margin: 15px 0;">
            
            <!-- FAQ Section -->
            <h6 class="text-info" style="font-size: 13px; margin-bottom: 10px;">
              <i class="fa fa-question-circle"></i> <strong>Common Questions</strong>
            </h6>
            <div class="row">
              <div class="col-md-6">
                <p style="font-size: 12px; line-height: 1.5; margin-bottom: 10px;">
                  <strong>Q: Stock way different than system?</strong><br>
                  A: Enter correct physical count. Large discrepancies auto-flagged for review.
                </p>
                <p style="font-size: 12px; line-height: 1.5; margin-bottom: 10px;">
                  <strong>Q: Can I pause and come back?</strong><br>
                  A: No, complete each product immediately. Timer runs until you click Complete.
                </p>
                <p style="font-size: 12px; line-height: 1.5; margin-bottom: 10px;">
                  <strong>Q: What if I make a mistake?</strong><br>
                  A: Accuracy % drops slightly, recover by being accurate on future products.
                </p>
              </div>
              <div class="col-md-6">
                <p style="font-size: 12px; line-height: 1.5; margin-bottom: 10px;">
                  <strong>Q: Why "Critical" stock level?</strong><br>
                  A: Stock very low (&lt;5). Extra important to be accurate for reordering.
                </p>
                <p style="font-size: 12px; line-height: 1.5; margin-bottom: 10px;">
                  <strong>Q: Improve leaderboard position?</strong><br>
                  A: Complete more products with high accuracy. Consistency beats speed.
                </p>
                <p style="font-size: 12px; line-height: 1.5; margin-bottom: 0;">
                  <strong>Q: Can't find product?</strong><br>
                  A: Enter 0 and Complete. Manager investigates. Don't leave incomplete.
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </main>
  </div>

  <!-- Blur Overlay (shown when tab loses focus) - HIGHEST Z-INDEX -->
  <div class="blur-overlay" id="blur-overlay">
    <div style="background: white; padding: 30px 40px; border-radius: 8px; max-width: 450px; text-align: center; box-shadow: 0 10px 40px rgba(0,0,0,0.3); position: relative; z-index: 1000001 !important; pointer-events: auto; border-left: 4px solid #ffc107;">
      <h1 style="font-size: 24px; margin-bottom: 15px; color: #333; font-weight: 600;">⚠️ Tab Switch Detected</h1>
      <p style="color: #666; font-size: 15px; margin-bottom: 12px; line-height: 1.5;">You switched tabs during stock verification. This has been logged for security purposes.</p>
      <div style="font-size: 48px; font-weight: bold; color: #dc3545; margin: 20px 0;" id="countdown-timer">15</div>
      <p style="color: #999; font-size: 13px; margin-bottom: 25px;">Please wait <span id="countdown-text">15</span> seconds before continuing...</p>
      <button id="continue-btn" disabled
              onclick="if(!this.disabled) { document.getElementById('blur-overlay').classList.remove('active'); document.getElementById('blur-overlay').style.display='none'; }" 
              style="background: #ccc; color: #666; border: none; padding: 12px 30px; font-size: 15px; font-weight: 600; border-radius: 4px; cursor: not-allowed; box-shadow: none; transition: all 0.2s; position: relative; z-index: 1000002 !important; pointer-events: auto;">
        Continue (wait...)
      </button>
    </div>
  </div>

  <!-- Pass outlet_id to JavaScript -->
  <script>
    window.OUTLET_ID = '<?= htmlspecialchars($outletId, ENT_QUOTES) ?>';
    
    // Update watermark timestamp every second to prevent static screenshots (NZ Time)
    setInterval(function() {
      const watermark = document.querySelector('.watermark');
      if (watermark) {
        // Get NZ time (Pacific/Auckland)
        const now = new Date();
        const nzTime = new Date(now.toLocaleString('en-US', { timeZone: 'Pacific/Auckland' }));
        
        // Format as DD/MM/YYYY H:MM:SS AM/PM
        const day = String(nzTime.getDate()).padStart(2, '0');
        const month = String(nzTime.getMonth() + 1).padStart(2, '0');
        const year = nzTime.getFullYear();
        
        let hours = nzTime.getHours();
        const ampm = hours >= 12 ? 'PM' : 'AM';
        hours = hours % 12;
        hours = hours ? hours : 12; // 0 becomes 12
        
        const minutes = String(nzTime.getMinutes()).padStart(2, '0');
        const seconds = String(nzTime.getSeconds()).padStart(2, '0');
        
        const dateStr = day + '/' + month + '/' + year + ' ' + hours + ':' + minutes + ':' + seconds + ' ' + ampm;
        
        const lines = watermark.innerHTML.split('<br>');
        watermark.innerHTML = lines[0] + '<br>' + dateStr;
      }
    }, 1000);
  </script>

  <!-- Load Anti-Cheat JavaScript -->
  <script src="/modules/flagged_products/assets/js/anti-cheat.js"></script>
  <script src="/modules/flagged_products/assets/js/flagged-products.js"></script>
</body>

<?php include("assets/template/footer.php"); ?>
