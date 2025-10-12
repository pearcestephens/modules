<?php
declare(strict_types=1);

use Modules\Shared\Kernel;
use Modules\Shared\Router;
use Modules\Consignments\Transfers\controllers\HomeController as TransfersHomeController;
use Modules\Consignments\Transfers\controllers\PackController as TransfersPackController;
use Modules\Consignments\Transfers\controllers\ReceiveController as TransfersReceiveController;
use Modules\Consignments\Transfers\controllers\HubController as TransfersHubController;
use Modules\Consignments\Transfers\controllers\Api\PackApiController as TransfersPackApiController;
use Modules\Consignments\Transfers\controllers\Api\ReceiveApiController as TransfersReceiveApiController;

require __DIR__ . '/module_bootstrap.php';

require_once __DIR__ . '/_shared/lib/Kernel.php';

// Initialize kernel (app bootstrap, autoloaders, auth, headers)
Kernel::boot();

$router = new Router();
$router->add('GET', '/', TransfersHomeController::class, 'index');
$router->add('GET', '/transfers', TransfersHomeController::class, 'index');
$router->add('GET', '/transfers/pack', TransfersPackController::class, 'index');
$router->add('GET', '/transfers/receive', TransfersReceiveController::class, 'index');
$router->add('GET', '/transfers/hub', TransfersHubController::class, 'index');
$router->add('POST', '/transfers/api/pack/add-line', TransfersPackApiController::class, 'addLine');
$router->add('POST', '/transfers/api/receive/add-line', TransfersReceiveApiController::class, 'addLine');

$router->dispatch('/modules/consignments');
