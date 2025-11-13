<?php
/**
 * CORE Module Configuration
 *
 * Central configuration for user profile, settings, and preferences system
 */

// Module Information
define('CORE_MODULE_NAME', 'CORE');
define('CORE_MODULE_VERSION', '1.0.0');
define('CORE_MODULE_PATH', __DIR__);

// Database Tables
define('CORE_TABLE_PROFILES', 'user_profiles');
define('CORE_TABLE_SETTINGS', 'user_settings');
define('CORE_TABLE_PREFERENCES', 'user_preferences');
define('CORE_TABLE_ACTIVITY_LOGS', 'user_activity_logs');

// Cache Configuration
define('CORE_CACHE_PROFILE_TTL', 3600);        // 1 hour
define('CORE_CACHE_SETTINGS_TTL', 86400);      // 24 hours
define('CORE_CACHE_PREFERENCES_TTL', 86400);   // 24 hours

// Security Settings
define('CORE_MIN_PASSWORD_LENGTH', 8);
define('CORE_PASSWORD_REQUIRE_UPPERCASE', true);
define('CORE_PASSWORD_REQUIRE_NUMBERS', true);
define('CORE_PASSWORD_REQUIRE_SPECIAL', true);
define('CORE_PASSWORD_HISTORY_COUNT', 5);
define('CORE_MAX_FAILED_LOGINS', 5);
define('CORE_LOCKOUT_DURATION', 900);  // 15 minutes

// Two-Factor Authentication
define('CORE_2FA_ENABLED', true);
define('CORE_2FA_METHODS', ['totp', 'sms', 'email']);
define('CORE_2FA_BACKUP_CODES', 10);

// Session Configuration
define('CORE_SESSION_TIMEOUT', 3600);          // 1 hour
define('CORE_MAX_CONCURRENT_SESSIONS', 5);
define('CORE_SESSION_SECURE_COOKIE', true);
define('CORE_SESSION_HTTPONLY_COOKIE', true);

// Theme Options
define('CORE_THEMES', [
    'silver'  => 'Silver Metallic',
    'dark'    => 'Dark Mode',
    'light'   => 'Light Mode',
    'blue'    => 'Blue',
    'green'   => 'Green'
]);

// Default Theme
define('CORE_DEFAULT_THEME', 'silver');

// Language Options
define('CORE_LANGUAGES', [
    'en' => 'English',
    'es' => 'Español',
    'fr' => 'Français',
    'de' => 'Deutsch',
    'it' => 'Italiano'
]);

// Default Language
define('CORE_DEFAULT_LANGUAGE', 'en');

// Timezone Options
define('CORE_DEFAULT_TIMEZONE', 'UTC');
define('CORE_TIMEZONE_OPTIONS', [
    'UTC' => 'UTC',
    'America/New_York' => 'Eastern Time',
    'America/Chicago' => 'Central Time',
    'America/Denver' => 'Mountain Time',
    'America/Los_Angeles' => 'Pacific Time',
    'Europe/London' => 'GMT',
    'Europe/Paris' => 'CET',
    'Australia/Sydney' => 'AEST',
    'Asia/Tokyo' => 'JST',
    'Asia/Shanghai' => 'CST'
]);

