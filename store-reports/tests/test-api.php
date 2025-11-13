<?php
/**
 * API Test Suite
 * Tests all store-reports API endpoints
 *
 * Usage: php test-api.php
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';

class ApiTester
{
    private string $baseUrl;
    private string $csrfToken;
    private array $cookies = [];
    private array $results = [];

    public function __construct(string $baseUrl)
    {
        $this->baseUrl = rtrim($baseUrl, '/');
        echo "╔════════════════════════════════════════════════════════════════╗\n";
        echo "║           STORE REPORTS API TEST SUITE                        ║\n";
        echo "╚════════════════════════════════════════════════════════════════╝\n\n";
    }

    public function runAll(): void
    {
        $this->testReportCreate();
        $this->testReportUpdate();
        $this->testPhotoUpload();
        $this->printSummary();
    }

    private function testReportCreate(): void
    {
        echo "🧪 Testing: POST /api/reports-create\n";

        $data = [
            'outlet_id' => 'test_outlet_001',
            'device_id' => 'test_device_' . time()
        ];

        $response = $this->post('/api/reports-create', $data);

        if ($response['success'] ?? false) {
            echo "   ✅ Report created: ID = {$response['report_id']}\n";
            echo "   📋 Checklist items: " . count($response['checklist'] ?? []) . "\n";
            $this->results['report_id'] = $response['report_id'];
        } else {
            echo "   ❌ Failed: " . ($response['error'] ?? 'Unknown error') . "\n";
        }

        echo "\n";
    }

    private function testReportUpdate(): void
    {
        echo "🧪 Testing: PUT /api/reports-update\n";

        $reportId = $this->results['report_id'] ?? null;

        if (!$reportId) {
            echo "   ⚠️  Skipped: No report_id from create test\n\n";
            return;
        }

        $data = [
            'report_id' => $reportId,
            'staff_notes' => 'Test notes from API test suite',
            'items' => [
                [
                    'checklist_id' => 1,
                    'response_value' => 4,
                    'staff_notes' => 'Excellent condition'
                ]
            ],
            'autosave' => true
        ];

        $response = $this->put('/api/reports-update', $data);

        if ($response['success'] ?? false) {
            echo "   ✅ Report updated\n";
            echo "   📊 Completion: {$response['completion_percentage']}%\n";
            echo "   💾 Autosave: " . ($response['autosave']['checkpoint_id'] ?? 'N/A') . "\n";
        } else {
            echo "   ❌ Failed: " . ($response['error'] ?? 'Unknown error') . "\n";
        }

        echo "\n";
    }

    private function testPhotoUpload(): void
    {
        echo "🧪 Testing: POST /api/photos-upload\n";

        $reportId = $this->results['report_id'] ?? null;

        if (!$reportId) {
            echo "   ⚠️  Skipped: No report_id from create test\n\n";
            return;
        }

        // Create dummy image
        $tmpImage = tempnam(sys_get_temp_dir(), 'test_photo_') . '.jpg';
        $img = imagecreatetruecolor(800, 600);
        $bgColor = imagecolorallocate($img, 100, 150, 200);
        imagefilledrectangle($img, 0, 0, 800, 600, $bgColor);
        imagejpeg($img, $tmpImage, 90);
        imagedestroy($img);

        $response = $this->postFile('/api/photos-upload', [
            'report_id' => $reportId,
            'caption' => 'Test photo from API suite'
        ], 'photo', $tmpImage);

        if ($response['success'] ?? false) {
            echo "   ✅ Photo uploaded: ID = {$response['image_id']}\n";
            echo "   📦 Size: " . $this->formatBytes($response['file_size'] ?? 0) . "\n";
            echo "   🔄 Queued for optimization\n";
        } else {
            echo "   ❌ Failed: " . ($response['error'] ?? 'Unknown error') . "\n";
        }

        unlink($tmpImage);
        echo "\n";
    }

    private function post(string $endpoint, array $data): array
    {
        return $this->request('POST', $endpoint, $data);
    }

    private function put(string $endpoint, array $data): array
    {
        return $this->request('PUT', $endpoint, $data);
    }

    private function request(string $method, string $endpoint, array $data): array
    {
        $url = $this->baseUrl . $endpoint;

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Content-Type: application/json',
            'X-CSRF-Token: ' . $this->getCsrfToken()
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        return json_decode($response, true) ?? ['error' => 'Invalid JSON response'];
    }

    private function postFile(string $endpoint, array $data, string $fileField, string $filePath): array
    {
        $url = $this->baseUrl . $endpoint;

        $postFields = $data;
        $postFields[$fileField] = new CURLFile($filePath, mime_content_type($filePath), basename($filePath));

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postFields);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'X-CSRF-Token: ' . $this->getCsrfToken()
        ]);

        $response = curl_exec($ch);
        curl_close($ch);

        return json_decode($response, true) ?? ['error' => 'Invalid JSON response'];
    }

    private function getCsrfToken(): string
    {
        if (!$this->csrfToken) {
            $this->csrfToken = bin2hex(random_bytes(32));
        }
        return $this->csrfToken;
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 2) . ' ' . $units[$i];
    }

    private function printSummary(): void
    {
        echo "════════════════════════════════════════════════════════════════\n";
        echo "TEST SUMMARY\n";
        echo "════════════════════════════════════════════════════════════════\n";
        echo "Report ID: " . ($this->results['report_id'] ?? 'N/A') . "\n";
        echo "\nAll critical API endpoints tested.\n";
        echo "Check above for individual test results.\n";
    }
}

// Run tests
$baseUrl = getenv('APP_URL') ?: 'https://staff.vapeshed.co.nz/modules/store-reports';
$tester = new ApiTester($baseUrl);
$tester->runAll();
