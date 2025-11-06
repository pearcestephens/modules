<?php
/**
 * Staff Performance & Gamification Module - Main Entry Point
 *
 * Routes requests to appropriate views
 *
 * @package CIS\Modules\StaffPerformance
 * @version 1.0.0
 */

// Load module bootstrap
require_once __DIR__ . '/bootstrap.php';

// Get route from query string
$route = $_GET['page'] ?? 'dashboard';

// Route to appropriate view
switch ($route) {
    case 'dashboard':
    case '':
        require_once __DIR__ . '/views/dashboard.php';
        break;

    case 'competitions':
        require_once __DIR__ . '/views/competitions.php';
        break;

    case 'achievements':
        require_once __DIR__ . '/views/achievements.php';
        break;

    case 'history':
        require_once __DIR__ . '/views/history.php';
        break;

    case 'leaderboard':
        require_once __DIR__ . '/views/leaderboard.php';
        break;

    default:
        http_response_code(404);
        echo "404 - Page not found";
        exit;
}
