<?php
declare(strict_types=1);

use Modules\Shared\Helpers;

/** @var array $data */
/** @var Modules\Consignments\Shared\View $view */
/** @var Modules\Shared\View $view */
$view = $view ?? null; // provided by PageController
?>
<!doctype html>
<html lang="en">
  <head>
    <?php $view?->include(__DIR__ . '/../partials/head.php'); ?>
  </head>
  <body>
    <div id="app" class="app">
      <?php $view?->include(__DIR__ . '/../partials/topbar.php'); ?>
      <div class="app-body d-flex">
        <?php $view?->include(__DIR__ . '/../partials/sidebar.php'); ?>
        <main class="main flex-fill p-3">
          <?= $content ?? '' ?>
        </main>
      </div>
      <?php $view?->include(__DIR__ . '/../partials/footer.php'); ?>
    </div>
  </body>
  </html>
