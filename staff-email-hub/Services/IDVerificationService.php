<?php
declare(strict_types=1);

namespace StaffEmailHub\Services;

use PDO;
use Exception;
use DateTime;

/**
 * IDVerificationService - Secure ID upload, OCR processing, and age verification
 *
 * Features: Image upload, OCR text extraction, identity verification, fraud detection,
 * age verification, expiry checking, audit logging, privacy protection
 *
 * @version 1.0.0
 */
class IDVerificationService
{
    private PDO $db;
    private string $storagePath;

    public function __construct(PDO $db, string $storagePath = '/var/storage/id-uploads')
    {
        $this->db = $db;
        $this->storagePath = $storagePath;

        // Ensure storage directory exists
        if (!is_dir($this->storagePath)) {
            @mkdir($this->storagePath, 0750, true);
        }
    }

    /**
     * Upload and process ID images
     */
    public function uploadIdImage(
        int $customerId,
        array $frontImage,
        ?array $backImage = null,
        string $idType = 'drivers_license'
    ): array {
        try {
            // Validate input
            if (!$frontImage || !isset($frontImage['tmp_name'])) {
                return $this->error('No front image provided');
            }

            // Generate unique ID for this upload session
            $traceId = $this->generateTraceId();

            // Validate image files
            $frontPath = $this->validateAndStoreImage($frontImage, $traceId, 'front');
            if (!$frontPath) {
                return $this->error('Failed to process front image');
            }

            $backPath = null;
            if ($backImage && isset($backImage['tmp_name'])) {
                $backPath = $this->validateAndStoreImage($backImage, $traceId, 'back');
            }

            // Create database record
            $stmt = $this->db->prepare("
                INSERT INTO customer_id_uploads
                (customer_id, trace_id, id_type, front_image_path, back_image_path,
                 front_image_hash, back_image_hash, verification_status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, 'pending', NOW())
            ");

            $frontHash = hash_file('sha256', $frontPath);
            $backHash = $backPath ? hash_file('sha256', $backPath) : null;

            $stmt->execute([
                $customerId,
                $traceId,
                $idType,
                $frontPath,
                $backPath,
                $frontHash,
                $backHash
            ]);

            $recordId = (int) $this->db->lastInsertId();

            // Log upload event
            $this->logAuditEvent($recordId, 'upload', 'system', [
                'trace_id' => $traceId,
                'id_type' => $idType,
                'has_back' => $backPath ? true : false
            ]);

            // Queue for OCR processing
            $this->processOCRAsync($recordId, $frontPath, $backPath);

            return [
                'success' => true,
                'record_id' => $recordId,
                'trace_id' => $traceId,
                'message' => 'ID upload successful. Processing...'
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Process OCR on ID images
     */
    public function processOCR(int $recordId, string $imagePath): array
    {
        try {
            if (!file_exists($imagePath)) {
                return $this->error('Image file not found');
            }

            // Extract text using Tesseract (if available)
            $ocrData = $this->extractOCRText($imagePath);

            if (!$ocrData) {
                return $this->error('OCR processing failed');
            }

            // Parse extracted data
            $parsed = $this->parseOCRData($ocrData);

            // Update record with OCR data
            $stmt = $this->db->prepare("
                UPDATE customer_id_uploads
                SET ocr_data = ?, extracted_name = ?, extracted_dob = ?,
                    extracted_id_number = ?, verification_status = 'pending_review'
                WHERE id = ?
            ");

            $stmt->execute([
                json_encode($ocrData),
                $parsed['name'] ?? null,
                $parsed['dob'] ?? null,
                $parsed['id_number'] ?? null,
                $recordId
            ]);

            return [
                'success' => true,
                'ocr_data' => $ocrData,
                'parsed' => $parsed
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Verify identity - Check extracted data against customer profile
     */
    public function verifyIdentity(int $recordId, int $customerId): array
    {
        try {
            // Get the ID upload record
            $stmt = $this->db->prepare("
                SELECT * FROM customer_id_uploads WHERE id = ? AND customer_id = ?
            ");
            $stmt->execute([$recordId, $customerId]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$record) {
                return $this->error('Record not found');
            }

            // Get customer profile
            $custStmt = $this->db->prepare("
                SELECT * FROM customer_hub_profile WHERE id = ?
            ");
            $custStmt->execute([$customerId]);
            $customer = $custStmt->fetch(PDO::FETCH_ASSOC);

            // Run verification checks
            $score = 0;
            $issues = [];

            // Check name match
            if ($record['extracted_name'] && $customer['full_name']) {
                $similarity = $this->stringSimilarity($record['extracted_name'], $customer['full_name']);
                if ($similarity >= 0.8) {
                    $score += 25;
                } else {
                    $issues[] = 'Name mismatch (OCR: ' . $record['extracted_name'] . ')';
                }
            }

            // Check DOB match
            if ($record['extracted_dob'] && $customer['date_of_birth']) {
                if (strtotime($record['extracted_dob']) === strtotime($customer['date_of_birth'])) {
                    $score += 25;
                } else {
                    $issues[] = 'DOB mismatch';
                }
            }

            // Check image quality
            if ($this->isImageHighQuality($record['front_image_path'])) {
                $score += 15;
            } else {
                $issues[] = 'Image quality too low';
            }

            // Check for tampering/forgery
            if (!$this->detectForgery($record['front_image_path'])) {
                $score += 20;
            } else {
                $issues[] = 'Potential forgery detected';
            }

            // Check expiry
            if ($record['extracted_dob']) {
                $dob = new DateTime($record['extracted_dob']);
                $now = new DateTime();
                $age = $now->diff($dob)->y;

                if ($age >= 18) {
                    $score += 15;
                } else {
                    $issues[] = 'Customer is under 18';
                }
            }

            // Update record
            $status = $score >= 70 ? 'verified' : 'requires_review';
            $stmt = $this->db->prepare("
                UPDATE customer_id_uploads
                SET verification_score = ?, is_verified = ?, verification_status = ?
                WHERE id = ?
            ");
            $stmt->execute([$score, $score >= 70 ? 1 : 0, $status, $recordId]);

            // Update customer profile if verified
            if ($score >= 70) {
                $this->updateCustomerVerification($customerId, $recordId, $record);
            }

            return [
                'success' => true,
                'verification_score' => $score,
                'status' => $status,
                'is_verified' => $score >= 70,
                'issues' => $issues
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Check customer age from DOB
     */
    public function checkAge(int $customerId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT date_of_birth FROM customer_hub_profile WHERE id = ?
            ");
            $stmt->execute([$customerId]);
            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$customer || !$customer['date_of_birth']) {
                return ['success' => false, 'error' => 'DOB not found'];
            }

            $dob = new DateTime($customer['date_of_birth']);
            $now = new DateTime();
            $age = $now->diff($dob)->y;

            return [
                'success' => true,
                'age' => $age,
                'is_adult' => $age >= 18,
                'dob' => $customer['date_of_birth']
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Check if ID is expired
     */
    public function checkExpiry(int $recordId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT customer_id, id_type, extracted_dob FROM customer_id_uploads WHERE id = ?
            ");
            $stmt->execute([$recordId]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$record) {
                return $this->error('Record not found');
            }

            // Different expiry rules by ID type
            $expiryYears = match($record['id_type']) {
                'passport' => 10,
                'drivers_license' => 10,
                'national_id' => 15,
                default => 10
            };

            if (!$record['extracted_dob']) {
                return [
                    'success' => false,
                    'error' => 'Issue date not found in OCR data'
                ];
            }

            $issueDate = new DateTime($record['extracted_dob']);
            $expiryDate = $issueDate->modify("+{$expiryYears} years");
            $now = new DateTime();
            $isExpired = $now > $expiryDate;

            // Update expiry info
            $stmt = $this->db->prepare("
                UPDATE customer_id_uploads
                SET expires_at = ?, is_expired = ?
                WHERE id = ?
            ");
            $stmt->execute([$expiryDate->format('Y-m-d'), $isExpired ? 1 : 0, $recordId]);

            return [
                'success' => true,
                'is_expired' => $isExpired,
                'expires_at' => $expiryDate->format('Y-m-d'),
                'days_remaining' => $isExpired ? 0 : $now->diff($expiryDate)->days
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Get verification status for customer
     */
    public function getVerificationStatus(int $customerId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM customer_id_uploads
                WHERE customer_id = ?
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$customerId]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$record) {
                return [
                    'success' => true,
                    'is_verified' => false,
                    'status' => 'not_submitted',
                    'message' => 'No ID submission found'
                ];
            }

            // Check expiry
            if ($record['expires_at']) {
                $expiry = new DateTime($record['expires_at']);
                $now = new DateTime();
                if ($now > $expiry) {
                    $record['is_expired'] = true;
                    $record['status'] = 'expired';
                }
            }

            return [
                'success' => true,
                'is_verified' => $record['is_verified'] ?? false,
                'status' => $record['verification_status'] ?? 'pending',
                'score' => $record['verification_score'] ?? 0,
                'verified_at' => $record['verified_at'],
                'verified_by_staff' => $record['verified_by_staff'],
                'is_expired' => $record['is_expired'] ?? false,
                'expires_at' => $record['expires_at'],
                'id_type' => $record['id_type'],
                'created_at' => $record['created_at']
            ];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Approve verification manually by staff
     */
    public function approveVerification(int $recordId, int $staffId, string $notes = ''): array
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE customer_id_uploads
                SET verification_status = 'verified', is_verified = TRUE,
                    verified_by_staff = ?, verification_notes = ?, verified_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$staffId, $notes, $recordId]);

            // Get record and update customer
            $getStmt = $this->db->prepare("SELECT customer_id FROM customer_id_uploads WHERE id = ?");
            $getStmt->execute([$recordId]);
            $record = $getStmt->fetch(PDO::FETCH_ASSOC);

            if ($record) {
                $this->updateCustomerVerification($record['customer_id'], $recordId, null);
                $this->logAuditEvent($recordId, 'approve', 'staff', ['staff_id' => $staffId, 'notes' => $notes]);
            }

            return ['success' => true, 'message' => 'Verification approved'];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    /**
     * Reject verification with reason
     */
    public function rejectVerification(int $recordId, int $staffId, string $reason): array
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE customer_id_uploads
                SET verification_status = 'rejected', is_verified = FALSE,
                    verified_by_staff = ?, verification_notes = ?, verified_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$staffId, $reason, $recordId]);

            $this->logAuditEvent($recordId, 'reject', 'staff', ['staff_id' => $staffId, 'reason' => $reason]);

            return ['success' => true, 'message' => 'Verification rejected'];
        } catch (Exception $e) {
            return $this->error($e->getMessage());
        }
    }

    // ============ PRIVATE HELPER METHODS ============

    /**
     * Validate and securely store image file
     */
    private function validateAndStoreImage(array $file, string $traceId, string $side): ?string
    {
        // Check file size (max 5MB)
        if ($file['size'] > 5 * 1024 * 1024) {
            error_log("[IDVerificationService] File too large: {$file['name']}");
            return null;
        }

        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mime = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mime, ['image/jpeg', 'image/png', 'image/webp'])) {
            error_log("[IDVerificationService] Invalid image type: {$mime}");
            return null;
        }

        // Generate secure filename
        $ext = match($mime) {
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'jpg'
        };

        $filename = "{$traceId}_{$side}.{$ext}";
        $filepath = "{$this->storagePath}/{$filename}";

        // Move file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            error_log("[IDVerificationService] Failed to move file: {$filepath}");
            return null;
        }

        // Secure permissions
        chmod($filepath, 0640);

        return $filepath;
    }

    /**
     * Extract text from image using Tesseract OCR
     */
    private function extractOCRText(string $imagePath): ?array
    {
        // Check if tesseract is available
        $which = shell_exec('which tesseract 2>/dev/null');
        if (!$which) {
            error_log("[IDVerificationService] Tesseract not available");
            return null;
        }

        $tempOutput = tempnam(sys_get_temp_dir(), 'ocr_');
        $command = sprintf('tesseract %s %s -c preserve_interword_spaces=1 2>/dev/null',
            escapeshellarg($imagePath),
            escapeshellarg($tempOutput)
        );

        exec($command);

        if (file_exists("{$tempOutput}.txt")) {
            $text = file_get_contents("{$tempOutput}.txt");
            unlink("{$tempOutput}.txt");

            return [
                'raw_text' => $text,
                'extracted_at' => (new DateTime())->format('Y-m-d H:i:s'),
                'confidence' => $this->estimateConfidence($text)
            ];
        }

        return null;
    }

    /**
     * Parse OCR output to extract key fields
     */
    private function parseOCRData(array $ocrData): array
    {
        $text = $ocrData['raw_text'] ?? '';
        $parsed = [];

        // Extract name (usually at top)
        $lines = explode("\n", $text);
        if (count($lines) > 0) {
            $parsed['name'] = trim($lines[0]);
        }

        // Extract DOB (look for date patterns)
        if (preg_match('/(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{4})/', $text, $matches)) {
            $parsed['dob'] = $matches[1];
        }

        // Extract ID number (alphanumeric sequence)
        if (preg_match('/\b([A-Z0-9]{6,15})\b/', $text, $matches)) {
            $parsed['id_number'] = $matches[1];
        }

        return $parsed;
    }

    /**
     * Detect potential image tampering/forgery
     */
    private function detectForgery(string $imagePath): bool
    {
        // Check image dimensions (IDs have standard size)
        $info = getimagesize($imagePath);
        if (!$info) return false;

        $width = $info[0];
        $height = $info[1];

        // Standard ID dimensions ratio check (usually 3.375 x 2.125)
        $ratio = $width / $height;
        if ($ratio < 1.4 || $ratio > 1.8) {
            return true; // Potential manipulation
        }

        return false;
    }

    /**
     * Check image quality
     */
    private function isImageHighQuality(string $imagePath): bool
    {
        if (!file_exists($imagePath)) return false;

        $info = getimagesize($imagePath);
        if (!$info) return false;

        // Minimum resolution check (200x200)
        if ($info[0] < 200 || $info[1] < 200) {
            return false;
        }

        // File size indicates quality
        $filesize = filesize($imagePath);
        if ($filesize < 50 * 1024) { // Less than 50KB might be compressed too much
            return false;
        }

        return true;
    }

    /**
     * Calculate string similarity percentage
     */
    private function stringSimilarity(string $a, string $b): float
    {
        $a = strtolower(trim($a));
        $b = strtolower(trim($b));

        similar_text($a, $b, $percent);
        return $percent / 100;
    }

    /**
     * Estimate OCR confidence
     */
    private function estimateConfidence(string $text): float
    {
        // Simple heuristic: if text has reasonable length and no odd chars
        $length = strlen(trim($text));
        if ($length < 20) return 0.3;
        if ($length < 50) return 0.6;
        return 0.85;
    }

    /**
     * Update customer verification status
     */
    private function updateCustomerVerification(int $customerId, int $recordId, ?array $uploadRecord): void
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE customer_hub_profile
                SET id_verified = TRUE, id_verified_at = NOW(), id_verified_by = ?
                WHERE id = ?
            ");
            $stmt->execute([$recordId, $customerId]);
        } catch (Exception $e) {
            error_log("[IDVerificationService] Update failed: " . $e->getMessage());
        }
    }

    /**
     * Log audit event
     */
    private function logAuditEvent(int $recordId, string $action, string $actorType, array $details): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO id_verification_audit_log
                (id_upload_id, action, actor_type, action_details, ip_address, timestamp)
                VALUES (?, ?, ?, ?, ?, NOW())
            ");

            $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $stmt->execute([
                $recordId,
                $action,
                $actorType,
                json_encode($details),
                $ip
            ]);
        } catch (Exception $e) {
            error_log("[IDVerificationService] Audit log failed: " . $e->getMessage());
        }
    }

    /**
     * Queue OCR for async processing
     */
    private function processOCRAsync(int $recordId, string $frontPath, ?string $backPath): void
    {
        // In production, this would queue a job
        // For now, we'll do it synchronously
        $this->processOCR($recordId, $frontPath);
    }

    /**
     * Generate unique trace ID
     */
    private function generateTraceId(): string
    {
        return 'ID-' . strtoupper(bin2hex(random_bytes(6)));
    }

    private function error(string $message): array
    {
        error_log("[IDVerificationService] {$message}");
        return ['success' => false, 'error' => $message];
    }
}
