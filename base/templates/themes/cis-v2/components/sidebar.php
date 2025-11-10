<?php
// Dynamic sidebar, driven by navigation + permissions tables
global $pdo;
if (!isset($pdo)) { $pdo = \CIS\Base\Database::pdo(); }
$userID = isset($_SESSION['userID']) ? (int)$_SESSION['userID'] : 0;

$mainCategories = [];
try {
	$stmt = $pdo->prepare("SELECT id, title, active, show_title_nav_bar, sort_order FROM navigation WHERE active = 1 ORDER BY sort_order ASC");
	$stmt->execute();
	$mainCategories = $stmt->fetchAll(PDO::FETCH_OBJ);
} catch (Exception $e) { error_log('nav load: '.$e->getMessage()); $mainCategories = []; }

$permissionItems = [];
if ($userID > 0) {
	try {
		$stmt = $pdo->prepare("SELECT DISTINCT p.id, p.name, p.filename, p.navigation_id, p.show_in_sidemenu FROM permissions p INNER JOIN user_permissions up ON p.id = up.permission_id WHERE up.user_id = ? AND p.show_in_sidemenu = 1 ORDER BY p.id ASC");
		$stmt->execute([$userID]);
		$permissionItems = $stmt->fetchAll(PDO::FETCH_OBJ);
	} catch (Exception $e) { error_log('perm load: '.$e->getMessage()); $permissionItems = []; }
}

$iconMap = [
	'Dashboard' => 'fa-solid fa-gauge',
	'View Dashboard' => 'fa-solid fa-gauge',
	'Consignments' => 'fa-solid fa-diagram-project',
	'Stock Transfers' => 'fa-solid fa-boxes-stacked',
	'Purchase Orders' => 'fa-solid fa-file-invoice',
	'Suppliers' => 'fa-solid fa-truck',
	'Staff' => 'fa-solid fa-users',
	'Reports' => 'fa-solid fa-chart-line',
	'Settings' => 'fa-solid fa-gear'
];
if (!function_exists('cisv2_menu_icon')) {
	function cisv2_menu_icon($name) {
		global $iconMap;
		foreach ($iconMap as $key=>$icon) { if (stripos($name, $key) !== false) return $icon; }
		return 'fa-regular fa-square';
	}
}

$organisedCats = [];
foreach ($mainCategories as $c) {
	$c->itemsArray = [];
	foreach ($permissionItems as $pi) {
		if ($c->id == $pi->navigation_id && $pi->show_in_sidemenu == 1) { $c->itemsArray[] = $pi; }
	}
	$organisedCats[] = $c;
}
?>

<aside class="cisv2-sidebar" data-simplebar>
	<div class="d-flex align-items-center mb-2 px-1">
		<a href="/" class="brand text-decoration-none d-flex align-items-center gap-2">
			<img src="/assets/img/brand/vapeshed-emblem.png" alt="Logo" height="22" />
			<strong>CIS</strong>
		</a>
	</div>

	<div class="mb-2">
		<a class="nav-link" href="/index.php"><i class="icon fa-solid fa-gauge"></i><span>View Dashboard</span></a>
	</div>

	<?php foreach ($organisedCats as $c): if (count($c->itemsArray) > 0): ?>
		<div class="section-title"><?= htmlspecialchars($c->title); ?></div>
		<?php foreach ($c->itemsArray as $i): $icon = cisv2_menu_icon($i->name); $url = '/' . ltrim($i->filename,'/'); ?>
			<a class="nav-link" href="<?= htmlspecialchars($url); ?>">
				<i class="icon <?= $icon; ?>"></i>
				<span><?= htmlspecialchars($i->name); ?></span>
			</a>
		<?php endforeach; ?>
	<?php endif; endforeach; ?>

	<div class="mt-3 small text-muted-2 px-2">CIS v2</div>
</aside>
<div class="cisv2-overlay"></div>
