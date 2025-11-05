<?php
/**
 * Advanced Transfer Packing - LAYOUT B: Horizontal Tabs
 * Top: Progress Bar | Middle: Tab Navigation | Bottom: Content Area
 * Dashboard-style with tab switching between products/freight/tools
 */

require_once __DIR__ . '/../../base/_templates/themes/cis-classic/theme.php';

$theme = new CISClassicTheme();
$theme->setTitle('Pack Transfer #12345 - Layout B');
$theme->setPageSubtitle('Horizontal Tabs - Wizard-Style Navigation');
$theme->showTimestamps = true;

// Breadcrumbs
$theme->addBreadcrumb('Consignments', '/modules/consignments/');
$theme->addBreadcrumb('Stock Transfers', '/modules/consignments/stock-transfers/');
$theme->addBreadcrumb('Pack Transfer', null);

// Header buttons
$theme->addHeaderButton('Save Draft', 'btn-outline-secondary', 'javascript:saveDraft()', 'fa-save');
$theme->addHeaderButton('AI Advisor', 'btn-outline-purple', 'javascript:openAIAdvisor()', 'fa-robot');
$theme->addHeaderButton('Pack & Finish', 'btn-success', 'javascript:finishPacking()', 'fa-check-circle');
?>

<?php $theme->render('html-head'); ?>
<?php $theme->render('header'); ?>
<?php $theme->render('sidebar'); ?>
<?php $theme->render('main-start'); ?>

<style>
/* Layout B: Horizontal Tabs */
.layout-b-container {
    margin-top: 20px;
}

/* Hero Progress Bar */
.hero-progress-b {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    padding: 40px;
    border-radius: 12px;
    margin-bottom: 20px;
    box-shadow: 0 4px 6px rgba(0,0,0,0.1);
    color: #fff;
}

.hero-progress-b h3 {
    margin: 0 0 20px 0;
    font-size: 28px;
}

.progress-stats-b {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 20px;
    margin-top: 20px;
}

.stat-card-b {
    text-align: center;
    background: rgba(255,255,255,0.15);
    padding: 15px;
    border-radius: 8px;
    backdrop-filter: blur(10px);
}

.stat-value-b {
    font-size: 32px;
    font-weight: bold;
}

.stat-label-b {
    font-size: 13px;
    opacity: 0.9;
    margin-top: 5px;
}

/* Tab Navigation */
.tab-nav-b {
    background: #fff;
    border-radius: 8px 8px 0 0;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    display: flex;
    overflow: hidden;
}

.tab-btn-b {
    flex: 1;
    padding: 18px 20px;
    border: none;
    background: #f8f9fa;
    border-bottom: 3px solid transparent;
    cursor: pointer;
    font-size: 16px;
    font-weight: 500;
    transition: all 0.3s;
    position: relative;
}

.tab-btn-b:hover {
    background: #e9ecef;
}

.tab-btn-b.active {
    background: #fff;
    border-bottom-color: #667eea;
    color: #667eea;
}

.tab-btn-b i {
    margin-right: 8px;
    font-size: 18px;
}

.tab-badge-b {
    position: absolute;
    top: 8px;
    right: 8px;
    background: #dc3545;
    color: #fff;
    border-radius: 10px;
    padding: 2px 8px;
    font-size: 11px;
    font-weight: bold;
}

/* Tab Content */
.tab-content-b {
    background: #fff;
    border-radius: 0 0 8px 8px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    padding: 30px;
    min-height: 500px;
}

.tab-pane-b {
    display: none;
}

.tab-pane-b.active {
    display: block;
}

/* Products Tab */
.product-search-b {
    margin-bottom: 20px;
}

.product-search-b input {
    width: 100%;
    padding: 15px 20px;
    font-size: 18px;
    border: 2px solid #dee2e6;
    border-radius: 50px;
}

