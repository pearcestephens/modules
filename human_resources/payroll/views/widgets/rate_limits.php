<?php
/**
 * Rate Limit Dashboard Widget
 *
 * Shows rate limit metrics for Xero and Deputy APIs
 */

// Get database connection
function getRateLimitStats(PDO $pdo): array {
    $sql = "
        SELECT
            service,
            COUNT(*) as total_calls,
            AVG(response_time_ms) as avg_response_time,
            SUM(CASE WHEN status_code = 429 THEN 1 ELSE 0 END) as rate_limit_hits,
            MAX(rate_limit_remaining) as current_remaining,
            MAX(logged_at) as last_call
        FROM payroll_rate_limits
        WHERE logged_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        GROUP BY service
        ORDER BY service
    ";

    $stmt = $pdo->query($sql);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

$stats = getRateLimitStats($pdo);
?>

<div class="card mb-4">
    <div class="card-header bg-info text-white">
        <h5 class="mb-0"><i class="fas fa-tachometer-alt"></i> API Rate Limits (Last 7 Days)</h5>
    </div>
    <div class="card-body">
        <?php if (empty($stats)): ?>
            <p class="text-muted text-center py-3">No API calls logged yet</p>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover">
                    <thead>
                        <tr>
                            <th>Service</th>
                            <th>Total Calls</th>
                            <th>Avg Response</th>
                            <th>429 Hits</th>
                            <th>Remaining</th>
                            <th>Last Call</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($stats as $stat): ?>
                            <?php
                                $hitRate = $stat['total_calls'] > 0
                                    ? ($stat['rate_limit_hits'] / $stat['total_calls']) * 100
                                    : 0;
                                $statusClass = $hitRate > 10 ? 'danger' : ($hitRate > 5 ? 'warning' : 'success');
                            ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars(ucfirst($stat['service'])) ?></strong>
                                </td>
                                <td><?= number_format($stat['total_calls']) ?></td>
                                <td><?= number_format($stat['avg_response_time'], 0) ?>ms</td>
                                <td>
                                    <span class="badge badge-<?= $statusClass ?>">
                                        <?= $stat['rate_limit_hits'] ?>
                                        (<?= number_format($hitRate, 1) ?>%)
                                    </span>
                                </td>
                                <td>
                                    <?php if ($stat['current_remaining'] !== null): ?>
                                        <span class="badge badge-secondary">
                                            <?= $stat['current_remaining'] ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted">N/A</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <small class="text-muted">
                                        <?= date('M j, g:ia', strtotime($stat['last_call'])) ?>
                                    </small>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-3">
                <a href="?view=rate-limits" class="btn btn-sm btn-outline-info">
                    <i class="fas fa-chart-line"></i> Detailed Analytics
                </a>
            </div>
        <?php endif; ?>
    </div>
</div>
