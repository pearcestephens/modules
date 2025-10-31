<?php
/**
 * Freight Quote Comparison Page
 *
 * Multi-carrier rate comparison for purchase orders:
 * - Get quotes from all carriers
 * - Compare prices and delivery times
 * - Select best option
 * - Create shipping label
 *
 * @package CIS\Consignments\PurchaseOrders
 * @version 1.0.0
 */

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';

use CIS\Consignments\Services\FreightService;
use CIS\Consignments\Services\PurchaseOrderService;

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

// Get PO ID from URL
$poId = filter_input(INPUT_GET, 'po_id', FILTER_VALIDATE_INT);
if (!$poId) {
    header('Location: /modules/consignments/purchase-orders/');
    exit;
}

// Initialize services
$poService = new PurchaseOrderService($db);
$freightService = new FreightService($db);

// Load PO details
$po = $poService->getById($poId);
if (!$po) {
    $_SESSION['error'] = 'Purchase Order not found';
    header('Location: /modules/consignments/purchase-orders/');
    exit;
}

// Check if PO is in correct state (APPROVED or SENT)
if (!in_array($po['status'], ['APPROVED', 'SENT'])) {
    $_SESSION['error'] = 'PO must be approved before creating freight labels';
    header("Location: /modules/consignments/purchase-orders/view.php?id={$poId}");
    exit;
}

// Calculate freight metrics
$metrics = $freightService->calculateMetrics($poId, 'actual');

// Get freight quotes (with caching)
$quotes = $freightService->getQuotes($poId);

// Get container suggestions
$containers = $freightService->suggestContainers($poId, 'balanced');

// Get AI recommendations
$recommendation = $freightService->getRecommendation($poId, 'balanced');

// Page title
$pageTitle = "Freight Quote - PO #{$po['consignment_number']}";

// Include header
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/purchase-orders/views/header.php';
?>

