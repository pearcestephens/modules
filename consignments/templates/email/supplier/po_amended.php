<?php
/**
 * Purchase Order Amended - Supplier Notification
 * Template variables: po_number, supplier_name, amendment_summary, amended_by, amended_at, po_url
 */
?>
<h2>Purchase Order Amendment</h2>

<p>Hi <?= htmlspecialchars($supplier_name ?? 'there') ?>,</p>

<p>Purchase Order <strong><?= htmlspecialchars($po_number ?? 'N/A') ?></strong> has been amended.</p>

<div class="info-box">
    <h3>Amendment Details</h3>
    <div class="info-row">
        <span class="info-label">PO Number:</span>
        <span class="info-value"><?= htmlspecialchars($po_number ?? 'N/A') ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Amendment Summary:</span>
        <span class="info-value"><?= htmlspecialchars($amendment_summary ?? 'N/A') ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Amended By:</span>
        <span class="info-value"><?= htmlspecialchars($amended_by ?? 'N/A') ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Amendment Date:</span>
        <span class="info-value"><?= htmlspecialchars($amended_at ?? 'N/A') ?></span>
    </div>
</div>

<div class="button-container">
    <a href="<?= htmlspecialchars($po_url ?? '#') ?>" class="button">View Updated Purchase Order</a>
</div>

<p>Please review the changes and confirm receipt. If you have any questions, please contact us.</p>
