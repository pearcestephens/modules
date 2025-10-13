<?php
declare(strict_types=1);

use Modules\Base\Kernel;
use Modules\Base\Router;
use Modules\Base\ErrorHandler;
use Modules\Base\Helpers;
use Modules\Consignments\controllers\HomeController;
use Modules\Consignments\controllers\PackController;
use Modules\Consignments\controllers\ReceiveController;
use Modules\Consignments\controllers\HubController;
use Modules\Consignments\controllers\Api\PackApiController;
use Modules\Consignments\controllers\Api\ReceiveApiController;

require __DIR__ . '/module_bootstrap.php';

require_once dirname(__DIR__) . '/base/lib/ErrorHandler.php';
require_once dirname(__DIR__) . '/base/lib/Kernel.php';

// Define context constant for layout security
if (!defined('CIS_MODULE_CONTEXT')) {
    define('CIS_MODULE_CONTEXT', true);
}

// Register error handler (debug mode from env)
$debug = ($_ENV['APP_DEBUG'] ?? '') === '1' || strtolower((string)($_ENV['APP_DEBUG'] ?? '')) === 'true';
ErrorHandler::register($debug);

// Initialize kernel (app bootstrap, autoloaders, auth, headers)
Kernel::boot();

// Set module base for URL generation
Helpers::setModuleBase('/modules/consignments');

$router = new Router();
$router->add('GET', '/', HomeController::class, 'index');
$router->add('GET', '/transfers', HomeController::class, 'index');
$router->add('GET', '/transfers/pack', PackController::class, 'index');
$router->add('GET', '/transfers/receive', ReceiveController::class, 'index');
$router->add('GET', '/pack', PackController::class, 'index'); // alias for backward-compat
$router->add('GET', '/receive', ReceiveController::class, 'index'); // alias for backward-compat
$router->add('GET', '/transfers/hub', HubController::class, 'index');
$router->add('POST', '/transfers/api/pack/add-line', PackApiController::class, 'addLine');
$router->add('POST', '/transfers/api/receive/add-line', ReceiveApiController::class, 'addLine');

$router->dispatch('/modules/consignments');
