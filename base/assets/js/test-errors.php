<?php
/**
 * Quick Error Handler Test
 * Test that the error handler loads without errors
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CIS Error Handler - Quick Test</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .test-card {
            background: white;
            padding: 30px;
            border-radius: 8px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }
        h1 { color: #333; }
        .btn {
            padding: 10px 20px;
            margin: 5px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
        }
        .btn-danger { background: #dc3545; color: white; }
        .btn-warning { background: #ffc107; color: #333; }
        .btn-info { background: #17a2b8; color: white; }
        .btn-success { background: #28a745; color: white; }
        .btn:hover { opacity: 0.9; }
        .status {
            padding: 15px;
            margin: 10px 0;
            border-radius: 4px;
            font-family: monospace;
        }
        .status.success { background: #d4edda; color: #155724; }
        .status.error { background: #f8d7da; color: #721c24; }
    </style>
</head>
<body>

<div class="test-card">
    <h1>🧪 CIS Error Handler - Quick Test</h1>
    <p>Testing that the error handler loads without DOM errors</p>

    <div id="loadStatus"></div>
</div>

<div class="test-card">
    <h2>Test Error Popups</h2>
    <button class="btn btn-danger" onclick="testError()">⛔ Show Error</button>
    <button class="btn btn-warning" onclick="testWarning()">⚠️ Show Warning</button>
    <button class="btn btn-info" onclick="testInfo()">ℹ️ Show Info</button>
    <button class="btn btn-success" onclick="testSuccess()">✅ Show Success</button>
</div>

<div class="test-card">
    <h2>Test Core Utilities</h2>
    <button class="btn btn-info" onclick="testFormatters()">Test Formatters</button>
    <button class="btn btn-success" onclick="testToast()">Test Toast</button>
    <div id="testResults" style="margin-top: 15px;"></div>
</div>

<!-- Load CIS Libraries -->
<script src="/modules/base/_assets/js/cis-error-handler.js?v=<?php echo time(); ?>"></script>
<script src="/modules/base/_assets/js/cis-core.js?v=<?php echo time(); ?>"></script>

<script>
// Check if libraries loaded
function checkLibraries() {
    const status = document.getElementById('loadStatus');

    const checks = [
        { name: 'CIS namespace', check: typeof window.CIS !== 'undefined' },
        { name: 'ErrorHandler', check: typeof window.CIS?.ErrorHandler !== 'undefined' },
        { name: 'Core utilities', check: typeof window.CIS?.Core !== 'undefined' },
        { name: 'Core alias ($)', check: typeof window.CIS?.$ !== 'undefined' }
    ];

    let allPassed = true;
    let html = '<div style="font-family: monospace;">';

    checks.forEach(check => {
        const icon = check.check ? '✅' : '❌';
        html += `${icon} ${check.name}<br>`;
        if (!check.check) allPassed = false;
    });

    html += '</div>';

    status.innerHTML = html;
    status.className = `status ${allPassed ? 'success' : 'error'}`;

    if (allPassed) {
        console.log('✅ All CIS libraries loaded successfully!');
    }
}

// Test functions
function testError() {
    CIS.ErrorHandler.error(
        'Test Error Message',
        'This is a test error with details.\nFile: test.js\nLine: 42'
    );
}

function testWarning() {
    CIS.ErrorHandler.warning(
        'Test Warning',
        'This is a warning message that will auto-dismiss'
    );
}

function testInfo() {
    CIS.ErrorHandler.info(
        'Test Information',
        'This is an informational message'
    );
}

function testSuccess() {
    CIS.ErrorHandler.success(
        'Test Success!',
        'Operation completed successfully'
    );
}

function testFormatters() {
    const results = {
        currency: CIS.$.formatCurrency(1234.56),
        date: CIS.$.formatDate('2025-11-04'),
        number: CIS.$.formatNumber(123456.789, 2),
        fileSize: CIS.$.formatFileSize(1536000)
    };

    const html = '<pre>' + JSON.stringify(results, null, 2) + '</pre>';
    document.getElementById('testResults').innerHTML = html;
    CIS.$.toast('Formatters tested!', 'success');
}

function testToast() {
    CIS.$.toast('This is a toast notification!', 'success');
}

// Run checks on load
window.addEventListener('DOMContentLoaded', () => {
    console.log('🔍 Checking CIS libraries...');
    checkLibraries();
});
</script>

</body>
</html>
