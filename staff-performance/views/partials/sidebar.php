

<?php
/**
 * Staff Performance Module - Sidebar Navigation
 *
 * @package CIS\Modules\StaffPerformance
 * @version 1.0.0
 */

$currentPage = $_GET['page'] ?? 'dashboard';
?>

<div class="sidebar-nav">
    <div class="list-group list-group-flush">

        <!-- Dashboard -->
        <a href="<?php echo STAFF_PERFORMANCE_MODULE_PATH; ?>"
           class="list-group-item list-group-item-action <?php echo $currentPage === 'dashboard' ? 'active' : ''; ?>">
            <i class="fas fa-chart-line me-2"></i>
            Dashboard
        </a>

        <!-- Competitions -->
        <a href="<?php echo STAFF_PERFORMANCE_MODULE_PATH; ?>?page=competitions"
           class="list-group-item list-group-item-action <?php echo $currentPage === 'competitions' ? 'active' : ''; ?>">
            <i class="fas fa-trophy me-2"></i>
            Competitions
            <?php
            // Show count of active competitions
            $activeCount = 0;
            try {
                $stmt = $db->prepare("SELECT COUNT(*) FROM competitions WHERE status = 'active' AND start_date <= NOW() AND end_date >= NOW()");
                $stmt->execute();
                $activeCount = $stmt->fetchColumn();
            } catch (Exception $e) {
                // Silently fail
            }
            if ($activeCount > 0):
            ?>
                <span class="badge bg-danger float-end"><?php echo $activeCount; ?></span>
            <?php endif; ?>
        </a>

        <!-- Leaderboard -->
        <a href="<?php echo STAFF_PERFORMANCE_MODULE_PATH; ?>?page=leaderboard"
           class="list-group-item list-group-item-action <?php echo $currentPage === 'leaderboard' ? 'active' : ''; ?>">
            <i class="fas fa-list-ol me-2"></i>
            Leaderboard
        </a>

        <!-- Achievements -->
        <a href="<?php echo STAFF_PERFORMANCE_MODULE_PATH; ?>?page=achievements"
           class="list-group-item list-group-item-action <?php echo $currentPage === 'achievements' ? 'active' : ''; ?>">
            <i class="fas fa-award me-2"></i>
            Achievements
            <?php
            // Show count of unlocked achievements
            $achievementCount = 0;
            try {
                $stmt = $db->prepare("SELECT COUNT(*) FROM staff_achievements WHERE staff_id = ? AND unlocked_at IS NOT NULL");
                $stmt->execute([$current_user_id]);
                $achievementCount = $stmt->fetchColumn();
            } catch (Exception $e) {
                // Silently fail
            }
            if ($achievementCount > 0):
            ?>
                <span class="badge bg-success float-end"><?php echo $achievementCount; ?></span>
            <?php endif; ?>
        </a>

        <!-- History -->
        <a href="<?php echo STAFF_PERFORMANCE_MODULE_PATH; ?>?page=history"
           class="list-group-item list-group-item-action <?php echo $currentPage === 'history' ? 'active' : ''; ?>">
            <i class="fas fa-history me-2"></i>
            History
        </a>

    </div>

    <!-- Performance Summary Box -->
    <div class="card mt-3">
        <div class="card-body p-3">
            <h6 class="card-subtitle mb-2 text-muted">
                <i class="fas fa-chart-pie me-1"></i> This Month
            </h6>
            <?php
            try {
                $stmt = $db->prepare("
                    SELECT
                        COALESCE(SUM(CASE WHEN gr.staff_id = ? THEN 1 ELSE 0 END), 0) as reviews,
                        COALESCE(SUM(CASE WHEN vd.staff_id = ? THEN 1 ELSE 0 END), 0) as drops,
                        COALESCE(SUM(CASE WHEN gr.staff_id = ? THEN 10.00 ELSE 0 END), 0) +
                        COALESCE(SUM(CASE WHEN vd.staff_id = ? THEN 6.00 ELSE 0 END), 0) as earnings
                    FROM (SELECT ? as staff_id) s
                    LEFT JOIN google_reviews gr ON gr.staff_id = s.staff_id
                        AND gr.review_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
                    LEFT JOIN vape_drops vd ON vd.staff_id = s.staff_id
                        AND vd.drop_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
                ");
                $stmt->execute([$current_user_id, $current_user_id, $current_user_id, $current_user_id, $current_user_id]);
                $monthStats = $stmt->fetch(PDO::FETCH_ASSOC);
            } catch (Exception $e) {
                $monthStats = ['reviews' => 0, 'drops' => 0, 'earnings' => 0];
            }
            ?>
            <div class="small">
                <div class="d-flex justify-content-between mb-1">
                    <span><i class="fas fa-star text-warning"></i> Reviews:</span>
                    <strong><?php echo $monthStats['reviews']; ?></strong>
                </div>
                <div class="d-flex justify-content-between mb-1">
                    <span><i class="fas fa-box text-info"></i> Drops:</span>
                    <strong><?php echo $monthStats['drops']; ?></strong>
                </div>
                <div class="d-flex justify-content-between">
                    <span><i class="fas fa-dollar-sign text-success"></i> Earned:</span>
                    <strong>$<?php echo number_format($monthStats['earnings'], 2); ?></strong>
                </div>
            </div>
        </div>
    </div>

    <!-- Help Box -->
    <div class="card mt-3 bg-light">
        <div class="card-body p-3">
            <h6 class="card-subtitle mb-2">
                <i class="fas fa-question-circle me-1"></i> Need Help?
            </h6>
            <p class="small mb-2">
                Learn how to earn more bonuses and climb the leaderboard!
            </p>
            <a href="#" class="btn btn-sm btn-outline-primary w-100" data-bs-toggle="modal" data-bs-target="#helpModal">
                <i class="fas fa-book me-1"></i> View Guide
            </a>
        </div>
    </div>

</div>

<!-- Help Modal -->
<div class="modal fade" id="helpModal" tabindex="-1" aria-labelledby="helpModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="helpModalLabel">
                    <i class="fas fa-graduation-cap me-2"></i>
                    Performance System Guide
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">

                <h6 class="fw-bold"><i class="fas fa-star text-warning"></i> Google Reviews</h6>
                <p>Get customers to leave Google Reviews mentioning your name:</p>
                <ul>
                    <li><strong>$10.00 bonus</strong> per review mentioning your name</li>
                    <li><strong>100 points</strong> toward leaderboard ranking</li>
                    <li>Only reviews with 4-5 stars count</li>
                </ul>

                <hr>

                <h6 class="fw-bold"><i class="fas fa-box text-info"></i> Vape Drops</h6>
                <p>Complete vape drop deliveries:</p>
                <ul>
                    <li><strong>$6.00 bonus</strong> per completed drop</li>
                    <li><strong>50 points</strong> toward leaderboard ranking</li>
                    <li>Mark drops as completed in the system</li>
                </ul>

                <hr>

                <h6 class="fw-bold"><i class="fas fa-trophy text-warning"></i> Competitions</h6>
                <p>Weekly and monthly challenges with cash prizes:</p>
                <ul>
                    <li>🥇 <strong>1st Place:</strong> Varies by competition</li>
                    <li>🥈 <strong>2nd Place:</strong> Varies by competition</li>
                    <li>🥉 <strong>3rd Place:</strong> Varies by competition</li>
                </ul>

                <hr>

                <h6 class="fw-bold"><i class="fas fa-award text-success"></i> Achievements</h6>
                <p>Unlock badges for milestones:</p>
                <ul>
                    <li><strong>First Review:</strong> Your first Google Review mention</li>
                    <li><strong>Review Champion:</strong> 50+ reviews in a month</li>
                    <li><strong>Competition Winner:</strong> Win any competition</li>
                    <li><strong>Legend:</strong> #1 rank for 3+ consecutive months</li>
                </ul>

            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<style>
.sidebar-nav .list-group-item {
    border-left: 3px solid transparent;
    transition: all 0.2s;
}

.sidebar-nav .list-group-item:hover {
    background-color: #f8f9fa;
    border-left-color: #667eea;
}

.sidebar-nav .list-group-item.active {
    background-color: #667eea;
    border-color: #667eea;
    color: white;
}

.sidebar-nav .list-group-item.active i {
    color: white;
}
</style>
