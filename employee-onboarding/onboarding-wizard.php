                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                                            <?php include '../partials/header.php'; ?>
<div class="container mt-5">
    <div class="row">
        <div class="col-md-12 text-center">
            <img src="/assets/images/company-logo.png" alt="Company Logo" class="img-fluid mb-4" style="max-width: 200px;">
            <h1>Welcome to the Onboarding Wizard</h1>
            <p class="lead">We’re excited to have you join our team! Please complete the steps below to get started.</p>
        </div>
    </div>
    <div class="row">
        <div class="col-md-8 offset-md-2">
            <div class="card">
                <div class="card-header bg-primary text-white">
                    <h4>Onboarding Steps</h4>
                </div>
                <div class="card-body">
                    <ol>
                        <li>Fill out your personal details.</li>
                        <li>Provide your tax and banking information.</li>
                        <li>Review and accept company policies.</li>
                        <li>Complete your profile setup.</li>
                    </ol>
                    <p>If you have any questions, please contact our HR team at <a href="mailto:hr@company.com">hr@company.com</a>.</p>
                </div>
            </div>
        </div>
    </div>
</div>
<?php include '../partials/footer.php'; ?>
<?php
/**
 * UNIVERSAL EMPLOYEE ONBOARDING WIZARD
 *
 * Create new employee ONCE → Provisions EVERYWHERE
 * - CIS (Master Database)
 * - Xero Payroll
 * - Deputy (Timesheets)
 * - Lightspeed POS
 */

require_once __DIR__ . '/../shared/bootstrap.php';
require_once __DIR__ . '/services/UniversalOnboardingService.php';

use CIS\EmployeeOnboarding\UniversalOnboardingService;

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// CSRF token for onboarding form
if (!isset($_SESSION['csrf_onboard'])) {
    $_SESSION['csrf_onboard'] = bin2hex(random_bytes(24));
}

// Ensure PDO instance is available from shared bootstrap
if (!isset($pdo) || !$pdo) {
    if (function_exists('cis_resolve_pdo')) {
        $pdo = cis_resolve_pdo();
    } elseif (isset($GLOBALS['pdo'])) {
        $pdo = $GLOBALS['pdo'];
    }
}

$onboarding = new UniversalOnboardingService($pdo);

// Check permission
if (!$onboarding->checkPermission($_SESSION['user_id'], 'system.manage_users')) {
    die('Unauthorized: You do not have permission to onboard employees');
}

