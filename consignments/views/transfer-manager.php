<?php
/**
 * Consignments Module - Transfer Manager
 *
 * @package CIS\Consignments
 * @version 3.0.0
 */

declare(strict_types=1);

// Load CIS Template
require_once __DIR__ . '/../lib/CISTemplate.php';

// Initialize template
$template = new CISTemplate();
$template->setTitle('Transfer Manager');
$template->setBreadcrumbs([
    ['label' => 'Home', 'url' => '/', 'icon' => 'fa-home'],
    ['label' => 'Consignments', 'url' => '/modules/consignments/'],
    ['label' => 'Transfer Manager', 'url' => '/modules/consignments/?route=transfer-manager', 'active' => true]
]);

// Start content capture
$template->startContent();
?>

<?php
/**
 * Load initialization data for Transfer Manager
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

        // Get sync state
        $syncFile = dirname(__DIR__) . '/TransferManager/.sync_enabled';
        $syncEnabled = true;
        if (file_exists($syncFile)) {
            $syncEnabled = (trim(file_get_contents($syncFile)) === '1');
        }

        // Check Lightspeed sync status
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

        $lastSyncTime = null;
        $syncAgeMinutes = null;
        $syncStatus = 'unknown';
        $queueStats = ['total_jobs' => 0, 'completed_jobs' => 0, 'failed_jobs' => 0, 'processing_jobs' => 0];

        $syncStmt = $pdo->query($syncQuery);
        if ($row = $syncStmt->fetch(PDO::FETCH_ASSOC)) {
            $lastSyncTime = $row['last_sync'];
            $queueStats = [
                'total_jobs' => (int)$row['total_jobs'],
                'completed_jobs' => (int)$row['completed_jobs'],
                'failed_jobs' => (int)$row['failed_jobs'],
                'processing_jobs' => (int)$row['processing_jobs']
            ];

            if ($lastSyncTime && $lastSyncTime !== '1970-01-01 00:00:00') {
                $syncTimestamp = strtotime($lastSyncTime);
                $syncAgeMinutes = round((time() - $syncTimestamp) / 60);

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
            'queue_stats' => $queueStats
        ];
    } catch (Exception $e) {
        return [
            '_error' => $e->getMessage(),
            'csrf_token' => $_SESSION['tt_csrf'] ?? bin2hex(random_bytes(16)),
            'outlet_map' => [],
            'supplier_map' => [],
            'sync_enabled' => true
        ];
    }
}

// Load initialization data
$initData = loadTransferManagerInit();

// Extract variables with defaults
$csrf = $initData['csrf_token'] ?? bin2hex(random_bytes(16));
$syncEnabled = (bool)($initData['sync_enabled'] ?? true);
$outletMap = $initData['outlet_map'] ?? [];
$supplierMap = $initData['supplier_map'] ?? [];
$lsBase = $initData['ls_consignment_base'] ?? 'https://vapeshed.retail.lightspeed.app/app/2.0/consignments/';

// Ensure arrays are initialized even if empty
if (!is_array($outletMap)) $outletMap = [];
if (!is_array($supplierMap)) $supplierMap = [];
?>

<!-- Transfer Manager Configuration (Global APP_CONFIG for JS) -->
<script>
    window.APP_CONFIG = {
        CSRF: <?= json_encode($csrf) ?>,
        LS_CONSIGNMENT_BASE: <?= json_encode($lsBase) ?>,
        OUTLET_MAP: <?= json_encode($outletMap) ?>,
        SUPPLIER_MAP: <?= json_encode($supplierMap) ?>,
        SYNC_ENABLED: <?= json_encode($syncEnabled) ?>,
        API_URL: '/modules/consignments/TransferManager/api.php',
        SYNC_STATUS: <?= json_encode($initData['sync_status'] ?? 'unknown') ?>,
        LAST_SYNC_TIME: <?= json_encode($initData['last_sync_time'] ?? null) ?>,
        SYNC_AGE_MINUTES: <?= json_encode($initData['sync_age_minutes'] ?? null) ?>,
        QUEUE_STATS: <?= json_encode($initData['queue_stats'] ?? []) ?>
    };
        // Expose role for client-side admin tools
        window.USER_ROLE = <?= json_encode(strtolower((string)($_SESSION['user_role'] ?? ''))) ?>;
</script>

<?php
// Include the actual Transfer Manager UI content wrapped in a scoped container
// Variables in scope: $outletMap, $supplierMap, $syncEnabled, $lsBase, $csrf
echo '<div class="transfer-manager-wrap">';
require_once dirname(__DIR__) . '/TransferManager/frontend-content.php';
echo '</div>';

// End content capture and render
$template->endContent();
$template->render();
