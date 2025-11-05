<!--
  Gamification Completion Modal
  Shows after completing a transfer receive with stats, achievements, and rank
-->
<div id="gamificationModal" class="gamification-modal" style="display: none;">
    <div class="gamification-modal-content">
        <div class="gamification-header">
            <div class="celebration-icon">
                <i class="fas fa-trophy"></i>
            </div>
            <h2>Transfer Completed!</h2>
            <p class="subtitle">Great work! Here's your performance summary</p>
        </div>

        <div class="stats-showcase">
            <div class="stat-card-large">
                <div class="stat-icon"><i class="fas fa-clock"></i></div>
                <div class="stat-value" id="completionTime">2:34</div>
                <div class="stat-label">Completion Time</div>
                <div class="stat-comparison positive" id="timeComparison">
                    <i class="fas fa-arrow-up"></i> 23% faster than average
                </div>
            </div>

            <div class="stat-card-large">
                <div class="stat-icon"><i class="fas fa-barcode"></i></div>
                <div class="stat-value" id="scansPerformed">47</div>
                <div class="stat-label">Items Scanned</div>
                <div class="stat-comparison positive" id="accuracyRate">
                    <i class="fas fa-check-circle"></i> 100% accuracy
                </div>
            </div>

            <div class="stat-card-large">
                <div class="stat-icon"><i class="fas fa-camera"></i></div>
                <div class="stat-value" id="photosUploaded">12</div>
                <div class="stat-label">Photos Uploaded</div>
                <div class="stat-comparison neutral">
                    <i class="fas fa-info-circle"></i> All items documented
                </div>
            </div>
        </div>

        <div class="achievements-earned" id="achievementsEarned">
            <h3><i class="fas fa-star me-2"></i>Achievements Earned</h3>
            <div class="achievement-grid">
                <div class="achievement-card new">
                    <div class="achievement-badge">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <div class="achievement-info">
                        <div class="achievement-name">Speed Demon</div>
                        <div class="achievement-desc">Completed transfer in under 3 minutes</div>
                    </div>
                    <div class="achievement-points">+50</div>
                </div>

                <div class="achievement-card new">
                    <div class="achievement-badge">
                        <i class="fas fa-camera"></i>
                    </div>
                    <div class="achievement-info">
                        <div class="achievement-name">Photo Pro</div>
                        <div class="achievement-desc">Uploaded photos for all items</div>
                    </div>
                    <div class="achievement-points">+25</div>
                </div>

                <div class="achievement-card">
                    <div class="achievement-badge locked">
                        <i class="fas fa-lock"></i>
                    </div>
                    <div class="achievement-info">
                        <div class="achievement-name">Perfect Week</div>
                        <div class="achievement-desc">Complete 5 transfers in one week</div>
                        <div class="achievement-progress">
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: 60%;"></div>
                            </div>
                            <span>3/5</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="ranking-section">
            <h3><i class="fas fa-trophy me-2"></i>Your Ranking</h3>
            <div class="ranking-card">
                <div class="rank-position">
                    <div class="rank-number">#3</div>
                    <div class="rank-label">This Week</div>
                </div>
                <div class="rank-stats">
                    <div class="rank-stat">
                        <div class="rank-stat-value">847</div>
                        <div class="rank-stat-label">Total Points</div>
                    </div>
                    <div class="rank-stat">
                        <div class="rank-stat-value">12</div>
                        <div class="rank-stat-label">Transfers</div>
                    </div>
                    <div class="rank-stat">
                        <div class="rank-stat-value">98.5%</div>
                        <div class="rank-stat-label">Accuracy</div>
                    </div>
                </div>
                <div class="rank-change positive">
                    <i class="fas fa-arrow-up"></i> Moved up 2 places!
                </div>
            </div>
        </div>

        <div class="improvement-tips">
            <h3><i class="fas fa-lightbulb me-2"></i>Pro Tips</h3>
            <div class="tips-grid">
                <div class="tip-card">
                    <div class="tip-icon"><i class="fas fa-keyboard"></i></div>
                    <div class="tip-text">Use keyboard shortcuts for faster scanning</div>
                </div>
                <div class="tip-card">
                    <div class="tip-icon"><i class="fas fa-camera"></i></div>
                    <div class="tip-text">Batch photo uploads save time</div>
                </div>
                <div class="tip-card">
                    <div class="tip-icon"><i class="fas fa-barcode"></i></div>
                    <div class="tip-text">Scan continuously without clicking</div>
                </div>
            </div>
        </div>

        <div class="modal-actions">
            <button class="btn btn-secondary" onclick="viewFullLeaderboard()">
                <i class="fas fa-list me-2"></i>View Full Leaderboard
            </button>
            <button class="btn btn-primary" onclick="closeGamificationModal()">
                <i class="fas fa-check me-2"></i>Continue
            </button>
        </div>
    </div>
