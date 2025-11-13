<?php
/**
 * Messaging Center - Integrated with VapeUltra Template
 *
 * Layout Modes:
 * - Full: Template LEFT sidebar minimized to 60px vertical bar, RIGHT sidebar gone + Full 3-column messaging
 * - Standard: Template LEFT sidebar visible, RIGHT sidebar closed + Full 3-column messaging
 * - Compact: Template BOTH sidebars visible + Full 3-column messaging
 */

// Get layout mode from URL
$layoutMode = $_GET['layout'] ?? 'standard';
$validLayouts = ['fullwidth', 'standard', 'compact'];
if (!in_array($layoutMode, $validLayouts)) {
    $layoutMode = 'standard';
}

// Set template classes based on layout mode
$templateClass = 'layout-' . $layoutMode;
$hideLeftSidebar = false; // Never hide left sidebar completely
$hideRightSidebar = ($layoutMode !== 'compact'); // Only show in compact mode

?>

<div class="messaging-center-app <?php echo $templateClass; ?>">
    <!-- Messaging Header -->
    <div class="messaging-header">
        <div class="header-left">
            <h1 class="messaging-title">
                <i class="bi bi-chat-dots-fill"></i>
                Messaging Center
            </h1>
        </div>

        <div class="header-right">
            <span class="layout-label" id="layout-label">Layout:</span>
            <div class="layout-buttons" role="group" aria-labelledby="layout-label">
                <button class="layout-btn <?php echo $layoutMode === 'fullwidth' ? 'active' : ''; ?>"
                        data-layout="fullwidth"
                        onclick="switchLayout('fullwidth')"
                        aria-label="Switch to full width layout - minimized left sidebar, maximum messaging space"
                        aria-pressed="<?php echo $layoutMode === 'fullwidth' ? 'true' : 'false'; ?>"
                        title="Full Width (Ctrl+1)">
                    <i class="bi bi-window" aria-hidden="true"></i> Full
                </button>
                <button class="layout-btn <?php echo $layoutMode === 'standard' ? 'active' : ''; ?>"
                        data-layout="standard"
                        onclick="switchLayout('standard')"
                        aria-label="Switch to standard layout - full left sidebar, balanced view"
                        aria-pressed="<?php echo $layoutMode === 'standard' ? 'true' : 'false'; ?>"
                        title="Standard (Ctrl+2)">
                    <i class="bi bi-layout-sidebar-inset" aria-hidden="true"></i> Standard
                </button>
                <button class="layout-btn <?php echo $layoutMode === 'compact' ? 'active' : ''; ?>"
                        data-layout="compact"
                        onclick="switchLayout('compact')"
                        aria-label="Switch to compact layout - all sidebars visible, maximum features"
                        aria-pressed="<?php echo $layoutMode === 'compact' ? 'true' : 'false'; ?>"
                        title="Compact (Ctrl+3)">
                    <i class="bi bi-layout-three-columns" aria-hidden="true"></i> Compact
                </button>
            </div>
        </div>

        <!-- Screen Reader Announcement Area -->
        <div aria-live="polite" aria-atomic="true" class="sr-only" id="layout-status"></div>
    </div>

    <!-- Messaging Tabs -->
    <div class="messaging-tabs">
        <a href="?page=messaging&view=inbox&layout=<?php echo $layoutMode; ?>" class="msg-tab active">
            <i class="bi bi-inbox-fill"></i>
            <span>Inbox</span>
            <span class="badge badge-primary">3</span>
        </a>
        <a href="?page=messaging&view=groups&layout=<?php echo $layoutMode; ?>" class="msg-tab">
            <i class="bi bi-people-fill"></i>
            <span>Groups</span>
        </a>
        <a href="?page=messaging&view=channels&layout=<?php echo $layoutMode; ?>" class="msg-tab">
            <i class="bi bi-hash"></i>
            <span>Channels</span>
        </a>
        <a href="?page=messaging&view=notifications&layout=<?php echo $layoutMode; ?>" class="msg-tab">
            <i class="bi bi-bell-fill"></i>
            <span>Notifications</span>
            <span class="badge badge-danger">5</span>
        </a>
        <a href="?page=messaging&view=settings&layout=<?php echo $layoutMode; ?>" class="msg-tab">
            <i class="bi bi-gear-fill"></i>
            <span>Settings</span>
        </a>
    </div>

    <!-- Messaging Content (3-column layout) -->
    <div class="messaging-content <?php echo $templateClass; ?>">
        <!-- Left: Conversations List -->
        <div class="msg-conversations">
            <div class="conversations-header">
                <input type="search" placeholder="Search..." class="form-control form-control-sm">
                <button class="btn btn-sm btn-primary">
                    <i class="bi bi-pencil-square"></i>
                </button>
            </div>

            <div class="conversations-list">
                <?php for ($i = 1; $i <= 10; $i++): ?>
                <div class="conversation-item <?php echo $i === 1 ? 'active' : ''; ?>">
                    <div class="conv-avatar">
                        <img src="https://ui-avatars.com/api/?name=User+<?php echo $i; ?>&background=667eea&color=fff" alt="User <?php echo $i; ?>">
                        <span class="status-online"></span>
                    </div>
                    <div class="conv-info">
                        <div class="conv-name">User <?php echo $i; ?></div>
                        <div class="conv-preview">Last message preview...</div>
                    </div>
                    <div class="conv-meta">
                        <div class="conv-time">2m</div>
                        <?php if ($i <= 3): ?>
                        <span class="badge badge-primary"><?php echo $i; ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endfor; ?>
            </div>
        </div>

        <!-- Center: Chat Window -->
        <div class="msg-chat">
            <div class="chat-header">
                <div class="chat-user">
                    <img src="https://ui-avatars.com/api/?name=John+Doe&background=667eea&color=fff" alt="John Doe">
                    <div class="chat-user-info">
                        <div class="chat-user-name">John Doe</div>
                        <div class="chat-user-status">Active now</div>
                    </div>
                </div>
                <div class="chat-actions">
                    <button class="btn btn-sm btn-light"><i class="bi bi-telephone"></i></button>
                    <button class="btn btn-sm btn-light"><i class="bi bi-camera-video"></i></button>
                    <button class="btn btn-sm btn-light"><i class="bi bi-info-circle"></i></button>
                </div>
            </div>

            <div class="chat-messages">
                <div class="message received">
                    <div class="message-avatar">
                        <img src="https://ui-avatars.com/api/?name=John+Doe&background=667eea&color=fff" alt="John Doe">
                    </div>
                    <div class="message-content">
                        <div class="message-text">Hey, are you available for a quick chat?</div>
                        <div class="message-time">10:30 AM</div>
                    </div>
                </div>

                <div class="message sent">
                    <div class="message-content">
                        <div class="message-text">Sure! What's up?</div>
                        <div class="message-time">10:32 AM</div>
                    </div>
                </div>

                <div class="message received">
                    <div class="message-avatar">
                        <img src="https://ui-avatars.com/api/?name=John+Doe&background=667eea&color=fff" alt="John Doe">
                    </div>
                    <div class="message-content">
                        <div class="message-text">I wanted to discuss the inventory levels for Auckland store.</div>
                        <div class="message-time">10:33 AM</div>
                    </div>
                </div>
            </div>

            <div class="chat-input">
                <button class="btn btn-sm btn-light"><i class="bi bi-paperclip"></i></button>
                <button class="btn btn-sm btn-light"><i class="bi bi-emoji-smile"></i></button>
                <input type="text" class="form-control" placeholder="Type a message...">
                <button class="btn btn-sm btn-primary"><i class="bi bi-send-fill"></i></button>
            </div>
        </div>

        <!-- Right: Chat Details -->
        <div class="msg-details">
            <div class="details-header">
                <img src="https://ui-avatars.com/api/?name=John+Doe&background=667eea&color=fff" alt="John Doe" class="details-avatar">
                <h4>John Doe</h4>
                <p>Store Manager - Auckland</p>
            </div>

            <div class="details-section">
                <h5><i class="bi bi-lightning-fill"></i> Quick Actions</h5>
                <button class="btn btn-sm btn-light btn-block mb-2">
                    <i class="bi bi-telephone"></i> Call
                </button>
                <button class="btn btn-sm btn-light btn-block mb-2">
                    <i class="bi bi-camera-video"></i> Video Call
                </button>
                <button class="btn btn-sm btn-light btn-block mb-2">
                    <i class="bi bi-bell-slash"></i> Mute
                </button>
                <button class="btn btn-sm btn-light btn-block mb-2">
                    <i class="bi bi-search"></i> Search in Chat
                </button>
            </div>

            <div class="details-section">
                <h5><i class="bi bi-people-fill"></i> Group Members (8)</h5>
                <div class="user-list">
                    <div class="user-item">
                        <img src="https://ui-avatars.com/api/?name=Sarah+Smith&background=10b981&color=fff" alt="Sarah">
                        <div class="user-info">
                            <div class="user-name">Sarah Smith</div>
                            <div class="user-role">Admin</div>
                        </div>
                        <span class="status-online"></span>
                    </div>
                    <div class="user-item">
                        <img src="https://ui-avatars.com/api/?name=Mike+Johnson&background=f59e0b&color=fff" alt="Mike">
                        <div class="user-info">
                            <div class="user-name">Mike Johnson</div>
                            <div class="user-role">Manager</div>
                        </div>
                        <span class="status-online"></span>
                    </div>
                    <div class="user-item">
                        <img src="https://ui-avatars.com/api/?name=Lisa+Wong&background=8b5cf6&color=fff" alt="Lisa">
                        <div class="user-info">
                            <div class="user-name">Lisa Wong</div>
                            <div class="user-role">Staff</div>
                        </div>
                        <span class="status-away"></span>
                    </div>
                    <div class="user-item">
                        <img src="https://ui-avatars.com/api/?name=Tom+Brown&background=ef4444&color=fff" alt="Tom">
                        <div class="user-info">
                            <div class="user-name">Tom Brown</div>
                            <div class="user-role">Staff</div>
                        </div>
                        <span class="status-offline"></span>
                    </div>
                    <button class="btn btn-sm btn-light btn-block mt-2">
                        <i class="bi bi-plus-circle"></i> Add People
                    </button>
                </div>
            </div>

            <div class="details-section">
                <h5><i class="bi bi-folder-fill"></i> Shared Files (12)</h5>
                <div class="file-list">
                    <div class="file-item">
                        <i class="bi bi-file-pdf-fill text-danger"></i>
                        <div class="file-info">
                            <div class="file-name">Inventory Report.pdf</div>
                            <div class="file-size">2.4 MB</div>
                        </div>
                        <button class="btn btn-xs btn-light"><i class="bi bi-download"></i></button>
                    </div>
                    <div class="file-item">
                        <i class="bi bi-file-earmark-excel-fill text-success"></i>
                        <div class="file-info">
                            <div class="file-name">Sales Data.xlsx</div>
                            <div class="file-size">1.8 MB</div>
                        </div>
                        <button class="btn btn-xs btn-light"><i class="bi bi-download"></i></button>
                    </div>
                    <div class="file-item">
                        <i class="bi bi-file-earmark-image-fill text-primary"></i>
                        <div class="file-info">
                            <div class="file-name">Store Photo.jpg</div>
                            <div class="file-size">856 KB</div>
                        </div>
                        <button class="btn btn-xs btn-light"><i class="bi bi-download"></i></button>
                    </div>
                    <button class="btn btn-sm btn-light btn-block mt-2">
                        <i class="bi bi-folder-plus"></i> Browse All Files
                    </button>
                </div>
            </div>

            <div class="details-section">
                <h5><i class="bi bi-gear-fill"></i> Settings</h5>
                <button class="btn btn-sm btn-light btn-block mb-2">
                    <i class="bi bi-image"></i> Change Background
                </button>
                <button class="btn btn-sm btn-light btn-block mb-2">
                    <i class="bi bi-palette"></i> Theme
                </button>
                <button class="btn btn-sm btn-danger btn-block">
                    <i class="bi bi-box-arrow-right"></i> Leave Chat
                </button>
            </div>
        </div>
    </div>
