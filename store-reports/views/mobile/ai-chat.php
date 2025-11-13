<?php
/**
 * Store Reports - AI Assistant Chat
 * Real-time conversational AI for store managers
 */
require_once __DIR__ . '/../../../../private_html/check-login.php';
require_once __DIR__ . '/../../config.php';

$userId = $_SESSION['user_id'] ?? 0;
$userName = $_SESSION['user_name'] ?? 'User';
$reportId = $_GET['report_id'] ?? null;

// Get report context if provided
$reportContext = null;
if ($reportId) {
    try {
        $db = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $db->prepare("SELECT sr.*, vo.name as outlet_name
            FROM store_reports sr
            LEFT JOIN vend_outlets vo ON sr.outlet_id = vo.id
            WHERE sr.id = ? AND sr.performed_by_user = ?");
        $stmt->execute([$reportId, $userId]);
        $reportContext = $stmt->fetch(PDO::FETCH_ASSOC);

        // Load conversation history
        $stmt = $db->prepare("SELECT * FROM store_report_ai_conversations
            WHERE report_id = ?
            ORDER BY created_at ASC");
        $stmt->execute([$reportId]);
        $conversationHistory = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        error_log("AI Chat - Failed to load context: " . $e->getMessage());
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="theme-color" content="#667eea">
    <title>AI Assistant - Store Reports</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        :root {
            --ai-primary: #667eea;
            --ai-secondary: #764ba2;
            --user-bubble: #4a90e2;
            --ai-bubble: #f5f6fa;
        }

        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: linear-gradient(135deg, var(--ai-primary), var(--ai-secondary));
            height: 100vh;
            overflow: hidden;
            display: flex;
            flex-direction: column;
        }

        /* Header */
        .chat-header {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 16px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .chat-back-btn {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #f5f5f5;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
        }

        .chat-back-btn:active {
            background: #e0e0e0;
            transform: scale(0.95);
        }

        .chat-avatar {
            width: 45px;
            height: 45px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--ai-primary), var(--ai-secondary));
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 20px;
        }

        .chat-header-info {
            flex: 1;
        }

        .chat-header-title {
            font-size: 16px;
            font-weight: 600;
            color: #1a1a2e;
            margin: 0;
        }

        .chat-header-subtitle {
            font-size: 12px;
            color: #666;
        }

        .chat-status {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: 12px;
            color: #4caf50;
        }

        .chat-status-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #4caf50;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.5; }
        }

        /* Messages Container */
        .chat-messages {
            flex: 1;
            overflow-y: auto;
            padding: 20px 16px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
        }

        .chat-messages::-webkit-scrollbar {
            width: 4px;
        }

        .chat-messages::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 4px;
        }

        /* Message Bubbles */
        .message {
            display: flex;
            margin-bottom: 16px;
            animation: slideUp 0.3s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .message.user {
            justify-content: flex-end;
        }

        .message-bubble {
            max-width: 75%;
            padding: 12px 16px;
            border-radius: 20px;
            font-size: 15px;
            line-height: 1.5;
            position: relative;
            word-wrap: break-word;
        }

        .message.user .message-bubble {
            background: var(--user-bubble);
            color: white;
            border-bottom-right-radius: 4px;
        }

        .message.ai .message-bubble {
            background: white;
            color: #1a1a2e;
            border-bottom-left-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .message-time {
            font-size: 11px;
            opacity: 0.6;
            margin-top: 4px;
            text-align: right;
        }

        .message.ai .message-time {
            text-align: left;
        }

        /* Typing Indicator */
        .typing-indicator {
            display: none;
            padding: 12px 16px;
            background: white;
            border-radius: 20px;
            width: 60px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .typing-indicator.active {
            display: inline-flex;
            align-items: center;
            gap: 4px;
        }

        .typing-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: #999;
            animation: typingDot 1.4s infinite;
        }

        .typing-dot:nth-child(2) {
            animation-delay: 0.2s;
        }

        .typing-dot:nth-child(3) {
            animation-delay: 0.4s;
        }

        @keyframes typingDot {
            0%, 60%, 100% { transform: translateY(0); }
            30% { transform: translateY(-10px); }
        }

        /* Quick Suggestions */
        .quick-suggestions {
            padding: 12px 16px;
            background: rgba(255, 255, 255, 0.95);
            overflow-x: auto;
            display: flex;
            gap: 8px;
            white-space: nowrap;
        }

        .quick-suggestions::-webkit-scrollbar {
            height: 4px;
        }

        .suggestion-chip {
            padding: 8px 16px;
            background: #f5f5f5;
            border: 1px solid #e0e0e0;
            border-radius: 20px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
            flex-shrink: 0;
        }

        .suggestion-chip:active {
            background: #e0e0e0;
            transform: scale(0.95);
        }

        /* Input Area */
        .chat-input-area {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            padding: 16px;
            box-shadow: 0 -2px 8px rgba(0,0,0,0.1);
        }

        .chat-input-wrapper {
            display: flex;
            gap: 8px;
            align-items: end;
        }

        .chat-input {
            flex: 1;
            border: 2px solid #e0e0e0;
            border-radius: 24px;
            padding: 12px 16px;
            font-size: 15px;
            resize: none;
            max-height: 120px;
            overflow-y: auto;
            transition: all 0.2s;
        }

        .chat-input:focus {
            outline: none;
            border-color: var(--ai-primary);
        }

        .chat-send-btn {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--ai-primary), var(--ai-secondary));
            color: white;
            border: none;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.2s;
            font-size: 18px;
        }

        .chat-send-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        .chat-send-btn:not(:disabled):active {
            transform: scale(0.9);
        }

        /* Context Banner */
        .context-banner {
            background: rgba(255, 255, 255, 0.9);
            padding: 12px 16px;
            border-bottom: 1px solid rgba(0,0,0,0.1);
            font-size: 13px;
        }

        .context-banner i {
            color: var(--ai-primary);
            margin-right: 8px;
        }

        /* Welcome Message */
        .welcome-message {
            text-align: center;
            padding: 40px 20px;
            color: white;
        }

        .welcome-icon {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 36px;
            margin: 0 auto 20px;
        }

        .welcome-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 8px;
        }

        .welcome-subtitle {
            font-size: 15px;
            opacity: 0.9;
        }
    </style>
