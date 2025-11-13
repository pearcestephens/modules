<!doctype html>
<html lang="en">
<head>
	<meta charset="utf-8" />
	<title>Store Reports Module</title>
	<meta name="csrf-token" content="<?= htmlspecialchars(csrf_token()); ?>" />
	<style>
		body{font-family:system-ui,Arial,sans-serif;margin:0;padding:0;background:#f8f9fa;color:#212529}
		header{background:#343a40;color:#fff;padding:10px 16px;display:flex;align-items:center;gap:16px}
		header a{color:#fff;text-decoration:none;font-size:14px;}
		nav a{margin-right:12px;}
		.container{padding:20px;}
		.sr-dropzone{background:#fff;border:2px dashed #6c757d;border-radius:6px;text-align:center;}
		.sr-dropzone.hover{border-color:#0d6efd;background:#e9f3ff}
		table{width:100%;border-collapse:collapse;margin-top:12px}
		th,td{border:1px solid #dee2e6;padding:6px;font-size:13px}
		th{background:#e9ecef}
		.btn{display:inline-block;padding:4px 10px;border-radius:4px;font-size:12px;border:1px solid #6c757d;background:#fff;color:#212529;text-decoration:none;}
		.btn-primary{background:#0d6efd;color:#fff;border-color:#0d6efd}
		.btn-secondary{background:#6c757d;color:#fff;border-color:#6c757d}
		.btn-outline-info{border-color:#0dcaf0;color:#0dcaf0}
	</style>
	<script>
		window.SR_CSRF='<?= csrf_token(); ?>';
	</script>
</head>
<body>
<header>
	<strong>Store Reports</strong>
	<nav>
		<a href="<?= htmlspecialchars(sr_url('dashboard')); ?>">Dashboard</a>
		<a href="<?= htmlspecialchars(sr_url('list')); ?>">Reports</a>
		<a href="<?= htmlspecialchars(sr_url('analytics')); ?>">Analytics</a>
		<a href="<?= htmlspecialchars(sr_url('history')); ?>">History</a>
		<a href="<?= htmlspecialchars(sr_url('create')); ?>">New Report</a>
	</nav>
	<span style="margin-left:auto;font-size:11px;opacity:.8">Correlation: <?= htmlspecialchars($SR_CORRELATION_ID ?? 'n/a'); ?></span>
</header>
<div class="container">
<?php
// Include view content selected by router (index.php already decided $viewMap)
if (isset($action) && isset($viewMap[$action])) {
		include __DIR__ . '/../' . basename($viewMap[$action]);
} else {
		echo '<p>Invalid view.</p>';
}
?>
</div>
</body>
</html>
