<?php
// Quick API test - run from CLI or browser
session_start();
$_SESSION['user_id'] = 1; // Simulate logged in user

// Test with transfer 41732
$_GET['transfer_id'] = 41732;

echo "Testing API with transfer ID 41732...\n\n";

// Capture output
ob_start();
include 'get-transfer-data.php';
$output = ob_get_clean();

// Try to decode JSON
$data = json_decode($output, true);

if ($data && $data['success']) {
    echo "✅ API SUCCESS!\n\n";
    echo "Transfer: #" . $data['transfer']['public_id'] . "\n";
    echo "Status: " . $data['transfer']['state'] . "\n";
    echo "From: " . $data['transfer']['outlet_from_name'] . "\n";
    echo "To: " . $data['transfer']['outlet_to_name'] . "\n";
    echo "\nMetrics:\n";
    echo "  Total Items: " . $data['metrics']['total_items'] . "\n";
    echo "  Progress: " . $data['metrics']['packing_progress'] . "%\n";
    echo "  Over Picks: " . $data['metrics']['over_picks'] . "\n";
    echo "\nItems: " . count($data['items']) . " products\n";
    echo "Parcels: " . count($data['parcels']) . " boxes\n";
    echo "Notes: " . count($data['notes']) . " notes\n";
} else {
    echo "❌ API FAILED\n\n";
    echo "Response:\n";
    echo $output;
}
