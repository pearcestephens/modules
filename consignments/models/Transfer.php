<?php
declare(strict_types=1);

namespace Modules\Consignments\Models;

use Modules\Consignments\ValueObjects\TransferState;
use Modules\Consignments\ValueObjects\DeliveryMode;

/**
 * Transfer Domain Model - Rich object with business behavior
 * 
 * Represents a transfer entity with full business logic,
 * validation, and state management encapsulated.
 * 
 * @package Modules\Consignments\Models
 */
final class Transfer
{
    private ?int $id = null;
    private string $outletFrom;
    private string $outletTo;
    private string $kind;
    private TransferState $state;
    private DeliveryMode $deliveryMode;
    private ?float $freightCost = null;
    private int $createdBy;
    private ?int $submittedBy = null;
    private array $metadata = [];
    private \DateTimeImmutable $createdAt;
    private ?\DateTimeImmutable $updatedAt = null;
    private ?\DateTimeImmutable $submittedAt = null;
    private ?array $optimizationPlan = null;

    public function __construct(array $data)
    {
        $this->id = $data['id'] ?? null;
        $this->outletFrom = $data['outlet_from'];
        $this->outletTo = $data['outlet_to'];
        $this->kind = $data['kind'];
        $this->state = $data['state'] instanceof TransferState ? $data['state'] : TransferState::from($data['state']);
        $this->deliveryMode = $data['delivery_mode'] instanceof DeliveryMode 
            ? $data['delivery_mode'] 
            : DeliveryMode::from($data['delivery_mode']);
        $this->freightCost = $data['freight_cost'] ?? null;
        $this->createdBy = $data['created_by'];
        $this->submittedBy = $data['submitted_by'] ?? null;
        $this->metadata = $data['metadata'] ?? [];
        $this->createdAt = $data['created_at'] instanceof \DateTimeImmutable 
            ? $data['created_at'] 
            : new \DateTimeImmutable($data['created_at'] ?? 'now');
        $this->updatedAt = isset($data['updated_at']) 
            ? new \DateTimeImmutable($data['updated_at']) 
            : null;
        $this->submittedAt = isset($data['submitted_at']) 
            ? new \DateTimeImmutable($data['submitted_at']) 
            : null;
        $this->optimizationPlan = $data['optimization_plan'] ?? null;

        $this->validate();
    }

