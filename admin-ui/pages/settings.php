<?php

// Get CIS database connection
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
/**
 * Settings Page - Dashboard Configuration
 *
 * Manages dashboard settings, preferences, and configuration
 */

declare(strict_types=1);

// Get database connection

// Get current settings
$settingsQuery = "SELECT * FROM dashboard_config LIMIT 1";
$settingsStmt = $pdo->prepare($settingsQuery);
$settingsStmt->execute([]);
$settings = $settingsStmt->fetch(PDO::FETCH_ASSOC) ?: [
    'theme' => 'light',
    'auto_refresh' => 300,
    'items_per_page' => 25,
    'email_alerts' => 1,
];

// Handle settings update
$message = '';
$messageType = '';
if ($_POST && isset($_POST['save_settings'])) {
    $theme = $_POST['theme'] ?? 'light';
    $autoRefresh = (int)($_POST['auto_refresh'] ?? 300);
    $itemsPerPage = (int)($_POST['items_per_page'] ?? 25);
    $emailAlerts = isset($_POST['email_alerts']) ? 1 : 0;

    try {
        $updateQuery = "UPDATE dashboard_config SET theme = ?, auto_refresh = ?, items_per_page = ?, email_alerts = ? WHERE id = 1";
        $updateStmt = $pdo->prepare($updateQuery);
        $updateStmt->execute([$theme, $autoRefresh, $itemsPerPage, $emailAlerts]);

        $message = '✓ Settings updated successfully';
        $messageType = 'success';
        $settings['theme'] = $theme;
        $settings['auto_refresh'] = $autoRefresh;
        $settings['items_per_page'] = $itemsPerPage;
        $settings['email_alerts'] = $emailAlerts;
    } catch (Exception $e) {
        $message = '✗ Error updating settings: ' . $e->getMessage();
        $messageType = 'danger';
    }
}
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-8">
            <h1 class="h3">⚙️ Dashboard Settings</h1>
            <p class="text-muted">Configure dashboard preferences and behavior</p>
        </div>
    </div>

    <?php if ($message): ?>
    <div class="alert alert-<?php echo $messageType; ?> alert-dismissible fade show" role="alert">
        <?php echo htmlspecialchars($message); ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
    <?php endif; ?>

    <div class="row">
        <!-- Display Settings -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">🎨 Display Settings</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <!-- Theme -->
                        <div class="mb-3">
                            <label class="form-label">Theme</label>
                            <select name="theme" class="form-select">
                                <option value="light" <?php echo $settings['theme'] === 'light' ? 'selected' : ''; ?>>Light</option>
                                <option value="dark" <?php echo $settings['theme'] === 'dark' ? 'selected' : ''; ?>>Dark</option>
                                <option value="auto" <?php echo $settings['theme'] === 'auto' ? 'selected' : ''; ?>>Auto</option>
                            </select>
                            <small class="form-text text-muted">Choose the color scheme for the dashboard</small>
                        </div>

                        <!-- Items Per Page -->
                        <div class="mb-3">
                            <label class="form-label">Items Per Page</label>
                            <select name="items_per_page" class="form-select">
                                <option value="10" <?php echo $settings['items_per_page'] == 10 ? 'selected' : ''; ?>>10</option>
                                <option value="25" <?php echo $settings['items_per_page'] == 25 ? 'selected' : ''; ?>>25</option>
                                <option value="50" <?php echo $settings['items_per_page'] == 50 ? 'selected' : ''; ?>>50</option>
                                <option value="100" <?php echo $settings['items_per_page'] == 100 ? 'selected' : ''; ?>>100</option>
                            </select>
                            <small class="form-text text-muted">Default number of items shown in tables</small>
                        </div>

                        <!-- Auto Refresh -->
                        <div class="mb-3">
                            <label class="form-label">Auto-Refresh Interval (seconds)</label>
                            <input type="number" name="auto_refresh" class="form-control" min="30" max="3600" step="30" value="<?php echo $settings['auto_refresh']; ?>">
                            <small class="form-text text-muted">How often to refresh data automatically (0 = disabled)</small>
                        </div>

                        <button type="submit" name="save_settings" class="btn btn-primary">
                            💾 Save Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Notification Settings -->
        <div class="col-md-6 mb-4">
            <div class="card">
                <div class="card-header bg-info text-white">
                    <h5 class="mb-0">🔔 Notification Settings</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <!-- Email Alerts -->
                        <div class="mb-3">
                            <div class="form-check">
                                <input type="checkbox" name="email_alerts" class="form-check-input" id="emailAlerts" <?php echo $settings['email_alerts'] ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="emailAlerts">
                                    Enable Email Alerts
                                </label>
                                <small class="form-text text-muted d-block">
                                    Receive email notifications for critical issues
                                </small>
                            </div>
                        </div>

                        <!-- Alert Types -->
                        <div class="mb-3">
                            <label class="form-label">Alert For:</label>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="alertCritical" checked disabled>
                                <label class="form-check-label" for="alertCritical">
                                    Critical Issues
                                </label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="alertWarning">
                                <label class="form-check-label" for="alertWarning">
                                    Warning Issues
                                </label>
                            </div>
                            <div class="form-check">
                                <input type="checkbox" class="form-check-input" id="alertInfo">
                                <label class="form-check-label" for="alertInfo">
                                    Info Updates
                                </label>
                            </div>
                        </div>

                        <button type="submit" name="save_settings" class="btn btn-primary">
                            💾 Save Settings
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- System Settings -->
    <div class="row">
        <div class="col-md-12 mb-4">
            <div class="card">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">🔧 System Settings</h5>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>MCP Hub Configuration</h6>
                            <ul class="list-unstyled">
                                <li><strong>Domain:</strong> gpt.ecigdis.co.nz</li>
                                <li><strong>Protocol:</strong> HTTPS</li>
                                <li><strong>Status:</strong> <span class="badge bg-success">Connected</span></li>
                            </ul>
                        </div>
                        <div class="col-md-6">
                            <h6>Database Configuration</h6>
                            <ul class="list-unstyled">
                                <li><strong>Host:</strong> localhost</li>
                                <li><strong>Database:</strong> hdgwrzntwa</li>
                                <li><strong>Status:</strong> <span class="badge bg-success">Connected</span></li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- About -->
    <div class="row">
        <div class="col-md-12 mb-4">
            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0">ℹ️ About Dashboard</h5>
                </div>
                <div class="card-body">
                    <p><strong>Dashboard Version:</strong> 1.0.0</p>
                    <p><strong>Build Date:</strong> October 30, 2025</p>
                    <p><strong>Last Updated:</strong> <?php echo date('F j, Y g:i A'); ?></p>
                    <hr>
                    <p class="mb-0"><small class="text-muted">
                        This dashboard provides comprehensive analysis and monitoring of your projects.
                        For support, contact: support@ecigdis.co.nz
                    </small></p>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.card {
    border: none;
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
    border-radius: 0.5rem;
}

.card-header {
    border-radius: 0.5rem 0.5rem 0 0;
    font-weight: 600;
}

.form-label {
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.form-control, .form-select {
    border: 1px solid #dee2e6;
    border-radius: 0.375rem;
}

.btn {
    font-weight: 600;
    border-radius: 0.375rem;
}

.badge {
    padding: 0.35rem 0.65rem;
    font-weight: 500;
}
</style>