</div>

<style>
/* ===================================
   CSS CUSTOM PROPERTIES (Variables)
   =================================== */
:root {
    --msg-sidebar-width: 320px;
    --msg-sidebar-collapsed: 60px;
    --msg-details-width: 280px;
    --msg-transition-speed: 0.3s;
    --msg-transition-timing: cubic-bezier(0.4, 0, 0.2, 1);
    --msg-primary-color: #667eea;
    --msg-danger-color: #ef4444;
    --msg-success-color: #10b981;
    --msg-warning-color: #f59e0b;
    --msg-border-color: #e5e7eb;
    --msg-bg-hover: #f9fafb;
    --msg-text-primary: #1f2937;
    --msg-text-secondary: #6b7280;
}

/* ===================================
   ACCESSIBILITY - REDUCED MOTION
   =================================== */
@media (prefers-reduced-motion: reduce) {
    * {
        animation-duration: 0.01ms !important;
        animation-iteration-count: 1 !important;
        transition-duration: 0.01ms !important;
    }
}

/* ===================================
   SCREEN READER ONLY UTILITY
   =================================== */
.sr-only {
    position: absolute;
    width: 1px;
    height: 1px;
    padding: 0;
    margin: -1px;
    overflow: hidden;
    clip: rect(0, 0, 0, 0);
    white-space: nowrap;
    border-width: 0;
}

