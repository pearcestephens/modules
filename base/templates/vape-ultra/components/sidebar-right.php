<?php
/**
 * Right Sidebar Component
 *
 * Widgets, stats, quick info
 * Modules can override this completely
 */
?>

<div class="sidebar-right-inner">
    <!-- Store Rankings Widget -->
    <div class="widget">
        <div class="widget-title">Store Rankings</div>
        <div class="quick-stat">
            <span class="quick-stat-label">🥇 Auckland CBD</span>
            <span class="quick-stat-value">$8.5K</span>
        </div>
        <div class="quick-stat">
            <span class="quick-stat-label">🥈 Wellington</span>
            <span class="quick-stat-value">$7.2K</span>
        </div>
        <div class="quick-stat">
            <span class="quick-stat-label">🥉 Christchurch</span>
            <span class="quick-stat-value">$5.8K</span>
        </div>
        <div class="quick-stat">
            <span class="quick-stat-label">📍 Hamilton</span>
            <span class="quick-stat-value">$4.2K</span>
        </div>
    </div>

    <!-- System Metrics Widget -->
    <div class="widget">
        <div class="widget-title">System Metrics</div>
        <div class="quick-stat">
            <span class="quick-stat-label">CPU Usage</span>
            <span class="quick-stat-value">45%</span>
        </div>
        <div class="quick-stat">
            <span class="quick-stat-label">Memory</span>
            <span class="quick-stat-value">2.6GB</span>
        </div>
        <div class="quick-stat">
            <span class="quick-stat-label">Storage</span>
            <span class="quick-stat-value">82%</span>
        </div>
    </div>

    <!-- Module can inject custom widgets here -->
    <?php if (isset($customWidgets) && $customWidgets): ?>
        <?= $customWidgets ?>
    <?php endif; ?>
</div>
