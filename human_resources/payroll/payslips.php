<?php
/**
 * Filename: payslips.php
 * Purpose : Handle payslip download and email actions for the payroll module.
 * Author  : GitHub Copilot
 * Last Modified: 2025-10-31
 * Dependencies: /app.php, PayslipPdfGenerator, PayslipEmailer, JsonGuard, ApiResponder
 */
declare(strict_types=1);

use ApiResponder;
use JsonGuard;
use PDO;
use Throwable;

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once __DIR__ . '/lib/PayslipPdfGenerator.php';
require_once __DIR__ . '/lib/PayslipEmailer.php';

$db = function_exists('cis_pdo') ? cis_pdo() : ($GLOBALS['pdo'] ?? null);
if (!$db instanceof PDO) {
    http_response_code(500);
    echo 'Database connection unavailable';
    exit;
}

$method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
$action = (string)($_GET['action'] ?? ($_POST['action'] ?? ''));

/**
 * Fetch a payslip and associated line items.
 */
function payroll_load_payslip(PDO $db, string $xeroPayslipId): ?array
{
    $headerStmt = $db->prepare(
        'SELECT p.*, u.first_name, u.last_name, u.email
         FROM payroll_payslips p
         INNER JOIN users u ON u.id = p.employee_id
         WHERE p.xero_payslip_id = ?
         LIMIT 1'
    );
    $headerStmt->execute([$xeroPayslipId]);
    $payslip = $headerStmt->fetch(PDO::FETCH_ASSOC);
    if (!$payslip) {
        return null;
    }

    $lineStmt = $db->prepare('SELECT * FROM payroll_payslip_lines WHERE payslip_id = ? ORDER BY id ASC');
    $lineStmt->execute([(int)$payslip['id']]);
    $payslip['__lines'] = $lineStmt->fetchAll(PDO::FETCH_ASSOC);

    return $payslip;
}

if ($method === 'GET' && $action === 'pdf') {
    $payslipId = (string)filter_input(
        INPUT_GET,
        'payslip_id',
        FILTER_UNSAFE_RAW,
        ['options' => ['default' => ''], 'flags' => FILTER_FLAG_STRIP_LOW]
    );
    if ($payslipId === '') {
        http_response_code(400);
        echo 'Missing payslip_id';
        exit;
    }

    $payslip = payroll_load_payslip($db, $payslipId);
    if (!$payslip) {
        http_response_code(404);
        echo 'Payslip not found';
        exit;
    }

    $html = PayslipPdfGenerator::renderHtml($payslip, $payslip['__lines']);
    $pdfBytes = PayslipPdfGenerator::toPdfBytes($html);

    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="payslip.pdf"');
    header('Content-Length: ' . strlen($pdfBytes));
    echo $pdfBytes;
    exit;
}

if ($method === 'POST' && $action === 'email') {
    JsonGuard::csrfCheckOptional();

    $payload = JsonGuard::readJson();
    $payslipId = trim((string)($payload['payslip_id'] ?? ''));
    if ($payslipId === '') {
        ApiResponder::json(['success' => false, 'error' => 'Missing payslip_id'], 400);
    }

    $payslip = payroll_load_payslip($db, $payslipId);
    if (!$payslip) {
        ApiResponder::json(['success' => false, 'error' => 'Payslip not found'], 404);
    }

    try {
        $queued = PayslipEmailer::queueEmail($payslip, $payslip['__lines']);
    } catch (Throwable $throwable) {
        error_log('Payslip email queue failed: ' . $throwable->getMessage());
        ApiResponder::json(['success' => false, 'error' => 'Unable to queue email'], 500);
    }

    ApiResponder::json(['success' => (bool)$queued, 'queued' => (bool)$queued]);
}

http_response_code(400);
if (!headers_sent()) {
    header('Content-Type: text/plain; charset=utf-8');
}
echo 'Unsupported action';
