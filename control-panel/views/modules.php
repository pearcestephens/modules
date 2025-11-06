<?php
/**
 * Control Panel - Modules View
 * Module inventory, management, and configuration
 */

if (!defined('CONTROL_PANEL_LOADED')) {
    die('Direct access not permitted');
}

use CIS\ControlPanel\ModuleRegistry;
use CIS\ControlPanel\DocumentationBuilder;

// Initialize services
$moduleRegistry = new ModuleRegistry($pdo);
$docBuilder = new DocumentationBuilder($pdo);

// Handle actions
$message = null;
$messageType = 'success';

if (isset($_GET['action'])) {
    switch ($_GET['action']) {
        case 'scan':
            $result = $moduleRegistry->discoverModules();
            $message = "Discovered {$result['discovered']} modules, updated {$result['updated']} records";
            break;

        case 'generate_docs':
            if (isset($_GET['module'])) {
                $result = $docBuilder->generateModuleDocs($_GET['module']);
                $message = $result['success']
                    ? "Documentation generated for {$_GET['module']}"
                    : "Failed: {$result['error']}";
                $messageType = $result['success'] ? 'success' : 'danger';
            }
            break;
    }
}

// Get all modules
$modules = $moduleRegistry->getFromDatabase();

// Sort by name
usort($modules, fn($a, $b) => strcmp($a['name'], $b['name']));

// Calculate totals
$totalModules = count($modules);
$activeModules = count(array_filter($modules, fn($m) => $m['status'] === 'active'));
$totalFiles = array_sum(array_column($modules, 'file_count'));
$totalSize = array_sum(array_column($modules, 'size_bytes'));

function formatBytes($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    $bytes /= (1 << (10 * $pow));
    return round($bytes, 2) . ' ' . $units[$pow];
}
?>

<!-- Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h3 mb-0">
            <i class="fas fa-cubes text-primary"></i>
            Module Manager
        </h1>
        <p class="text-muted mb-0">Manage and configure all CIS modules</p>
    </div>
    <div>
        <button class="btn btn-primary" onclick="scanModules()">
            <i class="fas fa-search"></i> Scan Modules
        </button>
    </div>
</div>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType ?> alert-dismissible fade show" role="alert">
        <i class="fas fa-<?= $messageType === 'success' ? 'check-circle' : 'exclamation-triangle' ?>"></i>
        <?= htmlspecialchars($message) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- Summary Stats -->
<div class="row g-3 mb-4">
    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Total Modules</p>
                        <h4 class="mb-0"><?= $totalModules ?></h4>
                    </div>
                    <div class="bg-primary bg-opacity-10 p-2 rounded">
                        <i class="fas fa-cube fa-lg text-primary"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Active Modules</p>
                        <h4 class="mb-0"><?= $activeModules ?></h4>
                    </div>
                    <div class="bg-success bg-opacity-10 p-2 rounded">
                        <i class="fas fa-check-circle fa-lg text-success"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Total Files</p>
                        <h4 class="mb-0"><?= number_format($totalFiles) ?></h4>
                    </div>
                    <div class="bg-info bg-opacity-10 p-2 rounded">
                        <i class="fas fa-file fa-lg text-info"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card border-0 shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <p class="text-muted mb-1 small">Total Size</p>
                        <h4 class="mb-0"><?= formatBytes($totalSize) ?></h4>
                    </div>
                    <div class="bg-warning bg-opacity-10 p-2 rounded">
                        <i class="fas fa-hdd fa-lg text-warning"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Search & Filter -->
