<?php
/**
 * CIS Template Wrapper for Consignments Module
 * Applies the standard CIS admin template to consignments pages
 */

class CISTemplate {
    private $title = 'Consignments';
    private $breadcrumbs = [];
    private $content = '';

    public function __construct() {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            @session_start();
        }
    }

    public function setTitle($title) {
        $this->title = $title;
    }

    public function setBreadcrumbs($breadcrumbs) {
        $this->breadcrumbs = $breadcrumbs;
    }

    public function startContent() {
        ob_start();
    }

    public function endContent() {
        $this->content = ob_get_clean();
    }

    public function render() {
        // Include header
        $this->renderHeader();

        // Render breadcrumbs if any
        if (!empty($this->breadcrumbs)) {
            $this->renderBreadcrumbs();
        }

        // Render content
        echo $this->content;

        // Include footer
        $this->renderFooter();
    }

    private function renderHeader() {
        ?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($this->title); ?> — CIS Control Panel</title>
  <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">
  <link rel="stylesheet" href="https://staff.vapeshed.co.nz/admin-ui/assets/css/admin.css">
  <link rel="stylesheet" href="https://staff.vapeshed.co.nz/admin-ui/assets/css/dashboard.css">
  <style>
    .sidebar { min-width: 250px; max-width: 250px; }
    .sidebar-brand { border-bottom: 1px solid rgba(255,255,255,0.1); }
    .brand-logo { filter: brightness(0) invert(1); }
    .brand-title { font-size: 14px; font-weight: 600; }
    .brand-subtitle { font-size: 11px; opacity: 0.7; }
    .nav-link { padding: 0.75rem 1rem; border-left: 3px solid transparent; }
    .nav-link:hover { background: rgba(255,255,255,0.1); border-left-color: #007bff; }
    .nav-link.active { background: rgba(255,255,255,0.15); border-left-color: #007bff; }
  </style>
</head>
<body>
  <div id="admin-app" class="d-flex">
    <nav id="sidebar" class="sidebar bg-dark text-white">
      <div class="sidebar-brand d-flex align-items-center p-3">
        <img src="https://staff.vapeshed.co.nz/assets/img/ecigdis-logo.png" alt="The Vape Shed" height="28" class="mr-2 brand-logo" onerror="this.onerror=null;this.src='https://staff.vapeshed.co.nz/assets/img/ecigdis-white.png';">
        <div class="brand-text overflow-hidden">
          <div class="brand-title">CIS Control Panel</div>
          <div class="brand-subtitle">Consignments Module</div>
        </div>
      </div>
      <ul class="nav flex-column">
        <li class="nav-item"><a class="nav-link text-white" href="/"><i class="fa fa-home mr-2"></i>Main Dashboard</a></li>
        <li class="nav-item"><a class="nav-link text-white active" href="/modules/consignments/"><i class="fa fa-boxes mr-2"></i>Consignments Home</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="/modules/consignments/?route=transfer-manager"><i class="fa fa-exchange-alt mr-2"></i>Transfer Manager</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="/modules/consignments/?route=purchase-orders"><i class="fa fa-shopping-cart mr-2"></i>Purchase Orders</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="/modules/consignments/?route=stock-transfers"><i class="fa fa-truck mr-2"></i>Stock Transfers</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="/modules/consignments/?route=receiving"><i class="fa fa-inbox mr-2"></i>Receiving</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="/modules/consignments/?route=freight"><i class="fa fa-shipping-fast mr-2"></i>Freight</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="/modules/consignments/?route=queue-status"><i class="fa fa-tasks mr-2"></i>Queue Status</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="/modules/consignments/?route=ai-insights"><i class="fa fa-brain mr-2"></i>AI Insights</a></li>
        <li class="nav-item"><a class="nav-link text-white" href="/modules/consignments/?route=admin-controls"><i class="fa fa-cog mr-2"></i>Admin Controls</a></li>
      </ul>
      <div class="mt-auto p-3 small text-muted border-top border-secondary">
        <div><i class="fa fa-code mr-1"></i>Version: v3.0.0</div>
        <div><a href="/modules/installer.php" class="text-info"><i class="fa fa-download mr-1"></i>Module Installer</a></div>
      </div>
    </nav>
    <div class="flex-grow-1 d-flex flex-column min-vh-100">
      <header class="topbar navbar navbar-light bg-light border-bottom">
        <span class="navbar-brand mb-0 h6"><i class="fa fa-boxes mr-2"></i><?php echo htmlspecialchars($this->title); ?></span>
        <div class="ml-auto d-flex align-items-center">
          <a class="btn btn-sm btn-outline-primary mr-2" href="/modules/outlets/dashboard.php"><i class="fa fa-store mr-1"></i>Outlets</a>
          <a class="btn btn-sm btn-outline-success mr-2" href="/modules/business-intelligence/dashboard.php"><i class="fa fa-chart-line mr-1"></i>BI</a>
          <a class="btn btn-sm btn-outline-info mr-3" href="/modules/installer.php"><i class="fa fa-th mr-1"></i>All Modules</a>
          <span class="mr-3 text-muted small"><i class="fa fa-user-circle mr-1"></i><?php echo htmlspecialchars($_SESSION['username'] ?? 'Guest'); ?></span>
          <a class="btn btn-sm btn-outline-secondary" href="/logout.php">Logout</a>
        </div>
      </header>
      <main class="flex-grow-1 p-4">
        <?php
    }

    private function renderBreadcrumbs() {
        if (empty($this->breadcrumbs)) return;
        ?>
        <nav aria-label="breadcrumb" class="mb-3">
          <ol class="breadcrumb">
            <?php foreach ($this->breadcrumbs as $crumb): ?>
              <?php if (isset($crumb['active']) && $crumb['active']): ?>
                <li class="breadcrumb-item active"><?php echo htmlspecialchars($crumb['label']); ?></li>
              <?php else: ?>
                <li class="breadcrumb-item">
                  <a href="<?php echo htmlspecialchars($crumb['url']); ?>">
                    <?php if (isset($crumb['icon'])): ?><i class="fas <?php echo $crumb['icon']; ?> mr-1"></i><?php endif; ?>
                    <?php echo htmlspecialchars($crumb['label']); ?>
                  </a>
                </li>
              <?php endif; ?>
            <?php endforeach; ?>
          </ol>
        </nav>
        <?php
    }

    private function renderFooter() {
        ?>
      </main>
      <footer class="border-top bg-white p-2 text-center small text-muted">
        <span>&copy; <?php echo date('Y'); ?> Ecigdis Ltd — The Vape Shed CIS</span>
      </footer>
    </div>
  </div>
  <script src="https://code.jquery.com/jquery-3.5.1.min.js"></script>
  <script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.16.1/umd/popper.min.js"></script>
  <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js"></script>
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
</body>
</html>
        <?php
    }
}
