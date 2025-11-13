<?php
/**
 * User Settings Page
 * Manage user settings, preferences, security, and notifications
 */

require_once __DIR__ . '/../../base/templates/vape-ultra/config.php';
require_once __DIR__ . '/../../base/templates/vape-ultra/layouts/main.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../controllers/SettingsController.php';
require_once __DIR__ . '/../controllers/PreferencesController.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

// Get active tab
$tab = $_GET['tab'] ?? 'general';

// Initialize controllers
$settingsController = new SettingsController();
$preferencesController = new PreferencesController();

// Get current settings
$settings = $settingsController->getSettings($_SESSION['user_id']);
$preferences = $preferencesController->getPreferences($_SESSION['user_id']);
$user = $_SESSION['user'] ?? [];

// Handle form submissions
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;

    try {
        if ($action === 'update_general') {
            $data = [
                'theme' => htmlspecialchars($_POST['theme'] ?? ''),
                'language' => htmlspecialchars($_POST['language'] ?? ''),
                'timezone' => htmlspecialchars($_POST['timezone'] ?? '')
            ];
            if ($settingsController->updateSettings($_SESSION['user_id'], $data)) {
                $message = 'General settings updated successfully!';
                $settings = $settingsController->getSettings($_SESSION['user_id']);
            }
        }

        elseif ($action === 'update_notifications') {
            $data = [
                'email_daily_digest' => isset($_POST['email_daily_digest']),
                'email_alerts' => isset($_POST['email_alerts']),
                'email_updates' => isset($_POST['email_updates']),
                'in_app_notifications' => isset($_POST['in_app_notifications']),
                'push_notifications' => isset($_POST['push_notifications'])
            ];
            if ($preferencesController->updatePreferences($_SESSION['user_id'], $data)) {
                $message = 'Notification settings updated!';
                $preferences = $preferencesController->getPreferences($_SESSION['user_id']);
            }
        }

        elseif ($action === 'change_password') {
            $current_password = $_POST['current_password'] ?? '';
            $new_password = $_POST['new_password'] ?? '';
            $confirm_password = $_POST['confirm_password'] ?? '';

            if (empty($current_password) || empty($new_password) || empty($confirm_password)) {
                throw new Exception('All password fields are required.');
            }

            if ($new_password !== $confirm_password) {
                throw new Exception('New passwords do not match.');
            }

            if (strlen($new_password) < CORE_MIN_PASSWORD_LENGTH) {
                throw new Exception('Password must be at least ' . CORE_MIN_PASSWORD_LENGTH . ' characters.');
            }

            if ($settingsController->changePassword($_SESSION['user_id'], $current_password, $new_password)) {
                $message = 'Password changed successfully!';
            } else {
                throw new Exception('Current password is incorrect.');
            }
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Page configuration
$page = [
    'title' => 'Settings',
    'breadcrumbs' => [
        'Home' => '/admin/',
        'Settings' => null
    ],
    'icon' => 'fas fa-cog'
];

// Build content
$content = <<<HTML
<div class="settings-container">
    <div class="row">
        <!-- Left Sidebar - Navigation -->
        <div class="col-md-3">
            <div class="list-group sticky-top">
                <a href="?tab=general" class="list-group-item list-group-item-action {($tab === 'general' ? 'active' : '')}">
                    <i class="fas fa-sliders-h"></i> General
                </a>
                <a href="?tab=preferences" class="list-group-item list-group-item-action {($tab === 'preferences' ? 'active' : '')}">
                    <i class="fas fa-star"></i> Preferences
                </a>
                <a href="?tab=chat" class="list-group-item list-group-item-action {($tab === 'chat' ? 'active' : '')}">
                    <i class="fas fa-comments"></i> Chat Settings <span class="badge bg-info">NEW</span>
                </a>
                <a href="?tab=notifications" class="list-group-item list-group-item-action {($tab === 'notifications' ? 'active' : '')}">
                    <i class="fas fa-bell"></i> Notifications
                </a>
                <a href="?tab=security" class="list-group-item list-group-item-action {($tab === 'security' ? 'active' : '')}">
                    <i class="fas fa-shield-alt"></i> Security
                </a>
                <a href="?tab=privacy" class="list-group-item list-group-item-action {($tab === 'privacy' ? 'active' : '')}">
                    <i class="fas fa-lock"></i> Privacy
                </a>
                <a href="?tab=activity" class="list-group-item list-group-item-action {($tab === 'activity' ? 'active' : '')}">
                    <i class="fas fa-history"></i> Activity Log
                </a>
            </div>
        </div>

        <!-- Right Content -->
        <div class="col-md-9">
            HTML;

// Messages
if ($message) {
    $content .= <<<HTML
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-check-circle"></i> {$message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            HTML;
}

if ($error) {
    $content .= <<<HTML
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-exclamation-circle"></i> {$error}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            HTML;
}

// GENERAL TAB
if ($tab === 'general') {
    $content .= <<<HTML
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-sliders-h"></i> General Settings</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_general">

                        <div class="mb-4">
                            <label for="theme" class="form-label">Theme</label>
                            <select class="form-select" id="theme" name="theme" required>
                                <option value="">Select Theme...</option>
                                HTML;

    foreach (CORE_THEMES as $key => $name) {
        $selected = $settings['theme'] === $key ? 'selected' : '';
        $content .= "<option value=\"$key\" $selected>$name</option>";
    }

    $content .= <<<HTML
                            </select>
                            <small class="form-text text-muted">Choose your preferred interface theme</small>
                        </div>

                        <div class="mb-4">
                            <label for="language" class="form-label">Language</label>
                            <select class="form-select" id="language" name="language" required>
                                <option value="">Select Language...</option>
                                HTML;

    foreach (CORE_LANGUAGES as $key => $name) {
        $selected = $settings['language'] === $key ? 'selected' : '';
        $content .= "<option value=\"$key\" $selected>$name</option>";
    }

    $content .= <<<HTML
                            </select>
                            <small class="form-text text-muted">Select your preferred language</small>
                        </div>

                        <div class="mb-4">
                            <label for="timezone" class="form-label">Timezone</label>
                            <select class="form-select" id="timezone" name="timezone" required>
                                <option value="">Select Timezone...</option>
                                HTML;

    foreach (CORE_TIMEZONE_OPTIONS as $key => $name) {
        $selected = $settings['timezone'] === $key ? 'selected' : '';
        $content .= "<option value=\"$key\" $selected>$name</option>";
    }

    $content .= <<<HTML
                            </select>
                            <small class="form-text text-muted">Used for displaying dates and times</small>
                        </div>

                        <hr class="my-4">

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            HTML;
}

// PREFERENCES TAB
elseif ($tab === 'preferences') {
    $content .= <<<HTML
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-star"></i> User Preferences</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_preferences">

                        <h6 class="mb-3">Dashboard</h6>
                        <div class="mb-4">
                            <label for="layout" class="form-label">Dashboard Layout</label>
                            <select class="form-select" id="layout" name="dashboard_layout">
                                HTML;

    foreach (CORE_DASHBOARD_LAYOUTS as $key => $name) {
        $selected = $preferences['dashboard_layout'] === $key ? 'selected' : '';
        $content .= "<option value=\"$key\" $selected>$name</option>";
    }

    $content .= <<<HTML
                            </select>
                        </div>

                        <div class="mb-4">
                            <label for="itemsPerPage" class="form-label">Items Per Page</label>
                            <select class="form-select" id="itemsPerPage" name="items_per_page">
                                HTML;

    foreach (CORE_ITEMS_PER_PAGE_OPTIONS as $option) {
        $selected = $preferences['items_per_page'] === $option ? 'selected' : '';
        $content .= "<option value=\"$option\" $selected>$option items</option>";
    }

    $content .= <<<HTML
                            </select>
                        </div>

                        <hr class="my-4">
                        <h6 class="mb-3">Privacy</h6>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="onlineStatus" name="show_online_status"
                                   {($preferences['show_online_status'] ? 'checked' : '')}>
                            <label class="form-check-label" for="onlineStatus">
                                Show my online status
                            </label>
                        </div>

                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" id="directMessages" name="allow_direct_messages"
                                   {($preferences['allow_direct_messages'] ? 'checked' : '')}>
                            <label class="form-check-label" for="directMessages">
                                Allow direct messages from team members
                            </label>
                        </div>

                        <hr class="my-4">

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            HTML;
}

// CHAT SETTINGS TAB (NEW)
elseif ($tab === 'chat') {
    $content .= <<<HTML
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-comments"></i> Message Delivery & Reading</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_chat_settings">

                        <h6 class="mb-3">Message Status Indicators</h6>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="deliveryIndicators" name="delivery_indicators"
                                   {(isset($preferences['delivery_indicators']) && $preferences['delivery_indicators'] ? 'checked' : '')}>
                            <label class="form-check-label" for="deliveryIndicators">
                                <strong>Show Message Delivery Status</strong>
                                <br><small class="text-muted">Others can see sent/delivered status for your messages</small>
                            </label>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="readReceipts" name="read_receipts"
                                   {(isset($preferences['read_receipts']) && $preferences['read_receipts'] ? 'checked' : '')}>
                            <label class="form-check-label" for="readReceipts">
                                <strong>Send Read Receipts</strong>
                                <br><small class="text-muted">Others can see when you've read their messages</small>
                            </label>
                        </div>

                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" id="typingIndicators" name="typing_indicators"
                                   {(isset($preferences['typing_indicators']) && $preferences['typing_indicators'] ? 'checked' : '')}>
                            <label class="form-check-label" for="typingIndicators">
                                <strong>Show Typing Indicators</strong>
                                <br><small class="text-muted">Others see when you're composing a message</small>
                            </label>
                        </div>

                        <hr class="my-4">
                        <h6 class="mb-3">Online Status Visibility</h6>

                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" id="onlineVisibility" name="online_visibility"
                                   {(isset($preferences['online_visibility']) && $preferences['online_visibility'] ? 'checked' : '')}>
                            <label class="form-check-label" for="onlineVisibility">
                                <strong>Show When I'm Online</strong>
                                <br><small class="text-muted">Others can see your online/offline status in real-time</small>
                            </label>
                        </div>

                        <hr class="my-4">

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Message Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-phone-alt"></i> Call & Video Settings</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_call_settings">

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="audioPermission" class="form-label">Audio Calls</label>
                                <select class="form-select" id="audioPermission" name="audio_calls">
                                    <option value="enabled" {(($preferences['audio_calls'] ?? 'enabled') === 'enabled' ? 'selected' : '')}>
                                        <i class="fas fa-microphone"></i> Enabled
                                    </option>
                                    <option value="contacts" {(($preferences['audio_calls'] ?? '') === 'contacts' ? 'selected' : '')}>
                                        <i class="fas fa-user-friends"></i> Contacts Only
                                    </option>
                                    <option value="disabled" {(($preferences['audio_calls'] ?? '') === 'disabled' ? 'selected' : '')}>
                                        <i class="fas fa-ban"></i> Disabled
                                    </option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="videoPermission" class="form-label">Video Calls</label>
                                <select class="form-select" id="videoPermission" name="video_calls">
                                    <option value="enabled" {(($preferences['video_calls'] ?? 'enabled') === 'enabled' ? 'selected' : '')}>
                                        <i class="fas fa-video"></i> Enabled
                                    </option>
                                    <option value="contacts" {(($preferences['video_calls'] ?? '') === 'contacts' ? 'selected' : '')}>
                                        <i class="fas fa-user-friends"></i> Contacts Only
                                    </option>
                                    <option value="disabled" {(($preferences['video_calls'] ?? '') === 'disabled' ? 'selected' : '')}>
                                        <i class="fas fa-ban"></i> Disabled
                                    </option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="screenShare" class="form-label">Screen Sharing</label>
                                <select class="form-select" id="screenShare" name="screen_share">
                                    <option value="enabled" {(($preferences['screen_share'] ?? 'enabled') === 'enabled' ? 'selected' : '')}>
                                        <i class="fas fa-desktop"></i> Enabled
                                    </option>
                                    <option value="contacts" {(($preferences['screen_share'] ?? '') === 'contacts' ? 'selected' : '')}>
                                        <i class="fas fa-user-friends"></i> Contacts Only
                                    </option>
                                    <option value="disabled" {(($preferences['screen_share'] ?? '') === 'disabled' ? 'selected' : '')}>
                                        <i class="fas fa-ban"></i> Disabled
                                    </option>
                                </select>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Call Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-volume-mute"></i> Do Not Disturb (DND)</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_dnd_settings">

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="dndEnabled" name="dnd_enabled"
                                   onchange="toggleDNDSettings()" {(isset($preferences['dnd_enabled']) && $preferences['dnd_enabled'] ? 'checked' : '')}>
                            <label class="form-check-label" for="dndEnabled">
                                <strong>Enable Do Not Disturb</strong>
                                <br><small class="text-muted">Mute notifications during specified hours</small>
                            </label>
                        </div>

                        <div id="dndSettings" style="display: {(isset($preferences['dnd_enabled']) && $preferences['dnd_enabled'] ? 'block' : 'none')}; padding-left: 2rem;">
                            <div class="row g-3 mb-3">
                                <div class="col-md-6">
                                    <label for="dndStart" class="form-label">Start Time</label>
                                    <input type="time" class="form-control" id="dndStart" name="dnd_start"
                                           value="{$preferences['dnd_start'] ?? '22:00'}">
                                </div>
                                <div class="col-md-6">
                                    <label for="dndEnd" class="form-label">End Time</label>
                                    <input type="time" class="form-control" id="dndEnd" name="dnd_end"
                                           value="{$preferences['dnd_end'] ?? '08:00'}">
                                </div>
                            </div>

                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="dndCallsAllowed" name="dnd_calls_allowed"
                                       {(isset($preferences['dnd_calls_allowed']) && $preferences['dnd_calls_allowed'] ? 'checked' : '')}>
                                <label class="form-check-label" for="dndCallsAllowed">
                                    <strong>Allow calls from favorites</strong>
                                    <br><small class="text-muted">Contacts in your favorites can still reach you</small>
                                </label>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save DND Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-volume-up"></i> Notification Sounds & Vibration</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_notification_sounds">

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="messageSound" class="form-label">Message Sound</label>
                                <select class="form-select" id="messageSound" name="message_sound">
                                    <option value="none">🔇 Silent</option>
                                    <option value="default" selected>📳 Default</option>
                                    <option value="bell">🔔 Bell</option>
                                    <option value="chime">🎵 Chime</option>
                                    <option value="pop">💥 Pop</option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="callSound" class="form-label">Call Sound</label>
                                <select class="form-select" id="callSound" name="call_sound">
                                    <option value="none">🔇 Silent</option>
                                    <option value="default" selected>📳 Default</option>
                                    <option value="ring">📞 Ring</option>
                                    <option value="digital">🎛️ Digital</option>
                                </select>
                            </div>
                        </div>

                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" id="vibration" name="vibration_enabled"
                                   {(isset($preferences['vibration_enabled']) && $preferences['vibration_enabled'] ? 'checked' : '')}>
                            <label class="form-check-label" for="vibration">
                                <strong>Enable Vibration</strong>
                                <br><small class="text-muted">Mobile devices will vibrate on notifications</small>
                            </label>
                        </div>

                        <hr class="my-4">

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Sound Settings
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i>
                <strong>Privacy Note:</strong> These chat settings affect how you communicate with other users. Read receipts and typing indicators help others know when you've seen their messages, but you can disable them for more privacy.
            </div>
            HTML;
}

// NOTIFICATIONS TAB
elseif ($tab === 'notifications') {
    $content .= <<<HTML
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-bell"></i> Notification Settings</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="update_notifications">

                        <h6 class="mb-3">Email Notifications</h6>
                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="dailyDigest" name="email_daily_digest"
                                   {($preferences['email_daily_digest'] ? 'checked' : '')}>
                            <label class="form-check-label" for="dailyDigest">
                                Daily digest email
                                <br><small class="text-muted">Summary of daily activities</small>
                            </label>
                        </div>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="emailAlerts" name="email_alerts"
                                   {($preferences['email_alerts'] ? 'checked' : '')}>
                            <label class="form-check-label" for="emailAlerts">
                                Email alerts
                                <br><small class="text-muted">Immediate notifications for important events</small>
                            </label>
                        </div>

                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" id="emailUpdates" name="email_updates"
                                   {($preferences['email_updates'] ? 'checked' : '')}>
                            <label class="form-check-label" for="emailUpdates">
                                Email updates
                                <br><small class="text-muted">Product updates and announcements</small>
                            </label>
                        </div>

                        <hr class="my-4">
                        <h6 class="mb-3">In-App Notifications</h6>

                        <div class="form-check form-switch mb-3">
                            <input class="form-check-input" type="checkbox" id="inAppNotifications" name="in_app_notifications"
                                   {($preferences['in_app_notifications'] ? 'checked' : '')}>
                            <label class="form-check-label" for="inAppNotifications">
                                In-app notifications
                            </label>
                        </div>

                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" id="pushNotifications" name="push_notifications"
                                   {($preferences['push_notifications'] ? 'checked' : '')}>
                            <label class="form-check-label" for="pushNotifications">
                                Push notifications
                            </label>
                        </div>

                        <hr class="my-4">

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            HTML;
}

// SECURITY TAB
elseif ($tab === 'security') {
    $content .= <<<HTML
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-key"></i> Change Password</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">

                        <div class="mb-3">
                            <label for="currentPassword" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="currentPassword" name="current_password" required>
                        </div>

                        <div class="mb-3">
                            <label for="newPassword" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="newPassword" name="new_password" required>
                            <small class="form-text text-muted">
                                Minimum 8 characters. Include uppercase, numbers, and special characters.
                            </small>
                        </div>

                        <div class="mb-4">
                            <label for="confirmPassword" class="form-label">Confirm Password</label>
                            <input type="password" class="form-control" id="confirmPassword" name="confirm_password" required>
                        </div>

                        <hr class="my-4">

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-shield-alt"></i> Two-Factor Authentication</h5>
                </div>
                <div class="card-body">
                    <p>Two-factor authentication adds an extra layer of security to your account.</p>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        Two-factor authentication is currently {($settings['two_factor_enabled'] ? '<strong>ENABLED</strong>' : '<strong>DISABLED</strong>')}.
                    </div>
                    <button class="btn btn-primary">
                        <i class="fas fa-cog"></i> Configure 2FA
                    </button>
                </div>
            </div>
            HTML;
}

// PRIVACY TAB
elseif ($tab === 'privacy') {
    $content .= <<<HTML
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-lock"></i> Privacy Settings</h5>
                </div>
                <div class="card-body">
                    <h6 class="mb-3">Profile Visibility</h6>
                    <div class="mb-4">
                        <p class="text-muted">Control who can see your profile information</p>
                        HTML;

    foreach (CORE_PROFILE_VISIBILITY_OPTIONS as $key => $name) {
        $checked = $preferences['profile_visibility'] === $key ? 'checked' : '';
        $content .= <<<HTML
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="radio" name="profile_visibility"
                                   value="$key" id="visibility_$key" $checked>
                            <label class="form-check-label" for="visibility_$key">
                                $name
                            </label>
                        </div>
                        HTML;
    }

    $content .= <<<HTML
                    </div>

                    <hr class="my-4">

                    <h6 class="mb-3">Data & Privacy</h6>
                    <p class="text-muted">Manage your personal data</p>

                    <div class="row g-2">
                        <div class="col-md-6">
                            <button class="btn btn-outline-primary w-100">
                                <i class="fas fa-download"></i> Download My Data
                            </button>
                        </div>
                        <div class="col-md-6">
                            <button class="btn btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                                <i class="fas fa-trash"></i> Delete Account
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            HTML;
}

// ACTIVITY LOG TAB
elseif ($tab === 'activity') {
    $content .= <<<HTML
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-history"></i> Activity Log</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted">Your recent account activity</p>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Action</th>
                                    <th>IP Address</th>
                                    <th>Device</th>
                                    <th>Date & Time</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><span class="badge bg-success">Login</span></td>
                                    <td>192.168.1.1</td>
                                    <td>Chrome on Windows</td>
                                    <td>Today at 10:30 AM</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-info">Settings Change</span></td>
                                    <td>192.168.1.1</td>
                                    <td>Chrome on Windows</td>
                                    <td>Today at 9:15 AM</td>
                                </tr>
                                <tr>
                                    <td><span class="badge bg-warning">Failed Login</span></td>
                                    <td>203.0.113.45</td>
                                    <td>Firefox on Linux</td>
                                    <td>Yesterday at 11:45 PM</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            HTML;
}

$content .= <<<HTML
        </div>
    </div>
</div>

<!-- Delete Account Modal -->
<div class="modal fade" id="deleteAccountModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-danger">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title">Delete Account</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p><strong>Warning:</strong> This action is permanent and cannot be undone.</p>
                <p>Your profile, settings, and all associated data will be permanently deleted.</p>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" id="confirmDelete">
                    <label class="form-check-label" for="confirmDelete">
                        I understand and want to delete my account
                    </label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-danger" id="deleteBtn" disabled>
                    <i class="fas fa-trash"></i> Delete Account
                </button>
            </div>
        </div>
    </div>
</div>

<style>
    .list-group-item.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-color: #667eea;
    }

    .card {
        border: 1px solid #eee;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }

    .card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
    }

    .card-title {
        color: white;
    }
</style>

<script>
// Delete account confirmation
document.getElementById('confirmDelete').addEventListener('change', function() {
    document.getElementById('deleteBtn').disabled = !this.checked;
});

document.getElementById('deleteBtn').addEventListener('click', function() {
    if (confirm('Are you absolutely sure? This cannot be undone.')) {
        // Submit delete request
        alert('Account deletion requested. You will receive a confirmation email.');
    }
});

// Toggle DND settings visibility
function toggleDNDSettings() {
    const dndEnabled = document.getElementById('dndEnabled').checked;
    const dndSettings = document.getElementById('dndSettings');
    if (dndSettings) {
        dndSettings.style.display = dndEnabled ? 'block' : 'none';
    }
}
</script>
HTML;

// Render with template
renderMainLayout($page, $content);
?>
