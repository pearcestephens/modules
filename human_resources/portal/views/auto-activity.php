<?php
/**
 * Auto-Pilot Activity View
 * Shows what the AI has been auto-approving
 */

$recentActivity = $dashboard->getRecentActivity(50);
?>

<div class="auto-activity-view">
    <div class="view-header">
        <h4>🤖 AI Auto-Pilot Activity</h4>
        <p class="text-muted">Items automatically processed by AI (last 50)</p>
    </div>

    <?php if (empty($recentActivity)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            No auto-pilot activity yet. Enable auto-pilot and AI will start processing items automatically.
        </div>
    <?php else: ?>
        <div class="activity-table-wrapper">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th>Time</th>
                        <th>Staff</th>
                        <th>Type</th>
                        <th>AI Decision</th>
                        <th>Confidence</th>
                        <th>Human Override</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentActivity as $item): ?>
                        <tr class="activity-row">
                            <td>
                                <small><?php echo date('d M, H:i', strtotime($item['created_at'])); ?></small>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($item['staff_name'] ?? 'Unknown'); ?></strong>
                            </td>
                            <td>
                                <span class="badge bg-secondary">
                                    <?php echo htmlspecialchars(ucfirst($item['item_type'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($item['decision'] === 'auto_approve'): ?>
                                    <span class="badge bg-success">✓ Auto-Approved</span>
                                <?php elseif ($item['decision'] === 'manual_review'): ?>
                                    <span class="badge bg-warning">⚠ Needs Review</span>
                                <?php elseif ($item['decision'] === 'escalate'): ?>
                                    <span class="badge bg-danger">🔺 Escalated</span>
                                <?php else: ?>
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($item['decision']); ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <div class="confidence-mini-meter">
                                    <div class="confidence-fill" style="width: <?php echo ($item['confidence_score'] * 100); ?>%;
                                         background-color: <?php
                                            if ($item['confidence_score'] >= 0.8) echo '#28a745';
                                            elseif ($item['confidence_score'] >= 0.6) echo '#ffc107';
                                            else echo '#dc3545';
                                         ?>;">
                                    </div>
                                </div>
                                <small><?php echo round($item['confidence_score'] * 100); ?>%</small>
                            </td>
                            <td>
                                <?php if ($item['human_action']): ?>
                                    <?php if ($item['human_action'] === 'approved'): ?>
                                        <span class="badge bg-success">✓ Confirmed</span>
                                    <?php elseif ($item['human_action'] === 'denied'): ?>
                                        <span class="badge bg-danger">✗ Overridden</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($item['processed_at']): ?>
                                    <span class="badge bg-info">✓ Processed</span>
                                <?php else: ?>
                                    <span class="badge bg-light text-dark">Pending</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="activity-stats mt-4">
            <div class="row">
                <div class="col-md-3">
                    <div class="stat-mini-card">
                        <div class="stat-label">Total Processed</div>
                        <div class="stat-value"><?php echo count($recentActivity); ?></div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-mini-card">
                        <div class="stat-label">Auto-Approved</div>
                        <div class="stat-value text-success">
                            <?php echo count(array_filter($recentActivity, fn($i) => $i['decision'] === 'auto_approve')); ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-mini-card">
                        <div class="stat-label">Human Overrides</div>
                        <div class="stat-value text-warning">
                            <?php echo count(array_filter($recentActivity, fn($i) => !empty($i['human_action']))); ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="stat-mini-card">
                        <div class="stat-label">Avg Confidence</div>
                        <div class="stat-value">
                            <?php
                            $avgConfidence = array_sum(array_column($recentActivity, 'confidence_score')) / count($recentActivity);
                            echo round($avgConfidence * 100, 1) . '%';
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<style>
.confidence-mini-meter {
    width: 60px;
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
    display: inline-block;
    vertical-align: middle;
}

.confidence-fill {
    height: 100%;
    transition: width 0.3s ease;
}

.activity-row:hover {
    background-color: #f8f9fa;
}

.stat-mini-card {
    padding: 15px;
    background: #f8f9fa;
    border-radius: 8px;
    text-align: center;
}

.stat-mini-card .stat-label {
    font-size: 12px;
    color: #6c757d;
    margin-bottom: 5px;
}

.stat-mini-card .stat-value {
    font-size: 24px;
    font-weight: bold;
}
</style>
