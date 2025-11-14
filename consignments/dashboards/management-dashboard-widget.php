<?php
/**
 * MANAGEMENT CONTROL PANEL WIDGET
 * Full system management - create, edit, approve transfers
 */

try {
    // System-wide transfer stats
    $stmt = $pdo->prepare("
        SELECT
            status,
            COUNT(*) as count,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today
        FROM consignments
        GROUP BY status
    ");
    $stmt->execute();
    $systemStats = [];
    foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
        $systemStats[$row['status']] = $row;
    }

    // Cost metrics
    $stmt2 = $pdo->prepare("
        SELECT
            SUM(estimated_shipping_cost) as total_shipping,
            SUM(CASE WHEN DATE(created_at) = CURDATE() THEN estimated_shipping_cost ELSE 0 END) as shipping_today,
            SUM(estimated_profit) as total_profit,
            COUNT(DISTINCT from_outlet_id) as from_outlets,
            COUNT(DISTINCT to_outlet_id) as to_outlets
        FROM consignments
        WHERE status IN ('sent', 'ready', 'in_progress')
    ");
    $stmt2->execute();
    $costMetrics = $stmt2->fetch(\PDO::FETCH_ASSOC) ?? [
        'total_shipping' => 0,
        'shipping_today' => 0,
        'total_profit' => 0,
        'from_outlets' => 0,
        'to_outlets' => 0
    ];

    // Recent transfers needing action
    $stmt3 = $pdo->prepare("
        SELECT
            c.id,
            c.transfer_number,
            c.transfer_type,
            c.status,
            c.created_at,
            COUNT(DISTINCT ci.id) as item_count,
            SUM(ci.quantity) as total_qty,
            o1.outlet_name as from_outlet,
            o2.outlet_name as to_outlet,
            c.estimated_profit,
            c.estimated_shipping_cost
        FROM consignments c
        LEFT JOIN consignment_items ci ON ci.consignment_id = c.id
        LEFT JOIN outlets o1 ON o1.id = c.from_outlet_id
        LEFT JOIN outlets o2 ON o2.id = c.to_outlet_id
        WHERE c.status IN ('draft', 'ready', 'in_progress')
        GROUP BY c.id
        ORDER BY
            CASE WHEN c.status = 'draft' THEN 1
                 WHEN c.status = 'ready' THEN 2
                 ELSE 3 END,
            c.created_at DESC
        LIMIT 20
    ");
    $stmt3->execute();
    $pendingTransfers = $stmt3->fetchAll(\PDO::FETCH_ASSOC);

    // Unprofitable transfers alert
    $stmt4 = $pdo->prepare("
        SELECT
            c.id,
            c.transfer_number,
            c.estimated_profit,
            c.estimated_shipping_cost,
            COUNT(DISTINCT ci.id) as item_count,
            o1.outlet_name as from_outlet,
            o2.outlet_name as to_outlet
        FROM consignments c
        LEFT JOIN consignment_items ci ON ci.consignment_id = c.id
        LEFT JOIN outlets o1 ON o1.id = c.from_outlet_id
        LEFT JOIN outlets o2 ON o2.id = c.to_outlet_id
        WHERE c.status IN ('draft', 'ready')
          AND c.estimated_profit < 10
        GROUP BY c.id
        ORDER BY c.estimated_profit ASC
        LIMIT 10
    ");
    $stmt4->execute();
    $unprofitable = $stmt4->fetchAll(\PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $systemStats = [];
    $costMetrics = ['total_shipping' => 0, 'shipping_today' => 0, 'total_profit' => 0, 'from_outlets' => 0, 'to_outlets' => 0];
    $pendingTransfers = [];
    $unprofitable = [];
}

$draftCount = $systemStats['draft']['count'] ?? 0;
$readyCount = $systemStats['ready']['count'] ?? 0;
$inProgressCount = $systemStats['in_progress']['count'] ?? 0;
$totalShipping = $costMetrics['total_shipping'] ?? 0;
$totalProfit = $costMetrics['total_profit'] ?? 0;
?>

<!-- KEY METRICS -->
<div class="widget-grid">
    <div class="widget-card warning">
        <div class="metric-box">
            <div class="metric-value"><?= $draftCount ?></div>
            <div class="metric-label">DRAFTS PENDING</div>
        </div>
        <small class="text-muted">Awaiting approval</small>
    </div>

    <div class="widget-card info">
        <div class="metric-box">
            <div class="metric-value"><?= $readyCount ?></div>
            <div class="metric-label">READY TO SEND</div>
        </div>
        <small class="text-muted">Awaiting courier</small>
    </div>

    <div class="widget-card success">
        <div class="metric-box">
            <div class="metric-value"><?= $inProgressCount ?></div>
            <div class="metric-label">IN PROGRESS</div>
        </div>
        <small class="text-muted">Currently being packed</small>
    </div>

    <div class="widget-card info">
        <div class="metric-box">
            <div class="metric-value">$<?= number_format($totalShipping, 0) ?></div>
            <div class="metric-label">TOTAL SHIPPING COST</div>
        </div>
        <small class="text-muted">Active transfers</small>
    </div>

    <div class="widget-card success">
        <div class="metric-box">
            <div class="metric-value">$<?= number_format($totalProfit, 0) ?></div>
            <div class="metric-label">PROJECTED PROFIT</div>
        </div>
        <small class="text-muted">All pending transfers</small>
    </div>

    <div class="widget-card info">
        <div class="metric-box">
            <div class="metric-value"><?= $costMetrics['from_outlets'] ?? 0 ?></div>
            <div class="metric-label">ACTIVE ROUTES</div>
        </div>
        <small class="text-muted">Outlets involved</small>
    </div>
</div>

<!-- UNPROFITABLE TRANSFERS ALERT -->
<?php if (!empty($unprofitable)): ?>
    <div class="widget-card urgent" style="margin-bottom: 1.5rem;">
        <div class="widget-title" style="color: #dc3545;">
            <i class="fas fa-exclamation-triangle"></i> ⚠️ Unprofitable Transfers
        </div>
        <div style="padding: 0.75rem; background: #fff5f5; border-radius: 6px; margin-bottom: 1rem;">
            <strong style="color: #dc3545;"><?= count($unprofitable) ?> transfers have margins < $10</strong><br>
            <small>Consider consolidating or canceling these transfers</small>
        </div>
        <div class="widget-content">
            <?php foreach ($unprofitable as $t): ?>
                <div style="padding: 1rem; background: #fffaf0; border-left: 3px solid #ffc107; margin-bottom: 0.75rem; border-radius: 4px;">
                    <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem;">
                        <strong><?= htmlspecialchars($t['transfer_number']) ?></strong>
                        <span style="color: #dc3545; font-weight: 700;">
                            Profit: $<?= number_format($t['estimated_profit'], 2) ?>
                        </span>
                    </div>
                    <small style="color: #666;">
                        <?= htmlspecialchars($t['from_outlet']) ?> → <?= htmlspecialchars($t['to_outlet']) ?>
                        • <?= $t['item_count'] ?> items
                    </small>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<!-- PENDING ACTIONS -->
<div class="widget-card" style="margin-bottom: 1.5rem;">
    <div class="widget-title">
        <i class="fas fa-tasks"></i> Transfers Requiring Action
    </div>

    <?php if (empty($pendingTransfers)): ?>
        <div class="empty-state">
            <i class="fas fa-check-circle"></i>
            <p>All transfers are on track</p>
        </div>
    <?php else: ?>
        <div class="widget-content">
            <?php foreach ($pendingTransfers as $t): ?>
                <div style="padding: 1.25rem; background: #f8f9fa; border-radius: 8px; margin-bottom: 1rem; border-left: 4px solid <?=
                    ($t['status'] === 'draft' ? '#ffc107' : ($t['status'] === 'ready' ? '#0d6efd' : '#198754'))
                ?>;">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 0.75rem;">
                        <div>
                            <strong style="font-size: 1.1rem;"><?= htmlspecialchars($t['transfer_number']) ?></strong>
                            <div style="color: #666; margin-top: 0.25rem;">
                                <?= htmlspecialchars($t['from_outlet']) ?> → <?= htmlspecialchars($t['to_outlet']) ?>
                            </div>
                        </div>
                        <span class="status-badge status-<?= $t['status'] ?>">
                            <?= ucfirst(str_replace('_', ' ', $t['status'])) ?>
                        </span>
                    </div>

                    <div class="stat-row" style="border: none; padding: 0; margin-bottom: 1rem;">
                        <span><?= $t['item_count'] ?> items • <?= $t['total_qty'] ?> units</span>
                        <span style="color: <?= ($t['estimated_profit'] >= 50 ? '#198754' : '#dc3545') ?>; font-weight: 700;">
                            $<?= number_format($t['estimated_profit'], 2) ?> profit
                        </span>
                    </div>

                    <div>
                        <a href="/modules/consignments/view.php?id=<?= $t['id'] ?>"
                           class="quick-action-btn" style="padding: 0.4rem 0.8rem; font-size: 0.8rem;">
                            <i class="fas fa-eye"></i> View Details
                        </a>
                        <?php if ($t['status'] === 'draft'): ?>
                            <a href="/modules/consignments/approve.php?id=<?= $t['id'] ?>"
                               class="quick-action-btn" style="padding: 0.4rem 0.8rem; font-size: 0.8rem; background: #198754;">
                                <i class="fas fa-check"></i> Approve
                            </a>
                        <?php endif; ?>
                        <a href="/modules/consignments/edit.php?id=<?= $t['id'] ?>"
                           class="quick-action-btn" style="padding: 0.4rem 0.8rem; font-size: 0.8rem; background: #6c757d;">
                            <i class="fas fa-edit"></i> Edit
                        </a>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- MANAGEMENT TOOLS -->
<div class="widget-grid">
    <div class="widget-card">
        <div class="widget-title">
            <i class="fas fa-plus-circle"></i> Create Transfer
        </div>
        <a href="/modules/consignments/create.php" class="quick-action-btn" style="width: 100%; text-align: center;">
            <i class="fas fa-plus"></i> New Transfer
        </a>
    </div>

    <div class="widget-card">
        <div class="widget-title">
            <i class="fas fa-chart-bar"></i> View Reports
        </div>
        <a href="/modules/consignments/reports.php" class="quick-action-btn" style="width: 100%; text-align: center;">
            <i class="fas fa-chart-line"></i> Analytics
        </a>
    </div>

    <div class="widget-card">
        <div class="widget-title">
            <i class="fas fa-cog"></i> System Settings
        </div>
        <a href="/modules/consignments/settings.php" class="quick-action-btn" style="width: 100%; text-align: center;">
            <i class="fas fa-sliders-h"></i> Configure
        </a>
    </div>

    <div class="widget-card">
        <div class="widget-title">
            <i class="fas fa-brain"></i> AI Engine Status
        </div>
        <a href="/modules/consignments/ai-status.php" class="quick-action-btn" style="width: 100%; text-align: center;">
            <i class="fas fa-heartbeat"></i> Health Check
        </a>
    </div>

    <div class="widget-card">
        <div class="widget-title">
            <i class="fas fa-exchange-alt"></i> Mass Operations
        </div>
        <a href="/modules/consignments/batch.php" class="quick-action-btn" style="width: 100%; text-align: center;">
            <i class="fas fa-list-check"></i> Batch Actions
        </a>
    </div>

    <div class="widget-card">
        <div class="widget-title">
            <i class="fas fa-download"></i> Export
        </div>
        <a href="/modules/consignments/export.php" class="quick-action-btn" style="width: 100%; text-align: center;">
            <i class="fas fa-file-csv"></i> Download Data
        </a>
    </div>
</div>
