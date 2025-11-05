<?php
/**
 * Advanced Transfer Packing - LAYOUT B: Professional Tabs
 * Tab-based navigation with maximum space efficiency
 */

require_once __DIR__ . '/../../base/_templates/themes/cis-classic/theme.php';

$theme = new CISClassicTheme();
$theme->setTitle('Pack Transfer #12345');
$theme->setPageSubtitle('Layout B: Horizontal Tabs - Professional');
$theme->showTimestamps = true;

$theme->addBreadcrumb('Consignments', '/modules/consignments/');
$theme->addBreadcrumb('Stock Transfers', '/modules/consignments/stock-transfers/');
$theme->addBreadcrumb('Pack Transfer', null);

$theme->addHeaderButton('Save', 'btn-sm btn-secondary', 'javascript:saveDraft()', 'fa-save');
$theme->addHeaderButton('AI Optimize', 'btn-sm btn-info', 'javascript:openAIAdvisor()', 'fa-robot');
$theme->addHeaderButton('Finish Packing', 'btn-sm btn-success', 'javascript:finishPacking()', 'fa-check');
?>

<?php $theme->render('html-head'); ?>
<?php $theme->render('header'); ?>
<?php $theme->render('sidebar'); ?>
<?php $theme->render('main-start'); ?>

<style>
/* Professional & Compact Design */
body { font-size: 13px; line-height: 1.4; }

.pack-container {
    max-width: 1600px;
    margin: 0 auto;
    padding: 12px;
    background: #f6f8fa;
}

/* Compact Progress Bar */
.progress-header {
    background: #fff;
    border: 1px solid #d1d5db;
    padding: 15px;
    border-radius: 3px;
    margin-bottom: 12px;
}

.progress-header h3 {
    font-size: 15px;
    font-weight: 600;
    margin: 0 0 10px 0;
    color: #24292e;
}

.progress-bar-custom {
    height: 8px;
    background: #e1e4e8;
    border-radius: 4px;
    overflow: hidden;
}

.progress-bar-fill {
    height: 100%;
    background: #28a745;
    transition: width 0.3s;
}

.stats-inline {
    display: flex;
    gap: 20px;
    margin-top: 12px;
}

.stat-compact {
    display: flex;
    align-items: center;
    gap: 8px;
}

.stat-compact .icon {
    width: 32px;
    height: 32px;
    border-radius: 3px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 14px;
}

