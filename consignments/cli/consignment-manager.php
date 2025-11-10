#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * ═══════════════════════════════════════════════════════════════════════════
 * LIGHTSPEED CONSIGNMENT MANAGER CLI - PRODUCTION ENTERPRISE GRADE
 * ═══════════════════════════════════════════════════════════════════════════
 *
 * Complete lifecycle management for Lightspeed consignments following the
 * official Lightspeed API model with full CRUD operations, state management,
 * and 3-tier sync (Lightspeed → Shadow → Regular tables).
 *
 * @package    CIS\Consignments\CLI
 * @version    3.0.0-enterprise
 * @author     AI Assistant (Enterprise Grade)
 * @copyright  2025 Ecigdis Limited / The Vape Shed
 * @license    Proprietary
 *
 * FEATURES:
 * ─────────────────────────────────────────────────────────────────────────
 * ✓ Complete Consignment Lifecycle (Create → Pack → Send → Receive → Complete)
 * ✓ Product Management (Add/Update/Remove/List)
 * ✓ Status Transitions (OPEN → PACKAGED → SENT → RECEIVING → RECEIVED)
 * ✓ Inventory Sync (Shadow Tables → Regular Tables → Lightspeed)
 * ✓ Webhook Processing (Inbound Lightspeed events)
 * ✓ Queue Management (Background jobs with retry logic)
 * ✓ Comprehensive Error Handling & Logging
 * ✓ Audit Trail (All operations logged)
 * ✓ Idempotent Operations (Safe to retry)
 * ✓ Dry-Run Mode (Test without committing)
 * ✓ Batch Operations (Bulk processing)
 * ✓ Health Monitoring
 * ✓ Database Validation
 *
 * USAGE:
 * ─────────────────────────────────────────────────────────────────────────
 * php consignment-manager.php <command> [options]
 *
 * CONSIGNMENT LIFECYCLE:
 *   create              Create new consignment
 *   get <id>            Get consignment details
 *   update <id>         Update consignment fields
 *   delete <id>         Delete consignment (soft delete)
 *   list                List consignments with filters
 *
 * PRODUCT OPERATIONS:
 *   product:add         Add product to consignment
 *   product:update      Update product quantity/cost
 *   product:remove      Remove product from consignment
 *   product:list        List all products in consignment
 *   product:bulk-add    Add multiple products at once
 *
 * STATUS TRANSITIONS:
 *   status:pack         Mark as PACKING (add products)
 *   status:packaged     Mark as PACKAGED (ready to send)
 *   status:send         Mark as SENT (in transit)
 *   status:receive      Start receiving process
 *   status:received     Mark as RECEIVED (complete)
 *   status:cancel       Cancel consignment
 *
 * SYNC OPERATIONS:
 *   sync:pull           Pull updates from Lightspeed
 *   sync:push           Push local changes to Lightspeed
 *   sync:shadow         Sync shadow tables
 *   sync:inventory      Update inventory counts
 *   sync:full           Full 3-tier sync
 *
 * QUEUE OPERATIONS:
 *   queue:work          Process queue jobs
 *   queue:stats         Show queue statistics
 *   queue:retry         Retry failed job
 *   queue:clear         Clear completed jobs
 *
 * MAINTENANCE:
 *   health              System health check
 *   validate            Validate database integrity
 *   cleanup             Clean orphaned records
 *   export <id>         Export consignment data
 *   import <file>       Import consignment data
 *
 * EXAMPLES:
 * ─────────────────────────────────────────────────────────────────────────
 * # Create consignment
 * php consignment-manager.php create \
 *   --from=OUTLET_001 \
 *   --to=OUTLET_002 \
 *   --type=OUTLET \
 *   --ref="Transfer #123"
 *
 * # Add products
 * php consignment-manager.php product:bulk-add <consignment_id> \
 *   --products="PROD_001:10,PROD_002:5,PROD_003:20"
 *
 * # Send consignment
 * php consignment-manager.php status:send <consignment_id> \
 *   --sync-lightspeed
 *
 * # Receive consignment
 * php consignment-manager.php status:receive <consignment_id> \
 *   --scan-mode
 *
 * # Full sync
 * php consignment-manager.php sync:full \
 *   --direction=bidirectional \
 *   --validate
 *
 * ═══════════════════════════════════════════════════════════════════════════
 */

