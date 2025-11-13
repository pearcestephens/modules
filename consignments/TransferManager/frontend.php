<?php
// Optional IP gate during go-live:
// if (($_SERVER['REMOTE_ADDR'] ?? '') !== '125.236.217.224') { http_response_code(403); exit('Come back shortly.'); }

if (session_status() !== PHP_SESSION_ACTIVE) session_start();

// Load modern bootstrap only (NOT legacy app.php to avoid function redeclaration)
require_once __DIR__ . '/../bootstrap.php';

// Check authentication
if (!isset($_SESSION['user_id']) || empty($_SESSION['user_id'])) {
    $currentUrl = $_SERVER['REQUEST_URI'] ?? '/modules/consignments/?route=transfer-manager';
    $loginUrl = 'https://staff.vapeshed.co.nz/login.php?redirect=' . urlencode($currentUrl);
    header('Location: ' . $loginUrl);
    exit;
}

/**
 * frontend.php — Transfers UI (independent)
 * - Loads CSRF + maps directly from database (no backend.php call needed for init)
 */

function fetch_init(): array
{
  try {
    // Database connection using modern bootstrap env vars
    $host = $_ENV['DB_HOST'] ?? 'localhost';
    $user = $_ENV['DB_USER'] ?? 'jcepnzzkmj';
    $pass = $_ENV['DB_PASS'] ?? '';
    $name = $_ENV['DB_NAME'] ?? 'jcepnzzkmj';

    $db = new mysqli($host, $user, $pass, $name);
    if ($db->connect_error) {
      return ['_error' => 'Database connection failed: ' . $db->connect_error];
    }
    $db->set_charset('utf8mb4');

    // Generate CSRF token if not exists
    if (!isset($_SESSION['tt_csrf'])) {
      $_SESSION['tt_csrf'] = bin2hex(random_bytes(16));
    }

    // Load outlets
    $outletMap = [];
    $q = "SELECT id, COALESCE(NULLIF(name,''), NULLIF(store_code,''), NULLIF(physical_city,''), id) AS label
          FROM vend_outlets
          WHERE deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00' OR deleted_at = '0000-00-00'
          ORDER BY label ASC";
    if ($r = $db->query($q)) {
      while ($row = $r->fetch_assoc()) {
        $outletMap[$row['id']] = $row['label'];
      }
      $r->free();
    }

    // Load suppliers
    $supplierMap = [];
    $supplierTableExists = $db->query("SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME='vend_suppliers' LIMIT 1");
    if ($supplierTableExists && $supplierTableExists->num_rows > 0) {
      $s = $db->query("SELECT id, name FROM vend_suppliers WHERE deleted_at IS NULL OR deleted_at = '' OR deleted_at = '0' ORDER BY name ASC");
      if ($s) {
        while ($row = $s->fetch_assoc()) {
          $supplierMap[$row['id']] = $row['name'] ?: $row['id'];
        }
        $s->free();
      }
    }

    // Get sync state from file
    $syncFile = __DIR__ . '/.sync_enabled';
    $syncEnabled = true;
    if (file_exists($syncFile)) {
      $syncEnabled = (trim(file_get_contents($syncFile)) === '1');
    }

    // Check Lightspeed sync cron job status (using vend_consignment_queue table)
    $lastSyncTime = null;
    $syncAgeMinutes = null;
    $syncStatus = 'unknown';

    // Monitor the queue table for last activity (completed_at for successful syncs, updated_at for any activity)
    $syncQuery = "SELECT
                    GREATEST(
                      COALESCE(MAX(completed_at), '1970-01-01'),
                      COALESCE(MAX(updated_at), '1970-01-01')
                    ) as last_sync,
                    COUNT(*) as total_jobs,
                    SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_jobs,
                    SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_jobs,
                    SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_jobs
                  FROM vend_consignment_queue
                  WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";

    // Store queue stats for reporting
    $queueStats = [
      'total_jobs' => 0,
      'completed_jobs' => 0,
      'failed_jobs' => 0,
      'processing_jobs' => 0
    ];

    if ($syncResult = $db->query($syncQuery)) {
      if ($row = $syncResult->fetch_assoc()) {
        $lastSyncTime = $row['last_sync'];
        $queueStats['total_jobs'] = (int)$row['total_jobs'];
        $queueStats['completed_jobs'] = (int)$row['completed_jobs'];
        $queueStats['failed_jobs'] = (int)$row['failed_jobs'];
        $queueStats['processing_jobs'] = (int)$row['processing_jobs'];

        // Only calculate sync age if we have valid data (not 1970-01-01)
        if ($lastSyncTime && $lastSyncTime !== '1970-01-01 00:00:00') {
          $syncTimestamp = strtotime($lastSyncTime);
          $nowTimestamp = time();
          $syncAgeMinutes = round(($nowTimestamp - $syncTimestamp) / 60);

          if ($syncAgeMinutes <= 15) {
            $syncStatus = 'healthy';
          } elseif ($syncAgeMinutes <= 30) {
            $syncStatus = 'warning';
          } else {
            $syncStatus = 'critical';
          }
        } else {
          // No sync activity detected (queue is empty or no recent jobs)
          $syncStatus = 'idle';
        }
      }
      $syncResult->free();
    }

    $db->close();

    return [
      'csrf_token' => $_SESSION['tt_csrf'],
      'ls_consignment_base' => 'https://vapeshed.retail.lightspeed.app/app/2.0/consignments/',
      'outlet_map' => $outletMap,
      'supplier_map' => $supplierMap,
      'sync_enabled' => $syncEnabled,
      'sync_status' => $syncStatus,
      'last_sync_time' => $lastSyncTime,
      'sync_age_minutes' => $syncAgeMinutes,
      'queue_stats' => $queueStats,
      'debug' => [
        'db_connected' => true,
        'total_outlets' => count($outletMap),
        'outlets_loaded' => count($outletMap),
        'suppliers_loaded' => count($supplierMap),
        'errors' => []
      ]
    ];
  } catch (Exception $e) {
    return ['_error' => $e->getMessage()];
  }
}

