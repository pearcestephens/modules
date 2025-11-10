<?php
// Determine user details for greeting if available
$uid = $_SESSION['userID'] ?? null;
$userDetails = ['first_name' => 'User'];
if ($uid && function_exists('getUserInformation')) {
	try {
		$ud = getUserInformation($uid);
		if (is_array($ud)) { $userDetails = $ud; }
		elseif (is_object($ud)) { $userDetails = ['first_name' => $ud->first_name ?? 'User']; }
	} catch (Throwable $e) { /* ignore */ }
}
?>
<body>
	<header class="cisv2-header border-bottom">
		<nav class="navbar navbar-expand-lg bg-body px-2 px-sm-3">
			<div class="container-fluid">
				<button class="btn btn-ghost" type="button" aria-label="Toggle sidebar" data-cisv2-toggle="sidebar">
					<i class="fa-solid fa-bars"></i>
				</button>
				<a href="/" class="brand navbar-brand me-2">
					<img src="/assets/img/brand/vapeshed-emblem.png" alt="Logo" />
					<span>CIS</span>
				</a>

				<div class="d-none d-lg-flex align-items-center gap-2 flex-grow-1">
					<div class="flex-grow-1"></div>
					<button class="btn btn-light" data-cisv2-toggle="theme" title="Toggle theme"><i class="fa-solid fa-circle-half-stroke"></i></button>
					<div class="dropdown">
						<button class="btn btn-light position-relative" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
							<i class="fa-regular fa-bell"></i>
							<span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">0</span>
						</button>
						<div class="dropdown-menu dropdown-menu-end shadow-sm p-0" style="min-width: 320px;">
							<div class="p-3 text-muted">No new notifications</div>
						</div>
					</div>
					<div class="dropdown">
						<a href="#" class="d-flex align-items-center text-decoration-none" data-bs-toggle="dropdown" aria-expanded="false">
							<img class="rounded-circle" src="/assets/img/avatars/6.jpg" width="32" height="32" alt="Avatar" />
							<span class="ms-2 d-none d-xl-inline">Hello, <?= htmlspecialchars($userDetails['first_name'] ?? 'User'); ?></span>
						</a>
						<ul class="dropdown-menu dropdown-menu-end">
							<li><a class="dropdown-item" href="/my-account.php"><i class="fa-regular fa-user me-2"></i>My Account</a></li>
							<?php if ($uid): ?><li><a class="dropdown-item" href="?logout=true"><i class="fa-solid fa-arrow-right-from-bracket me-2"></i>Logout</a></li><?php endif; ?>
						</ul>
					</div>
				</div>
			</div>
		</nav>

		<?php
			$__crumbs = $GLOBALS['CIS_BREADCRUMBS_DATA'] ?? [];
			if (is_array($__crumbs) && count($__crumbs) > 0): ?>
			<div class="bg-body-tertiary border-top">
				<div class="container-fluid py-1 px-3">
					<nav aria-label="breadcrumb">
						<ol class="breadcrumb mb-0">
							<?php foreach ($__crumbs as $crumb): ?>
								<?php if (!empty($crumb['active'])): ?>
									<li class="breadcrumb-item active" aria-current="page"><?= htmlspecialchars($crumb['label']); ?></li>
								<?php else: ?>
									<li class="breadcrumb-item">
										<a href="<?= htmlspecialchars($crumb['url'] ?? '#'); ?>">
											<?php if (!empty($crumb['icon'])): ?><i class="<?= htmlspecialchars($crumb['icon']); ?> me-1"></i><?php endif; ?>
											<?= htmlspecialchars($crumb['label']); ?>
										</a>
									</li>
								<?php endif; ?>
							<?php endforeach; ?>
						</ol>
					</nav>
				</div>
			</div>
		<?php endif; ?>
	</header>
