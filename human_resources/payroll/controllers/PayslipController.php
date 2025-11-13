<?php
declare(strict_types=1);

namespace PayrollModule\Controllers;

use PayrollModule\Services\PayslipService;
use PayrollModule\Services\BonusService;
use PayrollModule\Services\BankExportService;
use PDO;

/**
 * Payslip Controller
 *
 * HTTP API endpoints for payslip operations
 *
 * @package PayrollModule\Controllers
 * @version 1.0.0
 */
class PayslipController
{
    private PDO $db;
    private PayslipService $payslipService;
    private BonusService $bonusService;
    private BankExportService $bankExportService;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->payslipService = new PayslipService();
        $this->bonusService = new BonusService($db);
        $this->bankExportService = new BankExportService($db);
    }

    /**
     * POST /api/payroll/payslips/calculate
     * Calculate payslips for a pay period
     */
    public function calculatePayslips(): void
    {
        try {
            $input = $this->getJsonInput();

            $periodStart = $input['period_start'] ?? null;
            $periodEnd = $input['period_end'] ?? null;
            $staffIds = $input['staff_ids'] ?? null; // Optional: specific staff only

            if (!$periodStart || !$periodEnd) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'period_start and period_end are required'
                ], 400);
                return;
            }

            // Calculate payslips
            $results = $this->payslipService->calculatePayslipsForPeriod(
                $periodStart,
                $periodEnd,
                $staffIds
            );

            $this->jsonResponse([
                'success' => true,
                'data' => $results,
                'message' => sprintf('Calculated %d payslips', count($results))
            ]);

        } catch (\Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * GET /api/payroll/payslips/{id}
     * Get payslip details
     */
    public function getPayslip(int $id): void
    {
        try {
            $payslip = $this->payslipService->getPayslipById($id);

            if (!$payslip) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Payslip not found'
                ], 404);
                return;
            }

            // Get bonus breakdown
            $bonusSummary = $this->bonusService->getUnpaidBonusSummary($payslip['staff_id']);

            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'payslip' => $payslip,
                    'bonus_summary' => $bonusSummary
                ]
            ]);

        } catch (\Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * GET /api/payroll/payslips/period/{start}/{end}
     * List payslips for period
     */
    public function listPayslipsByPeriod(string $start, string $end): void
    {
        try {
            $status = $_GET['status'] ?? null;

            $payslips = $this->payslipService->getPayslipsByPeriod($start, $end, $status);

            $this->jsonResponse([
                'success' => true,
                'data' => $payslips,
                'count' => count($payslips)
            ]);

        } catch (\Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * GET /api/payroll/payslips/staff/{staffId}
     * Get all payslips for staff member
     */
    public function getStaffPayslips(int $staffId): void
    {
        try {
            $limit = (int)($_GET['limit'] ?? 10);

            $payslips = $this->payslipService->getStaffPayslips($staffId, $limit);

            $this->jsonResponse([
                'success' => true,
                'data' => $payslips,
                'count' => count($payslips)
            ]);

        } catch (\Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * POST /api/payroll/payslips/{id}/review
     * Mark payslip as reviewed
     */
    public function reviewPayslip(int $id): void
    {
        try {
            $userId = $this->getCurrentUserId();

            $success = $this->payslipService->reviewPayslip($id, $userId);

            if (!$success) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Failed to review payslip'
                ], 400);
                return;
            }

            $this->jsonResponse([
                'success' => true,
                'message' => 'Payslip reviewed successfully'
            ]);

        } catch (\Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * POST /api/payroll/payslips/{id}/approve
     * Approve payslip for payment
     */
    public function approvePayslip(int $id): void
    {
        try {
            $userId = $this->getCurrentUserId();

            $success = $this->payslipService->approvePayslip($id, $userId);

            if (!$success) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Failed to approve payslip'
                ], 400);
                return;
            }

            $this->jsonResponse([
                'success' => true,
                'message' => 'Payslip approved successfully'
            ]);

        } catch (\Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * POST /api/payroll/payslips/{id}/cancel
     * Cancel payslip
     */
    public function cancelPayslip(int $id): void
    {
        try {
            $input = $this->getJsonInput();
            $reason = $input['reason'] ?? 'Cancelled by user';

            $success = $this->payslipService->cancelPayslip($id, $reason);

            if (!$success) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Failed to cancel payslip'
                ], 400);
                return;
            }

            $this->jsonResponse([
                'success' => true,
                'message' => 'Payslip cancelled successfully'
            ]);

        } catch (\Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * POST /api/payroll/payslips/export/bank
     * Generate bank export file
     */
    public function exportToBank(): void
    {
        try {
            $input = $this->getJsonInput();

            $payslipIds = $input['payslip_ids'] ?? [];
            $fromAccount = $input['from_account'] ?? null;
            $period = $input['period'] ?? date('Y-m-W');

            if (empty($payslipIds)) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'payslip_ids array is required'
                ], 400);
                return;
            }

            if (!$fromAccount) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'from_account is required'
                ], 400);
                return;
            }

            $result = $this->bankExportService->generateBankFile(
                $payslipIds,
                $fromAccount,
                $period
            );

            $this->jsonResponse([
                'success' => true,
                'data' => $result,
                'message' => sprintf('Exported %d payslips', $result['payslip_count'])
            ]);

        } catch (\Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * GET /api/payroll/exports/{id}
     * Get bank export details
     */
    public function getExport(int $id): void
    {
        try {
            $export = $this->bankExportService->getExport($id);

            if (!$export) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Export not found'
                ], 404);
                return;
            }

            // Get payslips included
            $payslips = $this->bankExportService->getExportPayslips($id);

            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'export' => $export,
                    'payslips' => $payslips
                ]
            ]);

        } catch (\Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * GET /api/payroll/exports/{id}/verify
     * Verify bank export file integrity
     */
    public function verifyExport(int $id): void
    {
        try {
            $valid = $this->bankExportService->verifyFileIntegrity($id);

            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'export_id' => $id,
                    'valid' => $valid,
                    'verified_at' => date('Y-m-d H:i:s')
                ]
            ]);

        } catch (\Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * GET /api/payroll/exports
     * List bank exports
     */
    public function listExports(): void
    {
        try {
            $startDate = $_GET['start_date'] ?? date('Y-m-01');
            $endDate = $_GET['end_date'] ?? date('Y-m-t');

            $exports = $this->bankExportService->getExportsByDateRange($startDate, $endDate);

            $this->jsonResponse([
                'success' => true,
                'data' => $exports,
                'count' => count($exports)
            ]);

        } catch (\Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * GET /api/payroll/bonuses/unpaid/{staffId}
     * Get unpaid bonuses for staff
     */
    public function getUnpaidBonuses(int $staffId): void
    {
        try {
            $summary = $this->bonusService->getUnpaidBonusSummary($staffId);

            $this->jsonResponse([
                'success' => true,
                'data' => $summary
            ]);

        } catch (\Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * POST /api/payroll/bonuses/monthly
     * Create monthly bonus
     */
    public function createMonthlyBonus(): void
    {
        try {
            $input = $this->getJsonInput();

            $staffId = (int)($input['staff_id'] ?? 0);
            $amount = (float)($input['amount'] ?? 0);
            $type = $input['type'] ?? 'discretionary';
            $reason = $input['reason'] ?? '';
            $periodStart = $input['period_start'] ?? null;
            $periodEnd = $input['period_end'] ?? null;

            if ($staffId === 0 || $amount === 0.0 || !$periodStart || !$periodEnd) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'staff_id, amount, period_start, and period_end are required'
                ], 400);
                return;
            }

            $userId = $this->getCurrentUserId();

            $bonusId = $this->bonusService->createMonthlyBonus(
                $staffId,
                $amount,
                $type,
                $reason,
                $userId,
                $periodStart,
                $periodEnd
            );

            $this->jsonResponse([
                'success' => true,
                'data' => ['bonus_id' => $bonusId],
                'message' => 'Bonus created successfully'
            ]);

        } catch (\Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * POST /api/payroll/bonuses/{id}/approve
     * Approve monthly bonus
     */
    public function approveBonus(int $id): void
    {
        try {
            $userId = $this->getCurrentUserId();

            $success = $this->bonusService->approveMonthlyBonus($id, $userId);

            if (!$success) {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Failed to approve bonus'
                ], 400);
                return;
            }

            $this->jsonResponse([
                'success' => true,
                'message' => 'Bonus approved successfully'
            ]);

        } catch (\Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * GET /api/payroll/dashboard
     * Get payroll dashboard data
     */
    public function getDashboard(): void
    {
        try {
            $currentPeriod = $this->payslipService->getCurrentPeriod();
            $stats = [
                'current_period' => $currentPeriod,
                'payslips_calculated' => $this->payslipService->countByStatus('calculated'),
                'payslips_reviewed' => $this->payslipService->countByStatus('reviewed'),
                'payslips_approved' => $this->payslipService->countByStatus('approved'),
                'payslips_exported' => $this->payslipService->countByStatus('exported'),
                'total_pending_amount' => $this->payslipService->getTotalPendingAmount(),
                'staff_count' => $this->payslipService->getActiveStaffCount()
            ];

            $this->jsonResponse([
                'success' => true,
                'data' => $stats
            ]);

        } catch (\Exception $e) {
            $this->handleError($e);
        }
    }

    // ========================================================================
    // HELPER METHODS
    // ========================================================================

    private function getJsonInput(): array
    {
        $json = file_get_contents('php://input');
        $data = json_decode($json, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \InvalidArgumentException('Invalid JSON input');
        }

        return $data ?? [];
    }

    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT);
        exit;
    }

    private function getCurrentUserId(): int
    {
        if (!isset($_SESSION['user_id'])) {
            throw new \RuntimeException('User not authenticated');
        }

        return (int)$_SESSION['user_id'];
    }

    private function handleError(\Exception $e): void
    {
        error_log('PayslipController Error: ' . $e->getMessage());

        $this->jsonResponse([
            'success' => false,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ], 500);
    }
}
