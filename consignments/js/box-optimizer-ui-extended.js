/**
 * Box Optimizer UI - Extended Features
 *
 * Adds:
 * - Quick size presets for internal/staff transfers
 * - Historical box data tracking
 * - Cost comparison charts
 * - Mobile-friendly interface
 *
 * @file box-optimizer-ui-extended.js
 * @version 2.0.0
 */

// Extend the base BoxOptimizerUI class
if (typeof BoxOptimizerUI !== 'undefined') {
    BoxOptimizerUI.prototype.setQuickSize = function(sizeKey) {
        const sizes = {
            'small': { length: 15, width: 15, height: 15, weight: 1, label: 'Small Box' },
            'medium': { length: 25, width: 20, height: 15, weight: 3, label: 'Medium Box' },
            'large': { length: 35, width: 25, height: 25, weight: 5, label: 'Large Box' }
        };

        const size = sizes[sizeKey] || sizes['medium'];

        // Update form fields
        const lengthInput = document.querySelector('input[name="box_length"]');
        const widthInput = document.querySelector('input[name="box_width"]');
        const heightInput = document.querySelector('input[name="box_height"]');
        const weightInput = document.querySelector('input[name="box_weight"]');

        if (lengthInput) lengthInput.value = size.length;
        if (widthInput) widthInput.value = size.width;
        if (heightInput) heightInput.value = size.height;
        if (weightInput) weightInput.value = size.weight;

        // Show visual feedback
        this.showQuickSizeNotice(size.label);

        // Trigger analysis
        this.analyzeCurrentBoxes();
    };

    BoxOptimizerUI.prototype.showQuickSizeNotice = function(label) {
        const notice = document.createElement('div');
        notice.className = 'alert alert-info alert-dismissible fade show';
        notice.style.marginTop = '10px';
        notice.innerHTML = `
            <i class="bi bi-info-circle"></i>
            <strong>${label}</strong> selected - analyzing optimization...
            <button type="button" class="close" data-dismiss="alert">&times;</button>
        `;

        const container = document.querySelector('[data-optimization-alerts]');
        if (container) {
            container.insertBefore(notice, container.firstChild);
        }

        // Auto-dismiss after 3 seconds
        setTimeout(() => {
            if (notice.parentElement) {
                notice.remove();
            }
        }, 3000);
    };

    /**
     * Track historical box choices
     */
    BoxOptimizerUI.prototype.saveBoxHistory = function(transferId, dimensions, carrier) {
        const history = JSON.parse(localStorage.getItem('boxOptimizerHistory') || '[]');

        history.push({
            transferId: transferId,
            timestamp: new Date().toISOString(),
            dimensions: dimensions,
            carrier: carrier
        });

        // Keep last 50 entries
        if (history.length > 50) {
            history.shift();
        }

        localStorage.setItem('boxOptimizerHistory', JSON.stringify(history));
    };

    /**
     * Get frequently used box sizes
     */
    BoxOptimizerUI.prototype.getFrequentBoxes = function(limit = 5) {
        const history = JSON.parse(localStorage.getItem('boxOptimizerHistory') || '[]');
        const counts = {};

        history.forEach(entry => {
            const key = `${entry.dimensions.length}x${entry.dimensions.width}x${entry.dimensions.height}`;
            counts[key] = (counts[key] || 0) + 1;
        });

        return Object.entries(counts)
            .sort((a, b) => b[1] - a[1])
            .slice(0, limit)
            .map(([key, count]) => {
                const [l, w, h] = key.split('x').map(Number);
                return { length: l, width: w, height: h, uses: count };
            });
    };

    /**
     * Show a comparison chart of cost vs box size
     */
    BoxOptimizerUI.prototype.showCostComparison = function(suggestions) {
        if (!suggestions || suggestions.length === 0) return;

        // Create simple bar chart (can integrate with Chart.js later)
        const comparison = document.createElement('div');
        comparison.className = 'cost-comparison mt-3';
        comparison.style.cssText = `
            background: #f9fafb;
            padding: 15px;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
        `;

        let html = '<h6 class="mb-3">Cost Comparison</h6>';
        html += '<div style="display: grid; grid-template-columns: 1fr 1fr; gap: 10px;">';

        suggestions.forEach((s, idx) => {
            if (s.savings) {
                const barWidth = (s.savings / 100) * 100;
                html += `
                    <div style="padding: 8px; background: white; border-radius: 4px; border: 1px solid #e5e7eb;">
                        <div style="font-weight: 600; font-size: 12px; margin-bottom: 4px;">
                            ${s.type === 'smaller_box' ? 'Smaller Box' : s.type}
                        </div>
                        <div style="background: #e5e7eb; height: 4px; border-radius: 2px; overflow: hidden;">
                            <div style="background: #10b981; height: 100%; width: ${barWidth}%;"></div>
                        </div>
                        <div style="font-size: 11px; color: #10b981; margin-top: 4px; font-weight: 600;">
                            Save $${s.savings.toFixed(2)}
                        </div>
                    </div>
                `;
            }
        });

        html += '</div>';
        comparison.innerHTML = html;

        return comparison;
    };

    /**
     * Print packing label with optimized dimensions
     */
    BoxOptimizerUI.prototype.printPackingLabel = function(transferId, dimensions, carrier) {
        const printWindow = window.open('', '_blank', 'width=500,height=600');

        const html = `
            <!DOCTYPE html>
            <html>
            <head>
                <style>
                    body { font-family: Arial, sans-serif; padding: 20px; }
                    .label { border: 2px solid #000; padding: 15px; margin: 10px 0; }
                    .label h3 { margin: 0 0 10px 0; }
                    .dimensions { display: grid; grid-template-columns: 1fr 1fr 1fr 1fr; gap: 10px; margin: 10px 0; }
                    .dim { border: 1px solid #999; padding: 8px; text-align: center; }
                    .dim label { font-size: 11px; color: #666; }
                    .dim value { font-size: 16px; font-weight: bold; }
                </style>
            </head>
            <body onload="window.print()">
                <div class="label">
                    <h3>Packing Label</h3>
                    <p><strong>Transfer:</strong> #${transferId}</p>
                    <p><strong>Carrier:</strong> ${carrier}</p>
                    <div class="dimensions">
                        <div class="dim">
                            <label>Length</label>
                            <div value>${dimensions.length} cm</div>
                        </div>
                        <div class="dim">
                            <label>Width</label>
                            <div value>${dimensions.width} cm</div>
                        </div>
                        <div class="dim">
                            <label>Height</label>
                            <div value>${dimensions.height} cm</div>
                        </div>
                        <div class="dim">
                            <label>Weight</label>
                            <div value>${dimensions.weight} kg</div>
                        </div>
                    </div>
                </div>
            </body>
            </html>
        `;

        printWindow.document.write(html);
        printWindow.document.close();

        // Save to history
        this.saveBoxHistory(transferId, dimensions, carrier);
    };

    /**
     * Export packing data as CSV
     */
    BoxOptimizerUI.prototype.exportPackingData = function(transferId, filename) {
        const history = JSON.parse(localStorage.getItem('boxOptimizerHistory') || '[]');
        const transferHistory = history.filter(h => h.transferId === transferId);

        let csv = 'Transfer ID,Date,Length (cm),Width (cm),Height (cm),Weight (kg),Carrier\n';

        transferHistory.forEach(entry => {
            csv += `${entry.transferId},${entry.timestamp},${entry.dimensions.length},${entry.dimensions.width},${entry.dimensions.height},${entry.dimensions.weight},${entry.carrier}\n`;
        });

        // Download
        const blob = new Blob([csv], { type: 'text/csv' });
        const url = window.URL.createObjectURL(blob);
        const a = document.createElement('a');
        a.href = url;
        a.download = filename || `packing-${transferId}.csv`;
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
        window.URL.revokeObjectURL(url);
    };

    /**
     * Mobile-friendly interface enhancement
     */
    BoxOptimizerUI.prototype.detectMobile = function() {
        return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
    };

    /**
     * Optimize UI for mobile
     */
    BoxOptimizerUI.prototype.optimizeForMobile = function() {
        if (!this.detectMobile()) return;

        // Stack dimension inputs vertically on mobile
        const cols = document.querySelectorAll('[class*="col-md"]');
        cols.forEach(col => {
            col.classList.remove('col-md-3');
            col.classList.add('col-12');
        });

        // Make buttons full width
        const buttons = document.querySelectorAll('.btn-outline-secondary, .btn-outline-success');
        buttons.forEach(btn => {
            btn.classList.add('btn-block', 'mb-2');
        });

        // Increase touch target size
        const inputs = document.querySelectorAll('.form-control-sm');
        inputs.forEach(input => {
            input.style.padding = '0.75rem 0.5rem';
            input.style.fontSize = '16px'; // Prevents zoom on iOS
        });
    };
}

// Auto-optimize for mobile on load
document.addEventListener('DOMContentLoaded', function() {
    if (window.boxOptimizer && window.boxOptimizer.optimizeForMobile) {
        window.boxOptimizer.optimizeForMobile();
    }
});
