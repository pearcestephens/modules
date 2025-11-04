<?php
declare(strict_types=1);

namespace Consignments\Services;

use PDO;
use Exception;

/**
 * AI-Powered Consignment Assistant
 *
 * Provides intelligent recommendations, analysis, and automation for consignments
 * using the Universal AI Stack (Intelligence Hub + OpenAI + Anthropic + Claude Bot)
 *
 * Features:
 * - Smart carrier recommendations based on weight, destination, cost
 * - Transfer analysis and optimization suggestions
 * - Anomaly detection (unusual quantities, patterns)
 * - Cost predictions
 * - Natural language queries about consignments
 * - Auto-categorization and tagging
 *
 * @package Consignments\Services
 */
class AIConsignmentAssistant
{
    private $ai;
    private PDO $db;

    public function __construct(PDO $db = null)
    {
        $this->db = $db ?? db_ro();

        // Load AI Router
        require_once __DIR__ . '/../../lib/Services/AI/UniversalAIRouter.php';
        $this->ai = new \CIS\Consignments\Services\AI\UniversalAIRouter($this->db);
    }

    /**
     * Get smart carrier recommendation for a transfer
     *
     * @param array $transferData Transfer details (weight, destination, origin, urgency)
     * @return array Recommendation with carrier, cost estimate, reasoning
     */
    public function recommendCarrier(array $transferData): array
    {
        $context = [
            'transfer_id' => $transferData['id'] ?? null,
            'from_outlet' => $transferData['origin_outlet_id'] ?? null,
            'to_outlet' => $transferData['dest_outlet_id'] ?? null,
            'total_weight' => $this->calculateTotalWeight($transferData['id'] ?? 0),
            'item_count' => $this->getItemCount($transferData['id'] ?? 0),
            'urgency' => $transferData['urgency'] ?? 'normal',
        ];

        $prompt = "Recommend the best carrier for this transfer:\n";
        $prompt .= "- From: Outlet {$context['from_outlet']}\n";
        $prompt .= "- To: Outlet {$context['to_outlet']}\n";
        $prompt .= "- Weight: {$context['total_weight']}kg\n";
        $prompt .= "- Items: {$context['item_count']}\n";
        $prompt .= "- Urgency: {$context['urgency']}\n\n";
        $prompt .= "Consider: cost, speed, reliability, weight limits, and past performance.";

        try {
            $response = $this->ai->chat($prompt, $context);

            return [
                'success' => true,
                'recommendation' => $response['message'],
                'confidence' => $response['confidence'],
                'reasoning' => $response['reasoning'] ?? '',
                'provider' => $response['metadata']['provider'],
                'cost_usd' => $response['metadata']['cost_usd'],
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'fallback' => $this->fallbackCarrierLogic($context),
            ];
        }
    }

