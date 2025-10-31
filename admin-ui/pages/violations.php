<?php
/**
 * Dashboard Violations Page
 * View and manage rule violations
 *
 * @package hdgwrzntwa/dashboard/admin
 * @category Dashboard Page
 */

$projectId = 1;
$severity = $_GET['severity'] ?? '';
$page = (int)($_GET['viol_page'] ?? 1);
$limit = 25;
$offset = ($page - 1) * $limit;

$pdo = new PDO("mysql:host=localhost;dbname=hdgwrzntwa", "hdgwrzntwa", "bFUdRjh4Jx");

// Build query
$baseQuery = "FROM project_rule_violations WHERE project_id = :projectId";
$params = [':projectId' => $projectId];

if ($severity) {
    $baseQuery .= " AND severity = :severity";
    $params[':severity'] = $severity;
}

// Get total
$countQuery = "SELECT COUNT(*) FROM project_rule_violations WHERE project_id = :projectId" . ($severity ? " AND severity = :severity" : "");
$countStmt = $pdo->prepare($countQuery);
$countStmt->execute($severity ? [':projectId' => $projectId, ':severity' => $severity] : [':projectId' => $projectId]);
$total = (int)$countStmt->fetchColumn();
$totalPages = ceil($total / $limit);

// Get violations
$limitInt = (int)$limit;
$offsetInt = (int)$offset;
$query = "SELECT * $baseQuery ORDER BY severity DESC, detected_at DESC LIMIT $limitInt OFFSET $offsetInt";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$violations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get severity summary
$summaryQuery = "
    SELECT
        severity,
        COUNT(*) as count
    FROM project_rule_violations
    WHERE project_id = ?
    GROUP BY severity
    ORDER BY FIELD(severity, 'critical', 'high', 'medium', 'low')
";

$summaryStmt = $pdo->prepare($summaryQuery);
$summaryStmt->execute([$projectId]);
$summary = $summaryStmt->fetchAll(PDO::FETCH_ASSOC);

$severityColors = [
    'critical' => 'danger',
    'high' => 'warning',
    'medium' => 'info',
    'low' => 'secondary'
];
?>

<div class="dashboard-container">
    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h1>Rule Violations</h1>
            <p class="text-muted">Manage coding standard violations</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#fixViolationModal">
            <i class="fas fa-wrench"></i> Quick Fix
        </button>
    </div>

    <!-- Severity Summary -->
    <div class="row mb-4">
        <?php foreach ($summary as $item): ?>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h6 class="text-muted text-uppercase">
                            <span class="badge bg-<?php echo $severityColors[$item['severity']]; ?>">
                                <?php echo ucfirst($item['severity']); ?>
                            </span>
                        </h6>
                        <h3><?php echo $item['count']; ?></h3>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

    <!-- Filter -->
    <div class="card mb-4">
        <div class="card-body">
            <div class="row g-2">
                <div class="col-md-8">
                    <select class="form-control" id="severityFilter">
                        <option value="">All Severities</option>
                        <option value="critical" <?php echo $severity === 'critical' ? 'selected' : ''; ?>>Critical Only</option>
                        <option value="high" <?php echo $severity === 'high' ? 'selected' : ''; ?>>High Only</option>
                        <option value="medium" <?php echo $severity === 'medium' ? 'selected' : ''; ?>>Medium Only</option>
                        <option value="low" <?php echo $severity === 'low' ? 'selected' : ''; ?>>Low Only</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <button class="btn btn-outline-secondary w-100" onclick="applySeverityFilter()">
                        <i class="fas fa-filter"></i> Filter
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Violations Table -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Rule</th>
                        <th>File</th>
                        <th>Line</th>
                        <th>Severity</th>
                        <th>Description</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($violations)): ?>
                        <tr>
                            <td colspan="6" class="text-center py-4 text-muted">
                                <i class="fas fa-check-circle"></i> No violations found
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($violations as $violation): ?>
                            <tr>
                                <td>
                                    <strong><?php echo htmlspecialchars($violation['rule_name']); ?></strong>
                                </td>
                                <td>
                                    <code><?php echo htmlspecialchars(substr($violation['file_path'], -40)); ?></code>
                                </td>
                                <td>
                                    <small><?php echo $violation['line_number']; ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-<?php echo $severityColors[$violation['severity']]; ?>">
                                        <?php echo ucfirst($violation['severity']); ?>
                                    </span>
                                </td>
                                <td>
                                    <small><?php echo htmlspecialchars(substr($violation['description'], 0, 50)); ?>...</small>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary"
                                            onclick="showViolationDetails('<?php echo $violation['id']; ?>')">
                                        <i class="fas fa-eye"></i> View
                                    </button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Pagination -->
    <?php if ($totalPages > 1): ?>
        <nav class="mt-4">
            <ul class="pagination justify-content-center">
                <?php if ($page > 1): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=violations&viol_page=1&severity=<?php echo urlencode($severity); ?>">First</a>
                    </li>
                <?php endif; ?>

                <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                    <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                        <a class="page-link" href="?page=violations&viol_page=<?php echo $i; ?>&severity=<?php echo urlencode($severity); ?>">
                            <?php echo $i; ?>
                        </a>
                    </li>
                <?php endfor; ?>

                <?php if ($page < $totalPages): ?>
                    <li class="page-item">
                        <a class="page-link" href="?page=violations&viol_page=<?php echo $totalPages; ?>&severity=<?php echo urlencode($severity); ?>">Last</a>
                    </li>
                <?php endif; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<script>
function applySeverityFilter() {
    const severity = document.getElementById('severityFilter').value;
    window.location = `?page=violations&severity=${encodeURIComponent(severity)}`;
}

function showViolationDetails(violationId) {
    API.get(`/dashboard/api/violations/details/${violationId}`, function(data) {
        Notify.info('Violation: ' + JSON.stringify(data.data, null, 2));
    });
}
</script>
