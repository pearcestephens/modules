<?php
/**
 * Main Header Component
 *
 * Logo, search, notifications, user menu
 */

$currentUser = $_SESSION['user'] ?? [
    'name' => 'Guest User',
    'role' => 'Viewer',
    'avatar' => 'GU'
];
?>

<div class="header-inner">
    <!-- Logo -->
    <div class="logo">
        <img src="https://www.vapeshed.co.nz/wp-content/uploads/2023/01/vapeshed-logo-horizontal.png"
             alt="Vape Shed"
             class="logo-img"
             style="height: 32px; width: auto;">
    </div>

    <!-- Search Bar -->
    <div class="search-bar">
        <i class="bi bi-search search-icon"></i>
        <input type="search"
               class="search-input"
               placeholder="Search products, orders, transfers..."
               id="global-search"
               autocomplete="off">
    </div>

    <!-- Header Actions -->
    <div class="header-actions">
        <button class="header-btn" id="notifications-btn" title="Notifications" data-bs-toggle="dropdown">
            <i class="bi bi-bell"></i>
            <span class="badge-dot"></span>
        </button>

        <!-- Notifications Dropdown -->
        <div class="dropdown-menu dropdown-menu-end notification-dropdown">
            <div class="notification-header">
                <h6>Notifications</h6>
                <button class="btn btn-sm">Mark all read</button>
            </div>
            <div class="notification-list">
                <!-- Notifications will be loaded here -->
            </div>
        </div>

        <button class="header-btn" id="messages-btn" title="Messages">
            <i class="bi bi-chat-dots"></i>
            <span class="badge-dot"></span>
        </button>

        <button class="header-btn" id="quick-actions-btn" title="Quick Actions">
            <i class="bi bi-plus-circle"></i>
        </button>

        <button class="header-btn" id="toggle-right-sidebar" title="Toggle Right Sidebar">
            <i class="bi bi-layout-sidebar-inset-reverse"></i>
        </button>
    </div>

    <!-- User Menu -->
    <div class="user-menu dropdown">
        <button class="user-menu-btn" data-bs-toggle="dropdown">
            <div class="avatar"><?= strtoupper(substr($currentUser['avatar'], 0, 2)) ?></div>
            <div class="user-info">
                <div class="user-name"><?= htmlspecialchars($currentUser['name']) ?></div>
                <div class="user-role"><?= htmlspecialchars($currentUser['role']) ?></div>
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
