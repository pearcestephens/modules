<?php
declare(strict_types=1);

/**
 * Module Skeleton Generator (CIS-ready)
 *
 * - CLI & Web UI
 * - Creates: modules/<group>/_shared (with Autoload + modal components),
 *            modules/<group>/<module> (controllers/models/services/repos/policies/middleware/views/assets/api/tools/tests)
 * - PSR-4 autoload (Modules\* => /modules)
 * - Base classes + inheritance (ModuleController extends BaseController, etc.)
 * - API front controller, routes, views, assets
 * - Hardening via .htaccess and safe writers
 * - Built-in tools:
 *      tools/ModuleDoctor.php   → validates structure/inheritance, autoload
 *      tools/BlueprintUI.php    → add predefined page templates (CRUD/list/show/edit/create)
 *
 * Usage (CLI):
 *   php generate_module_skeleton.php --group=transfers --module=stock --base=/home/xxx/public_html/modules --with-api=1 --with-db=1 --with-tests=1 --with-sse=0 --dry-run=0
 *
 * Browser:
 *   Open in browser; fill the form; submit.
 *
 * Notes:
 * - Assumes CIS entrypoint at /app.php (you can edit below).
 * - Uses MySQLi when DB option enabled (no external deps).
 * - Safe idempotency: won’t overwrite existing files unless --force=1.
 */

@date_default_timezone_set('Pacific/Auckland');

/* =========================
 * Configuration defaults
 * ========================= */
const DEFAULT_NS_BASE = 'Modules'; // root namespace
const DEFAULT_DOCROOT_GUESS = 'DOCUMENT_ROOT';
const DEFAULT_APP_BOOTSTRAP = '/app.php'; // adjust if needed
const SAFE_PERMS_DIR = 0755;
const SAFE_PERMS_FILE = 0644;

/* =========================
 * Helpers
 * ========================= */
function is_cli(): bool { return PHP_SAPI === 'cli'; }

function respond_json(array $data, int $code = 200): void {
    if (!is_cli()) {
        http_response_code($code);
        header('Content-Type: application/json');
    }
    echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
}

function sanitize_name(string $name): string {
    // Allow letters, numbers, underscores; must start with letter
    if (!preg_match('/^[A-Za-z][A-Za-z0-9_]*$/', $name)) {
        throw new InvalidArgumentException("Invalid name '{$name}'. Use letters, numbers, underscores; start with a letter.");
    }
    return $name;
}

function normalize_path(string $path): string {
    $path = str_replace('\\', '/', $path);
    $path = preg_replace('#/+#', '/', $path);
    return rtrim($path, '/');
}

function join_path(string ...$parts): string {
    return normalize_path(implode('/', $parts));
}

function mkdir_safe(string $dir, array &$log, bool $dryRun = false): void {
    if (is_dir($dir)) { $log[] = "[=] dir exists: $dir"; return; }
    if ($dryRun) { $log[] = "[DRY] mkdir $dir"; return; }
    if (!@mkdir($dir, SAFE_PERMS_DIR, true) && !is_dir($dir)) {
        throw new RuntimeException("Failed to create dir: $dir");
    }
    @chmod($dir, SAFE_PERMS_DIR);
    $log[] = "[+] dir: $dir";
}

function write_file(string $file, string $content, array &$log, bool $overwrite = false, bool $dryRun = false): void {
    if (is_file($file) && !$overwrite) { $log[] = "[=] file exists (skip): $file"; return; }
    if ($dryRun) { $log[] = "[DRY] write $file (" . strlen($content) . " bytes)"; return; }
    $dir = dirname($file);
    if (!is_dir($dir)) {
        mkdir_safe($dir, $log, $dryRun);
    }
    if (@file_put_contents($file, $content) === false) {
        throw new RuntimeException("Failed to write file: $file");
    }
    @chmod($file, SAFE_PERMS_FILE);
    $log[] = (is_file($file) ? "[*] wrote: $file" : "[+] file: $file");
}

function bool_opt($v): bool {
    if (is_bool($v)) return $v;
    $v = strtolower((string)$v);
    return in_array($v, ['1','true','yes','on'], true);
}

