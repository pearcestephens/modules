/**
 * Security Monitor - Client-Side Behavior Detection
 *
 * Monitors user behavior for security and fraud detection:
 * - DevTools detection (console open, inspect element)
 * - Rapid keyboard entry patterns
 * - Copy/paste behavior tracking
 * - Mouse pattern analysis
 * - Focus loss and tab switching
 * - Suspicious input patterns
 *
 * All monitoring is privacy-safe: aggregates patterns, no raw keystroke capture.
 *
 * Usage:
 *   SecurityMonitor.init({ poId: 123, page: 'receive' });
 *   // Automatically monitors and reports via InteractionLogger
 *
 * @package CIS\Consignments\PurchaseOrders
 * @version 1.0.0
 */

var SecurityMonitor = (function(){
    var config = {
        poId: null,
        page: 'unknown',
        enabled: true,
        // Thresholds
        rapidKeyboardThreshold: 8, // keys per second
        copyPasteThreshold: 3, // paste events per session
        devToolsCheckInterval: 1000, // ms
        mouseSampleInterval: 500, // ms
        focusLossThreshold: 3 // focus loss count
    };

    var state = {
        keystrokes: [],
        pasteCount: 0,
        copyCount: 0,
        focusLossCount: 0,
        mousePositions: [],
        devToolsDetected: false,
        sessionStart: Date.now()
    };

    var timers = {
        devTools: null,
        mouseSample: null
    };

    // ========================================================================
    // PUBLIC API
    // ========================================================================

    function init(options) {
        if (!options) options = {};
        config.poId = options.poId || null;
        config.page = options.page || 'unknown';
        config.enabled = options.enabled !== false;

        if (!config.enabled) return;

        console.log('[SecurityMonitor] Initialized for page:', config.page);

        bindEvents();
        startDevToolsDetection();
        startMouseTracking();
    }

    function destroy() {
        unbindEvents();
        stopDevToolsDetection();
        stopMouseTracking();
        console.log('[SecurityMonitor] Destroyed');
    }

    // ========================================================================
    // EVENT BINDING
    // ========================================================================

    function bindEvents() {
        // Keyboard monitoring
        document.addEventListener('keydown', handleKeydown);

        // Copy/paste monitoring
        document.addEventListener('paste', handlePaste);
        document.addEventListener('copy', handleCopy);

        // Focus monitoring
        window.addEventListener('blur', handleFocusLoss);
        document.addEventListener('visibilitychange', handleVisibilityChange);
    }

    function unbindEvents() {
        document.removeEventListener('keydown', handleKeydown);
        document.removeEventListener('paste', handlePaste);
        document.removeEventListener('copy', handleCopy);
        window.removeEventListener('blur', handleFocusLoss);
        document.removeEventListener('visibilitychange', handleVisibilityChange);
    }

    // ========================================================================
    // KEYBOARD MONITORING
    // ========================================================================

    function handleKeydown(e) {
        try {
            var now = Date.now();

            // Store keystroke timestamp (no key values - privacy safe)
            state.keystrokes.push(now);

            // Keep only last 20 keystrokes
            if (state.keystrokes.length > 20) {
                state.keystrokes.shift();
            }

            // Check for rapid keyboard entry (every 10 keystrokes)
            if (state.keystrokes.length >= 10) {
                checkRapidKeyboard();
            }
        } catch (err) {
            console.warn('[SecurityMonitor] Keyboard handler error', err);
        }
    }

    function checkRapidKeyboard() {
        if (state.keystrokes.length < 10) return;

        // Get last 10 keystrokes
        var recent = state.keystrokes.slice(-10);
        var firstTime = recent[0];
        var lastTime = recent[recent.length - 1];
        var duration = (lastTime - firstTime) / 1000.0; // seconds

        if (duration === 0) return;

        var keysPerSecond = 10 / duration;

        if (keysPerSecond > config.rapidKeyboardThreshold) {
            reportRapidKeyboard(keysPerSecond, 10);
            // Clear to avoid duplicate reports
            state.keystrokes = [];
        }
    }

    function reportRapidKeyboard(keysPerSecond, totalKeys) {
        console.warn('[SecurityMonitor] Rapid keyboard detected:', keysPerSecond.toFixed(2), 'keys/sec');

        if (typeof InteractionLogger !== 'undefined' && InteractionLogger.track) {
            InteractionLogger.track({
                type: 'rapid_keyboard',
                po_id: config.poId,
                page: config.page,
                field: document.activeElement ? document.activeElement.name || document.activeElement.id || 'unknown' : 'unknown',
                entries_per_second: keysPerSecond,
                total_entries: totalKeys,
                timestamp: Date.now()
            });
        }
    }

    // ========================================================================
    // COPY/PASTE MONITORING
    // ========================================================================

    function handlePaste(e) {
        try {
            state.pasteCount++;

            var targetField = e.target.name || e.target.id || 'unknown';

            console.info('[SecurityMonitor] Paste detected in field:', targetField, '(count:', state.pasteCount + ')');

            // Report if exceeds threshold
            if (state.pasteCount >= config.copyPasteThreshold) {
                reportCopyPaste('paste', targetField);
            }
        } catch (err) {
            console.warn('[SecurityMonitor] Paste handler error', err);
        }
    }

    function handleCopy(e) {
        try {
            state.copyCount++;

            var targetField = e.target.name || e.target.id || 'unknown';

            console.info('[SecurityMonitor] Copy detected from field:', targetField, '(count:', state.copyCount + ')');

            // Report if exceeds threshold
            if (state.copyCount >= config.copyPasteThreshold) {
                reportCopyPaste('copy', targetField);
            }
        } catch (err) {
            console.warn('[SecurityMonitor] Copy handler error', err);
        }
    }

    function reportCopyPaste(action, field) {
        console.warn('[SecurityMonitor] Copy/paste threshold exceeded:', action, 'in', field);

        if (typeof InteractionLogger !== 'undefined' && InteractionLogger.track) {
            InteractionLogger.track({
                type: 'suspicious_value',
                po_id: config.poId,
                page: config.page,
                field: field,
                pattern: action + '_behavior',
                entered_value: null,
                expected_value: null,
                timestamp: Date.now()
            });
        }
    }

    // ========================================================================
    // DEVTOOLS DETECTION
    // ========================================================================

    function startDevToolsDetection() {
        timers.devTools = setInterval(checkDevTools, config.devToolsCheckInterval);
    }

    function stopDevToolsDetection() {
        if (timers.devTools) {
            clearInterval(timers.devTools);
            timers.devTools = null;
        }
    }

    function checkDevTools() {
        try {
            var threshold = 160;
            var widthThreshold = window.outerWidth - window.innerWidth > threshold;
            var heightThreshold = window.outerHeight - window.innerHeight > threshold;

            // Check if devtools likely open
            var isOpen = widthThreshold || heightThreshold;

            if (isOpen && !state.devToolsDetected) {
                state.devToolsDetected = true;
                reportDevTools();
            } else if (!isOpen && state.devToolsDetected) {
                // DevTools closed
                state.devToolsDetected = false;
            }
        } catch (err) {
            console.warn('[SecurityMonitor] DevTools check error', err);
        }
    }

    function reportDevTools() {
        console.warn('[SecurityMonitor] DevTools detected');

        if (typeof InteractionLogger !== 'undefined' && InteractionLogger.track) {
            InteractionLogger.track({
                type: 'devtools_detected',
                po_id: config.poId,
                page: config.page,
                timestamp: Date.now()
            });
        }
    }

    // ========================================================================
    // FOCUS LOSS MONITORING
    // ========================================================================

    function handleFocusLoss() {
        try {
            state.focusLossCount++;

            console.info('[SecurityMonitor] Focus loss detected (count:', state.focusLossCount + ')');

            if (state.focusLossCount >= config.focusLossThreshold) {
                reportFocusLoss();
            }
        } catch (err) {
            console.warn('[SecurityMonitor] Focus loss handler error', err);
        }
    }

    function handleVisibilityChange() {
        try {
            if (document.hidden) {
                state.focusLossCount++;

                console.info('[SecurityMonitor] Tab hidden (count:', state.focusLossCount + ')');

                if (state.focusLossCount >= config.focusLossThreshold) {
                    reportFocusLoss();
                }
            }
        } catch (err) {
            console.warn('[SecurityMonitor] Visibility handler error', err);
        }
    }

    function reportFocusLoss() {
        console.warn('[SecurityMonitor] Focus loss threshold exceeded:', state.focusLossCount);

        if (typeof InteractionLogger !== 'undefined' && InteractionLogger.track) {
            InteractionLogger.track({
                type: 'focus_loss',
                po_id: config.poId,
                page: config.page,
                focus_loss_count: state.focusLossCount,
                timestamp: Date.now()
            });
        }

        // Reset count after reporting
        state.focusLossCount = 0;
    }

    // ========================================================================
    // MOUSE TRACKING (OPTIONAL - LIGHTWEIGHT)
    // ========================================================================

    function startMouseTracking() {
        // Optional: track mouse positions for erratic pattern detection
        // Disabled by default - enable if needed
        // timers.mouseSample = setInterval(sampleMousePosition, config.mouseSampleInterval);
    }

    function stopMouseTracking() {
        if (timers.mouseSample) {
            clearInterval(timers.mouseSample);
            timers.mouseSample = null;
        }
    }

    function sampleMousePosition() {
        // Placeholder for mouse tracking
        // Could track sudden movements, tremors, etc.
    }

    // ========================================================================
    // SESSION SUMMARY
    // ========================================================================

    function getSessionSummary() {
        var duration = (Date.now() - state.sessionStart) / 1000.0;

        return {
            duration_seconds: duration,
            keystroke_count: state.keystrokes.length,
            paste_count: state.pasteCount,
            copy_count: state.copyCount,
            focus_loss_count: state.focusLossCount,
            devtools_detected: state.devToolsDetected
        };
    }

    // ========================================================================
    // RETURN PUBLIC API
    // ========================================================================

    return {
        init: init,
        destroy: destroy,
        getSessionSummary: getSessionSummary,
        // Allow external threshold adjustments
        setThreshold: function(key, value) {
            if (config.hasOwnProperty(key)) {
                config[key] = value;
            }
        }
    };
})();

// Auto-cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (typeof SecurityMonitor !== 'undefined' && SecurityMonitor.getSessionSummary) {
        var summary = SecurityMonitor.getSessionSummary();
        console.log('[SecurityMonitor] Session summary:', summary);
    }
});
