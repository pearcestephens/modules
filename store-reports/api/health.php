<?php
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../Response.php';
require_once __DIR__ . '/../JsonLogger.php';
sr_rate_limit('health', 60);

$pdo = sr_pdo();
$dbOk = false; $dbVersion = null; $err = null;
if ($pdo) {
    try {
        $row = $pdo->query('SELECT VERSION() as v')->fetch(PDO::FETCH_ASSOC);
        $dbVersion = $row['v'] ?? null;
        $dbOk = true;
    } catch (Throwable $e) {
        $err = $e->getMessage();
    }
}
$logger = new JsonLogger('store-reports-health');
$logger->info('health_check', ['db_available'=>$dbOk,'db_version'=>$dbVersion,'error'=>$err]);
SR_Response::json([
    'service' => 'store_reports_module',
    'status' => 'ok',
    'db' => [ 'available' => $dbOk, 'version' => $dbVersion, 'error' => $err ],
    'app' => [ 'env' => APP_ENV, 'debug' => APP_DEBUG, 'version' => APP_VERSION ]
]);
?>
