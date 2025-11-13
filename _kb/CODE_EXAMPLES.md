# VapeUltra Template - Code Examples from Real Pages

**Status:** ✅ Ready to Copy & Use
**Date:** November 11, 2025

---

## 🎯 Real Code You Can Copy

All these examples are from **working pages** in your system. Copy and adapt them!

---

## 1️⃣ DASHBOARD WITH STATISTICS

**From:** `/modules/admin-ui/pages/overview.php`

### Full Minimal Version
```php
<?php
/**
 * Dashboard Overview Page
 * Complete example with CIS statistics
 */

declare(strict_types=1);

// Get CIS database connection
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once '/modules/base/templates/vape-ultra/config.php';
require_once '/modules/base/templates/vape-ultra/layouts/main.php';

// ============================================================================
// SYSTEM STATISTICS
// ============================================================================

$systemStats = [
    'total_outlets' => 0,
    'total_products' => 0,
    'total_inventory_value' => 0,
    'low_stock_items' => 0
];

try {
    // Count outlets
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM vend_outlets WHERE active = 1");
    $systemStats['total_outlets'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    // Count products
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM vend_products WHERE active = 1");
    $systemStats['total_products'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

    // Calculate inventory value
    $stmt = $pdo->query("SELECT SUM(quantity * cost) as total_value FROM vend_inventory WHERE quantity > 0");
    $systemStats['total_inventory_value'] = (float)($stmt->fetch(PDO::FETCH_ASSOC)['total_value'] ?? 0);

    // Count low stock items
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM vend_inventory WHERE quantity < 10 AND quantity > 0");
    $systemStats['low_stock_items'] = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);
} catch (Exception $e) {
    error_log("System stats error: " . $e->getMessage());
    $systemStats['error'] = "Could not load statistics";
}

// ============================================================================
// SALES METRICS (Last 30 days)
// ============================================================================

$salesMetrics = [
    'total_sales' => 0,
    'sales_count' => 0,
    'avg_transaction' => 0
];

try {
    // Total sales
    $stmt = $pdo->query("
        SELECT
            SUM(total) as sales_total,
            COUNT(*) as transaction_count,
            AVG(total) as avg_sale
        FROM vend_sales
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $data = $stmt->fetch(PDO::FETCH_ASSOC);
    $salesMetrics['total_sales'] = (float)($data['sales_total'] ?? 0);
    $salesMetrics['sales_count'] = (int)($data['transaction_count'] ?? 0);
    $salesMetrics['avg_transaction'] = (float)($data['avg_sale'] ?? 0);
} catch (Exception $e) {
    error_log("Sales metrics error: " . $e->getMessage());
}

// ============================================================================
// PAGE RENDERING
// ============================================================================

$page = [
    'title' => 'Dashboard Overview',
    'breadcrumbs' => [
        'Home' => '/admin/',
        'Dashboard' => null
    ],
    'icon' => 'fas fa-chart-line'
];

$content = <<<HTML
<div class="row">
    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title text-muted">Total Outlets</h6>
                <h2 class="text-primary">{$systemStats['total_outlets']}</h2>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title text-muted">Total Products</h6>
                <h2 class="text-info">{$systemStats['total_products']}</h2>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title text-muted">30-Day Sales</h6>
                <h2 class="text-success">\${$salesMetrics['total_sales']}</h2>
            </div>
        </div>
    </div>

    <div class="col-md-3">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title text-muted">Low Stock Items</h6>
                <h2 class="text-warning">{$systemStats['low_stock_items']}</h2>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        <h5 class="card-title">Sales Summary (Last 30 Days)</h5>
    </div>
    <div class="card-body">
        <p><strong>Total Transactions:</strong> {$salesMetrics['sales_count']}</p>
        <p><strong>Average Transaction:</strong> \${$salesMetrics['avg_transaction']}</p>
    </div>
</div>
HTML;

renderMainLayout($page, $content);
?>
```

**Copy This When:** You need a dashboard with multiple statistics cards
**Time to Adapt:** 5-10 minutes
**Difficulty:** Easy ⭐

---

## 2️⃣ TABLE WITH DATA

**From:** `/modules/admin-ui/pages/violations.php`

