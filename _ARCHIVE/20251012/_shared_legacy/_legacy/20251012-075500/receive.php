<?php
declare(strict_types=1);

use Transfers\Lib\Db;
use Transfers\Lib\Security;

require_once __DIR__.'/../lib/Db.php';
require_once __DIR__.'/../lib/Security.php';

$pdo = Db::pdo();
$transferId = (int)($_GET['id'] ?? 0);

$tStmt = $pdo->prepare("SELECT * FROM transfers WHERE id = ?");
$tStmt->execute([$transferId]);
$transfer = $tStmt->fetch();

$iStmt = $pdo->prepare("SELECT ti.*, NULL AS sku, NULL AS name FROM transfer_items ti WHERE transfer_id = ? ORDER BY id");
$iStmt->execute([$transferId]);
$items = $iStmt->fetchAll();

$kind = 'receive';
$outletFrom = $transfer['outlet_from'] ?? '';
$outletTo   = $transfer['outlet_to'] ?? '';

include __DIR__.'/../components/_container_open.php';
include __DIR__.'/../components/receive/header_scanner.php';
include __DIR__.'/../components/receive/filter_toolbar_stats.php';
include __DIR__.'/../components/receive/table_receive.php';
include __DIR__.'/../components/receive/confidence_and_disc.php';
include __DIR__.'/../components/receive/exceptions_and_decl.php';
include __DIR__.'/../components/receive/success_modal.php';
include __DIR__.'/../components/_container_close.php';
?>
<link rel="stylesheet" href="/assets/transfers/css/transfer-core.css">
<link rel="stylesheet" href="/assets/transfers/css/transfer-receive.css">
<script type="module" src="/assets/transfers/js/receive/init.js"></script>
<script type="module">
  import { initReceive } from '/assets/transfers/js/receive/init.js';
  initReceive({
    transferId: <?= (int)$transferId ?>,
    userId: <?= (int)Security::currentUserId() ?>,
    apiBase: '/modules/transfers/stock/api',
    csrf: '<?= Security::csrfToken(); ?>',
    enableScanner: true
  });
</script>
