<?php
declare(strict_types=1);

// Variables expected:
// - int $transferId
// - array|null $transfer
// - array $items
// - int $transferCount

use Modules\Base\Helpers;

// Fallback defaults to avoid notices
$transferId = (int)($transferId ?? 0);
$transfer = $transfer ?? null;
$items = $items ?? [];

// Load legacy libs used by component blocks
require_once dirname(__DIR__, 2) . '/lib/Db.php';
require_once dirname(__DIR__, 2) . '/lib/Security.php';

// Variables expected by container and components
$kind = 'pack';
$outletFrom = is_array($transfer) ? (string)($transfer['outlet_from'] ?? '') : '';
$outletTo   = is_array($transfer) ? (string)($transfer['outlet_to'] ?? '') : '';
?>
      <?php if ($transferId <= 0): ?>
        <div class="alert alert-warning">No transfer selected. Append ?transfer=ID to the URL.</div>
      <?php endif; ?>

      <?php if (!empty($_GET['success'])): ?>
        <div class="alert alert-success alert-sm mb-3">Saved.</div>
      <?php elseif (!empty($_GET['error'])): ?>
        <?php
          // Allowlist validation to prevent XSS
          $allowedCodes = ['csrf', 'validation', 'db', 'not_found', 'locked'];
          $rawCode = $_GET['error'] ?? '';
          $code = in_array($rawCode, $allowedCodes, true) ? $rawCode : 'unknown';
          
          $msg = match ($code) {
            'csrf' => 'Security check failed. Please try again.',
            'validation' => 'Please provide a SKU and a valid quantity (>= 1).',
            'db' => 'Server error saving the line. Please retry.',
            'not_found' => 'Transfer not found.',
            'locked' => 'Transfer is locked by another user.',
            default => 'Something went wrong.'
          };
        ?>
        <div class="alert alert-danger alert-sm mb-3"><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></div>
      <?php endif; ?>

      <?php
        // Include legacy-styled blocks if present (non-fatal if missing)
        // __DIR__ is .../modules/consignments/transfers/views/pack
        // Go up 3 -> .../modules/consignments, then into /components/pack
        $root = dirname(__DIR__, 3);
        $base = $root . '/components/pack';
        // Helper to include safely
        $inc = static function(string $file) use ($base, $transferId, $transfer, $items, $kind, $outletFrom, $outletTo): void {
          $path = $base . '/' . $file;
          if (is_file($path)) { include $path; }
        };
      ?>
  <?php // Outer container wrappers (if present); these render inside the CIS template card body
  $containerOpen = dirname(__DIR__, 3) . '/components/_container_open.php';
  if (is_file($containerOpen)) { include $containerOpen; }
  ?>
      <?php $inc('header.php'); ?>
      <?php $inc('draft_controls.php'); ?>
      <?php $inc('table_pack.php'); ?>
      <?php $inc('shipping_and_labels.php'); ?>
      <?php $inc('printer_panel.php'); ?>
      <?php $inc('add_products_modal.php'); ?>
      <?php $inc('tracking_and_history.php'); ?>
      <?php $inc('action_footer_pack.php'); ?>
      <?php $containerClose = dirname(__DIR__, 3) . '/components/_container_close.php'; if (is_file($containerClose)) { include $containerClose; } ?>

<?php $dev = isset($_GET['dev']) && $_GET['dev'] === '1'; ?>

<link rel="stylesheet" href="<?= Modules\Base\Helpers::url('/assets/css/transfer.css'); ?>">

<?php if ($dev): ?>
  <!-- Dev: ES modules -->
  <script type="module">
    import { initPack } from "<?= Modules\Base\Helpers::url('/js/pack/init.js'); ?>";
    initPack({ 
      transferId: <?= (int)$transferId ?>, 
      userId: <?= (int)\Transfers\Lib\Security::currentUserId() ?>, 
      apiBase: "<?= Modules\Base\Helpers::url('/api'); ?>", 
      csrf: "<?= \Transfers\Lib\Security::csrfToken(); ?>" 
    });
  </script>
<?php else: ?>
  <!-- Prod: bundles -->
  <script src="<?= Modules\Base\Helpers::url('/assets/js/core.bundle.js'); ?>"></script>
  <script src="<?= Modules\Base\Helpers::url('/assets/js/pack.bundle.js'); ?>"></script>
  <script>
    initPack({ 
      transferId: <?= (int)$transferId ?>, 
      userId: <?= (int)\Transfers\Lib\Security::currentUserId() ?>, 
      apiBase: "<?= Modules\Base\Helpers::url('/api'); ?>", 
      csrf: "<?= \Transfers\Lib\Security::csrfToken(); ?>" 
    });
  </script>
<?php endif; ?>
