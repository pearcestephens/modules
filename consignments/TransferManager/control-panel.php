<?php
/**
 * Transfer System Control Panel
 * Professional admin interface for managing stock transfers
 * USING NEW BASE MODULE TEMPLATE SYSTEM
 */

// Include Base Module bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/base/bootstrap.php';

// Set page variables for template
$pageTitle = "Transfer System Control Panel";
$pageCSS = [];
$pageJS = [];
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/', 'icon' => 'fa-home'],
    ['label' => 'Consignments', 'url' => '/modules/consignments/'],
    ['label' => 'Transfer Control Panel', 'url' => '', 'active' => true]
];

// Database connection from CIS\Base\Database
$pdo = CIS\Base\Database::pdo();
$db = $pdo;

// Load system stats
$stats = [
    'open_transfers' => 0,
    'in_transit' => 0,
    'pending_receive' => 0,
    'sync_queue' => 0,
    'total_value' => 0
];

// Open transfers
$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM vend_consignments WHERE state = 'OPEN' AND transfer_category = 'STOCK_TRANSFER'");
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $stats['open_transfers'] = (int)$row['cnt'];
}

// In transit
$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM vend_consignments WHERE state IN ('SENT', 'RECEIVING') AND transfer_category = 'STOCK_TRANSFER'");
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $stats['in_transit'] = (int)$row['cnt'];
}

// Pending receive (RECEIVING or PARTIAL state)
$stmt = $pdo->query("SELECT COUNT(*) as cnt, SUM(total_cost) as val FROM vend_consignments WHERE state IN ('RECEIVING', 'PARTIAL') AND transfer_category = 'STOCK_TRANSFER'");
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $stats['pending_receive'] = (int)$row['cnt'];
    $stats['total_value'] = (float)$row['val'];
}

// Sync queue
$stmt = $pdo->query("SELECT COUNT(*) as cnt FROM vend_consignment_queue WHERE status IN ('pending', 'processing')");
if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $stats['sync_queue'] = (int)$row['cnt'];
}

// Get outlets
$outlets = [];
$stmt = $pdo->query("SELECT id, name, store_code FROM vend_outlets WHERE deleted_at IS NULL ORDER BY name ASC");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    $outlets[] = $row;
}

// Check sync enabled status
$syncFile = __DIR__ . '/.sync_enabled';
$syncEnabled = true;
if (file_exists($syncFile)) {
    $syncEnabled = (trim(file_get_contents($syncFile)) === '1');
}

