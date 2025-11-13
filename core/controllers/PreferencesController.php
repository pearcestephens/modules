<?php
/**
 * CORE Module - Preferences Controller
 * Manages user preferences, notifications, and dashboard customization
 *
 * @package CIS\Core\Controllers
 */

namespace CIS\Core\Controllers;

class PreferencesController
{
    protected $db;
    protected $userId;
    protected $preferences = [];

    public function __construct($db = null, $userId = null)
    {
        // Database connection (placeholder - inject in real app)
        $this->db = $db;
        // Use CIS standard session variable: user_id (snake_case)
        $this->userId = $userId ?? ($_SESSION['user_id'] ?? null);
    }

    /**
     * Get all preferences for user
     *
     * @param int $userId
     * @return array
     */
    public function getPreferences($userId = null)
    {
        $userId = $userId ?? $this->userId;

        // Default preferences structure
        $defaults = [
            // Dashboard
            'dashboard_layout' => '3-column',
            'items_per_page' => 25,
            'default_view' => 'grid',

            // Notifications
            'email_daily_digest' => true,
            'email_alerts' => true,
            'email_updates' => false,
            'in_app_notifications' => true,
            'push_notifications' => false,

            // Privacy
            'profile_visibility' => 'network',
            'show_online_status' => true,
            'allow_direct_messages' => true,
            'show_last_activity' => false,

            // Display
            'show_tooltips' => true,
            'show_animations' => true,
            'compact_mode' => false,

            // Email preferences
            'notification_frequency' => 'real-time',
            'digest_day' => 'monday',
            'digest_time' => '09:00'
        ];

        // In real app: fetch from database and merge with defaults
        // $stored = $this->getStoredPreferences($userId);
        // return array_merge($defaults, $stored);

        return $defaults;
    }

    /**
     * Update user preferences
     *
     * @param int $userId
     * @param array $data
     * @return bool
     */
    public function updatePreferences($userId = null, $data = [])
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        // Validate dashboard layout
        if (!empty($data['dashboard_layout'])) {
            if (!in_array($data['dashboard_layout'], array_keys(CORE_DASHBOARD_LAYOUTS))) {
                throw new \Exception('Invalid dashboard layout');
            }
        }

        // Validate items per page
        if (!empty($data['items_per_page'])) {
            if (!in_array($data['items_per_page'], CORE_ITEMS_PER_PAGE_OPTIONS)) {
                throw new \Exception('Invalid items per page option');
            }
        }

        // Validate profile visibility
        if (!empty($data['profile_visibility'])) {
            if (!in_array($data['profile_visibility'], array_keys(CORE_PROFILE_VISIBILITY_OPTIONS))) {
                throw new \Exception('Invalid profile visibility option');
            }
        }

        // Validate notification frequency
        if (!empty($data['notification_frequency'])) {
            if (!in_array($data['notification_frequency'], array_keys(CORE_NOTIFICATION_FREQUENCIES))) {
                throw new \Exception('Invalid notification frequency');
            }
        }

        // Handle day of week
        if (!empty($data['digest_day'])) {
            $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            if (!in_array($data['digest_day'], $days)) {
                throw new \Exception('Invalid digest day');
            }
        }

        // Handle time format (HH:MM)
        if (!empty($data['digest_time'])) {
            if (!preg_match('/^\d{2}:\d{2}$/', $data['digest_time'])) {
                throw new \Exception('Invalid time format. Use HH:MM');
            }
        }

