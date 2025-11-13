/**
 * Validation UI Integration
 * Connects validation engine to UI panels
 * Updates 13-validation-tools.js with working implementations
 *
 * @version 1.0.0
 */

class ValidationUIIntegration {
    constructor() {
        this.engine = window.validationEngine;
        this.results = {};
        this.init();
    }

    init() {
        // Hook into existing validation tools if they exist
        if (window.validationTools) {
            this.enhanceValidationTools();
        }
        console.log('✅ Validation UI Integration loaded');
    }

    enhanceValidationTools() {
        const tools = window.validationTools;

        // Override validation methods with working implementations
        tools.validateHTML = () => this.validateHTML();
        tools.validateCSS = () => this.validateCSS();
        tools.validateJS = () => this.validateJS();
        tools.validateAll = () => this.validateAll();

        // Override formatting methods
        tools.formatCode = (mode) => this.formatCode(mode);
        tools.beautifyAll = () => this.beautifyAll();

        // Override optimization methods
        tools.optimizeCSS = () => this.optimizeCSS();
        tools.optimizeJS = () => this.optimizeJS();
        tools.removeUnusedCSS = () => this.removeUnusedCSS();
        tools.analyzePerformance = () => this.analyzePerformance();
    }

    // =====================================================================
    // VALIDATION METHODS
    // =====================================================================

    async validateHTML() {
        this.showLoading('Validating HTML5...');

        const html = window.htmlEditor?.getValue() || '';
        const { errors, warnings } = this.engine.validateHTML(html);
        const stats = this.engine.getFileStats(html, 'html');

        this.displayResults('HTML5 Validation', errors, warnings, stats);

        // Highlight errors in editor
        this.highlightErrors(window.htmlEditor, errors, 'error');
    }

    async validateCSS() {
        this.showLoading('Validating CSS...');

        const css = window.cssEditor?.getValue() || '';
        const { errors, warnings } = this.engine.validateCSS(css);
        const stats = this.engine.getFileStats(css, 'css');

        this.displayResults('CSS Validation', errors, warnings, stats);

        // Highlight errors in editor
        this.highlightErrors(window.cssEditor, errors, 'error');
    }

    async validateJS() {
        this.showLoading('Validating JavaScript...');

        const js = window.jsEditor?.getValue() || '';
        const { errors, warnings } = this.engine.validateJS(js);
        const stats = this.engine.getFileStats(js, 'js');

        this.displayResults('JavaScript Validation', errors, warnings, stats);

        // Highlight errors in editor
        this.highlightErrors(window.jsEditor, errors, 'error');
    }

    async validateAll() {
        this.showLoading('Validating all code...');

        const htmlResult = this.engine.validateHTML(window.htmlEditor?.getValue() || '');
        const cssResult = this.engine.validateCSS(window.cssEditor?.getValue() || '');
        const jsResult = this.engine.validateJS(window.jsEditor?.getValue() || '');

        const totalErrors = htmlResult.errors.length + cssResult.errors.length + jsResult.errors.length;
        const totalWarnings = htmlResult.warnings.length + cssResult.warnings.length + jsResult.warnings.length;

        const summary = `
            <div class="validation-summary">
                <h6><i class="fas fa-check-circle"></i> Validation Summary</h6>
                <div class="summary-stats">
                    <div class="stat">
                        <span class="stat-icon error"><i class="fas fa-times"></i></span>
                        <span class="stat-label">Errors: ${totalErrors}</span>
                    </div>
                    <div class="stat">
                        <span class="stat-icon warning"><i class="fas fa-exclamation"></i></span>
                        <span class="stat-label">Warnings: ${totalWarnings}</span>
                    </div>
                </div>
                <div class="validation-sections">
                    <details>
                        <summary><i class="fab fa-html5"></i> HTML5 (${htmlResult.errors.length} errors, ${htmlResult.warnings.length} warnings)</summary>
                        <div class="issues-list">
                            ${this.renderIssues(htmlResult.errors, htmlResult.warnings)}
                        </div>
                    </details>
                    <details>
                        <summary><i class="fab fa-css3-alt"></i> CSS (${cssResult.errors.length} errors, ${cssResult.warnings.length} warnings)</summary>
                        <div class="issues-list">
                            ${this.renderIssues(cssResult.errors, cssResult.warnings)}
                        </div>
                    </details>
                    <details>
                        <summary><i class="fab fa-js"></i> JavaScript (${jsResult.errors.length} errors, ${jsResult.warnings.length} warnings)</summary>
                        <div class="issues-list">
                            ${this.renderIssues(jsResult.errors, jsResult.warnings)}
                        </div>
                    </details>
                </div>
            </div>
        `;

        this.displayRawHTML(summary);
    }

