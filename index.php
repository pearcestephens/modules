<?php
declare(strict_types=1);

use Modules\Core\Kernel;
use Modules\Core\ErrorHandler;

require __DIR__ . '/core/ErrorHandler.php';
require __DIR__ . '/core/Kernel.php';

ErrorHandler::register(displayErrors: getenv('APP_DEBUG') === '1');
$kernel = new Kernel(basePath: __DIR__);
$kernel->boot();
$kernel->run();
