/**
 * CIS Advanced Barcode Scanner Widget v2.0
 *
 * Features:
 * - Compact icon that expands to full scanner panel
 * - Multiple layout modes (bottom bar, right sidebar, full-screen mobile)
 * - Remembers state in localStorage
 * - Photo capture mode for damage documentation
 * - Context-aware (knows when page expects photos)
 * - Enhanced audio feedback (7+ different tones)
 * - Scan history with local + remote storage
 * - Works on all pages with per-transfer-type config
 */

class CISAdvancedBarcodeWidget {
    constructor(options = {}) {
        this.options = {
            transferId: options.transferId || null,
            transferType: options.transferType || 'stock_transfer', // stock_transfer, consignment, purchase_order
            userId: options.userId || null,
            outletId: options.outletId || null,
            onScan: options.onScan || this.defaultOnScan.bind(this),
            onPhoto: options.onPhoto || this.defaultOnPhoto.bind(this),
            onError: options.onError || this.defaultOnError.bind(this),
            ...options
        };

        this.config = null;
        this.scanner = null;
        this.isExpanded = false;
        this.mode = 'barcode'; // 'barcode' or 'photo'
        this.layoutMode = 'bottom'; // 'bottom', 'right', 'fullscreen'
        this.audioContext = null;
        this.scanHistory = [];
        this.sessionStats = {
            totalScans: 0,
            successfulScans: 0,
            failedScans: 0,
            photosTaken: 0,
            sessionStart: new Date()
        };
        this.expectedPhotos = []; // Products that need photos

        this.init();
    }

    /**
     * Initialize widget
     */
    async init() {
        // Load saved state
        this.loadState();

        // Load configuration
        await this.loadConfig();

        // Check if scanner enabled for this transfer type
        if (!this.isEnabledForTransferType()) {
            console.log(`[Scanner Widget] Disabled for transfer type: ${this.options.transferType}`);
            return;
        }

        // Create widget UI
        this.createWidget();

        // Initialize audio
        this.initAudio();

        // Setup scanner
        await this.initScanner();

        // Restore previous state
        if (this.isExpanded) {
            this.expand();
        }

        console.log('[Scanner Widget] Initialized', {
            transferType: this.options.transferType,
            layoutMode: this.layoutMode,
            expanded: this.isExpanded
        });
    }

