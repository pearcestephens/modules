<?php
/**
 * Master Template - Self-Contained Module Wrapper (CONSOLIDATED)
 * 
 * This is the consolidated template that includes ALL CIS template components inline.
 * This makes modules independent from main CIS /assets/template/ files, allowing
 * safe refactoring without breaking the live site.
 * 
 * KEY DIFFERENCES FROM OLD master.php:
 *   - No external template includes (self-contained)
 *   - Fixed duplicate <body> tags
 *   - Fixed duplicate </body></html> tags
 *   - Fixed include order (footer content BEFORE scripts)
 *   - All CIS template content inline (html-header, header, sidemenu, footer, html-footer)
 * 
 * Requires:
 *   - $pageTitle: string (optional, defaults to "The Vape Shed - CIS")
 *   - $extraHead: string (optional, additional <head> content)
 *   - $breadcrumbs: array (optional, breadcrumb navigation)
 *   - $moduleCSS: array (optional, module-specific CSS files)
 *   - $moduleJS: array (optional, module-specific JS files)
 *   - $content: string (main page content)
 * 
 * Usage:
 *   ob_start();
 *   // Your page content here
 *   $content = ob_get_clean();
 *   require __DIR__ . '/master-consolidated.php';
 * 
 * @version 2.0 - Consolidated (no external template dependencies)
 * @date 2025-01-12
 */

declare(strict_types=1);

if (!defined('CIS_MODULE_CONTEXT')) {
    http_response_code(403);
    exit('Direct access forbidden');
}

// Ensure HTTPS_URL constant is available (fallback if config not loaded)
if (!defined('HTTPS_URL')) {
    define('HTTPS_URL', 'https://staff.vapeshed.co.nz/');
}

// Allow pages to override the <title> and inject extra <head> content safely
$___defaultTitle = 'The Vape Shed - Central Information System';
$___pageTitle = isset($pageTitle) && is_string($pageTitle) && $pageTitle !== '' ? $pageTitle : $___defaultTitle;

// Safe defaults for variables
$content = $content ?? '';
$breadcrumbs = $breadcrumbs ?? [];
$bodyClass = $bodyClass ?? 'app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show';
$moduleCSS = $moduleCSS ?? [];
$moduleJS = $moduleJS ?? [];
$extraHead = $extraHead ?? '';

// Safely resolve user context for navbar
$uid = $_SESSION["userID"] ?? null;
$userDetails = ["first_name" => "Guest"];
if ($uid && function_exists('getUserInformation')) {
  try {
    $ud = getUserInformation($uid);
    if (is_array($ud)) {
      $userDetails = $ud;
    } elseif (is_object($ud)) {
      $userDetails = ["first_name" => $ud->first_name ?? "User"];
    }
  } catch (Throwable $e) {
    // degrade silently
  }
}

// Get navigation menus for sidebar
$mainCategories = function_exists('getNavigationMenus') ? getNavigationMenus() : [];
$userID = isset($_SESSION["userID"]) ? (int)$_SESSION["userID"] : 0;
$permissionItems = $userID > 0 && function_exists('getCurrentUserPermissions') ? getCurrentUserPermissions($userID) : [];
$organisedCats = array();
foreach($mainCategories as $c){
  $c->itemsArray = array();
  foreach($permissionItems as $pi){
    if ($c->id == $pi->navigation_id && $pi->show_in_sidemenu == 1){        
      array_push($c->itemsArray,$pi);
    }
  }
  array_push($organisedCats,$c);
}

// Get notification count
$notificationObject = (object)["totalNotifications" => 0];
if ($uid && function_exists('userNotifications_getAllUnreadNotifications')) {
  try {
    $notificationObject = userNotifications_getAllUnreadNotifications($uid) ?: (object)["totalNotifications" => 0];
  } catch (Throwable $e) {
    $notificationObject = (object)["totalNotifications" => 0];
  }
}

