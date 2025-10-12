<?php
declare(strict_types=1);

// Variables expected:
// - int $transferId
// - array|null $transfer
// - array $items
// - int $transferCount

use Modules\Shared\Helpers;

$transferId = (int)($transferId ?? 0);
$transfer = $transfer ?? null;
$items = $items ?? [];

// Load legacy libs used by component blocks
require_once dirname(__DIR__, 3) . '/lib/Db.php';
require_once dirname(__DIR__, 3) . '/lib/Security.php';

// Variables expected by container and components
$kind = 'receive';
$outletFrom = is_array($transfer) ? (string)($transfer['outlet_from'] ?? '') : '';
$outletTo   = is_array($transfer) ? (string)($transfer['outlet_to'] ?? '') : '';
?>
      <?php if ($transferId <= 0): ?>
        <div class="alert alert-warning">No transfer selected. Append ?transfer=ID to the URL.</div>
      <?php endif; ?>

      <?php
        // Include legacy-styled blocks if present (non-fatal if missing)
        $root = dirname(__DIR__, 3);
        $base = $root . '/components/receive';
        $inc = static function(string $file) use ($base, $transferId, $transfer, $items, $kind, $outletFrom, $outletTo): void {
          $path = $base . '/' . $file;
          if (is_file($path)) { include $path; }
        };
      ?>
      <?php $containerOpen = dirname(__DIR__, 3) . '/components/_container_open.php'; if (is_file($containerOpen)) { include $containerOpen; } ?>
      <?php $inc('header_scanner.php'); ?>
      <?php $inc('filter_toolbar_stats.php'); ?>
      <?php $inc('table_receive.php'); ?>
      <?php $inc('confidence_and_disc.php'); ?>
      <?php $inc('exceptions_and_decl.php'); ?>
      <?php $inc('success_modal.php'); ?>
      <?php $containerClose = dirname(__DIR__, 3) . '/components/_container_close.php'; if (is_file($containerClose)) { include $containerClose; } ?>

<link rel="stylesheet" href="<?= Modules\Shared\Helpers::url('/assets/css/transfer.css'); ?>">

<script type="module">
  import { initReceive } from '<?= Modules\Shared\Helpers::url('/js/receive/init.js'); ?>';
  initReceive({
    transferId: <?= (int)$transferId ?>,
    userId: <?= (int)\Transfers\Lib\Security::currentUserId() ?>,
    apiBase: '<?= Modules\Shared\Helpers::url('/api'); ?>',
    csrf: '<?= \Transfers\Lib\Security::csrfToken(); ?>',
    enableScanner: true
  });
</script>