<div class="freight-quote-container">

    <!-- Header -->
    <div class="freight-quote-header">
        <div class="header-left">
            <h1 class="freight-quote-title">
                <i class="fas fa-shipping-fast"></i>
                Freight Quote Comparison
            </h1>
            <div class="freight-quote-subtitle">
                Purchase Order #<?= htmlspecialchars($po['consignment_number']) ?>
            </div>
        </div>
        <div class="header-right">
            <a href="/modules/consignments/purchase-orders/view.php?id=<?= $poId ?>" class="btn btn-secondary">
                <i class="fas fa-arrow-left"></i> Back to PO
            </a>
        </div>
    </div>

    <!-- Shipment Details -->
    <div class="shipment-details-section">
        <h2 class="section-title">Shipment Details</h2>

        <div class="details-grid">
            <!-- Origin -->
            <div class="detail-card">
                <div class="detail-icon">
                    <i class="fas fa-warehouse"></i>
                </div>
                <div class="detail-content">
                    <div class="detail-label">Origin</div>
                    <div class="detail-value">
                        <?= htmlspecialchars($po['supplier_name'] ?? 'Unknown Supplier') ?><br>
                        <small><?= htmlspecialchars($po['supplier_address'] ?? '') ?></small>
                    </div>
                </div>
            </div>

            <!-- Destination -->
            <div class="detail-card">
                <div class="detail-icon">
                    <i class="fas fa-store"></i>
                </div>
                <div class="detail-content">
                    <div class="detail-label">Destination</div>
                    <div class="detail-value">
                        <?= htmlspecialchars($po['outlet_name'] ?? 'Unknown Outlet') ?><br>
                        <small><?= htmlspecialchars($po['outlet_address'] ?? '') ?></small>
                    </div>
                </div>
            </div>

            <!-- Weight -->
            <div class="detail-card">
                <div class="detail-icon">
                    <i class="fas fa-weight"></i>
                </div>
                <div class="detail-content">
                    <div class="detail-label">Total Weight</div>
                    <div class="detail-value">
                        <?= number_format($metrics['total_weight'], 2) ?> kg
                        <br>
                        <small>Volumetric: <?= number_format($metrics['volumetric_weight'], 2) ?> kg</small>
                    </div>
                </div>
            </div>

            <!-- Volume -->
            <div class="detail-card">
                <div class="detail-icon">
                    <i class="fas fa-cube"></i>
                </div>
                <div class="detail-content">
                    <div class="detail-label">Total Volume</div>
                    <div class="detail-value">
                        <?= number_format($metrics['total_volume'], 4) ?> m³
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- AI Recommendation -->
    <?php if ($recommendation): ?>
    <div class="ai-recommendation-section">
        <div class="ai-badge">
            <i class="fas fa-robot"></i> AI Recommendation
        </div>
        <div class="recommendation-content">
            <div class="recommendation-carrier">
                <strong><?= htmlspecialchars($recommendation['carrier']) ?></strong>
                <span class="recommendation-service"><?= htmlspecialchars($recommendation['service']) ?></span>
            </div>
            <div class="recommendation-reason">
                <?= htmlspecialchars($recommendation['reason']) ?>
            </div>
            <div class="recommendation-confidence">
                Confidence: <span class="confidence-bar" style="width: <?= $recommendation['confidence'] ?>%"></span>
                <?= $recommendation['confidence'] ?>%
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Container Suggestions -->
    <?php if (!empty($containers)): ?>
    <div class="container-suggestions-section">
        <h2 class="section-title">
            <i class="fas fa-box"></i> Suggested Containers
        </h2>
        <div class="container-grid">
            <?php foreach ($containers as $container): ?>
            <div class="container-card">
                <div class="container-type">
                    <?= htmlspecialchars($container['type']) ?>
                </div>
                <div class="container-dimensions">
                    <?= $container['dimensions']['length'] ?>cm ×
                    <?= $container['dimensions']['width'] ?>cm ×
                    <?= $container['dimensions']['height'] ?>cm
                </div>
                <div class="container-quantity">
                    Quantity: <strong><?= $container['quantity'] ?></strong>
                </div>
                <div class="container-utilization">
                    Utilization: <?= number_format($container['utilization'] * 100, 1) ?>%
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Freight Quotes -->
    <div class="freight-quotes-section">
        <h2 class="section-title">
            <i class="fas fa-dollar-sign"></i> Available Quotes
        </h2>

        <?php if (empty($quotes)): ?>
        <div class="no-quotes-message">
            <i class="fas fa-exclamation-triangle"></i>
            <p>No freight quotes available at this time.</p>
            <button id="refresh-quotes-btn" class="btn btn-primary">
                <i class="fas fa-sync"></i> Refresh Quotes
            </button>
        </div>
        <?php else: ?>

        <div class="quotes-grid">
            <?php
            $cheapest = min(array_column($quotes, 'price'));
            $fastest = min(array_column($quotes, 'estimated_days'));

            foreach ($quotes as $quote):
                $isCheapest = ($quote['price'] == $cheapest);
                $isFastest = ($quote['estimated_days'] == $fastest);
            ?>
            <div class="quote-card <?= $isCheapest ? 'cheapest' : '' ?> <?= $isFastest ? 'fastest' : '' ?>"
                 data-carrier="<?= htmlspecialchars($quote['carrier']) ?>"
                 data-service="<?= htmlspecialchars($quote['service']) ?>"
                 data-price="<?= $quote['price'] ?>">

                <!-- Badges -->
                <div class="quote-badges">
                    <?php if ($isCheapest): ?>
                    <span class="badge badge-success">
                        <i class="fas fa-dollar-sign"></i> Cheapest
                    </span>
                    <?php endif; ?>

                    <?php if ($isFastest): ?>
                    <span class="badge badge-info">
                        <i class="fas fa-tachometer-alt"></i> Fastest
                    </span>
                    <?php endif; ?>
                </div>

                <!-- Carrier Logo -->
                <div class="quote-carrier-logo">
                    <img src="/assets/images/carriers/<?= strtolower($quote['carrier']) ?>.png"
                         alt="<?= htmlspecialchars($quote['carrier']) ?>"
                         onerror="this.src='/assets/images/carriers/default.png'">
                </div>

                <!-- Carrier Name -->
                <div class="quote-carrier-name">
                    <?= htmlspecialchars($quote['carrier']) ?>
                </div>

                <!-- Service Type -->
                <div class="quote-service-type">
                    <?= htmlspecialchars($quote['service']) ?>
                </div>

                <!-- Price -->
                <div class="quote-price">
                    $<?= number_format($quote['price'], 2) ?>
                    <span class="price-gst">incl. GST</span>
                </div>

                <!-- Delivery Time -->
                <div class="quote-delivery-time">
                    <i class="fas fa-clock"></i>
                    <?= $quote['estimated_days'] ?> business day<?= $quote['estimated_days'] != 1 ? 's' : '' ?>
                </div>

                <!-- Features -->
                <?php if (!empty($quote['features'])): ?>
                <div class="quote-features">
                    <?php foreach ($quote['features'] as $feature): ?>
                    <div class="feature-item">
                        <i class="fas fa-check"></i> <?= htmlspecialchars($feature) ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Select Button -->
                <button class="btn btn-primary select-quote-btn"
                        data-carrier="<?= htmlspecialchars($quote['carrier']) ?>"
                        data-service="<?= htmlspecialchars($quote['service']) ?>"
                        data-price="<?= $quote['price'] ?>">
                    <i class="fas fa-check"></i> Select This Quote
                </button>

                <!-- View Details -->
                <button class="btn btn-link view-quote-details-btn"
                        data-quote-id="<?= $quote['id'] ?? '' ?>">
                    View Full Details
                </button>
            </div>
            <?php endforeach; ?>
        </div>

        <?php endif; ?>
    </div>

    <!-- Manual Entry Option -->
    <div class="manual-entry-section">
        <button id="manual-entry-btn" class="btn btn-secondary">
            <i class="fas fa-keyboard"></i> Enter Manual Freight Details
        </button>
    </div>

