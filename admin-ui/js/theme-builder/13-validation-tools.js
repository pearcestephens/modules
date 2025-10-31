/**
 * Code Validation & Formatting Tools
 * HTML5, CSS, JavaScript validators and formatters
 *
 * Features:
 * - HTML5 validation
 * - CSS validation and linting
 * - JavaScript validation (ESLint-style)
 * - Code minification
 * - Code beautification
 * - Multiple formatting modes
 *
 * @version 1.0.0
 */

class CodeValidationTools {
    constructor() {
        this.validationResults = {};
        this.formattingMode = 'pretty'; // 'pretty', 'compact', 'minified'

        this.init();
    }

    init() {
        this.createToolsPanel();
        this.loadValidators();
        console.log('🔧 Code Validation Tools initialized');
    }

    createToolsPanel() {
        const panel = document.createElement('div');
        panel.id = 'validation-tools-panel';
        panel.className = 'validation-tools-panel';
        panel.innerHTML = `
            <div class="tools-header">
                <h4><i class="fas fa-tools"></i> Code Tools</h4>
                <button class="btn-collapse-tools" onclick="validationTools.togglePanel()">
                    <i class="fas fa-chevron-up"></i>
                </button>
            </div>

            <div class="tools-content">
                <!-- Validation Section -->
                <div class="tools-section">
                    <h5><i class="fas fa-check-circle"></i> Validation</h5>
                    <div class="tools-buttons">
                        <button class="btn-tool" onclick="validationTools.validateHTML()">
                            <i class="fab fa-html5"></i> Validate HTML5
                        </button>
                        <button class="btn-tool" onclick="validationTools.validateCSS()">
                            <i class="fab fa-css3-alt"></i> Validate CSS
                        </button>
                        <button class="btn-tool" onclick="validationTools.validateJS()">
                            <i class="fab fa-js"></i> Validate JavaScript
                        </button>
                        <button class="btn-tool btn-primary" onclick="validationTools.validateAll()">
                            <i class="fas fa-check-double"></i> Validate All
                        </button>
                    </div>
                </div>

                <!-- Formatting Section -->
                <div class="tools-section">
                    <h5><i class="fas fa-magic"></i> Formatting</h5>
                    <div class="tools-buttons">
                        <button class="btn-tool" onclick="validationTools.formatCode('pretty')">
                            <i class="fas fa-align-left"></i> Pretty
                        </button>
                        <button class="btn-tool" onclick="validationTools.formatCode('compact')">
                            <i class="fas fa-compress"></i> Compact
                        </button>
                        <button class="btn-tool" onclick="validationTools.formatCode('minified')">
                            <i class="fas fa-minus-square"></i> Minify
                        </button>
                        <button class="btn-tool" onclick="validationTools.beautifyAll()">
                            <i class="fas fa-star"></i> Beautify All
                        </button>
                    </div>
                </div>

                <!-- Optimization Section -->
                <div class="tools-section">
                    <h5><i class="fas fa-rocket"></i> Optimization</h5>
                    <div class="tools-buttons">
                        <button class="btn-tool" onclick="validationTools.optimizeCSS()">
                            <i class="fas fa-compress-arrows-alt"></i> Optimize CSS
                        </button>
                        <button class="btn-tool" onclick="validationTools.optimizeJS()">
                            <i class="fas fa-bolt"></i> Optimize JS
                        </button>
                        <button class="btn-tool" onclick="validationTools.removeUnusedCSS()">
                            <i class="fas fa-trash-alt"></i> Remove Unused
                        </button>
                        <button class="btn-tool" onclick="validationTools.analyzePerformance()">
                            <i class="fas fa-tachometer-alt"></i> Performance
                        </button>
                    </div>
                </div>

                <!-- Results Section -->
                <div class="tools-section validation-results" id="validation-results" style="display: none;">
                    <h5><i class="fas fa-clipboard-list"></i> Results</h5>
                    <div id="validation-output" class="validation-output"></div>
                </div>
            </div>
        `;

        document.body.appendChild(panel);
        this.addStyles();
    }