### Display Items in a Table
```php
<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once '/modules/base/templates/vape-ultra/config.php';
require_once '/modules/base/templates/vape-ultra/layouts/main.php';

// ============================================================================
// GET DATA
// ============================================================================

$violations = [];
$filter_severity = $_GET['severity'] ?? null;

try {
    $query = "SELECT id, title, severity, status, created_at FROM violations WHERE 1=1";
    $params = [];

    // Add severity filter if provided
    if ($filter_severity) {
        $query .= " AND severity = ?";
        $params[] = $filter_severity;
    }

    $query .= " ORDER BY created_at DESC LIMIT 100";

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);
    $violations = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("Violations error: " . $e->getMessage());
}

// ============================================================================
// PAGE RENDERING
// ============================================================================

$page = [
    'title' => 'Violations',
    'breadcrumbs' => [
        'Home' => '/admin/',
        'Violations' => null
    ]
];

// Build table rows
$table_rows = '';
foreach ($violations as $violation) {
    $id = htmlspecialchars($violation['id']);
    $title = htmlspecialchars($violation['title']);
    $severity = htmlspecialchars($violation['severity']);
    $status = htmlspecialchars($violation['status']);
    $date = $violation['created_at'];

    // Severity badge color
    $severity_color = match($severity) {
        'high' => 'danger',
        'medium' => 'warning',
        'low' => 'info',
        default => 'secondary'
    };

    // Status badge color
    $status_color = $status === 'resolved' ? 'success' : 'secondary';

    $table_rows .= <<<HTML
    <tr>
        <td>{$id}</td>
        <td>{$title}</td>
        <td><span class="badge bg-{$severity_color}">{$severity}</span></td>
        <td><span class="badge bg-{$status_color}">{$status}</span></td>
        <td>{$date}</td>
        <td>
            <button class="btn btn-sm btn-primary" onclick="editViolation({$id})">Edit</button>
            <button class="btn btn-sm btn-danger" onclick="deleteViolation({$id})">Delete</button>
        </td>
    </tr>
HTML;
}

$content = <<<HTML
<div class="card">
    <div class="card-header">
        <div class="row">
            <div class="col">
                <h5 class="card-title">Violations</h5>
            </div>
            <div class="col text-end">
                <button class="btn btn-primary" onclick="addViolation()">+ New Violation</button>
            </div>
        </div>
    </div>
    <div class="card-body">
        <table class="table table-striped table-hover">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>Title</th>
                    <th>Severity</th>
                    <th>Status</th>
                    <th>Date</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                {$table_rows}
            </tbody>
        </table>
    </div>
</div>

<script>
function editViolation(id) {
    window.location = '/admin/violations/edit?id=' + id;
}

function deleteViolation(id) {
    if (confirm('Delete this violation?')) {
        VapeUltra.API.post('/api/violations/delete', {id: id}, function(data) {
            if (data.success) {
                VapeUltra.Notifications.show('Success', 'Violation deleted', 'success');
                location.reload();
            } else {
                VapeUltra.Notifications.show('Error', data.error, 'danger');
            }
        });
    }
}

function addViolation() {
    window.location = '/admin/violations/new';
}
</script>
HTML;

renderMainLayout($page, $content);
?>
```

**Copy This When:** You need a data table with filtering and actions
**Time to Adapt:** 10-15 minutes
**Difficulty:** Medium ⭐⭐

---

## 3️⃣ FORM PAGE

**From:** `/modules/admin-ui/pages/settings.php`

### Settings Form with Validation
```php
<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once '/modules/base/templates/vape-ultra/config.php';
require_once '/modules/base/templates/vape-ultra/layouts/main.php';

// ============================================================================
// HANDLE FORM SUBMISSION
// ============================================================================

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Validate input
        $name = trim($_POST['name'] ?? '');
        $value = trim($_POST['value'] ?? '');

        if (empty($name)) {
            throw new Exception("Name is required");
        }

        if (empty($value)) {
            throw new Exception("Value is required");
        }

        // Check if setting exists
        $stmt = $pdo->prepare("SELECT id FROM settings WHERE name = ?");
        $stmt->execute([$name]);
        $existing = $stmt->fetch();

        if ($existing) {
            // Update
            $stmt = $pdo->prepare("UPDATE settings SET value = ?, updated_at = NOW() WHERE name = ?");
            $stmt->execute([$value, $name]);
            $message = "Setting updated successfully!";
        } else {
            // Insert
            $stmt = $pdo->prepare("INSERT INTO settings (name, value, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$name, $value]);
            $message = "Setting created successfully!";
        }
    } catch (Exception $e) {
        error_log("Settings error: " . $e->getMessage());
        $error = $e->getMessage();
    }
}

// ============================================================================
// GET CURRENT SETTINGS
// ============================================================================

$settings = [];
try {
    $stmt = $pdo->query("SELECT name, value FROM settings ORDER BY name");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {
    error_log("Settings fetch error: " . $e->getMessage());
}

// ============================================================================
// PAGE RENDERING
// ============================================================================

$page = [
    'title' => 'System Settings',
    'breadcrumbs' => [
        'Home' => '/admin/',
        'Settings' => null
    ]
];

$alert_html = '';
if ($message) {
    $alert_html = <<<HTML
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> {$message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
HTML;
}

if ($error) {
    $alert_html = <<<HTML
    <div class="alert alert-danger alert-dismissible fade show" role="alert">
        <i class="fas fa-exclamation-circle"></i> {$error}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
HTML;
}

$content = <<<HTML
{$alert_html}

<div class="card">
    <div class="card-header">
        <h5 class="card-title">Update Settings</h5>
    </div>
    <div class="card-body">
        <form method="POST">
            <div class="mb-3">
                <label for="name" class="form-label">Setting Name</label>
                <input type="text" class="form-control" id="name" name="name" required>
                <small class="form-text text-muted">e.g., "app_name", "max_users"</small>
            </div>

            <div class="mb-3">
                <label for="value" class="form-label">Setting Value</label>
                <textarea class="form-control" id="value" name="value" rows="4" required></textarea>
            </div>

            <div class="mb-3">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Save Setting
                </button>
                <a href="/admin/" class="btn btn-secondary">Cancel</a>
            </div>
        </form>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        <h5 class="card-title">Current Settings</h5>
    </div>
    <div class="card-body">
        <table class="table table-sm">
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Value</th>
                </tr>
            </thead>
            <tbody>
HTML;

foreach ($settings as $name => $value) {
    $name_safe = htmlspecialchars($name);
    $value_safe = htmlspecialchars($value);
    $content .= <<<HTML
            <tr>
                <td><strong>{$name_safe}</strong></td>
                <td><code>{$value_safe}</code></td>
            </tr>
HTML;
}

$content .= <<<HTML
            </tbody>
        </table>
    </div>
</div>
HTML;

renderMainLayout($page, $content);
?>
```

