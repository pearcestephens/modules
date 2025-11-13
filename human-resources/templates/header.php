<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle ?? 'HR Module'; ?> - CIS</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">

    <style>
        body {
            background: #f8f9fa;
            padding: 20px;
        }

        .navbar {
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-light bg-light">
    <div class="container-fluid">
        <a class="navbar-brand" href="/modules/human-resources/dashboard.php">HR Module</a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse" id="navbarNav">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link" href="/modules/human-resources/staff-directory.php">Staff Directory</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/modules/human-resources/staff-payroll.php">Payroll</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/modules/human-resources/staff-timesheets.php">Timesheets</a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="/modules/human-resources/integrations.php">Integrations</a>
                </li>
            </ul>
        </div>
    </div>
</nav>
