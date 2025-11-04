<aside class="cis-sidebar" id="sidebar">
    <div class="sidebar-header">
        <img src="/assets/images/logo.png" alt="CIS" height="32">
        <h5>CIS</h5>
    </div>

    <nav class="sidebar-nav">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link active" href="/dashboard.php">
                    <i class="fas fa-home"></i>
                    <span>Dashboard</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="/inventory.php">
                    <i class="fas fa-box"></i>
                    <span>Inventory</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="/transfers.php">
                    <i class="fas fa-exchange-alt"></i>
                    <span>Transfers</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="/orders.php">
                    <i class="fas fa-shopping-cart"></i>
                    <span>Purchase Orders</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="/consignments.php">
                    <i class="fas fa-truck"></i>
                    <span>Consignments</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="/payroll.php">
                    <i class="fas fa-money-bill"></i>
                    <span>Payroll</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="/reports.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Reports</span>
                </a>
            </li>
        </ul>

        <div class="sidebar-divider"></div>

        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link" href="/settings.php">
                    <i class="fas fa-cog"></i>
                    <span>Settings</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="/help.php">
                    <i class="fas fa-question-circle"></i>
                    <span>Help</span>
                </a>
            </li>
        </ul>
    </nav>
</aside>

<style>
    .cis-sidebar {
        position: fixed;
        top: 0;
        left: 0;
        bottom: 0;
        width: 260px;
        background: #1F2937;
        color: white;
        z-index: 1001;
        transition: transform 0.3s;
    }

    .sidebar-header {
        display: flex;
        align-items: center;
        gap: 1rem;
        padding: 1.5rem;
        border-bottom: 1px solid rgba(255,255,255,0.1);
    }

    .sidebar-header h5 {
        margin: 0;
        color: white;
        font-weight: 600;
    }

    .sidebar-nav {
        padding: 1rem 0;
        overflow-y: auto;
        height: calc(100vh - 80px);
    }

    .sidebar-nav .nav-link {
        display: flex;
        align-items: center;
        gap: 0.75rem;
        padding: 0.75rem 1.5rem;
        color: #D1D5DB;
        text-decoration: none;
        transition: all 0.2s;
    }

    .sidebar-nav .nav-link:hover {
        background: rgba(255,255,255,0.05);
        color: white;
    }

    .sidebar-nav .nav-link.active {
        background: var(--cis-primary);
        color: white;
    }

    .sidebar-nav .nav-link i {
        width: 20px;
        text-align: center;
    }

    .sidebar-divider {
        height: 1px;
        background: rgba(255,255,255,0.1);
        margin: 1rem 0;
    }

    @media (max-width: 768px) {
        .cis-sidebar {
            transform: translateX(-100%);
        }

        .cis-sidebar.show {
            transform: translateX(0);
        }
    }
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');

    if (toggle) {
        toggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
    }
});
</script>
