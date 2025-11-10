<?php
/**
 * Consignments Module - Stock Transfers
 *
 * @package CIS\Consignments
 * @version 3.0.0
 */

declare(strict_types=1);

// Load CIS Template and Consignments bootstrap (shared helpers)
require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/CISTemplate.php';

// Prepare data via shared enrichment helper (DB-driven)
$limit = 25;
$state = isset($_GET['state']) ? (string)$_GET['state'] : '';
$opts = [];
if ($state !== '') { $opts['state'] = $state; }
$my = isset($_GET['scope']) && $_GET['scope'] === 'mine';
if ($my) {
    $uid = $_SESSION['userID'] ?? null;
    if ($uid) { $opts['created_by'] = (int)$uid; }
}
$transfers = getRecentTransfersEnrichedDB($limit, 'STOCK', $opts);

// Counts for filter badges
$countsAll = getTransferCountsByState('STOCK');
$countsMine = [];
if (!empty($uid)) { $countsMine = getTransferCountsByState('STOCK', ['created_by' => (int)$uid]); }

// Initialize template
$template = new CISTemplate();
$template->setTitle('Stock Transfers');
$template->setBreadcrumbs([
    ['label' => 'Home', 'url' => '/', 'icon' => 'fa-home'],
    ['label' => 'Consignments', 'url' => '/modules/consignments/'],
    ['label' => 'Stock Transfers', 'url' => '/modules/consignments/?route=stock-transfers', 'active' => true]
]);

// Start content capture
$template->startContent();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h2 class="mb-0"><i class="fas fa-box mr-2"></i>Stock Transfers</h2>
            <div class="text-muted small">Manage inter-outlet inventory transfers</div>
        </div>
        <div class="left-actions">
            <a href="/modules/consignments/?route=transfer-manager" class="btn btn-left-solid-pill btn-success">
                <i class="fas fa-plus mr-2"></i> New Transfer
            </a>
        </div>
    </div>

                <?php
                $allActive = ($state === '' && !$my);
                $openActive = ($state === 'OPEN');
                $sentActive = ($state === 'SENT');
                $receivingActive = ($state === 'RECEIVING');
                $receivedActive = ($state === 'RECEIVED');
                $mineActive = $my;
            ?>
                <div class="d-flex justify-content-between align-items-center mb-2">
                <div class="btn-group btn-group-sm" role="group" aria-label="Quick filters">
                            <a href="?route=stock-transfers" class="btn <?= $allActive ? 'btn-secondary' : 'btn-outline-secondary' ?>">
                                All <?php if (!empty($countsAll['TOTAL'])): ?><span class="badge badge-light ml-1"><?= (int)$countsAll['TOTAL'] ?></span><?php endif; ?>
                            </a>
                            <a href="?route=stock-transfers&state=OPEN" class="btn <?= $openActive ? 'btn-info' : 'btn-outline-info' ?>">
                                Open <?php if (!empty($countsAll['OPEN'])): ?><span class="badge badge-light ml-1"><?= (int)$countsAll['OPEN'] ?></span><?php endif; ?>
                            </a>
                            <a href="?route=stock-transfers&state=SENT" class="btn <?= $sentActive ? 'btn-warning' : 'btn-outline-warning' ?>">
                                Sent <?php if (!empty($countsAll['SENT'])): ?><span class="badge badge-light ml-1"><?= (int)$countsAll['SENT'] ?></span><?php endif; ?>
                            </a>
                            <a href="?route=stock-transfers&state=RECEIVING" class="btn <?= $receivingActive ? 'btn-warning' : 'btn-outline-warning' ?>">
                                Receiving <?php if (!empty($countsAll['RECEIVING'])): ?><span class="badge badge-light ml-1"><?= (int)$countsAll['RECEIVING'] ?></span><?php endif; ?>
                            </a>
                            <a href="?route=stock-transfers&state=RECEIVED" class="btn <?= $receivedActive ? 'btn-success' : 'btn-outline-success' ?>">
                                Received <?php if (!empty($countsAll['RECEIVED'])): ?><span class="badge badge-light ml-1"><?= (int)$countsAll['RECEIVED'] ?></span><?php endif; ?>
                            </a>
                            <a href="?route=stock-transfers&scope=mine" class="btn <?= $mineActive ? 'btn-primary' : 'btn-outline-primary' ?>">
                                My Transfers <?php if (!empty($countsMine['TOTAL'])): ?><span class="badge badge-light ml-1"><?= (int)$countsMine['TOTAL'] ?></span><?php endif; ?>
                            </a>
                </div>
                    <?php if (!$allActive): ?>
                        <a href="?route=stock-transfers" class="small text-muted">Clear filters</a>
                    <?php endif; ?>
            </div>

