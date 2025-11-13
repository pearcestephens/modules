<?php
/**
 * User Preferences Page
 * Manage notification preferences, dashboard layout, privacy settings
 */

require_once __DIR__ . '/../../base/templates/vape-ultra/config.php';
require_once __DIR__ . '/../../base/templates/vape-ultra/layouts/main.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../controllers/PreferencesController.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

// Initialize controller
$preferencesController = new PreferencesController();

// Get current preferences
$preferences = $preferencesController->getPreferences($_SESSION['user_id']);

// Handle form submission
$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $data = [
            'dashboard_layout' => htmlspecialchars($_POST['dashboard_layout'] ?? ''),
            'items_per_page' => (int)($_POST['items_per_page'] ?? 25),
            'notification_frequency' => htmlspecialchars($_POST['notification_frequency'] ?? ''),
            'profile_visibility' => htmlspecialchars($_POST['profile_visibility'] ?? ''),
            'email_daily_digest' => isset($_POST['email_daily_digest']),
            'email_alerts' => isset($_POST['email_alerts']),
            'email_updates' => isset($_POST['email_updates']),
            'in_app_notifications' => isset($_POST['in_app_notifications']),
            'push_notifications' => isset($_POST['push_notifications']),
            'show_online_status' => isset($_POST['show_online_status']),
            'allow_direct_messages' => isset($_POST['allow_direct_messages']),
            'show_last_activity' => isset($_POST['show_last_activity']),
            'show_tooltips' => isset($_POST['show_tooltips']),
            'show_animations' => isset($_POST['show_animations']),
            'compact_mode' => isset($_POST['compact_mode'])
        ];

        // Handle digest time and day if notification frequency is digest
        if ($data['notification_frequency'] === 'digest') {
            $data['digest_day'] = htmlspecialchars($_POST['digest_day'] ?? 'monday');
            $data['digest_time'] = htmlspecialchars($_POST['digest_time'] ?? '09:00');
        }

        if ($preferencesController->updatePreferences($_SESSION['user_id'], $data)) {
            $message = 'Preferences updated successfully!';
            $preferences = $preferencesController->getPreferences($_SESSION['user_id']);
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Page configuration
$page = [
    'title' => 'Preferences',
    'breadcrumbs' => [
        'Home' => '/admin/',
        'Preferences' => null
    ],
    'icon' => 'fas fa-star'
];

// Build content
$content = <<<HTML
<div class="preferences-container">
    <div class="row">
        <!-- Main Content -->
        <div class="col-12">
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

$content .= <<<HTML
            <form method="POST" id="preferencesForm">
                <!-- Dashboard Preferences -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-th-large"></i> Dashboard Preferences</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label for="layout" class="form-label">Dashboard Layout</label>
                                <select class="form-select" id="layout" name="dashboard_layout" required>
                                    HTML;

foreach (CORE_DASHBOARD_LAYOUTS as $key => $name) {
    $selected = $preferences['dashboard_layout'] === $key ? 'selected' : '';
    $content .= "<option value=\"$key\" $selected>$name</option>";
}

$content .= <<<HTML
                                </select>
                                <small class="form-text text-muted">Choose how your dashboard is displayed</small>
                            </div>

                            <div class="col-md-6">
                                <label for="itemsPerPage" class="form-label">Items Per Page</label>
                                <select class="form-select" id="itemsPerPage" name="items_per_page" required>
                                    HTML;

foreach (CORE_ITEMS_PER_PAGE_OPTIONS as $option) {
    $selected = $preferences['items_per_page'] === $option ? 'selected' : '';
    $content .= "<option value=\"$option\" $selected>$option items</option>";
}

$content .= <<<HTML
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notification Preferences -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-bell"></i> Notification Preferences</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="frequency" class="form-label">Notification Frequency</label>
                                <select class="form-select" id="frequency" name="notification_frequency"
                                        onchange="toggleDigestSettings()">
                                    HTML;

foreach (CORE_NOTIFICATION_FREQUENCIES as $key => $name) {
    $selected = $preferences['notification_frequency'] === $key ? 'selected' : '';
    $content .= "<option value=\"$key\" $selected>$name</option>";
}

$content .= <<<HTML
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="digestDay" class="form-label">Digest Day</label>
                                <select class="form-select" id="digestDay" name="digest_day">
                                    HTML;

$days = ['monday' => 'Monday', 'tuesday' => 'Tuesday', 'wednesday' => 'Wednesday',
         'thursday' => 'Thursday', 'friday' => 'Friday', 'saturday' => 'Saturday', 'sunday' => 'Sunday'];
foreach ($days as $key => $name) {
    $selected = ($preferences['digest_day'] ?? 'monday') === $key ? 'selected' : '';
    $content .= "<option value=\"$key\" $selected>$name</option>";
}

$content .= <<<HTML
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="digestTime" class="form-label">Digest Time</label>
                                <input type="time" class="form-control" id="digestTime"
                                       name="digest_time" value="{$preferences['digest_time']}">
                            </div>
                        </div>

                        <hr class="my-3">

                        <h6 class="mb-3">Notification Channels</h6>
                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="dailyDigest"
                                           name="email_daily_digest" {($preferences['email_daily_digest'] ? 'checked' : '')}>
                                    <label class="form-check-label" for="dailyDigest">
                                        <strong>Daily Digest</strong>
                                        <br><small class="text-muted">Email summary of daily activities</small>
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="emailAlerts"
                                           name="email_alerts" {($preferences['email_alerts'] ? 'checked' : '')}>
                                    <label class="form-check-label" for="emailAlerts">
                                        <strong>Email Alerts</strong>
                                        <br><small class="text-muted">Immediate notifications for important events</small>
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="emailUpdates"
                                           name="email_updates" {($preferences['email_updates'] ? 'checked' : '')}>
                                    <label class="form-check-label" for="emailUpdates">
                                        <strong>Email Updates</strong>
                                        <br><small class="text-muted">Product updates and announcements</small>
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="inAppNotifications"
                                           name="in_app_notifications" {($preferences['in_app_notifications'] ? 'checked' : '')}>
                                    <label class="form-check-label" for="inAppNotifications">
                                        <strong>In-App Notifications</strong>
                                        <br><small class="text-muted">Real-time notifications in app</small>
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="pushNotifications"
                                           name="push_notifications" {($preferences['push_notifications'] ? 'checked' : '')}>
                                    <label class="form-check-label" for="pushNotifications">
                                        <strong>Push Notifications</strong>
                                        <br><small class="text-muted">Browser push notifications</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Privacy & Display -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-eye"></i> Privacy & Display</h5>
                    </div>
                    <div class="card-body">
                        <h6 class="mb-3">Profile Visibility</h6>
                        <div class="row g-3 mb-4">
                            HTML;

foreach (CORE_PROFILE_VISIBILITY_OPTIONS as $key => $name) {
    $checked = $preferences['profile_visibility'] === $key ? 'checked' : '';
    $descriptions = [
        'private' => 'Only you can see your profile',
        'team' => 'Team members can see your profile',
        'network' => 'Everyone in your network can see your profile',
        'public' => 'Anyone can see your profile'
    ];

    $content .= <<<HTML
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="profile_visibility"
                                           value="$key" id="visibility_$key" $checked>
                                    <label class="form-check-label" for="visibility_$key">
                                        <strong>$name</strong>
                                        <br><small class="text-muted">{$descriptions[$key]}</small>
                                    </label>
                                </div>
                            </div>
                            HTML;
}

$content .= <<<HTML
                        </div>

                        <hr class="my-3">
                        <h6 class="mb-3">Privacy Settings</h6>
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="onlineStatus"
                                           name="show_online_status" {($preferences['show_online_status'] ? 'checked' : '')}>
                                    <label class="form-check-label" for="onlineStatus">
                                        <strong>Show my online status</strong>
                                    </label>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="directMessages"
                                           name="allow_direct_messages" {($preferences['allow_direct_messages'] ? 'checked' : '')}>
                                    <label class="form-check-label" for="directMessages">
                                        <strong>Allow direct messages from team members</strong>
                                    </label>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="lastActivity"
                                           name="show_last_activity" {($preferences['show_last_activity'] ? 'checked' : '')}>
                                    <label class="form-check-label" for="lastActivity">
                                        <strong>Show my last activity time</strong>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Display Options -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-sliders-h"></i> Display Options</h5>
                    </div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="tooltips"
                                           name="show_tooltips" {($preferences['show_tooltips'] ? 'checked' : '')}>
                                    <label class="form-check-label" for="tooltips">
                                        <strong>Show tooltips</strong>
                                        <br><small class="text-muted">Display helpful tooltips on hover</small>
                                    </label>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="animations"
                                           name="show_animations" {($preferences['show_animations'] ? 'checked' : '')}>
                                    <label class="form-check-label" for="animations">
                                        <strong>Enable animations</strong>
                                        <br><small class="text-muted">Smooth transitions and effects</small>
                                    </label>
                                </div>
                            </div>

                            <div class="col-12">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="compactMode"
                                           name="compact_mode" {($preferences['compact_mode'] ? 'checked' : '')}>
                                    <label class="form-check-label" for="compactMode">
                                        <strong>Compact mode</strong>
                                        <br><small class="text-muted">Reduced spacing and smaller text</small>
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chat Preferences (NEW) -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="card-title mb-0"><i class="fas fa-comments"></i> Chat & Messaging Preferences</h5>
                    </div>
                    <div class="card-body">
                        <h6 class="mb-3"><i class="fas fa-handshake"></i> Friend & Contact Settings</h6>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="friendRequestPolicy" class="form-label">Friend Requests</label>
                                <select class="form-select" id="friendRequestPolicy" name="friend_request_policy">
                                    <option value="manual" {(($preferences['friend_request_policy'] ?? 'manual') === 'manual' ? 'selected' : '')}>
                                        ✋ Require my approval
                                    </option>
                                    <option value="auto_accept" {(($preferences['friend_request_policy'] ?? '') === 'auto_accept' ? 'selected' : '')}>
                                        ✅ Auto-accept from anyone
                                    </option>
                                    <option value="contacts_only" {(($preferences['friend_request_policy'] ?? '') === 'contacts_only' ? 'selected' : '')}>
                                        👥 Contacts only
                                    </option>
                                </select>
                                <small class="form-text text-muted">How you want to handle friend requests</small>
                            </div>

                            <div class="col-md-6">
                                <label for="groupInvites" class="form-label">Group Invitations</label>
                                <select class="form-select" id="groupInvites" name="group_invitation_policy">
                                    <option value="notify" {(($preferences['group_invitation_policy'] ?? 'notify') === 'notify' ? 'selected' : '')}>
                                        🔔 Notify me and ask
                                    </option>
                                    <option value="auto_join" {(($preferences['group_invitation_policy'] ?? '') === 'auto_join' ? 'selected' : '')}>
                                        ✅ Auto-join (notify after)
                                    </option>
                                    <option value="skip_notify" {(($preferences['group_invitation_policy'] ?? '') === 'skip_notify' ? 'selected' : '')}>
                                        🚫 Silently decline
                                    </option>
                                </select>
                            </div>
                        </div>

                        <hr class="my-3">
                        <h6 class="mb-3"><i class="fas fa-envelope"></i> Direct Message Privacy</h6>

                        <div class="mb-3">
                            <label for="dmPrivacy" class="form-label">Who Can Direct Message You?</label>
                            <select class="form-select" id="dmPrivacy" name="dm_privacy_level">
                                <option value="everyone" {(($preferences['dm_privacy_level'] ?? 'everyone') === 'everyone' ? 'selected' : '')}>
                                    🌍 Everyone
                                </option>
                                <option value="contacts" {(($preferences['dm_privacy_level'] ?? '') === 'contacts' ? 'selected' : '')}>
                                    👥 Contacts only
                                </option>
                                <option value="approved" {(($preferences['dm_privacy_level'] ?? '') === 'approved' ? 'selected' : '')}>
                                    ✅ Approved contacts only
                                </option>
                                <option value="blocked" {(($preferences['dm_privacy_level'] ?? '') === 'blocked' ? 'selected' : '')}>
                                    🚫 Nobody (disable DMs)
                                </option>
                            </select>
                            <small class="form-text text-muted">Limit who can send you direct messages</small>
                        </div>

                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" id="dmRequireApproval" name="dm_require_approval"
                                   {(isset($preferences['dm_require_approval']) && $preferences['dm_require_approval'] ? 'checked' : '')}>
                            <label class="form-check-label" for="dmRequireApproval">
                                <strong>Require approval for new conversations</strong>
                                <br><small class="text-muted">First messages from non-contacts go to Requests</small>
                            </label>
                        </div>

                        <hr class="my-3">
                        <h6 class="mb-3"><i class="fas fa-shield-alt"></i> Spam & Safety</h6>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="spamFilter" class="form-label">Spam Filtering Level</label>
                                <select class="form-select" id="spamFilter" name="spam_filter_level">
                                    <option value="permissive" {(($preferences['spam_filter_level'] ?? 'permissive') === 'permissive' ? 'selected' : '')}>
                                        🟢 Permissive (show most messages)
                                    </option>
                                    <option value="moderate" {(($preferences['spam_filter_level'] ?? '') === 'moderate' ? 'selected' : '')}>
                                        🟡 Moderate (balanced)
                                    </option>
                                    <option value="strict" {(($preferences['spam_filter_level'] ?? '') === 'strict' ? 'selected' : '')}>
                                        🔴 Strict (filter aggressively)
                                    </option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="messageSearch" class="form-label">Message Search Indexing</label>
                                <select class="form-select" id="messageSearch" name="message_search_indexing">
                                    <option value="enabled" {(($preferences['message_search_indexing'] ?? 'enabled') === 'enabled' ? 'selected' : '')}>
                                        ✅ Index my messages (searchable)
                                    </option>
                                    <option value="disabled" {(($preferences['message_search_indexing'] ?? '') === 'disabled' ? 'selected' : '')}>
                                        🔒 Don't index (not searchable)
                                    </option>
                                </select>
                            </div>
                        </div>

                        <hr class="my-3">
                        <h6 class="mb-3"><i class="fas fa-eye"></i> Activity & Visibility</h6>

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <label for="profileSearchability" class="form-label">Profile Search Visibility</label>
                                <select class="form-select" id="profileSearchability" name="profile_searchable">
                                    <option value="public" {(($preferences['profile_searchable'] ?? 'public') === 'public' ? 'selected' : '')}>
                                        🌍 Searchable by everyone
                                    </option>
                                    <option value="contacts" {(($preferences['profile_searchable'] ?? '') === 'contacts' ? 'selected' : '')}>
                                        👥 Searchable by contacts
                                    </option>
                                    <option value="hidden" {(($preferences['profile_searchable'] ?? '') === 'hidden' ? 'selected' : '')}>
                                        🔒 Hidden from search
                                    </option>
                                </select>
                            </div>

                            <div class="col-md-6">
                                <label for="statusFrequency" class="form-label">Status Update Frequency</label>
                                <select class="form-select" id="statusFrequency" name="status_update_frequency">
                                    <option value="always" {(($preferences['status_update_frequency'] ?? 'always') === 'always' ? 'selected' : '')}>
                                        ⏱️ Always show (real-time)
                                    </option>
                                    <option value="delayed_5" {(($preferences['status_update_frequency'] ?? '') === 'delayed_5' ? 'selected' : '')}>
                                        ⏱️ 5 minute delay
                                    </option>
                                    <option value="delayed_hour" {(($preferences['status_update_frequency'] ?? '') === 'delayed_hour' ? 'selected' : '')}>
                                        ⏱️ 1 hour delay
                                    </option>
                                    <option value="hidden" {(($preferences['status_update_frequency'] ?? '') === 'hidden' ? 'selected' : '')}>
                                        🔒 Don't show
                                    </option>
                                </select>
                                <small class="form-text text-muted">How often your activity status updates</small>
                            </div>
                        </div>

                        <div class="form-check form-switch mb-4">
                            <input class="form-check-input" type="checkbox" id="autoArchive" name="auto_archive_conversations"
                                   {(isset($preferences['auto_archive_conversations']) && $preferences['auto_archive_conversations'] ? 'checked' : '')}>
                            <label class="form-check-label" for="autoArchive">
                                <strong>Auto-archive old conversations</strong>
                                <br><small class="text-muted">Conversations inactive for 30+ days auto-archive</small>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Submit Buttons -->
                <div class="card mb-4 bg-light">
                    <div class="card-body">
                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="reset" class="btn btn-outline-secondary">
                                <i class="fas fa-redo"></i> Reset
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Preferences
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
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

    .form-check-label {
        padding-top: 0.25rem;
    }

    .form-check-label strong {
        display: block;
        margin-bottom: 0.25rem;
    }
</style>

<script>
function toggleDigestSettings() {
    const frequency = document.getElementById('frequency').value;
    const digestDay = document.getElementById('digestDay');
    const digestTime = document.getElementById('digestTime');

    if (frequency === 'digest') {
        digestDay.parentElement.style.display = 'block';
        digestTime.parentElement.style.display = 'block';
        digestDay.required = true;
        digestTime.required = true;
    } else {
        digestDay.parentElement.style.display = 'none';
        digestTime.parentElement.style.display = 'none';
        digestDay.required = false;
        digestTime.required = false;
    }
}

// Initialize on page load
document.addEventListener('DOMContentLoaded', toggleDigestSettings);
</script>
HTML;

$content .= <<<HTML
        </div>
    </div>
</div>
HTML;

// Render with template
renderMainLayout($page, $content);
?>
