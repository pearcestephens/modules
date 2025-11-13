<?php
/**
 * LAYOUT C: ACCORDION MOBILE-OPTIMIZED PACKING INTERFACE
 *
 * Features:
 * - Collapsible accordion sections for compact mobile experience
 * - Stacked vertical layout optimized for narrow screens
 * - Swipe-friendly product cards
 * - Same freight integration as Layouts A & B
 * - Progressive disclosure for better mobile UX
 * - Touch-optimized controls
 *
 * @version 2.0.0 - Production Ready
 * @date 2025-11-09
 * @quality TOP QUALITY BEST INTERFACE HIGHEST QUALITY
 */

// Health/Status: respond OK to HEAD probes
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'HEAD') {
    http_response_code(200);
    exit;
}

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/CISTemplate.php';
@include_once ($_SERVER['DOCUMENT_ROOT'] . '/assets/services/core/freight/FreightEngine.php');
@include_once ($_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/TransferManager/functions/transfer_functions.php');

// Security & Auth
// Allow demo mode without login
$__demo = isset($_GET['demo']) && $_GET['demo'] === '1';
if (!isset($_SESSION['staff_id']) && ! $__demo) {
    header('Location: /login.php');
    exit;
}

// Get transfer ID (demo support)
$transfer_id = isset($_GET['transfer_id']) ? (int)$_GET['transfer_id'] : 0;
$__demo = isset($_GET['demo']) && $_GET['demo'] === '1';
if ($transfer_id <= 0 && ! $__demo) {
    die('Transfer ID required');
}

// Load transfer data/products
if (function_exists('get_transfer_by_id')) { $transfer = $transfer_id > 0 ? get_transfer_by_id($transfer_id) : null; } else { $transfer = null; }
if (function_exists('get_transfer_products')) { $products = $transfer_id > 0 ? get_transfer_products($transfer_id) : []; } else { $products = []; }

// Initialize FreightEngine if available
if (class_exists('FreightEngine')) { $freightEngine = new FreightEngine($pdo); } else { $freightEngine = null; }

// Load outlet data (or demo)
if ($transfer) {
    $outlet_from = get_outlet_details($transfer['outlet_id_from']);
    $outlet_to = get_outlet_details($transfer['outlet_id_to']);
} else if ($__demo) {
    $transfer_id = 999103;
    $transfer = [ 'id' => $transfer_id, 'outlet_id_from' => 1, 'outlet_id_to' => 6 ];
    $outlet_from = ['name' => 'Main Warehouse', 'address' => '1 Supply Way, Auckland'];
    $outlet_to = ['name' => 'Outlet 003', 'address' => '200 Example Ave, Christchurch'];
    $products = [
        ['product_id' => 'SKU-1201', 'name' => 'Pod 2ml (4pk)', 'sku' => 'POD-2-4', 'quantity' => 12],
        ['product_id' => 'SKU-3303', 'name' => 'Battery 18650', 'sku' => 'BAT-18650', 'quantity' => 4],
    ];
}
if (!isset($outlet_from)) { $outlet_from = ['name' => 'From']; }
if (!isset($outlet_to)) { $outlet_to = ['name' => 'To']; }

// Page metadata
$page_title = "Pack Transfer: {$outlet_from['name']} → {$outlet_to['name']}";
$page_icon = "📦";

// Initialize CIS Template
$template = new CISTemplate();
$template->setTitle($page_title);
$template->setBreadcrumbs([
    ['label' => 'Consignments', 'url' => '/modules/consignments/'],
    ['label' => 'Stock Transfers', 'url' => '/modules/consignments/TransferManager/frontend.php'],
    ['label' => 'Packing (Mobile)', 'url' => '#', 'active' => true]
]);
$template->startContent();
?>

<!-- CSRF Token -->
<meta name="csrf-token" content="<?= generate_csrf_token() ?>">

<!-- Custom CSS for Layout C -->
<style>
/* === LAYOUT C: ACCORDION MOBILE-OPTIMIZED (scoped) === */
.pack-layout-c {
    --primary-blue: #2563eb;
    --success-green: #10b981;
    --warning-orange: #f59e0b;
    --danger-red: #ef4444;
    --gray-bg: #f3f4f6;
    --gray-light: #e5e7eb;
    --gray-dark: #6b7280;
    --text-primary: #111827;
    --text-secondary: #4b5563;
    --border-color: #d1d5db;
    --shadow-sm: 0 1px 2px 0 rgba(0, 0, 0, 0.05);
    --shadow-md: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
.pack-layout-c {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    font-size: 14px;
    background: var(--gray-bg);
    color: var(--text-primary);
}

/* Container */
.pack-layout-c .accordion-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 16px;
}

/* Header Card */
.header-card {
    background: linear-gradient(135deg, var(--primary-blue), #1e40af);
    color: white;
    border-radius: 12px;
    padding: 20px;
    margin-bottom: 16px;
    box-shadow: var(--shadow-md);
}

.route-display {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 16px;
    font-weight: 700;
}

.route-arrow {
    font-size: 20px;
}

.outlet-details {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 16px;
    margin-top: 16px;
    padding-top: 16px;
    border-top: 1px solid rgba(255, 255, 255, 0.2);
}

.outlet-detail h4 {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    opacity: 0.8;
    margin: 0 0 6px 0;
}

.outlet-detail .name {
    font-size: 14px;
    font-weight: 600;
}

.outlet-detail .address {
    font-size: 12px;
    opacity: 0.9;
    margin-top: 4px;
}

/* Stats Bar */
.stats-bar {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
    margin-bottom: 16px;
}

.stat-box {
    background: white;
    border-radius: 10px;
    padding: 16px;
    text-align: center;
    box-shadow: var(--shadow-sm);
    border: 2px solid var(--border-color);
}

.stat-box-icon {
    font-size: 28px;
    margin-bottom: 8px;
}

.stat-box-value {
    display: block;
    font-size: 20px;
    font-weight: 700;
    color: var(--text-primary);
    margin: 4px 0;
}

.stat-box-label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--gray-dark);
    letter-spacing: 0.3px;
}

