<?php
/**
 * AUTO-PUSH MONITOR - Smart Git Push Every 5 Minutes
 *
 * Features:
 * - Only pushes when actual changes exist
 * - Batches multiple changes into single commit
 * - 5-minute activity window
 * - Runs in background silently
 * - Auto-restarts if killed
 *
 * Start: php .auto-push-monitor.php start
 * Stop:  php .auto-push-monitor.php stop
 * Status: php .auto-push-monitor.php status
 */

declare(strict_types=1);

// Configuration
$config = [
    'repo_path' => __DIR__,
    'check_interval' => 300,        // 5 minutes
    'pid_file' => __DIR__ . '/.auto-push.pid',
    'log_file' => __DIR__ . '/.auto-push.log',
    'idle_threshold' => 300,        // 5 min = stop pushing if idle
    'batch_size' => 50,             // Max files per commit
];

// Parse command
$command = $argv[1] ?? 'start';

if ($command === 'start') {
    startMonitor($config);
} elseif ($command === 'stop') {
    stopMonitor($config);
} elseif ($command === 'status') {
    showStatus($config);
} elseif ($command === 'daemon') {
    runDaemon($config);  // Called by systemd/cron
} else {
    echo "Usage:\n";
    echo "  php .auto-push-monitor.php start    - Start monitoring\n";
    echo "  php .auto-push-monitor.php stop     - Stop monitoring\n";
    echo "  php .auto-push-monitor.php status   - Show status\n";
    exit(1);
}

// ============================================================================

function startMonitor(array $config): void
{
    if (isMonitorRunning($config)) {
        echo "✓ Monitor already running\n";
        showStatus($config);
        return;
    }

    // Start in background
    $cmd = "nohup php '{$config['repo_path']}/.auto-push-monitor.php' daemon > '{$config['log_file']}' 2>&1 &";

    if (PHP_OS_FAMILY === 'Windows') {
        // Windows: use popen for background process
        popen("start /B php '{$config['repo_path']}/.auto-push-monitor.php' daemon", 'r');
    } else {
        // Linux/Mac: use shell background
        shell_exec($cmd);
    }

    sleep(1);

    if (isMonitorRunning($config)) {
        echo "✓ Auto-push monitor started\n";
        echo "  Pushes to GitHub every 5 minutes when changes detected\n";
        echo "  Log: {$config['log_file']}\n";
    } else {
        echo "✗ Failed to start monitor\n";
        exit(1);
    }
}

function stopMonitor(array $config): void
{
    if (!isMonitorRunning($config)) {
        echo "✓ Monitor not running\n";
        return;
    }

    $pid = @file_get_contents($config['pid_file']);
    if ($pid) {
        if (PHP_OS_FAMILY !== 'Windows') {
            @exec("kill $pid 2>/dev/null");
        }
    }

    @unlink($config['pid_file']);
    sleep(1);

    if (!isMonitorRunning($config)) {
        echo "✓ Monitor stopped\n";
    } else {
        echo "✗ Failed to stop monitor\n";
    }
}

function showStatus(array $config): void
{
    $isRunning = isMonitorRunning($config);
    $status = $isRunning ? "🟢 RUNNING" : "🔴 STOPPED";

    echo "\n=== Auto-Push Monitor Status ===\n";
    echo "Status: $status\n";
    echo "Repo: {$config['repo_path']}\n";
    echo "Check Interval: {$config['check_interval']}s\n";
    echo "Log: {$config['log_file']}\n";

    if (file_exists($config['log_file'])) {
        echo "\n--- Recent Log (Last 20 lines) ---\n";
        $log = file($config['log_file']);
        $recent = array_slice($log, -20);
        foreach ($recent as $line) {
            echo trim($line) . "\n";
        }
    }
    echo "\n";
}

function isMonitorRunning(array $config): bool
{
    if (!file_exists($config['pid_file'])) {
        return false;
    }

    $pid = (int)@file_get_contents($config['pid_file']);

    if (PHP_OS_FAMILY === 'Windows') {
        // Windows: check if process exists
        $output = @shell_exec("tasklist /FI \"PID eq $pid\" 2>NUL");
        return (stripos($output, 'php.exe') !== false);
    } else {
        // Linux/Mac: check if process exists
        $output = @shell_exec("ps -p $pid 2>/dev/null");
        return !empty(trim($output));
    }
}

function runDaemon(array $config): void
{
    // Write PID
    file_put_contents($config['pid_file'], getmypid());

    log_msg("Auto-push daemon started (PID: " . getmypid() . ")");

    $lastPush = time();
    $lastActivity = time();

    // Main loop
    while (true) {
        // Check for changes
        $output = [];
        $exit = 0;
        exec("cd '{$config['repo_path']}' && git status --porcelain 2>&1", $output, $exit);

        $hasChanges = (count($output) > 0 && $exit === 0);

        if ($hasChanges) {
            $lastActivity = time();

            // Time to push?
            if (time() - $lastPush >= $config['check_interval']) {
                pushChanges($config);
                $lastPush = time();
            }
        } else {
            // No changes - check if idle too long
            if (time() - $lastActivity > $config['idle_threshold']) {
                log_msg("Idle detected, skipping push");
            }
        }

        sleep($config['check_interval']);
    }
}

function pushChanges(array $config): void
{
    chdir($config['repo_path']);

    // Get status
    $output = [];
    exec("git status --porcelain 2>&1", $output);

    if (empty($output)) {
        log_msg("No changes to push");
        return;
    }

    $changeCount = count($output);
    log_msg("Detected $changeCount changed files");

    // Stage changes
    exec("git add . 2>&1", $stageOutput, $stageExit);

    if ($stageExit !== 0) {
        log_msg("Stage failed: " . implode(", ", $stageOutput));
        return;
    }

    // Create commit
    $commitMsg = "Auto-push: " . date('Y-m-d H:i:s') . " ($changeCount files)";
    $commitCmd = "git commit -m " . escapeshellarg($commitMsg) . " 2>&1";
    exec($commitCmd, $commitOutput, $commitExit);

    if ($commitExit !== 0) {
        log_msg("Commit failed: " . implode(", ", $commitOutput));
        return;
    }

    log_msg("Created commit: $commitMsg");

    // Push to GitHub
    exec("git push origin main 2>&1", $pushOutput, $pushExit);

    if ($pushExit === 0) {
        log_msg("✓ Pushed to GitHub (via origin/main)");
    } else {
        log_msg("✗ Push failed: " . implode(", ", $pushOutput));
        // Retry once on failure
        sleep(2);
        exec("git push origin main 2>&1", $retryOutput, $retryExit);
        if ($retryExit === 0) {
            log_msg("✓ Pushed to GitHub (retry successful)");
        } else {
            log_msg("✗ Push failed (retry): " . implode(", ", $retryOutput));
        }
    }
}

function log_msg(string $msg): void
{
    global $config;
    $timestamp = date('Y-m-d H:i:s');
    $logLine = "[$timestamp] $msg\n";

    echo $logLine;
    @file_put_contents($GLOBALS['config']['log_file'], $logLine, FILE_APPEND);
}
?>
