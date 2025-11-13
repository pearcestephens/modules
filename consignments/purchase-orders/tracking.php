<?php
/**
 * Shipment Tracking Dashboard
 *
 * Real-time tracking for purchase order shipments:
 * - Track multiple shipments
 * - View carrier updates
 * - Display delivery progress
 * - Show transit events
 * - Manage exceptions
 *
 * @package CIS\Consignments\PurchaseOrders
 * @version 1.0.0
 */

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';

use CIS\Services\Consignments\Integration\FreightService;
use CIS\Services\Consignments\Core\PurchaseOrderService;

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// Get PO ID if provided (single PO view)
$poId = filter_input(INPUT_GET, 'po_id', FILTER_VALIDATE_INT);

// Initialize services
$poService = new PurchaseOrderService($db);
$freightService = new FreightService($db);

// Page title
$pageTitle = $poId ? "Track Shipment - PO #{$poId}" : "Shipment Tracking Dashboard";

// Load tracking data
if ($poId) {
    // Single PO tracking
    $po = $poService->getById($poId);
    if (!$po) {
        $_SESSION['error'] = 'Purchase Order not found';
        header('Location: /modules/consignments/purchase-orders/');
        exit;
    }

    $trackingData = $freightService->trackShipment($poId);
    $shipments = [$trackingData];
} else {
    // All active shipments
    $stmt = $db->query("
        SELECT DISTINCT
            vc.id,
            vc.consignment_number,
            cs.tracking_number,
            cs.carrier,
            cs.status,
            cs.delivery_eta,
            cp.weight,
            vo_from.name as origin_outlet,
            vo_to.name as destination_outlet
        FROM vend_consignments vc
        INNER JOIN consignment_shipments cs ON vc.id = cs.consignment_id
        LEFT JOIN consignment_parcels cp ON cs.id = cp.shipment_id
        LEFT JOIN vend_outlets vo_from ON vc.source_outlet_id = vo_from.id
        LEFT JOIN vend_outlets vo_to ON vc.destination_outlet_id = vo_to.id
        WHERE vc.transfer_category = 'PURCHASE_ORDER'
        AND cs.status IN ('in_transit', 'dispatched', 'out_for_delivery')
        ORDER BY vc.created_at DESC
        LIMIT 50
    ");
    $shipments = $stmt->fetchAll(PDO::FETCH_ASSOC);
}

// Include header
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/purchase-orders/views/header.php';
?>

<div class="tracking-dashboard-container">

    <!-- Header -->
    <div class="tracking-dashboard-header">
        <div class="header-left">
            <h1 class="tracking-dashboard-title">
                <i class="fas fa-map-marker-alt"></i>
                <?= $poId ? "Track Shipment" : "Shipment Tracking Dashboard" ?>
            </h1>
            <?php if ($poId): ?>
            <div class="tracking-dashboard-subtitle">
                Purchase Order #<?= htmlspecialchars($po['consignment_number']) ?>
            </div>
            <?php endif; ?>
        </div>
        <div class="header-right">
            <?php if ($poId): ?>
            <a href="/modules/consignments/purchase-orders/view.php?id=<?= $poId ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to PO
            </a>
            <?php else: ?>
            <button id="refresh-all-btn" class="btn btn-primary">
                <i class="fas fa-sync"></i> Refresh All
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($poId && !empty($trackingData)): ?>
    <!-- Single Shipment Detailed View -->
    <div class="shipment-detailed-view">

        <!-- Status Summary -->
        <div class="status-summary-card">
            <div class="status-icon status-<?= strtolower($trackingData['status']) ?>">
                <i class="fas fa-<?= getStatusIcon($trackingData['status']) ?>"></i>
            </div>
            <div class="status-content">
                <div class="status-label">Current Status</div>
                <div class="status-value"><?= htmlspecialchars($trackingData['status_text']) ?></div>
                <?php if (!empty($trackingData['last_update'])): ?>
                <div class="status-date">
                    Last updated: <?= date('d M Y H:i', strtotime($trackingData['last_update'])) ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Progress Bar -->
        <div class="delivery-progress-section">
            <h2 class="section-title">Delivery Progress</h2>

            <div class="progress-tracker">
                <?php
                $stages = [
                    'label_created' => 'Label Created',
                    'dispatched' => 'Dispatched',
                    'in_transit' => 'In Transit',
                    'out_for_delivery' => 'Out for Delivery',
                    'delivered' => 'Delivered'
                ];

                $currentStage = $trackingData['status'] ?? 'label_created';
                $stageKeys = array_keys($stages);
                $currentIndex = array_search($currentStage, $stageKeys);
                ?>

                <?php foreach ($stages as $key => $label): ?>
                <?php
                $index = array_search($key, $stageKeys);
                $isComplete = $index <= $currentIndex;
                $isCurrent = $index === $currentIndex;
                ?>
                <div class="progress-stage <?= $isComplete ? 'complete' : '' ?> <?= $isCurrent ? 'current' : '' ?>">
                    <div class="stage-marker">
                        <?php if ($isComplete): ?>
                        <i class="fas fa-check"></i>
                        <?php else: ?>
                        <span><?= $index + 1 ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="stage-label"><?= $label ?></div>
                    <?php if ($isCurrent && !empty($trackingData['estimated_delivery'])): ?>
                    <div class="stage-eta">
                        ETA: <?= date('d M', strtotime($trackingData['estimated_delivery'])) ?>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if ($index < count($stages) - 1): ?>
                <div class="stage-connector <?= $isComplete ? 'complete' : '' ?>"></div>
                <?php endif; ?>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Shipment Details Grid -->
        <div class="shipment-details-grid">
            <div class="detail-card">
                <div class="detail-icon">
                    <i class="fas fa-truck"></i>
                </div>
                <div class="detail-content">
                    <div class="detail-label">Carrier</div>
                    <div class="detail-value"><?= htmlspecialchars($trackingData['carrier']) ?></div>
                </div>
            </div>

            <div class="detail-card">
                <div class="detail-icon">
                    <i class="fas fa-barcode"></i>
                </div>
                <div class="detail-content">
                    <div class="detail-label">Tracking Number</div>
                    <div class="detail-value">
                        <a href="<?= htmlspecialchars($trackingData['tracking_url']) ?>"
                           target="_blank" class="tracking-link">
                            <?= htmlspecialchars($trackingData['tracking_number']) ?>
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                    </div>
                </div>
            </div>

            <div class="detail-card">
                <div class="detail-icon">
                    <i class="fas fa-weight"></i>
                </div>
                <div class="detail-content">
                    <div class="detail-label">Weight</div>
                    <div class="detail-value"><?= number_format($trackingData['weight'], 2) ?> kg</div>
                </div>
            </div>

            <div class="detail-card">
                <div class="detail-icon">
                    <i class="fas fa-calendar"></i>
                </div>
                <div class="detail-content">
                    <div class="detail-label">Estimated Delivery</div>
                    <div class="detail-value">
                        <?= !empty($trackingData['estimated_delivery'])
                            ? date('d M Y', strtotime($trackingData['estimated_delivery']))
                            : 'TBA' ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transit Events Timeline -->
        <div class="transit-events-section">
            <h2 class="section-title">
                <i class="fas fa-history"></i> Transit Events
            </h2>

            <?php if (!empty($trackingData['events'])): ?>
            <div class="events-timeline">
                <?php foreach ($trackingData['events'] as $event): ?>
                <div class="event-item">
                    <div class="event-marker">
                        <i class="fas fa-circle"></i>
                    </div>
                    <div class="event-content">
                        <div class="event-header">
                            <div class="event-description">
                                <?= htmlspecialchars($event['description']) ?>
                            </div>
                            <div class="event-date">
                                <?= date('d M Y H:i', strtotime($event['timestamp'])) ?>
                            </div>
                        </div>
                        <?php if (!empty($event['location'])): ?>
                        <div class="event-location">
                            <i class="fas fa-map-marker-alt"></i>
                            <?= htmlspecialchars($event['location']) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="no-events-message">
                <i class="fas fa-info-circle"></i>
                <p>No tracking events available yet</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Actions -->
        <div class="tracking-actions">
            <button id="refresh-tracking-btn" class="btn btn-primary">
                <i class="fas fa-sync"></i> Refresh Tracking
            </button>
            <button id="print-tracking-btn" class="btn btn-secondary">
                <i class="fas fa-print"></i> Print
            </button>
            <button id="email-tracking-btn" class="btn btn-info">
                <i class="fas fa-envelope"></i> Email Updates
            </button>
        </div>
    </div>

    <?php else: ?>
    <!-- Multiple Shipments List View -->
    <div class="shipments-list-view">

        <!-- Filters -->
        <div class="tracking-filters">
            <div class="filter-group">
                <label>Status</label>
                <select id="status-filter" class="form-control">
                    <option value="">All Statuses</option>
                    <option value="dispatched">Dispatched</option>
                    <option value="in_transit">In Transit</option>
                    <option value="out_for_delivery">Out for Delivery</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Carrier</label>
                <select id="carrier-filter" class="form-control">
                    <option value="">All Carriers</option>
                    <option value="NZ Post">NZ Post</option>
                    <option value="CourierPost">CourierPost</option>
                    <option value="Aramex">Aramex</option>
                    <option value="DHL">DHL</option>
                </select>
            </div>

            <div class="filter-group">
                <label>Search</label>
                <input type="text" id="search-tracking" class="form-control"
                       placeholder="PO number, tracking number...">
            </div>
        </div>

        <!-- Shipments Table -->
        <div class="shipments-table-wrapper">
            <table id="shipments-table" class="shipments-table">
                <thead>
                    <tr>
                        <th>PO Number</th>
                        <th>Tracking Number</th>
                        <th>Carrier</th>
                        <th>Origin</th>
                        <th>Destination</th>
                        <th>Status</th>
                        <th>ETA</th>
                        <th>Weight</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($shipments)): ?>
                    <tr>
                        <td colspan="9" class="no-data">
                            <i class="fas fa-info-circle"></i>
                            No active shipments found
                        </td>
                    </tr>
                    <?php else: ?>
                    <?php foreach ($shipments as $shipment): ?>
                    <tr data-po-id="<?= $shipment['id'] ?>">
                        <td>
                            <a href="/modules/consignments/purchase-orders/view.php?id=<?= $shipment['id'] ?>">
                                #<?= htmlspecialchars($shipment['consignment_number']) ?>
                            </a>
                        </td>
                        <td>
                            <a href="<?= getTrackingUrl($shipment['carrier'], $shipment['tracking_number']) ?>"
                               target="_blank" class="tracking-link">
                                <?= htmlspecialchars($shipment['tracking_number']) ?>
                                <i class="fas fa-external-link-alt"></i>
                            </a>
                        </td>
                        <td><?= htmlspecialchars($shipment['carrier']) ?></td>
                        <td><?= htmlspecialchars($shipment['origin_outlet'] ?? 'N/A') ?></td>
                        <td><?= htmlspecialchars($shipment['destination_outlet'] ?? 'N/A') ?></td>
                        <td>
                            <span class="status-badge status-<?= strtolower($shipment['status']) ?>">
                                <?= htmlspecialchars($shipment['status']) ?>
                            </span>
                        </td>
                        <td>
                            <?= !empty($shipment['delivery_eta'])
                                ? date('d M Y', strtotime($shipment['delivery_eta']))
                                : 'TBA' ?>
                        </td>
                        <td><?= number_format($shipment['weight'], 2) ?> kg</td>
                        <td>
                            <div class="action-buttons">
                                <a href="/modules/consignments/purchase-orders/tracking.php?po_id=<?= $shipment['id'] ?>"
                                   class="btn btn-sm btn-primary" title="View Details">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <button class="btn btn-sm btn-info refresh-shipment-btn"
                                        data-po-id="<?= $shipment['id'] ?>" title="Refresh">
                                    <i class="fas fa-sync"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php endif; ?>

