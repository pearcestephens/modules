<?php
/**
 * Consignments Module - Main Index / Transfer Manager
 *
 * High-level management dashboard for consignment transfers between locations.
 * Integrates the sophisticated Transfer Manager as the primary interface.
 *
 * @package CIS\Consignments
 * @version 2.0.0
 * @created 2025-11-01
 */

declare(strict_types=1);

// Load module bootstrap (includes base/bootstrap.php)
require_once __DIR__ . '/bootstrap.php';

// Initialize session if not already started
if (session_status() !== PHP_SESSION_ACTIVE) {
    CIS\Base\Session::init();
}

// Authentication check
if (!function_exists('isLoggedIn') || !isLoggedIn()) {
    $currentUrl = $_SERVER['REQUEST_URI'] ?? '/modules/consignments/';
    $loginUrl = 'https://staff.vapeshed.co.nz/login.php?redirect=' . urlencode($currentUrl);
    header('Location: ' . $loginUrl);
    exit;
}

/**
 * Load initialization data for Transfer Manager
 *
 * @return array Init data including outlets, suppliers, CSRF token
 */
function loadTransferManagerInit(): array
{
    try {
        $pdo = CIS\Base\Database::pdo();

        // Generate CSRF token if not exists
        if (!isset($_SESSION['tt_csrf'])) {
            $_SESSION['tt_csrf'] = bin2hex(random_bytes(16));
        }

        // Load outlets
        $outletMap = [];
        $stmt = $pdo->query(
            "SELECT id, COALESCE(NULLIF(name,''), NULLIF(store_code,''), NULLIF(physical_city,''), id) AS label
             FROM vend_outlets
             WHERE deleted_at IS NULL OR deleted_at = '0000-00-00 00:00:00' OR deleted_at = '0000-00-00'
             ORDER BY label ASC"
        );
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $outletMap[$row['id']] = $row['label'];
        }

        // Load suppliers
        $supplierMap = [];
        $supplierStmt = $pdo->query(
            "SELECT id, name FROM vend_suppliers
             WHERE deleted_at IS NULL OR deleted_at = '' OR deleted_at = '0'
             ORDER BY name ASC"
        );
        while ($row = $supplierStmt->fetch(PDO::FETCH_ASSOC)) {
            $supplierMap[$row['id']] = $row['name'] ?: $row['id'];
        }

        // Get sync state from file
        $syncFile = __DIR__ . '/TransferManager/.sync_enabled';
        $syncEnabled = true;
        if (file_exists($syncFile)) {
            $syncEnabled = (trim(file_get_contents($syncFile)) === '1');
        }

        // Check Lightspeed sync status from queue table
        $lastSyncTime = null;
        $syncAgeMinutes = null;
        $syncStatus = 'unknown';
        $queueStats = [
            'total_jobs' => 0,
            'completed_jobs' => 0,
            'failed_jobs' => 0,
            'processing_jobs' => 0
        ];

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

        $syncStmt = $pdo->query($syncQuery);
        if ($row = $syncStmt->fetch(PDO::FETCH_ASSOC)) {
            $lastSyncTime = $row['last_sync'];
            $queueStats['total_jobs'] = (int)$row['total_jobs'];
            $queueStats['completed_jobs'] = (int)$row['completed_jobs'];
            $queueStats['failed_jobs'] = (int)$row['failed_jobs'];
            $queueStats['processing_jobs'] = (int)$row['processing_jobs'];

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
                $syncStatus = 'idle';
            }
        }

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
        return [
            '_error' => $e->getMessage(),
            'csrf_token' => $_SESSION['tt_csrf'] ?? bin2hex(random_bytes(16)),
            'outlet_map' => [],
            'supplier_map' => [],
            'sync_enabled' => true,
            'debug' => ['errors' => [$e->getMessage()]]
        ];
    }
}

// Load data
$initData = loadTransferManagerInit();
$csrf = $initData['csrf_token'] ?? '';
$syncEnabled = (bool)($initData['sync_enabled'] ?? true);
$outletMap = $initData['outlet_map'] ?? [];
$supplierMap = $initData['supplier_map'] ?? [];
$lsBase = $initData['ls_consignment_base'] ?? '';
$debugInfo = $initData['debug'] ?? null;
$initError = $initData['_error'] ?? null;

