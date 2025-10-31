<?php
/**
 * Filename: payslip.php
 * Purpose : Render an interactive payslip viewer with PDF download and email queue actions.
 * Author  : GitHub Copilot
 * Last Modified: 2025-10-31
 * Dependencies: /app.php, PayslipPdfGenerator, PayslipEmailer
 */
declare(strict_types=1);

use PDO;

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once __DIR__ . '/../lib/PayslipPdfGenerator.php';
require_once __DIR__ . '/../lib/PayslipEmailer.php';

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

$db = function_exists('cis_pdo') ? cis_pdo() : ($GLOBALS['pdo'] ?? null);
if (!$db instanceof PDO) {
  http_response_code(500);
  echo 'Database connection unavailable';
  exit;
}
$stmt = $db->prepare(
    'SELECT p.*, u.first_name, u.last_name, u.email
     FROM payroll_payslips p
     INNER JOIN users u ON u.id = p.employee_id
     WHERE p.xero_payslip_id = ?
     LIMIT 1'
);
$stmt->execute([$payslipId]);
$payslip = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$payslip) {
    http_response_code(404);
    echo 'Payslip not found';
    exit;
}

$lineStmt = $db->prepare(
    'SELECT * FROM payroll_payslip_lines WHERE payslip_id = ? ORDER BY id ASC'
);
$lineStmt->execute([(int)$payslip['id']]);
$items = $lineStmt->fetchAll(PDO::FETCH_ASSOC);

$grossTotal = (float)($payslip['gross'] ?? 0.0);
$netTotal = (float)($payslip['net'] ?? 0.0);
$periodLabel = sprintf(
    '%s → %s',
    htmlspecialchars((string)($payslip['period_start'] ?? ''), ENT_QUOTES, 'UTF-8'),
    htmlspecialchars((string)($payslip['period_end'] ?? ''), ENT_QUOTES, 'UTF-8')
);
$displayName = htmlspecialchars(
    trim(($payslip['first_name'] ?? '') . ' ' . ($payslip['last_name'] ?? '')),
    ENT_QUOTES,
    'UTF-8'
);
$payslipIdEscaped = htmlspecialchars($payslipId, ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <title>Payslip #<?= $payslipIdEscaped ?></title>
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
  <style>
    body { padding: 20px; }
    .slim { font-size: 0.9rem; color: #666; }
    .table-sm td,
    .table-sm th { padding: 0.4rem 0.5rem; }
  </style>
</head>
<body>
  <h1 class="h4 mb-1">Payslip</h1>
  <div class="slim mb-3">
    <?= $displayName ?> · Period: <?= $periodLabel ?>
  </div>

  <div class="mb-3">
    <a class="btn btn-outline-secondary btn-sm" href="/modules/human_resources/payroll/payslips.php?action=pdf&amp;payslip_id=<?= $payslipIdEscaped ?>">
      Download PDF
    </a>
    <button class="btn btn-primary btn-sm" id="emailBtn">Email to Employee</button>
  </div>

  <table class="table table-sm table-striped">
    <thead>
      <tr>
        <th>Type</th>
        <th>Description</th>
        <th class="text-end">Units</th>
        <th class="text-end">Rate</th>
        <th class="text-end">Amount</th>
      </tr>
    </thead>
    <tbody>
      <?php foreach ($items as $item): ?>
        <tr>
          <td><?= htmlspecialchars((string)($item['type'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
          <td><?= htmlspecialchars((string)($item['description'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
          <td class="text-end"><?= htmlspecialchars((string)($item['units'] ?? ''), ENT_QUOTES, 'UTF-8') ?></td>
          <td class="text-end"><?= number_format((float)($item['rate'] ?? 0.0), 2) ?></td>
          <td class="text-end"><?= number_format((float)($item['amount'] ?? 0.0), 2) ?></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
    <tfoot>
      <tr>
        <th colspan="4" class="text-end">Gross</th>
        <th class="text-end"><?= number_format($grossTotal, 2) ?></th>
      </tr>
      <tr>
        <th colspan="4" class="text-end">Net</th>
        <th class="text-end"><?= number_format($netTotal, 2) ?></th>
      </tr>
    </tfoot>
  </table>

  <script>
    document.getElementById('emailBtn').addEventListener('click', async () => {
      const response = await fetch('/modules/human_resources/payroll/payslips.php?action=email', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ payslip_id: '<?= $payslipIdEscaped ?>' })
      });
      const payload = await response.json().catch(() => ({ ok: false, error: 'Unexpected response' }));
      alert(payload.ok ? 'Email queued' : `Failed: ${payload.error || 'unknown error'}`);
    });
  </script>
</body>
</html>
