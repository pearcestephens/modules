<?php
/**
 * Front controller handling all HTTP requests via ?endpoint routing.
 */

declare(strict_types=1);

use App\Http\Kernel;

require_once __DIR__ . '/../config/env-loader.php';

$appConfig = require __DIR__ . '/../config/app.php';
$urlConfig = require __DIR__ . '/../config/urls.php';
$securityConfig = require __DIR__ . '/../config/security.php';

$kernel = new Kernel($appConfig, $urlConfig, $securityConfig);
$kernel->handle();