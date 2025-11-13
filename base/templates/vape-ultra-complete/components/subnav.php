<?php
/**
 * =============================================================================
 * SUB-NAVIGATION COMPONENT
 * =============================================================================
 *
 * Module-level navigation menu shown below breadcrumb.
 * Allows modules to define their own internal navigation structure.
 *
 * REQUIRED VARIABLES:
 * - $subnav (array): Array of sub-navigation items
 *
 * SUB-NAV ITEM STRUCTURE:
 * [
 *     'label' => 'Dashboard',               // Display text
 *     'url' => '/sales',                    // Link URL
 *     'icon' => 'bi bi-house',              // Optional icon
 *     'active' => true,                     // Is current page
 *     'badge' => [                          // Optional badge
 *         'count' => 5,
 *         'type' => 'primary'               // primary, success, danger, warning, info
 *     ],
 *     'disabled' => false                   // Optional disabled state
 * ]
 *
 * CONFIGURATION:
 * - $subnavStyle (string): 'horizontal' (default) or 'vertical'
 * - $subnavAlign (string): 'left', 'center', 'right' (for horizontal)
 *
 * USAGE EXAMPLE:
 * ```php
 * $subnav = [
 *     ['label' => 'Dashboard', 'url' => '/sales', 'icon' => 'bi bi-house', 'active' => true],
 *     ['label' => 'Reports', 'url' => '/sales/reports', 'icon' => 'bi bi-graph-up'],
 *     ['label' => 'Invoices', 'url' => '/sales/invoices', 'icon' => 'bi bi-receipt', 'badge' => ['count' => 5, 'type' => 'danger']],
 *     ['label' => 'Settings', 'url' => '/sales/settings', 'icon' => 'bi bi-gear']
 * ];
 * ```
 *
 * =============================================================================
 */

// Ensure subnav data exists
$subnav = $subnav ?? [];

if (empty($subnav)) {
    return; // Don't render empty subnav
}

// Configuration
$subnavStyle = $subnavStyle ?? 'horizontal'; // horizontal or vertical
$subnavAlign = $subnavAlign ?? 'left'; // left, center, right (for horizontal)
?>

<div class="vape-subnav-wrapper vape-subnav-<?= $subnavStyle ?> vape-subnav-align-<?= $subnavAlign ?>">
    <nav class="vape-subnav-container">
        <ul class="vape-subnav-list" role="menubar">

            <?php foreach ($subnav as $index => $item): ?>
                <?php
                $label = $item['label'] ?? 'Untitled';
                $url = $item['url'] ?? '#';
                $icon = $item['icon'] ?? null;
                $isActive = $item['active'] ?? false;
                $badge = $item['badge'] ?? null;
                $disabled = $item['disabled'] ?? false;
                ?>

                <li class="vape-subnav-item <?= $isActive ? 'active' : '' ?> <?= $disabled ? 'disabled' : '' ?>"
                    role="none">

                    <?php if (!$disabled): ?>
                        <!-- Active subnav link -->
                        <a href="<?= htmlspecialchars($url) ?>"
                           class="vape-subnav-link"
                           role="menuitem"
                           <?= $isActive ? 'aria-current="page"' : '' ?>>

                            <?php if ($icon): ?>
                                <i class="<?= htmlspecialchars($icon) ?> vape-subnav-icon" aria-hidden="true"></i>
                            <?php endif; ?>

                            <span class="vape-subnav-label"><?= htmlspecialchars($label) ?></span>

                            <?php if ($badge): ?>
                                <span class="vape-subnav-badge vape-badge vape-badge-<?= htmlspecialchars($badge['type'] ?? 'primary') ?>">
                                    <?= htmlspecialchars($badge['count'] ?? '') ?>
                                </span>
                            <?php endif; ?>

                            <?php if ($isActive): ?>
                                <span class="vape-subnav-indicator" aria-hidden="true"></span>
                            <?php endif; ?>
                        </a>
                    <?php else: ?>
                        <!-- Disabled subnav item -->
                        <span class="vape-subnav-link disabled"
                              role="menuitem"
                              aria-disabled="true">

                            <?php if ($icon): ?>
                                <i class="<?= htmlspecialchars($icon) ?> vape-subnav-icon" aria-hidden="true"></i>
                            <?php endif; ?>

                            <span class="vape-subnav-label"><?= htmlspecialchars($label) ?></span>
                        </span>
                    <?php endif; ?>

                </li>

            <?php endforeach; ?>

        </ul>

        <!-- Mobile menu toggle (for vertical style on mobile) -->
        <button class="vape-subnav-toggle"
                type="button"
                aria-label="Toggle navigation menu"
                aria-expanded="false"
                aria-controls="subnavList">
            <i class="bi bi-list"></i>
        </button>
    </nav>
