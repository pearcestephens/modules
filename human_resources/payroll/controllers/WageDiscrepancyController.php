<?php
declare(strict_types=1);

namespace PayrollModule\Controllers;

/**
 * Wage Discrepancy Controller
 *
 * HTTP API endpoints for wage discrepancy management:
 * - POST /api/payroll/discrepancies/submit - Submit new discrepancy
 * - GET /api/payroll/discrepancies/:id - Get discrepancy details
 * - GET /api/payroll/discrepancies/pending - List pending discrepancies
 * - GET /api/payroll/discrepancies/my-history - Get staff's discrepancy history
 * - POST /api/payroll/discrepancies/:id/approve - Approve discrepancy (admin)
 * - POST /api/payroll/discrepancies/:id/decline - Decline discrepancy (admin)
 * - POST /api/payroll/discrepancies/:id/upload-evidence - Upload evidence file
 * - GET /api/payroll/discrepancies/statistics - Get system statistics (admin)
 *
 * @package PayrollModule\Controllers
 * @version 1.0.0
 */

use PayrollModule\Services\WageDiscrepancyService;
use PayrollModule\Lib\PayrollLogger;

class WageDiscrepancyController extends BaseController
{
    private WageDiscrepancyService $discrepancyService;

    /**
     * Constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->discrepancyService = new WageDiscrepancyService();
    }

    /**
     * Submit a new wage discrepancy
     *
     * POST /api/payroll/discrepancies/submit
     *
     * Required POST fields:
     * - payslip_id (int)
     * - discrepancy_type (string: underpaid_hours|overpaid_hours|missing_break_deduction|incorrect_break_deduction|missing_overtime|incorrect_rate|missing_bonus|missing_reimbursement|incorrect_deduction|duplicate_payment|missing_holiday_pay|other)
     * - description (string: min 20 chars)
     *
     * Optional fields:
     * - line_item_type (string: earnings|deduction|reimbursement)
     * - line_item_id (int)
     * - claimed_hours (float)
     * - claimed_amount (float)
     * - evidence_file (file upload)
     *
     * @return void Outputs JSON response
     */
    public function submit(): void
    {
        $this->requireAuth();
        $this->requirePost();
        $this->verifyCsrf();

        try {
            // Validate input
            $data = $this->validateInput([
                'payslip_id' => ['required', 'integer'],
                'discrepancy_type' => ['required', 'enum:underpaid_hours,overpaid_hours,missing_break_deduction,incorrect_break_deduction,missing_overtime,incorrect_rate,missing_bonus,missing_reimbursement,incorrect_deduction,duplicate_payment,missing_holiday_pay,other'],
                'description' => ['required', 'string', 'min:20'],
                'line_item_type' => ['optional', 'enum:earnings,deduction,reimbursement'],
                'line_item_id' => ['optional', 'integer'],
                'claimed_hours' => ['optional', 'float'],
                'claimed_amount' => ['optional', 'float']
            ]);

            // Add staff ID from session
            $data['staff_id'] = $this->getCurrentUserId();

            // Handle evidence upload if provided
            if (isset($_FILES['evidence_file']) && $_FILES['evidence_file']['error'] === UPLOAD_ERR_OK) {
                $uploadResult = $this->handleEvidenceUpload($_FILES['evidence_file']);
                $data['evidence_path'] = $uploadResult['path'];
                $data['evidence_hash'] = $uploadResult['hash'];

                // Run OCR if image/PDF
                if (in_array($uploadResult['mime'], ['image/jpeg', 'image/png', 'application/pdf'])) {
                    $ocrResult = $this->runOCR($uploadResult['path'], $uploadResult['mime']);
                    $data['ocr_data'] = $ocrResult;
                }
            }

            // Submit discrepancy
            $result = $this->discrepancyService->submitDiscrepancy($data);

            if ($result['success']) {
                $this->logger->info('Discrepancy submitted successfully', [
                    'discrepancy_id' => $result['discrepancy_id'],
                    'staff_id' => $data['staff_id'],
                    'type' => $data['discrepancy_type']
                ]);

                $this->jsonSuccess([
                    'discrepancy_id' => $result['discrepancy_id'],
                    'status' => $result['status'],
                    'ai_analysis' => $result['ai_analysis'],
                    'estimated_resolution_time' => $result['estimated_resolution_time'],
                    'message' => $result['status'] === 'auto_approved'
                        ? 'Your discrepancy has been automatically approved and will be processed in the next pay run'
                        : 'Your discrepancy has been submitted for review. You will be notified once it is processed.'
                ]);
            } else {
                $this->jsonError('Failed to submit discrepancy', $result['error'] ?? 'Unknown error');
            }

        } catch (\InvalidArgumentException $e) {
            $this->jsonError('Validation error', $e->getMessage(), 400);
        } catch (\Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Get discrepancy details
     *
     * GET /api/payroll/discrepancies/:id
     *
     * @param int $id Discrepancy ID
     * @return void Outputs JSON response
     */
    public function getDiscrepancy(int $id): void
    {
        $this->requireAuth();

        try {
            $discrepancy = $this->discrepancyService->getDiscrepancy($id);

            if (!$discrepancy) {
                $this->jsonError('Discrepancy not found', null, 404);
                return;
            }

            // Security: Staff can only view their own discrepancies
            if (!$this->isAdmin() && $discrepancy['staff_id'] !== $this->getCurrentUserId()) {
                $this->jsonError('Access denied', 'You can only view your own discrepancies', 403);
                return;
            }

            $this->jsonSuccess($discrepancy);

        } catch (\Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Get pending discrepancies for admin review
     *
     * GET /api/payroll/discrepancies/pending
     *
     * Optional query params:
     * - priority: Filter by priority (urgent|high|medium|low)
     * - discrepancy_type: Filter by type
     *
     * @return void Outputs JSON response
     */
    public function getPending(): void
    {
        $this->requireAuth();
        $this->requireAdmin(); // Only admins can view pending queue

        try {
            $filters = [];

            if (!empty($_GET['priority'])) {
                $filters['priority'] = $_GET['priority'];
            }

            if (!empty($_GET['discrepancy_type'])) {
                $filters['discrepancy_type'] = $_GET['discrepancy_type'];
            }

            $discrepancies = $this->discrepancyService->getPendingDiscrepancies($filters);

            $this->jsonSuccess([
                'discrepancies' => $discrepancies,
                'count' => count($discrepancies)
            ]);

        } catch (\Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Get staff member's discrepancy history
     *
     * GET /api/payroll/discrepancies/my-history
     *
     * Optional query params:
     * - limit: Number of records (default: 20)
     * - status: Filter by status
     *
     * @return void Outputs JSON response
     */
    public function getMyHistory(): void
    {
        $this->requireAuth();

        try {
            $staffId = $this->getCurrentUserId();
            $limit = min((int)($_GET['limit'] ?? 20), 100);
            $status = $_GET['status'] ?? null;

            $sql = "SELECT
                        wd.*,
                        ps.period_start, ps.period_end, ps.payment_date
                    FROM payroll_wage_discrepancies wd
                    JOIN payroll_payslips ps ON wd.payslip_id = ps.id
                    WHERE wd.staff_id = ?";

            $params = [$staffId];

            if ($status) {
                $sql .= " AND wd.status = ?";
                $params[] = $status;
            }

            $sql .= " ORDER BY wd.submitted_at DESC LIMIT ?";
            $params[] = $limit;

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $this->jsonSuccess([
                'history' => $history,
                'count' => count($history)
            ]);

        } catch (\Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Approve a discrepancy
     *
     * POST /api/payroll/discrepancies/:id/approve
     *
     * Optional POST fields:
     * - admin_notes (string)
     *
     * @param int $id Discrepancy ID
     * @return void Outputs JSON response
     */
    public function approve(int $id): void
    {
        $this->requireAuth();
        $this->requireAdmin(); // Only admins can approve
        $this->requirePost();
        $this->verifyCsrf();

        try {
            $data = [
                'approved_by' => $this->getCurrentUserId(),
                'admin_notes' => $_POST['admin_notes'] ?? null
            ];

            $result = $this->discrepancyService->approveDiscrepancy($id, $data);

            if ($result['success']) {
                $this->logger->info('Discrepancy approved', [
                    'discrepancy_id' => $id,
                    'approved_by' => $data['approved_by'],
                    'amendment_id' => $result['amendment_id']
                ]);

                $this->jsonSuccess([
                    'message' => 'Discrepancy approved successfully',
                    'amendment_id' => $result['amendment_id']
                ]);
            } else {
                $this->jsonError('Failed to approve discrepancy', $result['error'] ?? 'Unknown error');
            }

        } catch (\Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Decline a discrepancy
     *
     * POST /api/payroll/discrepancies/:id/decline
     *
     * Required POST fields:
     * - decline_reason (string: min 20 chars)
     *
     * Optional fields:
     * - admin_notes (string)
     *
     * @param int $id Discrepancy ID
     * @return void Outputs JSON response
     */
    public function decline(int $id): void
    {
        $this->requireAuth();
        $this->requireAdmin(); // Only admins can decline
        $this->requirePost();
        $this->verifyCsrf();

        try {
            $data = $this->validateInput([
                'decline_reason' => ['required', 'string', 'min:20'],
                'admin_notes' => ['optional', 'string']
            ]);

            $data['declined_by'] = $this->getCurrentUserId();

            $result = $this->discrepancyService->declineDiscrepancy($id, $data);

            if ($result['success']) {
                $this->logger->info('Discrepancy declined', [
                    'discrepancy_id' => $id,
                    'declined_by' => $data['declined_by'],
                    'reason' => $data['decline_reason']
                ]);

                $this->jsonSuccess([
                    'message' => 'Discrepancy declined'
                ]);
            } else {
                $this->jsonError('Failed to decline discrepancy', $result['error'] ?? 'Unknown error');
            }

        } catch (\InvalidArgumentException $e) {
            $this->jsonError('Validation error', $e->getMessage(), 400);
        } catch (\Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Upload evidence for existing discrepancy
     *
     * POST /api/payroll/discrepancies/:id/upload-evidence
     *
     * Required:
     * - evidence_file (file upload)
     *
     * @param int $id Discrepancy ID
     * @return void Outputs JSON response
     */
    public function uploadEvidence(int $id): void
    {
        $this->requireAuth();
        $this->requirePost();
        $this->verifyCsrf();

        try {
            // Get discrepancy
            $discrepancy = $this->discrepancyService->getDiscrepancy($id);
            if (!$discrepancy) {
                $this->jsonError('Discrepancy not found', null, 404);
                return;
            }

            // Security check
            if (!$this->isAdmin() && $discrepancy['staff_id'] !== $this->getCurrentUserId()) {
                $this->jsonError('Access denied', 'You can only upload evidence for your own discrepancies', 403);
                return;
            }

            // Validate file upload
            if (!isset($_FILES['evidence_file']) || $_FILES['evidence_file']['error'] !== UPLOAD_ERR_OK) {
                $this->jsonError('No file uploaded', null, 400);
                return;
            }

            // Handle upload
            $uploadResult = $this->handleEvidenceUpload($_FILES['evidence_file']);

            // Run OCR if applicable
            $ocrData = null;
            if (in_array($uploadResult['mime'], ['image/jpeg', 'image/png', 'application/pdf'])) {
                $ocrData = $this->runOCR($uploadResult['path'], $uploadResult['mime']);
            }

            // Update discrepancy
            $sql = "UPDATE payroll_wage_discrepancies
                    SET evidence_path = ?,
                        evidence_hash = ?,
                        ocr_data = ?
                    WHERE id = ?";

            $stmt = $this->db->prepare($sql);
            $stmt->execute([
                $uploadResult['path'],
                $uploadResult['hash'],
                $ocrData ? json_encode($ocrData) : null,
                $id
            ]);

            $this->logger->info('Evidence uploaded', [
                'discrepancy_id' => $id,
                'file_hash' => $uploadResult['hash']
            ]);

            $this->jsonSuccess([
                'message' => 'Evidence uploaded successfully',
                'evidence_hash' => $uploadResult['hash'],
                'ocr_data' => $ocrData
            ]);

        } catch (\Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Get system statistics
     *
     * GET /api/payroll/discrepancies/statistics
     *
     * @return void Outputs JSON response
     */
    public function getStatistics(): void
    {
        $this->requireAuth();
        $this->requireAdmin(); // Only admins can view statistics

        try {
            $stats = $this->discrepancyService->getStatistics();

            $this->jsonSuccess([
                'statistics' => $stats,
                'auto_approval_rate' => $stats['total'] > 0
                    ? round(($stats['auto_approved'] / $stats['total']) * 100, 1)
                    : 0
            ]);

        } catch (\Exception $e) {
            $this->handleError($e);
        }
    }

    /**
     * Handle evidence file upload
     *
     * @param array $file $_FILES entry
     * @return array Upload result with path, hash, mime
     */
    private function handleEvidenceUpload(array $file): array
    {
        // Validate file size (max 10MB)
        $maxSize = 10 * 1024 * 1024;
        if ($file['size'] > $maxSize) {
            throw new \RuntimeException('File too large (max 10MB)');
        }

        // Validate MIME type
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($file['tmp_name']);

        if (!in_array($mime, $allowedMimes)) {
            throw new \RuntimeException('Invalid file type. Allowed: JPG, PNG, GIF, PDF');
        }

        // Generate hash
        $hash = hash_file('sha256', $file['tmp_name']);

        // Create upload directory if not exists
        $uploadDir = $_SERVER['DOCUMENT_ROOT'] . '/../private/payroll_evidence';
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0750, true);
        }

        // Generate filename
        $ext = match($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'application/pdf' => 'pdf',
            default => 'bin'
        };

        $filename = $hash . '.' . $ext;
        $destination = $uploadDir . '/' . $filename;

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $destination)) {
            throw new \RuntimeException('Failed to save uploaded file');
        }

        return [
            'path' => $destination,
            'hash' => $hash,
            'mime' => $mime
        ];
    }

    /**
     * Run OCR on uploaded file
     *
     * @param string $filePath File path
     * @param string $mime MIME type
     * @return array|null OCR result
     */
    private function runOCR(string $filePath, string $mime): ?array
    {
        try {
            // TODO: Integrate with OpenAI Vision or similar OCR service
            // For now, return basic file info

            return [
                'processed' => true,
                'confidence' => 0.0,
                'text' => null,
                'date' => null,
                'total' => null,
                'note' => 'OCR processing to be implemented'
            ];

        } catch (\Exception $e) {
            $this->logger->error('OCR failed', [
                'file_path' => $filePath,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Require admin permission
     *
     * @return void
     * @throws \Exception if not admin
     */
    private function requireAdmin(): void
    {
        if (!$this->isAdmin()) {
            $this->jsonError('Access denied', 'Admin permission required', 403);
            exit;
        }
    }

    /**
     * Check if current user is admin
     *
     * @return bool
     */
    private function isAdmin(): bool
    {
        // TODO: Integrate with your permission system
        return !empty($_SESSION['is_admin']) || !empty($_SESSION['permissions']['payroll.manage_discrepancies']);
    }
}
