<?php
/**
 * Freight Provider Interface
 *
 * Defines contract for freight provider implementations.
 * Enables swapping between different freight providers (FreightNow, Toll, etc.)
 *
 * @package Consignments\App\Contracts
 */

declare(strict_types=1);

namespace Consignments\App\Contracts;

interface FreightProviderInterface
{
    /**
     * Create freight booking
     *
     * @param array $bookingData Booking details
     * @return array Booking result with tracking number
     * @throws \RuntimeException if booking fails
     */
    public function createBooking(array $bookingData): array;

    /**
     * Get tracking information
     *
     * @param string $trackingNumber Tracking/consignment number
     * @return array Tracking details
     * @throws \RuntimeException if tracking fails
     */
    public function getTracking(string $trackingNumber): array;

    /**
     * Cancel booking
     *
     * @param string $bookingReference Booking reference number
     * @return array Cancellation result
     * @throws \RuntimeException if cancellation fails
     */
    public function cancelBooking(string $bookingReference): array;

    /**
     * Get label PDF
     *
     * @param string $bookingReference Booking reference number
     * @return string PDF content (base64 encoded)
     * @throws \RuntimeException if label retrieval fails
     */
    public function getLabel(string $bookingReference): string;

    /**
     * Validate address
     *
     * @param array $address Address details
     * @return array Validation result with suggestions if invalid
     */
    public function validateAddress(array $address): array;

    /**
     * Get provider name
     *
     * @return string Provider identifier (e.g., 'freight_now', 'toll')
     */
    public function getProviderName(): string;
}
