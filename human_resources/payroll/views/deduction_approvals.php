<?php
/**
 * Deduction Approvals UI (stub)
 *
 * - Lists pending deduction applications for approval
 * - Shows staff, order type, amount, status
 * - To be implemented: approve/reject, audit log
 */
require_once __DIR__ . '/../../../../app.php';

$pending = $conn->query("SELECT * FROM payroll_nz_deduction_applications WHERE is_active = 0 ORDER BY created_at DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Deduction Approvals</title>
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
    <h2>Deduction Approvals</h2>
    <table class="table table-bordered table-sm mt-3">
        <thead>
        <tr>
            <th>ID</th>
            <th>Staff</th>
            <th>Order Type</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Created</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($pending as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['id']) ?></td>
                <td><?= htmlspecialchars($row['staff_id']) ?></td>
                <td><?= htmlspecialchars($row['order_type']) ?></td>
                <td><?= htmlspecialchars($row['amount']) ?></td>
                <td><?= $row['is_active'] ? 'Active' : 'Pending' ?></td>
                <td><?= htmlspecialchars($row['created_at']) ?></td>
                <td><button class="btn btn-success btn-sm">Approve</button></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
