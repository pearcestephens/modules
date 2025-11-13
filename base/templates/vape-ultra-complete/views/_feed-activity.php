<?php
/**
 * Activity partial - expects $recentActivity array in scope
 */
// Ensure $recentActivity exists
$recentActivity = $recentActivity ?? [];

foreach($recentActivity as $activity):
    if (isset($activity->feed_type) && $activity->feed_type === 'external'):
?>
    <div class="activity-card news-card" data-type="news">
        <?php if (!empty($activity->image)): ?>
        <div class="news-image"><img src="<?= htmlspecialchars($activity->image) ?>" alt="<?= htmlspecialchars($activity->title) ?>"></div>
        <?php endif; ?>
        <div class="activity-content">
            <div class="activity-header">
                <div class="activity-title"><i class="bi bi-newspaper"></i> <?= htmlspecialchars($activity->title) ?></div>
                <div class="activity-time"><?= timeAgo($activity->timestamp) ?></div>
            </div>
            <div class="activity-body"><?= nl2br(htmlspecialchars(mb_strimwidth($activity->description, 0, 200, '...'))) ?></div>
            <div class="news-meta">
                <span class="news-source"><i class="bi bi-globe"></i> <?= htmlspecialchars($activity->details['source'] ?? 'Unknown') ?></span>
                <span class="news-category"><i class="bi bi-tag"></i> <?= htmlspecialchars($activity->details['category'] ?? '') ?></span>
            </div>
            <div class="activity-actions">
                <a href="<?= htmlspecialchars($activity->url) ?>" target="_blank" class="action-btn action-primary"><i class="bi bi-box-arrow-up-right"></i> Read Full Article</a>
            </div>
        </div>
    </div>

<?php else: ?>
    <div class="activity-card" data-type="<?= htmlspecialchars($activity->type ?? 'internal') ?>">
        <div class="activity-icon <?= htmlspecialchars($activity->type ?? '') ?>">
            <?php
            $icons = [
                'order' => 'cart-check-fill',
                'transfer' => 'arrow-left-right',
                'po' => 'clipboard-check-fill',
                'clickcollect' => 'bag-check-fill',
                'lowstock' => 'exclamation-triangle-fill',
                'feedback' => 'chat-square-quote-fill',
                'achievement' => 'trophy'
            ];
            ?>
            <i class="bi bi-<?= $icons[$activity->type] ?? 'circle-fill' ?>"></i>
        </div>
        <div class="activity-content">
            <div class="activity-header">
                <div class="activity-title"><?= htmlspecialchars($activity->title) ?></div>
                <div class="activity-time"><?= timeAgo($activity->timestamp) ?></div>
            </div>
            <div class="activity-body"><?= nl2br(htmlspecialchars($activity->description ?? '')) ?></div>
            <?php if (!empty($activity->details)): ?>
            <div class="activity-details">
                <?php foreach($activity->details as $key => $value): ?>
                <span class="detail-item"><strong><?= ucfirst(htmlspecialchars($key)) ?>:</strong> <?= htmlspecialchars($value) ?></span>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if (!empty($activity->actions)): ?>
            <div class="activity-actions">
                <?php foreach($activity->actions as $action): ?>
                <a href="<?= htmlspecialchars($action->url) ?>" class="action-btn"><i class="bi bi-<?= htmlspecialchars($action->icon) ?>"></i> <?= htmlspecialchars($action->label) ?></a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; endforeach; ?>