<div class="card border-0 shadow-sm mb-4">
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-6">
                <input type="text"
                       class="form-control"
                       id="moduleSearch"
                       placeholder="Search modules by name..."
                       onkeyup="filterModules()">
            </div>
            <div class="col-md-3">
                <select class="form-select" id="statusFilter" onchange="filterModules()">
                    <option value="">All Statuses</option>
                    <option value="active">Active</option>
                    <option value="inactive">Inactive</option>
                    <option value="development">Development</option>
                </select>
            </div>
            <div class="col-md-3">
                <button class="btn btn-outline-secondary w-100" onclick="resetFilters()">
                    <i class="fas fa-times"></i> Clear Filters
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modules Grid -->
<div class="row g-3" id="modulesGrid">
    <?php foreach ($modules as $module): ?>
        <div class="col-md-6 col-lg-4 module-card"
             data-name="<?= strtolower($module['name']) ?>"
             data-status="<?= $module['status'] ?>">
            <div class="card border-0 shadow-sm h-100">
                <div class="card-body">
                    <!-- Module Header -->
                    <div class="d-flex justify-content-between align-items-start mb-3">
                        <div class="flex-grow-1">
                            <h5 class="mb-1">
                                <i class="fas fa-cube text-primary"></i>
                                <?= htmlspecialchars($module['name']) ?>
                            </h5>
                            <p class="text-muted small mb-2">
                                <?= htmlspecialchars($module['description'] ?: 'No description') ?>
                            </p>
                        </div>
                        <?php
                        $statusClass = match($module['status']) {
                            'active' => 'success',
                            'inactive' => 'warning',
                            'development' => 'info',
                            default => 'secondary'
                        };
                        ?>
                        <span class="badge bg-<?= $statusClass ?> ms-2">
                            <?= ucfirst($module['status']) ?>
                        </span>
                    </div>

                    <!-- Module Info -->
                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <small class="text-muted d-block">Version</small>
                            <strong><?= htmlspecialchars($module['version']) ?></strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block">Files</small>
                            <strong><?= number_format($module['file_count']) ?></strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block">Size</small>
                            <strong><?= $module['size_formatted'] ?></strong>
                        </div>
                        <div class="col-6">
                            <small class="text-muted d-block">Updated</small>
                            <strong><?= date('M d', strtotime($module['last_modified'])) ?></strong>
                        </div>
                    </div>

                    <?php if ($module['author']): ?>
                        <div class="mb-3">
                            <small class="text-muted">
                                <i class="fas fa-user"></i> <?= htmlspecialchars($module['author']) ?>
                            </small>
                        </div>
                    <?php endif; ?>

                    <!-- Actions -->
                    <div class="d-grid gap-2">
                        <div class="btn-group" role="group">
                            <button class="btn btn-sm btn-outline-primary"
                                    onclick="generateDocs('<?= $module['name'] ?>')">
                                <i class="fas fa-book"></i> Docs
                            </button>
                            <button class="btn btn-sm btn-outline-info"
                                    onclick="viewFiles('<?= $module['name'] ?>')">
                                <i class="fas fa-folder"></i> Files
                            </button>
                            <button class="btn btn-sm btn-outline-secondary"
                                    onclick="viewConfig('<?= $module['name'] ?>')">
                                <i class="fas fa-cog"></i> Config
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Empty State -->
<div id="emptyState" class="text-center py-5 d-none">
    <i class="fas fa-search fa-3x text-muted mb-3"></i>
    <h5 class="text-muted">No modules found</h5>
    <p class="text-muted">Try adjusting your search or filters</p>
</div>

<!-- JavaScript -->
<script>
function scanModules() {
    if (confirm('Scan for new or updated modules?')) {
        window.location.href = '?page=modules&action=scan';
    }
}

function generateDocs(moduleName) {
    if (confirm(`Generate documentation for ${moduleName}?`)) {
        window.location.href = `?page=modules&action=generate_docs&module=${moduleName}`;
    }
}

function viewFiles(moduleName) {
    alert(`File browser for ${moduleName} - Coming soon!`);
    // TODO: Implement file browser modal
}

function viewConfig(moduleName) {
    window.location.href = `?page=config&module=${moduleName}`;
}

function filterModules() {
    const searchTerm = document.getElementById('moduleSearch').value.toLowerCase();
    const statusFilter = document.getElementById('statusFilter').value;
    const cards = document.querySelectorAll('.module-card');
    let visibleCount = 0;

    cards.forEach(card => {
        const name = card.dataset.name;
        const status = card.dataset.status;

        const matchesSearch = name.includes(searchTerm);
        const matchesStatus = !statusFilter || status === statusFilter;

        if (matchesSearch && matchesStatus) {
            card.classList.remove('d-none');
            visibleCount++;
        } else {
            card.classList.add('d-none');
        }
    });

    // Show/hide empty state
    const emptyState = document.getElementById('emptyState');
    const grid = document.getElementById('modulesGrid');

    if (visibleCount === 0) {
        emptyState.classList.remove('d-none');
        grid.classList.add('d-none');
    } else {
        emptyState.classList.add('d-none');
        grid.classList.remove('d-none');
    }
}

function resetFilters() {
    document.getElementById('moduleSearch').value = '';
    document.getElementById('statusFilter').value = '';
    filterModules();
}
</script>