    /**
     * Load configuration from API
     */
    async loadConfig() {
        try {
            const response = await fetch('/modules/consignments/api/barcode_config.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'get_effective_config',
                    user_id: this.options.userId,
                    outlet_id: this.options.outletId,
                    transfer_type: this.options.transferType
                })
            });

            const result = await response.json();
            this.config = result.success ? result.effective_config : this.getDefaultConfig();
        } catch (error) {
            console.error('[Scanner Widget] Config load failed:', error);
            this.config = this.getDefaultConfig();
        }
    }

    /**
     * Check if scanner enabled for this transfer type
     */
    isEnabledForTransferType() {
        if (!this.config || !this.config.enabled) return false;

        const typeKey = `enabled_${this.options.transferType}`;
        return this.config[typeKey] !== false; // Default to true if not specified
    }

    /**
     * Get default configuration
     */
    getDefaultConfig() {
        return {
            enabled: true,
            enabled_stock_transfer: true,
            enabled_consignment: true,
            enabled_purchase_order: true,
            usb_scanner_enabled: true,
            camera_scanner_enabled: true,
            photo_mode_enabled: true,
            audio_enabled: true,
            audio_volume: 0.6
        };
    }

    /**
     * Load saved state from localStorage
     */
    loadState() {
        try {
            const saved = localStorage.getItem('cis_scanner_state');
            if (saved) {
                const state = JSON.parse(saved);
                this.isExpanded = state.isExpanded || false;
                this.layoutMode = state.layoutMode || this.detectLayoutMode();
                this.scanHistory = state.scanHistory || [];
            } else {
                this.layoutMode = this.detectLayoutMode();
            }
        } catch (error) {
            console.error('[Scanner Widget] Failed to load state:', error);
            this.layoutMode = this.detectLayoutMode();
        }
    }

    /**
     * Save state to localStorage
     */
    saveState() {
        try {
            localStorage.setItem('cis_scanner_state', JSON.stringify({
                isExpanded: this.isExpanded,
                layoutMode: this.layoutMode,
                scanHistory: this.scanHistory.slice(-50) // Keep last 50 scans
            }));
        } catch (error) {
            console.error('[Scanner Widget] Failed to save state:', error);
        }
    }

    /**
     * Detect best layout mode based on screen size
     */
    detectLayoutMode() {
        if (window.innerWidth < 768) {
            return 'fullscreen'; // Mobile
        } else if (window.innerWidth < 1200) {
            return 'bottom'; // Tablet
        } else {
            return 'right'; // Desktop
        }
    }

    /**
     * Create widget UI
     */
    createWidget() {
        // Add styles
        this.injectStyles();

        // Create compact icon
        this.createCompactIcon();

        // Create expanded panel (hidden initially)
        this.createExpandedPanel();

        // Setup responsive handler
        window.addEventListener('resize', () => {
            const newMode = this.detectLayoutMode();
            if (newMode !== this.layoutMode && this.isExpanded) {
                this.layoutMode = newMode;
                this.updatePanelLayout();
            }
        });
    }

    /**
     * Inject CSS styles
     */
    injectStyles() {
        const style = document.createElement('style');
        style.textContent = `
            /* Compact Icon */
            .cis-scanner-icon {
                position: fixed;
                bottom: 20px;
                right: 20px;
                width: 56px;
                height: 56px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                border-radius: 50%;
                box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                z-index: 9998;
                transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
                animation: pulse 2s infinite;
            }

            .cis-scanner-icon:hover {
                transform: scale(1.1);
                box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
            }

            .cis-scanner-icon svg {
                width: 28px;
                height: 28px;
                fill: white;
            }

            .cis-scanner-icon.expanded {
                transform: scale(0);
                opacity: 0;
                pointer-events: none;
            }

            @keyframes pulse {
                0%, 100% { box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4); }
                50% { box-shadow: 0 4px 20px rgba(102, 126, 234, 0.8); }
            }

            /* Badge for unread history */
            .cis-scanner-badge {
                position: absolute;
                top: -4px;
                right: -4px;
                background: #ff4757;
                color: white;
                border-radius: 10px;
                padding: 2px 6px;
                font-size: 10px;
                font-weight: bold;
                min-width: 18px;
                text-align: center;
            }

            /* Expanded Panel - Bottom Mode */
            .cis-scanner-panel {
                position: fixed;
                background: white;
                box-shadow: 0 -4px 20px rgba(0,0,0,0.15);
                z-index: 9999;
                transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
                font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            }

            .cis-scanner-panel.bottom {
                bottom: 0;
                left: 0;
                right: 0;
                height: 280px;
                transform: translateY(100%);
            }

            .cis-scanner-panel.bottom.visible {
                transform: translateY(0);
            }

            .cis-scanner-panel.right {
                top: 0;
                right: 0;
                bottom: 0;
                width: 380px;
                transform: translateX(100%);
            }

            .cis-scanner-panel.right.visible {
                transform: translateX(0);
            }

            .cis-scanner-panel.fullscreen {
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                transform: scale(0.8);
                opacity: 0;
            }

            .cis-scanner-panel.fullscreen.visible {
                transform: scale(1);
                opacity: 1;
            }

            /* Panel Header */
            .cis-scanner-header {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 12px 16px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                user-select: none;
            }

            .cis-scanner-title {
                font-size: 16px;
                font-weight: 600;
                display: flex;
                align-items: center;
                gap: 8px;
            }

            .cis-scanner-controls {
                display: flex;
                gap: 8px;
            }

            .cis-scanner-btn {
                background: rgba(255,255,255,0.2);
                border: none;
                color: white;
                width: 32px;
                height: 32px;
                border-radius: 6px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: all 0.2s;
            }

            .cis-scanner-btn:hover {
                background: rgba(255,255,255,0.3);
            }

            .cis-scanner-btn.active {
                background: rgba(255,255,255,0.4);
            }

            /* Panel Content */
            .cis-scanner-content {
                height: calc(100% - 56px);
                display: flex;
                flex-direction: column;
                overflow: hidden;
            }

            /* Mode Tabs */
            .cis-scanner-tabs {
                display: flex;
                border-bottom: 1px solid #e1e4e8;
                background: #f6f8fa;
            }

            .cis-scanner-tab {
                flex: 1;
                padding: 12px;
                text-align: center;
                cursor: pointer;
                border: none;
                background: none;
                font-size: 14px;
                font-weight: 500;
                color: #586069;
                transition: all 0.2s;
                border-bottom: 3px solid transparent;
            }

            .cis-scanner-tab:hover {
                background: #fff;
                color: #24292e;
            }

            .cis-scanner-tab.active {
                background: white;
                color: #667eea;
                border-bottom-color: #667eea;
            }

            /* Scanner Body */
            .cis-scanner-body {
                flex: 1;
                overflow-y: auto;
                padding: 16px;
            }

            /* Camera Preview */
            .cis-camera-preview {
                width: 100%;
                background: #000;
                border-radius: 8px;
                overflow: hidden;
                position: relative;
                min-height: 200px;
            }

            .cis-camera-preview video {
                width: 100%;
                display: block;
            }

            .cis-camera-overlay {
                position: absolute;
                top: 50%;
                left: 50%;
                transform: translate(-50%, -50%);
                width: 80%;
                height: 60%;
                border: 2px dashed #fff;
                border-radius: 8px;
                pointer-events: none;
            }

            /* Scan History */
            .cis-scan-history {
                display: flex;
                flex-direction: column;
                gap: 8px;
            }

            .cis-scan-item {
                display: flex;
                align-items: center;
                gap: 12px;
                padding: 12px;
                background: #f6f8fa;
                border-radius: 6px;
                border-left: 3px solid #28a745;
                transition: all 0.2s;
            }

            .cis-scan-item:hover {
                background: #e1e4e8;
            }

            .cis-scan-item.failed {
                border-left-color: #dc3545;
            }

            .cis-scan-icon {
                width: 40px;
                height: 40px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                background: white;
                flex-shrink: 0;
            }

            .cis-scan-details {
                flex: 1;
                min-width: 0;
            }

            .cis-scan-barcode {
                font-weight: 600;
                font-size: 14px;
                color: #24292e;
                font-family: 'Courier New', monospace;
            }

            .cis-scan-product {
                font-size: 12px;
                color: #586069;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }

            .cis-scan-time {
                font-size: 11px;
                color: #959da5;
            }

            /* Stats Display */
            .cis-stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
                gap: 12px;
                margin-bottom: 16px;
            }

            .cis-stat-card {
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                padding: 16px;
                border-radius: 8px;
                text-align: center;
            }

            .cis-stat-value {
                font-size: 28px;
                font-weight: 700;
                margin-bottom: 4px;
            }

            .cis-stat-label {
                font-size: 11px;
                opacity: 0.9;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            /* Action Buttons */
            .cis-action-buttons {
                display: flex;
                gap: 8px;
                margin-top: 12px;
            }

            .cis-action-btn {
                flex: 1;
                padding: 12px;
                border: none;
                border-radius: 6px;
                font-size: 14px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.2s;
            }

            .cis-action-btn.primary {
                background: #667eea;
                color: white;
            }

            .cis-action-btn.primary:hover {
                background: #5568d3;
            }

            .cis-action-btn.secondary {
                background: #e1e4e8;
                color: #24292e;
            }

            .cis-action-btn.secondary:hover {
                background: #d1d5da;
            }

            /* Toast Notifications */
            .cis-toast {
                position: fixed;
                top: 20px;
                right: 20px;
                background: white;
                padding: 16px 20px;
                border-radius: 8px;
                box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                z-index: 10000;
                display: flex;
                align-items: center;
                gap: 12px;
                min-width: 300px;
                animation: slideInRight 0.3s ease;
            }

            @keyframes slideInRight {
                from {
                    transform: translateX(400px);
                    opacity: 0;
                }
                to {
                    transform: translateX(0);
                    opacity: 1;
                }
            }

            .cis-toast.success {
                border-left: 4px solid #28a745;
            }

            .cis-toast.error {
                border-left: 4px solid #dc3545;
            }

            .cis-toast.warning {
                border-left: 4px solid #ffc107;
            }

            /* Mobile Optimizations */
            @media (max-width: 768px) {
                .cis-scanner-icon {
                    width: 64px;
                    height: 64px;
                    bottom: 80px;
                }

                .cis-scanner-panel.fullscreen .cis-scanner-body {
                    padding: 12px;
                }

                .cis-camera-preview {
                    min-height: 300px;
                }

                .cis-action-btn {
                    padding: 16px;
                    font-size: 16px;
                }
            }
        `;
        document.head.appendChild(style);
    }

    /**
     * Create compact icon button
     */
    createCompactIcon() {
        const icon = document.createElement('div');
        icon.className = 'cis-scanner-icon';
        icon.innerHTML = `
            <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path d="M4 6h16v2H4V6zm0 4h16v2H4v-2zm0 4h16v2H4v-2zm0 4h16v2H4v-2z"/>
                <path d="M2 2h20v20H2V2zm2 2v16h16V4H4z" opacity="0.3"/>
            </svg>
        `;

        // Add badge if there's scan history
        if (this.scanHistory.length > 0) {
            const badge = document.createElement('div');
            badge.className = 'cis-scanner-badge';
            badge.textContent = this.scanHistory.length;
            icon.appendChild(badge);
        }

        icon.addEventListener('click', () => this.toggle());

        document.body.appendChild(icon);
        this.iconElement = icon;
    }

    /**
     * Create expanded panel
     */
    createExpandedPanel() {
        const panel = document.createElement('div');
        panel.className = `cis-scanner-panel ${this.layoutMode}`;
        panel.innerHTML = `
            <div class="cis-scanner-header">
                <div class="cis-scanner-title">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M4 6h16v2H4V6zm0 4h16v2H4v-2zm0 4h16v2H4v-2zm0 4h16v2H4v-2z"/>
                    </svg>
                    Turbo Scanner
                </div>
                <div class="cis-scanner-controls">
                    <button class="cis-scanner-btn" id="scannerLayoutBtn" title="Change Layout">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M4 4h6v6H4V4zm10 0h6v6h-6V4zM4 14h6v6H4v-6zm10 0h6v6h-6v-6z"/>
                        </svg>
                    </button>
                    <button class="cis-scanner-btn" id="scannerMinimizeBtn" title="Minimize">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M19 13H5v-2h14v2z"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="cis-scanner-content">
                <div class="cis-scanner-tabs">
                    <button class="cis-scanner-tab active" data-mode="barcode">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="display:inline-block; vertical-align:middle; margin-right:4px;">
                            <path d="M4 6h16v2H4V6zm0 4h16v2H4v-2zm0 4h16v2H4v-2zm0 4h16v2H4v-2z"/>
                        </svg>
                        Barcode
                    </button>
                    <button class="cis-scanner-tab" data-mode="photo">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="display:inline-block; vertical-align:middle; margin-right:4px;">
                            <path d="M21 19V5c0-1.1-.9-2-2-2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2zM8.5 13.5l2.5 3.01L14.5 12l4.5 6H5l3.5-4.5z"/>
                        </svg>
                        Photo
                    </button>
                    <button class="cis-scanner-tab" data-mode="history">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor" style="display:inline-block; vertical-align:middle; margin-right:4px;">
                            <path d="M13 3c-4.97 0-9 4.03-9 9H1l3.89 3.89.07.14L9 12H6c0-3.87 3.13-7 7-7s7 3.13 7 7-3.13 7-7 7c-1.93 0-3.68-.79-4.94-2.06l-1.42 1.42C8.27 19.99 10.51 21 13 21c4.97 0 9-4.03 9-9s-4.03-9-9-9zm-1 5v5l4.28 2.54.72-1.21-3.5-2.08V8H12z"/>
                        </svg>
                        History
                    </button>
                </div>

                <div class="cis-scanner-body" id="scannerBody">
                    <!-- Content dynamically loaded based on active tab -->
                </div>
            </div>
        `;

        document.body.appendChild(panel);
        this.panelElement = panel;

        // Setup event listeners
        this.setupPanelEvents();

        // Load initial content
        this.showBarcodeMode();
    }

    /**
     * Setup panel event listeners
     */
    setupPanelEvents() {
        // Layout button
        this.panelElement.querySelector('#scannerLayoutBtn').addEventListener('click', () => {
            this.cycleLayout();
        });

        // Minimize button
        this.panelElement.querySelector('#scannerMinimizeBtn').addEventListener('click', () => {
            this.collapse();
        });

        // Tab buttons
        this.panelElement.querySelectorAll('.cis-scanner-tab').forEach(tab => {
            tab.addEventListener('click', () => {
                const mode = tab.dataset.mode;
                this.switchMode(mode);
            });
        });
    }

    /**
     * Initialize scanner backend
     */
    async initScanner() {
        this.scanner = new CISBarcodeScanner({
            transferId: this.options.transferId,
            userId: this.options.userId,
            outletId: this.options.outletId,
            container: '#cisScannerCameraPreview',
            onScan: this.handleScan.bind(this),
            onError: this.handleError.bind(this)
        });
    }

    /**
     * Initialize audio context
     */
    initAudio() {
        if (this.config.audio_enabled) {
            this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
        }
    }

    /**
     * Play audio tone
     */
    playTone(type) {
        if (!this.config.audio_enabled || !this.audioContext) return;

        const tones = {
            scan: { freq: 800, duration: 100 },
            success: { freq: 1000, duration: 150 },
            error: { freq: 400, duration: 200 },
            notfound: { freq: 300, duration: 300 },
            complete: { freq: [600, 800, 1000], duration: 100 },
            target: { freq: [1200, 1000], duration: 150 },
            photo: { freq: 1200, duration: 50 }
        };

        const tone = tones[type] || tones.scan;

        if (Array.isArray(tone.freq)) {
            // Multi-tone sequence
            tone.freq.forEach((freq, i) => {
                setTimeout(() => this.playSimpleTone(freq, tone.duration), i * tone.duration);
            });
        } else {
            this.playSimpleTone(tone.freq, tone.duration);
        }
    }

    playSimpleTone(frequency, duration) {
        const oscillator = this.audioContext.createOscillator();
        const gainNode = this.audioContext.createGain();

        oscillator.connect(gainNode);
        gainNode.connect(this.audioContext.destination);

        oscillator.frequency.value = frequency;
        oscillator.type = 'sine';

        gainNode.gain.setValueAtTime(0.3, this.audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, this.audioContext.currentTime + duration / 1000);

        oscillator.start(this.audioContext.currentTime);
        oscillator.stop(this.audioContext.currentTime + duration / 1000);
    }

    /**
     * Handle barcode scan
     */
    async handleScan(barcode) {
        this.sessionStats.totalScans++;
        this.playTone('scan');

        // Add to history
        this.scanHistory.unshift({
            barcode,
            timestamp: new Date(),
            mode: this.mode,
            transferId: this.options.transferId
        });

        // Keep history limited
        if (this.scanHistory.length > 50) {
            this.scanHistory = this.scanHistory.slice(0, 50);
        }

        // Save to storage
        this.saveState();

        // Call user callback
        try {
            const result = await this.options.onScan(barcode);
            if (result.success) {
                this.sessionStats.successfulScans++;
                this.playTone('success');
            } else {
                this.sessionStats.failedScans++;
                this.playTone('notfound');
            }
        } catch (error) {
            this.sessionStats.failedScans++;
            this.playTone('error');
            this.handleError(error);
        }

        // Update stats display
        this.updateStatsDisplay();
    }

    /**
     * Handle photo capture
     */
    async handlePhoto(photoBlob) {
        this.playTone('photo');
        this.sessionStats.photosTaken++;

        try {
            await this.options.onPhoto(photoBlob);
            this.playTone('success');
        } catch (error) {
            this.playTone('error');
            this.handleError(error);
        }

        this.updateStatsDisplay();
    }

    /**
     * Handle error
     */
    handleError(error) {
        console.error('[Scanner Widget] Error:', error);
        this.options.onError(error);
    }

    /**
     * Toggle widget expansion
     */
    toggle() {
        if (this.isExpanded) {
            this.collapse();
        } else {
            this.expand();
        }
    }

    /**
     * Expand widget
     */
    expand() {
        this.isExpanded = true;
        const widget = document.getElementById('cisScannerWidget');
        widget.classList.remove('collapsed');
        widget.classList.add('expanded');

        document.querySelector('.cis-scanner-compact').style.display = 'none';
        document.querySelector('.cis-scanner-expanded').style.display = 'block';

        this.saveState();
        this.startScanner();
    }

    /**
     * Collapse widget
     */
    collapse() {
        this.isExpanded = false;
        const widget = document.getElementById('cisScannerWidget');
        widget.classList.remove('expanded');
        widget.classList.add('collapsed');

        document.querySelector('.cis-scanner-compact').style.display = 'flex';
        document.querySelector('.cis-scanner-expanded').style.display = 'none';

        this.saveState();
        this.stopScanner();
    }    /**
     * Switch mode (barcode/photo)
     */
    switchMode(mode) {
        this.mode = mode;

        // Update button active states
        document.getElementById('btnModeBarcode').classList.toggle('active', mode === 'barcode');
        document.getElementById('btnModePhoto').classList.toggle('active', mode === 'photo');

        // Update body content
        this.updateBodyContent();

        this.saveState();
    }

    /**
     * Cycle through layout modes
     */
    cycleLayout() {
        const modes = ['bottom', 'right', 'fullscreen'];
        const currentIndex = modes.indexOf(this.layoutMode);
        const nextMode = modes[(currentIndex + 1) % modes.length];
        this.setLayoutMode(nextMode);
    }

    /**
     * Set layout mode
     */
    setLayoutMode(mode) {
        this.layoutMode = mode;
        const panel = document.getElementById('cisScannerPanel');
        panel.className = `cis-scanner-panel cis-scanner-${mode}`;
        this.saveState();
    }

    /**
     * Update body content based on mode
     */
    updateBodyContent() {
        const body = document.getElementById('cisScannerBody');

        if (this.mode === 'barcode') {
            body.innerHTML = `
                <div id="cisScannerCameraPreview" class="cis-camera-preview"></div>
                <div class="cis-scanner-status">
                    <div class="cis-scanner-indicator"></div>
                    <span id="cisScannerStatusText">Ready to scan</span>
                </div>
            `;
            this.startScanner();
        } else if (this.mode === 'photo') {
            body.innerHTML = `
                <div class="text-center mb-3">
                    <h6><i class="fas fa-mobile-alt"></i> Upload from Phone</h6>
                    <p class="text-muted small mb-2">Scan QR code with your phone to upload photos</p>
                    <div id="qrCodeContainer" class="qr-code-container">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Generating QR code...</span>
                        </div>
                    </div>
                    <p class="text-muted small mt-2">
                        <i class="far fa-clock"></i> Link expires in <strong id="qrExpiry">15:00</strong>
                    </p>
                </div>
                <div class="text-center">
                    <p class="mb-2"><strong>OR</strong></p>
                    <button class="btn btn-primary btn-sm" onclick="cisBarcodeWidget.showPhotoCapture()">
                        <i class="fas fa-camera"></i> Use PC Camera
                    </button>
                </div>
                <div id="pcPhotoCapture" style="display: none;" class="mt-3">
                    <div id="cisScannerCameraPreview" class="cis-camera-preview"></div>
                    <button class="btn btn-primary btn-lg mt-3 w-100" onclick="cisBarcodeWidget.capturePhoto()">
                        <i class="fas fa-camera"></i> Take Photo
                    </button>
                </div>
            `;
            this.generateQRCode();
        }
    }

    /**
     * Generate QR code for mobile upload
     */
    async generateQRCode() {
        try {
            // Create upload session
            const response = await fetch('/modules/consignments/api/photo_upload_session.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'create_session',
                    transfer_id: this.options.transferId,
                    transfer_type: this.options.transferType,
                    user_id: this.options.userId,
                    outlet_id: this.options.outletId
                })
            });

            const result = await response.json();

            if (result.success) {
                // Generate QR code using Google Charts API
                const qrUrl = `https://chart.googleapis.com/chart?cht=qr&chs=200x200&chl=${encodeURIComponent(result.upload_url)}`;

                document.getElementById('qrCodeContainer').innerHTML = `
                    <img src="${qrUrl}" alt="QR Code" class="qr-code-image">
                    <div class="mt-2">
                        <button class="btn btn-sm btn-outline-primary" onclick="cisBarcodeWidget.regenerateQR()">
                            <i class="fas fa-sync"></i> New Code
                        </button>
                    </div>
                `;

                // Start expiry countdown
                this.startQRExpiry(result.expires_in_seconds);
            } else {
                document.getElementById('qrCodeContainer').innerHTML = `
                    <div class="alert alert-danger">
                        Failed to generate QR code
                    </div>
                `;
            }
        } catch (error) {
            console.error('QR generation failed:', error);
            document.getElementById('qrCodeContainer').innerHTML = `
                <div class="alert alert-danger">
                    Error: ${error.message}
                </div>
            `;
        }
    }

    /**
     * Start QR expiry countdown
     */
    startQRExpiry(seconds) {
        if (this.qrExpiryInterval) {
            clearInterval(this.qrExpiryInterval);
        }

        this.qrExpiryInterval = setInterval(() => {
            seconds--;

            if (seconds <= 0) {
                clearInterval(this.qrExpiryInterval);
                document.getElementById('qrExpiry').textContent = 'EXPIRED';
                document.getElementById('qrExpiry').style.color = '#e74c3c';
                return;
            }

            const minutes = Math.floor(seconds / 60);
            const secs = seconds % 60;
            const timeStr = `${minutes}:${secs.toString().padStart(2, '0')}`;

            const expiryEl = document.getElementById('qrExpiry');
            if (expiryEl) {
                expiryEl.textContent = timeStr;
                if (seconds < 60) {
                    expiryEl.style.color = '#e74c3c';
                } else if (seconds < 300) {
                    expiryEl.style.color = '#f39c12';
                }
            }
        }, 1000);
    }

    /**
     * Regenerate QR code
     */
    regenerateQR() {
        this.generateQRCode();
    }

    /**
     * Show PC photo capture
     */
    showPhotoCapture() {
        document.getElementById('pcPhotoCapture').style.display = 'block';
        this.startScanner();
    }

    /**
     * Capture photo
     */
    async capturePhoto() {
        if (!this.scanner || !this.scanner.stream) {
            alert('Camera not ready');
            return;
        }

        const video = document.querySelector('#cisScannerCameraPreview video');
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const ctx = canvas.getContext('2d');
        ctx.drawImage(video, 0, 0);

        canvas.toBlob(async (blob) => {
            await this.handlePhoto(blob);
        }, 'image/jpeg', 0.9);
    }

    /**
     * Start scanner
     */
    startScanner() {
        if (this.scanner && this.mode === 'barcode') {
            this.scanner.start();
        }
    }

    /**
     * Stop scanner
     */
    stopScanner() {
        if (this.scanner) {
            this.scanner.stop();
        }
    }

    /**
     * Update stats display
     */
    updateStatsDisplay() {
        const statsEl = document.getElementById('cisScannerStats');
        if (statsEl) {
            statsEl.innerHTML = `
                <div class="cis-stat-card">
                    <div class="cis-stat-value">${this.sessionStats.totalScans}</div>
                    <div class="cis-stat-label">Total Scans</div>
                </div>
                <div class="cis-stat-card">
                    <div class="cis-stat-value">${this.sessionStats.successfulScans}</div>
                    <div class="cis-stat-label">Found</div>
                </div>
                <div class="cis-stat-card">
                    <div class="cis-stat-value">${this.sessionStats.photosTaken}</div>
                    <div class="cis-stat-label">Photos</div>
                </div>
            `;
        }

        // Update compact view counter
        const compactCount = document.getElementById('cisScannerCompactCount');
        if (compactCount) {
            compactCount.textContent = this.sessionStats.totalScans;
        }
    }

    /**
     * Save state to localStorage
     */
    saveState() {
        localStorage.setItem('cisScannerWidget', JSON.stringify({
            isExpanded: this.isExpanded,
            mode: this.mode,
            layoutMode: this.layoutMode,
            scanHistory: this.scanHistory.slice(0, 20),
            sessionStats: this.sessionStats
        }));
    }

    /**
     * Load state from localStorage
     */
    loadState() {
        const saved = localStorage.getItem('cisScannerWidget');
        if (saved) {
            try {
                const state = JSON.parse(saved);
                this.isExpanded = state.isExpanded || false;
                this.mode = state.mode || 'barcode';
                this.layoutMode = state.layoutMode || 'bottom';
                this.scanHistory = state.scanHistory || [];
                this.sessionStats = state.sessionStats || this.sessionStats;
            } catch (e) {
                console.error('[Scanner Widget] Failed to load state:', e);
            }
        }
    }

    /**
     * Default scan handler
     */
    defaultOnScan(barcode) {
        console.log('[Scanner Widget] Scanned:', barcode);
        return { success: true, message: 'Barcode scanned' };
    }

    /**
     * Default photo handler
     */
    defaultOnPhoto(photoBlob) {
        console.log('[Scanner Widget] Photo captured:', photoBlob.size, 'bytes');
        return { success: true, message: 'Photo captured' };
    }

    /**
     * Default error handler
     */
    defaultOnError(error) {
        console.error('[Scanner Widget] Error:', error);
    }

    /**
     * Check if enabled for current transfer type
     */
    isEnabledForTransferType() {
        if (!this.config) return true; // Default to enabled if no config

        const setting = this.config[`${this.options.transferType}_enabled`];
        return setting !== false;
    }

    /**
     * Inject CSS styles
     */
    injectStyles() {
        const style = document.createElement('style');
        style.textContent = `
/* CIS Advanced Barcode Scanner Widget Styles */

.cis-scanner-icon {
    position: fixed;
    bottom: 20px;
    right: 20px;
    width: 56px;
    height: 56px;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(102, 126, 234, 0.4);
    z-index: 9999;
    transition: all 0.3s ease;
}

.cis-scanner-icon:hover {
    transform: scale(1.1);
    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
}

.cis-scanner-icon i {
    color: white;
    font-size: 24px;
}

.cis-scanner-icon.pulse {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.05); opacity: 0.8; }
}

.cis-scanner-panel {
    position: fixed;
    background: white;
    box-shadow: 0 -4px 20px rgba(0,0,0,0.15);
    z-index: 9998;
    display: none;
    border-radius: 12px 12px 0 0;
}

.cis-scanner-panel.cis-scanner-bottom {
    bottom: 0;
    left: 0;
    right: 0;
    height: 280px;
}

.cis-scanner-panel.cis-scanner-right {
    top: 0;
    right: 0;
    width: 380px;
    height: 100vh;
    border-radius: 0;
}

.cis-scanner-panel.cis-scanner-fullscreen {
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    border-radius: 0;
}

.cis-scanner-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 12px 16px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    border-radius: 12px 12px 0 0;
}

.cis-scanner-header h4 {
    margin: 0;
    font-size: 16px;
    font-weight: 600;
}

.cis-scanner-controls {
    display: flex;
    gap: 8px;
}

.cis-scanner-controls button {
    background: rgba(255,255,255,0.2);
    border: none;
    color: white;
    width: 32px;
    height: 32px;
    border-radius: 6px;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: background 0.2s;
}

.cis-scanner-controls button:hover {
    background: rgba(255,255,255,0.3);
}

.cis-scanner-tabs {
    display: flex;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
}

.cis-scanner-tab {
    flex: 1;
    padding: 12px;
    text-align: center;
    cursor: pointer;
    border: none;
    background: transparent;
    font-weight: 500;
    color: #6c757d;
    transition: all 0.2s;
}

.cis-scanner-tab.active {
    color: #667eea;
    border-bottom: 2px solid #667eea;
}

.cis-scanner-body {
    padding: 16px;
    overflow-y: auto;
    flex: 1;
}

.cis-camera-preview {
    width: 100%;
    height: 200px;
    background: #000;
    border-radius: 8px;
    position: relative;
    overflow: hidden;
}

.cis-camera-preview video {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.cis-scanner-status {
    display: flex;
    align-items: center;
    gap: 12px;
    margin-top: 12px;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 8px;
}

.cis-scanner-indicator {
    width: 12px;
    height: 12px;
    background: #28a745;
    border-radius: 50%;
    animation: blink 1.5s infinite;
}

@keyframes blink {
    0%, 100% { opacity: 1; }
    50% { opacity: 0.3; }
}

.cis-stats-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-top: 12px;
}

.cis-stat-card {
    background: #f8f9fa;
    padding: 12px;
    border-radius: 8px;
    text-align: center;
}

.cis-stat-value {
    font-size: 24px;
    font-weight: bold;
    color: #667eea;
}

.cis-stat-label {
    font-size: 12px;
    color: #6c757d;
    margin-top: 4px;
}

/* QR Code Display */
.qr-code-container {
    padding: 20px;
    background: white;
    border: 2px solid #dee2e6;
    border-radius: 12px;
    display: inline-block;
    margin: 10px auto;
}

.qr-code-image {
    display: block;
    width: 200px;
    height: 200px;
    margin: 0 auto;
}

/* Mobile optimization */
@media (max-width: 768px) {
    .cis-scanner-panel.cis-scanner-bottom {
        height: 50vh;
    }

    .cis-scanner-icon {
        bottom: 80px; /* Above mobile nav */
    }

    .qr-code-image {
        width: 150px;
        height: 150px;
    }
}
`;
        document.head.appendChild(style);
    }
}
}

// Export
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CISAdvancedBarcodeWidget;
}
