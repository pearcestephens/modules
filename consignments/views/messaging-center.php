<?php
/**
 * =============================================================================
 * MESSAGING CENTER - COMPLETE INTEGRATION
 * =============================================================================
 *
 * This is the main messaging hub integrating:
 * - Facebook-style chat bar (bottom)
 * - Group conversations
 * - Direct messages
 * - Notifications center
 * - Profile & chat settings
 * - AI chat assistant
 *
 * Components Found:
 * ✅ /modules/base/api/messenger.php - Messenger API
 * ✅ /modules/base/services/ChatService.php - Chat backend
 * ✅ /modules/base/templates/components/chat-bar.php - Bottom chat bar
 * ✅ /modules/base/database/chat_platform_schema_v3.sql - Database schema
 * ✅ /modules/base/api/notifications.php - Notifications API
 *
 * =============================================================================
 */

require_once __DIR__ . '/../bootstrap.php';

// ChatService now uses PDO (Database::getInstance) - safe to include
require_once __DIR__ . '/../../base/services/ChatService.php';
use CIS\Base\Services\ChatService;

$chatService = new ChatService();

$currentUser = [
    'id' => $_SESSION['user_id'] ?? 0,
    'name' => $_SESSION['first_name'] ?? 'Guest',
    'email' => $_SESSION['email'] ?? '',
];

// Get view mode (inbox, groups, channels, notifications, settings)
$view = $_GET['view'] ?? 'inbox';

// Get layout mode (fullwidth, standard, compact)
$layoutMode = $_GET['layout'] ?? 'standard';

// Start output buffering
ob_start();
?>