**Copy This When:** You need a form to update database records
**Time to Adapt:** 15-20 minutes
**Difficulty:** Medium ⭐⭐

---

## 4️⃣ MULTI-STORE DISPLAY

**From:** `/modules/consignments/pages/store_transfer_balance.php`

### Display Data for Multiple Stores
```php
<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once '/modules/base/templates/vape-ultra/config.php';
require_once '/modules/base/templates/vape-ultra/layouts/main.php';

// ============================================================================
// GET STORE TRANSFER BALANCE
// ============================================================================

$transfers = [];
$total_pending = 0;
$total_completed = 0;

try {
    $stmt = $pdo->query("
        SELECT
            v.outlet_name,
            COUNT(CASE WHEN st.status = 'pending' THEN 1 END) as pending_count,
            COUNT(CASE WHEN st.status = 'completed' THEN 1 END) as completed_count,
            SUM(CASE WHEN st.status = 'pending' THEN st.amount ELSE 0 END) as pending_amount,
            SUM(CASE WHEN st.status = 'completed' THEN st.amount ELSE 0 END) as completed_amount
        FROM store_transfers st
        JOIN vend_outlets v ON st.outlet_id = v.outlet_id
        GROUP BY v.outlet_name
        ORDER BY pending_count DESC
    ");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $transfers[] = $row;
        $total_pending += $row['pending_amount'] ?? 0;
        $total_completed += $row['completed_amount'] ?? 0;
    }
} catch (Exception $e) {
    error_log("Transfer balance error: " . $e->getMessage());
}

// ============================================================================
// PAGE RENDERING
// ============================================================================

$page = [
    'title' => 'Store Transfer Balance',
    'breadcrumbs' => [
        'Home' => '/admin/',
        'Consignments' => '/admin/consignments/',
        'Transfer Balance' => null
    ]
];

// Build transfer rows
$transfer_rows = '';
foreach ($transfers as $transfer) {
    $outlet = htmlspecialchars($transfer['outlet_name']);
    $pending = (int)($transfer['pending_count'] ?? 0);
    $completed = (int)($transfer['completed_count'] ?? 0);
    $pending_amt = (float)($transfer['pending_amount'] ?? 0);
    $completed_amt = (float)($transfer['completed_amount'] ?? 0);

    $transfer_rows .= <<<HTML
    <tr>
        <td>{$outlet}</td>
        <td><span class="badge bg-warning">{$pending} Pending</span></td>
        <td>\${$pending_amt}</td>
        <td><span class="badge bg-success">{$completed} Completed</span></td>
        <td>\${$completed_amt}</td>
        <td>
            <button class="btn btn-sm btn-info">View Details</button>
        </td>
    </tr>
HTML;
}

$content = <<<HTML
<div class="row">
    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title text-muted">Pending Transfers</h6>
                <h2 class="text-warning">\${$total_pending}</h2>
            </div>
        </div>
    </div>

    <div class="col-md-6">
        <div class="card">
            <div class="card-body">
                <h6 class="card-title text-muted">Completed Transfers</h6>
                <h2 class="text-success">\${$total_completed}</h2>
            </div>
        </div>
    </div>
</div>

<div class="card mt-4">
    <div class="card-header">
        <h5 class="card-title">Store Transfers</h5>
    </div>
    <div class="card-body">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>Store</th>
                    <th>Pending</th>
                    <th>Pending Amount</th>
                    <th>Completed</th>
                    <th>Completed Amount</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                {$transfer_rows}
            </tbody>
        </table>
    </div>
</div>
HTML;

renderMainLayout($page, $content);
?>
```

