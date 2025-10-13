<?php
declare(strict_types=1);

use Modules\Base\Helpers;

$flash = (string)($_GET['flash'] ?? '');
$tx    = htmlspecialchars((string)($_GET['tx'] ?? ''), ENT_QUOTES, 'UTF-8');
$rid   = htmlspecialchars((string)($_GET['rid'] ?? ''), ENT_QUOTES, 'UTF-8');

$messages = [
  'pack_success'     => $tx !== '' ? "Transfer #{$tx} packed and set ready for delivery." : "Transfer packed and set ready for delivery.",
  'receive_complete' => $tx !== '' ? "Transfer #{$tx} fully received".($rid ? " (Receipt {$rid})" : '')."." : "Transfer fully received.",
  'receive_partial'  => $tx !== '' ? "Partial receive saved for transfer #{$tx}".($rid ? " (Receipt {$rid})" : '')."." : "Partial receive saved.",
];
?>
<div class="cis-content">

  <?php if (isset($messages[$flash])): ?>
    <div class="alert alert-success" role="alert" style="margin-bottom:12px;">
      <?= $messages[$flash] ?>
    </div>
    <script>
      // clean the query so the banner doesn't reappear on refresh
      try { history.replaceState(null, '', location.pathname); } catch (e) {}
    </script>
  <?php endif; ?>

  <div class="cis-module-card">
    <div class="cis-module-card-header">
      <strong>Transfers</strong>
    </div>
    <div class="cis-module-card-body">
      <p class="text-muted">Welcome to the Consignments module.</p>
      <div class="d-flex" style="gap:.5rem;">
        <a class="btn btn-primary"   href="<?= Helpers::url('/transfers/pack'); ?>">Open Pack</a>
        <a class="btn btn-secondary" href <?= Helpers::url('/transfers/receive'); ?>>Open Receive</a>
        <a class="btn btn-light"     href="<?= Helpers::url('/transfers/hub'); ?>">Open Hub</a>
      </div>
    </div>
  </div>
</div>