<!-- Transfers Table -->
<div class="card">
    <div class="card-body">
        <?php $hasValue = !empty($transfers) && array_key_exists('total_cost', (array)$transfers[0]); ?>
        <table class="table table-hover" id="transfersTable">
            <thead>
                <tr>
                    <th>Consignment</th>
                    <th>From</th>
                    <th>To</th>
                    <th>Status</th>
                    <th>Items</th>
                    <th>Shipments</th>
                    <th>Progress</th>
                    <th>Contact</th>
                    <?php if ($hasValue): ?><th>Value</th><?php endif; ?>
                    <th>Created (NZ)</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if (count($transfers) > 0): ?>
                    <?php foreach ($transfers as $t): ?>
                        <tr class="transfer-row" style="cursor:pointer;">
                            <td>
                                <strong><?= htmlspecialchars($t['consignment_number'] ?? ($t['name'] ?? $t['cis_internal_id'] ?? '')) ?></strong>
                                <i class="fas fa-chevron-down text-muted ml-1 small exp-icon" aria-hidden="true"></i><br>
                                <small class="text-muted">Vend ID: <?= htmlspecialchars($t['id'] ?? '') ?></small>
                            </td>
                            <td><?= htmlspecialchars($t['from_outlet_name'] ?? '-') ?></td>
                            <td><?= htmlspecialchars($t['to_outlet_name'] ?? '-') ?></td>
                            <td>
                                <?php
                                $badgeClass = 'secondary';
                                $state = (string)($t['status'] ?? $t['state'] ?? '');
                                switch ($state) {
                                    case 'RECEIVED':
                                        $badgeClass = 'success';
                                        break;
                                    case 'SENT':
                                    case 'RECEIVING':
                                        $badgeClass = 'warning';
                                        break;
                                    case 'OPEN':
                                        $badgeClass = 'info';
                                        break;
                                }
                                ?>
                                <span class="badge bg-<?= $badgeClass ?>"><?= htmlspecialchars($state) ?></span>
                            </td>
                            <td><?= number_format((int)($t['item_count_total'] ?? 0)) ?></td>
                            <td>
                                <span title="Parcels">📦 <?= (int)($t['parcels_count'] ?? 0) ?></span>
                                <span class="ml-2" title="Shipments">🚚 <?= (int)($t['shipments_count'] ?? 0) ?></span>
                            </td>
                            <td>
                                <?php
                                $received = (int)($t['items_received'] ?? 0);
                                $total = (int)($t['item_count_total'] ?? 0);
                                $pct = ($total > 0) ? max(0, min(100, (int)round(($received / $total) * 100))) : 0;
                                $pctClass = $pct >= 90 ? 'success' : ($pct >= 50 ? 'warning' : ($pct > 0 ? 'danger' : 'secondary'));
                                ?>
                                <span class="badge bg-<?= $pctClass ?>"><?= $pct ?>%</span>
                                <div class="small text-muted"><?= $received ?> / <?= $total ?></div>
                            </td>
                            <td>
                                <?php $phone = (string)($t['to_outlet_phone'] ?? ''); $email = (string)($t['to_outlet_email'] ?? ''); ?>
                                <?php if ($phone): ?><div><a href="tel:<?= htmlspecialchars($phone) ?>" class="text-decoration-none"><i class="fas fa-phone mr-1"></i> <?= htmlspecialchars($phone) ?></a></div><?php endif; ?>
                                <?php if (!$phone && $email): ?><div><a href="mailto:<?= htmlspecialchars($email) ?>" class="text-decoration-none"><i class="fas fa-envelope mr-1"></i> <?= htmlspecialchars($email) ?></a></div><?php endif; ?>
                                <?php if (!$phone && !$email): ?><div class="text-muted">—</div><?php endif; ?>
                            </td>
                            <?php if ($hasValue): ?>
                            <td><?= isset($t['total_cost']) ? ('$'.number_format((float)$t['total_cost'], 2)) : '—' ?></td>
                            <?php endif; ?>
                            <td>
                                <?php $dt = $t['created_at_nz'] ?? ($t['created_at'] ?? null); ?>
                                <?= $dt ? date('Y-m-d H:i', strtotime($dt)) : '-' ?>
                                <?php if (isset($t['age_hours_nz'])): ?>
                                    <div class="text-muted small">~<?= htmlspecialchars((string)$t['age_hours_nz']) ?>h ago</div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="/modules/consignments/stock-transfers/pack.php?id=<?= urlencode((string)($t['cis_internal_id'] ?? $t['id'] ?? '')) ?>" class="btn btn-sm btn-primary">
                                    <i class="fas fa-box"></i> Pack
                                </a>
                            </td>
                        </tr>
                        <tr class="transfer-expander" style="display:none;background:#f8f9fa;">
                          <td colspan="<?= 9 + ($hasValue ? 1 : 0) ?>" style="padding:8px 12px;">
                            <div class="d-flex justify-content-between align-items-start">
                              <div class="small text-muted">
                                <strong>Latest note:</strong>
                                <?= htmlspecialchars($t['latest_note'] ?? 'No notes') ?>
                              </div>
                              <div class="text-right small">
                                <div><strong>Tracking:</strong> <?= htmlspecialchars($t['latest_shipment_carrier'] ?? '-') ?> <?= htmlspecialchars($t['latest_tracking'] ?? '') ?></div>
                              </div>
                            </div>
                          </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="8" class="text-center text-muted py-4">No stock transfers found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<script>
        (function($){
                $(function(){
                    if ($.fn.DataTable) {
                        // Order by Created (NZ) column, which index depends on value column presence
                        var hasValue = <?= $hasValue ? 'true' : 'false' ?>;
                        var createdIdx = hasValue ? 9 : 8;
                        $('#transfersTable').DataTable({ order:[[createdIdx,'desc']], pageLength:25, responsive:true });
                    }
                    // Row expander toggle
                                $('#transfersTable').on('click', 'tr.transfer-row', function(){
                                    var $exp = $(this).next('tr.transfer-expander');
                                    $exp.toggle();
                                    var $ico = $(this).find('.exp-icon');
                                    if ($exp.is(':visible')) { $ico.removeClass('fa-chevron-down').addClass('fa-chevron-up'); }
                                    else { $ico.removeClass('fa-chevron-up').addClass('fa-chevron-down'); }
                                });
                });
        })(jQuery);
</script>

<!-- end-of-view: stock-transfers -->
</div>

<?php
// End content capture and render
$template->endContent();
$template->render();
