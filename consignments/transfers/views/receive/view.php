<?php
declare(strict_types=1);

use Modules\Shared\Helpers;

/** @var int $transferCount */
// Resolve transfer id from query (supports ?transfer= or legacy ?id=)
$transferId = 0;
if (isset($_GET['transfer'])) { $transferId = (int)$_GET['transfer']; }
elseif (isset($_GET['id'])) { $transferId = (int)$_GET['id']; }
?>

<div class="card">
  <div class="card-header">Receive</div>
  <div class="card-body">
    <?php if (!empty($_GET['success'])): ?>
      <div class="alert alert-success alert-sm mb-3">Line received.</div>
    <?php elseif (!empty($_GET['error'])): ?>
      <?php
        $code = (string)($_GET['error'] ?? '');
        $msg = 'Something went wrong.';
        if ($code === 'csrf') { $msg = 'Security check failed. Please try again.'; }
        elseif ($code === 'validation') { $msg = 'Please provide a SKU and a valid quantity (>= 1).'; }
        elseif ($code === 'db') { $msg = 'Server error saving the line. Please retry.'; }
      ?>
      <div class="alert alert-danger alert-sm mb-3"><?php echo htmlspecialchars($msg, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php endif; ?>
    <p>Total transfers in system: <strong><?= (int)($transferCount ?? 0); ?></strong></p>

    <form method="post" action="<?= Helpers::url('/transfers/api/receive/add-line'); ?>">
      <?= Helpers::csrfTokenInput(); ?>
      <input type="hidden" name="transfer" value="<?= (int)$transferId; ?>">
      <div style="margin-bottom:8px;">
        <label for="sku">SKU</label><br>
        <input type="text" id="sku" name="sku" required style="padding:6px; width:260px;">
      </div>
      <div style="margin-bottom:8px;">
        <label for="qty">Qty</label><br>
        <input type="number" id="qty" name="qty" min="1" step="1" required style="padding:6px; width:120px;">
      </div>
      <button class="btn btn-primary" type="submit">Receive Line</button>
    </form>
  </div>
</div>
