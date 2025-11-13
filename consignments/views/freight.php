<?php
/**
 * Freight View
 * Uses BASE framework ThemeManager
 */
declare(strict_types=1);

ob_start();
?>
<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="alert alert-info">
                <h4 class="alert-heading">
                    <i class="fas fa-info-circle me-2"></i>
                    Freight Module
                </h4>
                <p class="mb-0">This section is being updated to use the new unified template system.</p>
                <hr>
                <p class="mb-0">
                    <a href="/modules/consignments/" class="alert-link">← Back to Consignments Home</a>
                </p>
            </div>
        </div>
    </div>
</div>
<?php
$content = ob_get_clean();
render('base', $content, [
    'pageTitle' => 'Freight',
    'breadcrumbs' => [
        ['label' => 'Home', 'url' => '/'],
        ['label' => 'Consignments', 'url' => '/modules/consignments/'],
        ['label' => 'Freight', 'url' => '']
    ]
]);
