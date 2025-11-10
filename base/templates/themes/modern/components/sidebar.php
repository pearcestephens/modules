<body>

<!-- Sidebar -->
<aside class="cis-sidebar">
    <a href="/" class="sidebar-brand">
        <div class="sidebar-brand-icon">
            <i class="fas fa-database"></i>
        </div>
        <span class="sidebar-brand-text">CIS</span>
    </a>

    <nav class="sidebar-nav">
        <!-- Main Section -->
        <div class="nav-section-title">Main</div>

        <!-- Dashboard -->
        <div class="nav-item">
            <a href="/" class="nav-link">
                <i class="fas fa-home nav-link-icon"></i>
                <span class="nav-link-text">Dashboard</span>
                <span class="nav-tooltip">Dashboard</span>
            </a>
        </div>

        <!-- Operations Section -->
        <div class="nav-section-title">Operations</div>

        <!-- Consignments -->
        <div class="nav-item">
            <a href="/modules/consignments/" class="nav-link">
                <i class="fas fa-boxes nav-link-icon"></i>
                <span class="nav-link-text">Consignments</span>
                <span class="nav-tooltip">Consignments</span>
            </a>
        </div>

        <!-- Inventory -->
        <div class="nav-item">
            <a href="#" class="nav-link" data-toggle="submenu">
                <i class="fas fa-warehouse nav-link-icon"></i>
                <span class="nav-link-text">Inventory</span>
                <i class="fas fa-chevron-down nav-link-arrow"></i>
                <span class="nav-tooltip">Inventory</span>
            </a>
            <div class="nav-submenu">
                <a href="/modules/inventory/count.php">Stock Count</a>
                <a href="/modules/transfers/list.php">Transfers</a>
                <a href="/product-browser.php">Product Browser</a>
            </div>
        </div>

        <!-- Purchase Orders -->
        <div class="nav-item">
            <a href="/modules/purchase_orders/list.php" class="nav-link">
                <i class="fas fa-file-invoice nav-link-icon"></i>
                <span class="nav-link-text">Purchase Orders</span>
                <span class="nav-tooltip">Purchase Orders</span>
            </a>
        </div>

        <!-- Suppliers -->
        <div class="nav-item">
            <a href="/modules/suppliers/list.php" class="nav-link">
                <i class="fas fa-truck nav-link-icon"></i>
                <span class="nav-link-text">Suppliers</span>
                <span class="nav-tooltip">Suppliers</span>
            </a>
        </div>

        <!-- Reports Section -->
        <div class="nav-section-title">Reports & Analytics</div>

        <!-- Sales & Reports -->
        <div class="nav-item">
            <a href="#" class="nav-link" data-toggle="submenu">
                <i class="fas fa-chart-line nav-link-icon"></i>
                <span class="nav-link-text">Sales & Reports</span>
                <i class="fas fa-chevron-down nav-link-arrow"></i>
                <span class="nav-tooltip">Sales & Reports</span>
            </a>
            <div class="nav-submenu">
                <a href="/my-store-reports.php">Store Reports</a>
                <a href="/daily-store-reconciliations.php">Reconciliations</a>
                <a href="/individual-performance.php">Performance</a>
            </div>
        </div>

        <!-- Finance -->
        <div class="nav-item">
            <a href="#" class="nav-link" data-toggle="submenu">
                <i class="fas fa-dollar-sign nav-link-icon"></i>
                <span class="nav-link-text">Finance</span>
                <i class="fas fa-chevron-down nav-link-arrow"></i>
                <span class="nav-tooltip">Finance</span>
            </a>
            <div class="nav-submenu">
                <a href="/harp-financials.php">HARP Financials</a>
                <a href="/bank-transactions.php">Bank Transactions</a>
                <a href="/cash-expenses.php">Cash Expenses</a>
            </div>
        </div>

        <!-- HR Section -->
        <div class="nav-section-title">People</div>

        <!-- HR & Staff -->
        <div class="nav-item">
            <a href="#" class="nav-link" data-toggle="submenu">
                <i class="fas fa-users nav-link-icon"></i>
                <span class="nav-link-text">HR & Staff</span>
                <i class="fas fa-chevron-down nav-link-arrow"></i>
                <span class="nav-tooltip">HR & Staff</span>
            </a>
            <div class="nav-submenu">
                <a href="/hr-overview.php">HR Overview</a>
                <a href="/my-hr.php">My HR</a>
                <a href="/my-leave-requests.php">Leave Requests</a>
                <a href="/modules/staff-accounts/">Staff Accounts</a>
            </div>
        </div>

        <!-- System Section -->
        <div class="nav-section-title">System</div>

        <!-- Settings -->
        <div class="nav-item">
            <a href="/cis-configuration.php" class="nav-link">
                <i class="fas fa-cog nav-link-icon"></i>
                <span class="nav-link-text">Settings</span>
                <span class="nav-tooltip">Settings</span>
            </a>
        </div>
    </nav>

    <div class="sidebar-footer">
        <i class="fas fa-info-circle"></i>
        <span class="sidebar-footer-text">CIS v3.0.0</span>
    </div>
</aside>

<!-- Mobile Overlay -->
<div class="sidebar-overlay" id="sidebarOverlay"></div>
