<?php
/**
 * STAFF PAYROLL
 *
 * Displays payroll information for staff members.
 */

require_once __DIR__ . '/../shared/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// Fetch payroll data
try {
    $stmt = $pdo->query("SELECT staff.name, payroll.salary, payroll.bonus, payroll.deductions FROM payroll JOIN staff ON payroll.staff_id = staff.id ORDER BY staff.name");
    $payrollData = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log("Failed to fetch payroll data: " . $e->getMessage());
    http_response_code(500);
    echo 'Internal server error';
    exit;
}

$pageTitle = 'Staff Payroll';
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

        .payroll-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .table {
            margin-top: 20px;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="payroll-container">
        <h1 class="text-center mb-4">Staff Payroll</h1>
        <table class="table table-striped">
            <thead>
            <tr>
                <th>Name</th>
                <th>Salary</th>
                <th>Bonus</th>
                <th>Deductions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($payrollData as $payroll): ?>
                <tr>
                    <td><?php echo htmlspecialchars($payroll['name']); ?></td>
                    <td><?php echo htmlspecialchars($payroll['salary']); ?></td>
                    <td><?php echo htmlspecialchars($payroll['bonus']); ?></td>
                    <td><?php echo htmlspecialchars($payroll['deductions']); ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