?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, shrink-to-fit=no">
    <meta name="robots" content="noindex, nofollow">
    
    <title><?php echo htmlspecialchars($___pageTitle, ENT_QUOTES, 'UTF-8'); ?></title>

    <!-- CORE CSS - LOCAL COREUI v2.0.0 + BOOTSTRAP v4.1.1 (COMPATIBLE) -->
    <link href="<?php echo HTTPS_URL; ?>assets/css/style1.css?updated=224" rel="stylesheet">
    
    <!-- JQUERY UI COMPATIBLE WITH COREUI v2.0.0 -->
    <link href="https://code.jquery.com/ui/1.13.2/themes/ui-lightness/jquery-ui.css" rel="stylesheet">
    
    <!-- PACE LOADER -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/pace/1.2.4/themes/blue/pace-theme-minimal.min.css" rel="stylesheet">
    
    <!-- ICONS - COMPATIBLE WITH COREUI v2.0.0 -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/simple-line-icons/2.5.5/css/simple-line-icons.min.css" rel="stylesheet">
    
    <!-- CUSTOM OVERRIDES -->
    <link href="<?php echo HTTPS_URL; ?>assets/css/bootstrap-compatibility.css?v=20250902" rel="stylesheet">
    <link href="<?php echo HTTPS_URL; ?>assets/css/custom.css?updated=222" rel="stylesheet">
    <link href="<?php echo HTTPS_URL; ?>assets/css/sidebar-fixes.css?v=20250904d" rel="stylesheet">
    <link href="<?php echo HTTPS_URL; ?>assets/css/sidebar-dark-theme-restore.css?v=20250904" rel="stylesheet">

    <!-- Module-Specific Styles (if provided) -->
    <?php if (!empty($moduleCSS)): ?>
        <?php foreach ($moduleCSS as $style): ?>
            <link href="<?php echo htmlspecialchars($style, ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet">
        <?php endforeach; ?>
    <?php endif; ?>

    <!-- JAVASCRIPT - JQUERY FIRST -->
    <script src="https://code.jquery.com/jquery-3.7.1.min.js" integrity="sha256-/JqT3SQfawRcv/BIHPThkBvs0OEvtFFmqPF/lYI/Cxo=" crossorigin="anonymous"></script>
    
    <!-- MOMENT.JS -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/moment.js/2.29.4/moment.min.js"></script>

    <?php if (isset($_SESSION["userID"])): ?>
        <script>
            var staffID = <?php echo (int)$_SESSION["userID"]; ?>;
        </script>
    <?php endif; ?>
    <?php if (isset($_SESSION['csrf_token']) && is_string($_SESSION['csrf_token']) && $_SESSION['csrf_token'] !== ''): ?>
        <script>
            // Expose session CSRF token for AJAX posts that require it
            window.CIS_CSRF = <?php echo json_encode($_SESSION['csrf_token']); ?>;
        </script>
    <?php endif; ?>
    
    <?php
    // Optional: extra head markup from page (styles, meta, links). String only for safety.
    if (isset($extraHead) && is_string($extraHead) && $extraHead !== '') {
        echo $extraHead;
    }
    ?>
</head>
<body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') ?>">

<!-- ========================================
     HEADER / NAVBAR
     ======================================== -->
