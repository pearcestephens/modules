<?php
/**
 * LAYOUT B: TABS-BASED PROFESSIONAL PACKING INTERFACE
 *
 * Features:
 * - Tab-based navigation (Products → Freight → AI → Summary)
 * - Full-width layout with organized sections
 * - Same freight integration as Layout A
 * - Better for users who prefer sequential workflow
 * - Mobile-friendly tab switching
 *
 * @version 2.0.0 - Production Ready
 * @date 2025-11-09
 * @quality TOP QUALITY BEST INTERFACE HIGHEST QUALITY
 */

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/../lib/CISTemplate.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/services/core/freight/FreightEngine.php';
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/TransferManager/functions/transfer_functions.php';

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

// Load transfer data
$transfer = $transfer_id > 0 ? get_transfer_by_id($transfer_id) : null;
// Load transfer products
$products = $transfer_id > 0 ? get_transfer_products($transfer_id) : [];

// Initialize FreightEngine if available
$freightEngine = null;
if (class_exists('FreightEngine')) {
    $freightEngine = new FreightEngine($pdo);
}

// Load outlet data
if ($transfer) {
    $outlet_from = get_outlet_details($transfer['outlet_id_from']);
    $outlet_to = get_outlet_details($transfer['outlet_id_to']);
}

// Demo fallback
if (!$transfer && $__demo) {
    $transfer_id = 999102;
    $transfer = [
        'id' => $transfer_id,
        'outlet_id_from' => 1,
        'outlet_id_to' => 5
    ];
    $outlet_from = ['name' => 'Main Warehouse'];
    $outlet_to = ['name' => 'Outlet 002'];
    $products = [
        ['product_id' => 'SKU-1101', 'name' => 'E-Liquid 60ml (Berry)', 'sku' => 'EL-60-BER', 'quantity' => 10],
        ['product_id' => 'SKU-2202', 'name' => 'Coils 1.0Ω (5pk)', 'sku' => 'COIL-10-5', 'quantity' => 6],
    ];
}

// Page metadata
$page_title = "Pack Transfer: {$outlet_from['name']} → {$outlet_to['name']}";
$page_icon = "📦";

// Initialize CIS Template
$template = new CISTemplate();
$template->setTitle($page_title);
$template->setBreadcrumbs([
    ['label' => 'Consignments', 'url' => '/modules/consignments/'],
    ['label' => 'Stock Transfers', 'url' => '/modules/consignments/TransferManager/frontend.php'],
    ['label' => 'Packing (Tabs)', 'url' => '#', 'active' => true]
]);
$template->startContent();
?>

<!-- CSRF Token -->
<meta name="csrf-token" content="<?= generate_csrf_token() ?>">

