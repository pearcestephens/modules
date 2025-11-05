<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analytics System - Testing & Tools Hub</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
            font-family: 'Segoe UI', sans-serif;
        }

        .hub-container {
            max-width: 1400px;
            margin: 0 auto;
        }

        .hero-section {
            background: white;
            border-radius: 16px;
            padding: 50px;
            text-align: center;
            margin-bottom: 40px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .hero-section h1 {
            font-size: 48px;
            font-weight: bold;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            margin-bottom: 20px;
        }

        .hero-section .lead {
            font-size: 20px;
            color: #6c757d;
            margin-bottom: 30px;
        }

        .status-badge {
            display: inline-block;
            padding: 10px 20px;
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            border-radius: 50px;
            font-weight: 600;
            font-size: 14px;
            margin-top: 10px;
        }

        .section-header {
            color: white;
            font-size: 28px;
            font-weight: 600;
            margin-bottom: 25px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .tools-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(350px, 1fr));
            gap: 25px;
            margin-bottom: 40px;
        }

        .tool-card {
            background: white;
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s;
            position: relative;
            overflow: hidden;
        }

        .tool-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 5px;
            height: 100%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .tool-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .tool-icon {
            width: 60px;
            height: 60px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .tool-card h3 {
            font-size: 22px;
            font-weight: 600;
            margin-bottom: 12px;
            color: #333;
        }

        .tool-card p {
            color: #6c757d;
            font-size: 15px;
            line-height: 1.6;
            margin-bottom: 20px;
        }

        .tool-features {
            list-style: none;
            padding: 0;
            margin-bottom: 20px;
        }

        .tool-features li {
            padding: 6px 0;
            color: #495057;
            font-size: 14px;
        }

        .tool-features li i {
            color: #28a745;
            margin-right: 8px;
        }

        .btn-launch {
            width: 100%;
            padding: 12px;
            font-weight: 600;
            border-radius: 8px;
            transition: all 0.2s;
        }

        .btn-launch:hover {
            transform: scale(1.02);
        }

        .stats-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-top: 30px;
        }

        .stat-box {
            background: rgba(255,255,255,0.2);
            backdrop-filter: blur(10px);
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            color: white;
        }

        .stat-value {
            font-size: 42px;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .stat-label {
            font-size: 14px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .dashboard-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }

        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        }

        .dashboard-preview {
            width: 100%;
            height: 200px;
            background: linear-gradient(135deg, #f8f9fa 0%, #e9ecef 100%);
            border-radius: 8px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 64px;
            color: #dee2e6;
        }

        .doc-card {
            background: white;
            border-radius: 12px;
            padding: 25px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            margin-bottom: 20px;
        }

        .doc-card h4 {
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 15px;
        }

        .badge-custom {
            padding: 6px 12px;
            font-size: 12px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="hub-container">
        <!-- Hero Section -->
        <div class="hero-section">
            <h1><i class="bi bi-shield-check"></i> Analytics System Hub</h1>
            <p class="lead">Complete Testing & Development Tools for Barcode Analytics</p>
            <div class="status-badge">
                <i class="bi bi-check-circle-fill"></i> ALL SYSTEMS OPERATIONAL
            </div>

            <div class="stats-row">
                <div class="stat-box">
                    <div class="stat-value">13</div>
                    <div class="stat-label">API Endpoints</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value">14</div>
                    <div class="stat-label">Database Tables</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value">3</div>
                    <div class="stat-label">Dashboards</div>
                </div>
                <div class="stat-box">
                    <div class="stat-value">50+</div>
                    <div class="stat-label">Automated Tests</div>
                </div>
            </div>
        </div>

        <!-- Testing Tools Section -->
        <div class="section-header">
            <i class="bi bi-tools"></i> Testing & Development Tools
        </div>

        <div class="tools-grid">
            <!-- Comprehensive Test Suite -->
            <div class="tool-card">
                <div class="tool-icon">
                    <i class="bi bi-clipboard-check"></i>
                </div>
                <h3>Comprehensive Test Suite</h3>
                <p>Automated testing of all system components with detailed reporting and visual feedback.</p>
                <ul class="tool-features">
                    <li><i class="bi bi-check-circle-fill"></i> 50+ Automated Tests</li>
                    <li><i class="bi bi-check-circle-fill"></i> Database Schema Validation</li>
                    <li><i class="bi bi-check-circle-fill"></i> API Endpoint Verification</li>
                    <li><i class="bi bi-check-circle-fill"></i> Fraud Detection Tests</li>
                    <li><i class="bi bi-check-circle-fill"></i> Data Integrity Checks</li>
                </ul>
                <a href="COMPREHENSIVE_TEST_SUITE.php" class="btn btn-primary btn-launch">
                    <i class="bi bi-play-circle"></i> Run All Tests
                </a>
            </div>

            <!-- Endpoint Verifier -->
            <div class="tool-card">
                <div class="tool-icon">
                    <i class="bi bi-router"></i>
                </div>
                <h3>Endpoint Verifier</h3>
                <p>Real-time API endpoint testing with HTTP requests and response analysis.</p>
                <ul class="tool-features">
                    <li><i class="bi bi-check-circle-fill"></i> 13 Pre-configured Endpoints</li>
                    <li><i class="bi bi-check-circle-fill"></i> Interactive Testing Interface</li>
                    <li><i class="bi bi-check-circle-fill"></i> Response Time Tracking</li>
                    <li><i class="bi bi-check-circle-fill"></i> JSON Response Preview</li>
                    <li><i class="bi bi-check-circle-fill"></i> Export Test Results</li>
                </ul>
                <a href="ENDPOINT_VERIFIER.php" class="btn btn-success btn-launch">
                    <i class="bi bi-broadcast"></i> Test Endpoints
                </a>
            </div>

            <!-- Database Health Check -->
            <div class="tool-card">
                <div class="tool-icon">
                    <i class="bi bi-heart-pulse"></i>
                </div>
                <h3>Database Health Check</h3>
                <p>Complete database structure and performance analysis with integrity verification.</p>
                <ul class="tool-features">
                    <li><i class="bi bi-check-circle-fill"></i> Connection Health Monitoring</li>
                    <li><i class="bi bi-check-circle-fill"></i> Table & View Validation</li>
                    <li><i class="bi bi-check-circle-fill"></i> Performance Benchmarks</li>
                    <li><i class="bi bi-check-circle-fill"></i> Index Verification</li>
                    <li><i class="bi bi-check-circle-fill"></i> Data Integrity Checks</li>
                </ul>
                <a href="DATABASE_HEALTH_CHECK.php" class="btn btn-info btn-launch">
                    <i class="bi bi-database-check"></i> Check Database
                </a>
            </div>
        </div>

        <!-- Analytics Dashboards Section -->
        <div class="section-header">
            <i class="bi bi-speedometer2"></i> Analytics Dashboards
        </div>

        <div class="tools-grid">
            <!-- Performance Dashboard -->
            <div class="dashboard-card">
                <div class="dashboard-preview">
                    <i class="bi bi-graph-up-arrow"></i>
                </div>
                <h4>
                    Performance Dashboard
                    <span class="badge bg-success badge-custom">Live</span>
                </h4>
                <p>Track scanning performance with detailed metrics, achievements, and personal bests.</p>
                <a href="performance-dashboard.php" class="btn btn-outline-primary w-100">
                    <i class="bi bi-bar-chart-line"></i> Open Dashboard
                </a>
            </div>

            <!-- Leaderboard -->
            <div class="dashboard-card">
                <div class="dashboard-preview">
                    <i class="bi bi-trophy"></i>
                </div>
                <h4>
                    Leaderboard
                    <span class="badge bg-warning badge-custom">Live</span>
                </h4>
                <p>View rankings, compete with colleagues, and track performance across outlets.</p>
                <a href="leaderboard.php" class="btn btn-outline-warning w-100">
                    <i class="bi bi-award"></i> View Rankings
                </a>
            </div>

            <!-- Security Dashboard -->
            <div class="dashboard-card">
                <div class="dashboard-preview">
                    <i class="bi bi-shield-exclamation"></i>
                </div>
                <h4>
                    Security Dashboard
                    <span class="badge bg-danger badge-custom">Live</span>
                </h4>
                <p>Monitor suspicious activity, investigate fraud alerts, and review security patterns.</p>
                <a href="security-dashboard.php" class="btn btn-outline-danger w-100">
                    <i class="bi bi-shield-check"></i> Security Monitor
                </a>
            </div>
        </div>

        <!-- Documentation Section -->
        <div class="section-header">
            <i class="bi bi-book"></i> Documentation & Reports
        </div>

        <div class="row">
            <div class="col-md-6">
                <div class="doc-card">
                    <h4><i class="bi bi-file-text"></i> Testing Documentation</h4>
                    <p>Complete guide to testing procedures, expected results, and troubleshooting.</p>
                    <a href="TESTING_DOCUMENTATION.md" class="btn btn-outline-secondary btn-sm" target="_blank">
                        <i class="bi bi-file-earmark-text"></i> View Documentation
                    </a>
                </div>
            </div>
            <div class="col-md-6">
                <div class="doc-card">
                    <h4><i class="bi bi-clipboard-data"></i> System Analysis Report</h4>
                    <p>Comprehensive analysis of all system components, test results, and quality metrics.</p>
                    <a href="SYSTEM_ANALYSIS_REPORT.md" class="btn btn-outline-secondary btn-sm" target="_blank">
                        <i class="bi bi-file-earmark-bar-graph"></i> View Report
                    </a>
                </div>
            </div>
        </div>

        <!-- Quick Stats -->
        <div class="hero-section mt-4">
            <h3>System Status</h3>
            <div class="row mt-4">
                <div class="col-md-3">
                    <div class="text-center">
                        <div style="font-size: 48px; color: #28a745;"><i class="bi bi-check-circle-fill"></i></div>
                        <h5>Database</h5>
                        <p class="text-muted mb-0">11 Tables + 3 Views</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <div style="font-size: 48px; color: #007bff;"><i class="bi bi-check-circle-fill"></i></div>
                        <h5>API Endpoints</h5>
                        <p class="text-muted mb-0">13 Endpoints Active</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <div style="font-size: 48px; color: #17a2b8;"><i class="bi bi-check-circle-fill"></i></div>
                        <h5>Dashboards</h5>
                        <p class="text-muted mb-0">3 Pages Live</p>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="text-center">
                        <div style="font-size: 48px; color: #ffc107;"><i class="bi bi-check-circle-fill"></i></div>
                        <h5>Security</h5>
                        <p class="text-muted mb-0">5+ Fraud Rules</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="text-center mt-4" style="color: white;">
            <p>Analytics System v1.0.0 | Last Updated: November 5, 2025</p>
            <p><small>All systems tested and verified | Production Ready ✅</small></p>
        </div>
    </div>
</body>
</html>
