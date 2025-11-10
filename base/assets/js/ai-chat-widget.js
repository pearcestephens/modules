/**
 * AI Chat Widget - Universal AI Assistant
 * 
 * Provides AI chat capabilities on any page that includes this script
 * 
 * Usage:
 *   <script src="/modules/base/_assets/js/ai-chat-widget.js"></script>
 *   <script>
 *     const aiChat = new AIChatWidget({
 *       module: 'your-module',
 *       moduleName: 'Your Module',
 *       apiEndpoint: '/modules/base/api/ai-chat.php'
 *     });
 *   </script>
 */

class AIChatWidget {
    constructor(config = {}) {
        this.config = {
            module: config.module || 'unknown',
            moduleName: config.moduleName || 'CIS',
            apiEndpoint: config.apiEndpoint || '/modules/base/api/ai-chat.php',
            context: config.context || {},
            position: config.position || 'bottom-right', // bottom-right, bottom-left, top-right, top-left
            theme: config.theme || 'light', // light, dark
            autoOpen: config.autoOpen || false,
            ...config
        };
        
        this.isOpen = false;
        this.isMinimized = false;
        this.messageHistory = [];
        this.sessionId = this.generateSessionId();
        
        this.init();
    }
    
    init() {
        // Inject HTML if not already present
        if (!document.getElementById('ai-chat-widget')) {
            this.injectHTML();
        }
        
        // Bind events
        this.bindEvents();
        
        // Load message history from session storage
        this.loadHistory();
        
        // Auto-open if configured
        if (this.config.autoOpen) {
            setTimeout(() => this.open(), 500);
        }
        
        console.log('AI Chat Widget initialized for', this.config.module);
    }
    