    /**
     * Analyze a transfer and suggest improvements
     *
     * @param int $consignmentId Consignment ID
     * @return array Analysis with suggestions
     */
    public function analyzeTransfer(int $consignmentId): array
    {
        $consignment = $this->getConsignmentData($consignmentId);
        if (!$consignment) {
            return ['success' => false, 'error' => 'Consignment not found'];
        }

        $items = $this->getConsignmentItems($consignmentId);

        $context = [
            'transfer_id' => $consignmentId,
            'status' => $consignment['status'],
            'from_outlet' => $consignment['origin_outlet_id'],
            'to_outlet' => $consignment['dest_outlet_id'],
            'item_count' => count($items),
            'total_weight' => $this->calculateTotalWeight($consignmentId),
        ];

        $prompt = "Analyze this transfer and provide optimization suggestions:\n\n";
        $prompt .= "Transfer ID: {$consignmentId}\n";
        $prompt .= "Status: {$consignment['status']}\n";
        $prompt .= "Route: Outlet {$consignment['origin_outlet_id']} → Outlet {$consignment['dest_outlet_id']}\n";
        $prompt .= "Items: " . count($items) . "\n\n";
        $prompt .= "Consider:\n";
        $prompt .= "1. Is the quantity reasonable?\n";
        $prompt .= "2. Any potential issues with weight/volume?\n";
        $prompt .= "3. Better packing strategies?\n";
        $prompt .= "4. Cost optimization opportunities?\n";
        $prompt .= "5. Any anomalies or red flags?";

        try {
            $response = $this->ai->chat($prompt, $context, [
                'provider' => 'intelligence_hub', // Use internal for analysis
            ]);

            return [
                'success' => true,
                'analysis' => $response['message'],
                'confidence' => $response['confidence'],
                'suggestions' => $response['actions'] ?? [],
                'anomalies_detected' => $this->detectAnomalies($consignment, $items),
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Ask AI a question about consignments (natural language)
     *
     * @param string $question User's question
     * @param array $context Optional context
     * @return array AI response
     */
    public function ask(string $question, array $context = []): array
    {
        try {
            $response = $this->ai->chat($question, $context, [
                'provider' => 'intelligence_hub', // Use Intelligence Hub for codebase context
            ]);

            return [
                'success' => true,
                'answer' => $response['message'],
                'confidence' => $response['confidence'],
                'sources' => $response['metadata']['sources'] ?? [],
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Predict cost for a transfer
     *
     * @param array $transferData Transfer details
     * @return array Cost prediction
     */
    public function predictCost(array $transferData): array
    {
        $context = [
            'from_outlet' => $transferData['origin_outlet_id'] ?? null,
            'to_outlet' => $transferData['dest_outlet_id'] ?? null,
            'weight' => $this->calculateTotalWeight($transferData['id'] ?? 0),
            'urgency' => $transferData['urgency'] ?? 'normal',
        ];

        $prompt = "Predict freight cost for:\n";
        $prompt .= "Route: Outlet {$context['from_outlet']} → {$context['to_outlet']}\n";
        $prompt .= "Weight: {$context['weight']}kg\n";
        $prompt .= "Urgency: {$context['urgency']}\n\n";
        $prompt .= "Provide cost range (min-max) and most likely cost.";

        try {
            $response = $this->ai->chat($prompt, $context);

            return [
                'success' => true,
                'prediction' => $response['message'],
                'confidence' => $response['confidence'],
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Detect anomalies in a transfer
     *
     * @param array $consignment Consignment data
     * @param array $items Items data
     * @return array Detected anomalies
     */
    private function detectAnomalies(array $consignment, array $items): array
    {
        $anomalies = [];

        // Check for unusual quantities
        foreach ($items as $item) {
            if ($item['qty'] > 1000) {
                $anomalies[] = [
                    'type' => 'high_quantity',
                    'severity' => 'warning',
                    'message' => "Unusually high quantity for SKU {$item['sku']}: {$item['qty']} units",
                ];
            }

            if ($item['qty'] <= 0) {
                $anomalies[] = [
                    'type' => 'invalid_quantity',
                    'severity' => 'error',
                    'message' => "Invalid quantity for SKU {$item['sku']}: {$item['qty']}",
                ];
            }
        }

        // Check for empty transfers
        if (count($items) === 0) {
            $anomalies[] = [
                'type' => 'empty_transfer',
                'severity' => 'error',
                'message' => 'Transfer has no items',
            ];
        }

        // Check for same origin/destination
        if ($consignment['origin_outlet_id'] === $consignment['dest_outlet_id']) {
            $anomalies[] = [
                'type' => 'same_location',
                'severity' => 'error',
                'message' => 'Origin and destination are the same',
            ];
        }

        return $anomalies;
    }

    /**
     * Fallback carrier logic when AI fails
     */
    private function fallbackCarrierLogic(array $context): string
    {
        $weight = $context['total_weight'] ?? 0;

        if ($weight < 5) {
            return 'CourierPost (light parcel)';
        } elseif ($weight < 25) {
            return 'NZ Post (standard)';
        } elseif ($weight < 100) {
            return 'Mainfreight (freight)';
        } else {
            return 'Contact freight team for quote';
        }
    }

    /**
     * Get consignment data
     */
    private function getConsignmentData(int $id): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM consignments WHERE id = ? LIMIT 1');
        $stmt->execute([$id]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Get consignment items
     */
    private function getConsignmentItems(int $consignmentId): array
    {
        $stmt = $this->db->prepare('SELECT * FROM consignment_items WHERE consignment_id = ?');
        $stmt->execute([$consignmentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Calculate total weight
     */
    private function calculateTotalWeight(int $consignmentId): float
    {
        if ($consignmentId === 0) return 0.0;

        $items = $this->getConsignmentItems($consignmentId);
        $totalWeight = 0;

        foreach ($items as $item) {
            // Estimate 0.5kg per item if no weight data
            $totalWeight += ($item['qty'] ?? 0) * 0.5;
        }

        return round($totalWeight, 2);
    }

    /**
     * Get item count
     */
    private function getItemCount(int $consignmentId): int
    {
        if ($consignmentId === 0) return 0;

        $stmt = $this->db->prepare('SELECT COUNT(*) FROM consignment_items WHERE consignment_id = ?');
        $stmt->execute([$consignmentId]);
        return (int) $stmt->fetchColumn();
    }
}
