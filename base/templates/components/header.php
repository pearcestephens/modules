<header class="cis-header">
    <div class="header-container">
        <div class="header-left">
            <button type="button" class="btn btn-link mobile-toggle" id="sidebarToggle">
                <i class="fas fa-bars"></i>
            </button>
            <div class="header-logo">
                <img src="/assets/images/logo.png" alt="CIS" height="32">
            </div>
        </div>

        <div class="header-center">
            <div class="header-search">
                <i class="fas fa-search"></i>
                <input type="text" class="form-control" placeholder="Search anything..." id="globalSearch">
            </div>
        </div>

        <div class="header-right">
            <div class="header-actions">
                <!-- Notifications -->
                <div class="dropdown">
                    <button class="btn btn-link" data-bs-toggle="dropdown">
                        <i class="fas fa-bell"></i>
                        @if(!empty($notificationCount) && $notificationCount > 0)
                            <span class="badge bg-danger">{{ $notificationCount }}</span>
                        @endif
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <h6 class="dropdown-header">Notifications</h6>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="#">No new notifications</a>
                    </div>
                </div>

                <!-- User Menu -->
                <div class="dropdown">
                    <button class="btn btn-link" data-bs-toggle="dropdown">
                        <i class="fas fa-user-circle"></i>
                        <span>{{ $_SESSION['user_name'] ?? 'User' }}</span>
                    </button>
                    <div class="dropdown-menu dropdown-menu-end">
                        <a class="dropdown-item" href="/profile.php">
                            <i class="fas fa-user"></i> Profile
                        </a>
                        <a class="dropdown-item" href="/settings.php">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="/logout.php">
                            <i class="fas fa-sign-out-alt"></i> Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</header>

<style>
    .cis-header {
        position: fixed;
        top: 0;
        right: 0;
        left: 260px;
        height: 60px;
        background: white;
        border-bottom: 1px solid #E5E7EB;
        z-index: 1000;
        transition: left 0.3s;
    }

    .header-container {
        display: flex;
        align-items: center;
        height: 100%;
        padding: 0 1.5rem;
        gap: 1rem;
    }

    .header-left, .header-right {
        display: flex;
        align-items: center;
        gap: 1rem;
    }

    .header-center {
        flex: 1;
        max-width: 600px;
    }

    .header-search {
        position: relative;
    }

    .header-search i {
        position: absolute;
        left: 12px;
        top: 50%;
        transform: translateY(-50%);
        color: #9CA3AF;
    }

    .header-search input {
        padding-left: 40px;
        border-radius: 20px;
    }

    .header-actions {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }

    .header-actions .btn {
        position: relative;
        color: #6B7280;
    }

    .header-actions .badge {
        position: absolute;
        top: -5px;
        right: -5px;
        font-size: 0.65rem;
        padding: 0.25em 0.5em;
    }

    .mobile-toggle {
        display: none;
    }

    @media (max-width: 768px) {
        .cis-header {
            left: 0;
        }

        .mobile-toggle {
            display: block;
        }
    }
</style>