// ═══════════════════════════════════════════════════════════════════════════
// BOOTSTRAP
// ═══════════════════════════════════════════════════════════════════════════

error_reporting(E_ALL);
ini_set('display_errors', '1');
set_time_limit(0);
date_default_timezone_set('Pacific/Auckland');

// Determine base path
$basePath = dirname(__DIR__, 3);
if (!file_exists("$basePath/app.php")) {
    $basePath = '/home/129337.cloudwaysapps.com/jcepnzzkmj/public_html';
}

require_once "$basePath/assets/services/gpt/src/Bootstrap.php";

// ═══════════════════════════════════════════════════════════════════════════
// CORE CLASSES
// ═══════════════════════════════════════════════════════════════════════════

/**
 * CLI Output Handler - Production Grade
 */
final class CLIOutput
{
    private const COLORS = [
        'reset'   => "\033[0m",
        'bold'    => "\033[1m",
        'dim'     => "\033[2m",
        'red'     => "\033[31m",
        'green'   => "\033[32m",
        'yellow'  => "\033[33m",
        'blue'    => "\033[34m",
        'magenta' => "\033[35m",
        'cyan'    => "\033[36m",
        'white'   => "\033[37m",
    ];

    private bool $verbose;
    private bool $noColor;
    private $logFile;

    public function __construct(bool $verbose = false, bool $noColor = false, ?string $logFile = null)
    {
        $this->verbose = $verbose;
        $this->noColor = $noColor;

        if ($logFile) {
            $this->logFile = fopen($logFile, 'a');
        }
    }

    public function __destruct()
    {
        if ($this->logFile) {
            fclose($this->logFile);
        }
    }

    public function title(string $text): void
    {
        $this->writeln('');
        $this->writeln(str_repeat('═', 80), 'cyan');
        $this->writeln("  $text", 'cyan', true);
        $this->writeln(str_repeat('═', 80), 'cyan');
        $this->writeln('');
    }

    public function section(string $text): void
    {
        $this->writeln('');
        $this->writeln(str_repeat('─', 80), 'blue');
        $this->writeln("  $text", 'blue', true);
        $this->writeln(str_repeat('─', 80), 'blue');
    }

    public function success(string $message): void
    {
        $this->writeln("✓ $message", 'green');
    }

    public function error(string $message): void
    {
        $this->writeln("✗ $message", 'red');
    }

    public function warning(string $message): void
    {
        $this->writeln("⚠ $message", 'yellow');
    }

    public function info(string $message): void
    {
        $this->writeln("ℹ $message", 'blue');
    }

    public function debug(string $message): void
    {
        if ($this->verbose) {
            $this->writeln("  → $message", 'dim');
        }
    }

