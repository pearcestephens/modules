<?php
/**
 * Filename: PayslipPdfGenerator.php
 * Purpose : Generate payslip HTML/PDF payloads for viewing, downloads, and email attachments.
 * Author  : GitHub Copilot
 * Last Modified: 2025-10-31
 * Dependencies: None (pure utility)
 */
declare(strict_types=1);

final class PayslipPdfGenerator
{
    /**
     * Build a printable HTML representation of a payslip suitable for PDF conversion.
     *
     * @param array $payslip High-level payslip metadata (employee, totals, dates).
     * @param array $lines   Individual line entries associated with the payslip.
     *
     * @return string Rendered HTML document.
     */
    public static function renderHtml(array $payslip, array $lines): string
    {
        $employeeName = htmlspecialchars(trim(($payslip['first_name'] ?? '') . ' ' . ($payslip['last_name'] ?? '')));
        $periodLabel = htmlspecialchars(sprintf('%s → %s', $payslip['period_start'] ?? '', $payslip['period_end'] ?? ''));
        $grossFormatted = number_format((float)($payslip['gross'] ?? 0.0), 2);
        $netFormatted = number_format((float)($payslip['net'] ?? 0.0), 2);

        ob_start();
        ?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
body { font-family: Arial, Helvetica, sans-serif; font-size: 12px; }
h1 { font-size: 16px; margin: 0 0 8px; }
table { width: 100%; border-collapse: collapse; }
th, td { border: 1px solid #ddd; padding: 6px; }
tfoot th { background: #fafafa; }
.right { text-align: right; }
</style>
</head>
<body>
<h1>Payslip</h1>
<div>Employee: <?= $employeeName ?> &nbsp; | &nbsp; Period: <?= $periodLabel ?></div>
<br>
<table>
  <thead>
    <tr>
      <th>Type</th>
      <th>Description</th>
      <th class="right">Units</th>
      <th class="right">Rate</th>
      <th class="right">Amount</th>
    </tr>
  </thead>
  <tbody>
    <?php foreach ($lines as $line): ?>
    <tr>
      <td><?= htmlspecialchars((string)($line['type'] ?? '')) ?></td>
      <td><?= htmlspecialchars((string)($line['description'] ?? '')) ?></td>
      <td class="right"><?= htmlspecialchars((string)($line['units'] ?? '')) ?></td>
      <td class="right"><?= number_format((float)($line['rate'] ?? 0.0), 2) ?></td>
      <td class="right"><?= number_format((float)($line['amount'] ?? 0.0), 2) ?></td>
    </tr>
    <?php endforeach; ?>
  </tbody>
  <tfoot>
    <tr>
      <th colspan="4" class="right">Gross</th>
      <th class="right"><?= $grossFormatted ?></th>
    </tr>
    <tr>
      <th colspan="4" class="right">Net</th>
      <th class="right"><?= $netFormatted ?></th>
    </tr>
  </tfoot>
</table>
</body>
</html>
<?php
        return trim((string)ob_get_clean());
    }

    /**
     * Convert rendered HTML into PDF bytes using shared PdfService.
     *
     * @param string $html Rendered HTML payload.
     *
     * @return string Binary PDF contents.
     */
    public static function toPdfBytes(string $html): string
    {
        // Load shared PdfService
        require_once __DIR__ . '/../../../shared/services/PdfService.php';

        // Generate PDF using centralized service
        $pdf = \CIS\Shared\Services\PdfService::fromHtml($html, [
            'orientation' => 'portrait',
            'paper' => 'a4'
        ]);

        return $pdf->output();
    }
}
