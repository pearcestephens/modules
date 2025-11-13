<?php
/**
 * EMPLOYEE DASHBOARD
 *
 * View all employees with sync status across all systems
 */

require_once __DIR__ . '/../shared/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

if (!isset($pdo) || !$pdo) {
    if (function_exists('cis_resolve_pdo')) {
        $pdo = cis_resolve_pdo();
    } elseif (isset($GLOBALS['pdo'])) {
        $pdo = $GLOBALS['pdo'];
    }
}

// Query employees directly from database with performance optimization
try {
    $stmt = $pdo->prepare("
        SELECT 
            id, 
            name, 
            email, 
            role, 
            status 
        FROM employees 
        ORDER BY name ASC 
        LIMIT 1000
    ");
    $stmt->execute();
    $employees = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    error_log("HR Dashboard - Employee query failed: " . $e->getMessage());
    $employees = [];
}

$pageTitle = 'Employee Management';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - CIS</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
<style>
    .content-container {
        background: #f8f9fa;
        padding: 40px 0;
    }

    .content-container .table {
        background: white;
        border-radius: 10px;
        box-shadow: 0 10px 20px rgba(0,0,0,0.1);
    }

    .content-container .table th {
        background: #007bff;
        color: white;
    }
    
    /* Performance: prevent layout shift */
    .table { table-layout: fixed; }
    .table td { word-break: break-word; }
</style>
</head>
<body>
<div class="container mt-4">
    <h1 class="text-center mb-4">Employee Management</h1>
    
    <?php if (empty($employees)): ?>
        <div class="alert alert-warning" role="alert">
            <i class="fas fa-info-circle"></i> No employees found. Check database connection.
        </div>
    <?php else: ?>
        <div class="alert alert-info">
            Showing <strong><?php echo count($employees); ?></strong> employees
        </div>
        
        <table class="table table-striped table-hover">
            <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($employees as $employee): ?>
                <tr>
                    <td><?php echo htmlspecialchars($employee['name'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($employee['email'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($employee['role'] ?? 'N/A'); ?></td>
                    <td>
                        <span class="badge bg-<?php 
                            echo ($employee['status'] ?? '') === 'active' ? 'success' : 'secondary'; 
                        ?>">
                            <?php echo htmlspecialchars($employee['status'] ?? 'unknown'); ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
    // Log page load time for performance monitoring
    window.addEventListener('load', function() {
        console.log('Employee Dashboard loaded in ' + performance.now() + 'ms');
    });
</script>
</body>
</html>
