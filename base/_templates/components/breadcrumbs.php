<!-- Breadcrumbs Component -->
<?php if (!empty($breadcrumbs) && is_array($breadcrumbs)): ?>
<nav aria-label="breadcrumb" class="cis-breadcrumbs">
    <ol class="breadcrumb">
        <!-- Home -->
        <li class="breadcrumb-item">
            <a href="/index.php">
                <i class="fas fa-home"></i> Home
            </a>
        </li>
        
        <!-- Dynamic Breadcrumbs -->
        <?php 
        $lastIndex = count($breadcrumbs) - 1;
        foreach ($breadcrumbs as $index => $crumb): 
            $isLast = ($index === $lastIndex);
        ?>
            <li class="breadcrumb-item <?= $isLast ? 'active' : '' ?>" <?= $isLast ? 'aria-current="page"' : '' ?>>
                <?php if (!$isLast && !empty($crumb['url'])): ?>
                    <a href="<?= htmlspecialchars($crumb['url']) ?>">
                        <?= htmlspecialchars($crumb['label']) ?>
                    </a>
                <?php else: ?>
                    <?= htmlspecialchars($crumb['label']) ?>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ol>
</nav>
<?php endif; ?>

<style>
    .cis-breadcrumbs {
        background-color: var(--cis-gray-100);
        border-bottom: 1px solid var(--cis-border-color);
        padding: 0.75rem 1.5rem;
    }
    
    .breadcrumb {
        display: flex;
        flex-wrap: wrap;
        list-style: none;
        margin: 0;
        padding: 0;
        font-size: var(--cis-font-size-sm);
    }
    
    .breadcrumb-item {
        display: flex;
        align-items: center;
    }
    
    .breadcrumb-item + .breadcrumb-item::before {
        content: "/";
        padding: 0 0.5rem;
        color: var(--cis-gray-500);
    }
    
    .breadcrumb-item a {
        color: var(--cis-primary);
        text-decoration: none;
        transition: color 0.2s;
    }
    
    .breadcrumb-item a:hover {
        color: var(--cis-primary-dark);
        text-decoration: underline;
    }
    
    .breadcrumb-item.active {
        color: var(--cis-gray-600);
    }
    
    .breadcrumb-item i {
        margin-right: 0.25rem;
    }
    
    @media (max-width: 768px) {
        .cis-breadcrumbs {
            padding: 0.5rem 1rem;
        }
        
        .breadcrumb {
            font-size: var(--cis-font-size-xs);
        }
        
        .breadcrumb-item + .breadcrumb-item::before {
            padding: 0 0.25rem;
        }
    }
</style>

<!-- Usage Example:
<?php
$breadcrumbs = [
    ['label' => 'Inventory', 'url' => '/modules/inventory/'],
    ['label' => 'Stock Count', 'url' => '/modules/inventory/count.php'],
    ['label' => 'Edit Count'] // Last item (no URL)
];
?>
-->
