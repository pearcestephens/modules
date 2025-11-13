<?php
/**
 * User Security Settings Page
 * Manage password, two-factor authentication, sessions, and security
 */

require_once __DIR__ . '/../../base/templates/vape-ultra/config.php';
require_once __DIR__ . '/../../base/templates/vape-ultra/layouts/main.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../controllers/SettingsController.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: /login');
    exit;
}

// Initialize controller
$settingsController = new SettingsController();

// Get current settings
$settings = $settingsController->getSettings($_SESSION['user_id']);

// Handle form submission
$message = null;
$error = null;
$activeTab = $_GET['tab'] ?? 'password';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? null;

    try {
        if ($action === 'change_password') {
            $current = htmlspecialchars($_POST['current_password'] ?? '');
            $new = htmlspecialchars($_POST['new_password'] ?? '');
            $confirm = htmlspecialchars($_POST['confirm_password'] ?? '');

            if (empty($current) || empty($new) || empty($confirm)) {
                throw new Exception('All password fields are required');
            }

            if ($new !== $confirm) {
                throw new Exception('New passwords do not match');
            }

            if (strlen($new) < CORE_MIN_PASSWORD_LENGTH) {
                throw new Exception('Password must be at least ' . CORE_MIN_PASSWORD_LENGTH . ' characters');
            }

            if ($settingsController->changePassword($_SESSION['user_id'], $current, $new)) {
                $message = 'Password changed successfully!';
                $activeTab = 'password';
            }
        }
        elseif ($action === 'enable_2fa') {
            $method = htmlspecialchars($_POST['2fa_method'] ?? 'totp');
            $result = $settingsController->enableTwoFactor($_SESSION['user_id'], $method);

            if ($method === 'totp') {
                $_SESSION['2fa_setup_secret'] = $result['secret'];
                $_SESSION['2fa_setup_qr'] = $result['qr_code_url'];
                $message = 'Two-factor authentication setup started. Scan the QR code with your authenticator app.';
            }

            $activeTab = '2fa';
        }
        elseif ($action === 'disable_2fa') {
            if ($settingsController->disableTwoFactor($_SESSION['user_id'])) {
                $message = 'Two-factor authentication has been disabled';
                $settings = $settingsController->getSettings($_SESSION['user_id']);
                $activeTab = '2fa';
            }
        }
    } catch (Exception $e) {
        $error = 'Error: ' . $e->getMessage();
    }
}

// Page configuration
$page = [
    'title' => 'Security Settings',
    'breadcrumbs' => [
        'Home' => '/admin/',
        'Settings' => '/admin/settings/',
        'Security' => null
    ],
    'icon' => 'fas fa-shield-alt'
];

// Build content
$content = <<<HTML
<div class="security-container">
    <div class="row">
        <!-- Left Navigation -->
        <div class="col-md-3 mb-4 mb-md-0">
            <div class="list-group sticky-top">
                <a href="?tab=password" class="list-group-item list-group-item-action {($activeTab === 'password' ? 'active' : '')}">
                    <i class="fas fa-key"></i> Change Password
                </a>
                <a href="?tab=2fa" class="list-group-item list-group-item-action {($activeTab === '2fa' ? 'active' : '')}">
                    <i class="fas fa-lock"></i> Two-Factor Auth
                </a>
                <a href="?tab=blocked" class="list-group-item list-group-item-action {($activeTab === 'blocked' ? 'active' : '')}">
                    <i class="fas fa-ban"></i> Blocked & Reported <span class="badge bg-danger">NEW</span>
                </a>
                <a href="?tab=sessions" class="list-group-item list-group-item-action {($activeTab === 'sessions' ? 'active' : '')}">
                    <i class="fas fa-laptop"></i> Active Sessions
                </a>
                <a href="?tab=login" class="list-group-item list-group-item-action {($activeTab === 'login' ? 'active' : '')}">
                    <i class="fas fa-history"></i> Login History
                </a>
            </div>
        </div>

        <!-- Right Content -->
        <div class="col-md-9">
            HTML;

