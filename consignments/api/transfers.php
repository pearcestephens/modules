<?php
declare(strict_types=1);

/**
 * Transfer Services API
 *
 * Handles outlet transfers, supplier returns, and stocktake operations
 *
 * Endpoints:
 * POST /api/transfers.php?action=outlet_transfer_create
 * POST /api/transfers.php?action=outlet_transfer_approve
 * POST /api/transfers.php?action=outlet_transfer_send
 * POST /api/transfers.php?action=outlet_transfer_receive
 *
 * POST /api/transfers.php?action=supplier_return_create
 * POST /api/transfers.php?action=supplier_return_approve
 * POST /api/transfers.php?action=supplier_return_ship
 * POST /api/transfers.php?action=supplier_return_process_refund
 *
 * POST /api/transfers.php?action=stocktake_create
 * POST /api/transfers.php?action=stocktake_approve
 * POST /api/transfers.php?action=stocktake_generate_adjustment
 *
 * @package CIS\Consignments\API
 */

require_once __DIR__ . '/_common.php';
require_once __DIR__ . '/../domain/Services/OutletTransferService.php';
require_once __DIR__ . '/../domain/Services/SupplierReturnService.php';
require_once __DIR__ . '/../domain/Services/StocktakeService.php';
require_once __DIR__ . '/../infra/Lightspeed/LightspeedClient.php';

use Consignments\Domain\Services\OutletTransferService;
use Consignments\Domain\Services\SupplierReturnService;
use Consignments\Domain\Services\StocktakeService;
use Consignments\Infra\Lightspeed\LightspeedClient;

// Require authentication
ConsignAuth::requireRole('ops');

// Get action from query string
$action = $_GET['action'] ?? '';

// Database connection
$dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_NAME);
$pdo = new PDO($dsn, DB_USER, DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
]);

// Simple logger
$logger = new class implements \Psr\Log\LoggerInterface {
    use \Psr\Log\LoggerTrait;
    public function log($level, $message, array $context = []): void {
        error_log("[{$level}] {$message} " . json_encode($context));
    }
};

// Lightspeed client
$lightspeedClient = new LightspeedClient(
    $_ENV['LIGHTSPEED_DOMAIN'] ?? '',
    $_ENV['LIGHTSPEED_CLIENT_ID'] ?? '',
    $_ENV['LIGHTSPEED_CLIENT_SECRET'] ?? '',
    $pdo,
    $logger
);

// Initialize services
$outletTransferService = new OutletTransferService($pdo, $lightspeedClient, $logger);
$supplierReturnService = new SupplierReturnService($pdo, $lightspeedClient, $logger);
$stocktakeService = new StocktakeService($pdo, $lightspeedClient, $logger);

// Get input data
$input = json_input();

// ========================================
// OUTLET TRANSFER ENDPOINTS
// ========================================

if ($action === 'outlet_transfer_create') {
    try {
        $result = $outletTransferService->createTransfer($input);
        api_ok($result);
    } catch (\InvalidArgumentException $e) {
        api_fail($e->getMessage(), 400);
    } catch (\Throwable $e) {
        api_fail('Failed to create outlet transfer: ' . $e->getMessage(), 500);
    }
}

if ($action === 'outlet_transfer_approve') {
    try {
        $transferId = (int)($input['transfer_id'] ?? 0);
        $approverId = (int)($_SESSION['user_id'] ?? 0);

        $result = $outletTransferService->approve($transferId, $approverId);
        api_ok(['approved' => $result]);
    } catch (\InvalidArgumentException $e) {
        api_fail($e->getMessage(), 400);
    } catch (\Throwable $e) {
        api_fail('Failed to approve outlet transfer: ' . $e->getMessage(), 500);
    }
}

if ($action === 'outlet_transfer_send') {
    try {
        $transferId = (int)($input['transfer_id'] ?? 0);
        $shippingData = $input['shipping'] ?? [];

        $result = $outletTransferService->send($transferId, $shippingData);
        api_ok($result);
    } catch (\InvalidArgumentException $e) {
        api_fail($e->getMessage(), 400);
    } catch (\Throwable $e) {
        api_fail('Failed to send outlet transfer: ' . $e->getMessage(), 500);
    }
}

if ($action === 'outlet_transfer_receive') {
    try {
        $transferId = (int)($input['transfer_id'] ?? 0);
        $receivedData = $input['received'] ?? [];

        $result = $outletTransferService->receive($transferId, $receivedData);
        api_ok($result);
    } catch (\InvalidArgumentException $e) {
        api_fail($e->getMessage(), 400);
    } catch (\Throwable $e) {
        api_fail('Failed to receive outlet transfer: ' . $e->getMessage(), 500);
    }
}

// ========================================
// SUPPLIER RETURN ENDPOINTS
// ========================================

if ($action === 'supplier_return_create') {
    try {
        $result = $supplierReturnService->createReturn($input);
        api_ok($result);
    } catch (\InvalidArgumentException $e) {
        api_fail($e->getMessage(), 400);
    } catch (\Throwable $e) {
        api_fail('Failed to create supplier return: ' . $e->getMessage(), 500);
    }
}