/* ===================================
   MESSAGING CENTER STYLES
   =================================== */
.messaging-center-app {
    display: flex;
    flex-direction: column;
    height: 100%;
    min-height: 0;
    background: white;
    overflow: hidden;
}

/* Header */
.messaging-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 24px;
    border-bottom: 2px solid #e5e7eb;
    background: white;
}

.messaging-title {
    margin: 0;
    font-size: 24px;
    font-weight: 700;
    display: flex;
    align-items: center;
    gap: 12px;
}

.messaging-title i {
    color: #667eea;
}

.header-right {
    display: flex;
    align-items: center;
    gap: 12px;
}

.layout-label {
    font-size: 13px;
    color: #6b7280;
    font-weight: 500;
}

.layout-buttons {
    display: flex;
    border: 1px solid #d1d5db;
    border-radius: 6px;
    overflow: hidden;
}

.layout-btn {
    padding: 8px 16px;
    background: white;
    border: none;
    border-right: 1px solid #d1d5db;
    color: #6b7280;
    font-size: 13px;
    cursor: pointer;
    transition: all 0.2s;
    display: flex;
    align-items: center;
    gap: 6px;
}

.layout-btn:last-child {
    border-right: none;
}

.layout-btn:hover {
    background: #f9fafb;
}

.layout-btn.active {
    background: #667eea;
    color: white;
}