</div>

<style>
.gamification-modal {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.85);
    z-index: 9999;
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
    animation: fadeIn 0.3s ease;
}

@keyframes fadeIn {
    from { opacity: 0; }
    to { opacity: 1; }
}

.gamification-modal-content {
    background: white;
    border-radius: 16px;
    max-width: 900px;
    width: 100%;
    max-height: 90vh;
    overflow-y: auto;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
    animation: slideUp 0.4s ease;
}

@keyframes slideUp {
    from {
        transform: translateY(50px);
        opacity: 0;
    }
    to {
        transform: translateY(0);
        opacity: 1;
    }
}

.gamification-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 40px;
    text-align: center;
    border-radius: 16px 16px 0 0;
}

.celebration-icon {
    font-size: 64px;
    margin-bottom: 16px;
    animation: bounce 0.6s ease infinite alternate;
}

@keyframes bounce {
    from { transform: translateY(0); }
    to { transform: translateY(-10px); }
}

.gamification-header h2 {
    font-size: 32px;
    font-weight: 700;
    margin: 0 0 8px 0;
}

.gamification-header .subtitle {
    font-size: 16px;
    opacity: 0.95;
    margin: 0;
}

.stats-showcase {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 20px;
    padding: 32px;
    background: #f8f9fa;
}