    // =====================================================================
    // FORMATTING METHODS
    // =====================================================================

    async formatCode(mode) {
        this.showLoading(`Formatting code (${mode})...`);

        const html = window.htmlEditor?.getValue() || '';
        const css = window.cssEditor?.getValue() || '';
        const js = window.jsEditor?.getValue() || '';

        // Format each editor
        if (html) {
            const formatted = this.engine.formatHTML(html, mode);
            window.htmlEditor?.setValue(formatted);
        }

        if (css) {
            const formatted = this.engine.formatCSS(css, mode);
            window.cssEditor?.setValue(formatted);
        }

        if (js) {
            const formatted = this.engine.formatJS(js, mode);
            window.jsEditor?.setValue(formatted);
        }

        this.showSuccess(`✅ Code formatted to ${mode} mode`);
        this.updatePreview();
    }

    async beautifyAll() {
        this.formatCode('pretty');
    }

    // =====================================================================
    // OPTIMIZATION METHODS
    // =====================================================================

    async optimizeCSS() {
        this.showLoading('Optimizing CSS...');

        const css = window.cssEditor?.getValue() || '';
        const original = css.length;

        const minified = this.engine.minifyCSS(css);
        const optimized = original - minified.length;
        const savings = ((optimized / original) * 100).toFixed(1);

        window.cssEditor?.setValue(minified);

        const result = `
            <div class="optimization-result success">
                <h6><i class="fas fa-check"></i> CSS Optimized</h6>
                <p><strong>Original:</strong> ${original} bytes</p>
                <p><strong>Minified:</strong> ${minified.length} bytes</p>
                <p><strong>Saved:</strong> ${optimized} bytes (${savings}%)</p>
            </div>
        `;

        this.displayRawHTML(result);
        this.updatePreview();
    }

    async optimizeJS() {
        this.showLoading('Optimizing JavaScript...');

        const js = window.jsEditor?.getValue() || '';
        const original = js.length;

        const minified = this.engine.minifyJS(js);
        const optimized = original - minified.length;
        const savings = ((optimized / original) * 100).toFixed(1);

        window.jsEditor?.setValue(minified);

        const result = `
            <div class="optimization-result success">
                <h6><i class="fas fa-check"></i> JavaScript Optimized</h6>
                <p><strong>Original:</strong> ${original} bytes</p>
                <p><strong>Minified:</strong> ${minified.length} bytes</p>
                <p><strong>Saved:</strong> ${optimized} bytes (${savings}%)</p>
            </div>
        `;

        this.displayRawHTML(result);
        this.updatePreview();
    }

