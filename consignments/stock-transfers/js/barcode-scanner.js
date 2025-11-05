/**
 * CIS Barcode Scanner Library v1.0
 * Unified barcode scanning system supporting:
 * - USB hardware scanners (keyboard wedge)
 * - Camera-based scanning (QuaggaJS)
 * - Manual entry fallback
 * - Multi-level configuration (Global/Outlet/User)
 * - Audio & visual feedback
 * - Complete logging & analytics
 */

class CISBarcodeScanner {
    constructor(options = {}) {
        this.options = {
            transferId: options.transferId || null,
            consignmentId: options.consignmentId || null,
            purchaseOrderId: options.purchaseOrderId || null,
            userId: options.userId || null,
            outletId: options.outletId || null,
            onScan: options.onScan || this.defaultOnScan,
            onError: options.onError || this.defaultOnError,
            container: options.container || null, // For camera preview
            ...options
        };

        this.config = null;
        this.isInitialized = false;
        this.scanBuffer = '';
        this.lastScanTime = 0;
        this.lastBarcode = null;
        this.audioContext = null;
        this.cameraActive = false;
        this.scanHistory = [];

        this.init();
    }

    /**
     * Initialize scanner system
     */
    async init() {
        try {
            // Load configuration
            await this.loadConfig();

            if (!this.config.enabled) {
                console.log('[CIS Scanner] System disabled by configuration');
                return;
            }

            // Initialize audio context
            if (this.config.audio_enabled) {
                this.audioContext = new (window.AudioContext || window.webkitAudioContext)();
            }

            // Setup scanners based on config
            if (this.config.scan_mode === 'auto' || this.config.scan_mode === 'usb_only') {
                if (this.config.usb_scanner_enabled) {
                    this.initUSBScanner();
                }
            }

            if (this.config.scan_mode === 'auto' || this.config.scan_mode === 'camera_only') {
                if (this.config.camera_scanner_enabled) {
                    await this.initCameraScanner();
                }
            }

            this.isInitialized = true;
            console.log('[CIS Scanner] Initialized successfully', this.config);

        } catch (error) {
            console.error('[CIS Scanner] Initialization failed:', error);
            this.options.onError(error);
        }
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
                    outlet_id: this.options.outletId
                })
            });

            const result = await response.json();

            if (!result.success) {
                throw new Error(result.error || 'Failed to load config');
            }

            this.config = result.effective_config;

        } catch (error) {
            console.error('[CIS Scanner] Config load failed, using defaults:', error);
            // Use safe defaults
            this.config = {
                enabled: true,
                usb_scanner_enabled: true,
                camera_scanner_enabled: true,
                manual_entry_enabled: true,
                scan_mode: 'auto',
                audio_enabled: true,
                audio_volume: 0.5,
                tone1_frequency: 1200,
                tone2_frequency: 800,
                tone3_frequency: 400,
                tone_duration_ms: 100,
                visual_feedback_enabled: true,
                success_color: '#28a745',
                warning_color: '#ffc107',
                error_color: '#dc3545',
                flash_duration_ms: 500,
                scan_cooldown_ms: 100,
                require_exact_match: false,
                allow_duplicate_scans: true,
                block_on_qty_exceed: false
            };
        }
    }

    /**
     * Initialize USB barcode scanner (keyboard wedge detection)
     */
    initUSBScanner() {
        console.log('[CIS Scanner] USB scanner enabled');

        // Listen for rapid keyboard input (characteristic of scanner)
        document.addEventListener('keydown', (e) => {
            const now = Date.now();
            const timeSinceLastKey = now - this.lastScanTime;

            // If more than 100ms since last key, assume human typing - reset buffer
            if (timeSinceLastKey > 100) {
                this.scanBuffer = '';
            }

            this.lastScanTime = now;

            // Enter key indicates end of barcode
            if (e.key === 'Enter' && this.scanBuffer.length > 3) {
                e.preventDefault();
                this.processScan(this.scanBuffer, 'usb_scanner');
                this.scanBuffer = '';
                return;
            }

            // Build buffer from printable characters
            if (e.key.length === 1 && !e.ctrlKey && !e.metaKey && !e.altKey) {
                // Only buffer if focus is not in input/textarea
                const activeElement = document.activeElement;
                if (!activeElement || (activeElement.tagName !== 'INPUT' && activeElement.tagName !== 'TEXTAREA')) {
                    this.scanBuffer += e.key;

                    // Auto-trigger if buffer looks like complete barcode (12-13 chars for EAN/UPC)
                    if (this.scanBuffer.length >= 12 && /^\d+$/.test(this.scanBuffer)) {
                        setTimeout(() => {
                            if (this.scanBuffer.length === this.scanBuffer.length) { // No new input
                                this.processScan(this.scanBuffer, 'usb_scanner');
                                this.scanBuffer = '';
                            }
                        }, 50);
                    }
                }
            }
        });
    }

    /**
     * Initialize camera-based scanner using QuaggaJS
     */
    async initCameraScanner() {
        if (!this.options.container) {
            console.warn('[CIS Scanner] Camera scanner requires container element');
            return;
        }

        // Check for camera access
        if (!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia) {
            console.warn('[CIS Scanner] Camera API not available (HTTPS required)');
            return;
        }

        // Load QuaggaJS if not already loaded
        if (typeof Quagga === 'undefined') {
            await this.loadQuaggaJS();
        }

        console.log('[CIS Scanner] Camera scanner ready (call startCamera() to activate)');
    }

    /**
     * Load QuaggaJS library dynamically
     */
    loadQuaggaJS() {
        return new Promise((resolve, reject) => {
            if (typeof Quagga !== 'undefined') {
                resolve();
                return;
            }

            const script = document.createElement('script');
            script.src = 'https://cdn.jsdelivr.net/npm/@ericblade/quagga2@1.7.5/dist/quagga.min.js';
            script.onload = resolve;
            script.onerror = reject;
            document.head.appendChild(script);
        });
    }

    /**
     * Start camera scanning
     */
    async startCamera() {
        if (!this.config.camera_scanner_enabled) {
            throw new Error('Camera scanning is disabled');
        }

        if (this.cameraActive) {
            return;
        }

        if (typeof Quagga === 'undefined') {
            throw new Error('QuaggaJS not loaded');
        }

        const container = document.querySelector(this.options.container);
        if (!container) {
            throw new Error('Camera container not found');
        }

        return new Promise((resolve, reject) => {
            Quagga.init({
                inputStream: {
                    name: "Live",
                    type: "LiveStream",
                    target: container,
                    constraints: {
                        width: 640,
                        height: 480,
                        facingMode: "environment"
                    }
                },
                decoder: {
                    readers: [
                        "ean_reader",
                        "ean_8_reader",
                        "code_128_reader",
                        "code_39_reader",
                        "upc_reader",
                        "upc_e_reader"
                    ],
                    debug: {
                        drawBoundingBox: true,
                        showFrequency: false,
                        drawScanline: true,
                        showPattern: false
                    }
                },
                locate: true,
                frequency: this.config.camera_fps || 10
            }, (err) => {
                if (err) {
                    console.error('[CIS Scanner] Camera init failed:', err);
                    reject(err);
                    return;
                }

                console.log('[CIS Scanner] Camera started');
                Quagga.start();
                this.cameraActive = true;

                // Listen for detections
                Quagga.onDetected((result) => {
                    if (result && result.codeResult && result.codeResult.code) {
                        const barcode = result.codeResult.code;
                        const format = result.codeResult.format;

                        // Prevent duplicate rapid scans
                        if (this.lastBarcode === barcode &&
                            (Date.now() - this.lastScanTime) < (this.config.scan_cooldown_ms || 1000)) {
                            return;
                        }

                        this.processScan(barcode, 'camera', format);
                    }
                });

                resolve();
            });
        });
    }

    /**
     * Stop camera scanning
     */
    stopCamera() {
        if (typeof Quagga !== 'undefined' && this.cameraActive) {
            Quagga.stop();
            this.cameraActive = false;
            console.log('[CIS Scanner] Camera stopped');
        }
    }

    /**
     * Toggle camera on/off
     */
    toggleCamera() {
        if (this.cameraActive) {
            this.stopCamera();
        } else {
            this.startCamera();
        }
    }

    /**
     * Manual barcode entry
     */
    manualEntry(barcode) {
        if (!this.config.manual_entry_enabled) {
            throw new Error('Manual entry is disabled');
        }

        this.processScan(barcode, 'manual_entry');
    }

    /**
     * Process a barcode scan
     */
    async processScan(barcode, method, format = 'UNKNOWN') {
        const scanStart = Date.now();

        // Cooldown check
        if (!this.config.allow_duplicate_scans &&
            barcode === this.lastBarcode &&
            (scanStart - this.lastScanTime) < this.config.scan_cooldown_ms) {
            console.log('[CIS Scanner] Scan ignored (cooldown)');
            return;
        }

        this.lastBarcode = barcode;
        this.lastScanTime = scanStart;

        console.log(`[CIS Scanner] Processing scan: ${barcode} (${method})`);

        try {
            // Call user's onScan callback
            const result = await this.options.onScan(barcode, method, format);

            const scanDuration = Date.now() - scanStart;

            // Log to database
            await this.logScan({
                barcode,
                method,
                format,
                result: result.success ? 'success' : result.reason || 'not_found',
                productId: result.productId || null,
                sku: result.sku || null,
                productName: result.productName || null,
                qtyScanned: result.qty || 1,
                scanDuration
            });

            // Audio feedback
            if (this.config.audio_enabled) {
                if (result.success) {
                    this.playTone(this.config.tone1_frequency, 'tone1');
                } else if (result.warning) {
                    this.playTone(this.config.tone2_frequency, 'tone2');
                } else {
                    this.playTone(this.config.tone3_frequency, 'tone3');
                }
            }

            // Visual feedback
            if (this.config.visual_feedback_enabled && result.element) {
                this.showVisualFeedback(result.element, result.success ? 'success' : 'error');
            }

            // Add to history
            this.scanHistory.push({
                barcode,
                method,
                timestamp: new Date(),
                success: result.success
            });

        } catch (error) {
            console.error('[CIS Scanner] Scan processing error:', error);

            // Error tone
            if (this.config.audio_enabled) {
                this.playTone(this.config.tone3_frequency, 'tone3');
            }

            // Log error
            await this.logScan({
                barcode,
                method,
                format,
                result: 'error',
                scanDuration: Date.now() - scanStart
            });

            this.options.onError(error);
        }
    }

    /**
     * Play audio tone
     */
    playTone(frequency, type = 'tone1') {
        if (!this.audioContext) return;

        const oscillator = this.audioContext.createOscillator();
        const gainNode = this.audioContext.createGain();

        oscillator.connect(gainNode);
        gainNode.connect(this.audioContext.destination);

        oscillator.frequency.value = frequency;
        oscillator.type = 'sine';

        gainNode.gain.setValueAtTime(this.config.audio_volume || 0.5, this.audioContext.currentTime);
        gainNode.gain.exponentialRampToValueAtTime(0.01, this.audioContext.currentTime + (this.config.tone_duration_ms / 1000));

        oscillator.start(this.audioContext.currentTime);
        oscillator.stop(this.audioContext.currentTime + (this.config.tone_duration_ms / 1000));
    }

    /**
     * Show visual feedback flash
     */
    showVisualFeedback(element, type = 'success') {
        if (!element) return;

        const colors = {
            success: this.config.success_color,
            warning: this.config.warning_color,
            error: this.config.error_color
        };

        const originalBg = element.style.backgroundColor;
        element.style.backgroundColor = colors[type] || colors.success;
        element.style.transition = `background-color ${this.config.flash_duration_ms}ms ease`;

        setTimeout(() => {
            element.style.backgroundColor = originalBg;
        }, this.config.flash_duration_ms);
    }

    /**
     * Log scan to database
     */
    async logScan(data) {
        if (!this.config.log_all_scans && data.result === 'success') {
            return;
        }

        if (!this.config.log_failed_scans && data.result !== 'success') {
            return;
        }

        try {
            await fetch('/modules/consignments/api/barcode_log.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    transfer_id: this.options.transferId,
                    consignment_id: this.options.consignmentId,
                    purchase_order_id: this.options.purchaseOrderId,
                    barcode_value: data.barcode,
                    barcode_format: data.format,
                    scan_method: data.method,
                    vend_product_id: data.productId,
                    sku: data.sku,
                    product_name: data.productName,
                    scan_result: data.result,
                    qty_scanned: data.qtyScanned,
                    audio_feedback: data.result === 'success' ? 'tone1' : data.result === 'warning' ? 'tone2' : 'tone3',
                    user_id: this.options.userId,
                    outlet_id: this.options.outletId,
                    scan_duration_ms: data.scanDuration,
                    user_agent: navigator.userAgent
                })
            });
        } catch (error) {
            console.error('[CIS Scanner] Log failed:', error);
        }
    }

    /**
     * Default onScan handler (override with your own)
     */
    defaultOnScan(barcode, method, format) {
        console.log(`[CIS Scanner] Scanned: ${barcode} via ${method} (${format})`);
        return {
            success: false,
            reason: 'No onScan handler defined'
        };
    }

    /**
     * Default onError handler
     */
    defaultOnError(error) {
        console.error('[CIS Scanner] Error:', error);
    }

    /**
     * Get scan statistics
     */
    getStats() {
        const total = this.scanHistory.length;
        const successful = this.scanHistory.filter(s => s.success).length;
        const failed = total - successful;

        return {
            total,
            successful,
            failed,
            successRate: total > 0 ? (successful / total * 100).toFixed(1) : 0
        };
    }

    /**
     * Clear scan history
     */
    clearHistory() {
        this.scanHistory = [];
    }

    /**
     * Destroy scanner instance
     */
    destroy() {
        this.stopCamera();

        if (this.audioContext) {
            this.audioContext.close();
        }

        this.isInitialized = false;
        console.log('[CIS Scanner] Destroyed');
    }
}

// Export for use in modules
if (typeof module !== 'undefined' && module.exports) {
    module.exports = CISBarcodeScanner;
}
