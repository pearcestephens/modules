<?php
/**
 * SYSTEM HEALTH DASHBOARD WIDGET
 * Admin-only: System metrics, AI engine status, health checks
 */

try {
    // System health status
    $health = [
        'database' => 'unknown',
        'pdo_available' => false,
        'transfer_engine' => 'unknown',
        'ai_engine' => 'unknown',
        'profitability_checker' => 'unknown'
    ];

    try {
        // Test database
        $test = $pdo->query("SELECT 1");
        $health['database'] = 'ok';
        $health['pdo_available'] = true;
    } catch (Exception $e) {
        $health['database'] = 'error';
    }

    // Get last engine run
    $stmt = $pdo->prepare("
        SELECT
            MAX(created_at) as last_run,
            SUM(transfers_created) as total_transfers,
            AVG(duration_seconds) as avg_duration
        FROM transfer_engine_logs
        WHERE DATE(created_at) = CURDATE()
    ");
    $stmt->execute();
    $engineLog = $stmt->fetch(\PDO::FETCH_ASSOC) ?? ['last_run' => null];
    $health['transfer_engine'] = $engineLog['last_run'] ? 'ok' : 'pending';

    // Get current profitability stats
    $stmt2 = $pdo->prepare("
        SELECT
            COUNT(*) as total_active,
            SUM(estimated_profit) as total_profit,
            SUM(CASE WHEN estimated_profit < 10 THEN 1 ELSE 0 END) as unprofitable_count
        FROM consignments
        WHERE status IN ('draft', 'ready', 'in_progress')
    ");
    $stmt2->execute();
    $profitStats = $stmt2->fetch(\PDO::FETCH_ASSOC) ?? ['total_active' => 0, 'total_profit' => 0, 'unprofitable_count' => 0];

    // Get inventory health
    $stmt3 = $pdo->prepare("
        SELECT
            SUM(CASE WHEN oi.quantity <= COALESCE(p.reorder_point, 20) THEN 1 ELSE 0 END) as low_stock_count,
            SUM(CASE WHEN oi.quantity > COALESCE(p.overstock_point, 100) THEN 1 ELSE 0 END) as overstock_count,
            COUNT(DISTINCT oi.product_id) as unique_products,
            SUM(oi.quantity) as total_units
        FROM outlet_inventory oi
        LEFT JOIN products p ON p.id = oi.product_id
        WHERE oi.quantity > 0
    ");
    $stmt3->execute();
    $inventoryHealth = $stmt3->fetch(\PDO::FETCH_ASSOC) ?? ['low_stock_count' => 0, 'overstock_count' => 0];

    // Get service file checks
    $services = [
        'ai-intelligence-engine.php' => file_exists(__DIR__ . '/../../modules/consignments/api/ai-intelligence-engine.php'),
        'transfer-engine.php' => file_exists(__DIR__ . '/../../modules/consignments/api/transfer-engine.php'),
        'profitability-checker.php' => file_exists(__DIR__ . '/../../modules/consignments/api/profitability-checker.php'),
    ];

} catch (Exception $e) {
    error_log("System Health Error: " . $e->getMessage());
}
?>

<!-- SYSTEM STATUS -->
<div class="widget-grid">
    <div class="widget-card <?= ($health['database'] === 'ok' ? 'success' : 'urgent') ?>">
        <div class="widget-title">
            <i class="fas fa-database"></i> Database
        </div>
        <div style="text-align: center;">
            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">
                <?= ($health['database'] === 'ok' ? '✅' : '❌') ?>
            </div>
            <div style="font-weight: 700; color: <?= ($health['database'] === 'ok' ? '#198754' : '#dc3545') ?>">
                <?= ucfirst($health['database']) ?>
            </div>
        </div>
    </div>

    <div class="widget-card <?= ($health['transfer_engine'] === 'ok' ? 'success' : 'warning') ?>">
        <div class="widget-title">
            <i class="fas fa-cogs"></i> Transfer Engine
        </div>
        <div style="text-align: center;">
            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">
                <?= ($health['transfer_engine'] === 'ok' ? '✅' : '⏳') ?>
            </div>
            <div style="font-weight: 700;">
                <?= ucfirst($health['transfer_engine']) ?>
            </div>
            <?php if ($engineLog['last_run']): ?>
                <small style="color: #888;">Last: <?= date('H:i', strtotime($engineLog['last_run'])) ?></small>
            <?php endif; ?>
        </div>
    </div>

    <div class="widget-card info">
        <div class="widget-title">
            <i class="fas fa-brain"></i> AI Intelligence
        </div>
        <div style="text-align: center;">
            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">
                🧠
            </div>
            <div style="font-weight: 700; color: #0d6efd;">
                Ready
            </div>
            <small style="color: #888;">On-demand scheduling</small>
        </div>
    </div>

    <div class="widget-card info">
        <div class="widget-title">
            <i class="fas fa-check-circle"></i> Profitability Checker
        </div>
        <div style="text-align: center;">
            <div style="font-size: 2.5rem; margin-bottom: 0.5rem;">
                ✅
            </div>
            <div style="font-weight: 700; color: #198754;">
                Active
            </div>
            <small style="color: #888;">Real-time validation</small>
        </div>
    </div>

    <div class="widget-card warning">
        <div class="metric-box">
            <div class="metric-value"><?= $profitStats['unprofitable_count'] ?></div>
            <div class="metric-label">UNPROFITABLE</div>
        </div>
        <small class="text-muted">Transfers with < $10 margin</small>
    </div>

    <div class="widget-card warning">
        <div class="metric-box">
            <div class="metric-value"><?= $inventoryHealth['low_stock_count'] ?? 0 ?></div>
            <div class="metric-label">LOW STOCK ITEMS</div>
        </div>
        <small class="text-muted">Below reorder point</small>
    </div>
</div>

<!-- SERVICE FILES -->
<div class="widget-card" style="margin-bottom: 1.5rem;">
    <div class="widget-title">
        <i class="fas fa-file-code"></i> Service Files
    </div>
    <div class="widget-content">
        <?php foreach ($services as $filename => $exists): ?>
            <div style="padding: 0.75rem; display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #eee;">
                <span>
                    <strong><?= htmlspecialchars($filename) ?></strong>
                    <br>
                    <small style="color: #888;">/api/<?= $filename ?></small>
                </span>
                <span style="font-size: 1.3rem;">
                    <?= ($exists ? '✅' : '❌') ?>
                </span>
            </div>
        <?php endforeach; ?>
    </div>
</div>

<!-- ACTIVE TRANSFERS METRICS -->
<div class="widget-card" style="margin-bottom: 1.5rem;">
    <div class="widget-title">
        <i class="fas fa-chart-pie"></i> Active Transfers
    </div>
    <div class="metric-box">
        <div class="metric-value"><?= $profitStats['total_active'] ?></div>
        <div class="metric-label">ACTIVE TRANSFERS</div>
    </div>
    <div style="padding: 1rem; background: #f8f9fa; border-radius: 8px; margin-top: 1rem;">
        <strong style="display: block; margin-bottom: 0.75rem;">💰 Profit Potential</strong>
        <div style="font-size: 1.8rem; font-weight: 700; color: <?= ($profitStats['total_profit'] > 0 ? '#198754' : '#dc3545') ?>;">
            $<?= number_format($profitStats['total_profit'] ?? 0, 2) ?>
        </div>
        <small style="color: #888;">Across all active transfers</small>
    </div>
</div>

<!-- INVENTORY HEALTH -->
<div class="widget-card" style="margin-bottom: 1.5rem;">
    <div class="widget-title">
        <i class="fas fa-warehouse"></i> Inventory Status
    </div>
    <div class="stat-row">
        <span class="stat-label">Unique Products</span>
        <span class="stat-value"><?= $inventoryHealth['unique_products'] ?? 0 ?></span>
    </div>
    <div class="stat-row">
        <span class="stat-label">Total Units</span>
        <span class="stat-value"><?= number_format($inventoryHealth['total_units'] ?? 0) ?></span>
    </div>
    <div class="stat-row">
        <span class="stat-label">Low Stock Items</span>
        <span class="stat-value" style="color: #dc3545;"><?= $inventoryHealth['low_stock_count'] ?? 0 ?></span>
    </div>
    <div class="stat-row">
        <span class="stat-label">Overstock Items</span>
        <span class="stat-value" style="color: #ffc107;"><?= $inventoryHealth['overstock_count'] ?? 0 ?></span>
    </div>
</div>

<!-- QUICK ACTIONS -->
<div class="widget-grid">
    <div class="widget-card">
        <div class="widget-title">
            <i class="fas fa-sync"></i> Run Engine Now
        </div>
        <button class="quick-action-btn" style="width: 100%; text-align: center;">
            <i class="fas fa-play"></i> Execute Transfer Engine
        </button>
        <small style="display: block; margin-top: 0.5rem; color: #888;">
            Run daily optimization job manually
        </small>
    </div>

    <div class="widget-card">
        <div class="widget-title">
            <i class="fas fa-magic"></i> Get AI Recommendations
        </div>
        <button class="quick-action-btn" style="width: 100%; text-align: center;">
            <i class="fas fa-brain"></i> Generate
        </button>
        <small style="display: block; margin-top: 0.5rem; color: #888;">
            Get intelligent transfer suggestions
        </small>
    </div>

    <div class="widget-card">
        <div class="widget-title">
            <i class="fas fa-download"></i> System Logs
        </div>
        <button class="quick-action-btn" style="width: 100%; text-align: center;">
            <i class="fas fa-file-alt"></i> Download
        </button>
        <small style="display: block; margin-top: 0.5rem; color: #888;">
            Export error & engine logs
        </small>
    </div>

    <div class="widget-card">
        <div class="widget-title">
            <i class="fas fa-wrench"></i> Maintenance
        </div>
        <button class="quick-action-btn" style="width: 100%; text-align: center;">
            <i class="fas fa-broom"></i> Clear Cache
        </button>
        <small style="display: block; margin-top: 0.5rem; color: #888;">
            Flush recommendation cache
        </small>
    </div>
</div>

<!-- ENGINE LOGS -->
<?php if ($engineLog['last_run']): ?>
    <div class="widget-card" style="margin-top: 1.5rem;">
        <div class="widget-title">
            <i class="fas fa-history"></i> Last Engine Run
        </div>
        <div class="stat-row">
            <span class="stat-label">Time</span>
            <span class="stat-value"><?= date('M d, H:i:s', strtotime($engineLog['last_run'])) ?></span>
        </div>
        <div class="stat-row">
            <span class="stat-label">Transfers Created</span>
            <span class="stat-value"><?= $engineLog['total_transfers'] ?? 0 ?></span>
        </div>
        <div class="stat-row">
            <span class="stat-label">Duration</span>
            <span class="stat-value"><?= number_format($engineLog['avg_duration'] ?? 0, 2) ?>s</span>
        </div>
    </div>
<?php endif; ?>
