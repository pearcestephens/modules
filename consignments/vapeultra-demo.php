<?php
/**
 * VapeUltra Demo Router
 * Routes to different demo pages
 */

$page = $_GET['page'] ?? 'test';

switch ($page) {
    case 'test':
        require_once __DIR__ . '/views/vapeultra-demo-test.php';
        break;

    case 'full':
        require_once __DIR__ . '/views/vapeultra-demo-full.php';
        break;

    case 'messaging':
        // Use integrated version with VapeUltra template
        require_once __DIR__ . '/bootstrap.php';

        // Load the messaging center view
        $contentFile = __DIR__ . '/views/messaging-center-integrated.php';

        // Get layout mode
        $layoutMode = $_GET['layout'] ?? 'standard';

        // Create renderer instance and render
        $renderer = new \App\Template\Renderer();
        $renderer->render(
            file_get_contents($contentFile),
            [
                'layout' => ($layoutMode === 'fullwidth') ? 'messaging' : 'main',
                'title' => 'Messaging Center',
                'hide_right_sidebar' => false, // Always render sidebar, JavaScript will control visibility
                'class' => 'messaging-page layout-' . $layoutMode
            ]
        );
        break;

    default:
        require_once __DIR__ . '/views/vapeultra-demo-test.php';
        break;
}
