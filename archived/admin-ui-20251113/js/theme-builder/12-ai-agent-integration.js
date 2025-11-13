/**
 * AI Agent Integration for Theme Builder
 * Allows AI agents to edit code in real-time across all tabs
 *
 * Features:
 * - Real-time code editing by AI
 * - Multi-tab support (HTML, CSS, JS)
 * - Live preview updates
 * - Edit history tracking
 * - Agent conversation interface
 * - Code review and suggestions
 *
 * @version 1.0.0
 */

class AIAgentIntegration {
    constructor() {
        this.apiEndpoint = '/modules/admin-ui/api/ai-agent-handler.php';
        this.agentActive = false;
        this.editQueue = [];
        this.conversationHistory = [];
        this.currentSession = null;
        this.watchMode = false;

        this.init();
    }

    init() {
        this.createAgentPanel();
        this.bindEvents();
        this.setupMonacoIntegration();
        console.log('🤖 AI Agent Integration initialized');
    }

    createAgentPanel() {
        const panel = document.createElement('div');
        panel.id = 'ai-agent-panel';
        panel.className = 'ai-agent-panel collapsed';
        panel.innerHTML = `
            <div class="ai-agent-header">
                <div class="agent-status">
                    <div class="status-indicator" id="agent-status-indicator"></div>
                    <span id="agent-status-text">AI Agent: Inactive</span>
                </div>
                <div class="agent-controls">
                    <button class="btn-agent-toggle" onclick="aiAgent.toggleWatchMode()" title="Enable Watch Mode">
                        <i class="fas fa-eye"></i>
                    </button>
                    <button class="btn-agent-collapse" onclick="aiAgent.togglePanel()">
                        <i class="fas fa-chevron-left"></i>
                    </button>
                </div>
            </div>

            <div class="ai-agent-content">
                <!-- Chat Interface -->
                <div class="agent-chat-section">
                    <div class="chat-messages" id="agent-chat-messages">
                        <div class="chat-welcome">
                            <i class="fas fa-robot"></i>
                            <h4>AI Agent Ready</h4>
                            <p>Tell me what you want to build or modify</p>
                        </div>
                    </div>

                    <div class="chat-input-container">
                        <textarea
                            id="agent-chat-input"
                            placeholder="Describe what you want the AI to edit..."
                            rows="3"></textarea>
                        <div class="chat-actions">
                            <button class="btn-send" onclick="aiAgent.sendMessage()">
                                <i class="fas fa-paper-plane"></i> Send
                            </button>
                            <button class="btn-quick-action" onclick="aiAgent.quickAction('review')">
                                <i class="fas fa-search"></i> Review Code
                            </button>
                            <button class="btn-quick-action" onclick="aiAgent.quickAction('optimize')">
                                <i class="fas fa-bolt"></i> Optimize
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Edit Queue -->
                <div class="agent-edit-queue" id="agent-edit-queue">
                    <div class="queue-header">
                        <h5>Edit Queue</h5>
                        <span class="queue-count">0 pending</span>
                    </div>
                    <div class="queue-items" id="queue-items"></div>
                </div>

                <!-- Live Activity Log -->
                <div class="agent-activity-log">
                    <div class="activity-header">
                        <h5>Activity Log</h5>
                        <button class="btn-clear-log" onclick="aiAgent.clearLog()">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                    <div class="activity-items" id="activity-items">
                        <div class="activity-item">
                            <i class="fas fa-info-circle"></i>
                            <span>Ready to receive AI commands</span>
                            <small>${new Date().toLocaleTimeString()}</small>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.appendChild(panel);
        this.addStyles();
    }

    addStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .ai-agent-panel {
                position: fixed;
                right: 0;
                top: 60px;
                width: 400px;
                height: calc(100vh - 60px);
                background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
                border-left: 2px solid #8b5cf6;
                box-shadow: -4px 0 20px rgba(139, 92, 246, 0.2);
                z-index: 900;
                transition: transform 0.3s ease;
                display: flex;
                flex-direction: column;
            }

            .ai-agent-panel.collapsed {
                transform: translateX(100%);
            }

            .ai-agent-header {
                padding: 1rem 1.5rem;
                background: rgba(139, 92, 246, 0.1);
                border-bottom: 1px solid rgba(139, 92, 246, 0.3);
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .agent-status {
                display: flex;
                align-items: center;
                gap: 0.75rem;
            }

            .status-indicator {
                width: 12px;
                height: 12px;
                border-radius: 50%;
                background: #64748b;
                box-shadow: 0 0 10px rgba(100, 116, 139, 0.5);
                animation: pulse 2s infinite;
            }

            .status-indicator.active {
                background: #10b981;
                box-shadow: 0 0 10px rgba(16, 185, 129, 0.5);
            }

            .status-indicator.editing {
                background: #f59e0b;
                box-shadow: 0 0 10px rgba(245, 158, 11, 0.5);
                animation: blink 0.5s infinite;
            }

            @keyframes pulse {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.5; }
            }

            @keyframes blink {
                0%, 100% { opacity: 1; }
                50% { opacity: 0.3; }
            }

            #agent-status-text {
                color: #f1f5f9;
                font-size: 0.875rem;
                font-weight: 600;
            }

            .agent-controls {
                display: flex;
                gap: 0.5rem;
            }

            .btn-agent-toggle,
            .btn-agent-collapse {
                width: 32px;
                height: 32px;
                border-radius: 6px;
                background: rgba(139, 92, 246, 0.2);
                border: 1px solid rgba(139, 92, 246, 0.3);
                color: #8b5cf6;
                cursor: pointer;
                transition: all 0.2s;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .btn-agent-toggle:hover,
            .btn-agent-collapse:hover {
                background: rgba(139, 92, 246, 0.3);
                transform: scale(1.05);
            }

            .btn-agent-toggle.active {
                background: #8b5cf6;
                color: white;
            }

            .ai-agent-content {
                flex: 1;
                display: flex;
                flex-direction: column;
                overflow: hidden;
            }

            .agent-chat-section {
                flex: 1;
                display: flex;
                flex-direction: column;
                border-bottom: 1px solid rgba(139, 92, 246, 0.2);
            }

            .chat-messages {
                flex: 1;
                overflow-y: auto;
                padding: 1rem;
            }

            .chat-welcome {
                text-align: center;
                padding: 2rem 1rem;
                color: #94a3b8;
            }

            .chat-welcome i {
                font-size: 3rem;
                color: #8b5cf6;
                margin-bottom: 1rem;
            }

            .chat-welcome h4 {
                margin: 0 0 0.5rem 0;
                color: #f1f5f9;
            }

            .chat-message {
                margin-bottom: 1rem;
                animation: slideIn 0.3s ease;
            }

            @keyframes slideIn {
                from {
                    opacity: 0;
                    transform: translateY(10px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .chat-message.user {
                text-align: right;
            }

            .chat-bubble {
                display: inline-block;
                padding: 0.75rem 1rem;
                border-radius: 12px;
                max-width: 80%;
                word-wrap: break-word;
            }

            .chat-message.user .chat-bubble {
                background: linear-gradient(135deg, #8b5cf6, #7c3aed);
                color: white;
                border-bottom-right-radius: 4px;
            }

            .chat-message.agent .chat-bubble {
                background: #334155;
                color: #f1f5f9;
                border-bottom-left-radius: 4px;
            }

            .chat-timestamp {
                display: block;
                font-size: 0.75rem;
                color: #64748b;
                margin-top: 0.25rem;
            }

            .chat-input-container {
                padding: 1rem;
                background: rgba(0, 0, 0, 0.2);
            }

            #agent-chat-input {
                width: 100%;
                padding: 0.75rem;
                background: #334155;
                border: 1px solid #475569;
                border-radius: 8px;
                color: #f1f5f9;
                font-size: 0.875rem;
                resize: none;
                margin-bottom: 0.5rem;
            }

            #agent-chat-input:focus {
                outline: none;
                border-color: #8b5cf6;
            }

            .chat-actions {
                display: flex;
                gap: 0.5rem;
            }

            .btn-send {
                flex: 1;
                padding: 0.625rem 1rem;
                background: linear-gradient(135deg, #8b5cf6, #7c3aed);
                color: white;
                border: none;
                border-radius: 6px;
                font-size: 0.875rem;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s;
            }

            .btn-send:hover {
                transform: translateY(-1px);
                box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
            }

            .btn-quick-action {
                padding: 0.625rem 0.75rem;
                background: #334155;
                color: #f1f5f9;
                border: none;
                border-radius: 6px;
                font-size: 0.75rem;
                cursor: pointer;
                transition: all 0.2s;
            }

            .btn-quick-action:hover {
                background: #475569;
            }

            .agent-edit-queue,
            .agent-activity-log {
                border-bottom: 1px solid rgba(139, 92, 246, 0.2);
            }

            .queue-header,
            .activity-header {
                padding: 0.75rem 1rem;
                background: rgba(139, 92, 246, 0.1);
                display: flex;
                justify-content: space-between;
                align-items: center;
            }

            .queue-header h5,
            .activity-header h5 {
                margin: 0;
                font-size: 0.875rem;
                color: #f1f5f9;
                font-weight: 600;
            }

            .queue-count {
                font-size: 0.75rem;
                color: #94a3b8;
            }

            .queue-items,
            .activity-items {
                max-height: 200px;
                overflow-y: auto;
            }

            .queue-item {
                padding: 0.75rem 1rem;
                border-bottom: 1px solid rgba(139, 92, 246, 0.1);
                display: flex;
                align-items: center;
                gap: 0.75rem;
            }

            .queue-item-icon {
                width: 32px;
                height: 32px;
                border-radius: 6px;
                background: rgba(139, 92, 246, 0.2);
                display: flex;
                align-items: center;
                justify-content: center;
                color: #8b5cf6;
            }

            .queue-item-info {
                flex: 1;
            }

            .queue-item-title {
                font-size: 0.875rem;
                color: #f1f5f9;
                font-weight: 500;
                margin-bottom: 0.25rem;
            }

            .queue-item-detail {
                font-size: 0.75rem;
                color: #94a3b8;
            }

            .activity-item {
                padding: 0.75rem 1rem;
                display: flex;
                align-items: flex-start;
                gap: 0.75rem;
                border-bottom: 1px solid rgba(139, 92, 246, 0.1);
            }

            .activity-item i {
                color: #8b5cf6;
                margin-top: 0.25rem;
            }

            .activity-item span {
                flex: 1;
                font-size: 0.875rem;
                color: #f1f5f9;
            }

            .activity-item small {
                font-size: 0.75rem;
                color: #64748b;
            }

            .btn-clear-log {
                width: 24px;
                height: 24px;
                background: transparent;
                border: none;
                color: #64748b;
                cursor: pointer;
                border-radius: 4px;
                transition: all 0.2s;
            }

            .btn-clear-log:hover {
                background: rgba(239, 68, 68, 0.2);
                color: #ef4444;
            }

            /* Code Change Highlight */
            .monaco-editor .ai-edit-highlight {
                background: rgba(139, 92, 246, 0.2) !important;
                border-left: 3px solid #8b5cf6 !important;
            }

            /* Floating Agent Button (when panel is collapsed) */
            .floating-agent-btn {
                position: fixed;
                right: 24px;
                bottom: 150px;
                width: 56px;
                height: 56px;
                border-radius: 50%;
                background: linear-gradient(135deg, #8b5cf6, #7c3aed);
                color: white;
                border: none;
                cursor: pointer;
                box-shadow: 0 4px 12px rgba(139, 92, 246, 0.4);
                z-index: 899;
                font-size: 1.5rem;
                transition: all 0.3s;
                display: flex;
                align-items: center;
                justify-content: center;
            }

            .floating-agent-btn:hover {
                transform: scale(1.1);
                box-shadow: 0 6px 20px rgba(139, 92, 246, 0.6);
            }

            .floating-agent-btn.active {
                animation: pulse 2s infinite;
            }
        `;

        document.head.appendChild(style);
    }

