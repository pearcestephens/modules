<?php
/**
 * Freight Label Generation & Display Page
 *
 * Display and print shipping labels:
 * - Show label details
 * - Print thermal label (4x6)
 * - Print standard A4
 * - Email label
 * - Regenerate if needed
 *
 * @package CIS\Consignments\PurchaseOrders
 * @version 1.0.0
 */

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';

use CIS\Consignments\Services\FreightService;
use CIS\Consignments\Services\PurchaseOrderService;

// Check authentication
if (!isset($_SESSION['userID'])) {
    header('Location: /login.php');
    exit;
}

// Get parameters
$poId = filter_input(INPUT_GET, 'po_id', FILTER_VALIDATE_INT);
$labelId = filter_input(INPUT_GET, 'label_id', FILTER_VALIDATE_INT);

if (!$poId) {
    header('Location: /modules/consignments/purchase-orders/');
    exit;
}

// Initialize services
$poService = new PurchaseOrderService($db);
$freightService = new FreightService($db);

// Load PO
$po = $poService->getById($poId);
if (!$po) {
    $_SESSION['error'] = 'Purchase Order not found';
    header('Location: /modules/consignments/purchase-orders/');
    exit;
}

// Load label details
$label = null;
if ($labelId) {
    $stmt = $db->prepare("
        SELECT
            cp.*,
            cs.carrier,
            cs.service_type,
            cs.tracking_url,
            cs.delivery_eta
        FROM consignment_parcels cp
        LEFT JOIN consignment_shipments cs ON cp.shipment_id = cs.id
        WHERE cp.id = ? AND cp.consignment_id = ?
    ");
    $stmt->execute([$labelId, $poId]);
    $label = $stmt->fetch(PDO::FETCH_ASSOC);
}

// If no label yet, redirect to quote page
if (!$label) {
    $_SESSION['error'] = 'Shipping label not found. Please select a freight quote first.';
    header("Location: /modules/consignments/purchase-orders/freight-quote.php?po_id={$poId}");
    exit;
}

// Page title
$pageTitle = "Shipping Label - PO #{$po['consignment_number']}";

// Include header
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/purchase-orders/views/header.php';
?>

<div class="freight-label-container">

    <!-- Header -->
    <div class="freight-label-header">
        <div class="header-left">
            <h1 class="freight-label-title">
                <i class="fas fa-tag"></i>
                Shipping Label Created
            </h1>
            <div class="freight-label-subtitle">
                Purchase Order #<?= htmlspecialchars($po['consignment_number']) ?>
            </div>
        </div>
        <div class="header-right">
            <a href="/modules/consignments/purchase-orders/view.php?id=<?= $poId ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to PO
            </a>
        </div>
    </div>

    <!-- Success Message -->
    <div class="success-message">
        <i class="fas fa-check-circle"></i>
        <div class="success-content">
            <strong>Label Created Successfully!</strong>
            <p>Your shipping label has been generated and is ready to print.</p>
        </div>
    </div>

    <!-- Label Actions -->
    <div class="label-actions-bar">
        <button id="print-thermal-btn" class="btn btn-primary">
            <i class="fas fa-print"></i> Print Thermal (4x6)
        </button>
        <button id="print-a4-btn" class="btn btn-secondary">
            <i class="fas fa-print"></i> Print A4
        </button>
        <button id="email-label-btn" class="btn btn-info">
            <i class="fas fa-envelope"></i> Email Label
        </button>
        <button id="download-pdf-btn" class="btn btn-success">
            <i class="fas fa-download"></i> Download PDF
        </button>
        <button id="regenerate-label-btn" class="btn btn-warning">
            <i class="fas fa-sync"></i> Regenerate Label
        </button>
    </div>

    <!-- Label Preview -->
    <div class="label-preview-section">
        <h2 class="section-title">Label Preview</h2>

        <div class="label-preview-container">
            <!-- Thermal Label Preview (4x6 inches) -->
            <div id="thermal-label-preview" class="thermal-label">

                <!-- Company Header -->
                <div class="label-header">
                    <img src="/assets/images/logo.png" alt="Company Logo" class="label-logo">
                    <div class="label-company-name">The Vape Shed</div>
                </div>

                <!-- From Address -->
                <div class="label-from">
                    <div class="label-from-title">FROM:</div>
                    <div><?= htmlspecialchars($po['supplier_name'] ?? 'Supplier') ?></div>
                    <?php if (!empty($po['supplier_address'])): ?>
                    <div><?= nl2br(htmlspecialchars($po['supplier_address'])) ?></div>
                    <?php endif; ?>
                </div>

                <!-- To Address -->
                <div class="label-to">
                    <div class="label-to-title">DELIVER TO:</div>
                    <div class="label-to-address">
                        <strong><?= htmlspecialchars($po['outlet_name'] ?? 'Outlet') ?></strong><br>
                        <?php if (!empty($po['outlet_address'])): ?>
                        <?= nl2br(htmlspecialchars($po['outlet_address'])) ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Barcode -->
                <?php if (!empty($label['barcode'])): ?>
                <div class="label-barcode">
                    <img src="/api/barcode/generate.php?code=<?= urlencode($label['barcode']) ?>&type=code128"
                         alt="Barcode">
                </div>
                <?php endif; ?>

                <!-- Tracking Number -->
                <?php if (!empty($label['tracking_number'])): ?>
                <div class="label-tracking">
                    <?= htmlspecialchars($label['tracking_number']) ?>
                </div>
                <?php endif; ?>

                <!-- Carrier Information -->
                <div class="label-carrier-info">
                    <div class="label-carrier">
                        <strong>Carrier:</strong> <?= htmlspecialchars($label['carrier'] ?? 'N/A') ?>
                    </div>
                    <div class="label-service">
                        <strong>Service:</strong> <?= htmlspecialchars($label['service_type'] ?? 'N/A') ?>
                    </div>
                </div>

                <div class="label-carrier-info">
                    <div class="label-weight">
                        <strong>Weight:</strong> <?= number_format($label['weight'], 2) ?> kg
                    </div>
                    <div class="label-date">
                        <strong>Date:</strong> <?= date('d/m/Y') ?>
                    </div>
                </div>

                <!-- Footer -->
                <div class="label-footer">
                    PO #<?= htmlspecialchars($po['consignment_number']) ?> |
                    Label #<?= $label['id'] ?>
                </div>
            </div>

            <!-- Label Information Sidebar -->
            <div class="label-info-sidebar">
                <h3>Label Details</h3>

                <div class="info-group">
                    <div class="info-label">Label ID:</div>
                    <div class="info-value">#<?= $label['id'] ?></div>
                </div>

                <div class="info-group">
                    <div class="info-label">Carrier:</div>
                    <div class="info-value"><?= htmlspecialchars($label['carrier'] ?? 'N/A') ?></div>
                </div>

                <div class="info-group">
                    <div class="info-label">Service Type:</div>
                    <div class="info-value"><?= htmlspecialchars($label['service_type'] ?? 'N/A') ?></div>
                </div>

                <div class="info-group">
                    <div class="info-label">Tracking Number:</div>
                    <div class="info-value">
                        <?php if (!empty($label['tracking_number'])): ?>
                        <a href="<?= htmlspecialchars($label['tracking_url'] ?? '#') ?>"
                           target="_blank" class="tracking-link">
                            <?= htmlspecialchars($label['tracking_number']) ?>
                            <i class="fas fa-external-link-alt"></i>
                        </a>
                        <?php else: ?>
                        <span class="text-muted">Not available</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="info-group">
                    <div class="info-label">Weight:</div>
                    <div class="info-value"><?= number_format($label['weight'], 2) ?> kg</div>
                </div>

                <?php if (!empty($label['dimensions'])): ?>
                <?php $dims = json_decode($label['dimensions'], true); ?>
                <div class="info-group">
                    <div class="info-label">Dimensions:</div>
                    <div class="info-value">
                        <?= $dims['length'] ?? 0 ?> ×
                        <?= $dims['width'] ?? 0 ?> ×
                        <?= $dims['height'] ?? 0 ?> cm
                    </div>
                </div>
                <?php endif; ?>

                <?php if (!empty($label['delivery_eta'])): ?>
                <div class="info-group">
                    <div class="info-label">Est. Delivery:</div>
                    <div class="info-value">
                        <?= date('d M Y', strtotime($label['delivery_eta'])) ?>
                    </div>
                </div>
                <?php endif; ?>

                <div class="info-group">
                    <div class="info-label">Created:</div>
                    <div class="info-value">
                        <?= date('d M Y H:i', strtotime($label['created_at'])) ?>
                    </div>
                </div>

                <div class="info-group">
                    <div class="info-label">Status:</div>
                    <div class="info-value">
                        <span class="status-badge status-<?= strtolower($label['status'] ?? 'pending') ?>">
                            <?= htmlspecialchars($label['status'] ?? 'Pending') ?>
                        </span>
                    </div>
                </div>

                <!-- QR Code for Mobile Tracking -->
                <div class="qr-code-section">
                    <h4>Mobile Tracking</h4>
                    <div class="qr-code">
                        <?php if (!empty($label['tracking_url'])): ?>
                        <img src="/api/qr/generate.php?url=<?= urlencode($label['tracking_url']) ?>"
                             alt="QR Code">
                        <p class="qr-code-text">Scan to track</p>
                        <?php else: ?>
                        <p class="text-muted">Not available</p>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Shipment Timeline -->
    <div class="shipment-timeline-section">
        <h2 class="section-title">
            <i class="fas fa-history"></i> Shipment Timeline
        </h2>

        <div class="timeline">
            <?php
            // Load shipment events
            $stmt = $db->prepare("
                SELECT * FROM consignment_unified_log
                WHERE consignment_id = ?
                AND event_type IN ('label_created', 'label_printed', 'shipment_dispatched', 'shipment_delivered')
                ORDER BY created_at DESC
                LIMIT 10
            ");
            $stmt->execute([$poId]);
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($events)):
            ?>
            <div class="timeline-empty">
                <i class="fas fa-info-circle"></i>
                <p>No shipment events yet</p>
            </div>
            <?php else: ?>

            <?php foreach ($events as $event): ?>
            <div class="timeline-item">
                <div class="timeline-marker bg-<?= $event['severity'] ?? 'info' ?>">
                    <i class="fas fa-<?= getEventIcon($event['event_type']) ?>"></i>
                </div>
                <div class="timeline-content">
                    <div class="timeline-header">
                        <div class="timeline-action"><?= htmlspecialchars($event['event_type']) ?></div>
                        <div class="timeline-date">
                            <?= date('d M Y H:i', strtotime($event['created_at'])) ?>
                        </div>
                    </div>
                    <?php if (!empty($event['message'])): ?>
                    <div class="timeline-message">
                        <?= htmlspecialchars($event['message']) ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

            <?php endif; ?>
        </div>
    </div>

</div>

<!-- Email Label Modal -->
<div id="email-label-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Email Shipping Label</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="email-label-form">
                <input type="hidden" name="label_id" value="<?= $label['id'] ?>">
                <input type="hidden" name="po_id" value="<?= $poId ?>">

                <div class="form-group">
                    <label>Recipient Email <span class="required">*</span></label>
                    <input type="email" name="email" class="form-control"
                           placeholder="email@example.com" required>
                </div>

                <div class="form-group">
                    <label>Message (Optional)</label>
                    <textarea name="message" class="form-control" rows="3"
                              placeholder="Add a message..."></textarea>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="include_instructions" value="1" checked>
                        Include shipping instructions
                    </label>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-paper-plane"></i> Send Email
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Regenerate Confirmation Modal -->
<div id="regenerate-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Regenerate Label?</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <p><strong>Warning:</strong> This will void the current label and create a new one.</p>
            <p>The current tracking number will be cancelled if possible.</p>
            <p>Are you sure you want to continue?</p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary modal-close">Cancel</button>
            <button type="button" id="confirm-regenerate-btn" class="btn btn-danger">
                <i class="fas fa-sync"></i> Yes, Regenerate Label
            </button>
        </div>
    </div>
</div>

<style>
/* Include freight-label.css or add inline styles */
.freight-label-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
}

