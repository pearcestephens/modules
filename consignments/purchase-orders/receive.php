<?php
/**
 * Purchase Order Receive Page (UI)
 *
 * Provides a manual receiving interface for PO items before full feature build.
 * Classic Theme wrapper for consistency with packing layouts.
 */

declare(strict_types=1);


// Bootstrap and classic theme
$baseDir = dirname(dirname(__DIR__)); // /modules
require_once $baseDir . '/base/bootstrap.php';

$classicThemePath = $baseDir . '/base/templates/themes/cis-classic/theme.php';
if (!is_file($classicThemePath)) {
    echo '<div style="padding:20px;background:#ffebee;color:#b71c1c;font-family:monospace">'
        . 'Theme include missing: ' . htmlspecialchars($classicThemePath) . '</div>';
    return;
}
require_once $classicThemePath;

$theme = new CISClassicTheme();
$theme->setTitle('Receive Purchase Order');
$theme->addBreadcrumb('Consignments', '/modules/consignments/');
$theme->addBreadcrumb('Purchase Orders', '/modules/consignments/purchase-orders/index.php');
$theme->addBreadcrumb('Receive', null);

// Include page CSS/JS
$head = [];
$head[] = '<link rel="stylesheet" href="/modules/consignments/assets/css/98-receive.css">';
$head[] = '<script src="/modules/consignments/assets/js/98-receive.js" defer></script>';
$theme->addHeadContent(implode("\n", $head));

$theme->render('html-head');
$theme->render('header');
$theme->render('sidebar');
$theme->render('main-start');
?>

<div class="container-fluid">
  <div class="animated fadeIn">
    <div class="row">
      <div class="col-12">
        <div class="card shadow-sm">
          <div class="card-header d-flex align-items-center justify-content-between">
            <h4 class="mb-0">Receive Purchase Order</h4>
          </div>
          <div class="card-body">
            <form id="po-receive-form" class="mb-3" onsubmit="return false;">
              <div class="form-row">
                <div class="form-group col-md-3">
                  <label for="po-id">PO ID</label>
                  <input type="number" min="1" class="form-control" id="po-id" placeholder="e.g. 12345" required>
                </div>
                <div class="form-group col-md-6">
                  <label for="po-notes">Notes (optional)</label>
                  <input type="text" class="form-control" id="po-notes" placeholder="Delivery notes, carton info, etc.">
                </div>
              </div>

              <div class="receive-items mb-2">
                <div class="form-row align-items-end">
                  <div class="form-group col-md-3">
                    <label for="po-product-id">Product ID</label>
                    <input type="number" min="1" class="form-control" id="po-product-id" placeholder="PID">
                  </div>
                  <div class="form-group col-md-2">
                    <label for="po-qty">Qty</label>
                    <input type="number" min="0" class="form-control" id="po-qty" value="0">
                  </div>
                  <div class="form-group col-md-2">
                    <label for="po-damaged">Damaged</label>
                    <input type="number" min="0" class="form-control" id="po-damaged" value="0">
                  </div>
                  <div class="form-group col-md-3">
                    <label for="po-barcode">Barcode (optional)</label>
                    <input type="text" class="form-control" id="po-barcode" placeholder="Scan...">
                  </div>
                  <div class="form-group col-md-2">
                    <button id="po-add-item" class="btn btn-outline-primary btn-block" type="button">Add Item</button>
                  </div>
                </div>
                <div class="table-responsive">
                  <table class="table table-sm table-bordered mb-2" id="po-items-table">
                    <thead class="thead-light">
                      <tr>
                        <th>Product ID</th>
                        <th>Qty</th>
                        <th>Damaged</th>
                        <th>Barcode</th>
                        <th>Notes</th>
                        <th>Remove</th>
                      </tr>
                    </thead>
                    <tbody></tbody>
                  </table>
                </div>
              </div>

              <div class="d-flex justify-content-between">
                <button id="po-submit" class="btn btn-success" type="button">Submit Receive</button>
                <span id="po-status" class="text-muted small"></span>
              </div>
            </form>
          </div>
        </div>
      </div>
    </div>
  </div>

  <script>
    window.ReceiveConfig = window.ReceiveConfig || {};
    window.ReceiveConfig.poApiUrl = '/modules/consignments/api/purchase-orders/receive.php';
  </script>
</div>

<?php $theme->render('main-end'); ?>
<?php $theme->render('footer'); ?>
<?php $theme->render('html-end'); ?>
