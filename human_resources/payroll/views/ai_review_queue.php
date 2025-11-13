<?php
/**
 * AI Review Queue UI (stub)
 *
 * - Lists pending AI decisions for human review
 * - Shows confidence, reason, and override button
 * - To be implemented: real-time dashboard, audit log
 */
require_once __DIR__ . '/../../../../app.php';

// Fetch pending AI decisions (stub query)
$pending = $conn->query("SELECT * FROM v_ai_decisions_pending_review ORDER BY created_at DESC LIMIT 100")->fetch_all(MYSQLI_ASSOC);
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AI Review Queue</title>
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
    <h2>AI Review Queue</h2>
    <table class="table table-bordered table-sm mt-3">
        <thead>
        <tr>
            <th>ID</th>
            <th>Type</th>
            <th>Staff</th>
            <th>Confidence</th>
            <th>Reason</th>
            <th>Created</th>
            <th>Action</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($pending as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['id']) ?></td>
                <td><?= htmlspecialchars($row['decision_type']) ?></td>
                <td><?= htmlspecialchars($row['staff_id']) ?></td>
                <td><?= htmlspecialchars($row['confidence']) ?></td>
                <td><?= htmlspecialchars($row['reason']) ?></td>
                <td><?= htmlspecialchars($row['created_at']) ?></td>
                <td><button class="btn btn-warning btn-sm">Override</button></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
