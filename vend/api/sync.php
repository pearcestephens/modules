<?php
/**
 * Vend Sync Manager - JSON API Endpoint
 *
 * Provides RESTful API access to Vend sync operations.
 * Can be called from web applications, cron jobs, or external systems.
 *
 * Usage:
 *   POST /modules/vend/api/sync.php?action=sync&entity=products
 *   POST /modules/vend/api/sync.php?action=queue_stats
 *   POST /modules/vend/api/sync.php?action=webhook_process
 *
 * Authentication: Requires valid API token in Authorization header
 *
 * @version 1.0.0
 */

// Security: Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Method not allowed. Use POST.']);
    exit;
}

// Set JSON response header
header('Content-Type: application/json');

// Load CIS bootstrap
require_once __DIR__ . '/../../../private_html/app.php';

// Authentication check
function authenticate(): bool
{
    // Check for Authorization header
    $headers = getallheaders();
    $token = $headers['Authorization'] ?? $headers['authorization'] ?? null;

    if (!$token) {
        return false;
    }

    // Remove "Bearer " prefix if present
    $token = str_replace('Bearer ', '', $token);

    // Validate against configured API token
    $validToken = cis_config_get('vend_api_token') ?? getenv('VEND_API_TOKEN');

    return hash_equals($validToken, $token);
}

// Rate limiting check
function checkRateLimit(): bool
{
    $ip = $_SERVER['REMOTE_ADDR'];
    $cacheKey = "api_rate_limit:$ip";

    // Use simple file-based cache if no Redis
    $cacheFile = sys_get_temp_dir() . '/' . md5($cacheKey);

    if (file_exists($cacheFile)) {
        $data = json_decode(file_get_contents($cacheFile), true);
        $requests = $data['requests'] ?? 0;
        $resetTime = $data['reset'] ?? 0;

        if (time() < $resetTime) {
            if ($requests >= 60) { // 60 requests per minute
                return false;
            }
            $requests++;
        } else {
            $requests = 1;
            $resetTime = time() + 60;
        }
    } else {
        $requests = 1;
        $resetTime = time() + 60;
    }

    file_put_contents($cacheFile, json_encode([
        'requests' => $requests,
        'reset' => $resetTime,
    ]));

    return true;
}

// Authenticate request
if (!authenticate()) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized. Valid API token required.']);
    exit;
}

// Check rate limit
if (!checkRateLimit()) {
    http_response_code(429);
    echo json_encode(['error' => 'Rate limit exceeded. Try again later.']);
    exit;
}

// Parse action
$action = $_GET['action'] ?? $_POST['action'] ?? null;

if (!$action) {
    http_response_code(400);
    echo json_encode(['error' => 'Action parameter required']);
    exit;
}

