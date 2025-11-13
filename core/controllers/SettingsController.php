<?php
/**
 * CORE Module - Settings Controller
 * Manages user settings, password changes, and 2FA
 *
 * @package CIS\Core\Controllers
 */

namespace CIS\Core\Controllers;

class SettingsController
{
    protected $db;
    protected $userId;
    protected $settings = [];

    public function __construct($db = null, $userId = null)
    {
        // Database connection (placeholder - inject in real app)
        $this->db = $db;
        // Use CIS standard session variable: user_id (snake_case)
        $this->userId = $userId ?? ($_SESSION['user_id'] ?? null);
    }

    /**
     * Get all settings for user
     *
     * @param int $userId
     * @return array
     */
    public function getSettings($userId = null)
    {
        $userId = $userId ?? $this->userId;

        // Default settings structure
        $defaults = [
            'theme' => 'silver',
            'language' => 'en',
            'timezone' => 'UTC',
            'two_factor_enabled' => false,
            'two_factor_method' => null,
            'session_timeout' => 3600,
            'max_concurrent_sessions' => 5,
            'password_change_required' => false,
            'last_password_change' => null
        ];

        // In real app: fetch from database
        // For now, return defaults
        return $defaults;
    }

    /**
     * Update user settings
     *
     * @param int $userId
     * @param array $data
     * @return bool
     */
    public function updateSettings($userId = null, $data = [])
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        // Validate theme
        if (!empty($data['theme'])) {
            if (!in_array($data['theme'], array_keys(CORE_THEMES))) {
                throw new \Exception('Invalid theme selected');
            }
        }

        // Validate language
        if (!empty($data['language'])) {
            if (!in_array($data['language'], array_keys(CORE_LANGUAGES))) {
                throw new \Exception('Invalid language selected');
            }
        }

        // Validate timezone
        if (!empty($data['timezone'])) {
            if (!in_array($data['timezone'], array_keys(CORE_TIMEZONE_OPTIONS))) {
                throw new \Exception('Invalid timezone selected');
            }
        }

        // In real app: update database
        // UPDATE user_settings SET theme = ?, language = ?, timezone = ? WHERE user_id = ?

        // Log the change
        $this->logSettingChange($userId, 'settings_updated', $data);

