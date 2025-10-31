/**
 * Flagged Products - Main Application Logic
 * 
 * Handles product completion, timers, UI interactions
 * Works with anti-cheat.js for security
 * 
 * @version 2.0.0
 */

// Initialize Anti-Cheat
let antiCheat;

document.addEventListener('DOMContentLoaded', () => {
    initAntiCheat();
    initTimers();
    initBlurOverlay();
    addWatermark();
});

/**
 * Initialize Anti-Cheat System
 */
function initAntiCheat() {
    antiCheat = new AntiCheatDetector({
        onDevToolsDetected: () => {
            reportViolation('devtools_detected', 'critical');
            showToast('⚠️ Developer tools detected! This action has been logged.', 'danger');
        },
        onExtensionDetected: (extensions) => {
            reportViolation('extensions_detected', 'medium', { extensions });
        },
        onFocusLost: () => {
            document.getElementById('blur-overlay').classList.add('active');
            reportViolation('focus_lost', 'low');
        },
        onFocusRegained: () => {
            document.getElementById('blur-overlay').classList.remove('active');
        },
        onSuspiciousActivity: (type, data) => {
            reportViolation(type, 'high', data);
        }
    });

    antiCheat.start();
}

/**
 * Initialize product timers
 */
function initTimers() {
    setInterval(() => {
        document.querySelectorAll('.timer').forEach(timer => {
            const start = parseInt(timer.dataset.start);
            const elapsed = Math.floor(Date.now() / 1000) - start;
            const mins = Math.floor(elapsed / 60).toString().padStart(2, '0');
            const secs = (elapsed % 60).toString().padStart(2, '0');
            timer.textContent = `${mins}:${secs}`;
        });
    }, 1000);
}

/**
 * Initialize blur overlay behavior
 */
function initBlurOverlay() {
    const overlay = document.getElementById('blur-overlay');
    if (overlay) {
        overlay.addEventListener('click', () => {
            overlay.classList.remove('active');
        });
    }
}

/**
 * Add watermark for screenshot protection
 */
function addWatermark() {
    const watermark = document.createElement('div');
    watermark.className = 'watermark';
    watermark.textContent = 'CONFIDENTIAL - DO NOT COPY';
    document.body.appendChild(watermark);
}

/**
 * Complete a flagged product
 * Exposed globally for onclick handlers
 */
window.completeProduct = async function(productId, isMobile = false) {
    // Handle both mobile and desktop views
    const qtyInputId = isMobile ? `qty-mobile-${productId}` : `qty-${productId}`;
    const qtyInput = document.getElementById(qtyInputId);
    const qty = parseInt(qtyInput.value);
    
    // Validate quantity
    if (isNaN(qty) || qty < 0) {
        showToast('Please enter a valid quantity', 'warning');
        qtyInput.classList.add('shake');
        setTimeout(() => qtyInput.classList.remove('shake'), 500);
        return;
    }
    
    // Get security context
    const securityContext = antiCheat.getSecurityContext();
    
    // Get timer (works for both views)
    const containerSelector = isMobile ? `#product-mobile-${productId}` : `#product-${productId}`;
    const timerEl = document.querySelector(`${containerSelector} .timer`) || 
                    document.querySelector(`tr[data-product-id="${productId}"] .timer`);
    const timeTaken = Math.floor(Date.now() / 1000) - parseInt(timerEl.dataset.start);
    
    // Get container (card or table row)
    const container = document.getElementById(isMobile ? `product-mobile-${productId}` : `product-${productId}`) ||
                     document.querySelector(`tr[data-product-id="${productId}"]`);
    container.classList.add('completing');
    const btn = container.querySelector('.btn-complete');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Saving...';
    
    try {
        const response = await fetch('/modules/flagged_products/functions/api.php?action=complete', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                product_id: productId,
                outlet_id: window.OUTLET_ID,
                quantity: qty,
                time_taken: timeTaken,
                security_context: securityContext
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Update stats
            updateStats(result);
            
            // Show achievements
            if (result.achievements && result.achievements.length > 0) {
                result.achievements.forEach(achievement => {
                    showToast(`🏆 Achievement Unlocked: ${achievement}`, 'success', 5000);
                });
            }
            
            // Mark card as completed
            card.classList.add('completed');
            btn.innerHTML = '✓ Completed';
            
            showToast(`+${result.points_awarded} points! ${result.message}`, 'success');
            
            // Remove element after animation (different logic for mobile cards vs desktop table rows)
            setTimeout(() => {
                if (isMobile) {
                    // Mobile: remove the card's wrapper column
                    container.parentElement.remove();
                    
                    // Check if all mobile cards done
                    if (document.querySelectorAll('.product-card:not(.completed)').length === 0) {
                        window.location.href = '/modules/flagged_products/views/summary.php?outlet_id=' + encodeURIComponent(window.OUTLET_ID);
                    }
                } else {
                    // Desktop: remove the table row
                    container.remove();
                    
                    // Check if all desktop rows done
                    if (document.querySelectorAll('.product-row:not(.completed)').length === 0) {
                        window.location.href = '/modules/flagged_products/views/summary.php?outlet_id=' + encodeURIComponent(window.OUTLET_ID);
                    }
                }
            }, 1000);
            
        } else {
            // Error
            showToast(result.error || 'Failed to complete product', 'danger');
            btn.disabled = false;
            btn.innerHTML = '✓ Complete';
            container.classList.remove('completing');
        }
        
    } catch (error) {
        console.error('Error:', error);
        showToast('Network error. Please try again.', 'danger');
        btn.disabled = false;
        btn.innerHTML = '✓ Complete';
        container.classList.remove('completing');
    }
}

