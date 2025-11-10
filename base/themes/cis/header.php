<?php
// Enhanced top header with personalization bar, notifications, avatar, and logout
// Pull user details if available
$uid = $_SESSION['userID'] ?? null;
$userDetails = ['first_name' => 'User'];
if ($uid && function_exists('getUserInformation')) {
  try {
    $ud = getUserInformation($uid);
    if (is_array($ud)) { $userDetails = $ud; }
    elseif (is_object($ud)) { $userDetails = ['first_name' => $ud->first_name ?? 'User']; }
  } catch (Throwable $e) { /* ignore */ }
}
?>
<body class="app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show">
<header class="app-header navbar" style="position: sticky; top: 0; z-index: 1030;">
  <button class="navbar-toggler sidebar-toggler d-lg-none mr-auto" type="button" data-toggle="sidebar-show">
    <span class="navbar-toggler-icon"></span>
  </button>
  <a class="navbar-brand" href="/">
    <img class="navbar-brand-full" src="/assets/img/brand/logo.jpg" width="120" height="38" alt="The Vape Shed Logo">
    <img class="navbar-brand-minimized" src="/assets/img/brand/vapeshed-emblem.png" width="30" height="30" alt="The Vape Shed Logo">
  </a>
  <button class="navbar-toggler sidebar-toggler d-md-down-none" type="button" data-toggle="sidebar-lg-show">
    <span class="navbar-toggler-icon"></span>
  </button>

  <!-- Left spacer / AI bar slot -->
  <ul class="nav navbar-nav d-md-down-none">
    <li class="aibar"></li>
  </ul>

  <ul class="nav navbar-nav ml-auto align-items-center">
    <!-- Notifications bell + dropdown include (compat with legacy) -->
    <li class="nav-item dropdown">
      <a class="nav-link dropdown-toggle" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false" id="notificationToggle">
        <div class="notification-bell" style="background-color:#eeeeee;width:35px;height:35px;border-radius:50px;margin:0 5px 0 15px;padding:0;cursor:pointer;">
          <span class="badge badge-pill badge-danger notify notific-count" style="margin-top:-20px;margin-left:10px;display:none;">
            <span class='userNotifCounter'>0</span>
          </span>
          <i class="fa fa-bell-o" style="padding-top:10px;font-size:15px;"></i>
        </div>
      </a>
      <?php
        if (file_exists($_SERVER['DOCUMENT_ROOT'] . '/assets/template/personalisation-menu.php')) {
          // Ensure helper functions exist for the dropdown (time_ago_in_php)
          if (!function_exists('time_ago_in_php') && file_exists($_SERVER['DOCUMENT_ROOT'] . '/assets/functions/helpers.php')) {
            include_once $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/helpers.php';
          }
          include $_SERVER['DOCUMENT_ROOT'] . '/assets/template/personalisation-menu.php';
        }
      ?>
    </li>

    <!-- User greeting + logout -->
    <li class="nav-item d-none d-md-block">
      <span class="nav-link">Hello, <?php echo htmlspecialchars($userDetails['first_name'] ?? 'User'); ?><?php if ($uid): ?>
        <a class="ml-2" href="?logout=true">Logout</a><?php endif; ?></span>
    </li>

    <!-- Avatar -->
    <li class="nav-item dropdown">
      <a class="nav-link" data-toggle="" role="button" aria-haspopup="true" aria-expanded="false">
        <img class="img-avatar" src="/assets/img/avatars/6.jpg" alt="The Vape Shed">
      </a>
    </li>
  </ul>
</header>
<?php
  // Render second-layer (breadcrumbs/action bar) inside header if provided
  $__crumbs = $GLOBALS['CIS_BREADCRUMBS_DATA'] ?? [];
  if (is_array($__crumbs) && count($__crumbs) > 0): ?>
  <div class="app-breadcrumb" style="background:#fff;border-bottom:1px solid #c8ced3;padding:0.5rem 1rem;">
    <nav aria-label="breadcrumb" class="mb-0">
      <ol class="breadcrumb mb-0">
        <?php foreach ($__crumbs as $crumb): ?>
          <?php if (!empty($crumb['active'])): ?>
            <li class="breadcrumb-item active"><?php echo htmlspecialchars($crumb['label']); ?></li>
          <?php else: ?>
            <li class="breadcrumb-item">
              <a href="<?php echo htmlspecialchars($crumb['url'] ?? '#'); ?>">
                <?php if (!empty($crumb['icon'])): ?><i class="fas <?php echo htmlspecialchars($crumb['icon']); ?> mr-1"></i><?php endif; ?>
                <?php echo htmlspecialchars($crumb['label']); ?>
              </a>
            </li>
          <?php endif; ?>
        <?php endforeach; ?>
      </ol>
    </nav>
  </div>
<?php endif; ?>
<!-- app-body, sidemenu, and main container are opened by CISTemplate -->