/* Tabs */
.messaging-tabs {
    display: flex;
    gap: 4px;
    padding: 0 24px;
    border-bottom: 1px solid #e5e7eb;
    background: white;
}

.msg-tab {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px;
    color: #6b7280;
    text-decoration: none;
    border-bottom: 3px solid transparent;
    transition: all 0.2s;
}

.msg-tab:hover {
    color: #667eea;
    background: #f9fafb;
}

.msg-tab.active {
    color: #667eea;
    border-bottom-color: #667eea;
}

/* Content Layout */
.messaging-content {
    flex: 1;
    display: grid;
    min-height: 0;
    height: 100%;
    transition: grid-template-columns 0.3s ease;
}

/* Layout Modes - Messaging content is always full 3-column */

/* Full: Messaging always shows all 3 columns (conversations + chat + details) */
.messaging-content.layout-fullwidth {
    grid-template-columns: 320px 1fr 280px;
}

/* Standard: Messaging always shows all 3 columns (conversations + chat + details) */
.messaging-content.layout-standard {
    grid-template-columns: 320px 1fr 280px;
}

/* Compact: Messaging always shows all 3 columns (conversations + chat + details) */
.messaging-content.layout-compact {
    grid-template-columns: 320px 1fr 280px;
}

/* Conversations */
.msg-conversations {
    border-right: 1px solid #e5e7eb;
    display: flex;
    flex-direction: column;
    background: #f9fafb;
    min-height: 0;
    overflow: hidden;
}

.conversations-header {
    padding: 16px;
    display: flex;
    gap: 8px;
    border-bottom: 1px solid #e5e7eb;
    background: white;
}

.conversations-list {
    flex: 1;
    overflow-y: auto;
    min-height: 0;
}

.conversation-item {
    display: flex;
    gap: 12px;
    padding: 12px 16px;
    cursor: pointer;
    transition: background 0.2s;
    border-bottom: 1px solid #e5e7eb;
    background: white;
}

.conversation-item:hover {
    background: #f3f4f6;
}

.conversation-item.active {
    background: #ede9fe;
    border-left: 3px solid #667eea;
}

.conv-avatar {
    position: relative;
    flex-shrink: 0;
}

.conv-avatar img {
    width: 48px;
    height: 48px;
    border-radius: 50%;
}

.status-online {
    position: absolute;
    bottom: 2px;
    right: 2px;
    width: 12px;
    height: 12px;
    background: #10b981;
    border: 2px solid white;
    border-radius: 50%;
}

.conv-info {
    flex: 1;
    min-width: 0;
}

.conv-name {
    font-weight: 600;
    font-size: 14px;
    color: #1f2937;
    margin-bottom: 4px;
}

.conv-preview {
    font-size: 13px;
    color: #6b7280;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.conv-meta {
    display: flex;
    flex-direction: column;
    align-items: flex-end;
    gap: 4px;
}

.conv-time {
    font-size: 12px;
    color: #9ca3af;
}

/* Chat Window */
.msg-chat {
    display: flex;
    flex-direction: column;
    background: white;
    min-height: 0;
    overflow: hidden;
}

.chat-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 24px;
    border-bottom: 1px solid #e5e7eb;
}

.chat-user {
    display: flex;
    align-items: center;
    gap: 12px;
}

.chat-user img {
    width: 40px;
    height: 40px;
    border-radius: 50%;
}

.chat-user-name {
    font-weight: 600;
    font-size: 16px;
}

.chat-user-status {
    font-size: 13px;
    color: #10b981;
}

.chat-actions {
    display: flex;
    gap: 8px;
}

.chat-messages {
    flex: 1;
    overflow-y: auto;
    padding: 24px;
    background: #f9fafb;
    min-height: 0;
}

.message {
    display: flex;
    gap: 12px;
    margin-bottom: 16px;
}

.message.sent {
    justify-content: flex-end;
}

.message.sent .message-content {
    background: #667eea;
    color: white;
}

.message-avatar img {
    width: 36px;
    height: 36px;
    border-radius: 50%;
}

.message-content {
    max-width: 60%;
}

.message-text {
    padding: 12px 16px;
    background: white;
    border-radius: 12px;
    font-size: 14px;
}

.message-time {
    font-size: 11px;
    color: #9ca3af;
    margin-top: 4px;
    padding: 0 8px;
}

