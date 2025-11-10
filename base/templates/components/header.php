<!-- Header Component -->
<header class="cis-header">
    <div class="header-left">
        <!-- Mobile Menu Toggle -->
        <button type="button" class="btn btn-outline-secondary mobile-menu-toggle" id="mobileMenuToggle">
            <i class="fas fa-bars"></i>
        </button>
        
        <!-- Logo -->
        <div class="header-logo">
            <a href="/index.php">
                <img src="/assets/images/logo.png" alt="CIS" height="32">
            </a>
        </div>
    </div>
    
    <!-- Search Bar (Universal AI Search) -->
    <div class="header-search">
        <?php include __DIR__ . '/search-bar.php'; ?>
    </div>
    
    <!-- Right Side Actions -->
    <div class="header-right">
        
        <!-- Notifications -->
        <div class="header-item">
            <button type="button" class="btn btn-outline-secondary" id="notificationsBtn">
                <i class="fas fa-bell"></i>
                <?php if (!empty($notificationCount) && $notificationCount > 0): ?>
                    <span class="badge badge-danger badge-pill"><?= $notificationCount ?></span>
                <?php endif; ?>
            </button>
        </div>
        
        <!-- User Menu -->
        <div class="header-item">
            <div class="dropdown">
                <button type="button" class="btn btn-outline-secondary dropdown-toggle" id="userMenuBtn">
                    <i class="fas fa-user"></i>
                    <span class="user-name"><?= $_SESSION['user_name'] ?? 'User' ?></span>
                </button>
                <div class="dropdown-menu dropdown-menu-right" id="userMenu">
                    <a class="dropdown-item" href="/my-profile.php">
                        <i class="fas fa-user-circle"></i> My Profile
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
</header>

<style>
    .cis-header {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 0 1.5rem;
        height: 60px;
        background-color: var(--cis-white);
        border-bottom: 1px solid var(--cis-border-color);
        box-shadow: var(--cis-shadow-sm);
        position: sticky;
        top: 0;
        z-index: var(--cis-z-sticky);
    }
    
    .header-left,
    .header-right {
        display: flex;
        align-items: center;
        gap: var(--cis-space-3);
    }
    
    .header-logo img {
        vertical-align: middle;
    }
    
    .header-search {
        flex: 1;
        max-width: 600px;
        margin: 0 2rem;
    }
    
    .header-item {
        position: relative;
    }
    
    .header-item .badge {
        position: absolute;
        top: -5px;
        right: -5px;
    }
    
    .mobile-menu-toggle {
        display: none;
    }
    
    .user-name {
        margin-left: 0.5rem;
    }
    
    /* Dropdown Menu */
    .dropdown {
        position: relative;
    }
    
    .dropdown-menu {
        display: none;
        position: absolute;
        top: 100%;
        right: 0;
        margin-top: 0.5rem;
        min-width: 200px;
        background-color: var(--cis-white);
        border: 1px solid var(--cis-border-color);
        border-radius: var(--cis-border-radius);
        box-shadow: var(--cis-shadow-lg);
        z-index: var(--cis-z-dropdown);
    }
    
    .dropdown-menu.show {
        display: block;
    }
    
    .dropdown-item {
        display: block;
        padding: 0.5rem 1rem;
        color: var(--cis-gray-800);
        text-decoration: none;
        transition: background-color 0.2s;
    }
    
    .dropdown-item:hover {
        background-color: var(--cis-gray-100);
    }
    
    .dropdown-item i {
        margin-right: 0.5rem;
        width: 16px;
        text-align: center;
    }
    
    .dropdown-divider {
        height: 1px;
        margin: 0.5rem 0;
        background-color: var(--cis-border-color);
    }
    
    @media (max-width: 768px) {
        .mobile-menu-toggle {
            display: inline-block;
        }
        
        .header-search {
            display: none;
        }
        
        .user-name {
            display: none;
        }
        
        .cis-header {
            padding: 0 1rem;
        }
    }
</style>

<script>
    $(document).ready(function() {
        // User menu dropdown toggle
        $('#userMenuBtn').on('click', function(e) {
            e.stopPropagation();
            $('#userMenu').toggleClass('show');
        });
        
        // Close dropdown when clicking outside
        $(document).on('click', function() {
            $('#userMenu').removeClass('show');
        });
        
        // Prevent dropdown close when clicking inside
        $('#userMenu').on('click', function(e) {
            e.stopPropagation();
        });
        
        // Notifications (placeholder - will integrate with WebSocket later)
        $('#notificationsBtn').on('click', function() {
            // TODO: Show notifications dropdown
            console.log('Show notifications');
        });
    });
</script>
