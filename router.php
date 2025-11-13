<?php
// CIS Modules Front Controller Router
// Forces all module requests through a single entrypoint

declare(strict_types=1);

// Correlation + forced routing headers for easy identification
$CID = bin2hex(random_bytes(8));
header('X-Correlation-ID: '.$CID);
header('X-CIS-Modules-Routed: 1');

// Helpers
function wants_json(): bool {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    return (stripos($accept,'application/json')!==false) || (stripos($xhr,'xmlhttprequest')!==false);
}
function router_error(int $status, string $code, string $message, array $ctx = []): void {
    http_response_code($status);
    $payload = [
        'success' => false,
        'error' => $message,
        'code' => $code,
        'module' => $ctx['module'] ?? null,
        'path' => $ctx['path'] ?? null,
        'correlation_id' => $GLOBALS['CID'] ?? null
    ];
    if (wants_json()) {
        header('Content-Type: application/json');
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    } else {
        // Minimal readable HTML with clear badge indicating forced router
        echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>CIS Modules Router Error</title>'
            .'<style>body{font-family:Arial;margin:40px;background:#0b1020;color:#e5e7eb} .card{background:#111827;border:1px solid #374151;border-radius:8px;padding:18px;max-width:900px} .badge{display:inline-block;background:#9333ea;color:#fff;padding:4px 8px;border-radius:12px;font-size:12px;margin-left:8px} .code{background:#1f2937;color:#fbbf24;display:inline-block;padding:2px 6px;border-radius:4px} .hint{color:#a7b0bf;font-size:14px} ul{line-height:1.7} pre{background:#1f2937;padding:10px;border-radius:6px;overflow:auto}</style>'
            .'</head><body><div class="card">'
            .'<h2>CIS Modules Router Error <span class="badge">Forced via /modules/router.php</span></h2>'
            .'<p><strong>Code:</strong> <span class="code">'.htmlspecialchars($code).'</span></p>'
            .'<p><strong>Message:</strong> '.htmlspecialchars($message).'</p>'
            .'<p class="hint">Module: '.htmlspecialchars((string)$payload['module']).' | Path: '.htmlspecialchars((string)$payload['path']).' | CID: '.htmlspecialchars((string)$payload['correlation_id']).'</p>'
            .'<h3>What to do</h3><ul>'
            .'<li>Ensure module folder exists under /modules and contains an index.php</li>'
            .'<li>For Store Reports: /api/<name> maps to action=api:<name>, other segments map to action=<view></li>'
            .'<li>Verify /modules/.htaccess rewrites to router.php and server allows overrides</li>'
            .'<li>Confirm CORE bootstrap loads (modules/core/bootstrap.php) for auth + DB</li>'
            .'<li>Check authentication status; you may need to login via /modules/core/login.php</li>'
            .'</ul></div></body></html>';
    }
    error_log('[modules/router]['.$code.'] '.$message.' module='.( $ctx['module'] ?? '').' path='.( $ctx['path'] ?? '').' cid='.($GLOBALS['CID'] ?? ''));
    exit;
}

// Strict error handling without leaking internals
set_error_handler(function($s,$m,$f,$l){ router_error(500,'MOD-RTR-500','Internal Error',['module'=>$_GET['m']??'','path'=>$_GET['path']??'']); });

// Load CORE (preferred) which loads BASE; fallback to legacy root bootstrap
$coreBootstrap = __DIR__ . '/core/bootstrap.php';
if (is_file($coreBootstrap)) { require_once $coreBootstrap; }
else { $rootBootstrap = dirname(__DIR__) . '/bootstrap.php'; if (is_file($rootBootstrap)) require_once $rootBootstrap; }
if (function_exists('cis_log')) { cis_log('router','INFO',[ 'cid'=>$CID, 'uri'=>($_SERVER['REQUEST_URI']??''), 'host'=>($_SERVER['HTTP_HOST']??'') ]); }

// Parse module and path
$module = $_GET['m'] ?? '';
$rawPath = $_GET['path'] ?? '';

// If not provided via query, derive from REQUEST_URI (/modules/<module>/...)
if ($module === '') {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $after = strstr($uri, '/modules/');
    if ($after !== false) {
        $parts = explode('/', ltrim(substr($after, strlen('/modules/')), '/'));
        $module = $parts[0] ?? '';
        $rawPath = implode('/', array_slice($parts, 1));
    }
}

// Sanitize module and path
$module = preg_replace('/[^a-z0-9\-_.]/i', '', $module);
$rawPath = trim($rawPath, '/');

// Landing page if no module
if ($module === '') {
    $landing = __DIR__ . '/index.php';
    if (is_file($landing)) { require $landing; exit; }
    router_error(404,'MOD-RTR-404-NOMODULE','Module not specified');
}

$moduleDir = __DIR__ . '/' . $module;
header('X-CIS-Module: '.$module);
if (!is_dir($moduleDir)) { http_response_code(404); echo 'Unknown module'; exit; }

// Common module entrypoint
$moduleIndex = $moduleDir . '/index.php';

// If no path provided, load module's index.php directly
if ($rawPath === '') {
    if (is_file($moduleIndex)) { require $moduleIndex; exit; }
    router_error(404,'MOD-RTR-404-ENTRY','Module entry missing',[ 'module'=>$module, 'path'=>$rawPath ]);
}

// If direct file exists (e.g., views/dashboard.php), serve it only for .php under the module (prevent traversal)
if ($rawPath !== '') {
    // Explicit API mapping for store-reports (api/<name> -> action=api:<name>)
    if ($module === 'store-reports') {
        $segs = explode('/', $rawPath);
        if ($segs[0] === 'api' && !empty($segs[1])) {
            $_GET['action'] = 'api:'.preg_replace('/[^a-z0-9\-_.]/i','',$segs[1]);
        } else {
            // map first segment to view action if known
            $view = preg_replace('/[^a-z0-9\-_.]/i','',$segs[0]);
            if ($view) { $_GET['action'] = $view; }
        }
        if (is_file($moduleIndex)) { if (function_exists('cis_log')) { cis_log('router','INFO',[ 'cid'=>$CID, 'module'=>$module, 'path'=>$rawPath, 'mapped_action'=>($_GET['action']??'') ]); } require $moduleIndex; exit; }
        router_error(404,'MOD-RTR-404-ENTRY','Module entry missing',['module'=>$module,'path'=>$rawPath]);
    }

    // For other modules, try to include exact PHP if it exists under moduleDir
    $candidate = realpath($moduleDir . '/' . $rawPath);
    if ($candidate && strpos($candidate, realpath($moduleDir)) === 0 && substr($candidate,-4) === '.php' && is_file($candidate)) {
        require $candidate; exit;
    }
}

// Default: include module index
if (is_file($moduleIndex)) { if (function_exists('cis_log')) { cis_log('router','INFO',[ 'cid'=>$CID, 'module'=>$module, 'path'=>$rawPath, 'direct'=>true ]); } require $moduleIndex; exit; }

router_error(404,'MOD-RTR-404-NOTFOUND','Not Found',['module'=>$module,'path'=>$rawPath]);