        // Sanitize all boolean values
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (in_array($key, [
                'email_daily_digest', 'email_alerts', 'email_updates',
                'in_app_notifications', 'push_notifications',
                'show_online_status', 'allow_direct_messages',
                'show_last_activity', 'show_tooltips',
                'show_animations', 'compact_mode'
            ])) {
                $sanitized[$key] = (bool)$value;
            } else {
                $sanitized[$key] = $value;
            }
        }

        // In real app: update database
        // UPDATE user_preferences SET ... WHERE user_id = ?

        // Log the change
        $this->logPreferenceChange($userId, 'preferences_updated', $sanitized);

        return true;
    }

    /**
     * Set specific notification preferences
     *
     * @param int $userId
     * @param string $notificationType (email|sms|push|in_app)
     * @param bool $enabled
     * @return bool
     */
    public function setNotificationPreference($userId = null, $notificationType, $enabled = true)
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        $validTypes = ['email', 'sms', 'push', 'in_app'];
        if (!in_array($notificationType, $validTypes)) {
            throw new \Exception('Invalid notification type');
        }

        // Map to database column
        $column = $notificationType . '_notifications';

        // In real app: update database
        // UPDATE user_preferences SET {$column} = ? WHERE user_id = ?

        $this->logPreferenceChange($userId, 'notification_preference_changed', [
            'type' => $notificationType,
            'enabled' => $enabled
        ]);

        return true;
    }

    /**
     * Set notification frequency
     *
     * @param int $userId
     * @param string $frequency (real-time|hourly|daily|weekly)
     * @return bool
     */
    public function setNotificationFrequency($userId = null, $frequency)
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        if (!in_array($frequency, array_keys(CORE_NOTIFICATION_FREQUENCIES))) {
            throw new \Exception('Invalid frequency option');
        }

        // In real app: update database
        // UPDATE user_preferences SET notification_frequency = ? WHERE user_id = ?

        $this->logPreferenceChange($userId, 'notification_frequency_changed', [
            'frequency' => $frequency
        ]);

        return true;
    }

    /**
     * Set dashboard layout
     *
     * @param int $userId
     * @param string $layout
     * @return bool
     */
    public function setDashboardLayout($userId = null, $layout)
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        if (!in_array($layout, array_keys(CORE_DASHBOARD_LAYOUTS))) {
            throw new \Exception('Invalid dashboard layout');
        }

        // In real app: update database
        // UPDATE user_preferences SET dashboard_layout = ? WHERE user_id = ?

        $this->logPreferenceChange($userId, 'dashboard_layout_changed', [
            'layout' => $layout
        ]);

        return true;
    }

    /**
     * Set profile visibility
     *
     * @param int $userId
     * @param string $visibility (private|team|network|public)
     * @return bool
     */
    public function setProfileVisibility($userId = null, $visibility)
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        if (!in_array($visibility, array_keys(CORE_PROFILE_VISIBILITY_OPTIONS))) {
            throw new \Exception('Invalid visibility option');
        }

        // In real app: update database
        // UPDATE user_preferences SET profile_visibility = ? WHERE user_id = ?

        $this->logPreferenceChange($userId, 'profile_visibility_changed', [
            'visibility' => $visibility
        ]);

        return true;
    }

    /**
     * Get dashboard layout options
     *
     * @return array
     */
    public function getDashboardLayouts()
    {
        return CORE_DASHBOARD_LAYOUTS;
    }

    /**
     * Get notification frequencies
     *
     * @return array
     */
    public function getNotificationFrequencies()
    {
        return CORE_NOTIFICATION_FREQUENCIES;
    }

    /**
     * Get profile visibility options
     *
     * @return array
     */
    public function getProfileVisibilityOptions()
    {
        return CORE_PROFILE_VISIBILITY_OPTIONS;
    }

    /**
     * Get stored preferences from database (placeholder)
     *
     * @param int $userId
     * @return array
     */
    protected function getStoredPreferences($userId)
    {
        // In real app: fetch from database
        // SELECT * FROM user_preferences WHERE user_id = ?

        return [];
    }

    /**
     * Log preference change to activity log
     *
     * @param int $userId
     * @param string $action
     * @param array $details
     * @return bool
     */
    protected function logPreferenceChange($userId, $action, $details = [])
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
     * Validate preferences data
     *
     * @param array $data
     * @return array
     */
    public function validatePreferencesData($data = [])
    {
        $errors = [];

        if (!empty($data['dashboard_layout']) &&
            !in_array($data['dashboard_layout'], array_keys(CORE_DASHBOARD_LAYOUTS))) {
            $errors['dashboard_layout'] = 'Invalid dashboard layout';
        }

        if (!empty($data['items_per_page']) &&
            !in_array($data['items_per_page'], CORE_ITEMS_PER_PAGE_OPTIONS)) {
            $errors['items_per_page'] = 'Invalid items per page option';
        }

        if (!empty($data['notification_frequency']) &&
            !in_array($data['notification_frequency'], array_keys(CORE_NOTIFICATION_FREQUENCIES))) {
            $errors['notification_frequency'] = 'Invalid notification frequency';
        }

        if (!empty($data['profile_visibility']) &&
            !in_array($data['profile_visibility'], array_keys(CORE_PROFILE_VISIBILITY_OPTIONS))) {
            $errors['profile_visibility'] = 'Invalid profile visibility option';
        }

        if (!empty($data['digest_time']) &&
            !preg_match('/^\d{2}:\d{2}$/', $data['digest_time'])) {
            $errors['digest_time'] = 'Invalid time format. Use HH:MM';
        }

        return $errors;
    }

    /**
     * Set friend request settings (Chat Feature)
     *
     * @param int $userId
     * @param string $policy (manual|auto_accept|contacts_only)
     * @param bool $notifications
     * @return bool
     */
    public function setFriendRequestSettings($userId = null, $policy = 'manual', $notifications = true)
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        if (!in_array($policy, ['manual', 'auto_accept', 'contacts_only'])) {
            throw new \Exception('Invalid friend request policy');
        }

        // In real app: update database
        // UPDATE user_preferences SET friend_request_policy = ?, friend_request_notifications = ? WHERE user_id = ?

        $this->logPreferenceChange($userId, 'friend_request_settings_updated', [
            'policy' => $policy,
            'notifications' => $notifications
        ]);

        return true;
    }

    /**
     * Set group invitation settings (Chat Feature)
     *
     * @param int $userId
     * @param string $policy (notify|auto_join|skip_notify)
     * @param bool $notifications
     * @return bool
     */
    public function setGroupInvitationSettings($userId = null, $policy = 'notify', $notifications = true)
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        if (!in_array($policy, ['notify', 'auto_join', 'skip_notify'])) {
            throw new \Exception('Invalid group invitation policy');
        }

        // In real app: update database
        // UPDATE user_preferences SET group_invitation_policy = ?, group_invitation_notifications = ? WHERE user_id = ?

        $this->logPreferenceChange($userId, 'group_invitation_settings_updated', [
            'policy' => $policy,
            'notifications' => $notifications
        ]);

        return true;
    }

    /**
     * Set direct message privacy (Chat Feature)
     *
     * @param int $userId
     * @param string $privacyLevel (everyone|contacts|approved|blocked)
     * @param bool $requireApproval
     * @return bool
     */
    public function setDirectMessagePrivacy($userId = null, $privacyLevel = 'everyone', $requireApproval = false)
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        if (!in_array($privacyLevel, CORE_DM_PRIVACY_LEVELS)) {
            throw new \Exception('Invalid DM privacy level');
        }

        // In real app: update database
        // UPDATE user_preferences SET dm_privacy_level = ?, dm_require_approval = ? WHERE user_id = ?

        $this->logPreferenceChange($userId, 'dm_privacy_updated', [
            'privacy_level' => $privacyLevel,
            'require_approval' => $requireApproval
        ]);

        return true;
    }

    /**
     * Get direct message privacy settings
     *
     * @param int $userId
     * @return array
     */
    public function getDirectMessagePrivacy($userId = null)
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        // In real app: fetch from database
        return [
            'privacy_level' => 'everyone',
            'require_approval' => false
        ];
    }

    /**
     * Set spam filtering level (Chat Feature)
     *
     * @param int $userId
     * @param string $level (permissive|moderate|strict)
     * @return bool
     */
    public function setSpamFilteringLevel($userId = null, $level = 'moderate')
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        if (!in_array($level, CORE_SPAM_FILTER_LEVELS)) {
            throw new \Exception('Invalid spam filter level');
        }

        // In real app: update database
        // UPDATE user_preferences SET spam_filter_level = ? WHERE user_id = ?

        $this->logPreferenceChange($userId, 'spam_filter_updated', ['level' => $level]);

        return true;
    }

    /**
     * Set message search privacy (Chat Feature)
     *
     * @param int $userId
     * @param bool $searchable
     * @return bool
     */
    public function setMessageSearchPrivacy($userId = null, $searchable = true)
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        // In real app: update database
        // UPDATE user_preferences SET message_search_indexing = ? WHERE user_id = ?

        $this->logPreferenceChange($userId, 'message_search_privacy_updated', ['searchable' => $searchable]);

        return true;
    }

    /**
     * Set profile search visibility (Chat Feature)
     *
     * @param int $userId
     * @param string $visibility (public|contacts|hidden)
     * @return bool
     */
    public function setProfileSearchVisibility($userId = null, $visibility = 'public')
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        if (!in_array($visibility, ['public', 'contacts', 'hidden'])) {
            throw new \Exception('Invalid profile search visibility option');
        }

        // In real app: update database
        // UPDATE user_preferences SET profile_searchable = ? WHERE user_id = ?

        $this->logPreferenceChange($userId, 'profile_search_visibility_updated', ['visibility' => $visibility]);

        return true;
    }

    /**
     * Set status update frequency (Chat Feature)
     *
     * @param int $userId
     * @param string $frequency (always|delayed_5|delayed_hour|hidden)
     * @return bool
     */
    public function setStatusUpdateFrequency($userId = null, $frequency = 'always')
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        if (!in_array($frequency, CORE_STATUS_UPDATE_FREQUENCIES)) {
            throw new \Exception('Invalid status update frequency');
        }

        // In real app: update database
        // UPDATE user_preferences SET status_update_frequency = ? WHERE user_id = ?

        $this->logPreferenceChange($userId, 'status_update_frequency_updated', ['frequency' => $frequency]);

        return true;
    }

    /**
     * Set message archive policy (Chat Feature)
     *
     * @param int $userId
     * @param bool $autoArchive
     * @param int $days (inactive days before archiving)
     * @return bool
     */
    public function setMessageArchivePolicy($userId = null, $autoArchive = false, $days = 30)
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        if ($days < 1) {
            throw new \Exception('Archive days must be at least 1');
        }

        // In real app: update database
        // UPDATE user_preferences SET auto_archive_conversations = ?, archive_days = ? WHERE user_id = ?

        $this->logPreferenceChange($userId, 'archive_policy_updated', [
            'auto_archive' => $autoArchive,
            'days' => $days
        ]);

        return true;
    }

    /**
     * Set contact export privacy (Chat Feature)
     *
     * @param int $userId
     * @param bool $allowed
     * @return bool
     */
    public function setContactExportPrivacy($userId = null, $allowed = false)
    {
        $userId = $userId ?? $this->userId;

        if (!$userId) {
            throw new \Exception('User ID is required');
        }

        // In real app: update database
        // UPDATE user_preferences SET contact_export_allowed = ? WHERE user_id = ?

        $this->logPreferenceChange($userId, 'contact_export_privacy_updated', ['allowed' => $allowed]);

        return true;
    }

    /**
     * Validate chat preferences
     *
     * @param array $data
     * @return array
     */
    public function validateChatPreferences($data = [])
    {
        $errors = [];

        if (!empty($data['dm_privacy_level']) && !in_array($data['dm_privacy_level'], CORE_DM_PRIVACY_LEVELS)) {
            $errors['dm_privacy_level'] = 'Invalid DM privacy level';
        }

        if (!empty($data['spam_filter_level']) && !in_array($data['spam_filter_level'], CORE_SPAM_FILTER_LEVELS)) {
            $errors['spam_filter_level'] = 'Invalid spam filter level';
        }

        if (!empty($data['status_update_frequency']) && !in_array($data['status_update_frequency'], CORE_STATUS_UPDATE_FREQUENCIES)) {
            $errors['status_update_frequency'] = 'Invalid status update frequency';
        }

        return $errors;
    }
}
?>
