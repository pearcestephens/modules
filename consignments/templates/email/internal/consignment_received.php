<h2>Consignment Received</h2>

<p>Hi <?= htmlspecialchars($recipient_name  ?? 'N/A') ?>,</p>

<div class="alert success">
    <p><strong>Consignment Received:</strong> A consignment has been successfully received and processed.</p>
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
    <div class="info-row">
        <span class="info-label">Items Received:</span>
        <span class="info-value"><?= htmlspecialchars($items_count  ?? 'N/A') ?> items</span>
    </div>
    <div class="info-row">
        <span class="info-label">Total Value:</span>
        <span class="info-value"><?= htmlspecialchars($total_value  ?? 'N/A') ?></span>
    </div>
</div>

<?= htmlspecialchars($#has_discrepancies  ?? 'N/A') ?>
<div class="alert">
    <p><strong>Note:</strong> This consignment has <?= htmlspecialchars($discrepancies_count  ?? 'N/A') ?> discrepancy(ies) that require review.</p>
</div>
<?= htmlspecialchars($/has_discrepancies  ?? 'N/A') ?>

<div class="button-container">
    <a href="<?= htmlspecialchars($consignment_url  ?? 'N/A') ?>" class="button">View Consignment</a>
</div>

<p>All items have been added to inventory and are now available for sale.</p>
