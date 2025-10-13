<?php
declare(strict_types=1);

namespace Modules\Consignments\ValueObjects;

/**
 * Transfer State Value Object - Type-safe state management
 * 
 * Encapsulates transfer state logic and valid transitions
 * with compile-time type safety.
 */
enum TransferState: string
{
    case DRAFT = 'draft';
    case SUBMITTED = 'submitted';
    case PROCESSING = 'processing';
    case SHIPPED = 'shipped';
    case DELIVERED = 'delivered';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    /**
     * Can items be added/modified in this state?
     */
    public function canAddItems(): bool
    {
        return match ($this) {
            self::DRAFT => true,
            default => false,
        };
    }

    /**
     * Can transfer be submitted in this state?
     */
    public function canSubmit(): bool
    {
        return match ($this) {
            self::DRAFT => true,
            default => false,
        };
    }

    /**
     * Is this state considered active (not terminal)?
     */
    public function isActive(): bool
    {
        return match ($this) {
            self::COMPLETED, self::CANCELLED => false,
            default => true,
        };
    }

    /**
     * Get human-readable label
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::DRAFT => 'Draft',
            self::SUBMITTED => 'Submitted',
            self::PROCESSING => 'Processing',
            self::SHIPPED => 'Shipped',
            self::DELIVERED => 'Delivered',
            self::COMPLETED => 'Completed',
            self::CANCELLED => 'Cancelled',
        };
    }

    /**
     * Get CSS class for state styling
     */
    public function getCssClass(): string
    {
        return match ($this) {
            self::DRAFT => 'badge-secondary',
            self::SUBMITTED => 'badge-info',
            self::PROCESSING => 'badge-warning',
            self::SHIPPED => 'badge-primary',
            self::DELIVERED => 'badge-success',
            self::COMPLETED => 'badge-success',
            self::CANCELLED => 'badge-danger',
        };
    }

    /**
     * Get next possible states from current state
     */
    public function getNextStates(): array
    {
        return match ($this) {
            self::DRAFT => [self::SUBMITTED, self::CANCELLED],
            self::SUBMITTED => [self::PROCESSING, self::CANCELLED],
            self::PROCESSING => [self::SHIPPED],
            self::SHIPPED => [self::DELIVERED],
            self::DELIVERED => [self::COMPLETED],
            default => [],
        };
    }

    /**
     * Can transition to given state?
     */
    public function canTransitionTo(self $newState): bool
    {
        return in_array($newState, $this->getNextStates(), true);
    }
}