.stat-card-large {
    background: white;
    border-radius: 12px;
    padding: 24px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.stat-card-large .stat-icon {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    color: white;
    margin: 0 auto 16px auto;
}

.stat-card-large .stat-value {
    font-size: 36px;
    font-weight: 700;
    color: #333;
    margin-bottom: 8px;
}

.stat-card-large .stat-label {
    font-size: 14px;
    color: #6c757d;
    font-weight: 600;
    margin-bottom: 12px;
}

.stat-comparison {
    font-size: 13px;
    font-weight: 600;
    padding: 6px 12px;
    border-radius: 6px;
    display: inline-block;
}

.stat-comparison.positive {
    background: #d4edda;
    color: #155724;
}

.stat-comparison.neutral {
    background: #cce5ff;
    color: #004085;
}

.achievements-earned {
    padding: 32px;
}

.achievements-earned h3 {
    font-size: 20px;
    font-weight: 700;
    margin: 0 0 20px 0;
    color: #333;
}

.achievement-grid {
    display: grid;
    gap: 16px;
}

.achievement-card {
    background: white;
    border: 2px solid #dee2e6;
    border-radius: 12px;
    padding: 20px;
    display: flex;
    align-items: center;
    gap: 16px;
    transition: all 0.3s;
}

.achievement-card.new {
    border-color: #ffc107;
    background: linear-gradient(135deg, rgba(255, 193, 7, 0.1) 0%, rgba(255, 193, 7, 0.05) 100%);
    animation: pulseGlow 2s ease infinite;
}

@keyframes pulseGlow {
    0%, 100% { box-shadow: 0 0 0 rgba(255, 193, 7, 0.4); }
    50% { box-shadow: 0 0 20px rgba(255, 193, 7, 0.6); }
}

.achievement-badge {
    width: 60px;
    height: 60px;
    background: linear-gradient(135deg, #ffc107 0%, #ff9800 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 28px;
    color: white;
    flex-shrink: 0;
}

.achievement-badge.locked {
    background: #e9ecef;
    color: #6c757d;
}

.achievement-info {
    flex: 1;
}

.achievement-name {
    font-size: 16px;
    font-weight: 700;
    color: #333;
    margin-bottom: 4px;
}

.achievement-desc {
    font-size: 13px;
    color: #6c757d;
}

.achievement-progress {
    margin-top: 8px;
    display: flex;
    align-items: center;
    gap: 8px;
}

.progress-bar {
    flex: 1;
    height: 8px;
    background: #e9ecef;
    border-radius: 4px;
    overflow: hidden;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    transition: width 0.3s;
}

.achievement-points {
    font-size: 20px;
    font-weight: 700;
    color: #ffc107;
}

.ranking-section {
    padding: 32px;
    background: #f8f9fa;
}

.ranking-section h3 {
    font-size: 20px;
    font-weight: 700;
    margin: 0 0 20px 0;
    color: #333;
}

.ranking-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.rank-position {
    text-align: center;
    margin-bottom: 20px;
}

.rank-number {
    font-size: 48px;
    font-weight: 700;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    -webkit-background-clip: text;
    -webkit-text-fill-color: transparent;
    background-clip: text;
}

.rank-label {
    font-size: 14px;
    color: #6c757d;
    font-weight: 600;
}

.rank-stats {
    display: flex;
    justify-content: space-around;
    margin-bottom: 16px;
    padding-bottom: 16px;
    border-bottom: 1px solid #dee2e6;
}

.rank-stat {
    text-align: center;
}

.rank-stat-value {
    font-size: 24px;
    font-weight: 700;
    color: #333;
    margin-bottom: 4px;
}

.rank-stat-label {
    font-size: 12px;
    color: #6c757d;
    font-weight: 600;
}

.rank-change {
    text-align: center;
    font-size: 14px;
    font-weight: 600;
    padding: 8px;
    border-radius: 6px;
}

.rank-change.positive {
    background: #d4edda;
    color: #155724;
}

.improvement-tips {
    padding: 32px;
}

.improvement-tips h3 {
    font-size: 20px;
    font-weight: 700;
    margin: 0 0 20px 0;
    color: #333;
}

.tips-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 16px;
}

.tip-card {
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 16px;
    display: flex;
    align-items: center;
    gap: 12px;
}

.tip-icon {
    width: 40px;
    height: 40px;
    background: #f8f9fa;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: #667eea;
    flex-shrink: 0;
}

.tip-text {
    font-size: 13px;
    color: #495057;
    font-weight: 500;
}

.modal-actions {
    padding: 24px 32px 32px 32px;
    display: flex;
    gap: 12px;
    justify-content: center;
}

.modal-actions .btn {
    padding: 12px 32px;
    font-size: 16px;
    font-weight: 600;
    border-radius: 8px;
}

@media (max-width: 768px) {
    .gamification-modal-content {
        max-height: 95vh;
    }

    .gamification-header {
        padding: 24px;
    }

    .celebration-icon {
        font-size: 48px;
    }

    .gamification-header h2 {
        font-size: 24px;
    }

    .stats-showcase {
        padding: 20px;
        gap: 12px;
    }

    .modal-actions {
        flex-direction: column;
    }
}
</style>

<script>
function showGamificationModal(data) {
    // Update with real data
    if (data.completionTime) {
        document.getElementById('completionTime').textContent = data.completionTime;
    }
    if (data.scansPerformed) {
        document.getElementById('scansPerformed').textContent = data.scansPerformed;
    }
    if (data.photosUploaded) {
        document.getElementById('photosUploaded').textContent = data.photosUploaded;
    }

    // Show modal
    document.getElementById('gamificationModal').style.display = 'flex';

    // Play celebration sound (optional)
    // new Audio('/sounds/achievement.mp3').play();
}

function closeGamificationModal() {
    document.getElementById('gamificationModal').style.display = 'none';
    // Redirect or refresh
    window.location.href = '/modules/consignments/?route=stock-transfers';
}

function viewFullLeaderboard() {
    window.location.href = '/modules/consignments/analytics/leaderboard.php';
}
</script>