/**
 * Update stats display
 */
function updateStats(result) {
    if (result.new_points !== undefined) {
        document.getElementById('stat-points').textContent = result.new_points.toLocaleString();
    }
    
    const remaining = document.getElementById('stat-remaining');
    if (remaining) {
        remaining.textContent = parseInt(remaining.textContent) - 1;
    }
    
    if (result.streak !== undefined) {
        document.getElementById('stat-streak').textContent = result.streak + ' 🔥';
    }
    
    if (result.accuracy !== undefined) {
        document.getElementById('stat-accuracy').textContent = result.accuracy.toFixed(1) + '%';
    }
}

/**
 * Report security violation to server
 */
async function reportViolation(type, severity, data = {}) {
    try {
        await fetch('/modules/flagged_products/functions/api.php?action=report_violation', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ type, severity, data })
        });
        
        // Update security score indicator
        updateSecurityScore(severity);
        
    } catch (error) {
        console.error('Failed to report violation:', error);
    }
}

/**
 * Update security score display
 */
function updateSecurityScore(severity) {
    const scoreEl = document.getElementById('security-score');
    if (!scoreEl) return;
    
    const currentScore = parseInt(scoreEl.textContent);
    const penalties = {
        critical: 50,
        high: 25,
        medium: 15,
        low: 5
    };
    const newScore = Math.max(0, currentScore - (penalties[severity] || 10));
    scoreEl.textContent = newScore;
    
    // Update class
    scoreEl.className = 'security-score ' + (
        newScore >= 90 ? 'excellent' :
        newScore >= 75 ? 'good' :
        newScore >= 60 ? 'fair' :
        newScore >= 40 ? 'poor' : 'critical'
    );
}

/**
 * Show toast notification
 */
function showToast(message, type = 'info', duration = 3000) {
    const toast = document.createElement('div');
    toast.className = `alert alert-${type} alert-dismissible fade show`;
    toast.style.position = 'fixed';
    toast.style.top = '80px';
    toast.style.right = '20px';
    toast.style.zIndex = '9999';
    toast.style.minWidth = '300px';
    toast.innerHTML = `${message}<button type="button" class="close" onclick="this.parentElement.remove()">×</button>`;
    document.body.appendChild(toast);
    
    setTimeout(() => toast.remove(), duration);
}

// Prevent right-click on images
document.addEventListener('contextmenu', (e) => {
    if (e.target.tagName === 'IMG') {
        e.preventDefault();
        showToast('Screenshots are not allowed', 'warning');
    }
});

// Prevent PrintScreen
document.addEventListener('keyup', (e) => {
    if (e.key === 'PrintScreen') {
        showToast('Screenshots are not allowed', 'warning');
        reportViolation('screenshot_attempt', 'medium');
    }
});
