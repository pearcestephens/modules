<?php
/**
 * Outlet Sidebar Partial
 * Expects $transferData to be in scope (object/array) and contains outlet_from/outlet_to and shipments/notes arrays
 */
if (!isset($transferData)) {
    echo '<div class="alert alert-secondary">No transfer data available</div>'; return;
}

$to = is_object($transferData) ? ($transferData->outlet_to ?? null) : ($transferData['outlet_to'] ?? null);
$shipments = is_object($transferData) ? ($transferData->shipments ?? []) : ($transferData['shipments'] ?? []);
$notes = is_object($transferData) ? ($transferData->notes ?? []) : ($transferData['notes'] ?? []);

?>
<aside class="consignment-sidebar" style="width:320px;padding:12px;">
  <div class="card">
    <div class="card-body">
      <h5 class="mb-1"><?= htmlspecialchars($to->name ?? ($to['name'] ?? 'Destination')) ?></h5>
      <div class="small text-muted mb-2">Destination Outlet</div>

      <div class="mb-2">
        <div><strong>Phone</strong></div>
        <div><a href="tel:<?= htmlspecialchars($to->phone ?? ($to['phone'] ?? '')) ?>"><?= htmlspecialchars($to->phone ?? ($to['phone'] ?? '-')) ?></a></div>
      </div>

      <div class="mb-2">
        <div><strong>Email</strong></div>
        <div><?= htmlspecialchars($to->email ?? ($to['email'] ?? '-')) ?></div>
      </div>

      <hr>
      <div class="mb-2">
        <div><strong>Recent Shipments</strong></div>
        <?php if (!empty($shipments)): ?>
          <ul class="list-unstyled small mb-0">
            <?php foreach (array_slice($shipments,0,3) as $s): ?>
              <li>
                <div><?= htmlspecialchars($s['carrier'] ?? $s->carrier ?? '-') ?> — <?= htmlspecialchars($s['tracking_number'] ?? $s->tracking_number ?? '-') ?></div>
                <div class="text-muted small"><?= htmlspecialchars($s['packed_at'] ?? $s->created_at ?? '') ?></div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php else: ?>
          <div class="small text-muted">No recent shipments</div>
        <?php endif; ?>
      </div>

      <hr>
      <div>
        <div><strong>Latest Note</strong></div>
        <div class="small text-muted mb-1"><?= htmlspecialchars($notes[0]['text'] ?? $notes[0]->text ?? 'No notes') ?></div>
        <a href="/modules/consignments/?route=transfer-notes&transfer_id=<?= urlencode((string)($transferData->id ?? $transferData['id'] ?? '')) ?>" class="small">View all notes</a>
      </div>
    </div>
  </div>
</aside>
