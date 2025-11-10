<h2>Purchase Order Approved</h2>

<p>Hi <?= htmlspecialchars($recipient_name  ?? 'N/A') ?>,</p>

<div class="alert success">
    <p><strong>Great news!</strong> Your purchase order has been approved and will be sent to the supplier.</p>
</div>

<div class="info-box">
    <h3>Purchase Order Details</h3>
    <div class="info-row">
        <span class="info-label">PO Number:</span>
        <span class="info-value"><?= htmlspecialchars($po_number  ?? 'N/A') ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Supplier:</span>
        <span class="info-value"><?= htmlspecialchars($supplier_name  ?? 'N/A') ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Total Value:</span>
        <span class="info-value"><?= htmlspecialchars($total_value  ?? 'N/A') ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Approved By:</span>
        <span class="info-value"><?= htmlspecialchars($approved_by  ?? 'N/A') ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Approved:</span>
        <span class="info-value"><?= htmlspecialchars($approved_at  ?? 'N/A') ?></span>
    </div>
    <?= htmlspecialchars($#approval_notes  ?? 'N/A') ?>
    <div class="info-row">
        <span class="info-label">Notes:</span>
        <span class="info-value"><?= htmlspecialchars($approval_notes  ?? 'N/A') ?></span>
    </div>
    <?= htmlspecialchars($/approval_notes  ?? 'N/A') ?>
</div>

<div class="button-container">
    <a href="<?= htmlspecialchars($po_url  ?? 'N/A') ?>" class="button">View Purchase Order</a>
</div>

<p>The supplier will receive this purchase order shortly. You can track its progress in the consignments system.</p>