.product-grid-b {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.product-card-b {
    border: 2px solid #dee2e6;
    border-radius: 8px;
    padding: 15px;
    transition: all 0.3s;
}

.product-card-b:hover {
    border-color: #667eea;
    box-shadow: 0 4px 8px rgba(102, 126, 234, 0.2);
}

.product-card-b.packed {
    border-color: #28a745;
    background: #e8f5e9;
}

.product-card-b.under {
    border-color: #ffc107;
    background: #fff8e1;
}

.product-card-b.over {
    border-color: #dc3545;
    background: #ffebee;
}

.product-header-b {
    display: flex;
    gap: 15px;
    margin-bottom: 15px;
}

.product-img-b {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 8px;
}

.product-info-b {
    flex: 1;
}

.product-name-b {
    font-weight: bold;
    margin-bottom: 5px;
}

.product-sku-b {
    color: #6c757d;
    font-family: monospace;
    font-size: 13px;
}

.product-inputs-b {
    display: grid;
    grid-template-columns: 1fr 1fr 1fr;
    gap: 10px;
}

/* Freight Tab */
.freight-grid-b {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 30px;
}

.freight-section-b {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
}

.freight-section-b h5 {
    margin-bottom: 15px;
    color: #667eea;
}

.metric-row-b {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #dee2e6;
}

.metric-row-b:last-child {
    border-bottom: none;
}

.carrier-option-b {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.3s;
}

.carrier-option-b:hover {
    border-color: #667eea;
}

.carrier-option-b.recommended {
    border-color: #28a745;
    background: #e8f5e9;
}

.carrier-badge-b {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: bold;
}

.carrier-badge-b.cheapest {
    background: #28a745;
    color: #fff;
}

.carrier-badge-b.fastest {
    background: #007bff;
    color: #fff;
}

.carrier-badge-b.recommended {
    background: #ffc107;
    color: #333;
}

/* Tools Tab */
.tools-grid-b {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(250px, 1fr));
    gap: 20px;
}

.tool-card-b {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    padding: 30px;
    border-radius: 12px;
    cursor: pointer;
    transition: all 0.3s;
    text-align: center;
}

.tool-card-b:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 16px rgba(102, 126, 234, 0.3);
}

.tool-icon-b {
    font-size: 48px;
    margin-bottom: 15px;
}

.tool-name-b {
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 8px;
}

.tool-desc-b {
    font-size: 13px;
    opacity: 0.9;
}

/* Responsive */
@media (max-width: 992px) {
    .progress-stats-b {
        grid-template-columns: repeat(3, 1fr);
    }

    .freight-grid-b {
        grid-template-columns: 1fr;
    }

    .product-grid-b {
        grid-template-columns: 1fr;
    }
}
</style>