// Profile Settings
define('CORE_PROFILE_AVATAR_MAX_SIZE', 5242880);  // 5MB
define('CORE_PROFILE_AVATAR_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('CORE_PROFILE_AVATAR_PATH', '/uploads/avatars/');
define('CORE_MAX_BIO_LENGTH', 500);  // 500 characters
define('CORE_MAX_AVATAR_SIZE', 5);  // 5MB

// Notification Settings
define('CORE_NOTIFICATION_TYPES', [
    'email' => 'Email',
    'sms' => 'SMS',
    'push' => 'Push Notification',
    'in_app' => 'In-App'
]);

define('CORE_NOTIFICATION_FREQUENCIES', [
    'realtime' => 'Real-time',
    'daily' => 'Daily Digest',
    'weekly' => 'Weekly Summary',
    'never' => 'Never'
]);

// Activity Log Configuration
define('CORE_LOG_PROFILE_CHANGES', true);
define('CORE_LOG_SETTINGS_CHANGES', true);
define('CORE_LOG_LOGIN_ATTEMPTS', true);
define('CORE_LOG_PASSWORD_CHANGES', true);
define('CORE_LOG_2FA_CHANGES', true);
define('CORE_LOG_RETENTION_DAYS', 90);

// Dashboard Layout Options
define('CORE_DASHBOARD_LAYOUTS', [
    '2-column' => '2 Column',
    '3-column' => '3 Column (Default)',
    '4-column' => '4 Column',
    'list' => 'List View'
]);

// Default Dashboard Layout
define('CORE_DEFAULT_DASHBOARD_LAYOUT', '3-column');

// Items Per Page Options
define('CORE_ITEMS_PER_PAGE_OPTIONS', [10, 25, 50, 100]);
define('CORE_DEFAULT_ITEMS_PER_PAGE', 25);

// Privacy Settings
define('CORE_PROFILE_VISIBILITY_OPTIONS', [
    'private' => 'Private (Only Me)',
    'team' => 'Team (My Store)',
    'network' => 'Network (All Stores)',
    'public' => 'Public'
]);

define('CORE_DEFAULT_PROFILE_VISIBILITY', 'team');

// API Configuration
define('CORE_API_RATE_LIMIT', 100);          // requests
define('CORE_API_RATE_WINDOW', 3600);        // per second
define('CORE_API_VERSION', 'v1');

// Audit Trail Configuration
define('CORE_AUDIT_TRAIL_ENABLED', true);
define('CORE_AUDIT_TRAIL_RETENTION_DAYS', 180);

// Email Verification
define('CORE_EMAIL_VERIFICATION_REQUIRED', true);
define('CORE_EMAIL_VERIFICATION_EXPIRY', 86400);  // 24 hours

// CHAT APP FEATURES - Availability & Presence
define('CORE_AVAILABILITY_STATUSES', ['online', 'away', 'offline', 'do_not_disturb']);
define('CORE_DEFAULT_AVAILABILITY_STATUS', 'online');
define('CORE_STATUS_MESSAGE_MAX_LENGTH', 100);

// CHAT APP FEATURES - Direct Message Privacy
define('CORE_DM_PRIVACY_LEVELS', ['everyone', 'contacts', 'approved', 'blocked']);
define('CORE_DEFAULT_DM_PRIVACY', 'everyone');

// CHAT APP FEATURES - Spam Filtering
define('CORE_SPAM_FILTER_LEVELS', ['permissive', 'moderate', 'strict']);
define('CORE_DEFAULT_SPAM_FILTER', 'moderate');

// CHAT APP FEATURES - Status Update Frequency
define('CORE_STATUS_UPDATE_FREQUENCIES', ['always', 'delayed_5', 'delayed_hour', 'hidden']);
define('CORE_DEFAULT_STATUS_FREQUENCY', 'always');

// CHAT APP FEATURES - Device Trust
define('CORE_DEVICE_TRUST_LEVELS', ['auto_trust', 'manual', 'strict']);
define('CORE_DEFAULT_DEVICE_TRUST', 'manual');
define('CORE_DEVICE_TRUST_AUTO_EXPIRE_DAYS', 30);

// CHAT APP FEATURES - Message Delivery Options
define('CORE_MESSAGE_DELIVERY_OPTIONS', ['show', 'hide_delivery', 'hide_read']);
define('CORE_DEFAULT_DELIVERY_VISIBILITY', 'show');

// CHAT APP FEATURES - Read Receipt Options
define('CORE_READ_RECEIPT_OPTIONS', ['send_and_receive', 'receive_only', 'hide_all']);
define('CORE_DEFAULT_READ_RECEIPTS', 'send_and_receive');

// CHAT APP FEATURES - Report & Block Reasons
define('CORE_REPORT_REASONS', ['spam', 'harassment', 'inappropriate', 'impersonation', 'other']);
define('CORE_BLOCK_REASONS', ['spam', 'harassment', 'abuse', 'privacy', 'other']);

// CHAT APP FEATURES - Limits & Restrictions
define('CORE_MAX_BLOCKED_USERS', 1000);
define('CORE_MAX_REPORTED_USERS', 500);
define('CORE_DM_HISTORY_RETENTION_DAYS', 365);  // 1 year
define('CORE_MESSAGE_SEARCH_AVAILABLE', true);
define('CORE_MESSAGE_ARCHIVE_AUTO_DAYS', 30);

// CHAT APP FEATURES - Call & Video Settings
define('CORE_CALL_OPTIONS', ['enabled', 'contacts', 'disabled']);
define('CORE_VIDEO_OPTIONS', ['enabled', 'contacts', 'disabled']);
define('CORE_SCREEN_SHARE_OPTIONS', ['enabled', 'contacts', 'disabled']);
define('CORE_DEFAULT_CALL_OPTION', 'enabled');
define('CORE_DEFAULT_VIDEO_OPTION', 'enabled');
define('CORE_DEFAULT_SCREEN_SHARE', 'enabled');

// CHAT APP FEATURES - Do Not Disturb
define('CORE_DND_ENABLED', true);
define('CORE_DND_DEFAULT_START', '22:00');
define('CORE_DND_DEFAULT_END', '08:00');
define('CORE_DND_ALLOW_FAVORITES_EXCEPTIONS', true);

// CHAT APP FEATURES - Notification Sounds
define('CORE_NOTIFICATION_SOUNDS', ['none', 'default', 'bell', 'chime', 'pop', 'ring', 'digital']);
define('CORE_DEFAULT_MESSAGE_SOUND', 'default');
define('CORE_DEFAULT_CALL_SOUND', 'default');
define('CORE_VIBRATION_ENABLED_DEFAULT', true);

// CHAT APP FEATURES - Friend & Group Settings
define('CORE_FRIEND_REQUEST_POLICIES', ['manual', 'auto_accept', 'contacts_only']);
define('CORE_GROUP_INVITATION_POLICIES', ['notify', 'auto_join', 'skip_notify']);
define('CORE_DEFAULT_FRIEND_POLICY', 'manual');
define('CORE_DEFAULT_GROUP_POLICY', 'notify');

// CHAT APP FEATURES - Profile Visibility Options (Chat)
define('CORE_CHAT_PROFILE_VISIBILITY_OPTIONS', [
    'public' => 'Public - Everyone',
    'contacts' => 'Contacts Only',
    'private' => 'Private - Only Me'
]);
define('CORE_LAST_SEEN_PRIVACY_OPTIONS', [
    'everyone' => 'Everyone',
    'contacts' => 'Contacts Only',
    'private' => 'Hidden'
]);
define('CORE_AVATAR_VISIBILITY_OPTIONS', [
    'public' => 'Public',
    'contacts' => 'Contacts Only',
    'private' => 'Private'
]);
define('CORE_BIO_VISIBILITY_OPTIONS', [
    'public' => 'Public',
    'contacts' => 'Contacts Only',
    'private' => 'Private'
]);

// CHAT APP FEATURES - Cache Configuration (Chat Specific)
define('CORE_CACHE_AVAILABILITY_TTL', 300);        // 5 minutes
define('CORE_CACHE_BLOCKED_USERS_TTL', 3600);      // 1 hour
define('CORE_CACHE_CHAT_SETTINGS_TTL', 1800);      // 30 minutes
define('CORE_CACHE_DM_PRIVACY_TTL', 3600);         // 1 hour

// Profile Settings
define('CORE_PROFILE_AVATAR_MAX_SIZE', 5242880);  // 5MB
define('CORE_MAX_BIO_LENGTH', 500);  // 500 characters
define('CORE_MAX_AVATAR_SIZE', 5);  // 5MB
define('CORE_PROFILE_AVATAR_EXTENSIONS', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('CORE_PROFILE_AVATAR_PATH', '/uploads/avatars/');

// Return array of config
return [
    'module_name' => CORE_MODULE_NAME,
    'version' => CORE_MODULE_VERSION,
    'path' => CORE_MODULE_PATH,
    'tables' => [
        'profiles' => CORE_TABLE_PROFILES,
        'settings' => CORE_TABLE_SETTINGS,
        'preferences' => CORE_TABLE_PREFERENCES,
        'activity_logs' => CORE_TABLE_ACTIVITY_LOGS
    ],
    'security' => [
        'min_password_length' => CORE_MIN_PASSWORD_LENGTH,
        'password_require_uppercase' => CORE_PASSWORD_REQUIRE_UPPERCASE,
        'password_require_numbers' => CORE_PASSWORD_REQUIRE_NUMBERS,
        'password_require_special' => CORE_PASSWORD_REQUIRE_SPECIAL,
        '2fa_enabled' => CORE_2FA_ENABLED
    ],
    'cache' => [
        'profile_ttl' => CORE_CACHE_PROFILE_TTL,
        'settings_ttl' => CORE_CACHE_SETTINGS_TTL,
        'preferences_ttl' => CORE_CACHE_PREFERENCES_TTL
    ],
    'themes' => CORE_THEMES,
    'languages' => CORE_LANGUAGES,
    'timezones' => CORE_TIMEZONE_OPTIONS
];
