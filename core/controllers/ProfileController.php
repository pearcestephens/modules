<?php
/**
 * CORE Module - Profile Controller
 * Manages user profiles, avatars, and personal information
 *
 * @package CIS\Core\Controllers
 */

namespace CIS\Core\Controllers;

class ProfileController
{
    protected $db;
    protected $userId;

    public function __construct($db = null, $userId = null)
    {
        // Database connection (placeholder - inject in real app)
        $this->db = $db;
        // Use CIS standard session variable: user_id (snake_case)
        $this->userId = $userId ?? ($_SESSION['user_id'] ?? null);
    }

    /**
     * Get user profile
     *
     * @param int $userId
     * @return array
     */
    public function getProfile($userId = null)
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        // Default profile structure
        $defaults = [
            'user_id' => $userId,
            'first_name' => '',
            'last_name' => '',
            'email' => '',
            'phone' => '',
            'avatar_url' => null,
            'bio' => '',
            'street_address' => '',
            'city' => '',
            'state' => '',
            'postal_code' => '',
            'country' => '',
            'social_twitter' => '',
            'social_linkedin' => '',
            'social_website' => '',
            'role' => '',
            'outlet_id' => null,
            'member_since' => date('Y-m-d'),
            'last_updated' => date('Y-m-d H:i:s'),
            'completion_percentage' => 0
        ];

        // In real app: fetch from database
        // SELECT * FROM user_profiles WHERE user_id = ?

