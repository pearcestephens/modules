<?php
/**
 * Payroll Module - Header Layout
 *
 * Standard header for all payroll views
 * Includes Bootstrap 5, CSS, authentication checks
 *
 * @package HumanResources\Payroll\Views\Layouts
 */

// Security check - must be accessed through proper routing
if (!defined('PAYROLL_MODULE') && !isset($_SERVER['DOCUMENT_ROOT'])) {
    http_response_code(403);
    die('Direct access not permitted');
}

// Set page title default if not set
$pageTitle = $pageTitle ?? 'Payroll System';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?php echo htmlspecialchars($pageTitle); ?> - CIS Payroll</title>

    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.10.0/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Payroll Module CSS -->
    <link href="/modules/human_resources/payroll/assets/css/main.css" rel="stylesheet">

    <!-- Page-specific styles injected by views -->
    <?php if (isset($additionalStyles)): ?>
        <?php echo $additionalStyles; ?>
    <?php endif; ?>

    <style>
        /* Global Layout Styles */
        :root {
            --primary-color: #667eea;
            --primary-dark: #764ba2;
            --secondary-color: #6c757d;
            --success-color: #28a745;
            --danger-color: #dc3545;
            --warning-color: #ffc107;
            --info-color: #17a2b8;
            --light-color: #f8f9fa;
            --dark-color: #343a40;
            --border-color: #e2e8f0;
            --shadow-sm: 0 1px 3px rgba(0,0,0,0.1);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: #f8f9fa;
            color: #2d3748;
            line-height: 1.6;
        }

        .main-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 1rem;
        }

        /* Navigation */
        .payroll-nav {
            background: white;
            border-bottom: 1px solid var(--border-color);
            padding: 1rem 0;
            margin-bottom: 2rem;
            box-shadow: var(--shadow-sm);
        }

        .payroll-nav .nav-brand {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--primary-color);
            text-decoration: none;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .payroll-nav .nav-links {
            display: flex;
            gap: 1.5rem;
            list-style: none;
            margin: 0;
            padding: 0;
            align-items: center;
        }

        .payroll-nav .nav-link {
            color: #4a5568;
            text-decoration: none;
            padding: 0.5rem 1rem;
            border-radius: 0.375rem;
            transition: all 0.2s;
            font-weight: 500;
        }

        .payroll-nav .nav-link:hover {
            background: #f7fafc;
            color: var(--primary-color);
        }

        .payroll-nav .nav-link.active {
            background: var(--primary-color);
            color: white;
        }

        /* Content Area */
        .content-wrapper {
            background: white;
            border-radius: 0.5rem;
            box-shadow: var(--shadow-sm);
            padding: 1.5rem;
            min-height: 600px;
        }

        /* Utility Classes */
        .text-muted {
            color: #718096 !important;
        }

        .badge {
            font-weight: 600;
            padding: 0.375rem 0.75rem;
        }

        /* Loading Spinner */
        .spinner-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            display: none;
            align-items: center;
            justify-content: center;
            z-index: 9999;
        }

        .spinner-overlay.active {
            display: flex;
        }

        .spinner-border {
            width: 3rem;
            height: 3rem;
            border-width: 0.3rem;
        }

        /* Toast Notifications */
        .toast-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9998;
        }

        .toast {
            min-width: 300px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .main-container {
                padding: 0.5rem;
            }

            .payroll-nav .nav-links {
                flex-direction: column;
                gap: 0.5rem;
            }

            .content-wrapper {
                padding: 1rem;
            }
        }
    </style>
</head>
<body>
    <!-- Loading Spinner -->
    <div class="spinner-overlay" id="loadingSpinner">
        <div class="spinner-border text-light" role="status">
            <span class="visually-hidden">Loading...</span>
        </div>
    </div>

    <!-- Navigation -->
    <nav class="payroll-nav">
        <div class="main-container">
            <div class="d-flex justify-content-between align-items-center">
                <a href="/modules/human_resources/payroll/" class="nav-brand">
                    <i class="bi bi-cash-stack"></i>
                    CIS Payroll
                </a>

                <ul class="nav-links">
                    <li>
                        <a href="/modules/human_resources/payroll/?view=dashboard"
                           class="nav-link <?php echo (isset($_GET['view']) && $_GET['view'] === 'dashboard') || !isset($_GET['view']) ? 'active' : ''; ?>">
                            <i class="bi bi-speedometer2"></i> Dashboard
                        </a>
                    </li>
                    <li>
                        <a href="/modules/human_resources/payroll/?view=reconciliation"
                           class="nav-link <?php echo isset($_GET['view']) && $_GET['view'] === 'reconciliation' ? 'active' : ''; ?>">
                            <i class="bi bi-arrows-collapse"></i> Reconciliation
                        </a>
                    </li>
                    <li>
                        <a href="/modules/human_resources/payroll/?view=payruns"
                           class="nav-link <?php echo isset($_GET['view']) && $_GET['view'] === 'payruns' ? 'active' : ''; ?>">
                            <i class="bi bi-calendar-check"></i> Pay Runs
                        </a>
                    </li>
                    <li>
                        <a href="/modules/human_resources/payroll/?view=reports"
                           class="nav-link <?php echo isset($_GET['view']) && $_GET['view'] === 'reports' ? 'active' : ''; ?>">
                            <i class="bi bi-bar-chart"></i> Reports
                        </a>
                    </li>
                    <li>
                        <a href="/modules/human_resources/payroll/?view=settings"
                           class="nav-link <?php echo isset($_GET['view']) && $_GET['view'] === 'settings' ? 'active' : ''; ?>">
                            <i class="bi bi-gear"></i> Settings
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="main-container">
        <div class="content-wrapper">
