<?php
/**
 * ═══════════════════════════════════════════════════════════════════════════
 * VAPEULTRA THEME API - Production Grade
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Complete REST API for theme CRUD operations
 * Database persistence, validation, error handling
 *
 * @version 2.0.0
 * @author Ecigdis Limited
 */

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}
// Correlation ID
$correlationId = $_SERVER['HTTP_X_CORRELATION_ID'] ?? $_GET['cid'] ?? $_POST['cid'] ?? bin2hex(random_bytes(8));
$_SESSION['correlation_id'] = $correlationId;
// Embedding flags (allow harness to override behavior)
if (!defined('THEME_API_NO_EXIT')) { define('THEME_API_NO_EXIT', false); }
if (!defined('THEME_API_EMBED')) { define('THEME_API_EMBED', false); }
header('Content-Type: application/json');
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/base/bootstrap.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/base/lib/ThemeGenerator.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/base/lib/ThemeManager.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/base/lib/UnifiedThemeContext.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/base/lib/ThemeAuditLogger.php';
use Services\Database; use CIS\Base\ThemeAuditLogger; use CIS\Base\UnifiedThemeContext; use CIS\Base\ThemeManager;
header('Access-Control-Allow-Origin: ' . ($_SERVER['HTTP_ORIGIN'] ?? '*'));
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');

class ThemeAPI {
    private Database $db;
    private $pdo;
    private array $baseTokens = [];

    public function __construct() {
        $this->db = Database::getInstance();
        $this->pdo = $this->db->getConnection();
        $this->ensureSchema();
        $this->loadBaseTokens();
    }

    /**
     * Ensure database schema exists
     */
    private function ensureSchema() {
        $sql = "CREATE TABLE IF NOT EXISTS user_themes (id INT PRIMARY KEY AUTO_INCREMENT,user_id INT DEFAULT 1,name VARCHAR(100) NOT NULL,description TEXT,theme_data JSON NOT NULL,is_active BOOLEAN DEFAULT 0,version VARCHAR(20) DEFAULT '2.0.0',created_at DATETIME DEFAULT CURRENT_TIMESTAMP,updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,INDEX idx_user_active (user_id,is_active),INDEX idx_user_created (user_id,created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        $this->pdo->exec($sql);
        // Migration: ensure 'version' column exists (older tables may lack it)
        try {
            $check = $this->pdo->query("SHOW COLUMNS FROM user_themes LIKE 'version'");
            if ($check->rowCount() === 0) {
                $this->pdo->exec("ALTER TABLE user_themes ADD COLUMN version VARCHAR(20) DEFAULT '2.0.0' AFTER is_active");
            }
        } catch (Exception $e) {
            // Log but do not fail API startup
            error_log('Schema migration (version column) failed: '.$e->getMessage());
        }
    }

    /**
     * Load canonical token manifest for normalization
     */
    private function loadBaseTokens(): void {
        $file = $_SERVER['DOCUMENT_ROOT'] . '/modules/base/themes/_tokens.json';
        if (file_exists($file)) {
            $json = json_decode(file_get_contents($file), true);
            if (is_array($json)) {
                $this->baseTokens = $json;
            }
        }
    }

    /**
     * Normalize theme_data against canonical tokens (fill missing, restrict unknown)
     */
    private function normalizeTokens(array $themeData): array {
        if (empty($this->baseTokens)) { return $themeData; }
        foreach (['colors','typography','layout'] as $group) {
            $themeData[$group] = $themeData[$group] ?? [];
            // Fill defaults
            foreach ($this->baseTokens[$group] as $key => $val) {
                if (!array_key_exists($key, $themeData[$group])) {
                    $themeData[$group][$key] = $val;
                }
            }
            // Remove unknown keys to enforce contract
            foreach (array_keys($themeData[$group]) as $k) {
                if (!array_key_exists($k, $this->baseTokens[$group])) {
                    unset($themeData[$group][$k]);
                }
            }
        }
        return $themeData;
    }