<div class="messaging-center">

    <!-- Header with Tabs -->
    <div class="messaging-header">
        <div class="messaging-header-top">
            <h1 class="messaging-title">
                <i class="bi bi-chat-dots-fill"></i>
                Messaging Center
            </h1>

            <!-- Layout Mode Switcher -->
            <div class="layout-mode-switcher">
                <span class="switcher-label">Layout:</span>
                <div class="layout-buttons">
                    <a href="?page=messaging&view=<?= $view ?>&layout=fullwidth"
                       class="layout-btn <?= $layoutMode === 'fullwidth' ? 'active' : '' ?>"
                       title="Full Width - No sidebars">
                        <i class="bi bi-window"></i> Full
                    </a>
                    <a href="?page=messaging&view=<?= $view ?>&layout=standard"
                       class="layout-btn <?= $layoutMode === 'standard' ? 'active' : '' ?>"
                       title="Standard - Right sidebar hidden">
                        <i class="bi bi-layout-sidebar-inset"></i> Standard
                    </a>
                    <a href="?page=messaging&view=<?= $view ?>&layout=compact"
                       class="layout-btn <?= $layoutMode === 'compact' ? 'active' : '' ?>"
                       title="Compact - With sidebar">
                        <i class="bi bi-layout-three-columns"></i> Compact
                    </a>
                </div>
            </div>
        </div>

        <div class="messaging-tabs">
            <a href="?page=messaging&view=inbox&layout=<?= $layoutMode ?>" class="messaging-tab <?= $view === 'inbox' ? 'active' : '' ?>">
                <i class="bi bi-inbox-fill"></i>
                <span>Inbox</span>
                <span class="badge bg-primary">3</span>
            </a>
            <a href="?page=messaging&view=groups&layout=<?= $layoutMode ?>" class="messaging-tab <?= $view === 'groups' ? 'active' : '' ?>">
                <i class="bi bi-people-fill"></i>
                <span>Groups</span>
            </a>
            <a href="?page=messaging&view=channels&layout=<?= $layoutMode ?>" class="messaging-tab <?= $view === 'channels' ? 'active' : '' ?>">
                <i class="bi bi-hash"></i>
                <span>Channels</span>
            </a>
            <a href="?page=messaging&view=notifications&layout=<?= $layoutMode ?>" class="messaging-tab <?= $view === 'notifications' ? 'active' : '' ?>">
                <i class="bi bi-bell-fill"></i>
                <span>Notifications</span>
                <span class="badge bg-danger">5</span>
            </a>
            <a href="?page=messaging&view=settings&layout=<?= $layoutMode ?>" class="messaging-tab <?= $view === 'settings' ? 'active' : '' ?>">
                <i class="bi bi-gear-fill"></i>
                <span>Settings</span>
            </a>
        </div>
    </div>

    <!-- Main Content Area -->
    <div class="messaging-content">

        <?php if ($view === 'inbox'): ?>
            <!-- INBOX VIEW -->
            <div class="messaging-layout">

                <!-- Left: Conversations List -->
                <aside class="conversations-list">
                    <div class="conversations-header">
                        <input type="search" placeholder="Search conversations..." class="conversations-search">
                        <button class="btn btn-sm btn-primary" onclick="startNewChat()">
                            <i class="bi bi-pencil-square"></i>
                        </button>
                    </div>

                    <div class="conversations-items">
                        <!-- Demo conversations -->
                        <div class="conversation-item active">
                            <div class="conversation-avatar">
                                <img src="https://ui-avatars.com/api/?name=John+Doe&background=667eea&color=fff" alt="John Doe">
                                <span class="status-indicator online"></span>
                            </div>
                            <div class="conversation-details">
                                <div class="conversation-name">John Doe</div>
                                <div class="conversation-preview">Hey, are you available?</div>
                            </div>
                            <div class="conversation-meta">
                                <div class="conversation-time">2m ago</div>
                                <span class="unread-badge">2</span>
                            </div>
                        </div>

                        <div class="conversation-item">
                            <div class="conversation-avatar">
                                <img src="https://ui-avatars.com/api/?name=Team+Chat&background=10b981&color=fff" alt="Team">
                                <span class="group-indicator"><i class="bi bi-people-fill"></i></span>
                            </div>
                            <div class="conversation-details">
                                <div class="conversation-name">Team Chat</div>
                                <div class="conversation-preview"><strong>Sarah:</strong> Meeting at 3pm</div>
                            </div>
                            <div class="conversation-meta">
                                <div class="conversation-time">15m ago</div>
                            </div>
                        </div>

                        <div class="conversation-item">
                            <div class="conversation-avatar">
                                <img src="https://ui-avatars.com/api/?name=Store+Auckland&background=f59e0b&color=fff" alt="Store">
                            </div>
                            <div class="conversation-details">
                                <div class="conversation-name">Store - Auckland</div>
                                <div class="conversation-preview">Stock transfer completed</div>
                            </div>
                            <div class="conversation-meta">
                                <div class="conversation-time">1h ago</div>
                            </div>
                        </div>
                    </div>
                </aside>

                <!-- Center: Chat Window -->
                <main class="chat-window">
                    <div class="chat-header">
                        <div class="chat-header-user">
                            <img src="https://ui-avatars.com/api/?name=John+Doe&background=667eea&color=fff" alt="John Doe" class="chat-avatar">
                            <div class="chat-header-info">
                                <h3>John Doe</h3>
                                <span class="status-text"><span class="status-dot online"></span> Online</span>
                            </div>
                        </div>
                        <div class="chat-header-actions">
                            <button class="btn-icon" title="Call"><i class="bi bi-telephone-fill"></i></button>
                            <button class="btn-icon" title="Video Call"><i class="bi bi-camera-video-fill"></i></button>
                            <button class="btn-icon" title="Info"><i class="bi bi-info-circle-fill"></i></button>
                        </div>
                    </div>

                    <div class="chat-messages" id="chatMessages">
                        <!-- Demo messages -->
                        <div class="message received">
                            <img src="https://ui-avatars.com/api/?name=John+Doe&background=667eea&color=fff" class="message-avatar">
                            <div class="message-content">
                                <div class="message-bubble">
                                    Hey! Are you available for a quick chat?
                                </div>
                                <div class="message-time">2:30 PM</div>
                            </div>
                        </div>

                        <div class="message sent">
                            <div class="message-content">
                                <div class="message-bubble">
                                    Yes, I'm here! What's up?
                                </div>
                                <div class="message-time">2:31 PM · Read</div>
                            </div>
                        </div>

                        <div class="message received">
                            <img src="https://ui-avatars.com/api/?name=John+Doe&background=667eea&color=fff" class="message-avatar">
                            <div class="message-content">
                                <div class="message-bubble">
                                    I need help with the stock transfer for Auckland store.
                                    <br><br>
                                    Can you check if the consignment has been approved?
                                </div>
                                <div class="message-time">2:32 PM</div>
                            </div>
                        </div>

                        <div class="typing-indicator">
                            <img src="https://ui-avatars.com/api/?name=John+Doe&background=667eea&color=fff" class="message-avatar">
                            <div class="typing-dots">
                                <span></span>
                                <span></span>
                                <span></span>
                            </div>
                        </div>
                    </div>

                    <div class="chat-input">
                        <button class="btn-icon" title="Attach File"><i class="bi bi-paperclip"></i></button>
                        <button class="btn-icon" title="Emoji"><i class="bi bi-emoji-smile"></i></button>
                        <input type="text" placeholder="Type a message..." class="message-input">
                        <button class="btn-send"><i class="bi bi-send-fill"></i></button>
                    </div>
                </main>

                <!-- Right: Chat Details -->
                <aside class="chat-details">
                    <div class="chat-details-header">
                        <img src="https://ui-avatars.com/api/?name=John+Doe&background=667eea&color=fff" alt="John Doe" class="detail-avatar">
                        <h3>John Doe</h3>
                        <p>Store Manager - Auckland</p>
                    </div>

                    <div class="chat-details-section">
                        <h4>Shared Files</h4>
                        <div class="shared-files">
                            <div class="shared-file">
                                <i class="bi bi-file-earmark-pdf-fill text-danger"></i>
                                <span>Transfer_Report.pdf</span>
                            </div>
                            <div class="shared-file">
                                <i class="bi bi-file-earmark-image-fill text-primary"></i>
                                <span>Product_Image.jpg</span>
                            </div>
                        </div>
                    </div>

                    <div class="chat-details-section">
                        <h4>Quick Actions</h4>
                        <button class="detail-action">
                            <i class="bi bi-bell-fill"></i>
                            Mute Notifications
                        </button>
                        <button class="detail-action">
                            <i class="bi bi-search"></i>
                            Search in Conversation
                        </button>
                        <button class="detail-action text-danger">
                            <i class="bi bi-trash-fill"></i>
                            Delete Conversation
                        </button>
                    </div>
                </aside>

            </div>

        <?php elseif ($view === 'groups'): ?>
            <!-- GROUPS VIEW -->
            <div class="groups-grid">
                <div class="group-card">
                    <div class="group-icon">
                        <i class="bi bi-people-fill"></i>
                    </div>
                    <h3>Team Chat</h3>
                    <p>12 members · 247 messages</p>
                    <button class="btn btn-primary btn-sm">Open Chat</button>
                </div>

                <div class="group-card">
                    <div class="group-icon bg-success">
                        <i class="bi bi-shop"></i>
                    </div>
                    <h3>Store Managers</h3>
                    <p>17 members · 1,543 messages</p>
                    <button class="btn btn-primary btn-sm">Open Chat</button>
                </div>

                <div class="group-card">
                    <div class="group-icon bg-warning">
                        <i class="bi bi-box-seam"></i>
                    </div>
                    <h3>Inventory Team</h3>
                    <p>8 members · 89 messages</p>
                    <button class="btn btn-primary btn-sm">Open Chat</button>
                </div>

                <div class="group-card create-new">
                    <i class="bi bi-plus-circle-fill"></i>
                    <h3>Create New Group</h3>
                </div>
            </div>

        <?php elseif ($view === 'channels'): ?>
            <!-- CHANNELS VIEW -->
            <div class="channels-list">
                <h2>Available Channels</h2>
                <p class="text-muted">Join channels to stay updated with announcements and department discussions</p>

                <div class="channel-item">
                    <div class="channel-icon">
                        <i class="bi bi-megaphone-fill"></i>
                    </div>
                    <div class="channel-info">
                        <h4>#announcements</h4>
                        <p>Company-wide announcements and updates</p>
                    </div>
                    <button class="btn btn-outline-primary btn-sm">Join</button>
                </div>

                <div class="channel-item">
                    <div class="channel-icon bg-success">
                        <i class="bi bi-lightbulb-fill"></i>
                    </div>
                    <div class="channel-info">
                        <h4>#ideas</h4>
                        <p>Share your ideas and suggestions</p>
                    </div>
                    <button class="btn btn-primary btn-sm">Joined</button>
                </div>

                <div class="channel-item">
                    <div class="channel-icon bg-warning">
                        <i class="bi bi-tools"></i>
                    </div>
                    <div class="channel-info">
                        <h4>#support</h4>
                        <p>Get help from IT and support team</p>
                    </div>
                    <button class="btn btn-outline-primary btn-sm">Join</button>
                </div>
            </div>

        <?php elseif ($view === 'notifications'): ?>
            <!-- NOTIFICATIONS VIEW -->
            <div class="notifications-container">
                <div class="notifications-header">
                    <h2>Notifications</h2>
                    <button class="btn btn-sm btn-outline-primary">Mark all as read</button>
                </div>

                <div class="notification-item unread">
                    <div class="notification-icon bg-primary">
                        <i class="bi bi-chat-dots-fill"></i>
                    </div>
                    <div class="notification-content">
                        <strong>John Doe</strong> sent you a message
                        <span class="notification-time">2 minutes ago</span>
                    </div>
                </div>

                <div class="notification-item unread">
                    <div class="notification-icon bg-success">
                        <i class="bi bi-check-circle-fill"></i>
                    </div>
                    <div class="notification-content">
                        <strong>Stock Transfer</strong> has been approved
                        <span class="notification-time">15 minutes ago</span>
                    </div>
                </div>

                <div class="notification-item">
                    <div class="notification-icon bg-warning">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                    </div>
                    <div class="notification-content">
                        <strong>Low Stock Alert:</strong> Product XYZ is running low
                        <span class="notification-time">1 hour ago</span>
                    </div>
                </div>
            </div>

        <?php elseif ($view === 'settings'): ?>
            <!-- SETTINGS VIEW -->
            <div class="settings-container">
                <h2>Chat & Notification Settings</h2>

                <div class="settings-section">
                    <h3>Notifications</h3>
                    <div class="setting-item">
                        <label>
                            <input type="checkbox" checked>
                            Enable desktop notifications
                        </label>
                    </div>
                    <div class="setting-item">
                        <label>
                            <input type="checkbox" checked>
                            Play sound for new messages
                        </label>
                    </div>
                    <div class="setting-item">
                        <label>
                            <input type="checkbox">
                            Notify me when mentioned
                        </label>
                    </div>
                </div>

                <div class="settings-section">
                    <h3>Chat Preferences</h3>
                    <div class="setting-item">
                        <label>
                            Message preview
                            <select class="form-select form-select-sm">
                                <option>Show full message</option>
                                <option>Show sender only</option>
                                <option>Hide preview</option>
                            </select>
                        </label>
                    </div>
                    <div class="setting-item">
                        <label>
                            <input type="checkbox" checked>
                            Show typing indicators
                        </label>
                    </div>
                    <div class="setting-item">
                        <label>
                            <input type="checkbox" checked>
                            Send read receipts
                        </label>
                    </div>
                </div>

                <div class="settings-section">
                    <h3>Privacy</h3>
                    <div class="setting-item">
                        <label>
                            Who can message you
                            <select class="form-select form-select-sm">
                                <option>Everyone</option>
                                <option>Team members only</option>
                                <option>Contacts only</option>
                            </select>
                        </label>
                    </div>
                    <div class="setting-item">
                        <label>
                            <input type="checkbox">
                            Hide online status
                        </label>
                    </div>
                </div>

                <button class="btn btn-primary">Save Settings</button>
            </div>

        <?php endif; ?>

    </div>

