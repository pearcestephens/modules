<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - CIS</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f5f5f5;
        }
        .navbar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .navbar-brand {
            font-weight: 700;
            color: white !important;
        }
        .navbar .nav-link {
            color: rgba(255,255,255,0.9) !important;
        }
        .navbar .nav-link:hover {
            color: white !important;
        }
        .sidebar {
            background: white;
            min-height: calc(100vh - 56px);
            box-shadow: 2px 0 10px rgba(0,0,0,0.05);
        }
        .sidebar .nav-link {
            color: #333;
            padding: 12px 20px;
            border-left: 3px solid transparent;
            transition: all 0.3s;
        }
        .sidebar .nav-link:hover,
        .sidebar .nav-link.active {
            background-color: #f8f9fa;
            border-left-color: #667eea;
            color: #667eea;
        }
        .stat-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.05);
            transition: transform 0.2s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
        .stat-icon {
            width: 50px;
            height: 50px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 24px;
        }
        .stat-icon.primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .stat-icon.success {
            background: linear-gradient(135deg, #84fab0 0%, #8fd3f4 100%);
            color: white;
        }
        .stat-icon.warning {
            background: linear-gradient(135deg, #fa709a 0%, #fee140 100%);
            color: white;
        }
        .stat-icon.info {
            background: linear-gradient(135deg, #30cfd0 0%, #330867 100%);
            color: white;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="/modules/core/index.php">
                <i class="bi bi-grid-3x3-gap-fill"></i> CIS Dashboard
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($user['username'] ?? 'User'); ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="/modules/core/views/profile.php"><i class="bi bi-person"></i> Profile</a></li>
                            <li><a class="dropdown-item" href="/modules/core/views/settings.php"><i class="bi bi-gear"></i> Settings</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/modules/core/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                        </ul>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <div class="col-md-2 px-0 sidebar">
                <nav class="nav flex-column py-3">
                    <a class="nav-link active" href="/modules/core/index.php">
                        <i class="bi bi-house-door"></i> Dashboard
                    </a>
                    <a class="nav-link" href="/modules/core/views/profile.php">
                        <i class="bi bi-person"></i> Profile
                    </a>
                    <a class="nav-link" href="/modules/core/views/settings.php">
                        <i class="bi bi-gear"></i> Settings
                    </a>
                    <a class="nav-link" href="/modules/core/views/preferences.php">
                        <i class="bi bi-sliders"></i> Preferences
                    </a>
                    <a class="nav-link" href="/modules/core/views/security.php">
                        <i class="bi bi-shield-lock"></i> Security
                    </a>
                </nav>
            </div>

            <!-- Main Content -->
            <div class="col-md-10 px-4 py-4">
                <?php if (isset($flash) && $flash): ?>
                    <div class="alert alert-<?php echo htmlspecialchars($flash['type'] ?? 'info'); ?> alert-dismissible fade show" role="alert">
                        <?php echo htmlspecialchars($flash['message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <h1 class="mb-4">Welcome back, <?php echo htmlspecialchars($user['first_name'] ?? $user['username']); ?>!</h1>

                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon primary me-3">
                                    <i class="bi bi-people"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-0">Total Users</h6>
                                    <h3 class="mb-0"><?php echo number_format($stats['total_users'] ?? 0); ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon success me-3">
                                    <i class="bi bi-check-circle"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-0">Active Sessions</h6>
                                    <h3 class="mb-0"><?php echo number_format($stats['active_sessions'] ?? 0); ?></h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon warning me-3">
                                    <i class="bi bi-clock-history"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-0">Account Age</h6>
                                    <h3 class="mb-0"><?php echo $stats['account_age_days'] ?? 0; ?> days</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="stat-card">
                            <div class="d-flex align-items-center">
                                <div class="stat-icon info me-3">
                                    <i class="bi bi-graph-up"></i>
                                </div>
                                <div>
                                    <h6 class="text-muted mb-0">Profile Complete</h6>
                                    <h3 class="mb-0"><?php echo $stats['profile_completion'] ?? 0; ?>%</h3>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="row mb-4">
                    <div class="col-12">
                        <div class="stat-card">
                            <h5 class="mb-3"><i class="bi bi-lightning-charge"></i> Quick Actions</h5>
                            <div class="d-flex gap-2 flex-wrap">
                                <?php if (isset($quick_actions) && is_array($quick_actions)): ?>
                                    <?php foreach ($quick_actions as $action): ?>
                                        <a href="<?php echo htmlspecialchars($action['url']); ?>" class="btn btn-outline-primary">
                                            <i class="bi bi-<?php echo htmlspecialchars($action['icon']); ?>"></i>
                                            <?php echo htmlspecialchars($action['label']); ?>
                                        </a>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <a href="/modules/core/views/profile.php" class="btn btn-outline-primary">
                                        <i class="bi bi-person"></i> Edit Profile
                                    </a>
                                    <a href="/modules/core/views/settings.php" class="btn btn-outline-primary">
                                        <i class="bi bi-gear"></i> Settings
                                    </a>
                                    <a href="/modules/core/views/security.php" class="btn btn-outline-primary">
                                        <i class="bi bi-shield-lock"></i> Security
                                    </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Recent Activity -->
                <div class="row">
                    <div class="col-12">
                        <div class="stat-card">
                            <h5 class="mb-3"><i class="bi bi-clock-history"></i> Recent Activity</h5>
                            <?php if (isset($recent_activity) && count($recent_activity) > 0): ?>
                                <div class="list-group list-group-flush">
                                    <?php foreach ($recent_activity as $activity): ?>
                                        <div class="list-group-item px-0">
                                            <div class="d-flex align-items-center">
                                                <div class="me-3">
                                                    <i class="bi bi-<?php echo htmlspecialchars($activity['icon'] ?? 'circle'); ?>"></i>
                                                </div>
                                                <div class="flex-grow-1">
                                                    <h6 class="mb-0"><?php echo htmlspecialchars($activity['title']); ?></h6>
                                                    <small class="text-muted"><?php echo htmlspecialchars($activity['description'] ?? ''); ?></small>
                                                </div>
                                                <div class="text-muted small">
                                                    <?php echo htmlspecialchars($activity['time']); ?>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No recent activity.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
