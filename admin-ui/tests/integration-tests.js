/**
 * Integration Tests - Theme Builder Complete Feature Suite
 * Tests all 8 features working together end-to-end
 *
 * Run in browser console:
 * - IntegrationTestSuite.runAll()
 * - IntegrationTestSuite.runValidation()
 * - IntegrationTestSuite.runAI()
 * - IntegrationTestSuite.runPerformance()
 */

class IntegrationTestSuite {
    constructor() {
        this.results = [];
        this.startTime = Date.now();
        this.editorStats = {};
    }

    /**
     * Main test runner - Execute all tests
     */
    static async runAll() {
        console.clear();
        console.log('🚀 INTEGRATION TEST SUITE - START\n');
        const suite = new IntegrationTestSuite();

        // Phase 1: Validation Tests
        await suite.testValidation();

        // Phase 2: Formatting Tests
        await suite.testFormatting();

        // Phase 3: Minification Tests
        await suite.testMinification();

        // Phase 4: File Operations Tests
        await suite.testFileOperations();

        // Phase 5: PHP Execution Tests
        await suite.testPHPExecution();

        // Phase 6: AI Integration Tests
        await suite.testAIIntegration();

        // Phase 7: Combined Workflows
        await suite.testCombinedWorkflows();

        // Phase 8: Performance Tests
        await suite.testPerformance();

        suite.printSummary();
    }

    /**
     * PHASE 1: Validation Tests
     */
    async testValidation() {
        console.log('📋 PHASE 1: VALIDATION ENGINE\n');

        // Test 1.1: HTML Validation
        try {
            const validator = window.validationEngine;
            if (!validator) throw new Error('Validation engine not found');

            const validHTML = `
                <!DOCTYPE html>
                <html lang="en">
                <head>
                    <meta charset="UTF-8">
                    <meta name="viewport" content="width=device-width, initial-scale=1">
                    <title>Test</title>
                </head>
                <body>
                    <img src="image.jpg" alt="Test image">
                </body>
                </html>
            `;

            const result = validator.validateHTML(validHTML);
            const passed = result.errors.length === 0;
            this.logTest('HTML Validation - Valid Code', passed, `Errors: ${result.errors.length}, Warnings: ${result.warnings.length}`);

        } catch (e) {
            this.logTest('HTML Validation - Valid Code', false, e.message);
        }

        // Test 1.2: HTML with Errors
        try {
            const validator = window.validationEngine;
            const invalidHTML = `
                <html>
                <body>
                <img src="no-alt.jpg">
                </body>
            `;

            const result = validator.validateHTML(invalidHTML);
            const passed = result.errors.length > 0 || result.warnings.length > 0;
            this.logTest('HTML Validation - Detects Issues', passed, `Found ${result.errors.length} errors, ${result.warnings.length} warnings`);

        } catch (e) {
            this.logTest('HTML Validation - Detects Issues', false, e.message);
        }

        // Test 1.3: CSS Validation
        try {
            const validator = window.validationEngine;
            const validCSS = `
                body {
                    background: #fff;
                    color: #333;
                }

                .container {
                    max-width: 1200px;
                    margin: 0 auto;
                }
            `;

            const result = validator.validateCSS(validCSS);
            const passed = result.errors.length === 0;
            this.logTest('CSS Validation - Valid Code', passed, `Errors: ${result.errors.length}`);

        } catch (e) {
            this.logTest('CSS Validation - Valid Code', false, e.message);
        }

        // Test 1.4: CSS with Missing Semicolons
        try {
            const validator = window.validationEngine;
            const invalidCSS = `
                body {
                    background: #fff
                    color: #333;
                }
            `;

            const result = validator.validateCSS(invalidCSS);
            const passed = result.warnings.length > 0;
            this.logTest('CSS Validation - Detects Missing Semicolons', passed, `Warnings: ${result.warnings.length}`);

        } catch (e) {
            this.logTest('CSS Validation - Detects Missing Semicolons', false, e.message);
        }

        // Test 1.5: JavaScript Validation
        try {
            const validator = window.validationEngine;
            const validJS = `
                function greet(name) {
                    console.log('Hello, ' + name);
                    return true;
                }

                greet('World');
            `;

            const result = validator.validateJS(validJS);
            const passed = result.errors.length === 0;
            this.logTest('JavaScript Validation - Valid Code', passed, `Errors: ${result.errors.length}`);

        } catch (e) {
            this.logTest('JavaScript Validation - Valid Code', false, e.message);
        }

        // Test 1.6: JavaScript with Dangerous Code
        try {
            const validator = window.validationEngine;
            const dangerousJS = `
                eval('alert("dangerous")');
                console.log('test');
            `;

            const result = validator.validateJS(dangerousJS);
            const passed = result.errors.length > 0;
            this.logTest('JavaScript Validation - Detects eval()', passed, `Errors: ${result.errors.length}`);

        } catch (e) {
            this.logTest('JavaScript Validation - Detects eval()', false, e.message);
        }

        console.log('');
    }

