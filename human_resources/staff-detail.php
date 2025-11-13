<?php
/**
 * STAFF DETAIL PAGE
 *
 * Displays detailed information about a staff member.
 */

require_once __DIR__ . '/../shared/bootstrap.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$staffId = $_GET['id'] ?? null;
if (!$staffId) {
    http_response_code(400);
    echo 'Invalid staff ID';
    exit;
}

// Fetch staff details
try {
    $stmt = $pdo->prepare("SELECT * FROM staff WHERE id = ?");
    $stmt->execute([$staffId]);
    $staff = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$staff) {
        http_response_code(404);
        echo 'Staff not found';
        exit;
    }
} catch (Throwable $e) {
    error_log("Failed to fetch staff details: " . $e->getMessage());
    http_response_code(500);
    echo 'Internal server error';
    exit;
}

$pageTitle = 'Staff Detail - ' . htmlspecialchars($staff['name']);
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

        .detail-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
<div class="container">
    <div class="detail-container">
        <h1 class="text-center mb-4">Staff Detail</h1>
        <p><strong>Name:</strong> <?php echo htmlspecialchars($staff['name']); ?></p>
        <p><strong>Email:</strong> <?php echo htmlspecialchars($staff['email']); ?></p>
        <p><strong>Role:</strong> <?php echo htmlspecialchars($staff['role']); ?></p>
        <p><strong>Status:</strong> <?php echo htmlspecialchars($staff['status']); ?></p>
    </div>
</div>
</body>
</html>
