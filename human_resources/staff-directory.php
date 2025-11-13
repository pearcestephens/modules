<?php
/**
 * STAFF DIRECTORY
 *
 * Displays a list of all staff members.
 */

require_once __DIR__ . '/../shared/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// Fetch staff list
try {
    $stmt = $pdo->query("SELECT id, name, email, role, status FROM staff ORDER BY name");
    $staffList = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    error_log("Failed to fetch staff list: " . $e->getMessage());
    http_response_code(500);
    echo 'Internal server error';
    exit;
}

$pageTitle = 'Staff Directory';
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

        .directory-container {
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
    <div class="directory-container">
        <h1 class="text-center mb-4">Staff Directory</h1>
        <table class="table table-striped">
            <thead>
            <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Role</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($staffList as $staff): ?>
                <tr>
                    <td><?php echo htmlspecialchars($staff['name']); ?></td>
                    <td><?php echo htmlspecialchars($staff['email']); ?></td>
                    <td><?php echo htmlspecialchars($staff['role']); ?></td>
                    <td><?php echo htmlspecialchars($staff['status']); ?></td>
                    <td>
                        <a href="staff-detail.php?id=<?php echo $staff['id']; ?>" class="btn btn-sm btn-primary">View</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
</body>
</html>
