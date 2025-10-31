2#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Lightspeed Sync CLI Tool
 *
 * Comprehensive command-line interface for managing Lightspeed synchronization
 *
 * Usage:
 *   php lightspeed-cli.php <command> [options]
 *
 * Commands:
 *   sync:po <id>             - Sync single PO to Lightspeed
 *   sync:pending             - Sync all pending POs
 *   sync:status <id>         - Check sync status
 *   sync:retry <id>          - Retry failed sync
 *
 *   queue:work               - Start queue worker
 *   queue:stats              - Show queue statistics
 *   queue:list               - List recent jobs
 *   queue:retry <id>         - Retry failed job
 *   queue:cancel <id>        - Cancel job
 *   queue:clear              - Clear all jobs (DANGER)
 *   queue:prune <days>       - Prune old jobs
 *
 *   vend:test                - Test Vend API connection
 *   vend:outlets             - List all outlets
 *   vend:suppliers           - List all suppliers
 *   vend:products <sku>      - Search products by SKU
 *   vend:consignment <id>    - Get consignment details
 *
 *   webhook:list             - List webhook subscriptions
 *   webhook:create <url>     - Create webhook subscription
 *   webhook:delete <id>      - Delete webhook subscription
 *
 *   config:show              - Show current configuration
 *   config:set <key> <value> - Set configuration value
 *
 *   status                   - Show system status
 *   help                     - Show this help message
 *
 * @package CIS\CLI
 * @version 2.0.0
 * @author AI Assistant
 * @date 2025-10-31
 */

// Bootstrap
require_once __DIR__ . '/../../../app.php';
require_once __DIR__ . '/../../../assets/services/VendAPI.php';
require_once __DIR__ . '/../../../assets/services/QueueService.php';
require_once __DIR__ . '/../../../assets/services/LightspeedSyncService.php';

use CIS\Services\VendAPI;
use CIS\Services\QueueService;
use CIS\Services\LightspeedSyncService;

// CLI helper class
class CLI {
    private array $colors = [
        'reset' => "\033[0m",
        'bold' => "\033[1m",
        'red' => "\033[31m",
        'green' => "\033[32m",
        'yellow' => "\033[33m",
        'blue' => "\033[34m",
        'cyan' => "\033[36m",
    ];

    public function error(string $message): void {
        echo $this->colors['red'] . '✗ ' . $message . $this->colors['reset'] . PHP_EOL;
    }

    public function success(string $message): void {
        echo $this->colors['green'] . '✓ ' . $message . $this->colors['reset'] . PHP_EOL;
    }

    public function info(string $message): void {
        echo $this->colors['blue'] . 'ℹ ' . $message . $this->colors['reset'] . PHP_EOL;
    }

    public function warning(string $message): void {
        echo $this->colors['yellow'] . '⚠ ' . $message . $this->colors['reset'] . PHP_EOL;
    }

    public function header(string $message): void {
        echo PHP_EOL . $this->colors['bold'] . $this->colors['cyan'];
        echo str_repeat('=', 60) . PHP_EOL;
        echo $message . PHP_EOL;
        echo str_repeat('=', 60) . $this->colors['reset'] . PHP_EOL . PHP_EOL;
    }