if ($action === 'supplier_return_add_item') {
    try {
        $returnId = (int)($input['return_id'] ?? 0);
        $itemData = $input['item'] ?? [];

        $result = $supplierReturnService->addReturnItem($returnId, $itemData);
        api_ok(['item_added' => $result]);
    } catch (\InvalidArgumentException $e) {
        api_fail($e->getMessage(), 400);
    } catch (\Throwable $e) {
        api_fail('Failed to add return item: ' . $e->getMessage(), 500);
    }
}

if ($action === 'supplier_return_attach_evidence') {
    try {
        $returnId = (int)($input['return_id'] ?? 0);
        $photoUrl = (string)($input['photo_url'] ?? '');

        $result = $supplierReturnService->attachEvidence($returnId, $photoUrl);
        api_ok(['evidence_attached' => $result]);
    } catch (\InvalidArgumentException $e) {
        api_fail($e->getMessage(), 400);
    } catch (\Throwable $e) {
        api_fail('Failed to attach evidence: ' . $e->getMessage(), 500);
    }
}

if ($action === 'supplier_return_approve') {
    try {
        $returnId = (int)($input['return_id'] ?? 0);
        $approverId = (int)($_SESSION['user_id'] ?? 0);

        $result = $supplierReturnService->approve($returnId, $approverId);
        api_ok(['approved' => $result]);
    } catch (\InvalidArgumentException $e) {
        api_fail($e->getMessage(), 400);
    } catch (\Throwable $e) {
        api_fail('Failed to approve supplier return: ' . $e->getMessage(), 500);
    }
}

if ($action === 'supplier_return_ship') {
    try {
        $returnId = (int)($input['return_id'] ?? 0);
        $trackingData = $input['tracking'] ?? [];

        $result = $supplierReturnService->ship($returnId, $trackingData);
        api_ok(['shipped' => $result]);
    } catch (\InvalidArgumentException $e) {
        api_fail($e->getMessage(), 400);
    } catch (\Throwable $e) {
        api_fail('Failed to ship supplier return: ' . $e->getMessage(), 500);
    }
}

if ($action === 'supplier_return_process_refund') {
    try {
        $returnId = (int)($input['return_id'] ?? 0);
        $refundAmount = (float)($input['refund_amount'] ?? 0.0);
        $refundNote = (string)($input['refund_note'] ?? '');

        $result = $supplierReturnService->processRefund($returnId, $refundAmount, $refundNote);
        api_ok(['refund_processed' => $result]);
    } catch (\InvalidArgumentException $e) {
        api_fail($e->getMessage(), 400);
    } catch (\Throwable $e) {
        api_fail('Failed to process refund: ' . $e->getMessage(), 500);
    }
}

// ========================================
// STOCKTAKE ENDPOINTS
// ========================================

if ($action === 'stocktake_create') {
    try {
        $outletId = (int)($input['outlet_id'] ?? 0);
        $counts = $input['counts'] ?? [];

        $result = $stocktakeService->createStocktake($outletId, $counts);
        api_ok($result);
    } catch (\InvalidArgumentException $e) {
        api_fail($e->getMessage(), 400);
    } catch (\Throwable $e) {
        api_fail('Failed to create stocktake: ' . $e->getMessage(), 500);
    }
}

if ($action === 'stocktake_calculate_variances') {
    try {
        $stocktakeId = (int)($input['stocktake_id'] ?? 0);

        $result = $stocktakeService->calculateVariances($stocktakeId);
        api_ok($result);
    } catch (\InvalidArgumentException $e) {
        api_fail($e->getMessage(), 400);
    } catch (\Throwable $e) {
        api_fail('Failed to calculate variances: ' . $e->getMessage(), 500);
    }
}

if ($action === 'stocktake_approve') {
    try {
        $stocktakeId = (int)($input['stocktake_id'] ?? 0);
        $approverId = (int)($_SESSION['user_id'] ?? 0);

        $result = $stocktakeService->approve($stocktakeId, $approverId);
        api_ok(['approved' => $result]);
    } catch (\InvalidArgumentException $e) {
        api_fail($e->getMessage(), 400);
    } catch (\Throwable $e) {
        api_fail('Failed to approve stocktake: ' . $e->getMessage(), 500);
    }
}

if ($action === 'stocktake_generate_adjustment') {
    try {
        $stocktakeId = (int)($input['stocktake_id'] ?? 0);

        $result = $stocktakeService->generateAdjustmentTransfer($stocktakeId);
        api_ok($result);
    } catch (\InvalidArgumentException $e) {
        api_fail($e->getMessage(), 400);
    } catch (\Throwable $e) {
        api_fail('Failed to generate adjustment: ' . $e->getMessage(), 500);
    }
}

// Unknown action
api_fail('Unknown action: ' . $action, 404);