</div>

<!-- Email Tracking Modal -->
<div id="email-tracking-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Email Tracking Updates</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="email-tracking-form">
                <input type="hidden" name="po_id" value="<?= $poId ?>">

                <div class="form-group">
                    <label>Recipient Email <span class="required">*</span></label>
                    <input type="email" name="email" class="form-control"
                           placeholder="email@example.com" required>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="auto_updates" value="1">
                        Subscribe to automatic updates
                    </label>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Send
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
/* Tracking Dashboard Styles */
.tracking-dashboard-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.status-summary-card {
    background: white;
    padding: 30px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 20px;
}

.status-icon {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 36px;
    color: white;
}

.status-icon.status-delivered {
    background: #28a745;
}

.status-icon.status-in_transit {
    background: #17a2b8;
}

.status-icon.status-dispatched {
    background: #ffc107;
}

.progress-tracker {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 40px 20px;
}

.progress-stage {
    display: flex;
    flex-direction: column;
    align-items: center;
    position: relative;
    flex: 1;
}

.stage-marker {
    width: 50px;
    height: 50px;
    border-radius: 50%;
    background: #e9ecef;
    border: 3px solid #dee2e6;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: bold;
    margin-bottom: 10px;
    transition: all 0.3s ease;
}

.progress-stage.complete .stage-marker {
    background: #28a745;
    border-color: #28a745;
    color: white;
}

