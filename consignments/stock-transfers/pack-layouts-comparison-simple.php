<?php
/**
 * Layout Comparison Page - Choose Your Preferred Design
 * Simplified version to debug 500 error
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/../../base/_templates/themes/cis-classic/theme.php';

$theme = new CISClassicTheme();
$theme->setTitle('Advanced Pack Page - Layout Comparison');
$theme->setPageSubtitle('Choose Your Preferred Design');

$theme->addBreadcrumb('Consignments', '/modules/consignments/');
$theme->addBreadcrumb('Stock Transfers', '/modules/consignments/stock-transfers/');
$theme->addBreadcrumb('Layout Comparison', null);
?>

<?php $theme->render('html-head'); ?>
<?php $theme->render('header'); ?>
<?php $theme->render('sidebar'); ?>
<?php $theme->render('main-start'); ?>

<style>
.comparison-container {
    margin-top: 20px;
}

.intro-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    padding: 40px;
    border-radius: 12px;
    margin-bottom: 30px;
    text-align: center;
}

.layout-card {
    background: #fff;
    border-radius: 12px;
    padding: 30px;
    margin-bottom: 20px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.btn-view {
    display: inline-block;
    padding: 12px 30px;
    margin: 10px;
    background: #667eea;
    color: #fff;
    border-radius: 8px;
    text-decoration: none;
    font-weight: bold;
}

.btn-view:hover {
    background: #5568d3;
    color: #fff;
}
</style>

<div class="comparison-container">
    <div class="intro-section">
        <h2><i class="fa fa-rocket"></i> Advanced Transfer Packing - Layout Options</h2>
        <p>Three carefully designed layouts for maximum productivity. Choose the one that fits your workflow best.</p>
    </div>

    <div class="layout-card">
        <h3>Layout A - Two Column Split</h3>
        <p>Classic design with products table (70%) on left, sticky freight console (30%) on right.</p>
        <a href="pack-advanced-layout-a.php" target="_blank" class="btn-view">
            <i class="fa fa-external-link-alt"></i> View Layout A
        </a>
    </div>

    <div class="layout-card">
        <h3>Layout B - Horizontal Tabs</h3>
        <p>Full-width dashboard with tab navigation. Product grid cards view.</p>
        <a href="pack-advanced-layout-b.php" target="_blank" class="btn-view">
            <i class="fa fa-external-link-alt"></i> View Layout B
        </a>
    </div>

    <div class="layout-card">
        <h3>Layout C - Compact Dashboard</h3>
        <p>Collapsible accordion panels with floating freight action bar at bottom.</p>
        <a href="pack-advanced-layout-c.php" target="_blank" class="btn-view">
            <i class="fa fa-external-link-alt"></i> View Layout C
        </a>
    </div>
</div>

<?php $theme->render('footer'); ?>