    public function table(array $headers, array $rows): void
    {
        if (empty($rows)) {
            $this->info('No data to display');
            return;
        }

        // Calculate column widths
        $widths = array_map('strlen', $headers);
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i], strlen((string)$cell));
            }
        }

        // Header
        $this->writeln('');
        $headerStr = '│ ';
        foreach ($headers as $i => $header) {
            $headerStr .= str_pad($header, $widths[$i]) . ' │ ';
        }
        $this->writeln(rtrim($headerStr), 'bold');

        // Separator
        $sepStr = '├─';
        foreach ($widths as $width) {
            $sepStr .= str_repeat('─', $width) . '─┼─';
        }
        $this->writeln(rtrim($sepStr, '┼─') . '┤');

        // Rows
        foreach ($rows as $row) {
            $rowStr = '│ ';
            foreach ($row as $i => $cell) {
                $rowStr .= str_pad((string)$cell, $widths[$i]) . ' │ ';
            }
            $this->writeln(rtrim($rowStr));
        }
        $this->writeln('');
    }

    public function progress(int $current, int $total, string $message = ''): void
    {
        $percent = ($total > 0) ? (int)(($current / $total) * 100) : 0;
        $bar = str_repeat('█', (int)($percent / 2)) . str_repeat('░', 50 - (int)($percent / 2));

        $line = sprintf(
            "\r[%s] %3d%% (%d/%d) %s",
            $bar,
            $percent,
            $current,
            $total,
            $message
        );

        echo $line;

        if ($current === $total) {
            echo PHP_EOL;
        }
    }

    public function confirm(string $message, bool $default = false): bool
    {
        $suffix = $default ? '[Y/n]' : '[y/N]';
        $this->write("$message $suffix: ", 'yellow');

        $handle = fopen('php://stdin', 'r');
        $line = trim(fgets($handle));
        fclose($handle);

        if ($line === '') {
            return $default;
        }

        return in_array(strtolower($line), ['y', 'yes', '1', 'true']);
    }

    public function ask(string $question, ?string $default = null): string
    {
        $suffix = $default ? "[$default]" : '';
        $this->write("$question $suffix: ", 'cyan');

        $handle = fopen('php://stdin', 'r');
        $line = trim(fgets($handle));
        fclose($handle);

        return $line ?: ($default ?? '');
    }

    private function writeln(string $text, ?string $color = null, bool $bold = false): void
    {
        $this->write($text . PHP_EOL, $color, $bold);
    }

    private function write(string $text, ?string $color = null, bool $bold = false): void
    {
        $output = $text;

        if (!$this->noColor && $color) {
            $prefix = self::COLORS[$color] ?? '';
            if ($bold && isset(self::COLORS['bold'])) {
                $prefix = self::COLORS['bold'] . $prefix;
            }
            $output = $prefix . $text . self::COLORS['reset'];
        }

        echo $output;

        // Log to file if enabled
        if ($this->logFile) {
            fwrite($this->logFile, strip_tags($text));
        }
    }
}

/**
 * Configuration Manager - Centralized Config
 */
final class ConfigManager
{
    private array $config = [];
    private PDO $db;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->loadConfig();
    }

    private function loadConfig(): void
    {
        // Load from environment
        $this->config = [
            'lightspeed' => [
                'base_url' => getenv('LS_BASE_URL') ?: 'https://api.vendhq.com/api/2.0',
                'token' => getenv('LS_TOKEN') ?: '',
                'timeout' => (int)(getenv('LS_TIMEOUT') ?: 30),
                'retry_attempts' => (int)(getenv('LS_RETRY_ATTEMPTS') ?: 3),
            ],
            'database' => [
                'shadow_tables' => ['vend_consignments', 'vend_consignment_line_items', 'vend_consignment_queue'],
                'queue_tables' => ['queue_consignments', 'queue_consignment_products', 'queue_consignment_state_transitions'],
                'regular_tables' => ['consignment_logs', 'consignment_audit_log', 'consignment_shipments'],
            ],
            'sync' => [
                'auto_sync' => (bool)(getenv('CONSIGNMENT_AUTO_SYNC') ?: true),
                'sync_direction' => getenv('CONSIGNMENT_SYNC_DIRECTION') ?: 'bidirectional', // pull, push, bidirectional
                'batch_size' => (int)(getenv('CONSIGNMENT_BATCH_SIZE') ?: 50),
            ],
            'logging' => [
                'enabled' => true,
                'level' => getenv('LOG_LEVEL') ?: 'info', // debug, info, warning, error
                'file' => '/home/129337.cloudwaysapps.com/jcepnzzkmj/private_html/logs/consignment-cli.log',
            ],
        ];

        // Load additional config from database
        try {
            $stmt = $this->db->query("SELECT config_key, config_value FROM consignment_config WHERE enabled = 1");
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $this->config['db'][$row['config_key']] = json_decode($row['config_value'], true) ?? $row['config_value'];
            }
        } catch (\Throwable $e) {
            // Config table may not exist, continue with defaults
        }
    }

    public function get(string $key, $default = null)
    {
        $keys = explode('.', $key);
        $value = $this->config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    public function set(string $key, $value): void
    {
        $keys = explode('.', $key);
        $config = &$this->config;

        foreach ($keys as $i => $k) {
            if ($i === count($keys) - 1) {
                $config[$k] = $value;
            } else {
                if (!isset($config[$k]) || !is_array($config[$k])) {
                    $config[$k] = [];
                }
                $config = &$config[$k];
            }
        }
    }

    public function all(): array
    {
        return $this->config;
    }
}