</div>

<!-- Quote Details Modal -->
<div id="quote-details-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Quote Details</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body" id="quote-details-content">
            <!-- Populated via JavaScript -->
        </div>
    </div>
</div>

<!-- Manual Entry Modal -->
<div id="manual-entry-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Manual Freight Entry</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <form id="manual-freight-form">
                <input type="hidden" name="po_id" value="<?= $poId ?>">

                <div class="form-group">
                    <label>Carrier <span class="required">*</span></label>
                    <select name="carrier" class="form-control" required>
                        <option value="">Select Carrier</option>
                        <option value="NZ Post">NZ Post</option>
                        <option value="CourierPost">CourierPost</option>
                        <option value="Aramex">Aramex</option>
                        <option value="DHL">DHL</option>
                        <option value="FedEx">FedEx</option>
                        <option value="Other">Other</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Service Type <span class="required">*</span></label>
                    <input type="text" name="service_type" class="form-control"
                           placeholder="e.g., Express, Standard" required>
                </div>

                <div class="form-group">
                    <label>Cost <span class="required">*</span></label>
                    <input type="number" name="cost" class="form-control"
                           step="0.01" min="0" placeholder="0.00" required>
                </div>

                <div class="form-group">
                    <label>Tracking Number</label>
                    <input type="text" name="tracking_number" class="form-control"
                           placeholder="Optional">
                </div>

                <div class="form-group">
                    <label>Estimated Delivery Days</label>
                    <input type="number" name="estimated_days" class="form-control"
                           min="1" value="3">
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary modal-close">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Save & Create Label
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Confirmation Modal -->
<div id="confirm-selection-modal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Confirm Freight Selection</h3>
            <button class="modal-close">&times;</button>
        </div>
        <div class="modal-body">
            <p>You are about to create a shipping label with:</p>
            <div class="confirmation-details">
                <div class="detail-row">
                    <strong>Carrier:</strong>
                    <span id="confirm-carrier"></span>
                </div>
                <div class="detail-row">
                    <strong>Service:</strong>
                    <span id="confirm-service"></span>
                </div>
                <div class="detail-row">
                    <strong>Cost:</strong>
                    <span id="confirm-price"></span>
                </div>
            </div>
            <p><strong>This action cannot be undone.</strong></p>
        </div>
        <div class="modal-footer">
            <button type="button" class="btn btn-secondary modal-close">Cancel</button>
            <button type="button" id="confirm-create-label-btn" class="btn btn-success">
                <i class="fas fa-check"></i> Confirm & Create Label
            </button>
        </div>
    </div>
</div>

<!-- Loading Overlay -->
<div id="loading-overlay" class="loading-overlay" style="display: none;">
    <div class="loading-spinner"></div>
    <div class="loading-text">Creating shipping label...</div>
</div>

<style>
/* Include freight-quote.css styles here or link to external file */
</style>

