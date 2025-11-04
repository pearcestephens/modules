<?php
/**
 * Quick Bot API Test Script
 *
 * Tests all 3 bot API endpoints to verify they're working
 */

declare(strict_types=1);

echo "=== BOT API TEST SUITE ===\n\n";

$baseUrl = 'https://staff.vapeshed.co.nz/modules/human_resources/payroll/api';
$botToken = 'ci_automation_token';

// Test 1: Health Check
echo "TEST 1: Health Check\n";
echo "--------------------\n";
$ch = curl_init("{$baseUrl}/bot_events.php?action=health_check");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Bot-Token: {$botToken}"]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
echo "Response: " . ($response ?: '(empty)') . "\n\n";

// Test 2: Pending Events
echo "TEST 2: Pending Events\n";
echo "----------------------\n";
$ch = curl_init("{$baseUrl}/bot_events.php?action=pending_events");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Bot-Token: {$botToken}"]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
if ($response) {
    $data = json_decode($response, true);
    if ($data) {
        echo "Success: " . ($data['success'] ? 'YES' : 'NO') . "\n";
        echo "Event Count: " . count($data['data']['events'] ?? []) . "\n";
        echo "Response:\n" . json_encode($data, JSON_PRETTY_PRINT) . "\n\n";
    } else {
        echo "Response (not JSON): {$response}\n\n";
    }
} else {
    echo "Response: (empty)\n\n";
}

// Test 3: Bot Context API
echo "TEST 3: Bot Context API (staff 1)\n";
echo "----------------------------------\n";
$ch = curl_init("{$baseUrl}/bot_context.php?action=get_context&event_type=amendment&staff_id=1");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, ["X-Bot-Token: {$botToken}"]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
if ($response) {
    $data = json_decode($response, true);
    if ($data) {
        echo "Success: " . ($data['success'] ? 'YES' : 'NO') . "\n";
        if (isset($data['data']['staff_profile'])) {
            echo "Staff Name: " . $data['data']['staff_profile']['name'] . "\n";
        }
        echo "Response:\n" . json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n\n";
    } else {
        echo "Response (not JSON): {$response}\n\n";
    }
} else {
    echo "Response: (empty)\n\n";
}

// Test 4: Report Bot Status
echo "TEST 4: Report Bot Status\n";
echo "-------------------------\n";
$postData = json_encode([
    'bot_id' => 'test_bot_001',
    'status' => 'active',
    'events_processed' => 0,
    'decisions_made' => 0,
    'errors_count' => 0
]);

$ch = curl_init("{$baseUrl}/bot_events.php?action=report_status");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "X-Bot-Token: {$botToken}",
    "Content-Type: application/json"
]);
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: {$httpCode}\n";
echo "Response: " . ($response ?: '(empty)') . "\n\n";

echo "=== TEST COMPLETE ===\n";