.success-message {
    background: #d4edda;
    border: 1px solid #c3e6cb;
    color: #155724;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    display: flex;
    align-items: center;
    gap: 15px;
}

.success-message i {
    font-size: 36px;
}

.label-actions-bar {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
    margin-bottom: 20px;
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
}

.label-preview-container {
    display: grid;
    grid-template-columns: 1fr 350px;
    gap: 20px;
}

.thermal-label {
    background: white;
    width: 4in;
    height: 6in;
    padding: 0.25in;
    border: 2px solid #000;
    box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    margin: 0 auto;
}

.label-info-sidebar {
    background: white;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

@media print {
    body * {
        visibility: hidden;
    }

    #thermal-label-preview,
    #thermal-label-preview * {
        visibility: visible;
    }

    #thermal-label-preview {
        position: absolute;
        left: 0;
        top: 0;
    }
}
</style>

<script>
const FreightLabel = {
    labelId: <?= $label['id'] ?>,
    poId: <?= $poId ?>,

    init() {
        this.bindEvents();
    },

    bindEvents() {
        document.getElementById('print-thermal-btn')?.addEventListener('click', () => {
            this.printThermal();
        });

        document.getElementById('print-a4-btn')?.addEventListener('click', () => {
            this.printA4();
        });

        document.getElementById('email-label-btn')?.addEventListener('click', () => {
            this.showEmailModal();
        });

        document.getElementById('download-pdf-btn')?.addEventListener('click', () => {
            this.downloadPDF();
        });

        document.getElementById('regenerate-label-btn')?.addEventListener('click', () => {
            this.showRegenerateModal();
        });

        document.getElementById('email-label-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.sendEmail();
        });

        document.getElementById('confirm-regenerate-btn')?.addEventListener('click', () => {
            this.regenerateLabel();
        });

        // Modal close buttons
        document.querySelectorAll('.modal-close').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.currentTarget.closest('.modal').style.display = 'none';
            });
        });
    },

    printThermal() {
        window.print();
    },

    printA4() {
        window.open(`/modules/consignments/purchase-orders/print-label.php?label_id=${this.labelId}&format=a4`, '_blank');
    },

    showEmailModal() {
        document.getElementById('email-label-modal').style.display = 'flex';
    },

    async sendEmail() {
        const form = document.getElementById('email-label-form');
        const formData = new FormData(form);

        try {
            const response = await fetch('/modules/consignments/api/purchase-orders/email-label.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                alert('Label emailed successfully!');
                document.getElementById('email-label-modal').style.display = 'none';
            } else {
                alert('Error: ' + result.error);
            }
        } catch (error) {
            console.error('Email error:', error);
            alert('Failed to send email');
        }
    },

    downloadPDF() {
        window.location.href = `/modules/consignments/api/purchase-orders/download-label.php?label_id=${this.labelId}`;
    },

    showRegenerateModal() {
        document.getElementById('regenerate-modal').style.display = 'flex';
    },

    async regenerateLabel() {
        try {
            const response = await fetch('/modules/consignments/api/purchase-orders/regenerate-label.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    label_id: this.labelId,
                    po_id: this.poId
                })
            });

            const result = await response.json();

            if (result.success) {
                window.location.reload();
            } else {
                alert('Error: ' + result.error);
            }
        } catch (error) {
            console.error('Regenerate error:', error);
            alert('Failed to regenerate label');
        }
    }
};

document.addEventListener('DOMContentLoaded', () => {
    FreightLabel.init();
});
</script>

<?php
function getEventIcon($eventType) {
    $icons = [
        'label_created' => 'tag',
        'label_printed' => 'print',
        'shipment_dispatched' => 'shipping-fast',
        'shipment_delivered' => 'check-circle',
    ];
    return $icons[$eventType] ?? 'info-circle';
}

// Include footer
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/purchase-orders/views/footer.php';
?>