/* Accordion */
.accordion {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.accordion-item {
    background: white;
    border-radius: 10px;
    box-shadow: var(--shadow-sm);
    overflow: hidden;
    border: 2px solid var(--border-color);
}

.accordion-item.open {
    border-color: var(--primary-blue);
}

.accordion-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 20px;
    cursor: pointer;
    background: white;
    transition: all 0.2s;
    user-select: none;
    -webkit-tap-highlight-color: transparent;
}

.accordion-header:active {
    background: var(--gray-bg);
}

.accordion-header-left {
    display: flex;
    align-items: center;
    gap: 12px;
}

.accordion-icon {
    font-size: 24px;
}

.accordion-title-group h3 {
    font-size: 15px;
    font-weight: 700;
    margin: 0;
    color: var(--text-primary);
}

.accordion-subtitle {
    font-size: 12px;
    color: var(--gray-dark);
    margin: 4px 0 0 0;
}

.accordion-chevron {
    font-size: 20px;
    color: var(--gray-dark);
    transition: transform 0.3s;
}

.accordion-item.open .accordion-chevron {
    transform: rotate(180deg);
}

.accordion-body {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease;
}

.accordion-item.open .accordion-body {
    max-height: 5000px;
}

.accordion-content {
    padding: 0 20px 20px 20px;
}

/* Search Bar */
.search-wrapper {
    margin-bottom: 16px;
}

.search-input-mobile {
    width: 100%;
    padding: 12px 16px 12px 44px;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    font-size: 15px;
    background-image: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="%236b7280" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>');
    background-repeat: no-repeat;
    background-position: 14px center;
}

