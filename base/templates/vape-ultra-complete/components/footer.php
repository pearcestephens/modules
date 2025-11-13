<?php
/**
 * Footer Component
 *
 * Status bar with system info
 */

// Get system stats
$version = '2.00';
$phpVersion = PHP_VERSION;
$serverLoad = sys_getloadavg();

// Get actual server memory usage
$memoryUsage = 0;
$memoryTotal = 0;
$memoryPercent = 0;

if (function_exists('shell_exec')) {
    // Try to get real server memory stats
    $memInfo = @shell_exec('free -m | grep Mem');
    if ($memInfo) {
        preg_match_all('/\d+/', $memInfo, $matches);
        if (isset($matches[0][0]) && isset($matches[0][1])) {
            $memoryTotal = (int)$matches[0][0]; // Total MB
            $memoryUsage = (int)$matches[0][1];  // Used MB
            $memoryPercent = round(($memoryUsage / $memoryTotal) * 100);
        }
    }
}

// Fallback to PHP memory if shell_exec not available
if ($memoryTotal == 0) {
    $memoryUsage = round(memory_get_usage(true) / 1024 / 1024);
    $memoryTotal = 0;
    $memoryPercent = 0;
}

// Get MySQL connection status and info
$dbStatus = 'Disconnected';
$dbColor = 'text-danger';
$dbInfo = '';

try {
    if (isset($GLOBALS['conn']) && $GLOBALS['conn']) {
        // Get MySQL version and connection info
        if ($GLOBALS['conn'] instanceof mysqli) {
            $result = $GLOBALS['conn']->query("SELECT VERSION() as version, DATABASE() as db");
            if ($result && $row = $result->fetch_assoc()) {
                $mysqlVersion = explode('-', $row['version'])[0]; // Get version without suffix
                $dbName = $row['db'] ?: 'N/A';
                $dbStatus = 'Connected';
                $dbColor = 'text-success';
                $dbInfo = "v{$mysqlVersion} • {$dbName}";
            }
        } else {
            $dbStatus = 'Connected';
            $dbColor = 'text-success';
        }
    }
} catch (Exception $e) {
    $dbStatus = 'Error';
    $dbColor = 'text-danger';
}

// Get actual user name from database if session doesn't have it
$userName = 'Guest';
if (isset($_SESSION['user_id']) && $_SESSION['user_id'] > 0) {
    // Check if name is in session
    if (!empty($_SESSION['first_name'])) {
        $firstName = $_SESSION['first_name'];
        $lastInitial = !empty($_SESSION['last_name']) ? substr($_SESSION['last_name'], 0, 1) . '.' : '';
        $userName = $firstName . ' ' . $lastInitial;
    } else {
        // Fetch from database
        try {
            if (isset($GLOBALS['conn']) && $GLOBALS['conn']) {
                $userId = (int)$_SESSION['user_id'];
                $result = $GLOBALS['conn']->query("SELECT first_name, last_name FROM staff_accounts WHERE id = {$userId} LIMIT 1");
                if ($result && $row = $result->fetch_assoc()) {
                    $firstName = $row['first_name'];
                    $lastInitial = !empty($row['last_name']) ? substr($row['last_name'], 0, 1) . '.' : '';
                    $userName = $firstName . ' ' . $lastInitial;
                }
            }
        } catch (Exception $e) {
            $userName = 'User #' . $_SESSION['user_id'];
        }
    }
}
?>

<div class="footer-left">
    <div class="footer-item system-status-trigger" id="systemStatusBtn">
        <span class="status-dot status-online"></span>
        <span class="footer-label">System</span>
        <span class="footer-value">Online</span>
        <svg viewBox="0 0 16 16" fill="none" style="width: 10px; height: 10px; margin-left: 4px; opacity: 0.5;">
            <path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"></path>
        </svg>
    </div>
</div>

