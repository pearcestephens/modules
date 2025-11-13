<?php
/**
 * Advanced Transfer Packing - LAYOUT C: Professional Accordion
 * Collapsible panels with floating action bar and maximum space efficiency
 */

// Corrected theme include path (was _templates)
require_once __DIR__ . '/../../base/templates/themes/cis-classic/theme.php';

$theme = new CISClassicTheme();
$theme->setTitle('Pack Transfer #12345');
$theme->setPageSubtitle('Layout C: Accordion Panels - Professional');
$theme->showTimestamps = true;

$theme->addBreadcrumb('Consignments', '/modules/consignments/');
$theme->addBreadcrumb('Stock Transfers', '/modules/consignments/stock-transfers/');
$theme->addBreadcrumb('Pack Transfer', null);

$theme->addHeaderButton('Save', 'btn-sm btn-secondary', 'javascript:saveDraft()', 'fa-save');
$theme->addHeaderButton('AI Optimize', 'btn-sm btn-info', 'javascript:openAIAdvisor()', 'fa-robot');
?>

<?php $theme->render('html-head'); ?>
<?php $theme->render('header'); ?>
<?php $theme->render('sidebar'); ?>
<?php $theme->render('main-start'); ?>

<style>
/* Professional & Compact Design */
body {
    font-size: 13px;
    line-height: 1.4;
    padding-bottom: 100px; /* Space for floating bar */
}

.pack-container {
    max-width: 1600px;
    margin: 0 auto;
    padding: 12px;
    background: #f6f8fa;
}

/* Quick Stats Pills */
.quick-stats {
    display: flex;
    gap: 10px;
    margin-bottom: 12px;
    flex-wrap: wrap;
}

.stat-pill {
    display: flex;
    align-items: center;
    gap: 8px;
    background: #fff;
    border: 1px solid #e1e4e8;
    padding: 8px 12px;
    border-radius: 20px;
    font-size: 12px;
}

.stat-pill i {
    color: #0366d6;
}

.stat-pill .label {
    color: #6a737d;
    font-weight: 500;
}

.stat-pill .value {
    color: #24292e;
    font-weight: 600;
}