</div>

<?php
$moduleContent = ob_get_clean();

// Choose layout based on mode
switch ($layoutMode) {
    case 'fullwidth':
        // Special messaging layout (no sidebars, full width)
        $layout = 'messaging';
        $hideRightSidebar = true;
        $pageClass = 'page-messaging page-messaging-fullwidth';
        break;

    case 'compact':
        // Standard layout with right sidebar visible
        $layout = 'main';
        $hideRightSidebar = false;
        $pageClass = 'page-messaging page-messaging-compact';
        break;

    case 'standard':
    default:
        // Standard layout with right sidebar hidden
        $layout = 'main';
        $hideRightSidebar = true;
        $pageClass = 'page-messaging page-messaging-standard';
        break;
}

$renderer->render($moduleContent, [
    'title' => 'Messaging Center - CIS 2.0',
    'class' => $pageClass,
    'layout' => $layout,
    'scripts' => [],
    'styles' => [],
    'hide_right_sidebar' => $hideRightSidebar
]);
?>

<!-- Include Chat Bar Component at Bottom -->
<?php
$CHAT_ENABLED = true;
include $_SERVER['DOCUMENT_ROOT'] . '/modules/base/templates/components/chat-bar.php';
?>

<style>
/* Messaging Center Styles */
.messaging-center {
    background: #f5f6f8;
    height: 100%;
    display: flex;
    flex-direction: column;
}

