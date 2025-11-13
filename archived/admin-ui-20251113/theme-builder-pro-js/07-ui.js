/**
 * Theme Builder PRO - UI Interactions
 * Tab switching, modals, notifications, etc.
 * @version 3.0.0
 */

window.ThemeBuilder.ui = {

    // Initialize all UI event listeners
    init: function() {
        // Editor tab switching
        document.querySelectorAll('.editor-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const editor = this.dataset.editor;

                document.querySelectorAll('.editor-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.editor-wrapper').forEach(w => w.classList.remove('active'));

                this.classList.add('active');
                document.getElementById(editor + '-editor').classList.add('active');
            });
        });

        // Sidebar tab switching
        document.querySelectorAll('.sidebar-tab').forEach(tab => {
            tab.addEventListener('click', function() {
                const target = this.dataset.tab;

                document.querySelectorAll('.sidebar-tab').forEach(t => t.classList.remove('active'));
                document.querySelectorAll('.tab-content').forEach(c => c.style.display = 'none');

                this.classList.add('active');
                document.querySelector(`[data-content="${target}"]`).style.display = 'block';
            });
        });

        // Device preview buttons
        document.querySelectorAll('.device-btn').forEach(btn => {
            btn.addEventListener('click', function() {
                window.ThemeBuilder.setDeviceMode(this.dataset.device);
            });
        });

        // Close FAB menu when clicking outside
        document.addEventListener('click', function(e) {
            const fab = document.getElementById('fab-menu');
            if (fab && !fab.contains(e.target)) {
                fab.classList.remove('open');
            }
        });

        // Keyboard shortcuts
        document.addEventListener('keydown', function(e) {
            // Ctrl+S or Cmd+S to save
            if ((e.ctrlKey || e.metaKey) && e.key === 's') {
                e.preventDefault();
                window.ThemeBuilder.themes.save();
            }

            // Ctrl+E or Cmd+E to export
            if ((e.ctrlKey || e.metaKey) && e.key === 'e') {
                e.preventDefault();
                window.ThemeBuilder.themes.export();
            }
        });

        // Warn before leaving with unsaved changes
        window.addEventListener('beforeunload', function(e) {
            if (window.ThemeBuilder.state.unsavedChanges) {
                e.preventDefault();
                e.returnValue = 'You have unsaved changes. Are you sure you want to leave?';
                return e.returnValue;
            }
        });

        console.log('✓ UI interactions initialized');
    },

    // Toggle FAB menu
    toggleFab: function() {
        document.getElementById('fab-menu').classList.toggle('open');
    },

    // Show modal
    showModal: function(modalId) {
        document.getElementById(modalId).classList.add('show');
    },

    // Close modal
    closeModal: function(modalId) {
        document.getElementById(modalId).classList.remove('show');
    },

    // Show notification toast
    showNotification: function(message, type = 'info') {
        const colors = {
            success: '#10b981',
            error: '#ef4444',
            warning: '#f59e0b',
            info: '#3b82f6'
        };

        const notification = document.createElement('div');
        notification.style.cssText = `
            position: fixed;
            top: 80px;
            right: 24px;
            background: ${colors[type]};
            color: white;
            padding: 1rem 1.5rem;
            border-radius: 8px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.3);
            z-index: 10000;
            font-weight: 500;
            animation: slideInRight 0.3s ease;
        `;
        notification.textContent = message;

        document.body.appendChild(notification);

        setTimeout(() => {
            notification.style.animation = 'slideOutRight 0.3s ease';
            setTimeout(() => notification.remove(), 300);
        }, 3000);
    }
};

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }

    .btn-icon-sm {
        background: none;
        border: none;
        color: var(--text-secondary);
        cursor: pointer;
        padding: 0.375rem;
        border-radius: 4px;
        transition: all 0.2s;
        font-size: 0.875rem;
    }

    .btn-icon-sm:hover {
        background: var(--bg-primary);
        color: var(--danger);
    }
`;
document.head.appendChild(style);
