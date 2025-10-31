<!-- Sidebar Component -->
<aside class="cis-sidebar" id="cisSidebar">
    <div class="sidebar-header">
        <a href="/index.php" class="sidebar-brand">
            <img src="/assets/images/logo-white.png" alt="CIS" height="32">
            <span class="brand-text">CIS</span>
        </a>
    </div>
    
    <nav class="sidebar-nav">
        <ul class="nav-list">
            
            <!-- Dashboard -->
            <li class="nav-item">
                <a href="/index.php" class="nav-link">
                    <i class="fas fa-home"></i>
                    <span class="nav-text">Dashboard</span>
                </a>
            </li>
            
            <!-- Inventory Section -->
            <li class="nav-item has-submenu">
                <a href="#" class="nav-link" data-toggle="submenu">
                    <i class="fas fa-boxes"></i>
                    <span class="nav-text">Inventory</span>
                    <i class="fas fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="nav-submenu">
                    <li><a href="/modules/inventory/count.php">Stock Count</a></li>
                    <li><a href="/modules/transfers/list.php">Transfers</a></li>
                    <li><a href="/modules/consignments/list.php">Consignments</a></li>
                    <li><a href="/product-browser.php">Product Browser</a></li>
                </ul>
            </li>
            
            <!-- Purchase Orders -->
            <li class="nav-item">
                <a href="/modules/purchase_orders/list.php" class="nav-link">
                    <i class="fas fa-file-invoice-dollar"></i>
                    <span class="nav-text">Purchase Orders</span>
                </a>
            </li>
            
            <!-- Suppliers -->
            <li class="nav-item">
                <a href="/modules/suppliers/list.php" class="nav-link">
                    <i class="fas fa-truck"></i>
                    <span class="nav-text">Suppliers</span>
                </a>
            </li>
            
            <!-- Sales & Reports -->
            <li class="nav-item has-submenu">
                <a href="#" class="nav-link" data-toggle="submenu">
                    <i class="fas fa-chart-line"></i>
                    <span class="nav-text">Sales & Reports</span>
                    <i class="fas fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="nav-submenu">
                    <li><a href="/my-store-reports.php">Store Reports</a></li>
                    <li><a href="/daily-store-reconciliations.php">Reconciliations</a></li>
                    <li><a href="/individual-performance.php">Performance</a></li>
                    <li><a href="/juice-reporting.php">Juice Reports</a></li>
                    <li><a href="/nicotine-reporting.php">Nicotine Reports</a></li>
                </ul>
            </li>
            
            <!-- HR & Staff -->
            <li class="nav-item has-submenu">
                <a href="#" class="nav-link" data-toggle="submenu">
                    <i class="fas fa-users"></i>
                    <span class="nav-text">HR & Staff</span>
                    <i class="fas fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="nav-submenu">
                    <li><a href="/hr-overview.php">HR Overview</a></li>
                    <li><a href="/my-hr.php">My HR</a></li>
                    <li><a href="/my-leave-requests.php">Leave Requests</a></li>
                    <li><a href="/employment-applications.php">Applications</a></li>
                    <li><a href="/employee-of-the-month.php">Employee of Month</a></li>
                </ul>
            </li>
            
            <!-- Finance -->
            <li class="nav-item has-submenu">
                <a href="#" class="nav-link" data-toggle="submenu">
                    <i class="fas fa-dollar-sign"></i>
                    <span class="nav-text">Finance</span>
                    <i class="fas fa-chevron-down submenu-arrow"></i>
                </a>
                <ul class="nav-submenu">
                    <li><a href="/harp-financials.php">HARP Financials</a></li>
                    <li><a href="/bank-transactions.php">Bank Transactions</a></li>
                    <li><a href="/cash-expenses.php">Cash Expenses</a></li>
                    <li><a href="/pay-refunds.php">Refunds</a></li>
                </ul>
            </li>
            
            <!-- Settings -->
            <li class="nav-item">
                <a href="/cis-configuration.php" class="nav-link">
                    <i class="fas fa-cog"></i>
                    <span class="nav-text">Settings</span>
                </a>
            </li>
            
        </ul>
    </nav>
    
    <div class="sidebar-footer">
        <p class="version">v2.0.0</p>
    </div>
