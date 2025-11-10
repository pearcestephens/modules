<?php
/**
 * Example API Endpoint
 * 
 * Demonstrates API usage with bootstrap system
 */

// Load bootstrap
require_once __DIR__ . '/../../base/bootstrap.php';

// Require auth for API
requireAuth();

// Check permission
if (!hasPermission('example.api')) {
    jsonResponse([
        'success' => false,
        'error' => 'Insufficient permissions'
    ], 403);
}

// Your API logic
$response = [
    'success' => true,
    'data' => [
        'message' => 'API endpoint working!',
        'user' => getCurrentUser()['username'] ?? 'Unknown',
        'theme' => theme(),
        'timestamp' => date('Y-m-d H:i:s')
    ]
];

// Send JSON response
jsonResponse($response);