// Route to appropriate handler
try {
    $result = null;

    switch ($action) {
        // ─────────────────────────────────────────────────────────────────
        // SYNC OPERATIONS
        // ─────────────────────────────────────────────────────────────────
        case 'sync':
            $entity = $_GET['entity'] ?? $_POST['entity'] ?? null;
            $full = isset($_GET['full']) || isset($_POST['full']);
            $since = $_GET['since'] ?? $_POST['since'] ?? null;

            if (!$entity) {
                throw new Exception('Entity parameter required');
            }

            $cmd = ['php', __DIR__ . '/../cli/vend-sync-manager.php', "sync:$entity"];
            if ($full) $cmd[] = '--full';
            if ($since) $cmd[] = "--since=$since";

            $output = shell_exec(escapeshellcmd(implode(' ', $cmd)) . ' 2>&1');

            $result = [
                'action' => 'sync',
                'entity' => $entity,
                'output' => $output,
            ];
            break;

        case 'sync_all':
            $full = isset($_GET['full']) || isset($_POST['full']);

            $cmd = ['php', __DIR__ . '/../cli/vend-sync-manager.php', 'sync:all'];
            if ($full) $cmd[] = '--full';

            $output = shell_exec(escapeshellcmd(implode(' ', $cmd)) . ' 2>&1');

            $result = [
                'action' => 'sync_all',
                'output' => $output,
            ];
            break;

        // ─────────────────────────────────────────────────────────────────
        // QUEUE OPERATIONS
        // ─────────────────────────────────────────────────────────────────
        case 'queue_stats':
            $db = db_ro();
            $stmt = $db->query("
                SELECT
                    entity_type,
                    status,
                    COUNT(*) as count,
                    MAX(created_at) as latest
                FROM vend_queue
                GROUP BY entity_type, status
                ORDER BY entity_type, status
            ");

            $result = [
                'action' => 'queue_stats',
                'stats' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            ];
            break;

        case 'queue_process':
            $limit = $_GET['limit'] ?? $_POST['limit'] ?? 100;

            $cmd = ['php', __DIR__ . '/../cli/vend-sync-manager.php', 'queue:process', "--limit=$limit"];
            $output = shell_exec(escapeshellcmd(implode(' ', $cmd)) . ' 2>&1');

            $result = [
                'action' => 'queue_process',
                'output' => $output,
            ];
            break;

        case 'queue_failed':
            $limit = $_GET['limit'] ?? $_POST['limit'] ?? 50;

            $cmd = ['php', __DIR__ . '/../cli/vend-sync-manager.php', 'queue:process-failed', "--limit=$limit"];
            $output = shell_exec(escapeshellcmd(implode(' ', $cmd)) . ' 2>&1');

            $result = [
                'action' => 'queue_failed',
                'output' => $output,
            ];
            break;

        // ─────────────────────────────────────────────────────────────────
        // WEBHOOK OPERATIONS
        // ─────────────────────────────────────────────────────────────────
        case 'webhook_process':
            $payload = json_decode(file_get_contents('php://input'), true);

            if (!$payload) {
                throw new Exception('Invalid webhook payload');
            }

            // Include CLI manager
            require_once __DIR__ . '/../cli/vend-sync-manager.php';

            // Create processor
            $config = new ConfigManager();
            $output = new CLIOutput();
            $db = new DatabaseManager($config);
            $api = new LightspeedAPIClient($config);
            $logger = new AuditLogger($db);
            $queue = new QueueManager($db, $logger);
            $sync = new SyncEngine($api, $db, $logger, $config);

            $processor = new WebhookProcessor($sync, $db, $queue, $logger, $config);
            $result = $processor->process($payload);
            break;

        case 'webhook_events':
            require_once __DIR__ . '/../cli/vend-sync-manager.php';

            $result = [
                'action' => 'webhook_events',
                'events' => WebhookProcessor::getSupportedEvents(),
            ];
            break;

        // ─────────────────────────────────────────────────────────────────
        // CONSIGNMENT OPERATIONS
        // ─────────────────────────────────────────────────────────────────
        case 'consignment_validate':
            $id = $_GET['id'] ?? $_POST['id'] ?? null;

            if (!$id) {
                throw new Exception('Consignment ID required');
            }

            $cmd = ['php', __DIR__ . '/../cli/vend-sync-manager.php', 'consignment:validate', "--id=$id"];
            $output = shell_exec(escapeshellcmd(implode(' ', $cmd)) . ' 2>&1');

            $result = [
                'action' => 'consignment_validate',
                'id' => $id,
                'output' => $output,
            ];
            break;

        case 'consignment_transition':
            $id = $_GET['id'] ?? $_POST['id'] ?? null;
            $to = $_GET['to'] ?? $_POST['to'] ?? null;
            $dryRun = isset($_GET['dry_run']) || isset($_POST['dry_run']);

            if (!$id || !$to) {
                throw new Exception('Consignment ID and target state required');
            }

            $cmd = ['php', __DIR__ . '/../cli/vend-sync-manager.php', 'consignment:transition', "--id=$id", "--to=$to"];
            if ($dryRun) $cmd[] = '--dry-run';

            $output = shell_exec(escapeshellcmd(implode(' ', $cmd)) . ' 2>&1');

            $result = [
                'action' => 'consignment_transition',
                'id' => $id,
                'to' => $to,
                'dry_run' => $dryRun,
                'output' => $output,
            ];
            break;

        // ─────────────────────────────────────────────────────────────────
        // HEALTH OPERATIONS
        // ─────────────────────────────────────────────────────────────────
        case 'health':
            $cmd = ['php', __DIR__ . '/../cli/vend-sync-manager.php', 'health:check'];
            $output = shell_exec(escapeshellcmd(implode(' ', $cmd)) . ' 2>&1');

            // Parse output for JSON
            $lines = explode("\n", $output);
            $checks = [];
            foreach ($lines as $line) {
                if (strpos($line, '✓') !== false || strpos($line, '✗') !== false) {
                    $checks[] = $line;
                }
            }

            $result = [
                'action' => 'health',
                'checks' => $checks,
                'output' => $output,
            ];
            break;

        case 'health_api':
            $cmd = ['php', __DIR__ . '/../cli/vend-sync-manager.php', 'health:api'];
            $output = shell_exec(escapeshellcmd(implode(' ', $cmd)) . ' 2>&1');

            $result = [
                'action' => 'health_api',
                'output' => $output,
            ];
            break;

        // ─────────────────────────────────────────────────────────────────
        // AUDIT OPERATIONS
        // ─────────────────────────────────────────────────────────────────
        case 'audit_logs':
            $entity = $_GET['entity'] ?? $_POST['entity'] ?? null;
            $limit = $_GET['limit'] ?? $_POST['limit'] ?? 100;

            $db = db_ro();
            $sql = "SELECT * FROM vend_api_logs";
            $params = [];

            if ($entity) {
                $sql .= " WHERE entity_type = :entity";
                $params['entity'] = $entity;
            }

            $sql .= " ORDER BY created_at DESC LIMIT :limit";

            $stmt = $db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->bindValue('limit', (int)$limit, PDO::PARAM_INT);
            $stmt->execute();

            $result = [
                'action' => 'audit_logs',
                'logs' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            ];
            break;

        case 'audit_status':
            $db = db_ro();
            $stmt = $db->query("
                SELECT
                    entity_type,
                    status,
                    COUNT(*) as count,
                    MAX(created_at) as latest
                FROM vend_api_logs
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY entity_type, status
                ORDER BY entity_type, status
            ");

            $result = [
                'action' => 'audit_status',
                'stats' => $stmt->fetchAll(PDO::FETCH_ASSOC),
            ];
            break;

        // ─────────────────────────────────────────────────────────────────
        // UTILITY OPERATIONS
        // ─────────────────────────────────────────────────────────────────
        case 'version':
            $result = [
                'action' => 'version',
                'version' => '1.0.0',
                'cli_path' => __DIR__ . '/../cli/vend-sync-manager.php',
                'api_path' => __FILE__,
            ];
            break;

        default:
            throw new Exception("Unknown action: $action");
    }

    // Success response
    http_response_code(200);
    echo json_encode([
        'success' => true,
        'result' => $result,
        'timestamp' => date('c'),
    ], JSON_PRETTY_PRINT);

} catch (Exception $e) {
    // Error response
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage(),
        'timestamp' => date('c'),
    ], JSON_PRETTY_PRINT);
}
