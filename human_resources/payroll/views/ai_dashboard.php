<?php
/**
 * AI Dashboard UI (stub)
 *
 * - Shows real-time AI decision stats, performance, error rates
 * - To be implemented: charts, alerts, config
 */
require_once __DIR__ . '/../../../../app.php';

$stats = $conn->query("SELECT * FROM v_ai_decisions_dashboard LIMIT 1")->fetch_assoc();
?><!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>AI Dashboard</title>
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
</head>
<body>
<div class="container mt-4">
    <h2>AI Dashboard</h2>
    <table class="table table-bordered table-sm mt-3">
        <tr><th>Total Decisions</th><td><?= htmlspecialchars($stats['total_decisions'] ?? '-') ?></td></tr>
        <tr><th>Auto-Approved</th><td><?= htmlspecialchars($stats['auto_approved'] ?? '-') ?></td></tr>
        <tr><th>Human Review</th><td><?= htmlspecialchars($stats['pending_review'] ?? '-') ?></td></tr>
        <tr><th>Avg Confidence</th><td><?= htmlspecialchars($stats['avg_confidence'] ?? '-') ?></td></tr>
        <tr><th>Errors</th><td><?= htmlspecialchars($stats['error_count'] ?? '-') ?></td></tr>
    </table>
    <div class="alert alert-info">Charts and alerts coming soon.</div>
</div>
</body>
</html>
