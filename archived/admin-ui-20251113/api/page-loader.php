<?php
/**
 * Page Loader API
 *
 * Loads page content dynamically for the Admin UI
 * Usage: GET /api/page-loader.php?page=overview
 *
 * @package AdminUI
 * @version 1.0.0
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

try {
    // Get requested page
    $page = $_GET['page'] ?? 'overview';
    $page = preg_replace('/[^a-z0-9\-_]/i', '', $page); // Sanitize

    if (empty($page)) {
        throw new Exception('No page specified');
    }

    // Map pages to files
    $pageFile = __DIR__ . "/../pages/{$page}.php";

    // Security: prevent directory traversal
    $realPath = realpath($pageFile);
    $allowedDir = realpath(__DIR__ . '/../pages');

    if (!$realPath || strpos($realPath, $allowedDir) !== 0 || !file_exists($realPath)) {
        throw new Exception("Page not found: {$page}");
    }

    // Include app context
    require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';

    // Capture output
    ob_start();
    include $realPath;
    $content = ob_get_clean();

    // Return success
    header('HTTP/1.1 200 OK');
    echo json_encode([
        'success' => true,
        'page' => $page,
        'content' => $content,
        'timestamp' => date('Y-m-d H:i:s')
    ]);

} catch (Exception $e) {
    header('HTTP/1.1 400 Bad Request');
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'page' => $_GET['page'] ?? 'unknown'
    ]);
}
