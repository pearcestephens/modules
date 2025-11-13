<?php
/**
 * Control Panel Module - CONVERTED TO BASE TEMPLATE
 *
 * Now uses VapeUltra base template system
 *
 * @package CIS\Modules\ControlPanel
 * @version 2.0.0 - ULTRA EDITION
 */

require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../base/Template/Renderer.php';
require_once __DIR__ . '/../base/middleware/MiddlewarePipeline.php';

use App\Template\Renderer;
use App\Middleware\MiddlewarePipeline;

// Create authenticated middleware pipeline
$pipeline = MiddlewarePipeline::createAuthenticated();

// Execute pipeline
$pipeline->handle($_REQUEST, function($request) {

    // Get requested page
    $page = $_GET['page'] ?? 'dashboard';

    // Validate page
    $allowedPages = [
        'dashboard',
        'modules',
        'config',
        'backups',
        'environments',
        'documentation',
        'system-info',
        'logs'
    ];

    if (!in_array($page, $allowedPages)) {
        $page = 'dashboard';
    }

    // Start output buffering for module content
    ob_start();

    // Route to view
    $viewFile = CONTROL_PANEL_VIEWS_PATH . '/' . $page . '.php';

    if (file_exists($viewFile)) {
        require_once $viewFile;
    } else {
        ?>
        <div class="container-fluid">
            <div class="alert alert-danger">
                <h4>404 - Page Not Found</h4>
                <p>The requested view does not exist: <?= htmlspecialchars($page) ?></p>
            </div>
        </div>
        <?php
    }

    $moduleContent = ob_get_clean();

    // Render with VapeUltra base template
    $renderer = new Renderer();
    $renderer->render($moduleContent, [
        'title' => 'Control Panel - Vape Shed CIS Ultra',
        'class' => 'page-control-panel',
        'layout' => 'main',
        'scripts' => [
            '/modules/control-panel/assets/js/control-panel.js',
        ],
        'styles' => [
            '/modules/control-panel/assets/css/control-panel.css',
        ],
        'inline_scripts' => "
            VapeUltra.Core.registerModule('ControlPanel', {
                init: function() {
                    console.log('✅ Control Panel module initialized');
                }
            });
        ",
        'nav_items' => [
            'control-panel' => [
                'title' => 'Control Panel',
                'items' => [
                    ['icon' => 'speedometer2', 'label' => 'Dashboard', 'href' => '/modules/control-panel/?page=dashboard', 'badge' => null],
                    ['icon' => 'grid', 'label' => 'Modules', 'href' => '/modules/control-panel/?page=modules', 'badge' => null],
                    ['icon' => 'gear', 'label' => 'Configuration', 'href' => '/modules/control-panel/?page=config', 'badge' => null],
                    ['icon' => 'shield-check', 'label' => 'Backups', 'href' => '/modules/control-panel/?page=backups', 'badge' => null],
                    ['icon' => 'server', 'label' => 'System Info', 'href' => '/modules/control-panel/?page=system-info', 'badge' => null],
                    ['icon' => 'file-text', 'label' => 'Logs', 'href' => '/modules/control-panel/?page=logs', 'badge' => null],
                ]
            ]
        ]
    ]);

});
