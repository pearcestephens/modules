<?php
/**
 * Barcode Scanner Management Control Panel
 * Complete admin interface for managing barcode scanning system
 *
 * Features:
 * - Global settings
 * - Per-outlet configuration
 * - Per-user preferences
 * - Scan history & analytics
 * - Real-time monitoring
 * - Audit log viewing
 */

require_once __DIR__ . '/../../app/bootstrap.php';
require_once __DIR__ . '/../../app/auth.php';

// Require admin access
if (!hasPermission('barcode_admin')) {
    http_response_code(403);
    die('Access denied. Barcode admin permission required.');
}

$db = getDb();
$currentUser = getCurrentUser();

// Get all outlets for dropdown
$outlets = $db->query("SELECT id, name, code FROM outlets ORDER BY name")->fetchAll(PDO::FETCH_ASSOC);

// Get global configuration
$globalConfig = $db->query("SELECT * FROM BARCODE_CONFIGURATION WHERE outlet_id IS NULL")->fetch(PDO::FETCH_ASSOC);
if (!$globalConfig) {
    // Create default if not exists
    $db->exec("INSERT INTO BARCODE_CONFIGURATION (outlet_id, enabled) VALUES (NULL, 1)");
    $globalConfig = $db->query("SELECT * FROM BARCODE_CONFIGURATION WHERE outlet_id IS NULL")->fetch(PDO::FETCH_ASSOC);
}

// Get scan statistics
$statsQuery = "
    SELECT
        COUNT(*) as total_scans,
        SUM(CASE WHEN scan_result = 'success' THEN 1 ELSE 0 END) as successful_scans,
        SUM(CASE WHEN scan_method = 'usb_scanner' THEN 1 ELSE 0 END) as usb_scans,
        SUM(CASE WHEN scan_method = 'camera' THEN 1 ELSE 0 END) as camera_scans,
        COUNT(DISTINCT user_id) as unique_users,
        COUNT(DISTINCT outlet_id) as active_outlets,
        DATE(MAX(scan_timestamp)) as last_scan_date
    FROM BARCODE_SCANS
    WHERE scan_timestamp >= DATE_SUB(NOW(), INTERVAL 30 DAY)
";
$stats = $db->query($statsQuery)->fetch(PDO::FETCH_ASSOC);

