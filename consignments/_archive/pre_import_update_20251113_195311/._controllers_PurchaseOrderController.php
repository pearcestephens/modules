<?php
/**
 * Purchase Order Controller
 *
 * Handles all purchase order operations including:
 * - Creating and managing purchase orders
 * - Freight quote management
 * - Label generation
 * - Shipment tracking
 * - Receiving interface
 *
 * @package CIS\Consignments\Controllers
 * @version 1.0.0
 */

declare(strict_types=1);

namespace CIS\Consignments\Controllers;

use CIS\Consignments\Services\FreightService;
use PDO;

class PurchaseOrderController extends BaseController
{
    private FreightService $freightService;

    public function __construct(PDO $db)
    {
        parent::__construct($db);
        $this->freightService = new FreightService($db);
    }

    /**
     * Display list of purchase orders
     */
    public function index(): void
    {
        $this->render('purchase-orders/index', [
            'title' => 'Purchase Orders',
            'orders' => $this->getPurchaseOrders()
        ]);
    }

    /**
     * Show single purchase order details
     */
    public function view(int $poId): void
    {
        $po = $this->getPurchaseOrder($poId);

        if (!$po) {
            $this->error('Purchase order not found');
            return;
        }

        $this->render('purchase-orders/view', [
            'title' => 'Purchase Order #' . $poId,
            'po' => $po,
            'items' => $this->getPOItems($poId)
        ]);
    }

    /**
     * Show create purchase order form
     */
    public function create(): void
    {
        $this->render('purchase-orders/create', [
            'title' => 'Create Purchase Order',
            'suppliers' => $this->getSuppliers(),
            'outlets' => $this->getOutlets()
        ]);
    }

    /**
     * Get freight quote for a PO
     */
    public function getFreightQuote(int $poId): void
    {
        try {
            $quote = $this->freightService->getFreightQuote($poId);
            $this->json([
                'success' => true,
                'quote' => $quote
            ]);
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Generate freight label
     */
    public function createFreightLabel(int $poId): void
    {
        try {
            $carrier = $_POST['carrier'] ?? '';
            $service = $_POST['service'] ?? '';

            if (!$carrier || !$service) {
                throw new \InvalidArgumentException('Carrier and service are required');
            }

            $label = $this->freightService->createLabel($poId, $carrier, $service);

            $this->json([
                'success' => true,
                'label' => $label
            ]);
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Track shipment
     */
    public function trackShipment(int $poId): void
    {
        try {
            $tracking = $this->freightService->trackShipment($poId);
            $this->json([
                'success' => true,
                'tracking' => $tracking
            ]);
        } catch (\Exception $e) {
            $this->json([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Show receiving interface
     */
    public function receive(int $poId): void
    {
        $po = $this->getPurchaseOrder($poId);

        if (!$po) {
            $this->error('Purchase order not found');
            return;
        }

        $this->render('purchase-orders/receive', [
            'title' => 'Receive PO #' . $poId,
            'po' => $po,
            'items' => $this->getPOItems($poId)
        ]);
    }

    /**
     * Get all purchase orders
     */
    private function getPurchaseOrders(): array
    {
        $stmt = $this->db->prepare("
            SELECT
                po.*,
                s.name AS supplier_name,
                o.name AS outlet_name
            FROM vend_consignments po
            LEFT JOIN suppliers s ON po.supplier_id = s.id
            LEFT JOIN outlets o ON po.destination_outlet_id = o.id
            WHERE po.transfer_category = 'PURCHASE_ORDER'
                AND po.deleted_at IS NULL
            ORDER BY po.created_at DESC
            LIMIT 100
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get single purchase order
     */
    private function getPurchaseOrder(int $poId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT
                po.*,
                s.name AS supplier_name,
                s.email AS supplier_email,
                s.phone AS supplier_phone,
                o.name AS outlet_name,
                o.address AS outlet_address
            FROM vend_consignments po
            LEFT JOIN suppliers s ON po.supplier_id = s.id
            LEFT JOIN outlets o ON po.destination_outlet_id = o.id
            WHERE po.id = ?
                AND po.deleted_at IS NULL
        ");
        $stmt->execute([$poId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Get PO items
     */
    private function getPOItems(int $poId): array
    {
        $stmt = $this->db->prepare("
            SELECT
                li.*,
                p.name AS product_name,
                p.sku,
                p.supply_price,
                pd.weight AS product_weight,
                pd.length AS product_length,
                pd.width AS product_width,
                pd.height AS product_height
            FROM vend_consignment_line_items li
            LEFT JOIN vend_products p ON li.product_id = p.id
            LEFT JOIN product_dimensions pd ON p.id = pd.product_id
            WHERE li.transfer_id = ?
                AND li.deleted_at IS NULL
            ORDER BY p.name ASC
        ");
        $stmt->execute([$poId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get suppliers
     */
    private function getSuppliers(): array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, email, phone
            FROM suppliers
            WHERE deleted_at IS NULL
            ORDER BY name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get outlets
     */
    private function getOutlets(): array
    {
        $stmt = $this->db->prepare("
            SELECT id, name, address
            FROM outlets
            WHERE deleted_at IS NULL
            ORDER BY name ASC
        ");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
