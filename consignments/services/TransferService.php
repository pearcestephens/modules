<?php
declare(strict_types=1);

namespace Modules\Consignments\Services;

use Modules\Base\Services\BaseService;
use Modules\Consignments\Repositories\TransferRepository;
use Modules\Consignments\Repositories\TransferItemRepository;
use Modules\Consignments\Models\Transfer;
use Modules\Consignments\Models\TransferItem;
use Modules\Consignments\ValueObjects\TransferState;
use Modules\Consignments\ValueObjects\DeliveryMode;
use Modules\Consignments\Events\TransferCreated;
use Modules\Consignments\Events\TransferSubmitted;
use Transfers\Lib\Security;
use Transfers\Lib\Log;

/**
 * Transfer Service - Orchestrates transfer business logic
 * 
 * This service encapsulates all transfer-related business rules,
 * validation, and coordination between repositories and external systems.
 * 
 * @package Modules\Consignments\Services
 */
final class TransferService extends BaseService
{
    public function __construct(
        private TransferRepository $transferRepo,
        private TransferItemRepository $itemRepo,
        private VendSyncService $vendSync,
        private NotificationService $notifications,
        private FreightCalculationService $freight
    ) {
        parent::__construct();
    }

    /**
     * Create a new transfer with validation and state management
     */
    public function createTransfer(array $data): Transfer
    {
        $this->validateTransferData($data);
        
        $transfer = new Transfer([
            'outlet_from' => $data['outlet_from'],
            'outlet_to' => $data['outlet_to'],
            'kind' => $data['kind'] ?? 'GENERAL',
            'state' => TransferState::DRAFT,
            'delivery_mode' => DeliveryMode::from($data['delivery_mode'] ?? 'pickup'),
            'created_by' => Security::currentUserId(),
            'metadata' => $data['metadata'] ?? []
        ]);

        $transfer = $this->transferRepo->create($transfer);
        
        // Fire domain event
        $this->eventDispatcher->dispatch(new TransferCreated($transfer));
        
        // Log the action
        Log::audit($this->pdo, [
            'entity_type' => 'transfer',
            'entity_pk' => $transfer->getId(),
            'action' => 'CREATE',
            'status' => 'success'
        ]);

        return $transfer;
    }

    /**
     * Add item to transfer with business logic
     */
    public function addItemToTransfer(int $transferId, int $productId, int $quantity): TransferItem
    {
        $transfer = $this->getTransferById($transferId);
        
        // Business rule: Only draft/partial transfers can be modified
        if (!$transfer->getState()->canAddItems()) {
            throw new \DomainException("Cannot add items to transfer in state: {$transfer->getState()->value}");
        }

        // Check if item already exists
        $existingItem = $this->itemRepo->findByTransferAndProduct($transferId, $productId);
        
        if ($existingItem) {
            // Update existing item quantity
            $existingItem->addQuantity($quantity);
            return $this->itemRepo->update($existingItem);
        }

        // Create new item
        $item = new TransferItem([
            'transfer_id' => $transferId,
            'product_id' => $productId,
            'qty_requested' => $quantity,
            'unit_cost' => $this->getProductCost($productId, $transfer->getOutletFrom())
        ]);

        return $this->itemRepo->create($item);
    }

    /**
     * Submit transfer for processing (complex business logic)
     */
    public function submitTransfer(int $transferId, array $submissionData): Transfer
    {
        $transfer = $this->getTransferById($transferId);
        
        // Validate submission
        $this->validateTransferSubmission($transfer, $submissionData);
        
        // Calculate freight if needed
        if ($transfer->requiresFreightCalculation()) {
            $freightCost = $this->freight->calculateCost($transfer);
            $transfer->setFreightCost($freightCost);
        }

        // Update transfer state and metadata
        $transfer->setState(TransferState::SUBMITTED);
        $transfer->setSubmittedAt(new \DateTimeImmutable());
        $transfer->setSubmittedBy(Security::currentUserId());
        
        // Handle different submission modes
        match ($transfer->getKind()) {
            'STAFF' => $this->handleStaffTransferSubmission($transfer, $submissionData),
            'GENERAL' => $this->handleGeneralTransferSubmission($transfer, $submissionData),
            'JUICE' => $this->handleJuiceTransferSubmission($transfer, $submissionData),
            'SUPPLIER' => $this->handleSupplierTransferSubmission($transfer, $submissionData),
        };

        $transfer = $this->transferRepo->update($transfer);
        
        // Fire domain event
        $this->eventDispatcher->dispatch(new TransferSubmitted($transfer));
        
        return $transfer;
    }

    /**
     * Handle STAFF transfer optimization logic
     */
    private function handleStaffTransferSubmission(Transfer $transfer, array $data): void
    {
        // Complex STAFF transfer logic - multi-source fulfillment optimization
        $optimizer = new StaffTransferOptimizer(
            $this->transferRepo,
            $this->inventoryService,
            $this->freight
        );
        
        $optimizedPlan = $optimizer->optimize($transfer);
        $transfer->setOptimizationPlan($optimizedPlan);
        
        // Create sub-transfers if needed for multi-source fulfillment
        if ($optimizedPlan->requiresMultipleSources()) {
            $this->createOptimizedSubTransfers($transfer, $optimizedPlan);
        }
    }

    /**
     * Get transfer with full domain context
     */
    public function getTransferById(int $id): Transfer
    {
        $transfer = $this->transferRepo->findById($id);
        
        if (!$transfer) {
            throw new \DomainException("Transfer not found: {$id}");
        }
        
        return $transfer;
    }

    /**
     * Get transfers with filtering and pagination
     */
    public function getTransfers(array $filters = [], int $page = 1, int $limit = 20): array
    {
        return $this->transferRepo->findWithFilters($filters, $page, $limit);
    }

    /**
     * Business validation for transfer data
     */
    private function validateTransferData(array $data): void
    {
        if (empty($data['outlet_from']) || empty($data['outlet_to'])) {
            throw new \InvalidArgumentException('Source and destination outlets are required');
        }
        
        if ($data['outlet_from'] === $data['outlet_to']) {
            throw new \InvalidArgumentException('Source and destination outlets cannot be the same');
        }
        
        // Additional business rules...
    }

    /**
     * Validate transfer can be submitted
     */
    private function validateTransferSubmission(Transfer $transfer, array $data): void
    {
        if (!$transfer->getState()->canSubmit()) {
            throw new \DomainException("Cannot submit transfer in state: {$transfer->getState()->value}");
        }
        
        $items = $this->itemRepo->findByTransferId($transfer->getId());
        if (empty($items)) {
            throw new \DomainException('Cannot submit transfer with no items');
        }
        
        // Validate all items have positive quantities
        foreach ($items as $item) {
            if ($item->getQtyRequested() <= 0) {
                throw new \DomainException("Invalid quantity for item {$item->getProductId()}");
            }
        }
    }

    private function getProductCost(int $productId, string $outletId): float
    {
        // Implement cost lookup logic
        return 0.0;
    }
}