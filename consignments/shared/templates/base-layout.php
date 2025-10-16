<?php
/**
 * Base Template Layout for Consignments Module
 * 
 * This template provides a consistent structure that inherits from CIS global templates
 * and ensures proper HTML structure with all JavaScript loading correctly.
 * 
 * USAGE IN MODULE PAGES:
 * ----------------------
 * 1. Set template variables BEFORE calling this file:
 *    $page_title = 'Your Page Title';
 *    $page_head_extra = '<link rel="stylesheet" href="...">'; // Optional extra CSS
 *    $body_class = 'app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show';
 *    
 * 2. Define your page content in a function or capture it:
 *    ob_start();
 *    ?>
 *    <!-- Your page HTML here -->
 *    <?php
 *    $page_content = ob_get_clean();
 *    
 * 3. Include this base template:
 *    require __DIR__ . '/../shared/templates/base-layout.php';
 * 
 * TEMPLATE STRUCTURE:
 * -------------------
 * <!DOCTYPE html>
 * <html>
 *   <head>
 *     [Global CIS CSS from html-header.php]
 *     [Module-specific CSS from $page_head_extra]
 *     [jQuery loaded here]
 *   </head>
 *   <body class="$body_class">
 *     [Top navigation from header.php]
 *     <div class="app-body">
 *       [Sidebar from sidemenu.php]
 *       <main class="main">
 *         [Your page content from $page_content]
 *       </main>
 *       [Personalisation menu if exists]
 *     </div> <!-- /.app-body -->
 *     
 *     [Module-specific JS from $page_scripts_before_footer]
 *     [Bootstrap, CoreUI, jQuery UI from html-footer.php]
 *     [Template footer scripts from footer.php]
 *     [Modal HTML from $page_modals]
 *     [Overlay HTML from $page_overlays]
 *   </body>
 * </html>
 * 
 * @package CIS\Consignments\Templates
 * @version 1.0.0
 */

// Ensure we have ROOT_PATH
if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', realpath(__DIR__ . '/../../../../'));
}

// Default values for template variables
$page_title = $page_title ?? 'CIS - Consignments';
$body_class = $body_class ?? 'app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show';
$page_head_extra = $page_head_extra ?? '';
$page_content = $page_content ?? '';
$page_scripts_before_footer = $page_scripts_before_footer ?? '';
$page_modals = $page_modals ?? '';
$page_overlays = $page_overlays ?? '';
$show_breadcrumb = $show_breadcrumb ?? true;
$breadcrumb_items = $breadcrumb_items ?? [];

// Set variables that html-header.php expects
$pageTitle = $page_title;
$extraHead = $page_head_extra;

// Start output - HTML HEAD
include(ROOT_PATH . "/assets/template/html-header.php");

// BODY and site navigation
include(ROOT_PATH . "/assets/template/header.php");
?>

<!-- CIS Base Layout - App Body Container -->
<div class="app-body">
    <?php include(ROOT_PATH . "/assets/template/sidemenu.php"); ?>
    
    <main class="main" role="main">
        <?php if ($show_breadcrumb && !empty($breadcrumb_items)): ?>
        <!-- Breadcrumb -->
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-2">
                <?php foreach ($breadcrumb_items as $index => $item): ?>
                    <?php if ($index === count($breadcrumb_items) - 1): ?>
                        <li class="breadcrumb-item active" aria-current="page">
                            <?php echo htmlspecialchars($item['label'] ?? '', ENT_QUOTES); ?>
                        </li>
                    <?php else: ?>
                        <li class="breadcrumb-item">
                            <?php if (!empty($item['url'])): ?>
                                <a href="<?php echo htmlspecialchars($item['url'], ENT_QUOTES); ?>">
                                    <?php echo htmlspecialchars($item['label'] ?? '', ENT_QUOTES); ?>
                                </a>
                            <?php else: ?>
                                <?php echo htmlspecialchars($item['label'] ?? '', ENT_QUOTES); ?>
                            <?php endif; ?>
                        </li>
                    <?php endif; ?>
                <?php endforeach; ?>
                
                <!-- Breadcrumb Menu (Quick Product Search) -->
                <?php if (file_exists(ROOT_PATH . '/assets/template/quick-product-search.php')): ?>
                <li class="breadcrumb-menu d-md-down-none">
                    <?php include(ROOT_PATH . '/assets/template/quick-product-search.php'); ?>
                </li>
                <?php endif; ?>
            </ol>
        </nav>
        <?php endif; ?>
        
        <!-- Page Content Injected Here -->
        <?php echo $page_content; ?>
        
    </main>
    
    <?php
    // Include personalisation menu if it exists
    $personalisation_menu = ROOT_PATH . "/assets/template/personalisation-menu.php";
    if (file_exists($personalisation_menu)) {
        include($personalisation_menu);
    }
    ?>
</div> <!-- /.app-body -->

<!-- Module-Specific Scripts (loaded before core libraries to prevent conflicts) -->
<?php if (!empty($page_scripts_before_footer)): ?>
    <?php echo $page_scripts_before_footer; ?>
<?php endif; ?>

<!-- Core JavaScript Libraries (jQuery, Bootstrap, CoreUI, etc.) -->
<?php include(ROOT_PATH . "/assets/template/html-footer.php"); ?>

<!-- Template Footer Scripts -->
<?php include(ROOT_PATH . "/assets/template/footer.php"); ?>

<!-- Page-Specific Modals -->
<?php if (!empty($page_modals)): ?>
    <?php echo $page_modals; ?>
<?php endif; ?>

<!-- Page-Specific Overlays -->
<?php if (!empty($page_overlays)): ?>
    <?php echo $page_overlays; ?>
<?php endif; ?>

<!-- Note: html-footer.php already closes </body></html> -->