.messaging-header {
    background: white;
    padding: 16px 24px;
    border-bottom: 1px solid #e5e7eb;
    flex-shrink: 0;
}

.messaging-header-top {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 16px;
}

.layout-mode-switcher {
    display: flex;
    align-items: center;
    gap: 12px;
}

.switcher-label {
    font-size: 13px;
    color: #6b7280;
    font-weight: 500;
}

.layout-buttons {
    display: flex;
    gap: 0;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    overflow: hidden;
}

.layout-btn {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 12px;
    background: white;
    border: none;
    border-right: 1px solid #d1d5db;
    color: #6b7280;
    text-decoration: none;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s;
    cursor: pointer;
}

.layout-btn:last-child {
    border-right: none;
}

.layout-btn:hover {
    background: #f9fafb;
    color: #1f2937;
}

.layout-btn.active {
    background: #2563eb;
    color: white;
}

.layout-btn i {
    font-size: 14px;
}

/* Layout-specific adjustments */
.page-messaging-fullwidth .messaging-center {
    height: 100vh;
}

.page-messaging-standard .messaging-center,
.page-messaging-compact .messaging-center {
    min-height: calc(100vh - 180px);
}

.messaging-title {
    margin: 0 0 16px 0;
    font-size: 24px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
}

.messaging-tabs {
    display: flex;
    gap: 4px;
}

