/**
 * Validation & Formatting Engine
 * Complete validators for HTML5, CSS, JavaScript
 * Minification and multiple formatting modes
 *
 * @version 1.0.0
 */

class ValidationEngine {
    constructor() {
        this.validationCache = {};
        this.formattingModes = {
            'pretty': { indent: 4, minify: false },
            'compact': { indent: 2, minify: false },
            'minified': { indent: 0, minify: true }
        };
    }

    // =====================================================================
    // HTML5 VALIDATION
    // =====================================================================

    validateHTML(html) {
        const errors = [];
        const warnings = [];

        // 1. DOCTYPE check
        if (!html.trim().toUpperCase().startsWith('<!DOCTYPE HTML>')) {
            warnings.push({
                line: 1,
                severity: 'warning',
                message: 'Missing or incorrect <!DOCTYPE html> declaration'
            });
        }

        // 2. Check required meta tags
        if (!/<meta\s+charset=/i.test(html)) {
            warnings.push({
                line: null,
                severity: 'warning',
                message: 'Missing charset meta tag'
            });
        }

        if (!/<meta\s+name=["']viewport["']/i.test(html)) {
            warnings.push({
                line: null,
                severity: 'warning',
                message: 'Missing viewport meta tag (responsive design)'
            });
        }

        // 3. Check for semantic HTML5 tags
        const semanticTags = ['header', 'nav', 'main', 'article', 'section', 'aside', 'footer'];
        const hasSemanticTags = semanticTags.some(tag => new RegExp(`<${tag}[>\\s]`).test(html));
        if (!hasSemanticTags) {
            warnings.push({
                line: null,
                severity: 'info',
                message: 'Consider using semantic HTML5 tags for better structure'
            });
        }

        // 4. Check for img alt attributes (accessibility)
        const imgRegex = /<img[^>]*>/gi;
        let match;
        while ((match = imgRegex.exec(html)) !== null) {
            if (!/alt\s*=/i.test(match[0])) {
                warnings.push({
                    line: this.getLineNumber(html, match.index),
                    severity: 'warning',
                    message: 'Image missing alt attribute (WCAG compliance)'
                });
            }
        }

        // 5. Check for unclosed tags
        const selfClosing = ['area', 'base', 'br', 'col', 'embed', 'hr', 'img', 'input', 'link', 'meta', 'param', 'source', 'track', 'wbr'];
        const tagRegex = /<(\w+)[^>]*>/gi;
        const openTags = [];

        while ((match = tagRegex.exec(html)) !== null) {
            const tagName = match[1].toLowerCase();
            if (!selfClosing.includes(tagName)) {
                openTags.push({ tag: tagName, index: match.index });
            }
        }

        // 6. Check for deprecated tags
        const deprecatedTags = ['font', 'center', 'blink', 'marquee'];
        deprecatedTags.forEach(tag => {
            if (new RegExp(`<${tag}[>\\s]`, 'i').test(html)) {
                warnings.push({
                    line: this.getLineNumber(html, html.search(new RegExp(`<${tag}`, 'i'))),
                    severity: 'warning',
                    message: `${tag.toUpperCase()} tag is deprecated. Use CSS instead.`
                });
            }
        });

        // 7. Check lang attribute
        if (!/html[^>]*lang\s*=/i.test(html)) {
            warnings.push({
                line: 1,
                severity: 'info',
                message: 'Missing lang attribute on <html> tag'
            });
        }

        return { errors, warnings };
    }

    // =====================================================================
    // CSS VALIDATION
    // =====================================================================

    validateCSS(css) {
        const errors = [];
        const warnings = [];

        // 1. Check brace matching
        const openBraces = (css.match(/\{/g) || []).length;
        const closeBraces = (css.match(/\}/g) || []).length;
        if (openBraces !== closeBraces) {
            errors.push({
                line: null,
                severity: 'error',
                message: `Brace mismatch: ${openBraces} opening, ${closeBraces} closing`
            });
        }

        // 2. Check for missing semicolons
        const lines = css.split('\n');
        lines.forEach((line, index) => {
            const trimmed = line.trim();
            if (trimmed && !trimmed.endsWith(';') && !trimmed.endsWith('{') && !trimmed.endsWith('}') && !trimmed.startsWith('/*') && trimmed.includes(':')) {
                warnings.push({
                    line: index + 1,
                    severity: 'warning',
                    message: 'Missing semicolon'
                });
            }
        });

        // 3. Check for vendor prefix consistency
        if (/webkit-/i.test(css) && !/moz-/i.test(css)) {
            warnings.push({
                line: null,
                severity: 'info',
                message: 'Consider adding -moz- prefix for Firefox support'
            });
        }

        // 4. Check for unused selectors (basic heuristic)
        const selectorRegex = /([^{]+)\s*\{/g;
        const selectors = [];
        let match;
        while ((match = selectorRegex.exec(css)) !== null) {
            const selector = match[1].trim();
            selectors.push(selector);
        }

        // 5. Check for common CSS mistakes
        if (/width\s*:\s*100%\s*;.*height\s*:\s*100%/is.test(css)) {
            warnings.push({
                line: null,
                severity: 'info',
                message: 'Consider using viewport units (vh, vw) instead of percentages for full-screen layouts'
            });
        }

        // 6. Check for !important abuse
        const importantCount = (css.match(/!important/g) || []).length;
        if (importantCount > 5) {
            warnings.push({
                line: null,
                severity: 'warning',
                message: `${importantCount} instances of !important. Consider reducing specificity instead.`
            });
        }

        return { errors, warnings };
    }

    // =====================================================================
    // JAVASCRIPT VALIDATION
    // =====================================================================

    validateJS(js) {
        const errors = [];
        const warnings = [];

        // 1. Check for syntax errors using Function constructor
        try {
            new Function(js);
        } catch (e) {
            errors.push({
                line: null,
                severity: 'error',
                message: `Syntax error: ${e.message}`
            });
        }

        // 2. Check for common issues
        if (/eval\(/g.test(js)) {
            warnings.push({
                line: null,
                severity: 'error',
                message: 'eval() is dangerous and should be avoided'
            });
        }

        // 3. Check for missing const/let/var
        if (/^\s*\w+\s*=/m.test(js) && !/const |let |var /.test(js)) {
            warnings.push({
                line: null,
                severity: 'warning',
                message: 'Variables declared without const/let/var (implicit globals)'
            });
        }

        // 4. Check for console statements
        if (/console\.\w+/g.test(js)) {
            const count = (js.match(/console\.\w+/g) || []).length;
            warnings.push({
                line: null,
                severity: 'info',
                message: `${count} console statement(s) found. Consider removing in production.`
            });
        }

        // 5. Check for debugger statements
        if (/debugger\s*;/g.test(js)) {
            warnings.push({
                line: null,
                severity: 'error',
                message: 'Debugger statement found'
            });
        }

        // 6. Check for potential memory leaks
        if (/setInterval\s*\(/g.test(js) && !/clearInterval/g.test(js)) {
            warnings.push({
                line: null,
                severity: 'warning',
                message: 'setInterval found without corresponding clearInterval'
            });
        }

        // 7. Check for missing error handling
        if (/fetch\s*\(/g.test(js) && !/.catch/g.test(js)) {
            warnings.push({
                line: null,
                severity: 'warning',
                message: 'fetch() calls should have error handling (.catch)'
            });
        }

        return { errors, warnings };
    }

    // =====================================================================
    // CODE FORMATTING & MINIFICATION
    // =====================================================================

    formatHTML(html, mode = 'pretty') {
        const config = this.formattingModes[mode] || this.formattingModes['pretty'];

        if (mode === 'minified') {
            // Remove comments, whitespace, newlines
            return html
                .replace(/<!--[\s\S]*?-->/g, '') // Remove HTML comments
                .replace(/>\s+</g, '><')           // Remove whitespace between tags
                .replace(/\n/g, '')                // Remove newlines
                .trim();
        }

        // Pretty/Compact formatting
        const indent = ' '.repeat(config.indent);
        let result = '';
        let depth = 0;

        const tokens = html.match(/(<[^>]+>|[^<]+)/g) || [];
        tokens.forEach(token => {
            if (token.startsWith('</')) {
                depth = Math.max(0, depth - 1);
                result += indent.repeat(depth) + token.trim() + '\n';
            } else if (token.startsWith('<') && token.endsWith('/>')) {
                result += indent.repeat(depth) + token.trim() + '\n';
            } else if (token.startsWith('<')) {
                result += indent.repeat(depth) + token.trim() + '\n';
                if (!token.startsWith('</')) depth++;
            } else if (token.trim()) {
                result += indent.repeat(depth) + token.trim() + '\n';
            }
        });

        return result.trim();
    }

    formatCSS(css, mode = 'pretty') {
        const config = this.formattingModes[mode] || this.formattingModes['pretty'];

        if (mode === 'minified') {
            return css
                .replace(/\/\*[\s\S]*?\*\//g, '')   // Remove comments
                .replace(/\s+/g, ' ')               // Collapse whitespace
                .replace(/\s*{\s*/g, '{')           // Remove spaces around braces
                .replace(/\s*}\s*/g, '}')
                .replace(/\s*:\s*/g, ':')           // Remove spaces around colons
                .replace(/\s*;\s*/g, ';')           // Remove spaces around semicolons
                .replace(/\s*,\s*/g, ',')           // Remove spaces around commas
                .trim();
        }

        const indent = ' '.repeat(config.indent);
        let result = '';
        let depth = 0;

        let i = 0;
        while (i < css.length) {
            const char = css[i];

            if (char === '{') {
                result += ' {\n';
                depth++;
            } else if (char === '}') {
                depth = Math.max(0, depth - 1);
                result += '\n' + indent.repeat(depth) + '}\n';
            } else if (char === ';') {
                result += ';\n' + indent.repeat(depth);
            } else if (char === '\n') {
                result = result.trimRight() + '\n' + indent.repeat(depth);
            } else if (/\s/.test(char) && result.endsWith('\n')) {
                // Skip leading whitespace on new lines
            } else {
                result += char;
            }
            i++;
        }

        return result.trim();
    }

    formatJS(js, mode = 'pretty') {
        const config = this.formattingModes[mode] || this.formattingModes['pretty'];

        if (mode === 'minified') {
            return js
                .replace(/\/\*[\s\S]*?\*\//g, '')   // Remove block comments
                .replace(/\/\/.*$/gm, '')           // Remove line comments
                .replace(/\s+/g, ' ')               // Collapse whitespace
                .replace(/\s*([{}();,=:<>+\-*/])\s*/g, '$1') // Remove spaces around operators
                .trim();
        }

        const indent = ' '.repeat(config.indent);
        let result = '';
        let depth = 0;
        let inString = false;
        let stringChar = '';

        for (let i = 0; i < js.length; i++) {
            const char = js[i];
            const prevChar = js[i - 1];
            const nextChar = js[i + 1];

            // Handle strings
            if ((char === '"' || char === "'" || char === '`') && prevChar !== '\\') {
                inString = !inString;
                stringChar = inString ? char : '';
                result += char;
                continue;
            }

            if (inString) {
                result += char;
                continue;
            }

            // Handle braces
            if (char === '{') {
                result += ' {\n' + indent.repeat(depth + 1);
                depth++;
            } else if (char === '}') {
                depth = Math.max(0, depth - 1);
                result = result.trimRight() + '\n' + indent.repeat(depth) + '}';
                if (nextChar === '\n' || nextChar === ';') result += '\n';
            } else if (char === ';') {
                result += ';\n' + indent.repeat(depth);
            } else if (char === '\n') {
                result = result.trimRight() + '\n' + indent.repeat(depth);
            } else if (/\s/.test(char) && result.endsWith('\n')) {
                // Skip leading whitespace
            } else {
                result += char;
            }
        }

        return result.trim();
    }

    // =====================================================================
    // CSS MINIFICATION (Advanced)
    // =====================================================================

    minifyCSS(css) {
        return css
            // Remove comments
            .replace(/\/\*[\s\S]*?\*\//g, '')
            // Remove whitespace
            .replace(/\s+/g, ' ')
            .replace(/\s*([{}:;,>+~])\s*/g, '$1')
            // Combine adjacent selectors
            .replace(/\},\s*/g, '},')
            // Remove trailing semicolon in last declaration
            .replace(/;}/g, '}')
            // Minify color values
            .replace(/#([0-9a-f])\1([0-9a-f])\2([0-9a-f])\3/gi, '#$1$2$3')
            // Remove quotes from font names
            .replace(/font-family:\s*["']([^"']+)["']/g, 'font-family:$1')
            .trim();
    }

    // =====================================================================
    // JAVASCRIPT MINIFICATION (Advanced)
    // =====================================================================

    minifyJS(js) {
        return js
            // Remove block comments
            .replace(/\/\*[\s\S]*?\*\//g, '')
            // Remove line comments
            .replace(/\/\/.*$/gm, '')
            // Collapse whitespace
            .replace(/\s+/g, ' ')
            // Remove spaces around operators (but not in strings)
            .replace(/\s*([{}();,=:<>+\-*/!&|?:])\s*/g, '$1')
            // Remove unnecessary spaces
            .replace(/,\s+/g, ',')
            .replace(/:\s+/g, ':')
            // Remove semicolons before closing brace
            .replace(/;}/g, '}')
            // Remove spaces before closing paren in function calls
            .replace(/\s*\)/g, ')')
            .trim();
    }

    // =====================================================================
    // HELPER METHODS
    // =====================================================================

    getLineNumber(text, index) {
        return text.substring(0, index).split('\n').length;
    }

    beautifyJSON(json) {
        try {
            const parsed = JSON.parse(json);
            return JSON.stringify(parsed, null, 2);
        } catch (e) {
            return json;
        }
    }

    calculateComplexity(js) {
        const complexity = {
            functions: (js.match(/function\s+\w+|=>\s*{/g) || []).length,
            loops: (js.match(/for\s*\(|while\s*\(|forEach/g) || []).length,
            conditionals: (js.match(/if\s*\(|else|switch/g) || []).length,
            asyncOperations: (js.match(/async|await|Promise|\.then|\.catch/g) || []).length
        };
        return complexity;
    }

    getFileStats(code, type = 'html') {
        return {
            lines: code.split('\n').length,
            chars: code.length,
            bytes: new Blob([code]).size,
            words: code.split(/\s+/).length,
            complexity: type === 'js' ? this.calculateComplexity(code) : null
        };
    }
}

// Export globally
window.validationEngine = new ValidationEngine();
