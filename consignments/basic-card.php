<?php

/**
 * Basic Full-Card Layout (Consignments)
 *
 * Now using the CIS Classic Theme layout, aligned with the flagship page.
 */

// Initialize CIS Base (autoload, sessions, config, etc.)
require_once __DIR__ . '/../base/bootstrap.php';

// Resolve and include CIS Classic theme (robust path)
$classicThemePath = dirname(__DIR__) . '/base/templates/themes/cis-classic/theme.php';
if (!is_file($classicThemePath)) {
    // Fail fast with clear diagnostic instead of PHP warning
    echo '<div style="padding:20px;background:#ffebee;color:#b71c1c;font-family:monospace">'
        . 'Theme include missing: ' . htmlspecialchars($classicThemePath) . '</div>';
    return; // Stop rendering further
}
require_once $classicThemePath;

$theme = new CISClassicTheme();
$theme->setTitle('Basic Card');
$theme->addBreadcrumb('Consignments', '/modules/consignments/');
$theme->addBreadcrumb('Basic Card', null);
// Padding override moved to external CSS: modules/consignments/assets/css/99-basic-card-layout.css

// Render standard layout wrappers
$theme->render('html-head');
$theme->render('header');
$theme->render('sidebar');
$theme->render('main-start');
?>

<div class="container-fluid">
  <div class="animated fadeIn">
    <div class="row">
      <div class="col-12">
  <div id="layout-preview-card" class="card shadow-sm">
          <div class="card-header d-flex align-items-center
            justify-content-between">
            <h4 class="mb-0">Basic Card Layout</h4>
            <div class="card-actions"></div>
          </div>
          <div class="card-body">
            <p class="mb-2 text-muted">
              This is a clean starting point. Drop your content here.
            </p>
            <div class="small text-muted mb-3">
              Template sanity check: layout container, breadcrumbs, side menu spacing.
            </div>

            <div class="border rounded p-4 text-center bg-white">
              <div class="h5 mb-2">Page Content Placeholder</div>
              <p class="text-muted mb-0">
                Remove this block and begin implementing your consignments UI
                components here.
              </p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<?php $theme->render('main-end'); ?>
<?php $theme->render('footer'); ?>
<?php $theme->render('html-end'); ?>
