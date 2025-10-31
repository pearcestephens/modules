<?php
declare(strict_types=1);
/**
 * Picking Sheet (Dedicated Print)
 * Columns: LINE # • SKU (small) • NAME (wide, left) • SOURCE QTY • QTY PLANNED • QTY (blank) • NOTES
 * - Header repeats every page
 * - Per-product line id printed small as TRANSFERID-1..N
 * - Per-page numbering displayed as TRANSFERID-P{page}-{rowOnPage}
 */

$transferId = (int)($_GET['transfer_id'] ?? 0);
if ($transferId <= 0) { http_response_code(400); echo 'Missing transfer_id'; exit; }

require_once __DIR__ . '/../bootstrap.php';
$pdo = cis_resolve_pdo();

function tfetch(int $tid): array {
    $pdo = cis_resolve_pdo();
    $tq = $pdo->prepare("SELECT id, public_id, outlet_from, outlet_to, created_at FROM transfers WHERE id=? LIMIT 1");
    $tq->execute([$tid]);
    $t = $tq->fetch(PDO::FETCH_ASSOC) ?: [];
    if (!$t) return [];
    $o = $pdo->prepare("SELECT name, CONCAT_WS(', ', physical_address_1, physical_suburb, physical_city) AS addr, physical_phone_number AS phone FROM vend_outlets WHERE id=? LIMIT 1");
    foreach (['outlet_from','outlet_to'] as $k) {
        $o->execute([$t[$k]]);
        $row = $o->fetch(PDO::FETCH_ASSOC) ?: [];
        foreach ($row as $rk=>$rv) { $t[$k.'_'.$rk] = $rv; }
    }
    return $t;
}
function titems(int $tid, string $from): array {
    $pdo = cis_resolve_pdo();
    $q = $pdo->prepare("
        SELECT vp.sku, vp.name, ti.qty_requested,
               COALESCE(vi.current_amount,0) AS stock_at_source
          FROM transfer_items ti
     LEFT JOIN vend_products vp ON vp.id = ti.product_id
     LEFT JOIN vend_inventory vi ON vi.product_id = ti.product_id AND vi.outlet_id = :from
         WHERE ti.transfer_id = :tid
      ORDER BY vp.name ASC
    ");
    $q->execute([':tid'=>$tid, ':from'=>$from]);
    return $q->fetchAll(PDO::FETCH_ASSOC) ?: [];
}

$T  = tfetch($transferId);
if (!$T) { echo 'Transfer not found'; exit; }
$IT = titems($transferId, (string)$T['outlet_from']);

?><!doctype html><html lang="en"><head>
<meta charset="utf-8">
<title>Picking Sheet #<?= (int)$T['id'] ?></title>
<meta name="viewport" content="width=device-width, initial-scale=1">
<style>
  @page { size: A4; margin: 12mm; }
  * { box-sizing: border-box; }
  body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color:#111; }
  .header { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:8px; }
  .h-left .title { font-size:18px; font-weight:700; }
  .h-left .meta { margin-top:4px; color:#444; }
  .h-block { border:1px solid #ddd; padding:6px 8px; border-radius:6px; width:48%; }
  .sub { color:#666; font-size:10px; }
  table { width:100%; border-collapse: collapse; }
  thead { display: table-header-group; }
  tr { page-break-inside: avoid; }
  th, td { border:1px solid #ddd; padding:4px 4px; text-align:center; vertical-align:middle; }
  th.name, td.name { text-align:left !important; padding-left:6px; padding-right:6px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
  th.line, td.line { width:40px; padding:0 2px; }
  th.sku, td.sku   { width:90px; padding:0 2px; font-size:10px; }
  th.qty, td.qty   { width:80px; }
  td.blank { height:20px; }
  .notes { width:auto; }
  .id-small { color:#888; font-size:9px; }
  .footer { position: fixed; bottom: 10mm; left: 0; right: 0; text-align:center; color:#666; font-size:10px; }
</style>
</head><body onload="window.print()">
  <div class="header">
    <div class="h-left">
      <div class="title">Picking Sheet — Transfer #<?= (int)$T['id'] ?></div>
      <div class="meta">
        Created: <?= htmlspecialchars(date('Y-m-d H:i', strtotime((string)$T['created_at']))) ?>
        • From: <?= htmlspecialchars($T['outlet_from_name'] ?? '') ?> (<?= htmlspecialchars($T['outlet_from_phone'] ?? '') ?>)
        • To: <?= htmlspecialchars($T['outlet_to_name'] ?? '') ?> (<?= htmlspecialchars($T['outlet_to_phone'] ?? '') ?>)
      </div>
    </div>
  </div>

  <table>
    <thead>
      <tr>
        <th class="line">#</th>
        <th class="sku">SKU</th>
        <th class="name">Product name</th>
        <th class="qty">Source Qty</th>
        <th class="qty">Qty Planned</th>
        <th class="qty">Qty</th>
        <th class="notes">Notes</th>
      </tr>
    </thead>
    <tbody>
      <?php $n=0; foreach ($IT as $row): $n++; ?>
      <tr>
        <td class="line">
          <?= $n ?><br><span class="id-small"><?= (int)$T['id'].'-'.$n ?></span>
        </td>
        <td class="sku"><?= htmlspecialchars($row['sku'] ?: '-') ?></td>
        <td class="name"><?= htmlspecialchars($row['name'] ?: 'Unnamed') ?></td>
        <td class="qty"><?= (int)$row['stock_at_source'] ?></td>
        <td class="qty"><?= (int)$row['qty_requested'] ?></td>
        <td class="qty blank"></td>
        <td class="notes"></td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>

  <script>
    // Per-page numbering display (visual; content already shows per-product TRANSFERID-{n})
    // Optionally, you can add footer page numbers here if needed.
  </script>
</body></html>
