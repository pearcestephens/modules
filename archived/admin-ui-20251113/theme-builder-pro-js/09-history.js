/**
 * Theme Builder PRO - Version History & Undo/Redo
 * Track changes and provide undo/redo functionality
 * @version 3.0.0
 */

window.ThemeBuilder.history = {

    stack: [],
    currentIndex: -1,
    maxStackSize: 50,

    // Initialize history tracking
    init: function() {
        // Save initial state
        this.saveState('Initial state');

        // Track editor changes
        setTimeout(() => {
            const editors = window.ThemeBuilder.state.editors;

            if (editors.html) {
                editors.html.onDidChangeModelContent(() => {
                    this.debounceAutoSave();
                });
            }
            if (editors.css) {
                editors.css.onDidChangeModelContent(() => {
                    this.debounceAutoSave();
                });
            }
            if (editors.js) {
                editors.js.onDidChangeModelContent(() => {
                    this.debounceAutoSave();
                });
            }
        }, 1000);

        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            // Ctrl+Z for undo
            if ((e.ctrlKey || e.metaKey) && e.key === 'z' && !e.shiftKey) {
                e.preventDefault();
                this.undo();
            }

            // Ctrl+Shift+Z or Ctrl+Y for redo
            if (((e.ctrlKey || e.metaKey) && e.shiftKey && e.key === 'z') ||
                ((e.ctrlKey || e.metaKey) && e.key === 'y')) {
                e.preventDefault();
                this.redo();
            }
        });

        console.log('✓ History tracking initialized');
    },

    autoSaveTimeout: null,

    // Debounced auto-save to history
    debounceAutoSave: function() {
        clearTimeout(this.autoSaveTimeout);
        this.autoSaveTimeout = setTimeout(() => {
            this.saveState('Auto-save');
        }, 2000);
    },

    // Save current state to history
    saveState: function(label = 'Change') {
        const state = window.ThemeBuilder.state;

        if (!state.editors.html) return;

        const snapshot = {
            timestamp: Date.now(),
            label: label,
            theme: {
                id: state.currentTheme.id,
                name: state.currentTheme.name,
                html: state.editors.html.getValue(),
                css: state.editors.css.getValue(),
                js: state.editors.js.getValue()
            }
        };

        // Remove any states after current index (for redo branch)
        if (this.currentIndex < this.stack.length - 1) {
            this.stack = this.stack.slice(0, this.currentIndex + 1);
        }

        // Add new state
        this.stack.push(snapshot);
        this.currentIndex = this.stack.length - 1;

        // Limit stack size
        if (this.stack.length > this.maxStackSize) {
            this.stack.shift();
            this.currentIndex--;
        }

        this.updateHistoryUI();
    },

    // Undo to previous state
    undo: function() {
        if (this.currentIndex <= 0) {
            window.ThemeBuilder.ui.showNotification('Nothing to undo', 'info');
            return;
        }

        this.currentIndex--;
        this.restoreState(this.stack[this.currentIndex]);
        window.ThemeBuilder.ui.showNotification(`Undo: ${this.stack[this.currentIndex].label}`, 'info');
        this.updateHistoryUI();
    },

    // Redo to next state
    redo: function() {
        if (this.currentIndex >= this.stack.length - 1) {
            window.ThemeBuilder.ui.showNotification('Nothing to redo', 'info');
            return;
        }

        this.currentIndex++;
        this.restoreState(this.stack[this.currentIndex]);
        window.ThemeBuilder.ui.showNotification(`Redo: ${this.stack[this.currentIndex].label}`, 'info');
        this.updateHistoryUI();
    },

    // Restore a specific state
    restoreState: function(snapshot) {
        const state = window.ThemeBuilder.state;

        if (state.editors.html) {
            state.editors.html.setValue(snapshot.theme.html);
        }
        if (state.editors.css) {
            state.editors.css.setValue(snapshot.theme.css);
        }
        if (state.editors.js) {
            state.editors.js.setValue(snapshot.theme.js);
        }

        state.currentTheme.html = snapshot.theme.html;
        state.currentTheme.css = snapshot.theme.css;
        state.currentTheme.js = snapshot.theme.js;

        window.ThemeBuilder.refreshPreview();
    },

    // Jump to specific state in history
    jumpTo: function(index) {
        if (index < 0 || index >= this.stack.length) return;

        this.currentIndex = index;
        this.restoreState(this.stack[index]);
        window.ThemeBuilder.ui.showNotification(`Jumped to: ${this.stack[index].label}`, 'info');
        this.updateHistoryUI();
    },

    // Update history UI
    updateHistoryUI: function() {
        const historyContainer = document.getElementById('history-timeline');
        if (!historyContainer) return;

        let html = '<div class="history-list">';

        this.stack.forEach((snapshot, index) => {
            const isActive = index === this.currentIndex;
            const time = new Date(snapshot.timestamp).toLocaleTimeString();

            html += `
                <div class="history-item ${isActive ? 'active' : ''}"
                     onclick="window.ThemeBuilder.history.jumpTo(${index})"
                     style="
                         padding: 0.75rem;
                         border-left: 3px solid ${isActive ? 'var(--primary)' : 'var(--bg-tertiary)'};
                         background: ${isActive ? 'var(--bg-tertiary)' : 'transparent'};
                         cursor: pointer;
                         transition: all 0.2s;
                         margin-bottom: 0.5rem;
                     ">
                    <div style="color: var(--text-primary); font-weight: ${isActive ? '600' : '400'};">
                        ${snapshot.label}
                    </div>
                    <div style="color: var(--text-secondary); font-size: 0.75rem;">
                        ${time}
                    </div>
                </div>
            `;
        });

        html += '</div>';

        historyContainer.innerHTML = html;
    },

    // Show history timeline modal
    showTimeline: function() {
        const modal = `
            <div id="history-modal" style="
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
            " onclick="if(event.target.id === 'history-modal') this.remove()">
                <div style="
                    background: var(--bg-secondary);
                    border: 1px solid var(--bg-tertiary);
                    border-radius: 8px;
                    padding: 2rem;
                    max-width: 500px;
                    width: 90%;
                    max-height: 80vh;
                    overflow-y: auto;
                " onclick="event.stopPropagation()">
                    <h3 style="color: var(--primary); margin-bottom: 1.5rem;">
                        <i class="fas fa-history"></i> Version History
                    </h3>
                    <div id="history-timeline"></div>
                    <div style="margin-top: 1.5rem; display: flex; gap: 1rem;">
                        <button onclick="window.ThemeBuilder.history.undo(); window.ThemeBuilder.history.updateHistoryUI();" style="
                            flex: 1;
                            background: var(--secondary);
                            color: white;
                            border: none;
                            padding: 0.5rem;
                            border-radius: 6px;
                            cursor: pointer;
                            font-weight: 500;
                        ">
                            <i class="fas fa-undo"></i> Undo
                        </button>
                        <button onclick="window.ThemeBuilder.history.redo(); window.ThemeBuilder.history.updateHistoryUI();" style="
                            flex: 1;
                            background: var(--secondary);
                            color: white;
                            border: none;
                            padding: 0.5rem;
                            border-radius: 6px;
                            cursor: pointer;
                            font-weight: 500;
                        ">
                            <i class="fas fa-redo"></i> Redo
                        </button>
                        <button onclick="document.getElementById('history-modal').remove()" style="
                            flex: 1;
                            background: var(--bg-tertiary);
                            color: white;
                            border: none;
                            padding: 0.5rem;
                            border-radius: 6px;
                            cursor: pointer;
                            font-weight: 500;
                        ">Close</button>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modal);
        this.updateHistoryUI();
    },

    // Clear all history
    clear: function() {
        if (confirm('Clear all version history? This cannot be undone.')) {
            this.stack = [];
            this.currentIndex = -1;
            this.saveState('Initial state');
            window.ThemeBuilder.ui.showNotification('History cleared', 'info');
        }
    }
};
