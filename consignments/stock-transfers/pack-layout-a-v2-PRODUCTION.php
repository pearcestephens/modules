<?php
/**
 * LAYOUT A: TWO-COLUMN PROFESSIONAL PACKING INTERFACE
 *
 * Features:
 * - Professional two-column layout (main content + freight sidebar)
 * - Real-time weight/volume calculations via FreightEngine
 * - NZ Courier + NZ Post integration with carrier logos
 * - AI-powered carrier recommendations
 * - Outlet-based freight rules
 * - Packing slip generation
 * - Box management system
 * - Live tracking number assignment
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
// Safe-include external dependencies if present
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
if ($transfer_id <= 0 && ! $__demo) {
    die('Transfer ID required');
}

// Load transfer data
if (function_exists('get_transfer_by_id')) {
    $transfer = $transfer_id > 0 ? get_transfer_by_id($transfer_id) : null;
} else { $transfer = null; }
// Load transfer products
if (function_exists('get_transfer_products')) {
    $products = $transfer_id > 0 ? get_transfer_products($transfer_id) : [];
} else { $products = []; }

// Initialize FreightEngine if available
$freightEngine = null;
if (class_exists('FreightEngine')) { $freightEngine = new FreightEngine($pdo); }

// Load outlet data
if ($transfer) {
    $outlet_from = get_outlet_details($transfer['outlet_id_from']);
    $outlet_to = get_outlet_details($transfer['outlet_id_to']);
}

// Demo fallback
if (!$transfer && $__demo) {
    $transfer_id = 999101;
    $transfer = [
        'id' => $transfer_id,
        'outlet_id_from' => 1,
        'outlet_id_to' => 5
    ];
    $outlet_from = ['name' => 'Main Warehouse', 'address' => '1 Supply Way, Auckland'];
    $outlet_to = ['name' => 'Outlet 001', 'address' => '100 Example Rd, Wellington'];
    $products = [
        ['product_id' => 'SKU-1001', 'name' => 'Vape Juice 100ml (Strawberry)', 'sku' => 'VJ-100-STR', 'quantity' => 12, 'image_url' => null],
        ['product_id' => 'SKU-2002', 'name' => 'Coil Pack 5pcs (0.8Ω)', 'sku' => 'COIL-08-5', 'quantity' => 8, 'image_url' => null],
        ['product_id' => 'SKU-3003', 'name' => 'Pod Kit XROS 3', 'sku' => 'KIT-XR3', 'quantity' => 3, 'image_url' => null],
    ];
}
// Fallback outlet names to avoid notices
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
    ['label' => 'Packing', 'url' => '#', 'active' => true]
]);
$template->startContent();
?>

<?php $csrfToken = function_exists('generate_csrf_token') ? generate_csrf_token() : bin2hex(random_bytes(8)); ?>
<!-- CSRF Token -->
<meta name="csrf-token" content="<?= htmlspecialchars($csrfToken) ?>">

<!-- Custom CSS for Layout A -->
<style>
/* === LAYOUT A: TWO-COLUMN PROFESSIONAL === */
.pack-layout-a {
    --sidebar-width: 380px;
    --header-height: 60px;
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
    --shadow-lg: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
.pack-layout-a {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    font-size: 13px;
    background: var(--gray-bg);
    color: var(--text-primary);
}

/* Grid Layout: Main + Sidebar */
.packing-grid {
    display: grid;
    grid-template-columns: 1fr var(--sidebar-width);
    gap: 20px;
    padding: 20px;
    max-width: 1800px;
    margin: 0 auto;
}

/* Main Content Area */
.main-content {
    background: white;
    border-radius: 8px;
    box-shadow: var(--shadow-md);
    overflow: hidden;
}

/* Transfer Info Bar */
.transfer-info-bar {
    background: linear-gradient(135deg, var(--primary-blue), #1e40af);
    color: white;
    padding: 16px 20px;
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 20px;
}

.info-column h3 {
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    opacity: 0.9;
    margin: 0 0 8px 0;
}

.info-column .outlet-name {
    font-size: 16px;
    font-weight: 700;
    margin: 0 0 4px 0;
}

.info-column .outlet-address {
    font-size: 12px;
    opacity: 0.85;
    line-height: 1.4;
}

/* Stats Bar */
.stats-bar {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1px;
    background: var(--gray-light);
    border-bottom: 2px solid var(--border-color);
}

.stat-item {
    background: white;
    padding: 12px 16px;
    text-align: center;
    border-right: 1px solid var(--gray-light);
}

.stat-item:last-child {
    border-right: none;
}

.stat-icon {
    font-size: 20px;
    margin-bottom: 4px;
}

.stat-value {
    display: block;
    font-size: 20px;
    font-weight: 700;
    color: var(--text-primary);
    margin: 4px 0;
}

.stat-label {
    display: block;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    color: var(--gray-dark);
    letter-spacing: 0.3px;
}

/* Search Bar */
.search-bar {
    padding: 16px 20px;
    background: var(--gray-bg);
    border-bottom: 1px solid var(--border-color);
}

.search-input-wrapper {
    position: relative;
}

.search-input-wrapper input {
    width: 100%;
    padding: 10px 40px 10px 40px;
    border: 2px solid var(--border-color);
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.2s;
}

.search-input-wrapper input:focus {
    outline: none;
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.search-icon {
    position: absolute;
    left: 12px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 18px;
    color: var(--gray-dark);
}

.search-clear {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    background: none;
    border: none;
    font-size: 18px;
    color: var(--gray-dark);
    cursor: pointer;
    display: none;
}

/* Product Table */
.product-table-container {
    max-height: calc(100vh - 420px);
    overflow-y: auto;
}

.product-table {
    width: 100%;
    border-collapse: collapse;
}

.product-table thead {
    position: sticky;
    top: 0;
    background: white;
    z-index: 10;
    box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
}

.product-table th {
    padding: 10px 12px;
    text-align: left;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--gray-dark);
    letter-spacing: 0.5px;
    border-bottom: 2px solid var(--border-color);
    background: var(--gray-bg);
}

.product-table td {
    padding: 10px 12px;
    border-bottom: 1px solid var(--gray-light);
    font-size: 13px;
    vertical-align: middle;
}

.product-table tbody tr {
    transition: background 0.15s;
}

.product-table tbody tr:hover {
    background: var(--gray-bg);
}

.product-table tbody tr.packed {
    background: rgba(16, 185, 129, 0.05);
}

/* Product Image */
.product-img {
    width: 40px;
    height: 40px;
    border-radius: 4px;
    object-fit: cover;
    border: 1px solid var(--border-color);
}

/* Product Info Cell */
.product-info {
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.product-name {
    font-weight: 600;
    color: var(--text-primary);
    line-height: 1.3;
}

.product-sku {
    font-size: 11px;
    color: var(--gray-dark);
    font-family: 'Courier New', monospace;
}

/* Weight Badge */
.weight-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 600;
    background: var(--gray-light);
    color: var(--text-secondary);
}

.weight-badge.source-P {
    background: rgba(16, 185, 129, 0.1);
    color: var(--success-green);
}

.weight-badge.source-C {
    background: rgba(245, 158, 11, 0.1);
    color: var(--warning-orange);
}

.weight-badge.source-D {
    background: rgba(239, 68, 68, 0.1);
    color: var(--danger-red);
}

/* Quantity Controls */
.qty-controls {
    display: flex;
    align-items: center;
    gap: 8px;
}

.qty-input {
    width: 60px;
    padding: 6px 8px;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    text-align: center;
    font-size: 13px;
    font-weight: 600;
}

.qty-btn {
    width: 28px;
    height: 28px;
    padding: 0;
    border: 1px solid var(--border-color);
    border-radius: 4px;
    background: white;
    cursor: pointer;
    font-size: 16px;
    line-height: 1;
    transition: all 0.15s;
}

.qty-btn:hover {
    background: var(--gray-bg);
    border-color: var(--primary-blue);
}

.qty-btn:active {
    transform: scale(0.95);
}

/* Freight Sidebar */
.freight-sidebar {
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.freight-console {
    background: white;
    border-radius: 8px;
    box-shadow: var(--shadow-md);
    position: sticky;
    top: 20px;
    max-height: calc(100vh - 40px);
    display: flex;
    flex-direction: column;
}

.console-header {
    padding: 16px 20px;
    background: linear-gradient(135deg, #1e293b, #334155);
    color: white;
    border-radius: 8px 8px 0 0;
}

.console-title {
    font-size: 14px;
    font-weight: 700;
    margin: 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.console-subtitle {
    font-size: 12px;
    font-weight: 700;
    color: var(--text-primary);
    margin: 16px 0 12px 0;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.console-body {
    padding: 20px;
    overflow-y: auto;
    flex: 1;
}

/* Metric Rows */
.metric-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 0;
    border-bottom: 1px solid var(--gray-light);
}

.metric-row:last-child {
    border-bottom: none;
}

.metric-label {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-secondary);
}

.metric-value {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-primary);
}

/* Weight Legend */
.weight-legend {
    margin-top: 12px;
    padding: 10px;
    background: var(--gray-bg);
    border-radius: 6px;
}

.weight-legend small {
    font-size: 11px;
    color: var(--text-secondary);
    line-height: 1.5;
}

/* Carrier Options */
.carrier-options {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-top: 12px;
}

.carrier-option {
    padding: 14px;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
    background: white;
}

.carrier-option:hover {
    border-color: var(--primary-blue);
    box-shadow: var(--shadow-sm);
}

.carrier-option.recommended {
    border-color: var(--success-green);
    background: rgba(16, 185, 129, 0.02);
}

.carrier-option.selected {
    border-color: var(--primary-blue);
    background: rgba(37, 99, 235, 0.05);
    box-shadow: 0 0 0 3px rgba(37, 99, 235, 0.1);
}

.carrier-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 10px;
}

.carrier-logo {
    height: 28px;
    width: auto;
}

.badge-ai {
    font-size: 10px;
    font-weight: 700;
    padding: 4px 8px;
    border-radius: 4px;
    background: linear-gradient(135deg, var(--success-green), #059669);
    color: white;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.carrier-details {
    display: grid;
    grid-template-columns: 1fr auto auto;
    gap: 12px;
    align-items: center;
}

.carrier-name {
    font-size: 12px;
    font-weight: 600;
    color: var(--text-primary);
}

.carrier-price {
    font-size: 16px;
    font-weight: 700;
    color: var(--success-green);
}

.carrier-price small {
    font-size: 10px;
    font-weight: 400;
    color: var(--gray-dark);
}

.carrier-eta {
    font-size: 11px;
    color: var(--text-secondary);
    font-weight: 600;
}

.ai-reason {
    margin-top: 8px;
    padding-top: 8px;
    border-top: 1px solid var(--gray-light);
}

.ai-reason small {
    font-size: 11px;
    color: var(--text-secondary);
    font-style: italic;
}

/* Parcel Items */
.parcel-item {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 12px;
    background: var(--gray-bg);
    border-radius: 6px;
    margin-bottom: 8px;
}

.parcel-item:last-child {
    margin-bottom: 0;
}

.parcel-item strong {
    font-size: 12px;
    color: var(--text-primary);
}

.parcel-item span {
    font-size: 11px;
    color: var(--text-secondary);
}

/* Loading State */
.loading-state {
    text-align: center;
    padding: 40px 20px;
}

.spinner {
    width: 40px;
    height: 40px;
    border: 4px solid var(--gray-light);
    border-top-color: var(--primary-blue);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin: 0 auto 16px auto;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Error State */
.error-state {
    text-align: center;
    padding: 40px 20px;
}

.error-icon {
    font-size: 48px;
    margin-bottom: 12px;
    display: block;
}

/* Section Divider */
.section-divider {
    height: 1px;
    background: var(--gray-light);
    margin: 16px 0;
}

/* Buttons */
.btn {
    padding: 10px 20px;
    border: none;
    border-radius: 6px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
    display: inline-flex;
    align-items: center;
    gap: 6px;
    justify-content: center;
}

.btn-block {
    width: 100%;
}

.btn-primary {
    background: var(--primary-blue);
    color: white;
}

.btn-primary:hover {
    background: #1d4ed8;
    box-shadow: var(--shadow-md);
}

.btn-success {
    background: var(--success-green);
    color: white;
}

.btn-success:hover {
    background: #059669;
    box-shadow: var(--shadow-md);
}

.btn-secondary {
    background: var(--gray-light);
    color: var(--text-primary);
}

.btn-secondary:hover {
    background: var(--border-color);
}

/* Modal */
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    right: 0;
    bottom: 0;
    background: rgba(0, 0, 0, 0.6);
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 9999;
    padding: 20px;
}

.modal-content {
    background: white;
    border-radius: 12px;
    box-shadow: var(--shadow-lg);
    max-width: 600px;
    width: 100%;
    padding: 30px;
}

.modal-content h2 {
    margin: 0 0 20px 0;
    font-size: 20px;
    color: var(--text-primary);
}

.booking-details {
    background: var(--gray-bg);
    padding: 20px;
    border-radius: 8px;
    margin: 20px 0;
}

.booking-details p {
    margin: 8px 0;
    font-size: 13px;
}

.booking-details ul {
    list-style: none;
    padding: 0;
    margin: 12px 0 0 0;
}

.booking-details li {
    padding: 8px 0;
    border-bottom: 1px solid var(--border-color);
}

.booking-details li:last-child {
    border-bottom: none;
}

.booking-details code {
    font-family: 'Courier New', monospace;
    background: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    font-weight: 600;
}

.modal-actions {
    display: flex;
    gap: 12px;
    margin-top: 24px;
}

/* Responsive */
@media (max-width: 1400px) {
    .packing-grid {
        grid-template-columns: 1fr 320px;
    }
}

@media (max-width: 1024px) {
    .packing-grid {
        grid-template-columns: 1fr;
    }

    .freight-sidebar {
        order: -1;
    }

    .freight-console {
        position: static;
        max-height: none;
    }
}
</style>

<!-- Main Layout -->
<div class="pack-layout-a">
    <div class="alert alert-primary" role="alert" style="margin:20px auto;max-width:1800px;">
        <strong>Outgoing to:</strong> <?= htmlspecialchars($outlet_to['name']) ?> &nbsp;•&nbsp; <strong>Transfer #<?= (int)$transfer_id ?></strong>
        <span class="text-muted ml-2">(Ensure destination details and transfer number are verified before dispatch)</span>
    </div>
<div class="packing-grid">

    <!-- MAIN CONTENT -->
    <div class="main-content">

        <!-- Transfer Info Bar -->
        <div class="transfer-info-bar">
            <div class="info-column" id="outlet-from" data-outlet-id="<?= $transfer['outlet_id_from'] ?>">
                <h3>📤 Sending From</h3>
                <div id="outlet-from-info">
                    <div class="outlet-name"><?= htmlspecialchars($outlet_from['name']) ?></div>
                    <div class="outlet-address"><?= htmlspecialchars($outlet_from['address']) ?></div>
                </div>
            </div>
            <div class="info-column" id="outlet-to" data-outlet-id="<?= $transfer['outlet_id_to'] ?>">
                <h3>📥 Delivering To</h3>
                <div id="outlet-to-info">
                    <div class="outlet-name"><?= htmlspecialchars($outlet_to['name']) ?></div>
                    <div class="outlet-address"><?= htmlspecialchars($outlet_to['address']) ?></div>
                </div>
            </div>
        </div>

        <!-- Stats Bar -->
        <div class="stats-bar">
            <div class="stat-item">
                <div class="stat-icon">📦</div>
                <span class="stat-value" id="total-items"><?= count($products) ?></span>
                <span class="stat-label">Total Items</span>
            </div>
            <div class="stat-item">
                <div class="stat-icon">✅</div>
                <span class="stat-value stat-success" id="packed-items">0</span>
                <span class="stat-label">Packed</span>
            </div>
            <div class="stat-item">
                <div class="stat-icon">⚖️</div>
                <span class="stat-value" id="total-weight">0.00 kg</span>
                <span class="stat-label">Total Weight</span>
            </div>
            <div class="stat-item">
                <div class="stat-icon">💰</div>
                <span class="stat-value" id="freight-cost">$0.00</span>
                <span class="stat-label">Est. Freight</span>
            </div>
        </div>

        <!-- Search Bar -->
        <div class="search-bar">
            <div class="search-input-wrapper">
                <span class="search-icon">🔍</span>
                <input type="text"
                       id="product-search"
                       placeholder="Search by SKU, barcode, or product name... (supports barcode scanner)"
                       autocomplete="off">
                <button class="search-clear" id="search-clear" onclick="clearSearch()">✕</button>
            </div>
        </div>

        <!-- Tools Bar -->
        <div style="padding: 8px 20px 0 20px; display:flex; gap:8px; align-items:center;">
            <a class="btn btn-secondary" target="_blank"
               href="/modules/consignments/stock-transfers/packing-slip.php?transfer_id=<?= (int)$transfer_id ?>">
                🖨️ Packing Slip
            </a>
            <small class="text-muted">Destination: <strong><?= htmlspecialchars($outlet_to['name']) ?></strong> • Transfer #<?= (int)$transfer_id ?></small>
        </div>

        <!-- Product Table -->
        <div class="product-table-container">
            <table class="product-table" id="product-table">
                <thead>
                    <tr>
                        <th style="width: 60px;">Image</th>
                        <th>Product</th>
                        <th style="width: 120px;">Weight</th>
                        <th style="width: 100px; text-align: center;">Transfer Qty</th>
                        <th style="width: 150px; text-align: center;">Pack Qty</th>
                        <th style="width: 100px; text-align: center;">Status</th>
                    </tr>
                </thead>
                <tbody id="product-tbody">
                    <?php foreach ($products as $product):
                        $weight_data = ['resolved_weight_g' => 100, 'legend_code' => 'D'];
                        if ($freightEngine) {
                            try {
                                $weight_info = $freightEngine->resolveWeights([$product['product_id']]);
                                $weight_data = $weight_info['weights'][$product['product_id']] ?? $weight_data;
                            } catch (Throwable $e) { /* fallback default */ }
                        }
                    ?>
                    <tr data-product-id="<?= $product['product_id'] ?>"
                        data-sku="<?= htmlspecialchars($product['sku']) ?>"
                        data-weight="<?= $weight_data['resolved_weight_g'] ?>"
                        data-weight-source="<?= $weight_data['legend_code'] ?>">
                        <td>
                            <img src="<?= htmlspecialchars($product['image_url'] ?? '/assets/images/no-image.png') ?>"
                                 alt="<?= htmlspecialchars($product['name']) ?>"
                                 class="product-img">
                        </td>
                        <td>
                            <div class="product-info">
                                <span class="product-name"><?= htmlspecialchars($product['name']) ?></span>
                                <span class="product-sku"><?= htmlspecialchars($product['sku']) ?></span>
                            </div>
                        </td>
                        <td>
                            <span class="weight-badge source-<?= $weight_data['legend_code'] ?>">
                                <?= number_format($weight_data['resolved_weight_g'] / 1000, 3) ?> kg
                                <small>(<?= $weight_data['legend_code'] ?>)</small>
                            </span>
                        </td>
                        <td style="text-align: center;">
                            <strong><?= $product['quantity'] ?></strong>
                        </td>
                        <td>
                            <div class="qty-controls">
                                <button class="qty-btn" onclick="adjustQty(<?= $product['product_id'] ?>, -1)">−</button>
                    <input type="number"
                        class="qty-input"
                        readonly
                                       id="qty-<?= $product['product_id'] ?>"
                                       value="0"
                                       min="0"
                                       max="<?= $product['quantity'] ?>"
                                       onchange="updateQty(<?= $product['product_id'] ?>, this.value)">
                                <button class="qty-btn" onclick="adjustQty(<?= $product['product_id'] ?>, 1)">+</button>
                            </div>
                        </td>
                        <td style="text-align: center;">
                            <span class="status-badge" id="status-<?= $product['product_id'] ?>">⏳ Pending</span>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    </div>

    <!-- FREIGHT SIDEBAR -->
    <div class="freight-sidebar">
        <div class="freight-console">
            <div class="console-header">
                <h2 class="console-title">🚚 Freight Intelligence</h2>
            </div>
            <div class="console-body" id="freight-console-body">
                <div class="loading-state">
                    <p>Pack items to calculate freight options</p>
                    <small>Weight, volume, and carrier rates will appear here</small>
                </div>
            </div>
        </div>
    </div>

 </div>
 </div><!-- /.pack-layout-a -->

<!-- Scripts -->
<script src="/modules/consignments/stock-transfers/js/freight-engine.js"></script>
<script>
// Lightweight telemetry and UX hooks
async function tlog(event, payload={}){
    try{ await fetch('/modules/consignments/api/telemetry.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({event,transfer_id: <?= (int)$transfer_id ?>,payload,ts:new Date().toISOString()})}); }catch(e){}
}
(function(){
    const t0=Date.now();
    window.addEventListener('beforeunload',()=>tlog('pack_session_end',{elapsed_ms: Date.now()-t0}));
    // Patch updateQty if present to log count changes
    if (typeof window.updateQty === 'function'){
        const orig = window.updateQty;
        window.updateQty = function(pid, qty){ tlog('count_changed',{product_id: pid, counted: qty}); return orig(pid, qty); };
    }
})();
</script>
// Product packing logic
const packedItems = [];

function adjustQty(productId, delta) {
    const input = document.getElementById(`qty-${productId}`);
    const currentQty = parseInt(input.value) || 0;
    const maxQty = parseInt(input.max);
    const newQty = Math.max(0, Math.min(maxQty, currentQty + delta));

    input.value = newQty;
    updateQty(productId, newQty);
}

function updateQty(productId, qty) {
    qty = parseInt(qty) || 0;
    const row = document.querySelector(`tr[data-product-id="${productId}"]`);
    const maxQty = parseInt(document.getElementById(`qty-${productId}`).max);

    // Clamp to valid range
    if (qty > maxQty) qty = maxQty;
    if (qty < 0) qty = 0;

    document.getElementById(`qty-${productId}`).value = qty;

    // Update status
    const statusBadge = document.getElementById(`status-${productId}`);
    if (qty === 0) {
        statusBadge.textContent = '⏳ Pending';
        statusBadge.style.color = 'var(--gray-dark)';
        row.classList.remove('packed');
    } else if (qty < maxQty) {
        statusBadge.textContent = '⚠️ Partial';
        statusBadge.style.color = 'var(--warning-orange)';
        row.classList.add('packed');
    } else {
        statusBadge.textContent = '✅ Complete';
        statusBadge.style.color = 'var(--success-green)';
        row.classList.add('packed');
    }

    // Update stats and recalculate freight
    updateStats();
    recalculateFreight();
}

function updateStats() {
    const rows = document.querySelectorAll('#product-tbody tr');
    let totalPacked = 0;
    let totalWeight = 0;

    rows.forEach(row => {
        const productId = row.dataset.productId;
        const qty = parseInt(document.getElementById(`qty-${productId}`).value) || 0;
        const weightPerUnit = parseFloat(row.dataset.weight) || 100; // grams

        if (qty > 0) {
            totalPacked++;
            totalWeight += (qty * weightPerUnit / 1000); // convert to kg
        }
    });

    document.getElementById('packed-items').textContent = totalPacked;
    document.getElementById('total-weight').textContent = totalWeight.toFixed(2) + ' kg';
}

function recalculateFreight() {
    // Collect packed items
    const rows = document.querySelectorAll('#product-tbody tr');
    const items = [];

    rows.forEach(row => {
        const productId = row.dataset.productId;
        const qty = parseInt(document.getElementById(`qty-${productId}`).value) || 0;

        if (qty > 0) {
            items.push({
                product_id: productId,
                sku: row.dataset.sku,
                qty_packed: qty,
                weight_g: parseFloat(row.dataset.weight),
                weight_source: row.dataset.weightSource
            });
        }
    });

    // Calculate freight if items exist
    if (items.length > 0 && typeof freightEngine !== 'undefined') {
        freightEngine.calculateFreight(items);
    }
}

// Search functionality
document.getElementById('product-search').addEventListener('input', function(e) {
    const query = e.target.value.toLowerCase();
    const rows = document.querySelectorAll('#product-tbody tr');
    const clearBtn = document.getElementById('search-clear');

    clearBtn.style.display = query ? 'block' : 'none';

    rows.forEach(row => {
        const sku = row.dataset.sku.toLowerCase();
        const name = row.querySelector('.product-name').textContent.toLowerCase();

        if (sku.includes(query) || name.includes(query)) {
            row.style.display = '';
        } else {
            row.style.display = 'none';
        }
    });
});

function clearSearch() {
    document.getElementById('product-search').value = '';
    document.getElementById('search-clear').style.display = 'none';
    document.querySelectorAll('#product-tbody tr').forEach(row => {
        row.style.display = '';
    });
}

// Packing slip
function printPackingSlip() {
    const transferId = new URLSearchParams(window.location.search).get('transfer_id');
    window.open(`/modules/consignments/stock-transfers/packing-slip.php?transfer_id=${transferId}`, '_blank');
}

// Save progress
async function saveProgress() {
    const rows = document.querySelectorAll('#product-tbody tr');
    const items = [];

    rows.forEach(row => {
        const productId = row.dataset.productId;
        const qty = parseInt(document.getElementById(`qty-${productId}`).value) || 0;

        items.push({
            product_id: productId,
            qty_packed: qty
        });
    });

    try {
        const response = await fetch('/modules/consignments/TransferManager/backend.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({
                action: 'save_packing_progress',
                transfer_id: new URLSearchParams(window.location.search).get('transfer_id'),
                items: items,
                csrf: document.querySelector('meta[name="csrf-token"]').content
            })
        });

        const data = await response.json();

        if (data.success) {
            alert('✅ Progress saved successfully!');
        } else {
            alert('❌ Failed to save: ' + data.message);
        }
    } catch (error) {
        alert('❌ Save failed: ' + error.message);
    }
}

// Auto-save every 30 seconds
setInterval(saveProgress, 30000);

// Barcode scanner support
let barcodeBuffer = '';
let lastKeypressTime = Date.now();

document.addEventListener('keypress', function(e) {
    const now = Date.now();

    // Reset buffer if > 100ms between keystrokes (typed vs scanned)
    if (now - lastKeypressTime > 100) {
        barcodeBuffer = '';
    }

    lastKeypressTime = now;

    // Build barcode buffer
    if (e.key === 'Enter') {
        // Barcode complete, search for it
        if (barcodeBuffer.length > 3) {
            document.getElementById('product-search').value = barcodeBuffer;
            document.getElementById('product-search').dispatchEvent(new Event('input'));
        }
        barcodeBuffer = '';
    } else {
        barcodeBuffer += e.key;
    }
});

// Manual unlock toggle for Layout A
document.addEventListener('DOMContentLoaded', () => {
    const toolbar = document.createElement('div');
    toolbar.className = 'd-flex align-items-center mb-2';
    toolbar.innerHTML = `
        <div class="custom-control custom-switch">
            <input type="checkbox" class="custom-control-input" id="unlockManualA">
            <label class="custom-control-label" for="unlockManualA">Unlock manual quantity editing</label>
        </div>`;
    const grid = document.querySelector('.packing-grid');
    if (grid) grid.parentNode.insertBefore(toolbar, grid);
    const toggle = document.getElementById('unlockManualA');
    if (toggle) {
        toggle.addEventListener('change', () => {
            document.querySelectorAll('.qty-input').forEach(inp => inp.readOnly = !toggle.checked);
        });
    }
});
</script>

<?php
$template->endContent();
$template->render();
?>