// Messages
if ($message) {
    $content .= <<<HTML
            <div class="alert alert-success alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-check-circle"></i> {$message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            HTML;
}

if ($error) {
    $content .= <<<HTML
            <div class="alert alert-danger alert-dismissible fade show mb-4" role="alert">
                <i class="fas fa-exclamation-circle"></i> {$error}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            HTML;
}

// PASSWORD TAB
if ($activeTab === 'password') {
    $content .= <<<HTML
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-key"></i> Change Password</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <input type="hidden" name="action" value="change_password">

                        <div class="mb-3">
                            <label for="currentPassword" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="currentPassword"
                                   name="current_password" required>
                            <small class="form-text text-muted">Enter your current password for verification</small>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <label for="newPassword" class="form-label">New Password</label>
                                <input type="password" class="form-control" id="newPassword"
                                       name="new_password" required
                                       oninput="checkPasswordStrength(this.value)">
                                <small class="form-text text-muted">At least 8 characters with uppercase, numbers, and symbols</small>
                            </div>

                            <div class="col-md-6">
                                <label for="confirmPassword" class="form-label">Confirm Password</label>
                                <input type="password" class="form-control" id="confirmPassword"
                                       name="confirm_password" required>
                            </div>
                        </div>

                        <div id="strengthMeter" style="display: none;">
                            <label class="form-label">Password Strength</label>
                            <div class="progress mb-3">
                                <div id="strengthBar" class="progress-bar" role="progressbar"
                                     style="width: 0%;" aria-valuenow="0" aria-valuemin="0" aria-valuemax="100"></div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="alert alert-info mb-4">
                            <h6><i class="fas fa-info-circle"></i> Password Requirements</h6>
                            <ul class="mb-0 ms-3">
                                <li>Minimum 8 characters</li>
                                <li>At least one uppercase letter (A-Z)</li>
                                <li>At least one number (0-9)</li>
                                <li>At least one special character (!@#\$%^&*)</li>
                                <li>Cannot be same as last 5 passwords</li>
                            </ul>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                            <button type="reset" class="btn btn-outline-secondary">
                                <i class="fas fa-redo"></i> Clear
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Change Password
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            HTML;
}

// 2FA TAB
elseif ($activeTab === '2fa') {
    $content .= <<<HTML
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-lock"></i> Two-Factor Authentication</h5>
                </div>
                <div class="card-body">
                    <div class="alert alert-info mb-4">
                        <i class="fas fa-info-circle"></i>
                        Two-factor authentication is currently <strong>{($settings['two_factor_enabled'] ? 'ENABLED' : 'DISABLED')}</strong>
                    </div>

                    HTML;

    if (!$settings['two_factor_enabled']) {
        $content .= <<<HTML
                    <p>Add an extra layer of security to your account with two-factor authentication. When enabled, you'll need to provide a code from your authenticator app or phone when logging in.</p>

                    <h6 class="mb-3">Choose 2FA Method:</h6>

                    <form method="POST" class="mb-4">
                        <input type="hidden" name="action" value="enable_2fa">

                        <div class="row g-3 mb-4">
                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="2fa_method"
                                           value="totp" id="method_totp" checked>
                                    <label class="form-check-label" for="method_totp">
                                        <strong>Authenticator App</strong>
                                        <br><small class="text-muted">Use an app like Google Authenticator or Authy</small>
                                    </label>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="2fa_method"
                                           value="sms" id="method_sms" disabled>
                                    <label class="form-check-label" for="method_sms">
                                        <strong>SMS Text Message</strong>
                                        <br><small class="text-muted">Receive codes via text (Coming soon)</small>
                                    </label>
                                </div>
                            </div>
                        </div>

                        <div class="d-grid gap-2 d-md-flex justify-content-md-start">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Enable 2FA
                            </button>
                        </div>
                    </form>

                    HTML;

        // Show QR code if in setup process
        if (!empty($_SESSION['2fa_setup_qr'])) {
            $content .= <<<HTML
                    <hr class="my-4">

                    <h6 class="mb-3">Scan QR Code</h6>
                    <div class="text-center mb-3">
                        <img src="{$_SESSION['2fa_setup_qr']}" alt="2FA QR Code" style="max-width: 300px;">
                    </div>

                    <p class="text-center text-muted">Or enter this code manually:</p>
                    <div class="text-center mb-4">
                        <code style="font-size: 1.1rem; letter-spacing: 3px; word-break: break-all;">
                            {$_SESSION['2fa_setup_secret']}
                        </code>
                    </div>

                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Save these backup codes</strong> in a safe place. You can use them to regain access if you lose your authenticator device.
                    </div>

                    HTML;
        }
    } else {
        $content .= <<<HTML
                    <div class="alert alert-success mb-4">
                        <i class="fas fa-check-circle"></i>
                        <strong>Two-factor authentication is active</strong> and protecting your account.
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <div class="card border">
                                <div class="card-body">
                                    <h6><i class="fas fa-lock"></i> Current Method</h6>
                                    <p class="mb-0"><strong>Authenticator App (TOTP)</strong></p>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="card border">
                                <div class="card-body">
                                    <h6><i class="fas fa-shield-alt"></i> Backup Codes</h6>
                                    <p class="mb-0"><strong>5 codes remaining</strong></p>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="d-grid gap-2 d-md-flex gap-md-2">
                        <button class="btn btn-outline-primary flex-grow-1">
                            <i class="fas fa-redo"></i> Regenerate Backup Codes
                        </button>
                        <form method="POST" class="flex-grow-1">
                            <input type="hidden" name="action" value="disable_2fa">
                            <button type="submit" class="btn btn-outline-danger w-100"
                                    onclick="return confirm('Are you sure you want to disable 2FA?')">
                                <i class="fas fa-times"></i> Disable 2FA
                            </button>
                        </form>
                    </div>

                    HTML;
    }

    $content .= <<<HTML
                </div>
            </div>
            HTML;
}

// BLOCKED & REPORTED TAB (NEW)
elseif ($activeTab === 'blocked') {
    $content .= <<<HTML
            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-ban"></i> Blocked Users <span class="badge bg-danger">0</span></h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">Users you've blocked cannot message you or see your online status</p>

                    <div class="table-responsive mb-4">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Blocked Since</th>
                                    <th>Reason</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="blockedUsersList">
                                <tr>
                                    <td colspan="4" class="text-center text-muted py-4">
                                        <i class="fas fa-shield-alt"></i> No blocked users. Your inbox is safe!
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="alert alert-info small">
                        <i class="fas fa-info-circle"></i>
                        <strong>Privacy:</strong> Blocked users won't be notified that they're blocked. They simply won't be able to interact with you.
                    </div>
                </div>
            </div>

            <div class="card mb-4">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-flag"></i> Reported Users <span class="badge bg-warning">0</span></h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">Users you've reported to our support team for violations</p>

                    <div class="table-responsive mb-4">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>User</th>
                                    <th>Reported On</th>
                                    <th>Reason</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="reportedUsersList">
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">
                                        <i class="fas fa-check-circle"></i> No reports submitted yet
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="alert alert-warning small">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Important:</strong> Reports are handled by our moderation team. Use responsibly for genuine policy violations only.
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-laptop"></i> Device Trust Management</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">Manage which devices are trusted to access your account</p>

                    <h6 class="mb-3">Trusted Devices</h6>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Device</th>
                                    <th>Browser/OS</th>
                                    <th>Last Used</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td><i class="fas fa-laptop"></i> Windows Desktop</td>
                                    <td>Chrome 120 on Windows 11</td>
                                    <td>Today</td>
                                    <td><span class="badge bg-success">Trusted</span></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-trash"></i> Untrust
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td><i class="fas fa-mobile-alt"></i> iPhone</td>
                                    <td>Safari on iOS 18</td>
                                    <td>2 hours ago</td>
                                    <td><span class="badge bg-success">Trusted</span></td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-trash"></i> Untrust
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" id="autoTrust" name="auto_trust_devices">
                        <label class="form-check-label" for="autoTrust">
                            <strong>Auto-trust new devices</strong>
                            <br><small class="text-muted">Automatically trust devices for 30 days on first login</small>
                        </label>
                    </div>

                    <hr class="my-4">

                    <h6 class="mb-3">Location-Based Security</h6>
                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" id="locationAlerts" name="location_alerts" checked>
                        <label class="form-check-label" for="locationAlerts">
                            <strong>Alert on unusual locations</strong>
                            <br><small class="text-muted">Get notified when login occurs from unusual locations</small>
                        </label>
                    </div>

                    <div class="alert alert-success small">
                        <i class="fas fa-check-circle"></i>
                        <strong>Current Location:</strong> New Zealand (Your regular location)
                    </div>

                    <hr class="my-4">

                    <h6 class="mb-3">IP Address Management</h6>
                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" id="ipAllowlist" name="ip_allowlist_enabled">
                        <label class="form-check-label" for="ipAllowlist">
                            <strong>Enable IP Allowlist</strong>
                            <br><small class="text-muted">Only allow login from specific IP addresses (advanced)</small>
                        </label>
                    </div>

                    <div id="ipAllowlistSettings" style="display: none; padding-left: 2rem;">
                        <textarea class="form-control mb-3" id="ipAddresses" placeholder="One IP address per line&#10;Example:&#10;192.168.1.1&#10;203.0.113.45" rows="5"></textarea>
                        <button class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-plus"></i> Add Current IP
                        </button>
                    </div>

                    <hr class="my-4">

                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Save Device Settings
                        </button>
                    </div>
                </div>
            </div>

            <script>
            document.getElementById('ipAllowlist').addEventListener('change', function() {
                const settings = document.getElementById('ipAllowlistSettings');
                settings.style.display = this.checked ? 'block' : 'none';
            });
            </script>
            HTML;
}

// SESSIONS TAB
elseif ($activeTab === 'sessions') {
    $content .= <<<HTML
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-laptop"></i> Active Sessions</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">Manage your active sessions across devices</p>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Device</th>
                                    <th>Browser</th>
                                    <th>IP Address</th>
                                    <th>Last Active</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <i class="fas fa-laptop"></i> Windows Desktop
                                    </td>
                                    <td>Chrome 120</td>
                                    <td>192.168.1.1</td>
                                    <td>Just now</td>
                                    <td>
                                        <span class="badge bg-success">Current</span>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <i class="fas fa-mobile-alt"></i> iPhone
                                    </td>
                                    <td>Safari iOS</td>
                                    <td>203.0.113.45</td>
                                    <td>2 hours ago</td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-sign-out-alt"></i> Sign Out
                                        </button>
                                    </td>
                                </tr>
                                <tr>
                                    <td>
                                        <i class="fas fa-tablet-alt"></i> iPad
                                    </td>
                                    <td>Safari iOS</td>
                                    <td>198.51.100.1</td>
                                    <td>1 day ago</td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-danger">
                                            <i class="fas fa-sign-out-alt"></i> Sign Out
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <hr class="my-4">

                    <div class="d-grid">
                        <button class="btn btn-outline-danger"
                                onclick="return confirm('Sign out from all other sessions? You will only remain signed in on this device.')">
                            <i class="fas fa-sign-out-alt"></i> Sign Out From All Other Sessions
                        </button>
                    </div>
                </div>
            </div>
            HTML;
}

// LOGIN HISTORY TAB
elseif ($activeTab === 'login') {
    $content .= <<<HTML
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0"><i class="fas fa-history"></i> Login History</h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">Recent login activity on your account</p>

                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Date & Time</th>
                                    <th>Device</th>
                                    <th>Browser</th>
                                    <th>IP Address</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Today, 10:30 AM</td>
                                    <td><i class="fas fa-laptop"></i> Windows</td>
                                    <td>Chrome 120</td>
                                    <td>192.168.1.1</td>
                                    <td><span class="badge bg-success">Success</span></td>
                                </tr>
                                <tr>
                                    <td>Today, 9:15 AM</td>
                                    <td><i class="fas fa-mobile-alt"></i> iPhone</td>
                                    <td>Safari</td>
                                    <td>203.0.113.45</td>
                                    <td><span class="badge bg-success">Success</span></td>
                                </tr>
                                <tr>
                                    <td>Yesterday, 11:45 PM</td>
                                    <td><i class="fas fa-laptop"></i> Windows</td>
                                    <td>Firefox</td>
                                    <td>198.51.100.1</td>
                                    <td><span class="badge bg-danger">Failed</span></td>
                                </tr>
                                <tr>
                                    <td>Yesterday, 8:20 PM</td>
                                    <td><i class="fas fa-laptop"></i> Linux</td>
                                    <td>Chrome</td>
                                    <td>192.0.2.1</td>
                                    <td><span class="badge bg-success">Success</span></td>
                                </tr>
                                <tr>
                                    <td>3 days ago, 2:10 PM</td>
                                    <td><i class="fas fa-tablet-alt"></i> iPad</td>
                                    <td>Safari</td>
                                    <td>203.0.113.89</td>
                                    <td><span class="badge bg-success">Success</span></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            HTML;
}

$content .= <<<HTML
        </div>
    </div>
</div>

<style>
    .list-group-item.active {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-color: #667eea;
    }

    .card {
        border: 1px solid #eee;
        box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
    }

    .card-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        border: none;
    }

    .card-title {
        color: white;
    }

    .progress-bar {
        background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
    }
</style>

<script>
function checkPasswordStrength(password) {
    const strengthMeter = document.getElementById('strengthMeter');
    const strengthBar = document.getElementById('strengthBar');

    if (password.length === 0) {
        strengthMeter.style.display = 'none';
        return;
    }

    strengthMeter.style.display = 'block';

    let strength = 0;

    // Length check
    if (password.length >= 8) strength += 20;
    if (password.length >= 12) strength += 10;

    // Uppercase check
    if (/[A-Z]/.test(password)) strength += 20;

    // Number check
    if (/[0-9]/.test(password)) strength += 20;

    // Special character check
    if (/[!@#\$%^&*()_+=\[\]{};:\'",.<>?\/\\|`~-]/.test(password)) strength += 20;

    // Update progress bar
    strengthBar.style.width = strength + '%';
    strengthBar.setAttribute('aria-valuenow', strength);

    // Color coding
    if (strength < 40) {
        strengthBar.className = 'progress-bar bg-danger';
    } else if (strength < 70) {
        strengthBar.className = 'progress-bar bg-warning';
    } else {
        strengthBar.className = 'progress-bar bg-success';
    }
}
</script>
HTML;

// Render with template
renderMainLayout($page, $content);
?>
