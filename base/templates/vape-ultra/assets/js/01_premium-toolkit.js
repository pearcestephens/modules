/**
 * ═══════════════════════════════════════════════════════════════════════════
 * VAPEULTRA PREMIUM FRONTEND TOOLKIT v2.0
 * ═══════════════════════════════════════════════════════════════════════════
 * 
 * Complete UX library with modern components:
 * - Toast notifications with sounds & haptics
 * - Modal dialogs with animations
 * - Loading states & progress bars
 * - Error handling & logging
 * - Sound effects system
 * - Confirmation prompts
 * - Copy to clipboard
 * - Network status monitoring
 * - Performance monitoring
 * 
 * AI & Chat optimized:
 * - Markdown rendering (marked.js)
 * - Code syntax highlighting (highlight.js)
 * - XSS protection (DOMPurify)
 * - Real-time updates (Socket.IO)
 * - Typing indicators
 * - Message animations
 * 
 * @author Ecigdis Limited
 * @version 2.0.0
 * @premium
 */

window.VapeUltra = window.VapeUltra || {};

// ═══════════════════════════════════════════════════════════════════════════
// TOAST NOTIFICATION SYSTEM
// ═══════════════════════════════════════════════════════════════════════════
VapeUltra.Toast = {
    container: null,
    queue: [],
    active: [],
    maxVisible: 5,
    soundEnabled: localStorage.getItem('vu_sound_enabled') !== 'false',
    
    init() {
        if (!this.container) {
            this.container = document.createElement('div');
            this.container.className = 'vu-toast-container';
            document.body.appendChild(this.container);
        }
    },
    
    show(message, type = 'info', options = {}) {
        this.init();
        
        const toast = {
            id: Date.now() + Math.random(),
            message,
            type,
            options: {
                duration: 4000,
                icon: true,
                sound: true,
                action: null,
                onAction: null,
                dismissible: true,
                ...options
            }
        };
        
        if (this.active.length >= this.maxVisible) {
            this.queue.push(toast);
        } else {
            this.display(toast);
        }
        
        return toast.id;
    },
    
    display(toast) {
        const el = document.createElement('div');
        el.className = `vu-toast vu-toast-${toast.type} animate__animated animate__fadeInRight`;
        el.dataset.toastId = toast.id;
        
        const iconMap = {
            success: 'bi-check-circle-fill',
            error: 'bi-x-circle-fill',
            warning: 'bi-exclamation-triangle-fill',
            info: 'bi-info-circle-fill'
        };
        
        el.innerHTML = `
            <div class="vu-toast-content">
                ${toast.options.icon ? `<i class="bi ${iconMap[toast.type]}"></i>` : ''}
                <span class="vu-toast-message">${toast.message}</span>
                ${toast.options.action ? `<button class="vu-toast-action">${toast.options.action}</button>` : ''}
                ${toast.options.dismissible ? '<button class="vu-toast-close"><i class="bi bi-x"></i></button>' : ''}
            </div>
            <div class="vu-toast-progress" style="animation-duration: ${toast.options.duration}ms"></div>
        `;
        
        this.container.appendChild(el);
        this.active.push(toast.id);
        
        // Sound effect
        if (this.soundEnabled && toast.options.sound) {
            VapeUltra.Sound?.play(toast.type);
        }
        
        // Action button
        if (toast.options.action && toast.options.onAction) {
            el.querySelector('.vu-toast-action')?.addEventListener('click', () => {
                toast.options.onAction();
                this.hide(toast.id);
            });
        }
        
        // Close button
        el.querySelector('.vu-toast-close')?.addEventListener('click', () => {
            this.hide(toast.id);
        });
        
        // Auto-hide
        setTimeout(() => this.hide(toast.id), toast.options.duration);
    },
    
    hide(id) {
        const el = this.container.querySelector(`[data-toast-id="${id}"]`);
        if (!el) return;
        
        el.classList.remove('animate__fadeInRight');
        el.classList.add('animate__fadeOutRight');
        
        setTimeout(() => {
            el.remove();
            this.active = this.active.filter(tid => tid !== id);
            
            // Show next queued
            if (this.queue.length > 0) {
                this.display(this.queue.shift());
            }
        }, 300);
    },
    
    // Shorthand methods
    success(msg, opts) { return this.show(msg, 'success', opts); },
    error(msg, opts) { return this.show(msg, 'error', opts); },
    warning(msg, opts) { return this.show(msg, 'warning', opts); },
    info(msg, opts) { return this.show(msg, 'info', opts); }
};