/**
 * Lightspeed API Client - Production Grade with Retry Logic
 */
final class LightspeedAPIClient
{
    private string $baseUrl;
    private string $token;
    private int $timeout;
    private int $maxRetries;
    private CLIOutput $output;

    public function __construct(ConfigManager $config, CLIOutput $output)
    {
        $this->baseUrl = rtrim($config->get('lightspeed.base_url'), '/');
        $this->token = $config->get('lightspeed.token');
        $this->timeout = $config->get('lightspeed.timeout', 30);
        $this->maxRetries = $config->get('lightspeed.retry_attempts', 3);
        $this->output = $output;

        if (empty($this->token)) {
            throw new \RuntimeException('Lightspeed API token not configured');
        }
    }

    /**
     * Create consignment in Lightspeed
     */
    public function createConsignment(array $data): array
    {
        return $this->request('POST', '/consignments', [
            'type' => $data['type'] ?? 'OUTLET',
            'status' => $data['status'] ?? 'OPEN',
            'source_outlet_id' => $data['source_outlet_id'],
            'outlet_id' => $data['outlet_id'],
            'name' => $data['name'] ?? '',
            'reference' => $data['reference'] ?? '',
        ]);
    }

    /**
     * Get consignment details
     */
    public function getConsignment(string $consignmentId): array
    {
        return $this->request('GET', "/consignments/{$consignmentId}");
    }

    /**
     * Update consignment
     */
    public function updateConsignment(string $consignmentId, array $data): array
    {
        return $this->request('PUT', "/consignments/{$consignmentId}", $data);
    }

    /**
     * Delete consignment
     */
    public function deleteConsignment(string $consignmentId): array
    {
        return $this->request('DELETE', "/consignments/{$consignmentId}");
    }

    /**
     * List consignments
     */
    public function listConsignments(array $filters = []): array
    {
        $query = http_build_query($filters);
        return $this->request('GET', "/consignments" . ($query ? "?$query" : ''));
    }

    /**
     * Add product to consignment
     */
    public function addProduct(string $consignmentId, array $product): array
    {
        return $this->request('POST', "/consignments/{$consignmentId}/products", [
            'product_id' => $product['product_id'],
            'count' => $product['count'],
            'cost' => $product['cost'] ?? null,
        ]);
    }

    /**
     * Update product in consignment
     */
    public function updateProduct(string $consignmentId, string $productId, array $data): array
    {
        return $this->request('PUT', "/consignments/{$consignmentId}/products/{$productId}", $data);
    }

    /**
     * Remove product from consignment
     */
    public function removeProduct(string $consignmentId, string $productId): array
    {
        return $this->request('DELETE', "/consignments/{$consignmentId}/products/{$productId}");
    }

    /**
     * List products in consignment
     */
    public function listProducts(string $consignmentId): array
    {
        return $this->request('GET', "/consignments/{$consignmentId}/products");
    }

    /**
     * Update consignment status
     */
    public function updateStatus(string $consignmentId, string $status): array
    {
        // Get current consignment data first
        $current = $this->getConsignment($consignmentId);
        if (!$current['ok']) {
            return $current;
        }

        return $this->request('PUT', "/consignments/{$consignmentId}", [
            'type' => $current['data']['type'],
            'outlet_id' => $current['data']['outlet_id'],
            'source_outlet_id' => $current['data']['source_outlet_id'],
            'status' => strtoupper($status),
            'name' => $current['data']['name'],
            'reference' => $current['data']['reference'],
        ]);
    }

