<?php declare(strict_types=1);
// Behavior Events Inspection Endpoint (consolidated)
// Shows last 200 cis_user_events for current session/user.
// Controlled by 'behavior_debug' feature flag.

use CIS\Base\Database;
use CIS\Base\Support\SessionGuard;
use CIS\Base\Support\Response;

require_once __DIR__ . '/../Database.php';
require_once __DIR__ . '/../Support/SessionGuard.php';
require_once __DIR__ . '/../Support/Response.php';

SessionGuard::ensureStarted();

// Load flags via helper if available
$flagsLoaderPath = dirname(__DIR__, 2) . '/config/feature-flags.php';
$flags = file_exists($flagsLoaderPath) ? (require $flagsLoaderPath) : [];
if (!is_array($flags)) $flags = [];

if (empty($flags['behavior_debug'])) {
    Response::error('behavior_debug_disabled', 'Feature flag behavior_debug is false', 403);
    return;
}

if (!isset($_SESSION['user_id'])) {
    Response::error('unauthorized', 'Session missing user_id', 401);
    return;
}

$userId = (int) $_SESSION['user_id'];
$sessionId = session_id();

try {
    $rows = Database::query(
        "SELECT id, event_type, page_url, occurred_at_ms, event_data, ip_address, created_at
         FROM cis_user_events WHERE session_id = ? OR user_id = ?
         ORDER BY occurred_at_ms DESC LIMIT 200",
        [$sessionId, $userId]
    );
} catch (Exception $e) {
    Response::error('query_failed', 'Unable to fetch events', 500, [
        'trace_id' => substr(sha1($e->getMessage() . microtime()), 0, 12)
    ]);
    return;
}

$format = ($_GET['format'] ?? '') === 'html' ? 'html' : 'json';
$accept = $_SERVER['HTTP_ACCEPT'] ?? '';
if ($format !== 'html' && str_contains($accept, 'text/html')) {
    $format = 'html';
}

if ($format === 'json') {
    Response::json([
        'session_id' => $sessionId,
        'user_id' => $userId,
        'count' => count($rows),
        'events' => array_map(function ($r) {
            $r['event_data'] = json_decode($r['event_data'], true);
            return $r;
        }, $rows)
    ]);
    return;
}

?><!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<title>Behavior Events Inspect</title>
<style>
body{font-family:system-ui,Arial,sans-serif;margin:20px;background:#111;color:#eee}
table{border-collapse:collapse;width:100%;font-size:13px}
th,td{border:1px solid #333;padding:4px 6px;vertical-align:top}
th{background:#222}
tr:nth-child(even){background:#1a1a1a}
code{font-family:monospace;font-size:12px;white-space:pre-wrap;word-break:break-word}
.meta{color:#999;font-size:12px;margin-bottom:10px}
</style>
</head>
<body>
<h1>Behavior Events (<?= count($rows) ?>)</h1>
<div class="meta">Session: <?= htmlspecialchars($sessionId) ?> | User #<?= $userId ?> | <a href="?format=json" style="color:#6cf">JSON</a></div>
<table>
<thead><tr><th>ID</th><th>Type</th><th>Page</th><th>Time(ms)</th><th>Data</th><th>IP</th><th>Created</th></tr></thead>
<tbody>
<?php foreach ($rows as $r): $data = json_decode($r['event_data'], true); ?>
<tr>
  <td><?= (int)$r['id'] ?></td>
  <td><?= htmlspecialchars($r['event_type']) ?></td>
  <td><?= htmlspecialchars($r['page_url'] ?? '') ?></td>
  <td><?= htmlspecialchars($r['occurred_at_ms']) ?></td>
  <td><code><?= htmlspecialchars(json_encode($data, JSON_UNESCAPED_UNICODE)) ?></code></td>
  <td><?= htmlspecialchars($r['ip_address'] ?? '') ?></td>
  <td><?= htmlspecialchars($r['created_at']) ?></td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</body>
</html>