        return true;
    }

    /**
     * Change user password
     *
     * @param int $userId
     * @param string $currentPassword
     * @param string $newPassword
     * @return bool
     */
    public function changePassword($userId = null, $currentPassword, $newPassword)
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        if (empty($currentPassword) || empty($newPassword)) {
            throw new \Exception('All password fields are required');
        }

        // Validate new password strength
        if (!$this->validatePasswordStrength($newPassword)) {
            throw new \Exception('Password does not meet strength requirements');
        }

        // Verify current password (in real app: fetch from DB and verify)
        // This is a placeholder - implement actual verification
        // $currentHash = $this->getUserPasswordHash($userId);
        // if (!password_verify($currentPassword, $currentHash)) {
        //     return false;
        // }

        // Check password history (can't reuse last 5 passwords)
        if ($this->isPasswordInHistory($userId, $newPassword)) {
            throw new \Exception('Cannot reuse recently used passwords');
        }

        // Hash new password
        $newHash = password_hash($newPassword, PASSWORD_BCRYPT, ['cost' => 12]);

        // In real app: update database
        // UPDATE user_settings SET password_hash = ?, last_password_change = NOW() WHERE user_id = ?
        // INSERT INTO password_history (user_id, password_hash, created_at) VALUES (?, ?, NOW())

        // Log the change
        $this->logSettingChange($userId, 'password_changed', ['timestamp' => date('Y-m-d H:i:s')]);

        return true;
    }

    /**
     * Enable two-factor authentication
     *
     * @param int $userId
     * @param string $method (totp|sms|email)
     * @return array
     */
    public function enableTwoFactor($userId = null, $method = 'totp')
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        if (!in_array($method, CORE_2FA_METHODS)) {
            throw new \Exception('Invalid 2FA method');
        }

        $result = [];

        if ($method === 'totp') {
            // Generate TOTP secret (QR code for authenticator apps)
            $secret = $this->generateTOTPSecret();
            $result['secret'] = $secret;
            $result['qr_code_url'] = $this->generateQRCode($userId, $secret);
            $result['method'] = 'totp';
        }
        elseif ($method === 'sms') {
            // Verify phone number and send test code
            $result['method'] = 'sms';
            $result['message'] = 'A verification code will be sent to your phone';
        }
        elseif ($method === 'email') {
            // Send verification email
            $result['method'] = 'email';
            $result['message'] = 'A verification code will be sent to your email';
        }

        // In real app: store in database as pending until verified
        // INSERT INTO user_2fa_methods (user_id, method, secret, verified, created_at) VALUES (...)

        return $result;
    }

    /**
     * Disable two-factor authentication
     *
     * @param int $userId
     * @return bool
     */
    public function disableTwoFactor($userId = null)
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        // In real app: delete from database
        // DELETE FROM user_2fa_methods WHERE user_id = ? AND verified = true

        $this->logSettingChange($userId, '2fa_disabled', ['timestamp' => date('Y-m-d H:i:s')]);

        return true;
    }

    /**
     * Validate password strength
     *
     * @param string $password
     * @return bool
     */
    protected function validatePasswordStrength($password)
    {
        $rules = [
            'length' => strlen($password) >= CORE_MIN_PASSWORD_LENGTH,
            'uppercase' => preg_match('/[A-Z]/', $password),
            'lowercase' => preg_match('/[a-z]/', $password),
            'number' => preg_match('/[0-9]/', $password),
            'special' => preg_match('/[!@#$%^&*()_+=\[\]{};:\'",.<>?\/\\|`~-]/', $password)
        ];

        // Require at least 4 out of 5 rules
        $passCount = array_sum($rules);

        return $passCount >= 4;
    }

    /**
     * Check if password is in history
     *
     * @param int $userId
     * @param string $password
     * @return bool
     */
    protected function isPasswordInHistory($userId, $password)
    {
        // In real app: check database
        // SELECT password_hash FROM password_history
        // WHERE user_id = ? ORDER BY created_at DESC LIMIT ?

        // For now, return false (not in history)
        return false;
    }

    /**
     * Generate TOTP secret
     *
     * @return string
     */
    protected function generateTOTPSecret()
    {
        // Generate random base32 string (32 characters)
        $chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $secret = '';

        for ($i = 0; $i < 32; $i++) {
            $secret .= $chars[random_int(0, strlen($chars) - 1)];
        }

        return $secret;
    }

    /**
     * Generate QR code URL for TOTP setup
     *
     * @param int $userId
     * @param string $secret
     * @return string
     */
    protected function generateQRCode($userId, $secret)
    {
        $issuer = 'CIS';
        $accountName = 'user_' . $userId;

        // Use Google Charts API for QR code
        $otpauthUrl = "otpauth://totp/$issuer:$accountName?secret=$secret&issuer=$issuer";

        return 'https://chart.googleapis.com/chart?chs=300x300&chld=M|0&cht=qr&chl=' .
               urlencode($otpauthUrl);
    }

    /**
     * Get user password hash (placeholder)
     *
     * @param int $userId
     * @return string|null
     */
    protected function getUserPasswordHash($userId)
    {
        // In real app: fetch from database
        // SELECT password_hash FROM users WHERE id = ?

        return null;
    }

    /**
     * Log settings change to activity log
     *
     * @param int $userId
     * @param string $action
     * @param array $details
     * @return bool
     */
    protected function logSettingChange($userId, $action, $details = [])
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
     * Get settings validation errors
     *
     * @param array $data
     * @return array
     */
    public function validateSettingsData($data = [])
    {
        $errors = [];

        if (!empty($data['theme']) && !in_array($data['theme'], array_keys(CORE_THEMES))) {
            $errors['theme'] = 'Invalid theme selected';
        }

        if (!empty($data['language']) && !in_array($data['language'], array_keys(CORE_LANGUAGES))) {
            $errors['language'] = 'Invalid language selected';
        }

        if (!empty($data['timezone']) && !in_array($data['timezone'], array_keys(CORE_TIMEZONE_OPTIONS))) {
            $errors['timezone'] = 'Invalid timezone selected';
        }

        return $errors;
    }

    /**
     * Configure message delivery settings (Chat Feature)
     *
     * @param int $userId
     * @param bool $deliveryIndicators
     * @param bool $readReceipts
     * @param bool $typingIndicators
     * @return bool
     */
    public function configureMessageSettings($userId = null, $deliveryIndicators = true, $readReceipts = true, $typingIndicators = true)
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        // In real app: update database with message settings
        // UPDATE user_settings SET delivery_indicators = ?, read_receipts = ?, typing_indicators = ? WHERE user_id = ?

        $this->logSettingChange($userId, 'message_settings_updated', [
            'delivery_indicators' => $deliveryIndicators,
            'read_receipts' => $readReceipts,
            'typing_indicators' => $typingIndicators
        ]);

        return true;
    }

    /**
     * Set message delivery indicators visibility
     *
     * @param int $userId
     * @param bool $enabled
     * @return bool
     */
    public function setMessageDeliveryIndicators($userId = null, $enabled = true)
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        // In real app: update database
        // UPDATE user_settings SET delivery_indicators = ? WHERE user_id = ?

        $this->logSettingChange($userId, 'delivery_indicators_updated', ['enabled' => $enabled]);

        return true;
    }

    /**
     * Set read receipts visibility option
     *
     * @param int $userId
     * @param string $option (send_and_receive|receive_only|hide_all)
     * @return bool
     */
    public function setReadReceiptsVisibility($userId = null, $option = 'send_and_receive')
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        if (!in_array($option, CORE_READ_RECEIPT_OPTIONS)) {
            throw new \Exception('Invalid read receipts option');
        }

        // In real app: update database
        // UPDATE user_settings SET read_receipts_option = ? WHERE user_id = ?

        $this->logSettingChange($userId, 'read_receipts_updated', ['option' => $option]);

        return true;
    }

    /**
     * Set typing indicators toggle
     *
     * @param int $userId
     * @param bool $enabled
     * @return bool
     */
    public function setTypingIndicators($userId = null, $enabled = true)
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        // In real app: update database
        // UPDATE user_settings SET typing_indicators = ? WHERE user_id = ?

        $this->logSettingChange($userId, 'typing_indicators_updated', ['enabled' => $enabled]);

        return true;
    }

    /**
     * Set online status visibility
     *
     * @param int $userId
     * @param string $visibility (everyone|contacts|hidden)
     * @return bool
     */
    public function setOnlineStatusVisibility($userId = null, $visibility = 'everyone')
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        if (!in_array($visibility, ['everyone', 'contacts', 'hidden'])) {
            throw new \Exception('Invalid visibility option');
        }

        // In real app: update database
        // UPDATE user_settings SET online_status_visibility = ? WHERE user_id = ?

        $this->logSettingChange($userId, 'online_status_visibility_updated', ['visibility' => $visibility]);

        return true;
    }

    /**
     * Set message retention policy
     *
     * @param int $userId
     * @param int $days (0 = keep forever, null = default 30 days)
     * @return bool
     */
    public function setMessageRetentionPolicy($userId = null, $days = 30)
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        if ($days !== null && $days < 0) {
            throw new \Exception('Message retention days cannot be negative');
        }

        // In real app: update database
        // UPDATE user_settings SET message_retention_days = ? WHERE user_id = ?

        $this->logSettingChange($userId, 'message_retention_updated', ['days' => $days]);

        return true;
    }

    /**
     * Configure call settings (Chat Feature)
     *
     * @param int $userId
     * @param string $audioOption (enabled|contacts|disabled)
     * @param string $videoOption (enabled|contacts|disabled)
     * @param string $screenShareOption (enabled|contacts|disabled)
     * @return bool
     */
    public function configureCallSettings($userId = null, $audioOption = 'enabled', $videoOption = 'enabled', $screenShareOption = 'enabled')
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        $validOptions = ['enabled', 'contacts', 'disabled'];
        foreach (['audio' => $audioOption, 'video' => $videoOption, 'screen' => $screenShareOption] as $type => $option) {
            if (!in_array($option, $validOptions)) {
                throw new \Exception('Invalid ' . $type . ' option');
            }
        }

        // In real app: update database
        // UPDATE user_settings SET audio_calls = ?, video_calls = ?, screen_sharing = ? WHERE user_id = ?

        $this->logSettingChange($userId, 'call_settings_updated', [
            'audio' => $audioOption,
            'video' => $videoOption,
            'screen_share' => $screenShareOption
        ]);

        return true;
    }

    /**
     * Set notification sounds
     *
     * @param int $userId
     * @param string $messageSound
     * @param string $callSound
     * @param bool $vibrationEnabled
     * @return bool
     */
    public function setNotificationSounds($userId = null, $messageSound = 'default', $callSound = 'default', $vibrationEnabled = true)
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        $validSounds = ['none', 'default', 'bell', 'chime', 'pop', 'ring', 'digital'];
        if (!in_array($messageSound, $validSounds) || !in_array($callSound, $validSounds)) {
            throw new \Exception('Invalid sound option');
        }

        // In real app: update database
        // UPDATE user_settings SET message_sound = ?, call_sound = ?, vibration_enabled = ? WHERE user_id = ?

        $this->logSettingChange($userId, 'notification_sounds_updated', [
            'message_sound' => $messageSound,
            'call_sound' => $callSound,
            'vibration' => $vibrationEnabled
        ]);

        return true;
    }

    /**
     * Set Do Not Disturb schedule
     *
     * @param int $userId
     * @param string $startTime (HH:MM format)
     * @param string $endTime (HH:MM format)
     * @param bool $allowCalls
     * @return bool
     */
    public function setDoNotDisturbSchedule($userId = null, $startTime = '22:00', $endTime = '08:00', $allowCalls = false)
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        // Validate time format HH:MM
        if (!preg_match('/^\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
            throw new \Exception('Invalid time format. Use HH:MM');
        }

        // In real app: update database
        // UPDATE user_settings SET dnd_enabled = true, dnd_start_time = ?, dnd_end_time = ?, dnd_allow_calls = ? WHERE user_id = ?

        $this->logSettingChange($userId, 'dnd_schedule_updated', [
            'start_time' => $startTime,
            'end_time' => $endTime,
            'allow_calls' => $allowCalls
        ]);

        return true;
    }

    /**
     * Get chat notification settings
     *
     * @param int $userId
     * @return array
     */
    public function getChatNotificationSettings($userId = null)
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        // In real app: fetch from database
        $defaults = [
            'delivery_indicators' => true,
            'read_receipts' => true,
            'typing_indicators' => true,
            'online_visibility' => 'everyone',
            'message_sound' => 'default',
            'call_sound' => 'default',
            'vibration_enabled' => true,
            'dnd_enabled' => false,
            'dnd_start_time' => '22:00',
            'dnd_end_time' => '08:00',
            'dnd_allow_calls' => false
        ];

        return $defaults;
    }

    /**
     * Validate message settings data
     *
     * @param array $data
     * @return array
     */
    public function validateMessageSettings($data = [])
    {
        $errors = [];

        if (!empty($data['read_receipts_option']) && !in_array($data['read_receipts_option'], CORE_READ_RECEIPT_OPTIONS)) {
            $errors['read_receipts'] = 'Invalid read receipts option';
        }

        if (!empty($data['online_visibility']) && !in_array($data['online_visibility'], ['everyone', 'contacts', 'hidden'])) {
            $errors['online_visibility'] = 'Invalid online visibility option';
        }

        return $errors;
    }
}
?>
