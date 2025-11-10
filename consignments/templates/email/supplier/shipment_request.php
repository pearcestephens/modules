<?php
/**
 * Shipment Request - Supplier Notification
 * Template variables: po_number, supplier_name, requested_ship_date, special_instructions, contact_name, contact_email, contact_phone
 */
?>
<h2>Shipment Request</h2>

<p>Hi <?= htmlspecialchars($supplier_name ?? 'there') ?>,</p>

<p>We are requesting shipment for Purchase Order <strong><?= htmlspecialchars($po_number ?? 'N/A') ?></strong>.</p>

<div class="info-box">
    <h3>Shipment Details</h3>
    <div class="info-row">
        <span class="info-label">PO Number:</span>
        <span class="info-value"><?= htmlspecialchars($po_number ?? 'N/A') ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Requested Ship Date:</span>
        <span class="info-value"><?= htmlspecialchars($requested_ship_date ?? 'ASAP') ?></span>
    </div>
    <?php if (!empty($special_instructions)): ?>
    <div class="info-row">
        <span class="info-label">Special Instructions:</span>
        <span class="info-value"><?= nl2br(htmlspecialchars($special_instructions)) ?></span>
    </div>
    <?php endif; ?>
</div>

<div class="info-box">
    <h3>Contact Information</h3>
    <div class="info-row">
        <span class="info-label">Contact Name:</span>
        <span class="info-value"><?= htmlspecialchars($contact_name ?? 'N/A') ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Email:</span>
        <span class="info-value"><?= htmlspecialchars($contact_email ?? 'N/A') ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Phone:</span>
        <span class="info-value"><?= htmlspecialchars($contact_phone ?? 'N/A') ?></span>
    </div>
</div>

<p>Please confirm receipt and provide tracking information once shipped. Thank you!</p>
