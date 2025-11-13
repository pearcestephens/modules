/**
 * ═══════════════════════════════════════════════════════════════════════════
 * VAPEULTRA THEME CUSTOMIZER v1.0
 * ═══════════════════════════════════════════════════════════════════════════
 * 
 * Visual color palette customizer
 * Real-time theme preview
 * Save/Load presets
 * Export CSS variables
 * 
 * @author Ecigdis Limited
 * @version 1.0.0
 */

window.VapeUltra = window.VapeUltra || {};

VapeUltra.ThemeCustomizer = {
    panel: null,
    isOpen: false,
    
    // Default color palette
    colors: {
        primary: '#2563eb',
        primaryHover: '#1d4ed8',
        secondary: '#6b7280',
        secondaryHover: '#4b5563',
        success: '#059669',
        successHover: '#047857',
        danger: '#dc2626',
        dangerHover: '#b91c1c',
        warning: '#d97706',
        warningHover: '#b45309',
        info: '#2563eb',
        infoHover: '#1d4ed8',
        
        // Backgrounds
        toastSuccessBg: '#f0fdf4',
        toastErrorBg: '#fef2f2',
        toastWarningBg: '#fffbeb',
        toastInfoBg: '#eff6ff',
        
        modalHeaderBg: '#f9fafb',
        modalBodyBg: '#ffffff',
        
        // Text colors
        textDark: '#111827',
        textMedium: '#374151',
        textLight: '#6b7280',
        
        // Borders
        borderLight: '#e5e7eb',
        borderMedium: '#d1d5db',
        borderDark: '#9ca3af'
    },
    
    presets: {
        'Professional Blue': {
            primary: '#2563eb',
            success: '#059669',
            danger: '#dc2626',
            warning: '#d97706'
        },
        'Corporate Gray': {
            primary: '#4b5563',
            success: '#10b981',
            danger: '#ef4444',
            warning: '#f59e0b'
        },
        'Modern Purple': {
            primary: '#7c3aed',
            success: '#10b981',
            danger: '#ef4444',
            warning: '#f59e0b'
        },
        'Tech Teal': {
            primary: '#0d9488',
            success: '#059669',
            danger: '#dc2626',
            warning: '#d97706'
        },
        'Executive Dark': {
            primary: '#1f2937',
            success: '#047857',
            danger: '#991b1b',
            warning: '#92400e'
        },
        'Retail Red': {
            primary: '#dc2626',
            success: '#059669',
            danger: '#7f1d1d',
            warning: '#d97706'
        }
    },
    
    init() {
        // Load saved colors
        this.loadColors();
        
        // Apply colors
        this.applyColors();
        
        // Create customizer panel
        this.createPanel();
        
        // Create toggle button
        this.createToggleButton();
        
        console.log('🎨 Theme Customizer initialized');
    },
    
    createToggleButton() {
        const btn = document.createElement('button');
        btn.className = 'vu-theme-toggle';
        btn.innerHTML = '<i class="bi bi-palette-fill"></i>';
        btn.title = 'Customize Theme Colors';
        btn.onclick = () => this.toggle();
        document.body.appendChild(btn);
    },
    
    createPanel() {
        this.panel = document.createElement('div');
        this.panel.className = 'vu-theme-customizer';
        this.panel.innerHTML = `
            <div class="vu-theme-customizer-header">
                <h3><i class="bi bi-palette"></i> Theme Customizer</h3>
                <button class="vu-theme-close" onclick="VapeUltra.ThemeCustomizer.close()">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
            
            <div class="vu-theme-customizer-body">
                <!-- Presets -->
                <div class="vu-theme-section">
                    <h4>Quick Presets</h4>
                    <div class="vu-theme-presets" id="theme-presets"></div>
                </div>
                
                <!-- Button Colors -->
                <div class="vu-theme-section">
                    <h4>Button Colors</h4>
                    <div class="vu-theme-color-grid">
                        <div class="vu-theme-color-item">
                            <label>Primary</label>
                            <input type="color" id="color-primary" value="${this.colors.primary}">
                            <span class="vu-theme-color-value">${this.colors.primary}</span>
                        </div>
                        <div class="vu-theme-color-item">
                            <label>Success</label>
                            <input type="color" id="color-success" value="${this.colors.success}">
                            <span class="vu-theme-color-value">${this.colors.success}</span>
                        </div>
                        <div class="vu-theme-color-item">
                            <label>Danger</label>
                            <input type="color" id="color-danger" value="${this.colors.danger}">
                            <span class="vu-theme-color-value">${this.colors.danger}</span>
                        </div>
                        <div class="vu-theme-color-item">
                            <label>Warning</label>
                            <input type="color" id="color-warning" value="${this.colors.warning}">
                            <span class="vu-theme-color-value">${this.colors.warning}</span>
                        </div>
                    </div>
                </div>
                
                <!-- Toast Backgrounds -->
                <div class="vu-theme-section">
                    <h4>Toast Backgrounds</h4>
                    <div class="vu-theme-color-grid">
                        <div class="vu-theme-color-item">
                            <label>Success BG</label>
                            <input type="color" id="color-toastSuccessBg" value="${this.colors.toastSuccessBg}">
                            <span class="vu-theme-color-value">${this.colors.toastSuccessBg}</span>
                        </div>
                        <div class="vu-theme-color-item">
                            <label>Error BG</label>
                            <input type="color" id="color-toastErrorBg" value="${this.colors.toastErrorBg}">
                            <span class="vu-theme-color-value">${this.colors.toastErrorBg}</span>
                        </div>
                        <div class="vu-theme-color-item">
                            <label>Warning BG</label>
                            <input type="color" id="color-toastWarningBg" value="${this.colors.toastWarningBg}">
                            <span class="vu-theme-color-value">${this.colors.toastWarningBg}</span>
                        </div>
                        <div class="vu-theme-color-item">
                            <label>Info BG</label>
                            <input type="color" id="color-toastInfoBg" value="${this.colors.toastInfoBg}">
                            <span class="vu-theme-color-value">${this.colors.toastInfoBg}</span>
                        </div>
                    </div>
                </div>
                
                <!-- Preview -->
                <div class="vu-theme-section">
                    <h4>Preview</h4>
                    <div class="vu-theme-preview">
                        <button class="vu-btn vu-btn-primary" onclick="VapeUltra.Toast.success('Success message!')">
                            <i class="bi bi-check-circle"></i> Primary Button
                        </button>
                        <button class="vu-btn vu-btn-success" onclick="VapeUltra.Toast.success('Success!')">
                            <i class="bi bi-check"></i> Success
                        </button>
                        <button class="vu-btn vu-btn-danger" onclick="VapeUltra.Toast.error('Error!')">
                            <i class="bi bi-x"></i> Danger
                        </button>
                        <button class="vu-btn vu-btn-secondary">
                            Secondary
                        </button>
                    </div>
                </div>
                
                <!-- Actions -->
                <div class="vu-theme-section">
                    <h4>Actions</h4>
                    <div class="vu-theme-actions">
                        <button class="vu-btn vu-btn-secondary" onclick="VapeUltra.ThemeCustomizer.reset()">
                            <i class="bi bi-arrow-counterclockwise"></i> Reset to Default
                        </button>
                        <button class="vu-btn vu-btn-primary" onclick="VapeUltra.ThemeCustomizer.exportCSS()">
                            <i class="bi bi-download"></i> Export CSS
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(this.panel);
        
        // Generate presets
        this.renderPresets();
        
        // Attach color change listeners
        this.attachListeners();
    },
    
    renderPresets() {
        const container = document.getElementById('theme-presets');
        
        Object.keys(this.presets).forEach(name => {
            const btn = document.createElement('button');
            btn.className = 'vu-theme-preset-btn';
            btn.innerHTML = `
                <div class="vu-theme-preset-colors">
                    <span style="background: ${this.presets[name].primary}"></span>
                    <span style="background: ${this.presets[name].success}"></span>
                    <span style="background: ${this.presets[name].danger}"></span>
                    <span style="background: ${this.presets[name].warning}"></span>
                </div>
                <span class="vu-theme-preset-name">${name}</span>
            `;
            btn.onclick = () => this.applyPreset(name);
            container.appendChild(btn);
        });
    },
    
    attachListeners() {
        const colorInputs = this.panel.querySelectorAll('input[type="color"]');
        
        colorInputs.forEach(input => {
            input.addEventListener('input', (e) => {
                const colorKey = e.target.id.replace('color-', '');
                this.colors[colorKey] = e.target.value;
                
                // Update displayed value
                const valueSpan = e.target.nextElementSibling;
                if (valueSpan) valueSpan.textContent = e.target.value;
                
                // Apply colors
                this.applyColors();
                
                // Save to localStorage
                this.saveColors();
            });
        });
    },
    
    applyPreset(presetName) {
        const preset = this.presets[presetName];
        
        Object.keys(preset).forEach(key => {
            this.colors[key] = preset[key];
            
            // Update hover colors
            this.colors[key + 'Hover'] = this.darkenColor(preset[key], 20);
            
            // Update input if exists
            const input = document.getElementById('color-' + key);
            if (input) {
                input.value = preset[key];
                const valueSpan = input.nextElementSibling;
                if (valueSpan) valueSpan.textContent = preset[key];
            }
        });
        
        this.applyColors();
        this.saveColors();
        
        VapeUltra.Toast?.success(`Applied "${presetName}" preset`, { duration: 2000 });
    },
    
    applyColors() {
        const root = document.documentElement;
        
        // Apply CSS custom properties
        root.style.setProperty('--vu-primary', this.colors.primary);
        root.style.setProperty('--vu-primary-hover', this.colors.primaryHover || this.darkenColor(this.colors.primary, 20));
        root.style.setProperty('--vu-success', this.colors.success);
        root.style.setProperty('--vu-success-hover', this.colors.successHover || this.darkenColor(this.colors.success, 20));
        root.style.setProperty('--vu-danger', this.colors.danger);
        root.style.setProperty('--vu-danger-hover', this.colors.dangerHover || this.darkenColor(this.colors.danger, 20));
        root.style.setProperty('--vu-warning', this.colors.warning);
        root.style.setProperty('--vu-warning-hover', this.colors.warningHover || this.darkenColor(this.colors.warning, 20));
        
        root.style.setProperty('--vu-toast-success-bg', this.colors.toastSuccessBg);
        root.style.setProperty('--vu-toast-error-bg', this.colors.toastErrorBg);
        root.style.setProperty('--vu-toast-warning-bg', this.colors.toastWarningBg);
        root.style.setProperty('--vu-toast-info-bg', this.colors.toastInfoBg);
    },
    
    darkenColor(hex, percent) {
        const num = parseInt(hex.slice(1), 16);
        const amt = Math.round(2.55 * percent);
        const R = (num >> 16) - amt;
        const G = (num >> 8 & 0x00FF) - amt;
        const B = (num & 0x0000FF) - amt;
        return '#' + (0x1000000 + (R < 255 ? R < 1 ? 0 : R : 255) * 0x10000 +
            (G < 255 ? G < 1 ? 0 : G : 255) * 0x100 +
            (B < 255 ? B < 1 ? 0 : B : 255))
            .toString(16).slice(1).toUpperCase();
    },
    
    saveColors() {
        localStorage.setItem('vu_theme_colors', JSON.stringify(this.colors));
    },
    
    loadColors() {
        const saved = localStorage.getItem('vu_theme_colors');
        if (saved) {
            try {
                this.colors = { ...this.colors, ...JSON.parse(saved) };
            } catch (e) {
                console.warn('Failed to load saved colors');
            }
        }
    },
    
    reset() {
        if (confirm('Reset all colors to default?')) {
            localStorage.removeItem('vu_theme_colors');
            location.reload();
        }
    },
    
    exportCSS() {
        const css = `:root {
    /* Button Colors */
    --vu-primary: ${this.colors.primary};
    --vu-primary-hover: ${this.colors.primaryHover || this.darkenColor(this.colors.primary, 20)};
    --vu-success: ${this.colors.success};
    --vu-success-hover: ${this.colors.successHover || this.darkenColor(this.colors.success, 20)};
    --vu-danger: ${this.colors.danger};
    --vu-danger-hover: ${this.colors.dangerHover || this.darkenColor(this.colors.danger, 20)};
    --vu-warning: ${this.colors.warning};
    --vu-warning-hover: ${this.colors.warningHover || this.darkenColor(this.colors.warning, 20)};
    
    /* Toast Backgrounds */
    --vu-toast-success-bg: ${this.colors.toastSuccessBg};
    --vu-toast-error-bg: ${this.colors.toastErrorBg};
    --vu-toast-warning-bg: ${this.colors.toastWarningBg};
    --vu-toast-info-bg: ${this.colors.toastInfoBg};
}

/* Apply to buttons */
.vu-btn-primary {
    background: var(--vu-primary);
    border-color: var(--vu-primary);
}
.vu-btn-primary:hover {
    background: var(--vu-primary-hover);
    border-color: var(--vu-primary-hover);
}

.vu-btn-success {
    background: var(--vu-success);
    border-color: var(--vu-success);
}
.vu-btn-success:hover {
    background: var(--vu-success-hover);
    border-color: var(--vu-success-hover);
}

.vu-btn-danger {
    background: var(--vu-danger);
    border-color: var(--vu-danger);
}
.vu-btn-danger:hover {
    background: var(--vu-danger-hover);
    border-color: var(--vu-danger-hover);
}

/* Toast backgrounds */
.vu-toast-success { background: var(--vu-toast-success-bg); }
.vu-toast-error { background: var(--vu-toast-error-bg); }
.vu-toast-warning { background: var(--vu-toast-warning-bg); }
.vu-toast-info { background: var(--vu-toast-info-bg); }
`;
        
        // Download as file
        const blob = new Blob([css], { type: 'text/css' });
        const url = URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = 'vapeultra-custom-theme.css';
        a.click();
        URL.revokeObjectURL(url);
        
        VapeUltra.Toast?.success('Theme CSS exported!', { duration: 3000 });
    },
    
    toggle() {
        if (this.isOpen) {
            this.close();
        } else {
            this.open();
        }
    },
    
    open() {
        this.panel.classList.add('vu-theme-customizer-open');
        this.isOpen = true;
    },
    
    close() {
        this.panel.classList.remove('vu-theme-customizer-open');
        this.isOpen = false;
    }
};

// Auto-initialize
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => VapeUltra.ThemeCustomizer.init());
} else {
    VapeUltra.ThemeCustomizer.init();
}
