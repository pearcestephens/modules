<?php
/**
 * Main Sidebar Navigation
 *
 * Modules can extend this by adding their own nav items
 */

$currentPath = $_SERVER['REQUEST_URI'] ?? '/';
$navItems = [
    'main' => [
        'title' => 'Main',
        'items' => [
            ['icon' => 'grid-fill', 'label' => 'Dashboard', 'href' => '/', 'badge' => null],
            ['icon' => 'activity', 'label' => 'Activity', 'href' => '/activity', 'badge' => 12],
            ['icon' => 'graph-up', 'label' => 'Analytics', 'href' => '/analytics', 'badge' => null],
        ]
    ],
    'inventory' => [
        'title' => 'Inventory',
        'items' => [
            ['icon' => 'box-seam', 'label' => 'Products', 'href' => '/products', 'badge' => null],
            ['icon' => 'arrow-left-right', 'label' => 'Transfers', 'href' => '/transfers', 'badge' => 5],
            ['icon' => 'clipboard-check', 'label' => 'Purchase Orders', 'href' => '/purchase-orders', 'badge' => null],
        ]
    ],
    'stores' => [
        'title' => 'Stores',
        'items' => [
            ['icon' => 'shop', 'label' => 'All Outlets', 'href' => '/stores', 'badge' => null],
            ['icon' => 'people', 'label' => 'Staff', 'href' => '/staff', 'badge' => null],
        ]
    ],
];

// Allow modules to inject their nav items
if (isset($moduleNavItems) && is_array($moduleNavItems)) {
    $navItems = array_merge($navItems, $moduleNavItems);
}
?>

<div class="sidebar-inner">
    <?php foreach ($navItems as $section): ?>
    <div class="sidebar-section">
        <div class="sidebar-section-title"><?= htmlspecialchars($section['title']) ?></div>
        <ul class="sidebar-menu">
            <?php foreach ($section['items'] as $item): ?>
            <li class="sidebar-menu-item">
                <a href="<?= htmlspecialchars($item['href']) ?>"
                   class="sidebar-menu-link <?= strpos($currentPath, $item['href']) === 0 && $item['href'] !== '/' ? 'active' : '' ?>">
                    <i class="bi bi-<?= htmlspecialchars($item['icon']) ?>"></i>
                    <span><?= htmlspecialchars($item['label']) ?></span>
                    <?php if ($item['badge']): ?>
                    <span class="sidebar-menu-badge"><?= $item['badge'] ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endforeach; ?>
</div>
