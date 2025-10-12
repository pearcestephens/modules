<?php
/** @var string $kind ('pack'|'receive') */
/** @var int    $transferId */
/** @var string $outletFrom */
/** @var string $outletTo */
?>
<div class="vs-transfer vs-transfer--<?= htmlspecialchars($kind) ?>"
     data-transfer-id="<?= (int)$transferId ?>"
     data-outlet-from="<?= htmlspecialchars($outletFrom) ?>"
     data-outlet-to="<?= htmlspecialchars($outletTo) ?>">
  <main class="vs-transfer__container container-fluid">