    addStyles() {
        const style = document.createElement('style');
        style.textContent = `
            .validation-tools-panel {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 420px;
                background: linear-gradient(135deg, #1e293b 0%, #0f172a 100%);
                border-top: 2px solid #10b981;
                box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.3);
                z-index: 850;
                transition: transform 0.3s ease;
            }

            .validation-tools-panel.collapsed {
                transform: translateY(calc(100% - 50px));
            }

            .tools-header {
                padding: 0.75rem 1.5rem;
                background: rgba(16, 185, 129, 0.1);
                border-bottom: 1px solid rgba(16, 185, 129, 0.3);
                display: flex;
                justify-content: space-between;
                align-items: center;
                cursor: pointer;
            }

            .tools-header h4 {
                margin: 0;
                font-size: 1rem;
                font-weight: 600;
                color: #10b981;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }

            .btn-collapse-tools {
                width: 32px;
                height: 32px;
                background: transparent;
                border: 1px solid rgba(16, 185, 129, 0.3);
                border-radius: 4px;
                color: #10b981;
                cursor: pointer;
                transition: all 0.2s;
            }

            .btn-collapse-tools:hover {
                background: rgba(16, 185, 129, 0.2);
            }

            .tools-content {
                padding: 1rem 1.5rem;
                max-height: 300px;
                overflow-y: auto;
            }

            .tools-section {
                margin-bottom: 1.5rem;
            }

            .tools-section:last-child {
                margin-bottom: 0;
            }

            .tools-section h5 {
                margin: 0 0 0.75rem 0;
                font-size: 0.875rem;
                color: #f1f5f9;
                font-weight: 600;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }

            .tools-section h5 i {
                color: #10b981;
            }

            .tools-buttons {
                display: flex;
                flex-wrap: wrap;
                gap: 0.5rem;
            }

            .btn-tool {
                padding: 0.5rem 1rem;
                background: #334155;
                border: 1px solid #475569;
                border-radius: 6px;
                color: #f1f5f9;
                font-size: 0.875rem;
                font-weight: 500;
                cursor: pointer;
                transition: all 0.2s;
                display: flex;
                align-items: center;
                gap: 0.5rem;
            }

            .btn-tool:hover {
                background: #475569;
                transform: translateY(-1px);
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            }

            .btn-tool.btn-primary {
                background: linear-gradient(135deg, #10b981, #059669);
                border-color: #10b981;
            }

            .btn-tool.btn-primary:hover {
                box-shadow: 0 2px 8px rgba(16, 185, 129, 0.3);
            }

            .validation-output {
                background: #0f172a;
                border: 1px solid #334155;
                border-radius: 6px;
                padding: 1rem;
                max-height: 200px;
                overflow-y: auto;
            }

            .validation-item {
                padding: 0.75rem;
                margin-bottom: 0.5rem;
                border-radius: 6px;
                display: flex;
                align-items: flex-start;
                gap: 0.75rem;
            }

            .validation-item:last-child {
                margin-bottom: 0;
            }

            .validation-item.success {
                background: rgba(16, 185, 129, 0.1);
                border-left: 3px solid #10b981;
            }

            .validation-item.error {
                background: rgba(239, 68, 68, 0.1);
                border-left: 3px solid #ef4444;
            }

            .validation-item.warning {
                background: rgba(245, 158, 11, 0.1);
                border-left: 3px solid #f59e0b;
            }

            .validation-item i {
                font-size: 1.25rem;
                margin-top: 0.125rem;
            }

            .validation-item.success i {
                color: #10b981;
            }

            .validation-item.error i {
                color: #ef4444;
            }

            .validation-item.warning i {
                color: #f59e0b;
            }

            .validation-text {
                flex: 1;
            }

            .validation-text strong {
                display: block;
                margin-bottom: 0.25rem;
                color: #f1f5f9;
            }

            .validation-text span {
                font-size: 0.875rem;
                color: #94a3b8;
            }

            .validation-stats {
                display: flex;
                gap: 1rem;
                margin-top: 0.5rem;
                padding-top: 0.5rem;
                border-top: 1px solid #334155;
            }

            .stat-item {
                display: flex;
                align-items: center;
                gap: 0.5rem;
                font-size: 0.875rem;
            }

            .stat-item i {
                font-size: 1rem;
            }
        `;

        document.head.appendChild(style);
    }