    /**
     * PHASE 2: Formatting Tests
     */
    async testFormatting() {
        console.log('🎨 PHASE 2: CODE FORMATTING\n');

        // Test 2.1: Format HTML Pretty
        try {
            const validator = window.validationEngine;
            const compactHTML = `<html><body><h1>Test</h1></body></html>`;

            const formatted = validator.formatHTML(compactHTML, 'pretty');
            const passed = formatted.includes('\n') && formatted.includes('  ');
            this.logTest('HTML Format - Pretty Mode', passed, `Output length: ${formatted.length}`);

        } catch (e) {
            this.logTest('HTML Format - Pretty Mode', false, e.message);
        }

        // Test 2.2: Format CSS Compact
        try {
            const validator = window.validationEngine;
            const prettyCSS = `
                body {
                    background: white;
                    color: black;
                }
            `;

            const formatted = validator.formatCSS(prettyCSS, 'compact');
            const passed = formatted && !formatted.includes('\n');
            this.logTest('CSS Format - Compact Mode', passed, `Output length: ${formatted.length}`);

        } catch (e) {
            this.logTest('CSS Format - Compact Mode', false, e.message);
        }

        // Test 2.3: Format JavaScript Pretty
        try {
            const validator = window.validationEngine;
            const compactJS = `const x=5;const y=10;console.log(x+y);`;

            const formatted = validator.formatJS(compactJS, 'pretty');
            const passed = formatted.includes('\n') || formatted.includes('  ');
            this.logTest('JavaScript Format - Pretty Mode', passed, `Output length: ${formatted.length}`);

        } catch (e) {
            this.logTest('JavaScript Format - Pretty Mode', false, e.message);
        }

        console.log('');
    }

    /**
     * PHASE 3: Minification Tests
     */
    async testMinification() {
        console.log('📦 PHASE 3: CODE MINIFICATION\n');

        // Test 3.1: CSS Minification Efficiency
        try {
            const validator = window.validationEngine;
            const originalCSS = `
                /* Comment */
                body {
                    background-color: white;
                    color: black;
                    font-family: Arial, sans-serif;
                }

                .container {
                    max-width: 1200px;
                    margin: 0 auto;
                }

                h1 { font-size: 2rem; }
            `;

            const minified = validator.minifyCSS(originalCSS);
            const ratio = ((1 - minified.length / originalCSS.length) * 100).toFixed(1);
            const passed = minified.length < originalCSS.length && ratio > 30;
            this.logTest(`CSS Minification (${ratio}% savings)`, passed, `Original: ${originalCSS.length}b, Minified: ${minified.length}b`);

        } catch (e) {
            this.logTest('CSS Minification', false, e.message);
        }

        // Test 3.2: JavaScript Minification Efficiency
        try {
            const validator = window.validationEngine;
            const originalJS = `
                // Calculate sum
                function calculateSum(a, b) {
                    // Add two numbers
                    const result = a + b;
                    return result;
                }

                const x = 10;
                const y = 20;
                console.log(calculateSum(x, y));
            `;

            const minified = validator.minifyJS(originalJS);
            const ratio = ((1 - minified.length / originalJS.length) * 100).toFixed(1);
            const passed = minified.length < originalJS.length && ratio > 30;
            this.logTest(`JavaScript Minification (${ratio}% savings)`, passed, `Original: ${originalJS.length}b, Minified: ${minified.length}b`);

        } catch (e) {
            this.logTest('JavaScript Minification', false, e.message);
        }

        // Test 3.3: Minified Code Still Valid
        try {
            const validator = window.validationEngine;
            const originalJS = `function test(){return 42;}`;
            const minified = validator.minifyJS(originalJS);
            const validation = validator.validateJS(minified);
            const passed = validation.errors.length === 0;
            this.logTest('Minified JavaScript Still Valid', passed, `Validation errors: ${validation.errors.length}`);

        } catch (e) {
            this.logTest('Minified JavaScript Still Valid', false, e.message);
        }

        console.log('');
    }