.chat-input {
    display: flex;
    gap: 8px;
    padding: 16px 24px;
    border-top: 1px solid #e5e7eb;
    background: white;
}

.chat-input .form-control {
    flex: 1;
}

/* Details Sidebar */
.msg-details {
    border-left: 1px solid #e5e7eb;
    padding: 24px;
    overflow-y: auto;
    background: #f9fafb;
    min-height: 0;
}

.details-header {
    text-align: center;
    margin-bottom: 24px;
}

.details-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    margin-bottom: 12px;
}

.details-header h4 {
    margin: 0 0 4px 0;
    font-size: 18px;
}

.details-header p {
    margin: 0;
    color: #6b7280;
    font-size: 14px;
}

.details-section {
    margin-bottom: 24px;
}

.details-section h5 {
    font-size: 14px;
    font-weight: 600;
    margin-bottom: 12px;
    color: #1f2937;
    display: flex;
    align-items: center;
    gap: 6px;
}

.details-section h5 i {
    color: #667eea;
    font-size: 16px;
}

/* User List */
.user-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.user-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 8px;
    border-radius: 8px;
    transition: background 0.2s;
    cursor: pointer;
}

.user-item:hover {
    background: #f3f4f6;
}

.user-item img {
    width: 36px;
    height: 36px;
    border-radius: 50%;
}

.user-info {
    flex: 1;
    min-width: 0;
}

.user-name {
    font-size: 13px;
    font-weight: 600;
    color: #1f2937;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.user-role {
    font-size: 11px;
    color: #6b7280;
}

.status-online {
    width: 10px;
    height: 10px;
    background: #10b981;
    border-radius: 50%;
    border: 2px solid white;
}

.status-away {
    width: 10px;
    height: 10px;
    background: #f59e0b;
    border-radius: 50%;
    border: 2px solid white;
}

.status-offline {
    width: 10px;
    height: 10px;
    background: #9ca3af;
    border-radius: 50%;
    border: 2px solid white;
}

/* File List */
.file-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.file-item {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px;
    border-radius: 8px;
    background: #f9fafb;
    border: 1px solid #e5e7eb;
    transition: all 0.2s;
}

.file-item:hover {
    background: #f3f4f6;
    border-color: #d1d5db;
}

.file-item i {
    font-size: 24px;
}

.file-info {
    flex: 1;
    min-width: 0;
}