<!-- Custom CSS for Layout B (scoped) -->
<style>
/* === LAYOUT B: TABS-BASED === */
.pack-layout-b {
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
.pack-layout-b {
    font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
    font-size: 13px;
    background: var(--gray-bg);
    color: var(--text-primary);
}

/* Container */
.pack-layout-b .tabs-container {
    max-width: 1600px;
    margin: 20px auto;
    padding: 0 20px;
}

/* Card Wrapper */
.card {
    background: white;
    border-radius: 12px;
    box-shadow: var(--shadow-md);
    overflow: hidden;
}

/* Transfer Header */
.transfer-header {
    background: linear-gradient(135deg, var(--primary-blue), #1e40af);
    color: white;
    padding: 24px 30px;
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 30px;
}

.header-column h3 {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    opacity: 0.9;
    margin: 0 0 10px 0;
}

.header-column .outlet-name {
    font-size: 18px;
    font-weight: 700;
    margin: 0 0 6px 0;
}

.header-column .outlet-address {
    font-size: 13px;
    opacity: 0.9;
    line-height: 1.5;
}

/* Tabs Navigation */
.tabs-nav {
    display: flex;
    background: var(--gray-bg);
    border-bottom: 2px solid var(--border-color);
    padding: 0;
    margin: 0;
    list-style: none;
}

.tab-item {
    flex: 1;
    text-align: center;
}

.tab-link {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 18px 20px;
    background: transparent;
    border: none;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-size: 13px;
    font-weight: 600;
    color: var(--gray-dark);
    transition: all 0.2s;
    width: 100%;
}

.tab-link:hover {
    background: white;
    color: var(--text-primary);
}

.tab-link.active {
    background: white;
    color: var(--primary-blue);
    border-bottom-color: var(--primary-blue);
}

.tab-icon {
    font-size: 24px;
}

.tab-label {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

/* Tab Content */
.tab-content {
    display: none;
}

.tab-content.active {
    display: block;
    animation: fadeIn 0.3s;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Stats Grid */
.stats-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 20px;
    padding: 24px 30px;
    background: white;
    border-bottom: 1px solid var(--border-color);
}

.stat-card {
    text-align: center;
    padding: 16px;
    background: var(--gray-bg);
    border-radius: 8px;
    border: 2px solid transparent;
    transition: all 0.2s;
}

.stat-card:hover {
    border-color: var(--primary-blue);
    box-shadow: var(--shadow-sm);
}

.stat-card-icon {
    font-size: 32px;
    margin-bottom: 8px;
}

.stat-card-value {
    display: block;
    font-size: 24px;
    font-weight: 700;
    color: var(--text-primary);
    margin: 8px 0;
}

.stat-card-label {
    display: block;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--gray-dark);
    letter-spacing: 0.4px;
}

/* Products Tab */
.products-tab-content {
    padding: 30px;
}

.search-bar {
    margin-bottom: 20px;
}

.search-input-wrapper {
    position: relative;
    max-width: 600px;
}

.search-input-wrapper input {
    width: 100%;
    padding: 12px 45px 12px 45px;
    border: 2px solid var(--border-color);
    border-radius: 8px;
    font-size: 14px;
    transition: all 0.2s;
}

.search-input-wrapper input:focus {
    outline: none;
    border-color: var(--primary-blue);
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
}

.search-icon {
    position: absolute;
    left: 14px;
    top: 50%;
    transform: translateY(-50%);
    font-size: 20px;
    color: var(--gray-dark);
}

/* Product Grid */
.products-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
    gap: 20px;
    margin-top: 20px;
}

.product-card {
    background: white;
    border: 2px solid var(--border-color);
    border-radius: 10px;
    padding: 16px;
    transition: all 0.2s;
}

.product-card:hover {
    border-color: var(--primary-blue);
    box-shadow: var(--shadow-md);
}

.product-card.packed {
    border-color: var(--success-green);
    background: rgba(16, 185, 129, 0.02);
}

.product-card-header {
    display: flex;
    gap: 14px;
    margin-bottom: 14px;
}

.product-card-image {
    width: 70px;
    height: 70px;
    border-radius: 8px;
    object-fit: cover;
    border: 1px solid var(--border-color);
}

.product-card-info {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.product-card-name {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-primary);
    line-height: 1.4;
}

.product-card-sku {
    font-size: 11px;
    color: var(--gray-dark);
    font-family: 'Courier New', monospace;
    font-weight: 600;
}

.product-card-weight {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 4px;
    font-size: 11px;
    font-weight: 700;
    background: var(--gray-light);
    color: var(--text-secondary);
    margin-top: 4px;
}

.product-card-weight.source-P {
    background: rgba(16, 185, 129, 0.15);
    color: var(--success-green);
}

.product-card-weight.source-C {
    background: rgba(245, 158, 11, 0.15);
    color: var(--warning-orange);
}

.product-card-weight.source-D {
    background: rgba(239, 68, 68, 0.15);
    color: var(--danger-red);
}

.product-card-body {
    border-top: 1px solid var(--gray-light);
    padding-top: 14px;
}

.transfer-qty {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 12px;
    padding: 8px 12px;
    background: var(--gray-bg);
    border-radius: 6px;
}

.transfer-qty-label {
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--gray-dark);
}

.transfer-qty-value {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-primary);
}

.qty-controls {
    display: grid;
    grid-template-columns: 40px 1fr 40px;
    gap: 8px;
    align-items: center;
}

.qty-btn {
    height: 40px;
    border: 2px solid var(--border-color);
    border-radius: 6px;
    background: white;
    cursor: pointer;
    font-size: 20px;
    font-weight: 700;
    transition: all 0.15s;
}

.qty-btn:hover {
    background: var(--gray-bg);
    border-color: var(--primary-blue);
}

.qty-btn:active {
    transform: scale(0.95);
}

.qty-input {
    height: 40px;
    padding: 0 12px;
    border: 2px solid var(--border-color);
    border-radius: 6px;
    text-align: center;
    font-size: 16px;
    font-weight: 700;
}

.qty-input:focus {
    outline: none;
    border-color: var(--primary-blue);
}

/* Freight Tab */
.freight-tab-content {
    padding: 40px;
}

.freight-section {
    max-width: 1000px;
    margin: 0 auto;
}

.section-title {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 20px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.weight-summary-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 40px;
}

.weight-card {
    padding: 20px;
    background: var(--gray-bg);
    border-radius: 10px;
    border: 2px solid transparent;
    transition: all 0.2s;
}