    /**
     * Low-level HTTP request with retry logic
     */
    private function request(string $method, string $endpoint, ?array $data = null): array
    {
        $url = $this->baseUrl . $endpoint;
        $attempt = 0;
        $lastError = null;

        while ($attempt < $this->maxRetries) {
            $attempt++;

            try {
                $ch = curl_init($url);

                curl_setopt_array($ch, [
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT => $this->timeout,
                    CURLOPT_HTTPHEADER => [
                        'Authorization: Bearer ' . $this->token,
                        'Content-Type: application/json',
                        'Accept: application/json',
                        'X-Request-ID: ' . uniqid('req-', true),
                    ],
                ]);

                if ($method !== 'GET') {
                    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
                    if ($data !== null) {
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    }
                }

                $response = curl_exec($ch);
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $error = curl_error($ch);
                curl_close($ch);

                if ($error) {
                    throw new \RuntimeException("cURL error: $error");
                }

                $json = json_decode($response, true);

                // Success
                if ($httpCode >= 200 && $httpCode < 300) {
                    return [
                        'ok' => true,
                        'status' => $httpCode,
                        'data' => $json,
                    ];
                }

                // Rate limited - wait and retry
                if ($httpCode === 429) {
                    $retryAfter = $json['retry_after'] ?? (2 ** $attempt);
                    $this->output->warning("Rate limited. Retrying in {$retryAfter}s (attempt $attempt/{$this->maxRetries})");
                    sleep($retryAfter);
                    continue;
                }

                // Server error - retry with backoff
                if ($httpCode >= 500) {
                    $backoff = 2 ** $attempt;
                    $this->output->warning("Server error $httpCode. Retrying in {$backoff}s (attempt $attempt/{$this->maxRetries})");
                    sleep($backoff);
                    continue;
                }

                // Client error - don't retry
                return [
                    'ok' => false,
                    'status' => $httpCode,
                    'error' => $json['error'] ?? $json['message'] ?? "HTTP $httpCode",
                    'data' => $json,
                ];

            } catch (\Throwable $e) {
                $lastError = $e->getMessage();
                if ($attempt < $this->maxRetries) {
                    $backoff = 2 ** $attempt;
                    $this->output->warning("Request failed: {$lastError}. Retrying in {$backoff}s");
                    sleep($backoff);
                }
            }
        }

        return [
            'ok' => false,
            'status' => 0,
            'error' => $lastError ?? 'Max retries exceeded',
        ];
    }
}

/**
 * Database Manager - Handles 3-tier sync (Lightspeed → Shadow → Regular)
 */
final class DatabaseManager
{
    private PDO $db;
    private CLIOutput $output;
    private ConfigManager $config;

    public function __construct(PDO $db, ConfigManager $config, CLIOutput $output)
    {
        $this->db = $db;
        $this->config = $config;
        $this->output = $output;
    }

