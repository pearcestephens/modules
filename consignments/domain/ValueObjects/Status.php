<?php declare(strict_types=1);

namespace Consignments\Domain\ValueObjects;

/**
 * Canonical Internal Status Value Object
 *
 * Represents the authoritative internal state of a consignment in CIS.
 * Maps to Lightspeed X-Series consignment states via StatusMap.
 *
 * @see \Consignments\Infra\Lightspeed\StatusMap for CIS↔LS mapping
 * @see \Consignments\Domain\Policies\StateTransitionPolicy for allowed transitions
 */
final class Status
{
    // Canonical internal states
    public const DRAFT = 'draft';           // Created in CIS, not yet sent to LS
    public const SENT = 'sent';             // Sent to LS, awaiting dispatch
    public const RECEIVING = 'receiving';   // Being received at destination
    public const RECEIVED = 'received';     // Fully received, not yet finalized
    public const COMPLETED = 'completed';   // Finalized and closed
    public const CANCELLED = 'cancelled';   // Cancelled/voided

    private const VALID_STATES = [
        self::DRAFT,
        self::SENT,
        self::RECEIVING,
        self::RECEIVED,
        self::COMPLETED,
        self::CANCELLED,
    ];

    private function __construct(
        private readonly string $value
    ) {
        if (!in_array($value, self::VALID_STATES, true)) {
            throw new \InvalidArgumentException(
                sprintf('Invalid status "%s". Allowed: %s',
                    $value,
                    implode(', ', self::VALID_STATES)
                )
            );
        }
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public static function draft(): self
    {
        return new self(self::DRAFT);
    }

    public static function sent(): self
    {
        return new self(self::SENT);
    }

    public static function receiving(): self
    {
        return new self(self::RECEIVING);
    }

    public static function received(): self
    {
        return new self(self::RECEIVED);
    }

    public static function completed(): self
    {
        return new self(self::COMPLETED);
    }

    public static function cancelled(): self
    {
        return new self(self::CANCELLED);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(Status $other): bool
    {
        return $this->value === $other->value;
    }

    public function isDraft(): bool
    {
        return $this->value === self::DRAFT;
    }

    public function isSent(): bool
    {
        return $this->value === self::SENT;
    }

    public function isReceiving(): bool
    {
        return $this->value === self::RECEIVING;
    }

    public function isReceived(): bool
    {
        return $this->value === self::RECEIVED;
    }

    public function isCompleted(): bool
    {
        return $this->value === self::COMPLETED;
    }

    public function isCancelled(): bool
    {
        return $this->value === self::CANCELLED;
    }

    public function isFinal(): bool
    {
        return $this->value === self::COMPLETED || $this->value === self::CANCELLED;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