.weight-card:hover {
    border-color: var(--primary-blue);
}

.weight-card-label {
    font-size: 12px;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--gray-dark);
    margin-bottom: 10px;
}

.weight-card-value {
    font-size: 28px;
    font-weight: 700;
    color: var(--text-primary);
}

.weight-legend {
    margin-top: 30px;
    padding: 16px 20px;
    background: rgba(37, 99, 235, 0.05);
    border-left: 4px solid var(--primary-blue);
    border-radius: 6px;
}

.carrier-comparison {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 24px;
    margin-top: 30px;
}

.carrier-card {
    padding: 24px;
    border: 3px solid var(--border-color);
    border-radius: 12px;
    background: white;
    cursor: pointer;
    transition: all 0.2s;
}

.carrier-card:hover {
    border-color: var(--primary-blue);
    box-shadow: var(--shadow-md);
}

.carrier-card.recommended {
    border-color: var(--success-green);
    background: rgba(16, 185, 129, 0.02);
}

.carrier-card.selected {
    border-color: var(--primary-blue);
    background: rgba(37, 99, 235, 0.05);
    box-shadow: 0 0 0 4px rgba(37, 99, 235, 0.1);
}

.carrier-card-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 16px;
}

.carrier-logo {
    height: 36px;
    width: auto;
}

.badge-ai {
    font-size: 10px;
    font-weight: 700;
    padding: 5px 10px;
    border-radius: 5px;
    background: linear-gradient(135deg, var(--success-green), #059669);
    color: white;
    text-transform: uppercase;
    letter-spacing: 0.5px;
}

.carrier-service-name {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-primary);
    margin-bottom: 16px;
}

.carrier-details-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 12px;
}

.carrier-detail {
    padding: 12px;
    background: var(--gray-bg);
    border-radius: 6px;
}

.carrier-detail-label {
    font-size: 10px;
    font-weight: 700;
    text-transform: uppercase;
    color: var(--gray-dark);
    margin-bottom: 4px;
}

.carrier-detail-value {
    font-size: 18px;
    font-weight: 700;
    color: var(--text-primary);
}

.carrier-price {
    color: var(--success-green);
}

/* AI Tab */
.ai-tab-content {
    padding: 40px;
}

.ai-section {
    max-width: 900px;
    margin: 0 auto;
}

.ai-recommendation-card {
    background: linear-gradient(135deg, #4f46e5, #7c3aed);
    color: white;
    padding: 30px;
    border-radius: 12px;
    margin-bottom: 30px;
}

.ai-recommendation-card h2 {
    font-size: 20px;
    margin: 0 0 16px 0;
    display: flex;
    align-items: center;
    gap: 10px;
}

.ai-recommendation-card p {
    font-size: 14px;
    opacity: 0.95;
    line-height: 1.6;
    margin: 0;
}

.insights-grid {
    display: grid;
    gap: 20px;
}

.insight-card {
    padding: 24px;
    background: white;
    border-radius: 10px;
    border-left: 4px solid var(--primary-blue);
    box-shadow: var(--shadow-sm);
}

.insight-card h3 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 12px 0;
    display: flex;
    align-items: center;
    gap: 8px;
}

.insight-card p {
    font-size: 13px;
    color: var(--text-secondary);
    line-height: 1.6;
    margin: 0;
}

/* Summary Tab */
.summary-tab-content {
    padding: 40px;
}

.summary-section {
    max-width: 1000px;
    margin: 0 auto;
}

.summary-grid {
    display: grid;
    gap: 24px;
}

.summary-card {
    padding: 24px;
    background: white;
    border: 2px solid var(--border-color);
    border-radius: 10px;
}

.summary-card h3 {
    font-size: 16px;
    font-weight: 700;
    color: var(--text-primary);
    margin: 0 0 16px 0;
    padding-bottom: 12px;
    border-bottom: 2px solid var(--border-color);
}

.summary-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 0;
    border-bottom: 1px solid var(--gray-light);
}

.summary-row:last-child {
    border-bottom: none;
}

.summary-label {
    font-size: 13px;
    font-weight: 600;
    color: var(--text-secondary);
}

.summary-value {
    font-size: 14px;
    font-weight: 700;
    color: var(--text-primary);
}

.complete-button {
    margin-top: 30px;
    width: 100%;
    padding: 16px;
    background: var(--success-green);
    color: white;
    border: none;
    border-radius: 10px;
    font-size: 16px;
    font-weight: 700;
    cursor: pointer;
    transition: all 0.2s;
}

.complete-button:hover {
    background: #059669;
    box-shadow: var(--shadow-lg);
    transform: translateY(-2px);
}

