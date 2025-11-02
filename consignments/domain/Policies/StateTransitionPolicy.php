<?php declare(strict_types=1);

namespace Consignments\Domain\Policies;

use Consignments\Domain\ValueObjects\Status;

/**
 * State Transition Policy
 *
 * Enforces legal state transitions for consignments.
 * Prevents illegal jumps (e.g., draft→completed without intermediate steps).
 *
 * Transition Rules:
 * - draft → sent | cancelled
 * - sent → receiving | cancelled
 * - receiving → received | cancelled
 * - received → completed
 * - completed → (terminal state)
 * - cancelled → (terminal state)
 *
 * Design: Fail-fast with explicit error messages for audit trail.
 */
final class StateTransitionPolicy
{
    /**
     * Allowed transitions map
     * @var array<string, string[]>
     */
    private const ALLOWED = [
        Status::DRAFT => [Status::SENT, Status::CANCELLED],
        Status::SENT => [Status::RECEIVING, Status::CANCELLED],
        Status::RECEIVING => [Status::RECEIVED, Status::CANCELLED],
        Status::RECEIVED => [Status::COMPLETED],
        Status::COMPLETED => [],
        Status::CANCELLED => [],
    ];

    /**
     * Validate if transition from current to new status is allowed
     *
     * @throws \InvalidArgumentException if transition is illegal
     */
    public static function assertAllowed(Status $from, Status $to): void
    {
        $fromValue = $from->toString();
        $toValue = $to->toString();

        // Same state is always allowed (idempotent updates)
        if ($fromValue === $toValue) {
            return;
        }

        $allowed = self::ALLOWED[$fromValue] ?? [];

        if (!in_array($toValue, $allowed, true)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'Illegal state transition: %s → %s. Allowed from %s: %s',
                    $fromValue,
                    $toValue,
                    $fromValue,
                    empty($allowed) ? '(terminal state)' : implode(', ', $allowed)
                )
            );
        }
    }

    /**
     * Check if transition is allowed without throwing
     */
    public static function isAllowed(Status $from, Status $to): bool
    {
        $fromValue = $from->toString();
        $toValue = $to->toString();

        // Same state is always allowed
        if ($fromValue === $toValue) {
            return true;
        }

        $allowed = self::ALLOWED[$fromValue] ?? [];
        return in_array($toValue, $allowed, true);
    }

    /**
     * Get list of allowed next states from current status
     *
     * @return string[]
     */
    public static function getAllowedTransitions(Status $from): array
    {
        return self::ALLOWED[$from->toString()] ?? [];
    }

    /**
     * Validate transition and return structured error for API responses
     *
     * @return array{valid: bool, error: string|null}
     */
    public static function validate(Status $from, Status $to): array
    {
        try {
            self::assertAllowed($from, $to);
            return ['valid' => true, 'error' => null];
        } catch (\InvalidArgumentException $e) {
            return ['valid' => false, 'error' => $e->getMessage()];
        }
    }
}
