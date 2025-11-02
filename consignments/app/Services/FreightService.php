<?php
/**
 * Freight Service
 *
 * High-level abstraction for freight operations.
 * Wraps existing freight provider implementations for backward compatibility.
 *
 * @package Consignments\App\Services
 */

declare(strict_types=1);

namespace Consignments\App\Services;

use Consignments\App\Contracts\FreightProviderInterface;
use Psr\Log\LoggerInterface;

class FreightService
{
    private FreightProviderInterface $provider;
    private \PDO $pdo;
    private LoggerInterface $logger;

    public function __construct(
        FreightProviderInterface $provider,
        \PDO $pdo,
        LoggerInterface $logger
    ) {
        $this->provider = $provider;
        $this->pdo = $pdo;
        $this->logger = $logger;
    }

    /**
     * Create shipment for transfer
     *
     * @param int $transferId Transfer ID
     * @param array $shipmentData Shipment details
     * @return array Shipment result with tracking number
     * @throws \InvalidArgumentException if data invalid
     * @throws \RuntimeException if booking fails
     */
    public function createShipment(int $transferId, array $shipmentData): array
    {
        // Validate required fields
        $this->validateShipmentData($shipmentData);

        // Get transfer details
        $transfer = $this->getTransfer($transferId);
        if (!$transfer) {
            throw new \RuntimeException("Transfer {$transferId} not found");
        }

        $this->pdo->beginTransaction();
        try {
            // Create booking with provider
            $bookingResult = $this->provider->createBooking($shipmentData);

            // Store freight details
            $stmt = $this->pdo->prepare("
                INSERT INTO freight_bookings (
                    transfer_id,
                    provider,
                    booking_reference,
                    tracking_number,
                    label_url,
                    status,
                    booking_data,
                    created_at
                ) VALUES (
                    :transfer_id,
                    :provider,
                    :booking_reference,
                    :tracking_number,
                    :label_url,
                    'BOOKED',
                    :booking_data,
                    NOW()
                )
            ");
            $stmt->execute([
                'transfer_id' => $transferId,
                'provider' => $this->provider->getProviderName(),
                'booking_reference' => $bookingResult['booking_reference'],
                'tracking_number' => $bookingResult['tracking_number'],
                'label_url' => $bookingResult['label_url'] ?? null,
                'booking_data' => json_encode($bookingResult),
            ]);

            $bookingId = (int)$this->pdo->lastInsertId();

            // Update transfer with freight info
            $stmt = $this->pdo->prepare("
                UPDATE stock_transfers
                SET freight_booking_id = :booking_id,
                    freight_tracking_number = :tracking_number,
                    freight_booked_at = NOW()
                WHERE id = :transfer_id
            ");
            $stmt->execute([
                'booking_id' => $bookingId,
                'tracking_number' => $bookingResult['tracking_number'],
                'transfer_id' => $transferId,
            ]);

            $this->pdo->commit();

            $this->logger->info('Freight shipment created', [
                'transfer_id' => $transferId,
                'booking_id' => $bookingId,
                'tracking_number' => $bookingResult['tracking_number'],
                'provider' => $this->provider->getProviderName(),
            ]);

            return [
                'booking_id' => $bookingId,
                'booking_reference' => $bookingResult['booking_reference'],
                'tracking_number' => $bookingResult['tracking_number'],
                'label_url' => $bookingResult['label_url'] ?? null,
                'provider' => $this->provider->getProviderName(),
            ];

        } catch (\Exception $e) {
            $this->pdo->rollBack();
            $this->logger->error('Failed to create freight shipment', [
                'transfer_id' => $transferId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Track shipment
     *
     * @param int $transferId Transfer ID
     * @return array Tracking details
     * @throws \RuntimeException if tracking fails
     */
    public function trackShipment(int $transferId): array
    {
        // Get freight booking
        $booking = $this->getFreightBooking($transferId);
        if (!$booking) {
            throw new \RuntimeException("No freight booking found for transfer {$transferId}");
        }

        // Get tracking from provider
        $tracking = $this->provider->getTracking($booking['tracking_number']);

        // Update booking status if changed
        if ($tracking['status'] !== $booking['status']) {
            $stmt = $this->pdo->prepare("
                UPDATE freight_bookings
                SET status = :status,
                    last_tracking_update = NOW()
                WHERE id = :booking_id
            ");
            $stmt->execute([
                'status' => $tracking['status'],
                'booking_id' => $booking['id'],
            ]);
        }

        $this->logger->info('Freight tracking retrieved', [
            'transfer_id' => $transferId,
            'tracking_number' => $booking['tracking_number'],
            'status' => $tracking['status'],
        ]);

        return array_merge($tracking, [
            'booking_id' => $booking['id'],
            'provider' => $booking['provider'],
        ]);
    }

    /**
     * Cancel shipment
     *
     * @param int $transferId Transfer ID
     * @param string $reason Cancellation reason
     * @return array Cancellation result
     * @throws \RuntimeException if cancellation fails
     */
    public function cancelShipment(int $transferId, string $reason): array
    {
        // Get freight booking
        $booking = $this->getFreightBooking($transferId);
        if (!$booking) {
            throw new \RuntimeException("No freight booking found for transfer {$transferId}");
        }

        if ($booking['status'] === 'CANCELLED') {
            throw new \RuntimeException("Shipment already cancelled");
        }

        $this->pdo->beginTransaction();
        try {
            // Cancel with provider
            $result = $this->provider->cancelBooking($booking['booking_reference']);

            // Update booking status
            $stmt = $this->pdo->prepare("
                UPDATE freight_bookings
                SET status = 'CANCELLED',
                    cancellation_reason = :reason,
                    cancelled_at = NOW()
                WHERE id = :booking_id
            ");
            $stmt->execute([
                'reason' => $reason,
                'booking_id' => $booking['id'],
            ]);

            // Clear freight info from transfer
            $stmt = $this->pdo->prepare("
                UPDATE stock_transfers
                SET freight_booking_id = NULL,
                    freight_tracking_number = NULL
                WHERE id = :transfer_id
            ");
            $stmt->execute(['transfer_id' => $transferId]);

            $this->pdo->commit();

            $this->logger->info('Freight shipment cancelled', [
                'transfer_id' => $transferId,
                'booking_id' => $booking['id'],
                'reason' => $reason,
            ]);

            return [
                'success' => true,
                'booking_id' => $booking['id'],
                'tracking_number' => $booking['tracking_number'],
                'cancelled_at' => date('Y-m-d H:i:s'),
            ];

        } catch (\Exception $e) {
            $this->pdo->rollBack();
            $this->logger->error('Failed to cancel freight shipment', [
                'transfer_id' => $transferId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get label PDF
     *
     * @param int $transferId Transfer ID
     * @return string Base64 encoded PDF content
     * @throws \RuntimeException if label retrieval fails
     */
    public function getLabel(int $transferId): string
    {
        // Get freight booking
        $booking = $this->getFreightBooking($transferId);
        if (!$booking) {
            throw new \RuntimeException("No freight booking found for transfer {$transferId}");
        }

        // Get label from provider
        $labelPdf = $this->provider->getLabel($booking['booking_reference']);

        $this->logger->info('Freight label retrieved', [
            'transfer_id' => $transferId,
            'booking_id' => $booking['id'],
        ]);

        return $labelPdf;
    }

    /**
     * Validate shipping address
     *
     * @param array $address Address details
     * @return array Validation result
     */
    public function validateAddress(array $address): array
    {
        return $this->provider->validateAddress($address);
    }

    /**
     * Get freight booking history for transfer
     *
     * @param int $transferId Transfer ID
     * @return array Booking history
     */
    public function getBookingHistory(int $transferId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM freight_bookings
            WHERE transfer_id = :transfer_id
            ORDER BY created_at DESC
        ");
        $stmt->execute(['transfer_id' => $transferId]);
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    // ========================================================================
    // Private Helper Methods
    // ========================================================================

    /**
     * Validate shipment data
     *
     * @param array $data Shipment data
     * @throws \InvalidArgumentException if invalid
     */
    private function validateShipmentData(array $data): void
    {
        $required = ['from_address', 'to_address', 'items'];

        foreach ($required as $field) {
            if (empty($data[$field])) {
                throw new \InvalidArgumentException("Missing required field: {$field}");
            }
        }

        // Validate addresses have required fields
        foreach (['from_address', 'to_address'] as $addressField) {
            $address = $data[$addressField];
            $requiredAddressFields = ['name', 'address1', 'suburb', 'postcode', 'state'];

            foreach ($requiredAddressFields as $field) {
                if (empty($address[$field])) {
                    throw new \InvalidArgumentException("{$addressField}.{$field} is required");
                }
            }
        }

        // Validate items
        if (empty($data['items']) || !is_array($data['items'])) {
            throw new \InvalidArgumentException('At least one item is required');
        }
    }

    /**
     * Get transfer
     *
     * @param int $transferId Transfer ID
     * @return array|false Transfer data or false if not found
     */
    private function getTransfer(int $transferId): array|false
    {
        $stmt = $this->pdo->prepare("SELECT * FROM stock_transfers WHERE id = :id");
        $stmt->execute(['id' => $transferId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }

    /**
     * Get freight booking
     *
     * @param int $transferId Transfer ID
     * @return array|false Booking data or false if not found
     */
    private function getFreightBooking(int $transferId): array|false
    {
        $stmt = $this->pdo->prepare("
            SELECT * FROM freight_bookings
            WHERE transfer_id = :transfer_id
            ORDER BY created_at DESC
            LIMIT 1
        ");
        $stmt->execute(['transfer_id' => $transferId]);
        return $stmt->fetch(\PDO::FETCH_ASSOC);
    }
}
