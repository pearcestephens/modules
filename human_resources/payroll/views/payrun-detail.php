<?php
/**
 * Pay Run Detail View
 *
 * Shows individual pay run with all employee payslips
 * Variables available: $summary, $payslips, $pageTitle
 *
 * @package HumanResources\Payroll\Views
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/human_resources/payroll/views/layouts/header.php';
?>

<style>
/* Pay Run Detail Specific Styles */
.pay-run-header {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: white;
    padding: 2rem;
    margin: -1.5rem -1.5rem 2rem -1.5rem;
    border-radius: 0.5rem 0.5rem 0 0;
}

.pay-run-header h1 {
    margin: 0;
    font-size: 1.75rem;
    font-weight: 600;
}

.pay-run-header .subtitle {
    opacity: 0.9;
    font-size: 0.95rem;
    margin-top: 0.5rem;
}

.summary-stats {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 1rem;
    margin-bottom: 2rem;
}

.summary-card {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 0.5rem;
    padding: 1.25rem;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.summary-card .label {
    color: #718096;
    font-size: 0.875rem;
    font-weight: 500;
    margin-bottom: 0.5rem;
}

.summary-card .value {
    font-size: 1.5rem;
    font-weight: 700;
    color: #2d3748;
}

.summary-card.success .value {
    color: #28a745;
}

.payslip-table {
    background: white;
    border-radius: 0.5rem;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,0.1);
}

.payslip-table table {
    width: 100%;
    margin: 0;
}

.payslip-table th {
    background: #f7fafc;
    color: #4a5568;
    font-weight: 600;
    text-align: left;
    padding: 1rem;
    font-size: 0.875rem;
    border-bottom: 2px solid #e2e8f0;
}

.payslip-table td {
    padding: 1rem;
    border-bottom: 1px solid #e2e8f0;
}

.payslip-table tbody tr:hover {
    background: #f7fafc;
}

.status-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 9999px;
    font-size: 0.75rem;
    font-weight: 600;
    text-transform: uppercase;
}

.status-badge.draft {
    background: #fef3c7;
    color: #92400e;
}

.status-badge.calculated {
    background: #dbeafe;
    color: #1e3a8a;
}

.status-badge.approved {
    background: #d1fae5;
    color: #065f46;
}

.action-buttons {
    display: flex;
    gap: 1rem;
    margin-bottom: 2rem;
}

