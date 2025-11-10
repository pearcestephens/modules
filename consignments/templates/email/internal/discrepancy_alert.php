<h2>Consignment Discrepancy Alert</h2>

<p>Hi <?= htmlspecialchars($recipient_name  ?? 'N/A') ?>,</p>

<div class="alert danger">
    <p><strong>Urgent:</strong> A consignment has been received with discrepancies that require immediate attention.</p>
</div>

<div class="info-box">
    <h3>Consignment Details</h3>
    <div class="info-row">
        <span class="info-label">Consignment Number:</span>
        <span class="info-value"><?= htmlspecialchars($consignment_number  ?? 'N/A') ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">PO Number:</span>
        <span class="info-value"><?= htmlspecialchars($po_number  ?? 'N/A') ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Supplier:</span>
        <span class="info-value"><?= htmlspecialchars($supplier_name  ?? 'N/A') ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Store:</span>
        <span class="info-value"><?= htmlspecialchars($store_name  ?? 'N/A') ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Received By:</span>
        <span class="info-value"><?= htmlspecialchars($received_by  ?? 'N/A') ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Received:</span>
        <span class="info-value"><?= htmlspecialchars($received_at  ?? 'N/A') ?></span>
    </div>
</div>

<div class="info-box">
    <h3>Discrepancies Found</h3>
    <?= htmlspecialchars($#discrepancies  ?? 'N/A') ?>
    <div style="margin-bottom: 15px; padding-bottom: 15px; border-bottom: 1px solid #e9ecef;">
        <p style="margin: 0 0 5px 0;"><strong><?= htmlspecialchars($product_name  ?? 'N/A') ?></strong></p>
        <div class="info-row">
            <span class="info-label">Type:</span>
            <span class="info-value"><?= htmlspecialchars($type  ?? 'N/A') ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Expected:</span>
            <span class="info-value"><?= htmlspecialchars($expected_quantity  ?? 'N/A') ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Received:</span>
            <span class="info-value"><?= htmlspecialchars($received_quantity  ?? 'N/A') ?></span>
        </div>
        <div class="info-row">
            <span class="info-label">Variance:</span>
            <span class="info-value"><?= htmlspecialchars($variance  ?? 'N/A') ?></span>
        </div>
        <?= htmlspecialchars($#notes  ?? 'N/A') ?>
        <div class="info-row">
            <span class="info-label">Notes:</span>
            <span class="info-value"><?= htmlspecialchars($notes  ?? 'N/A') ?></span>
        </div>
        <?= htmlspecialchars($/notes  ?? 'N/A') ?>
    </div>
    <?= htmlspecialchars($/discrepancies  ?? 'N/A') ?>
</div>

<div class="button-container">
    <a href="<?= htmlspecialchars($consignment_url  ?? 'N/A') ?>" class="button">Review Discrepancies</a>
</div>

<p><strong>Action Required:</strong> Please review these discrepancies and contact the supplier if necessary. The receiving staff member may have added notes with additional context.</p>