    /**
     * Calculate WCAG contrast ratio between two hex colors
     */
    private function contrastRatio(string $hex1, string $hex2): float {
        $l1 = $this->relativeLuminance($hex1);
        $l2 = $this->relativeLuminance($hex2);
        $lighter = max($l1,$l2); $darker = min($l1,$l2);
        return round( ( ($lighter + 0.05) / ($darker + 0.05) ), 2 );
    }
    private function relativeLuminance(string $hex): float {
        $hex = ltrim($hex,'#');
        if (strlen($hex)===3) { $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2]; }
        $r = hexdec(substr($hex,0,2)) / 255;
        $g = hexdec(substr($hex,2,2)) / 255;
        $b = hexdec(substr($hex,4,2)) / 255;
        $transform = function($c){ return $c <= 0.03928 ? $c/12.92 : pow(($c+0.055)/1.055,2.4); };
        $r = $transform($r); $g = $transform($g); $b = $transform($b);
        return 0.2126*$r + 0.7152*$g + 0.0722*$b;
    }

    /**
     * Compute accessibility metrics for a color set
     */
    private function accessibilityMetrics(array $colors): array {
        $pairs = [
            ['text','background'],
            ['textSecondary','background'],
            ['primary','background'],
            ['accent','background'],
            ['danger','background'],
            ['warning','background'],
            ['success','background']
        ];
        $metrics = [];
        foreach ($pairs as $p) {
            [$fg,$bg] = $p;
            if (!empty($colors[$fg]) && !empty($colors[$bg])) {
                $ratio = $this->contrastRatio($colors[$fg], $colors[$bg]);
                $metrics[$fg.'On'.$bg] = [
                    'ratio' => $ratio,
                    'AA' => $ratio >= 4.5,
                    'AAA' => $ratio >= 7.0
                ];
            }
        }
        return $metrics;
    }

    /**
     * Handle incoming request
     */
    public function handleRequest() {
        $method = $_SERVER['REQUEST_METHOD'];
        $action = $_GET['action'] ?? $_POST['action'] ?? null;

        try {
            switch ($action) {
                case 'save': $this->guard(); return $this->saveTheme();
                case 'load': $this->guard(); return $this->loadTheme();
                case 'list': $this->guard(); return $this->listThemes();
                case 'delete': $this->guard(); return $this->deleteTheme();
                case 'duplicate': $this->guard(); return $this->duplicateTheme();
                case 'set_active': $this->guard(); return $this->setActiveTheme();
                case 'get_active': $this->guard(); return $this->getActiveTheme();
                case 'export': $this->guard(); return $this->exportTheme();
                case 'import': $this->guard(); return $this->importTheme();
                case 'generate': $this->guard(); return $this->generateTheme();
                case 'list_packs': $this->guard(); return $this->listPacks();
                case 'load_pack': $this->guard(); return $this->loadPack();
                case 'switch_runtime': $this->guard(true); return $this->switchRuntime();
                default: throw new Exception('Invalid action');
            }
        } catch (Exception $e) { return $this->error($e->getMessage()); }
    }

    private function guard(bool $designAdmin = false): void {
        if (!function_exists('isAuthenticated') || !isAuthenticated()) {
            $this->error('Authentication required', 401);
        }
        if ($designAdmin) {
            $roles = $_SESSION['roles'] ?? [];
            if (!in_array('design_admin', $roles) && !in_array('admin', $roles)) {
                $this->error('Forbidden', 403);
            }
        }
    }

    /**
     * Save theme (create or update)
     */
    private function saveTheme() {
        $data = json_decode(file_get_contents('php://input'), true);

        if (!$data) {
            $data = $_POST;
        }

        // Validation
        if (empty($data['name'])) {
            throw new Exception('Theme name is required');
        }

        if (empty($data['theme_data'])) {
            throw new Exception('Theme data is required');
        }

        $userId = $_SESSION['user_id'] ?? 1;
        $themeId = isset($data['id']) ? (int)$data['id'] : null;
        $name = trim($data['name']);
        $description = trim($data['description'] ?? '');
    $themeDataArr = is_string($data['theme_data']) ? json_decode($data['theme_data'], true) : $data['theme_data'];
    $themeDataArr = $this->normalizeTokens($themeDataArr);
        $themeDataJson = json_encode($themeDataArr, JSON_UNESCAPED_UNICODE);
        $isActive = !empty($data['is_active']) ? 1 : 0;
        if ($themeId) {
            $stmt = $this->pdo->prepare("UPDATE user_themes SET name=?, description=?, theme_data=?, is_active=?, updated_at=NOW() WHERE id=? AND user_id=?");
            $stmt->execute([$name, $description, $themeDataJson, $isActive, $themeId, $userId]);
            ThemeAuditLogger::log('update', $themeId, ['name' => $name]);
            return $this->success(['message' => 'Theme updated', 'theme_id' => $themeId]);
        } else {
            $stmt = $this->pdo->prepare("INSERT INTO user_themes (user_id,name,description,theme_data,is_active) VALUES (?,?,?,?,?)");
            $stmt->execute([$userId, $name, $description, $themeDataJson, $isActive]);
            $newId = (int)$this->pdo->lastInsertId();
            ThemeAuditLogger::log('create', $newId, ['name' => $name]);
            return $this->success(['message' => 'Theme created', 'theme_id' => $newId]);
        }
    }

    /**
     * Load specific theme
     */
    private function loadTheme() {
        $themeId = $_GET['theme_id'] ?? $_POST['theme_id'] ?? null;

        if (!$themeId) {
            throw new Exception('Theme ID is required');
        }

        $userId = $_SESSION['user_id'] ?? 1;
        $stmt = $this->pdo->prepare("SELECT * FROM user_themes WHERE id=? AND user_id=? LIMIT 1");
        $stmt->execute([$themeId, $userId]);
        $theme = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$theme) { throw new Exception('Theme not found'); }
    $theme['theme_data'] = $this->normalizeTokens(json_decode($theme['theme_data'], true));
        return $this->success(['theme' => $theme]);
    }

    /**
     * List all themes for current user
     */
    private function listThemes() {
        $userId = $_SESSION['user_id'] ?? 1;
        $stmt = $this->pdo->prepare("SELECT id,name,description,is_active,version,created_at,updated_at FROM user_themes WHERE user_id=? ORDER BY is_active DESC, updated_at DESC");
        $stmt->execute([$userId]);
        return $this->success(['themes' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    /**
     * Delete theme
     */
    private function deleteTheme() {
        $themeId = $_POST['theme_id'] ?? null;

        if (!$themeId) {
            throw new Exception('Theme ID is required');
        }

        $userId = $_SESSION['user_id'] ?? 1;
        $stmt = $this->pdo->prepare("SELECT is_active FROM user_themes WHERE id=? AND user_id=? LIMIT 1");
        $stmt->execute([$themeId, $userId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$row) { throw new Exception('Theme not found'); }
        if (!empty($row['is_active'])) { throw new Exception('Cannot delete active theme'); }
        $del = $this->pdo->prepare("DELETE FROM user_themes WHERE id=? AND user_id=?");
        $del->execute([$themeId, $userId]);
        ThemeAuditLogger::log('delete', (int)$themeId, []);
        return $this->success(['message' => 'Theme deleted']);
    }

    /**
     * Duplicate theme
     */
    private function duplicateTheme() {
        $themeId = $_POST['theme_id'] ?? null;

        if (!$themeId) {
            throw new Exception('Theme ID is required');
        }

        $userId = $_SESSION['user_id'] ?? 1;
        $stmt = $this->pdo->prepare("SELECT * FROM user_themes WHERE id=? AND user_id=? LIMIT 1");
        $stmt->execute([$themeId, $userId]);
        $theme = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$theme) { throw new Exception('Theme not found'); }
        $newName = $theme['name'] . ' (Copy)';
        $ins = $this->pdo->prepare("INSERT INTO user_themes (user_id,name,description,theme_data,is_active) VALUES (?,?,?,?,0)");
        $ins->execute([$userId, $newName, $theme['description'], $theme['theme_data']]);
        $newId = (int)$this->pdo->lastInsertId();
        ThemeAuditLogger::log('duplicate', $newId, ['source_id' => (int)$themeId]);
        return $this->success(['message' => 'Theme duplicated', 'theme_id' => $newId]);
    }

    /**
     * Set active theme
     */
    private function setActiveTheme() {
        $themeId = $_POST['theme_id'] ?? null;

        if (!$themeId) {
            throw new Exception('Theme ID is required');
        }

        $userId = $_SESSION['user_id'] ?? 1;
        $this->pdo->prepare("UPDATE user_themes SET is_active=0 WHERE user_id=?")->execute([$userId]);
        $stmt = $this->pdo->prepare("UPDATE user_themes SET is_active=1 WHERE id=? AND user_id=?");
        $stmt->execute([$themeId, $userId]);
        if ($stmt->rowCount() === 0) { throw new Exception('Theme not found'); }
        ThemeAuditLogger::log('set_active', (int)$themeId, []);
        return $this->success(['message' => 'Active theme set']);
    }

    /**
     * Get active theme
     */
    private function getActiveTheme() {
        $active = UnifiedThemeContext::getActive();
        return $this->success(['active' => $active]);
    }

    /**
     * Export theme as JSON
     */
    private function exportTheme() {
        $themeId = $_GET['theme_id'] ?? null;

        if (!$themeId) {
            throw new Exception('Theme ID is required');
        }

        $userId = $_SESSION['user_id'] ?? 1;
        $stmt = $this->pdo->prepare("SELECT * FROM user_themes WHERE id=? AND user_id=? LIMIT 1");
        $stmt->execute([$themeId, $userId]);
        $theme = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$theme) { throw new Exception('Theme not found'); }
    $theme['theme_data'] = $this->normalizeTokens(json_decode($theme['theme_data'], true));
    $export = [ 'name'=>$theme['name'],'description'=>$theme['description'],'version'=>$theme['version'],'created_at'=>$theme['created_at'],'updated_at'=>$theme['updated_at'],'theme_data'=>$theme['theme_data'] ];
    $exportJson = json_encode($export, JSON_UNESCAPED_UNICODE);
    $hash = hash('sha256', $exportJson);
    $export['integrity'] = $hash;
        ThemeAuditLogger::log('export',(int)$themeId,[]);
        return $this->success(['export' => $export]);
    }

    /**
     * Import theme from JSON
     */
    private function importTheme() {
    $importData = json_decode(file_get_contents('php://input'), true);

        if (!$importData || empty($importData['theme_data'])) {
            throw new Exception('Invalid import data');
        }

        $userId = $_SESSION['user_id'] ?? 1;
        $name = trim($importData['name'] ?? 'Imported Theme');
        $description = trim($importData['description'] ?? '');
        // Integrity check
        if (!empty($importData['integrity'])) {
            $rehashSource = $importData;
            unset($rehashSource['integrity']);
            $calc = hash('sha256', json_encode($rehashSource, JSON_UNESCAPED_UNICODE));
            if (!hash_equals($importData['integrity'], $calc)) {
                throw new Exception('Integrity hash mismatch');
            }
        }
        $normData = $this->normalizeTokens($importData['theme_data']);
        $themeData = json_encode($normData, JSON_UNESCAPED_UNICODE);
        $stmt = $this->pdo->prepare("INSERT INTO user_themes (user_id,name,description,theme_data,is_active) VALUES (?,?,?,?,0)");
        $stmt->execute([$userId, $name, $description, $themeData]);
        $newId = (int)$this->pdo->lastInsertId();
        ThemeAuditLogger::log('import',$newId,['name'=>$name]);
        return $this->success(['message'=>'Theme imported','theme_id'=>$newId]);
    }

    /**
     * Generate theme using color theory
     */
    private function generateTheme() {
        // ThemeGenerator already loaded at top of file

        $hue = (int)($_POST['hue'] ?? 220);
        $scheme = $_POST['scheme'] ?? 'complementary';

        if ($hue < 0 || $hue > 360) {
            throw new Exception('Hue must be between 0 and 360');
        }

        $validSchemes = ['complementary', 'analogous', 'triadic', 'split-complementary', 'tetradic', 'monochromatic'];
        if (!in_array($scheme, $validSchemes)) {
            throw new Exception('Invalid color scheme');
        }

    $colors = ThemeGenerator::generateTheme($hue, $scheme);
    $metrics = $this->accessibilityMetrics($colors);
    return $this->success(['generated' => ['colors'=>$colors,'hue'=>$hue,'scheme'=>$scheme,'accessibility'=>$metrics]]);
    }

    private function listPacks() {
        $dir = $_SERVER['DOCUMENT_ROOT'] . '/modules/base/themes';
        $packs = [];
        foreach (glob($dir . '/*', GLOB_ONLYDIR) as $d) {
            $slug = basename($d);
            $json = $d . '/theme.json';
            $meta = file_exists($json) ? json_decode(file_get_contents($json), true) : [];
            $packs[] = ['slug'=>$slug,'name'=>$meta['name']??$slug,'version'=>$meta['version']??null];
        }
        return $this->success(['packs'=>$packs]);
    }

    private function loadPack() {
        $slug = $_GET['slug'] ?? $_POST['slug'] ?? null;
        if (!$slug) { throw new Exception('slug required'); }
        $json = $_SERVER['DOCUMENT_ROOT'] . '/modules/base/themes/' . basename($slug) . '/theme.json';
        if (!file_exists($json)) { throw new Exception('Pack not found'); }
        $meta = json_decode(file_get_contents($json), true);
        $bridge = [
            'name' => $meta['name'] ?? $slug,
            'description' => $meta['description'] ?? '',
            'theme_data' => [
                'colors' => [
                    'primary' => $meta['colors']['primary'] ?? '#2563eb',
                    'secondary' => $meta['colors']['secondary'] ?? '#6b7280',
                    'accent' => $meta['colors']['accent'] ?? '#7c3aed',
                    'background' => $meta['colors']['background'] ?? '#0f172a',
                    'surface' => $meta['colors']['surface'] ?? '#1e293b',
                    'surfaceHover' => $meta['colors']['surfaceHover'] ?? '#334155',
                    'text' => $meta['colors']['text'] ?? '#f1f5f9',
                    'textSecondary' => $meta['colors']['textSecondary'] ?? '#94a3b8',
                    'success' => $meta['colors']['success'] ?? '#10b981',
                    'warning' => $meta['colors']['warning'] ?? '#f59e0b',
                    'danger' => $meta['colors']['danger'] ?? '#ef4444',
                    'border' => $meta['colors']['border'] ?? '#334155'
                ],
                'typography' => ['fontFamily'=>'Inter','fontSize'=>'14px','lineHeight'=>'1.6','letterSpacing'=>'0'],
                'layout' => ['borderRadius'=>'8px','spacingDensity'=>1.0,'shadowDepth'=>'medium']
            ]
        ];
        $bridge['theme_data'] = $this->normalizeTokens($bridge['theme_data']);
        return $this->success(['pack'=>$bridge]);
    }

    private function switchRuntime() {
        $slug = $_POST['slug'] ?? null;
        if (!$slug) { throw new Exception('slug required'); }
        $ok = ThemeManager::setActive($slug);
        if (!$ok) { throw new Exception('Invalid theme slug'); }
        ThemeAuditLogger::log('switch_runtime', null, ['slug'=>$slug]);
        return $this->success(['message'=>'Runtime theme switched','slug'=>$slug]);
    }

    /**
     * Success response
     */
    private function success($data = [], $status = 200) {
        http_response_code($status);
        global $correlationId;
        echo json_encode(['success'=>true,'data'=>$data,'meta'=>['ts'=>time(),'cid'=>$correlationId]]);
        if (!THEME_API_NO_EXIT) { exit; }
    }

    private function error($message, $status = 400) {
        http_response_code($status);
        global $correlationId;
        echo json_encode(['success'=>false,'error'=>$message,'meta'=>['ts'=>time(),'cid'=>$correlationId]]);
        if (!THEME_API_NO_EXIT) { exit; }
    }
}

// Initialize and handle request unless embedded
if (!THEME_API_EMBED) {
    $api = new ThemeAPI();
    $api->handleRequest();
}