// Handle AJAX requests
if (isset($_POST['action'])) {
    header('Content-Type: application/json');

    switch ($_POST['action']) {
        case 'toggle_sync':
            $newState = isset($_POST['enable']) && $_POST['enable'] === '1' ? '1' : '0';
            file_put_contents($syncFile, $newState);
            echo json_encode(['success' => true, 'enabled' => $newState === '1']);
            exit;

        case 'get_analytics':
            // Get transfer analytics
            $analytics = [];

            // Transfers by state (last 30 days)
            $stmt = $pdo->query("
                SELECT
                    state,
                    COUNT(*) as count,
                    SUM(total_cost) as value,
                    SUM(total_count) as units
                FROM vend_consignments
                WHERE transfer_category = 'STOCK_TRANSFER'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY state
            ");
            $analytics['by_state'] = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $analytics['by_state'][] = $row;
            }

            // Top routes (last 30 days)
            $stmt = $pdo->query("
                SELECT
                    c.outlet_from as from_outlet,
                    c.outlet_to as to_outlet,
                    COUNT(*) as transfer_count,
                    SUM(c.total_count) as total_units
                FROM vend_consignments c
                WHERE c.transfer_category = 'STOCK_TRANSFER'
                AND c.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                GROUP BY c.outlet_from, c.outlet_to
                ORDER BY transfer_count DESC
                LIMIT 10
            ");
            $analytics['top_routes'] = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $analytics['top_routes'][] = $row;
            }

            // Sync queue status
            $stmt = $pdo->query("
                SELECT
                    status,
                    COUNT(*) as count,
                    AVG(TIMESTAMPDIFF(SECOND, created_at, COALESCE(completed_at, NOW()))) as avg_duration
                FROM vend_consignment_queue
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY status
            ");
            $analytics['sync_status'] = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $analytics['sync_status'][] = $row;
            }

            echo json_encode(['success' => true, 'data' => $analytics]);
            exit;
    }
}
?>
<?php
// Start capturing content
ob_start();
?>
    <style>
        /* Page-specific utility classes */
        .h2 {
            font-size: 1.75rem;
            font-weight: 600;
            color: var(--cis-gray-900);
        }

        .mb-0 { margin-bottom: 0; }
        .mb-1 { margin-bottom: 0.25rem; }
        .mb-4 { margin-bottom: 1.5rem; }
        .mt-3 { margin-top: 1rem; }
        .me-2 { margin-right: 0.5rem; }
        .text-primary { color: var(--cis-primary, #0066cc); }
        .text-muted { color: var(--cis-gray-600, #6c757d); }

        .d-flex { display: flex; }
        .justify-content-between { justify-content: space-between; }
        .align-items-center { align-items: center; }

        /* Scoped styles for control panel */
        .stat-card {
            background: white;
            line-height: 1.6;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.05);
            transition: transform 0.2s, box-shadow 0.2s;
        }

        .stat-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }

        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: #fff;
            padding: 20px;
            border-radius: 8px;
            border-left: 4px solid #0066cc;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }

        .stat-card.warning { border-left-color: #ff9800; }
        .stat-card.success { border-left-color: #4caf50; }
        .stat-card.info { border-left-color: #2196f3; }

        .stat-value {
            font-size: 32px;
            font-weight: 700;
            color: #1a1a1a;
            margin-bottom: 5px;
        }

        .stat-label {
            font-size: 13px;
            color: #666;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .panel {
            background: #fff;
            border-radius: 8px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.08);
        }

        .panel-header {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 20px;
            color: #1a1a1a;
            border-bottom: 2px solid #f0f0f0;
            padding-bottom: 10px;
        }

        .control-row {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #f0f0f0;
        }

        .control-row:last-child {
            border-bottom: none;
        }

        .control-label {
            font-weight: 500;
            color: #333;
        }

        .control-desc {
            font-size: 13px;
            color: #666;
            margin-top: 4px;
        }

        .toggle-switch {
            position: relative;
            width: 50px;
            height: 26px;
            background: #ccc;
            border-radius: 13px;
            cursor: pointer;
            transition: background 0.3s;
        }

        .toggle-switch.active {
            background: #4caf50;
        }

        .toggle-switch::after {
            content: '';
            position: absolute;
            width: 22px;
            height: 22px;
            background: #fff;
            border-radius: 50%;
            top: 2px;
            left: 2px;
            transition: left 0.3s;
            box-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }

        .toggle-switch.active::after {
            left: 26px;
        }

        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            font-size: 14px;
            font-weight: 500;
            cursor: pointer;
            transition: all 0.2s;
        }

        .btn-primary {
            background: #0066cc;
            color: #fff;
        }

        .btn-primary:hover {
            background: #0052a3;
        }

        .btn-secondary {
            background: #e0e0e0;
            color: #333;
        }

        .btn-secondary:hover {
            background: #d0d0d0;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14px;
        }

        th {
            background: #f8f8f8;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            color: #555;
            border-bottom: 2px solid #e0e0e0;
        }

        td {
            padding: 12px;
            border-bottom: 1px solid #f0f0f0;
        }

        tr:hover {
            background: #f8f8f8;
        }

        .badge {
            display: inline-block;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
        }

        .badge-success { background: #e8f5e9; color: #2e7d32; }
        .badge-warning { background: #fff3e0; color: #f57c00; }
        .badge-danger { background: #ffebee; color: #c62828; }
        .badge-info { background: #e3f2fd; color: #1976d2; }

        .grid-2 {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 20px;
        }

        @media (max-width: 768px) {
            .grid-2 { grid-template-columns: 1fr; }
            .stats-grid { grid-template-columns: 1fr; }
        }

        .loader {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #0066cc;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            animation: spin 1s linear infinite;
            display: inline-block;
        }

        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>

    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h2 mb-1">
                <i class="fas fa-exchange-alt text-primary me-2"></i>
                Transfer System Control Panel
            </h1>
            <p class="text-muted mb-0">Manage stock transfers and sync operations</p>
        </div>
    </div>

    <!-- Stats Grid -->
    <div class="row mt-3">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['open_transfers']; ?></div>
                <div class="stat-label">Open Transfers</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card info">
                <div class="stat-value"><?php echo $stats['in_transit']; ?></div>
                <div class="stat-label">In Transit</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card warning">
                <div class="stat-value"><?php echo $stats['pending_receive']; ?></div>
                <div class="stat-label">Pending Receive</div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="stat-card success">
                <div class="stat-value">$<?php echo number_format($stats['total_value'], 2); ?></div>
                <div class="stat-label">Total Value</div>
            </div>
        </div>
    </div>

    <div class="row mt-3">
        <div class="col-md-3">
            <div class="stat-card">
                <div class="stat-value"><?php echo $stats['sync_queue']; ?></div>
                <div class="stat-label">Sync Queue</div>
            </div>
        </div>
    </div>

    <!-- System Controls -->
    <div class="panel">
        <div class="panel-header">System Controls</div>

            <div class="control-row">
                <div>
                    <div class="control-label">Lightspeed Sync</div>
                    <div class="control-desc">Enable/disable automatic synchronization with Lightspeed POS</div>
                </div>
                <div class="toggle-switch <?php echo $syncEnabled ? 'active' : ''; ?>"
                     id="syncToggle"
                     onclick="toggleSync()">
                </div>
            </div>

            <div class="control-row">
                <div>
                    <div class="control-label">Transfer Manager</div>
                    <div class="control-desc">Access the main transfer management interface</div>
                </div>
                <button class="btn btn-primary" onclick="window.open('frontend.php', '_blank')">
                    Open Transfer Manager →
                </button>
            </div>

            <div class="control-row">
                <div>
                    <div class="control-label">Refresh Analytics</div>
                    <div class="control-desc">Reload transfer statistics and analytics data</div>
                </div>
                <button class="btn btn-secondary" onclick="loadAnalytics()">
                    <span id="refreshBtn">Refresh</span>
                </button>
            </div>
        </div>

        <!-- Analytics Grid -->
        <div class="grid-2">
            <!-- Transfers by State -->
            <div class="panel">
                <div class="panel-header">Transfers by State (Last 30 Days)</div>
                <table id="stateTable">
                    <thead>
                        <tr>
                            <th>State</th>
                            <th>Count</th>
                            <th>Total Units</th>
                            <th>Value</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="4" style="text-align: center; color: #999;">Loading...</td></tr>
                    </tbody>
                </table>
            </div>

            <!-- Top Routes -->
            <div class="panel">
                <div class="panel-header">Top Transfer Routes</div>
                <table id="routeTable">
                    <thead>
                        <tr>
                            <th>From</th>
                            <th>To</th>
                            <th>Transfers</th>
                            <th>Units</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr><td colspan="4" style="text-align: center; color: #999;">Loading...</td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Sync Status -->
        <div class="panel">
            <div class="panel-header">Sync Queue Status (Last 24 Hours)</div>
            <table id="syncTable">
                <thead>
                    <tr>
                        <th>Status</th>
                        <th>Count</th>
                        <th>Avg Duration (sec)</th>
                    </tr>
                </thead>
                <tbody>
                    <tr><td colspan="3" style="text-align: center; color: #999;">Loading...</td></tr>
                </tbody>
            </table>
        </div>

    <script>
        let isLoading = false;

        async function toggleSync() {
            if (isLoading) return;

            const toggle = document.getElementById('syncToggle');
            const currentState = toggle.classList.contains('active');
            const newState = !currentState;

            isLoading = true;

            try {
                const formData = new FormData();
                formData.append('action', 'toggle_sync');
                formData.append('enable', newState ? '1' : '0');

                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    if (result.enabled) {
                        toggle.classList.add('active');
                    } else {
                        toggle.classList.remove('active');
                    }
                }
            } catch (error) {
                console.error('Failed to toggle sync:', error);
                alert('Failed to toggle sync. Please try again.');
            } finally {
                isLoading = false;
            }
        }

        async function loadAnalytics() {
            if (isLoading) return;

            isLoading = true;
            const refreshBtn = document.getElementById('refreshBtn');
            refreshBtn.innerHTML = '<span class="loader"></span>';

            try {
                const formData = new FormData();
                formData.append('action', 'get_analytics');

                const response = await fetch('', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    updateStateTable(result.data.by_state);
                    updateRouteTable(result.data.top_routes);
                    updateSyncTable(result.data.sync_status);
                }
            } catch (error) {
                console.error('Failed to load analytics:', error);
            } finally {
                isLoading = false;
                refreshBtn.textContent = 'Refresh';
            }
        }

        function updateStateTable(data) {
            const tbody = document.querySelector('#stateTable tbody');

            if (!data || data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: #999;">No data</td></tr>';
                return;
            }

            const stateBadges = {
                'OPEN': 'info',
                'SENT': 'warning',
                'RECEIVING': 'warning',
                'PARTIAL': 'warning',
                'RECEIVED': 'success'
            };

            tbody.innerHTML = data.map(row => `
                <tr>
                    <td><span class="badge badge-${stateBadges[row.state] || 'info'}">${row.state}</span></td>
                    <td>${row.count}</td>
                    <td>${parseInt(row.units || 0).toLocaleString()}</td>
                    <td>$${parseFloat(row.value || 0).toLocaleString('en-NZ', {minimumFractionDigits: 2, maximumFractionDigits: 2})}</td>
                </tr>
            `).join('');
        }

        function updateRouteTable(data) {
            const tbody = document.querySelector('#routeTable tbody');

            if (!data || data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align: center; color: #999;">No data</td></tr>';
                return;
            }

            tbody.innerHTML = data.map(row => `
                <tr>
                    <td>${row.from_outlet || 'Unknown'}</td>
                    <td>${row.to_outlet || 'Unknown'}</td>
                    <td>${row.transfer_count}</td>
                    <td>${parseInt(row.total_units).toLocaleString()}</td>
                </tr>
            `).join('');
        }

        function updateSyncTable(data) {
            const tbody = document.querySelector('#syncTable tbody');

            if (!data || data.length === 0) {
                tbody.innerHTML = '<tr><td colspan="3" style="text-align: center; color: #999;">No data</td></tr>';
                return;
            }

            const statusBadges = {
                'completed': 'success',
                'pending': 'warning',
                'processing': 'info',
                'failed': 'danger'
            };

            tbody.innerHTML = data.map(row => `
                <tr>
                    <td><span class="badge badge-${statusBadges[row.status] || 'info'}">${row.status}</span></td>
                    <td>${row.count}</td>
                    <td>${row.avg_duration ? parseFloat(row.avg_duration).toFixed(1) : 'N/A'}</td>
                </tr>
            `).join('');
        }

        // Load analytics on page load
        loadAnalytics();

        // Auto-refresh every 30 seconds
        setInterval(loadAnalytics, 30000);
    </script>

<?php
// Capture content
$content = ob_get_clean();

// Render with dashboard layout
include $_SERVER['DOCUMENT_ROOT'] . '/modules/base/_templates/layouts/dashboard.php';
?>