**Copy This When:** You need to display data grouped by store or location
**Time to Adapt:** 10-15 minutes
**Difficulty:** Medium ⭐⭐

---

## 5️⃣ FEED DISPLAY

**From:** `/modules/base/templates/vape-ultra/views/dashboard-feed.php`

### Activity Feed
```php
<?php
declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once '/modules/base/templates/vape-ultra/config.php';
require_once '/modules/base/templates/vape-ultra/layouts/main.php';

// ============================================================================
// GET FEED ITEMS
// ============================================================================

$feed_items = [];

try {
    // Recent orders
    $stmt = $pdo->query("
        SELECT 'order' as type, id, customer_name, total, created_at
        FROM vend_sales
        ORDER BY created_at DESC
        LIMIT 20
    ");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $feed_items[] = [
            'type' => 'order',
            'icon' => 'fas fa-shopping-cart',
            'color' => 'primary',
            'title' => 'Order from ' . $row['customer_name'],
            'amount' => '$' . $row['total'],
            'time' => $row['created_at']
        ];
    }

    // Recent alerts
    $stmt = $pdo->query("
        SELECT 'alert' as type, title, severity, created_at
        FROM violations
        ORDER BY created_at DESC
        LIMIT 10
    ");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $feed_items[] = [
            'type' => 'alert',
            'icon' => 'fas fa-exclamation-triangle',
            'color' => $row['severity'] === 'high' ? 'danger' : 'warning',
            'title' => $row['title'],
            'severity' => $row['severity'],
            'time' => $row['created_at']
        ];
    }

    // Sort by date
    usort($feed_items, function($a, $b) {
        return strtotime($b['time']) - strtotime($a['time']);
    });
} catch (Exception $e) {
    error_log("Feed error: " . $e->getMessage());
}

// ============================================================================
// PAGE RENDERING
// ============================================================================

$page = [
    'title' => 'Activity Feed',
    'breadcrumbs' => [
        'Home' => '/admin/',
        'Feed' => null
    ]
];

// Build feed
$feed_html = '';
foreach ($feed_items as $item) {
    $icon = htmlspecialchars($item['icon']);
    $title = htmlspecialchars($item['title']);
    $time = $item['time'];
    $color = htmlspecialchars($item['color']);

    $extra = '';
    if (isset($item['amount'])) {
        $extra = '<span class="text-muted ms-2">' . htmlspecialchars($item['amount']) . '</span>';
    }

    $feed_html .= <<<HTML
    <div class="feed-item">
        <div class="feed-icon">
            <i class="{$icon}" style="color: var(--bs-{$color})"></i>
        </div>
        <div class="feed-content">
            <p class="feed-title">{$title}{$extra}</p>
            <small class="text-muted">{$time}</small>
        </div>
    </div>
HTML;
}

$content = <<<HTML
<div class="card">
    <div class="card-header">
        <h5 class="card-title">Recent Activity</h5>
    </div>
    <div class="card-body feed-container">
        {$feed_html}
    </div>
</div>

<style>
.feed-container {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.feed-item {
    display: flex;
    gap: 12px;
    padding: 12px;
    border-radius: 4px;
    background: #f9f9f9;
}

.feed-icon {
    font-size: 18px;
    min-width: 24px;
}

.feed-content {
    flex: 1;
}

.feed-title {
    margin: 0;
    font-weight: 500;
}
</style>
HTML;

renderMainLayout($page, $content);
?>
```

**Copy This When:** You need to display activity or feed items
**Time to Adapt:** 10 minutes
**Difficulty:** Easy ⭐

---

## ✅ Summary: Which Pattern to Use

| Need | Example File | Pattern |
|------|---|---|
| Dashboard with stats | `overview.php` | Cards in grid |
| Table with data | `violations.php` | Bootstrap table |
| Form for settings | `settings.php` | POST form handling |
| Multi-store view | `store_transfer_balance.php` | Grouped queries |
| Activity feed | `dashboard-feed.php` | Timeline display |

---

## 🚀 Next Steps

1. **Pick one pattern** from above
2. **Copy the code** to a new file
3. **Change table/column names** for your data
4. **Test in browser**
5. **Celebrate!** 🎉

---

**All these examples are production-ready and used in your live system!**