    bindEvents() {
        // Enter key to send message
        const input = document.getElementById('agent-chat-input');
        input?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                this.sendMessage();
            }
        });

        // Create floating button when panel is collapsed
        this.createFloatingButton();
    }

    createFloatingButton() {
        const btn = document.createElement('button');
        btn.className = 'floating-agent-btn';
        btn.innerHTML = '<i class="fas fa-robot"></i>';
        btn.title = 'Open AI Agent';
        btn.onclick = () => this.togglePanel();

        document.body.appendChild(btn);

        // Hide when panel is open
        const panel = document.getElementById('ai-agent-panel');
        const observer = new MutationObserver(() => {
            if (panel.classList.contains('collapsed')) {
                btn.style.display = 'flex';
            } else {
                btn.style.display = 'none';
            }
        });

        observer.observe(panel, { attributes: true, attributeFilter: ['class'] });
    }

    togglePanel() {
        const panel = document.getElementById('ai-agent-panel');
        panel.classList.toggle('collapsed');
    }

    toggleWatchMode() {
        this.watchMode = !this.watchMode;
        const btn = document.querySelector('.btn-agent-toggle');
        btn.classList.toggle('active');

        this.addActivity(
            this.watchMode ? 'Watch mode enabled - AI can now edit code in real-time' : 'Watch mode disabled',
            this.watchMode ? 'success' : 'info'
        );

        if (this.watchMode) {
            this.setStatus('active', 'AI Agent: Watch Mode Active');
        } else {
            this.setStatus('inactive', 'AI Agent: Inactive');
        }
    }

    setupMonacoIntegration() {
        // Hook into Monaco editors for real-time updates
        if (typeof ThemeBuilderState !== 'undefined') {
            console.log('🔗 Monaco integration ready');
        }
    }

    async sendMessage() {
        const input = document.getElementById('agent-chat-input');
        const message = input.value.trim();

        if (!message) return;

        // Add user message to chat
        this.addChatMessage(message, 'user');
        input.value = '';

        // Set status to processing
        this.setStatus('editing', 'AI Agent: Processing...');
        this.addActivity(`User: ${message}`, 'info');

        try {
            // Send to AI agent API
            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'process_command',
                    message: message,
                    context: this.getCurrentContext(),
                    watchMode: this.watchMode
                })
            });

            const data = await response.json();

            if (data.success) {
                // Add agent response
                this.addChatMessage(data.response, 'agent');

                // Process edits if any
                if (data.edits && data.edits.length > 0) {
                    this.processEdits(data.edits);
                }

                this.addActivity(`Agent: ${data.response}`, 'success');
            } else {
                this.addChatMessage('Sorry, I encountered an error processing your request.', 'agent');
                this.addActivity(`Error: ${data.error}`, 'error');
            }

        } catch (error) {
            console.error('AI Agent error:', error);
            this.addChatMessage('Sorry, I\'m having trouble connecting right now.', 'agent');
            this.addActivity('Connection error', 'error');
        } finally {
            this.setStatus('active', 'AI Agent: Ready');
        }
    }

    async quickAction(action) {
        const prompts = {
            review: 'Review the current code and suggest improvements',
            optimize: 'Optimize the current code for performance and readability',
            beautify: 'Format and beautify the current code',
            document: 'Add comprehensive comments and documentation'
        };

        const input = document.getElementById('agent-chat-input');
        input.value = prompts[action];
        await this.sendMessage();
    }

    /**
     * NEW: Validate current code and suggest fixes
     */
    async validateCode() {
        this.addChatMessage('🔍 Validating your code...', 'agent');
        this.setStatus('editing', 'Validating code...');

        try {
            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'validate_and_fix',
                    html: this.getEditorContent('html'),
                    css: this.getEditorContent('css'),
                    javascript: this.getEditorContent('javascript'),
                    watchMode: this.watchMode
                })
            });

            const data = await response.json();

            if (data.success) {
                // Show validation results
                const resultMessage = this.formatValidationResults(data.validationResults);
                this.addChatMessage(
                    `${data.response}\n\n${resultMessage}\n\n${data.readyToApply ? '✅ Ready to apply fixes?' : ''}`,
                    'agent'
                );

                // Store validation results for applying fixes
                this.lastValidationResults = data;

                // Show suggestions
                if (data.suggestions && data.suggestions.length > 0) {
                    this.addChatMessage(`I have ${data.suggestions.length} suggestions for improvement:`, 'agent');
                    data.suggestions.forEach((sugg, i) => {
                        this.addChatMessage(`${i + 1}. [${sugg.type}] ${sugg.message || sugg.recommendation}`, 'agent');
                    });
                }

                this.addActivity('Code validation completed', 'success');

                // Auto-apply fixes in watch mode
                if (this.watchMode && data.readyToApply) {
                    await this.applyValidationFixes();
                }
            } else {
                this.addChatMessage('Validation error: ' + (data.error || 'Unknown error'), 'agent');
            }

        } catch (error) {
            console.error('Validation error:', error);
            this.addChatMessage('Failed to validate code', 'agent');
            this.addActivity('Validation error', 'error');
        } finally {
            this.setStatus('active', 'AI Agent: Ready');
        }
    }

    /**
     * NEW: Get AI suggestions for improvements
     */
    async suggestImprovements() {
        this.addChatMessage('💡 Analyzing for improvement opportunities...', 'agent');
        this.setStatus('editing', 'Generating suggestions...');

        try {
            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'suggest_improvements',
                    html: this.getEditorContent('html'),
                    css: this.getEditorContent('css'),
                    javascript: this.getEditorContent('javascript')
                })
            });

            const data = await response.json();

            if (data.success && data.suggestions.length > 0) {
                // Group by severity
                const errors = data.suggestions.filter(s => s.severity === 'error');
                const warnings = data.suggestions.filter(s => s.severity === 'warning');
                const info = data.suggestions.filter(s => s.severity === 'info');

                let message = `Found ${data.totalSuggestions} improvement opportunities:\n\n`;

                if (errors.length > 0) {
                    message += `❌ Critical Issues (${errors.length}):\n`;
                    errors.forEach(s => message += `  • ${s.message}\n`);
                    message += '\n';
                }

                if (warnings.length > 0) {
                    message += `⚠️ Warnings (${warnings.length}):\n`;
                    warnings.forEach(s => message += `  • ${s.message}\n`);
                    message += '\n';
                }

                if (info.length > 0) {
                    message += `ℹ️ Suggestions (${info.length}):\n`;
                    info.forEach(s => message += `  • ${s.message || s.recommendation}\n`);
                }

                this.addChatMessage(message, 'agent');
                this.addActivity('Generated improvement suggestions', 'success');

            } else {
                this.addChatMessage('✅ Your code looks great! No improvements needed right now.', 'agent');
            }

        } catch (error) {
            console.error('Suggestions error:', error);
            this.addChatMessage('Failed to generate suggestions', 'agent');
            this.addActivity('Suggestions error', 'error');
        } finally {
            this.setStatus('active', 'AI Agent: Ready');
        }
    }

    /**
     * NEW: Apply validation fixes automatically
     */
    async applyValidationFixes() {
        if (!this.lastValidationResults || !this.lastValidationResults.fixes) {
            this.addChatMessage('No fixes available. Run validation first.', 'agent');
            return;
        }

        this.addChatMessage('🔧 Applying fixes...', 'agent');
        this.setStatus('editing', 'Applying validation fixes...');

        try {
            const response = await fetch(this.apiEndpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    action: 'apply_validation_fixes',
                    fixes: this.lastValidationResults.fixes
                })
            });

            const data = await response.json();

            if (data.success) {
                // Apply edits from fixes
                if (data.edits && data.edits.length > 0) {
                    this.processEdits(data.edits);
                }

                this.addChatMessage(data.response, 'agent');
                this.addActivity(`Applied ${data.appliedFixes.length} fix(es)`, 'success');

                // Re-validate to confirm fixes
                setTimeout(() => {
                    this.validateCode();
                }, 500);
            } else {
                this.addChatMessage('Failed to apply fixes', 'agent');
            }

        } catch (error) {
            console.error('Apply fixes error:', error);
            this.addChatMessage('Failed to apply fixes', 'agent');
            this.addActivity('Apply fixes error', 'error');
        } finally {
            this.setStatus('active', 'AI Agent: Ready');
        }
    }

    /**
     * Helper: Get current editor content
     */
    getEditorContent(type) {
        if (!window.monacoEditors) return '';
        const editor = window.monacoEditors[type];
        return editor ? editor.getValue() : '';
    }

    /**
     * Helper: Format validation results for display
     */
    formatValidationResults(results) {
        let output = '';

        for (const [type, validation] of Object.entries(results)) {
            const errors = validation.errors || [];
            const warnings = validation.warnings || [];

            if (errors.length > 0 || warnings.length > 0) {
                output += `\n**${type.toUpperCase()}**:\n`;
                errors.forEach(e => output += `  ❌ ${e.message} (line ${e.line || '?'})\n`);
                warnings.forEach(w => output += `  ⚠️ ${w.message} (line ${w.line || '?'})\n`);
            }
        }

        return output || '✅ All code validates successfully!';
    }
}

// Auto-initialize
let aiAgent;
document.addEventListener('DOMContentLoaded', () => {
    aiAgent = new AIAgentIntegration();
});