</aside>

<style>
    :root {
        --cis-sidebar-bg: #495057;
        --cis-sidebar-text: #ffffff;
        --cis-sidebar-hover: rgba(255, 255, 255, 0.1);
        --cis-primary: #8B5CF6;
        --cis-gray-400: #ced4da;
        --cis-gray-500: #adb5bd;
    }
    
    .cis-sidebar {
        width: 260px;
        height: 100vh;
        background-color: var(--cis-sidebar-bg);
        color: var(--cis-sidebar-text);
        display: flex;
        flex-direction: column;
        position: fixed;
        left: 0;
        top: 0;
        z-index: 999;
        transition: transform 0.3s ease;
        overflow-y: auto;
    }
    
    .cis-sidebar.collapsed {
        transform: translateX(-260px);
    }
    
    .sidebar-header {
        padding: 1.5rem;
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
    }
    
    .sidebar-brand {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        color: #ffffff;
        text-decoration: none;
        font-size: 1.2rem;
        font-weight: 600;
    }
    
    .sidebar-nav {
        flex: 1;
        overflow-y: auto;
        padding: 1rem 0;
    }
    
    .nav-list {
        list-style: none;
        margin: 0;
        padding: 0;
    }
    
    .nav-item {
        margin: 0.25rem 0;
    }
    
    .nav-link {
        display: flex;
        align-items: center;
        padding: 0.75rem 1.5rem;
        color: rgba(255, 255, 255, 0.8);
        text-decoration: none;
        transition: all 0.2s;
    }
    
    .nav-link:hover {
        background-color: rgba(255, 255, 255, 0.1);
        color: #ffffff;
    }
    
    .nav-link.active {
        background-color: var(--cis-primary);
        color: #ffffff;
    }
    
    .nav-link i {
        width: 20px;
        margin-right: 0.75rem;
        text-align: center;
    }
    
    .nav-text {
        flex: 1;
    }
    
    .submenu-arrow {
        font-size: 0.75rem;
        transition: transform 0.2s;
    }
    
    .nav-item.open .submenu-arrow {
        transform: rotate(180deg);
    }
    
    .nav-submenu {
        list-style: none;
        margin: 0;
        padding: 0;
        max-height: 0;
        overflow: hidden;
        transition: max-height 0.3s ease;
    }
    
    .nav-item.open .nav-submenu {
        max-height: 500px;
    }
    
    .nav-submenu a {
        display: block;
        padding: 0.5rem 1.5rem 0.5rem 3.5rem;
        color: rgba(255, 255, 255, 0.7);
        text-decoration: none;
        transition: all 0.2s;
        font-size: 0.9rem;
    }
    
    .nav-submenu a:hover {
        background-color: rgba(255, 255, 255, 0.1);
        color: #ffffff;
    }
    
    .sidebar-footer {
        padding: 1rem 1.5rem;
        border-top: 1px solid rgba(255, 255, 255, 0.1);
        text-align: center;
    }
    
    .sidebar-footer .version {
        margin: 0;
        font-size: 0.85rem;
        color: rgba(255, 255, 255, 0.5);
    }
    
    @media (max-width: 768px) {
        .cis-sidebar {
            transform: translateX(-260px);
        }
        
        .cis-sidebar.show {
            transform: translateX(0);
        }
    }
</style>

<script>
    $(document).ready(function() {
        // Submenu toggle
        $('.nav-link[data-toggle="submenu"]').on('click', function(e) {
            e.preventDefault();
            const $item = $(this).closest('.nav-item');
            $item.toggleClass('open');
        });
        
        // Set active link based on current page
        const currentPath = window.location.pathname;
        $('.nav-link, .nav-submenu a').each(function() {
            const href = $(this).attr('href');
            if (href && currentPath.includes(href)) {
                $(this).addClass('active');
                // Open parent submenu if in submenu
                $(this).closest('.nav-item').addClass('open');
            }
        });
    });
</script>