.file-name {
    font-size: 12px;
    font-weight: 600;
    color: #1f2937;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}

.file-size {
    font-size: 11px;
    color: #6b7280;
}

.btn-xs {
    padding: 4px 8px;
    font-size: 12px;
}

/* ===================================
   RESPONSIVE BREAKPOINTS
   =================================== */

/* Tablet and below (1200px) - Hide messaging right details panel */
@media (max-width: 1200px) {
    .messaging-content {
        grid-template-columns: 320px 1fr !important;
    }
    
    .msg-details {
        display: none;
    }
}

/* Mobile (768px) - Stack vertically or show only chat */
@media (max-width: 768px) {
    .messaging-header {
        flex-direction: column;
        gap: 12px;
        padding: 12px;
    }
    
    .header-right {
        width: 100%;
        justify-content: space-between;
    }
    
    .messaging-tabs {
        overflow-x: auto;
        padding: 8px;
    }
    
    .msg-tab {
        flex-shrink: 0;
        padding: 8px 12px;
        font-size: 13px;
    }
    
    /* Show only active conversation or chat */
    .messaging-content {
        grid-template-columns: 1fr !important;
    }
    
    .msg-conversations {
        display: none; /* Hidden by default on mobile */
    }
    
    /* Show conversations when no active chat */
    .messaging-content.show-conversations .msg-conversations {
        display: flex;
    }
    
    .messaging-content.show-conversations .msg-chat {
        display: none;
    }
}

/* Small mobile (480px) */
@media (max-width: 480px) {
    .layout-buttons {
        flex-direction: column;
        width: 100%;
    }
    
    .layout-btn {
        width: 100%;
        justify-content: center;
    }
    
    .messaging-title {
        font-size: 18px;
    }
    
    .chat-header {
        padding: 12px;
    }
    
    .chat-messages {
        padding: 12px;
    }
}
</style>

<script>
/**
 * Messaging Layout Controller
 * Handles dynamic layout switching with accessibility and performance optimizations
 *
 * @version 2.0
 * @author CIS Development Team
 */
'use strict';

console.log('MessagingApp script loading...');

const MessagingLayout = {
    // State
    currentLayout: '<?php echo $layoutMode; ?>',

    // DOM References (cached)
    appGrid: null,
    appSidebar: null,
    appSidebarRight: null,
    layoutButtons: null,
    layoutStatusAnnouncer: null,

    // Event handlers storage (for cleanup)
    eventHandlers: new Map(),

    /**
     * Initialize the messaging layout controller
     */
    init() {
        console.log('=== MessagingLayout Init ===');

        try {
            // Cache DOM elements
            this.cacheDOMElements();

            // Validate required elements
            if (!this.validateElements()) {
                throw new Error('Required DOM elements not found');
            }

            // Setup event listeners
            this.setupButtons();
            this.setupKeyboardShortcuts();

            // Apply initial layout
            this.applyLayout(this.currentLayout);

            // Load saved preferences
            this.loadPreferences();

            console.log('MessagingLayout initialization complete');
        } catch (error) {
            console.error('Failed to initialize MessagingLayout:', error);
            this.showError('Layout controller failed to initialize. Please refresh the page.');
        }
    },

    /**
     * Cache DOM element references
     */
    cacheDOMElements() {
        this.appGrid = document.querySelector('.app-grid');
        this.appSidebar = document.getElementById('app-sidebar');
        this.appSidebarRight = document.getElementById('app-sidebar-right');
        this.layoutButtons = document.querySelectorAll('.layout-btn');
        this.layoutStatusAnnouncer = document.getElementById('layout-status');

        console.log('DOM Elements cached:', {
            appGrid: !!this.appGrid,
            appSidebar: !!this.appSidebar,
            appSidebarRight: !!this.appSidebarRight,
            layoutButtons: this.layoutButtons.length,
            layoutStatusAnnouncer: !!this.layoutStatusAnnouncer
        });
    },

    /**
     * Validate that required elements exist
     * @returns {boolean}
     */
    validateElements() {
        if (!this.appGrid) {
            console.error('ERROR: .app-grid not found! Template may not be loaded correctly.');
            return false;
        }

        if (this.layoutButtons.length === 0) {
            console.error('ERROR: No layout buttons found!');
            return false;
        }

        return true;
    },

    /**
     * Setup button click handlers
     */
    setupButtons() {
        this.layoutButtons.forEach(btn => {
            const handler = (e) => {
                e.preventDefault();
                e.stopPropagation();

                const layout = btn.getAttribute('data-layout');
                console.log('Button clicked:', layout);

                if (layout) {
                    this.switchLayout(layout);
                }
            };

            btn.addEventListener('click', handler);
            this.eventHandlers.set(btn, handler);
        });

        console.log('Layout buttons setup complete');
    },

    /**
     * Setup keyboard shortcuts
     * Ctrl+1 = Full, Ctrl+2 = Standard, Ctrl+3 = Compact
     */
    setupKeyboardShortcuts() {
        const keyHandler = (e) => {
            // Check for Ctrl+1, Ctrl+2, or Ctrl+3
            if ((e.ctrlKey || e.metaKey) && ['1', '2', '3'].includes(e.key)) {
                e.preventDefault();

                const layouts = ['fullwidth', 'standard', 'compact'];
                const layoutIndex = parseInt(e.key) - 1;
                const layout = layouts[layoutIndex];

                if (layout) {
                    console.log('Keyboard shortcut triggered:', e.key, '→', layout);
                    this.switchLayout(layout);
                }
            }
        };

        document.addEventListener('keydown', keyHandler);
        this.eventHandlers.set('keyboard', keyHandler);

        console.log('Keyboard shortcuts enabled: Ctrl+1/2/3');
    },    switchLayout(newLayout) {
        console.log(`\n=== Switching from ${this.currentLayout} to ${newLayout} ===`);

        this.currentLayout = newLayout;

        // Update button states
        this.layoutButtons.forEach(btn => {
            const btnLayout = btn.getAttribute('data-layout');
            if (btnLayout === newLayout) {
                btn.classList.add('active');
            } else {
                btn.classList.remove('active');
            }
        });

        // Apply the layout
        this.applyLayout(newLayout);

        // Save to localStorage
        localStorage.setItem('messagingLayout', newLayout);
    },

    applyLayout(layout) {
        console.log('Applying layout:', layout);

        if (!this.appGrid) {
            console.error('Cannot apply layout: appGrid not found');
            return;
        }

        // Get computed style to check current values
        const currentGrid = window.getComputedStyle(this.appGrid).gridTemplateColumns;
        console.log('Current grid columns:', currentGrid);

        switch (layout) {
            case 'fullwidth':
                this.applyFullLayout();
                break;
            case 'standard':
                this.applyStandardLayout();
                break;
            case 'compact':
                this.applyCompactLayout();
                break;
            default:
                console.warn('Unknown layout:', layout);
        }

        // Log new grid
        setTimeout(() => {
            const newGrid = window.getComputedStyle(this.appGrid).gridTemplateColumns;
            console.log('New grid columns:', newGrid);
        }, 100);
    },

    applyFullLayout() {
        console.log('Applying FULL layout...');

        // Grid: 60px (collapsed sidebar) | 1fr (main content) | 0 (no right sidebar)
        if (this.appGrid) {
            this.appGrid.style.gridTemplateColumns = '60px 1fr 0';
            this.appGrid.style.gridTemplateAreas = '"header header header" "sidebar main main" "footer footer footer"';
        }

        // Collapse left sidebar to vertical bar with first letters
        if (this.appSidebar) {
            this.appSidebar.classList.add('sidebar-collapsed');
            this.appSidebar.style.overflowX = 'hidden';
            this.appSidebar.style.overflowY = 'auto';
            this.appSidebar.style.width = '60px';

            // Transform section titles to show first letter only
            const sectionTitles = this.appSidebar.querySelectorAll('.nav-section-title');
            sectionTitles.forEach(title => {
                const fullText = title.textContent.trim();
                const firstLetter = fullText.charAt(0).toUpperCase();

                title.setAttribute('data-full-text', fullText);
                title.style.display = 'block';
                title.style.fontSize = '20px';
                title.style.fontWeight = '700';
                title.style.textAlign = 'center';
                title.style.padding = '16px 0 8px 0';
                title.style.margin = '0';
                title.style.color = 'var(--text-2, #6b7280)';
                title.textContent = firstLetter;
                title.setAttribute('title', fullText);
            });

            // Hide sidebar header spans
            const headerSpans = this.appSidebar.querySelectorAll('.sidebar-header span');
            headerSpans.forEach(el => {
                el.style.display = 'none';
            });

            // Transform nav items to show ONLY icons (no letters)
            const navItems = this.appSidebar.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                item.style.display = 'flex';
                item.style.flexDirection = 'column';
                item.style.alignItems = 'center';
                item.style.justifyContent = 'center';
                item.style.padding = '10px 4px';
                item.style.textAlign = 'center';
                item.style.minHeight = '48px';

                // Hide the text span completely
                const span = item.querySelector('span:not(.badge)');
                if (span) {
                    const fullText = span.textContent.trim();
                    span.setAttribute('data-full-text', fullText);
                    span.style.display = 'none'; // HIDE TEXT COMPLETELY

                    // Add tooltip on hover
                    item.setAttribute('title', fullText);
                }

                // Show only icon
                const icon = item.querySelector('i');
                if (icon) {
                    icon.style.fontSize = '20px';
                    icon.style.marginBottom = '0';
                }

                // Keep badges visible but smaller
                const badge = item.querySelector('.badge');
                if (badge) {
                    badge.style.fontSize = '10px';
                    badge.style.minWidth = '18px';
                    badge.style.height = '18px';
                    badge.style.lineHeight = '18px';
                }
            });
        }

        // Hide right sidebar
        if (this.appSidebarRight) {
            this.appSidebarRight.style.display = 'none';
        }

        console.log('FULL layout applied - vertical bar with first letters');
    },

    applyStandardLayout() {
        console.log('Applying STANDARD layout...');

        // Grid: 240px (full sidebar) | 1fr (main content) | 0 (no right sidebar)
        if (this.appGrid) {
            this.appGrid.style.gridTemplateColumns = '240px 1fr 0';
            this.appGrid.style.gridTemplateAreas = '"header header header" "sidebar main main" "footer footer footer"';
        }

        // Show full left sidebar
        if (this.appSidebar) {
            this.appSidebar.classList.remove('sidebar-collapsed');
            this.appSidebar.style.overflowX = '';
            this.appSidebar.style.overflowY = '';
            this.appSidebar.style.width = ''; // Reset width

            // Restore section titles to full text
            const sectionTitles = this.appSidebar.querySelectorAll('.nav-section-title');
            sectionTitles.forEach(title => {
                const originalText = title.getAttribute('data-full-text');
                if (originalText) {
                    title.textContent = originalText;
                }
                title.style.display = '';
                title.style.fontSize = '';
                title.style.fontWeight = '';
                title.style.textAlign = '';
                title.style.padding = '';
                title.style.margin = '';
                title.style.color = '';
                title.removeAttribute('title');
            });

            // Show sidebar header spans
            const headerSpans = this.appSidebar.querySelectorAll('.sidebar-header span');
            headerSpans.forEach(el => {
                el.style.display = '';
            });

            // Restore nav items to full text
            const navItems = this.appSidebar.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                item.style.display = '';
                item.style.flexDirection = '';
                item.style.alignItems = '';
                item.style.justifyContent = '';
                item.style.padding = '';
                item.style.textAlign = '';
                item.style.minHeight = '';
                item.removeAttribute('title');

                const span = item.querySelector('span:not(.badge)');
                if (span) {
                    const originalText = span.getAttribute('data-full-text');
                    if (originalText) {
                        span.textContent = originalText;
                    }
                    span.style.display = '';
                    span.style.fontSize = '';
                    span.style.fontWeight = '';
                    span.style.lineHeight = '';
                }

                const icon = item.querySelector('i');
                if (icon) {
                    icon.style.fontSize = '';
                    icon.style.marginBottom = '';
                }

                const badge = item.querySelector('.badge');
                if (badge) {
                    badge.style.fontSize = '';
                    badge.style.minWidth = '';
                    badge.style.height = '';
                    badge.style.lineHeight = '';
                }
            });
        }

        // Hide right sidebar
        if (this.appSidebarRight) {
            this.appSidebarRight.style.display = 'none';
        }

        console.log('STANDARD layout applied');
    },

    applyCompactLayout() {
        console.log('Applying COMPACT layout...');

        // Grid: 240px (full sidebar) | 1fr (main content) | 280px (right sidebar)
        // Note: Right sidebar will auto-hide at 1200px via CSS media queries
        if (this.appGrid) {
            this.appGrid.style.gridTemplateColumns = '240px 1fr 280px';
            this.appGrid.style.gridTemplateAreas = '"header header header" "sidebar main right" "footer footer footer"';
        }

        // Show full left sidebar
        if (this.appSidebar) {
            this.appSidebar.classList.remove('sidebar-collapsed');
            this.appSidebar.style.overflowX = '';
            this.appSidebar.style.overflowY = '';
            this.appSidebar.style.width = ''; // Reset width

            // Restore section titles to full text
            const sectionTitles = this.appSidebar.querySelectorAll('.nav-section-title');
            sectionTitles.forEach(title => {
                const originalText = title.getAttribute('data-full-text');
                if (originalText) {
                    title.textContent = originalText;
                }
                title.style.display = '';
                title.style.fontSize = '';
                title.style.fontWeight = '';
                title.style.textAlign = '';
                title.style.padding = '';
                title.style.margin = '';
                title.style.color = '';
                title.removeAttribute('title');
            });

            // Show sidebar header spans
            const headerSpans = this.appSidebar.querySelectorAll('.sidebar-header span');
            headerSpans.forEach(el => {
                el.style.display = '';
            });

            // Show all text elements
            const textElements = this.appSidebar.querySelectorAll('.nav-section-title, .sidebar-header span');
            textElements.forEach(el => {
                el.style.display = '';
            });

            // Restore nav items to full text
            const navItems = this.appSidebar.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                item.style.display = '';
                item.style.flexDirection = '';
                item.style.alignItems = '';
                item.style.justifyContent = '';
                item.style.padding = '';
                item.style.textAlign = '';
                item.style.minHeight = '';
                item.removeAttribute('title');

                const span = item.querySelector('span:not(.badge)');
                if (span) {
                    const originalText = span.getAttribute('data-full-text');
                    if (originalText) {
                        span.textContent = originalText;
                    }
                    span.style.display = '';
                    span.style.fontSize = '';
                    span.style.fontWeight = '';
                    span.style.lineHeight = '';
                }

                const icon = item.querySelector('i');
                if (icon) {
                    icon.style.fontSize = '';
                    icon.style.marginBottom = '';
                }
            });
        }

        // Show right sidebar - but let CSS media queries handle responsive hiding
        if (this.appSidebarRight) {
            // Remove inline display style to allow CSS media queries to work
            this.appSidebarRight.style.display = '';
        }

        console.log('COMPACT layout applied - right sidebar responsive behavior enabled');
    }
};

