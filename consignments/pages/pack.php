<?php
declare(strict_types=1);

use Transfers\Lib\Db;
use Transfers\Lib\Security;

require_once __DIR__.'/../lib/Db.php';
require_once __DIR__.'/../lib/Security.php';

$pdo = Db::pdo();
$transferId = (int)($_GET['id'] ?? 0);

// Fetch transfer + items
$tStmt = $pdo->prepare("SELECT * FROM transfers WHERE id = ?");
$tStmt->execute([$transferId]);
$transfer = $tStmt->fetch();

$iStmt = $pdo->prepare("SELECT ti.*, NULL AS sku, NULL AS name FROM transfer_items ti WHERE transfer_id = ? ORDER BY id");
$iStmt->execute([$transferId]);
$items = $iStmt->fetchAll();

$kind = 'pack';
$outletFrom = $transfer['outlet_from'] ?? '';
$outletTo   = $transfer['outlet_to'] ?? '';

include __DIR__.'/../components/_container_open.php';
include __DIR__.'/../components/pack/header.php';
include __DIR__.'/../components/pack/draft_controls.php';
include __DIR__.'/../components/pack/table_pack.php';
include __DIR__.'/../components/pack/shipping_and_labels.php';

include __DIR__.'/../components/pack/printer_panel.php';       // C8 – printers
include __DIR__.'/../components/pack/add_products_modal.php';   // C7 – add products modal


include __DIR__.'/../components/pack/tracking_and_history.php';
include __DIR__.'/../components/pack/action_footer_pack.php';
include __DIR__.'/../components/_container_close.php';
?>

<link rel="stylesheet" href="/assets/transfers/css/transfer-core.css">
<link rel="stylesheet" href="/assets/transfers/css/transfer-pack.css">

<script type="module" src="/assets/transfers/js/pack/init.js"></script>

<script type="module">
  import { initPack } from '/assets/transfers/js/pack/init.js';
  initPack({
    transferId: <?= (int)$transferId ?>,
    userId: <?= (int)Security::currentUserId() ?>,
    apiBase: '/modules/transfers/stock/api',
    csrf: '<?= Security::csrfToken(); ?>'
  });
</script>