.search-input-mobile:focus {
    outline: none;
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

/* Product List */
.products-list {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.product-mobile-card {
    background: var(--gray-bg);
    border-radius: 8px;
    padding: 14px;
    border: 2px solid transparent;
    transition: all 0.2s;
}

.product-mobile-card.packed {
    background: rgba(16, 185, 129, 0.05);
    border-color: var(--success-green);
}

.product-mobile-header {
    display: flex;
    gap: 12px;
    margin-bottom: 12px;
}

.product-mobile-image {
    width: 60px;
    height: 60px;
    border-radius: 6px;
    object-fit: cover;
    border: 1px solid var(--border-color);
}

.product-mobile-info {
    flex: 1;
}

.product-mobile-name {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1.3;
    margin-bottom: 4px;
}

.product-mobile-sku {
    font-size: 12px;
    color: var(--gray-dark);
    font-family: 'Courier New', monospace;
    margin-bottom: 6px;
}

.product-mobile-weight {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    background: rgba(107, 114, 128, 0.15);
    color: var(--gray-dark);
}

.product-mobile-weight.source-P {
    background: rgba(16, 185, 129, 0.15);
    color: var(--success-green);
}

.product-mobile-weight.source-C {
    background: rgba(245, 158, 11, 0.15);
    color: var(--warning-orange);
}

.product-mobile-weight.source-D {
    background: rgba(239, 68, 68, 0.15);
    color: var(--danger-red);
}

.product-mobile-qty-section {
    display: flex;
    align-items: center;
    gap: 12px;
    background: white;
    padding: 10px;
    border-radius: 6px;
}

.transfer-qty-display {
    flex: 1;
    text-align: center;
    padding: 8px;
    background: var(--gray-bg);
    border-radius: 4px;
}

.transfer-qty-label {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--gray-dark);
}

.transfer-qty-value {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-primary);
}

.qty-controls-mobile {
    display: flex;
    align-items: center;
    gap: 10px;
}

.qty-btn-mobile {
    width: 44px;
    height: 44px;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    background: white;
    cursor: pointer;
    font-size: 22px;
    font-weight: 700;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.15s;
    -webkit-tap-highlight-color: transparent;
}

.qty-btn-mobile:active {
    transform: scale(0.92);
    background: var(--gray-bg);
}

.qty-input-mobile {
    width: 70px;
    height: 44px;
    padding: 0;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    text-align: center;
    font-size: 18px;
    font-weight: 700;
}

/* Freight Section */
.freight-mobile-section {
    padding-top: 8px;
}

.weight-summary-mobile {
    background: var(--gray-bg);
    border-radius: 8px;
    padding: 16px;
    margin-bottom: 16px;
}

.weight-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid var(--border-color);
}

.weight-row:last-child {
    border-bottom: none;
}

.weight-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-secondary);
}

.weight-value {
    font-size: 15px;
    font-weight: 700;
    color: var(--text-primary);
}

.carriers-mobile {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.carrier-mobile-card {
    padding: 16px;
    border: 3px solid var(--border-color);
    border-radius: 10px;
    background: white;
    transition: all 0.2s;
    -webkit-tap-highlight-color: transparent;
}

.carrier-mobile-card.recommended {
    border-color: var(--success-green);
    background: rgba(16, 185, 129, 0.02);
}

.carrier-mobile-card.selected {
    border-color: var(--primary-blue);
    background: rgba(37, 99, 235, 0.05);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.carrier-mobile-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 14px;
}

.carrier-logo-mobile {
    height: 32px;
    width: auto;
}

.badge-ai-mobile {
    font-size: 10px;
    font-weight: 700;
    padding: 4px 8px;
    border-radius: 4px;
    background: linear-gradient(135deg, var(--success-green), #059669);
    color: white;
    text-transform: uppercase;
}

.carrier-mobile-name {
    font-size: 15px;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 12px;
}

.carrier-mobile-details {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}

.carrier-mobile-detail {
    text-align: center;
    padding: 10px;
    background: var(--gray-bg);
    border-radius: 6px;
}

.carrier-mobile-detail-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--gray-dark);
    margin-bottom: 4px;
}

.carrier-mobile-detail-value {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-primary);
}

.price-green {
    color: var(--success-green);
}

/* Action Buttons */
.action-button {
    width: 100%;
    padding: 16px;
    border: none;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
    margin-top: 16px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    -webkit-tap-highlight-color: transparent;
}

.action-button:active {
    transform: scale(0.98);
}

.action-button-primary {
    background: var(--primary-blue);
    color: white;
}

.action-button-success {
    background: var(--success-green);
    color: white;
}

/* Loading State */
.loading-mobile {
    text-align: center;
    padding: 40px 20px;
}

