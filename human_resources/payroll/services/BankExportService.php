<?php
declare(strict_types=1);

namespace PayrollModule\Services;

use PDO;

/**
 * Bank Export Service
 *
 * Generates ASB CSV bank files for direct credit payments
 * Format: Period,Date,FromAccount,Amount,Type,Particulars,Code,Reference,ToAccount,Code2,Particulars2,Code3,Payee
 *
 * Based on xero-payruns.php bank export logic
 *
 * @package PayrollModule\Services
 * @version 1.0.0
 */
class BankExportService
{
    private PDO $db;

    // ASB CSV format constants
    private const CSV_HEADER = 'Period,Date,FromAccount,Amount,Type,Particulars,Code,Reference,ToAccount,Code2,Particulars2,Code3,Payee';
    private const PAYMENT_TYPE = '02'; // Direct credit

    public function __construct(PDO $db)
    {
        $this->db = $db;
    }

    /**
     * Generate ASB CSV file for approved payslips
     *
     * @param array $payslipIds Array of payslip IDs to export
     * @param string $fromAccount Company bank account
     * @param string $period Period description (e.g., "2025-01-W2")
     * @return array Export result with file path and hash
     */
    public function generateBankFile(array $payslipIds, string $fromAccount, string $period): array
    {
        if (empty($payslipIds)) {
            throw new \InvalidArgumentException('No payslips provided for export');
        }

        // Get approved payslips
        $payslips = $this->getPayslipsForExport($payslipIds);

        if (empty($payslips)) {
            throw new \RuntimeException('No approved payslips found for export');
        }

        // Generate CSV content
        $csvContent = $this->generateCSVContent($payslips, $fromAccount, $period);

        // Generate file hash
        $fileHash = hash('sha256', $csvContent);

        // Generate filename
        $filename = sprintf(
            'ASB_Payroll_%s_%s.csv',
            $period,
            date('YmdHis')
        );

        // Save to exports directory
        $exportPath = $this->getExportPath();
        $filePath = $exportPath . '/' . $filename;

        if (!is_dir($exportPath)) {
            mkdir($exportPath, 0755, true);
        }

        file_put_contents($filePath, $csvContent);

        // Record export in database
        $exportId = $this->recordExport(
            $filename,
            $filePath,
            $fileHash,
            count($payslips),
            array_sum(array_column($payslips, 'net_pay')),
            $payslipIds
        );

        return [
            'export_id' => $exportId,
            'filename' => $filename,
            'file_path' => $filePath,
            'file_hash' => $fileHash,
            'payslip_count' => count($payslips),
            'total_amount' => array_sum(array_column($payslips, 'net_pay'))
        ];
    }

