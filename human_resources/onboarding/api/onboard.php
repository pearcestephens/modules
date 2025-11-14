$employeeData = [
    'first_name' => trim($post['first_name'] ?? ''),
    'last_name' => trim($post['last_name'] ?? ''),
    'email' => trim($post['email'] ?? ''),
    'mobile' => trim($post['mobile'] ?? ''),
    'phone' => trim($post['phone'] ?? ''),
    'date_of_birth' => trim($post['date_of_birth'] ?? null),
    'job_title' => trim($post['job_title'] ?? ''),
    'department' => trim($post['department'] ?? ''),
    'start_date' => trim($post['start_date'] ?? date('Y-m-d')),
    'employment_type' => trim($post['employment_type'] ?? 'full_time'),
    'location_id' => (int)($post['location_id'] ?? 0),
    'manager_id' => $post['manager_id'] ? (int)$post['manager_id'] : null,
    'roles' => array_map('intval', $post['roles'] ?? []),
    'notes' => trim($post['notes'] ?? ''),
    'username' => trim($post['username'] ?? ''),
    'password' => trim($post['password'] ?? ''),
    'ird_number' => trim($post['ird_number'] ?? ''),
    'tax_code' => trim($post['tax_code'] ?? ''),
    'address' => trim($post['address'] ?? '')
];

// Validate NZ-specific fields
if (empty($employeeData['ird_number']) || !preg_match('/^[0-9]{8,9}$/', $employeeData['ird_number'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid IRD number']);
    exit;
}

if (empty($employeeData['tax_code']) || !in_array($employeeData['tax_code'], ['M', 'ME', 'S', 'SH', 'ST'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid tax code']);
    exit;
}

if (empty($employeeData['address'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Address is required']);
    exit;
}
<?php
declare(strict_types=1);

require_once __DIR__ . '/../shared/bootstrap.php';
require_once __DIR__ . '/../services/UniversalOnboardingService.php';

use CIS\EmployeeOnboarding\UniversalOnboardingService;

// Ensure session
if (session_status() !== PHP_SESSION_ACTIVE) { session_start(); }

header('Content-Type: application/json; charset=utf-8');

// Auth
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

// CSRF
$post = $_POST ?? [];
if (!isset($post['csrf_token']) || !hash_equals($_SESSION['csrf_onboard'] ?? '', $post['csrf_token'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Permission check using service
// Resolve PDO defensively
if (!isset($pdo) || !$pdo instanceof PDO) {
    if (function_exists('cis_resolve_pdo')) { $pdo = cis_resolve_pdo(); }
    elseif (isset($GLOBALS['pdo']) && $GLOBALS['pdo'] instanceof PDO) { $pdo = $GLOBALS['pdo']; }
}

if (!$pdo instanceof PDO) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database unavailable']);
    exit;
}

$service = new UniversalOnboardingService($pdo);
if (!$service->checkPermission($_SESSION['user_id'], 'system.manage_users')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Insufficient permissions']);
    exit;
}

// Collect and sanitize input
$employeeData = [
    'first_name' => trim($post['first_name'] ?? ''),
    'last_name' => trim($post['last_name'] ?? ''),
    'email' => trim($post['email'] ?? ''),
    'mobile' => trim($post['mobile'] ?? ''),
    'phone' => trim($post['phone'] ?? ''),
    'date_of_birth' => trim($post['date_of_birth'] ?? null),
    'job_title' => trim($post['job_title'] ?? ''),
    'department' => trim($post['department'] ?? ''),
    'start_date' => trim($post['start_date'] ?? date('Y-m-d')),
    'employment_type' => trim($post['employment_type'] ?? 'full_time'),
    'location_id' => (int)($post['location_id'] ?? 0),
    'manager_id' => $post['manager_id'] ? (int)$post['manager_id'] : null,
    'roles' => array_map('intval', $post['roles'] ?? []),
    'notes' => trim($post['notes'] ?? ''),
    'username' => trim($post['username'] ?? ''),
    'password' => trim($post['password'] ?? '')
];

$options = [
    'sync_xero' => isset($post['sync_xero']),
    'sync_deputy' => isset($post['sync_deputy']),
    'sync_lightspeed' => isset($post['sync_lightspeed'])
];

// Basic validation
if (empty($employeeData['first_name']) || empty($employeeData['last_name']) || empty($employeeData['email'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

// Call service
try {
    $result = $service->onboardEmployee($employeeData, $options);
    echo json_encode($result, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => $e->getMessage()]);
}
<?php
/**
 * API: Employee Onboarding
 *
 * Creates employee and provisions to all systems
 */

header('Content-Type: application/json');

require_once __DIR__ . '/../../shared/bootstrap.php';
require_once __DIR__ . '/../services/UniversalOnboardingService.php';

use CIS\EmployeeOnboarding\UniversalOnboardingService;

// Authentication check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
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
