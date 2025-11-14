<?php
/**
 * STAFF DASHBOARD WIDGET
 * Shows only staff's active transfers
 */

try {
    // Get all transfers for this staff member
    $stmt = $pdo->prepare("
        SELECT
            c.id,
            c.transfer_number,
            c.transfer_type,
            c.status,
            c.created_at,
            c.sent_at,
            COUNT(DISTINCT ci.id) as item_count,
            SUM(ci.quantity) as total_qty,
            o1.outlet_name as from_outlet,
            o2.outlet_name as to_outlet
        FROM consignments c
        LEFT JOIN consignment_items ci ON ci.consignment_id = c.id
        LEFT JOIN outlets o1 ON o1.id = c.from_outlet_id
        LEFT JOIN outlets o2 ON o2.id = c.to_outlet_id
        WHERE c.created_by_user_id = ? OR c.assigned_to_user_id = ?
        GROUP BY c.id
        ORDER BY
            CASE WHEN c.status = 'in_progress' THEN 1
                 WHEN c.status = 'ready' THEN 2
                 WHEN c.status = 'draft' THEN 3
                 ELSE 4 END,
            c.created_at DESC
        LIMIT 50
    ");

    $stmt->execute([$userID, $userID]);
    $transfers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

    // Count by status
    $stmt2 = $pdo->prepare("
        SELECT status, COUNT(*) as count
        FROM consignments
        WHERE created_by_user_id = ? OR assigned_to_user_id = ?
        GROUP BY status
    ");
    $stmt2->execute([$userID, $userID]);
    $statusCounts = [];
    foreach ($stmt2->fetchAll(\PDO::FETCH_ASSOC) as $row) {
        $statusCounts[$row['status']] = $row['count'];
    }

    $inProgress = $statusCounts['in_progress'] ?? 0;
    $ready = $statusCounts['ready'] ?? 0;
    $draft = $statusCounts['draft'] ?? 0;
    $sent = $statusCounts['sent'] ?? 0;

} catch (Exception $e) {
    $transfers = [];
    $inProgress = $ready = $draft = $sent = 0;
}
?>

<div class="widget-grid">
    <!-- Stats Cards -->
    <div class="widget-card info">
        <div class="metric-box">
            <div class="metric-value"><?= $inProgress ?></div>
            <div class="metric-label">IN PROGRESS</div>
        </div>
        <small class="text-muted">Currently packing</small>
    </div>

    <div class="widget-card success">
        <div class="metric-box">
            <div class="metric-value"><?= $ready ?></div>
            <div class="metric-label">READY TO SEND</div>
        </div>
        <small class="text-muted">Awaiting courier pickup</small>
    </div>

    <div class="widget-card warning">
        <div class="metric-box">
            <div class="metric-value"><?= $draft ?></div>
            <div class="metric-label">DRAFTS</div>
        </div>
        <small class="text-muted">Not yet started</small>
    </div>
</div>

<!-- Transfers List -->
<div class="widget-card">
    <div class="widget-title">
        <i class="fas fa-list"></i> Active Transfers
    </div>

    <?php if (empty($transfers)): ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <p>No active transfers</p>
            <a href="/modules/consignments/create.php" class="quick-action-btn">
                <i class="fas fa-plus"></i> Create Transfer
            </a>
        </div>
    <?php else: ?>
        <div class="widget-content">
            <?php foreach ($transfers as $t): ?>
                <div style="padding: 1rem; border-bottom: 1px solid #eee; margin-bottom: 1rem;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.5rem;">
                        <strong><?= htmlspecialchars($t['transfer_number']) ?></strong>
                        <span class="status-badge status-<?= $t['status'] ?>">
                            <?= ucfirst(str_replace('_', ' ', $t['status'])) ?>
                        </span>
                    </div>

                    <div style="font-size: 0.9rem; color: #666; margin-bottom: 0.5rem;">
                        <i class="fas fa-arrow-right"></i>
                        <?= htmlspecialchars($t['from_outlet'] ?? 'Unknown') ?> →
                        <?= htmlspecialchars($t['to_outlet'] ?? 'Unknown') ?>
                    </div>

                    <div class="stat-row" style="border: none; padding: 0;">
                        <span><?= $t['item_count'] ?> items • <?= $t['total_qty'] ?? 0 ?> units</span>
                        <small class="text-muted">
                            <?= date('M d, H:i', strtotime($t['created_at'])) ?>
                        </small>
                    </div>

                    <div style="margin-top: 0.5rem;">
                        <a href="/modules/consignments/view.php?id=<?= $t['id'] ?>"
                           class="quick-action-btn" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;">
                            <i class="fas fa-eye"></i> View
                        </a>
                        <?php if ($t['status'] === 'draft'): ?>
                            <a href="/modules/consignments/edit.php?id=<?= $t['id'] ?>"
                               class="quick-action-btn" style="padding: 0.35rem 0.75rem; font-size: 0.8rem;">
                                <i class="fas fa-edit"></i> Edit
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<div class="widget-card info">
    <div class="widget-title">
        <i class="fas fa-lightbulb"></i> Quick Tips
    </div>
    <ul style="margin: 0; padding-left: 1.5rem; color: #555; line-height: 1.8;">
        <li>Use the Box Optimizer to reduce packaging costs</li>
        <li>Check profitability before creating transfers</li>
        <li>Consolidate with other transfers when possible</li>
        <li>Always verify recipient outlet before sending</li>
    </ul>
</div>
