<?php
/**
 * Messaging Layout
 *
 * Special full-width layout for messaging center
 * Only header + full content + footer (no sidebars)
 */
?>

<div class="app-messaging">
    <!-- Header -->
    <header class="header messaging-header-bar" id="app-header">
        <div class="header-inner">
            <!-- Back/Home Button -->
            <a href="/" class="header-btn" title="Back to Dashboard">
                <i class="bi bi-house-fill"></i>
            </a>

            <!-- Logo -->
            <div class="logo">
                <img src="https://www.vapeshed.co.nz/wp-content/uploads/2023/01/vapeshed-logo-horizontal.png"
                     alt="Vape Shed"
                     class="logo-img"
                     style="height: 28px; width: auto;">
            </div>

            <!-- Spacer -->
            <div style="flex: 1;"></div>

            <!-- Quick Links -->
            <div class="messaging-quick-links">
                <a href="/" class="quick-link">
                    <i class="bi bi-speedometer2"></i>
                    <span>Dashboard</span>
                </a>
                <a href="/modules/consignments/" class="quick-link">
                    <i class="bi bi-box-seam"></i>
                    <span>Consignments</span>
                </a>
                <a href="/transfers/" class="quick-link">
                    <i class="bi bi-arrow-left-right"></i>
                    <span>Transfers</span>
                </a>
            </div>

            <!-- Header Actions -->
            <div class="header-actions">
                <button class="header-btn" id="notifications-btn" title="Notifications">
                    <i class="bi bi-bell"></i>
                    <span class="badge-dot"></span>
                </button>

                <button class="header-btn" id="quick-actions-btn" title="Quick Actions">
                    <i class="bi bi-plus-circle"></i>
                </button>
            </div>

            <!-- User Menu -->
            <div class="user-menu dropdown">
                <button class="user-menu-btn" data-bs-toggle="dropdown">
                    <div class="avatar">
                        <?= strtoupper(substr($_SESSION['first_name'] ?? 'GU', 0, 2)) ?>
                    </div>
                    <div class="user-info">
                        <div class="user-name"><?= htmlspecialchars($_SESSION['first_name'] ?? 'Guest') ?></div>
                        <div class="user-role"><?= htmlspecialchars($_SESSION['role'] ?? 'Viewer') ?></div>
                    </div>
                    <i class="bi bi-chevron-down"></i>
                </button>

                <ul class="dropdown-menu dropdown-menu-end">
                    <li><a class="dropdown-item" href="/profile"><i class="bi bi-person"></i> Profile</a></li>
                    <li><a class="dropdown-item" href="/settings"><i class="bi bi-gear"></i> Settings</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="/logout"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                </ul>
            </div>
        </div>
    </header>

    <!-- Full-Width Content Area -->
    <main class="messaging-main" id="app-main">
        <?php
        // This is where messaging center content gets injected
        if (isset($moduleContent) && $moduleContent) {
            echo $moduleContent;
        } elseif (isset($contentFile) && file_exists($contentFile)) {
            include $contentFile;
        } else {
            echo '<div class="alert alert-info">No content loaded.</div>';
        }
        ?>
    </main>

    <!-- Footer -->
    <footer class="footer messaging-footer" id="app-footer">
        <div class="footer-left">
            <span>CIS 2.0 Messaging</span>
            <span class="status-indicator">
                <span class="status-dot" style="background: #10b981;"></span>
                Connected
            </span>
        </div>
        <div class="footer-right">
            <span><?= date('Y') ?> © Vape Shed</span>
            <a href="/help">Help</a>
            <a href="/privacy">Privacy</a>
        </div>
    </footer>
</div>

<style>
/* Messaging Layout Styles */
.app-messaging {
    display: grid;
    grid-template-rows: var(--header-h) 1fr var(--footer-h);
    height: 100vh;
    overflow: hidden;
}

.messaging-header-bar {
    grid-row: 1;
    background: var(--bg-card);
    border-bottom: 1px solid rgba(0,0,0,0.08);
    display: flex;
    align-items: center;
    padding: 0 var(--space-lg);
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    z-index: 100;
}

.messaging-main {
    grid-row: 2;
    overflow: hidden;
    background: #f5f6f8;
}

.messaging-footer {
    grid-row: 3;
    background: var(--bg-card);
    border-top: 1px solid rgba(0,0,0,0.08);
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 0 var(--space-lg);
    font-size: var(--font-size-xs);
    color: var(--text-4);
}

/* Quick Links */
.messaging-quick-links {
    display: flex;
    gap: 8px;
    margin-right: 24px;
}

.quick-link {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 8px 12px;
    border-radius: 6px;
    text-decoration: none;
    color: var(--text-3);
    font-size: 14px;
    transition: all 0.2s;
}

.quick-link:hover {
    background: var(--bg-hover);
    color: var(--text-1);
}

.quick-link i {
    font-size: 16px;
}

/* Status indicator */
.status-indicator {
    display: flex;
    align-items: center;
    gap: 6px;
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.5; }
}

/* Responsive */
@media (max-width: 768px) {
    .messaging-quick-links {
        display: none;
    }

    .quick-link span {
        display: none;
    }
}
</style>
