<?php
// Direct test of PayrollAutomationController
header('Content-Type: application/json');

require_once __DIR__ . '/lib/PayrollLogger.php';
require_once __DIR__ . '/controllers/BaseController.php';
require_once __DIR__ . '/services/BaseService.php';
require_once __DIR__ . '/services/AmendmentService.php';
require_once __DIR__ . '/services/XeroService.php';
require_once __DIR__ . '/services/DeputyService.php';
require_once __DIR__ . '/services/VendService.php';
require_once __DIR__ . '/services/PayrollAutomationService.php';
require_once __DIR__ . '/controllers/PayrollAutomationController.php';

try {
    $db = new PDO('mysql:host=127.0.0.1;dbname=jcepnzzkmj', 'jcepnzzkmj', 'wprKh9Jq63', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);

    $controller = new \HumanResources\Payroll\Controllers\PayrollAutomationController($db);
    echo json_encode(['success' => true, 'message' => 'Controller instantiated successfully!', 'class' => get_class($controller)]);
} catch (Throwable $e) {
    echo json_encode(['success' => false, 'error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
}
