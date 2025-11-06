<?php
/**
 * Audit Trail View
 * Complete searchable audit log
 */

$filters = $_GET ?? [];
$auditTrail = $dashboard->getAuditTrail($filters, 100);
?>

<div class="audit-trail-view">
    <div class="view-header">
        <h4>📋 Complete Audit Trail</h4>
        <p class="text-muted">Full history of all payroll decisions and actions</p>
    </div>

    <div class="audit-filters mb-3">
        <form method="GET" class="row g-3">
            <div class="col-md-3">
                <label class="form-label">Staff Member</label>
                <select name="staff_id" class="form-select">
                    <option value="">All Staff</option>
                    <?php
                    // Get all staff
                    $staffStmt = $pdo->query("SELECT id, name FROM staff WHERE active = 1 ORDER BY name");
                    while ($staff = $staffStmt->fetch(PDO::FETCH_ASSOC)) {
                        $selected = (isset($filters['staff_id']) && $filters['staff_id'] == $staff['id']) ? 'selected' : '';
                        echo "<option value='{$staff['id']}' {$selected}>{$staff['name']}</option>";
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Type</label>
                <select name="item_type" class="form-select">
                    <option value="">All Types</option>
                    <option value="timesheet" <?php echo (isset($filters['item_type']) && $filters['item_type'] === 'timesheet') ? 'selected' : ''; ?>>Timesheet</option>
                    <option value="payrun" <?php echo (isset($filters['item_type']) && $filters['item_type'] === 'payrun') ? 'selected' : ''; ?>>Payrun</option>
                    <option value="vend" <?php echo (isset($filters['item_type']) && $filters['item_type'] === 'vend') ? 'selected' : ''; ?>>Vend</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Decision</label>
                <select name="decision" class="form-select">
                    <option value="">All Decisions</option>
                    <option value="auto_approve" <?php echo (isset($filters['decision']) && $filters['decision'] === 'auto_approve') ? 'selected' : ''; ?>>Auto-Approve</option>
                    <option value="manual_review" <?php echo (isset($filters['decision']) && $filters['decision'] === 'manual_review') ? 'selected' : ''; ?>>Manual Review</option>
                    <option value="escalate" <?php echo (isset($filters['decision']) && $filters['decision'] === 'escalate') ? 'selected' : ''; ?>>Escalated</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label">Date From</label>
                <input type="date" name="date_from" class="form-control" value="<?php echo $filters['date_from'] ?? ''; ?>">
            </div>
            <div class="col-md-2">
                <label class="form-label">Date To</label>
                <input type="date" name="date_to" class="form-control" value="<?php echo $filters['date_to'] ?? ''; ?>">
            </div>
            <div class="col-md-1 d-flex align-items-end">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-search"></i> Filter
                </button>
            </div>
        </form>
    </div>

    <?php if (empty($auditTrail)): ?>
        <div class="alert alert-info">
            <i class="fas fa-info-circle"></i>
            No audit records found matching your filters.
        </div>
    <?php else: ?>
        <div class="table-responsive">
            <table class="table table-striped table-hover audit-table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>Staff</th>
                        <th>Type</th>
                        <th>AI Decision</th>
                        <th>Confidence</th>
                        <th>Human Action</th>
                        <th>Processed By</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($auditTrail as $record): ?>
                        <tr>
                            <td>
                                <small><?php echo date('d M Y', strtotime($record['created_at'])); ?></small><br>
                                <small class="text-muted"><?php echo date('H:i:s', strtotime($record['created_at'])); ?></small>
                            </td>
                            <td>
                                <strong><?php echo htmlspecialchars($record['staff_name'] ?? 'Unknown'); ?></strong>
                            </td>
                            <td>
                                <span class="badge bg-secondary">
                                    <?php echo htmlspecialchars(ucfirst($record['item_type'])); ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($record['decision'] === 'auto_approve'): ?>
                                    <span class="badge bg-success">✓ Auto-Approved</span>
                                <?php elseif ($record['decision'] === 'manual_review'): ?>
                                    <span class="badge bg-warning">⚠ Manual Review</span>
                                <?php elseif ($record['decision'] === 'escalate'): ?>
                                    <span class="badge bg-danger">🔺 Escalated</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="confidence-badge" style="background-color: <?php
                                    if ($record['confidence_score'] >= 0.8) echo '#28a745';
                                    elseif ($record['confidence_score'] >= 0.6) echo '#ffc107';
                                    else echo '#dc3545';
                                ?>;">
                                    <?php echo round($record['confidence_score'] * 100); ?>%
                                </span>
                            </td>
                            <td>
                                <?php if ($record['human_action']): ?>
                                    <?php if ($record['human_action'] === 'approved'): ?>
                                        <span class="badge bg-success">✓ Approved</span>
                                    <?php elseif ($record['human_action'] === 'denied'): ?>
                                        <span class="badge bg-danger">✗ Denied</span>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($record['processed_by_username']): ?>
                                    <small><?php echo htmlspecialchars($record['processed_by_username']); ?></small>
                                <?php else: ?>
                                    <small class="text-muted">AI</small>
                                <?php endif; ?>
                            </td>
                            <td>
                                <?php if ($record['processed_at']): ?>
                                    <span class="badge bg-success">Processed</span>
                                <?php else: ?>
                                    <span class="badge bg-warning">Pending</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-primary" onclick="viewAuditDetails(<?php echo $record['id']; ?>)">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="audit-summary mt-4">
            <div class="row">
                <div class="col-md-12">
                    <div class="card">
                        <div class="card-body">
                            <h5>Audit Summary</h5>
                            <div class="row">
                                <div class="col-md-3">
                                    <strong>Total Records:</strong> <?php echo count($auditTrail); ?>
                                </div>
                                <div class="col-md-3">
                                    <strong>Auto-Approved:</strong>
                                    <?php echo count(array_filter($auditTrail, fn($r) => $r['decision'] === 'auto_approve')); ?>
                                </div>
                                <div class="col-md-3">
                                    <strong>Human Actions:</strong>
                                    <?php echo count(array_filter($auditTrail, fn($r) => !empty($r['human_action']))); ?>
                                </div>
                                <div class="col-md-3">
                                    <strong>AI Accuracy:</strong>
                                    <?php
                                    $withHumanAction = array_filter($auditTrail, fn($r) => !empty($r['human_action']));
                                    $correct = count(array_filter($withHumanAction, fn($r) => $r['decision'] === $r['human_action']));
                                    $total = count($withHumanAction);
                                    echo $total > 0 ? round(($correct / $total) * 100, 1) . '%' : 'N/A';
                                    ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="export-actions mt-3">
            <button class="btn btn-outline-secondary" onclick="exportAuditTrail()">
                <i class="fas fa-download"></i> Export to CSV
            </button>
            <button class="btn btn-outline-secondary" onclick="printAuditTrail()">
                <i class="fas fa-print"></i> Print Report
            </button>
        </div>
    <?php endif; ?>
</div>

<style>
.audit-table {
    font-size: 13px;
}

.audit-table th {
    background-color: #f8f9fa;
    font-weight: 600;
}

.confidence-badge {
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: bold;
}

.audit-filters {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
}
</style>

<script>
function viewAuditDetails(decisionId) {
    // Show modal with full details
    alert('View details for decision #' + decisionId);
}

function exportAuditTrail() {
    // Export current filtered results to CSV
    window.location.href = 'api/export-audit.php?' + new URLSearchParams(<?php echo json_encode($filters); ?>);
}

function printAuditTrail() {
    window.print();
}
</script>