<!-- System Stats Popover -->
<div class="system-stats-popover" id="systemStatsPopover">
    <div class="popover-header">
        <span class="status-dot status-online"></span>
        <strong>System Information</strong>
    </div>
    <div class="popover-body">
        <div class="stat-row">
            <div class="stat-icon">
                <svg viewBox="0 0 16 16" fill="none">
                    <circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5"></circle>
                    <path d="M8 4v4l2 2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                </svg>
            </div>
            <div class="stat-info">
                <div class="stat-label">PHP Version</div>
                <div class="stat-value"><?= $phpVersion ?></div>
            </div>
        </div>

        <div class="stat-row">
            <div class="stat-icon">
                <svg viewBox="0 0 16 16" fill="none">
                    <rect x="2" y="4" width="12" height="8" rx="1" stroke="currentColor" stroke-width="1.5"></rect>
                    <path d="M2 7h12M5 4V3M11 4V3" stroke="currentColor" stroke-width="1.5"></path>
                </svg>
            </div>
            <div class="stat-info">
                <div class="stat-label">Server</div>
                <div class="stat-value">staff.vapeshed.co.nz</div>
            </div>
        </div>

        <div class="stat-row">
            <div class="stat-icon">
                <svg viewBox="0 0 16 16" fill="none">
                    <path d="M2 8h4l2-4 2 8 2-4h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
            </div>
            <div class="stat-info">
                <div class="stat-label">Server Load</div>
                <div class="stat-value"><?= number_format($serverLoad[0], 2) ?> / <?= number_format($serverLoad[1], 2) ?> / <?= number_format($serverLoad[2], 2) ?></div>
                <div class="stat-hint">1min / 5min / 15min average</div>
            </div>
        </div>

        <div class="stat-row">
            <div class="stat-icon">
                <svg viewBox="0 0 16 16" fill="none">
                    <rect x="3" y="2" width="10" height="12" rx="1" stroke="currentColor" stroke-width="1.5"></rect>
                    <path d="M5 5h6M5 8h6M5 11h4" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"></path>
                </svg>
            </div>
            <div class="stat-info">
                <div class="stat-label">Memory Usage</div>
                <?php if ($memoryTotal > 0): ?>
                    <div class="stat-value"><?= number_format($memoryUsage, 0) ?>MB / <?= number_format($memoryTotal, 0) ?>MB</div>
                    <div class="progress-bar">
                        <div class="progress-fill" style="width: <?= $memoryPercent ?>%"></div>
                    </div>
                    <div class="stat-hint"><?= $memoryPercent ?>% utilized</div>
                <?php else: ?>
                    <div class="stat-value"><?= number_format($memoryUsage, 0) ?>MB (PHP script)</div>
                <?php endif; ?>
            </div>
        </div>

        <div class="stat-row">
            <div class="stat-icon">
                <svg viewBox="0 0 16 16" fill="none">
                    <circle cx="8" cy="8" r="6" stroke="currentColor" stroke-width="1.5"></circle>
                    <path d="M8 12V8M8 8l3-3M8 8L5 5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path>
                </svg>
            </div>
            <div class="stat-info">
                <div class="stat-label">MySQL Database</div>
                <div class="stat-value <?= $dbColor ?>"><?= $dbStatus ?></div>
                <?php if (!empty($dbInfo)): ?>
                    <div class="stat-hint"><?= $dbInfo ?></div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="footer-center"></div>

<div class="footer-right">
    <div class="footer-item">
        <svg viewBox="0 0 16 16" fill="none">
            <circle cx="8" cy="6" r="3" stroke="currentColor" stroke-width="1.5"></circle>
            <path d="M3 14a5 5 0 0 1 10 0" stroke="currentColor" stroke-width="1.5"></path>
        </svg>
        <span class="footer-label">User:</span>
        <span class="footer-value"><?= htmlspecialchars($userName) ?></span>
    </div>
    <div class="footer-separator"></div>
    <div class="footer-item">
        <svg viewBox="0 0 16 16" fill="none">
            <rect x="2" y="2" width="12" height="12" rx="2" stroke="currentColor" stroke-width="1.5"></rect>
            <path d="M2 6h12M6 2v4M10 2v4" stroke="currentColor" stroke-width="1.5"></path>
        </svg>
        <span class="footer-value"><?= date('M d, Y H:i') ?> NZDT</span>
    </div>
    <div class="footer-separator"></div>
    <div class="footer-item">
        <span class="footer-label">CIS Ultra</span>
        <span class="footer-value">v<?= $version ?></span>
    </div>
</div>

