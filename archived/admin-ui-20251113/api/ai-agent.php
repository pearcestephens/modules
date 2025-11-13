<?php
/**
 * Admin UI - AI Agent Endpoint
 * Minimal, secure endpoint for admin users to request template edits.
 * - Requires session is_admin
 * - Requires POST with csrf_token matching session
 * - Basic rate limit per session (5 requests / minute)
 */
declare(strict_types=1);

session_start();

// Simple admin guard
if (empty($_SESSION['is_admin'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'forbidden']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
if ($method !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'method_not_allowed']);
    exit;
}

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$csrf = $body['csrf_token'] ?? '';
if (empty($csrf) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'invalid_csrf']);
    exit;
}

// Simple per-session rate limit: allow 5 per 60 seconds
$now = time();
$_SESSION['ai_agent_calls'] = $_SESSION['ai_agent_calls'] ?? [];
// remove old
$_SESSION['ai_agent_calls'] = array_filter($_SESSION['ai_agent_calls'], function($t) use ($now) { return ($now - $t) < 60; });
if (count($_SESSION['ai_agent_calls']) >= 5) {
    http_response_code(429);
    echo json_encode(['success' => false, 'error' => 'rate_limited']);
    exit;
}

// record call
$_SESSION['ai_agent_calls'][] = $now;

$action = $body['action'] ?? 'preview';
$templatePath = $body['template_path'] ?? '';
$instructions = $body['instructions'] ?? '';

// Validate inputs
if (empty($templatePath) || strpos($templatePath, '..') !== false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'invalid_template_path']);
    exit;
}

$fullPath = $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($templatePath, '/');
if (!file_exists($fullPath) || !is_writable($fullPath)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'template_not_writable']);
    exit;
}

// For safety: actions supported: preview (return proposed edit), apply (write file)
// Currently we implement a very small, server-side placeholder edit: prepend an audit comment including instructions.

$backupDir = $_SERVER['DOCUMENT_ROOT'] . '/private_html/backups';
if (!is_dir($backupDir)) {
    @mkdir($backupDir, 0755, true);
}

// read original
$original = file_get_contents($fullPath);
if ($original === false) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'read_error']);
    exit;
}

$audit = "<!-- AI-EDIT: user=" . ($_SESSION['user_name'] ?? 'admin') . " at " . date('c') . " -->\n";
$audit .= "<!-- INSTRUCTIONS: " . substr($instructions, 0, 1000) . " -->\n";

$proposed = $audit . $original;

if ($action === 'preview') {
    echo json_encode(['success' => true, 'preview' => $proposed]);
    exit;
}

if ($action === 'apply') {
    // create backup
    $bkFile = $backupDir . '/' . basename($fullPath) . '.' . time() . '.bak';
    @file_put_contents($bkFile, $original);

    $written = @file_put_contents($fullPath, $proposed);
    if ($written === false) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'write_failed']);
        exit;
    }

    // simple audit log to file (no external network calls)
    $logLine = json_encode([
        'user' => $_SESSION['user_name'] ?? 'admin',
        'template' => $templatePath,
        'action' => 'apply',
        'timestamp' => date('c'),
        'backup' => $bkFile,
    ]) . "\n";
    file_put_contents($_SERVER['DOCUMENT_ROOT'] . '/logs/admin-ai-agent.log', $logLine, FILE_APPEND);

    echo json_encode(['success' => true, 'backup' => $bkFile]);
    exit;
}

http_response_code(400);
echo json_encode(['success' => false, 'error' => 'unknown_action']);
exit;

?>