<header class="app-header navbar">
  <button class="navbar-toggler sidebar-toggler d-lg-none mr-auto" type="button" data-toggle="sidebar-show">
    <span class="navbar-toggler-icon"></span>
  </button>
  <a class="navbar-brand" href="#">
    <img class="navbar-brand-full" src="<?php echo HTTPS_URL; ?>assets/img/brand/logo.jpg" width="120va" height="38" alt="The Vape Shed Logo">
    <img class="navbar-brand-minimized" src="<?php echo HTTPS_URL; ?>assets/img/brand/vapeshed-emblem.png" width="30" height="30" alt="The Vape Shed Logo">
  </a>
  <button class="navbar-toggler sidebar-toggler d-md-down-none" type="button" data-toggle="sidebar-lg-show">
    <span class="navbar-toggler-icon"></span>
  </button>
  <ul class="nav navbar-nav d-md-down-none">
    <li class="aibar"></li>
  </ul>
  <ul class="nav navbar-nav ml-auto">
    <li class="nav-item d-md-down-none">
      <span>Hello, <?php echo htmlspecialchars($userDetails["first_name"] ?? "Guest"); ?><?php if ($uid): ?><a class="nav-link" href="?logout=true">Logout</a><?php endif; ?></span>
    </li>
    <li class="nav-item dropdown">
      <a class="nav-link dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false" id="notificationToggle">
        <div class="notification-bell" style="background-color: #eeeeee;width: 35px;height: 35px;border-radius: 50px;margin: 0 5px 0 15px;padding: 0;cursor: pointer;">
          <span class="badge badge-pill badge-danger notify notific-count" style=" margin-top: -20px; margin-left: 10px; <?php if ($notificationObject->totalNotifications == 0) { echo 'display:none;'; } ?>">
            <span class='userNotifCounter'><?php echo $notificationObject->totalNotifications; ?></span></span>
          <i class="fa fa-bell-o" style="padding-top: 10px;font-size: 15px;"></i>
        </div>
      </a><?php if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/notification-dropdown.php')) { include($_SERVER['DOCUMENT_ROOT'] . '/notification-dropdown.php'); } ?>
    </li>
    <li class="nav-item dropdown">
      <a class="nav-link" data-toggle="" role="button" aria-haspopup="true" aria-expanded="false">
        <img class="img-avatar" src="<?php echo HTTPS_URL; ?>assets/img/avatars/6.jpg" alt="The Vape Shed">
      </a>
    </li>
  </ul>
</header>

<!-- Notification Modal -->
<div class="modal fade" id="notificationModel" tabindex="-1" role="dialog" aria-labelledby="notificationModelLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="notificationModelLabel">Modal title</h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <div class="modal-body" style=" font-size: 12px; ">
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>

<!-- ========================================
     APP BODY CONTAINER
     ======================================== -->
<div class="app-body">
    
    <!-- ========================================
         SIDEBAR NAVIGATION
         ======================================== -->
    <div class="sidebar">
      <nav class="sidebar-nav">
        <ul class="nav">
        <li class="nav-item open">
            <a class="nav-link active" href="/index.php">View Dashboard</a>
        </li>
        <?php 
          foreach($organisedCats as $c){
            if (count($c->itemsArray) > 0){
              echo '<li class="nav-title">'.$c->title.'</li>';
              foreach($c->itemsArray as $i){
                echo '<li class="nav-item">
                          <a class="nav-link" href="'.$i->filename.'">'.$i->name.'</a>
                      </li>';
              }
            }
          }
        ?>
        </ul>
      </nav>
    </div>

    <!-- ========================================
         MAIN CONTENT AREA
         ======================================== -->
    <main class="main" role="main">
        
        <!-- Breadcrumbs -->
        <ol class="breadcrumb">
            <?php if (!empty($breadcrumbs)): ?>
                <?php foreach ($breadcrumbs as $crumb): ?>
                    <li class="breadcrumb-item<?= !empty($crumb['active']) ? ' active' : '' ?>">
                        <?php if (!empty($crumb['active']) || empty($crumb['href'])): ?>
                            <?= htmlspecialchars($crumb['label'] ?? '', ENT_QUOTES, 'UTF-8') ?>
                        <?php else: ?>
                            <a href="<?= htmlspecialchars($crumb['href'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($crumb['label'], ENT_QUOTES, 'UTF-8') ?></a>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            <?php else: ?>
                <li class="breadcrumb-item"><a href="/index.php">Home</a></li>
                <li class="breadcrumb-item active"><?= htmlspecialchars($___pageTitle, ENT_QUOTES, 'UTF-8') ?></li>
            <?php endif; ?>
        </ol>

        <!-- Main Page Content (from view) -->
        <div class="container-fluid">
            <?= $content ?>
        </div>
    </main>