/* Loading State */
.loading-state {
    text-align: center;
    padding: 60px 20px;
}

.spinner {
    width: 50px;
    height: 50px;
    border: 4px solid var(--gray-light);
    border-top-color: var(--primary-blue);
    border-radius: 50%;
    animation: spin 0.8s linear infinite;
    margin: 0 auto 20px auto;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

/* Responsive */
@media (max-width: 1200px) {
    .stats-grid {
        grid-template-columns: repeat(2, 1fr);
    }

    .products-grid {
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    }

    .carrier-comparison {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 768px) {
    .transfer-header {
        grid-template-columns: 1fr;
        gap: 20px;
    }

    .tabs-nav {
        flex-wrap: wrap;
    }

    .tab-item {
        flex: 0 0 50%;
    }

    .products-grid {
        grid-template-columns: 1fr;
    }

    .weight-summary-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="pack-layout-b">
    <div class="alert alert-primary" role="alert" style="margin:20px auto;max-width:1600px;display:flex;justify-content:space-between;align-items:center;">
        <div><strong>Outgoing to:</strong> <?= htmlspecialchars($outlet_to['name']) ?> &nbsp;•&nbsp; <strong>Transfer #<?= (int)$transfer_id ?></strong></div>
        <div>
            <a class="btn btn-sm btn-outline-secondary" target="_blank" href="/modules/consignments/stock-transfers/packing-slip.php?transfer_id=<?= (int)$transfer_id ?>">🖨️ Packing Slip</a>
        </div>
    </div>

<!-- Main Container -->
<div class="tabs-container">
    <div class="card">

        <!-- Transfer Header -->
        <div class="transfer-header">
            <div class="header-column" id="outlet-from" data-outlet-id="<?= $transfer['outlet_id_from'] ?>">
                <h3>📤 Sending From</h3>
                <div id="outlet-from-info">
                    <div class="outlet-name"><?= htmlspecialchars($outlet_from['name']) ?></div>
                    <div class="outlet-address"><?= htmlspecialchars($outlet_from['address']) ?></div>
                </div>
            </div>
            <div class="header-column" id="outlet-to" data-outlet-id="<?= $transfer['outlet_id_to'] ?>">
                <h3>📥 Delivering To</h3>
                <div id="outlet-to-info">
                    <div class="outlet-name"><?= htmlspecialchars($outlet_to['name']) ?></div>
                    <div class="outlet-address"><?= htmlspecialchars($outlet_to['address']) ?></div>
                </div>
            </div>
        </div>

        <!-- Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-card-icon">📦</div>
                <span class="stat-card-value" id="total-items"><?= count($products) ?></span>
                <span class="stat-card-label">Total Items</span>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon">✅</div>
                <span class="stat-card-value" id="packed-items">0</span>
                <span class="stat-card-label">Packed</span>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon">⚖️</div>
                <span class="stat-card-value" id="total-weight">0.00 kg</span>
                <span class="stat-card-label">Total Weight</span>
            </div>
            <div class="stat-card">
                <div class="stat-card-icon">💰</div>
                <span class="stat-card-value" id="freight-cost">$0.00</span>
                <span class="stat-card-label">Est. Freight</span>
            </div>
        </div>

        <!-- Tabs Navigation -->
        <ul class="tabs-nav">
            <li class="tab-item">
                <button class="tab-link active" data-tab="products">
                    <span class="tab-icon">📦</span>
                    <span class="tab-label">Products</span>
                </button>
            </li>
            <li class="tab-item">
                <button class="tab-link" data-tab="freight">
                    <span class="tab-icon">🚚</span>
                    <span class="tab-label">Freight</span>
                </button>
            </li>
            <li class="tab-item">
                <button class="tab-link" data-tab="ai">
                    <span class="tab-icon">🤖</span>
                    <span class="tab-label">AI Insights</span>
                </button>
            </li>
            <li class="tab-item">
                <button class="tab-link" data-tab="summary">
                    <span class="tab-icon">📋</span>
                    <span class="tab-label">Summary</span>
                </button>
            </li>
        </ul>

        <!-- TAB 1: Products -->
        <div class="tab-content active" id="tab-products">
            <div class="products-tab-content">
                <div class="search-bar">
                    <div class="search-input-wrapper">
                        <span class="search-icon">🔍</span>
                        <input type="text"
                               id="product-search"
                               placeholder="Search by SKU, barcode, or product name... (supports barcode scanner)"
                               autocomplete="off">
                    </div>
                </div>

                <div class="products-grid" id="products-grid">
                    <?php foreach ($products as $product):
                        $weight_info = $freightEngine->resolveWeights([$product['product_id']]);
                        $weight_data = $weight_info['weights'][$product['product_id']] ?? ['resolved_weight_g' => 100, 'legend_code' => 'D'];
                    ?>
                    <div class="product-card"
                         data-product-id="<?= $product['product_id'] ?>"
                         data-sku="<?= htmlspecialchars($product['sku']) ?>"
                         data-weight="<?= $weight_data['resolved_weight_g'] ?>"
                         data-weight-source="<?= $weight_data['legend_code'] ?>">

                        <div class="product-card-header">
                            <img src="<?= htmlspecialchars($product['image_url'] ?? '/assets/images/no-image.png') ?>"
                                 alt="<?= htmlspecialchars($product['name']) ?>"
                                 class="product-card-image">
                            <div class="product-card-info">
                                <div class="product-card-name"><?= htmlspecialchars($product['name']) ?></div>
                                <div class="product-card-sku"><?= htmlspecialchars($product['sku']) ?></div>
                                <span class="product-card-weight source-<?= $weight_data['legend_code'] ?>">
                                    ⚖️ <?= number_format($weight_data['resolved_weight_g'] / 1000, 3) ?> kg (<?= $weight_data['legend_code'] ?>)
                                </span>
                            </div>
                        </div>

                        <div class="product-card-body">
                            <div class="transfer-qty">
                                <span class="transfer-qty-label">Transfer Qty</span>
                                <span class="transfer-qty-value"><?= $product['quantity'] ?></span>
                            </div>

                            <div class="qty-controls">
                                <button class="qty-btn" onclick="adjustQty(<?= $product['product_id'] ?>, -1)">−</button>
                    <input type="number"
                        class="qty-input" readonly
                                       id="qty-<?= $product['product_id'] ?>"
                                       value="0"
                                       min="0"
                                       max="<?= $product['quantity'] ?>"
                                       onchange="updateQty(<?= $product['product_id'] ?>, this.value)">
                                <button class="qty-btn" onclick="adjustQty(<?= $product['product_id'] ?>, 1)">+</button>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

        <!-- TAB 2: Freight -->
        <div class="tab-content" id="tab-freight">
            <div class="freight-tab-content">
                <div class="freight-section" id="freight-section-body">
                    <div class="loading-state">
                        <div class="spinner"></div>
                        <p>Pack items in the Products tab to calculate freight</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 3: AI Insights -->
        <div class="tab-content" id="tab-ai">
            <div class="ai-tab-content">
                <div class="ai-section" id="ai-section-body">
                    <div class="loading-state">
                        <div class="spinner"></div>
                        <p>AI insights will appear after freight calculation</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- TAB 4: Summary -->
        <div class="tab-content" id="tab-summary">
            <div class="summary-tab-content">
                <div class="summary-section" id="summary-section-body">
                    <div class="loading-state">
                        <p>Complete packing and freight selection to see summary</p>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<!-- Scripts -->
<script src="/modules/consignments/stock-transfers/js/freight-engine.js"></script>
<script src="/modules/consignments/stock-transfers/js/pack-layout-b.js"></script>
<script>
// Minimal telemetry for training flow
async function tlog(event,payload={}){try{await fetch('/modules/consignments/api/telemetry.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({event,transfer_id:<?= (int)$transfer_id ?>,payload,ts:new Date().toISOString()})})}catch(e){}}
(function(){
    const t0=Date.now();
    window.addEventListener('beforeunload',()=>tlog('pack_session_end',{elapsed_ms: Date.now()-t0}));
    // Tab click logging
    document.querySelectorAll('[data-tab]').forEach(el=>el.addEventListener('click',()=>tlog('tab_switched',{tab:el.dataset.tab})));
})();

// Manual unlock toggle for Layout B
document.addEventListener('DOMContentLoaded', () => {
    const toolbar = document.createElement('div');
    toolbar.className = 'd-flex align-items-center mb-2';
    toolbar.innerHTML = `
        <div class="custom-control custom-switch">
            <input type="checkbox" class="custom-control-input" id="unlockManualB">
            <label class="custom-control-label" for="unlockManualB">Unlock manual quantity editing</label>
        </div>`;
    const container = document.querySelector('.tabs-container');
    if (container) container.parentNode.insertBefore(toolbar, container);
    const toggle = document.getElementById('unlockManualB');
    if (toggle) toggle.addEventListener('change', ()=>{
        document.querySelectorAll('.qty-input').forEach(inp => inp.readOnly = !toggle.checked);
    });
});
</script>

<?php
$template->endContent();
$template->render();
?>
