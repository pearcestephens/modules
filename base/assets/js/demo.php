<?php
/**
 * CIS JavaScript Stack - Live Demo
 *
 * Interactive demonstration of all CIS JavaScript features
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/base/_templates/themes/cis-classic/theme.php';

$theme = new CISClassicTheme();
$theme->setTitle('CIS JavaScript Stack - Live Demo');
$theme->setCurrentPage('demo/javascript');

// Enable debug mode
$theme->addHeadContent('<script>
    // Enable debug mode to see feature detection
    if (window.CIS && window.CIS.Core) {
        window.CIS.Core.configure({ debug: true });
    }
</script>');

$theme->render('html-head');
$theme->render('header');
$theme->render('sidebar');
$theme->render('main-start');
?>

<div class="container-fluid">
    <div class="animated fadeIn">

        <!-- Page Header -->
        <div class="card mb-4" style="border-left: 4px solid #007bff;">
            <div class="card-body">
                <h1 class="mb-2">
                    <i class="fas fa-rocket text-primary"></i>
                    CIS JavaScript Stack - Live Demo
                </h1>
                <p class="lead mb-0">
                    Interactive playground for testing all CIS JavaScript features
                </p>
            </div>
        </div>

        <!-- Quick Actions -->
        <div class="row mb-4">
            <div class="col-md-12">
                <div class="card">
                    <div class="card-header bg-primary text-white">
                        <strong><i class="fas fa-flask"></i> Quick Tests</strong>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3 mb-2">
                                <button class="btn btn-danger btn-block" onclick="testError()">
                                    ⛔ Show Error
                                </button>
                            </div>
                            <div class="col-md-3 mb-2">
                                <button class="btn btn-warning btn-block" onclick="testWarning()">
                                    ⚠️ Show Warning
                                </button>
                            </div>
                            <div class="col-md-3 mb-2">
                                <button class="btn btn-info btn-block" onclick="testInfo()">
                                    ℹ️ Show Info
                                </button>
                            </div>
                            <div class="col-md-3 mb-2">
                                <button class="btn btn-success btn-block" onclick="testSuccess()">
                                    ✅ Show Success
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">

            <!-- Error Handler Tests -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <strong><i class="fas fa-exclamation-triangle"></i> Error Handler</strong>
                    </div>
                    <div class="card-body">
                        <p>Professional error handling with beautiful popups</p>
                        <div class="btn-group-vertical w-100">
                            <button class="btn btn-outline-danger mb-2" onclick="testError()">
                                Show Error with Stack Trace
                            </button>
                            <button class="btn btn-outline-warning mb-2" onclick="testAjaxError()">
                                Test AJAX Error (404)
                            </button>
                            <button class="btn btn-outline-info mb-2" onclick="testMultipleErrors()">
                                Show Multiple Errors
                            </button>
                            <button class="btn btn-outline-secondary" onclick="CIS.ErrorHandler.clearAll()">
                                Clear All Errors
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Format Utilities -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <strong><i class="fas fa-format"></i> Format Utilities</strong>
                    </div>
                    <div class="card-body">
                        <p>Format currency, dates, numbers, and more</p>
                        <button class="btn btn-primary btn-block mb-2" onclick="testFormatters()">
                            Test All Formatters
                        </button>
                        <div id="formatterResults" class="mt-3" style="display:none;">
                            <div class="alert alert-info">
                                <h6>Format Results:</h6>
                                <pre id="formatterOutput" style="font-size: 12px;"></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- AJAX Helper -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <strong><i class="fas fa-exchange-alt"></i> AJAX Helpers</strong>
                    </div>
                    <div class="card-body">
                        <p>Easy AJAX with automatic error handling</p>
                        <button class="btn btn-success btn-block mb-2" onclick="testAjaxSuccess()">
                            Test Successful Request
                        </button>
                        <button class="btn btn-danger btn-block mb-2" onclick="testAjaxError()">
                            Test Failed Request (auto error popup)
                        </button>
                        <button class="btn btn-info btn-block" onclick="testAjaxWithLoading()">
                            Test with Loading Overlay
                        </button>
                    </div>
                </div>
            </div>

            <!-- User Feedback -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <strong><i class="fas fa-comment"></i> User Feedback</strong>
                    </div>
                    <div class="card-body">
                        <p>Toast notifications and confirmations</p>
                        <button class="btn btn-success btn-block mb-2" onclick="testToast()">
                            Show Toast Notifications
                        </button>
                        <button class="btn btn-warning btn-block mb-2" onclick="testConfirm()">
                            Show Confirmation Dialog
                        </button>
                        <button class="btn btn-info btn-block" onclick="testLoading()">
                            Show Loading Overlay
                        </button>
                    </div>
                </div>
            </div>

            <!-- LocalStorage -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <strong><i class="fas fa-database"></i> LocalStorage</strong>
                    </div>
                    <div class="card-body">
                        <p>Easy client-side storage</p>
                        <button class="btn btn-primary btn-block mb-2" onclick="testLocalStorage()">
                            Test Store/Retrieve
                        </button>
                        <button class="btn btn-danger btn-block" onclick="CIS.$.clearStorage(); CIS.$.toast('Storage cleared!', 'success')">
                            Clear CIS Storage
                        </button>
                    </div>
                </div>
            </div>

            <!-- Advanced Logging -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <strong><i class="fas fa-terminal"></i> Advanced Logging</strong>
                    </div>
                    <div class="card-body">
                        <p>Professional logging with levels</p>
                        <button class="btn btn-info btn-block mb-2" onclick="testLogger()">
                            Test Logger (check console)
                        </button>
                        <button class="btn btn-secondary btn-block" onclick="testPerformance()">
                            Test Performance Timing
                        </button>
                    </div>
                </div>
            </div>

            <!-- Clipboard -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <strong><i class="fas fa-clipboard"></i> Clipboard API</strong>
                    </div>
                    <div class="card-body">
                        <p>Copy/paste with toast feedback</p>
                        <div class="input-group mb-2">
                            <input type="text" class="form-control" id="clipboardText" value="Hello CIS!" placeholder="Text to copy">
                            <button class="btn btn-primary" onclick="testClipboard()">
                                <i class="fas fa-copy"></i> Copy
                            </button>
                        </div>
                        <button class="btn btn-info btn-block" onclick="testClipboardRead()">
                            Read from Clipboard
                        </button>
                    </div>
                </div>
            </div>

            <!-- Geolocation -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <strong><i class="fas fa-map-marker-alt"></i> Geolocation</strong>
                    </div>
                    <div class="card-body">
                        <p>Get user location</p>
                        <button class="btn btn-primary btn-block" onclick="testGeolocation()">
                            Get Current Position
                        </button>
                        <div id="geoResults" class="mt-2" style="display:none;">
                            <div class="alert alert-info">
                                <strong>Location:</strong><br>
                                <span id="geoOutput"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Network Info -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <strong><i class="fas fa-wifi"></i> Network Information</strong>
                    </div>
                    <div class="card-body">
                        <p>Connection type and status</p>
                        <button class="btn btn-info btn-block" onclick="testNetwork()">
                            Get Network Info
                        </button>
                        <div id="networkResults" class="mt-2" style="display:none;">
                            <div class="alert alert-info">
                                <span id="networkOutput"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Validation -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <strong><i class="fas fa-check-circle"></i> Validation Helpers</strong>
                    </div>
                    <div class="card-body">
                        <p>Email, phone, URL validation</p>
                        <div class="input-group mb-2">
                            <input type="text" class="form-control" id="validateEmail" placeholder="test@example.com">
                            <button class="btn btn-primary" onclick="testValidateEmail()">
                                Validate Email
                            </button>
                        </div>
                        <div class="input-group mb-2">
                            <input type="text" class="form-control" id="validatePhone" placeholder="021 123 4567">
                            <button class="btn btn-primary" onclick="testValidatePhone()">
                                Validate Phone
                            </button>
                        </div>
                        <div class="input-group">
                            <input type="text" class="form-control" id="validateUrl" placeholder="https://example.com">
                            <button class="btn btn-primary" onclick="testValidateUrl()">
                                Validate URL
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Utilities -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <strong><i class="fas fa-tools"></i> Utility Functions</strong>
                    </div>
                    <div class="card-body">
                        <p>Debounce, throttle, unique IDs</p>
                        <button class="btn btn-primary btn-block mb-2" onclick="testDebounce()">
                            Test Debounce (type fast)
                        </button>
                        <input type="text" class="form-control mb-2" id="debounceInput" placeholder="Type here...">
                        <button class="btn btn-success btn-block" onclick="testUniqueId()">
                            Generate Unique ID
                        </button>
                    </div>
                </div>
            </div>

            <!-- Performance -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <strong><i class="fas fa-tachometer-alt"></i> Performance API</strong>
                    </div>
                    <div class="card-body">
                        <p>Page load metrics and timing</p>
                        <button class="btn btn-info btn-block" onclick="testPerformanceMetrics()">
                            Get Performance Metrics
                        </button>
                        <div id="perfResults" class="mt-2" style="display:none;">
                            <div class="alert alert-info">
                                <pre id="perfOutput" style="font-size: 11px;"></pre>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Browser Notifications -->
            <div class="col-md-6 mb-4">
                <div class="card h-100">
                    <div class="card-header">
                        <strong><i class="fas fa-bell"></i> Browser Notifications</strong>
                    </div>
                    <div class="card-body">
                        <p>Native desktop notifications</p>
                        <button class="btn btn-warning btn-block mb-2" onclick="testNotification()">
                            Show Browser Notification
                        </button>
                        <small class="text-muted">Requires permission grant</small>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<script>
// Test Functions

function testError() {
    CIS.ErrorHandler.error(
        'Database Connection Failed',
        'Host: localhost\nPort: 3306\nError Code: ECONNREFUSED\nConnection timeout after 30 seconds'
    );
}

function testWarning() {
    CIS.ErrorHandler.warning(
        'Data Might Be Incomplete',
        'Some optional fields were not provided.\nThe record was saved but may need review.'
    );
}

function testInfo() {
    CIS.ErrorHandler.info(
        'Processing Complete',
        'Successfully processed 1,234 records in 2.3 seconds.\nNo errors found.'
    );
}

function testSuccess() {
    CIS.ErrorHandler.success(
        'Transfer Saved Successfully!',
        'Transfer ID: 12345\nStatus: Pending\nCreated: ' + new Date().toLocaleString()
    );
}

function testAjaxError() {
    CIS.$.get('/api/nonexistent-endpoint')
        .catch(() => {}); // Error auto-handled by ErrorHandler
}

function testMultipleErrors() {
    setTimeout(() => testError(), 100);
    setTimeout(() => testWarning(), 300);
    setTimeout(() => testInfo(), 500);
}

function testFormatters() {
    const results = {
        'Currency': CIS.$.formatCurrency(1234.56),
        'Date': CIS.$.formatDate('2025-11-04'),
        'DateTime': CIS.$.formatDateTime('2025-11-04 14:30:00'),
        'Number': CIS.$.formatNumber(1234567.89, 2),
        'File Size': CIS.$.formatFileSize(1536000),
        'Phone': CIS.$.formatPhone('0211234567')
    };

    document.getElementById('formatterOutput').textContent = JSON.stringify(results, null, 2);
    document.getElementById('formatterResults').style.display = 'block';
    CIS.$.toast('Formatters tested!', 'success');
}

function testAjaxSuccess() {
    CIS.$.toast('Simulating successful AJAX request...', 'info');
    // In real app, would call actual endpoint
    setTimeout(() => {
        CIS.$.toast('Request completed successfully!', 'success');
    }, 1000);
}

function testAjaxWithLoading() {
    CIS.$.showLoading('Fetching data...');
    setTimeout(() => {
        CIS.$.hideLoading();
        CIS.$.toast('Data loaded!', 'success');
    }, 2000);
}

function testToast() {
    CIS.$.toast('This is a success toast!', 'success');
    setTimeout(() => CIS.$.toast('This is an error toast!', 'error'), 500);
    setTimeout(() => CIS.$.toast('This is a warning toast!', 'warning'), 1000);
    setTimeout(() => CIS.$.toast('This is an info toast!', 'info'), 1500);
}

function testConfirm() {
    CIS.$.confirm(
        'Are you sure you want to delete this item?',
        () => CIS.$.toast('Item deleted!', 'success'),
        () => CIS.$.toast('Deletion cancelled', 'info')
    );
}

function testLoading() {
    CIS.$.showLoading('Please wait...');
    setTimeout(() => CIS.$.hideLoading(), 3000);
}

function testLocalStorage() {
    const data = {
        name: 'John Doe',
        email: 'john@example.com',
        timestamp: new Date().toISOString()
    };

    CIS.$.store('testData', data);
    const retrieved = CIS.$.retrieve('testData');

    CIS.$.toast('Data stored and retrieved!', 'success', JSON.stringify(retrieved, null, 2));
}

function testLogger() {
    const logger = CIS.$.createLogger('Demo');

    logger.debug('This is a debug message', { level: 'debug' });
    logger.info('This is an info message', { level: 'info' });
    logger.warn('This is a warning message', { level: 'warn' });
    logger.error('This is an error message', { level: 'error' });

    logger.group('Grouped Logs');
    logger.info('Message 1');
    logger.info('Message 2');
    logger.groupEnd();

    logger.table([
        { id: 1, name: 'John' },
        { id: 2, name: 'Jane' }
    ]);

    CIS.$.toast('Check the console for log output!', 'info');
}

function testPerformance() {
    const logger = CIS.$.createLogger('Performance');
    logger.time('Test Operation');

    // Simulate work
    let sum = 0;
    for (let i = 0; i < 1000000; i++) {
        sum += i;
    }

    logger.timeEnd('Test Operation');
    CIS.$.toast('Performance test complete! Check console.', 'success');
}

function testClipboard() {
    const text = document.getElementById('clipboardText').value;
    CIS.$.copyToClipboard(text);
}

async function testClipboardRead() {
    const text = await CIS.$.readClipboard();
    if (text) {
        CIS.$.toast('Clipboard content: ' + text, 'info');
    } else {
        CIS.$.toast('Could not read clipboard', 'warning');
    }
}

async function testGeolocation() {
    try {
        const pos = await CIS.$.getCurrentPosition();
        document.getElementById('geoOutput').innerHTML = `
            Latitude: ${pos.latitude.toFixed(6)}<br>
            Longitude: ${pos.longitude.toFixed(6)}<br>
            Accuracy: ${pos.accuracy.toFixed(0)}m
        `;
        document.getElementById('geoResults').style.display = 'block';
        CIS.$.toast('Location retrieved!', 'success');
    } catch (error) {
        CIS.$.toast('Geolocation failed: ' + error.message, 'error');
    }
}

function testNetwork() {
    const info = CIS.$.getNetworkInfo();
    document.getElementById('networkOutput').innerHTML = `
        <strong>Status:</strong> ${info.online ? '🟢 Online' : '🔴 Offline'}<br>
        <strong>Type:</strong> ${info.effectiveType || 'Unknown'}<br>
        <strong>Downlink:</strong> ${info.downlink ? info.downlink + ' Mbps' : 'N/A'}<br>
        <strong>RTT:</strong> ${info.rtt ? info.rtt + ' ms' : 'N/A'}
    `;
    document.getElementById('networkResults').style.display = 'block';
}

function testValidateEmail() {
    const email = document.getElementById('validateEmail').value;
    const valid = CIS.$.isEmail(email);
    CIS.$.toast(
        valid ? 'Valid email address!' : 'Invalid email address!',
        valid ? 'success' : 'error'
    );
}

function testValidatePhone() {
    const phone = document.getElementById('validatePhone').value;
    const valid = CIS.$.isPhone(phone);
    CIS.$.toast(
        valid ? 'Valid NZ phone!' : 'Invalid phone number!',
        valid ? 'success' : 'error'
    );
}

function testValidateUrl() {
    const url = document.getElementById('validateUrl').value;
    const valid = CIS.$.isUrl(url);
    CIS.$.toast(
        valid ? 'Valid URL!' : 'Invalid URL!',
        valid ? 'success' : 'error'
    );
}

function testDebounce() {
    const input = document.getElementById('debounceInput');
    const debouncedFn = CIS.$.debounce((value) => {
        CIS.$.toast('Debounced: ' + value, 'info');
    }, 500);

    input.addEventListener('input', (e) => debouncedFn(e.target.value));
    CIS.$.toast('Type in the input below!', 'info');
}

function testUniqueId() {
    const id = CIS.$.uniqueId('demo');
    CIS.$.toast('Generated ID: ' + id, 'success', 'Check console for full ID');
    console.log('Unique ID:', id);
}

function testPerformanceMetrics() {
    const metrics = CIS.$.getPerformanceMetrics();
    document.getElementById('perfOutput').textContent = JSON.stringify(metrics, null, 2);
    document.getElementById('perfResults').style.display = 'block';
    CIS.$.toast('Performance metrics retrieved!', 'success');
}

async function testNotification() {
    const granted = await CIS.$.requestNotificationPermission();
    if (granted) {
        CIS.$.notify('CIS Notification', {
            body: 'This is a test notification from CIS!',
            icon: '/assets/images/logo.png'
        });
        CIS.$.toast('Notification sent!', 'success');
    } else {
        CIS.$.toast('Notification permission denied', 'warning');
    }
}

// Initialize
console.log('🎯 CIS JavaScript Demo Page Ready');
console.log('📦 Available: CIS.ErrorHandler, CIS.Core (CIS.$)');
console.log('🔧 Try the buttons to test features!');
</script>

<?php
$theme->render('footer');
echo '</body></html>';
?>
