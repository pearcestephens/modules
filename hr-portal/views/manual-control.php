<?php
/**
 * Manual Control View
 * Manual payroll processing interface
 */

$pendingItems = $dashboard->getPendingItems(100);
?>

<div class="manual-control-view">
    <div class="view-header">
        <h4>👤 Manual Control Center</h4>
        <p class="text-muted">All pending items requiring human review</p>
    </div>

    <div class="control-actions mb-3">
        <button class="btn btn-success" onclick="batchApprove()">
            <i class="fas fa-check-double"></i> Approve All High Confidence
        </button>
        <button class="btn btn-outline-secondary" onclick="exportPending()">
            <i class="fas fa-download"></i> Export to CSV
        </button>
    </div>

    <?php if (empty($pendingItems)): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i>
            <strong>All clear!</strong> No items need your review right now.
        </div>
    <?php else: ?>
        <div class="pending-items-list">
            <?php foreach ($pendingItems as $item): ?>
                <div class="pending-item-card" data-decision-id="<?php echo $item['id']; ?>">
                    <div class="item-header">
                        <div class="item-icon">
                            <?php echo $item['icon']; ?>
                        </div>
                        <div class="item-info">
                            <h5><?php echo htmlspecialchars($item['staff_name'] ?? 'Unknown Staff'); ?></h5>
                            <span class="item-type"><?php echo htmlspecialchars($item['type_label']); ?></span>
                            <span class="item-date"><?php echo date('d M Y, H:i', strtotime($item['created_at'])); ?></span>
                        </div>
                        <div class="item-confidence">
                            <div class="confidence-label">AI Confidence</div>
                            <div class="confidence-value"><?php echo round($item['confidence_score'] * 100); ?>%</div>
                            <div class="confidence-meter-small">
                                <div class="confidence-fill" style="width: <?php echo ($item['confidence_score'] * 100); ?>%;
                                     background-color: <?php
                                        if ($item['confidence_score'] >= 0.8) echo '#28a745';
                                        elseif ($item['confidence_score'] >= 0.6) echo '#ffc107';
                                        else echo '#dc3545';
                                     ?>;">
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="item-body">
                        <div class="item-details">
                            <strong>Details:</strong>
                            <?php if ($item['item_type'] === 'timesheet'): ?>
                                <p>
                                    Time change: <?php echo $item['details']['original_hours']; ?>h → <?php echo $item['details']['new_hours']; ?>h
                                    (<?php echo $item['details']['diff_hours']; ?>h difference)
                                </p>
                                <p><strong>Date:</strong> <?php echo $item['details']['date']; ?></p>
                                <p><strong>Reason:</strong> <?php echo htmlspecialchars($item['details']['reason']); ?></p>
                            <?php elseif ($item['item_type'] === 'payrun'): ?>
                                <p>
                                    Pay adjustment: $<?php echo number_format($item['details']['original_amount'], 2); ?> →
                                    $<?php echo number_format($item['details']['new_amount'], 2); ?>
                                    ($<?php echo number_format(abs($item['details']['adjustment_amount']), 2); ?> change)
                                </p>
                                <p><strong>Reason:</strong> <?php echo htmlspecialchars($item['details']['reason']); ?></p>
                            <?php elseif ($item['item_type'] === 'vend'): ?>
                                <p>
                                    Vend payment: $<?php echo number_format($item['details']['amount'], 2); ?>
                                </p>
                                <p><strong>Type:</strong> <?php echo htmlspecialchars($item['details']['payment_type']); ?></p>
                                <p><strong>Reference:</strong> <?php echo htmlspecialchars($item['details']['reference']); ?></p>
                            <?php endif; ?>
                        </div>

                        <div class="item-reasoning">
                            <strong>AI Reasoning:</strong>
                            <p class="text-muted"><?php echo htmlspecialchars($item['reasoning']); ?></p>
                        </div>

                        <?php if ($item['decision'] === 'escalate'): ?>
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i>
                                <strong>Escalated!</strong> This item requires manager approval.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="item-actions">
                        <button class="btn btn-success" onclick="approveItem(<?php echo $item['id']; ?>)">
                            <i class="fas fa-check"></i> Approve
                        </button>
                        <button class="btn btn-danger" onclick="denyItem(<?php echo $item['id']; ?>)">
                            <i class="fas fa-times"></i> Deny
                        </button>
                        <button class="btn btn-outline-secondary" onclick="viewDetails(<?php echo $item['id']; ?>)">
                            <i class="fas fa-info-circle"></i> More Details
                        </button>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<style>
.pending-item-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 20px;
    margin-bottom: 15px;
    transition: all 0.2s ease;
}

.pending-item-card:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transform: translateY(-2px);
}

.item-header {
    display: flex;
    align-items: center;
    margin-bottom: 15px;
    padding-bottom: 15px;
    border-bottom: 1px solid #e9ecef;
}

.item-icon {
    font-size: 36px;
    margin-right: 15px;
}

.item-info {
    flex: 1;
}

.item-info h5 {
    margin: 0 0 5px 0;
}

.item-type {
    background: #6c757d;
    color: white;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    margin-right: 10px;
}

.item-date {
    color: #6c757d;
    font-size: 13px;
}

.item-confidence {
    text-align: center;
    min-width: 100px;
}

.confidence-label {
    font-size: 11px;
    color: #6c757d;
}

.confidence-value {
    font-size: 24px;
    font-weight: bold;
    margin: 5px 0;
}

.confidence-meter-small {
    width: 80px;
    height: 6px;
    background: #e9ecef;
    border-radius: 3px;
    overflow: hidden;
    margin: 0 auto;
}

.item-body {
    margin-bottom: 15px;
}

.item-details, .item-reasoning {
    margin-bottom: 15px;
}

.item-actions {
    display: flex;
    gap: 10px;
}

.item-actions .btn {
    flex: 1;
}
</style>