    /**
     * PHASE 4: File Operations Tests
     */
    async testFileOperations() {
        console.log('📁 PHASE 4: FILE OPERATIONS\n');

        // Test 4.1: List Files
        try {
            const response = await fetch('/modules/admin-ui/api/file-explorer-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'list',
                    path: '/modules/admin-ui'
                })
            });

            const data = await response.json();
            const passed = data.success && Array.isArray(data.files);
            this.logTest('File Explorer - List Files', passed, `Found ${data.files?.length || 0} items`);

        } catch (e) {
            this.logTest('File Explorer - List Files', false, e.message);
        }

        // Test 4.2: Read File
        try {
            const response = await fetch('/modules/admin-ui/api/file-explorer-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'read',
                    path: '/modules/admin-ui/index.php'
                })
            });

            const data = await response.json();
            const passed = data.success && typeof data.content === 'string';
            this.logTest('File Explorer - Read File', passed, `Read ${data.content?.length || 0} bytes`);

        } catch (e) {
            this.logTest('File Explorer - Read File', false, e.message);
        }

        console.log('');
    }

    /**
     * PHASE 5: PHP Execution Tests
     */
    async testPHPExecution() {
        console.log('⚙️ PHASE 5: PHP SANDBOX EXECUTION\n');

        // Test 5.1: Safe PHP Execution
        try {
            const response = await fetch('/modules/admin-ui/api/sandbox-executor.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    code: '<?php echo "Hello from PHP"; ?>'
                })
            });

            const data = await response.json();
            const passed = data.success && data.output.includes('Hello');
            this.logTest('PHP Sandbox - Simple Echo', passed, `Output: ${data.output}`);

        } catch (e) {
            this.logTest('PHP Sandbox - Simple Echo', false, e.message);
        }

        // Test 5.2: PHP Variable Assignment
        try {
            const response = await fetch('/modules/admin-ui/api/sandbox-executor.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    code: '<?php $x = 10; $y = 20; echo $x + $y; ?>'
                })
            });

            const data = await response.json();
            const passed = data.success && data.output === '30';
            this.logTest('PHP Sandbox - Arithmetic', passed, `Result: ${data.output}`);

        } catch (e) {
            this.logTest('PHP Sandbox - Arithmetic', false, e.message);
        }

        console.log('');
    }

    /**
     * PHASE 6: AI Integration Tests
     */
    async testAIIntegration() {
        console.log('🤖 PHASE 6: AI AGENT INTEGRATION\n');

        // Test 6.1: Validate and Fix via AI
        try {
            const response = await fetch('/modules/admin-ui/api/ai-agent-handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'validate_and_fix',
                    html: '<html><body><img src="test.jpg"></body></html>',
                    css: 'body { color: #fff }',
                    javascript: ''
                })
            });

            const data = await response.json();
            const passed = data.success && data.validationResults;
            this.logTest('AI - Validate and Fix', passed, `Found issues: ${Object.keys(data.validationResults || {}).length}`);

        } catch (e) {
            this.logTest('AI - Validate and Fix', false, e.message);
        }

        // Test 6.2: AI Suggestions
        try {
            const response = await fetch('/modules/admin-ui/api/ai-agent-handler.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    action: 'suggest_improvements',
                    html: '<html><body><p>Test</p></body></html>',
                    css: 'body { color: red !important; }',
                    javascript: ''
                })
            });

            const data = await response.json();
            const passed = data.success && Array.isArray(data.suggestions);
            this.logTest('AI - Suggestions', passed, `${data.suggestions?.length || 0} suggestions`);

        } catch (e) {
            this.logTest('AI - Suggestions', false, e.message);
        }

        console.log('');
    }

    /**
     * PHASE 7: Combined Workflows
     */
    async testCombinedWorkflows() {
        console.log('🔗 PHASE 7: COMBINED WORKFLOWS\n');

        // Test 7.1: Full Workflow - Code → Validate → Fix → Format → Minify
        try {
            const validator = window.validationEngine;

            // Step 1: Original code
            const originalCSS = `
                body {
                    background: white;
                    color: black
                }
                h1 { font-size: 2rem; }
            `;

            // Step 2: Validate
            const validation = validator.validateCSS(originalCSS);

            // Step 3: Fix (format)
            const formatted = validator.formatCSS(originalCSS, 'pretty');

            // Step 4: Minify
            const minified = validator.minifyCSS(formatted);

            const passed = minified && minified.length < originalCSS.length;
            this.logTest('Full Workflow - Code → Validate → Format → Minify', passed, `Size: ${originalCSS.length} → ${minified.length}b`);

        } catch (e) {
            this.logTest('Full Workflow - Code → Validate → Format → Minify', false, e.message);
        }

        // Test 7.2: Multi-Language Validation
        try {
            const validator = window.validationEngine;

            const html = '<html><body><h1>Test</h1></body></html>';
            const css = 'body { color: white; }';
            const js = 'console.log("test");';

            const htmlResult = validator.validateHTML(html);
            const cssResult = validator.validateCSS(css);
            const jsResult = validator.validateJS(js);

            const passed =
                htmlResult.errors.length === 0 &&
                cssResult.errors.length === 0 &&
                jsResult.errors.length === 0;

            this.logTest('Multi-Language Validation', passed, 'All 3 languages valid');

        } catch (e) {
            this.logTest('Multi-Language Validation', false, e.message);
        }

        console.log('');
    }

    /**
     * PHASE 8: Performance Tests
     */
    async testPerformance() {
        console.log('⚡ PHASE 8: PERFORMANCE BENCHMARKS\n');

        // Test 8.1: Large File Validation (100KB+)
        try {
            const validator = window.validationEngine;

            // Generate large CSS file
            let largeCSS = `/* Large CSS file */\nbody { color: black; }\n`;
            for (let i = 0; i < 1000; i++) {
                largeCSS += `.class-${i} { font-size: ${i}px; }\n`;
            }

            const start = performance.now();
            const result = validator.validateCSS(largeCSS);
            const duration = (performance.now() - start).toFixed(2);

            const passed = duration < 1000; // Should complete in < 1 second
            this.logTest(`Large File Validation (${(largeCSS.length / 1024).toFixed(1)}KB)`, passed, `${duration}ms`);

        } catch (e) {
            this.logTest('Large File Validation', false, e.message);
        }

        // Test 8.2: Batch Processing Speed
        try {
            const validator = window.validationEngine;

            const start = performance.now();
            for (let i = 0; i < 100; i++) {
                validator.validateJS(`function test${i}() { return ${i}; }`);
            }
            const duration = (performance.now() - start).toFixed(2);

            const passed = duration < 5000; // 100 validations in < 5 seconds
            this.logTest('Batch Processing (100 validations)', passed, `${duration}ms (${(5000/duration).toFixed(1)}x target)`);

        } catch (e) {
            this.logTest('Batch Processing', false, e.message);
        }

        // Test 8.3: Memory Efficiency
        try {
            if (performance.memory) {
                const start = performance.memory.usedJSHeapSize;

                // Process several files
                for (let i = 0; i < 50; i++) {
                    window.validationEngine.validateJS(`function f${i}(){return ${i};}`);
                }

                const end = performance.memory.usedJSHeapSize;
                const increase = ((end - start) / 1024 / 1024).toFixed(2);

                const passed = increase < 50; // Less than 50MB increase
                this.logTest('Memory Efficiency', passed, `+${increase}MB after 50 validations`);
            } else {
                this.logTest('Memory Efficiency', false, 'performance.memory not available');
            }
        } catch (e) {
            this.logTest('Memory Efficiency', false, e.message);
        }

        // Test 8.4: API Response Time
        try {
            const start = performance.now();

            const response = await fetch('/modules/admin-ui/api/file-explorer-api.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'list', path: '/' })
            });

            const duration = (performance.now() - start).toFixed(2);
            const passed = duration < 500; // API response < 500ms

            this.logTest('API Response Time', passed, `${duration}ms`);

        } catch (e) {
            this.logTest('API Response Time', false, e.message);
        }

        console.log('');
    }

    /**
     * Helper: Log test result
     */
    logTest(name, passed, detail = '') {
        const icon = passed ? '✅' : '❌';
        const status = passed ? 'PASS' : 'FAIL';
        console.log(`${icon} ${name} [${status}] - ${detail}`);

        this.results.push({
            name,
            passed,
            detail
        });
    }

    /**
     * Print summary of all tests
     */
    printSummary() {
        console.log('\n' + '='.repeat(60));
        console.log('📊 TEST SUMMARY');
        console.log('='.repeat(60));

        const total = this.results.length;
        const passed = this.results.filter(r => r.passed).length;
        const failed = total - passed;
        const duration = ((Date.now() - this.startTime) / 1000).toFixed(2);
        const percentage = ((passed / total) * 100).toFixed(1);

        console.log(`✅ Passed: ${passed}/${total}`);
        console.log(`❌ Failed: ${failed}/${total}`);
        console.log(`📈 Success Rate: ${percentage}%`);
        console.log(`⏱️ Duration: ${duration}s`);
        console.log('='.repeat(60) + '\n');

        if (failed === 0) {
            console.log('🎉 ALL TESTS PASSED - PRODUCTION READY!\n');
        } else {
            console.log(`⚠️ ${failed} test(s) failed - see details above\n`);
        }
    }

    // Convenience methods for running specific test phases
    static async runValidation() {
        const suite = new IntegrationTestSuite();
        await suite.testValidation();
        suite.printSummary();
    }

    static async runAI() {
        const suite = new IntegrationTestSuite();
        await suite.testAIIntegration();
        suite.printSummary();
    }

    static async runPerformance() {
        const suite = new IntegrationTestSuite();
        await suite.testPerformance();
        suite.printSummary();
    }
}

// Export for use
window.IntegrationTestSuite = IntegrationTestSuite;
