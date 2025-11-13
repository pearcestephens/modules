<?php

// Get CIS database connection
require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
/**
 * Dashboard Rules Page
 * Manage coding standards and rules
 *
 * @package hdgwrzntwa/dashboard/admin
 * @category Dashboard Page
 */

$projectId = 1;

// Get all rules
$query = "
    SELECT
        id,
        standard_key as rule_name,
        description,
        enforced as enabled,
        category,
        created_at
    FROM code_standards
    ORDER BY priority DESC, standard_key ASC
";

$stmt = $pdo->prepare($query);
$stmt->execute([]);
$rules = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Count by category
$countQuery = "
    SELECT category, COUNT(*) as count
    FROM code_standards
    GROUP BY category
";

$countStmt = $pdo->prepare($countQuery);
$countStmt->execute([]);
$severityCounts = $countStmt->fetchAll(PDO::FETCH_KEY_PAIR);
?>

<div class="dashboard-container">
    <!-- Page Header -->
    <div class="page-header d-flex justify-content-between align-items-center">
        <div>
            <h1>Coding Rules</h1>
            <p class="text-muted">Manage project coding standards</p>
        </div>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#newRuleModal">
            <i class="fas fa-plus"></i> Create Rule
        </button>
    </div>

    <!-- Rule Statistics -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3><?php echo count($rules); ?></h3>
                    <p class="text-muted mb-0">Total Rules</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3><?php echo $severityCounts['critical'] ?? 0; ?></h3>
                    <p class="text-muted mb-0"><span class="badge bg-info">Security</span></p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3><?php echo count(array_filter($rules, fn($r) => $r['enabled'])); ?></h3>
                    <p class="text-muted mb-0">Enabled</p>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card text-center">
                <div class="card-body">
                    <h3><?php echo array_sum(array_map(fn($r) => $r['violation_count'] ?? 0, $rules)); ?></h3>
                    <p class="text-muted mb-0">Total Violations</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Rules List -->
    <div class="card">
        <div class="table-responsive">
            <table class="table table-hover mb-0">
                <thead class="table-light">
                    <tr>
                        <th>Status</th>
                        <th>Rule</th>
                        <th>Category</th>
                        <th>Priority</th>
                        <th>Description</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($rules as $rule): ?>
                        <tr>
                            <td>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox"
                                           <?php echo $rule['enabled'] ? 'checked' : ''; ?>
                                           onchange="toggleRule('<?php echo $rule['id']; ?>')">
                                </div>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($rule['rule_name']); ?></strong>
                            </td>
                            <td>
                                <span class="badge bg-secondary"><?php echo htmlspecialchars($rule['category'] ?? 'General'); ?></span>
                            </td>
                            <td>
                                <small class="text-muted"><?php echo $rule['priority'] ?? 50; ?></small>
                            </td>
                            <td>
                                <small><?php echo htmlspecialchars(substr($rule['description'], 0, 40)); ?>...</small>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary"
                                        onclick="editRule('<?php echo $rule['id']; ?>')">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-sm btn-outline-danger"
                                        onclick="deleteRule('<?php echo $rule['id']; ?>')">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
function toggleRule(ruleId) {
    API.post('/dashboard/api/rules/toggle', {id: ruleId}, function(data) {
        Notify.success('Rule updated');
        setTimeout(() => location.reload(), 1000);
    });
}

function editRule(ruleId) {
    alert('Edit rule ' + ruleId);
}

function deleteRule(ruleId) {
    if (confirm('Delete this rule? This action cannot be undone.')) {
        API.post('/dashboard/api/rules/delete', {id: ruleId}, function(data) {
            Notify.success('Rule deleted');
            setTimeout(() => location.reload(), 1000);
        });
    }
}
</script>
