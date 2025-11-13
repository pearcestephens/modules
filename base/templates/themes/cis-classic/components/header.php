<body class="<?php echo htmlspecialchars($body_class ?? 'app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show'); ?>">

<!-- Mobile sidebar overlay -->
<div class="sidebar-mobile-overlay" onclick="document.body.classList.remove('sidebar-mobile-show')"></div>

<header class="app-header navbar">
  <!-- Mobile hamburger - ONLY on mobile, positioned first -->
  <button class="navbar-toggler d-lg-none" type="button" onclick="document.body.classList.toggle('sidebar-mobile-show')" aria-label="Toggle sidebar" style="order: -1; margin-right: 0.5rem;">
    <span class="navbar-toggler-icon"></span>
  </button>

  <!-- Logo -->
  <a class="navbar-brand" href="/">
    <img class="navbar-brand-full" src="https://staff.vapeshed.co.nz/assets/img/brand/logo.jpg" width="120" height="38" alt="The Vape Shed Logo">
    <img class="navbar-brand-minimized" src="https://staff.vapeshed.co.nz/assets/img/brand/vapeshed-emblem.png" width="30" height="30" alt="The Vape Shed Logo">
  </a>

  <!-- Desktop sidebar minimizer button - ONLY on desktop -->
  <button class="navbar-toggler sidebar-toggler d-none d-lg-block" type="button" data-toggle="sidebar-lg-show" aria-label="Toggle sidebar">
    <span class="navbar-toggler-icon"></span>
  </button>

  <ul class="nav navbar-nav d-md-down-none">
    <li class="nav-item px-3"></li>
  </ul>

  <ul class="nav navbar-nav ml-auto">
    <!-- User greeting -->
    <li class="nav-item d-md-down-none">
      <span class="nav-link">Hello, <strong><?php echo htmlspecialchars($userData['first_name'] ?? 'Guest'); ?></strong></span>
    </li>

    <!-- Notifications with proper badge -->
    <li class="nav-item dropdown">
      <?php
      $notificationObject = (object)["totalNotifications" => 0];
      $uid = $_SESSION["user_id"] ?? null;

      if ($uid && function_exists('userNotifications_getAllUnreadNotifications')) {
        try {
          $notificationObject = userNotifications_getAllUnreadNotifications($uid) ?: (object)["totalNotifications" => 0];
        } catch (Throwable $e) {
          $notificationObject = (object)["totalNotifications" => 0];
        }
      }
      ?>
      <a class="nav-link" data-toggle="dropdown" role="button" aria-haspopup="true" aria-expanded="false" style="position: relative;">
        <i class="icon-bell" style="font-size: 20px;"></i>
        <?php if ($notificationObject->totalNotifications > 0): ?>
          <span class="badge badge-pill badge-danger" style="position: absolute; top: 5px; right: 0; font-size: 0.625rem; padding: 0.2rem 0.4rem;">
            <?php echo $notificationObject->totalNotifications; ?>
          </span>
        <?php endif; ?>
      </a>
      <?php
      // Include notification dropdown if it exists
      $notificationDropdown = $_SERVER['DOCUMENT_ROOT'] . '/assets/template/notification-dropdown.php';
      if (file_exists($notificationDropdown)) {
          include($notificationDropdown);
      }
      ?>
    </li>

    <!-- Logout -->
    <li class="nav-item d-md-down-none">
      <a class="nav-link" href="?logout=true" title="Logout">
        <span>Logout</span>
      </a>
    </li>
  </ul>
</header>

<!-- Action Bar / Breadcrumb Bar - Always visible with white background -->
<div class="app-breadcrumb" style="background: #fff; border-bottom: 1px solid #c8ced3; padding: 0.75rem 1rem; min-height: 50px; display: flex; align-items: center;">

  <?php
  // DEBUG: Show what data we have
  if (isset($_GET['debug'])):
    echo '<!-- DEBUG MODE -->';
    echo '<div style="font-size: 0.7rem; color: #dc3545; background: #fff5f5; padding: 0.5rem; border: 1px solid #dc3545; border-radius: 4px;">';
    echo '<strong>DEBUG:</strong> ';
    echo 'page_subtitle=' . var_export($theme->getPageSubtitle(), true) . ' | ';
    echo 'breadcrumbs=' . var_export($breadcrumbs ?? 'NOT SET', true) . ' | ';
    echo 'header_buttons=' . var_export($header_buttons ?? 'NOT SET', true) . ' | ';
    echo 'show_timestamps=' . var_export($show_timestamps ?? 'NOT SET', true);
    echo '</div>';
  endif;
  ?>

  <!-- Page Subtitle - left side -->
  <?php
  $page_subtitle = $theme->getPageSubtitle();
  if (!empty($page_subtitle)):
  ?>
    <div style="font-size: 0.9375rem; color: #23282c; font-weight: 500;">
      <?php echo htmlspecialchars($page_subtitle); ?>
    </div>
  <?php endif; ?>

  <!-- Breadcrumbs - left side (if set) -->
  <nav aria-label="breadcrumb" style="<?php echo !empty($page_subtitle) ? 'margin-left: 1.5rem;' : ''; ?>">
    <?php if (isset($breadcrumbs) && is_array($breadcrumbs) && count($breadcrumbs) > 0): ?>
    <ol class="breadcrumb mb-0" style="background: transparent; padding: 0;">
      <?php foreach ($breadcrumbs as $crumb): ?>
        <?php if (isset($crumb['url'])): ?>
          <li class="breadcrumb-item">
            <a href="<?php echo htmlspecialchars($crumb['url']); ?>">
              <?php echo htmlspecialchars($crumb['label']); ?>
            </a>
          </li>
        <?php else: ?>
          <li class="breadcrumb-item active" aria-current="page">
            <?php echo htmlspecialchars($crumb['label']); ?>
          </li>
        <?php endif; ?>
      <?php endforeach; ?>
    </ol>
    <?php endif; ?>
  </nav>

  <!-- Buttons - left side after breadcrumbs -->
  <?php if (isset($header_buttons) && is_array($header_buttons) && count($header_buttons) > 0): ?>
    <div class="d-flex" style="margin-left: auto;">
      <?php foreach ($header_buttons as $button): ?>
        <a href="<?php echo htmlspecialchars($button['url'] ?? '#'); ?>"
           class="btn btn-<?php echo htmlspecialchars($button['color'] ?? 'primary'); ?> btn-sm ml-2"
           <?php if (isset($button['target'])): ?>target="<?php echo htmlspecialchars($button['target']); ?>"<?php endif; ?>>
          <?php if (isset($button['icon'])): ?>
            <i class="<?php echo htmlspecialchars($button['icon']); ?> mr-1"></i>
          <?php endif; ?>
          <?php echo htmlspecialchars($button['label']); ?>
        </a>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- Timestamp display - right side -->
  <?php if (isset($show_timestamps) && $show_timestamps): ?>
  <div class="ml-auto d-none d-md-flex align-items-center" style="font-size: 0.8125rem; color: #73818f;">
    <span>
      <i class="far fa-clock mr-1"></i>
      <?php echo date('m/d/Y g:i A'); ?>
    </span>
  </div>
  <?php endif; ?>

</div>

<!-- Notification Modal -->