<style>
.footer-left,
.footer-center,
.footer-right {
    display: flex;
    align-items: center;
    gap: 16px;
}

.footer-center {
    flex: 1;
    justify-content: center;
}

.footer-item {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 13px;
}

.footer-item svg {
    width: 14px;
    height: 14px;
    color: #6c757d;
    flex-shrink: 0;
}

.footer-label {
    color: #6c757d;
    font-weight: 500;
}

.footer-value {
    color: #2c3e50;
    font-weight: 600;
}

.footer-separator {
    width: 1px;
    height: 16px;
    background: #dee2e6;
}

.status-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    flex-shrink: 0;
}

.status-online {
    background: #28a745;
    box-shadow: 0 0 0 2px rgba(40, 167, 69, 0.2);
}

@media (max-width: 1200px) {
    .footer-left .footer-item:last-child,
    .footer-center .footer-item:last-child {
        display: none;
    }
}

@media (max-width: 768px) {
    .footer-center {
        display: none;
    }
}

/* System Status Popover */
.system-status-trigger {
    cursor: pointer;
    transition: all 0.2s ease;
    position: relative;
}

.system-status-trigger:hover {
    background: rgba(0,0,0,0.03);
    border-radius: 6px;
    padding: 4px 8px;
    margin: -4px -8px;
}

.system-stats-popover {
    position: fixed;
    bottom: 50px;
    left: 20px;
    width: 280px;
    background: #ffffff;
    border-radius: 10px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.12), 0 2px 8px rgba(0,0,0,0.08);
    opacity: 0;
    visibility: hidden;
    transform: translateY(10px);
    transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    z-index: 10000;
    border: 1px solid rgba(0,0,0,0.08);
}

.system-stats-popover.show {
    opacity: 1;
    visibility: visible;
    transform: translateY(0);
}

.popover-header {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 12px 16px;
    border-bottom: 1px solid #e9ecef;
    font-size: 13px;
}

.popover-body {
    padding: 8px 16px 12px;
}

.stat-row {
    display: flex;
    gap: 10px;
    padding: 10px 0;
    border-bottom: 1px solid #f5f6f8;
}

.stat-row:last-child {
    border-bottom: none;
}

.stat-icon {
    width: 28px;
    height: 28px;
    border-radius: 6px;
    background: #f5f6f8;
    display: flex;
    align-items: center;
    justify-content: center;
    flex-shrink: 0;
}

.stat-icon svg {
    width: 14px;
    height: 14px;
    color: #6c757d;
}

.stat-info {
    flex: 1;
}

.stat-label {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    color: #6c757d;
    letter-spacing: 0.5px;
    margin-bottom: 4px;
}

.stat-value {
    font-size: 12px;
    font-weight: 600;
    color: #2c3e50;
    font-family: 'Monaco', 'Courier New', monospace;
}

.stat-hint {
    font-size: 10px;
    color: #95a5a6;
    margin-top: 2px;
}

.progress-bar {
    width: 100%;
    height: 5px;
    background: #e9ecef;
    border-radius: 3px;
    overflow: hidden;
    margin-top: 4px;
    margin-bottom: 3px;
}

.progress-fill {
    height: 100%;
    background: linear-gradient(90deg, #28a745, #20c997);
    border-radius: 3px;
    transition: width 0.3s ease;
}

.text-success {
    color: #28a745 !important;
}

.text-danger {
    color: #dc3545 !important;
}
</style>

<script>
// System stats popover toggle
(function() {
    const trigger = document.getElementById('systemStatusBtn');
    const popover = document.getElementById('systemStatsPopover');

    if (!trigger || !popover) return;

    let isOpen = false;

    trigger.addEventListener('click', function(e) {
        e.stopPropagation();
        isOpen = !isOpen;
        if (isOpen) {
            popover.classList.add('show');
        } else {
            popover.classList.remove('show');
        }
    });

    // Close when clicking outside
    document.addEventListener('click', function(e) {
        if (isOpen && !popover.contains(e.target) && !trigger.contains(e.target)) {
            isOpen = false;
            popover.classList.remove('show');
        }
    });

    // Close on escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && isOpen) {
            isOpen = false;
            popover.classList.remove('show');
        }
    });
})();
</script>
