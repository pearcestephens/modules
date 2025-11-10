<?php
/**
 * CIS Facebook-Style Bottom Chat Bar Component
 * 
 * Displays a persistent bottom chat bar with online users and chat windows
 * Similar to Facebook Messenger bottom bar
 * 
 * @package CIS\Components
 * @version 1.0.0
 * 
 * Usage:
 *   // Enable chat
 *   include $_SERVER['DOCUMENT_ROOT'] . '/modules/base/_templates/components/chat-bar.php';
 * 
 *   // Or disable chat
 *   $CHAT_ENABLED = false;
 *   include $_SERVER['DOCUMENT_ROOT'] . '/modules/base/_templates/components/chat-bar.php';
 */

// Configuration
$CHAT_ENABLED = $CHAT_ENABLED ?? true; // Default: enabled
$CHAT_POSITION = $CHAT_POSITION ?? 'bottom'; // bottom or side
$CHAT_AUTO_LOAD_USERS = $CHAT_AUTO_LOAD_USERS ?? true; // Auto-fetch online users
$CHAT_WEBSOCKET_URL = $CHAT_WEBSOCKET_URL ?? null; // WebSocket URL for real-time
$CHAT_POLLING_INTERVAL = $CHAT_POLLING_INTERVAL ?? 5000; // Poll interval in ms (if no WebSocket)

// Early return if chat is disabled
if (!$CHAT_ENABLED) {
    // Still output the structure but hidden, for easy enabling later
    echo '<!-- CIS Chat Bar: Disabled (set $CHAT_ENABLED = true to enable) -->';
    return;
}

// Load current user info (from session or database)
$currentUser = [
    'id' => $_SESSION['userID'] ?? 0,
    'name' => $_SESSION['user_name'] ?? 'Guest',
    'initials' => $_SESSION['user_initials'] ?? 'GU',
    'avatar' => $_SESSION['user_avatar'] ?? null,
];

?>