// Get roles for dropdown
$stmt = $pdo->query("SELECT id, display_name, description, level FROM roles ORDER BY level DESC, display_name");
$roles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get locations for dropdown
$stmt = $pdo->query("SELECT id, name FROM locations WHERE active = 1 ORDER BY name");
$locations = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get managers for dropdown
$stmt = $pdo->query("SELECT id, CONCAT(first_name, ' ', last_name) as name FROM users WHERE status = 'active' ORDER BY name");
$managers = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Employee Onboarding Wizard';
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
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 0;
        }

        .wizard-container {
            max-width: 1000px;
            margin: 0 auto;
        }

        .wizard-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }

        .wizard-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }

        .wizard-header h1 {
            margin: 0;
            font-size: 2rem;
            font-weight: 700;
        }

        .wizard-header p {
            margin: 10px 0 0;
            opacity: 0.9;
        }

        .wizard-body {
            padding: 40px;
        }

        .wizard-steps {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            position: relative;
        }

        .wizard-steps::before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 3px;
            background: #e9ecef;
            z-index: 0;
        }

        .wizard-step {
            flex: 1;
            text-align: center;
            position: relative;
            z-index: 1;
        }

        .step-circle {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background: #e9ecef;
            color: #6c757d;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-weight: bold;
            transition: all 0.3s;
        }

        .wizard-step.active .step-circle {
            background: #667eea;
            color: white;
            transform: scale(1.2);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
        }

        .wizard-step.completed .step-circle {
            background: #28a745;
            color: white;
        }

        .step-label {
            font-size: 0.9rem;
            color: #6c757d;
            font-weight: 500;
        }

        .wizard-step.active .step-label {
            color: #667eea;
            font-weight: 600;
        }

        .wizard-content {
            display: none;
        }

        .wizard-content.active {
            display: block;
            animation: fadeIn 0.3s;
        }

        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }

        .wizard-actions {
            display: flex;
            justify-content: space-between;
            margin-top: 40px;
            padding-top: 20px;
            border-top: 2px solid #e9ecef;
        }

        .btn-wizard {
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-wizard:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        }

        .system-toggle {
            background: #f8f9fa;
            border: 2px solid #dee2e6;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 15px;
            cursor: pointer;
            transition: all 0.3s;
        }

        .system-toggle:hover {
            border-color: #667eea;
            background: #f0f4ff;
        }

        .system-toggle.enabled {
            border-color: #28a745;
            background: #d4edda;
        }

        .system-icon {
            font-size: 2rem;
            margin-bottom: 10px;
        }

        .summary-card {
            background: #f8f9fa;
            border-left: 4px solid #667eea;
            padding: 20px;
            margin-bottom: 20px;
            border-radius: 5px;
        }

        .result-badge {
            display: inline-block;
            padding: 5px 15px;
            border-radius: 20px;
            font-weight: 600;
            margin-right: 10px;
        }

        .result-success {
            background: #d4edda;
            color: #155724;
        }

        .result-warning {
            background: #fff3cd;
            color: #856404;
        }

        .result-error {
            background: #f8d7da;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="wizard-container">
        <div class="wizard-card">
            <div class="wizard-header">
                <h1><i class="fas fa-user-plus"></i> Universal Employee Onboarding</h1>
                <p>Create employee once → Provisions everywhere (CIS, Xero, Deputy, Lightspeed)</p>
            </div>

            <div class="wizard-body">
                <!-- Wizard Steps -->
                <div class="wizard-steps">
                    <div class="wizard-step active" data-step="1">
                        <div class="step-circle">1</div>
                        <div class="step-label">Personal Info</div>
                    </div>
                    <div class="wizard-step" data-step="2">
                        <div class="step-circle">2</div>
                        <div class="step-label">Employment</div>
                    </div>
                    <div class="wizard-step" data-step="3">
                        <div class="step-circle">3</div>
                        <div class="step-label">Systems</div>
                    </div>
                    <div class="wizard-step" data-step="4">
                        <div class="step-circle">4</div>
                        <div class="step-label">Review</div>
                    </div>
                    <div class="wizard-step" data-step="5">
                        <div class="step-circle"><i class="fas fa-check"></i></div>
                        <div class="step-label">Complete</div>
                    </div>
                </div>

                <form id="onboardingForm">
                    <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars($_SESSION['csrf_onboard']); ?>">
                    <!-- Step 1: Personal Information -->
                    <div class="wizard-content active" data-step="1">
                        <h3 class="mb-4"><i class="fas fa-user"></i> Personal Information</h3>

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
                                <small class="text-muted">Must be unique across all systems</small>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Mobile Phone</label>
                                <input type="tel" class="form-control" name="mobile">
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date of Birth</label>
                                <input type="date" class="form-control" name="date_of_birth">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Home Phone</label>
                                <input type="tel" class="form-control" name="phone">
                            </div>
                        </div>
                    </div>

                    <!-- Step 2: Employment Details -->
                    <div class="wizard-content" data-step="2">
                        <h3 class="mb-4"><i class="fas fa-briefcase"></i> Employment Details</h3>

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

                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Primary Location *</label>
                                <select class="form-select" name="location_id" required>
                                    <option value="">Select location...</option>
                                    <?php foreach ($locations as $location): ?>
                                        <option value="<?php echo $location['id']; ?>"><?php echo htmlspecialchars($location['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Reports To</label>
                                <select class="form-select" name="manager_id">
                                    <option value="">Select manager...</option>
                                    <?php foreach ($managers as $manager): ?>
                                        <option value="<?php echo $manager['id']; ?>"><?php echo htmlspecialchars($manager['name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Roles *</label>
                            <div class="row">
                                <?php foreach ($roles as $role): ?>
                                    <div class="col-md-6 mb-2">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" name="roles[]" value="<?php echo $role['id']; ?>" id="role_<?php echo $role['id']; ?>">
                                            <label class="form-check-label" for="role_<?php echo $role['id']; ?>">
                                                <strong><?php echo htmlspecialchars($role['display_name']); ?></strong>
                                                <br><small class="text-muted"><?php echo htmlspecialchars($role['description']); ?></small>
                                            </label>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="3" placeholder="Any additional information..."></textarea>
                        </div>
                    </div>

                    <!-- Step 3: System Access -->
                    <div class="wizard-content" data-step="3">
                        <h3 class="mb-4"><i class="fas fa-network-wired"></i> System Provisioning</h3>
                        <p class="text-muted">Select which external systems to provision this employee in:</p>

                        <div class="system-toggle enabled" data-system="xero">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0 system-icon" style="color: #13B5EA;">
                                    <i class="fas fa-dollar-sign"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h5 class="mb-1">
                                        <i class="fas fa-check-circle text-success"></i> Xero Payroll
                                        <span class="badge bg-success">Recommended</span>
                                    </h5>
                                    <p class="mb-0 text-muted">Creates employee in Xero PayrollNZ for pay runs and leave management</p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="sync_xero" checked>
                                </div>
                            </div>
                        </div>

                        <div class="system-toggle enabled" data-system="deputy">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0 system-icon" style="color: #007AFF;">
                                    <i class="fas fa-clock"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h5 class="mb-1">
                                        <i class="fas fa-check-circle text-success"></i> Deputy
                                        <span class="badge bg-success">Recommended</span>
                                    </h5>
                                    <p class="mb-0 text-muted">Creates employee in Deputy for timesheet tracking and rostering</p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="sync_deputy" checked>
                                </div>
                            </div>
                        </div>

                        <div class="system-toggle enabled" data-system="lightspeed">
                            <div class="d-flex align-items-center">
                                <div class="flex-shrink-0 system-icon" style="color: #00A651;">
                                    <i class="fas fa-cash-register"></i>
                                </div>
                                <div class="flex-grow-1 ms-3">
                                    <h5 class="mb-1">
                                        <i class="fas fa-check-circle text-success"></i> Lightspeed POS
                                        <span class="badge bg-success">Recommended</span>
                                    </h5>
                                    <p class="mb-0 text-muted">Creates user account in Lightspeed/Vend for POS access</p>
                                </div>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="sync_lightspeed" checked>
                                </div>
                            </div>
                        </div>

                        <div class="alert alert-info mt-4">
                            <i class="fas fa-info-circle"></i> <strong>Note:</strong> Failed syncs will be automatically retried. You can manually re-sync later from the employee dashboard.
                        </div>
                    </div>

                    <!-- Step 4: Review -->
                    <div class="wizard-content" data-step="4">
                        <h3 class="mb-4"><i class="fas fa-clipboard-check"></i> Review & Confirm</h3>

                        <div class="summary-card">
                            <h5>Personal Information</h5>
                            <div id="reviewPersonal"></div>
                        </div>

                        <div class="summary-card">
                            <h5>Employment Details</h5>
                            <div id="reviewEmployment"></div>
                        </div>

                        <div class="summary-card">
                            <h5>System Provisioning</h5>
                            <div id="reviewSystems"></div>
                        </div>

                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> <strong>Ready to create employee?</strong> This will create the employee in CIS and provision them to selected external systems.
                        </div>
                    </div>

                    <!-- Step 5: Complete -->
                    <div class="wizard-content" data-step="5">
                        <div id="resultContainer"></div>
                    </div>
                </form>

                <!-- Wizard Actions -->
                <div class="wizard-actions">
                    <button type="button" class="btn btn-secondary btn-wizard" id="btnPrevious">
                        <i class="fas fa-arrow-left"></i> Previous
                    </button>
                    <button type="button" class="btn btn-primary btn-wizard" id="btnNext">
                        Next <i class="fas fa-arrow-right"></i>
                    </button>
                    <button type="button" class="btn btn-success btn-wizard" id="btnSubmit" style="display:none;">
                        <i class="fas fa-rocket"></i> Create Employee
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentStep = 1;
        const totalSteps = 5;

        document.getElementById('btnNext').addEventListener('click', () => {
            if (validateStep(currentStep)) {
                if (currentStep === 4) {
                    // Show submit button on review step
                    document.getElementById('btnNext').style.display = 'none';
                    document.getElementById('btnSubmit').style.display = 'block';
                }
                goToStep(currentStep + 1);
            }
        });

        document.getElementById('btnPrevious').addEventListener('click', () => {
            document.getElementById('btnNext').style.display = 'block';
            document.getElementById('btnSubmit').style.display = 'none';
            goToStep(currentStep - 1);
        });

        document.getElementById('btnSubmit').addEventListener('click', submitForm);

        // System toggle handlers
        document.querySelectorAll('.system-toggle').forEach(toggle => {
            toggle.addEventListener('click', function() {
                const checkbox = this.querySelector('input[type="checkbox"]');
                checkbox.checked = !checkbox.checked;
                this.classList.toggle('enabled', checkbox.checked);
            });
        });

        function goToStep(step) {
            if (step < 1 || step > totalSteps) return;

            // Update content
            document.querySelectorAll('.wizard-content').forEach(content => {
                content.classList.remove('active');
            });
            document.querySelector(`.wizard-content[data-step="${step}"]`).classList.add('active');

            // Update step indicators
            document.querySelectorAll('.wizard-step').forEach((stepElem, index) => {
                stepElem.classList.remove('active');
                if (index < step - 1) {
                    stepElem.classList.add('completed');
                } else {
                    stepElem.classList.remove('completed');
                }
            });
            document.querySelector(`.wizard-step[data-step="${step}"]`).classList.add('active');

            // Update buttons
            document.getElementById('btnPrevious').style.display = step === 1 ? 'none' : 'block';

            // Load review data if on step 4
            if (step === 4) {
                loadReview();
            }

            currentStep = step;
        }

        function validateStep(step) {
            const content = document.querySelector(`.wizard-content[data-step="${step}"]`);
            const requiredFields = content.querySelectorAll('[required]');

            for (let field of requiredFields) {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    field.focus();
                    return false;
                }
                field.classList.remove('is-invalid');
            }

            // Validate roles selected on step 2
            if (step === 2) {
                const roleCheckboxes = document.querySelectorAll('input[name="roles[]"]:checked');
                if (roleCheckboxes.length === 0) {
                    alert('Please select at least one role');
                    return false;
                }
            }

            return true;
        }

        function loadReview() {
            const formData = new FormData(document.getElementById('onboardingForm'));

            // Personal Info
            document.getElementById('reviewPersonal').innerHTML = `
                <p><strong>Name:</strong> ${formData.get('first_name')} ${formData.get('last_name')}</p>
                <p><strong>Email:</strong> ${formData.get('email')}</p>
                <p><strong>Mobile:</strong> ${formData.get('mobile') || 'N/A'}</p>
                <p><strong>Date of Birth:</strong> ${formData.get('date_of_birth') || 'N/A'}</p>
            `;

            // Employment
            const locationName = document.querySelector(`select[name="location_id"] option:checked`).textContent;
            const roles = Array.from(document.querySelectorAll('input[name="roles[]"]:checked'))
                .map(cb => document.querySelector(`label[for="${cb.id}"] strong`).textContent)
                .join(', ');

            document.getElementById('reviewEmployment').innerHTML = `
                <p><strong>Job Title:</strong> ${formData.get('job_title')}</p>
                <p><strong>Department:</strong> ${formData.get('department') || 'N/A'}</p>
                <p><strong>Employment Type:</strong> ${formData.get('employment_type')}</p>
                <p><strong>Start Date:</strong> ${formData.get('start_date')}</p>
                <p><strong>Location:</strong> ${locationName}</p>
                <p><strong>Roles:</strong> ${roles}</p>
            `;

            // Systems
            const systems = [];
            if (formData.get('sync_xero')) systems.push('<span class="result-badge result-success"><i class="fas fa-check"></i> Xero Payroll</span>');
            if (formData.get('sync_deputy')) systems.push('<span class="result-badge result-success"><i class="fas fa-check"></i> Deputy</span>');
            if (formData.get('sync_lightspeed')) systems.push('<span class="result-badge result-success"><i class="fas fa-check"></i> Lightspeed POS</span>');

            document.getElementById('reviewSystems').innerHTML = systems.length ? systems.join(' ') : '<p class="text-muted">No external systems selected</p>';
        }

        async function submitForm() {
            const formData = new FormData(document.getElementById('onboardingForm'));
            const submitBtn = document.getElementById('btnSubmit');

            submitBtn.disabled = true;
            submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Creating employee...';

            try {
                const response = await fetch('api/onboard.php', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                goToStep(5);
                displayResult(result);

            } catch (error) {
                alert('Error: ' + error.message);
                submitBtn.disabled = false;
                submitBtn.innerHTML = '<i class="fas fa-rocket"></i> Create Employee';
            }
        }

        function displayResult(result) {
            let html = '';

            if (result.success) {
                html = `
                    <div class="text-center mb-4">
                        <div class="display-1 text-success mb-3">
                            <i class="fas fa-check-circle"></i>
                        </div>
                        <h2>Employee Created Successfully!</h2>
                        <p class="text-muted">User ID: ${result.user_id}</p>
                    </div>

                    <div class="summary-card">
                        <h5>System Provisioning Results</h5>
                        ${formatSyncResults(result.sync_results)}
                    </div>

                    <div class="text-center mt-4">
                        <a href="dashboard.php" class="btn btn-primary btn-wizard">
                            <i class="fas fa-users"></i> View All Employees
                        </a>
                        <a href="onboarding-wizard.php" class="btn btn-secondary btn-wizard ms-2">
                            <i class="fas fa-plus"></i> Add Another Employee
                        </a>
                    </div>
                `;
            } else {
                html = `
                    <div class="text-center mb-4">
                        <div class="display-1 text-danger mb-3">
                            <i class="fas fa-times-circle"></i>
                        </div>
                        <h2>Onboarding Failed</h2>
                        <p class="text-danger">${result.error}</p>
                    </div>

                    <div class="text-center">
                        <button class="btn btn-primary btn-wizard" onclick="location.reload()">
                            <i class="fas fa-redo"></i> Try Again
                        </button>
                    </div>
                `;
            }

            document.getElementById('resultContainer').innerHTML = html;
            document.getElementById('btnPrevious').style.display = 'none';
            document.getElementById('btnSubmit').style.display = 'none';
        }

        function formatSyncResults(results) {
            let html = '';

            for (let [system, result] of Object.entries(results)) {
                const icon = result.status === 'success' ? 'check-circle' :
                            result.status === 'failed' ? 'times-circle' : 'info-circle';
                const color = result.status === 'success' ? 'success' :
                             result.status === 'failed' ? 'danger' : 'secondary';

                html += `
                    <p>
                        <i class="fas fa-${icon} text-${color}"></i>
                        <strong>${system.charAt(0).toUpperCase() + system.slice(1)}:</strong>
                        ${result.message || result.error || 'Unknown status'}
                    </p>
                `;
            }

            return html;
        }
    </script>
</body>
</html>
