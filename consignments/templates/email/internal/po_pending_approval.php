<h2>Purchase Order Approval Required</h2>

<p>Hi <?= htmlspecialchars($recipient_name  ?? 'N/A') ?>,</p>

<p>A purchase order requires your approval before it can be sent to the supplier.</p>

<div class="alert">
    <p><strong>Action Required:</strong> Please review and approve or reject this purchase order.</p>
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
        <span class="info-label">Requested By:</span>
        <span class="info-value"><?= htmlspecialchars($created_by  ?? 'N/A') ?></span>
    </div>
    <div class="info-row">
        <span class="info-label">Created:</span>
        <span class="info-value"><?= htmlspecialchars($created_at  ?? 'N/A') ?></span>
    </div>
    <?= htmlspecialchars($#approval_deadline  ?? 'N/A') ?>
    <div class="info-row">
        <span class="info-label">Approval Deadline:</span>
        <span class="info-value"><?= htmlspecialchars($approval_deadline  ?? 'N/A') ?></span>
    </div>
    <?= htmlspecialchars($/approval_deadline  ?? 'N/A') ?>
</div>

<div class="button-container">
    <a href="<?= htmlspecialchars($approval_url  ?? 'N/A') ?>" class="button">Review & Approve</a>
</div>

<p><em>Note: If this purchase order is not approved by the deadline, it will be automatically escalated to the next approval level.</em></p>
