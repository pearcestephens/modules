<section class="vt-block vt-block--table">
  <form id="receiveForm" class="vt-form" autocomplete="off">
    <input type="hidden" name="csrf" value="<?= \Transfers\Lib\Security::csrfToken(); ?>">
    <input type="hidden" name="nonce" value="<?= bin2hex(random_bytes(8)); ?>">
    <input type="hidden" name="transfer_id" value="<?= (int)$transferId ?>">

    <table class="table vt-table js-receive-table">
      <thead>
        <tr><th>SKU</th><th>Product</th><th>Sent</th><th>Received</th><th>Notes</th></tr>
      </thead>
      <tbody>
        <?php foreach ($items as $line): ?>
          <tr data-item-id="<?= (int)$line['id'] ?>">
            <td><?= htmlspecialchars($line['sku'] ?? '') ?></td>
            <td><?= htmlspecialchars($line['name'] ?? $line['product_id']) ?></td>
            <td><?= (int)$line['qty_sent_total'] ?></td>
            <td>
              <input class="form-control form-control-sm qty-input"
                     type="number" min="0" max="<?= (int)$line['qty_sent_total'] - (int)$line['qty_received_total'] ?>"
                     name="lines[<?= (int)$line['id'] ?>][qty_received]" value="0">
              <input type="hidden" name="lines[<?= (int)$line['id'] ?>][condition]" value="ok">
            </td>
            <td><input class="form-control form-control-sm" type="text" name="lines[<?= (int)$line['id'] ?>][notes]" placeholder="Optional"></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