</div><!-- /.app-body -->

<!-- ========================================
     FOOTER (CONTENT) - BEFORE SCRIPTS
     ======================================== -->
<footer class="app-footer">
  <div>
    <a href="https://www.vapeshed.co.nz">The Vape Shed</a>
    <span>&copy; <?php echo date("Y"); ?> Ecigdis Ltd</span>
  </div>
  <div class="ml-auto">
    <div>
      <small class="">
        Developed by <a href="https://www.pearcestephens.co.nz" target="_blank">Pearce Stephens</a>
      </small>
      <a href="/submit_ticket.php" class="btn btn-sm btn-outline-danger" style="font-size: 13px;">
        🐞 Report a Bug
      </a>
    </div>

    <style>
      .btn-outline-danger:hover {
        background-color: #dc3545 !important;
        color: white !important;
        transition: 0.2s ease-in-out;
      }
    </style>
  </div>
  
  <?php if (isset($_SESSION["userID"])){ ?>
    <script>
        var url = document.location.toString();
        if (url.match('#')) {
            $('.nav-tabs a[href="#' + url.split('#')[1] + '"]').tab('show');
        }

        //Change hash for page-reload
        $('.nav-tabs li a').on('click', function (e) {
            window.location.hash = e.target.hash;
        }); 
    </script>

    <meta name="analytics-token" content="">
    <!-- Analytics scripts commented out - can be re-enabled later -->
  <?php } ?>
</footer>

<!-- ========================================
     SCRIPTS (HTML FOOTER)
     ======================================== -->

<!-- Nicotine Checker Feature (modal + script) -->
<?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/template/features/nicotine-checker.php'; ?>

<!-- jQuery already loaded in head -->
<!-- Popper v1 (required by Bootstrap 4) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.1/umd/popper.min.js"></script>

<!-- Bootstrap 4.2 JS (jQuery plugins like $(...).tab() work) -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.2.0/dist/js/bootstrap.min.js"></script>

<!-- Pace + Perfect Scrollbar (safe with BS4) -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/pace/1.2.4/pace.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/perfect-scrollbar/1.5.5/perfect-scrollbar.min.js"></script>

<!-- CoreUI v3 (Bootstrap 4-compatible). DO NOT use CoreUI v5 here. -->
<script src="https://cdn.jsdelivr.net/npm/@coreui/coreui@3.4.0/dist/js/coreui.bundle.min.js"></script>

<!-- Chart.js v2 (matches CoreUI custom-tooltips plugin) -->
<script src="https://cdn.jsdelivr.net/npm/chart.js@2.9.4/dist/Chart.min.js"></script>
<script src="<?php echo HTTPS_URL; ?>assets/node_modules/@coreui/coreui-plugin-chartjs-custom-tooltips/dist/js/custom-tooltips.min.js"></script>

<!-- Your app JS -->
<script src="<?php echo HTTPS_URL; ?>assets/js/main.js"></script>

<!-- jQuery UI (loads after jQuery; fine with BS4) -->
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>

<!-- CIS Sidebar Mobile Enhancement - Load AFTER jQuery UI -->
<script src="<?php echo HTTPS_URL; ?>assets/js/sidebar-mobile-enhance.js?v=20250904c"></script>

<!-- Cash-Up Calculator Feature (all functions) -->
<?php include $_SERVER['DOCUMENT_ROOT'] . '/assets/template/features/cashup-calculator.php'; ?>

<!-- Module-Specific Scripts (if provided) -->
<?php if (!empty($moduleJS)): ?>
    <?php foreach ($moduleJS as $script): ?>
        <script src="<?= htmlspecialchars($script, ENT_QUOTES, 'UTF-8') ?>?v=<?= date('Ymd') ?>"></script>
    <?php endforeach; ?>
<?php endif; ?>

</body>
</html>