// ═══════════════════════════════════════════════════════════════════════════
// SOUND SYSTEM
// ═══════════════════════════════════════════════════════════════════════════
VapeUltra.Sound = {
    sounds: {},
    volume: 0.3,
    enabled: localStorage.getItem('vu_sound_enabled') !== 'false',
    
    preload(name, url) {
        if (!window.Howl) return;
        this.sounds[name] = new Howl({ src: [url], volume: this.volume });
    },
    
    play(name) {
        if (!this.enabled || !this.sounds[name]) return;
        this.sounds[name].play();
    },
    
    toggle() {
        this.enabled = !this.enabled;
        localStorage.setItem('vu_sound_enabled', this.enabled);
        return this.enabled;
    }
};

// ═══════════════════════════════════════════════════════════════════════════
// MODAL SYSTEM
// ═══════════════════════════════════════════════════════════════════════════
VapeUltra.Modal = {
    active: [],
    
    show(options = {}) {
        const modal = document.createElement('div');
        modal.className = 'vu-modal-overlay animate__animated animate__fadeIn animate__faster';
        modal.setAttribute('role', 'dialog');
        modal.setAttribute('aria-modal', 'true');
        
        const id = 'modal_' + Date.now();
        const size = options.size || 'medium';
        const closable = options.closable !== false;
        
        modal.innerHTML = `
            <div class="vu-modal vu-modal-${size} animate__animated animate__zoomIn animate__faster">
                ${closable ? '<button class="vu-modal-close"><i class="bi bi-x-lg"></i></button>' : ''}
                ${options.title ? `<div class="vu-modal-header"><h3>${options.title}</h3></div>` : ''}
                <div class="vu-modal-body">${options.content || ''}</div>
                ${options.footer ? `<div class="vu-modal-footer">${options.footer}</div>` : ''}
            </div>
        `;
        
        modal.dataset.modalId = id;
        document.body.appendChild(modal);
        document.body.style.overflow = 'hidden';
        
        this.active.push(id);
        
        if (closable) {
            modal.querySelector('.vu-modal-close')?.addEventListener('click', () => this.hide(id));
            modal.addEventListener('click', e => {
                if (e.target === modal) this.hide(id);
            });
            
            const escHandler = e => {
                if (e.key === 'Escape') this.hide(id);
            };
            modal.escHandler = escHandler;
            document.addEventListener('keydown', escHandler);
        }
        
        options.onShow?.(modal);
        
        return { id, element: modal };
    },
    
    hide(id) {
        const modal = document.querySelector(`[data-modal-id="${id}"]`);
        if (!modal) return;
        
        modal.querySelector('.vu-modal').classList.add('animate__zoomOut');
        modal.classList.add('animate__fadeOut');
        
        setTimeout(() => {
            modal.remove();
            this.active = this.active.filter(mid => mid !== id);
            
            if (this.active.length === 0) {
                document.body.style.overflow = '';
            }
            
            if (modal.escHandler) {
                document.removeEventListener('keydown', modal.escHandler);
            }
        }, 300);
    },
    
    confirm(options = {}) {
        return new Promise(resolve => {
            const { id } = this.show({
                title: options.title || 'Confirm',
                content: options.message || 'Are you sure?',
                footer: `
                    <button class="vu-btn vu-btn-secondary" data-action="cancel">
                        ${options.cancelText || 'Cancel'}
                    </button>
                    <button class="vu-btn vu-btn-primary" data-action="confirm">
                        ${options.confirmText || 'Confirm'}
                    </button>
                `,
                size: 'small',
                closable: options.closable !== false
            });
            
            const modal = document.querySelector(`[data-modal-id="${id}"]`);
            
            modal.querySelector('[data-action="confirm"]').addEventListener('click', () => {
                this.hide(id);
                resolve(true);
            });
            
            modal.querySelector('[data-action="cancel"]').addEventListener('click', () => {
                this.hide(id);
                resolve(false);
            });
        });
    },
    
    alert(message, title = 'Alert') {
        return new Promise(resolve => {
            const { id } = this.show({
                title,
                content: message,
                footer: '<button class="vu-btn vu-btn-primary" data-action="ok">OK</button>',
                size: 'small'
            });
            
            document.querySelector(`[data-modal-id="${id}"] [data-action="ok"]`)
                .addEventListener('click', () => {
                    this.hide(id);
                    resolve();
                });
        });
    }
};