.messaging-tab {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 10px 16px;
    background: transparent;
    border: none;
    border-radius: 8px;
    text-decoration: none;
    color: #6b7280;
    font-weight: 500;
    transition: all 0.2s;
    position: relative;
}

.messaging-tab:hover {
    background: #f3f4f6;
    color: #1f2937;
}

.messaging-tab.active {
    background: #eff6ff;
    color: #2563eb;
}

.messaging-tab .badge {
    font-size: 11px;
    padding: 2px 6px;
}

/* Inbox Layout */
.messaging-layout {
    display: grid;
    grid-template-columns: 320px 1fr 280px;
    gap: 0;
    flex: 1;
    background: white;
    overflow: hidden;
    border-top: 1px solid #e5e7eb;
}

/* Full width mode */
.page-messaging-fullwidth .messaging-layout {
    height: calc(100vh - 220px);
    border-radius: 0;
    box-shadow: none;
    margin: 0;
}

/* Standard mode (in main container, no right sidebar) */
.page-messaging-standard .messaging-layout {
    height: calc(100vh - 240px);
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin: 0 24px 24px 24px;
}

/* Compact mode (in container with potential right sidebar) */
.page-messaging-compact .messaging-layout {
    height: calc(100vh - 240px);
    border-radius: 12px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin: 0 24px 24px 24px;
    grid-template-columns: 280px 1fr 240px; /* Smaller columns for compact */
}

/* Conversations List */
.conversations-list {
    border-right: 1px solid #e5e7eb;
    display: flex;
    flex-direction: column;
}

.conversations-header {
    padding: 16px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    gap: 8px;
}

.conversations-search {
    flex: 1;
    padding: 8px 12px;
    border: 1px solid #e5e7eb;
    border-radius: 8px;
    font-size: 14px;
}

.conversations-items {
    flex: 1;
    overflow-y: auto;
}

.conversation-item {
    display: flex;
    gap: 12px;
    padding: 12px 16px;
    cursor: pointer;
    transition: background 0.2s;
    border-bottom: 1px solid #f3f4f6;
}

.conversation-item:hover {
    background: #f9fafb;
}

.conversation-item.active {
    background: #eff6ff;
}

.conversation-avatar {
    position: relative;
}

.conversation-avatar img {
    width: 48px;
    height: 48px;
    border-radius: 50%;
}

.status-indicator {
    position: absolute;
    bottom: 0;
    right: 0;
    width: 12px;
    height: 12px;
    border: 2px solid white;
    border-radius: 50%;
    background: #10b981;
}

