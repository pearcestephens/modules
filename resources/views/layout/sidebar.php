<?php
/**
 * File: resources/views/layout/sidebar.php
 * Purpose: Reusable sidebar navigation for administrative layouts.
 * Author: GitHub Copilot
 * Last Modified: 2025-11-02
 * Dependencies: Bootstrap 5
 */

declare(strict_types=1);

$navItems = isset($navItems) && is_array($navItems) ? $navItems : [];
$currentEndpoint = isset($currentEndpoint) ? (string)$currentEndpoint : '';
?>
        <aside class="col-12 col-md-3 col-xl-2 px-sm-2 px-0 bg-body-tertiary border-end min-vh-100 vs-sidebar">
            <div class="d-flex flex-column align-items-stretch px-3 pt-3 gap-2">
                <?php if (!empty($navItems)): ?>
                    <nav class="nav nav-pills flex-column gap-1">
                        <?php foreach ($navItems as $item):
                            $label = htmlspecialchars((string)($item['label'] ?? ''), ENT_QUOTES, 'UTF-8');
                            $endpoint = htmlspecialchars((string)($item['endpoint'] ?? ''), ENT_QUOTES, 'UTF-8');
                            $icon = htmlspecialchars((string)($item['icon'] ?? ''), ENT_QUOTES, 'UTF-8');
                            $active = $currentEndpoint !== '' && $currentEndpoint === ($item['endpoint'] ?? '');
                        ?>
                            <a class="nav-link d-flex align-items-center gap-2 <?= $active ? 'active' : ''; ?>"
                               href="?endpoint=<?= $endpoint; ?>"
                               aria-current="<?= $active ? 'page' : 'false'; ?>">
                                <?php if ($icon !== ''): ?>
                                    <span class="vs-sidebar__icon" aria-hidden="true">
                                        <i class="<?= $icon; ?>"></i>
                                    </span>
                                <?php endif; ?>
                                <span><?= $label; ?></span>
                            </a>
                        <?php endforeach; ?>
                    </nav>
                <?php else: ?>
                    <div class="alert alert-info small mb-0" role="status">
                        Navigation will appear here once configured.
                    </div>
                <?php endif; ?>
            </div>
        </aside>
        <main id="main-content" class="col py-4 vs-main" tabindex="-1">
