<?php
/**
 * CIS Two-Tier Header Component v2.0
 * 
 * Modern two-tier header matching current CIS design:
 * - Top tier: Logo, center search bar, notifications, messages, user menu
 * - Bottom tier: Breadcrumbs and purple action buttons
 * 
 * @package CIS\Templates\Components
 * @version 2.0.0
 */

// Configuration
$headerConfig = [
    'logo_url' => 'https://staff.vapeshed.co.nz/assets/img/brand/logo.jpg',
    'logo_alt' => 'The Vape Shed',
    'site_name' => 'CIS Dashboard',
    'search_placeholder' => 'Search products, orders, customers...',
    'user_name' => $_SESSION['user_name'] ?? 'Admin User',
    'user_avatar' => $_SESSION['user_avatar'] ?? null,
    'notifications_count' => $_SESSION['notifications_count'] ?? 13,
    'messages_count' => $_SESSION['messages_count'] ?? 0,
];
?>

<!-- CIS Two-Tier Header CSS -->
<style>
    :root {
        --cis-primary: #8B5CF6;
        --cis-primary-hover: #7C3AED;
        --cis-primary-light: #EDE9FE;
        --cis-danger: #dc3545;
        --cis-success: #28a745;
        --cis-dark: #343a40;
        --cis-secondary: #6c757d;
        --cis-light: #f8f9fa;
        --cis-border-color: #dee2e6;
    }
    
    .cis-header-wrapper {
        position: sticky;
        top: 0;
        z-index: 1000;
        background: white;
        box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    }
    
    /* Top Tier - Main Header */
    .cis-header-top {
        background: white;
        border-bottom: 1px solid var(--cis-border-color);
        padding: 0.75rem 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 2rem;
    }
    
    .cis-header-logo {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        text-decoration: none;
        color: var(--cis-dark);
        flex-shrink: 0;
    }
    
    .cis-header-logo img {
        height: 32px;
        width: auto;
    }
    
    .cis-header-logo-text {
        font-weight: 600;
        font-size: 1.1rem;
        white-space: nowrap;
    }
    
    /* Center Search Bar */
    .cis-header-search {
        flex: 1;
        max-width: 600px;
        position: relative;
    }
    
    .cis-header-search-input {
        width: 100%;
        padding: 0.6rem 2.5rem 0.6rem 1rem;
        border: 1px solid var(--cis-border-color);
        border-radius: 6px;
        font-size: 0.95rem;
        transition: all 0.2s;
    }
    
    .cis-header-search-input:focus {
        outline: none;
        border-color: var(--cis-primary);
        box-shadow: 0 0 0 3px rgba(139, 92, 246, 0.1);
    }
    
    .cis-header-search-icon {
        position: absolute;
        right: 1rem;
        top: 50%;
        transform: translateY(-50%);
        color: var(--cis-secondary);
        pointer-events: none;
    }
    
    /* Right Actions */
    .cis-header-actions {
        display: flex;
        align-items: center;
        gap: 1rem;
        flex-shrink: 0;
    }
    
    .cis-header-icon-btn {
        position: relative;
        background: none;
        border: none;
        padding: 0.5rem;
        cursor: pointer;
        color: var(--cis-dark);
        font-size: 1.2rem;
        transition: all 0.2s;
        border-radius: 6px;
    }
    
    .cis-header-icon-btn:hover {
        background: var(--cis-light);
    }
    
    .cis-header-badge {
        position: absolute;
        top: 2px;
        right: 2px;
        background: var(--cis-danger);
        color: white;
        border-radius: 10px;
        min-width: 20px;
        height: 20px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        font-weight: bold;
        padding: 0 5px;
        box-shadow: 0 2px 4px rgba(220, 53, 69, 0.3);
    }
    
    /* Dropdown Panels */
    .cis-header-dropdown {
        position: absolute;
        top: calc(100% + 10px);
        right: 0;
        background: white;
        border-radius: 8px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.15);
        width: 380px;
        max-height: 500px;
        display: none;
        z-index: 1001;
        border: 1px solid rgba(0,0,0,0.08);
    }
    
    .cis-header-dropdown.show {
        display: block;
        animation: dropdownFadeIn 0.2s ease-out;
    }
    
    @keyframes dropdownFadeIn {
        from {
            opacity: 0;
            transform: translateY(-10px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .cis-dropdown-header {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid #e9ecef;
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .cis-dropdown-title {
        font-weight: 700;
        font-size: 1rem;
        color: #1a202c;
    }
    
    .cis-dropdown-badge {
        background: var(--cis-primary);
        color: white;
        font-size: 0.75rem;
        padding: 0.25rem 0.5rem;
        border-radius: 12px;
        font-weight: 600;
    }
    
    .cis-dropdown-body {
        max-height: 400px;
        overflow-y: auto;
    }
    
    .cis-notification-item,
    .cis-message-item {
        padding: 1rem 1.25rem;
        border-bottom: 1px solid #f1f3f5;
        display: flex;
        gap: 0.75rem;
        cursor: pointer;
        transition: background 0.15s;
    }
    
    .cis-notification-item:hover,
    .cis-message-item:hover {
        background: #f8f9fa;
    }
    
    .cis-notification-item.unread,
    .cis-message-item.unread {
        background: #f0f7ff;
    }
    
    .cis-notification-icon,
    .cis-message-avatar {
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }
    
    .cis-notification-icon {
        background: linear-gradient(135deg, #8B5CF6, #7C3AED);
        color: white;
        font-size: 1rem;
    }
    
    .cis-notification-icon.success {
        background: linear-gradient(135deg, #10b981, #059669);
    }
    
    .cis-notification-icon.warning {
        background: linear-gradient(135deg, #f59e0b, #d97706);
    }
    
    .cis-notification-icon.danger {
        background: linear-gradient(135deg, #ef4444, #dc2626);
    }
    
    .cis-message-avatar {
        background: var(--cis-primary);
        color: white;
        font-weight: 600;
    }
    
    .cis-message-avatar img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
    }
    
    .cis-notification-content,
    .cis-message-content {
        flex: 1;
        min-width: 0;
    }
    
    .cis-notification-title,
    .cis-message-name {
        font-weight: 600;
        font-size: 0.9rem;
        color: #1a202c;
        margin-bottom: 0.25rem;
    }
    
    .cis-notification-text,
    .cis-message-text {
        font-size: 0.85rem;
        color: #6c757d;
        line-height: 1.4;
        display: -webkit-box;
        -webkit-line-clamp: 2;
        -webkit-box-orient: vertical;
        overflow: hidden;
    }
    
    .cis-notification-time,
    .cis-message-time {
        font-size: 0.75rem;
        color: #adb5bd;
        margin-top: 0.25rem;
    }
    
    .cis-dropdown-footer {
        padding: 0.75rem;
        border-top: 1px solid #e9ecef;
        text-align: center;
    }
    
    .cis-dropdown-footer a {
        color: var(--cis-primary);
        text-decoration: none;
        font-weight: 600;
        font-size: 0.875rem;
        transition: color 0.2s;
    }
    
    .cis-dropdown-footer a:hover {
        color: var(--cis-primary-hover);
    }
    
    .cis-header-user {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.4rem 0.8rem;
        background: none;
        border: 1px solid var(--cis-border-color);
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.2s;
        font-size: 0.9rem;
    }
    
    .cis-header-user:hover {
        background: var(--cis-light);
        border-color: var(--cis-primary);
    }
    
    .cis-header-user-avatar {
        width: 32px;
        height: 32px;
        border-radius: 50%;
        background: var(--cis-primary);
        color: white;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 0.85rem;
    }
    
    /* Bottom Tier - Breadcrumbs & Actions */
    .cis-header-bottom {
        background: var(--cis-light);
        padding: 0.75rem 1.5rem;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 1rem;
    }
    
    .cis-breadcrumb {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        font-size: 0.9rem;
        margin: 0;
        padding: 0;
        list-style: none;
    }
    
    .cis-breadcrumb-item {
        color: var(--cis-secondary);
    }
    
    .cis-breadcrumb-item a {
        color: var(--cis-primary);
        text-decoration: none;
        transition: color 0.2s;
    }
    
    .cis-breadcrumb-item a:hover {
        color: var(--cis-primary-hover);
        text-decoration: underline;
    }
    
    .cis-breadcrumb-item.active {
        color: var(--cis-dark);
        font-weight: 500;
    }
    
    .cis-breadcrumb-separator {
        color: var(--cis-secondary);
    }
    
    .cis-header-quick-actions {
        display: flex;
        gap: 0.5rem;
    }
    
    .cis-quick-action-btn {
        padding: 0.5rem 1rem;
        border: none;
        border-radius: 6px;
        font-size: 0.85rem;
        font-weight: 500;
        cursor: pointer;
        transition: all 0.2s;
        white-space: nowrap;
    }
    
    .cis-quick-action-btn.primary {
        background: var(--cis-primary);
        color: white;
    }
    
    .cis-quick-action-btn.primary:hover {
        background: var(--cis-primary-hover);
        transform: translateY(-1px);
        box-shadow: 0 2px 4px rgba(139, 92, 246, 0.3);
    }
    
    .cis-quick-action-btn.success {
        background: var(--cis-success);
        color: white;
    }
    
    .cis-quick-action-btn.success:hover {
        filter: brightness(0.9);
        transform: translateY(-1px);
    }
    
    /* Responsive */
    @media (max-width: 992px) {
        .cis-header-top {
            flex-wrap: wrap;
            gap: 1rem;
        }
        
        .cis-header-search {
            order: 3;
            flex-basis: 100%;
            max-width: none;
        }
        
        .cis-header-logo-text {
            display: none;
        }
    }
    
    @media (max-width: 768px) {
        .cis-header-bottom {
            flex-direction: column;
            align-items: flex-start;
        }
        
        .cis-header-quick-actions {
            width: 100%;
            justify-content: space-between;
        }
        
        .cis-quick-action-btn {
            font-size: 0.75rem;
            padding: 0.4rem 0.8rem;
        }
    }
</style>

<!-- CIS Two-Tier Header HTML -->
<header class="cis-header-wrapper">
    <!-- Top Tier -->
    <div class="cis-header-top">
        <!-- Logo -->
        <a href="/" class="cis-header-logo">
            <img src="<?= htmlspecialchars($headerConfig['logo_url']) ?>" alt="<?= htmlspecialchars($headerConfig['logo_alt']) ?>">
            <span class="cis-header-logo-text"><?= htmlspecialchars($headerConfig['site_name']) ?></span>
        </a>
        
        <!-- Center Search -->
        <div class="cis-header-search">
            <input 
                type="search" 
                class="cis-header-search-input" 
                placeholder="<?= htmlspecialchars($headerConfig['search_placeholder']) ?>"
                id="cis-global-search"
                autocomplete="off"
            >
            <i class="fas fa-search cis-header-search-icon"></i>
        </div>
        
        <!-- Right Actions -->
        <div class="cis-header-actions">
            <!-- Notifications -->
            <button class="cis-header-icon-btn" id="cis-notifications-btn" title="Notifications">
                <i class="fas fa-bell"></i>
                <?php if ($headerConfig['notifications_count'] > 0): ?>
                    <span class="cis-header-badge"><?= $headerConfig['notifications_count'] ?></span>
                <?php endif; ?>
            </button>
            
            <!-- Messages -->
            <button class="cis-header-icon-btn" id="cis-messages-btn" title="Messages">
                <i class="fas fa-comment-dots"></i>
                <?php if ($headerConfig['messages_count'] > 0): ?>
                    <span class="cis-header-badge"><?= $headerConfig['messages_count'] ?></span>
                <?php endif; ?>
            </button>
            
            <!-- Messages Dropdown -->
            <div class="cis-header-dropdown" id="cis-messages-dropdown">
                <div class="cis-dropdown-header">
                    <span class="cis-dropdown-title">Messages</span>
                    <span class="cis-dropdown-badge">5 New</span>
                </div>
                <div class="cis-dropdown-body">
                    <div class="cis-message-item unread">
                        <div class="cis-message-avatar">JD</div>
                        <div class="cis-message-content">
                            <div class="cis-message-name">John Davis</div>
                            <div class="cis-message-text">Hey, can you check the stock levels for product #2458?</div>
                            <div class="cis-message-time">2 minutes ago</div>
                        </div>
                    </div>
                    <div class="cis-message-item unread">
                        <div class="cis-message-avatar">SW</div>
                        <div class="cis-message-content">
                            <div class="cis-message-name">Sarah Wilson</div>
                            <div class="cis-message-text">The new shipment arrived! Ready for processing.</div>
                            <div class="cis-message-time">15 minutes ago</div>
                        </div>
                    </div>
                    <div class="cis-message-item">
                        <div class="cis-message-avatar">MB</div>
                        <div class="cis-message-content">
                            <div class="cis-message-name">Michael Brown</div>
                            <div class="cis-message-text">Thanks for the quick response on that order!</div>
                            <div class="cis-message-time">1 hour ago</div>
                        </div>
                    </div>
                    <div class="cis-message-item">
                        <div class="cis-message-avatar">ET</div>
                        <div class="cis-message-content">
                            <div class="cis-message-name">Emma Taylor</div>
                            <div class="cis-message-text">I've updated the inventory report. Please review when you can.</div>
                            <div class="cis-message-time">3 hours ago</div>
                        </div>
                    </div>
                    <div class="cis-message-item">
                        <div class="cis-message-avatar">RJ</div>
                        <div class="cis-message-content">
                            <div class="cis-message-name">Robert Johnson</div>
                            <div class="cis-message-text">Meeting scheduled for 3pm tomorrow to discuss Q4 sales.</div>
                            <div class="cis-message-time">Yesterday</div>
                        </div>
                    </div>
                </div>
                <div class="cis-dropdown-footer">
                    <a href="/messages">View All Messages</a>
                </div>
            </div>
            
            <!-- Notifications Dropdown -->
            <div class="cis-header-dropdown" id="cis-notifications-dropdown">
                <div class="cis-dropdown-header">
                    <span class="cis-dropdown-title">Notifications</span>
                    <span class="cis-dropdown-badge">13</span>
                </div>
                <div class="cis-dropdown-body">
                    <div class="cis-notification-item unread">
                        <div class="cis-notification-icon success">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <div class="cis-notification-content">
                            <div class="cis-notification-title">Order Completed</div>
                            <div class="cis-notification-text">Order #ORD-2458 has been successfully processed and shipped.</div>
                            <div class="cis-notification-time">5 minutes ago</div>
                        </div>
                    </div>
                    <div class="cis-notification-item unread">
                        <div class="cis-notification-icon">
                            <i class="fas fa-box"></i>
                        </div>
                        <div class="cis-notification-content">
                            <div class="cis-notification-title">Low Stock Alert</div>
                            <div class="cis-notification-text">Product "Vape Kit Pro" is running low. Only 15 units remaining.</div>
                            <div class="cis-notification-time">1 hour ago</div>
                        </div>
                    </div>
                    <div class="cis-notification-item unread">
                        <div class="cis-notification-icon warning">
                            <i class="fas fa-exclamation-triangle"></i>
                        </div>
                        <div class="cis-notification-content">
                            <div class="cis-notification-title">Payment Pending</div>
                            <div class="cis-notification-text">Invoice #INV-1234 is overdue. Please follow up with customer.</div>
                            <div class="cis-notification-time">2 hours ago</div>
                        </div>
                    </div>
                    <div class="cis-notification-item">
                        <div class="cis-notification-icon success">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="cis-notification-content">
                            <div class="cis-notification-title">New Customer</div>
                            <div class="cis-notification-text">Alice Johnson registered as a new customer.</div>
                            <div class="cis-notification-time">3 hours ago</div>
                        </div>
                    </div>
                    <div class="cis-notification-item">
                        <div class="cis-notification-icon">
                            <i class="fas fa-truck"></i>
                        </div>
                        <div class="cis-notification-content">
                            <div class="cis-notification-title">Shipment Arrived</div>
                            <div class="cis-notification-text">New stock shipment has arrived at Auckland warehouse.</div>
                            <div class="cis-notification-time">5 hours ago</div>
                        </div>
                    </div>
                    <div class="cis-notification-item">
                        <div class="cis-notification-icon danger">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <div class="cis-notification-content">
                            <div class="cis-notification-title">Order Cancelled</div>
                            <div class="cis-notification-text">Customer cancelled order #ORD-2455. Refund processed.</div>
                            <div class="cis-notification-time">Yesterday</div>
                        </div>
                    </div>
                </div>
                <div class="cis-dropdown-footer">
                    <a href="/notifications">View All Notifications</a>
                </div>
            </div>
            
            <!-- User Menu -->
            <button class="cis-header-user" id="cis-user-menu-btn">
                <div class="cis-header-user-avatar">
                    <?php if ($headerConfig['user_avatar']): ?>
                        <img src="<?= htmlspecialchars($headerConfig['user_avatar']) ?>" alt="User" style="width: 100%; height: 100%; border-radius: 50%; object-fit: cover;">
                    <?php else: ?>
                        <?= strtoupper(substr($headerConfig['user_name'], 0, 1)) ?>
                    <?php endif; ?>
                </div>
                <span><?= htmlspecialchars($headerConfig['user_name']) ?></span>
                <span>▼</span>
            </button>
        </div>
    </div>
    
    <!-- Bottom Tier -->
    <div class="cis-header-bottom">
        <!-- Breadcrumbs -->
        <nav>
            <ol class="cis-breadcrumb">
                <li class="cis-breadcrumb-item">
                    <a href="/">Home</a>
                </li>
                <li class="cis-breadcrumb-separator">/</li>
                <li class="cis-breadcrumb-item">
                    <a href="#"><?= $pageParent ?? 'Section' ?></a>
                </li>
                <li class="cis-breadcrumb-separator">/</li>
                <li class="cis-breadcrumb-item active">
                    <?= $pageTitle ?? 'Page' ?>
                </li>
            </ol>
        </nav>
        
        <!-- Quick Actions -->
        <div class="cis-header-quick-actions">
            <button class="cis-quick-action-btn primary" onclick="window.location.href='/quick-product-qty-change.php'">
                Quick Product Qty Change
            </button>
            <button class="cis-quick-action-btn success" onclick="window.location.href='/store-cashup-calculator.php'">
                Store Cashup Calculator
            </button>
        </div>
    </div>
</header>

<!-- Header JavaScript -->
<script>
    // Global search functionality
    document.getElementById('cis-global-search')?.addEventListener('input', function(e) {
        const query = e.target.value;
        // TODO: Implement search suggestions dropdown
        console.log('Search query:', query);
    });
    
    // Notifications dropdown toggle
    const notificationsBtn = document.getElementById('cis-notifications-btn');
    const notificationsDropdown = document.getElementById('cis-notifications-dropdown');
    const messagesBtn = document.getElementById('cis-messages-btn');
    const messagesDropdown = document.getElementById('cis-messages-dropdown');
    const userMenuBtn = document.getElementById('cis-user-menu-btn');
    
    // Toggle notifications dropdown
    notificationsBtn?.addEventListener('click', function(e) {
        e.stopPropagation();
        notificationsDropdown.classList.toggle('show');
        messagesDropdown.classList.remove('show');
    });
    
    // Toggle messages dropdown
    messagesBtn?.addEventListener('click', function(e) {
        e.stopPropagation();
        messagesDropdown.classList.toggle('show');
        notificationsDropdown.classList.remove('show');
    });
    
    // User menu button
    userMenuBtn?.addEventListener('click', function(e) {
        e.stopPropagation();
        notificationsDropdown.classList.remove('show');
        messagesDropdown.classList.remove('show');
        alert('User menu coming soon!');
    });
    
    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.cis-header-actions')) {
            notificationsDropdown?.classList.remove('show');
            messagesDropdown?.classList.remove('show');
        }
    });
    
    // Prevent dropdown close when clicking inside
    notificationsDropdown?.addEventListener('click', function(e) {
        e.stopPropagation();
    });
    
    messagesDropdown?.addEventListener('click', function(e) {
        e.stopPropagation();
    });
    
    // Mark notification as read when clicked
    document.querySelectorAll('.cis-notification-item').forEach(item => {
        item.addEventListener('click', function() {
            this.classList.remove('unread');
        });
    });
    
    // Mark message as read when clicked
    document.querySelectorAll('.cis-message-item').forEach(item => {
        item.addEventListener('click', function() {
            this.classList.remove('unread');
            // TODO: Open full message view
            console.log('Opening message...');
        });
    });
</script>
