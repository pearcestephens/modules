<?php
/**
 * Lightspeed Sync Service
 *
 * Handles synchronization between CIS consignments and Lightspeed:
 * - Sync consignments to Lightspeed
 * - Sync line items
 * - Update stock levels
 * - Handle webhooks
 *
 * @package CIS\Consignments\Services
 * @version 1.0.0
 */

declare(strict_types=1);

namespace CIS\Consignments\Services;

use CIS\Consignments\Infra\Lightspeed\LightspeedClient;
use PDO;

class LightspeedSync
{
    private PDO $db;
    private LightspeedClient $lightspeed;
    private ConsignmentHelpers $helpers;

    public function __construct(PDO $db)
    {
        $this->db = $db;
        $this->lightspeed = new LightspeedClient();
        $this->helpers = new ConsignmentHelpers($db);
    }

    /**
     * Sync consignment to Lightspeed
     */
    public function syncConsignment(int $consignmentId): array
    {
        try {
            // Get consignment data
            $consignment = $this->getConsignment($consignmentId);

            if (!$consignment) {
                throw new \RuntimeException("Consignment not found: {$consignmentId}");
            }

            // Check if already synced
            if ($consignment['lightspeed_id']) {
                return $this->updateLightspeedConsignment($consignment);
            } else {
                return $this->createLightspeedConsignment($consignment);
            }

        } catch (\Exception $e) {
            $this->helpers->logEvent($consignmentId, 'sync_failed', [
                'error' => $e->getMessage()
            ]);
            throw $e;
        }
    }

    /**
     * Create new consignment in Lightspeed
     */
    private function createLightspeedConsignment(array $consignment): array
    {
        $lightspeedData = $this->prepareLightspeedData($consignment);

        $response = $this->lightspeed->createConsignment($lightspeedData);

        if ($response['success']) {
            // Save Lightspeed ID
            $this->saveLightspeedId(
                (int) $consignment['id'],
                $response['data']['id']
            );

            $this->helpers->logEvent((int) $consignment['id'], 'synced_to_lightspeed', [
                'lightspeed_id' => $response['data']['id']
            ]);
        }

        return $response;
    }

    /**
     * Update existing consignment in Lightspeed
     */
    private function updateLightspeedConsignment(array $consignment): array
    {
        $lightspeedData = $this->prepareLightspeedData($consignment);

        $response = $this->lightspeed->updateConsignment(
            $consignment['lightspeed_id'],
            $lightspeedData
        );

        if ($response['success']) {
            $this->helpers->logEvent((int) $consignment['id'], 'updated_in_lightspeed', [
                'lightspeed_id' => $consignment['lightspeed_id']
            ]);
        }

        return $response;
    }

    /**
     * Prepare data for Lightspeed format
     */
    private function prepareLightspeedData(array $consignment): array
    {
        return [
            'name' => $consignment['name'] ?? 'Transfer #' . $consignment['id'],
            'type' => $consignment['transfer_category'],
            'source_outlet_id' => $consignment['source_outlet_id'],
            'destination_outlet_id' => $consignment['destination_outlet_id'],
            'supplier_id' => $consignment['supplier_id'],
            'status' => $consignment['status'],
            'due_at' => $consignment['due_at'],
            'total_cost' => $this->helpers->calculateTotalValue((int) $consignment['id'])
        ];
    }

    /**
     * Save Lightspeed ID back to database
     */
    private function saveLightspeedId(int $consignmentId, string $lightspeedId): void
    {
        $stmt = $this->db->prepare("
            UPDATE vend_consignments
            SET lightspeed_id = ?,
                updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$lightspeedId, $consignmentId]);
    }

    /**
     * Get consignment data
     */
    private function getConsignment(int $consignmentId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM vend_consignments
            WHERE id = ?
                AND deleted_at IS NULL
        ");
        $stmt->execute([$consignmentId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Sync line items to Lightspeed
     */
    public function syncLineItems(int $consignmentId): array
    {
        $items = $this->getLineItems($consignmentId);
        $results = [];

        foreach ($items as $item) {
            try {
                $response = $this->lightspeed->createLineItem([
                    'consignment_id' => $consignmentId,
                    'product_id' => $item['product_id'],
                    'quantity' => $item['quantity'],
                    'cost' => $item['unit_cost']
                ]);

                $results[] = [
                    'item_id' => $item['id'],
                    'success' => $response['success']
                ];

            } catch (\Exception $e) {
                $results[] = [
                    'item_id' => $item['id'],
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return $results;
    }

    /**
     * Get line items
     */
    private function getLineItems(int $consignmentId): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM vend_consignment_line_items
            WHERE transfer_id = ?
                AND deleted_at IS NULL
        ");
        $stmt->execute([$consignmentId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Handle Lightspeed webhook
     */
    public function handleWebhook(array $payload): array
    {
        $eventType = $payload['type'] ?? '';

        switch ($eventType) {
            case 'consignment.updated':
                return $this->handleConsignmentUpdated($payload['data']);

            case 'consignment.received':
                return $this->handleConsignmentReceived($payload['data']);

            default:
                return [
                    'success' => false,
                    'error' => 'Unknown webhook type: ' . $eventType
                ];
        }
    }

    /**
     * Handle consignment updated webhook
     */
    private function handleConsignmentUpdated(array $data): array
    {
        // Update local consignment with Lightspeed data
        $stmt = $this->db->prepare("
            UPDATE vend_consignments
            SET status = ?,
                updated_at = NOW()
            WHERE lightspeed_id = ?
        ");

        $stmt->execute([
            $data['status'],
            $data['id']
        ]);

        return ['success' => true];
    }

    /**
     * Handle consignment received webhook
     */
    private function handleConsignmentReceived(array $data): array
    {
        $this->helpers->updateStatus(
            (int) $data['id'],
            'RECEIVED',
            'Received via Lightspeed webhook'
        );

        return ['success' => true];
    }
}
