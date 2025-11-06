<?php
/**
 * Age Verification Service
 *
 * Handles post-payment age verification workflow with ID uploads, AI analysis,
 * manual review, and fraud blacklist management.
 *
 * @package CIS\Modules\EcommerceOps\Services
 */

namespace CIS\Modules\EcommerceOps;

class AgeVerificationService {

    private $db;
    private $enabled;
    private $expiryDays;
    private $aiEnabled;
    private $storagePath;

    /**
     * Constructor
     */
    public function __construct() {
        global $conn;
        $this->db = $conn;
        $this->enabled = ecomm_env('AGE_VERIFICATION_ENABLED', 'true') === 'true';
        $this->expiryDays = (int)ecomm_env('AGE_VERIFICATION_EXPIRY_DAYS', 7);
        $this->aiEnabled = ecomm_env('AGE_VERIFICATION_AI_ENABLED', 'true') === 'true';
        $this->storagePath = ecomm_env('AGE_VERIFICATION_STORAGE_PATH', $_SERVER['DOCUMENT_ROOT'] . '/secure/id-photos/');

        // Ensure storage directory exists
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0700, true);
        }
    }

    /**
     * Check if customer needs age verification
     *
     * @param int $customerId
     * @return bool
     */
    public function needsVerification(int $customerId): bool {
        if (!$this->enabled) {
            return false;
        }

        // Check if customer already approved
        $stmt = $this->db->prepare("
            SELECT id FROM ecommerce_age_verifications
            WHERE customer_id = ?
            AND verification_status = 'approved'
            AND verified_at > DATE_SUB(NOW(), INTERVAL ? DAY)
            LIMIT 1
        ");

        $stmt->execute([$customerId, $this->expiryDays]);
        $approved = $stmt->fetch(\PDO::FETCH_ASSOC);

        return !$approved;
    }

    /**
     * Check if customer is blacklisted
     *
     * @param string $email
     * @param string|null $phone
     * @param string|null $address
     * @return array|null Blacklist entry if found
     */
    public function checkBlacklist(string $email, ?string $phone = null, ?string $address = null): ?array {
        $where = ['is_active = 1'];
        $params = [];

        // Check email
        $where[] = "email = ?";
        $params[] = $email;

        // Check phone
        if ($phone) {
            $where[] = "OR phone = ? OR mobile = ?";
            $params[] = $phone;
            $params[] = $phone;
        }

        // Check address
        if ($address) {
            $where[] = "OR LOWER(address_line1) = LOWER(?)";
            $params[] = $address;
        }

        $whereSQL = implode(' ', $where);

        $stmt = $this->db->prepare("
            SELECT * FROM ecommerce_fraud_blacklist
            WHERE $whereSQL
            LIMIT 1
        ");

        $stmt->execute($params);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Create age verification request for order
     *
     * @param int $orderId
     * @param int $customerId
     * @return int Verification ID
     */
    public function createVerificationRequest(int $orderId, int $customerId): int {
        $stmt = $this->db->prepare("
            INSERT INTO ecommerce_age_verifications
            (order_id, customer_id, verification_status, created_at)
            VALUES (?, ?, 'pending', NOW())
        ");

        $stmt->execute([$orderId, $customerId]);
        $verificationId = $this->db->lastInsertId();

        ecomm_log_error("Age verification request created", [
            'verification_id' => $verificationId,
            'order_id' => $orderId,
            'customer_id' => $customerId
        ]);

        return $verificationId;
    }

    /**
     * Get verification by ID
     *
     * @param int $verificationId
     * @return array|null
     */
    public function getVerification(int $verificationId): ?array {
        $stmt = $this->db->prepare("
            SELECT
                av.*,
                c.first_name as customer_first_name,
                c.last_name as customer_last_name,
                c.email as customer_email,
                o.id as order_id,
                o.total_price as order_total
            FROM ecommerce_age_verifications av
            LEFT JOIN vend_customers c ON av.customer_id = c.id
            LEFT JOIN vend_sales o ON av.order_id = o.id
            WHERE av.id = ?
        ");

        $stmt->execute([$verificationId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get verification by customer
     *
     * @param int $customerId
     * @return array|null
     */
    public function getCustomerVerification(int $customerId): ?array {
        $stmt = $this->db->prepare("
            SELECT * FROM ecommerce_age_verifications
            WHERE customer_id = ?
            ORDER BY created_at DESC
            LIMIT 1
        ");

        $stmt->execute([$customerId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get pending verifications
     *
     * @param int $limit
     * @return array
     */
    public function getPendingVerifications(int $limit = 50): array {
        $stmt = $this->db->prepare("
            SELECT
                av.*,
                c.first_name as customer_first_name,
                c.last_name as customer_last_name,
                c.email as customer_email,
                o.id as order_id,
                o.total_price as order_total
            FROM ecommerce_age_verifications av
            LEFT JOIN vend_customers c ON av.customer_id = c.id
            LEFT JOIN vend_sales o ON av.order_id = o.id
            WHERE av.verification_status = 'pending'
            AND av.id_photo_path IS NOT NULL
            ORDER BY av.created_at ASC
            LIMIT ?
        ");

        $stmt->execute([$limit]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    /**
     * Upload ID photo with STRICT PRIVACY CONTROLS
     *
     * @param int $verificationId
     * @param string $photoData Base64 encoded photo or file path
     * @param string $photoType (passport|license)
     * @param string $uploadIp IP address of uploader (for audit)
     * @return bool
     */
    public function uploadIdPhoto(int $verificationId, string $photoData, string $photoType, string $uploadIp = ''): bool {
        $verification = $this->getVerification($verificationId);

        if (!$verification) {
            return false;
        }

        // Validate photo type
        if (!in_array($photoType, ['passport', 'license'])) {
            ecomm_log_error("Invalid photo type", [
                'verification_id' => $verificationId,
                'photo_type' => $photoType
            ]);
            return false;
        }

        // Decode photo data
        if (strpos($photoData, 'data:image') === 0) {
            // Base64 encoded
            $photoData = explode(',', $photoData)[1];
            $photoData = base64_decode($photoData);
        } elseif (file_exists($photoData)) {
            // File path
            $photoData = file_get_contents($photoData);
        }

        // Validate image data
        if (!$photoData || strlen($photoData) < 1000) {
            ecomm_log_error("Invalid photo data", ['verification_id' => $verificationId]);
            return false;
        }

        // Check file size (max 10MB)
        if (strlen($photoData) > 10485760) {
            ecomm_log_error("Photo too large", [
                'verification_id' => $verificationId,
                'size' => strlen($photoData)
            ]);
            return false;
        }

        // Strip EXIF data for privacy (remove GPS, camera info)
        $photoData = $this->stripExifData($photoData);

        // Generate unique filename (NO customer info in filename)
        $filename = $verificationId . '_' . time() . '_' . $photoType . '.jpg';
        $filepath = $this->storagePath . $filename;

        // Ensure directory has correct permissions (0700)
        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0700, true);
        }
        chmod($this->storagePath, 0700);

        // Save photo with restrictive permissions
        if (!file_put_contents($filepath, $photoData)) {
            ecomm_log_error("Failed to save ID photo", [
                'verification_id' => $verificationId,
                'filepath' => $filepath
            ]);
            return false;
        }

        // Set file permissions (0600 - owner read/write only)
        chmod($filepath, 0600);

        // Update verification record (store ONLY filename, not full path)
        $stmt = $this->db->prepare("
            UPDATE ecommerce_age_verifications
            SET id_photo_path = ?,
                id_photo_type = ?,
                id_uploaded_at = NOW(),
                upload_ip_address = ?
            WHERE id = ?
        ");

        $result = $stmt->execute([$filename, $photoType, $uploadIp, $verificationId]);

        if ($result) {
            // Audit log (NO customer PII in log)
            ecomm_log_error("ID photo uploaded", [
                'verification_id' => $verificationId,
                'photo_type' => $photoType,
                'file_size' => strlen($photoData),
                'upload_ip' => $uploadIp
            ]);

            // Trigger AI analysis if enabled
            if ($this->aiEnabled) {
                $this->analyzeIdWithAI($verificationId);
            }
        }

        return $result;
    }

    /**
     * Strip EXIF data from image for privacy
     * Removes GPS coordinates, camera info, timestamps
     *
     * @param string $imageData
     * @return string Cleaned image data
     */
    private function stripExifData(string $imageData): string {
        // Create image from string
        $image = imagecreatefromstring($imageData);

        if ($image === false) {
            return $imageData; // Return original if can't process
        }

        // Start output buffering
        ob_start();
        imagejpeg($image, null, 90); // Re-save without EXIF
        $cleanedData = ob_get_clean();

        imagedestroy($image);

        return $cleanedData;
    }

    /**
     * Get ID photo with ACCESS CONTROL
     * Returns time-limited token URL, not direct file path
     *
     * @param int $verificationId
     * @param int $staffId Requesting staff member
     * @return string|null Time-limited URL or null if denied
     */
    public function getPhotoUrl(int $verificationId, int $staffId): ?string {
        // Check staff has permission
        if (!$this->checkStaffPermission($staffId, 'age_verification_review')) {
            ecomm_log_error("Unauthorized photo access attempt", [
                'verification_id' => $verificationId,
                'staff_id' => $staffId,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
            ]);
            return null;
        }

        $verification = $this->getVerification($verificationId);

        if (!$verification || !$verification['id_photo_path']) {
            return null;
        }

        // Generate time-limited access token (expires in 5 minutes)
        $token = bin2hex(random_bytes(32));
        $expires = time() + 300; // 5 minutes

        // Store token in session or cache
        $_SESSION['photo_access_tokens'][$token] = [
            'verification_id' => $verificationId,
            'staff_id' => $staffId,
            'expires' => $expires,
            'filename' => $verification['id_photo_path']
        ];

        // Audit log access
        $this->logPhotoAccess($verificationId, $staffId, 'view');

        // Return URL with token
        return "/modules/ecommerce-ops/api/age-verification/view-photo.php?token=$token";
    }

    /**
     * Log photo access for audit trail
     *
     * @param int $verificationId
     * @param int $staffId
     * @param string $action (view|download|delete)
     * @return void
     */
    private function logPhotoAccess(int $verificationId, int $staffId, string $action): void {
        $stmt = $this->db->prepare("
            INSERT INTO ecommerce_age_verification_access_log
            (verification_id, staff_id, action, ip_address, user_agent, created_at)
            VALUES (?, ?, ?, ?, ?, NOW())
        ");

        $stmt->execute([
            $verificationId,
            $staffId,
            $action,
            $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            $_SERVER['HTTP_USER_AGENT'] ?? 'unknown'
        ]);
    }

    /**
     * Check if staff has required permission
     *
     * @param int $staffId
     * @param string $permission
     * @return bool
     */
    private function checkStaffPermission(int $staffId, string $permission): bool {
        // TODO: Integrate with actual permission system
        // For now, check if user exists and has admin role
        $stmt = $this->db->prepare("
            SELECT id FROM users
            WHERE id = ?
            AND (role = 'admin' OR permissions LIKE ?)
        ");

        $stmt->execute([$staffId, "%$permission%"]);
        return $stmt->fetch(\PDO::FETCH_ASSOC) !== false;
    }

    /**
     * Auto-delete expired photos per retention policy
     * Should be run via CRON daily
     *
     * @return array Deletion summary
     */
    public function autoDeleteExpiredPhotos(): array {
        $deleted = [
            'approved' => 0,
            'rejected' => 0,
            'abandoned' => 0
        ];

        // Delete approved verifications older than 7 days
        $stmt = $this->db->prepare("
            SELECT id, id_photo_path FROM ecommerce_age_verifications
            WHERE verification_status = 'approved'
            AND verified_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
            AND id_photo_path IS NOT NULL
        ");
        $stmt->execute();

        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            if ($this->deletePhoto($row['id'])) {
                $deleted['approved']++;
            }
        }

        // Delete rejected verifications older than 30 days
        $stmt = $this->db->prepare("
            SELECT id, id_photo_path FROM ecommerce_age_verifications
            WHERE verification_status = 'rejected'
            AND rejected_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND id_photo_path IS NOT NULL
        ");
        $stmt->execute();

        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            if ($this->deletePhoto($row['id'])) {
                $deleted['rejected']++;
            }
        }

        // Delete abandoned verifications (uploaded but not reviewed for 30 days)
        $stmt = $this->db->prepare("
            SELECT id, id_photo_path FROM ecommerce_age_verifications
            WHERE verification_status = 'pending'
            AND id_uploaded_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
            AND id_photo_path IS NOT NULL
        ");
        $stmt->execute();

        foreach ($stmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            if ($this->deletePhoto($row['id'])) {
                $deleted['abandoned']++;
            }
        }

        ecomm_log_error("Auto-deleted expired ID photos", $deleted);

        return $deleted;
    }

    /**
     * Delete ID photo securely
     *
     * @param int $verificationId
     * @return bool
     */
    private function deletePhoto(int $verificationId): bool {
        $verification = $this->getVerification($verificationId);

        if (!$verification || !$verification['id_photo_path']) {
            return false;
        }

        $filepath = $this->storagePath . $verification['id_photo_path'];

        // Secure deletion: overwrite with random data before unlinking
        if (file_exists($filepath)) {
            $filesize = filesize($filepath);
            $handle = fopen($filepath, 'w');
            fwrite($handle, random_bytes($filesize)); // Overwrite with random data
            fclose($handle);
            unlink($filepath); // Delete file
        }

        // Clear photo path from database
        $stmt = $this->db->prepare("
            UPDATE ecommerce_age_verifications
            SET id_photo_path = NULL,
                id_deleted_at = NOW()
            WHERE id = ?
        ");

        return $stmt->execute([$verificationId]);
    }    /**
     * Analyze ID photo with AI
     *
     * @param int $verificationId
     * @return bool
     */
    private function analyzeIdWithAI(int $verificationId): bool {
        $verification = $this->getVerification($verificationId);

        if (!$verification || !$verification['id_photo_path']) {
            return false;
        }

        // TODO: Integrate with actual AI service (OpenAI Vision API, AWS Rekognition, etc)
        // For now, just log that AI analysis would happen here

        ecomm_log_error("AI analysis triggered (placeholder)", [
            'verification_id' => $verificationId
        ]);

        // Placeholder: Mark as needing manual review
        $stmt = $this->db->prepare("
            UPDATE ecommerce_age_verifications
            SET ai_analysis_result = 'pending_manual_review',
                ai_analyzed_at = NOW()
            WHERE id = ?
        ");

        return $stmt->execute([$verificationId]);
    }

    /**
     * Approve verification
     *
     * @param int $verificationId
     * @param int $staffId
     * @param string $notes
     * @return bool
     */
    public function approve(int $verificationId, int $staffId, string $notes = ''): bool {
        $verification = $this->getVerification($verificationId);

        if (!$verification) {
            return false;
        }

        // Update verification status
        $stmt = $this->db->prepare("
            UPDATE ecommerce_age_verifications
            SET verification_status = 'approved',
                verified_at = NOW(),
                verified_by_staff_id = ?,
                verification_notes = ?
            WHERE id = ?
        ");

        $result = $stmt->execute([$staffId, $notes, $verificationId]);

        if ($result) {
            ecomm_log_error("Age verification approved", [
                'verification_id' => $verificationId,
                'customer_id' => $verification['customer_id'],
                'staff_id' => $staffId
            ]);

            // Send approval notification
            $notificationService = new NotificationService();
            $orderService = new OrderService();
            $order = $orderService->getOrder($verification['order_id']);

            if ($order) {
                $notificationService->notifyAgeVerificationApproved($order);
            }
        }

        return $result;
    }

    /**
     * Reject verification
     *
     * @param int $verificationId
     * @param int $staffId
     * @param string $reason
     * @param bool $addToBlacklist
     * @return bool
     */
    public function reject(int $verificationId, int $staffId, string $reason, bool $addToBlacklist = false): bool {
        $verification = $this->getVerification($verificationId);

        if (!$verification) {
            return false;
        }

        // Update verification status
        $stmt = $this->db->prepare("
            UPDATE ecommerce_age_verifications
            SET verification_status = 'rejected',
                rejected_at = NOW(),
                verified_by_staff_id = ?,
                verification_notes = ?
            WHERE id = ?
        ");

        $result = $stmt->execute([$staffId, $reason, $verificationId]);

        if ($result) {
            ecomm_log_error("Age verification rejected", [
                'verification_id' => $verificationId,
                'customer_id' => $verification['customer_id'],
                'reason' => $reason,
                'staff_id' => $staffId
            ]);

            // Add to blacklist if requested
            if ($addToBlacklist) {
                $customerService = new CustomerService();
                $customerService->addToBlacklist($verification['customer_id'], $reason, $staffId);
            }

            // Cancel order and refund
            $orderService = new OrderService();
            $orderService->updateStatus($verification['order_id'], 'cancelled', $staffId);
            $orderService->addComment($verification['order_id'], "Age verification rejected: $reason. Refund processed.", false, $staffId);

            // Send rejection notification
            $notificationService = new NotificationService();
            $order = $orderService->getOrder($verification['order_id']);

            if ($order) {
                $notificationService->notifyAgeVerificationRejected($order);
            }
        }

        return $result;
    }

    /**
     * Get verification statistics
     *
     * @param array $filters
     * @return array
     */
    public function getStatistics(array $filters = []): array {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['date_from'])) {
            $where[] = "created_at >= ?";
            $params[] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $where[] = "created_at <= ?";
            $params[] = $filters['date_to'] . ' 23:59:59';
        }

        $whereSQL = implode(' AND ', $where);

        $stmt = $this->db->prepare("
            SELECT
                COUNT(*) as total_verifications,
                SUM(CASE WHEN verification_status = 'approved' THEN 1 ELSE 0 END) as approved_count,
                SUM(CASE WHEN verification_status = 'rejected' THEN 1 ELSE 0 END) as rejected_count,
                SUM(CASE WHEN verification_status = 'pending' THEN 1 ELSE 0 END) as pending_count,
                AVG(TIMESTAMPDIFF(HOUR, created_at, COALESCE(verified_at, rejected_at))) as avg_processing_hours
            FROM ecommerce_age_verifications
            WHERE $whereSQL
        ");

        $stmt->execute($params);
        return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
    }

    /**
     * Get blacklist entries
     *
     * @param int $page
     * @param int $perPage
     * @return array
     */
    public function getBlacklist(int $page = 1, int $perPage = 50): array {
        $offset = ($page - 1) * $perPage;

        // Get total count
        $countStmt = $this->db->prepare("
            SELECT COUNT(*) FROM ecommerce_fraud_blacklist WHERE is_active = 1
        ");
        $countStmt->execute();
        $total = $countStmt->fetchColumn();

        // Get blacklist entries
        $stmt = $this->db->prepare("
            SELECT
                fb.*,
                c.first_name as customer_first_name,
                c.last_name as customer_last_name,
                u.name as added_by_staff_name
            FROM ecommerce_fraud_blacklist fb
            LEFT JOIN vend_customers c ON fb.customer_id = c.id
            LEFT JOIN users u ON fb.added_by_staff_id = u.id
            WHERE fb.is_active = 1
            ORDER BY fb.created_at DESC
            LIMIT ? OFFSET ?
        ");

        $stmt->execute([$perPage, $offset]);

        return [
            'entries' => $stmt->fetchAll(\PDO::FETCH_ASSOC),
            'total' => $total,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => ceil($total / $perPage)
        ];
    }

    /**
     * Export blacklist for website caching
     *
     * @return array
     */
    public function exportBlacklist(): array {
        $stmt = $this->db->prepare("
            SELECT email, phone, mobile, address_line1
            FROM ecommerce_fraud_blacklist
            WHERE is_active = 1
        ");

        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }
}
