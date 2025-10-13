<?php
declare(strict_types=1);

namespace Modules\Consignments\ValueObjects;

/**
 * Delivery Mode Value Object - Type-safe delivery options
 */
enum DeliveryMode: string
{
    case PICKUP = 'pickup';
    case DROPOFF = 'dropoff';  
    case COURIER = 'manual';
    case INTERNAL_DRIVE = 'internal_drive';

    /**
     * Get human-readable label
     */
    public function getLabel(): string
    {
        return match ($this) {
            self::PICKUP => 'Pickup',
            self::DROPOFF => 'Drop-off',
            self::COURIER => 'Manual Courier',
            self::INTERNAL_DRIVE => 'Internal Drive',
        };
    }

    /**
     * Does this mode require freight calculation?
     */
    public function requiresFreight(): bool
    {
        return match ($this) {
            self::COURIER, self::INTERNAL_DRIVE => true,
            default => false,
        };
    }

    /**
     * Get icon class for UI
     */
    public function getIcon(): string
    {
        return match ($this) {
            self::PICKUP => 'fas fa-arrow-up',
            self::DROPOFF => 'fas fa-arrow-down',
            self::COURIER => 'fas fa-truck',
            self::INTERNAL_DRIVE => 'fas fa-car',
        };
    }
}