$d = fetch_init();
$csrf         = $d['csrf_token'] ?? ($_SESSION['tt_csrf'] ?? '');
$syncEnabled  = (bool)($d['sync_enabled'] ?? true);  // Default to TRUE (enabled)
$outlet_map   = $d['outlet_map']   ?? [];
$supplier_map = $d['supplier_map'] ?? [];
$ls_base      = $d['ls_consignment_base'] ?? '';

// 🔍 DEBUG: Display what we got from init
$debug_info = $d['debug'] ?? null;
$init_error = $d['_error'] ?? null;
$raw_response = $d['_raw'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Consignments Utility</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="styles.css" rel="stylesheet">
  <link href="modal-premium.css" rel="stylesheet">
  <style>
    /* Ultra-Compact Table Design */
    .compact-header th {
      padding: 0.4rem 0.5rem !important;
      font-size: 0.875rem;
      font-weight: 600;
      white-space: nowrap;
    }

    .table-sm td {
      padding: 0.5rem 0.75rem !important;
      vertical-align: middle;
    }

    /* Compact Vend Icon Button - SUBTLE LIGHT GREEN */
    .btn-vend-compact {
      width: 40px;
      height: 40px;
      padding: 0;
      display: inline-flex;
      align-items: center;
      justify-content: center;
      border: none;
      background: transparent;
      border-radius: 8px;
      transition: all 0.2s ease;
      position: relative;
      cursor: pointer;
    }

    /* ACTIVE STATE - Light subtle green */
    .btn-vend-compact.vend-active {
      background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
      border: 2px solid #86efac;
      box-shadow: 0 2px 4px rgba(134, 239, 172, 0.2);
    }

    .btn-vend-compact.vend-active:hover {
      transform: scale(1.08);
      background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
      border-color: #4ade80;
      box-shadow: 0 4px 8px rgba(74, 222, 128, 0.3);
    }

    .btn-vend-compact.vend-active:active {
      transform: scale(1.02);
    }

    /* Bigger tick - make SVG larger and lighter green */
    .btn-vend-compact.vend-active svg {
      width: 28px !important;
      height: 28px !important;
    }

    .btn-vend-compact.vend-active svg circle {
      fill: #86efac !important; /* Light green */
    }

    .btn-vend-compact.vend-active svg path {
      stroke: #ffffff !important;
      stroke-width: 2.5 !important;
    }

    /* DISABLED STATE - Subtle gray */
    .btn-vend-compact.vend-disabled {
      cursor: not-allowed;
      opacity: 0.4;
      background: #f8fafc;
      border: 2px solid #e2e8f0;
    }

    .btn-vend-compact.vend-disabled:hover {
      transform: none;
      box-shadow: none;
    }

    .btn-vend-compact.vend-disabled svg {
      width: 28px !important;
      height: 28px !important;
    }

    .btn-vend-compact.vend-disabled svg circle {
      fill: #cbd5e1 !important;
    }

    .btn-vend-compact.vend-disabled svg path {
      stroke: #ffffff !important;
    }
  </style>
</head>

<body>
  <div class="wrap">

    <!-- 🔍 DEBUG INFO - ENABLED -->
    <?php if (true): // Set to false to disable debug display ?>
    <?php
    // Determine alert type based on status
    $hasError = $init_error || count($outlet_map) === 0 || !$debug_info;
    $alertType = $hasError ? 'alert-danger' : 'alert-success';
    $alertIcon = $hasError ? '🚨' : '✅';
    ?>
    <div class="alert <?= $alertType ?> alert-dismissible fade show" role="alert" style="position: fixed; top: 10px; right: 10px; z-index: 9999; max-width: 700px; box-shadow: 0 4px 12px rgba(0,0,0,0.3);">
      <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
      <h5 class="alert-heading"><?= $alertIcon ?> System Status Check</h5>

      <div class="mb-2">
        <strong>Outlets Loaded:</strong>
        <span class="badge <?= count($outlet_map) > 0 ? 'bg-success' : 'bg-danger' ?>"><?= count($outlet_map) ?></span>
      </div>
      <div class="mb-2">
        <strong>Suppliers Loaded:</strong>
        <span class="badge <?= count($supplier_map) > 0 ? 'bg-success' : 'bg-warning' ?>"><?= count($supplier_map) ?></span>
      </div>

      <?php if ($init_error): ?>
        <hr>
        <div class="alert alert-danger mb-0" style="padding: 0.75rem;">
          <h6 class="alert-heading mb-2">❌ CRITICAL ERROR</h6>
          <strong>Backend Response Failed:</strong> <?= htmlspecialchars($init_error) ?><br>
          <small class="text-muted">This means backend.php is not responding correctly.</small>
          <?php if ($raw_response): ?>
            <details class="mt-2">
              <summary class="text-primary" style="cursor: pointer;">📋 View Raw Response</summary>
              <pre style="font-size: 10px; max-height: 150px; overflow-y: auto; background: #f8f9fa; padding: 0.5rem; border-radius: 4px; margin-top: 0.5rem;"><?= htmlspecialchars($raw_response) ?></pre>
            </details>
          <?php endif; ?>
          <div class="mt-2">
            <strong>Check:</strong>
            <ul class="mb-0" style="font-size: 12px;">
              <li>PHP error logs at: <code>logs/apache_phpstack-129337-518184.cloudwaysapps.com.error.log</code></li>
              <li>Database connection in backend.php</li>
              <li>File permissions on backend.php</li>
            </ul>
          </div>
        </div>
      <?php endif; ?>

      <?php if ($debug_info): ?>
        <hr>
        <div style="font-size: 12px;">
          <strong>📊 Backend Debug Info:</strong><br>
          <ul class="mb-0">
            <li>DB Connected:
              <span class="badge <?= ($debug_info['db_connected'] ?? false) ? 'bg-success' : 'bg-danger' ?>">
                <?= ($debug_info['db_connected'] ?? false) ? 'YES' : 'NO' ?>
              </span>
            </li>
            <li>Total Outlets in DB: <?= $debug_info['total_outlets'] ?? 'N/A' ?></li>
            <li>Outlets Loaded: <?= $debug_info['outlets_loaded'] ?? 'N/A' ?></li>
            <li>Suppliers Loaded: <?= $debug_info['suppliers_loaded'] ?? 'N/A' ?></li>
          </ul>

          <?php if (!empty($debug_info['errors'])): ?>
            <div class="alert alert-danger mt-2 mb-0" style="padding: 0.5rem;">
              <strong>⚠️ Backend Errors:</strong><br>
              <ul class="mb-0">
                <?php foreach ($debug_info['errors'] as $err): ?>
                  <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>

          <?php if (!empty($debug_info['deleted_at_values'])): ?>
          <details class="mt-2">
            <summary class="text-primary" style="cursor: pointer;">📋 View deleted_at values in DB</summary>
            <pre style="font-size: 10px; max-height: 150px; overflow-y: auto; background: #f8f9fa; padding: 0.5rem; border-radius: 4px; margin-top: 0.5rem;"><?= json_encode($debug_info['deleted_at_values'] ?? [], JSON_PRETTY_PRINT) ?></pre>
          </details>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <hr>
        <div class="alert alert-danger mb-0" style="padding: 0.75rem;">
          <h6 class="alert-heading mb-2">❌ BACKEND NOT RESPONDING</h6>
          <p class="mb-2">Backend is supposed to send debug info, but none was received!</p>
          <strong>This indicates:</strong>
          <ul class="mb-0">
            <li>PHP fatal error in backend.php (check error logs)</li>
            <li>Database connection failure</li>
            <li>Output buffering issue or syntax error</li>
          </ul>
        </div>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Header -->
    <div class="d-flex align-items-center justify-content-between mb-3">
      <div>
        <h2 class="mb-0">Transfers Tool <span class="small-note">Ad-hoc</span></h2>
        <div class="small-note">Manage consignments. Press <kbd>/</kbd> to search.</div>
      </div>
      <div class="d-flex align-items-center gap-2">
        <div class="sync-control-group d-flex align-items-center gap-2 me-3" style="padding: 8px 16px; background: linear-gradient(135deg, #f8fafc 0%, #f1f5f9 100%); border-radius: 12px; border: 2px solid #e2e8f0;">
          <div class="form-check form-switch mb-0">
            <input class="form-check-input" type="checkbox" id="syncToggle" <?= $syncEnabled ? 'checked' : '' ?>>
            <label class="form-check-label" for="syncToggle">Lightspeed Sync</label>
          </div>
          <button id="btnVerifySync" class="btn btn-sm btn-outline-success" style="font-size: 0.8rem; padding: 4px 12px;" title="Verify all Lightspeed table data and sync status">
            <i class="bi bi-shield-check me-1"></i> Verify
          </button>
        </div>
        <button id="btnNew" class="btn btn-success btn-elevated"><i class="bi bi-plus-lg me-1"></i> New Transfer</button>
        <button id="btnRefresh" class="btn btn-primary btn-elevated"><i class="bi bi-arrow-repeat me-1"></i> Refresh</button>
        <button id="btnHardRefresh" class="btn btn-secondary btn-elevated" title="Hard refresh (bypass cache)"><i class="bi bi-arrow-clockwise me-1"></i> Hard Refresh</button>
      </div>
    </div>

    <!-- Filters - 🏆 TOURNAMENT CHAMPION: 4 columns → 2 columns + enhanced search -->
    <div class="card mb-3 filters-stick">
      <div class="card-body">
        <div class="row g-3">
          <!-- Primary Filters Column -->
          <div class="col-lg-5">
            <div class="row g-2">
              <div class="col-6">
                <label class="form-label fw-semibold">
                  <i class="bi bi-funnel me-1"></i>Type
                </label>
                <select id="filterType" class="form-select">
                  <option value="">All Types</option>
                  <option>STOCK</option>
                  <option>JUICE</option>
                  <option>STAFF</option>
                  <option>RETURN</option>
                  <option>PURCHASE_ORDER</option>
                </select>
              </div>
              <div class="col-6">
                <label class="form-label fw-semibold">
                  <i class="bi bi-flag me-1"></i>State
                </label>
                <select id="filterState" class="form-select">
                  <option value="">All States</option>
                  <option>DRAFT</option>
                  <option>OPEN</option>
                  <option>PACKING</option>
                  <option>PACKAGED</option>
                  <option>SENT</option>
                  <option>RECEIVING</option>
                  <option>PARTIAL</option>
                  <option>RECEIVED</option>
                  <option>CANCELLED</option>
                  <option>CLOSED</option>
                </select>
              </div>
            </div>
          </div>

          <!-- Outlet Filter Column -->
          <div class="col-lg-3">
            <label class="form-label fw-semibold">
              <i class="bi bi-shop me-1"></i>Outlet
            </label>
            <select id="filterOutlet" class="form-select">
              <option value="">All Outlets</option>
              <?php foreach ($outlet_map as $id => $label): ?>
                <option value="<?= htmlspecialchars($id, ENT_QUOTES) ?>"><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
              <?php endforeach; ?>
            </select>
          </div>

          <!-- Enhanced Search Column -->
          <div class="col-lg-4">
            <label class="form-label fw-semibold">
              <i class="bi bi-search me-1"></i>Smart Search
            </label>
            <div class="input-group">
              <span class="input-group-text bg-white">
                <i class="bi bi-search text-primary"></i>
              </span>
              <input
                id="filterQ"
                type="text"
                class="form-control"
                placeholder="Transfer #, Vend #, outlet, supplier..."
                title="Search across transfers, outlets, and suppliers">
            </div>
            <small class="text-muted d-block mt-1">
              <i class="bi bi-lightbulb text-warning"></i> Pro tip: Press <kbd>/</kbd> to quick search
            </small>
          </div>
        </div>
      </div>
    </div>

    <!-- Table -->
    <div class="card mb-3">
      <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
          <div id="resultCount" class="small-note">Loading…</div>
          <div class="d-flex gap-2 align-items-center">
            <div class="small-note">Rows</div>
            <select id="ddlPerPage" class="form-select form-select-sm" style="width:auto;">
              <option>10</option>
              <option selected>25</option>
              <option>50</option>
              <option>100</option>
            </select>
            <button id="prevPage" class="btn btn-ghost btn-sm"><i class="bi bi-chevron-left"></i> Prev</button>
            <button id="nextPage" class="btn btn-ghost btn-sm">Next <i class="bi bi-chevron-right"></i></button>
          </div>
        </div>
        <div class="table-responsive">
          <table class="table table-hover align-middle table-sm">
            <thead class="table-light">
              <tr class="compact-header">
                <th style="width: 180px; padding: 0.4rem 0.5rem;"><i class="bi bi-tag me-1"></i>Type</th>
                <th style="width: 200px; padding: 0.4rem 0.5rem;"><i class="bi bi-building me-1"></i>Supplier</th>
                <th style="width: 160px; padding: 0.4rem 0.5rem;"><i class="bi bi-geo-alt me-1"></i>Destination</th>
                <th style="width: 130px; padding: 0.4rem 0.5rem;"><i class="bi bi-activity me-1"></i>Progress</th>
                <th style="width: 100px; padding: 0.4rem 0.5rem;"><i class="bi bi-flag me-1"></i>State</th>
                <th class="text-center" style="width: 80px; padding: 0.4rem 0.5rem;"><i class="bi bi-box-seam me-1"></i>Boxes</th>
                <th style="width: 150px; padding: 0.4rem 0.5rem;"><i class="bi bi-clock me-1"></i>Updated</th>
                <th class="text-end" style="width: 140px; padding: 0.4rem 0.5rem;">Actions</th>
              </tr>
            </thead>
            <tbody id="tblRows">
              <tr>
                <td colspan="8" class="text-center text-muted py-5">
                  <div class="d-flex flex-column align-items-center gap-2">
                    <i class="bi bi-inbox fs-1 opacity-50"></i>
                    <div>No transfers found</div>
                    <small class="text-muted">Create a new transfer to get started</small>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div><!-- /.wrap -->

  <!-- Detail Modal -->
  <div class="modal fade" id="modalQuick" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-box-seam me-2"></i>Transfer</h5>
          <button class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body">
          <div id="qBody" class="d-grid gap-3"></div>
        </div>
        <div class="modal-footer"><button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button></div>
      </div>
    </div>
  </div>

  <!-- Create Transfer -->
  <div class="modal fade" id="modalCreate" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title"><i class="bi bi-plus-circle me-2"></i>Create Transfer</h5>
          <button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <form id="formCreate" class="needs-validation" novalidate>
          <div class="modal-body">
            <div class="mb-3">
              <label class="form-label">Type</label>
              <select class="form-select" id="ct_type" required>
                <option value="STOCK">STOCK</option>
                <option value="JUICE">JUICE</option>
                <option value="STAFF">STAFF</option>
                <option value="RETURN">RETURN</option>
                <option value="PURCHASE_ORDER">PURCHASE_ORDER</option>
              </select>
            </div>
            <div class="mb-3" id="ct_supplier_wrap" style="display:none">
              <label class="form-label">Supplier</label>
              <select class="form-select" id="ct_supplier_select" required>
                <option value="">Choose supplier</option>
                <?php foreach ($supplier_map as $id => $name): ?>
                  <option value="<?= htmlspecialchars($id, ENT_QUOTES) ?>"><?= htmlspecialchars($name, ENT_QUOTES) ?></option>
                <?php endforeach; ?>
              </select>
              <div class="invalid-feedback">Choose a supplier</div>
              <div class="small-note">Required for PURCHASE_ORDER</div>
            </div>
            <div class="row g-3">
              <div class="col-md-6">
                <label class="form-label">From (Outlet)</label>
                <select class="form-select" id="ct_from_select" required>
                  <option value="">Choose outlet</option>
                  <?php foreach ($outlet_map as $id => $label): ?>
                    <option value="<?= htmlspecialchars($id, ENT_QUOTES) ?>"><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
                  <?php endforeach; ?>
                </select>
                <div class="invalid-feedback">Choose an outlet</div>
              </div>
              <div class="col-md-6">
                <label class="form-label">To (Outlet)</label>
                <select class="form-select" id="ct_to_select" required>
                  <option value="">Choose outlet</option>
                  <?php foreach ($outlet_map as $id => $label): ?>
                    <option value="<?= htmlspecialchars($id, ENT_QUOTES) ?>"><?= htmlspecialchars($label, ENT_QUOTES) ?></option>
                  <?php endforeach; ?>
                </select>
                <div class="invalid-feedback">Choose an outlet</div>
              </div>
            </div>
            <div class="mt-3">
              <div class="form-check">
                <input class="form-check-input" type="checkbox" id="ct_add_products" checked>
                <label class="form-check-label" for="ct_add_products">Add products immediately after creating</label>
              </div>
            </div>
          </div>
          <div class="modal-footer">
            <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button class="btn btn-primary btn-elevated" type="submit">Create</button>
          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- Action Modal -->
  <div class="modal fade" id="modalAction" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 id="maTitle" class="modal-title">Action</h5><button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div id="maBody" class="modal-body"></div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Close</button>
          <button class="btn btn-primary btn-elevated" id="maSubmit">Save</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Confirm Modal -->
  <div class="modal fade" id="modalConfirm" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <div class="modal-header">
          <h5 id="mcTitle" class="modal-title">Confirm</h5><button class="btn-close" data-bs-dismiss="modal"></button>
        </div>
        <div id="mcBody" class="modal-body">Are you sure?</div>
        <div class="modal-footer">
          <button class="btn btn-outline-secondary" data-bs-dismiss="modal">No</button>
          <button class="btn btn-danger btn-elevated" id="mcYes">Yes</button>
        </div>
      </div>
    </div>
  </div>

  <!-- 🆕 Receiving Mode Selection Modal -->
  <div class="modal fade" id="modalReceiving" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content">
        <div class="modal-header bg-primary text-white">
          <h5 id="receivingTitle" class="modal-title"><i class="bi bi-box-arrow-in-down me-2"></i>Choose Receiving Method</h5>
          <button class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
        </div>
        <div class="modal-body">
          <div class="alert alert-info mb-4">
            <i class="bi bi-info-circle me-2"></i>
            <strong>Transfer Summary:</strong> <span id="receivingItemCount">0</span> items, <span id="receivingTotalQty">0</span> total units
          </div>

          <div class="row g-4">
            <!-- Option 1: Begin Receiving (Manual) -->
            <div class="col-md-6">
              <div class="card h-100 border-warning shadow-sm receiving-option-card">
                <div class="card-body d-flex flex-column">
                  <div class="mb-3">
                    <div class="d-flex align-items-center mb-2">
                      <i class="bi bi-pencil-square fs-2 text-warning me-2"></i>
                      <h5 class="card-title mb-0">Begin Receiving</h5>
                    </div>
                    <p class="text-muted small mb-0">Manual entry mode</p>
                  </div>

                  <ul class="list-unstyled mb-4 flex-grow-1">
                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Enter actual received quantities</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Handle partial shipments</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Verify each item individually</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Complete when ready</li>
                  </ul>

                  <button class="btn btn-warning btn-lg w-100 btn-elevated" id="btnBeginReceiving">
                    <i class="bi bi-pencil-square me-2"></i>Begin Receiving
                  </button>
                </div>
              </div>
            </div>

            <!-- Option 2: Receive All (Auto-Fill) -->
            <div class="col-md-6">
              <div class="card h-100 border-success shadow-sm receiving-option-card">
                <div class="card-body d-flex flex-column">
                  <div class="mb-3">
                    <div class="d-flex align-items-center mb-2">
                      <i class="bi bi-lightning-charge-fill fs-2 text-success me-2"></i>
                      <h5 class="card-title mb-0">Receive All</h5>
                    </div>
                    <p class="text-muted small mb-0">Auto-complete instantly</p>
                  </div>

                  <ul class="list-unstyled mb-4 flex-grow-1">
                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Auto-fill all quantities</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Update inventory immediately</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Complete transfer in one click</li>
                    <li class="mb-2"><i class="bi bi-check-circle text-success me-2"></i>Sync to Lightspeed instantly</li>
                  </ul>

                  <button class="btn btn-success btn-lg w-100 btn-elevated" id="btnReceiveAll">
                    <i class="bi bi-lightning-charge-fill me-2"></i>Receive All Now
                  </button>
                </div>
              </div>
            </div>
          </div>

          <div class="alert alert-light border mt-4 mb-0">
            <div class="d-flex align-items-start">
              <i class="bi bi-lightbulb text-primary me-2 mt-1"></i>
              <div class="small">
                <strong>Tip:</strong> Use <strong>"Begin Receiving"</strong> if you need to verify each item or handle partial deliveries.
                Use <strong>"Receive All"</strong> for complete shipments where all items arrived as expected.
              </div>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-light">
          <button class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
        </div>
      </div>
    </div>
  </div>

  <!-- Activity overlay + toast container -->
  <div id="globalActivity" aria-live="polite" aria-atomic="true" style="display:none;">
    <div class="ga-box">
      <div class="spinner-border" role="status" aria-hidden="true"></div>
      <div>
        <div id="gaTitle" class="fw-semibold">Working…</div>
        <div id="gaSub" class="small-note">Please wait</div>
      </div>
    </div>
  </div>
  <div class="toast-container" id="toastContainer" aria-live="polite" aria-atomic="true"></div>

  <!-- Bootstrap 5 Framework -->
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <!-- Step 1: Load Configuration (PHP-generated JavaScript) -->
  <script src="config.js.php"></script>

  <!-- Step 2: Load Application Modules (auto-discovery via PHP) -->
  <?php
    $jsDir = __DIR__ . '/js';
    $files = glob($jsDir . '/*.js');
    natsort($files); // Loads in order: 00-config-init.js, 01-core-helpers.js, ..., 08-dom-ready.js
    foreach ($files as $f) {
      $rel = 'js/' . basename($f);
      echo '  <script src="' . $rel . '"></script>' . PHP_EOL;
    }
  ?>

</body>

</html>
