<?php
declare(strict_types=1);
// Standalone, printer-optimized A4 packing slip (no CIS template)
require_once __DIR__ . '/../bootstrap.php';

$transferId = (int)($_GET['transfer_id'] ?? 0);
$demo = isset($_GET['demo']) && $_GET['demo'] === '1';
$auto = isset($_GET['auto']) && $_GET['auto'] === '1';

$transfer = null; $items = [];
if ($transferId > 0 && function_exists('getUniversalTransfer')) {
    try { $transfer = getUniversalTransfer($transferId); } catch (Throwable $e) { $transfer = null; }
}
if ($transfer && function_exists('getTransferItems')) {
    try { $items = getTransferItems($transferId); } catch (Throwable $e) { $items = []; }
}
if ((!$transfer || !$items) && $demo) {
    $transferId = $transferId ?: 999201;
    $transfer = (object) [
    // Resolve outlet details for professional header
    function resolveOutlet(?string $id): array {
        if (!$id) return [];
        try {
            $pdo = \CIS\Base\Database::pdo();
            $stmt = $pdo->prepare("SELECT id,name,physical_address_1,physical_city,physical_state,physical_postcode,phone FROM vend_outlets WHERE id = ? LIMIT 1");
            $stmt->execute([$id]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
            return $row ?: [];
        } catch (\Throwable $e) { return []; }
    }

    // Try to extract outlet IDs from transfer object (supports multiple shapes)
    $sourceId = $transfer->source_outlet_id ?? ($transfer->outlet_from->id ?? null);
    $destId   = $transfer->destination_outlet_id ?? ($transfer->outlet_to->id ?? null);
    $srcInfo  = resolveOutlet($sourceId);
    $dstInfo  = resolveOutlet($destId);

    // Totals for slip footer
    $totalLines = is_array($items) ? count($items) : 0;
    $totalUnits = 0;
    foreach ($items as $it) { $totalUnits += (int)($it['qty_requested'] ?? $it->qty_requested ?? 0); }
        'id' => $transferId,
        'outlet_from' => (object)['name' => 'Main Warehouse'],
        'outlet_to'   => (object)['name' => 'Outlet 001']
    ];
    $items = [
        ['product_id' => 'SKU-1001', 'name' => 'Vape Juice 100ml (Strawberry)', 'sku' => 'VJ-100-STR', 'qty_requested' => 12],
        ['product_id' => 'SKU-2002', 'name' => 'Coil Pack 5pcs (0.8Ω)', 'sku' => 'COIL-08-5', 'qty_requested' => 8],
        ['product_id' => 'SKU-3003', 'name' => 'Pod Kit XROS 3', 'sku' => 'KIT-XR3', 'qty_requested' => 3],
        :root { --fs-body: 12pt; --pad-cell: 3.5mm; --border: 0.4pt solid #000; --margin: 12mm; }
        @page { size: A4; margin: var(--margin); }
        html, body { background: #fff; color: #000; font: var(--fs-body)/1.35 -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif; }
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
        .header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 6mm; }
        .title { font-size: 18pt; font-weight: 800; letter-spacing: 0.5px; }
        .meta { font-size: 10pt; color: #111; }
        .dest { font-size: 12pt; font-weight: 700; }
        .addr { font-size: 10pt; }
        .blocks { display: grid; grid-template-columns: 1fr 1fr; gap: 5mm; margin-bottom: 5mm; }
        .block { border: var(--border); padding: 3.5mm; }
    .no-print { display: block; margin-bottom: 8px; }
    @media print { .no-print { display: none !important; } }

    .slip { width: 100%; }
    .header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom: 8mm; }
        th, td { border: var(--border); padding: var(--pad-cell) 3mm; vertical-align: middle; }
    .meta { font-size: 10pt; color: #111; }
    .blocks { display: grid; grid-template-columns: 1fr 1fr; gap: 6mm; margin-bottom: 6mm; }
    .block { border: 1px solid #000; padding: 4mm; }
    .block h4 { margin: 0 0 2mm; font-size: 10pt; text-transform: uppercase; }
    table { width: 100%; border-collapse: collapse; page-break-inside:auto; }
        .writein { height: 8mm; border: 1px dashed #000; }
        .notes { height: 8mm; border: 1px dashed #000; }
        .footer { display:flex; justify-content:space-between; margin-top: 5mm; align-items:flex-end; }
    th, td { border: 1px solid #000; padding: 3.5mm 3mm; vertical-align: middle; }
    th { font-size: 10pt; text-align: left; }
    .col-num { width: 10mm; text-align: center; }

        /* Compact mode to save paper */
        body.compact { --fs-body: 11pt; --pad-cell: 2.5mm; --border: 0.3pt solid #000; --margin: 10mm; }
    .col-sku { width: 38mm; font-family: ui-monospace, SFMono-Regular, Menlo, monospace; font-size: 10pt; }
    .col-planned { width: 20mm; text-align: center; }
    <body class="<?= (isset($_GET['compact']) && $_GET['compact'] === '1') ? 'compact' : '' ?>">
    .writein { height: 10mm; border: 1px dashed #000; }
    .notes { height: 10mm; border: 1px dashed #000; }
        <a href="/modules/consignments/stock-transfers/pack.php?transfer_id=<?= (int)$transferId ?>">Back to Pack</a>
        <a href="?transfer_id=<?= (int)$transferId ?>&compact=1" title="Compact mode">Compact</a>
    .line { width: 70mm; height: 10mm; border-bottom: 1px solid #000; }
    .pagenum { position: fixed; bottom: 6mm; right: 0; font-size: 9pt; }
    .pagenum:after { content: "Page " counter(page) " of " counter(pages); }
  </style>
</head>
<body>
            <div class="meta">Transfer #<?= (int)$transferId ?> • Generated <?= date('Y-m-d H:i') ?> • Lines <?= (int)$totalLines ?> • Units <?= (int)$totalUnits ?></div>
            <div class="dest">Destination: <?= htmlspecialchars($dstInfo['name'] ?? ($transfer->outlet_to->name ?? '—')) ?></div>
            <?php if (!empty($dstInfo)): ?>
              <div class="addr">
                <?= htmlspecialchars(trim(($dstInfo['physical_address_1'] ?? '') . ', ' . ($dstInfo['physical_city'] ?? ''))) ?>
                <?php if (!empty($dstInfo['physical_state']) || !empty($dstInfo['physical_postcode'])): ?>
                  • <?= htmlspecialchars(trim(($dstInfo['physical_state'] ?? '') . ' ' . ($dstInfo['physical_postcode'] ?? ''))) ?>
                <?php endif; ?>
                <?php if (!empty($dstInfo['phone'])): ?> • Phone: <?= htmlspecialchars($dstInfo['phone']) ?><?php endif; ?>
              </div>
            <?php endif; ?>
    <button onclick="window.print()">Print</button>
    <a href="/modules/consignments/stock-transfers/pack.php?transfer_id=<?= (int)$transferId ?>">Back to Pack</a>
  </div>

  <div class="slip">
    <div class="header">
      <div>
        <div class="title">PACKING SLIP</div>
        <div class="meta">Transfer #<?= (int)$transferId ?> • Generated <?= date('Y-m-d H:i') ?></div>
            <div><strong><?= htmlspecialchars($srcInfo['name'] ?? ($transfer->outlet_from->name ?? '—')) ?></strong></div>
            <?php if (!empty($srcInfo)) : ?>
            <div class="addr">
              <?= htmlspecialchars(trim(($srcInfo['physical_address_1'] ?? '') . ', ' . ($srcInfo['physical_city'] ?? ''))) ?>
              <?php if (!empty($srcInfo['physical_state']) || !empty($srcInfo['physical_postcode'])): ?>
                • <?= htmlspecialchars(trim(($srcInfo['physical_state'] ?? '') . ' ' . ($srcInfo['physical_postcode'] ?? ''))) ?>
              <?php endif; ?>
              <?php if (!empty($srcInfo['phone'])): ?> • Phone: <?= htmlspecialchars($srcInfo['phone']) ?><?php endif; ?>
            </div>
            <?php endif; ?>
      <div class="meta">
        Code: PS-<?= substr(sha1((string)$transferId . date('Ymd')),0,8) ?><br>
      </div>
            <div><strong><?= htmlspecialchars($dstInfo['name'] ?? ($transfer->outlet_to->name ?? '—')) ?></strong></div>
            <?php if (!empty($dstInfo)) : ?>
            <div class="addr">
              <?= htmlspecialchars(trim(($dstInfo['physical_address_1'] ?? '') . ', ' . ($dstInfo['physical_city'] ?? ''))) ?>
              <?php if (!empty($dstInfo['physical_state']) || !empty($dstInfo['physical_postcode'])): ?>
                • <?= htmlspecialchars(trim(($dstInfo['physical_state'] ?? '') . ' ' . ($dstInfo['physical_postcode'] ?? ''))) ?>
              <?php endif; ?>
              <?php if (!empty($dstInfo['phone'])): ?> • Phone: <?= htmlspecialchars($dstInfo['phone']) ?><?php endif; ?>
            </div>
            <?php endif; ?>

    <div class="blocks">
      <div class="block">
        <h4>From</h4>
        <div><strong><?= htmlspecialchars($transfer->outlet_from->name ?? '—') ?></strong></div>
      </div>
      <div class="block">
        <h4>To</h4>
        <div><strong><?= htmlspecialchars($transfer->outlet_to->name ?? '—') ?></strong></div>
      </div>
    </div>

    <table aria-label="Items">
      <thead>
        <tr>
          <th class="col-num">#</th>
          <th>Product</th>
          <th class="col-sku">SKU</th>
          <th class="col-planned">Planned</th>
          <th class="col-counted">Counted</th>
          <th>Notes</th>
        </tr>
      </thead>
      <tbody>
      <?php $i=0; foreach ($items as $r): $i++; ?>
        <tr>
          <td class="col-num"><?= $i ?></td>
          <td><?= htmlspecialchars((string)($r['name'] ?? $r->name ?? '')) ?></td>
          <td class="col-sku"><?= htmlspecialchars((string)($r['sku']  ?? $r->sku  ?? '')) ?></td>
          <td class="col-planned"><?= (int)($r['qty_requested'] ?? $r->qty_requested ?? 0) ?></td>
          <td><div class="writein"></div></td>
          <td><div class="notes"></div></td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>

    <div class="footer">
      <div>
        <div style="font-size:10pt;">Packed by</div>
        <div class="line"></div>
      </div>
      <div>
        <div style="font-size:10pt;">Sign-off</div>
        <div class="line"></div>
      </div>
    </div>

    <div class="pagenum"></div>
  </div>

  <?php if ($auto): ?>
  <script>window.print();</script>
  <?php endif; ?>
</body>
</html>
