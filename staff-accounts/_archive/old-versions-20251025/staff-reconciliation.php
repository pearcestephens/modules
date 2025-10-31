<?php
/**
 * Staff Accounts - Staff Reconciliation (ULTRA PROFESSIONAL)
 * Feature-rich admin view with REAL data, advanced filtering, sorting, bulk actions
 */

require_once __DIR__ . '/bootstrap.php';
cis_require_login();

// Fetch REAL staff data from API
$api_url = '/modules/staff-accounts/api/staff-reconciliation.php';
$context = stream_context_create(['http' => ['method' => 'GET', 'header' => "Cookie: " . $_SERVER['HTTP_COOKIE'] . "\r\n"]]);
$api_response = @file_get_contents($_SERVER['DOCUMENT_ROOT'] . $api_url, false, $context);
$data = json_decode($api_response, true);

if (!$data || !isset($data['success']) || !$data['success']) {
    $error_message = $data['message'] ?? 'Failed to load staff data';
    $staff_list = [];
} else {
    $staff_list = $data['data'] ?? [];
}

$filter = $_GET['filter'] ?? 'all';
$search = $_GET['search'] ?? '';
$sort_by = $_GET['sort'] ?? 'balance_desc';
$filtered_list = $staff_list;

// Apply filtering on REAL data - USING ACTUAL COLUMN NAMES
if ($filter !== 'all' && !empty($filtered_list)) {
    $filtered_list = array_filter($filtered_list, function($staff) use ($filter) {
        $balance = floatval($staff['vend_balance']);
        $days = $staff['days_since_last_payment'] ?? 0;
        switch ($filter) {
            case 'negative': return $balance < 0;
            case 'positive': return $balance >= 0;
            case 'critical': return $balance < -500;
            case 'overdue_30': return $days > 30 && $balance < 0;
            case 'overdue_60': return $days > 60 && $balance < 0;
            case 'overdue_90': return $days > 90 && $balance < 0;
            default: return true;
        }
    });
}

if ($search && !empty($filtered_list)) {
    $filtered_list = array_filter($filtered_list, function($staff) use ($search) {
        return stripos($staff['employee_name'], $search) !== false ||
               stripos($staff['user_id'], $search) !== false ||
               stripos($staff['vend_customer_id'] ?? '', $search) !== false;
    });
}

// Apply sorting - USING ACTUAL COLUMN NAMES
if (!empty($filtered_list)) {
    usort($filtered_list, function($a, $b) use ($sort_by) {
        switch ($sort_by) {
            case 'balance_desc': return floatval($a['vend_balance']) <=> floatval($b['vend_balance']);
            case 'balance_asc': return floatval($b['vend_balance']) <=> floatval($a['vend_balance']);
            case 'name_asc': return strcasecmp($a['employee_name'], $b['employee_name']);
            case 'name_desc': return strcasecmp($b['employee_name'], $a['employee_name']);
            case 'last_payment':
                $date_a = $a['last_payment_date'] ? strtotime($a['last_payment_date']) : 0;
                $date_b = $b['last_payment_date'] ? strtotime($b['last_payment_date']) : 0;
                return $date_b <=> $date_a;
            case 'days_overdue': return ($b['days_since_last_payment'] ?? 0) <=> ($a['days_since_last_payment'] ?? 0);
            default: return 0;
        }
    });
}

// Calculate REAL statistics - USING ACTUAL COLUMN NAMES
$total_staff = count($staff_list);
$filtered_staff = count($filtered_list);
$total_balance = array_sum(array_map(fn($s) => floatval($s['vend_balance']), $filtered_list));
$total_owed = abs(array_sum(array_map(fn($s) => min(0, floatval($s['vend_balance'])), $filtered_list)));
$total_purchases = array_sum(array_map(fn($s) => floatval($s['total_allocated']), $filtered_list));
$total_payments = array_sum(array_map(fn($s) => floatval($s['total_payments_ytd']), $filtered_list));
$critical_count = count(array_filter($filtered_list, fn($s) => floatval($s['vend_balance']) < -500));
$overdue_count = count(array_filter($filtered_list, fn($s) => ($s['days_since_last_payment'] ?? 0) > 30 && floatval($s['vend_balance']) < 0));

