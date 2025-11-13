<?php
/**
 * EMPLOYEE DASHBOARD
 *
 * View all employees with sync status across all systems
 */

require_once __DIR__ . '/../shared/bootstrap.php';
require_once __DIR__ . '/services/UniversalOnboardingService.php';

use CIS\EmployeeOnboarding\UniversalOnboardingService;

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

$onboarding = new UniversalOnboardingService($pdo);
$employees = $onboarding->getAllEmployees();

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
        body {
            background: #f8f9fa;
            padding: 40px 0;
        }

        .table {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }

        .table th {
            background: #007bff;
            color: white;
        }
    </style>
</head>
<body>
<div class="container">
    <h1 class="text-center mb-4">Employee Management</h1>
    <table class="table table-striped">
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
                <td><?php echo htmlspecialchars($employee['name']); ?></td>
                <td><?php echo htmlspecialchars($employee['email']); ?></td>
                <td><?php echo htmlspecialchars($employee['role']); ?></td>
                <td><?php echo htmlspecialchars($employee['status']); ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
</body>
</html>