        return $defaults;
    }

    /**
     * Update user profile
     *
     * @param int $userId
     * @param array $data
     * @return bool
     */
    public function updateProfile($userId = null, $data = [])
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        // Validate required fields
        $errors = $this->validateProfileData($data);
        if (!empty($errors)) {
            throw new \Exception('Validation failed: ' . implode(', ', $errors));
        }

        // Sanitize inputs
        $sanitized = [
            'first_name' => htmlspecialchars($data['first_name'] ?? ''),
            'last_name' => htmlspecialchars($data['last_name'] ?? ''),
            'phone' => htmlspecialchars($data['phone'] ?? ''),
            'bio' => htmlspecialchars($data['bio'] ?? ''),
            'street_address' => htmlspecialchars($data['street_address'] ?? ''),
            'city' => htmlspecialchars($data['city'] ?? ''),
            'state' => htmlspecialchars($data['state'] ?? ''),
            'postal_code' => htmlspecialchars($data['postal_code'] ?? ''),
            'country' => htmlspecialchars($data['country'] ?? ''),
            'social_twitter' => filter_var($data['social_twitter'] ?? '', FILTER_VALIDATE_URL) ?: '',
            'social_linkedin' => filter_var($data['social_linkedin'] ?? '', FILTER_VALIDATE_URL) ?: '',
            'social_website' => filter_var($data['social_website'] ?? '', FILTER_VALIDATE_URL) ?: ''
        ];

        // In real app: update database
        // UPDATE user_profiles SET first_name = ?, last_name = ?, ... WHERE user_id = ?

        // Log the change
        $this->logProfileChange($userId, 'profile_updated', $sanitized);

        return true;
    }

    /**
     * Upload avatar
     *
     * @param int $userId
     * @param array $file ($_FILES array)
     * @return array
     */
    public function uploadAvatar($userId = null, $file = [])
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        if (empty($file) || empty($file['tmp_name'])) {
            throw new \Exception('No file uploaded');
        }

        // Validate file
        $errors = $this->validateAvatarFile($file);
        if (!empty($errors)) {
            throw new \Exception('File validation failed: ' . implode(', ', $errors));
        }

        // Generate unique filename
        $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
        $uploadDir = __DIR__ . '/../assets/uploads/avatars/';
        $filepath = $uploadDir . $filename;

        // Create directory if not exists
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }

        // Move uploaded file
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new \Exception('Failed to save uploaded file');
        }

        // Delete old avatar if exists
        $oldAvatar = $this->getAvatarPath($userId);
        if ($oldAvatar && file_exists($oldAvatar)) {
            unlink($oldAvatar);
        }

        // In real app: update database
        // UPDATE user_profiles SET avatar_url = ? WHERE user_id = ?

        // Log the change
        $this->logProfileChange($userId, 'avatar_uploaded', [
            'filename' => $filename,
            'size' => $file['size'],
            'mime_type' => $file['type']
        ]);

        return [
            'success' => true,
            'filename' => $filename,
            'url' => '/modules/core/assets/uploads/avatars/' . $filename
        ];
    }

    /**
     * Delete avatar
     *
     * @param int $userId
     * @return bool
     */
    public function deleteAvatar($userId = null)
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        $avatarPath = $this->getAvatarPath($userId);

        if ($avatarPath && file_exists($avatarPath)) {
            unlink($avatarPath);
        }

        // In real app: update database
        // UPDATE user_profiles SET avatar_url = NULL WHERE user_id = ?

        // Log the change
        $this->logProfileChange($userId, 'avatar_deleted', []);

        return true;
    }

    /**
     * Get avatar path
     *
     * @param int $userId
     * @return string|null
     */
    public function getAvatarPath($userId = null)
    {
        // In real app: fetch from database and return full path
        // SELECT avatar_url FROM user_profiles WHERE user_id = ?

        return null;
    }

    /**
     * Calculate profile completion percentage
     *
     * @param array $profile
     * @return int
     */
    public function calculateCompletion($profile = [])
    {
        $fields = [
            'first_name', 'last_name', 'email', 'phone',
            'bio', 'street_address', 'city', 'country'
        ];

        $completed = 0;
        foreach ($fields as $field) {
            if (!empty($profile[$field])) {
                $completed++;
            }
        }

        return intval(($completed / count($fields)) * 100);
    }

    /**
     * Get profile with completion percentage
     *
     * @param int $userId
     * @return array
     */
    public function getProfileWithCompletion($userId = null)
    {
        $profile = $this->getProfile($userId);
        $profile['completion_percentage'] = $this->calculateCompletion($profile);

        return $profile;
    }

    /**
     * Validate profile data
     *
     * @param array $data
     * @return array
     */
    public function validateProfileData($data = [])
    {
        $errors = [];

        // First name is required
        if (empty($data['first_name'])) {
            $errors['first_name'] = 'First name is required';
        } elseif (strlen($data['first_name']) < 2) {
            $errors['first_name'] = 'First name must be at least 2 characters';
        }

        // Last name is required
        if (empty($data['last_name'])) {
            $errors['last_name'] = 'Last name is required';
        } elseif (strlen($data['last_name']) < 2) {
            $errors['last_name'] = 'Last name must be at least 2 characters';
        }

        // Phone validation (if provided)
        if (!empty($data['phone'])) {
            if (!preg_match('/^[\d\s\-\+\(\)]+$/', $data['phone'])) {
                $errors['phone'] = 'Invalid phone number format';
            }
        }

        // Bio validation
        if (!empty($data['bio']) && strlen($data['bio']) > CORE_MAX_BIO_LENGTH) {
            $errors['bio'] = 'Bio must not exceed ' . CORE_MAX_BIO_LENGTH . ' characters';
        }

        // Social links validation (if provided)
        if (!empty($data['social_twitter'])) {
            if (!filter_var($data['social_twitter'], FILTER_VALIDATE_URL)) {
                $errors['social_twitter'] = 'Invalid Twitter URL';
            }
        }

        if (!empty($data['social_linkedin'])) {
            if (!filter_var($data['social_linkedin'], FILTER_VALIDATE_URL)) {
                $errors['social_linkedin'] = 'Invalid LinkedIn URL';
            }
        }

        if (!empty($data['social_website'])) {
            if (!filter_var($data['social_website'], FILTER_VALIDATE_URL)) {
                $errors['social_website'] = 'Invalid website URL';
            }
        }

        return $errors;
    }

    /**
     * Validate avatar file
     *
     * @param array $file
     * @return array
     */
    protected function validateAvatarFile($file = [])
    {
        $errors = [];

        // Check file size (max 5MB)
        if ($file['size'] > (CORE_MAX_AVATAR_SIZE * 1024 * 1024)) {
            $errors['size'] = 'File size must not exceed ' . CORE_MAX_AVATAR_SIZE . 'MB';
        }

        // Check file type
        $allowedMimes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        if (!in_array($file['type'], $allowedMimes)) {
            $errors['type'] = 'File must be an image (JPG, PNG, GIF, WebP)';
        }

        // Check file extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (!in_array($ext, $allowedExtensions)) {
            $errors['extension'] = 'File extension not allowed';
        }

        // Check if file is actually an image
        if (is_uploaded_file($file['tmp_name'])) {
            $imageinfo = @getimagesize($file['tmp_name']);
            if (!$imageinfo) {
                $errors['image'] = 'File is not a valid image';
            }
        }

        return $errors;
    }

    /**
     * Log profile change to activity log
     *
     * @param int $userId
     * @param string $action
     * @param array $details
     * @return bool
     */
    protected function logProfileChange($userId, $action, $details = [])
    {
        $logData = [
            'user_id' => $userId,
            'action' => $action,
            'details' => json_encode($details),
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
            'created_at' => date('Y-m-d H:i:s')
        ];

        // In real app: insert into user_activity_logs table
        // INSERT INTO user_activity_logs (user_id, action, details, ip_address, user_agent, created_at)
        // VALUES (?, ?, ?, ?, ?, ?)

        return true;
    }

    /**
     * Update user availability status (Chat Feature)
     *
     * @param int $userId
     * @param string $status (online|away|offline|do_not_disturb)
     * @return bool
     */
    public function updateAvailabilityStatus($userId = null, $status = 'online')
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        // Validate status
        if (!in_array($status, CORE_AVAILABILITY_STATUSES)) {
            throw new \Exception('Invalid availability status');
        }

        // In real app: update database
        // UPDATE user_profiles SET availability_status = ? WHERE user_id = ?

        $this->logProfileChange($userId, 'availability_status_updated', ['status' => $status]);

        return true;
    }

    /**
     * Update user status message (Chat Feature)
     *
     * @param int $userId
     * @param string $message
     * @return bool
     */
    public function updateStatusMessage($userId = null, $message = '')
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        // Limit message to 100 characters
        if (strlen($message) > 100) {
            throw new \Exception('Status message must not exceed 100 characters');
        }

        // In real app: update database
        // UPDATE user_profiles SET status_message = ? WHERE user_id = ?

        $this->logProfileChange($userId, 'status_message_updated', ['message' => $message]);

        return true;
    }

    /**
     * Get user availability status
     *
     * @param int $userId
     * @return string
     */
    public function getAvailabilityStatus($userId = null)
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        // In real app: fetch from database
        // SELECT availability_status FROM user_profiles WHERE user_id = ?

        return 'online'; // default
    }

    /**
     * Block another user (Chat Feature)
     *
     * @param int $userId
     * @param int $blockedUserId
     * @param string $reason
     * @return bool
     */
    public function blockUser($userId = null, $blockedUserId, $reason = 'other')
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        if ($userId === $blockedUserId) {
            throw new \Exception('You cannot block yourself');
        }

        if (!in_array($reason, CORE_BLOCK_REASONS)) {
            throw new \Exception('Invalid block reason');
        }

        // In real app: insert into user_blocks table
        // INSERT INTO user_blocks (user_id, blocked_user_id, reason, created_at)
        // VALUES (?, ?, ?, NOW())

        $this->logProfileChange($userId, 'user_blocked', ['blocked_user_id' => $blockedUserId, 'reason' => $reason]);

        return true;
    }

    /**
     * Unblock a user
     *
     * @param int $userId
     * @param int $blockedUserId
     * @return bool
     */
    public function unblockUser($userId = null, $blockedUserId)
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        // In real app: delete from user_blocks table
        // DELETE FROM user_blocks WHERE user_id = ? AND blocked_user_id = ?

        $this->logProfileChange($userId, 'user_unblocked', ['blocked_user_id' => $blockedUserId]);

        return true;
    }

    /**
     * Get list of blocked users
     *
     * @param int $userId
     * @return array
     */
    public function getBlockedUsers($userId = null)
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        // In real app: fetch from user_blocks table
        // SELECT * FROM user_blocks WHERE user_id = ? ORDER BY created_at DESC

        return [];
    }

    /**
     * Report another user (Chat Feature)
     *
     * @param int $userId
     * @param int $reportedUserId
     * @param string $reason
     * @param string $details
     * @return bool
     */
    public function reportUser($userId = null, $reportedUserId, $reason = 'other', $details = '')
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        if ($userId === $reportedUserId) {
            throw new \Exception('You cannot report yourself');
        }

        if (!in_array($reason, CORE_REPORT_REASONS)) {
            throw new \Exception('Invalid report reason');
        }

        if (strlen($details) > 500) {
            throw new \Exception('Report details must not exceed 500 characters');
        }

        // In real app: insert into user_reports table
        // INSERT INTO user_reports (reporter_id, reported_user_id, reason, details, status, created_at)
        // VALUES (?, ?, ?, ?, 'pending', NOW())

        $this->logProfileChange($userId, 'user_reported', [
            'reported_user_id' => $reportedUserId,
            'reason' => $reason,
            'details' => $details
        ]);

        return true;
    }

    /**
     * Set profile visibility (Chat Feature)
     *
     * @param int $userId
     * @param string $visibility (public|contacts|private)
     * @return bool
     */
    public function setProfileVisibility($userId = null, $visibility = 'contacts')
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        if (!in_array($visibility, ['public', 'contacts', 'private'])) {
            throw new \Exception('Invalid visibility level');
        }

        // In real app: update database
        // UPDATE user_profiles SET profile_visibility = ? WHERE user_id = ?

        $this->logProfileChange($userId, 'profile_visibility_updated', ['visibility' => $visibility]);

        return true;
    }

    /**
     * Get last seen timestamp (respecting privacy settings)
     *
     * @param int $userId
     * @param string $privacyLevel
     * @return string|null
     */
    public function getLastSeen($userId = null, $privacyLevel = 'everyone')
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        // In real app: fetch from database and check privacy settings
        // SELECT last_seen FROM user_profiles WHERE user_id = ?
        // Then check if viewing user has permission based on privacy_settings

        return null;
    }

    /**
     * Get user activity indicators (Chat Feature)
     *
     * @param int $userId
     * @return array
     */
    public function getActivityIndicators($userId = null)
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        // In real app: fetch from activity logs and determine status
        $indicators = [
            'is_online' => false,
            'last_seen' => null,
            'status' => 'offline',
            'status_message' => null,
            'last_activity' => null
        ];

        return $indicators;
    }

    /**
     * Validate availability status
     *
     * @param string $status
     * @return bool
     */
    protected function validateAvailabilityStatus($status)
    {
        return in_array($status, CORE_AVAILABILITY_STATUSES);
    }
}
?>
