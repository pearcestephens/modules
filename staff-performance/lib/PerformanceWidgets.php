<?php
/**
 * STAFF PERFORMANCE & GAMIFICATION - WIDGET LIBRARY
 *
 * Reusable components for leaderboards, competitions, and stats
 */

namespace StaffPerformance\Widgets;

class PerformanceWidgets {

    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * WIDGET 1: Personal Stats Card
     * Shows individual staff member's performance
     */
    public function renderPersonalStats($staffId) {
        $stats = $this->getStaffStats($staffId);

        ob_start();
        ?>
        <div class="card stats-card">
            <div class="card-header bg-primary text-white">
                <h5 class="mb-0"><i class="fas fa-user-circle"></i> Your Performance</h5>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-md-4">
                        <div class="stat-item">
                            <div class="stat-icon text-warning"><i class="fas fa-star fa-2x"></i></div>
                            <div class="stat-value"><?= $stats['google_reviews'] ?></div>
                            <div class="stat-label">Google Reviews</div>
                            <div class="stat-money text-success">$<?= number_format($stats['review_earnings'], 2) ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-item">
                            <div class="stat-icon text-info"><i class="fas fa-box fa-2x"></i></div>
                            <div class="stat-value"><?= $stats['vape_drops'] ?></div>
                            <div class="stat-label">Vape Drops</div>
                            <div class="stat-money text-success">$<?= number_format($stats['drop_earnings'], 2) ?></div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="stat-item">
                            <div class="stat-icon text-success"><i class="fas fa-trophy fa-2x"></i></div>
                            <div class="stat-value"><?= $stats['total_points'] ?></div>
                            <div class="stat-label">Total Points</div>
                            <div class="stat-rank">Rank: #<?= $stats['rank'] ?></div>
                        </div>
                    </div>
                </div>

                <hr>

                <div class="progress-section">
                    <h6>This Month's Goal Progress</h6>
                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar bg-success"
                             style="width: <?= min(100, ($stats['google_reviews'] / 10) * 100) ?>%">
                            <?= $stats['google_reviews'] ?> / 10 reviews
                        </div>
                    </div>
                    <small class="text-muted">Reach 10 reviews for Gold Tier bonus!</small>
                </div>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * WIDGET 2: Leaderboard Widget
     * Shows top performers
     */
    public function renderLeaderboard($limit = 10) {
        $leaders = $this->getTopPerformers($limit);

        ob_start();
        ?>
        <div class="card leaderboard-card">
            <div class="card-header bg-dark text-white">
                <h5 class="mb-0"><i class="fas fa-trophy"></i> Leaderboard - Top <?= $limit ?></h5>
            </div>
            <div class="card-body p-0">
                <table class="table table-hover mb-0">
                    <thead>
                        <tr>
                            <th width="60">Rank</th>
                            <th>Staff Member</th>
                            <th width="100" class="text-center">Reviews</th>
                            <th width="100" class="text-center">Drops</th>
                            <th width="120" class="text-end">Points</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($leaders as $idx => $leader): ?>
                        <tr class="<?= $idx < 3 ? 'table-warning' : '' ?>">
                            <td class="text-center">
                                <?php if ($idx === 0): ?>
                                    <span class="badge bg-gold">🥇</span>
                                <?php elseif ($idx === 1): ?>
                                    <span class="badge bg-silver">🥈</span>
                                <?php elseif ($idx === 2): ?>
                                    <span class="badge bg-bronze">🥉</span>
                                <?php else: ?>
                                    <span class="text-muted">#<?= $idx + 1 ?></span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong><?= htmlspecialchars($leader['name']) ?></strong>
                                <br><small class="text-muted"><?= htmlspecialchars($leader['store_name']) ?></small>
                            </td>
                            <td class="text-center"><?= $leader['google_reviews'] ?></td>
                            <td class="text-center"><?= $leader['vape_drops'] ?></td>
                            <td class="text-end">
                                <strong class="text-primary"><?= number_format($leader['total_points']) ?></strong>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * WIDGET 3: Active Competition Card
     * Shows current week's challenge
     */
    public function renderActiveCompetition($staffId = null) {
        $competition = $this->getActiveCompetition();

        if (!$competition) {
            return '<div class="alert alert-info">No active competition this week. Check back Monday!</div>';
        }

        $userProgress = $staffId ? $this->getCompetitionProgress($competition['id'], $staffId) : null;

        ob_start();
        ?>
        <div class="card competition-card border-primary">
            <div class="card-header bg-primary text-white">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0"><i class="fas fa-bullseye"></i> This Week's Challenge</h5>
                    <span class="badge bg-light text-dark">
                        <i class="far fa-clock"></i> Ends <?= date('l g:ia', strtotime($competition['end_date'])) ?>
                    </span>
                </div>
            </div>
            <div class="card-body">
                <h4 class="card-title"><?= htmlspecialchars($competition['name']) ?></h4>
                <p class="card-text"><?= htmlspecialchars($competition['description']) ?></p>

                <div class="prize-section bg-light p-3 rounded mb-3">
                    <strong>🎁 Prize:</strong> <?= htmlspecialchars($competition['prize']) ?>
                </div>

                <div class="leader-section mb-3">
                    <strong>🏆 Current Leader:</strong>
                    <?= htmlspecialchars($competition['leader_name']) ?>
                    (<?= $competition['leader_score'] ?> <?= $competition['metric_unit'] ?>)
                </div>

                <?php if ($userProgress): ?>
                <div class="user-progress-section">
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <strong>Your Progress:</strong>
                        <span class="badge bg-primary"><?= $userProgress['score'] ?> <?= $competition['metric_unit'] ?></span>
                    </div>
                    <div class="progress" style="height: 25px;">
                        <div class="progress-bar bg-success"
                             style="width: <?= min(100, ($userProgress['score'] / $competition['leader_score']) * 100) ?>%">
                            <?= $userProgress['score'] ?> / <?= $competition['leader_score'] ?>
                        </div>
                    </div>
                    <small class="text-muted">
                        <?php
                        $behind = $competition['leader_score'] - $userProgress['score'];
                        if ($behind > 0) {
                            echo "You're " . $behind . " " . $competition['metric_unit'] . " behind the leader!";
                        } else {
                            echo "🎉 You're in the lead!";
                        }
                        ?>
                    </small>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php
        return ob_get_clean();
    }

    /**
     * WIDGET 4: Monthly Performance Chart
     * Shows trend over time
     */
    public function renderPerformanceChart($staffId, $months = 3) {
        $data = $this->getMonthlyPerformance($staffId, $months);

        ob_start();
        ?>
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0"><i class="fas fa-chart-line"></i> Performance Trend</h5>
            </div>
            <div class="card-body">
                <canvas id="performanceChart" height="100"></canvas>
            </div>
        </div>

        <script>
        const ctx = document.getElementById('performanceChart').getContext('2d');
        new Chart(ctx, {
            type: 'line',
            data: {
                labels: <?= json_encode(array_column($data, 'month')) ?>,
                datasets: [{
                    label: 'Google Reviews',
                    data: <?= json_encode(array_column($data, 'reviews')) ?>,
                    borderColor: 'rgb(255, 193, 7)',
                    backgroundColor: 'rgba(255, 193, 7, 0.1)',
                    tension: 0.1
                }, {
                    label: 'Vape Drops',
                    data: <?= json_encode(array_column($data, 'drops')) ?>,
                    borderColor: 'rgb(13, 202, 240)',
                    backgroundColor: 'rgba(13, 202, 240, 0.1)',
                    tension: 0.1
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'top',
                    },
                    title: {
                        display: true,
                        text: 'Last 3 Months Performance'
                    }
                }
            }
        });
        </script>
        <?php
        return ob_get_clean();
    }

    // ============================================================================
    // DATA FETCHING METHODS
    // ============================================================================

    private function getStaffStats($staffId) {
        // Get current month stats
        $stmt = $this->db->prepare("
            SELECT
                COUNT(CASE WHEN gr.id IS NOT NULL THEN 1 END) as google_reviews,
                COUNT(CASE WHEN vd.id IS NOT NULL THEN 1 END) as vape_drops,
                (COUNT(CASE WHEN gr.id IS NOT NULL THEN 1 END) * 10) as review_earnings,
                (COUNT(CASE WHEN vd.id IS NOT NULL THEN 1 END) * 6) as drop_earnings,
                (COUNT(CASE WHEN gr.id IS NOT NULL THEN 1 END) * 100 +
                 COUNT(CASE WHEN vd.id IS NOT NULL THEN 1 END) * 50) as total_points
            FROM staff_accounts sa
            LEFT JOIN google_reviews gr ON gr.staff_id = sa.vend_user_id
                AND gr.review_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
                AND gr.verified = 1
            LEFT JOIN vape_drops vd ON vd.staff_id = sa.vend_user_id
                AND vd.completed_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
                AND vd.completed = 1
            WHERE sa.vend_user_id = ?
            GROUP BY sa.vend_user_id
        ");
        $stmt->execute([$staffId]);
        $stats = $stmt->fetch(\PDO::FETCH_ASSOC) ?: [
            'google_reviews' => 0,
            'vape_drops' => 0,
            'review_earnings' => 0,
            'drop_earnings' => 0,
            'total_points' => 0
        ];

        // Get rank
        $rankStmt = $this->db->prepare("
            SELECT COUNT(*) + 1 as rank
            FROM (
                SELECT staff_id,
                       (COUNT(CASE WHEN type = 'review' THEN 1 END) * 100 +
                        COUNT(CASE WHEN type = 'drop' THEN 1 END) * 50) as points
                FROM (
                    SELECT staff_id, 'review' as type FROM google_reviews
                    WHERE review_date >= DATE_FORMAT(NOW(), '%Y-%m-01') AND verified = 1
                    UNION ALL
                    SELECT staff_id, 'drop' as type FROM vape_drops
                    WHERE completed_at >= DATE_FORMAT(NOW(), '%Y-%m-01') AND completed = 1
                ) combined
                GROUP BY staff_id
                HAVING points > ?
            ) rankings
        ");
        $rankStmt->execute([$stats['total_points']]);
        $stats['rank'] = $rankStmt->fetchColumn() ?: 1;

        return $stats;
    }

    private function getTopPerformers($limit) {
        $stmt = $this->db->prepare("
            SELECT
                sa.employee_name as name,
                o.outlet_name as store_name,
                COUNT(CASE WHEN gr.id IS NOT NULL THEN 1 END) as google_reviews,
                COUNT(CASE WHEN vd.id IS NOT NULL THEN 1 END) as vape_drops,
                (COUNT(CASE WHEN gr.id IS NOT NULL THEN 1 END) * 100 +
                 COUNT(CASE WHEN vd.id IS NOT NULL THEN 1 END) * 50) as total_points
            FROM staff_accounts sa
            LEFT JOIN google_reviews gr ON gr.staff_id = sa.vend_user_id
                AND gr.review_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
                AND gr.verified = 1
            LEFT JOIN vape_drops vd ON vd.staff_id = sa.vend_user_id
                AND vd.completed_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
                AND vd.completed = 1
            LEFT JOIN outlets o ON o.outlet_id = sa.outlet_id
            GROUP BY sa.vend_user_id
            ORDER BY total_points DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    private function getActiveCompetition() {
        $stmt = $this->db->query("
            SELECT c.*,
                   s.employee_name as leader_name,
                   cp.score as leader_score
            FROM competitions c
            LEFT JOIN competition_participants cp ON cp.competition_id = c.id
                AND cp.rank = 1
            LEFT JOIN staff_accounts s ON s.vend_user_id = cp.staff_id
            WHERE c.status = 'active'
            AND c.start_date <= NOW()
            AND c.end_date >= NOW()
            LIMIT 1
        ");
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    private function getCompetitionProgress($competitionId, $staffId) {
        $stmt = $this->db->prepare("
            SELECT score, rank
            FROM competition_participants
            WHERE competition_id = ? AND staff_id = ?
        ");
        $stmt->execute([$competitionId, $staffId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    private function getMonthlyPerformance($staffId, $months) {
        $stmt = $this->db->prepare("
            SELECT
                DATE_FORMAT(month_date, '%b %Y') as month,
                COUNT(CASE WHEN type = 'review' THEN 1 END) as reviews,
                COUNT(CASE WHEN type = 'drop' THEN 1 END) as drops
            FROM (
                SELECT DATE_FORMAT(review_date, '%Y-%m-01') as month_date, 'review' as type
                FROM google_reviews
                WHERE staff_id = ? AND verified = 1
                  AND review_date >= DATE_SUB(NOW(), INTERVAL ? MONTH)
                UNION ALL
                SELECT DATE_FORMAT(completed_at, '%Y-%m-01') as month_date, 'drop' as type
                FROM vape_drops
                WHERE staff_id = ? AND completed = 1
                  AND completed_at >= DATE_SUB(NOW(), INTERVAL ? MONTH)
            ) combined
            GROUP BY month_date
            ORDER BY month_date ASC
        ");
        $stmt->execute([$staffId, $months, $staffId, $months]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