<div class="layout-b-container">
    <!-- Hero Progress Bar -->
    <div class="hero-progress-b">
        <h3>Packing Transfer #12345 - Auckland to Wellington</h3>
        <div class="progress" style="height: 12px; background: rgba(255,255,255,0.3);">
            <div class="progress-bar bg-light" role="progressbar" style="width: 65%" aria-valuenow="65" aria-valuemin="0" aria-valuemax="100"></div>
        </div>

        <div class="progress-stats-b">
            <div class="stat-card-b">
                <div class="stat-value-b">32</div>
                <div class="stat-label-b">Total Items</div>
            </div>
            <div class="stat-card-b">
                <div class="stat-value-b">21</div>
                <div class="stat-label-b">Packed</div>
            </div>
            <div class="stat-card-b">
                <div class="stat-value-b">3</div>
                <div class="stat-label-b">Boxes</div>
            </div>
            <div class="stat-card-b">
                <div class="stat-value-b">15.7kg</div>
                <div class="stat-label-b">Weight</div>
            </div>
            <div class="stat-card-b">
                <div class="stat-value-b">$45.80</div>
                <div class="stat-label-b">Est. Freight</div>
            </div>
        </div>
    </div>

    <!-- Tab Navigation -->
    <div class="tab-nav-b">
        <button class="tab-btn-b active" onclick="switchTab('products')">
            <i class="fa fa-box"></i> Products
            <span class="tab-badge-b">11</span>
        </button>
        <button class="tab-btn-b" onclick="switchTab('freight')">
            <i class="fa fa-truck"></i> Freight
        </button>
        <button class="tab-btn-b" onclick="switchTab('tools')">
            <i class="fa fa-tools"></i> Productivity Tools
        </button>
        <button class="tab-btn-b" onclick="switchTab('history')">
            <i class="fa fa-history"></i> History
        </button>
    </div>

    <!-- Tab Content -->
    <div class="tab-content-b">
        <!-- Products Tab -->
        <div class="tab-pane-b active" id="tab-products">
            <div class="product-search-b">
                <input type="text" placeholder="Search products by name, SKU, or barcode... (Press / to focus)" autocomplete="off">
            </div>

            <div class="product-grid-b">
                <!-- Product Card 1 -->
                <div class="product-card-b packed">
                    <div class="product-header-b">
                        <img src="/placeholder.jpg" alt="" class="product-img-b">
                        <div class="product-info-b">
                            <div class="product-name-b">Vaporesso XROS 3 Pod Kit</div>
                            <div class="product-sku-b">VP-XR3-BLK</div>
                            <span class="badge badge-success mt-2">Fully Packed</span>
                        </div>
                    </div>
                    <div class="product-inputs-b">
                        <div class="form-group mb-0">
                            <label class="small">Planned</label>
                            <input type="number" class="form-control" value="10" readonly>
                        </div>
                        <div class="form-group mb-0">
                            <label class="small">Packed</label>
                            <input type="number" class="form-control" value="10">
                        </div>
                        <div class="form-group mb-0">
                            <label class="small">Box</label>
                            <select class="form-control">
                                <option>Box 1</option>
                                <option>Box 2</option>
                                <option>Box 3</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Product Card 2 -->
                <div class="product-card-b under">
                    <div class="product-header-b">
                        <img src="/placeholder.jpg" alt="" class="product-img-b">
                        <div class="product-info-b">
                            <div class="product-name-b">SMOK Nord 5 Replacement Pods</div>
                            <div class="product-sku-b">SM-N5-POD</div>
                            <span class="badge badge-warning mt-2">Under Packed</span>
                        </div>
                    </div>
                    <div class="product-inputs-b">
                        <div class="form-group mb-0">
                            <label class="small">Planned</label>
                            <input type="number" class="form-control" value="25" readonly>
                        </div>
                        <div class="form-group mb-0">
                            <label class="small">Packed</label>
                            <input type="number" class="form-control" value="20">
                        </div>
                        <div class="form-group mb-0">
                            <label class="small">Box</label>
                            <select class="form-control">
                                <option>Box 2</option>
                                <option>Box 1</option>
                                <option>Box 3</option>
                            </select>
                        </div>
                    </div>
                </div>

                <!-- Product Card 3 -->
                <div class="product-card-b over">
                    <div class="product-header-b">
                        <img src="/placeholder.jpg" alt="" class="product-img-b">
                        <div class="product-info-b">
                            <div class="product-name-b">Caliburn G2 Coils 0.8Ω</div>
                            <div class="product-sku-b">UV-G2-08</div>
                            <span class="badge badge-danger mt-2">Over Packed</span>
                        </div>
                    </div>
                    <div class="product-inputs-b">
                        <div class="form-group mb-0">
                            <label class="small">Planned</label>
                            <input type="number" class="form-control" value="15" readonly>
                        </div>
                        <div class="form-group mb-0">
                            <label class="small">Packed</label>
                            <input type="number" class="form-control" value="18">
                        </div>
                        <div class="form-group mb-0">
                            <label class="small">Box</label>
                            <select class="form-control">
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
        <div class="tab-pane-b" id="tab-freight">
            <div class="freight-grid-b">
                <!-- Left: Metrics -->
                <div>
                    <div class="freight-section-b mb-4">
                        <h5><i class="fa fa-calculator"></i> Freight Metrics</h5>
                        <div class="metric-row-b">
                            <span>Total Weight:</span>
                            <strong>15.7 kg</strong>
                        </div>
                        <div class="metric-row-b">
                            <span>Total Volume:</span>
                            <strong>0.045 m³</strong>
                        </div>
                        <div class="metric-row-b">
                            <span>Number of Boxes:</span>
                            <strong>3 boxes</strong>
                        </div>
                        <div class="metric-row-b">
                            <span>Distance:</span>
                            <strong>648 km</strong>
                        </div>
                    </div>

                    <div class="freight-section-b">
                        <h5><i class="fa fa-tags"></i> Tracking Numbers</h5>
                        <textarea class="form-control" rows="4" placeholder="Enter tracking numbers (1 per line)"></textarea>
                        <small class="text-muted">Manual entry - 1 line = 1 parcel</small>
                    </div>
                </div>

                <!-- Right: Carriers -->
                <div>
                    <div class="freight-section-b">
                        <h5><i class="fa fa-shipping-fast"></i> Select Carrier</h5>

                        <div class="carrier-option-b recommended">
                            <div>
                                <strong>NZ Post</strong>
                                <span class="carrier-badge-b recommended">Recommended</span>
                                <div class="small text-muted">ETA: 2-3 business days</div>
                            </div>
                            <div class="text-right">
                                <strong style="font-size: 20px;">$45.80</strong>
                            </div>
                        </div>

                        <div class="carrier-option-b">
                            <div>
                                <strong>GoSweetSpot</strong>
                                <span class="carrier-badge-b cheapest">Cheapest</span>
                                <div class="small text-muted">ETA: 3-4 business days</div>
                            </div>
                            <div class="text-right">
                                <strong style="font-size: 20px;">$42.50</strong>
                            </div>
                        </div>

                        <div class="carrier-option-b">
                            <div>
                                <strong>CourierPost</strong>
                                <span class="carrier-badge-b fastest">Fastest</span>
                                <div class="small text-muted">ETA: 1-2 business days</div>
                            </div>
                            <div class="text-right">
                                <strong style="font-size: 20px;">$58.20</strong>
                            </div>
                        </div>

                        <button class="btn btn-primary btn-lg btn-block mt-3">
                            <i class="fa fa-tag"></i> Generate Labels (3 boxes)
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tools Tab -->
        <div class="tab-pane-b" id="tab-tools">
            <div class="tools-grid-b">
                <div class="tool-card-b" onclick="openPackingSlip()">
                    <div class="tool-icon-b"><i class="fa fa-file-alt"></i></div>
                    <div class="tool-name-b">Packing Slip Generator</div>
                    <div class="tool-desc-b">Print preview with signature fields</div>
                </div>

                <div class="tool-card-b" onclick="openEmailSummary()">
                    <div class="tool-icon-b"><i class="fa fa-envelope"></i></div>
                    <div class="tool-name-b">Email Summary</div>
                    <div class="tool-desc-b">Send summary to destination store</div>
                </div>

                <div class="tool-card-b" onclick="openPhotoUpload()">
                    <div class="tool-icon-b"><i class="fa fa-camera"></i></div>
                    <div class="tool-name-b">Photo Evidence</div>
                    <div class="tool-desc-b">Upload photos after packing</div>
                </div>

                <div class="tool-card-b" onclick="toggleAutoAssign()">
                    <div class="tool-icon-b"><i class="fa fa-magic"></i></div>
                    <div class="tool-name-b">Auto-Assign Boxes</div>
                    <div class="tool-desc-b">Automatically distribute items</div>
                </div>

                <div class="tool-card-b" onclick="openAIAdvisor()">
                    <div class="tool-icon-b"><i class="fa fa-robot"></i></div>
                    <div class="tool-name-b">AI Optimization</div>
                    <div class="tool-desc-b">Get packing recommendations</div>
                </div>

                <div class="tool-card-b" onclick="openSettings()">
                    <div class="tool-icon-b"><i class="fa fa-cog"></i></div>
                    <div class="tool-name-b">Settings</div>
                    <div class="tool-desc-b">Configure packing preferences</div>
                </div>
            </div>
        </div>

        <!-- History Tab -->
        <div class="tab-pane-b" id="tab-history">
            <div class="alert alert-info">
                <i class="fa fa-info-circle"></i> History timeline will show here (packing events, label generation, emails sent, etc.)
            </div>
        </div>
    </div>
</div>

<script>
function switchTab(tabName) {
    // Hide all tabs
    document.querySelectorAll('.tab-pane-b').forEach(pane => {
        pane.classList.remove('active');
    });

    // Remove active from all buttons
    document.querySelectorAll('.tab-btn-b').forEach(btn => {
        btn.classList.remove('active');
    });

    // Show selected tab
    document.getElementById('tab-' + tabName).classList.add('active');
    event.target.classList.add('active');
}

function saveDraft() {
    alert('Draft saved!');
}

function openAIAdvisor() {
    alert('AI Advisor: This tabbed layout keeps all features organized and easy to navigate.');
}

function finishPacking() {
    if (confirm('Generate thermal labels and finish packing?')) {
        alert('Generating 3 thermal labels (80mm) and sending email summary...');
    }
}

function openPackingSlip() {
    alert('Opening packing slip generator with print preview and signature fields...');
}

function openEmailSummary() {
    alert('Composing email summary to destination store...');
}

function openPhotoUpload() {
    alert('Opening camera/upload interface for photo evidence...');
}

function toggleAutoAssign() {
    alert('Auto-assign per box mode toggled!');
}

function openSettings() {
    alert('Opening settings panel...');
}
</script>

<?php $theme->render('footer'); ?>
