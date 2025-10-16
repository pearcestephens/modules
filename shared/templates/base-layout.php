<?php
/**
 * Universal Base Template for All CIS Modules
 * 
 * Simple, clean template that provides consistent CIS structure.
 * Just provides an empty body container for your content.
 * 
 * ============================================================================
 * USAGE (3 Steps)
 * ============================================================================
 * 
 * 1. Set page title and content:
 *    $page_title = 'My Page';
 *    $page_content = '<div>Your HTML here</div>';
 * 
 * 2. Include this template:
 *    require __DIR__ . '/../../shared/templates/base-layout.php';
 * 
 * 3. Done! You get full CIS structure automatically.
 * 
 * ============================================================================
 * EXAMPLE
 * ============================================================================
 * 
 * <?php
 * $page_title = 'Dashboard';
 * $page_content = '
 *     <div class="container-fluid">
 *         <h1>Hello World</h1>
 *     </div>
 * ';
 * require __DIR__ . '/../../shared/templates/base-layout.php';
 * 
 * ============================================================================
 * TEMPLATE VARIABLES (Optional)
 * ============================================================================
 * 
 * $page_title              - Browser title (required)
 * $page_content            - Your HTML content (required)
 * $page_head_extra         - Extra CSS: '<link rel="stylesheet" href="...">'
 * $page_scripts_before_footer - Your scripts: '<script src="..."></script>'
 * $page_modals             - Modal HTML
 * $body_class              - Body CSS classes (default: includes sidebar-lg-show)
 *                            Set to custom value to hide sidebar or change layout
 * 
 * ============================================================================
 * OUTPUT STRUCTURE
 * ============================================================================
 * 
 * <!DOCTYPE html>
 * <html>
 *   <head>
 *     [CIS CSS + jQuery]
 *   </head>
 *   <body>
 *     [Top Nav]
 *     <div class="app-body">
 *       [Sidebar]
 *       <main class="main">
 *         <!-- YOUR CONTENT HERE -->
 *       </main>
 *     </div>
 *     [Your Scripts]
 *     [Bootstrap/CoreUI]
 *   </body>
 * </html>
 * 
 * @package CIS\Shared\Templates
 * @version 1.0.0
 */

// ============================================================================
// SETUP
// ============================================================================

// Find ROOT_PATH
if (!defined('ROOT_PATH')) {
    $possibleRoots = [
        realpath(__DIR__ . '/../../../'),
        realpath(__DIR__ . '/../../../../'),
        $_SERVER['DOCUMENT_ROOT'] ?? ''
    ];
    foreach ($possibleRoots as $root) {
        if ($root && is_dir($root . '/assets/template')) {
            define('ROOT_PATH', $root);
            break;
        }
    }
    if (!defined('ROOT_PATH')) {
        define('ROOT_PATH', realpath(__DIR__ . '/../../../'));
    }
}

// ============================================================================
// DEFAULTS
// ============================================================================

$page_title = $page_title ?? 'CIS';
$page_content = $page_content ?? '<div class="container-fluid"><div class="alert alert-warning">No content provided</div></div>';
$page_head_extra = $page_head_extra ?? '';
$page_scripts_before_footer = $page_scripts_before_footer ?? '';
$page_modals = $page_modals ?? '';
$page_before_app_body = $page_before_app_body ?? ''; // Content before app-body (e.g., auto-save indicator)

// Add critical sidebar fixes CSS to all pages
$sidebar_css = '<link rel="stylesheet" href="/assets/css/sidebar-fixes.css">';
$page_head_extra = $sidebar_css . "\n" . $page_head_extra;

// Variables for html-header.php
$pageTitle = $page_title;
$extraHead = $page_head_extra;

// Variables for header.php (sidebar control)
// Default: show sidebar with fixed positioning
$body_class = $body_class ?? 'app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show';

// Debug: Log body class
error_log("BASE-LAYOUT DEBUG: body_class = " . $body_class);

// ============================================================================
// RENDER
// ============================================================================
?>
<!-- BASE-LAYOUT.PHP RENDERING (v1.2.0) -->
<?php include(ROOT_PATH . "/assets/template/html-header.php"); ?>
<?php include(ROOT_PATH . "/assets/template/header.php"); ?>

<!-- DEBUG: body_class = <?php echo htmlspecialchars($body_class); ?> -->

<?php if (!empty($page_before_app_body)) echo $page_before_app_body; ?>

<div class="app-body">
    <?php include(ROOT_PATH . "/assets/template/sidemenu.php"); ?>
    
    <main class="main">
        <?php echo $page_content; ?>
    </main>
    
    <?php
    if (file_exists(ROOT_PATH . "/assets/template/personalisation-menu.php")) {
        include(ROOT_PATH . "/assets/template/personalisation-menu.php");
    }
    ?>
</div>

<?php if (!empty($page_scripts_before_footer)) echo $page_scripts_before_footer; ?>
<?php include(ROOT_PATH . "/assets/template/html-footer.php"); ?>
<?php include(ROOT_PATH . "/assets/template/footer.php"); ?>
<?php if (!empty($page_modals)) echo $page_modals; ?>
