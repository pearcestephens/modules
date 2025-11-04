<?php
/**
 * AI Consignment Assistant - Usage Examples
 *
 * This shows all the ways you can use AI in the Consignments module
 */

declare(strict_types=1);

require_once __DIR__ . '/src/Services/AIConsignmentAssistant.php';

use Consignments\Services\AIConsignmentAssistant;

echo "\n";
echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║         AI CONSIGNMENT ASSISTANT - USAGE EXAMPLES                ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n";
echo "\n";

// Initialize
$ai = new AIConsignmentAssistant();

// ========================================================================
// Example 1: Carrier Recommendation
// ========================================================================
echo "Example 1: Get Carrier Recommendation\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$transferData = [
    'id' => 12345,
    'origin_outlet_id' => 1,
    'dest_outlet_id' => 5,
    'urgency' => 'normal',
];

try {
    $recommendation = $ai->recommendCarrier($transferData);

    if ($recommendation['success']) {
        echo "✅ Recommendation:\n";
        echo $recommendation['recommendation'] . "\n";
        echo "\n📊 Details:\n";
        echo "  Confidence: " . round($recommendation['confidence'] * 100) . "%\n";
        echo "  AI Provider: {$recommendation['provider']}\n";
        echo "  Cost: \${$recommendation['cost_usd']}\n";
    } else {
        echo "❌ Failed: {$recommendation['error']}\n";
        echo "📦 Fallback: {$recommendation['fallback']}\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// ========================================================================
// Example 2: Transfer Analysis
// ========================================================================
echo "Example 2: Analyze Transfer\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$consignmentId = 12345;

try {
    $analysis = $ai->analyzeTransfer($consignmentId);

    if ($analysis['success']) {
        echo "✅ Analysis:\n";
        echo $analysis['analysis'] . "\n";

        if (!empty($analysis['anomalies_detected'])) {
            echo "\n⚠️  Anomalies Detected:\n";
            foreach ($analysis['anomalies_detected'] as $anomaly) {
                echo "  • [{$anomaly['severity']}] {$anomaly['message']}\n";
            }
        }

        if (!empty($analysis['suggestions'])) {
            echo "\n💡 Suggestions:\n";
            foreach ($analysis['suggestions'] as $suggestion) {
                echo "  • $suggestion\n";
            }
        }
    } else {
        echo "❌ Failed: {$analysis['error']}\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// ========================================================================
// Example 3: Ask AI a Question
// ========================================================================
echo "Example 3: Ask AI a Question\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$questions = [
    "What are the main files in the consignments module?",
    "How do I create a new transfer?",
    "What carriers do we support?",
];

foreach ($questions as $i => $question) {
    echo ($i + 1) . ". Question: \"$question\"\n";

    try {
        $answer = $ai->ask($question);

        if ($answer['success']) {
            echo "   ✅ Answer: " . substr($answer['answer'], 0, 150) . "...\n";
            echo "   Confidence: " . round($answer['confidence'] * 100) . "%\n";
        } else {
            echo "   ❌ Failed: {$answer['error']}\n";
        }
    } catch (Exception $e) {
        echo "   ❌ Error: " . $e->getMessage() . "\n";
    }

    echo "\n";
}

// ========================================================================
// Example 4: Cost Prediction
// ========================================================================
echo "Example 4: Predict Transfer Cost\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

$transferForCost = [
    'id' => 12345,
    'origin_outlet_id' => 1,
    'dest_outlet_id' => 10,
    'urgency' => 'urgent',
];

try {
    $prediction = $ai->predictCost($transferForCost);

    if ($prediction['success']) {
        echo "✅ Cost Prediction:\n";
        echo $prediction['prediction'] . "\n";
        echo "\n📊 Confidence: " . round($prediction['confidence'] * 100) . "%\n";
    } else {
        echo "❌ Failed: {$prediction['error']}\n";
    }
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
}

echo "\n";

// ========================================================================
// Example 5: Using with API
// ========================================================================
echo "Example 5: Using via REST API\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "curl -X POST https://staff.vapeshed.co.nz/modules/consignments/api/ai-assistant.php?action=recommend-carrier \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '{\n";
echo "    \"transfer\": {\n";
echo "      \"id\": 12345,\n";
echo "      \"origin_outlet_id\": 1,\n";
echo "      \"dest_outlet_id\": 5,\n";
echo "      \"urgency\": \"normal\"\n";
echo "    }\n";
echo "  }'\n\n";

echo "curl -X POST https://staff.vapeshed.co.nz/modules/consignments/api/ai-assistant.php?action=ask \\\n";
echo "  -H 'Content-Type: application/json' \\\n";
echo "  -d '{\"question\": \"How do I create a transfer?\"}'\n\n";

// ========================================================================
// Example 6: Integration with ConsignmentService
// ========================================================================
echo "Example 6: Integration with ConsignmentService\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

echo "<?php\n";
echo "// In your controller or service:\n";
echo "use Consignments\\Services\\ConsignmentService;\n";
echo "use Consignments\\Services\\AIConsignmentAssistant;\n\n";
echo "\$consignmentService = ConsignmentService::make();\n";
echo "\$ai = new AIConsignmentAssistant();\n\n";
echo "// Create transfer\n";
echo "\$transferId = \$consignmentService->create([\n";
echo "    'ref_code' => 'TR-2025-001',\n";
echo "    'origin_outlet_id' => 1,\n";
echo "    'dest_outlet_id' => 5,\n";
echo "    'status' => 'draft',\n";
echo "]);\n\n";
echo "// Get AI recommendation\n";
echo "\$recommendation = \$ai->recommendCarrier(['id' => \$transferId]);\n";
echo "echo \$recommendation['recommendation'];\n\n";
echo "// Analyze it\n";
echo "\$analysis = \$ai->analyzeTransfer(\$transferId);\n";
echo "if (!empty(\$analysis['anomalies_detected'])) {\n";
echo "    // Handle anomalies\n";
echo "}\n";

echo "\n";

// ========================================================================
// Summary
// ========================================================================
echo "\n";
echo "╔══════════════════════════════════════════════════════════════════╗\n";
echo "║                          SUMMARY                                 ║\n";
echo "╚══════════════════════════════════════════════════════════════════╝\n";
echo "\n";
echo "🎉 AI Features Available:\n";
echo "  ✅ Carrier recommendations\n";
echo "  ✅ Transfer analysis\n";
echo "  ✅ Natural language queries\n";
echo "  ✅ Cost predictions\n";
echo "  ✅ Anomaly detection\n";
echo "  ✅ REST API access\n";
echo "\n";
echo "💡 Next Steps:\n";
echo "  1. Integrate into Transfer Manager UI\n";
echo "  2. Add \"Ask AI\" button\n";
echo "  3. Show recommendations in create form\n";
echo "  4. Add anomaly alerts\n";
echo "\n";
echo "📚 Files:\n";
echo "  • src/Services/AIConsignmentAssistant.php - Main service\n";
echo "  • api/ai-assistant.php - REST API\n";
echo "  • ai-consignment-examples.php - This file\n";
echo "\n";