    /**
     * Sync consignment from Lightspeed to shadow tables
     */
    public function syncToShadow(array $lightspeedData): array
    {
        try {
            $this->db->beginTransaction();

            // Insert/Update vend_consignments
            $stmt = $this->db->prepare("
                INSERT INTO vend_consignments (
                    id, outlet_from, outlet_to, state, vend_transfer_id,
                    vend_consignment_id, reference, created_at, updated_at
                ) VALUES (
                    :id, :outlet_from, :outlet_to, :state, :vend_transfer_id,
                    :vend_consignment_id, :reference, NOW(), NOW()
                )
                ON DUPLICATE KEY UPDATE
                    state = VALUES(state),
                    updated_at = NOW()
            ");

            $stmt->execute([
                'id' => $lightspeedData['id'] ?? null,
                'outlet_from' => $lightspeedData['source_outlet_id'],
                'outlet_to' => $lightspeedData['outlet_id'],
                'state' => $lightspeedData['status'],
                'vend_transfer_id' => $lightspeedData['id'],
                'vend_consignment_id' => $lightspeedData['id'],
                'reference' => $lightspeedData['reference'] ?? '',
            ]);

            $shadowId = $this->db->lastInsertId() ?: $lightspeedData['id'];

            $this->db->commit();

            return ['ok' => true, 'shadow_id' => $shadowId];

        } catch (\Throwable $e) {
            $this->db->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Sync shadow to regular tables
     */
    public function syncToRegular(string $shadowId): array
    {
        try {
            $this->db->beginTransaction();

            // Get shadow data
            $stmt = $this->db->prepare("SELECT * FROM vend_consignments WHERE id = ?");
            $stmt->execute([$shadowId]);
            $shadow = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$shadow) {
                throw new \RuntimeException("Shadow consignment not found: $shadowId");
            }

            // Insert into queue_consignments
            $stmt = $this->db->prepare("
                INSERT INTO queue_consignments (
                    vend_consignment_id, transfer_id, type, status,
                    outlet_from_id, outlet_to_id, created_at, updated_at
                ) VALUES (
                    :vend_id, :transfer_id, 'OUTLET', :status,
                    :outlet_from, :outlet_to, NOW(), NOW()
                )
                ON DUPLICATE KEY UPDATE
                    status = VALUES(status),
                    updated_at = NOW()
            ");

            $stmt->execute([
                'vend_id' => $shadow['vend_consignment_id'],
                'transfer_id' => $shadow['id'],
                'status' => $shadow['state'],
                'outlet_from' => $shadow['outlet_from'],
                'outlet_to' => $shadow['outlet_to'],
            ]);

            // Log to consignment_logs
            $this->logEvent($shadowId, 'sync_to_regular', ['success' => true]);

            $this->db->commit();

            return ['ok' => true];

        } catch (\Throwable $e) {
            $this->db->rollBack();
            return ['ok' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Log consignment event
     */
    public function logEvent(string $consignmentId, string $eventType, array $details = []): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO consignment_logs (
                    consignment_id, event_type, severity, details,
                    created_by, source_system, created_at
                ) VALUES (
                    ?, ?, 'info', ?, 'cli', 'CIS', NOW()
                )
            ");

            $stmt->execute([
                $consignmentId,
                $eventType,
                json_encode($details),
            ]);
        } catch (\Throwable $e) {
            $this->output->debug("Failed to log event: " . $e->getMessage());
        }
    }

    /**
     * Get consignment by ID (from any tier)
     */
    public function getConsignment(string $id, string $tier = 'shadow'): ?array
    {
        $tables = [
            'shadow' => 'vend_consignments',
            'queue' => 'queue_consignments',
            'regular' => 'consignment_logs',
        ];

        $table = $tables[$tier] ?? $tables['shadow'];

        $stmt = $this->db->prepare("SELECT * FROM $table WHERE id = ? OR vend_consignment_id = ? LIMIT 1");
        $stmt->execute([$id, $id]);

        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * List consignments with filters
     */
    public function listConsignments(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        if (!empty($filters['status'])) {
            $where[] = 'state = ?';
            $params[] = $filters['status'];
        }

        if (!empty($filters['outlet_from'])) {
            $where[] = 'outlet_from = ?';
            $params[] = $filters['outlet_from'];
        }

        if (!empty($filters['outlet_to'])) {
            $where[] = 'outlet_to = ?';
            $params[] = $filters['outlet_to'];
        }

        $limit = $filters['limit'] ?? 50;
        $offset = $filters['offset'] ?? 0;

        $sql = sprintf(
            "SELECT * FROM vend_consignments WHERE %s ORDER BY created_at DESC LIMIT %d OFFSET %d",
            implode(' AND ', $where),
            $limit,
            $offset
        );

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

// CONTINUE IN NEXT MESSAGE - Building the complete command system...
