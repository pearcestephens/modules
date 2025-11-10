<?php
// Demo entry for cis-v2 theme
$pageTitle = 'CIS v2 Demo';
$extraHead = '';

require __DIR__ . '/components/head.php';
require __DIR__ . '/components/header.php';
echo '<div class="cisv2-app d-flex">';
require __DIR__ . '/components/sidebar.php';
echo '<main id="cisv2-main" class="flex-fill">';
require __DIR__ . '/layouts/dashboard.php';
echo '</main></div>';
require __DIR__ . '/components/scripts.php';