// Global function for inline onclick handlers
function switchLayout(layout) {
    console.log('switchLayout() called with:', layout);
    MessagingLayout.switchLayout(layout);
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM Content Loaded');
    MessagingLayout.init();
    initConversationHandlers();
});

// Also initialize immediately if DOM is already loaded
if (document.readyState === 'loading') {
    console.log('Document still loading...');
} else {
    console.log('Document already loaded, initializing immediately');
    // Small delay to ensure template is fully rendered
    setTimeout(() => {
        MessagingLayout.init();
        initConversationHandlers();
    }, 100);
}

/**
 * Initialize conversation click handlers
 */
function initConversationHandlers() {
    console.log('Initializing conversation handlers...');
    
    const conversationItems = document.querySelectorAll('.conversation-item');
    console.log('Found', conversationItems.length, 'conversations');
    
    conversationItems.forEach((item, index) => {
        item.addEventListener('click', function(e) {
            console.log('Conversation clicked:', index);
            
            // Remove active class from all
            conversationItems.forEach(conv => conv.classList.remove('active'));
            
            // Add active class to clicked item
            this.classList.add('active');
            
            // Update chat header with conversation info
            const userName = this.querySelector('.conv-name')?.textContent || 'User';
            const chatUserName = document.querySelector('.chat-user-name');
            if (chatUserName) {
                chatUserName.textContent = userName;
            }
            
            console.log('Activated conversation:', userName);
        });
    });
}
</script>