<!-- CIS Chat Bar CSS -->
<style>
    #cis-chat-bar {
        position: fixed;
        bottom: 0;
        left: 0;
        right: 0;
        background: white;
        border-top: 2px solid var(--cis-border-color, #dee2e6);
        z-index: 1000;
        height: 50px;
        display: flex;
        align-items: center;
        padding: 0 1rem;
        gap: 0.5rem;
        box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
    }
    
    #cis-chat-users {
        display: flex;
        gap: 0.5rem;
        flex: 1;
        overflow-x: auto;
        overflow-y: hidden;
    }
    
    #cis-chat-users::-webkit-scrollbar {
        height: 4px;
    }
    
    #cis-chat-users::-webkit-scrollbar-thumb {
        background: var(--cis-border-color, #dee2e6);
        border-radius: 2px;
    }
    
    .cis-chat-user {
        display: flex;
        align-items: center;
        gap: 0.5rem;
        padding: 0.25rem 0.75rem;
        background: var(--cis-light, #f8f9fa);
        border-radius: 20px;
        cursor: pointer;
        white-space: nowrap;
        transition: all 0.2s;
        user-select: none;
    }
    
    .cis-chat-user:hover {
        background: var(--cis-primary-light, #e6f2ff);
    }
    
    .cis-chat-user.active {
        background: var(--cis-primary-light, #e6f2ff);
    }
    
    .cis-chat-user.has-window {
        background: var(--cis-primary-light, #e6f2ff);
    }
    
    .cis-chat-avatar {
        position: relative;
        width: 30px;
        height: 30px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 0.8rem;
        color: white;
        flex-shrink: 0;
    }
    
    .cis-chat-status {
        position: absolute;
        bottom: 0;
        right: 0;
        width: 10px;
        height: 10px;
        border-radius: 50%;
        border: 2px solid white;
    }
    
    .cis-chat-status.online {
        background: var(--cis-success, #28a745);
    }
    
    .cis-chat-status.away {
        background: var(--cis-warning, #ffc107);
    }
    
    .cis-chat-status.offline {
        background: var(--cis-secondary, #6c757d);
    }
    
    .cis-chat-unread-badge {
        background: var(--cis-danger, #dc3545);
        color: white;
        border-radius: 50%;
        min-width: 18px;
        height: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 0.7rem;
        font-weight: bold;
        padding: 0 4px;
    }
    
    .cis-chat-toggle {
        background: var(--cis-primary, #0066cc);
        color: white;
        border: none;
        padding: 0.5rem 1rem;
        border-radius: 4px;
        cursor: pointer;
        font-size: 0.85rem;
        white-space: nowrap;
        transition: all 0.2s;
    }
    
    .cis-chat-toggle:hover {
        background: var(--cis-primary-hover, #0052a3);
    }
    
    /* Chat Window */
    .cis-chat-window {
        position: fixed;
        bottom: 50px;
        right: 20px;
        width: 320px;
        background: white;
        border: 1px solid var(--cis-border-color, #dee2e6);
        border-radius: 8px 8px 0 0;
        box-shadow: 0 -2px 10px rgba(0,0,0,0.15);
        display: flex;
        flex-direction: column;
        max-height: 500px;
        z-index: 1001;
    }
    
    .cis-chat-window.minimized {
        height: 45px;
        max-height: 45px;
    }
    
    .cis-chat-window.minimized .cis-chat-messages,
    .cis-chat-window.minimized .cis-chat-input {
        display: none;
    }
    
    .cis-chat-window-header {
        background: var(--cis-primary, #0066cc);
        color: white;
        padding: 0.75rem 1rem;
        border-radius: 8px 8px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
        cursor: pointer;
        user-select: none;
    }
    
    .cis-chat-window-user {
        display: flex;
        align-items: center;
        gap: 0.5rem;
    }
    
    .cis-chat-window-avatar {
        width: 30px;
        height: 30px;
        border-radius: 50%;
        background: rgba(255,255,255,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 0.8rem;
    }
    
    .cis-chat-window-info h5 {
        margin: 0;
        font-size: 0.9rem;
        font-weight: 600;
    }
    
    .cis-chat-window-info p {
        margin: 0;
        font-size: 0.75rem;
        opacity: 0.9;
    }
    
    .cis-chat-window-actions {
        display: flex;
        gap: 0.5rem;
    }
    
    .cis-chat-window-actions button {
        background: none;
        border: none;
        color: white;
        cursor: pointer;
        font-size: 1.2rem;
        padding: 0;
        width: 24px;
        height: 24px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: opacity 0.2s;
    }
    
    .cis-chat-window-actions button:hover {
        opacity: 0.7;
    }
    
    .cis-chat-messages {
        flex: 1;
        overflow-y: auto;
        padding: 1rem;
        background: var(--cis-light, #f8f9fa);
    }
    
    .cis-chat-message {
        margin-bottom: 1rem;
    }
    
    .cis-chat-message.sent {
        display: flex;
        justify-content: flex-end;
    }
    
    .cis-chat-message-content {
        max-width: 75%;
    }
    
    .cis-chat-message.received .cis-chat-message-content {
        display: flex;
        gap: 0.5rem;
        align-items: flex-start;
    }
    
    .cis-chat-message-avatar {
        width: 28px;
        height: 28px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: bold;
        font-size: 0.7rem;
        color: white;
        flex-shrink: 0;
    }
    
    .cis-chat-message-bubble {
        padding: 0.5rem 0.75rem;
        border-radius: 12px;
        font-size: 0.85rem;
        word-wrap: break-word;
    }
    
    .cis-chat-message.received .cis-chat-message-bubble {
        background: white;
        box-shadow: 0 1px 2px rgba(0,0,0,0.1);
    }
    
    .cis-chat-message.sent .cis-chat-message-bubble {
        background: var(--cis-primary, #0066cc);
        color: white;
    }
    
    .cis-chat-message-time {
        font-size: 0.7rem;
        color: var(--cis-secondary, #6c757d);
        margin-top: 0.25rem;
    }
    
    .cis-chat-message.received .cis-chat-message-time {
        padding-left: 36px;
    }
    
    .cis-chat-message.sent .cis-chat-message-time {
        text-align: right;
    }
    
    .cis-chat-input {
        border-top: 1px solid var(--cis-border-color, #dee2e6);
        padding: 0.75rem;
        background: white;
    }
    
    .cis-chat-input-form {
        display: flex;
        gap: 0.5rem;
    }
    
    .cis-chat-input-form input {
        flex: 1;
        padding: 0.5rem 0.75rem;
        border: 1px solid var(--cis-border-color, #dee2e6);
        border-radius: 20px;
        font-size: 0.85rem;
    }
    
    .cis-chat-input-form input:focus {
        outline: none;
        border-color: var(--cis-primary, #0066cc);
    }
    
    .cis-chat-input-form button {
        background: var(--cis-primary, #0066cc);
        color: white;
        border: none;
        width: 35px;
        height: 35px;
        border-radius: 50%;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        transition: all 0.2s;
    }
    
    .cis-chat-input-form button:hover {
        background: var(--cis-primary-hover, #0052a3);
    }
    
    /* Multiple chat windows stacking */
    .cis-chat-window[data-position="0"] { right: 20px; }
    .cis-chat-window[data-position="1"] { right: 360px; }
    .cis-chat-window[data-position="2"] { right: 700px; }
    .cis-chat-window[data-position="3"] { right: 1040px; }
    
    /* Responsive */
    @media (max-width: 768px) {
        #cis-chat-bar {
            height: 45px;
            padding: 0 0.5rem;
        }
        
        .cis-chat-window {
            width: 100%;
            right: 0 !important;
            left: 0;
            border-radius: 0;
        }
        
        .cis-chat-window-header {
            border-radius: 0;
        }
    }
</style>

<!-- CIS Chat Bar HTML -->
<div id="cis-chat-bar">
    <!-- Online Users List -->
    <div id="cis-chat-users">
        <!-- Users will be loaded here via JavaScript -->
    </div>
    
    <!-- Chat Toggle Button -->
    <button class="cis-chat-toggle" onclick="CISChat.toggleUserList()">
        💬 Chat (<span id="cis-chat-online-count">0</span> online)
    </button>
</div>

<!-- Chat Windows Container -->
<div id="cis-chat-windows">
    <!-- Chat windows will be added here dynamically -->
</div>

<!-- CIS Chat Bar JavaScript -->
<script>
    // CIS Chat System
    const CISChat = {
        config: {
            enabled: <?= $CHAT_ENABLED ? 'true' : 'false' ?>,
            autoLoadUsers: <?= $CHAT_AUTO_LOAD_USERS ? 'true' : 'false' ?>,
            websocketUrl: <?= $CHAT_WEBSOCKET_URL ? '"' . $CHAT_WEBSOCKET_URL . '"' : 'null' ?>,
            pollingInterval: <?= $CHAT_POLLING_INTERVAL ?>,
            currentUser: <?= json_encode($currentUser) ?>,
        },
        
        state: {
            users: [],
            openWindows: [],
            ws: null,
            pollTimer: null,
        },
        
        // Initialize chat system
        init() {
            if (!this.config.enabled) {
                console.log('CIS Chat: Disabled');
                return;
            }
            
            console.log('CIS Chat: Initializing...');
            
            if (this.config.autoLoadUsers) {
                this.loadOnlineUsers();
            }
            
            // Connect to WebSocket or start polling
            if (this.config.websocketUrl) {
                this.connectWebSocket();
            } else {
                this.startPolling();
            }
            
            // Handle page unload
            window.addEventListener('beforeunload', () => {
                this.disconnect();
            });
        },
        
        // Load online users from server
        async loadOnlineUsers() {
            try {
                const response = await fetch('/modules/base/api/chat-users.php');
                const data = await response.json();
                
                if (data.success) {
                    this.state.users = data.users;
                    this.renderUsers();
                    this.updateOnlineCount();
                }
            } catch (error) {
                console.error('CIS Chat: Failed to load users', error);
            }
        },
        
        // Render users in the bottom bar
        renderUsers() {
            const container = document.getElementById('cis-chat-users');
            container.innerHTML = '';
            
            this.state.users.forEach(user => {
                const userEl = document.createElement('div');
                userEl.className = 'cis-chat-user';
                userEl.dataset.userId = user.id;
                
                // Check if user has open window
                const hasWindow = this.state.openWindows.some(w => w.userId === user.id);
                if (hasWindow) {
                    userEl.classList.add('has-window');
                }
                
                userEl.innerHTML = `
                    <div class="cis-chat-avatar" style="background: ${user.color || '#0066cc'}">
                        ${user.initials}
                        <span class="cis-chat-status ${user.status}"></span>
                    </div>
                    <span style="font-size: 0.85rem; font-weight: ${user.unread > 0 ? '600' : '400'}">
                        ${user.name}
                    </span>
                    ${user.unread > 0 ? `<span class="cis-chat-unread-badge">${user.unread}</span>` : ''}
                `;
                
                userEl.addEventListener('click', () => this.openChatWindow(user));
                container.appendChild(userEl);
            });
        },
        
        // Update online count
        updateOnlineCount() {
            const count = this.state.users.filter(u => u.status === 'online').length;
            document.getElementById('cis-chat-online-count').textContent = count;
        },
        
        // Open chat window for user
        openChatWindow(user) {
            // Check if already open
            const existing = this.state.openWindows.find(w => w.userId === user.id);
            if (existing) {
                // Focus existing window
                existing.element.style.zIndex = 1002;
                return;
            }
            
            // Create new window
            const position = this.state.openWindows.length;
            const windowEl = document.createElement('div');
            windowEl.className = 'cis-chat-window';
            windowEl.dataset.userId = user.id;
            windowEl.dataset.position = position;
            
            windowEl.innerHTML = `
                <div class="cis-chat-window-header" onclick="CISChat.toggleWindow(${user.id})">
                    <div class="cis-chat-window-user">
                        <div class="cis-chat-window-avatar">${user.initials}</div>
                        <div class="cis-chat-window-info">
                            <h5>${user.name}</h5>
                            <p>● Active now</p>
                        </div>
                    </div>
                    <div class="cis-chat-window-actions">
                        <button onclick="event.stopPropagation(); CISChat.toggleWindow(${user.id})">−</button>
                        <button onclick="event.stopPropagation(); CISChat.closeWindow(${user.id})">×</button>
                    </div>
                </div>
                <div class="cis-chat-messages" id="cis-chat-messages-${user.id}">
                    <!-- Messages will be loaded here -->
                </div>
                <div class="cis-chat-input">
                    <form class="cis-chat-input-form" onsubmit="event.preventDefault(); CISChat.sendMessage(${user.id})">
                        <input type="text" id="cis-chat-input-${user.id}" placeholder="Type a message..." autocomplete="off">
                        <button type="submit">➤</button>
                    </form>
                </div>
            `;
            
            document.getElementById('cis-chat-windows').appendChild(windowEl);
            
            this.state.openWindows.push({
                userId: user.id,
                element: windowEl,
                messages: [],
            });
            
            // Load chat history
            this.loadChatHistory(user.id);
            
            // Update user list
            this.renderUsers();
        },
        
        // Toggle window minimized state
        toggleWindow(userId) {
            const window = this.state.openWindows.find(w => w.userId === userId);
            if (window) {
                window.element.classList.toggle('minimized');
            }
        },
        
        // Close chat window
        closeWindow(userId) {
            const index = this.state.openWindows.findIndex(w => w.userId === userId);
            if (index !== -1) {
                this.state.openWindows[index].element.remove();
                this.state.openWindows.splice(index, 1);
                
                // Reposition remaining windows
                this.state.openWindows.forEach((w, i) => {
                    w.element.dataset.position = i;
                });
                
                // Update user list
                this.renderUsers();
            }
        },
        
        // Load chat history
        async loadChatHistory(userId) {
            try {
                const response = await fetch(`/modules/base/api/chat-messages.php?user_id=${userId}`);
                const data = await response.json();
                
                if (data.success) {
                    const window = this.state.openWindows.find(w => w.userId === userId);
                    if (window) {
                        window.messages = data.messages;
                        this.renderMessages(userId);
                    }
                }
            } catch (error) {
                console.error('CIS Chat: Failed to load messages', error);
            }
        },
        
        // Render messages
        renderMessages(userId) {
            const container = document.getElementById(`cis-chat-messages-${userId}`);
            const window = this.state.openWindows.find(w => w.userId === userId);
            
            if (!container || !window) return;
            
            container.innerHTML = window.messages.map(msg => {
                const isSent = msg.from_user_id === this.config.currentUser.id;
                const messageClass = isSent ? 'sent' : 'received';
                
                return `
                    <div class="cis-chat-message ${messageClass}">
                        <div class="cis-chat-message-content">
                            ${!isSent ? `<div class="cis-chat-message-avatar" style="background: ${msg.from_color}">${msg.from_initials}</div>` : ''}
                            <div>
                                <div class="cis-chat-message-bubble">${this.escapeHtml(msg.message)}</div>
                                <div class="cis-chat-message-time">${this.formatTime(msg.timestamp)}</div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
            
            // Scroll to bottom
            container.scrollTop = container.scrollHeight;
        },
        
        // Send message
        async sendMessage(userId) {
            const input = document.getElementById(`cis-chat-input-${userId}`);
            const message = input.value.trim();
            
            if (!message) return;
            
            try {
                const response = await fetch('/modules/base/api/chat-send.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        to_user_id: userId,
                        message: message,
                    }),
                });
                
                const data = await response.json();
                
                if (data.success) {
                    // Add message to window
                    const window = this.state.openWindows.find(w => w.userId === userId);
                    if (window) {
                        window.messages.push(data.message);
                        this.renderMessages(userId);
                    }
                    
                    // Clear input
                    input.value = '';
                }
            } catch (error) {
                console.error('CIS Chat: Failed to send message', error);
            }
        },
        
        // WebSocket connection
        connectWebSocket() {
            if (!this.config.websocketUrl) return;
            
            this.state.ws = new WebSocket(this.config.websocketUrl);
            
            this.state.ws.onopen = () => {
                console.log('CIS Chat: WebSocket connected');
            };
            
            this.state.ws.onmessage = (event) => {
                const data = JSON.parse(event.data);
                this.handleWebSocketMessage(data);
            };
            
            this.state.ws.onerror = (error) => {
                console.error('CIS Chat: WebSocket error', error);
            };
            
            this.state.ws.onclose = () => {
                console.log('CIS Chat: WebSocket closed, reconnecting...');
                setTimeout(() => this.connectWebSocket(), 5000);
            };
        },
        
        // Handle WebSocket messages
        handleWebSocketMessage(data) {
            if (data.type === 'new_message') {
                // Add message to relevant window
                const window = this.state.openWindows.find(w => w.userId === data.from_user_id);
                if (window) {
                    window.messages.push(data.message);
                    this.renderMessages(data.from_user_id);
                } else {
                    // Update unread count in user list
                    const user = this.state.users.find(u => u.id === data.from_user_id);
                    if (user) {
                        user.unread = (user.unread || 0) + 1;
                        this.renderUsers();
                    }
                }
            }
        },
        
        // Start polling (fallback if no WebSocket)
        startPolling() {
            this.state.pollTimer = setInterval(() => {
                this.loadOnlineUsers();
                // Poll for new messages in open windows
                this.state.openWindows.forEach(w => {
                    this.loadChatHistory(w.userId);
                });
            }, this.config.pollingInterval);
        },
        
        // Disconnect
        disconnect() {
            if (this.state.ws) {
                this.state.ws.close();
            }
            if (this.state.pollTimer) {
                clearInterval(this.state.pollTimer);
            }
        },
        
        // Utility: Escape HTML
        escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        // Utility: Format time
        formatTime(timestamp) {
            const date = new Date(timestamp);
            const now = new Date();
            const diff = now - date;
            
            if (diff < 60000) return 'Just now';
            if (diff < 3600000) return Math.floor(diff / 60000) + 'm ago';
            if (diff < 86400000) return date.toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit' });
            return date.toLocaleDateString('en-US', { month: 'short', day: 'numeric' });
        },
        
        // Toggle user list visibility
        toggleUserList() {
            // Future: Show/hide full user list modal
            alert('Full user list coming soon!');
        },
    };
    
    // Auto-initialize on page load
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => CISChat.init());
    } else {
        CISChat.init();
    }
</script>