.stat-pill.success { border-color: #28a745; background: #f0f9f4; }
.stat-pill.success i { color: #28a745; }

.stat-pill.warning { border-color: #ffc107; background: #fffbeb; }
.stat-pill.warning i { color: #ffc107; }

/* Accordion Container */
.accordion-container {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

/* Accordion Panel */
.accordion-panel {
    background: #fff;
    border: 1px solid #d1d5db;
    border-radius: 3px;
    overflow: hidden;
}

.accordion-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 15px;
    cursor: pointer;
    background: #fafbfc;
    border-bottom: 1px solid #e1e4e8;
    transition: all 0.2s;
}

.accordion-header:hover {
    background: #f3f4f6;
}

.accordion-header.active {
    background: #e3f2fd;
    border-bottom-color: #0366d6;
}

.accordion-title {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 14px;
    font-weight: 600;
    color: #24292e;
}

.accordion-title i {
    font-size: 16px;
    color: #0366d6;
}

.accordion-meta {
    display: flex;
    align-items: center;
    gap: 15px;
}

.accordion-badge {
    display: inline-block;
    padding: 3px 8px;
    border-radius: 3px;
    font-size: 11px;
    font-weight: 600;
}

.badge-primary { background: #e3f2fd; color: #0366d6; }
.badge-success { background: #d4edda; color: #28a745; }
.badge-warning { background: #fff3cd; color: #856404; }
.badge-danger { background: #f8d7da; color: #721c24; }

.accordion-toggle {
    font-size: 14px;
    color: #6a737d;
    transition: transform 0.2s;
}

.accordion-header.active .accordion-toggle {
    transform: rotate(180deg);
}

/* Accordion Content */
.accordion-content {
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease-out;
}

.accordion-content.active {
    max-height: 2000px;
}

.accordion-body {
    padding: 15px;
}

/* Products Table */
.products-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 12px;
}

.products-table thead {
    background: #fafbfc;
    border-bottom: 2px solid #e1e4e8;
}

.products-table th {
    padding: 8px 10px;
    text-align: left;
    font-size: 11px;
    font-weight: 600;
    color: #586069;
    text-transform: uppercase;
}

.products-table td {
    padding: 10px;
    border-bottom: 1px solid #e1e4e8;
}

.products-table tbody tr:hover {
    background: #f6f8fa;
}

.product-img-sm {
    width: 36px;
    height: 36px;
    object-fit: cover;
    border-radius: 3px;
    border: 1px solid #e1e4e8;
}

.product-name-col {
    display: flex;
    align-items: center;
    gap: 10px;
}

.product-info {
    display: flex;
    flex-direction: column;
}

.product-name {
    font-weight: 600;
    color: #24292e;
    font-size: 12px;
}

.product-sku {
    font-family: 'Courier New', monospace;
    font-size: 10px;
    color: #6a737d;
}

.input-compact {
    width: 60px;
    padding: 4px 6px;
    font-size: 12px;
    border: 1px solid #e1e4e8;
    border-radius: 3px;
    text-align: center;
}

.select-compact {
    padding: 4px 8px;
    font-size: 12px;
    border: 1px solid #e1e4e8;
    border-radius: 3px;
}

/* Freight Details Grid */
.freight-details {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.freight-card {
    background: #fafbfc;
    border: 1px solid #e1e4e8;
    padding: 12px;
    border-radius: 3px;
}

.freight-card h6 {
    font-size: 11px;
    font-weight: 600;
    color: #586069;
    margin: 0 0 10px 0;
    text-transform: uppercase;
}

.metric-item {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    font-size: 12px;
    border-bottom: 1px solid #e1e4e8;
}

.metric-item:last-child {
    border-bottom: none;
}

.metric-item .label {
    color: #6a737d;
}

.metric-item .value {
    font-weight: 600;
    color: #24292e;
}

/* Tracking System */
.tracking-system {
    background: #fff8e1;
    border: 1px solid #ffc107;
    border-radius: 3px;
    padding: 12px;
    margin-bottom: 12px;
}

.tracking-info {
    display: flex;
    align-items: center;
    gap: 10px;
    font-size: 12px;
    color: #856404;
}

.tracking-info i {
    font-size: 16px;
}

.tracking-status {
    display: flex;
    align-items: center;
    gap: 15px;
    margin-top: 8px;
}

.tracking-count {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 6px 10px;
    background: #fff;
    border-radius: 3px;
    font-size: 12px;
    font-weight: 600;
}

.tracking-count.complete {
    background: #d4edda;
    color: #28a745;
}

/* Carrier Options */
.carrier-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}

.carrier-card {
    background: #fff;
    border: 2px solid #e1e4e8;
    border-radius: 3px;
    padding: 12px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
}

.carrier-card:hover {
    border-color: #0366d6;
}

.carrier-card.selected {
    border-color: #28a745;
    background: #f0f9f4;
}

.carrier-card-name {
    font-weight: 600;
    font-size: 13px;
    color: #24292e;
    margin-bottom: 4px;
}

.carrier-card-meta {
    font-size: 10px;
    color: #6a737d;
    margin-bottom: 8px;
}

.carrier-card-price {
    font-size: 18px;
    font-weight: 600;
    color: #0366d6;
}

/* Tools Grid */
.tools-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}

.tool-card {
    background: #fafbfc;
    border: 1px solid #e1e4e8;
    padding: 12px;
    border-radius: 3px;
    cursor: pointer;
    transition: all 0.2s;
    text-align: center;
}

.tool-card:hover {
    border-color: #0366d6;
    box-shadow: 0 2px 8px rgba(3, 102, 214, 0.15);
}

.tool-card i {
    font-size: 24px;
    color: #0366d6;
    margin-bottom: 8px;
}

.tool-card-name {
    font-weight: 600;
    font-size: 12px;
    color: #24292e;
    margin-bottom: 4px;
}

.tool-card-desc {
    font-size: 10px;
    color: #6a737d;
}

/* Floating Action Bar */
.floating-action-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    background: #fff;
    border-top: 2px solid #e1e4e8;
    padding: 15px 20px;
    display: flex;
    justify-content: space-between;
    align-items: center;
    z-index: 1000;
    box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.08);
}

.action-bar-left {
    display: flex;
    align-items: center;
    gap: 20px;
}

.progress-mini {
    display: flex;
    flex-direction: column;
    gap: 4px;
}

.progress-mini-label {
    font-size: 11px;
    color: #6a737d;
    font-weight: 600;
}

.progress-mini-bar {
    width: 200px;
    height: 6px;
    background: #e1e4e8;
    border-radius: 3px;
    overflow: hidden;
}

.progress-mini-fill {
    height: 100%;
    background: #28a745;
    transition: width 0.3s;
}

.action-stats {
    display: flex;
    gap: 15px;
}

.action-stat {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.action-stat-value {
    font-size: 18px;
    font-weight: 600;
    color: #24292e;
}

.action-stat-label {
    font-size: 10px;
    color: #6a737d;
    text-transform: uppercase;
}

.action-bar-right {
    display: flex;
    gap: 10px;
}

.btn-action {
    padding: 10px 20px;
    border: none;
    border-radius: 3px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
    transition: all 0.2s;
}

.btn-action i {
    margin-right: 6px;
}

.btn-action-secondary {
    background: #6c757d;
    color: #fff;
}

.btn-action-secondary:hover {
    background: #5a6268;
}

.btn-action-primary {
    background: #0366d6;
    color: #fff;
}

.btn-action-primary:hover {
    background: #0256b8;
}

.btn-action-success {
    background: #28a745;
    color: #fff;
    font-size: 14px;
    padding: 12px 30px;
}

.btn-action-success:hover {
    background: #218838;
}

/* Responsive */
@media (max-width: 1200px) {
    .freight-details,
    .carrier-grid,
    .tools-grid {
        grid-template-columns: 1fr;
    }

    .floating-action-bar {
        flex-direction: column;
        gap: 15px;
        padding: 15px;
    }

    .action-bar-left,
    .action-bar-right {
        width: 100%;
        justify-content: center;
    }
}
</style>

<div class="pack-container">
    <!-- Quick Stats Pills -->
    <div class="quick-stats">
        <div class="stat-pill">
            <i class="fa fa-box"></i>
            <span class="label">Items:</span>
            <span class="value">32</span>
        </div>
        <div class="stat-pill success">
            <i class="fa fa-check-circle"></i>
            <span class="label">Packed:</span>
            <span class="value">21 / 32</span>
        </div>
        <div class="stat-pill">
            <i class="fa fa-boxes"></i>
            <span class="label">Boxes:</span>
            <span class="value">3</span>
        </div>
        <div class="stat-pill">
            <i class="fa fa-weight"></i>
            <span class="label">Weight:</span>
            <span class="value">15.7kg</span>
        </div>
        <div class="stat-pill warning">
            <i class="fa fa-dollar-sign"></i>
            <span class="label">Est. Freight:</span>
            <span class="value">$45.80</span>
        </div>
    </div>

    <!-- Accordion Container -->
    <div class="accordion-container">
        <!-- Products Panel -->
        <div class="accordion-panel">
            <div class="accordion-header active" onclick="toggleAccordion(this)">
                <div class="accordion-title">
                    <i class="fa fa-box"></i>
                    <span>Products & Packing</span>
                </div>
                <div class="accordion-meta">
                    <span class="accordion-badge badge-warning">11 Remaining</span>
                    <span class="accordion-badge badge-success">21 Packed</span>
                    <i class="fa fa-chevron-down accordion-toggle"></i>
                </div>
            </div>
            <div class="accordion-content active">
                <div class="accordion-body">
                    <table class="products-table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Planned</th>
                                <th>Packed</th>
                                <th>Box</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <div class="product-name-col">
                                        <img src="/placeholder.jpg" alt="" class="product-img-sm">
                                        <div class="product-info">
                                            <span class="product-name">Vaporesso XROS 3 Pod Kit</span>
                                            <span class="product-sku">VP-XR3-BLK</span>
                                        </div>
                                    </div>
                                </td>
                                <td>10</td>
                                <td><input type="number" class="input-compact" value="10"></td>
                                <td>
                                    <select class="select-compact">
                                        <option>Box 1</option>
                                        <option>Box 2</option>
                                        <option>Box 3</option>
                                    </select>
                                </td>
                                <td><span class="accordion-badge badge-success">Complete</span></td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="product-name-col">
                                        <img src="/placeholder.jpg" alt="" class="product-img-sm">
                                        <div class="product-info">
                                            <span class="product-name">SMOK Nord 5 Replacement Pods</span>
                                            <span class="product-sku">SM-N5-POD</span>
                                        </div>
                                    </div>
                                </td>
                                <td>25</td>
                                <td><input type="number" class="input-compact" value="20"></td>
                                <td>
                                    <select class="select-compact">
                                        <option>Box 2</option>
                                        <option>Box 1</option>
                                        <option>Box 3</option>
                                    </select>
                                </td>
                                <td><span class="accordion-badge badge-warning">Under</span></td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="product-name-col">
                                        <img src="/placeholder.jpg" alt="" class="product-img-sm">
                                        <div class="product-info">
                                            <span class="product-name">Caliburn G2 Coils 0.8Ω</span>
                                            <span class="product-sku">UV-G2-08</span>
                                        </div>
                                    </div>
                                </td>
                                <td>15</td>
                                <td><input type="number" class="input-compact" value="18"></td>
                                <td>
                                    <select class="select-compact">
                                        <option>Box 3</option>
                                        <option>Box 1</option>
                                        <option>Box 2</option>
                                    </select>
                                </td>
                                <td><span class="accordion-badge badge-danger">Over</span></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Freight Panel -->
        <div class="accordion-panel">
            <div class="accordion-header" onclick="toggleAccordion(this)">
                <div class="accordion-title">
                    <i class="fa fa-truck"></i>
                    <span>Freight & Tracking</span>
                </div>
                <div class="accordion-meta">
                    <span class="accordion-badge badge-primary">3 Boxes = 3 Tracking Numbers</span>
                    <i class="fa fa-chevron-down accordion-toggle"></i>
                </div>
            </div>
            <div class="accordion-content">
                <div class="accordion-body">
                    <!-- Tracking System Alert -->
                    <div class="tracking-system">
                        <div class="tracking-info">
                            <i class="fa fa-info-circle"></i>
                            <div>
                                <strong>Automated Tracking System:</strong> When you click "Generate Labels", the system will automatically create tracking numbers via the courier API - one tracking number per box.
                            </div>
                        </div>
                        <div class="tracking-status">
                            <div class="tracking-count">
                                <i class="fa fa-boxes"></i>
                                <span>Boxes: 3</span>
                            </div>
                            <div class="tracking-count">
                                <i class="fa fa-arrow-right"></i>
                            </div>
                            <div class="tracking-count complete">
                                <i class="fa fa-check-circle"></i>
                                <span>Tracking Numbers: 3 will be generated</span>
                            </div>
                        </div>
                    </div>

                    <div class="freight-details">
                        <!-- Metrics -->
                        <div class="freight-card">
                            <h6><i class="fa fa-calculator"></i> Freight Metrics</h6>
                            <div class="metric-item">
                                <span class="label">Total Weight:</span>
                                <span class="value">15.7 kg</span>
                            </div>
                            <div class="metric-item">
                                <span class="label">Total Volume:</span>
                                <span class="value">0.045 m³</span>
                            </div>
                            <div class="metric-item">
                                <span class="label">Number of Boxes:</span>
                                <span class="value">3 boxes</span>
                            </div>
                            <div class="metric-item">
                                <span class="label">Distance:</span>
                                <span class="value">648 km</span>
                            </div>
                        </div>

                        <!-- Box Details -->
                        <div class="freight-card">
                            <h6><i class="fa fa-boxes"></i> Box Details</h6>
                            <div class="metric-item">
                                <span class="label">Box 1:</span>
                                <span class="value">5.2kg • 12 items</span>
                            </div>
                            <div class="metric-item">
                                <span class="label">Box 2:</span>
                                <span class="value">6.8kg • 15 items</span>
                            </div>
                            <div class="metric-item">
                                <span class="label">Box 3:</span>
                                <span class="value">3.7kg • 5 items</span>
                            </div>
                        </div>
                    </div>

                    <h6 style="margin: 20px 0 12px 0; font-size: 12px; font-weight: 600; color: #586069;">SELECT CARRIER</h6>
                    <div class="carrier-grid">
                        <div class="carrier-card selected">
                            <div class="carrier-card-name">NZ Post</div>
                            <div class="carrier-card-meta">2-3 days • Recommended</div>
                            <div class="carrier-card-price">$45.80</div>
                        </div>
                        <div class="carrier-card">
                            <div class="carrier-card-name">GoSweetSpot</div>
                            <div class="carrier-card-meta">3-4 days • Cheapest</div>
                            <div class="carrier-card-price">$42.50</div>
                        </div>
                        <div class="carrier-card">
                            <div class="carrier-card-name">CourierPost</div>
                            <div class="carrier-card-meta">1-2 days • Fastest</div>
                            <div class="carrier-card-price">$58.20</div>
                        </div>
                    </div>

                    <button style="width: 100%; padding: 12px; background: #6c757d; color: #fff; border: none; border-radius: 3px; font-weight: 600; margin-top: 15px; cursor: pointer; margin-bottom: 8px;" onclick="window.open('print-box-labels.php?transfer_id=12345', '_blank')">
                        <i class="fa fa-box"></i> Print Box Labels (Internal Use)
                    </button>

                    <button style="width: 100%; padding: 12px; background: #0366d6; color: #fff; border: none; border-radius: 3px; font-weight: 600; cursor: pointer;">
                        <i class="fa fa-tag"></i> Generate Shipping Labels via API
                    </button>
                    <small style="display: block; margin-top: 8px; font-size: 11px; color: #6a737d; text-align: center;">
                        Creates: Shipment → 3 Parcels (1 per box with tracking) → Parcel Items
                    </small>
                </div>
            </div>
        </div>

        <!-- Tools Panel -->
        <div class="accordion-panel">
            <div class="accordion-header" onclick="toggleAccordion(this)">
                <div class="accordion-title">
                    <i class="fa fa-tools"></i>
                    <span>Packing Tools</span>
                </div>
                <div class="accordion-meta">
                    <span class="accordion-badge badge-primary">7 Available</span>
                    <i class="fa fa-chevron-down accordion-toggle"></i>
                </div>
            </div>
            <div class="accordion-content">
                <div class="accordion-body">
                    <div class="tools-grid">
                        <div class="tool-card" onclick="window.open('print-box-labels.php?transfer_id=12345', '_blank')">
                            <i class="fa fa-box"></i>
                            <div class="tool-card-name">Box Labels (Internal)</div>
                            <div class="tool-card-desc">Print ID labels for warehouse</div>
                        </div>

                        <div class="tool-card">
                            <i class="fa fa-file-alt"></i>
                            <div class="tool-card-name">Packing Slip</div>
                            <div class="tool-card-desc">Print preview with signature</div>
                        </div>
                        <div class="tool-card">
                            <i class="fa fa-envelope"></i>
                            <div class="tool-card-name">Email Summary</div>
                            <div class="tool-card-desc">Send to destination store</div>
                        </div>
                        <div class="tool-card">
                            <i class="fa fa-camera"></i>
                            <div class="tool-card-name">Photo Evidence</div>
                            <div class="tool-card-desc">Upload after packing</div>
                        </div>
                        <div class="tool-card">
                            <i class="fa fa-magic"></i>
                            <div class="tool-card-name">Auto-Assign</div>
                            <div class="tool-card-desc">Distribute items to boxes</div>
                        </div>
                        <div class="tool-card">
                            <i class="fa fa-robot"></i>
                            <div class="tool-card-name">AI Optimization</div>
                            <div class="tool-card-desc">Get recommendations</div>
                        </div>
                        <div class="tool-card">
                            <i class="fa fa-cog"></i>
                            <div class="tool-card-name">Settings</div>
                            <div class="tool-card-desc">Configure preferences</div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Floating Action Bar -->
<div class="floating-action-bar">
    <div class="action-bar-left">
        <div class="progress-mini">
            <div class="progress-mini-label">Packing Progress</div>
            <div class="progress-mini-bar">
                <div class="progress-mini-fill" style="width: 65%;"></div>
            </div>
        </div>
        <div class="action-stats">
            <div class="action-stat">
                <div class="action-stat-value">21/32</div>
                <div class="action-stat-label">Packed</div>
            </div>
            <div class="action-stat">
                <div class="action-stat-value">3</div>
                <div class="action-stat-label">Boxes</div>
            </div>
            <div class="action-stat">
                <div class="action-stat-value">15.7kg</div>
                <div class="action-stat-label">Weight</div>
            </div>
        </div>
    </div>
    <div class="action-bar-right">
        <button class="btn-action btn-action-secondary" onclick="saveDraft()">
            <i class="fa fa-save"></i> Save Draft
        </button>
        <button class="btn-action btn-action-primary" onclick="openAIAdvisor()">
            <i class="fa fa-robot"></i> AI Optimize
        </button>
        <button class="btn-action btn-action-success" onclick="finishPacking()">
            <i class="fa fa-check-circle"></i> Finish & Create Tracking
        </button>
    </div>
</div>

<script>
function toggleAccordion(header) {
    const content = header.nextElementSibling;
    const isActive = header.classList.contains('active');

    // Close all panels
    document.querySelectorAll('.accordion-header').forEach(h => {
        h.classList.remove('active');
        h.nextElementSibling.classList.remove('active');
    });

    // Open clicked panel if it wasn't active
    if (!isActive) {
        header.classList.add('active');
        content.classList.add('active');
    }
}

function saveDraft() { alert('Draft saved!'); }
function openAIAdvisor() { alert('AI Advisor opened'); }
function finishPacking() {
    if (confirm('Generate 3 tracking numbers via courier API and finish packing?')) {
        alert('Creating shipment with 3 parcels, each with tracking number from courier API...');
    }
}
</script>

<?php $theme->render('footer'); ?>