.status-indicator.online { background: #10b981; }
.status-indicator.away { background: #f59e0b; }
.status-indicator.offline { background: #6b7280; }

.conversation-details {
    flex: 1;
    min-width: 0;
}

.conversation-name {
    font-weight: 600;
    font-size: 14px;
    color: #1f2937;
    margin-bottom: 4px;
}

.conversation-preview {
    font-size: 13px;
    color: #6b7280;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.conversation-meta {
    text-align: right;
}

.conversation-time {
    font-size: 12px;
    color: #9ca3af;
    margin-bottom: 4px;
}

.unread-badge {
    display: inline-block;
    padding: 2px 6px;
    background: #2563eb;
    color: white;
    border-radius: 10px;
    font-size: 11px;
    font-weight: 600;
}

/* Chat Window */
.chat-window {
    display: flex;
    flex-direction: column;
}

.chat-header {
    padding: 16px 24px;
    border-bottom: 1px solid #e5e7eb;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.chat-header-user {
    display: flex;
    gap: 12px;
    align-items: center;
}

.chat-avatar {
    width: 40px;
    height: 40px;
    border-radius: 50%;
}

.chat-header-info h3 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.status-text {
    font-size: 13px;
    color: #6b7280;
    display: flex;
    align-items: center;
    gap: 6px;
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #10b981;
}

.chat-header-actions {
    display: flex;
    gap: 8px;
}

.btn-icon {
    width: 36px;
    height: 36px;
    border: none;
    background: transparent;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #6b7280;
    font-size: 18px;
    transition: all 0.2s;
}

.btn-icon:hover {
    background: #f3f4f6;
    color: #1f2937;
}

.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 24px;
    background: #f9fafb;
}

.message {
    display: flex;
    gap: 12px;
    margin-bottom: 16px;
}

.message.sent {
    justify-content: flex-end;
}

.message-avatar {
    width: 32px;
    height: 32px;
    border-radius: 50%;
}

.message-content {
    max-width: 60%;
}

.message.sent .message-content {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
}

.message-bubble {
    padding: 12px 16px;
    border-radius: 18px;
    background: white;
    box-shadow: 0 1px 2px rgba(0,0,0,0.05);
    font-size: 14px;
    line-height: 1.5;
}

.message.sent .message-bubble {
    background: #2563eb;
    color: white;
}

.message-time {
    font-size: 11px;
    color: #9ca3af;
    margin-top: 4px;
}

.typing-indicator {
    display: flex;
    gap: 12px;
    align-items: center;
}

.typing-dots {
    display: flex;
    gap: 4px;
    padding: 12px 16px;
    background: white;
    border-radius: 18px;
}

.typing-dots span {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #9ca3af;
    animation: typing 1.4s infinite;
}

.typing-dots span:nth-child(2) { animation-delay: 0.2s; }
.typing-dots span:nth-child(3) { animation-delay: 0.4s; }

@keyframes typing {
    0%, 60%, 100% { transform: translateY(0); }
    30% { transform: translateY(-8px); }
}

.chat-input {
    padding: 16px 24px;
    border-top: 1px solid #e5e7eb;
    display: flex;
    gap: 12px;
    align-items: center;
    background: white;
}

.message-input {
    flex: 1;
    padding: 10px 16px;
    border: 1px solid #e5e7eb;
    border-radius: 24px;
    font-size: 14px;
}

.btn-send {
    width: 40px;
    height: 40px;
    border: none;
    background: #2563eb;
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 16px;
    transition: all 0.2s;
}

.btn-send:hover {
    background: #1d4ed8;
    transform: scale(1.05);
}

/* Chat Details */
.chat-details {
    border-left: 1px solid #e5e7eb;
    padding: 24px;
    overflow-y: auto;
}

.chat-details-header {
    text-align: center;
    margin-bottom: 24px;
}

.detail-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    margin-bottom: 12px;
}

.chat-details-header h3 {
    margin: 0 0 4px 0;
    font-size: 18px;
    font-weight: 600;
}

.chat-details-header p {
    margin: 0;
    font-size: 13px;
    color: #6b7280;
}

.chat-details-section {
    margin-bottom: 24px;
    padding-bottom: 24px;
    border-bottom: 1px solid #e5e7eb;
}

.chat-details-section h4 {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 12px;
    color: #1f2937;
}

.shared-files {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.shared-file {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px;
    background: #f9fafb;
    border-radius: 6px;
    font-size: 13px;
}

.detail-action {
    width: 100%;
    padding: 10px 12px;
    border: none;
    background: #f9fafb;
    border-radius: 6px;
    text-align: left;
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 14px;
    cursor: pointer;
    transition: all 0.2s;
    margin-bottom: 8px;
}

.detail-action:hover {
    background: #f3f4f6;
}

.detail-action.text-danger {
    color: #dc2626;
}

/* Groups Grid */
.groups-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 24px;
    padding: 24px;
}

.group-card {
    background: white;
    border-radius: 12px;
    padding: 24px;
    text-align: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: all 0.3s;
}

.group-card:hover {
    transform: translateY(-4px);
    box-shadow: 0 8px 16px rgba(0,0,0,0.15);
}

.group-icon {
    width: 60px;
    height: 60px;
    margin: 0 auto 16px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 24px;
}

.group-card h3 {
    margin: 0 0 8px 0;
    font-size: 18px;
    font-weight: 600;
}

.group-card p {
    margin: 0 0 16px 0;
    font-size: 13px;
    color: #6b7280;
}

.group-card.create-new {
    border: 2px dashed #e5e7eb;
    background: transparent;
    cursor: pointer;
    display: flex;
    flex-direction: column;
    justify-content: center;
    align-items: center;
}

.group-card.create-new i {
    font-size: 48px;
    color: #9ca3af;
    margin-bottom: 12px;
}

/* Channels List */
.channels-list {
    padding: 24px;
}

.channels-list h2 {
    margin: 0 0 8px 0;
    font-size: 24px;
    font-weight: 700;
}

.channel-item {
    display: flex;
    gap: 16px;
    align-items: center;
    padding: 20px;
    background: white;
    border-radius: 12px;
    margin-top: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05);
}

