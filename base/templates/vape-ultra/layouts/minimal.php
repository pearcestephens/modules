<?php
/**
 * Minimal Layout
 *
 * Simple layout with just header and content
 * Good for forms, login pages, single-purpose pages
 */
?>

<div class="app-minimal">
    <!-- Simple Header -->
    <header class="minimal-header">
        <?php include __DIR__ . '/../components/header-minimal.php'; ?>
    </header>

    <!-- Content Only -->
    <main class="minimal-content">
        <div class="container-fluid">
            <?php
            if (isset($moduleContent) && $moduleContent) {
                echo $moduleContent;
            } elseif (isset($contentFile) && file_exists($contentFile)) {
                include $contentFile;
            }
            ?>
        </div>
    </main>
</div>
