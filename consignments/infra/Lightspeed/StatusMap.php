<?php declare(strict_types=1);

namespace Consignments\Infra\Lightspeed;

use Consignments\Domain\ValueObjects\Status;

/**
 * Status Mapping: CIS ↔ Lightspeed X-Series
 *
 * Anti-Corruption Layer for status translation between systems.
 * Lightspeed X-Series consignment states: OPEN, SENT, DISPATCHED, RECEIVED, CANCELLED
 *
 * Mapping Strategy:
 * - CIS 'draft' → LS 'OPEN' (created but not dispatched)
 * - CIS 'sent' → LS 'SENT' (sent from source, in transit)
 * - CIS 'receiving' → LS 'DISPATCHED' (arrived, being processed)
 * - CIS 'received' → LS 'RECEIVED' (fully received, not finalized)
 * - CIS 'completed' → LS 'RECEIVED' (CIS finalization on top of LS receive)
 * - CIS 'cancelled' → LS 'CANCELLED'
 *
 * Design Notes:
 * - Both 'received' and 'completed' map to LS 'RECEIVED' because LS doesn't have
 *   a separate finalization state. CIS tracks the extra completion step internally.
 * - LS is source of truth for inventory; CIS adds workflow orchestration.
 *
 * @see https://developers.lightspeedhq.com/retail/endpoints/Consignment/
 */
final class StatusMap
{
    /**
     * Map CIS internal status to Lightspeed status
     *
     * @throws \InvalidArgumentException if internal status is unknown
     */
    public static function toLightspeed(Status $internal): string
    {
        return match ($internal->toString()) {
            Status::DRAFT => 'OPEN',
            Status::SENT => 'SENT',
            Status::RECEIVING => 'DISPATCHED',
            Status::RECEIVED => 'RECEIVED',
            Status::COMPLETED => 'RECEIVED', // CIS-only finalization
            Status::CANCELLED => 'CANCELLED',
            default => throw new \InvalidArgumentException(
                sprintf('Unknown internal status: %s', $internal->toString())
            )
        };
    }

    /**
     * Map Lightspeed status to CIS internal status
     *
     * Note: LS 'RECEIVED' maps to CIS 'received' (not 'completed').
     * Completion is a CIS workflow step that happens after LS receive.
     */
    public static function toInternal(string $lightspeedStatus): Status
    {
        $normalized = strtoupper(trim($lightspeedStatus));

        return match ($normalized) {
            'OPEN' => Status::draft(),
            'SENT' => Status::sent(),
            'DISPATCHED' => Status::receiving(),
            'RECEIVED' => Status::received(), // Not completed - that's CIS-only
            'CANCELLED' => Status::cancelled(),
            default => Status::draft() // Defensive: unknown LS states treated as draft
        };
    }

    /**
     * Check if Lightspeed status exists (for validation)
     */
    public static function isValidLightspeedStatus(string $status): bool
    {
        $normalized = strtoupper(trim($status));
        return in_array($normalized, ['OPEN', 'SENT', 'DISPATCHED', 'RECEIVED', 'CANCELLED'], true);
    }

    /**
     * Get all valid Lightspeed statuses
     *
     * @return string[]
     */
    public static function getLightspeedStatuses(): array
    {
        return ['OPEN', 'SENT', 'DISPATCHED', 'RECEIVED', 'CANCELLED'];
    }

    /**
     * Get human-readable status label
     */
    public static function getLabel(Status $status): string
    {
        return match ($status->toString()) {
            Status::DRAFT => 'Draft',
            Status::SENT => 'Sent',
            Status::RECEIVING => 'Receiving',
            Status::RECEIVED => 'Received',
            Status::COMPLETED => 'Completed',
            Status::CANCELLED => 'Cancelled',
            default => 'Unknown'
        };
    }

    /**
     * Get CSS class for UI rendering
     */
    public static function getCssClass(Status $status): string
    {
        return match ($status->toString()) {
            Status::DRAFT => 'badge-secondary',
            Status::SENT => 'badge-info',
            Status::RECEIVING => 'badge-warning',
            Status::RECEIVED => 'badge-primary',
            Status::COMPLETED => 'badge-success',
            Status::CANCELLED => 'badge-danger',
            default => 'badge-dark'
        };
    }
}
