<?php
/**
 * REAL-TIME ENDPOINT VERIFIER
 *
 * Tests all API endpoints with actual HTTP requests
 * Uses cURL for realistic testing
 */

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Endpoint Verifier - Analytics System</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            font-family: 'Segoe UI', sans-serif;
        }

        .verifier-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .header-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            margin-bottom: 20px;
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
        }

        .endpoint-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
            gap: 20px;
        }

        .endpoint-card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            transition: transform 0.2s;
        }

        .endpoint-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 8px 16px rgba(0,0,0,0.2);
        }

        .endpoint-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            padding-bottom: 15px;
            border-bottom: 2px solid #f0f0f0;
        }

        .endpoint-method {
            display: inline-block;
            padding: 4px 12px;
            border-radius: 4px;
            font-weight: 600;
            font-size: 12px;
            text-transform: uppercase;
        }

        .method-get { background: #e7f1ff; color: #007bff; }
        .method-post { background: #e8f5e9; color: #28a745; }

        .status-badge {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            display: inline-block;
            margin-right: 8px;
        }

        .status-pending { background: #ffc107; }
        .status-success { background: #28a745; }
        .status-error { background: #dc3545; }

        .test-btn {
            width: 100%;
            margin-top: 10px;
        }

        .response-time {
            font-size: 12px;
            color: #6c757d;
            font-weight: 600;
        }

        .response-preview {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 12px;
            border-radius: 6px;
            font-size: 12px;
            max-height: 200px;
            overflow-y: auto;
            margin-top: 10px;
            display: none;
        }

        .response-preview.show {
            display: block;
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 15px;
            margin-top: 20px;
        }

        .stat-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
        }

        .stat-value {
            font-size: 36px;
            font-weight: bold;
            margin: 10px 0;
        }

        .stat-label {
            font-size: 13px;
            opacity: 0.9;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 20px;
        }

        .endpoint-description {
            font-size: 13px;
            color: #6c757d;
            margin-bottom: 10px;
        }

        .param-list {
            font-size: 12px;
            color: #495057;
            margin-top: 8px;
        }

        .param-badge {
            display: inline-block;
            background: #f8f9fa;
            padding: 2px 6px;
            border-radius: 3px;
            margin-right: 4px;
            margin-bottom: 4px;
            border: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <div class="verifier-container">
        <div class="header-card">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="bi bi-router"></i> Endpoint Verifier</h1>
                    <p class="text-muted mb-0">Real-time testing of all Analytics API endpoints</p>
                </div>
                <div class="action-buttons">
                    <button class="btn btn-primary" onclick="testAll()">
                        <i class="bi bi-play-circle"></i> Test All Endpoints
                    </button>
                    <button class="btn btn-outline-secondary" onclick="exportResults()">
                        <i class="bi bi-download"></i> Export Results
                    </button>
                </div>
            </div>

            <div class="stats-row" id="statsRow">
                <div class="stat-box">
                    <div class="stat-label">Total Endpoints</div>
                    <div class="stat-value" id="totalEndpoints">0</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Tested</div>
                    <div class="stat-value" id="testedEndpoints">0</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Success</div>
                    <div class="stat-value" id="successEndpoints">0</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Failed</div>
                    <div class="stat-value" id="failedEndpoints">0</div>
                </div>
                <div class="stat-box">
                    <div class="stat-label">Avg Response</div>
                    <div class="stat-value" id="avgResponse">0<span style="font-size: 16px;">ms</span></div>
                </div>
            </div>
        </div>

        <div class="endpoint-grid" id="endpointGrid">
            <!-- Endpoints will be loaded here -->
        </div>
    </div>

    <script>
        const endpoints = [
            // Analytics API Endpoints
            {
                name: 'Start Receiving Session',
                method: 'POST',
                url: '/modules/consignments/api/barcode_analytics.php',
                action: 'start_session',
                description: 'Initialize a new receiving session for transfer tracking',
                params: ['transfer_id', 'transfer_type', 'user_id', 'outlet_id'],
                testData: {
                    transfer_id: 999999,
                    transfer_type: 'stock_transfer',
                    user_id: 1,
                    outlet_id: 1
                }
            },
            {
                name: 'Log Barcode Scan',
                method: 'POST',
                url: '/modules/consignments/api/barcode_analytics.php',
                action: 'log_scan',
                description: 'Log a barcode scan with real-time fraud detection',
                params: ['transfer_id', 'user_id', 'barcode', 'product_id', 'scan_result'],
                testData: {
                    transfer_id: 999999,
                    user_id: 1,
                    outlet_id: 1,
                    barcode: 'TEST' + Date.now(),
                    product_id: 1,
                    scan_result: 'success',
                    device_type: 'usb_scanner'
                }
            },
            {
                name: 'Update Session',
                method: 'POST',
                url: '/modules/consignments/api/barcode_analytics.php',
                action: 'update_session',
                description: 'Update receiving session progress and statistics',
                params: ['session_id', 'transfer_id'],
                testData: {
                    session_id: 'test_session',
                    transfer_id: 999999
                }
            },
            {
                name: 'Complete Session',
                method: 'POST',
                url: '/modules/consignments/api/barcode_analytics.php',
                action: 'complete_session',
                description: 'Mark receiving session as complete and calculate final stats',
                params: ['session_id', 'transfer_id', 'user_id'],
                testData: {
                    session_id: 'test_session',
                    transfer_id: 999999,
                    user_id: 1
                }
            },
            {
                name: 'Get Performance Stats',
                method: 'GET',
                url: '/modules/consignments/api/barcode_analytics.php',
                action: 'get_performance',
                description: 'Retrieve user performance metrics for specified period',
                params: ['user_id', 'period'],
                testData: {
                    user_id: 1,
                    period: 'week'
                }
            },
            {
                name: 'Get Leaderboard',
                method: 'GET',
                url: '/modules/consignments/api/barcode_analytics.php',
                action: 'get_leaderboard',
                description: 'Fetch leaderboard rankings for competition view',
                params: ['period', 'metric'],
                testData: {
                    period: 'weekly',
                    metric: 'overall'
                }
            },
            {
                name: 'Check Achievements',
                method: 'GET',
                url: '/modules/consignments/api/barcode_analytics.php',
                action: 'check_achievements',
                description: 'Check and unlock user achievements based on performance',
                params: ['user_id'],
                testData: {
                    user_id: 1
                }
            },
            {
                name: 'Get Suspicious Scans',
                method: 'GET',
                url: '/modules/consignments/api/barcode_analytics.php',
                action: 'get_suspicious_scans',
                description: 'Retrieve fraud-flagged scans for security review',
                params: ['severity', 'period'],
                testData: {
                    severity: 'all',
                    period: 'week'
                }
            },
            {
                name: 'Get Scan Details',
                method: 'GET',
                url: '/modules/consignments/api/barcode_analytics.php',
                action: 'get_scan_details',
                description: 'Get detailed information about a specific scan event',
                params: ['event_id'],
                testData: {
                    event_id: 1
                }
            },

            // Settings API Endpoints
            {
                name: 'Get Settings',
                method: 'GET',
                url: '/modules/consignments/api/analytics_settings.php',
                action: 'get_settings',
                description: 'Retrieve cascaded settings (global → outlet → user → transfer)',
                params: ['user_id', 'outlet_id', 'transfer_id'],
                testData: {
                    user_id: 1,
                    outlet_id: 1
                }
            },
            {
                name: 'Get Presets',
                method: 'GET',
                url: '/modules/consignments/api/analytics_settings.php',
                action: 'get_presets',
                description: 'List all complexity presets (Very Basic to Expert)',
                params: [],
                testData: {}
            },
            {
                name: 'Apply Preset',
                method: 'POST',
                url: '/modules/consignments/api/analytics_settings.php',
                action: 'apply_preset',
                description: 'Apply a complexity preset to specific level',
                params: ['preset_name', 'level', 'user_id', 'outlet_id'],
                testData: {
                    preset_name: 'balanced',
                    level: 'outlet',
                    outlet_id: 1
                }
            },
            {
                name: 'Update Settings',
                method: 'POST',
                url: '/modules/consignments/api/analytics_settings.php',
                action: 'update_settings',
                description: 'Update settings at any cascade level',
                params: ['level', 'settings'],
                testData: {
                    level: 'global',
                    settings: {
                        analytics_enabled: true,
                        show_leaderboard: true
                    }
                }
            }
        ];

        let testResults = [];
        let totalTests = 0;
        let completedTests = 0;

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('totalEndpoints').textContent = endpoints.length;
            renderEndpoints();
        });

        function renderEndpoints() {
            const grid = document.getElementById('endpointGrid');
            grid.innerHTML = '';

            endpoints.forEach((endpoint, index) => {
                const card = document.createElement('div');
                card.className = 'endpoint-card';
                card.id = `endpoint-${index}`;

                const paramBadges = endpoint.params.map(p =>
                    `<span class="param-badge">${p}</span>`
                ).join('');

                card.innerHTML = `
                    <div class="endpoint-header">
                        <div>
                            <span class="status-badge status-pending" id="status-${index}"></span>
                            <strong>${endpoint.name}</strong>
                        </div>
                        <span class="endpoint-method method-${endpoint.method.toLowerCase()}">${endpoint.method}</span>
                    </div>
                    <div class="endpoint-description">${endpoint.description}</div>
                    <div class="param-list">
                        <small><strong>Parameters:</strong> ${paramBadges || '<em>None</em>'}</small>
                    </div>
                    <div class="mt-2">
                        <small class="text-muted">
                            <i class="bi bi-link-45deg"></i>
                            ${endpoint.url}?action=${endpoint.action}
                        </small>
                    </div>
                    <div class="response-time mt-2" id="time-${index}" style="display: none;">
                        <i class="bi bi-clock"></i> Response Time: <span id="duration-${index}">0</span>ms
                    </div>
                    <button class="btn btn-outline-primary test-btn" onclick="testEndpoint(${index})">
                        <i class="bi bi-play-circle"></i> Test Endpoint
                    </button>
                    <div class="response-preview" id="response-${index}"></div>
                `;

                grid.appendChild(card);
            });
        }

        async function testEndpoint(index) {
            const endpoint = endpoints[index];
            const statusBadge = document.getElementById(`status-${index}`);
            const timeDisplay = document.getElementById(`time-${index}`);
            const durationSpan = document.getElementById(`duration-${index}`);
            const responseDiv = document.getElementById(`response-${index}`);

            // Reset status
            statusBadge.className = 'status-badge status-pending';
            responseDiv.classList.remove('show');

            const startTime = performance.now();

            try {
                const url = endpoint.url + '?action=' + endpoint.action;
                const options = {
                    method: endpoint.method,
                    headers: {
                        'Content-Type': 'application/json'
                    }
                };

                if (endpoint.method === 'POST') {
                    options.body = JSON.stringify(endpoint.testData);
                } else {
                    // Add GET params to URL
                    const params = new URLSearchParams(endpoint.testData);
                    const fullUrl = url + '&' + params.toString();
                    const response = await fetch(fullUrl, options);
                    const data = await response.json();

                    const duration = Math.round(performance.now() - startTime);

                    // Update UI
                    statusBadge.className = 'status-badge status-' + (data.success ? 'success' : 'error');
                    timeDisplay.style.display = 'block';
                    durationSpan.textContent = duration;

                    responseDiv.textContent = JSON.stringify(data, null, 2);
                    responseDiv.classList.add('show');

                    // Store result
                    testResults[index] = {
                        endpoint: endpoint.name,
                        success: data.success || false,
                        duration: duration,
                        response: data
                    };

                    updateStats();
                    return;
                }

                // For POST requests
                const response = await fetch(url, options);
                const data = await response.json();

                const duration = Math.round(performance.now() - startTime);

                // Update UI
                statusBadge.className = 'status-badge status-' + (data.success ? 'success' : 'error');
                timeDisplay.style.display = 'block';
                durationSpan.textContent = duration;

                responseDiv.textContent = JSON.stringify(data, null, 2);
                responseDiv.classList.add('show');

                // Store result
                testResults[index] = {
                    endpoint: endpoint.name,
                    success: data.success || false,
                    duration: duration,
                    response: data
                };

                updateStats();

            } catch (error) {
                const duration = Math.round(performance.now() - startTime);

                statusBadge.className = 'status-badge status-error';
                timeDisplay.style.display = 'block';
                durationSpan.textContent = duration;

                responseDiv.textContent = JSON.stringify({
                    error: error.message,
                    type: 'Network Error'
                }, null, 2);
                responseDiv.classList.add('show');

                testResults[index] = {
                    endpoint: endpoint.name,
                    success: false,
                    duration: duration,
                    error: error.message
                };

                updateStats();
            }
        }

        async function testAll() {
            for (let i = 0; i < endpoints.length; i++) {
                await testEndpoint(i);
                // Small delay between tests
                await new Promise(resolve => setTimeout(resolve, 100));
            }
        }

        function updateStats() {
            const tested = testResults.filter(r => r !== undefined).length;
            const success = testResults.filter(r => r && r.success).length;
            const failed = testResults.filter(r => r && !r.success).length;
            const avgDuration = tested > 0
                ? Math.round(testResults.filter(r => r).reduce((sum, r) => sum + r.duration, 0) / tested)
                : 0;

            document.getElementById('testedEndpoints').textContent = tested;
            document.getElementById('successEndpoints').textContent = success;
            document.getElementById('failedEndpoints').textContent = failed;
            document.getElementById('avgResponse').innerHTML = avgDuration + '<span style="font-size: 16px;">ms</span>';
        }

        function exportResults() {
            const results = {
                timestamp: new Date().toISOString(),
                summary: {
                    total: endpoints.length,
                    tested: testResults.filter(r => r !== undefined).length,
                    success: testResults.filter(r => r && r.success).length,
                    failed: testResults.filter(r => r && !r.success).length
                },
                endpoints: testResults.filter(r => r !== undefined)
            };

            const blob = new Blob([JSON.stringify(results, null, 2)], { type: 'application/json' });
            const url = URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = `endpoint-test-results-${Date.now()}.json`;
            document.body.appendChild(a);
            a.click();
            document.body.removeChild(a);
            URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
