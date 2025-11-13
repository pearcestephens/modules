#!/usr/bin/env php
<?php
declare(strict_types=1);

// Consignments: Health Check CLI
if (php_sapi_name() !== 'cli') { exit(1); }

$exitCode = 0;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../../../app.php';

use PDO; use PDOException;

function out(string $level, string $msg, array $ctx = []): void {
    $ts = date('Y-m-d H:i:s');
    $ctxStr = $ctx ? ' ' . json_encode($ctx) : '';
    echo "[$ts] [$level] $msg$ctxStr\n";
}

try {
    // Expect CIS Base DB to expose PDO via container or helper
    $pdo = new PDO(
        sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', getenv('DB_HOST') ?: '127.0.0.1', getenv('DB_NAME') ?: getenv('MYSQL_DATABASE') ?: ''),
        getenv('DB_USER') ?: getenv('MYSQL_USER') ?: '',
        getenv('DB_PASS') ?: getenv('MYSQL_PASSWORD') ?: '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    out('INFO', 'DB connection OK');
} catch (PDOException $e) {
    out('ERROR', 'DB connection failed', ['error' => $e->getMessage()]);
    exit(2);
}

// Verify critical tables exist
$required = ['queue_jobs', 'queue_jobs_dlq', 'sync_cursors', 'queue_consignments', 'webhook_events'];
foreach ($required as $tbl) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?");
    $stmt->execute([$tbl]);
    if ((int)$stmt->fetchColumn() === 0) {
        out('ERROR', 'Missing required table', ['table' => $tbl]);
        $exitCode = 3;
    } else {
        out('INFO', 'Table present', ['table' => $tbl]);
    }
}

// Check stuck jobs (>5 min without heartbeat)
$stuck = 0;
try {
    $q = $pdo->query("SELECT COUNT(*) FROM queue_jobs WHERE status='processing' AND (heartbeat_at IS NULL OR heartbeat_at < DATE_SUB(NOW(), INTERVAL 5 MINUTE))");
    $stuck = (int)$q->fetchColumn();
    if ($stuck > 0) {
        out('WARNING', 'Stuck jobs detected', ['count' => $stuck]);
        $exitCode = max($exitCode, 1);
    } else {
        out('INFO', 'No stuck jobs');
    }
} catch (Throwable $e) {
    out('WARNING', 'Unable to evaluate stuck jobs', ['error' => $e->getMessage()]);
}

// Webhook failures in last 24h
try {
    $q = $pdo->query("SELECT COUNT(*) FROM webhook_events WHERE status='failed' AND received_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)");
    $failed = (int)$q->fetchColumn();
    if ($failed > 0) {
        out('WARNING', 'Recent webhook failures', ['count' => $failed]);
        $exitCode = max($exitCode, 1);
    } else {
        out('INFO', 'No webhook failures in last 24h');
    }
} catch (Throwable $e) {
    out('WARNING', 'Unable to query webhook events', ['error' => $e->getMessage()]);
}

// Sync cursor progress sanity
try {
    $q = $pdo->query("SELECT cursor_type, last_processed_id, TIMESTAMPDIFF(MINUTE, last_processed_at, NOW()) AS mins_since FROM sync_cursors");
    foreach ($q->fetchAll(PDO::FETCH_ASSOC) as $row) {
        out('INFO', 'Sync cursor', $row);
        if ($row['mins_since'] !== null && (int)$row['mins_since'] > 60) {
            out('WARNING', 'Cursor stale', $row);
            $exitCode = max($exitCode, 1);
        }
    }
} catch (Throwable $e) {
    out('WARNING', 'Unable to read sync_cursors', ['error' => $e->getMessage()]);
}

exit($exitCode);