    loadValidators() {
        // Load external validators if needed
        // For now, using built-in validators
    }

    togglePanel() {
        const panel = document.getElementById('validation-tools-panel');
        panel.classList.toggle('collapsed');
    }

    // =====================================================
    // HTML5 VALIDATION
    // =====================================================

    async validateHTML() {
        this.showResults('Validating HTML5...', 'info');

        const html = window.htmlEditor?.getValue() || '';
        const errors = [];
        const warnings = [];

        // Check DOCTYPE
        if (!html.includes('<!DOCTYPE html>')) {
            errors.push({
                line: 1,
                message: 'Missing <!DOCTYPE html> declaration',
                type: 'error'
            });
        }

        // Check required meta tags
        if (!html.includes('<meta charset=')) {
            warnings.push({
                line: null,
                message: 'Missing charset meta tag',
                type: 'warning'
            });
        }

        if (!html.includes('<meta name="viewport"')) {
            warnings.push({
                line: null,
                message: 'Missing viewport meta tag for responsive design',
                type: 'warning'
            });
        }

        // Check semantic HTML5 tags usage
        const hasSemanticTags = /<(header|nav|main|article|section|aside|footer)/.test(html);
        if (!hasSemanticTags) {
            warnings.push({
                message: 'Consider using HTML5 semantic tags (header, nav, main, etc.)',
                type: 'warning'
            });
        }

        // Check for unclosed tags
        const openTags = html.match(/<(\w+)[^>]*>/g) || [];
        const closeTags = html.match(/<\/(\w+)>/g) || [];

        const selfClosing = ['img', 'br', 'hr', 'input', 'meta', 'link'];
        openTags.forEach(tag => {
            const tagName = tag.match(/<(\w+)/)[1].toLowerCase();
            if (!selfClosing.includes(tagName)) {
                const closeTag = `</${tagName}>`;
                if (!html.includes(closeTag)) {
                    errors.push({
                        message: `Unclosed tag: ${tagName}`,
                        type: 'error'
                    });
                }
            }
        });

        // Check alt attributes on images
        const imgTags = html.match(/<img[^>]*>/g) || [];
        imgTags.forEach(img => {
            if (!img.includes('alt=')) {
                warnings.push({
                    message: 'Image missing alt attribute (accessibility issue)',
                    type: 'warning'
                });
            }
        });

        // Check for accessibility
        if (!html.includes('lang=')) {
            warnings.push({
                message: 'Missing lang attribute on <html> tag',
                type: 'warning'
            });
        }

        this.displayValidationResults('HTML5', errors, warnings);
    }

    // =====================================================
    // CSS VALIDATION
    // =====================================================