    public function table(array $headers, array $rows): void {
        $widths = [];

        // Calculate column widths
        foreach ($headers as $i => $header) {
            $widths[$i] = strlen($header);
        }
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                $widths[$i] = max($widths[$i], strlen((string)$cell));
            }
        }

        // Print header
        echo $this->colors['bold'];
        foreach ($headers as $i => $header) {
            echo str_pad($header, $widths[$i] + 2);
        }
        echo $this->colors['reset'] . PHP_EOL;

        echo str_repeat('-', array_sum($widths) + count($widths) * 2) . PHP_EOL;

        // Print rows
        foreach ($rows as $row) {
            foreach ($row as $i => $cell) {
                echo str_pad((string)$cell, $widths[$i] + 2);
            }
            echo PHP_EOL;
        }
    }

    public function confirm(string $message): bool {
        echo $this->colors['yellow'] . $message . ' (y/N): ' . $this->colors['reset'];
        $answer = trim(fgets(STDIN));
        return strtolower($answer) === 'y';
    }

    public function progress(int $current, int $total, string $message = ''): void {
        $percent = ($current / $total) * 100;
        $bar = str_repeat('█', (int)($percent / 2));
        $empty = str_repeat('░', 50 - strlen($bar));

        echo "\r" . $this->colors['cyan'];
        echo sprintf('[%s%s] %3d%% ', $bar, $empty, $percent);
        if ($message) {
            echo $message;
        }
        echo $this->colors['reset'];

        if ($current >= $total) {
            echo PHP_EOL;
        }
    }
}

// Main CLI class
class LightspeedCLI {
    private PDO $pdo;
    private VendAPI $vend;
    private QueueService $queue;
    private LightspeedSyncService $sync;
    private CLI $cli;

    public function __construct() {
        global $pdo; // From app.php

        $this->pdo = $pdo;
        $this->cli = new CLI();

        // Initialize services
        try {
            $vendDomain = getenv('VEND_DOMAIN') ?: 'vapeshed';
            $vendToken = getenv('VEND_API_TOKEN');

            if (!$vendToken) {
                throw new Exception('VEND_API_TOKEN not configured');
            }

            $this->vend = new VendAPI($vendDomain, $vendToken);
            $this->queue = new QueueService($this->pdo);
            $this->sync = new LightspeedSyncService($this->pdo, $this->vend, $this->queue);

        } catch (Exception $e) {
            $this->cli->error('Initialization failed: ' . $e->getMessage());
            exit(1);
        }
    }

    public function run(array $argv): void {
        $command = $argv[1] ?? 'help';
        $args = array_slice($argv, 2);

        try {
            match($command) {
                'sync:po' => $this->syncPO($args),
                'sync:pending' => $this->syncPending($args),
                'sync:status' => $this->syncStatus($args),
                'sync:retry' => $this->syncRetry($args),

                'queue:work' => $this->queueWork($args),
                'queue:stats' => $this->queueStats($args),
                'queue:list' => $this->queueList($args),
                'queue:retry' => $this->queueRetry($args),
                'queue:cancel' => $this->queueCancel($args),
                'queue:clear' => $this->queueClear($args),
                'queue:prune' => $this->queuePrune($args),

                'vend:test' => $this->vendTest($args),
                'vend:outlets' => $this->vendOutlets($args),
                'vend:suppliers' => $this->vendSuppliers($args),
                'vend:products' => $this->vendProducts($args),
                'vend:consignment' => $this->vendConsignment($args),

                'webhook:list' => $this->webhookList($args),
                'webhook:create' => $this->webhookCreate($args),
                'webhook:delete' => $this->webhookDelete($args),

                'config:show' => $this->configShow($args),
                'config:set' => $this->configSet($args),

                'status' => $this->showStatus($args),
                'help' => $this->showHelp($args),

                default => $this->cli->error("Unknown command: {$command}") . $this->showHelp([])
            };
        } catch (Exception $e) {
            $this->cli->error('Error: ' . $e->getMessage());
            exit(1);
        }
    }

    // =========================================================================
    // SYNC COMMANDS
    // =========================================================================

    private function syncPO(array $args): void {
        if (empty($args[0])) {
            $this->cli->error('Usage: sync:po <po_id>');
            return;
        }

        $poId = $args[0];
        $async = !in_array('--sync', $args);

        $this->cli->header("Syncing Purchase Order: {$poId}");

        $result = $this->sync->syncPurchaseOrder($poId, $async);

        if ($result['ok']) {
            if ($result['queued'] ?? false) {
                $this->cli->success("Sync queued. Job ID: {$result['job_id']}");
            } else {
                $this->cli->success("Sync completed. Consignment ID: {$result['consignment_id']}");
            }
        } else {
            $this->cli->error('Sync failed: ' . ($result['error'] ?? 'Unknown error'));
        }
    }

