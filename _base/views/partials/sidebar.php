<?php
declare(strict_types=1);

use Modules\Shared\Helpers;
?>
<aside class="sidebar">
  <nav class="nav">
    <div class="muted" style="padding:4px 8px;">Transfers</div>
    <a href="<?= Helpers::url('/transfers'); ?>">Home</a>
    <a href="<?= Helpers::url('/transfers/pack'); ?>">Pack</a>
    <a href="<?= Helpers::url('/transfers/receive'); ?>">Receive</a>
  </nav>
</aside>
