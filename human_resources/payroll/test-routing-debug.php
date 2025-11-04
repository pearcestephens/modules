#!/usr/bin/env php
<?php
/**
 * Debug router path matching
 */

// Simulate requests
$tests = [
    [
        'REQUEST_URI' => '/modules/human_resources/payroll/',
        'SCRIPT_NAME' => '/modules/human_resources/payroll/index.php',
        'REQUEST_METHOD' => 'GET',
    ],
    [
        'REQUEST_URI' => '/modules/human_resources/payroll/dashboard',
        'SCRIPT_NAME' => '/modules/human_resources/payroll/index.php',
        'REQUEST_METHOD' => 'GET',
    ],
    [
        'REQUEST_URI' => '/modules/human_resources/payroll/payruns',
        'SCRIPT_NAME' => '/modules/human_resources/payroll/index.php',
        'REQUEST_METHOD' => 'GET',
    ],
];

foreach ($tests as $test) {
    echo "Testing: {$test['REQUEST_URI']}\n";

    $_SERVER = $test;
    $_GET = [];

    $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
    $isApi = isset($_GET['api']);
    $isView = isset($_GET['view']);

    if ($isApi) {
        $route = $method . ' /api/payroll/' . trim($_GET['api'], '/');
    } elseif ($isView) {
        $viewPath = trim($_GET['view'], '/');
        $route = 'GET /' . ($viewPath ? $viewPath : '');
    } elseif (isset($_GET['route'])) {
        $route = $method . ' /' . ltrim($_GET['route'], '/');
    } else {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
        $cleanUri = preg_replace('/\?.*$/', '', $requestUri);
        $modulePath = dirname($scriptName);

        if (strpos($cleanUri, 'index.php') !== false ||
            $cleanUri === $modulePath . '/' ||
            $cleanUri === $modulePath ||
            $cleanUri === rtrim($modulePath, '/')) {
            $route = 'GET /';
        } else {
            $path = str_replace($modulePath, '', $cleanUri);
            $route = $method . ' ' . $path;
        }
    }

    echo "  Route built: '{$route}'\n";
    echo "  Module path: '" . dirname($test['SCRIPT_NAME']) . "'\n";
    echo "  Clean URI: '" . preg_replace('/\?.*$/', '', $test['REQUEST_URI']) . "'\n";
    echo "\n";
}