.stat-compact .icon.primary { background: #e3f2fd; color: #0366d6; }
.stat-compact .icon.success { background: #d4edda; color: #28a745; }
.stat-compact .icon.warning { background: #fff3cd; color: #ffc107; }

.stat-compact .text {
    display: flex;
    flex-direction: column;
    line-height: 1.2;
}

.stat-compact .label {
    font-size: 10px;
    color: #6a737d;
    text-transform: uppercase;
    font-weight: 600;
}

.stat-compact .value {
    font-size: 16px;
    font-weight: 600;
    color: #24292e;
}

/* Tab Navigation */
.tab-nav {
    background: #fff;
    border: 1px solid #d1d5db;
    border-radius: 3px 3px 0 0;
    display: flex;
}

.tab-btn {
    flex: 1;
    padding: 10px 15px;
    border: none;
    background: #fafbfc;
    border-right: 1px solid #e1e4e8;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    color: #586069;
    transition: all 0.2s;
}

.tab-btn:last-child {
    border-right: none;
}

.tab-btn:hover {
    background: #f3f4f6;
}

.tab-btn.active {
    background: #fff;
    color: #0366d6;
    border-bottom: 2px solid #0366d6;
    font-weight: 600;
}

.tab-badge {
    display: inline-block;
    background: #dc3545;
    color: #fff;
    border-radius: 10px;
    padding: 2px 6px;
    font-size: 10px;
    font-weight: 600;
    margin-left: 6px;
}

/* Tab Content */
.tab-content {
    background: #fff;
    border: 1px solid #d1d5db;
    border-top: none;
    border-radius: 0 0 3px 3px;
    padding: 15px;
    min-height: 400px;
}

.tab-pane {
    display: none;
}

.tab-pane.active {
    display: block;
}

/* Search Bar in Tab */
.search-in-tab {
    padding: 8px 12px;
    font-size: 13px;
    border: 1px solid #e1e4e8;
    border-radius: 3px;
    width: 100%;
    margin-bottom: 15px;
}

.search-in-tab:focus {
    border-color: #0366d6;
    outline: none;
    box-shadow: 0 0 0 2px rgba(3, 102, 214, 0.1);
}

/* Product Grid */
.product-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 12px;
}

.product-card {
    border: 1px solid #e1e4e8;
    border-radius: 3px;
    padding: 12px;
    background: #fafbfc;
    transition: all 0.2s;
}

.product-card:hover {
    border-color: #0366d6;
    box-shadow: 0 2px 8px rgba(3, 102, 214, 0.15);
}

.product-card.packed {
    border-color: #28a745;
    background: #f0f9f4;
}

.product-card.under {
    border-color: #ffc107;
    background: #fffbeb;
}

.product-card.over {
    border-color: #dc3545;
    background: #fef2f2;
}

.product-card-header {
    display: flex;
    gap: 12px;
    margin-bottom: 12px;
}

.product-card-img {
    width: 60px;
    height: 60px;
    object-fit: cover;
    border-radius: 3px;
    border: 1px solid #e1e4e8;
}

.product-card-info {
    flex: 1;
}

.product-card-name {
    font-weight: 600;
    font-size: 13px;
    color: #24292e;
    margin-bottom: 4px;
}

.product-card-sku {
    font-family: 'Courier New', monospace;
    font-size: 11px;
    color: #6a737d;
}

.product-card-badge {
    display: inline-block;
    padding: 2px 8px;
    border-radius: 3px;
    font-size: 10px;
    font-weight: 600;
    margin-top: 4px;
}

.badge-success { background: #d4edda; color: #28a745; }
.badge-warning { background: #fff3cd; color: #856404; }
.badge-danger { background: #f8d7da; color: #721c24; }

.product-card-inputs {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 8px;
}

.input-group-compact {
    display: flex;
    flex-direction: column;
}

.input-group-compact label {
    font-size: 10px;
    font-weight: 600;
    color: #586069;
    margin-bottom: 4px;
    text-transform: uppercase;
}

.input-group-compact input,
.input-group-compact select {
    padding: 6px 8px;
    font-size: 12px;
    border: 1px solid #e1e4e8;
    border-radius: 3px;
}

/* Freight Grid */
.freight-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 15px;
}

.freight-box {
    background: #fafbfc;
    border: 1px solid #e1e4e8;
    padding: 15px;
    border-radius: 3px;
}

.freight-box h6 {
    font-size: 12px;
    font-weight: 600;
    color: #24292e;
    margin: 0 0 12px 0;
    text-transform: uppercase;
}

.metric-row {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    font-size: 12px;
    border-bottom: 1px solid #e1e4e8;
}

.metric-row:last-child {
    border-bottom: none;
}

.carrier-option {
    background: #fff;
    border: 1px solid #e1e4e8;
    border-radius: 3px;
    padding: 10px;
    margin-bottom: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.carrier-option:hover {
    border-color: #0366d6;
}

.carrier-option.selected {
    border-color: #28a745;
    background: #f0f9f4;
}

.carrier-option-content {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.carrier-name {
    font-weight: 600;
    font-size: 13px;
    color: #24292e;
}

.carrier-meta {
    font-size: 11px;
    color: #6a737d;
    margin-top: 2px;
}

.carrier-price {
    font-size: 16px;
    font-weight: 600;
    color: #0366d6;
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

/* Tools Grid */
.tools-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
}

.tool-card {
    background: #fafbfc;
    border: 1px solid #e1e4e8;
    padding: 15px;
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
    font-size: 28px;
    color: #0366d6;
    margin-bottom: 8px;
}

.tool-card-name {
    font-weight: 600;
    font-size: 13px;
    color: #24292e;
    margin-bottom: 4px;
}

.tool-card-desc {
    font-size: 11px;
    color: #6a737d;
}

/* Responsive */
@media (max-width: 992px) {
    .freight-grid {
        grid-template-columns: 1fr;
    }
    .product-grid {
        grid-template-columns: 1fr;
    }
    .tools-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="pack-container">
    <!-- Progress Header -->
    <div class="progress-header">
        <h3>Packing Transfer #12345 - Auckland to Wellington</h3>
        <div class="progress-bar-custom">
            <div class="progress-bar-fill" style="width: 65%;"></div>
        </div>

        <div class="stats-inline">
            <div class="stat-compact">
                <div class="icon primary"><i class="fa fa-box"></i></div>
                <div class="text">
                    <span class="label">Total Items</span>
                    <span class="value">32</span>
                </div>
            </div>
            <div class="stat-compact">
                <div class="icon success"><i class="fa fa-check"></i></div>
                <div class="text">
                    <span class="label">Packed</span>
                    <span class="value">21</span>
                </div>
            </div>
            <div class="stat-compact">
                <div class="icon primary"><i class="fa fa-boxes"></i></div>
                <div class="text">
                    <span class="label">Boxes</span>
                    <span class="value">3</span>
                </div>
            </div>
            <div class="stat-compact">
                <div class="icon success"><i class="fa fa-weight"></i></div>
                <div class="text">
                    <span class="label">Weight</span>
                    <span class="value">15.7kg</span>
                </div>
            </div>
            <div class="stat-compact">
                <div class="icon warning"><i class="fa fa-dollar-sign"></i></div>
                <div class="text">
                    <span class="label">Est. Freight</span>
                    <span class="value">$45.80</span>
                </div>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="tab-nav">
        <button class="tab-btn active" onclick="switchTab('products')">
            <i class="fa fa-box"></i> Products
            <span class="tab-badge">11</span>
        </button>
        <button class="tab-btn" onclick="switchTab('freight')">
            <i class="fa fa-truck"></i> Freight & Tracking
        </button>
        <button class="tab-btn" onclick="switchTab('tools')">
            <i class="fa fa-tools"></i> Tools
        </button>
    </div>

    <!-- Tab Content -->
    <div class="tab-content">
        <!-- Products Tab -->
        <div class="tab-pane active" id="tab-products">
            <input type="text" class="search-in-tab" placeholder="Search products by name, SKU, or barcode...">

            <div class="product-grid">
                <!-- Product Card 1 -->
                <div class="product-card packed">
                    <div class="product-card-header">
                        <img src="/placeholder.jpg" alt="" class="product-card-img">
                        <div class="product-card-info">
                            <div class="product-card-name">Vaporesso XROS 3 Pod Kit</div>
                            <div class="product-card-sku">VP-XR3-BLK</div>
                            <span class="product-card-badge badge-success">Fully Packed</span>
                        </div>
                    </div>
                    <div class="product-card-inputs">
                        <div class="input-group-compact">
                            <label>Planned</label>
                            <input type="number" value="10" readonly>
                        </div>
                        <div class="input-group-compact">
                            <label>Packed</label>
                            <input type="number" value="10">
                        </div>
                        <div class="input-group-compact">
                            <label>Box</label>
                            <select>
                                <option>Box 1</option>
                                <option>Box 2</option>
                                <option>Box 3</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Product Card 2 -->
                <div class="product-card under">
                    <div class="product-card-header">
                        <img src="/placeholder.jpg" alt="" class="product-card-img">
                        <div class="product-card-info">
                            <div class="product-card-name">SMOK Nord 5 Replacement Pods</div>
                            <div class="product-card-sku">SM-N5-POD</div>
                            <span class="product-card-badge badge-warning">Under Packed</span>
                        </div>
                    </div>
                    <div class="product-card-inputs">
                        <div class="input-group-compact">
                            <label>Planned</label>
                            <input type="number" value="25" readonly>
                        </div>
                        <div class="input-group-compact">
                            <label>Packed</label>
                            <input type="number" value="20">
                        </div>
                        <div class="input-group-compact">
                            <label>Box</label>
                            <select>
                                <option>Box 2</option>
                                <option>Box 1</option>
                                <option>Box 3</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Product Card 3 -->
                <div class="product-card over">
                    <div class="product-card-header">
                        <img src="/placeholder.jpg" alt="" class="product-card-img">
                        <div class="product-card-info">
                            <div class="product-card-name">Caliburn G2 Coils 0.8Ω</div>
                            <div class="product-card-sku">UV-G2-08</div>
                            <span class="product-card-badge badge-danger">Over Packed</span>
                        </div>
                    </div>
                    <div class="product-card-inputs">
                        <div class="input-group-compact">
                            <label>Planned</label>
                            <input type="number" value="15" readonly>
                        </div>
                        <div class="input-group-compact">
                            <label>Packed</label>
                            <input type="number" value="18">
                        </div>
                        <div class="input-group-compact">
                            <label>Box</label>
                            <select>
                                <option>Box 3</option>
                                <option>Box 1</option>
                                <option>Box 2</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Freight Tab -->
        <div class="tab-pane" id="tab-freight">
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

            <div class="freight-grid">
                <!-- Metrics -->
                <div class="freight-box">
                    <h6><i class="fa fa-calculator"></i> Freight Metrics</h6>
                    <div class="metric-row">
                        <span>Total Weight:</span>
                        <strong>15.7 kg</strong>
                    </div>
                    <div class="metric-row">
                        <span>Total Volume:</span>
                        <strong>0.045 m³</strong>
                    </div>
                    <div class="metric-row">
                        <span>Number of Boxes:</span>
                        <strong>3 boxes</strong>
                    </div>
                    <div class="metric-row">
                        <span>Distance:</span>
                        <strong>648 km</strong>
                    </div>
                </div>

                <!-- Carrier Selection -->
                <div class="freight-box">
                    <h6><i class="fa fa-shipping-fast"></i> Select Carrier</h6>

                    <div class="carrier-option selected">
                        <div class="carrier-option-content">
                            <div>
                                <div class="carrier-name">NZ Post</div>
                                <div class="carrier-meta">ETA: 2-3 business days • Recommended</div>
                            </div>
                            <div class="carrier-price">$45.80</div>
                        </div>
                    </div>

                    <div class="carrier-option">
                        <div class="carrier-option-content">
                            <div>
                                <div class="carrier-name">GoSweetSpot</div>
                                <div class="carrier-meta">ETA: 3-4 business days • Cheapest</div>
                            </div>
                            <div class="carrier-price">$42.50</div>
                        </div>
                    </div>

                    <div class="carrier-option">
                        <div class="carrier-option-content">
                            <div>
                                <div class="carrier-name">CourierPost</div>
                                <div class="carrier-meta">ETA: 1-2 business days • Fastest</div>
                            </div>
                            <div class="carrier-price">$58.20</div>
                        </div>
                    </div>

                    <button style="width: 100%; padding: 10px; background: #6c757d; color: #fff; border: none; border-radius: 3px; font-weight: 600; margin-top: 10px; cursor: pointer; margin-bottom: 8px;" onclick="window.open('print-box-labels.php?transfer_id=12345', '_blank')">
                        <i class="fa fa-box"></i> Print Box Labels (Internal Use)
                    </button>

                    <button style="width: 100%; padding: 10px; background: #0366d6; color: #fff; border: none; border-radius: 3px; font-weight: 600; cursor: pointer;">
                        <i class="fa fa-tag"></i> Generate Shipping Labels via API
                    </button>
                    <small style="display: block; margin-top: 8px; font-size: 11px; color: #6a737d;">
                        This will create 3 tracking numbers (1 per box) via courier API and store them in the system: Shipment → Parcel → Parcel Items
                    </small>
                </div>
            </div>
        </div>

        <!-- Tools Tab -->
        <div class="tab-pane" id="tab-tools">
            <div class="tools-grid">
                <div class="tool-card" onclick="window.open('print-box-labels.php?transfer_id=12345', '_blank')">
                    <i class="fa fa-box"></i>
                    <div class="tool-card-name">Box Labels (Internal)</div>
                    <div class="tool-card-desc">Print ID labels for warehouse</div>
                </div>

                <div class="tool-card">
                    <i class="fa fa-file-alt"></i>
                    <div class="tool-card-name">Packing Slip Generator</div>
                    <div class="tool-card-desc">Print preview with signature fields</div>
                </div>

                <div class="tool-card">
                    <i class="fa fa-envelope"></i>
                    <div class="tool-card-name">Email Summary</div>
                    <div class="tool-card-desc">Send summary to destination store</div>
                </div>

                <div class="tool-card">
                    <i class="fa fa-camera"></i>
                    <div class="tool-card-name">Photo Evidence</div>
                    <div class="tool-card-desc">Upload photos after packing</div>
                </div>

                <div class="tool-card">
                    <i class="fa fa-magic"></i>
                    <div class="tool-card-name">Auto-Assign Boxes</div>
                    <div class="tool-card-desc">Automatically distribute items</div>
                </div>

                <div class="tool-card">
                    <i class="fa fa-robot"></i>
                    <div class="tool-card-name">AI Optimization</div>
                    <div class="tool-card-desc">Get packing recommendations</div>
                </div>

                <div class="tool-card">
                    <i class="fa fa-cog"></i>
                    <div class="tool-card-name">Settings</div>
                    <div class="tool-card-desc">Configure packing preferences</div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function switchTab(tabName) {
    document.querySelectorAll('.tab-pane').forEach(pane => {
        pane.classList.remove('active');
    });
    document.querySelectorAll('.tab-btn').forEach(btn => {
        btn.classList.remove('active');
    });
    document.getElementById('tab-' + tabName).classList.add('active');
    event.target.closest('.tab-btn').classList.add('active');
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
