<?php
/**
 * Stock Transfer Receive Page (UI)
 * Rich interface with barcode scanning, per-item controls, and summary.
 */

declare(strict_types=1);

$baseDir = dirname(dirname(__DIR__)); // /modules
require_once $baseDir . '/base/bootstrap.php';

$classicThemePath = $baseDir . '/base/templates/themes/cis-classic/theme.php';
if (!is_file($classicThemePath)) {
    echo '<div style="padding:20px;background:#ffebee;color:#b71c1c;font-family:monospace">'
        . 'Theme include missing: ' . htmlspecialchars($classicThemePath) . '</div>';
    return;
}
require_once $classicThemePath;

// Load transfer metadata if id provided
$pdo = \CIS\Base\Database::pdo();
$transferId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$transfer = null; $transferItems = [];
if ($transferId > 0) {
    try {
        $stmt = $pdo->prepare("SELECT c.*, o1.name as outlet_from_name, o2.name as outlet_to_name
                               FROM vend_consignments c
                               LEFT JOIN outlets o1 ON o1.id = c.outlet_id_from
                               LEFT JOIN outlets o2 ON o2.id = c.outlet_id_to
                               WHERE c.id = ? LIMIT 1");
        $stmt->execute([$transferId]);
        $transfer = $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
        if ($transfer) {
            $s = $pdo->prepare("SELECT cp.id, cp.product_id, cp.count, p.sku, p.name as product_name, p.image_url
                                 FROM vend_consignment_products cp
                                 LEFT JOIN products p ON p.id = cp.product_id
                                 WHERE cp.consignment_id = ? ORDER BY p.name ASC");
            $s->execute([$transferId]);
            $transferItems = $s->fetchAll(\PDO::FETCH_ASSOC) ?: [];
        }
    } catch (\Throwable $e) {
        error_log('Receive load error: ' . $e->getMessage());
    }
}

$theme = new CISClassicTheme();
$theme->setTitle('Receive Stock Transfer');
$theme->addBreadcrumb('Consignments', '/modules/consignments/');
$theme->addBreadcrumb('Stock Transfers', '/modules/consignments/?route=stock-transfers');
$theme->addBreadcrumb('Receive', null);

$theme->addHeadContent('<link rel="stylesheet" href="/modules/consignments/assets/css/97-transfer-receive.css">');
$theme->addHeadContent('<script src="/modules/consignments/assets/js/97-transfer-receive.js" defer></script>');

$theme->render('html-head');
$theme->render('header');
$theme->render('sidebar');
$theme->render('main-start');
?>
<div class="container-fluid">
  <div class="animated fadeIn">
    <div class="row">
      <div class="col-12">
        <div class="d-flex justify-content-between align-items-center mb-3">
          <div>
            <h2 class="mb-0"><i class="fas fa-inbox mr-2"></i>Receive Stock Transfer<?= $transfer ? ' #' . htmlspecialchars((string)($transfer['consignment_number'] ?? $transferId)) : '' ?></h2>
            <div class="text-muted small">Scan and confirm items into destination outlet</div>
          </div>
          <div>
            <a class="btn btn-outline-secondary" href="/modules/consignments/?route=stock-transfers">Back to Transfers</a>
          </div>
        </div>

        <?php if (!$transfer): ?>
          <div class="alert alert-info">No transfer selected. Open from <a href="/modules/consignments/?route=stock-transfers">Stock Transfers</a>.</div>
        <?php else: ?>
          <div class="card mb-3">
            <div class="card-body">
              <div class="row">
                <div class="col-md-3"><div class="text-muted small">From</div><div><strong><?= htmlspecialchars((string)($transfer['outlet_from_name'] ?? '-')) ?></strong></div></div>
                <div class="col-md-3"><div class="text-muted small">To</div><div><strong><?= htmlspecialchars((string)($transfer['outlet_to_name'] ?? '-')) ?></strong></div></div>
                <div class="col-md-3"><div class="text-muted small">Created</div><div><?= isset($transfer['created_at']) ? date('Y-m-d H:i', strtotime((string)$transfer['created_at'])) : '-' ?></div></div>
                <div class="col-md-3"><div class="text-muted small">Status</div><div><span class="badge badge-warning"><?= htmlspecialchars((string)($transfer['status'] ?? 'SENT')) ?></span></div></div>
              </div>
            </div>
          </div>

          <div class="card mb-3">
            <div class="card-body">
              <div id="tr-barcode-feedback" class="alert" style="display:none"></div>
              <div class="form-row align-items-end">
                <div class="form-group col-md-6">
                  <label for="tr-barcode-scan">Scan barcode or enter SKU</label>
                  <input type="text" class="form-control" id="tr-barcode-scan" placeholder="Scan now" autocomplete="off">
                </div>
                <div class="form-group col-md-3">
                  <label for="transfer-notes">Notes</label>
                  <input type="text" class="form-control" id="transfer-notes" placeholder="Optional notes">
                </div>
                <div class="form-group col-md-3 text-right">
                  <label>&nbsp;</label>
                  <div>
                    <button id="tr-submit" type="button" class="btn btn-success">Submit Receive</button>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div class="card">
            <div class="card-body">
              <div class="table-responsive">
                <table class="table table-sm table-bordered mb-2" id="tr-items-table">
                  <thead class="thead-light">
                    <tr>
                      <th>Product</th>
                      <th>Expected</th>
                      <th>Received</th>
                      <th>Status</th>
                      <th>Actions</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php foreach ($transferItems as $it): $eid = (int)$it['id']; ?>
                      <tr id="item-<?= $eid ?>" data-item-id="<?= $eid ?>" data-sku="<?= htmlspecialchars((string)($it['sku'] ?? '')) ?>" data-expected="<?= (int)($it['count'] ?? 0) ?>">
                        <td>
                          <div class="d-flex align-items-center" style="gap:8px;">
                            <?php if (!empty($it['image_url'])): ?>
                              <img src="<?= htmlspecialchars((string)$it['image_url']) ?>" alt="" style="width:40px;height:40px;object-fit:cover;border-radius:4px;border:1px solid #eee;" />
                            <?php else: ?>
                              <div style="width:40px;height:40px;border:1px solid #eee;border-radius:4px;background:#f8f9fa;display:flex;align-items:center;justify-content:center;">
                                <i class="fas fa-box"></i>
                              </div>
                            <?php endif; ?>
                            <div>
                              <div><strong><?= htmlspecialchars((string)($it['product_name'] ?? 'Product #'.$it['product_id'])) ?></strong></div>
                              <div class="text-muted small">SKU: <?= htmlspecialchars((string)($it['sku'] ?? '')) ?></div>
                            </div>
                          </div>
                        </td>
                        <td class="text-center"><span class="badge badge-secondary"><?= (int)($it['count'] ?? 0) ?></span></td>
                        <td class="text-center">
                          <div class="btn-group" role="group" aria-label="qty">
                            <button class="btn btn-sm btn-outline-secondary" onclick="return window.TR.inc(<?= $eid ?>,-1);"><i class="fas fa-minus"></i></button>
                            <input type="number" min="0" max="<?= (int)($it['count'] ?? 0) ?>" value="0" class="form-control form-control-sm" style="width:80px;text-align:center;" id="received-<?= $eid ?>" onchange="window.TR.sync(<?= $eid ?>)" />
                            <button class="btn btn-sm btn-outline-secondary" onclick="return window.TR.inc(<?= $eid ?>,1);"><i class="fas fa-plus"></i></button>
                          </div>
                        </td>
                        <td class="text-center"><span id="status-<?= $eid ?>" class="badge badge-warning">Pending</span></td>
                        <td class="text-center">
                          <div class="btn-group btn-group-sm" role="group">
                            <button class="btn btn-outline-primary" onclick="return window.TR.note(<?= $eid ?>);" title="Add notes"><i class="fas fa-comment"></i></button>
                            <button class="btn btn-outline-warning" onclick="return window.TR.damage(<?= $eid ?>);" title="Report damage"><i class="fas fa-exclamation-triangle"></i></button>
                          </div>
                        </td>
                      </tr>
                    <?php endforeach; ?>
                  </tbody>
                </table>
              </div>

              <div class="d-flex justify-content-between align-items-center mt-3">
                <div class="text-muted">
                  <span>Total items: <strong id="sum-total"><?= count($transferItems) ?></strong></span>
                  <span class="ml-3">Received any: <strong id="sum-received">0</strong></span>
                  <span class="ml-3">Complete: <strong id="sum-complete">0</strong></span>
                </div>
                <div>
                  <button id="tr-complete" class="btn btn-primary" disabled>Complete Receiving</button>
                  <span id="tr-status" class="text-muted small ml-2"></span>
                </div>
              </div>
            </div>
          </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <script>
    window.TransferReceiveConfig = {
      apiUrl: '/modules/consignments/api/stock-transfers/receive.php'
    };
    window.TransferReceiveState = {
      transferId: <?= json_encode($transferId) ?>,
      items: <?= json_encode($transferItems, JSON_UNESCAPED_SLASHES) ?>
    };
  </script>
</div>
<?php $theme->render('main-end'); ?>
<?php $theme->render('footer'); ?>
<?php $theme->render('html-end'); ?>
