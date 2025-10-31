/**
 * Theme Builder PRO - AI Agent Integration
 * Hooks for AI-powered code analysis, generation, and assistance
 * @version 3.0.0
 */

window.ThemeBuilder.ai = {

    // Initialize AI agent hooks
    init: function() {
        console.log('✓ AI agent integration initialized');

        // Create AI chat interface if it doesn't exist
        this.createChatInterface();
    },

    // Analyze code for improvements
    analyzeCode: function(type = 'all') {
        const state = window.ThemeBuilder.state;
        let code = '';
        let language = '';

        switch(type) {
            case 'html':
                code = state.editors.html.getValue();
                language = 'html';
                break;
            case 'css':
                code = state.editors.css.getValue();
                language = 'css';
                break;
            case 'js':
                code = state.editors.js.getValue();
                language = 'javascript';
                break;
            case 'all':
                code = JSON.stringify({
                    html: state.editors.html.getValue(),
                    css: state.editors.css.getValue(),
                    js: state.editors.js.getValue()
                });
                language = 'json';
                break;
        }

        window.ThemeBuilder.ui.showNotification('Analyzing code...', 'info');

        // Call AI API
        window.ThemeBuilder.api.aiAnalyze(code, language, (response) => {
            if (response.success) {
                this.showAnalysisResults(response.data);
            } else {
                window.ThemeBuilder.ui.showNotification('Analysis failed: ' + response.error, 'error');
            }
        });
    },

    // Show analysis results
    showAnalysisResults: function(data) {
        const modal = `
            <div id="ai-analysis-modal" style="
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0,0,0,0.5);
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: center;
            " onclick="if(event.target.id === 'ai-analysis-modal') this.remove()">
                <div style="
                    background: var(--bg-secondary);
                    border: 1px solid var(--bg-tertiary);
                    border-radius: 8px;
                    padding: 2rem;
                    max-width: 700px;
                    width: 90%;
                    max-height: 80vh;
                    overflow-y: auto;
                " onclick="event.stopPropagation()">
                    <h3 style="color: var(--primary); margin-bottom: 1.5rem;">
                        <i class="fas fa-robot"></i> AI Code Analysis
                    </h3>
                    <div style="color: var(--text-primary);">
                        <h4 style="color: var(--secondary); margin-top: 1rem;">Suggestions:</h4>
                        <ul style="margin-left: 1.5rem; color: var(--text-secondary);">
                            ${data.suggestions ? data.suggestions.map(s => `<li>${s}</li>`).join('') : '<li>No suggestions</li>'}
                        </ul>

                        <h4 style="color: var(--accent); margin-top: 1rem;">Warnings:</h4>
                        <ul style="margin-left: 1.5rem; color: var(--text-secondary);">
                            ${data.warnings ? data.warnings.map(w => `<li>${w}</li>`).join('') : '<li>No warnings</li>'}
                        </ul>

                        <h4 style="color: var(--primary); margin-top: 1rem;">Quality Score:</h4>
                        <div style="
                            background: var(--bg-tertiary);
                            height: 30px;
                            border-radius: 15px;
                            overflow: hidden;
                            position: relative;
                        ">
                            <div style="
                                background: linear-gradient(90deg, var(--primary), var(--secondary));
                                height: 100%;
                                width: ${data.score || 75}%;
                                transition: width 0.5s;
                            "></div>
                            <span style="
                                position: absolute;
                                top: 50%;
                                left: 50%;
                                transform: translate(-50%, -50%);
                                color: white;
                                font-weight: bold;
                            ">${data.score || 75}%</span>
                        </div>
                    </div>
                    <button onclick="document.getElementById('ai-analysis-modal').remove()" style="
                        margin-top: 1.5rem;
                        background: var(--primary);
                        color: white;
                        border: none;
                        padding: 0.5rem 1.5rem;
                        border-radius: 6px;
                        cursor: pointer;
                        font-weight: 500;
                    ">Close</button>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modal);
    },

    // Generate code from prompt
    generateCode: function(prompt, type = 'html') {
        window.ThemeBuilder.ui.showNotification('Generating code...', 'info');

        // This would call an AI generation API
        // For now, placeholder
        setTimeout(() => {
            window.ThemeBuilder.ui.showNotification('AI code generation feature coming soon!', 'info');
        }, 1000);
    },

    // Auto-complete suggestions
    getSuggestions: function(context, position) {
        // This would return AI-powered code suggestions
        // Placeholder for now
        return [];
    },

    // Create AI chat interface
    createChatInterface: function() {
        const chat = document.getElementById('ai-chat-container');
        if (chat) return; // Already exists

        const chatHTML = `
            <div id="ai-chat-container" style="
                position: fixed;
                bottom: 80px;
                right: 24px;
                width: 350px;
                height: 500px;
                background: var(--bg-secondary);
                border: 1px solid var(--bg-tertiary);
                border-radius: 8px;
                box-shadow: 0 8px 32px rgba(0,0,0,0.5);
                display: none;
                flex-direction: column;
                z-index: 9999;
            ">
                <div style="
                    background: var(--bg-tertiary);
                    padding: 1rem;
                    border-radius: 8px 8px 0 0;
                    display: flex;
                    justify-content: space-between;
                    align-items: center;
                ">
                    <h4 style="color: var(--primary); margin: 0;">
                        <i class="fas fa-robot"></i> AI Assistant
                    </h4>
                    <button onclick="window.ThemeBuilder.ai.toggleChat()" style="
                        background: none;
                        border: none;
                        color: var(--text-secondary);
                        cursor: pointer;
                        font-size: 1.25rem;
                    ">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

                <div id="ai-chat-messages" style="
                    flex: 1;
                    overflow-y: auto;
                    padding: 1rem;
                    display: flex;
                    flex-direction: column;
                    gap: 0.5rem;
                ">
                    <div class="ai-message" style="
                        background: var(--bg-tertiary);
                        padding: 0.75rem;
                        border-radius: 8px;
                        color: var(--text-primary);
                    ">
                        👋 Hi! I'm your AI assistant. I can help you:
                        <ul style="margin: 0.5rem 0 0 1rem; font-size: 0.875rem;">
                            <li>Analyze your code</li>
                            <li>Generate components</li>
                            <li>Fix bugs</li>
                            <li>Optimize performance</li>
                        </ul>
                        Try: "Analyze my HTML" or "Create a button component"
                    </div>
                </div>

                <div style="
                    padding: 1rem;
                    border-top: 1px solid var(--bg-tertiary);
                    display: flex;
                    gap: 0.5rem;
                ">
                    <input id="ai-chat-input" type="text" placeholder="Ask me anything..." style="
                        flex: 1;
                        background: var(--bg-tertiary);
                        border: 1px solid var(--bg-primary);
                        border-radius: 6px;
                        padding: 0.5rem;
                        color: var(--text-primary);
                    " onkeypress="if(event.key === 'Enter') window.ThemeBuilder.ai.sendMessage()">
                    <button onclick="window.ThemeBuilder.ai.sendMessage()" style="
                        background: var(--primary);
                        border: none;
                        border-radius: 6px;
                        padding: 0.5rem 1rem;
                        color: white;
                        cursor: pointer;
                        font-weight: 500;
                    ">
                        <i class="fas fa-paper-plane"></i>
                    </button>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', chatHTML);
    },

    // Toggle AI chat interface
    toggleChat: function() {
        const chat = document.getElementById('ai-chat-container');
        if (chat) {
            chat.style.display = chat.style.display === 'none' ? 'flex' : 'none';
        }
    },

    // Send message to AI
    sendMessage: function() {
        const input = document.getElementById('ai-chat-input');
        const message = input.value.trim();

        if (!message) return;

        // Add user message
        this.addChatMessage(message, 'user');
        input.value = '';

        // Process message
        this.processChatMessage(message);
    },

    // Add message to chat
    addChatMessage: function(text, sender = 'ai') {
        const messagesContainer = document.getElementById('ai-chat-messages');
        if (!messagesContainer) return;

        const isUser = sender === 'user';
        const messageHTML = `
            <div style="
                background: ${isUser ? 'var(--primary)' : 'var(--bg-tertiary)'};
                color: white;
                padding: 0.75rem;
                border-radius: 8px;
                align-self: ${isUser ? 'flex-end' : 'flex-start'};
                max-width: 80%;
            ">
                ${text}
            </div>
        `;

        messagesContainer.insertAdjacentHTML('beforeend', messageHTML);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
    },

    // Process chat message
    processChatMessage: function(message) {
        const lowerMessage = message.toLowerCase();

        // Analyze commands
        if (lowerMessage.includes('analyze')) {
            if (lowerMessage.includes('html')) {
                this.addChatMessage('Analyzing your HTML code...', 'ai');
                this.analyzeCode('html');
            } else if (lowerMessage.includes('css')) {
                this.addChatMessage('Analyzing your CSS code...', 'ai');
                this.analyzeCode('css');
            } else if (lowerMessage.includes('js') || lowerMessage.includes('javascript')) {
                this.addChatMessage('Analyzing your JavaScript code...', 'ai');
                this.analyzeCode('js');
            } else {
                this.addChatMessage('Analyzing all your code...', 'ai');
                this.analyzeCode('all');
            }
        }

        // Create/generate commands
        else if (lowerMessage.includes('create') || lowerMessage.includes('generate')) {
            this.addChatMessage('AI code generation is coming soon! For now, you can use the component library.', 'ai');
        }

        // Help commands
        else if (lowerMessage.includes('help')) {
            this.addChatMessage(`
                I can help you with:
                <ul style="margin: 0.5rem 0 0 1rem;">
                    <li>"Analyze my HTML/CSS/JS" - Code analysis</li>
                    <li>"Create a component" - Generate code</li>
                    <li>"Show shortcuts" - Keyboard shortcuts</li>
                    <li>"Show history" - Version history</li>
                </ul>
            `, 'ai');
        }

        // Shortcuts
        else if (lowerMessage.includes('shortcut')) {
            window.ThemeBuilder.keyboard.showShortcutsHelp();
            this.addChatMessage('Opened keyboard shortcuts window!', 'ai');
        }

        // History
        else if (lowerMessage.includes('history')) {
            window.ThemeBuilder.history.showTimeline();
            this.addChatMessage('Opened version history window!', 'ai');
        }

        // Default response
        else {
            this.addChatMessage('I\'m not sure how to help with that. Try "help" to see what I can do!', 'ai');
        }
    }
};
