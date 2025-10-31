/**
 * Anti-Cheat Detection System - Client Side
 * 
 * Detects and prevents all forms of cheating:
 * - DevTools (F12, Inspect Element)
 * - Browser Extensions (Vend, Inventory viewers)
 * - Tab Switching / Window Focus Loss
 * - Screen Recording / Screenshots
 * - Multiple Monitors
 * - Virtual Machines
 * - Automated Bots
 * - Clipboard Snooping
 * 
 * @version 1.0.0
 */

class AntiCheatDetector {
    constructor() {
        this.violations = [];
        this.startTime = Date.now();
        this.mouseMovements = 0;
        this.tabSwitches = 0;
        this.hadFocus = true;
        this.devToolsDetected = false;
        this.extensionsDetected = [];
        this.suspiciousTiming = false;
        this.screenRecording = false;
        this.multipleMonitors = false;
        this.vmDetected = false;
        this.countdownInterval = null;
        
        this.init();
    }
    
    init() {
        this.detectDevTools();
        this.detectExtensions();
        this.monitorFocus();
        this.monitorMouse();
        this.preventScreenshots();
        this.detectMultipleMonitors();
        this.detectVirtualMachine();
        this.detectScreenRecording();
        this.monitorClipboard();
        this.setupWarnings();
    }
    
    // ============================================
    // DEVTOOLS DETECTION (Multiple Methods)
    // ============================================
    
    detectDevTools() {
        // Method 1: Check window.outerHeight vs innerHeight
        setInterval(() => {
            const widthThreshold = window.outerWidth - window.innerWidth > 160;
            const heightThreshold = window.outerHeight - window.innerHeight > 160;
            
            if (widthThreshold || heightThreshold) {
                this.flagViolation('devtools_detected', 'DevTools detected via window size', 'critical');
            }
        }, 1000);
        
        // Method 2: debugger statement timing
        setInterval(() => {
            const start = performance.now();
            debugger; // This pauses if DevTools open
            const end = performance.now();
            
            if (end - start > 100) {
                this.flagViolation('devtools_detected', 'DevTools detected via debugger timing', 'critical');
            }
        }, 2000);
        
        // Method 3: Console detection
        const devtools = /./;
        devtools.toString = () => {
            this.flagViolation('devtools_detected', 'DevTools detected via console', 'critical');
            return '';
        };
        console.log('%c', devtools);
        
        // Method 4: Firebug check
        if (window.Firebug && window.Firebug.chrome && window.Firebug.chrome.isInitialized) {
            this.flagViolation('devtools_detected', 'Firebug detected', 'critical');
        }
        
        // Method 5: Check for __REACT_DEVTOOLS_GLOBAL_HOOK__
        if (window.__REACT_DEVTOOLS_GLOBAL_HOOK__) {
            this.flagViolation('devtools_detected', 'React DevTools detected', 'high');
        }
    }
    
    // ============================================
    // EXTENSION DETECTION
    // ============================================
    
    detectExtensions() {
        // Check for common extension injected elements
        const extensionPatterns = [
            { name: 'Vend Helper', selector: '[data-vend-helper]' },
            { name: 'Inventory Viewer', selector: '[data-inventory-ext]' },
            { name: 'Price Checker', selector: '[data-price-check]' },
            { name: 'Chrome Extensions', property: 'chrome.runtime' }
        ];
        
        extensionPatterns.forEach(pattern => {
            if (pattern.selector && document.querySelector(pattern.selector)) {
                this.flagViolation('extension_detected', `Extension detected: ${pattern.name}`, 'high');
                this.extensionsDetected.push(pattern.name);
            }
            if (pattern.property && this.getNestedProperty(window, pattern.property)) {
                // Chrome extension detected
                this.extensionsDetected.push(pattern.name);
            }
        });
        
        // Check for modified fetch/XMLHttpRequest (extension interference)
        if (window.fetch.toString().includes('native code') === false) {
            this.flagViolation('extension_detected', 'Fetch API modified by extension', 'high');
        }
        
        // Check for Resource Timing (extensions load extra resources)
        const resources = performance.getEntriesByType('resource');
        const suspiciousResources = resources.filter(r => 
            r.name.includes('chrome-extension://') || 
            r.name.includes('moz-extension://')
        );
        
        if (suspiciousResources.length > 0) {
            this.flagViolation('extension_detected', `${suspiciousResources.length} extension resources loaded`, 'medium');
        }
    }
    
