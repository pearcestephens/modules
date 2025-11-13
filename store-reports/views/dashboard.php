<?php
// Simple dashboard stub with DB availability guard
if (!sr_db_available()) {
	$reports = null;
} else {
	$reports = sr_query("SELECT id,outlet_id,overall_score,grade,status,report_date FROM store_reports ORDER BY report_date DESC LIMIT 10");
}
?>
<h2>Store Reports Dashboard</h2>
<p><a href="?action=create" class="btn btn-sm btn-primary">Start New Report</a> <a href="?action=list" class="btn btn-sm btn-secondary">All Reports</a></p>
<table class="table table-striped">
	<thead><tr><th>ID</th><th>Outlet</th><th>Status</th><th>Score</th><th>Grade</th><th>Date</th><th>View</th></tr></thead>
	<tbody>
    <?php if (is_array($reports) && count($reports)): foreach ($reports as $r): ?>
		<tr>
			<td><?= (int)$r['id'] ?></td>
			<td><?= htmlspecialchars($r['outlet_id']) ?></td>
			<td><?= htmlspecialchars($r['status']) ?></td>
			<td><?= htmlspecialchars($r['overall_score']) ?></td>
			<td><?= htmlspecialchars($r['grade']) ?></td>
			<td><?= htmlspecialchars($r['report_date']) ?></td>
			<td><a class="btn btn-sm btn-outline-info" href="?action=view&report_id=<?= (int)$r['id'] ?>">Open</a></td>
		</tr>
	<?php endforeach; elseif ($reports === null): ?>
		<tr><td colspan="7">Database unavailable.</td></tr>
	<?php else: ?>
		<tr><td colspan="7">No reports yet.</td></tr>
	<?php endif; ?>
	</tbody>
</table>
