<?php
/**
 * Master Template - Modular Layout with Partials
 * 
 * Clean, modular template using separate partial files for each section.
 * This makes the template easy to read and maintain while staying independent
 * from the main CIS site templates.
 * 
 * IMPORTANT PREREQUISITES:
 * ========================
 * This template assumes app.php (or equivalent bootstrap) has been loaded
 * BEFORE reaching this file. The bootstrap should have already:
 * 
 *   1. Loaded constants.php (defines HTTPS_URL, etc.)
 *   2. Started the session (session_start())
 *   3. Opened database connection
 *   4. Loaded core functions (getUserInformation, etc.)
 * 
 * Typical usage pattern:
 * 
 *   // In your module's entry point (e.g., modules/consignments/index.php):
 *   require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';  // ONCE, at entry
 *   
 *   // In your page files (e.g., modules/consignments/pack.php):
 *   // NO app.php here - already loaded!
 *   ob_start();
 *   // Your page content here
 *   $content = ob_get_clean();
 *   require __DIR__ . '/../base/views/layouts/master.php';
 * 
 * Required variables:
 *   - $pageTitle: Page title (string, optional)
 *   - $content: Main page content (string)
 *   - $breadcrumbs: Breadcrumb navigation (array, optional)
 *   - $bodyClass: CSS classes for <body> (string, optional)
 *   - $moduleCSS: Module-specific CSS files (array, optional)
 *   - $moduleJS: Module-specific JS files (array, optional)
 *   - $extraHead: Additional <head> content (string, optional)
 * 
 * @package Modules\Base\Views\Layouts
 * @version 4.0.0 - Modular with Partials
 */

declare(strict_types=1);

// ============================================================================
// SAFETY CHECKS - Ensure prerequisites are met
// ============================================================================

if (!defined('CIS_MODULE_CONTEXT')) {
    http_response_code(403);
    exit('Direct access forbidden');
}

// Check that app.php (or equivalent) has been loaded
if (!defined('HTTPS_URL')) {
    trigger_error('HTTPS_URL constant not defined. Did you forget to include app.php at entry point?', E_USER_ERROR);
    exit('Configuration error: Missing required constants. Check your bootstrap.');
}

if (session_status() === PHP_SESSION_NONE) {
    trigger_error('Session not started. app.php should call session_start().', E_USER_WARNING);
    // Start it now as fallback, but this indicates a bootstrap problem
    session_start();
}

// Set default values for template variables
$___defaultTitle = 'The Vape Shed - Central Information System';
$___pageTitle = isset($pageTitle) && is_string($pageTitle) && $pageTitle !== '' ? $pageTitle : $___defaultTitle;
$content = $content ?? '';
$breadcrumbs = $breadcrumbs ?? [];
$bodyClass = $bodyClass ?? 'app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show';
$moduleCSS = $moduleCSS ?? [];
$moduleJS = $moduleJS ?? [];
$extraHead = $extraHead ?? '';

// Prepare user context for navbar
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
    // Degrade silently
  }
}

// Prepare navigation menus for sidebar
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

// Prepare notification count
$notificationObject = (object)["totalNotifications" => 0];
if ($uid && function_exists('userNotifications_getAllUnreadNotifications')) {
  try {
    $notificationObject = userNotifications_getAllUnreadNotifications($uid) ?: (object)["totalNotifications" => 0];
  } catch (Throwable $e) {
    // Degrade silently
  }
}

// Include HTML head (DOCTYPE, <html>, <head>, CSS, initial JS)
require __DIR__ . '/../partials/head.php';
?>

<body class="<?= htmlspecialchars($bodyClass, ENT_QUOTES, 'UTF-8') ?>">

<!-- Top Navigation Bar -->
<?php require __DIR__ . '/../partials/topbar.php'; ?>

<!-- App Body Container -->
<div class="app-body">
    
    <!-- Sidebar Navigation -->
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>

    <!-- Main Content Area -->
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

        <!-- Main Page Content -->
        <div class="container-fluid">
            <?= $content ?>
        </div>
    </main>

</div><!-- /.app-body -->

<!-- Footer Content -->
<?php require __DIR__ . '/../partials/footer.php'; ?>

<!-- Scripts (JavaScript libraries + features) -->
<?php require __DIR__ . '/../partials/scripts.php'; ?>

</body>
</html>
