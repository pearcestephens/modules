<h2>Purchase Order Rejected</h2>

<p>Hi <?= htmlspecialchars($recipient_name  ?? 'N/A') ?>,</p>

<div class="alert danger">
    <p><strong>Purchase Order Rejected:</strong> Your purchase order has not been approved.</p>
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
        <span class="info-label">Rejected By:</span>
        <span class="info-value"><?= htmlspecialchars($rejected_by  ?? 'N/A') ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Rejected:</span>
        <span class="info-value"><?= htmlspecialchars($rejected_at  ?? 'N/A') ?></span>
    </div>
</div>

<?= htmlspecialchars($#rejection_reason  ?? 'N/A') ?>
<div class="info-box">
    <h3>Rejection Reason</h3>
    <p><?= htmlspecialchars($rejection_reason  ?? 'N/A') ?></p>
</div>
<?= htmlspecialchars($/rejection_reason  ?? 'N/A') ?>

<div class="button-container">
    <a href="<?= htmlspecialchars($po_url  ?? 'N/A') ?>" class="button">View Purchase Order</a>
</div>

<p>Please review the rejection reason and make the necessary changes before resubmitting. If you have any questions, please contact the approver directly.</p>
