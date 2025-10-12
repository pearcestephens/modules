<?php
declare(strict_types=1);

use Transfers\Lib\Db;
use Transfers\Lib\Security;

// Load legacy libs from module root
require_once __DIR__.'/../../../lib/Db.php';
require_once __DIR__.'/../../../lib/Security.php';

$pdo = Db::pdo();
// Support both ?transfer= and legacy ?id=
$transferId = isset($_GET['transfer']) ? (int)$_GET['transfer'] : (int)($_GET['id'] ?? 0);

// Fetch transfer + items (tolerant if tables differ)
$transfer = null;
$items = [];
try {
  $tStmt = $pdo->prepare("SELECT * FROM transfers WHERE id = ?");
  $tStmt->execute([$transferId]);
  $transfer = $tStmt->fetch();

  $iStmt = $pdo->prepare("SELECT ti.*, NULL AS sku, NULL AS name FROM transfer_items ti WHERE transfer_id = ? ORDER BY id");
  $iStmt->execute([$transferId]);
  $items = $iStmt->fetchAll();
} catch (\Throwable $e) {
  // fail-soft: render UI without DB rows
  $transfer = $transfer ?? null;
  $items = $items ?? [];
}

$kind = 'pack';
$outletFrom = $transfer['outlet_from'] ?? '';
$outletTo   = $transfer['outlet_to'] ?? '';

// Safe include helper for existing component files under modules/consignments/components/pack
$base = dirname(__DIR__, 3) . '/components/pack';
$inc = static function(string $file) use ($base, $transferId, $transfer, $items): void {
  $path = $base . '/' . $file;
  if (is_file($path)) { include $path; }
};

// Render component blocks
$inc('header.php');
$inc('draft_controls.php');
$inc('table_pack.php');
$inc('shipping_and_labels.php');
$inc('printer_panel.php');       // C8 – printers
$inc('add_products_modal.php');  // C7 – add products modal
$inc('tracking_and_history.php');
$inc('action_footer_pack.php');
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