.btn {
    padding: 0.75rem 1.5rem;
    border-radius: 0.375rem;
    font-weight: 600;
    text-decoration: none;
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border: none;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-primary {
    background: #667eea;
    color: white;
}

.btn-primary:hover {
    background: #5568d3;
}

.btn-success {
    background: #28a745;
    color: white;
}

.btn-success:hover {
    background: #218838;
}

.btn-secondary {
    background: #6c757d;
    color: white;
}

.btn-secondary:hover {
    background: #5a6268;
}

.btn-outline {
    background: white;
    color: #667eea;
    border: 2px solid #667eea;
}

.btn-outline:hover {
    background: #667eea;
    color: white;
}

@media (max-width: 768px) {
    .summary-stats {
        grid-template-columns: 1fr;
    }

    .action-buttons {
        flex-direction: column;
    }

    .payslip-table {
        overflow-x: auto;
    }
}
</style>

<div class="pay-run-header">
    <div class="d-flex justify-content-between align-items-center">
        <div>
            <h1><?php echo htmlspecialchars($pageTitle); ?></h1>
            <p class="subtitle mb-0">
                <i class="bi bi-calendar3"></i>
                <?php echo date('F j, Y', strtotime($summary['period_start'])); ?> -
                <?php echo date('F j, Y', strtotime($summary['period_end'])); ?>
            </p>
        </div>
        <a href="/modules/human_resources/payroll/?view=payruns" class="btn btn-outline">
            <i class="bi bi-arrow-left"></i> Back to List
        </a>
    </div>
</div>

<!-- Summary Statistics -->
<div class="summary-stats">
    <div class="summary-card">
        <div class="label">
            <i class="bi bi-people"></i> Employees
        </div>
        <div class="value"><?php echo number_format($summary['employee_count']); ?></div>
    </div>

    <div class="summary-card">
        <div class="label">
            <i class="bi bi-cash-stack"></i> Gross Pay
        </div>
        <div class="value">$<?php echo number_format($summary['total_gross'], 2); ?></div>
    </div>

    <div class="summary-card">
        <div class="label">
            <i class="bi bi-calculator"></i> Deductions
        </div>
        <div class="value">$<?php echo number_format($summary['total_deductions'], 2); ?></div>
    </div>

    <div class="summary-card success">
        <div class="label">
            <i class="bi bi-currency-dollar"></i> Net Pay
        </div>
        <div class="value">$<?php echo number_format($summary['total_net'], 2); ?></div>
    </div>
</div>

<!-- Action Buttons -->
<div class="action-buttons">
    <?php if (in_array('draft', $summary['statuses']) || in_array('calculated', $summary['statuses'])): ?>
    <button class="btn btn-success" onclick="approvePayRun('<?php echo $summary['period_key']; ?>')">
        <i class="bi bi-check-circle"></i> Approve Pay Run
    </button>
    <?php endif; ?>

    <button class="btn btn-primary" onclick="exportToXero('<?php echo $summary['period_key']; ?>')">
        <i class="bi bi-cloud-upload"></i> Export to Xero
    </button>

    <button class="btn btn-secondary" onclick="printPayRun('<?php echo $summary['period_key']; ?>')">
        <i class="bi bi-printer"></i> Print
    </button>

    <button class="btn btn-outline" onclick="emailPayslips('<?php echo $summary['period_key']; ?>')">
        <i class="bi bi-envelope"></i> Email Payslips
    </button>
</div>

<!-- Payslips Table -->
<div class="payslip-table">
    <table>
        <thead>
            <tr>
                <th>Employee</th>
                <th>Ordinary Hours</th>
                <th>Overtime Hours</th>
                <th>Gross Pay</th>
                <th>Bonuses</th>
                <th>Deductions</th>
                <th>Net Pay</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($payslips as $payslip): ?>
            <tr>
                <td>
                    <strong><?php echo htmlspecialchars($payslip['employee_name']); ?></strong><br>
                    <small class="text-muted"><?php echo htmlspecialchars($payslip['employee_email']); ?></small>
                </td>
                <td><?php echo number_format($payslip['ordinary_hours'], 2); ?> hrs</td>
                <td><?php echo number_format($payslip['overtime_hours'], 2); ?> hrs</td>
                <td>$<?php echo number_format($payslip['gross_pay'], 2); ?></td>
                <td>$<?php echo number_format($payslip['total_bonuses'], 2); ?></td>
                <td>$<?php echo number_format($payslip['total_deductions'], 2); ?></td>
                <td><strong>$<?php echo number_format($payslip['net_pay'], 2); ?></strong></td>
                <td>
                    <span class="status-badge <?php echo $payslip['status']; ?>">
                        <?php echo ucfirst($payslip['status']); ?>
                    </span>
                </td>
                <td>
                    <button class="btn btn-sm btn-outline"
                            onclick="viewPayslipDetail(<?php echo $payslip['id']; ?>)"
                            title="View Details">
                        <i class="bi bi-eye"></i>
                    </button>
                    <button class="btn btn-sm btn-outline"
                            onclick="downloadPayslip(<?php echo $payslip['id']; ?>)"
                            title="Download PDF">
                        <i class="bi bi-download"></i>
                    </button>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Toast Container -->
<div class="toast-container" id="toastContainer"></div>

<script>
// Approve pay run
function approvePayRun(periodKey) {
    if (!confirm('Are you sure you want to approve this pay run? This action cannot be undone.')) {
        return;
    }

    showLoading(true);

    fetch(`/modules/human_resources/payroll/?api=payruns/${periodKey}/approve`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-Token': getCsrfToken()
        }
    })
    .then(response => response.json())
    .then(data => {
        showLoading(false);

        if (data.success) {
            showToast('Pay run approved successfully!', 'success');
            setTimeout(() => location.reload(), 1500);
        } else {
            showToast(data.error || 'Failed to approve pay run', 'error');
        }
    })
    .catch(error => {
        showLoading(false);
        console.error('Approve error:', error);
        showToast('Failed to approve pay run', 'error');
    });
}

// Export to Xero
function exportToXero(periodKey) {
    showLoading(true);

    fetch(`/modules/human_resources/payroll/?api=payruns/${periodKey}/export`, {
        method: 'GET',
        headers: {
            'X-CSRF-Token': getCsrfToken()
        }
    })
    .then(response => response.json())
    .then(data => {
        showLoading(false);

        if (data.success) {
            showToast('Pay run exported to Xero successfully!', 'success');
        } else {
            showToast(data.error || 'Failed to export to Xero', 'error');
        }
    })
    .catch(error => {
        showLoading(false);
        console.error('Export error:', error);
        showToast('Failed to export to Xero', 'error');
    });
}

// Print pay run
function printPayRun(periodKey) {
    window.print();
}

// Email payslips
function emailPayslips(periodKey) {
    if (!confirm('Send payslips to all employees via email?')) {
        return;
    }

    showToast('Email functionality coming soon!', 'info');
}

// View payslip detail (modal)
function viewPayslipDetail(payslipId) {
    showToast('Payslip detail view coming soon!', 'info');
}

// Download payslip PDF
function downloadPayslip(payslipId) {
    window.open(`/modules/human_resources/payroll/?api=payslips/${payslipId}/pdf`, '_blank');
}
</script>

<?php require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/human_resources/payroll/views/layouts/footer.php'; ?>