    // Getters
    public function getId(): ?int { return $this->id; }
    public function getOutletFrom(): string { return $this->outletFrom; }
    public function getOutletTo(): string { return $this->outletTo; }
    public function getKind(): string { return $this->kind; }
    public function getState(): TransferState { return $this->state; }
    public function getDeliveryMode(): DeliveryMode { return $this->deliveryMode; }
    public function getFreightCost(): ?float { return $this->freightCost; }
    public function getCreatedBy(): int { return $this->createdBy; }
    public function getSubmittedBy(): ?int { return $this->submittedBy; }
    public function getMetadata(): array { return $this->metadata; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getUpdatedAt(): ?\DateTimeImmutable { return $this->updatedAt; }
    public function getSubmittedAt(): ?\DateTimeImmutable { return $this->submittedAt; }
    public function getOptimizationPlan(): ?array { return $this->optimizationPlan; }

    // Setters with business logic
    public function setState(TransferState $state): void
    {
        if (!$this->canTransitionTo($state)) {
            throw new \DomainException("Invalid state transition from {$this->state->value} to {$state->value}");
        }
        $this->state = $state;
    }

    public function setFreightCost(float $cost): void
    {
        if ($cost < 0) {
            throw new \InvalidArgumentException('Freight cost cannot be negative');
        }
        $this->freightCost = $cost;
    }

    public function setSubmittedAt(\DateTimeImmutable $submittedAt): void
    {
        $this->submittedAt = $submittedAt;
    }

    public function setSubmittedBy(int $userId): void
    {
        $this->submittedBy = $userId;
    }

    public function setOptimizationPlan(array $plan): void
    {
        $this->optimizationPlan = $plan;
    }

    // Business Logic Methods
    public function isStaffTransfer(): bool
    {
        return $this->kind === 'STAFF';
    }

    public function requiresFreightCalculation(): bool
    {
        return in_array($this->deliveryMode, [DeliveryMode::COURIER, DeliveryMode::INTERNAL_DRIVE], true);
    }

    public function canBeModified(): bool
    {
        return $this->state->canAddItems();
    }

    public function canBeSubmitted(): bool
    {
        return $this->state->canSubmit();
    }

    public function canBeCancelled(): bool
    {
        return in_array($this->state, [TransferState::DRAFT, TransferState::SUBMITTED], true);
    }

    public function isCompleted(): bool
    {
        return $this->state === TransferState::COMPLETED;
    }

    public function getDurationSinceCreation(): \DateInterval
    {
        return $this->createdAt->diff(new \DateTimeImmutable());
    }

    public function getDisplayName(): string
    {
        return "Transfer #{$this->id} ({$this->outletFrom} → {$this->outletTo})";
    }

    public function getMetadataValue(string $key, mixed $default = null): mixed
    {
        return $this->metadata[$key] ?? $default;
    }

    public function setMetadataValue(string $key, mixed $value): void
    {
        $this->metadata[$key] = $value;
    }

    // Factory method for creating with ID
    public function withId(int $id): self
    {
        $clone = clone $this;
        $clone->id = $id;
        return $clone;
    }

    // State transition validation
    private function canTransitionTo(TransferState $newState): bool
    {
        return match ([$this->state, $newState]) {
            [TransferState::DRAFT, TransferState::SUBMITTED] => true,
            [TransferState::SUBMITTED, TransferState::PROCESSING] => true,
            [TransferState::PROCESSING, TransferState::SHIPPED] => true,
            [TransferState::SHIPPED, TransferState::DELIVERED] => true,
            [TransferState::DELIVERED, TransferState::COMPLETED] => true,
            [TransferState::DRAFT, TransferState::CANCELLED] => true,
            [TransferState::SUBMITTED, TransferState::CANCELLED] => true,
            default => false,
        };
    }

    // Domain validation
    private function validate(): void
    {
        if (empty($this->outletFrom)) {
            throw new \InvalidArgumentException('Source outlet is required');
        }

        if (empty($this->outletTo)) {
            throw new \InvalidArgumentException('Destination outlet is required');
        }

        if ($this->outletFrom === $this->outletTo) {
            throw new \InvalidArgumentException('Source and destination outlets cannot be the same');
        }

        if (!in_array($this->kind, ['GENERAL', 'STAFF', 'JUICE', 'SUPPLIER'], true)) {
            throw new \InvalidArgumentException("Invalid transfer kind: {$this->kind}");
        }

        if ($this->createdBy <= 0) {
            throw new \InvalidArgumentException('Created by user ID must be positive');
        }

        if ($this->freightCost !== null && $this->freightCost < 0) {
            throw new \InvalidArgumentException('Freight cost cannot be negative');
        }
    }

    // Serialization support
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'outlet_from' => $this->outletFrom,
            'outlet_to' => $this->outletTo,
            'kind' => $this->kind,
            'state' => $this->state->value,
            'delivery_mode' => $this->deliveryMode->value,
            'freight_cost' => $this->freightCost,
            'created_by' => $this->createdBy,
            'submitted_by' => $this->submittedBy,
            'metadata' => $this->metadata,
            'created_at' => $this->createdAt->format('Y-m-d H:i:s'),
            'updated_at' => $this->updatedAt?->format('Y-m-d H:i:s'),
            'submitted_at' => $this->submittedAt?->format('Y-m-d H:i:s'),
            'optimization_plan' => $this->optimizationPlan,
        ];
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}