$page_title = 'Staff Reconciliation';
$page_head_extra = '<link rel="stylesheet" href="/modules/staff-accounts/css/staff-accounts.css">';
$body_class = 'staff-accounts staff-reconciliation';
ob_start();
?>

<div class="container-fluid mt-4">
    <?php if (isset($error_message)): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <strong>⚠️ Error:</strong> <?= htmlspecialchars($error_message) ?>
        <button type="button" class="close" data-dismiss="alert">&times;</button>
    </div>
    <?php endif; ?>

    <!-- Summary Statistics (REAL DATA) -->
    <div class="row mb-4">
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stat-card">
                <div class="stat-label">📊 Showing / Total</div>
                <div class="stat-value"><?= $filtered_staff ?> <small class="text-muted">/ <?= $total_staff ?></small></div>
                <small class="text-muted"><?= $filtered_staff !== $total_staff ? round(($filtered_staff / $total_staff) * 100) . '% filtered' : 'All staff' ?></small>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stat-card <?= $total_owed > 0 ? 'danger' : 'success' ?>">
                <div class="stat-label">💰 Total Owed</div>
                <div class="stat-value">$<?= number_format($total_owed, 2) ?></div>
                <small class="text-muted"><?= count(array_filter($filtered_list, fn($s) => floatval($s['vend_balance']) < 0)) ?> staff</small>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stat-card <?= $critical_count > 0 ? 'danger' : 'success' ?>">
                <div class="stat-label">🚨 Critical (&lt;-$500)</div>
                <div class="stat-value"><?= $critical_count ?></div>
                <small class="text-muted"><?= $filtered_staff > 0 ? round(($critical_count / $filtered_staff) * 100) : 0 ?>%</small>
            </div>
        </div>
        <div class="col-lg-3 col-md-6 mb-3">
            <div class="stat-card <?= $overdue_count > 0 ? 'warning' : 'success' ?>">
                <div class="stat-label">⏰ Overdue (30+d)</div>
                <div class="stat-value"><?= $overdue_count ?></div>
                <small class="text-muted"><?= $filtered_staff > 0 ? round(($overdue_count / $filtered_staff) * 100) : 0 ?>%</small>
            </div>
        </div>
    </div>

    <!-- Filters & Actions -->
    <div class="content-card mb-4">
        <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap">
            <h4 class="mb-2 mb-md-0">📋 Staff Accounts</h4>
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-primary btn-custom" onclick="bulkSendReminders()" id="bulkBtn">
                    <i class="fa fa-envelope"></i> Bulk Reminders (<span id="count">0</span>)
                </button>
                <a href="?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>" class="btn btn-success btn-custom">
                    <i class="fa fa-download"></i> CSV
                </a>
                <a href="/modules/staff-accounts/manager-dashboard.php" class="btn btn-info btn-custom">
                    <i class="fa fa-dashboard"></i> Dashboard
                </a>
            </div>
        </div>

        <form method="get" class="row g-3">
            <div class="col-lg-4 col-md-6">
                <label class="form-label small text-muted mb-1"><i class="fa fa-search"></i> Search</label>
                <input type="text" name="search" class="form-control" placeholder="Name, ID, Xero ID..." value="<?= htmlspecialchars($search) ?>">
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label small text-muted mb-1"><i class="fa fa-filter"></i> Filter</label>
                <select name="filter" class="form-control" onchange="this.form.submit()">
                    <option value="all" <?= $filter === 'all' ? 'selected' : '' ?>>All (<?= count($staff_list) ?>)</option>
                    <option value="negative" <?= $filter === 'negative' ? 'selected' : '' ?>>❌ Negative</option>
                    <option value="positive" <?= $filter === 'positive' ? 'selected' : '' ?>>✅ Credit</option>
                    <option value="critical" <?= $filter === 'critical' ? 'selected' : '' ?>>🚨 Critical</option>
                    <option value="overdue_30" <?= $filter === 'overdue_30' ? 'selected' : '' ?>>⏰ 30+ days</option>
                    <option value="overdue_60" <?= $filter === 'overdue_60' ? 'selected' : '' ?>>⚠️ 60+ days</option>
                    <option value="overdue_90" <?= $filter === 'overdue_90' ? 'selected' : '' ?>>🔴 90+ days</option>
                </select>
            </div>
            <div class="col-lg-3 col-md-6">
                <label class="form-label small text-muted mb-1"><i class="fa fa-sort"></i> Sort</label>
                <select name="sort" class="form-control" onchange="this.form.submit()">
                    <option value="balance_desc" <?= $sort_by === 'balance_desc' ? 'selected' : '' ?>>Balance ↓</option>
                    <option value="balance_asc" <?= $sort_by === 'balance_asc' ? 'selected' : '' ?>>Balance ↑</option>
                    <option value="name_asc" <?= $sort_by === 'name_asc' ? 'selected' : '' ?>>Name A-Z</option>
                    <option value="name_desc" <?= $sort_by === 'name_desc' ? 'selected' : '' ?>>Name Z-A</option>
                    <option value="last_payment" <?= $sort_by === 'last_payment' ? 'selected' : '' ?>>Recent Payment</option>
                    <option value="days_overdue" <?= $sort_by === 'days_overdue' ? 'selected' : '' ?>>Most Overdue</option>
                </select>
            </div>
            <div class="col-lg-2 col-md-6">
                <label class="form-label small text-muted mb-1">&nbsp;</label>
                <button type="submit" class="btn btn-primary w-100">Apply</button>
            </div>
        </form>

        <?php if ($filter !== 'all' || $search): ?>
        <div class="mt-3 pt-3 border-top">
            <a href="?" class="btn btn-sm btn-outline-secondary"><i class="fa fa-times"></i> Clear</a>
            <span class="badge badge-info ml-3 p-2"><?= $filtered_staff ?> of <?= $total_staff ?></span>
        </div>
        <?php endif; ?>
    </div>

    <!-- Staff Table (REAL DATA) -->
    <div class="content-card">
        <?php if (!empty($filtered_list)): ?>
        <div class="table-responsive">
            <table class="table table-hover data-table" id="staffTable">
                <thead class="thead-light">
                    <tr>
                        <th width="30"><input type="checkbox" id="selectAll" onclick="toggleAll(this)"></th>
                        <th>Employee</th>
                        <th class="text-right">Balance</th>
                        <th class="text-right">Purchases</th>
                        <th class="text-right">Payments</th>
                        <th class="text-center">Last Payment</th>
                        <th class="text-center">Status</th>
                        <th class="text-center">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($filtered_list as $staff): 
                        // USING ACTUAL COLUMN NAMES: vend_balance, user_id
                        $balance = floatval($staff['vend_balance']);
                        $days = $staff['days_since_last_payment'] ?? 0;
                        
                        if ($balance >= 0) {
                            $status = 'success'; $status_text = '✅ Credit';
                        } elseif ($days > 90) {
                            $status = 'danger'; $status_text = '🔴 Critical';
                        } elseif ($days > 30) {
                            $status = 'warning'; $status_text = '⚠️ Overdue';
                        } else {
                            $status = 'info'; $status_text = '📌 Recent';
                        }
                        
                        $row_class = $balance < -500 ? 'table-danger' : ($balance < -200 ? 'table-warning' : '');
                    ?>
                    <tr class="<?= $row_class ?>">
                        <td><input type="checkbox" class="staff-cb" value="<?= htmlspecialchars($staff['user_id']) ?>" onchange="updateCount()"></td>
                        <td>
                            <strong><?= htmlspecialchars($staff['employee_name']) ?></strong>
                            <?php if ($balance < -500): ?>
                            <span class="badge badge-danger ml-2">CRITICAL</span>
                            <?php endif; ?>
                            <br><small class="text-muted">ID: <?= htmlspecialchars($staff['user_id']) ?></small>
                        </td>
                        <td class="text-right">
                            <strong class="<?= $balance < 0 ? 'text-danger' : 'text-success' ?>" style="font-size:1.1em;">
                                <?= $balance < 0 ? '-' : '+' ?>$<?= number_format(abs($balance), 2) ?>
                            </strong>
                        </td>
                        <td class="text-right"><strong>$<?= number_format($staff['total_allocated'], 2) ?></strong></td>
                        <td class="text-right"><strong>$<?= number_format($staff['total_payments_ytd'], 2) ?></strong></td>
                        <td class="text-center">
                            <?php if ($staff['last_payment_date']): ?>
                            <?= date('M j, Y', strtotime($staff['last_payment_date'])) ?>
                            <br><small class="text-muted"><?= $days ?>d ago</small>
                            <?php else: ?>
                            <span class="badge badge-secondary">Never</span>
                            <?php endif; ?>
                        </td>
                        <td class="text-center">
                            <span class="badge badge-<?= $status ?>"><?= $status_text ?></span>
                        </td>
                        <td class="text-center">
                            <div class="btn-group btn-group-sm">
                                <a href="/modules/staff-accounts/view-account.php?id=<?= urlencode($staff['user_id']) ?>" class="btn btn-info" title="View"><i class="fa fa-eye"></i></a>
                                <a href="/modules/staff-accounts/send-reminder.php?id=<?= urlencode($staff['user_id']) ?>" class="btn btn-warning" title="Remind"><i class="fa fa-envelope"></i></a>
                                <a href="/modules/staff-accounts/setup-plan.php?id=<?= urlencode($staff['user_id']) ?>" class="btn btn-primary" title="Plan"><i class="fa fa-calendar"></i></a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="thead-light font-weight-bold">
                    <tr>
                        <td colspan="2"><strong>TOTALS (<?= $filtered_staff ?>):</strong></td>
                        <td class="text-right">
                            <strong class="<?= $total_balance < 0 ? 'text-danger' : 'text-success' ?>" style="font-size:1.2em;">
                                <?= $total_balance < 0 ? '-' : '+' ?>$<?= number_format(abs($total_balance), 2) ?>
                            </strong>
                        </td>
                        <td class="text-right"><strong>$<?= number_format($total_purchases, 2) ?></strong></td>
                        <td class="text-right"><strong>$<?= number_format($total_payments, 2) ?></strong></td>
                        <td colspan="3"></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php else: ?>
        <div class="text-center py-5">
            <div style="font-size:4em;opacity:0.3;">📭</div>
            <h5 class="text-muted mt-3">No staff found</h5>
            <a href="?" class="btn btn-outline-primary mt-3">Clear Filters</a>
        </div>
        <?php endif; ?>
    </div>

    <!-- Analytics (REAL DATA) -->
    <div class="row mt-4">
        <div class="col-lg-6 mb-4">
            <div class="content-card">
                <h6 class="mb-3"><i class="fa fa-pie-chart text-primary"></i> Distribution</h6>
                <?php
                // USING ACTUAL COLUMN NAME: vend_balance
                $positive = count(array_filter($filtered_list, fn($s) => floatval($s['vend_balance']) >= 0));
                $negative = count(array_filter($filtered_list, fn($s) => floatval($s['vend_balance']) < 0 && floatval($s['vend_balance']) > -500));
                ?>
                <div class="mb-3">
                    <div class="d-flex justify-content-between"><span>🚨 Critical:</span><strong class="text-danger"><?= $critical_count ?></strong></div>
                    <div class="progress mt-1" style="height:10px;"><div class="progress-bar bg-danger" style="width:<?= $filtered_staff > 0 ? ($critical_count/$filtered_staff*100) : 0 ?>%"></div></div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between"><span>⚠️ Overdue:</span><strong class="text-warning"><?= $overdue_count ?></strong></div>
                    <div class="progress mt-1" style="height:10px;"><div class="progress-bar bg-warning" style="width:<?= $filtered_staff > 0 ? ($overdue_count/$filtered_staff*100) : 0 ?>%"></div></div>
                </div>
                <div class="mb-3">
                    <div class="d-flex justify-content-between"><span>❌ Negative:</span><strong class="text-info"><?= $negative ?></strong></div>
                    <div class="progress mt-1" style="height:10px;"><div class="progress-bar bg-info" style="width:<?= $filtered_staff > 0 ? ($negative/$filtered_staff*100) : 0 ?>%"></div></div>
                </div>
                <div class="mb-2">
                    <div class="d-flex justify-content-between"><span>✅ Credit:</span><strong class="text-success"><?= $positive ?></strong></div>
                    <div class="progress mt-1" style="height:10px;"><div class="progress-bar bg-success" style="width:<?= $filtered_staff > 0 ? ($positive/$filtered_staff*100) : 0 ?>%"></div></div>
                </div>
            </div>
        </div>
        <div class="col-lg-6 mb-4">
            <div class="content-card">
                <h6 class="mb-3"><i class="fa fa-money text-success"></i> Financial</h6>
                <table class="table table-sm table-borderless">
                    <tr><td>Total Owed:</td><td class="text-right"><strong class="text-danger">$<?= number_format($total_owed, 2) ?></strong></td></tr>
                    <tr><td>Total Purchases:</td><td class="text-right"><strong>$<?= number_format($total_purchases, 2) ?></strong></td></tr>
                    <tr><td>Total Payments:</td><td class="text-right"><strong class="text-success">$<?= number_format($total_payments, 2) ?></strong></td></tr>
                    <tr><td>Avg per Staff:</td><td class="text-right"><strong>$<?= $filtered_staff > 0 ? number_format(abs($total_balance)/$filtered_staff, 2) : '0.00' ?></strong></td></tr>
                    <tr class="border-top bg-light"><td><strong>Net:</strong></td><td class="text-right"><strong class="<?= $total_balance < 0 ? 'text-danger' : 'text-success' ?>" style="font-size:1.2em;"><?= $total_balance < 0 ? '-' : '+' ?>$<?= number_format(abs($total_balance), 2) ?></strong></td></tr>
                </table>
            </div>
        </div>
    </div>
</div>

<script>
function toggleAll(cb) { document.querySelectorAll('.staff-cb').forEach(c => c.checked = cb.checked); updateCount(); }
function updateCount() {
    const count = document.querySelectorAll('.staff-cb:checked').length;
    document.getElementById('count').textContent = count;
    document.getElementById('bulkBtn').disabled = count === 0;
}
function bulkSendReminders() {
    const selected = Array.from(document.querySelectorAll('.staff-cb:checked')).map(c => c.value);
    if (selected.length === 0) { alert('⚠️ Select staff members'); return; }
    if (confirm(`📧 Send reminders to ${selected.length} staff?`)) {
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/modules/staff-accounts/bulk-send-reminders.php';
        selected.forEach(id => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'user_ids[]';  // USING ACTUAL COLUMN NAME: user_id
            input.value = id;
            form.appendChild(input);
        });
        document.body.appendChild(form);
        form.submit();
    }
}
document.addEventListener('DOMContentLoaded', updateCount);
</script>

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../shared/templates/base-layout.php';
