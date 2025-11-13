<?php
/**
 * Deputy Employee Service
 *
 * Manages employee creation/updates in Deputy
 *
 * @package CIS\EmployeeOnboarding
 */

namespace CIS\EmployeeOnboarding;

use PDO;
use Exception;

class DeputyEmployeeService
{
    private $pdo;
    private $apiEndpoint;
    private $apiToken;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;

        // Load Deputy configuration
        $envFile = $_SERVER['DOCUMENT_ROOT'] . '/.env';
        if (file_exists($envFile)) {
            $env = parse_ini_file($envFile);
            $rawEndpoint = $env['DEPUTY_API_BASE_URL'] ?? $env['DEPUTY_ENDPOINT'] ?? 'vapeshed.au.deputy.com';
            $rawEndpoint = trim($rawEndpoint);
            // Remove any accidental leading scheme from legacy domain-only value
            $rawEndpoint = preg_replace('#^(https?://)+#i', '$1', $rawEndpoint);
            if (!preg_match('#^https?://#i', $rawEndpoint)) {
                $rawEndpoint = 'https://' . ltrim($rawEndpoint, '/');
            }
            // Ensure API path present
            if (!preg_match('#/api/\dv\d#', parse_url($rawEndpoint, PHP_URL_PATH) ?: '')) {
                $rawEndpoint = rtrim($rawEndpoint, '/') . '/api/v1';
            }
            $this->apiEndpoint = rtrim($rawEndpoint, '/');
            $this->apiToken = $env['DEPUTY_API_TOKEN'] ?? $env['DEPUTY_TOKEN'] ?? null;
        }

        if (!$this->apiToken) {
            // Try loading from existing deputy.php
            $deputyFile = $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/deputy.php';
            if (file_exists($deputyFile)) {
                require_once $deputyFile;
            }
        }
    }

    /**
     * Create employee in Deputy
     */
    public function createEmployee(int $userId, array $data): array
    {
        if (!$this->apiToken) {
            throw new Exception('Deputy API token not configured');
        }

        // Build employee payload
        $employee = [
            'FirstName' => $data['first_name'],
            'LastName' => $data['last_name'],
            'Email' => $data['email'],
            'Mobile' => $data['mobile'] ?? $data['phone'] ?? '',
            'DateOfBirth' => $data['date_of_birth'] ?? null,
            'StartDate' => strtotime($data['start_date'] ?? 'now'),
            'Active' => true,
            'StressProfile' => 1, // Default stress profile
            'EmploymentType' => $this->mapEmploymentType($data['employment_type'] ?? 'full_time'),
        ];

        // Add location if provided
        if (!empty($data['location_id'])) {
            $employee['MainLocation'] = $this->getDeputyLocationId($data['location_id']);
        }

        try {
            $response = $this->deputyApiCall('POST', '/resource/Employee', $employee);

            if ($response && isset($response['Id'])) {
                return [
                    'success' => true,
                    'employee_id' => (string) $response['Id'],
                    'response' => $response
                ];
            }

            throw new Exception('Deputy API returned no employee ID');

        } catch (Exception $e) {
            throw new Exception('Deputy API error: ' . $e->getMessage());
        }
    }

    /**
     * Update employee in Deputy
     */
    public function updateEmployee(int $userId, array $updates): array
    {
        $deputyId = $this->getDeputyEmployeeId($userId);

        if (!$deputyId) {
            throw new Exception('Employee not linked to Deputy');
        }

        // Build update payload
        $employee = ['Id' => $deputyId];

        $fieldMap = [
            'first_name' => 'FirstName',
            'last_name' => 'LastName',
            'email' => 'Email',
            'mobile' => 'Mobile',
            'phone' => 'Phone'
        ];

        foreach ($fieldMap as $cisField => $deputyField) {
            if (isset($updates[$cisField])) {
                $employee[$deputyField] = $updates[$cisField];
            }
        }

        try {
            $response = $this->deputyApiCall('POST', "/resource/Employee/{$deputyId}", $employee);

            return [
                'success' => true,
                'response' => $response
            ];

        } catch (Exception $e) {
            throw new Exception('Deputy update error: ' . $e->getMessage());
        }
    }

    /**
     * Deactivate employee in Deputy
     */
    public function deactivateEmployee(int $userId): array
    {
        $deputyId = $this->getDeputyEmployeeId($userId);

        if (!$deputyId) {
            return ['success' => true, 'message' => 'Not linked to Deputy'];
        }

        try {
            $response = $this->deputyApiCall('POST', "/resource/Employee/{$deputyId}", [
                'Id' => $deputyId,
                'Active' => false,
                'TerminationDate' => time()
            ]);

            return [
                'success' => true,
                'response' => $response
            ];

        } catch (Exception $e) {
            throw new Exception('Deputy deactivation error: ' . $e->getMessage());
        }
    }

    /**
     * Deputy API call helper
     */
    private function deputyApiCall(string $method, string $endpoint, ?array $data = null): array
    {
    // apiEndpoint already normalized to include /api/vX; avoid duplicating path
    $url = $this->apiEndpoint . $endpoint;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $this->apiToken,
            'Content-Type: application/json',
            'dp-meta-option: none'
        ]);

        if ($method === 'POST' && $data) {
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        }

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode >= 400) {
            throw new Exception("Deputy API returned HTTP $httpCode: $response");
        }

        return json_decode($response, true) ?: [];
    }

    /**
     * Get Deputy employee ID from mapping
     */
    private function getDeputyEmployeeId(int $userId): ?string
    {
        $stmt = $this->pdo->prepare("
            SELECT external_id
            FROM external_system_mappings
            WHERE user_id = ? AND system_name = 'deputy'
        ");
        $stmt->execute([$userId]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result['external_id'] ?? null;
    }

    /**
     * Map CIS location ID to Deputy location ID
     */
    private function getDeputyLocationId(int $cisLocationId): ?int
    {
        // This should be a mapping table or config
        // For now, return the same ID
        return $cisLocationId;
    }

    /**
     * Map employment type to Deputy format
     */
    private function mapEmploymentType(string $type): int
    {
        $map = [
            'full_time' => 1,
            'part_time' => 2,
            'casual' => 3,
            'contractor' => 4
        ];

        return $map[$type] ?? 1;
    }
}
