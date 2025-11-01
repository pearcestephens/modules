<?php
/**
 * Lightweight health check endpoint.
 */

declare(strict_types=1);

require_once __DIR__ . '/../../../config/env-loader.php';

use App\Support\Response;

Response::json([
    'status' => 'ok',
    'environment' => env('APP_ENV', 'production'),
    'timestamp' => gmdate('c'),
]);