</div>

<style>
/* ===========================================================================
   SUB-NAVIGATION STYLES
   =========================================================================== */

/* Wrapper */
.vape-subnav-wrapper {
    background: var(--vape-white);
    border-bottom: 1px solid var(--vape-gray-200);
    min-height: var(--vape-subnav-height);
    display: flex;
    align-items: center;
}

.vape-subnav-container {
    width: 100%;
    padding: 0 var(--vape-space-6);
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.vape-subnav-list {
    display: flex;
    align-items: center;
    list-style: none;
    margin: 0;
    padding: 0;
    gap: var(--vape-space-1);
}

/* Mobile toggle (hidden on desktop) */
.vape-subnav-toggle {
    display: none;
    background: none;
    border: none;
    font-size: var(--vape-text-xl);
    color: var(--vape-gray-600);
    cursor: pointer;
    padding: var(--vape-space-2);
    border-radius: var(--vape-radius-base);
    transition: var(--vape-transition-colors);
}

.vape-subnav-toggle:hover {
    background: var(--vape-gray-100);
    color: var(--vape-gray-900);
}

/* ===========================================================================
   HORIZONTAL STYLE (Default)
   =========================================================================== */
.vape-subnav-horizontal .vape-subnav-list {
    flex-direction: row;
}

.vape-subnav-horizontal.vape-subnav-align-center .vape-subnav-list {
    justify-content: center;
}

.vape-subnav-horizontal.vape-subnav-align-right .vape-subnav-list {
    justify-content: flex-end;
}

.vape-subnav-horizontal .vape-subnav-item {
    position: relative;
}

.vape-subnav-horizontal .vape-subnav-link {
    display: flex;
    align-items: center;
    gap: var(--vape-space-2);
    padding: var(--vape-space-3) var(--vape-space-4);
    color: var(--vape-gray-700);
    text-decoration: none;
    font-size: var(--vape-text-sm);
    font-weight: var(--vape-font-medium);
    border-radius: var(--vape-radius-md);
    transition: var(--vape-transition-colors);
    position: relative;
    white-space: nowrap;
}

.vape-subnav-horizontal .vape-subnav-link:hover {
    background: var(--vape-gray-100);
    color: var(--vape-gray-900);
}

.vape-subnav-horizontal .vape-subnav-link:focus {
    outline: 2px solid var(--vape-primary-500);
    outline-offset: 2px;
}

.vape-subnav-horizontal .vape-subnav-item.active .vape-subnav-link {
    color: var(--vape-primary-600);
    background: var(--vape-primary-50);
}

/* Active indicator (bottom border) */
.vape-subnav-horizontal .vape-subnav-indicator {
    position: absolute;
    bottom: calc(var(--vape-space-3) * -1);
    left: var(--vape-space-4);
    right: var(--vape-space-4);
    height: 3px;
    background: var(--vape-primary-500);
    border-radius: var(--vape-radius-full);
}

/* ===========================================================================
   VERTICAL STYLE
   =========================================================================== */
.vape-subnav-vertical {
    background: var(--vape-gray-50);
    border-right: 1px solid var(--vape-gray-200);
    border-bottom: none;
    min-height: 100%;
    width: 240px;
}

.vape-subnav-vertical .vape-subnav-container {
    flex-direction: column;
    align-items: stretch;
    padding: var(--vape-space-4);
}

.vape-subnav-vertical .vape-subnav-list {
    flex-direction: column;
    align-items: stretch;
    width: 100%;
    gap: var(--vape-space-1);
}

.vape-subnav-vertical .vape-subnav-link {
    display: flex;
    align-items: center;
    gap: var(--vape-space-3);
    padding: var(--vape-space-3) var(--vape-space-4);
    color: var(--vape-gray-700);
    text-decoration: none;
    font-size: var(--vape-text-sm);
    font-weight: var(--vape-font-medium);
    border-radius: var(--vape-radius-md);
    transition: var(--vape-transition-colors);
}

.vape-subnav-vertical .vape-subnav-link:hover {
    background: var(--vape-white);
    color: var(--vape-gray-900);
}

.vape-subnav-vertical .vape-subnav-item.active .vape-subnav-link {
    color: var(--vape-primary-600);
    background: var(--vape-white);
    box-shadow: var(--vape-shadow-sm);
}

/* Active indicator (left border) */
.vape-subnav-vertical .vape-subnav-indicator {
    position: absolute;
    left: 0;
    top: var(--vape-space-3);
    bottom: var(--vape-space-3);
    width: 3px;
    background: var(--vape-primary-500);
    border-radius: var(--vape-radius-full);
}

/* ===========================================================================
   COMMON ELEMENTS
   =========================================================================== */

/* Icon */
.vape-subnav-icon {
    font-size: var(--vape-icon-base);
    flex-shrink: 0;
}

/* Label */
.vape-subnav-label {
    flex: 1;
}

/* Badge */
.vape-subnav-badge {
    font-size: var(--vape-text-xs);
    padding: var(--vape-space-0-5) var(--vape-space-2);
    border-radius: var(--vape-radius-full);
    font-weight: var(--vape-font-semibold);
    min-width: 20px;
    text-align: center;
}

.vape-badge-primary {
    background: var(--vape-primary-500);
    color: var(--vape-white);
}

.vape-badge-success {
    background: var(--vape-success-500);
    color: var(--vape-white);
}

.vape-badge-danger {
    background: var(--vape-error-500);
    color: var(--vape-white);
}

.vape-badge-warning {
    background: var(--vape-warning-500);
    color: var(--vape-white);
}

.vape-badge-info {
    background: var(--vape-info-500);
    color: var(--vape-white);
}

/* Disabled state */
.vape-subnav-item.disabled .vape-subnav-link {
    color: var(--vape-gray-400);
    cursor: not-allowed;
    pointer-events: none;
    opacity: 0.6;
}

/* ===========================================================================
   RESPONSIVE: MOBILE
   =========================================================================== */
@media (max-width: 768px) {
    .vape-subnav-container {
        padding: 0 var(--vape-space-4);
    }

    /* Show mobile toggle */
    .vape-subnav-toggle {
        display: flex;
    }

    /* Hide menu by default on mobile */
    .vape-subnav-list {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        background: var(--vape-white);
        border-bottom: 1px solid var(--vape-gray-200);
        box-shadow: var(--vape-shadow-md);
        flex-direction: column;
        padding: var(--vape-space-4);
        gap: var(--vape-space-2);
        z-index: var(--vape-z-dropdown);
    }

    /* Show menu when expanded */
    .vape-subnav-wrapper.expanded .vape-subnav-list {
        display: flex;
    }

    /* Stack items vertically on mobile */
    .vape-subnav-horizontal .vape-subnav-link {
        width: 100%;
    }

    /* Hide bottom indicator on mobile */
    .vape-subnav-horizontal .vape-subnav-indicator {
        display: none;
    }
}

/* ===========================================================================
   PRINT STYLES
   =========================================================================== */
@media print {
    .vape-subnav-wrapper {
        display: none; /* Hide sub-nav in print */
    }
}
</style>

<script>
// Mobile sub-nav toggle
document.addEventListener('DOMContentLoaded', function() {
    const toggle = document.querySelector('.vape-subnav-toggle');
    const wrapper = document.querySelector('.vape-subnav-wrapper');

    if (toggle && wrapper) {
        toggle.addEventListener('click', function() {
            const isExpanded = wrapper.classList.toggle('expanded');
            toggle.setAttribute('aria-expanded', isExpanded);
        });
    }
});
</script>
