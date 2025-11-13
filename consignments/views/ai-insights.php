<?php
declare(strict_types=1);
require_once __DIR__ . '/../bootstrap.php';

$pageTitle = 'AI Insights';
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/', 'icon' => 'bi-house-door'],
    ['label' => 'Consignments', 'url' => '/modules/consignments/', 'icon' => 'bi-box-seam'],
    ['label' => 'AI Insights', 'url' => '/modules/consignments/?route=ai-insights', 'active' => true]
];
$pageCSS = ['https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css','/modules/admin-ui/css/cms-design-system.css'];
$pageJS = [];
ob_start();
?>
<div class="card mb-4 border-0 shadow-sm" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
    <div class="card-body p-4">
        <h1 class="h2 mb-2 text-white"><i class="bi bi-lightbulb me-2"></i>AI Insights</h1>
        <p class="mb-0 text-white opacity-90">Intelligent recommendations and predictions</p>
    </div>
</div>
<div class="card shadow-sm"><div class="card-body text-center py-5">
<i class="bi bi-robot display-1 text-primary mb-3"></i>
<h3>AI Insights Coming Soon</h3>
<p class="text-muted">Machine learning powered transfer optimization and predictions will be available here.</p>
</div></div>
<style>.fade-in{animation:fadeInUp 0.6s ease-out}@keyframes fadeInUp{from{opacity:0;transform:translateY(20px)}to{opacity:1;transform:translateY(0)}}</style>
<?php $content=ob_get_clean();require_once __DIR__.'/../../base/templates/themes/modern/layouts/dashboard.php';?>
