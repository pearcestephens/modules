<!-- Header -->
<header class="cis-header">
    <div class="header-left">
        <button class="header-toggle-btn" id="sidebarToggle">
            <i class="fas fa-bars"></i>
        </button>

        <?php if (!empty($breadcrumbs)): ?>
            <nav class="header-breadcrumb">
                <?php foreach ($breadcrumbs as $index => $crumb): ?>
                    <?php if ($index > 0): ?>
                        <i class="fas fa-chevron-right" style="font-size: 10px;"></i>
                    <?php endif; ?>
                    <?php if (!empty($crumb['active'])): ?>
                        <span class="active"><?= htmlspecialchars($crumb['label']) ?></span>
                    <?php else: ?>
                        <a href="<?= htmlspecialchars($crumb['url']) ?>"><?= htmlspecialchars($crumb['label']) ?></a>
                    <?php endif; ?>
                <?php endforeach; ?>
            </nav>
        <?php endif; ?>
    </div>

    <div class="header-search" style="position: relative;">
        <i class="fas fa-search header-search-icon"></i>
        <input type="text" placeholder="Search anything... (Ctrl+K)" id="globalSearch">
    </div>

    <div class="header-right">
        <button class="header-icon-btn" title="Notifications">
            <i class="fas fa-bell"></i>
            <?php if (!empty($notificationCount) && $notificationCount > 0): ?>
                <span class="header-badge"><?= $notificationCount ?></span>
            <?php endif; ?>
        </button>

        <button class="header-icon-btn" title="Messages">
            <i class="fas fa-envelope"></i>
        </button>

        <div class="header-user" id="headerUserMenu">
            <div class="header-avatar">
                <?= strtoupper(substr($_SESSION['user_name'] ?? 'U', 0, 1)) ?>
            </div>
            <div class="header-user-info">
                <div class="header-user-name"><?= htmlspecialchars($_SESSION['user_name'] ?? 'User') ?></div>
                <div class="header-user-role"><?= htmlspecialchars($_SESSION['user_role'] ?? 'Staff') ?></div>
            </div>
            <i class="fas fa-chevron-down" style="font-size: 10px; color: var(--cis-text-light);"></i>
        </div>
    </div>
</header>