.progress-stage.current .stage-marker {
    background: #17a2b8;
    border-color: #17a2b8;
    color: white;
    box-shadow: 0 0 0 4px rgba(23,162,184,0.2);
}

.stage-connector {
    flex: 1;
    height: 3px;
    background: #dee2e6;
    margin: 0 10px;
    margin-bottom: 40px;
}

.stage-connector.complete {
    background: #28a745;
}

.events-timeline {
    position: relative;
    padding-left: 30px;
}

.events-timeline::before {
    content: '';
    position: absolute;
    left: 8px;
    top: 0;
    bottom: 0;
    width: 2px;
    background: #dee2e6;
}

.event-item {
    position: relative;
    margin-bottom: 20px;
}

.event-marker {
    position: absolute;
    left: -26px;
    width: 16px;
    height: 16px;
    border-radius: 50%;
    background: #17a2b8;
    border: 3px solid white;
    box-shadow: 0 0 0 2px #dee2e6;
}

.shipments-table-wrapper {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    overflow-x: auto;
}

.shipments-table {
    width: 100%;
    border-collapse: collapse;
}

.shipments-table th {
    background: #f8f9fa;
    padding: 12px;
    text-align: left;
    font-weight: 600;
    border-bottom: 2px solid #dee2e6;
}

.shipments-table td {
    padding: 12px;
    border-bottom: 1px solid #f0f0f0;
}
</style>