<script>
// Freight Quote JavaScript
const FreightQuote = {
    poId: <?= $poId ?>,
    selectedQuote: null,

    init() {
        this.bindEvents();
    },

    bindEvents() {
        // Select quote buttons
        document.querySelectorAll('.select-quote-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const carrier = e.currentTarget.dataset.carrier;
                const service = e.currentTarget.dataset.service;
                const price = e.currentTarget.dataset.price;
                this.selectQuote(carrier, service, price);
            });
        });

        // View details buttons
        document.querySelectorAll('.view-quote-details-btn').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const quoteId = e.currentTarget.dataset.quoteId;
                this.viewQuoteDetails(quoteId);
            });
        });

        // Manual entry
        document.getElementById('manual-entry-btn')?.addEventListener('click', () => {
            this.showManualEntry();
        });

        // Manual entry form
        document.getElementById('manual-freight-form')?.addEventListener('submit', (e) => {
            e.preventDefault();
            this.submitManualEntry();
        });

        // Refresh quotes
        document.getElementById('refresh-quotes-btn')?.addEventListener('click', () => {
            this.refreshQuotes();
        });

        // Confirm create label
        document.getElementById('confirm-create-label-btn')?.addEventListener('click', () => {
            this.createLabel();
        });

        // Modal close buttons
        document.querySelectorAll('.modal-close').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.currentTarget.closest('.modal').style.display = 'none';
            });
        });
    },

    selectQuote(carrier, service, price) {
        this.selectedQuote = { carrier, service, price };

        // Show confirmation modal
        document.getElementById('confirm-carrier').textContent = carrier;
        document.getElementById('confirm-service').textContent = service;
        document.getElementById('confirm-price').textContent = `$${parseFloat(price).toFixed(2)}`;
        document.getElementById('confirm-selection-modal').style.display = 'flex';
    },

    async createLabel() {
        if (!this.selectedQuote) return;

        // Show loading
        document.getElementById('loading-overlay').style.display = 'flex';
        document.getElementById('confirm-selection-modal').style.display = 'none';

        try {
            const response = await fetch('/modules/consignments/api/purchase-orders/create-label.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    po_id: this.poId,
                    carrier: this.selectedQuote.carrier,
                    service: this.selectedQuote.service,
                    price: this.selectedQuote.price
                })
            });

            const result = await response.json();

            if (result.success) {
                // Redirect to label page
                window.location.href = `/modules/consignments/purchase-orders/freight-label.php?po_id=${this.poId}&label_id=${result.label_id}`;
            } else {
                alert('Error: ' + result.error);
                document.getElementById('loading-overlay').style.display = 'none';
            }
        } catch (error) {
            console.error('Create label error:', error);
            alert('Failed to create shipping label');
            document.getElementById('loading-overlay').style.display = 'none';
        }
    },

    showManualEntry() {
        document.getElementById('manual-entry-modal').style.display = 'flex';
    },

    async submitManualEntry() {
        const form = document.getElementById('manual-freight-form');
        const formData = new FormData(form);

        try {
            const response = await fetch('/modules/consignments/api/purchase-orders/create-label.php', {
                method: 'POST',
                body: formData
            });

            const result = await response.json();

            if (result.success) {
                window.location.href = `/modules/consignments/purchase-orders/freight-label.php?po_id=${this.poId}&label_id=${result.label_id}`;
            } else {
                alert('Error: ' + result.error);
            }
        } catch (error) {
            console.error('Manual entry error:', error);
            alert('Failed to create manual freight entry');
        }
    },

    async refreshQuotes() {
        document.getElementById('refresh-quotes-btn').disabled = true;
        document.getElementById('refresh-quotes-btn').innerHTML = '<i class="fas fa-spinner fa-spin"></i> Refreshing...';

        try {
            const response = await fetch(`/modules/consignments/api/purchase-orders/freight-quote.php?po_id=${this.poId}&force=1`);
            const result = await response.json();

            if (result.success) {
                // Reload page to show new quotes
                window.location.reload();
            } else {
                alert('Error: ' + result.error);
            }
        } catch (error) {
            console.error('Refresh quotes error:', error);
            alert('Failed to refresh quotes');
        }
    },

    viewQuoteDetails(quoteId) {
        // Show modal with detailed quote information
        document.getElementById('quote-details-modal').style.display = 'flex';
        // Load details via AJAX...
    }
};

// Initialize on page load
document.addEventListener('DOMContentLoaded', () => {
    FreightQuote.init();

    // Initialize security monitoring for freight quote page
    try {
        if (typeof SecurityMonitor !== 'undefined') {
            SecurityMonitor.init({
                poId: FreightQuote.poId,
                page: 'freight_quote',
                enabled: true
            });
        }
    } catch (error) {
        console.error('SecurityMonitor init failed:', error);
    }
});
</script>

<!-- Client-side Instrumentation -->
<script src="js/interaction-logger.js"></script>
<script src="js/security-monitor.js"></script>

<?php
// Include footer
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/purchase-orders/views/footer.php';
?>
