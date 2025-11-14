<?php
/**
 * EMPLOYEE SIGNUP FORM
 *
 * Collects all required information for CIS, Xero, Deputy, and Lightspeed.
 */

require_once __DIR__ . '/../shared/bootstrap.php';

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// CSRF token for signup form
if (!isset($_SESSION['csrf_signup'])) {
    $_SESSION['csrf_signup'] = bin2hex(random_bytes(24));
}

$pageTitle = 'Employee Signup';
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

        .signup-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .form-section {
            margin-bottom: 30px;
        }

        .form-section h3 {
            margin-bottom: 20px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="signup-container">
            <h1 class="text-center mb-4"><i class="fas fa-user-plus"></i> Employee Signup</h1>

            <form id="signupForm" action="api/onboard.php" method="POST">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_signup']); ?>">

                <!-- Personal Information -->
                <div class="form-section">
                    <h3>Personal Information</h3>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">First Name *</label>
                            <input type="text" class="form-control" name="first_name" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Last Name *</label>
                            <input type="text" class="form-control" name="last_name" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Email *</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Mobile Phone</label>
                            <input type="tel" class="form-control" name="mobile">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">IRD Number *</label>
                            <input type="text" class="form-control" name="ird_number" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Tax Code *</label>
                            <select class="form-select" name="tax_code" required>
                                <option value="M">M (Main Income)</option>
                                <option value="ME">ME (Main Income + Tax Credit)</option>
                                <option value="S">S (Secondary Income)</option>
                                <option value="SH">SH (Secondary High Income)</option>
                                <option value="ST">ST (Student Loan)</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label class="form-label">Residential Address *</label>
                            <textarea class="form-control" name="address" rows="2" required></textarea>
                        </div>
                    </div>
                </div>

                <!-- Employment Details -->
                <div class="form-section">
                    <h3>Employment Details</h3>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Job Title *</label>
                            <input type="text" class="form-control" name="job_title" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Department</label>
                            <input type="text" class="form-control" name="department" value="Retail">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Start Date *</label>
                            <input type="date" class="form-control" name="start_date" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Employment Type *</label>
                            <select class="form-select" name="employment_type" required>
                                <option value="full_time">Full Time</option>
                                <option value="part_time">Part Time</option>
                                <option value="casual">Casual</option>
                                <option value="contractor">Contractor</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- System Access -->
                <div class="form-section">
                    <h3>System Access</h3>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="sync_xero" id="syncXero" checked>
                        <label class="form-check-label" for="syncXero">
                            <strong>Xero Payroll</strong> - Create employee in Xero Payroll for pay runs and leave management.
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="sync_deputy" id="syncDeputy" checked>
                        <label class="form-check-label" for="syncDeputy">
                            <strong>Deputy</strong> - Create employee in Deputy for timesheet tracking and rostering.
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="sync_lightspeed" id="syncLightspeed" checked>
                        <label class="form-check-label" for="syncLightspeed">
                            <strong>Lightspeed POS</strong> - Create user account in Lightspeed/Vend for POS access.
                        </label>
                    </div>
                </div>

                <div class="text-center">
                    <button type="submit" class="btn btn-primary btn-lg">
                        <i class="fas fa-paper-plane"></i> Submit
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