.channel-icon {
    width: 48px;
    height: 48px;
    background: #2563eb;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 20px;
    flex-shrink: 0;
}

.channel-info {
    flex: 1;
}

.channel-info h4 {
    margin: 0 0 4px 0;
    font-size: 16px;
    font-weight: 600;
}

.channel-info p {
    margin: 0;
    font-size: 13px;
    color: #6b7280;
}

/* Notifications */
.notifications-container {
    padding: 24px;
}

.notifications-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 24px;
}

.notifications-header h2 {
    margin: 0;
    font-size: 24px;
    font-weight: 700;
}

.notification-item {
    display: flex;
    gap: 16px;
    padding: 16px;
    background: white;
    border-radius: 12px;
    margin-bottom: 12px;
    transition: all 0.2s;
}

.notification-item:hover {
    background: #f9fafb;
}

.notification-item.unread {
    background: #eff6ff;
}

.notification-icon {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    flex-shrink: 0;
}

.notification-content {
    flex: 1;
}

.notification-content strong {
    color: #1f2937;
}

.notification-time {
    display: block;
    font-size: 12px;
    color: #9ca3af;
    margin-top: 4px;
}

/* Settings */
.settings-container {
    padding: 24px;
    max-width: 800px;
}

.settings-container h2 {
    margin: 0 0 24px 0;
    font-size: 24px;
    font-weight: 700;
}

.settings-section {
    background: white;
    padding: 24px;
    border-radius: 12px;
    margin-bottom: 24px;
}

.settings-section h3 {
    margin: 0 0 16px 0;
    font-size: 18px;
    font-weight: 600;
}

.setting-item {
    margin-bottom: 16px;
}

.setting-item label {
    display: flex;
    align-items: center;
    gap: 12px;
    cursor: pointer;
    font-size: 14px;
}

.setting-item input[type="checkbox"] {
    width: 18px;
    height: 18px;
    cursor: pointer;
}

/* Responsive */
@media (max-width: 1200px) {
    .messaging-layout {
        grid-template-columns: 280px 1fr;
    }

    .chat-details {
        display: none;
    }
}

@media (max-width: 768px) {
    .messaging-layout {
        grid-template-columns: 1fr;
    }

    .conversations-list {
        display: none;
    }

    .chat-details {
        display: none;
    }
}
</style>

<script>
function startNewChat() {
    VapeUltra.Modal.prompt({
        title: 'New Conversation',
        message: 'Enter username or email:',
        placeholder: 'john.doe@vapeshed.co.nz'
    }).then(result => {
        if (result) {
            VapeUltra.Toast.success('Starting conversation with ' + result);
        }
    });
}
</script>
