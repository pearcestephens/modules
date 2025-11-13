<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Team Chat & Messaging - Professional Dark Theme</title>
    <?php echo $theme->styles(); ?>
</head>
<body>
    <header class="cis-header">
        <a href="#" class="cis-logo">🛍️ CIS Dashboard</a>
        <nav class="cis-nav">
            <a href="?layout=facebook-feed" class="cis-nav-link">Feed</a>
            <a href="?layout=card-grid" class="cis-nav-link">Products</a>
            <a href="?layout=store-outlet" class="cis-nav-link">Stores</a>
            <a href="?layout=messaging" class="cis-nav-link active">Messages</a>
        </nav>
        <div class="flex flex-center gap-2">
            <!-- Notification Bell -->
            <div class="cis-notification-bell" id="notificationBell">
                <span style="font-size: 20px;">🔔</span>
                <?php if ($unreadNotifications > 0): ?>
                <span class="cis-notification-badge"><?php echo $unreadNotifications; ?></span>
                <?php endif; ?>
            </div>

            <span class="text-small text-muted"><?php echo date('l, F j Y'); ?></span>
        </div>
    </header>

    <div class="cis-container" style="padding-top: 20px; max-width: 100%;">
        <!-- Chat Container -->
        <div class="cis-chat-container">
            <!-- Sidebar: Chat Rooms & Direct Messages -->
            <div class="cis-chat-sidebar">
                <!-- Tabs: Rooms vs Direct Messages -->
                <div class="cis-chat-tabs">
                    <button class="cis-chat-tab active" data-tab="rooms">
                        Chat Rooms (<?php echo count($chatRooms); ?>)
                    </button>
                    <button class="cis-chat-tab" data-tab="messages">
                        Direct Messages (<?php echo $unreadMessages; ?>)
                    </button>
                </div>

                <!-- Search -->
                <div class="cis-chat-search">
                    <input type="text" placeholder="🔍 Search conversations...">
                </div>

                <!-- Chat Rooms List -->
                <div class="cis-chat-list" id="roomsList">
                    <?php foreach ($chatRooms as $room): ?>
                    <div class="cis-chat-room <?php echo $room['id'] == 1 ? 'active' : ''; ?>"
                         data-room-id="<?php echo $room['id']; ?>"
                         data-room-name="<?php echo $room['name']; ?>">
                        <div class="cis-chat-room-icon">
                            <?php echo $room['icon']; ?>
                            <?php if ($room['unread_count'] > 0): ?>
                            <span class="cis-chat-room-badge"><?php echo $room['unread_count']; ?></span>
                            <?php endif; ?>
                        </div>
                        <div class="cis-chat-room-info">
                            <div class="cis-chat-room-name">
                                <?php echo $room['name']; ?>
                                <?php if ($room['pinned']): ?>
                                <span style="color: var(--color-warning);">📌</span>
                                <?php endif; ?>
                                <span class="cis-chat-room-online">
                                    <?php echo $room['online_count']; ?> online
                                </span>
                            </div>
                            <div class="cis-chat-room-preview">
                                <?php echo $room['last_message_from']; ?>: <?php echo $room['last_message']; ?>
                            </div>
                        </div>
                        <div class="cis-chat-room-time">
                            <?php echo $room['last_message_time']; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Direct Messages List (hidden by default) -->
                <div class="cis-chat-list" id="messagesList" style="display: none;">
                    <?php foreach ($directMessages as $message): ?>
                    <div class="cis-chat-room" data-user-id="<?php echo $message['id']; ?>">
                        <div class="cis-chat-room-icon">
                            <div class="cis-user-avatar">
                                <?php echo $message['from_avatar']; ?>
                                <span class="cis-user-status <?php echo $message['online'] ? 'online' : 'offline'; ?>"></span>
                            </div>
                        </div>
                        <div class="cis-chat-room-info">
                            <div class="cis-chat-room-name">
                                <?php echo $message['from_user']; ?>
                                <?php if (!$message['read']): ?>
                                <span class="cis-badge cis-badge-primary" style="padding: 2px 6px; font-size: 9px;">NEW</span>
                                <?php endif; ?>
                            </div>
                            <div class="cis-chat-room-preview">
                                <?php echo $message['message']; ?>
                            </div>
                        </div>
                        <div class="cis-chat-room-time">
                            <?php echo $message['time']; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>

                <!-- Online Users (bottom section) -->
                <div style="margin-top: auto; border-top: 1px solid var(--color-border);">
                    <div style="padding: 12px 16px; font-weight: 600; font-size: 12px; color: var(--color-text-secondary); text-transform: uppercase;">
                        Online Now (<?php echo count(array_filter($onlineUsers, function($u) { return $u['status'] === 'online'; })); ?>)
                    </div>
                    <div class="cis-online-users">
                        <?php foreach (array_slice($onlineUsers, 0, 3) as $user): ?>
                        <div class="cis-user-item">
                            <div class="cis-user-avatar">
                                <?php echo $user['avatar']; ?>
                                <span class="cis-user-status <?php echo $user['status']; ?>"></span>
                            </div>
                            <div class="cis-user-info">
                                <div class="cis-user-name"><?php echo $user['name']; ?></div>
                                <div class="cis-user-status-text"><?php echo $user['status_message']; ?></div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- Main Chat Area -->
            <div class="cis-chat-main">
                <!-- Chat Header -->
                <div class="cis-chat-header">
                    <div class="cis-chat-header-info">
                        <h3 id="chatRoomName">👥 All Staff</h3>
                        <div class="cis-chat-header-meta">
                            <span id="chatRoomMembers">47 members</span> •
                            <span id="chatRoomOnline" style="color: var(--color-success);">12 online</span>
                        </div>
                    </div>
                    <div class="flex gap-2">
                        <button class="cis-btn cis-btn-secondary" style="padding: 8px 12px; font-size: 13px;">
                            🔍 Search
                        </button>
                        <button class="cis-btn cis-btn-secondary" style="padding: 8px 12px; font-size: 13px;">
                            ⚙️ Settings
                        </button>
                    </div>
                </div>

                <!-- Chat Messages -->
                <div class="cis-chat-messages" id="chatMessages">
                    <?php foreach ($chatMessages as $msg): ?>
                    <div class="cis-chat-message">
                        <div class="cis-chat-message-avatar"><?php echo $msg['avatar']; ?></div>
                        <div class="cis-chat-message-content">
                            <div class="cis-chat-message-header">
                                <span class="cis-chat-message-author"><?php echo $msg['user']; ?></span>
                                <span class="cis-chat-message-role"><?php echo $msg['role']; ?></span>
                                <span class="cis-chat-message-time"><?php echo $msg['time']; ?></span>
                            </div>
                            <div class="cis-chat-message-bubble">
                                <?php echo $msg['message']; ?>
                            </div>
                            <?php if (!empty($msg['reactions'])): ?>
                            <div class="cis-chat-message-reactions">
                                <?php foreach ($msg['reactions'] as $emoji => $count): ?>
                                <div class="cis-chat-reaction">
                                    <span><?php echo $emoji; ?></span>
                                    <span><?php echo $count; ?></span>
                                </div>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>

                    <!-- Typing Indicator -->
                    <div class="cis-chat-message" id="typingIndicator" style="display: none;">
                        <div class="cis-chat-message-avatar">...</div>
                        <div class="cis-chat-message-content">
                            <div class="cis-chat-message-bubble" style="opacity: 0.6;">
                                <span class="cis-loading"></span>
                                <span style="margin-left: 8px;">Someone is typing...</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Chat Input -->
                <div class="cis-chat-input-area">
                    <div class="cis-chat-input-wrapper">
                        <textarea
                            class="cis-chat-input"
                            placeholder="Type your message..."
                            rows="1"
                            id="chatInput"></textarea>
                        <button class="cis-chat-send" id="sendMessage">
                            ➤
                        </button>
                    </div>
                    <div style="margin-top: 8px; font-size: 11px; color: var(--color-text-secondary); display: flex; gap: 12px;">
                        <button style="background: none; border: none; color: inherit; cursor: pointer;">📎 Attach</button>
                        <button style="background: none; border: none; color: inherit; cursor: pointer;">😊 Emoji</button>
                        <button style="background: none; border: none; color: inherit; cursor: pointer;">📷 Image</button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notification Panel (slides in from right) -->
    <div class="cis-notification-panel" id="notificationPanel">
        <div class="cis-notification-header">
            <div class="cis-notification-title">🔔 Notifications</div>
            <button class="cis-notification-close" id="closeNotifications">×</button>
        </div>
        <div class="cis-notification-list">
            <?php foreach ($notifications as $notification): ?>
            <div class="cis-notification-item <?php echo !$notification['read'] ? 'unread' : ''; ?>"
                 data-notification-id="<?php echo $notification['id']; ?>">
                <div class="cis-notification-icon"><?php echo $notification['icon']; ?></div>
                <div class="cis-notification-content">
                    <div class="cis-notification-item-title"><?php echo $notification['title']; ?></div>
                    <div class="cis-notification-item-message"><?php echo $notification['message']; ?></div>
                    <div class="cis-notification-item-time"><?php echo $notification['time']; ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <script>
        // Chat functionality
        document.addEventListener('DOMContentLoaded', function() {
            const notificationBell = document.getElementById('notificationBell');
            const notificationPanel = document.getElementById('notificationPanel');
            const closeNotifications = document.getElementById('closeNotifications');
            const chatInput = document.getElementById('chatInput');
            const sendButton = document.getElementById('sendMessage');
            const chatMessages = document.getElementById('chatMessages');

            // Toggle notification panel
            notificationBell.addEventListener('click', function() {
                notificationPanel.classList.toggle('active');
            });

            closeNotifications.addEventListener('click', function() {
                notificationPanel.classList.remove('active');
            });

            // Chat tabs
            document.querySelectorAll('.cis-chat-tab').forEach(tab => {
                tab.addEventListener('click', function() {
                    document.querySelectorAll('.cis-chat-tab').forEach(t => t.classList.remove('active'));
                    this.classList.add('active');

                    const tabType = this.dataset.tab;
                    if (tabType === 'rooms') {
                        document.getElementById('roomsList').style.display = 'block';
                        document.getElementById('messagesList').style.display = 'none';
                    } else {
                        document.getElementById('roomsList').style.display = 'none';
                        document.getElementById('messagesList').style.display = 'block';
                    }
                });
            });

            // Send message
            function sendMessage() {
                const message = chatInput.value.trim();
                if (!message) return;

                // Add message to chat
                const messageHTML = `
                    <div class="cis-chat-message">
                        <div class="cis-chat-message-avatar">ME</div>
                        <div class="cis-chat-message-content">
                            <div class="cis-chat-message-header">
                                <span class="cis-chat-message-author">You</span>
                                <span class="cis-chat-message-role">Administrator</span>
                                <span class="cis-chat-message-time">Just now</span>
                            </div>
                            <div class="cis-chat-message-bubble">${message}</div>
                        </div>
                    </div>
                `;

                chatMessages.insertAdjacentHTML('beforeend', messageHTML);
                chatMessages.scrollTop = chatMessages.scrollHeight;
                chatInput.value = '';

                // Show typing indicator briefly
                setTimeout(() => {
                    document.getElementById('typingIndicator').style.display = 'flex';
                    chatMessages.scrollTop = chatMessages.scrollHeight;
                }, 1000);

                setTimeout(() => {
                    document.getElementById('typingIndicator').style.display = 'none';
                }, 3000);
            }

            sendButton.addEventListener('click', sendMessage);
            chatInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    sendMessage();
                }
            });

            // Auto-resize textarea
            chatInput.addEventListener('input', function() {
                this.style.height = 'auto';
                this.style.height = this.scrollHeight + 'px';
            });

            // Simulate real-time messages
            setInterval(() => {
                if (Math.random() > 0.7) {
                    const messages = [
                        'Great work on that last order!',
                        'Can someone check stock levels?',
                        'New shipment arriving tomorrow',
                        'POS system is back online',
                        'Customer just gave us 5 stars! 🌟'
                    ];

                    const names = ['Sarah', 'James', 'Emma'];
                    const name = names[Math.floor(Math.random() * names.length)];
                    const message = messages[Math.floor(Math.random() * messages.length)];

                    const notification = document.querySelector('.cis-notification-badge');
                    if (notification) {
                        const count = parseInt(notification.textContent) + 1;
                        notification.textContent = count;
                    }
                }
            }, 30000); // Check every 30 seconds
        });
    </script>

    <?php echo $theme->scripts(); ?>
</body>
</html>