    /**
     * Get payslips ready for bank export
     */
    private function getPayslipsForExport(array $payslipIds): array
    {
        $placeholders = implode(',', array_fill(0, count($payslipIds), '?'));

        $stmt = $this->db->prepare("
            SELECT
                p.id,
                p.staff_id,
                p.net_pay,
                s.first_name,
                s.last_name,
                s.bank_account_number,
                p.pay_period_start,
                p.pay_period_end
            FROM payroll_payslips p
            INNER JOIN employee s ON p.staff_id = s.id
            WHERE p.id IN ($placeholders)
            AND p.status = 'approved'
            AND p.exported_to_bank = 0
            AND s.bank_account_number IS NOT NULL
            AND s.bank_account_number != ''
            ORDER BY s.last_name, s.first_name
        ");

        $stmt->execute($payslipIds);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Generate CSV content in ASB format
     */
    private function generateCSVContent(array $payslips, string $fromAccount, string $period): string
    {
        $lines = [];
        $lines[] = self::CSV_HEADER;

        $paymentDate = date('d/m/Y'); // Today's date

        foreach ($payslips as $payslip) {
            $lines[] = $this->formatCSVLine(
                $period,
                $paymentDate,
                $fromAccount,
                $payslip['net_pay'],
                $payslip['bank_account_number'],
                $payslip['first_name'],
                $payslip['last_name'],
                $payslip['pay_period_start'],
                $payslip['pay_period_end']
            );
        }

        return implode("\n", $lines);
    }

    /**
     * Format a single CSV line in ASB format
     */
    private function formatCSVLine(
        string $period,
        string $paymentDate,
        string $fromAccount,
        float $amount,
        string $toAccount,
        string $firstName,
        string $lastName,
        string $periodStart,
        string $periodEnd
    ): string {
        // Clean bank account number (remove spaces and dashes)
        $toAccount = preg_replace('/[^0-9]/', '', $toAccount);

        // Format amount (2 decimal places, no commas)
        $formattedAmount = number_format($amount, 2, '.', '');

        // Generate payee name (Last, First)
        $payee = sprintf('%s, %s', $lastName, $firstName);

        // Particulars: SALARY
        $particulars = 'SALARY';

        // Code: Period dates
        $code = date('d/m', strtotime($periodStart));

        // Reference: Staff surname
        $reference = strtoupper(substr($lastName, 0, 12));

        return sprintf(
            '%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s',
            $period,           // Period
            $paymentDate,      // Date
            $fromAccount,      // FromAccount
            $formattedAmount,  // Amount
            self::PAYMENT_TYPE,// Type (02 = direct credit)
            $particulars,      // Particulars
            $code,             // Code
            $reference,        // Reference
            $toAccount,        // ToAccount
            '',                // Code2 (blank)
            '',                // Particulars2 (blank)
            '',                // Code3 (blank)
            $payee             // Payee
        );
    }

    /**
     * Record bank export in database
     */
    private function recordExport(
        string $filename,
        string $filePath,
        string $fileHash,
        int $payslipCount,
        float $totalAmount,
        array $payslipIds
    ): int {
        $stmt = $this->db->prepare("
            INSERT INTO payroll_bank_exports
            (filename, file_path, file_hash, payslip_count, total_amount, exported_by)
            VALUES (?, ?, ?, ?, ?, ?)
        ");

        $exportedBy = $_SESSION['userID'] ?? null;

        $stmt->execute([
            $filename,
            $filePath,
            $fileHash,
            $payslipCount,
            $totalAmount,
            $exportedBy
        ]);

        $exportId = (int)$this->db->lastInsertId();

        // Mark payslips as exported
        $this->markPayslipsAsExported($payslipIds, $exportId);

        return $exportId;
    }

    /**
     * Mark payslips as exported to bank
     */
    private function markPayslipsAsExported(array $payslipIds, int $exportId): void
    {
        $placeholders = implode(',', array_fill(0, count($payslipIds), '?'));

        $stmt = $this->db->prepare("
            UPDATE payroll_payslips
            SET exported_to_bank = 1,
                bank_export_id = ?,
                exported_at = NOW()
            WHERE id IN ($placeholders)
        ");

        $stmt->execute(array_merge([$exportId], $payslipIds));
    }

    /**
     * Get export path
     */
    private function getExportPath(): string
    {
        return $_SERVER['DOCUMENT_ROOT'] . '/private_html/payroll_exports';
    }

    /**
     * Get export by ID
     */
    public function getExport(int $exportId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                e.*,
                u.username as exported_by_name
            FROM payroll_bank_exports e
            LEFT JOIN users u ON e.exported_by = u.id
            WHERE e.id = ?
        ");

        $stmt->execute([$exportId]);
        $export = $stmt->fetch(PDO::FETCH_ASSOC);

        return $export ?: null;
    }

    /**
     * Get all exports for date range
     */
    public function getExportsByDateRange(string $startDate, string $endDate): array
    {
        $stmt = $this->db->prepare("
            SELECT
                e.*,
                u.username as exported_by_name
            FROM payroll_bank_exports e
            LEFT JOIN users u ON e.exported_by = u.id
            WHERE DATE(e.created_at) BETWEEN ? AND ?
            ORDER BY e.created_at DESC
        ");

        $stmt->execute([$startDate, $endDate]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verify file integrity
     */
    public function verifyFileIntegrity(int $exportId): bool
    {
        $export = $this->getExport($exportId);

        if (!$export) {
            throw new \RuntimeException('Export not found');
        }

        if (!file_exists($export['file_path'])) {
            throw new \RuntimeException('Export file not found');
        }

        $currentHash = hash_file('sha256', $export['file_path']);

        return $currentHash === $export['file_hash'];
    }

    /**
     * Get payslips included in export
     */
    public function getExportPayslips(int $exportId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                p.*,
                s.first_name,
                s.last_name,
                s.email
            FROM payroll_payslips p
            INNER JOIN employee s ON p.staff_id = s.id
            WHERE p.bank_export_id = ?
            ORDER BY s.last_name, s.first_name
        ");

        $stmt->execute([$exportId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
