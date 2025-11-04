<?php
/**
 * CIS Classic Theme - Modern Responsive Sidebar
 *
 * Dynamic navigation sidebar with database-driven menu items
 * and user permission-based visibility
 */

// Get user ID from session
$userID = isset($_SESSION["userID"]) ? (int)$_SESSION["userID"] : 0;

// Get PDO connection (assumes it's available globally or in session)
global $pdo;
if (!isset($pdo)) {
    // Try to get PDO from common locations
    if (defined('DB_HOST') && defined('DB_NAME') && defined('DB_USER') && defined('DB_PASS')) {
        try {
            $pdo = new PDO(
                "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
                DB_USER,
                DB_PASS,
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
            );
        } catch (PDOException $e) {
            error_log("CIS Classic Theme: Database connection failed - " . $e->getMessage());
            $pdo = null;
        }
    }
}

// Query navigation categories
$mainCategories = [];
if ($pdo) {
    try {
        $stmt = $pdo->prepare("
            SELECT id, title, active, show_title_nav_bar, sort_order
            FROM navigation
            WHERE active = 1
            ORDER BY sort_order ASC
        ");
        $stmt->execute();
        $mainCategories = $stmt->fetchAll(PDO::FETCH_OBJ);
    } catch (Exception $e) {
        error_log("CIS Classic Theme: Error loading navigation - " . $e->getMessage());
        $mainCategories = [];
    }
}

// Query permission items for this user
$permissionItems = [];
if ($pdo && $userID > 0) {
    try {
        $stmt = $pdo->prepare("
            SELECT DISTINCT p.id, p.name, p.filename, p.navigation_id, p.show_in_sidemenu
            FROM permissions p
            INNER JOIN user_permissions up ON p.id = up.permission_id
            WHERE up.user_id = ? AND p.show_in_sidemenu = 1
            ORDER BY p.id ASC
        ");
        $stmt->execute([$userID]);
        $permissionItems = $stmt->fetchAll(PDO::FETCH_OBJ);
    } catch (Exception $e) {
        error_log("CIS Classic Theme: Error loading permissions - " . $e->getMessage());
        $permissionItems = [];
    }
}

// Icon mapping for menu items (FontAwesome 6.7.1)
$iconMap = [
    'Dashboard' => 'fa-gauge-high',
    'View Dashboard' => 'fa-gauge-high',
    'My Store Overview' => 'fa-store',
    'Store Banking' => 'fa-building-columns',
    'Banking Audit' => 'fa-file-invoice-dollar',
    'Transaction Viewer' => 'fa-list',
    'Flagged Products' => 'fa-flag',
    'Security Cameras' => 'fa-video',
    'Stock Transfers' => 'fa-boxes-stacked',
    'Transfer Manager' => 'fa-truck-ramp-box',
    'Transfers' => 'fa-truck-ramp-box',
    'Purchase Orders' => 'fa-receipt',
    'Juice Transfers' => 'fa-flask',
    'Staff Instore Transfers' => 'fa-people-arrows',
    'Online Orders' => 'fa-cart-shopping',
    'Online Customers' => 'fa-users',
    'Online Products' => 'fa-cube',
    'Online Products To Add' => 'fa-square-plus',
    'Website Page Manager' => 'fa-file-lines',
    'Wholesale Accounts' => 'fa-building',
    'Suppliers' => 'fa-truck',
    'Staff Management' => 'fa-user-tie',
    'Payroll' => 'fa-money-bill-wave',
    'Human Resources' => 'fa-people-group',
    'Reporting' => 'fa-chart-column',
    'Settings' => 'fa-gear',
    'Configuration' => 'fa-sliders',
];

// Function to get icon for menu item
if (!function_exists('getMenuIcon')) {
    function getMenuIcon($itemName, $iconMap) {
        // Check exact match
        if (isset($iconMap[$itemName])) {
            return $iconMap[$itemName];
        }

        // Check partial match
        foreach ($iconMap as $key => $icon) {
            if (stripos($itemName, $key) !== false) {
                return $icon;
            }
        }

        // Default icon
        return 'fa-circle';
    }
}

// Organize permissions by navigation category
$organisedCats = [];
foreach ($mainCategories as $c) {
    $c->itemsArray = [];
    foreach ($permissionItems as $pi) {
        if ($c->id == $pi->navigation_id && $pi->show_in_sidemenu == 1) {
            $c->itemsArray[] = $pi;
        }
    }
    $organisedCats[] = $c;
}

// Get current page for active highlighting
$currentPage = $current_page ?? '';
?>

<div class="sidebar">
  <nav class="sidebar-nav">
    <ul class="nav">
      <li class="nav-item">
        <a class="nav-link <?php echo ($currentPage === 'dashboard') ? 'active' : ''; ?>" href="/dashboard.php">
          <i class="nav-icon icon-speedometer"></i> Dashboard
        </a>
      </li>

      <?php foreach ($organisedCats as $c): ?>
        <?php if (count($c->itemsArray) > 0): ?>
          <?php if ($c->show_title_nav_bar == 1): ?>
            <li class="nav-title"><?php echo htmlspecialchars($c->title); ?></li>
          <?php endif; ?>

          <?php foreach ($c->itemsArray as $i): ?>
            <?php
            $icon = getMenuIcon($i->name, $iconMap);
            $url = '/' . ltrim($i->filename, '/');
            $isActive = ($currentPage === basename($i->filename, '.php'));
            ?>
            <li class="nav-item">
              <a class="nav-link <?php echo $isActive ? 'active' : ''; ?>" href="<?php echo htmlspecialchars($url); ?>">
                <i class="nav-icon fas <?php echo $icon; ?>"></i> <?php echo htmlspecialchars($i->name); ?>
              </a>
            </li>
          <?php endforeach; ?>
        <?php endif; ?>
      <?php endforeach; ?>

      <!-- Developer Section -->
      <li class="nav-title">DEVELOPER</li>
      <li class="nav-item">
        <a class="nav-link" href="/modules/base/_templates/themes/cis-classic/examples/ui-showcase.php">
          <i class="nav-icon fas fa-palette"></i> UI Showcase
          <span class="badge badge-primary">All Components</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="/modules/base/_assets/js/demo.php">
          <i class="nav-icon fas fa-flask"></i> JS Stack Demo
          <span class="badge badge-success">New</span>
        </a>
      </li>
      <li class="nav-item">
        <a class="nav-link" href="/modules/base/_assets/js/test-errors.php">
          <i class="nav-icon fas fa-bug"></i> Error Test
        </a>
      </li>
    </ul>
  </nav>
  <button class="sidebar-minimizer brand-minimizer" type="button"></button>
</div>