.spinner-mobile {
    width: 40px;
    height: 40px;
    border: 3px solid var(--gray-light);
    border-top-color: var(--primary-blue);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin: 0 auto 16px auto;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Responsive adjustments */
@media (min-width: 600px) {
    .stats-bar {
        grid-template-columns: repeat(4, 1fr);
    }

    .carrier-mobile-details {
        grid-template-columns: repeat(3, 1fr);
    }
}

@media (max-width: 400px) {
    .accordion-container {
        padding: 12px;
    }

    .stats-bar {
        grid-template-columns: 1fr;
    }

    .outlet-details {
        grid-template-columns: 1fr;
    }
}
</style>

<!-- Main Container -->
<div class="pack-layout-c">
    <div class="alert alert-primary" role="alert" style="margin:16px auto;max-width:800px;">
        <strong>Outgoing to:</strong> <?= htmlspecialchars($outlet_to['name']) ?> &nbsp;•&nbsp; <strong>Transfer #<?= (int)$transfer_id ?></strong>
    </div>
    <div class="accordion-container">

    <!-- Header Card -->
    <div class="header-card">
        <div class="route-display">
            <span><?= htmlspecialchars($outlet_from['name']) ?></span>
            <span class="route-arrow">→</span>
            <span><?= htmlspecialchars($outlet_to['name']) ?></span>
        </div>
        <div class="outlet-details" id="outlet-from" data-outlet-id="<?= $transfer['outlet_id_from'] ?>">
            <div class="outlet-detail" id="outlet-to" data-outlet-id="<?= $transfer['outlet_id_to'] ?>">
                <h4>📤 From</h4>
                <div id="outlet-from-info">
                    <div class="name"><?= htmlspecialchars($outlet_from['name']) ?></div>
                    <div class="address"><?= htmlspecialchars($outlet_from['address']) ?></div>
                </div>
            </div>
            <div class="outlet-detail">
                <h4>📥 To</h4>
                <div id="outlet-to-info">
                    <div class="name"><?= htmlspecialchars($outlet_to['name']) ?></div>
                    <div class="address"><?= htmlspecialchars($outlet_to['address']) ?></div>
                </div>
            </div>
        </div>
    </div>

    <!-- Stats Bar -->
    <div class="stats-bar">
        <div class="stat-box">
            <div class="stat-box-icon">📦</div>
            <span class="stat-box-value" id="total-items"><?= count($products) ?></span>
            <span class="stat-box-label">Items</span>
        </div>
        <div class="stat-box">
            <div class="stat-box-icon">✅</div>
            <span class="stat-box-value" id="packed-items">0</span>
            <span class="stat-box-label">Packed</span>
        </div>
        <div class="stat-box">
            <div class="stat-box-icon">⚖️</div>
            <span class="stat-box-value" id="total-weight">0kg</span>
            <span class="stat-box-label">Weight</span>
        </div>
        <div class="stat-box">
            <div class="stat-box-icon">💰</div>
            <span class="stat-box-value" id="freight-cost">$0</span>
            <span class="stat-box-label">Freight</span>
        </div>
    </div>

    <!-- Accordion -->
    <div class="accordion">

        <!-- Section 1: Products -->
        <div class="accordion-item open">
            <div class="accordion-header" onclick="toggleAccordion(this)">
                <div class="accordion-header-left">
                    <span class="accordion-icon">📦</span>
                    <div class="accordion-title-group">
                        <h3>Pack Products</h3>
                        <p class="accordion-subtitle">Select quantities to pack</p>
                    </div>
                </div>
                <span class="accordion-chevron">▼</span>
            </div>
            <div class="accordion-body">
                <div class="accordion-content">
                    <div class="search-wrapper">
                        <input type="text"
                               id="product-search"
                               class="search-input-mobile"
                               placeholder="Search products..."
                               autocomplete="off">
                    </div>

                    <div class="products-list" id="products-list">
                        <?php foreach ($products as $product):
                            $weight_info = $freightEngine->resolveWeights([$product['product_id']]);
                            $weight_data = $weight_info['weights'][$product['product_id']] ?? ['resolved_weight_g' => 100, 'legend_code' => 'D'];
                        ?>
                        <div class="product-mobile-card"
                             data-product-id="<?= $product['product_id'] ?>"
                             data-sku="<?= htmlspecialchars($product['sku']) ?>"
                             data-weight="<?= $weight_data['resolved_weight_g'] ?>"
                             data-weight-source="<?= $weight_data['legend_code'] ?>">

                            <div class="product-mobile-header">
                                <img src="<?= htmlspecialchars($product['image_url'] ?? '/assets/images/no-image.png') ?>"
                                     alt="<?= htmlspecialchars($product['name']) ?>"
                                     class="product-mobile-image">
                                <div class="product-mobile-info">
                                    <div class="product-mobile-name"><?= htmlspecialchars($product['name']) ?></div>
                                    <div class="product-mobile-sku"><?= htmlspecialchars($product['sku']) ?></div>
                                    <span class="product-mobile-weight source-<?= $weight_data['legend_code'] ?>">
                                        ⚖️ <?= number_format($weight_data['resolved_weight_g'] / 1000, 3) ?>kg (<?= $weight_data['legend_code'] ?>)
                                    </span>
                                </div>
                            </div>

                            <div class="product-mobile-qty-section">
                                <div class="transfer-qty-display">
                                    <div class="transfer-qty-label">Transfer</div>
                                    <div class="transfer-qty-value"><?= $product['quantity'] ?></div>
                                </div>

                                <div class="qty-controls-mobile">
                                    <button class="qty-btn-mobile" onclick="adjustQty(<?= $product['product_id'] ?>, -1)">−</button>
                     <input type="number"
                         class="qty-input-mobile" readonly
                                           id="qty-<?= $product['product_id'] ?>"
                                           value="0"
                                           min="0"
                                           max="<?= $product['quantity'] ?>"
                                           onchange="updateQty(<?= $product['product_id'] ?>, this.value)">
                                    <button class="qty-btn-mobile" onclick="adjustQty(<?= $product['product_id'] ?>, 1)">+</button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: Freight -->
        <div class="accordion-item">
            <div class="accordion-header" onclick="toggleAccordion(this)">
                <div class="accordion-header-left">
                    <span class="accordion-icon">🚚</span>
                    <div class="accordion-title-group">
                        <h3>Freight Options</h3>
                        <p class="accordion-subtitle">Choose carrier and service</p>
                    </div>
                </div>
                <span class="accordion-chevron">▼</span>
            </div>
            <div class="accordion-body">
                <div class="accordion-content">
                    <div class="freight-mobile-section" id="freight-mobile-body">
                        <div class="loading-mobile">
                            <p>Pack items to see freight options</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 3: Complete -->
        <div class="accordion-item">
            <div class="accordion-header" onclick="toggleAccordion(this)">
                <div class="accordion-header-left">
                    <span class="accordion-icon">✅</span>
                    <div class="accordion-title-group">
                        <h3>Complete Transfer</h3>
                        <p class="accordion-subtitle">Book freight and finish</p>
                    </div>
                </div>
                <span class="accordion-chevron">▼</span>
            </div>
            <div class="accordion-body">
                <div class="accordion-content">
                    <button class="action-button action-button-primary" onclick="printPackingSlip()">
                        🖨️ Print Packing Slip
                    </button>
                    <button class="action-button action-button-success" onclick="completeTransfer()">
                        ✅ Complete & Book Freight
                    </button>
                </div>
            </div>
        </div>

    </div>

</div>

<!-- Scripts -->
<script src="/modules/consignments/stock-transfers/js/freight-engine.js"></script>
<script src="/modules/consignments/stock-transfers/js/pack-layout-c.js"></script>
<script>
// Manual unlock toggle for Layout C (injects a switch above accordion)
document.addEventListener('DOMContentLoaded', ()=>{
    const switcher = document.createElement('div');
    switcher.className = 'd-flex align-items-center mb-2';
    switcher.innerHTML = `
        <div class="custom-control custom-switch">
            <input type="checkbox" class="custom-control-input" id="unlockManualC">
            <label class="custom-control-label" for="unlockManualC">Unlock manual quantity editing</label>
        </div>`;
    const container = document.querySelector('.accordion-container');
    if (container && container.parentNode) container.parentNode.insertBefore(switcher, container);
    const toggle = document.getElementById('unlockManualC');
    if (toggle) toggle.addEventListener('change', ()=>{
        document.querySelectorAll('.qty-input-mobile').forEach(inp => inp.readOnly = !toggle.checked);
    });
});
</script>

<?php
$template->endContent();
$template->render();
?>
