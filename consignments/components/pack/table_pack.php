<section class="vt-block vt-block--table">
  <form id="packForm" class="vt-form" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= \Transfers\Lib\Security::csrfToken(); ?>">
    <input type="hidden" name="nonce" value="<?= bin2hex(random_bytes(8)); ?>">
    <input type="hidden" name="transfer_id" value="<?= (int)$transferId ?>">

    <table class="table vt-table js-pack-table">
      <thead>
        <tr>
          <th>SKU</th><th>Product</th><th>Planned</th><th>Packed</th><th>Remaining</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($items as $line): ?>
          <tr data-item-id="<?= (int)$line['id'] ?>">
            <td><?= htmlspecialchars($line['sku'] ?? '') ?></td>
            <td><?= htmlspecialchars($line['name'] ?? $line['product_id']) ?></td>
            <td><?= (int)$line['qty_requested'] ?></td>
            <td>
              <input class="form-control form-control-sm qty-input"
                     type="number" min="0"
                     name="lines[<?= (int)$line['id'] ?>][qty_packed]"
                     value="0">
              <input type="hidden" name="lines[<?= (int)$line['id'] ?>][product_id]" value="<?= htmlspecialchars($line['product_id']) ?>">
              <input type="hidden" name="lines[<?= (int)$line['id'] ?>][qty_planned]" value="<?= (int)$line['qty_requested'] ?>">
            </td>
            <td class="rem-cell"><?= max(0, (int)$line['qty_requested'] - (int)$line['qty_sent_total']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
