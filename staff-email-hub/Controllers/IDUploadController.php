<?php
declare(strict_types=1);

namespace StaffEmailHub\Controllers;

use StaffEmailHub\Services\IDVerificationService;
use StaffEmailHub\Services\CustomerHubService;
use PDO;
use Exception;

/**
 * IDUploadController - Handle ID verification uploads and workflows
 *
 * Routes:
 * POST /id-verification/upload - Upload ID images
 * GET /id-verification/status/{customerId} - Check verification status
 * POST /id-verification/approve/{recordId} - Approve by staff
 * POST /id-verification/reject/{recordId} - Reject by staff
 * GET /id-verification/pending - List pending verifications (staff only)
 * POST /id-verification/check-age/{customerId} - Check customer age
 *
 * @version 1.0.0
 */
class IDUploadController
{
    private IDVerificationService $idService;
    private CustomerHubService $customerService;
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->idService = new IDVerificationService($db);
        $this->customerService = new CustomerHubService($db);
    }

    /**
     * POST /id-verification/upload - Upload ID document for verification
     */
    public function upload(
        int $customerId,
        array $frontImage,
        ?array $backImage = null,
        string $idType = 'drivers_license'
    ): array {
        try {
            // Validate ID type
            $validTypes = ['passport', 'drivers_license', 'national_id'];
            if (!in_array($idType, $validTypes)) {
                return $this->error('Invalid ID type');
            }

            // Validate images
            if (!$frontImage || !isset($frontImage['tmp_name'])) {
                return $this->error('Front image is required');
            }

            if (!file_exists($frontImage['tmp_name'])) {
                return $this->error('Invalid file upload');
            }

            // Process upload
            $result = $this->idService->uploadIdImage($customerId, $frontImage, $backImage, $idType);

            if (!$result['success']) {
                return $this->error($result['error']);
            }

            return [
                'status' => 'success',
                'data' => [
                    'message' => 'ID uploaded successfully',
                    'trace_id' => $result['trace_id'],
                    'record_id' => $result['record_id'],
                    'next_step' => 'Verification in progress. We will review and confirm within 24 hours.'
                ]
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * GET /id-verification/status/{customerId} - Get ID verification status
     */
    public function getStatus(int $customerId): array
    {
        try {
            $result = $this->idService->getVerificationStatus($customerId);

            if (!$result['success']) {
                return $this->error($result['error']);
            }

            return [
                'status' => 'success',
                'data' => $result
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * POST /id-verification/verify/{recordId} - Run automatic verification
     */
    public function runVerification(int $recordId): array
    {
        try {
            // Get the record
            $stmt = $this->db->prepare("SELECT customer_id, front_image_path FROM customer_id_uploads WHERE id = ?");
            $stmt->execute([$recordId]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$record) {
                return $this->error('Record not found', 404);
            }

            // Process OCR
            $ocrResult = $this->idService->processOCR($recordId, $record['front_image_path']);
            if (!$ocrResult['success']) {
                return $this->error('OCR processing failed');
            }

            // Run verification
            $verifyResult = $this->idService->verifyIdentity($recordId, $record['customer_id']);

            if (!$verifyResult['success']) {
                return $this->error('Verification check failed');
            }

            return [
                'status' => 'success',
                'data' => [
                    'verification_score' => $verifyResult['verification_score'],
                    'is_verified' => $verifyResult['is_verified'],
                    'status' => $verifyResult['status'],
                    'issues' => $verifyResult['issues']
                ]
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * POST /id-verification/approve/{recordId} - Approve verification (staff)
     */
    public function approveVerification(int $recordId, int $staffId, string $notes = ''): array
    {
        try {
            $result = $this->idService->approveVerification($recordId, $staffId, $notes);

            if (!$result['success']) {
                return $this->error($result['error']);
            }

            return [
                'status' => 'success',
                'data' => ['message' => 'ID verification approved']
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * POST /id-verification/reject/{recordId} - Reject verification (staff)
     */
    public function rejectVerification(int $recordId, int $staffId, string $reason): array
    {
        try {
            if (empty(trim($reason))) {
                return $this->error('Rejection reason is required');
            }

            $result = $this->idService->rejectVerification($recordId, $staffId, $reason);

            if (!$result['success']) {
                return $this->error($result['error']);
            }

            return [
                'status' => 'success',
                'data' => ['message' => 'ID verification rejected']
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * GET /id-verification/pending - List pending ID verifications (staff view)
     */
    public function getPending(int $page = 1, int $perPage = 20): array
    {
        try {
            $offset = ($page - 1) * $perPage;

            // Get count
            $countStmt = $this->db->prepare("
                SELECT COUNT(*) as count FROM customer_id_uploads
                WHERE verification_status IN ('pending', 'pending_review')
            ");
            $countStmt->execute();
            $total = $countStmt->fetch(PDO::FETCH_ASSOC)['count'];

            // Get pending records
            $stmt = $this->db->prepare("
                SELECT cid.*, cp.full_name, cp.email
                FROM customer_id_uploads cid
                JOIN customer_hub_profile cp ON cid.customer_id = cp.id
                WHERE cid.verification_status IN ('pending', 'pending_review')
                ORDER BY cid.created_at ASC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$perPage, $offset]);
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'status' => 'success',
                'data' => [
                    'records' => $records,
                    'count' => count($records),
                    'total' => $total,
                    'page' => $page,
                    'per_page' => $perPage,
                    'pages' => ceil($total / $perPage)
                ]
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * POST /id-verification/check-age/{customerId} - Check customer age
     */
    public function checkAge(int $customerId): array
    {
        try {
            $result = $this->idService->checkAge($customerId);

            if (!$result['success']) {
                return $this->error($result['error']);
            }

            $ageData = [
                'age' => $result['age'],
                'is_adult' => $result['is_adult'],
                'can_purchase_r18' => $result['is_adult'],
                'dob' => $result['dob']
            ];

            return [
                'status' => 'success',
                'data' => $ageData
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * GET /id-verification/expiry/{customerId} - Check ID expiry status
     */
    public function checkExpiry(int $customerId): array
    {
        try {
            // Get latest record
            $stmt = $this->db->prepare("
                SELECT id FROM customer_id_uploads
                WHERE customer_id = ?
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$customerId]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$record) {
                return $this->error('No ID record found');
            }

            $result = $this->idService->checkExpiry($record['id']);

            if (!$result['success']) {
                return $this->error($result['error']);
            }

            return [
                'status' => 'success',
                'data' => $result
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * GET /id-verification/stats - Get verification statistics (admin)
     */
    public function getStats(): array
    {
        try {
            // Total uploads
            $totalStmt = $this->db->prepare("SELECT COUNT(*) as count FROM customer_id_uploads");
            $totalStmt->execute();
            $total = $totalStmt->fetch(PDO::FETCH_ASSOC)['count'];

            // Verified
            $verifiedStmt = $this->db->prepare("SELECT COUNT(*) as count FROM customer_id_uploads WHERE is_verified = TRUE");
            $verifiedStmt->execute();
            $verified = $verifiedStmt->fetch(PDO::FETCH_ASSOC)['count'];

            // Pending
            $pendingStmt = $this->db->prepare("SELECT COUNT(*) as count FROM customer_id_uploads WHERE verification_status IN ('pending', 'pending_review')");
            $pendingStmt->execute();
            $pending = $pendingStmt->fetch(PDO::FETCH_ASSOC)['count'];

            // Rejected
            $rejectedStmt = $this->db->prepare("SELECT COUNT(*) as count FROM customer_id_uploads WHERE verification_status = 'rejected'");
            $rejectedStmt->execute();
            $rejected = $rejectedStmt->fetch(PDO::FETCH_ASSOC)['count'];

            // By ID type
            $byTypeStmt = $this->db->prepare("SELECT id_type, COUNT(*) as count FROM customer_id_uploads GROUP BY id_type");
            $byTypeStmt->execute();
            $byType = $byTypeStmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'status' => 'success',
                'data' => [
                    'total_uploads' => $total,
                    'verified' => $verified,
                    'verification_rate' => $total > 0 ? round(($verified / $total) * 100, 1) : 0,
                    'pending' => $pending,
                    'rejected' => $rejected,
                    'by_id_type' => $byType
                ]
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * POST /id-verification/re-verify/{recordId} - Request re-verification (customer)
     */
    public function requestReverification(int $recordId, int $customerId): array
    {
        try {
            // Verify ownership
            $stmt = $this->db->prepare("SELECT customer_id FROM customer_id_uploads WHERE id = ?");
            $stmt->execute([$recordId]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$record || $record['customer_id'] !== $customerId) {
                return $this->error('Access denied', 403);
            }

            // Reset verification status
            $updateStmt = $this->db->prepare("
                UPDATE customer_id_uploads
                SET verification_status = 'pending', is_verified = FALSE,
                    verified_by_staff = NULL, verified_at = NULL
                WHERE id = ?
            ");
            $updateStmt->execute([$recordId]);

            return [
                'status' => 'success',
                'data' => ['message' => 'Re-verification requested']
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    protected function error(string $message, int $code = 400): array
    {
        return [
            'status' => 'error',
            'error' => $message,
            'code' => $code
        ];
    }
}