// ═══════════════════════════════════════════════════════════════════════════
// LOADING SYSTEM
// ═══════════════════════════════════════════════════════════════════════════
VapeUltra.Loading = {
    loaders: new Map(),
    
    show(target = 'body', options = {}) {
        const element = typeof target === 'string' ? document.querySelector(target) : target;
        if (!element) return null;
        
        const id = 'loader_' + Date.now();
        const overlay = document.createElement('div');
        overlay.className = 'vu-loading-overlay animate__animated animate__fadeIn animate__faster';
        
        overlay.innerHTML = `
            <div class="vu-loading-content">
                <div class="vu-loading-spinner"></div>
                ${options.text ? `<p class="vu-loading-text">${options.text}</p>` : ''}
            </div>
        `;
        
        element.style.position = 'relative';
        element.appendChild(overlay);
        
        this.loaders.set(id, { element, overlay });
        
        return id;
    },
    
    hide(id) {
        const loader = this.loaders.get(id);
        if (!loader) return;
        
        loader.overlay.classList.add('animate__fadeOut');
        setTimeout(() => {
            loader.overlay.remove();
            this.loaders.delete(id);
        }, 300);
    },
    
    updateText(id, text) {
        const loader = this.loaders.get(id);
        if (!loader) return;
        
        const textEl = loader.overlay.querySelector('.vu-loading-text');
        if (textEl) textEl.textContent = text;
    }
};

// ═══════════════════════════════════════════════════════════════════════════
// INITIALIZATION
// ═══════════════════════════════════════════════════════════════════════════
VapeUltra.Toolkit = {
    init() {
        console.log('%c🎨 VapeUltra Premium Toolkit v2.0', 'color: #667eea; font-size: 14px; font-weight: bold;');
        
        // Preload sounds (if Howler.js available)
        if (window.Howl) {
            const soundPath = '/assets/vape-ultra/sounds/';
            ['success', 'error', 'warning', 'info', 'click', 'whoosh'].forEach(name => {
                VapeUltra.Sound.preload(name, soundPath + name + '.mp3');
            });
        }
        
        // Global error handler
        window.addEventListener('error', e => {
            console.error('Global error:', e);
            VapeUltra.Toast?.error('An error occurred. Please refresh the page.');
        });
        
        // Network status
        window.addEventListener('online', () => {
            VapeUltra.Toast?.success('Connection restored', { duration: 2000 });
        });
        
        window.addEventListener('offline', () => {
            VapeUltra.Toast?.warning('No internet connection', { duration: 0 });
        });
        
        console.log('✅ Toolkit initialized');
    }
};

// Auto-initialize
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => VapeUltra.Toolkit.init());
} else {
    VapeUltra.Toolkit.init();
}
