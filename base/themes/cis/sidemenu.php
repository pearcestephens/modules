<?php
// Compact, less-annoying sidemenu
?>
<?php
// Bring over original dynamic menu logic (using $pdo, $_SESSION['user_id'])
$userID = isset($_SESSION["user_id"]) ? (int)$_SESSION["user_id"] : 0;

$mainCategories = [];
try {
    $stmt = $pdo->prepare("\n        SELECT id, title, active, show_title_nav_bar, sort_order\n        FROM navigation\n        WHERE active = 1\n        ORDER BY sort_order ASC\n    ");
    $stmt->execute();
    $mainCategories = $stmt->fetchAll(PDO::FETCH_OBJ);
} catch (Exception $e) {
    error_log("Error loading navigation: " . $e->getMessage());
    $mainCategories = [];
}

$permissionItems = [];
if ($userID > 0) {
    try {
        $stmt = $pdo->prepare("\n            SELECT DISTINCT p.id, p.name, p.filename, p.navigation_id, p.show_in_sidemenu\n            FROM permissions p\n            INNER JOIN user_permissions up ON p.id = up.permission_id\n            WHERE up.user_id = ? AND p.show_in_sidemenu = 1\n            ORDER BY p.id ASC\n        ");
        $stmt->execute([$userID]);
        $permissionItems = $stmt->fetchAll(PDO::FETCH_OBJ);
    } catch (Exception $e) {
        error_log("Error loading permissions: " . $e->getMessage());
        $permissionItems = [];
    }
}

$iconMap = [
    'My Store Overview' => 'fa-store',
    'Store Banking' => 'fa-bank',
    'Banking Audit' => 'fa-audit',
    'Transaction Viewer' => 'fa-list',
    'Flagged Products' => 'fa-flag',
    'Security Cameras' => 'fa-video',
    'Stock Transfers' => 'fa-boxes',
    'Purchase Orders' => 'fa-receipt',
    'Juice Transfers' => 'fa-flask',
    'Staff Instore Transfers' => 'fa-people-arrows',
    'Online Orders' => 'fa-shopping-cart',
    'Online Customers' => 'fa-users',
    'Online Products' => 'fa-cube',
    'Online Products To Add' => 'fa-plus-square',
    'Website Page Manager' => 'fa-file-text',
    'Wholesale Accounts' => 'fa-building',
    'Suppliers' => 'fa-truck',
    'Staff Management' => 'fa-user-tie',
    'Reporting' => 'fa-chart-bar',
    'Settings' => 'fa-cog',
    'Configuration' => 'fa-sliders',
    'Dashboard' => 'fa-gauge',
    'View Dashboard' => 'fa-gauge',
];

if (!function_exists('getMenuIcon')) {
    function getMenuIcon($itemName) {
        global $iconMap;
        if (isset($iconMap[$itemName])) return $iconMap[$itemName];
        foreach ($iconMap as $key => $icon) {
            if (stripos($itemName, $key) !== false) return $icon;
        }
        return 'fa-link';
    }
}

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
?>

<div class="sidebar sidebar-dark sidebar-fixed" id="sidebar" style="width: 256px;">
  <nav class="sidebar-nav">
    <ul class="nav">
      <li class="nav-item open">
        <a class="nav-link active" href="/index.php"><i class="fas fa-gauge" style="width: 20px; margin-right: 10px;"></i>View Dashboard</a>
      </li>
      <?php foreach ($organisedCats as $c): if (count($c->itemsArray) > 0): ?>
        <li class="nav-title"><?php echo htmlspecialchars($c->title); ?></li>
        <?php foreach ($c->itemsArray as $i): $icon = getMenuIcon($i->name); $url='/' . ltrim($i->filename,'/'); ?>
          <li class="nav-item">
            <a class="nav-link" href="<?php echo htmlspecialchars($url); ?>">
              <i class="fas <?php echo $icon; ?>" style="width: 20px; margin-right: 10px;"></i>
              <?php echo htmlspecialchars($i->name); ?>
            </a>
          </li>
        <?php endforeach; ?>
      <?php endif; endforeach; ?>
    </ul>
  </nav>
</div>
