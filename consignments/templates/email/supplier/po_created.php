<h2>New Purchase Order</h2>

<p>Dear <?= htmlspecialchars($supplier_contact_name  ?? 'N/A') ?>,</p>

<p>We have created a new purchase order for your review and fulfillment.</p>

<div class="info-box">
    <h3>Purchase Order Details</h3>
    <div class="info-row">
        <span class="info-label">PO Number:</span>
        <span class="info-value"><?= htmlspecialchars($po_number  ?? 'N/A') ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Order Date:</span>
        <span class="info-value"><?= htmlspecialchars($order_date  ?? 'N/A') ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Total Value:</span>
        <span class="info-value"><?= htmlspecialchars($total_value  ?? 'N/A') ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Items:</span>
        <span class="info-value"><?= htmlspecialchars($items_count  ?? 'N/A') ?> line items</span>
    </div>
    <?= htmlspecialchars($#expected_delivery_date  ?? 'N/A') ?>
    <div class="info-row">
        <span class="info-label">Expected Delivery:</span>
        <span class="info-value"><?= htmlspecialchars($expected_delivery_date  ?? 'N/A') ?></span>
    </div>
    <?= htmlspecialchars($/expected_delivery_date  ?? 'N/A') ?>
</div>

<div class="info-box">
    <h3>Delivery Address</h3>
    <p>
        <?= htmlspecialchars($delivery_store_name  ?? 'N/A') ?><br>
        <?= htmlspecialchars($delivery_address_line1  ?? 'N/A') ?><br>
        <?= htmlspecialchars($#delivery_address_line2  ?? 'N/A') ?>{{delivery_address_line2  ?? 'N/A') ?><br>{{/delivery_address_line2  ?? 'N/A') ?>
        <?= htmlspecialchars($delivery_city  ?? 'N/A') ?>, {{delivery_postcode  ?? 'N/A') ?><br>
        <?= htmlspecialchars($delivery_country  ?? 'N/A') ?>
    </p>
</div>

<div class="button-container">
    <a href="<?= htmlspecialchars($supplier_portal_url  ?? 'N/A') ?>" class="button">View Purchase Order</a>
</div>

<p>You can view the complete purchase order details, including line items and delivery instructions, by clicking the button above.</p>

<p>If you have any questions or concerns about this order, please contact us at <?= htmlspecialchars($support_email  ?? 'N/A') ?> or call {{support_phone  ?? 'N/A') ?>.</p>

<p>Thank you for your continued partnership.</p>

<p>Best regards,<br>
<strong><?= htmlspecialchars($company_name  ?? 'N/A') ?> Purchasing Team</strong></p>
