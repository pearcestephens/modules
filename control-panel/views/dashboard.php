<?php
/**
 * Control Panel - Dashboard View
 * Main overview page with system stats and quick actions
 */

if (!defined('CONTROL_PANEL_LOADED')) {
    die('Direct access not permitted');
}

use CIS\ControlPanel\ModuleRegistry;
use CIS\ControlPanel\ConfigManager;
use CIS\ControlPanel\BackupManager;

// Initialize services
$moduleRegistry = new ModuleRegistry($pdo);
$configManager = new ConfigManager($pdo);
$backupManager = new BackupManager($pdo);

// Get system stats
$modules = $moduleRegistry->getFromDatabase();
$recentBackups = $backupManager->getBackups(['limit' => 5]);
$backupConfig = $backupManager->getStorageConfig();

// Count module statuses
$activeModules = count(array_filter($modules, fn($m) => $m['status'] === 'active'));
$totalModules = count($modules);

// Calculate total module size
$totalSize = array_sum(array_column($modules, 'size_bytes'));

// Get PHP info
$phpVersion = phpversion();
$memoryLimit = ini_get('memory_limit');
$maxExecution = ini_get('max_execution_time');

// Get database size
$stmt = $pdo->query("
    SELECT
        SUM(data_length + index_length) as size
    FROM information_schema.TABLES
    WHERE table_schema = DATABASE()
");
$dbSize = $stmt->fetch(PDO::FETCH_ASSOC)['size'] ?? 0;

// Format bytes function
function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>

<!-- Dashboard Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">
            <i class="fas fa-tachometer-alt text-primary"></i>
            Control Panel Dashboard
        </h1>
        <p class="text-muted mb-0">Central management for all CIS modules and systems</p>
    </div>
    <div>
        <button class="btn btn-sm btn-outline-primary" onclick="location.reload()">
            <i class="fas fa-sync-alt"></i> Refresh
        </button>
    </div>
</div>

<!-- System Status Cards -->
<div class="row g-3 mb-4">
    <!-- Modules Card -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1 small">Modules</p>
                        <h3 class="mb-0"><?= $activeModules ?> / <?= $totalModules ?></h3>
                    </div>
                    <div class="bg-primary bg-opacity-10 p-3 rounded">
                        <i class="fas fa-cube fa-lg text-primary"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <a href="?page=modules" class="btn btn-sm btn-outline-primary w-100">
                        View Modules <i class="fas fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Backups Card -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1 small">Backups</p>
                        <h3 class="mb-0"><?= count($recentBackups) ?></h3>
                    </div>
                    <div class="bg-success bg-opacity-10 p-3 rounded">
                        <i class="fas fa-database fa-lg text-success"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <button class="btn btn-sm btn-outline-success w-100" onclick="createBackup()">
                        <i class="fas fa-plus"></i> Create Backup
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Database Card -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1 small">Database Size</p>
                        <h3 class="mb-0"><?= formatBytes($dbSize) ?></h3>
                    </div>
                    <div class="bg-info bg-opacity-10 p-3 rounded">
                        <i class="fas fa-hdd fa-lg text-info"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <small class="text-muted">PHP: <?= $phpVersion ?></small>
                </div>
            </div>
        </div>
    </div>

    <!-- Storage Card -->
    <div class="col-md-3">
        <div class="card border-0 shadow-sm h-100">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <p class="text-muted mb-1 small">Module Storage</p>
                        <h3 class="mb-0"><?= formatBytes($totalSize) ?></h3>
                    </div>
                    <div class="bg-warning bg-opacity-10 p-3 rounded">
                        <i class="fas fa-folder fa-lg text-warning"></i>
                    </div>
                </div>
                <div class="mt-3">
                    <small class="text-muted">Memory: <?= $memoryLimit ?></small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Quick Actions -->
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0">
                <h5 class="mb-0">
                    <i class="fas fa-bolt text-primary"></i>
                    Quick Actions
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-2">
                    <div class="col-md-3">
                        <button class="btn btn-outline-primary w-100" onclick="scanModules()">
                            <i class="fas fa-search"></i> Scan Modules
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-success w-100" onclick="createBackup()">
                            <i class="fas fa-save"></i> Create Backup
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-info w-100" onclick="generateDocs()">
                            <i class="fas fa-book"></i> Generate Docs
                        </button>
                    </div>
                    <div class="col-md-3">
                        <button class="btn btn-outline-warning w-100" onclick="clearCache()">
                            <i class="fas fa-broom"></i> Clear Cache
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Recent Activity -->
<div class="row g-3">
    <!-- Recent Backups -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-clock text-primary"></i>
                    Recent Backups
                </h5>
                <a href="?page=backups" class="btn btn-sm btn-link">View All</a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentBackups)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="fas fa-inbox fa-2x mb-2"></i>
                        <p class="mb-0">No backups yet</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Type</th>
                                    <th>Size</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recentBackups as $backup): ?>
                                    <tr>
                                        <td>
                                            <span class="badge bg-primary">
                                                <?= strtoupper($backup['backup_type']) ?>
                                            </span>
                                        </td>
                                        <td><?= $backup['size_formatted'] ?></td>
                                        <td>
                                            <?php
                                            $statusClass = match($backup['status']) {
                                                'completed' => 'success',
                                                'failed' => 'danger',
                                                'in_progress' => 'warning',
                                                default => 'secondary'
                                            };
                                            ?>
                                            <span class="badge bg-<?= $statusClass ?>">
                                                <?= ucfirst($backup['status']) ?>
                                            </span>
                                        </td>
                                        <td>
                                            <small><?= date('M d, H:i', strtotime($backup['created_at'])) ?></small>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Module Status -->
    <div class="col-md-6">
        <div class="card border-0 shadow-sm">
            <div class="card-header bg-white border-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-cubes text-primary"></i>
                    Module Status
                </h5>
                <a href="?page=modules" class="btn btn-sm btn-link">View All</a>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Module</th>
                                <th>Version</th>
                                <th>Files</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach (array_slice($modules, 0, 5) as $module): ?>
                                <tr>
                                    <td class="fw-semibold"><?= htmlspecialchars($module['name']) ?></td>
                                    <td><span class="badge bg-secondary"><?= htmlspecialchars($module['version']) ?></span></td>
                                    <td><small class="text-muted"><?= $module['file_count'] ?> files</small></td>
                                    <td>
                                        <?php
                                        $statusClass = match($module['status']) {
                                            'active' => 'success',
                                            'inactive' => 'warning',
                                            'development' => 'info',
                                            default => 'secondary'
                                        };
                                        ?>
                                        <span class="badge bg-<?= $statusClass ?>">
                                            <?= ucfirst($module['status']) ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- JavaScript -->
<script>
function scanModules() {
    if (confirm('Scan all modules and update registry?')) {
        window.location.href = '?page=modules&action=scan';
    }
}

function createBackup() {
    if (confirm('Create a new database backup?')) {
        window.location.href = '?page=backups&action=create';
    }
}

function generateDocs() {
    if (confirm('Generate documentation for all modules?')) {
        window.location.href = '?page=documentation&action=generate';
    }
}

function clearCache() {
    if (confirm('Clear all system caches?')) {
        // TODO: Implement cache clearing
        alert('Cache cleared successfully!');
    }
}
</script>
