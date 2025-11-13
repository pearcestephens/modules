<?php
/**
 * =============================================================================
 * BREADCRUMB COMPONENT
 * =============================================================================
 *
 * Navigation trail showing user's current location in the application hierarchy.
 *
 * REQUIRED VARIABLES:
 * - $breadcrumb (array): Array of breadcrumb items
 *
 * BREADCRUMB ITEM STRUCTURE:
 * [
 *     'label' => 'Home',                    // Display text
 *     'url' => '/dashboard',                // Link URL (null for current page)
 *     'icon' => 'bi bi-house',              // Optional icon class
 *     'active' => false                     // Optional active state
 * ]
 *
 * USAGE EXAMPLE:
 * ```php
 * $breadcrumb = [
 *     ['label' => 'Home', 'url' => '/', 'icon' => 'bi bi-house'],
 *     ['label' => 'Sales', 'url' => '/sales', 'icon' => 'bi bi-cart'],
 *     ['label' => 'Invoices', 'url' => '/sales/invoices'],
 *     ['label' => 'Invoice #1234', 'url' => null] // Current page
 * ];
 * ```
 *
 * =============================================================================
 */

// Ensure breadcrumb data exists
$breadcrumb = $breadcrumb ?? [];

if (empty($breadcrumb)) {
    return; // Don't render empty breadcrumb
}
?>

<div class="vape-breadcrumb-wrapper">
    <ol class="vape-breadcrumb-list" itemscope itemtype="https://schema.org/BreadcrumbList">

        <?php foreach ($breadcrumb as $index => $item): ?>
            <?php
            $label = $item['label'] ?? 'Untitled';
            $url = $item['url'] ?? null;
            $icon = $item['icon'] ?? null;
            $isLast = ($index === count($breadcrumb) - 1);
            $isActive = $item['active'] ?? $isLast;
            ?>

            <li class="vape-breadcrumb-item <?= $isActive ? 'active' : '' ?>"
                itemprop="itemListElement"
                itemscope
                itemtype="https://schema.org/ListItem">

                <?php if (!$isActive && $url): ?>
                    <!-- Clickable breadcrumb link -->
                    <a href="<?= htmlspecialchars($url) ?>"
                       class="vape-breadcrumb-link"
                       itemprop="item">
                        <?php if ($icon): ?>
                            <i class="<?= htmlspecialchars($icon) ?>" aria-hidden="true"></i>
                        <?php endif; ?>
                        <span itemprop="name"><?= htmlspecialchars($label) ?></span>
                    </a>
                <?php else: ?>
                    <!-- Current page (not clickable) -->
                    <span class="vape-breadcrumb-current" itemprop="item">
                        <?php if ($icon): ?>
                            <i class="<?= htmlspecialchars($icon) ?>" aria-hidden="true"></i>
                        <?php endif; ?>
                        <span itemprop="name"><?= htmlspecialchars($label) ?></span>
                    </span>
                <?php endif; ?>

                <!-- Schema.org position -->
                <meta itemprop="position" content="<?= $index + 1 ?>">

                <!-- Separator (not shown for last item) -->
                <?php if (!$isLast): ?>
                    <i class="vape-breadcrumb-separator bi bi-chevron-right" aria-hidden="true"></i>
                <?php endif; ?>
            </li>

        <?php endforeach; ?>

    </ol>
</div>

<style>
/* Breadcrumb Styles */
.vape-breadcrumb-wrapper {
    background: var(--vape-white);
    border-bottom: 1px solid var(--vape-gray-200);
    padding: var(--vape-space-3) var(--vape-space-6);
    height: var(--vape-breadcrumb-height);
    display: flex;
    align-items: center;
}

.vape-breadcrumb-list {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    list-style: none;
    margin: 0;
    padding: 0;
    gap: var(--vape-space-2);
}

.vape-breadcrumb-item {
    display: flex;
    align-items: center;
    gap: var(--vape-space-2);
    font-size: var(--vape-text-sm);
    color: var(--vape-gray-600);
}

.vape-breadcrumb-link {
    display: flex;
    align-items: center;
    gap: var(--vape-space-1-5);
    color: var(--vape-gray-600);
    text-decoration: none;
    padding: var(--vape-space-1) var(--vape-space-2);
    border-radius: var(--vape-radius-base);
    transition: var(--vape-transition-colors);
}

.vape-breadcrumb-link:hover {
    color: var(--vape-primary-600);
    background: var(--vape-primary-50);
}

.vape-breadcrumb-link:focus {
    outline: 2px solid var(--vape-primary-500);
    outline-offset: 2px;
}

.vape-breadcrumb-current {
    display: flex;
    align-items: center;
    gap: var(--vape-space-1-5);
    color: var(--vape-gray-900);
    font-weight: var(--vape-font-medium);
    padding: var(--vape-space-1) var(--vape-space-2);
}

.vape-breadcrumb-item.active .vape-breadcrumb-current {
    color: var(--vape-primary-600);
}

.vape-breadcrumb-separator {
    color: var(--vape-gray-400);
    font-size: var(--vape-text-xs);
}

.vape-breadcrumb-link i,
.vape-breadcrumb-current i {
    font-size: var(--vape-icon-sm);
}

/* Responsive: Mobile */
@media (max-width: 768px) {
    .vape-breadcrumb-wrapper {
        padding: var(--vape-space-2) var(--vape-space-4);
    }

    /* Hide all but last 2 items on mobile */
    .vape-breadcrumb-item:not(:nth-last-child(-n+2)) {
        display: none;
    }

    /* Show ellipsis for hidden items */
    .vape-breadcrumb-list::before {
        content: '···';
        color: var(--vape-gray-400);
        padding: 0 var(--vape-space-2);
    }
}

/* Print styles */
@media print {
    .vape-breadcrumb-wrapper {
        border-bottom: 1px solid var(--vape-black);
        background: transparent;
    }

    .vape-breadcrumb-separator {
        content: '>';
    }
}
</style>