    // ============================================
    // FOCUS & TAB SWITCHING MONITORING
    // ============================================
    
    monitorFocus() {
        // Track when user leaves the page
        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                this.hadFocus = false;
                this.tabSwitches++;
                this.flagViolation('tab_switch', 'User switched tabs/windows', 'high');
                this.blurContent();
            }
            // Don't auto-close when coming back - let countdown finish
        });
        
        window.addEventListener('blur', () => {
            this.hadFocus = false;
            this.tabSwitches++;
            this.flagViolation('focus_lost', 'Window lost focus', 'medium');
            this.blurContent();
        });
        
        // Don't auto-close on focus - let countdown finish
    }
    
    blurContent() {
        // Use the existing blur-overlay div
        const overlay = document.getElementById('blur-overlay');
        if (overlay) {
            overlay.classList.add('active');
            overlay.style.display = 'flex';
            
            // Start 15-second countdown timer
            this.startCountdown();
        }
    }
    
    startCountdown() {
        // Clear any existing countdown
        if (this.countdownInterval) {
            clearInterval(this.countdownInterval);
        }
        
        let timeLeft = 15;
        const timerDisplay = document.getElementById('countdown-timer');
        const timerText = document.getElementById('countdown-text');
        const continueBtn = document.getElementById('continue-btn');
        
        if (!timerDisplay || !timerText || !continueBtn) {
            console.error('Countdown elements not found');
            return;
        }
        
        // Reset button state
        continueBtn.disabled = true;
        continueBtn.style.background = '#ccc';
        continueBtn.style.color = '#666';
        continueBtn.style.cursor = 'not-allowed';
        continueBtn.textContent = 'Continue (wait...)';
        
        // Update displays
        timerDisplay.textContent = timeLeft;
        timerText.textContent = timeLeft;
        
        this.countdownInterval = setInterval(() => {
            timeLeft--;
            timerDisplay.textContent = timeLeft;
            timerText.textContent = timeLeft;
            
            if (timeLeft <= 0) {
                clearInterval(this.countdownInterval);
                this.countdownInterval = null;
                
                // Enable button
                continueBtn.disabled = false;
                continueBtn.style.background = '#28a745';
                continueBtn.style.color = 'white';
                continueBtn.style.cursor = 'pointer';
                continueBtn.style.boxShadow = '0 2px 8px rgba(40,167,69,0.3)';
                continueBtn.textContent = 'Continue';
                
                // Add hover effects
                continueBtn.onmouseover = function() {
                    this.style.background = '#218838';
                    this.style.boxShadow = '0 4px 12px rgba(40,167,69,0.4)';
                };
                continueBtn.onmouseout = function() {
                    this.style.background = '#28a745';
                    this.style.boxShadow = '0 2px 8px rgba(40,167,69,0.3)';
                };
            }
        }, 1000);
    }
    
    unblurContent() {
        // Hide the blur-overlay
        const overlay = document.getElementById('blur-overlay');
        if (overlay) {
            overlay.classList.remove('active');
            overlay.style.display = 'none';
        }
    }
    
    returnToTask() {
        this.unblurContent();
    }
    
    // ============================================
    // MOUSE MOVEMENT TRACKING (Bot Detection)
    // ============================================
    
    monitorMouse() {
        let lastMove = Date.now();
        
        document.addEventListener('mousemove', () => {
            this.mouseMovements++;
            lastMove = Date.now();
        });
        
        // Check for suspiciously low mouse movement
        setInterval(() => {
            const elapsed = (Date.now() - this.startTime) / 1000;
            const movementsPerMinute = (this.mouseMovements / elapsed) * 60;
            
            if (elapsed > 30 && movementsPerMinute < 5) {
                this.flagViolation('bot_suspected', `Only ${Math.round(movementsPerMinute)} mouse movements per minute`, 'high');
            }
        }, 30000);
    }
    
    // ============================================
    // SCREENSHOT PREVENTION
    // ============================================
    
    preventScreenshots() {
        // Disable right-click
        document.addEventListener('contextmenu', (e) => {
            e.preventDefault();
            this.flagViolation('screenshot_attempt', 'Right-click disabled', 'low');
            return false;
        });
        
        // Disable common screenshot shortcuts
        document.addEventListener('keydown', (e) => {
            // Print Screen
            if (e.key === 'PrintScreen') {
                e.preventDefault();
                this.flagViolation('screenshot_attempt', 'Print Screen blocked', 'medium');
                alert('⚠️ Screenshots are disabled during stocktake for security');
            }
            
            // Ctrl+Shift+S (Firefox screenshot)
            if (e.ctrlKey && e.shiftKey && e.key === 'S') {
                e.preventDefault();
                this.flagViolation('screenshot_attempt', 'Screenshot shortcut blocked', 'medium');
            }
            
            // Windows+Shift+S (Windows Snipping Tool)
            if (e.metaKey && e.shiftKey && e.key === 'S') {
                e.preventDefault();
                this.flagViolation('screenshot_attempt', 'Snipping tool blocked', 'medium');
            }
        });
        
        // Watermark the page
        this.addSecurityWatermark();
    }
    
    addSecurityWatermark() {
        const watermark = document.createElement('div');
        watermark.style.cssText = `
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 120px;
            color: rgba(255, 0, 0, 0.1);
            pointer-events: none;
            z-index: 9998;
            user-select: none;
        `;
        watermark.textContent = `${document.querySelector('[data-user-id]')?.dataset.userId || 'USER'} - ${new Date().toISOString()}`;
        document.body.appendChild(watermark);
    }
    
    // ============================================
    // MULTIPLE MONITOR DETECTION
    // ============================================
    
    detectMultipleMonitors() {
        if (window.screen) {
            // Check for extended desktop
            if (window.screen.availWidth > window.screen.width ||
                window.screen.availHeight > window.screen.height) {
                this.multipleMonitors = true;
                this.flagViolation('multiple_monitors', 'Multiple monitors detected', 'medium');
            }
            
            // Check window position (off-screen = multiple monitors)
            if (window.screenX < 0 || window.screenY < 0 ||
                window.screenX > window.screen.width ||
                window.screenY > window.screen.height) {
                this.multipleMonitors = true;
                this.flagViolation('multiple_monitors', 'Window on secondary monitor', 'medium');
            }
        }
    }
    
    // ============================================
    // VIRTUAL MACHINE DETECTION
    // ============================================
    
    detectVirtualMachine() {
        const vmIndicators = [
            // Check for VM-specific user agents
            /VirtualBox|VMware|QEMU|Parallels/i.test(navigator.userAgent),
            
            // Check for VM-specific plugins
            navigator.plugins && Array.from(navigator.plugins).some(p => 
                /VM|Virtual|Guest/i.test(p.name)
            ),
            
            // Check hardware concurrency (VMs often limit cores)
            navigator.hardwareConcurrency && navigator.hardwareConcurrency < 2,
            
            // Check for suspiciously perfect screen dimensions
            window.screen.width === 1024 && window.screen.height === 768
        ];
        
        if (vmIndicators.some(indicator => indicator)) {
            this.vmDetected = true;
            this.flagViolation('vm_detected', 'Virtual machine detected', 'medium');
        }
    }
    
    // ============================================
    // SCREEN RECORDING DETECTION
    // ============================================
    
    detectScreenRecording() {
        // Check if navigator.mediaDevices.getDisplayMedia exists (screen capture API)
        if (navigator.mediaDevices && navigator.mediaDevices.getDisplayMedia) {
            const originalGetDisplayMedia = navigator.mediaDevices.getDisplayMedia;
            
            navigator.mediaDevices.getDisplayMedia = function(...args) {
                antiCheat.screenRecording = true;
                antiCheat.flagViolation('screen_recording', 'Screen recording detected', 'critical');
                return originalGetDisplayMedia.apply(this, args);
            };
        }
        
        // Check for OBS, XSplit, etc via performance timing
        const resources = performance.getEntriesByType('resource');
        const recordingSoftware = resources.filter(r => 
            /obs|xsplit|streamlabs|nvidia|shadowplay/i.test(r.name)
        );
        
        if (recordingSoftware.length > 0) {
            this.screenRecording = true;
            this.flagViolation('screen_recording', 'Recording software detected', 'high');
        }
    }
    
    // ============================================
    // CLIPBOARD MONITORING
    // ============================================
    
    monitorClipboard() {
        document.addEventListener('copy', (e) => {
            this.flagViolation('clipboard_access', 'User copied content', 'low');
        });
        
        document.addEventListener('paste', (e) => {
            this.flagViolation('clipboard_access', 'User pasted content', 'medium');
        });
    }
    
    // ============================================
    // TIMING ANALYSIS (Too Fast = Bot/Cheat)
    // ============================================
    
    analyzeCompletionTime(timeSpent) {
        // Average human takes 5-15 seconds per product
        if (timeSpent < 2) {
            this.suspiciousTiming = true;
            this.flagViolation('suspicious_timing', `Completed in ${timeSpent}s (too fast)`, 'critical');
            return false;
        }
        
        if (timeSpent > 120) {
            this.flagViolation('suspicious_timing', `Completed in ${timeSpent}s (too slow - possibly checking Vend)`, 'high');
        }
        
        return true;
    }
    
    // ============================================
    // VIOLATION LOGGING
    // ============================================
    
    flagViolation(type, description, severity = 'medium') {
        const violation = {
            type,
            description,
            severity,
            timestamp: Date.now(),
            url: window.location.href,
            userAgent: navigator.userAgent
        };
        
        this.violations.push(violation);
        
        // Log to console for debugging
        console.warn(`[ANTI-CHEAT] ${severity.toUpperCase()}: ${description}`);
        
        // Send to server immediately for critical violations
        if (severity === 'critical') {
            this.reportToServer(violation);
            
            if (type === 'devtools_detected') {
                this.showCriticalWarning();
            }
        }
        
        // Update UI warning indicator
        this.updateWarningIndicator();
    }
    
    showCriticalWarning() {
        // Silently log violations instead of showing scary warnings
        console.log('Security violation detected - logged to server');
    }
    
    updateWarningIndicator() {
        // Remove the visual indicator - just track violations silently
        let indicator = document.getElementById('security-indicator');
        if (indicator) {
            indicator.remove();
        }
    }
    
    reportToServer(violation) {
        fetch('/modules/flagged_products/api/report-violation.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                violation,
                context: this.getSecurityContext()
            })
        }).catch(err => console.error('Failed to report violation:', err));
    }
    
    // ============================================
    // SECURITY CONTEXT FOR COMPLETION
    // ============================================
    
    getSecurityContext() {
        return {
            time_spent: (Date.now() - this.startTime) / 1000,
            had_focus: this.hadFocus,
            tab_switches: this.tabSwitches,
            devtools_detected: this.devToolsDetected || this.violations.some(v => v.type === 'devtools_detected'),
            extensions_detected: this.extensionsDetected.length,
            suspicious_timing: this.suspiciousTiming,
            mouse_movements: this.mouseMovements,
            multiple_monitors: this.multipleMonitors,
            screen_recording: this.screenRecording,
            vm_detected: this.vmDetected,
            violations: this.violations,
            security_score: this.calculateSecurityScore()
        };
    }
    
    calculateSecurityScore() {
        let score = 100;
        
        this.violations.forEach(v => {
            if (v.severity === 'critical') score -= 20;
            else if (v.severity === 'high') score -= 10;
            else if (v.severity === 'medium') score -= 5;
            else score -= 2;
        });
        
        return Math.max(0, score);
    }
    
    setupWarnings() {
        // Add CSS for shake animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes shake {
                0%, 100% { transform: translateX(0); }
                10%, 30%, 50%, 70%, 90% { transform: translateX(-10px); }
                20%, 40%, 60%, 80% { transform: translateX(10px); }
            }
        `;
        document.head.appendChild(style);
    }
    
    getNestedProperty(obj, path) {
        return path.split('.').reduce((o, p) => o?.[p], obj);
    }
}

// Initialize anti-cheat system
let antiCheat;
document.addEventListener('DOMContentLoaded', () => {
    antiCheat = new AntiCheatDetector();
    window.antiCheat = antiCheat; // Make available globally
});
