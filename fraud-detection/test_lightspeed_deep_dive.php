<?php

/**
 * Lightspeed Deep Dive Analyzer - Test Script
 * 
 * Quick test to verify the analyzer works and show example usage
 */

require_once __DIR__ . '/LightspeedDeepDiveAnalyzer.php';
require_once __DIR__ . '/MultiSourceFraudAnalyzer.php';

use FraudDetection\LightspeedDeepDiveAnalyzer;
use FraudDetection\MultiSourceFraudAnalyzer;

// Database connection
try {
    $pdo = new PDO(
        "mysql:host=localhost;dbname=cis;charset=utf8mb4",
        "your_username",
        "your_password",
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage() . "\n");
}

echo "=================================================\n";
echo "LIGHTSPEED DEEP DIVE ANALYZER - TEST SCRIPT\n";
echo "=================================================\n\n";

// Test 1: Single Staff Analysis
echo "TEST 1: Single Staff Analysis\n";
echo "------------------------------\n";

$staffId = 1; // Change this to test with real staff ID
$days = 30;

$analyzer = new LightspeedDeepDiveAnalyzer($pdo, [
    'analysis_window_days' => $days,
    'enable_all_checks' => true,
    'alert_on_critical' => true,
]);

try {
    echo "Analyzing staff ID {$staffId} for last {$days} days...\n";
    $results = $analyzer->analyzeStaff($staffId, $days);
    
    echo "\n✅ ANALYSIS COMPLETE\n\n";
    
    // Show summary
    echo "Risk Score: {$results['risk_score']}/100\n";
    echo "Risk Level: {$results['risk_level']}\n";
    echo "Fraud Indicators: {$results['indicator_count']}\n";
    echo "Critical Alerts: {$results['critical_alert_count']}\n\n";
    
    // Show sections analyzed
    echo "Sections Analyzed:\n";
    foreach ($results['sections'] as $sectionName => $sectionData) {
        $issueCount = count($sectionData['issues_found'] ?? []);
        echo "  - {$sectionData['section_name']}: {$issueCount} issues\n";
    }
    
    echo "\n";
    
    // Show critical alerts if any
    if (count($results['critical_alerts']) > 0) {
        echo "🚨 CRITICAL ALERTS:\n";
        foreach ($results['critical_alerts'] as $idx => $alert) {
            echo "  " . ($idx + 1) . ". [{$alert['type']}] {$alert['description']}\n";
            echo "     Severity: " . ($alert['severity'] * 100) . "%\n";
        }
        echo "\n";
    }
    
    // Show top 5 fraud indicators
    if (count($results['fraud_indicators']) > 0) {
        echo "Top Fraud Indicators:\n";
        $topIndicators = array_slice($results['fraud_indicators'], 0, 5);
        foreach ($topIndicators as $idx => $indicator) {
            echo "  " . ($idx + 1) . ". [{$indicator['category']}] {$indicator['description']}\n";
            echo "     Severity: " . ($indicator['severity'] * 100) . "%\n";
        }
        echo "\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n\n";
}

// Test 2: Multi-Source Integration
echo "\nTEST 2: Multi-Source Fraud Analysis (with Lightspeed Deep Dive)\n";
echo "---------------------------------------------------------------\n";

$multiSource = new MultiSourceFraudAnalyzer($pdo, [
    'analysis_window_days' => 30,
    'enable_deep_camera_correlation' => true,
]);

try {
    echo "Running comprehensive multi-source analysis...\n";
    $analysis = $multiSource->analyzeStaff($staffId);
    
    echo "\n✅ MULTI-SOURCE ANALYSIS COMPLETE\n\n";
    
    echo "Overall Fraud Score: {$analysis['fraud_score']}\n";
    echo "Risk Level: {$analysis['risk_level']}\n";
    echo "Total Fraud Indicators: " . count($analysis['fraud_indicators']) . "\n";
    echo "\nSources Analyzed:\n";
    foreach ($analysis['sources_analyzed'] as $source) {
        echo "  - {$source}\n";
    }
    
    // Show Lightspeed-specific results
    if (isset($analysis['lightspeed_deep_dive'])) {
        echo "\n📊 LIGHTSPEED DEEP DIVE RESULTS:\n";
        $lsResults = $analysis['lightspeed_deep_dive'];
        echo "  Risk Score: {$lsResults['risk_score']}/100\n";
        echo "  Risk Level: {$lsResults['risk_level']}\n";
        echo "  Indicators: {$lsResults['indicator_count']}\n";
        echo "  Critical Alerts: {$lsResults['critical_alert_count']}\n";
    }
    
    echo "\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n\n";
}

// Test 3: Query Recent Analysis Results
echo "\nTEST 3: Query Recent Analysis Results from Database\n";
echo "---------------------------------------------------\n";

try {
    $stmt = $pdo->prepare("
        SELECT 
            a.id,
            a.staff_id,
            s.name as staff_name,
            a.risk_score,
            a.risk_level,
            a.indicator_count,
            a.critical_alert_count,
            a.created_at
        FROM lightspeed_deep_dive_analysis a
        LEFT JOIN staff_accounts s ON a.staff_id = s.id
        ORDER BY a.created_at DESC
        LIMIT 5
    ");
    $stmt->execute();
    $recentAnalyses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($recentAnalyses) > 0) {
        echo "Recent analyses:\n\n";
        foreach ($recentAnalyses as $analysis) {
            echo "ID: {$analysis['id']} | Staff: {$analysis['staff_name']} (ID {$analysis['staff_id']})\n";
            echo "  Risk: {$analysis['risk_score']}/100 ({$analysis['risk_level']})\n";
            echo "  Indicators: {$analysis['indicator_count']} | Critical: {$analysis['critical_alert_count']}\n";
            echo "  Date: {$analysis['created_at']}\n\n";
        }
    } else {
        echo "No analysis records found. Run Test 1 first to create data.\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n\n";
}

// Test 4: Query High-Risk Staff View
echo "\nTEST 4: High-Risk Staff View\n";
echo "----------------------------\n";

try {
    $stmt = $pdo->query("
        SELECT * FROM v_high_risk_staff_lightspeed
        LIMIT 5
    ");
    $highRiskStaff = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($highRiskStaff) > 0) {
        echo "High-risk staff members:\n\n";
        foreach ($highRiskStaff as $staff) {
            echo "Staff: {$staff['staff_name']} (ID {$staff['staff_id']})\n";
            echo "  Risk: {$staff['risk_score']}/100 ({$staff['risk_level']})\n";
            echo "  Payment Fraud: {$staff['payment_fraud_count']}\n";
            echo "  Customer Fraud: {$staff['customer_fraud_count']}\n";
            echo "  Inventory Fraud: {$staff['inventory_fraud_count']}\n";
            echo "  Register Fraud: {$staff['register_fraud_count']}\n";
            echo "  Banking Fraud: {$staff['banking_fraud_count']}\n";
            echo "  Transaction Manipulation: {$staff['manipulation_fraud_count']}\n";
            echo "  Last Analysis: {$staff['last_analysis']}\n\n";
        }
    } else {
        echo "No high-risk staff found.\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n\n";
}

// Test 5: Uninvestigated Incidents
echo "\nTEST 5: Uninvestigated Fraud Incidents\n";
echo "--------------------------------------\n";

try {
    $stmt = $pdo->query("
        SELECT * FROM v_uninvestigated_fraud_incidents
        ORDER BY severity DESC
        LIMIT 10
    ");
    $incidents = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($incidents) > 0) {
        echo "Uninvestigated incidents requiring attention:\n\n";
        foreach ($incidents as $incident) {
            echo "Category: {$incident['fraud_category']}\n";
            echo "  Staff ID: {$incident['staff_id']}\n";
            echo "  Type: {$incident['fraud_type']}\n";
            echo "  Severity: " . ($incident['severity'] * 100) . "%\n";
            echo "  Detected: {$incident['detected_at']}\n\n";
        }
    } else {
        echo "No uninvestigated incidents. All clear! ✅\n\n";
    }
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n\n";
}

echo "=================================================\n";
echo "TEST SCRIPT COMPLETE\n";
echo "=================================================\n";
echo "\nNext steps:\n";
echo "1. Review the analysis results above\n";
echo "2. Check database tables for stored data\n";
echo "3. Configure automated nightly analysis\n";
echo "4. Set up alerts for critical incidents\n";
echo "5. Train management on investigation workflow\n\n";