    async removeUnusedCSS() {
        this.showLoading('Analyzing CSS usage...');

        const css = window.cssEditor?.getValue() || '';
        const html = window.htmlEditor?.getValue() || '';

        const selectors = css.match(/([^{]+)\s*\{/g) || [];
        const unused = [];

        selectors.forEach(selector => {
            const selectorName = selector.replace(/\{/, '').trim();
            const classOrId = selectorName.match(/\.[\w-]+|#[\w-]+/g);

            if (classOrId) {
                classOrId.forEach(className => {
                    if (!html.includes(className)) {
                        unused.push(className);
                    }
                });
            }
        });

        const result = `
            <div class="optimization-result">
                <h6><i class="fas fa-trash"></i> Unused CSS Analysis</h6>
                <p>Found ${unused.length} potentially unused selectors:</p>
                <ul style="margin: 0.5rem 0; padding-left: 1.5rem;">
                    ${unused.slice(0, 10).map(u => `<li><code>${u}</code></li>`).join('')}
                    ${unused.length > 10 ? `<li>... and ${unused.length - 10} more</li>` : ''}
                </ul>
            </div>
        `;

        this.displayRawHTML(result);
    }

    async analyzePerformance() {
        this.showLoading('Analyzing performance...');

        const html = window.htmlEditor?.getValue() || '';
        const css = window.cssEditor?.getValue() || '';
        const js = window.jsEditor?.getValue() || '';

        const htmlStats = this.engine.getFileStats(html, 'html');
        const cssStats = this.engine.getFileStats(css, 'css');
        const jsStats = this.engine.getFileStats(js, 'js');
        const jsComplexity = this.engine.calculateComplexity(js);

        const result = `
            <div class="performance-analysis">
                <h6><i class="fas fa-tachometer-alt"></i> Performance Analysis</h6>

                <div class="perf-section">
                    <strong>Size Analysis</strong>
                    <div class="perf-item">
                        <span>HTML: ${htmlStats.bytes} bytes, ${htmlStats.lines} lines</span>
                    </div>
                    <div class="perf-item">
                        <span>CSS: ${cssStats.bytes} bytes, ${cssStats.lines} lines</span>
                    </div>
                    <div class="perf-item">
                        <span>JS: ${jsStats.bytes} bytes, ${jsStats.lines} lines</span>
                    </div>
                </div>

                <div class="perf-section">
                    <strong>Code Complexity</strong>
                    <div class="perf-item">
                        <span>Functions: ${jsComplexity.functions}</span>
                    </div>
                    <div class="perf-item">
                        <span>Loops: ${jsComplexity.loops}</span>
                    </div>
                    <div class="perf-item">
                        <span>Conditionals: ${jsComplexity.conditionals}</span>
                    </div>
                    <div class="perf-item">
                        <span>Async Operations: ${jsComplexity.asyncOperations}</span>
                    </div>
                </div>

                <div class="perf-recommendations">
                    ${jsStats.bytes > 50000 ? '<p>⚠️ Large JavaScript file. Consider code splitting.</p>' : ''}
                    ${jsComplexity.loops > 10 ? '<p>⚠️ Many loops detected. Check for optimization opportunities.</p>' : ''}
                    ${htmlStats.lines > 500 ? '<p>ℹ️ Large HTML file. Consider component-based approach.</p>' : ''}
                </div>
            </div>
        `;

        this.displayRawHTML(result);
    }

    // =====================================================================
    // UI DISPLAY METHODS
    // =====================================================================

    displayResults(title, errors, warnings, stats) {
        const resultsPanel = document.getElementById('validation-results');
        const output = document.getElementById('validation-output');

        if (!resultsPanel || !output) return;

        const html = `
            <h6><i class="fas fa-clipboard-list"></i> ${title}</h6>

            <div class="validation-stats">
                <span class="stat"><i class="fas fa-check"></i> Errors: ${errors.length}</span>
                <span class="stat"><i class="fas fa-exclamation"></i> Warnings: ${warnings.length}</span>
                ${stats ? `<span class="stat"><i class="fas fa-file"></i> ${stats.bytes} bytes</span>` : ''}
            </div>

            <div class="validation-issues">
                ${this.renderIssues(errors, warnings)}
            </div>
        `;

        output.innerHTML = html;
        resultsPanel.style.display = 'block';
    }

    renderIssues(errors, warnings) {
        const allIssues = [
            ...errors.map(e => ({ ...e, severity: 'error' })),
            ...warnings.map(w => ({ ...w, severity: 'warning' }))
        ];

        return allIssues.map(issue => `
            <div class="validation-item ${issue.severity}">
                <i class="fas ${issue.severity === 'error' ? 'fa-times-circle' : 'fa-exclamation-circle'}"></i>
                <div>
                    <strong>${issue.severity.toUpperCase()}</strong>
                    ${issue.line ? `<span class="line">(Line ${issue.line})</span>` : ''}
                    <p>${issue.message}</p>
                </div>
            </div>
        `).join('');
    }

    displayRawHTML(html) {
        const resultsPanel = document.getElementById('validation-results');
        const output = document.getElementById('validation-output');

        if (!resultsPanel || !output) return;

        output.innerHTML = html;
        resultsPanel.style.display = 'block';
    }

    highlightErrors(editor, errors, severity = 'error') {
        if (!editor || !errors.length) return;

        // Monaco/CodeMirror specific highlighting would go here
        console.log(`Highlighting ${errors.length} ${severity} items`);
    }

    showLoading(message) {
        const output = document.getElementById('validation-output');
        if (output) {
            output.innerHTML = `<div class="loading"><i class="fas fa-spinner fa-spin"></i> ${message}</div>`;
        }
    }

    showSuccess(message) {
        const output = document.getElementById('validation-output');
        if (output) {
            output.innerHTML = `<div class="success-message"><i class="fas fa-check-circle"></i> ${message}</div>`;
        }
    }

    updatePreview() {
        // Trigger preview update if available
        if (window.updatePreview) {
            window.updatePreview();
        }
    }
}

// Auto-initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        window.validationUIIntegration = new ValidationUIIntegration();
    });
} else {
    window.validationUIIntegration = new ValidationUIIntegration();
}