</head>
<body>
    <!-- Header -->
    <div class="chat-header">
        <button class="chat-back-btn" onclick="goBack()">
            <i class="fas fa-arrow-left"></i>
        </button>
        <div class="chat-avatar">
            <i class="fas fa-robot"></i>
        </div>
        <div class="chat-header-info">
            <div class="chat-header-title">AI Assistant</div>
            <div class="chat-status">
                <span class="chat-status-dot"></span>
                <span>Online</span>
            </div>
        </div>
    </div>

    <?php if ($reportContext): ?>
    <!-- Context Banner -->
    <div class="context-banner">
        <i class="fas fa-link"></i>
        <strong>Report #<?= $reportId ?></strong> - <?= htmlspecialchars($reportContext['outlet_name']) ?>
        (<?= date('M j, Y', strtotime($reportContext['report_date'])) ?>)
    </div>
    <?php endif; ?>

    <!-- Messages -->
    <div class="chat-messages" id="messages">
        <?php if (!empty($conversationHistory)): ?>
            <?php foreach ($conversationHistory as $msg): ?>
                <div class="message <?= $msg['role'] === 'user' ? 'user' : 'ai' ?>">
                    <div class="message-bubble">
                        <?= nl2br(htmlspecialchars($msg['message'])) ?>
                        <div class="message-time">
                            <?= date('g:i A', strtotime($msg['created_at'])) ?>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <!-- Welcome Message -->
            <div class="welcome-message">
                <div class="welcome-icon">
                    <i class="fas fa-robot"></i>
                </div>
                <div class="welcome-title">Hi, I'm your AI Assistant!</div>
                <div class="welcome-subtitle">
                    Ask me anything about store compliance, safety procedures, or best practices.
                </div>
            </div>
        <?php endif; ?>

        <!-- Typing Indicator -->
        <div class="message ai">
            <div class="typing-indicator" id="typing">
                <span class="typing-dot"></span>
                <span class="typing-dot"></span>
                <span class="typing-dot"></span>
            </div>
        </div>
    </div>

    <!-- Quick Suggestions -->
    <div class="quick-suggestions" id="suggestions">
        <button class="suggestion-chip" onclick="sendQuickMessage('What should I check in the refrigeration section?')">
            🧊 Refrigeration tips
        </button>
        <button class="suggestion-chip" onclick="sendQuickMessage('How do I handle expired products?')">
            📦 Expired products
        </button>
        <button class="suggestion-chip" onclick="sendQuickMessage('What are critical safety items?')">
            🚨 Critical safety
        </button>
        <button class="suggestion-chip" onclick="sendQuickMessage('Help me improve my score')">
            ⭐ Improve score
        </button>
        <button class="suggestion-chip" onclick="sendQuickMessage('Summarize my report')">
            📊 Report summary
        </button>
    </div>

    <!-- Input Area -->
    <div class="chat-input-area">
        <div class="chat-input-wrapper">
            <textarea
                id="message-input"
                class="chat-input"
                placeholder="Ask me anything..."
                rows="1"
            ></textarea>
            <button class="chat-send-btn" id="send-btn" onclick="sendMessage()">
                <i class="fas fa-paper-plane"></i>
            </button>
        </div>
    </div>

    <script>
        const reportId = <?= $reportId ? $reportId : 'null' ?>;
        const userId = <?= $userId ?>;
        const messagesContainer = document.getElementById('messages');
        const messageInput = document.getElementById('message-input');
        const sendBtn = document.getElementById('send-btn');
        const typingIndicator = document.getElementById('typing');

        // Auto-resize textarea
        messageInput.addEventListener('input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';

            sendBtn.disabled = !this.value.trim();
        });

        // Send on Enter (Shift+Enter for new line)
        messageInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                sendMessage();
            }
        });

        function goBack() {
            if (reportId) {
                window.location.href = '/modules/store-reports/views/mobile/create-report.php';
            } else {
                window.history.back();
            }
        }

        function sendQuickMessage(text) {
            messageInput.value = text;
            sendMessage();
        }

        async function sendMessage() {
            const message = messageInput.value.trim();
            if (!message) return;

            // Add user message to UI
            addMessage('user', message);

            // Clear input
            messageInput.value = '';
            messageInput.style.height = 'auto';
            sendBtn.disabled = true;

            // Show typing indicator
            typingIndicator.classList.add('active');
            scrollToBottom();

            try {
                const response = await fetch('/modules/store-reports/api/ai-chat-respond.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        message: message,
                        report_id: reportId
                    })
                });

                const result = await response.json();

                // Hide typing indicator
                typingIndicator.classList.remove('active');

                if (result.success) {
                    // Add AI response to UI
                    addMessage('ai', result.ai_response);

                    // Log token usage
                    if (result.tokens_used) {
                        console.log('💰 Tokens used:', result.tokens_used);
                    }
                } else {
                    throw new Error(result.message || 'AI response failed');
                }
            } catch (error) {
                typingIndicator.classList.remove('active');
                addMessage('ai', 'Sorry, I encountered an error. Please try again.');
                console.error('AI response error:', error);
            }
        }

        function addMessage(role, text) {
            const messageDiv = document.createElement('div');
            messageDiv.className = 'message ' + role;

            const now = new Date();
            const timeStr = now.toLocaleTimeString('en-US', {
                hour: 'numeric',
                minute: '2-digit',
                hour12: true
            });

            messageDiv.innerHTML = `
                <div class="message-bubble">
                    ${escapeHtml(text).replace(/\n/g, '<br>')}
                    <div class="message-time">${timeStr}</div>
                </div>
            `;

            messagesContainer.insertBefore(messageDiv, typingIndicator.parentElement);
            scrollToBottom();
        }

        function scrollToBottom() {
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
        }

        function escapeHtml(text) {
            const div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        }

        // Initial scroll
        scrollToBottom();

        // Focus input on load
        messageInput.focus();
    </script>
</body>
</html>
