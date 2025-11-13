<?php
/**
 * Consignments Module - CONVERTED TO BASE TEMPLATE
 *
 * Now uses VapeUltra base template system
 *
 * @package CIS\Consignments
 * @version 4.0.0 - ULTRA EDITION
 */

declare(strict_types=1);

// Load bootstrap
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../base/Template/Renderer.php';
require_once __DIR__ . '/../base/middleware/MiddlewarePipeline.php';

use App\Template\Renderer;
use App\Middleware\MiddlewarePipeline;

// Create authenticated middleware pipeline
$pipeline = MiddlewarePipeline::createAuthenticated();

// Execute pipeline
$pipeline->handle($_REQUEST, function($request) {

    // Determine which view to load
    $route = $_GET['route'] ?? 'home';

    // Start output buffering for module content
    ob_start();

    switch ($route) {
        case 'home':
        case '':
            require_once __DIR__ . '/views/home.php';
            break;

        case 'transfer-manager':
            require_once __DIR__ . '/views/transfer-manager-v5.php';
            break;

        case 'control-panel':
            require_once __DIR__ . '/views/control-panel.php';
            break;

        case 'purchase-orders':
            require_once __DIR__ . '/views/purchase-orders.php';
            break;

        case 'stock-transfers':
            require_once __DIR__ . '/views/stock-transfers.php';
            break;

        case 'receiving':
            require_once __DIR__ . '/views/receiving.php';
            break;

        case 'analytics':
            require_once __DIR__ . '/views/analytics.php';
            break;

        case 'settings':
            require_once __DIR__ . '/views/settings.php';
            break;

        default:
            http_response_code(404);
            echo '<div class="alert alert-danger">
                    <h4>404 - Route Not Found</h4>
                    <p>The requested route does not exist: ' . htmlspecialchars($route) . '</p>
                  </div>';
    }

    $moduleContent = ob_get_clean();

    // Render with VapeUltra base template
    $renderer = new Renderer();
    $renderer->render($moduleContent, [
        'title' => 'Consignments - Vape Shed CIS Ultra',
        'class' => 'page-consignments',
        'layout' => 'main',
        'scripts' => [
            '/modules/consignments/assets/js/consignments.js',
            '/modules/consignments/assets/js/transfers.js',
        ],
        'styles' => [
            '/modules/consignments/assets/css/consignments.css',
        ],
        'inline_scripts' => "
            console.log('🚀 Consignments module loaded');
            VapeUltra.Core.registerModule('Consignments', {
                init: function() {
                    console.log('✅ Consignments module initialized');
                }
            });
        ",
        'nav_items' => [
            'consignments' => [
                'title' => 'Consignments',
                'items' => [
                    ['icon' => 'house-door', 'label' => 'Home', 'href' => '/modules/consignments/?route=home', 'badge' => null],
                    ['icon' => 'arrow-left-right', 'label' => 'Transfer Manager', 'href' => '/modules/consignments/?route=transfer-manager', 'badge' => null],
                    ['icon' => 'clipboard-check', 'label' => 'Purchase Orders', 'href' => '/modules/consignments/?route=purchase-orders', 'badge' => 5],
                    ['icon' => 'truck', 'label' => 'Receiving', 'href' => '/modules/consignments/?route=receiving', 'badge' => 3],
                    ['icon' => 'graph-up', 'label' => 'Analytics', 'href' => '/modules/consignments/?route=analytics', 'badge' => null],
                    ['icon' => 'gear', 'label' => 'Settings', 'href' => '/modules/consignments/?route=settings', 'badge' => null],
                ]
            ]
        ]
    ]);

});