    injectHTML() {
        const html = `
            <div id="ai-chat-widget" class="ai-chat-widget ${this.config.position} ${this.config.theme}" style="display: none;">
                <!-- Toggle Button -->
                <button id="ai-chat-toggle" class="ai-chat-toggle" title="AI Assistant">
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor">
                        <path d="M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5z"/>
                        <path d="M12 9v6m0 0v3"/>
                        <circle cx="12" cy="12" r="1"/>
                    </svg>
                    <span class="ai-chat-badge" id="ai-chat-badge" style="display: none;">0</span>
                </button>
                
                <!-- Chat Panel -->
                <div id="ai-chat-panel" class="ai-chat-panel" style="display: none;">
                    <!-- Header -->
                    <div class="ai-chat-header">
                        <div class="ai-chat-title">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M12 2L2 7v10c0 5.55 3.84 10.74 9 12 5.16-1.26 9-6.45 9-12V7l-10-5z"/>
                            </svg>
                            <span>AI Assistant - ${this.config.moduleName}</span>
                        </div>
                        <div class="ai-chat-actions">
                            <button id="ai-chat-minimize" class="ai-chat-btn-icon" title="Minimize">−</button>
                            <button id="ai-chat-close" class="ai-chat-btn-icon" title="Close">×</button>
                        </div>
                    </div>
                    
                    <!-- Messages Area -->
                    <div id="ai-chat-messages" class="ai-chat-messages">
                        <div class="ai-message ai-system">
                            <div class="ai-message-icon">🤖</div>
                            <div class="ai-message-content">
                                <strong>Hi! I'm your AI assistant.</strong><br>
                                I can help you with ${this.config.moduleName}. Try asking:
                                <ul>
                                    <li>"What should I focus on today?"</li>
                                    <li>"Show me recent activity"</li>
                                    <li>"Help me with..."</li>
                                    <li>"Analyze this data"</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions (optional) -->
                    <div id="ai-chat-quick-actions" class="ai-chat-quick-actions" style="display: none;">
                        <button class="ai-quick-btn" data-action="summary">📊 Daily Summary</button>
                        <button class="ai-quick-btn" data-action="help">❓ Help</button>
                        <button class="ai-quick-btn" data-action="tips">💡 Tips</button>
                    </div>
                    
                    <!-- Input Area -->
                    <div class="ai-chat-input-area">
                        <textarea id="ai-chat-input" 
                                  class="ai-chat-input" 
                                  placeholder="Ask AI anything..." 
                                  rows="1"
                                  maxlength="1000"></textarea>
                        <button id="ai-chat-send" class="ai-chat-send" title="Send">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M2 21l21-9L2 3v7l15 2-15 2z"/>
                            </svg>
                        </button>
                    </div>
                    
                    <!-- Typing Indicator -->
                    <div id="ai-typing-indicator" class="ai-typing-indicator" style="display: none;">
                        <span></span><span></span><span></span>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', html);
        
        // Show widget after injection
        setTimeout(() => {
            document.getElementById('ai-chat-widget').style.display = 'block';
        }, 100);
    }
    
    bindEvents() {
        const toggle = document.getElementById('ai-chat-toggle');
        const close = document.getElementById('ai-chat-close');
        const minimize = document.getElementById('ai-chat-minimize');
        const send = document.getElementById('ai-chat-send');
        const input = document.getElementById('ai-chat-input');
        
        if (toggle) toggle.addEventListener('click', () => this.toggle());
        if (close) close.addEventListener('click', () => this.close());
        if (minimize) minimize.addEventListener('click', () => this.minimize());
        if (send) send.addEventListener('click', () => this.send());
        
        if (input) {
            // Auto-resize textarea
            input.addEventListener('input', (e) => {
                e.target.style.height = 'auto';
                e.target.style.height = Math.min(e.target.scrollHeight, 120) + 'px';
            });
            
            // Send on Enter (Shift+Enter for new line)
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !e.shiftKey) {
                    e.preventDefault();
                    this.send();
                }
            });
        }
        
        // Quick action buttons
        document.querySelectorAll('.ai-quick-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const action = e.target.dataset.action;
                this.handleQuickAction(action);
            });
        });
    }
    
    toggle() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    }
    
    open() {
        this.isOpen = true;
        this.isMinimized = false;
        
        const panel = document.getElementById('ai-chat-panel');
        const toggle = document.getElementById('ai-chat-toggle');
        const badge = document.getElementById('ai-chat-badge');
        
        if (panel) panel.style.display = 'flex';
        if (toggle) toggle.classList.add('active');
        if (badge) badge.style.display = 'none';
        
        // Focus input
        setTimeout(() => {
            const input = document.getElementById('ai-chat-input');
            if (input) input.focus();
        }, 100);
        
        // Log widget opened
        this.logEvent('widget_opened');
    }
    
    close() {
        this.isOpen = false;
        
        const panel = document.getElementById('ai-chat-panel');
        const toggle = document.getElementById('ai-chat-toggle');
        
        if (panel) panel.style.display = 'none';
        if (toggle) toggle.classList.remove('active');
        
        // Log widget closed
        this.logEvent('widget_closed');
    }
    
    minimize() {
        this.isMinimized = !this.isMinimized;
        
        const messages = document.getElementById('ai-chat-messages');
        const input = document.getElementById('ai-chat-input-area');
        const quickActions = document.getElementById('ai-chat-quick-actions');
        
        const display = this.isMinimized ? 'none' : 'block';
        
        if (messages) messages.style.display = display;
        if (input) input.style.display = display;
        if (quickActions) quickActions.style.display = display;
    }
    
    async send() {
        const input = document.getElementById('ai-chat-input');
        if (!input) return;
        
        const message = input.value.trim();
        if (!message) return;
        
        // Clear input
        input.value = '';
        input.style.height = 'auto';
        
        // Disable input while processing
        input.disabled = true;
        
        // Add user message to UI
        this.addMessage('user', message);
        
        // Show typing indicator
        this.showTyping(true);
        
        try {
            // Prepare request
            const payload = {
                message: message,
                context: {
                    ...this.config.context,
                    module: this.config.module,
                    session_id: this.sessionId,
                    history: this.messageHistory.slice(-5) // Last 5 messages for context
                }
            };
            
            // Send to API
            const response = await fetch(this.config.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });
            
            const data = await response.json();
            
            // Hide typing indicator
            this.showTyping(false);
            
            if (data.success) {
                // Add AI response
                this.addMessage('ai', data.response, {
                    confidence: data.confidence,
                    suggested_function: data.suggested_function
                });
                
                // Store in history
                this.messageHistory.push({
                    user: message,
                    ai: data.response,
                    timestamp: new Date().toISOString()
                });
                
                // Save to session storage
                this.saveHistory();
                
                // Log successful interaction
                this.logEvent('message_sent', { success: true });
                
            } else {
                this.addMessage('ai', '❌ ' + (data.message || data.error || 'Sorry, I encountered an error.'), {
                    error: true
                });
                
                // Log error
                this.logEvent('message_error', { error: data.error });
            }
            
        } catch (error) {
            console.error('AI Chat Error:', error);
            
            // Hide typing indicator
            this.showTyping(false);
            
            // Show error message
            this.addMessage('ai', '❌ Connection error. Please check your internet connection and try again.', {
                error: true
            });
            
            // Log error
            this.logEvent('message_error', { error: error.message });
            
        } finally {
            // Re-enable input
            input.disabled = false;
            input.focus();
        }
    }
    
    addMessage(type, text, options = {}) {
        const messagesDiv = document.getElementById('ai-chat-messages');
        if (!messagesDiv) return;
        
        const messageDiv = document.createElement('div');
        messageDiv.className = `ai-message ai-${type}`;
        
        if (options.error) {
            messageDiv.classList.add('ai-error');
        }
        
        // Icon
        const iconDiv = document.createElement('div');
        iconDiv.className = 'ai-message-icon';
        iconDiv.textContent = type === 'user' ? '👤' : '🤖';
        
        // Content
        const contentDiv = document.createElement('div');
        contentDiv.className = 'ai-message-content';
        
        // Convert markdown-like formatting to HTML
        const formattedText = this.formatMessage(text);
        contentDiv.innerHTML = formattedText;
        
        // Confidence indicator (if AI message)
        if (type === 'ai' && options.confidence !== undefined) {
            const confidenceSpan = document.createElement('span');
            confidenceSpan.className = 'ai-confidence';
            confidenceSpan.textContent = `Confidence: ${Math.round(options.confidence * 100)}%`;
            contentDiv.appendChild(confidenceSpan);
        }
        
        messageDiv.appendChild(iconDiv);
        messageDiv.appendChild(contentDiv);
        
        messagesDiv.appendChild(messageDiv);
        
        // Scroll to bottom
        messagesDiv.scrollTop = messagesDiv.scrollHeight;
        
        // Animate in
        setTimeout(() => messageDiv.classList.add('ai-message-animate'), 10);
    }
    
    formatMessage(text) {
        // Basic markdown-like formatting
        return text
            .replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>') // **bold**
            .replace(/\*(.*?)\*/g, '<em>$1</em>') // *italic*
            .replace(/`(.*?)`/g, '<code>$1</code>') // `code`
            .replace(/\n/g, '<br>'); // Line breaks
    }
    
    showTyping(show) {
        const indicator = document.getElementById('ai-typing-indicator');
        if (indicator) {
            indicator.style.display = show ? 'flex' : 'none';
        }
        
        // Scroll to bottom if showing
        if (show) {
            const messagesDiv = document.getElementById('ai-chat-messages');
            if (messagesDiv) {
                messagesDiv.scrollTop = messagesDiv.scrollHeight;
            }
        }
    }
    
    handleQuickAction(action) {
        const actions = {
            'summary': "Give me a summary of today's activity",
            'help': "What can you help me with?",
            'tips': "Give me some tips for using this module effectively"
        };
        
        const message = actions[action] || action;
        
        // Set input value and send
        const input = document.getElementById('ai-chat-input');
        if (input) {
            input.value = message;
            this.send();
        }
    }
    
    generateSessionId() {
        return 'session_' + Date.now() + '_' + Math.random().toString(36).substr(2, 9);
    }
    
    loadHistory() {
        try {
            const stored = sessionStorage.getItem('ai_chat_history_' + this.config.module);
            if (stored) {
                this.messageHistory = JSON.parse(stored);
                
                // Restore messages to UI (last 10)
                const lastMessages = this.messageHistory.slice(-10);
                lastMessages.forEach(msg => {
                    this.addMessage('user', msg.user);
                    this.addMessage('ai', msg.ai);
                });
            }
        } catch (error) {
            console.warn('Could not load chat history:', error);
        }
    }
    
    saveHistory() {
        try {
            // Keep only last 20 messages
            if (this.messageHistory.length > 20) {
                this.messageHistory = this.messageHistory.slice(-20);
            }
            
            sessionStorage.setItem(
                'ai_chat_history_' + this.config.module,
                JSON.stringify(this.messageHistory)
            );
        } catch (error) {
            console.warn('Could not save chat history:', error);
        }
    }
    
    logEvent(event, data = {}) {
        // Log to console in development
        if (console.debug) {
            console.debug('AI Chat Event:', event, data);
        }
        
        // Could also send to logging API
        // fetch('/api/log-ai-event', { ... });
    }
    
    // Public API
    setContext(newContext) {
        this.config.context = { ...this.config.context, ...newContext };
    }
    
    clearHistory() {
        this.messageHistory = [];
        sessionStorage.removeItem('ai_chat_history_' + this.config.module);
        
        // Clear UI
        const messagesDiv = document.getElementById('ai-chat-messages');
        if (messagesDiv) {
            messagesDiv.innerHTML = '';
        }
    }
}

// Export for use in modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = AIChatWidget;
}