<script>
const TrackingDashboard = {
    poId: <?= $poId ?? 'null' ?>,

    init() {
        this.bindEvents();
        <?php if (!$poId): ?>
        this.initDataTable();
        <?php endif; ?>
    },

    bindEvents() {
        document.getElementById('refresh-tracking-btn')?.addEventListener('click', () => {
            this.refreshTracking();
        });

        document.getElementById('refresh-all-btn')?.addEventListener('click', () => {
            this.refreshAll();
        });

        document.querySelectorAll('.refresh-shipment-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const poId = e.currentTarget.dataset.poId;
                this.refreshShipment(poId);
            });
        });

        document.getElementById('print-tracking-btn')?.addEventListener('click', () => {
            window.print();
        });

        document.getElementById('email-tracking-btn')?.addEventListener('click', () => {
            document.getElementById('email-tracking-modal').style.display = 'flex';
        });

        document.querySelectorAll('.modal-close').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.currentTarget.closest('.modal').style.display = 'none';
            });
        });
    },

    async refreshTracking() {
        try {
            const response = await fetch(`/modules/consignments/api/purchase-orders/track.php?po_id=${this.poId}&refresh=1`);
            const result = await response.json();

            if (result.success) {
                window.location.reload();
            } else {
                alert('Error: ' + result.error);
            }
        } catch (error) {
            console.error('Refresh error:', error);
            alert('Failed to refresh tracking');
        }
    },

    async refreshAll() {
        alert('Refreshing all shipments...');
        window.location.reload();
    },

    async refreshShipment(poId) {
        // Refresh single shipment in list
        console.log('Refreshing shipment:', poId);
    },

    initDataTable() {
        // Initialize DataTables if available
        if (typeof jQuery !== 'undefined' && jQuery.fn.DataTable) {
            jQuery('#shipments-table').DataTable({
                order: [[0, 'desc']],
                pageLength: 25
            });
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    TrackingDashboard.init();
});
</script>

<?php
function getStatusIcon($status) {
    $icons = [
        'delivered' => 'check-circle',
        'in_transit' => 'shipping-fast',
        'out_for_delivery' => 'truck',
        'dispatched' => 'box',
        'label_created' => 'tag',
    ];
    return $icons[$status] ?? 'question-circle';
}

function getTrackingUrl($carrier, $trackingNumber) {
    $urls = [
        'NZ Post' => "https://www.nzpost.co.nz/tools/tracking?trackid={$trackingNumber}",
        'CourierPost' => "https://www.courierpost.co.nz/tools/tracking?trackid={$trackingNumber}",
        'Aramex' => "https://www.aramex.co.nz/track?number={$trackingNumber}",
        'DHL' => "https://www.dhl.com/en/express/tracking.html?AWB={$trackingNumber}",
    ];
    return $urls[$carrier] ?? '#';
}

// Include footer
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/purchase-orders/views/footer.php';
?>