    async validateCSS() {
        this.showResults('Validating CSS...', 'info');

        const css = window.cssEditor?.getValue() || '';
        const errors = [];
        const warnings = [];

        // Check for syntax errors
        const lines = css.split('\n');
        let braceCount = 0;

        lines.forEach((line, index) => {
            braceCount += (line.match(/\{/g) || []).length;
            braceCount -= (line.match(/\}/g) || []).length;

            // Check for missing semicolons
            if (line.trim() && !line.trim().endsWith(';') && !line.trim().endsWith('{') && !line.trim().endsWith('}') && line.includes(':')) {
                warnings.push({
                    line: index + 1,
                    message: 'Missing semicolon',
                    type: 'warning'
                });
            }
        });

        if (braceCount !== 0) {
            errors.push({
                message: `Unmatched braces: ${braceCount > 0 ? 'missing closing' : 'extra closing'} brace(s)`,
                type: 'error'
            });
        }

        // Check for !important overuse
        const importantCount = (css.match(/!important/g) || []).length;
        if (importantCount > 5) {
            warnings.push({
                message: `Excessive use of !important (${importantCount} occurrences)`,
                type: 'warning'
            });
        }

        // Check for vendor prefixes
        const prefixes = ['-webkit-', '-moz-', '-ms-', '-o-'];
        prefixes.forEach(prefix => {
            if (css.includes(prefix)) {
                warnings.push({
                    message: `Consider using autoprefixer instead of manual ${prefix} prefixes`,
                    type: 'warning'
                });
            }
        });

        // Check for color format consistency
        const hexColors = (css.match(/#[0-9a-f]{3,6}/gi) || []).length;
        const rgbColors = (css.match(/rgb\(/gi) || []).length;
        const hslColors = (css.match(/hsl\(/gi) || []).length;

        if ([hexColors, rgbColors, hslColors].filter(count => count > 0).length > 1) {
            warnings.push({
                message: 'Inconsistent color formats (mixing hex, rgb, hsl)',
                type: 'warning'
            });
        }

        // Check for CSS variables usage
        if (!css.includes('var(--')) {
            warnings.push({
                message: 'Consider using CSS variables for better maintainability',
                type: 'warning'
            });
        }

        this.displayValidationResults('CSS', errors, warnings);
    }

    // =====================================================
    // JAVASCRIPT VALIDATION
    // =====================================================

    async validateJS() {
        this.showResults('Validating JavaScript...', 'info');

        const js = window.jsEditor?.getValue() || '';
        const errors = [];
        const warnings = [];

        // Check for syntax errors using try/catch
        try {
            new Function(js);
        } catch (e) {
            errors.push({
                message: `Syntax error: ${e.message}`,
                type: 'error'
            });
        }

        // Check for console.log statements
        const consoleLogs = (js.match(/console\.log/g) || []).length;
        if (consoleLogs > 0) {
            warnings.push({
                message: `Found ${consoleLogs} console.log statement(s) - remove before production`,
                type: 'warning'
            });
        }

        // Check for var usage (recommend let/const)
        const varCount = (js.match(/\bvar\s+/g) || []).length;
        if (varCount > 0) {
            warnings.push({
                message: `Using 'var' (${varCount}x) - consider 'let' or 'const' instead`,
                type: 'warning'
            });
        }

        // Check for == instead of ===
        const looseEquality = (js.match(/[^=!]==[^=]/g) || []).length;
        if (looseEquality > 0) {
            warnings.push({
                message: `Using loose equality (==) - recommend strict equality (===)`,
                type: 'warning'
            });
        }

        // Check for missing semicolons
        const lines = js.split('\n');
        lines.forEach((line, index) => {
            const trimmed = line.trim();
            if (trimmed &&
                !trimmed.endsWith(';') &&
                !trimmed.endsWith('{') &&
                !trimmed.endsWith('}') &&
                !trimmed.startsWith('//') &&
                !trimmed.startsWith('/*') &&
                !trimmed.includes('function') &&
                trimmed.length > 3) {
                warnings.push({
                    line: index + 1,
                    message: 'Missing semicolon',
                    type: 'warning'
                });
            }
        });

        // Check for function complexity
        const functionMatches = js.match(/function\s+\w+\s*\([^)]*\)\s*\{[^}]+\}/g) || [];
        functionMatches.forEach(func => {
            const lines = func.split('\n').length;
            if (lines > 20) {
                warnings.push({
                    message: `Function is too long (${lines} lines) - consider breaking it down`,
                    type: 'warning'
                });
            }
        });

        this.displayValidationResults('JavaScript', errors, warnings);
    }

    async validateAll() {
        this.showResults('Validating all files...', 'info');

        await this.validateHTML();
        await new Promise(resolve => setTimeout(resolve, 500));
        await this.validateCSS();
        await new Promise(resolve => setTimeout(resolve, 500));
        await this.validateJS();
    }

    // =====================================================
    // FORMATTING
    // =====================================================

    formatCode(mode) {
        this.formattingMode = mode;

        const activeTab = document.querySelector('.tab.active')?.dataset.tab;

        switch(activeTab) {
            case 'html':
                this.formatHTML(mode);
                break;
            case 'css':
                this.formatCSS(mode);
                break;
            case 'javascript':
                this.formatJS(mode);
                break;
        }

        this.showResults(`Code formatted in ${mode} mode`, 'success');
    }

    formatHTML(mode) {
        const html = window.htmlEditor?.getValue() || '';
        let formatted;

        if (mode === 'minified') {
            formatted = html
                .replace(/\s+/g, ' ')
                .replace(/>\s+</g, '><')
                .trim();
        } else if (mode === 'compact') {
            formatted = this.htmlBeautify(html, 2);
        } else {
            formatted = this.htmlBeautify(html, 4);
        }

        window.htmlEditor?.setValue(formatted);
    }

    formatCSS(mode) {
        const css = window.cssEditor?.getValue() || '';
        let formatted;

        if (mode === 'minified') {
            formatted = css
                .replace(/\s+/g, ' ')
                .replace(/\s*{\s*/g, '{')
                .replace(/\s*}\s*/g, '}')
                .replace(/\s*:\s*/g, ':')
                .replace(/\s*;\s*/g, ';')
                .replace(/;\s*}/g, '}')
                .trim();
        } else if (mode === 'compact') {
            formatted = this.cssBeautify(css, 2);
        } else {
            formatted = this.cssBeautify(css, 4);
        }

        window.cssEditor?.setValue(formatted);
    }

    formatJS(mode) {
        const js = window.jsEditor?.getValue() || '';
        let formatted;

        if (mode === 'minified') {
            formatted = js
                .replace(/\s+/g, ' ')
                .replace(/\s*{\s*/g, '{')
                .replace(/\s*}\s*/g, '}')
                .replace(/\s*;\s*/g, ';')
                .trim();
        } else if (mode === 'compact') {
            formatted = this.jsBeautify(js, 2);
        } else {
            formatted = this.jsBeautify(js, 4);
        }

        window.jsEditor?.setValue(formatted);
    }

    beautifyAll() {
        this.formatHTML('pretty');
        this.formatCSS('pretty');
        this.formatJS('pretty');
        this.showResults('All code beautified!', 'success');
    }

    // =====================================================
    // BEAUTIFY HELPERS
    // =====================================================

    htmlBeautify(html, indent = 4) {
        const tab = ' '.repeat(indent);
        let result = '';
        let level = 0;
        const tokens = html.split(/(<[^>]+>)/g).filter(t => t.trim());

        tokens.forEach(token => {
            if (token.startsWith('</')) {
                level--;
                result += tab.repeat(level) + token + '\n';
            } else if (token.startsWith('<') && !token.startsWith('<!') && !token.endsWith('/>')) {
                result += tab.repeat(level) + token + '\n';
                if (!token.match(/<(img|br|hr|input|meta|link)/i)) {
                    level++;
                }
            } else if (token.startsWith('<')) {
                result += tab.repeat(level) + token + '\n';
            } else if (token.trim()) {
                result += tab.repeat(level) + token.trim() + '\n';
            }
        });

        return result.trim();
    }

    cssBeautify(css, indent = 4) {
        const tab = ' '.repeat(indent);
        return css
            .replace(/\s*{\s*/g, ' {\n')
            .replace(/\s*}\s*/g, '\n}\n')
            .replace(/\s*;\s*/g, ';\n')
            .split('\n')
            .map(line => {
                const trimmed = line.trim();
                if (trimmed.endsWith('{')) return trimmed;
                if (trimmed === '}') return trimmed;
                if (trimmed) return tab + trimmed;
                return '';
            })
            .join('\n')
            .replace(/\n{3,}/g, '\n\n');
    }

    jsBeautify(js, indent = 4) {
        const tab = ' '.repeat(indent);
        let level = 0;

        return js
            .split('\n')
            .map(line => {
                const trimmed = line.trim();
                if (trimmed.includes('}')) level = Math.max(0, level - 1);
                const indented = tab.repeat(level) + trimmed;
                if (trimmed.endsWith('{')) level++;
                return indented;
            })
            .join('\n');
    }

    // =====================================================
    // OPTIMIZATION
    // =====================================================

    optimizeCSS() {
        this.showResults('Optimizing CSS...', 'info');

        const css = window.cssEditor?.getValue() || '';

        // Remove comments
        let optimized = css.replace(/\/\*[\s\S]*?\*\//g, '');

        // Remove extra whitespace
        optimized = optimized.replace(/\s+/g, ' ');

        // Remove unnecessary semicolons
        optimized = optimized.replace(/;}/g, '}');

        window.cssEditor?.setValue(optimized);
        this.showResults('CSS optimized!', 'success');
    }

    optimizeJS() {
        this.showResults('Optimizing JavaScript...', 'info');

        const js = window.jsEditor?.getValue() || '';

        // Remove comments
        let optimized = js.replace(/\/\*[\s\S]*?\*\//g, '');
        optimized = optimized.replace(/\/\/.*/g, '');

        // Remove extra whitespace
        optimized = optimized.replace(/\s+/g, ' ');

        window.jsEditor?.setValue(optimized);
        this.showResults('JavaScript optimized!', 'success');
    }

    removeUnusedCSS() {
        this.showResults('Analyzing unused CSS...', 'info');

        // This would require preview iframe analysis
        this.showResults('Feature coming soon: Analyze preview to detect unused CSS rules', 'warning');
    }

    analyzePerformance() {
        this.showResults('Analyzing performance...', 'info');

        const html = window.htmlEditor?.getValue() || '';
        const css = window.cssEditor?.getValue() || '';
        const js = window.jsEditor?.getValue() || '';

        const stats = {
            htmlSize: new Blob([html]).size,
            cssSize: new Blob([css]).size,
            jsSize: new Blob([js]).size,
            totalSize: new Blob([html + css + js]).size
        };

        const output = `
            <div class="validation-item success">
                <i class="fas fa-chart-bar"></i>
                <div class="validation-text">
                    <strong>Performance Analysis</strong>
                    <div class="validation-stats">
                        <div class="stat-item">
                            <i class="fab fa-html5"></i>
                            ${(stats.htmlSize / 1024).toFixed(2)} KB
                        </div>
                        <div class="stat-item">
                            <i class="fab fa-css3-alt"></i>
                            ${(stats.cssSize / 1024).toFixed(2)} KB
                        </div>
                        <div class="stat-item">
                            <i class="fab fa-js"></i>
                            ${(stats.jsSize / 1024).toFixed(2)} KB
                        </div>
                        <div class="stat-item">
                            <i class="fas fa-compress"></i>
                            Total: ${(stats.totalSize / 1024).toFixed(2)} KB
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.getElementById('validation-output').innerHTML = output;
        document.getElementById('validation-results').style.display = 'block';
    }

    // =====================================================
    // DISPLAY HELPERS
    // =====================================================

    displayValidationResults(type, errors, warnings) {
        const resultsDiv = document.getElementById('validation-results');
        const outputDiv = document.getElementById('validation-output');

        let html = '';

        if (errors.length === 0 && warnings.length === 0) {
            html = `
                <div class="validation-item success">
                    <i class="fas fa-check-circle"></i>
                    <div class="validation-text">
                        <strong>${type} Validation Passed</strong>
                        <span>No errors or warnings found</span>
                    </div>
                </div>
            `;
        } else {
            errors.forEach(error => {
                html += `
                    <div class="validation-item error">
                        <i class="fas fa-times-circle"></i>
                        <div class="validation-text">
                            <strong>Error${error.line ? ` (Line ${error.line})` : ''}</strong>
                            <span>${error.message}</span>
                        </div>
                    </div>
                `;
            });

            warnings.forEach(warning => {
                html += `
                    <div class="validation-item warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <div class="validation-text">
                            <strong>Warning${warning.line ? ` (Line ${warning.line})` : ''}</strong>
                            <span>${warning.message}</span>
                        </div>
                    </div>
                `;
            });
        }

        outputDiv.innerHTML = html;
        resultsDiv.style.display = 'block';
    }

    showResults(message, type = 'info') {
        const resultsDiv = document.getElementById('validation-results');
        const outputDiv = document.getElementById('validation-output');

        const icons = {
            info: 'fa-info-circle',
            success: 'fa-check-circle',
            warning: 'fa-exclamation-triangle',
            error: 'fa-times-circle'
        };

        outputDiv.innerHTML = `
            <div class="validation-item ${type}">
                <i class="fas ${icons[type]}"></i>
                <div class="validation-text">
                    <strong>${message}</strong>
                </div>
            </div>
        `;

        resultsDiv.style.display = 'block';
    }
}

// Auto-initialize
let validationTools;
document.addEventListener('DOMContentLoaded', () => {
    validationTools = new CodeValidationTools();
});
