<?php
/**
 * Purchase Order Created - Internal Notification
 * Template variables: po_number, supplier_name, total_value, created_by, created_at, po_url
 */
?>
<h2>Purchase Order Created</h2>

<p>Hi <?= htmlspecialchars($recipient_name ?? 'there') ?>,</p>

<p>A new purchase order has been created and requires your attention.</p>

<div class="info-box">
    <h3>Purchase Order Details</h3>
    <div class="info-row">
        <span class="info-label">PO Number:</span>
        <span class="info-value"><?= htmlspecialchars($po_number ?? 'N/A') ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Supplier:</span>
        <span class="info-value"><?= htmlspecialchars($supplier_name ?? 'N/A') ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Total Value:</span>
        <span class="info-value"><?= htmlspecialchars($total_value ?? 'N/A') ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Created By:</span>
        <span class="info-value"><?= htmlspecialchars($created_by ?? 'N/A') ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Created:</span>
        <span class="info-value"><?= htmlspecialchars($created_at ?? 'N/A') ?></span>
    </div>
</div>

<div class="button-container">
    <a href="<?= htmlspecialchars($po_url ?? '#') ?>" class="button">View Purchase Order</a>
</div>

<p>If you have any questions about this purchase order, please contact the purchasing team.</p>
