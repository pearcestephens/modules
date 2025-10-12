<?php
declare(strict_types=1);

if (!headers_sent()) {
  header('Content-Type: text/html; charset=UTF-8');
}

$__cisContent = $content ?? '';
$__pageTitle  = isset($page_title) && is_string($page_title) ? htmlspecialchars($page_title, ENT_QUOTES, 'UTF-8') : '';
$__pageBlurb  = isset($page_blurb) && is_string($page_blurb) ? htmlspecialchars($page_blurb, ENT_QUOTES, 'UTF-8') : '';

/**
 * CoreUI v2 (Bootstrap 4) classes. Keep it static; don’t “fix up” with JS.
 * If you want to add/override from a controller, pass $body_class.
 */
$body_class = trim(($body_class ?? '') . ' app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show');
?>

<?php
// Temporarily disabled due to parse errors in config.php
// TODO: Re-enable once assets/functions/config.php is fixed
// include $_SERVER['DOCUMENT_ROOT'].'/assets/functions/config.php';
?>

<?php
// Head-only: replicate global header without opening <body>
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
  <meta name="robots" content="noindex, nofollow">
  <title><?= $__pageTitle !== '' ? $__pageTitle : 'CIS' ?></title>

  <!-- Core CSS you want globally -->
  <link rel="stylesheet" href="https://staff.vapeshed.co.nz/assets/css/style1.css">
  <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/ui-lightness/jquery-ui.css">

  <!-- Pace loader -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/pace/1.2.4/themes/blue/pace-theme-minimal.min.css">

  <!-- Icons -->
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/simple-line-icons/2.5.5/css/simple-line-icons.min.css">

  <!-- Compatibility + custom -->
  <link rel="stylesheet" href="https://staff.vapeshed.co.nz/assets/css/bootstrap-compatibility.css">
  <link rel="stylesheet" href="https://staff.vapeshed.co.nz/assets/css/custom.css">
  <link rel="stylesheet" href="https://staff.vapeshed.co.nz/assets/css/sidebar-fixes.css">
  <link rel="stylesheet" href="https://staff.vapeshed.co.nz/assets/css/sidebar-dark-theme-restore.css">

  <!-- jQuery first (global) -->
  <script src="https://code.jquery.com/jquery-3.7.1.min.js" crossorigin="anonymous"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>
</head>

<body class="<?= htmlspecialchars($body_class, ENT_QUOTES, 'UTF-8') ?>">

  <?php include $_SERVER['DOCUMENT_ROOT'].'/assets/template/header.php'; ?>

  <div class="app-body">
    <?php include $_SERVER['DOCUMENT_ROOT'].'/assets/template/sidemenu.php'; ?>

    <main class="main">
      <!-- Breadcrumb (optional) -->
      <ol class="breadcrumb">
        <?php if (!empty($breadcrumbs) && is_array($breadcrumbs)): ?>
          <?php foreach ($breadcrumbs as $bc):
            $label  = htmlspecialchars((string)($bc['label'] ?? ''), ENT_QUOTES, 'UTF-8');
            $href   = (string)($bc['href'] ?? '');
            $active = !empty($bc['active']);
          ?>
            <?php if ($active || $href === ''): ?>
              <li class="breadcrumb-item active"><?= $label ?></li>
            <?php else: ?>
              <li class="breadcrumb-item"><a href="<?= htmlspecialchars($href, ENT_QUOTES, 'UTF-8') ?>"><?= $label ?></a></li>
            <?php endif; ?>
          <?php endforeach; ?>
        <?php else: ?>
          <li class="breadcrumb-item">Home</li>
          <li class="breadcrumb-item active"><?= $__pageTitle ?></li>
        <?php endif; ?>
        <li class="breadcrumb-menu d-md-down-none">
          <?php include $_SERVER['DOCUMENT_ROOT'].'/assets/template/quick-product-search.php'; ?>
        </li>
      </ol>

      <!-- IMPORTANT: no extra container/card here -->
      <div class="cis-content">
        <?= $__cisContent ?>
      </div>
    </main>
  </div>

  <?php include $_SERVER['DOCUMENT_ROOT'].'/assets/template/html-footer.php'; ?>
  <?php include $_SERVER['DOCUMENT_ROOT'].'/assets/template/footer.php'; ?>

  <!-- Safety loader: only load Popper/Bootstrap4/CoreUI if missing -->
  <script>
  (function() {
    var needBS4 = !(window.jQuery && typeof jQuery.fn.modal === 'function');
    function add(src) {
      var s = document.createElement('script');
      s.src = src; s.defer = false; document.head.appendChild(s);
    }
    if (needBS4) {
      add('https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.1/umd/popper.min.js');
      add('https://cdn.jsdelivr.net/npm/bootstrap@4.2.0/dist/js/bootstrap.min.js');
      // CoreUI v3 is fine with BS4
      add('https://cdn.jsdelivr.net/npm/@coreui/coreui@3.4.0/dist/js/coreui.bundle.min.js');
    }
  })();
  </script>
</body>
