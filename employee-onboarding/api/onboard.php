<?php
/**
 * API: Employee Onboarding
 *
 * Creates employee and provisions to all systems
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../services/UniversalOnboardingService.php';

use CIS\EmployeeOnboarding\UniversalOnboardingService;

// Authentication check
if (!isset($_SESSION['userID'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

$onboarding = new UniversalOnboardingService($pdo);

// Check permission
if (!$onboarding->checkPermission($_SESSION['userID'], 'system.manage_users')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Insufficient permissions']);
    exit;
}

try {
    // Build employee data from POST
    $employeeData = [
        'first_name' => $_POST['first_name'] ?? '',
        'last_name' => $_POST['last_name'] ?? '',
        'email' => $_POST['email'] ?? '',
        'phone' => $_POST['phone'] ?? null,
        'mobile' => $_POST['mobile'] ?? null,
        'date_of_birth' => $_POST['date_of_birth'] ?? null,
        'job_title' => $_POST['job_title'] ?? '',
        'department' => $_POST['department'] ?? null,
        'start_date' => $_POST['start_date'] ?? date('Y-m-d'),
        'employment_type' => $_POST['employment_type'] ?? 'full_time',
        'location_id' => $_POST['location_id'] ?? null,
        'manager_id' => $_POST['manager_id'] ?? null,
        'notes' => $_POST['notes'] ?? null,
        'roles' => $_POST['roles'] ?? []
    ];

    // System sync options
    $options = [
        'sync_xero' => isset($_POST['sync_xero']),
        'sync_deputy' => isset($_POST['sync_deputy']),
        'sync_lightspeed' => isset($_POST['sync_lightspeed'])
    ];

    // Validate required fields
    $required = ['first_name', 'last_name', 'email', 'job_title', 'location_id'];
    foreach ($required as $field) {
        if (empty($employeeData[$field])) {
            throw new Exception("Required field missing: $field");
        }
    }

    if (empty($employeeData['roles'])) {
        throw new Exception('At least one role must be selected');
    }

    // Onboard employee
    $result = $onboarding->onboardEmployee($employeeData, $options);

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
