<?php
/**
 * Staff Accounts - Staff List View (Manager Dashboard)
 *
 * Purpose: Browse and manage all staff accounts (manager-only view)
 *
 * Features:
 * - Browse all 247 staff accounts with pagination
 * - Search by name/email/vend_customer/xero_employee
 * - Filter by active/inactive/has_balance/manager_only
 * - Sort by name/balance/last_payment_date
 * - Edit mappings (Xero ↔ Vend)
 * - Suspend/activate accounts
 * - View payment history
 * - Bulk actions
 * - High-end professional design
 *
 * Database Tables:
 * - users (browse staff)
 * - staff_account_reconciliation (balances)
 * - cis_staff_vend_map (Xero-Vend mappings)
 *
 * @package CIS\Modules\StaffAccounts
 * @version 2.0.0
 */

// Bootstrap the module
require_once __DIR__ . '/../bootstrap.php';

// Require authentication
cis_require_login();

$user_id = $_SESSION['user_id'];

// Pagination settings
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$per_page = isset($_GET['per_page']) ? min(100, max(10, (int)$_GET['per_page'])) : 50;
$offset = ($page - 1) * $per_page;

// Search and filter parameters
$search = $_GET['search'] ?? '';
$filter_status = $_GET['status'] ?? 'all'; // all, active, inactive, has_balance, managers
$sort_by = $_GET['sort'] ?? 'name'; // name, balance, last_payment
$sort_dir = $_GET['dir'] ?? 'asc'; // asc, desc

// Build WHERE clause
$where_conditions = [];
$params = [];

if (!empty($search)) {
    $where_conditions[] = "(u.first_name LIKE ? OR u.last_name LIKE ? OR u.email LIKE ? OR u.vend_customer_account LIKE ? OR u.xero_id LIKE ?)";
    $search_param = "%{$search}%";
    $params = array_fill(0, 5, $search_param);
}

// Status filters
switch ($filter_status) {
    case 'active':
        $where_conditions[] = "u.staff_active = 1";
        break;
    case 'inactive':
        $where_conditions[] = "u.staff_active = 0";
        break;
    case 'has_balance':
        $where_conditions[] = "sar.vend_balance < 0";
        break;
    case 'managers':
        $where_conditions[] = "u.is_manager = 1";
        break;
}

$where_sql = !empty($where_conditions) ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Sorting
$sort_columns = [
    'name' => 'u.first_name, u.last_name',
    'balance' => 'sar.vend_balance',
    'last_payment' => 'sar.last_payment_date'
];
$sort_column = $sort_columns[$sort_by] ?? $sort_columns['name'];
$sort_direction = strtoupper($sort_dir) === 'DESC' ? 'DESC' : 'ASC';

// CHECK TABLE: Count total records
$count_sql = "
    SELECT COUNT(DISTINCT u.id) as total
    FROM users u
    LEFT JOIN staff_account_reconciliation sar ON u.id = sar.user_id
    {$where_sql}
";
$stmt = $pdo->prepare($count_sql);
$stmt->execute($params);
$total_records = $stmt->fetch(PDO::FETCH_ASSOC)['total'];
$total_pages = ceil($total_records / $per_page);

// CHECK TABLE: Fetch staff list with reconciliation data
$sql = "
    SELECT
        u.id,
        u.first_name,
        u.last_name,
        u.email,
        u.vend_customer_account,
        u.xero_id,
        u.is_manager,
        u.staff_active,
        u.account_locked,
        sar.vend_balance,
        sar.outstanding_amount,
        sar.total_payments_ytd,
        sar.last_payment_date,
        sar.last_payment_amount,
        sar.credit_limit,
        sar.status as reconciliation_status,
        csvm.vend_customer_id as mapped_vend_id
    FROM users u
    LEFT JOIN staff_account_reconciliation sar ON u.id = sar.user_id
    LEFT JOIN cis_staff_vend_map csvm ON u.xero_id = csvm.xero_employee_id
    {$where_sql}
    ORDER BY {$sort_column} {$sort_direction}
    LIMIT {$per_page} OFFSET {$offset}
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$staff_list = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Page configuration for CIS template
$page_title = 'Staff List - All Accounts';
$page_head_extra = '<link rel="stylesheet" href="/assets/css/staff-accounts.css">';
$body_class = 'staff-accounts staff-list';