// Get recent audit log
$auditLog = $db->query("
    SELECT * FROM BARCODE_AUDIT_LOG
    ORDER BY created_at DESC
    LIMIT 20
")->fetchAll(PDO::FETCH_ASSOC);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barcode Scanner Management | CIS</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary: #0366d6;
            --success: #28a745;
            --warning: #ffc107;
            --danger: #dc3545;
            --dark: #24292e;
        }

        body {
            background: #f6f8fa;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            font-size: 14px;
        }

        .header {
            background: var(--dark);
            color: white;
            padding: 1rem 0;
            margin-bottom: 2rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .header h1 {
            font-size: 24px;
            margin: 0;
            font-weight: 600;
        }

        .stat-card {
            background: white;
            border-radius: 6px;
            padding: 1.5rem;
            margin-bottom: 1rem;
            border: 1px solid #e1e4e8;
            transition: box-shadow 0.2s;
        }

        .stat-card:hover {
            box-shadow: 0 3px 8px rgba(0,0,0,0.1);
        }

        .stat-card .icon {
            font-size: 2rem;
            margin-bottom: 0.5rem;
        }

        .stat-card .value {
            font-size: 2rem;
            font-weight: 700;
            margin: 0.5rem 0;
        }

        .stat-card .label {
            color: #6a737d;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .config-section {
            background: white;
            border-radius: 6px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            border: 1px solid #e1e4e8;
        }

        .config-section h3 {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 1rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #e1e4e8;
        }

        .setting-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 0;
            border-bottom: 1px solid #f6f8fa;
        }

        .setting-row:last-child {
            border-bottom: none;
        }

        .setting-label {
            flex: 1;
        }

        .setting-label h4 {
            font-size: 14px;
            font-weight: 600;
            margin: 0 0 0.25rem 0;
        }

        .setting-label p {
            font-size: 13px;
            color: #6a737d;
            margin: 0;
        }

        .setting-control {
            margin-left: 2rem;
        }

        .toggle-switch {
            position: relative;
            width: 50px;
            height: 26px;
        }

        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }

        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: #ccc;
            transition: 0.3s;
            border-radius: 26px;
        }

        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 20px;
            width: 20px;
            left: 3px;
            bottom: 3px;
            background-color: white;
            transition: 0.3s;
            border-radius: 50%;
        }

        input:checked + .toggle-slider {
            background-color: var(--success);
        }

        input:checked + .toggle-slider:before {
            transform: translateX(24px);
        }

        .tab-nav {
            display: flex;
            gap: 1rem;
            border-bottom: 2px solid #e1e4e8;
            margin-bottom: 1.5rem;
        }

        .tab-nav button {
            background: none;
            border: none;
            padding: 0.75rem 1rem;
            font-size: 14px;
            font-weight: 500;
            color: #6a737d;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            margin-bottom: -2px;
            transition: all 0.2s;
        }

        .tab-nav button:hover {
            color: var(--dark);
        }

        .tab-nav button.active {
            color: var(--primary);
            border-bottom-color: var(--primary);
        }

        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        .audit-log-item {
            padding: 0.75rem;
            border-left: 3px solid #e1e4e8;
            margin-bottom: 0.5rem;
            background: #f6f8fa;
            border-radius: 3px;
        }

        .audit-log-item.action-enabled {
            border-left-color: var(--success);
        }

        .audit-log-item.action-disabled {
            border-left-color: var(--danger);
        }

        .audit-log-item .timestamp {
            font-size: 12px;
            color: #6a737d;
        }

        .badge-outlet {
            background: var(--primary);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-user {
            background: var(--warning);
            color: #333;
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
        }

        .badge-global {
            background: var(--dark);
            color: white;
            padding: 0.25rem 0.5rem;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
        }

        .color-picker-preview {
            width: 30px;
            height: 30px;
            border-radius: 3px;
            border: 2px solid #e1e4e8;
            cursor: pointer;
        }

        .outlet-config-card {
            background: white;
            border: 1px solid #e1e4e8;
            border-radius: 6px;
            padding: 1rem;
            margin-bottom: 1rem;
        }

        .outlet-config-card .header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #e1e4e8;
        }

        .btn-action {
            padding: 0.375rem 0.75rem;
            font-size: 13px;
            border-radius: 4px;
            border: 1px solid #e1e4e8;
            background: white;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-action:hover {
            background: #f6f8fa;
            border-color: var(--primary);
        }

        .btn-primary {
            background: var(--primary);
            color: white;
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background: #0256c7;
        }

        .btn-danger {
            background: var(--danger);
            color: white;
            border-color: var(--danger);
        }

        .btn-danger:hover {
            background: #c82333;
        }

        .save-indicator {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            background: var(--success);
            color: white;
            padding: 0.75rem 1.5rem;
            border-radius: 6px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.2);
            opacity: 0;
            transform: translateY(20px);
            transition: all 0.3s;
            pointer-events: none;
        }

        .save-indicator.show {
            opacity: 1;
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <div class="header">
        <div class="container">
            <div class="d-flex justify-content-between align-items-center">
                <h1><i class="bi bi-upc-scan"></i> Barcode Scanner Management</h1>
                <div>
                    <a href="/modules/consignments/" class="btn btn-outline-light btn-sm">
                        <i class="bi bi-arrow-left"></i> Back to Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="container">
        <!-- Statistics Overview -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon text-primary"><i class="bi bi-upc-scan"></i></div>
                    <div class="value"><?= number_format($stats['total_scans'] ?? 0) ?></div>
                    <div class="label">Total Scans (30d)</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon text-success"><i class="bi bi-check-circle"></i></div>
                    <div class="value"><?= number_format($stats['successful_scans'] ?? 0) ?></div>
                    <div class="label">Successful Scans</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon text-info"><i class="bi bi-people"></i></div>
                    <div class="value"><?= $stats['unique_users'] ?? 0 ?></div>
                    <div class="label">Active Users</div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="stat-card">
                    <div class="icon text-warning"><i class="bi bi-shop"></i></div>
                    <div class="value"><?= $stats['active_outlets'] ?? 0 ?></div>
                    <div class="label">Active Outlets</div>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="tab-nav">
            <button class="tab-btn active" data-tab="global">
                <i class="bi bi-globe"></i> Global Settings
            </button>
            <button class="tab-btn" data-tab="outlets">
                <i class="bi bi-shop"></i> Outlet Configuration
            </button>
            <button class="tab-btn" data-tab="users">
                <i class="bi bi-person-gear"></i> User Preferences
            </button>
            <button class="tab-btn" data-tab="history">
                <i class="bi bi-clock-history"></i> Scan History
            </button>
            <button class="tab-btn" data-tab="analytics">
                <i class="bi bi-graph-up"></i> Analytics
            </button>
            <button class="tab-btn" data-tab="audit">
                <i class="bi bi-shield-check"></i> Audit Log
            </button>
        </div>

        <!-- Global Settings Tab -->
        <div class="tab-content active" data-tab="global">
            <form id="globalSettingsForm">
                <input type="hidden" name="config_id" value="<?= $globalConfig['id'] ?>">
                <input type="hidden" name="scope" value="global">

                <!-- Master Controls -->
                <div class="config-section">
                    <h3><i class="bi bi-toggles"></i> Master Controls</h3>

                    <div class="setting-row">
                        <div class="setting-label">
                            <h4>Enable Barcode Scanning System</h4>
                            <p>Master switch for entire barcode scanning system across all outlets</p>
                        </div>
                        <div class="setting-control">
                            <label class="toggle-switch">
                                <input type="checkbox" name="enabled" <?= $globalConfig['enabled'] ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <h4>USB Hardware Scanners</h4>
                            <p>Enable USB barcode scanners (recommended for warehouse operations)</p>
                        </div>
                        <div class="setting-control">
                            <label class="toggle-switch">
                                <input type="checkbox" name="usb_scanner_enabled" <?= $globalConfig['usb_scanner_enabled'] ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <h4>Camera-Based Scanning</h4>
                            <p>Enable phone/webcam barcode scanning (requires HTTPS)</p>
                        </div>
                        <div class="setting-control">
                            <label class="toggle-switch">
                                <input type="checkbox" name="camera_scanner_enabled" <?= $globalConfig['camera_scanner_enabled'] ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <h4>Manual Barcode Entry</h4>
                            <p>Allow users to manually type barcode numbers</p>
                        </div>
                        <div class="setting-control">
                            <label class="toggle-switch">
                                <input type="checkbox" name="manual_entry_enabled" <?= $globalConfig['manual_entry_enabled'] ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>
                </div>

                <!-- Scanner Behavior -->
                <div class="config-section">
                    <h3><i class="bi bi-gear"></i> Scanner Behavior</h3>

                    <div class="setting-row">
                        <div class="setting-label">
                            <h4>Scanner Detection Mode</h4>
                            <p>How the system should detect and activate scanners</p>
                        </div>
                        <div class="setting-control">
                            <select name="scan_mode" class="form-select">
                                <option value="auto" <?= $globalConfig['scan_mode'] === 'auto' ? 'selected' : '' ?>>Auto-Detect</option>
                                <option value="usb_only" <?= $globalConfig['scan_mode'] === 'usb_only' ? 'selected' : '' ?>>USB Only</option>
                                <option value="camera_only" <?= $globalConfig['scan_mode'] === 'camera_only' ? 'selected' : '' ?>>Camera Only</option>
                                <option value="manual_only" <?= $globalConfig['scan_mode'] === 'manual_only' ? 'selected' : '' ?>>Manual Only</option>
                            </select>
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <h4>Require Exact Match</h4>
                            <p>Require exact barcode match or allow fuzzy matching</p>
                        </div>
                        <div class="setting-control">
                            <label class="toggle-switch">
                                <input type="checkbox" name="require_exact_match" <?= $globalConfig['require_exact_match'] ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <h4>Allow Duplicate Scans</h4>
                            <p>Allow scanning the same item multiple times consecutively</p>
                        </div>
                        <div class="setting-control">
                            <label class="toggle-switch">
                                <input type="checkbox" name="allow_duplicate_scans" <?= $globalConfig['allow_duplicate_scans'] ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <h4>Block on Quantity Exceed</h4>
                            <p>Block scan if quantity exceeds expected amount</p>
                        </div>
                        <div class="setting-control">
                            <label class="toggle-switch">
                                <input type="checkbox" name="block_on_qty_exceed" <?= $globalConfig['block_on_qty_exceed'] ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <h4>Scan Cooldown (milliseconds)</h4>
                            <p>Minimum time between scans to prevent accidental duplicates</p>
                        </div>
                        <div class="setting-control">
                            <input type="number" name="scan_cooldown_ms" value="<?= $globalConfig['scan_cooldown_ms'] ?>" class="form-control" style="width: 120px;" min="0" max="5000" step="50">
                        </div>
                    </div>
                </div>

                <!-- Audio Settings -->
                <div class="config-section">
                    <h3><i class="bi bi-volume-up"></i> Audio Feedback</h3>

                    <div class="setting-row">
                        <div class="setting-label">
                            <h4>Enable Audio Tones</h4>
                            <p>Play audio feedback tones on scan success/failure</p>
                        </div>
                        <div class="setting-control">
                            <label class="toggle-switch">
                                <input type="checkbox" name="audio_enabled" <?= $globalConfig['audio_enabled'] ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <h4>Audio Volume</h4>
                            <p>Volume level (0.0 - 1.0)</p>
                        </div>
                        <div class="setting-control">
                            <input type="range" name="audio_volume" min="0" max="1" step="0.05" value="<?= $globalConfig['audio_volume'] ?>" class="form-range" style="width: 200px;">
                            <span class="ms-2" id="volumeDisplay"><?= round($globalConfig['audio_volume'] * 100) ?>%</span>
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <h4>Success Tone Frequency (Hz)</h4>
                            <p>Tone played on successful scan</p>
                        </div>
                        <div class="setting-control">
                            <input type="number" name="tone1_frequency" value="<?= $globalConfig['tone1_frequency'] ?>" class="form-control" style="width: 120px;" min="200" max="2000" step="50">
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <h4>Warning Tone Frequency (Hz)</h4>
                            <p>Tone played on duplicate or unexpected scan</p>
                        </div>
                        <div class="setting-control">
                            <input type="number" name="tone2_frequency" value="<?= $globalConfig['tone2_frequency'] ?>" class="form-control" style="width: 120px;" min="200" max="2000" step="50">
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <h4>Error Tone Frequency (Hz)</h4>
                            <p>Tone played on scan failure or not found</p>
                        </div>
                        <div class="setting-control">
                            <input type="number" name="tone3_frequency" value="<?= $globalConfig['tone3_frequency'] ?>" class="form-control" style="width: 120px;" min="200" max="2000" step="50">
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <h4>Tone Duration (milliseconds)</h4>
                            <p>How long each tone plays</p>
                        </div>
                        <div class="setting-control">
                            <input type="number" name="tone_duration_ms" value="<?= $globalConfig['tone_duration_ms'] ?>" class="form-control" style="width: 120px;" min="50" max="1000" step="50">
                        </div>
                    </div>
                </div>

                <!-- Visual Feedback -->
                <div class="config-section">
                    <h3><i class="bi bi-palette"></i> Visual Feedback</h3>

                    <div class="setting-row">
                        <div class="setting-label">
                            <h4>Enable Visual Feedback</h4>
                            <p>Show colored flash animation on scan</p>
                        </div>
                        <div class="setting-control">
                            <label class="toggle-switch">
                                <input type="checkbox" name="visual_feedback_enabled" <?= $globalConfig['visual_feedback_enabled'] ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <h4>Success Color</h4>
                            <p>Flash color for successful scans</p>
                        </div>
                        <div class="setting-control d-flex align-items-center gap-2">
                            <input type="color" name="success_color" value="<?= $globalConfig['success_color'] ?>" class="form-control form-control-color">
                            <input type="text" value="<?= $globalConfig['success_color'] ?>" class="form-control" style="width: 100px;" readonly>
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <h4>Warning Color</h4>
                            <p>Flash color for warnings (duplicate, unexpected)</p>
                        </div>
                        <div class="setting-control d-flex align-items-center gap-2">
                            <input type="color" name="warning_color" value="<?= $globalConfig['warning_color'] ?>" class="form-control form-control-color">
                            <input type="text" value="<?= $globalConfig['warning_color'] ?>" class="form-control" style="width: 100px;" readonly>
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <h4>Error Color</h4>
                            <p>Flash color for errors (not found, failed)</p>
                        </div>
                        <div class="setting-control d-flex align-items-center gap-2">
                            <input type="color" name="error_color" value="<?= $globalConfig['error_color'] ?>" class="form-control form-control-color">
                            <input type="text" value="<?= $globalConfig['error_color'] ?>" class="form-control" style="width: 100px;" readonly>
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <h4>Flash Duration (milliseconds)</h4>
                            <p>How long the colored flash displays</p>
                        </div>
                        <div class="setting-control">
                            <input type="number" name="flash_duration_ms" value="<?= $globalConfig['flash_duration_ms'] ?>" class="form-control" style="width: 120px;" min="100" max="2000" step="50">
                        </div>
                    </div>
                </div>

                <!-- Logging & Retention -->
                <div class="config-section">
                    <h3><i class="bi bi-journal-text"></i> Logging & Data Retention</h3>

                    <div class="setting-row">
                        <div class="setting-label">
                            <h4>Log All Scans</h4>
                            <p>Record all successful scans to database</p>
                        </div>
                        <div class="setting-control">
                            <label class="toggle-switch">
                                <input type="checkbox" name="log_all_scans" <?= $globalConfig['log_all_scans'] ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <h4>Log Failed Scans</h4>
                            <p>Record failed scan attempts (not found, errors)</p>
                        </div>
                        <div class="setting-control">
                            <label class="toggle-switch">
                                <input type="checkbox" name="log_failed_scans" <?= $globalConfig['log_failed_scans'] ? 'checked' : '' ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                    </div>

                    <div class="setting-row">
                        <div class="setting-label">
                            <h4>Log Retention (days)</h4>
                            <p>How long to keep scan logs before automatic deletion</p>
                        </div>
                        <div class="setting-control">
                            <input type="number" name="log_retention_days" value="<?= $globalConfig['log_retention_days'] ?>" class="form-control" style="width: 120px;" min="7" max="365" step="1">
                        </div>
                    </div>
                </div>

                <!-- Save Button -->
                <div class="text-end">
                    <button type="button" class="btn btn-action" onclick="window.location.reload()">
                        <i class="bi bi-arrow-clockwise"></i> Reset Changes
                    </button>
                    <button type="submit" class="btn btn-action btn-primary">
                        <i class="bi bi-save"></i> Save Global Settings
                    </button>
                </div>
            </form>
        </div>

        <!-- Outlet Configuration Tab -->
        <div class="tab-content" data-tab="outlets">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3>Per-Outlet Configuration</h3>
                <button class="btn btn-action btn-primary" onclick="showOutletConfigModal()">
                    <i class="bi bi-plus-circle"></i> Configure Outlet
                </button>
            </div>

            <div id="outletConfigsList">
                <!-- Populated by JavaScript -->
            </div>
        </div>

        <!-- User Preferences Tab -->
        <div class="tab-content" data-tab="users">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3>User-Specific Preferences</h3>
                <button class="btn btn-action btn-primary" onclick="showUserPrefModal()">
                    <i class="bi bi-person-plus"></i> Set User Preference
                </button>
            </div>

            <div id="userPrefsList">
                <!-- Populated by JavaScript -->
            </div>
        </div>

        <!-- Scan History Tab -->
        <div class="tab-content" data-tab="history">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3>Scan History</h3>
                <div>
                    <button class="btn btn-action" onclick="exportScans()">
                        <i class="bi bi-download"></i> Export CSV
                    </button>
                    <button class="btn btn-action" onclick="loadScanHistory()">
                        <i class="bi bi-arrow-clockwise"></i> Refresh
                    </button>
                </div>
            </div>

            <!-- Filters -->
            <div class="config-section mb-3">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Date Range</label>
                        <select id="dateRange" class="form-select" onchange="loadScanHistory()">
                            <option value="1">Last 24 Hours</option>
                            <option value="7">Last 7 Days</option>
                            <option value="30" selected>Last 30 Days</option>
                            <option value="90">Last 90 Days</option>
                            <option value="365">Last Year</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Outlet</label>
                        <select id="filterOutlet" class="form-select" onchange="loadScanHistory()">
                            <option value="">All Outlets</option>
                            <?php foreach ($outlets as $outlet): ?>
                                <option value="<?= $outlet['id'] ?>"><?= htmlspecialchars($outlet['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Scan Method</label>
                        <select id="filterMethod" class="form-select" onchange="loadScanHistory()">
                            <option value="">All Methods</option>
                            <option value="usb_scanner">USB Scanner</option>
                            <option value="camera">Camera</option>
                            <option value="manual_entry">Manual Entry</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Result</label>
                        <select id="filterResult" class="form-select" onchange="loadScanHistory()">
                            <option value="">All Results</option>
                            <option value="success">Success</option>
                            <option value="not_found">Not Found</option>
                            <option value="duplicate">Duplicate</option>
                            <option value="blocked">Blocked</option>
                        </select>
                    </div>
                </div>
            </div>

            <div id="scanHistoryTable">
                <!-- Populated by JavaScript -->
            </div>
        </div>

        <!-- Analytics Tab -->
        <div class="tab-content" data-tab="analytics">
            <h3>Scanner Analytics</h3>
            <div id="analyticsCharts">
                <!-- Charts populated by JavaScript -->
            </div>
        </div>

        <!-- Audit Log Tab -->
        <div class="tab-content" data-tab="audit">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h3>Configuration Audit Log</h3>
                <button class="btn btn-action" onclick="loadAuditLog()">
                    <i class="bi bi-arrow-clockwise"></i> Refresh
                </button>
            </div>

            <div id="auditLogList">
                <?php foreach ($auditLog as $log): ?>
                    <div class="audit-log-item action-<?= $log['action'] ?>">
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <strong><?= ucwords(str_replace('_', ' ', $log['action'])) ?></strong>
                                <span class="badge-<?= $log['target_type'] ?> ms-2"><?= strtoupper($log['target_type']) ?></span>
                                <div class="timestamp"><?= date('Y-m-d H:i:s', strtotime($log['created_at'])) ?></div>
                            </div>
                            <div class="text-end">
                                <small><?= $log['ip_address'] ?? 'N/A' ?></small>
                            </div>
                        </div>
                        <?php if ($log['field_name']): ?>
                            <div class="mt-2">
                                <code><?= htmlspecialchars($log['field_name']) ?></code>:
                                <span class="text-muted"><?= htmlspecialchars($log['old_value'] ?? 'null') ?></span> →
                                <span class="text-success"><?= htmlspecialchars($log['new_value'] ?? 'null') ?></span>
                            </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <!-- Save Indicator -->
    <div class="save-indicator" id="saveIndicator">
        <i class="bi bi-check-circle"></i> Settings saved successfully
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Tab switching
        document.querySelectorAll('.tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const tab = btn.dataset.tab;

                // Update buttons
                document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                btn.classList.add('active');

                // Update content
                document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));
                document.querySelector(`.tab-content[data-tab="${tab}"]`).classList.add('active');
            });
        });

        // Volume slider display
        document.querySelector('input[name="audio_volume"]')?.addEventListener('input', (e) => {
            document.getElementById('volumeDisplay').textContent = Math.round(e.target.value * 100) + '%';
        });

        // Color picker sync with text input
        document.querySelectorAll('input[type="color"]').forEach(picker => {
            const textInput = picker.nextElementSibling;
            picker.addEventListener('input', (e) => {
                textInput.value = e.target.value;
            });
        });

        // Global settings form submit
        document.getElementById('globalSettingsForm').addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(e.target);
            const data = Object.fromEntries(formData);

            // Convert checkboxes to 1/0
            ['enabled', 'usb_scanner_enabled', 'camera_scanner_enabled', 'manual_entry_enabled',
             'require_exact_match', 'allow_duplicate_scans', 'block_on_qty_exceed',
             'audio_enabled', 'visual_feedback_enabled', 'log_all_scans', 'log_failed_scans'].forEach(field => {
                data[field] = document.querySelector(`input[name="${field}"]`).checked ? 1 : 0;
            });

            try {
                const response = await fetch('/modules/consignments/api/barcode_config.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'update_global', ...data })
                });

                const result = await response.json();

                if (result.success) {
                    showSaveIndicator();
                } else {
                    alert('Error: ' + (result.error || 'Unknown error'));
                }
            } catch (error) {
                alert('Save failed: ' + error.message);
            }
        });

        function showSaveIndicator() {
            const indicator = document.getElementById('saveIndicator');
            indicator.classList.add('show');
            setTimeout(() => indicator.classList.remove('show'), 3000);
        }

        // Load outlet configurations
        async function loadOutletConfigs() {
            // Implementation
        }

        // Load user preferences
        async function loadUserPrefs() {
            // Implementation
        }

        // Load scan history
        async function loadScanHistory() {
            // Implementation
        }

        // Load audit log
        async function loadAuditLog() {
            // Implementation
        }

        // Export scans
        function exportScans() {
            window.location.href = '/modules/consignments/api/barcode_export.php?format=csv';
        }

        // Initialize
        document.addEventListener('DOMContentLoaded', () => {
            loadOutletConfigs();
            loadUserPrefs();
            loadScanHistory();
        });
    </script>
</body>
</html>