function ui_header(string $title = 'Module Skeleton Generator'): void {
    if (is_cli()) return;
    echo "<!doctype html><html lang='en'><head><meta charset='utf-8'><meta name='viewport' content='width=device-width, initial-scale=1'>";
    echo "<title>{$title}</title><style>
        body{font:14px/1.4 system-ui,Segoe UI,Roboto,Arial;margin:24px;background:#f9fafb;color:#111}
        h1{font-size:22px;margin:0 0 16px}
        fieldset{border:1px solid #e5e7eb;padding:16px;margin:0 0 16px;background:#fff;border-radius:8px}
        label{display:block;margin:8px 0 4px}
        input[type=text],input[type=number],input[type=url]{width:100%;padding:8px;border:1px solid #e5e7eb;border-radius:6px}
        .row{display:grid;grid-template-columns:1fr 1fr;gap:16px}
        .btn{display:inline-block;background:#4f46e5;color:#fff;padding:10px 14px;border-radius:6px;text-decoration:none;border:0;cursor:pointer}
        .btn.secondary{background:#6b7280}
        .note{font-size:12px;color:#6b7280}
        pre{background:#0b1020;color:#e5e7eb;padding:12px;border-radius:8px;overflow:auto;max-height:420px}
        .success{background:#ecfdf5;border:1px solid #a7f3d0;color:#065f46;padding:12px;border-radius:8px;margin-top:12px}
        .warn{background:#fff7ed;border:1px solid #fed7aa;color:#9a3412;padding:12px;border-radius:8px;margin-top:12px}
    </style></head><body>";
}

function ui_footer(): void {
    if (is_cli()) return;
    echo "</body></html>";
}

/* =========================
 * Parse inputs
 * ========================= */
$defaults = [
    'group'      => 'transfers',
    'module'     => 'stock',
    'ns_base'    => DEFAULT_NS_BASE,
    'base'       => isset($_SERVER[DEFAULT_DOCROOT_GUESS]) ? normalize_path($_SERVER[DEFAULT_DOCROOT_GUESS]).'/modules' : getcwd().'/modules',
    'with_api'   => '1',
    'with_db'    => '1',
    'with_tests' => '1',
    'with_sse'   => '0',
    'with_assets'=> '1',
    'with_shared'=> '1',  // group-level _shared + _shared/modal
    'with_htacc' => '1',
    'with_tpl'   => '1',  // basic views/templates
    'force'      => '0',
    'dry_run'    => '0',
];

$in = $defaults;
if (is_cli()) {
    $opts = getopt('', [
        'group:', 'module:', 'ns_base:',
        'base:', 'with-api::', 'with-db::', 'with-tests::', 'with-sse::', 'with-assets::', 'with-shared::', 'with-htacc::', 'with-tpl::',
        'force::', 'dry-run::'
    ]);
    foreach ($opts as $k => $v) {
        $k = str_replace('-', '_', $k);
        $in[$k] = is_array($v) ? end($v) : (string)$v;
    }
} else {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        foreach (array_keys($defaults) as $k) {
            if (isset($_POST[$k])) $in[$k] = (string)$_POST[$k];
        }
    }
}

/* =========================
 * Show UI (GET)
 * ========================= */
if (!is_cli() && $_SERVER['REQUEST_METHOD'] === 'GET') {
    ui_header();
    ?>
    <h1>Module Skeleton Generator</h1>
    <form method="post">
        <fieldset>
            <div class="row">
                <div>
                    <label>Group (folder)</label>
                    <input name="group" value="<?=htmlspecialchars($in['group'])?>" required>
                </div>
                <div>
                    <label>Module (folder)</label>
                    <input name="module" value="<?=htmlspecialchars($in['module'])?>" required>
                </div>
            </div>
            <div class="row">
                <div>
                    <label>Namespace Base (root)</label>
                    <input name="ns_base" value="<?=htmlspecialchars($in['ns_base'])?>">
                    <div class="note">Default "Modules" → e.g. Modules\transfers\stock\...</div>
                </div>
                <div>
                    <label>Modules Base Path</label>
                    <input name="base" value="<?=htmlspecialchars($in['base'])?>">
                    <div class="note">Usually: <?=htmlspecialchars((isset($_SERVER[DEFAULT_DOCROOT_GUESS]) ? $_SERVER[DEFAULT_DOCROOT_GUESS].'/modules' : '[set absolute path]'))?></div>
                </div>
            </div>
        </fieldset>
        <fieldset>
            <div class="row">
                <div><label>With API</label><input name="with_api" value="<?=$in['with_api']?>"></div>
                <div><label>With DB (MySQLi)</label><input name="with_db" value="<?=$in['with_db']?>"></div>
            </div>
            <div class="row">
                <div><label>With Tests</label><input name="with_tests" value="<?=$in['with_tests']?>"></div>
                <div><label>With SSE stub</label><input name="with_sse" value="<?=$in['with_sse']?>"></div>
            </div>
            <div class="row">
                <div><label>With Assets (css/js)</label><input name="with_assets" value="<?=$in['with_assets']?>"></div>
                <div><label>With _shared</label><input name="with_shared" value="<?=$in['with_shared']?>"></div>
            </div>
            <div class="row">
                <div><label>With .htaccess</label><input name="with_htacc" value="<?=$in['with_htacc']?>"></div>
                <div><label>With Templates</label><input name="with_tpl" value="<?=$in['with_tpl']?>"></div>
            </div>
        </fieldset>
        <fieldset>
            <div class="row">
                <div><label>Force overwrite (1/0)</label><input name="force" value="<?=$in['force']?>"></div>
                <div><label>Dry-run (1/0)</label><input name="dry_run" value="<?=$in['dry_run']?>"></div>
            </div>
        </fieldset>
        <button class="btn" type="submit">Generate</button>
        <a class="btn secondary" href="?example=1">Show CLI Example</a>
    </form>
    <?php
    if (isset($_GET['example'])) {
        echo '<pre>$ php generate_module_skeleton.php --group=transfers --module=stock --with-api=1 --with-db=1 --with-tests=1 --with-shared=1 --with-assets=1 --with-htacc=1 --with-tpl=1</pre>';
    }
    ui_footer();
    exit;
}

/* =========================
 * Validate inputs
 * ========================= */
try {
    $group = sanitize_name($in['group']);
    $module = sanitize_name($in['module']);
    $nsBase = sanitize_name($in['ns_base']);
} catch (Throwable $e) {
    if (is_cli()) {
        fwrite(STDERR, "Input error: " . $e->getMessage() . PHP_EOL);
        exit(1);
    }
    ui_header('Error');
    echo "<div class='warn'>".$e->getMessage()."</div>";
    ui_footer();
    exit;
}

$baseModulesDir = normalize_path($in['base']);
$withApi    = bool_opt($in['with_api']);
$withDb     = bool_opt($in['with_db']);
$withTests  = bool_opt($in['with_tests']);
$withSse    = bool_opt($in['with_sse']);
$withAssets = bool_opt($in['with_assets']);
$withShared = bool_opt($in['with_shared']);
$withHtacc  = bool_opt($in['with_htacc']);
$withTpl    = bool_opt($in['with_tpl']);
$force      = bool_opt($in['force']);
$dry        = bool_opt($in['dry_run']);

$log = [];
$errors = [];

$groupDir  = join_path($baseModulesDir, $group);
$sharedDir = join_path($groupDir, '_shared');
$moduleDir = join_path($groupDir, $module);

$nsShared  = $nsBase . '\\' . $group . '\\_shared';
$nsModule  = $nsBase . '\\' . $group . '\\' . $module;

$appBootstrap = DEFAULT_APP_BOOTSTRAP;

/* =========================
 * Directory plan
 * ========================= */
$dirs = [
    $groupDir,
    $moduleDir,
    join_path($moduleDir, 'controllers'),
    join_path($moduleDir, 'models'),
    join_path($moduleDir, 'services'),
    join_path($moduleDir, 'repositories'),
    join_path($moduleDir, 'policies'),
    join_path($moduleDir, 'middleware'),
    join_path($moduleDir, 'views/layouts'),
    join_path($moduleDir, 'views/pages'),
    join_path($moduleDir, 'views/partials'),
    join_path($moduleDir, 'api'),
    join_path($moduleDir, 'tools'),
];
if ($withAssets) {
    $dirs[] = join_path($moduleDir, 'assets/css');
    $dirs[] = join_path($moduleDir, 'assets/js');
    $dirs[] = join_path($moduleDir, 'assets/img');
}
if ($withTests) {
    $dirs[] = join_path($moduleDir, 'tests/unit');
}

if ($withShared) {
    $dirs[] = $sharedDir;
    $dirs[] = join_path($sharedDir, 'Components');
    $dirs[] = join_path($sharedDir, 'modal');
}

/* =========================
 * Templates
 * ========================= */
$autoloadShared = <<<PHP
<?php
declare(strict_types=1);
/**
 * PSR-4 Autoloader for "{$nsBase}" => /modules
 */
spl_autoload_register(function(string \$class){
    \$prefix = '{$nsBase}\\\\';
    if (strncmp(\$class, \$prefix, strlen(\$prefix)) !== 0) return;
    \$baseDir = dirname(__DIR__,1); // .../modules/<group>
    \$relative = substr(\$class, strlen(\$prefix)); // group\\module\\...
    \$file = \$baseDir . '/' . str_replace('\\\\', '/', \$relative) . '.php';
    if (is_file(\$file)) require \$file;
});
PHP;

$baseController = <<<PHP
<?php
declare(strict_types=1);

namespace {$nsShared};

abstract class BaseController
{
    protected ?\\mysqli \$db = null;

    public function __construct(?\\mysqli \$db = null)
    {
        \$this->db = \$db;
    }

    protected function json(array \$data, int \$code = 200): void
    {
        http_response_code(\$code);
        header('Content-Type: application/json');
        echo json_encode(\$data, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
    }

    protected function render(string \$viewFile, array \$vars = []): void
    {
        extract(\$vars, EXTR_SKIP);
        include __DIR__ . "/../../{$module}/views/layouts/base.php";
    }

    protected function requireAuth(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        if (empty(\$_SESSION['user_id'])) {
            http_response_code(401);
            exit('Authentication required');
        }
    }

    protected function csrfToken(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        if (empty(\$_SESSION['csrf'])) \$_SESSION['csrf'] = bin2hex(random_bytes(16));
        return \$_SESSION['csrf'];
    }

    protected function checkCsrf(string \$token): bool
    {
        if (session_status() !== PHP_SESSION_ACTIVE) session_start();
        return isset(\$_SESSION['csrf']) && hash_equals(\$_SESSION['csrf'], \$token);
    }
}
PHP;

$baseService = <<<PHP
<?php
declare(strict_types=1);

namespace {$nsShared};

abstract class BaseService
{
    protected ?\\mysqli \$db;
    public function __construct(?\\mysqli \$db = null) { \$this->db = \$db; }
}
PHP;

$baseModel = <<<PHP
<?php
declare(strict_types=1);

namespace {$nsShared};

abstract class BaseModel
{
    protected array \$attributes = [];
    public function __construct(array \$attrs = []) { \$this->attributes = \$attrs; }
    public function get(string \$k, \$default=null){ return \$this->attributes[\$k] ?? \$default; }
    public function set(string \$k, \$v): void { \$this->attributes[\$k]=\$v; }
    public function all(): array { return \$this->attributes; }
}
PHP;

$moduleController = <<<PHP
<?php
declare(strict_types=1);

namespace {$nsModule}\\controllers;

use {$nsShared}\\BaseController;

class {$module}Controller extends BaseController
{
    public function index(): void
    {
        \$this->requireAuth();
        \$this->render(__DIR__ . '/../views/pages/index.php', [
            'title' => ucfirst('{$module}') . ' — Dashboard',
        ]);
    }

    public function apiHello(): void
    {
        \$this->json(['ok' => true, 'module' => '{$group}/{$module}', 'ts' => date('c')]);
    }
}
PHP;

$moduleService = <<<PHP
<?php
declare(strict_types=1);

namespace {$nsModule}\\services;

use {$nsShared}\\BaseService;

class {$module}Service extends BaseService
{
    public function ping(): array
    {
        return ['service' => '{$module}', 'db' => \$this->db ? 'connected?' : 'none'];
    }
}
PHP;

$moduleModel = <<<PHP
<?php
declare(strict_types=1);

namespace {$nsModule}\\models;

use {$nsShared}\\BaseModel;

class {$module}Entity extends BaseModel
{
    // Example entity for {$module}
}
PHP;

$layoutBase = <<<PHP
<?php /** @var string \$title */ ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars(\$title ?? '{$module}'); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if (is_file(__DIR__.'/../../assets/css/module.css')): ?>
    <link rel="stylesheet" href="../assets/css/module.css">
    <?php endif; ?>
</head>
<body>
    <header class="mod-header">
        <h1><?= htmlspecialchars(\$title ?? '{$module}'); ?></h1>
        <nav class="mod-nav">
            <a href="./index.php">Home</a>
            <a href="../tools/BlueprintUI.php">BlueprintUI</a>
            <a href="../tools/ModuleDoctor.php">ModuleDoctor</a>
        </nav>
    </header>
    <main class="mod-main">
        <?php include \$viewFile; ?>
    </main>
    <footer class="mod-footer">
        <small>&copy; <?= date('Y') ?> Ecigdis Ltd — Module <?= htmlspecialchars('{$group}/{$module}') ?></small>
    </footer>
    <?php if (is_file(__DIR__.'/../../assets/js/module.js')): ?>
    <script src="../assets/js/module.js"></script>
    <?php endif; ?>
</body>
</html>
PHP;

$pageIndex = <<<PHP
<?php
// Index page for {$group}/{$module}
?>
<section>
    <p>Welcome to <strong>{$group}/{$module}</strong>. This is your starter page.</p>
    <ul>
        <li>Try the API: <code>api/index.php?action=hello</code></li>
        <li>Open BlueprintUI to generate CRUD pages</li>
        <li>Run ModuleDoctor to validate inheritance & autoload</li>
    </ul>
</section>
PHP;

$assetCss = <<<CSS
:root{--brand:#6d28d9}
body{font:14px/1.5 system-ui,Segoe UI,Roboto,Arial;background:#f7f7fb;color:#0f172a;margin:0}
.mod-header{background:#fff;border-bottom:1px solid #e5e7eb;padding:16px}
.mod-nav a{margin-right:12px;color:#334155;text-decoration:none}
.mod-main{padding:20px}
.mod-footer{padding:12px;border-top:1px solid #e5e7eb;color:#64748b;background:#fff}
CSS;

$assetJs = <<<JS
document.addEventListener('DOMContentLoaded', ()=> {
    console.log('[{$group}/{$module}] module.js loaded');
});
JS;

$apiIndex = <<<PHP
<?php
declare(strict_types=1);

/**
 * {$group}/{$module} API front controller
 */
@date_default_timezone_set('Pacific/Auckland');

\$DOC = \$_SERVER['DOCUMENT_ROOT'] ?? dirname(__FILE__, 5);
require_once \$DOC . '{$appBootstrap}';

// Load PSR-4 autoload
require_once __DIR__ . '/../_bootstrap_autoload.php';

use {$nsModule}\\controllers\\{$module}Controller;

\$db = null;
if (class_exists('mysqli')) {
    // Optional: wire your DB here; leave null to skip
    // \$db = new mysqli(\$_ENV['DB_HOST']??'', \$_ENV['DB_USER']??'', \$_ENV['DB_PASS']??'', \$_ENV['DB_NAME']??'');
}

\$ctl = new {$module}Controller(\$db);
\$action = \$_GET['action'] ?? 'hello';

switch (\$action) {
    case 'hello':
        \$ctl->apiHello();
        break;
    default:
        http_response_code(404);
        header('Content-Type: application/json');
        echo json_encode(['error'=>'Unknown action','action'=>\$action]);
}
PHP;

$bootstrapAutoload = <<<PHP
<?php
/**
 * Bootstrap autoload for {$group}/{$module}
 */
require_once __DIR__ . '/../../_shared/Autoload.php';
PHP;

$routesPhp = <<<PHP
<?php
// Example route map (optional use in a global router)
return [
    '{$group}/{$module}' => [
        'GET /'        => '{$nsModule}\\\\controllers\\\\{$module}Controller@index',
        'GET /api/hi'  => '{$nsModule}\\\\controllers\\\\{$module}Controller@apiHello',
    ],
];
PHP;

$htaccessRoot = <<<HTA
# Security hardening
Options -Indexes
<IfModule mod_autoindex.c>
  IndexIgnore *
</IfModule>

# Deny direct execution in /views and /tests
<Directory "views">
  <FilesMatch "\\.(php|phtml)$">
    Require all denied
  </FilesMatch>
  Options -Indexes
</Directory>

<Directory "tests">
  Require all denied
</Directory>
HTA;

$toolDoctor = <<<PHP
<?php
declare(strict_types=1);

/**
 * ModuleDoctor — validates inheritance & autoload
 */
header('Content-Type: application/json');

@date_default_timezone_set('Pacific/Auckland');

try {
    require_once __DIR__ . '/../_bootstrap_autoload.php';

    \$checks = [];
    \$nsShared = '{$nsShared}';
    \$nsModule = '{$nsModule}';

    \$checks['autoload_shared_exists'] = class_exists(\$nsShared.'\\\\BaseController', true);
    \$checks['autoload_service_exists'] = class_exists(\$nsShared.'\\\\BaseService', true);
    \$checks['autoload_model_exists']   = class_exists(\$nsShared.'\\\\BaseModel', true);

    \$ctrlClass = \$nsModule . '\\\\controllers\\\\{$module}Controller';
    \$checks['controller_exists'] = class_exists(\$ctrlClass, true);

    \$okInherit = false;
    if (\$checks['controller_exists']) {
        \$okInherit = is_subclass_of(\$ctrlClass, \$nsShared.'\\\\BaseController', true);
    }
    \$checks['controller_extends_base'] = \$okInherit;

    echo json_encode(['ok'=>!in_array(false, \$checks, true), 'checks'=>\$checks], JSON_PRETTY_PRINT);
} catch (Throwable \$e) {
    http_response_code(500);
    echo json_encode(['ok'=>false,'error'=>\$e->getMessage(),'trace'=>\$e->getTraceAsString()]);
}
PHP;

$toolBlueprint = <<<PHP
<?php
declare(strict_types=1);

/**
 * BlueprintUI — generate predefined page templates
 * Creates views/pages/{list,show,create,edit}.php
 */
@date_default_timezone_set('Pacific/Auckland');

if (session_status() !== PHP_SESSION_ACTIVE) session_start();
if (empty(\$_SESSION['user_id'])) {
    http_response_code(401);
    exit('Auth required');
}

\$pages = ['list','show','create','edit'];
\$base = realpath(__DIR__ . '/../views/pages') ?: (__DIR__ . '/../views/pages');
\$msg = '';

function write_tpl(\$name, \$file){
\$tpl = <<<PHP
<?php /* Auto-generated {$group}/{$module} \${$name} page */ ?>
<section>
    <h2><?= ucfirst('{$module}') ?> — <?= ucfirst('{$group}') ?> / {$name}</h2>
    <p>This is the <strong>{$name}</strong> page template.</p>
</section>
PHP;
    return file_put_contents(\$file, \$tpl) !== false;
}

if (\$_SERVER['REQUEST_METHOD']==='POST') {
    \$picked = \$_POST['pages'] ?? [];
    foreach ((array)\$picked as \$p) {
        if (!in_array(\$p, \$pages, true)) continue;
        \$file = \$base . '/' . \$p . '.php';
        if (is_file(\$file)) { \$msg .= "Exists: \$p\\n"; continue; }
        write_tpl(\$p, \$file) ? \$msg .= "Created: \$p\\n" : \$msg .= "Failed: \$p\\n";
    }
}

?><!doctype html><meta charset="utf-8">
<title>BlueprintUI — <?=$group?>/<?=$module?></title>
<style>body{font:14px system-ui;margin:24px}fieldset{border:1px solid #ddd;padding:16px;border-radius:8px}</style>
<h1>BlueprintUI — <?=htmlspecialchars('{$group}/{$module}')?></h1>
<form method="post">
    <fieldset>
        <legend>Create page templates</legend>
        <?php foreach (\$pages as \$p): ?>
            <label><input type="checkbox" name="pages[]" value="<?=$p?>"> <?=$p?></label><br>
        <?php endforeach; ?>
        <p><button>Generate</button></p>
    </fieldset>
</form>
<?php if (!empty(\$msg)): ?>
<pre><?=htmlspecialchars(\$msg)?></pre>
<?php endif; ?>
PHP;

$readme = <<<MD
# {$group}/{$module} Module

Generated by Module Skeleton Generator.

## Structure

- \`controllers/\` — {$module}Controller extends _shared BaseController
- \`services/\` — {$module}Service extends _shared BaseService
- \`models/\` — {$module}Entity extends _shared BaseModel
- \`views/\` — layouts/pages/partials
- \`api/\` — front controller (\`index.php\`) + autoload bootstrap
- \`tools/\` — ModuleDoctor.php (validation), BlueprintUI.php (page templates)
- \`assets/\` — css/js/img
- \`_shared/\` (group-level) — Autoload.php, BaseController, BaseService, BaseModel, modal components

## Quick start

1) Wire autoload by requiring: \`modules/{$group}/_shared/Autoload.php\` (or \`_bootstrap_autoload.php\` inside module).
2) Visit \`views/pages/index.php\` via your router, or open \`tools/ModuleDoctor.php\` and \`tools/BlueprintUI.php\`.
3) API hello: \`{$group}/{$module}/api/index.php?action=hello\`.

## Notes
- Protect \`tools/\` behind auth in production.
- Update DB wiring in \`api/index.php\` if using MySQLi.
MD;

$composerJson = <<<JSON
{
  "name": "cis/{$group}-{$module}",
  "description": "{$group}/{$module} module",
  "type": "project",
  "autoload": {
    "psr-4": {
      "{$nsBase}\\\\": "modules/"
    }
  },
  "require": {}
}
JSON;

$sharedModalConfirm = <<<PHP
<?php
/** Group-level shared modal example (confirm) */
?>
<div class="modal" id="confirmModal" hidden>
  <div class="modal-dialog">
    <div class="modal-body">
      <p>Are you sure?</p>
      <button data-role="ok">OK</button>
      <button data-role="cancel">Cancel</button>
    </div>
  </div>
</div>
PHP;

$htaccessViews = <<<HTA
# Deny PHP execution in this directory
<FilesMatch "\\.(php|phtml)\$">
  Require all denied
</FilesMatch>
Options -Indexes
HTA;

$testsPhpUnit = <<<XML
<?xml version="1.0" encoding="UTF-8"?>
<phpunit bootstrap="../_bootstrap_tests.php" colors="true">
  <testsuites>
    <testsuite name="{$group}-{$module} Unit">
      <directory>./unit</directory>
    </testsuite>
  </testsuites>
</phpunit>
XML;

$testsBootstrap = <<<PHP
<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap_autoload.php';
PHP;

$unitTest = <<<PHP
<?php
declare(strict_types=1);

use PHPUnit\\Framework\\TestCase;

final class BasicTest extends TestCase
{
    public function test_truth(): void
    {
        \$this->assertTrue(true);
    }
}
PHP;

$sseStub = <<<PHP
<?php
/**
 * SSE stub for {$group}/{$module}
 */
@date_default_timezone_set('Pacific/Auckland');

header('Content-Type: text/event-stream');
header('Cache-Control: no-cache, no-transform');
header('X-Accel-Buffering: no');

\$start = time();
for (\$i=0;\$i<5;\$i++) {
    echo "event: ping\n";
    echo "data: ".json_encode(['i'=>\$i,'ts'=>date('c')])."\n\n";
    @ob_flush(); @flush();
    usleep(500000);
}
PHP;

/* =========================
 * Generate
 * ========================= */
try {
    // Directories
    foreach ($dirs as $d) { mkdir_safe($d, $log, $dry); }

    // Shared (group-level)
    if ($withShared) {
        write_file(join_path($sharedDir, 'Autoload.php'), $autoloadShared, $log, $force, $dry);
        write_file(join_path($sharedDir, 'BaseController.php'), $baseController, $log, $force, $dry);
        write_file(join_path($sharedDir, 'BaseService.php'), $baseService, $log, $force, $dry);
        write_file(join_path($sharedDir, 'BaseModel.php'), $baseModel, $log, $force, $dry);
        write_file(join_path($sharedDir, 'modal/confirm.php'), $sharedModalConfirm, $log, $force, $dry);
    }

    // Module bootstrap autoload pointer
    write_file(join_path($moduleDir, '_bootstrap_autoload.php'), $bootstrapAutoload, $log, $force, $dry);

    // Base files
    write_file(join_path($moduleDir, 'controllers', "{$module}Controller.php"), $moduleController, $log, $force, $dry);
    write_file(join_path($moduleDir, 'services', "{$module}Service.php"), $moduleService, $log, $force, $dry);
    write_file(join_path($moduleDir, 'models', "{$module}Entity.php"), $moduleModel, $log, $force, $dry);

    // Views
    if ($withTpl) {
        write_file(join_path($moduleDir, 'views/layouts/base.php'), $layoutBase, $log, $force, $dry);
        write_file(join_path($moduleDir, 'views/pages/index.php'), $pageIndex, $log, $force, $dry);
        write_file(join_path($moduleDir, 'views/.htaccess'), $htaccessViews, $log, $force, $dry);
    }

    // Assets
    if ($withAssets) {
        write_file(join_path($moduleDir, 'assets/css/module.css'), $assetCss, $log, $force, $dry);
        write_file(join_path($moduleDir, 'assets/js/module.js'), $assetJs, $log, $force, $dry);
    }

    // API
    if ($withApi) {
        write_file(join_path($moduleDir, 'api/index.php'), $apiIndex, $log, $force, $dry);
    }

    // Routes
    write_file(join_path($moduleDir, 'routes.php'), $routesPhp, $log, $force, $dry);

    // Tools
    write_file(join_path($moduleDir, 'tools/ModuleDoctor.php'), $toolDoctor, $log, $force, $dry);
    write_file(join_path($moduleDir, 'tools/BlueprintUI.php'), $toolBlueprint, $log, $force, $dry);

    // Readme & composer.json (optional)
    write_file(join_path($moduleDir, 'README.md'), $readme, $log, $force, $dry);
    write_file(join_path($moduleDir, 'composer.json'), $composerJson, $log, $force, $dry);

    // Tests
    if ($withTests) {
        write_file(join_path($moduleDir, 'tests/phpunit.xml'), $testsPhpUnit, $log, $force, $dry);
        write_file(join_path($moduleDir, 'tests/_bootstrap_tests.php'), $testsBootstrap, $log, $force, $dry);
        write_file(join_path($moduleDir, 'tests/unit/BasicTest.php'), $unitTest, $log, $force, $dry);
    }

    // SSE stub
    if ($withSse) {
        write_file(join_path($moduleDir, 'api/sse.php'), $sseStub, $log, $force, $dry);
    }

    // Hardening
    if ($withHtacc) {
        write_file(join_path($moduleDir, '.htaccess'), $htaccessRoot, $log, $force, $dry);
    }

    $result = [
        'ok' => true,
        'group' => $group,
        'module' => $module,
        'namespace_root' => $nsBase,
        'base_dir' => $baseModulesDir,
        'module_dir' => $moduleDir,
        'created' => $log,
        'next_steps' => [
            "1) Include modules/{$group}/_shared/Autoload.php (or {$group}/{$module}/_bootstrap_autoload.php) in your entrypoint/router.",
            "2) Visit {$group}/{$module}/tools/ModuleDoctor.php to verify inheritance/autoload.",
            "3) Open {$group}/{$module}/tools/BlueprintUI.php to add list/show/create/edit templates.",
            "4) API test: {$group}/{$module}/api/index.php?action=hello",
            "5) Wire DB in api/index.php if you enabled DB.",
        ],
    ];

    if (is_cli()) {
        echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL;
    } else {
        ui_header('Module Generated');
        echo "<div class='success'><strong>Success!</strong> Module generated.</div>";
        echo "<pre>".htmlspecialchars(json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES))."</pre>";
        ui_footer();
    }
} catch (Throwable $e) {
    $out = ['ok'=>false,'error'=>$e->getMessage(),'trace'=>$e->getTraceAsString(),'log'=>$log];
    if (is_cli()) {
        fwrite(STDERR, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . PHP_EOL);
        exit(1);
    }
    ui_header('Error');
    echo "<div class='warn'><strong>Error:</strong> ".htmlspecialchars($e->getMessage())."</div>";
    echo "<pre>".htmlspecialchars($e->getTraceAsString())."</pre>";
    if ($log) echo "<pre>".htmlspecialchars(implode("\n", $log))."</pre>";
    ui_footer();
}