    private function syncPending(array $args): void {
        $this->cli->header('Syncing All Pending Purchase Orders');

        // Get pending POs
        $sql = "
            SELECT id, public_id, total_cost
            FROM vend_consignments
            WHERE state = 'APPROVED'
            AND lightspeed_consignment_id IS NULL
            ORDER BY created_at ASC
            LIMIT 100
        ";

        $stmt = $this->pdo->query($sql);
        $pos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($pos)) {
            $this->cli->info('No pending POs to sync');
            return;
        }

        $this->cli->info('Found ' . count($pos) . ' POs to sync');

        $queued = 0;
        foreach ($pos as $i => $po) {
            $this->cli->progress($i + 1, count($pos), "PO #{$po['public_id']}");

            $result = $this->sync->syncPurchaseOrder($po['id'], true);
            if ($result['ok']) {
                $queued++;
            }
        }

        $this->cli->success("Queued {$queued} POs for sync");
    }

    private function syncStatus(array $args): void {
        if (empty($args[0])) {
            $this->cli->error('Usage: sync:status <sync_id>');
            return;
        }

        $syncId = $args[0];

        $sql = "SELECT * FROM lightspeed_sync_log WHERE id = ?";
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$syncId]);
        $log = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$log) {
            $this->cli->error('Sync log not found');
            return;
        }

        $this->cli->header("Sync Status: {$syncId}");

        echo "Entity ID:    {$log['entity_id']}\n";
        echo "Operation:    {$log['operation']}\n";
        echo "Status:       {$log['status']}\n";
        echo "Created:      {$log['created_at']}\n";
        echo "Completed:    " . ($log['completed_at'] ?? 'N/A') . "\n";

        if ($log['data']) {
            echo "\nData:\n";
            echo json_encode(json_decode($log['data']), JSON_PRETTY_PRINT);
        }

        if ($log['error_message']) {
            echo "\n";
            $this->cli->error("Error: {$log['error_message']}");
        }
    }

    private function syncRetry(array $args): void {
        if (empty($args[0])) {
            $this->cli->error('Usage: sync:retry <po_id>');
            return;
        }

        $poId = $args[0];

        // Reset sync status
        $sql = "
            UPDATE vend_consignments
            SET lightspeed_sync_status = NULL
            WHERE id = ?
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([$poId]);

        // Queue for sync
        $this->syncPO([$poId]);
    }

    // =========================================================================
    // QUEUE COMMANDS
    // =========================================================================

    private function queueWork(array $args): void {
        $this->cli->header('Starting Queue Worker');

        $sleep = (int)($args[0] ?? 3);

        $this->cli->info('Press Ctrl+C to stop');

        $shouldStop = false;
        pcntl_signal(SIGINT, function() use (&$shouldStop) {
            $shouldStop = true;
        });

        $this->queue->work($sleep, function() use (&$shouldStop) {
            pcntl_signal_dispatch();
            return $shouldStop;
        });
    }

    private function queueStats(array $args): void {
        $this->cli->header('Queue Statistics');

        $stats = $this->queue->getStats();

        echo "Total Jobs:    {$stats['total']}\n";
        echo "Pending:       {$stats['pending']}\n";
        echo "Processing:    {$stats['processing']}\n";
        echo "Completed:     {$stats['completed']}\n";
        echo "Failed:        {$stats['failed']}\n";
        echo "Cancelled:     {$stats['cancelled']}\n";
    }

    private function queueList(array $args): void {
        $status = $args[0] ?? null;
        $limit = (int)($args[1] ?? 20);

        $this->cli->header('Recent Queue Jobs');

        $jobs = $this->queue->getRecentJobs($limit, $status);

        if (empty($jobs)) {
            $this->cli->info('No jobs found');
            return;
        }

        $rows = [];
        foreach ($jobs as $job) {
            $rows[] = [
                substr($job['id'], 0, 20) . '...',
                $job['job_type'],
                $job['status'],
                $job['attempts'],
                $job['created_at']
            ];
        }

        $this->cli->table(
            ['Job ID', 'Type', 'Status', 'Attempts', 'Created'],
            $rows
        );
    }

    private function queueRetry(array $args): void {
        if (empty($args[0])) {
            $this->cli->error('Usage: queue:retry <job_id>');
            return;
        }

        $jobId = $args[0];

        if ($this->queue->retry($jobId)) {
            $this->cli->success('Job queued for retry');
        } else {
            $this->cli->error('Failed to retry job');
        }
    }

    private function queueCancel(array $args): void {
        if (empty($args[0])) {
            $this->cli->error('Usage: queue:cancel <job_id>');
            return;
        }

        $jobId = $args[0];

        if ($this->queue->cancel($jobId)) {
            $this->cli->success('Job cancelled');
        } else {
            $this->cli->error('Failed to cancel job');
        }
    }

    private function queueClear(array $args): void {
        if (!$this->cli->confirm('⚠ This will delete ALL jobs. Continue?')) {
            $this->cli->info('Cancelled');
            return;
        }

        $this->queue->clear();
        $this->cli->success('Queue cleared');
    }

    private function queuePrune(array $args): void {
        $days = (int)($args[0] ?? 7);

        $count = $this->queue->prune($days);
        $this->cli->success("Pruned {$count} old jobs");
    }

    // =========================================================================
    // VEND API COMMANDS
    // =========================================================================

    private function vendTest(array $args): void {
        $this->cli->header('Testing Vend API Connection');

        $result = $this->sync->testConnection();

        if ($result['ok']) {
            $this->cli->success('Connection successful');
            echo "User: " . ($result['user']['display_name'] ?? 'N/A') . "\n";
            echo "Rate Limit Remaining: {$result['rate_limit_remaining']}\n";
        } else {
            $this->cli->error('Connection failed: ' . $result['message']);
        }
    }

    private function vendOutlets(array $args): void {
        $this->cli->header('Vend Outlets');

        $response = $this->vend->listOutlets();

        if (!$response['ok']) {
            $this->cli->error('Failed to fetch outlets');
            return;
        }

        $outlets = $response['data']['outlets'] ?? [];

        $rows = [];
        foreach ($outlets as $outlet) {
            $rows[] = [
                $outlet['id'],
                $outlet['name'],
                $outlet['time_zone'] ?? 'N/A'
            ];
        }

        $this->cli->table(['ID', 'Name', 'Timezone'], $rows);
    }

    private function vendSuppliers(array $args): void {
        $this->cli->header('Vend Suppliers');

        $response = $this->vend->listSuppliers();

        if (!$response['ok']) {
            $this->cli->error('Failed to fetch suppliers');
            return;
        }

        $suppliers = $response['data']['suppliers'] ?? [];

        $rows = [];
        foreach ($suppliers as $supplier) {
            $rows[] = [
                $supplier['id'],
                $supplier['name'],
                $supplier['email'] ?? 'N/A'
            ];
        }

        $this->cli->table(['ID', 'Name', 'Email'], $rows);
    }

    private function vendProducts(array $args): void {
        if (empty($args[0])) {
            $this->cli->error('Usage: vend:products <sku>');
            return;
        }

        $sku = $args[0];

        $this->cli->header("Searching Products: {$sku}");

        $response = $this->vend->getProductBySku($sku);

        if (!$response['ok']) {
            $this->cli->error('Search failed');
            return;
        }

        $products = $response['data']['products'] ?? [];

        if (empty($products)) {
            $this->cli->info('No products found');
            return;
        }

        foreach ($products as $product) {
            echo "ID:          {$product['id']}\n";
            echo "Name:        {$product['name']}\n";
            echo "SKU:         {$product['sku']}\n";
            echo "Supplier:    " . ($product['supplier_name'] ?? 'N/A') . "\n";
            echo "Price:       " . ($product['retail_price'] ?? 'N/A') . "\n";
            echo "\n";
        }
    }

    private function vendConsignment(array $args): void {
        if (empty($args[0])) {
            $this->cli->error('Usage: vend:consignment <id>');
            return;
        }

        $consignmentId = $args[0];

        $this->cli->header("Consignment: {$consignmentId}");

        $response = $this->vend->getConsignment($consignmentId);

        if (!$response['ok']) {
            $this->cli->error('Failed to fetch consignment');
            return;
        }

        $consignment = $response['data']['consignment'] ?? null;

        if (!$consignment) {
            $this->cli->error('Consignment not found');
            return;
        }

        echo "Type:         {$consignment['type']}\n";
        echo "Status:       {$consignment['status']}\n";
        echo "Reference:    {$consignment['reference']}\n";
        echo "Destination:  {$consignment['destination_outlet_name']}\n";
        echo "Created:      {$consignment['created_at']}\n";
    }

    // =========================================================================
    // WEBHOOK COMMANDS
    // =========================================================================

    private function webhookList(array $args): void {
        $this->cli->header('Webhook Subscriptions');

        $response = $this->vend->listWebhooks();

        if (!$response['ok']) {
            $this->cli->error('Failed to fetch webhooks');
            return;
        }

        $webhooks = $response['data']['webhooks'] ?? [];

        if (empty($webhooks)) {
            $this->cli->info('No webhooks configured');
            return;
        }

        foreach ($webhooks as $webhook) {
            echo "ID:      {$webhook['id']}\n";
            echo "URL:     {$webhook['url']}\n";
            echo "Active:  " . ($webhook['active'] ? 'Yes' : 'No') . "\n";
            echo "Events:  " . implode(', ', $webhook['events']) . "\n";
            echo "\n";
        }
    }

    private function webhookCreate(array $args): void {
        if (empty($args[0])) {
            $this->cli->error('Usage: webhook:create <url>');
            return;
        }

        $url = $args[0];

        $events = [
            'consignment.update',
            'inventory.update',
            'sale.update',
            'product.update'
        ];

        $this->cli->info('Creating webhook subscription...');

        $response = $this->vend->createWebhook($url, $events, true);

        if ($response['ok']) {
            $webhookId = $response['data']['webhook']['id'] ?? 'N/A';
            $this->cli->success("Webhook created: {$webhookId}");
        } else {
            $this->cli->error('Failed to create webhook');
        }
    }

    private function webhookDelete(array $args): void {
        if (empty($args[0])) {
            $this->cli->error('Usage: webhook:delete <id>');
            return;
        }

        $webhookId = $args[0];

        $response = $this->vend->deleteWebhook($webhookId);

        if ($response['ok']) {
            $this->cli->success('Webhook deleted');
        } else {
            $this->cli->error('Failed to delete webhook');
        }
    }

    // =========================================================================
    // CONFIG COMMANDS
    // =========================================================================

    private function configShow(array $args): void {
        $this->cli->header('Lightspeed Sync Configuration');

        $sql = "
            SELECT config_value FROM system_config
            WHERE config_key = 'lightspeed_sync_config'
        ";

        $stmt = $this->pdo->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($row) {
            $config = json_decode($row['config_value'], true);
            echo json_encode($config, JSON_PRETTY_PRINT) . "\n";
        } else {
            $this->cli->info('No configuration found');
        }
    }

    private function configSet(array $args): void {
        if (count($args) < 2) {
            $this->cli->error('Usage: config:set <key> <value>');
            return;
        }

        [$key, $value] = $args;

        // Load current config
        $sql = "SELECT config_value FROM system_config WHERE config_key = 'lightspeed_sync_config'";
        $stmt = $this->pdo->query($sql);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $config = $row ? json_decode($row['config_value'], true) : [];

        // Parse value
        if ($value === 'true') $value = true;
        if ($value === 'false') $value = false;
        if (is_numeric($value)) $value = (int)$value;

        $config[$key] = $value;

        // Save
        $sql = "
            UPDATE system_config
            SET config_value = ?, updated_at = NOW()
            WHERE config_key = 'lightspeed_sync_config'
        ";

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([json_encode($config)]);

        $this->cli->success("Configuration updated: {$key} = {$value}");
    }

    // =========================================================================
    // STATUS COMMAND
    // =========================================================================

    private function showStatus(array $args): void {
        $this->cli->header('Lightspeed Sync System Status');

        // API connection
        $apiTest = $this->sync->testConnection();
        echo "API Connection:  ";
        if ($apiTest['ok']) {
            $this->cli->success('Connected');
        } else {
            $this->cli->error('Failed');
        }

        // Queue stats
        $queueStats = $this->queue->getStats();
        echo "\nQueue Status:\n";
        echo "  Pending:       {$queueStats['pending']}\n";
        echo "  Processing:    {$queueStats['processing']}\n";
        echo "  Completed:     {$queueStats['completed']}\n";
        echo "  Failed:        {$queueStats['failed']}\n";

        // Sync stats
        $syncStats = $this->sync->getStats();
        echo "\nSync Statistics (24h):\n";
        echo "  Total:         {$syncStats['total']}\n";
        echo "  Completed:     {$syncStats['completed']}\n";
        echo "  Failed:        {$syncStats['failed']}\n";
        echo "  In Progress:   {$syncStats['in_progress']}\n";
    }

    // =========================================================================
    // HELP COMMAND
    // =========================================================================

    private function showHelp(array $args): void {
        $this->cli->header('Lightspeed Sync CLI - Help');

        $commands = [
            'SYNC COMMANDS' => [
                'sync:po <id>' => 'Sync single PO to Lightspeed',
                'sync:pending' => 'Sync all pending POs',
                'sync:status <id>' => 'Check sync status',
                'sync:retry <id>' => 'Retry failed sync',
            ],
            'QUEUE COMMANDS' => [
                'queue:work [sleep]' => 'Start queue worker (default sleep: 3s)',
                'queue:stats' => 'Show queue statistics',
                'queue:list [status] [limit]' => 'List recent jobs',
                'queue:retry <id>' => 'Retry failed job',
                'queue:cancel <id>' => 'Cancel job',
                'queue:clear' => 'Clear all jobs (DANGER)',
                'queue:prune [days]' => 'Prune old jobs (default: 7 days)',
            ],
            'VEND API COMMANDS' => [
                'vend:test' => 'Test Vend API connection',
                'vend:outlets' => 'List all outlets',
                'vend:suppliers' => 'List all suppliers',
                'vend:products <sku>' => 'Search products by SKU',
                'vend:consignment <id>' => 'Get consignment details',
            ],
            'WEBHOOK COMMANDS' => [
                'webhook:list' => 'List webhook subscriptions',
                'webhook:create <url>' => 'Create webhook subscription',
                'webhook:delete <id>' => 'Delete webhook subscription',
            ],
            'CONFIG COMMANDS' => [
                'config:show' => 'Show current configuration',
                'config:set <key> <value>' => 'Set configuration value',
            ],
            'OTHER COMMANDS' => [
                'status' => 'Show system status',
                'help' => 'Show this help message',
            ],
        ];

        foreach ($commands as $section => $cmds) {
            echo $this->cli->colors['bold'] . $section . $this->cli->colors['reset'] . "\n";
            foreach ($cmds as $cmd => $desc) {
                echo sprintf("  %-35s %s\n", $this->cli->colors['cyan'] . $cmd . $this->cli->colors['reset'], $desc);
            }
            echo "\n";
        }
    }
}

// Run CLI
$cli = new LightspeedCLI();
$cli->run($argv);