// Page metadata
$pageTitle = 'Transfer Manager - Consignments';
$pageDescription = 'High-level management dashboard for consignment transfers between locations';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>">

    <!-- Bootstrap 5.3.3 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

    <!-- Transfer Manager Styles (scoped to content area) -->
    <link href="TransferManager/styles.css" rel="stylesheet">

    <style>
        /* Scoped styles - only affect the content wrapper */
        .consignments-content {
            max-width: 1600px;
            margin: 0 auto;
            padding: 20px 30px;
            box-sizing: border-box;
            background: #ffffff;
        }

        /* Ultra-Compact Table Design */
        .consignments-content .compact-header th {
            padding: 0.4rem 0.5rem !important;
            font-size: 0.875rem;
            font-weight: 600;
            white-space: nowrap;
        }

        .consignments-content .table-sm td {
            padding: 0.5rem 0.75rem !important;
            vertical-align: middle;
        }

        /* Compact Vend Icon Button */
        .consignments-content .btn-vend-compact {
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

        .consignments-content .btn-vend-compact.vend-active {
            background: linear-gradient(135deg, #f0fdf4 0%, #dcfce7 100%);
            border: 2px solid #86efac;
            box-shadow: 0 2px 4px rgba(134, 239, 172, 0.2);
        }

        .consignments-content .btn-vend-compact.vend-active:hover {
            transform: scale(1.08);
            background: linear-gradient(135deg, #dcfce7 0%, #bbf7d0 100%);
            border-color: #4ade80;
            box-shadow: 0 4px 8px rgba(74, 222, 128, 0.3);
        }

        .consignments-content .btn-vend-compact.vend-active svg {
            width: 28px !important;
            height: 28px !important;
        }

        .consignments-content .btn-vend-compact.vend-disabled {
            cursor: not-allowed;
            opacity: 0.4;
            background: #f8fafc;
            border: 2px solid #e2e8f0;
        }

        /* Debug alert positioning */
        .debug-alert {
            position: fixed;
            top: 10px;
            right: 10px;
            z-index: 9999;
            max-width: 700px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
        }
    </style>
</head>

<body>
    <!-- Main Content Wrapper (scoped) -->
    <div class="consignments-content">

        <!-- Debug Info (if enabled) -->
        <?php if ($debugInfo): ?>
            <?php
            $hasError = $initError || count($outletMap) === 0;
            $alertType = $hasError ? 'alert-danger' : 'alert-success';
            $alertIcon = $hasError ? '🚨' : '✅';
            ?>
            <div class="alert <?= $alertType ?> alert-dismissible fade show debug-alert" role="alert">
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <h5 class="alert-heading"><?= $alertIcon ?> System Status</h5>

                <div class="mb-2">
                    <strong>Outlets:</strong>
                    <span class="badge <?= count($outletMap) > 0 ? 'bg-success' : 'bg-danger' ?>">
                        <?= count($outletMap) ?>
                    </span>
                </div>
                <div class="mb-2">
                    <strong>Suppliers:</strong>
                    <span class="badge <?= count($supplierMap) > 0 ? 'bg-success' : 'bg-warning' ?>">
                        <?= count($supplierMap) ?>
                    </span>
                </div>

                <?php if ($initError): ?>
                    <hr>
                    <div class="alert alert-danger mb-0" style="padding: 0.75rem;">
                        <strong>Error:</strong> <?= htmlspecialchars($initError) ?>
                    </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <!-- Include Transfer Manager Frontend UI -->
        <?php require_once __DIR__ . '/TransferManager/frontend-content.php'; ?>

    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

    <!-- Transfer Manager Configuration -->
    <script>
        // Global configuration for Transfer Manager
        window.TT_CONFIG = {
            apiUrl: '/modules/consignments/TransferManager/api.php',
            csrfToken: <?= json_encode($csrf) ?>,
            lsConsignmentBase: <?= json_encode($lsBase) ?>,
            outletMap: <?= json_encode($outletMap) ?>,
            supplierMap: <?= json_encode($supplierMap) ?>,
            syncEnabled: <?= json_encode($syncEnabled) ?>,
            syncStatus: <?= json_encode($initData['sync_status'] ?? 'unknown') ?>,
            lastSyncTime: <?= json_encode($initData['last_sync_time'] ?? null) ?>,
            syncAgeMinutes: <?= json_encode($initData['sync_age_minutes'] ?? null) ?>,
            queueStats: <?= json_encode($initData['queue_stats'] ?? []) ?>
        };
    </script>

    <!-- Transfer Manager JavaScript Modules (in order) -->
    <script src="TransferManager/js/00-config-init.js"></script>
    <script src="TransferManager/js/01-core-helpers.js"></script>
    <script src="TransferManager/js/02-ui-components.js"></script>
    <script src="TransferManager/js/03-transfer-functions.js"></script>
    <script src="TransferManager/js/04-list-refresh.js"></script>
    <script src="TransferManager/js/05-detail-modal.js"></script>
    <script src="TransferManager/js/06-event-listeners.js"></script>
    <script src="TransferManager/js/07-init.js"></script>
    <script src="TransferManager/js/08-dom-ready.js"></script>
</body>
</html>