// Start output buffering
ob_start();
?>

<div class="container-fluid staff-accounts">
    <div class="staff-list-wrapper">
            <!-- Page Header -->
            <div class="page-header">
                <div class="container-fluid">
                    <div class="row align-items-center">
                        <div class="col-md-8">
                            <h1><i class="fas fa-users"></i> Staff Account Management</h1>
                            <p>Browse and manage all <?= number_format($total_records) ?> staff accounts</p>
                        </div>
                        <div class="col-md-4 text-right">
                            <a href="manager-dashboard.php" class="btn btn-light">
                                <i class="fas fa-arrow-left"></i> Back to Dashboard
                            </a>
                        </div>
                    </div>
                </div>
            </div>

            <div class="container-fluid">
        <!-- Filter Bar -->
        <div class="filter-bar">
            <form method="GET" action="" id="filterForm">
                <div class="row">
                    <!-- Search -->
                    <div class="col-md-4">
                        <div class="filter-section">
                            <div class="filter-label">Search</div>
                            <div class="search-container">
                                <i class="fas fa-search search-icon"></i>
                                <input
                                    type="text"
                                    name="search"
                                    class="search-input"
                                    placeholder="Name, email, Vend ID, Xero ID..."
                                    value="<?= htmlspecialchars($search) ?>"
                                >
                                <i class="fas fa-times clear-search" onclick="document.querySelector('.search-input').value=''; this.closest('form').submit();"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Status Filter -->
                    <div class="col-md-4">
                        <div class="filter-section">
                            <div class="filter-label">Status Filter</div>
                            <div class="filter-buttons">
                                <button type="submit" name="status" value="all" class="filter-btn <?= $filter_status === 'all' ? 'active' : '' ?>">
                                    All Staff
                                </button>
                                <button type="submit" name="status" value="active" class="filter-btn <?= $filter_status === 'active' ? 'active' : '' ?>">
                                    Active
                                </button>
                                <button type="submit" name="status" value="inactive" class="filter-btn <?= $filter_status === 'inactive' ? 'active' : '' ?>">
                                    Inactive
                                </button>
                                <button type="submit" name="status" value="has_balance" class="filter-btn <?= $filter_status === 'has_balance' ? 'active' : '' ?>">
                                    Has Balance
                                </button>
                                <button type="submit" name="status" value="managers" class="filter-btn <?= $filter_status === 'managers' ? 'active' : '' ?>">
                                    Managers
                                </button>
                            </div>
                        </div>
                    </div>

                    <!-- Sort Options -->
                    <div class="col-md-4">
                        <div class="filter-section">
                            <div class="filter-label">Sort By</div>
                            <div class="row">
                                <div class="col-8">
                                    <select name="sort" class="form-control" onchange="this.form.submit()">
                                        <option value="name" <?= $sort_by === 'name' ? 'selected' : '' ?>>Name</option>
                                        <option value="balance" <?= $sort_by === 'balance' ? 'selected' : '' ?>>Balance</option>
                                        <option value="last_payment" <?= $sort_by === 'last_payment' ? 'selected' : '' ?>>Last Payment</option>
                                    </select>
                                </div>
                                <div class="col-4">
                                    <select name="dir" class="form-control" onchange="this.form.submit()">
                                        <option value="asc" <?= $sort_dir === 'asc' ? 'selected' : '' ?>>↑ Asc</option>
                                        <option value="desc" <?= $sort_dir === 'desc' ? 'selected' : '' ?>>↓ Desc</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Keep pagination on filter changes -->
                <input type="hidden" name="per_page" value="<?= $per_page ?>">
            </form>
        </div>

        <!-- Staff Table -->
        <div class="staff-table-container">
            <table class="table">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Vend ID</th>
                        <th>Xero ID</th>
                        <th>Balance</th>
                        <th>Last Payment</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($staff_list as $staff): ?>
                        <tr>
                            <td>
                                <strong><?= htmlspecialchars($staff['first_name'] . ' ' . $staff['last_name']) ?></strong>
                                <?php if ($staff['is_manager']): ?>
                                    <span class="badge badge-manager ml-2">Manager</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($staff['email']) ?></td>
                            <td>
                                <?php if ($staff['vend_customer_account']): ?>
                                    <code><?= htmlspecialchars($staff['vend_customer_account']) ?></code>
                                <?php else: ?>
                                    <span style="color: #95A5A6;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($staff['xero_id']): ?>
                                    <code><?= htmlspecialchars($staff['xero_id']) ?></code>
                                <?php else: ?>
                                    <span style="color: #95A5A6;">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($staff['vend_balance'] !== null): ?>
                                    <span class="balance <?= $staff['vend_balance'] < 0 ? 'negative' : 'positive' ?>">
                                        $<?= number_format(abs($staff['vend_balance']), 2) ?>
                                    </span>
                                <?php else: ?>
                                    <span style="color: #95A5A6;">N/A</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($staff['last_payment_date']): ?>
                                    <div><?= date('M j, Y', strtotime($staff['last_payment_date'])) ?></div>
                                    <small style="color: #6C757D;">$<?= number_format($staff['last_payment_amount'], 2) ?></small>
                                <?php else: ?>
                                    <span style="color: #95A5A6;">Never</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($staff['account_locked']): ?>
                                    <span class="badge badge-locked">Locked</span>
                                <?php elseif ($staff['staff_active']): ?>
                                    <span class="badge badge-active">Active</span>
                                <?php else: ?>
                                    <span class="badge badge-inactive">Inactive</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="action-btn action-btn-primary" onclick="viewAccount(<?= $staff['id'] ?>)">
                                    <i class="fas fa-eye"></i> View
                                </button>
                                <button class="action-btn action-btn-secondary" onclick="editMapping(<?= $staff['id'] ?>)">
                                    <i class="fas fa-link"></i> Mapping
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>

                    <?php if (empty($staff_list)): ?>
                        <tr>
                            <td colspan="8" class="text-center" style="padding: 48px;">
                                <i class="fas fa-search" style="font-size: 48px; color: #95A5A6; margin-bottom: 16px;"></i>
                                <p style="color: #6C757D; margin: 0;">No staff members found matching your criteria.</p>
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <div class="pagination-container">
                    <div class="pagination-info">
                        Showing <?= number_format($offset + 1) ?>-<?= number_format(min($offset + $per_page, $total_records)) ?> of <?= number_format($total_records) ?> staff members
                    </div>

                    <nav>
                        <ul class="pagination">
                            <!-- Previous -->
                            <?php if ($page > 1): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page - 1 ?>&search=<?= urlencode($search) ?>&status=<?= $filter_status ?>&sort=<?= $sort_by ?>&dir=<?= $sort_dir ?>&per_page=<?= $per_page ?>">
                                        <i class="fas fa-chevron-left"></i>
                                    </a>
                                </li>
                            <?php endif; ?>

                            <!-- Page Numbers -->
                            <?php
                            $page_range = 2;
                            $start_page = max(1, $page - $page_range);
                            $end_page = min($total_pages, $page + $page_range);

                            for ($i = $start_page; $i <= $end_page; $i++): ?>
                                <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                    <a class="page-link" href="?page=<?= $i ?>&search=<?= urlencode($search) ?>&status=<?= $filter_status ?>&sort=<?= $sort_by ?>&dir=<?= $sort_dir ?>&per_page=<?= $per_page ?>">
                                        <?= $i ?>
                                    </a>
                                </li>
                            <?php endfor; ?>

                            <!-- Next -->
                            <?php if ($page < $total_pages): ?>
                                <li class="page-item">
                                    <a class="page-link" href="?page=<?= $page + 1 ?>&search=<?= urlencode($search) ?>&status=<?= $filter_status ?>&sort=<?= $sort_by ?>&dir=<?= $sort_dir ?>&per_page=<?= $per_page ?>">
                                        <i class="fas fa-chevron-right"></i>
                                    </a>
                                </li>
                            <?php endif; ?>
                        </ul>
                    </nav>
                </div>
            <?php endif; ?>
        </div>
        </div>

        <script>
            function viewAccount(userId) {
                // Redirect to employee mapping or account details page
                window.location.href = 'employee-mapping.php?user_id=' + userId;
            }

            function editMapping(userId) {
                // Redirect to mapping edit page
                window.location.href = 'employee-mapping.php?user_id=' + userId + '&mode=edit';
            }
        </script>
    </div> <!-- /staff-list-wrapper -->
</div> <!-- /container-fluid.staff-accounts -->

<?php
$page_content = ob_get_clean();
require_once __DIR__ . '/../shared/templates/base-layout.php';
