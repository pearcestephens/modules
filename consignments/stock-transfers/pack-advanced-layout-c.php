<?php
/**
 * Advanced Transfer Packing - LAYOUT C: Compact Dashboard
 * Top: Search + Quick Actions | Middle: Collapsible Panels | Bottom: Floating Freight Bar
 * Space-efficient design with everything on one screen
 */

require_once __DIR__ . '/../../base/_templates/themes/cis-classic/theme.php';

$theme = new CISClassicTheme();
$theme->setTitle('Pack Transfer #12345 - Layout C');
$theme->setPageSubtitle('Compact Dashboard - Everything Visible');
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
/* Layout C: Compact Dashboard */
.layout-c-container {
    margin-top: 20px;
    padding-bottom: 120px; /* Space for floating footer */
}

/* Top Bar: Search + Quick Stats */
.top-bar-c {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 20px;
    margin-bottom: 20px;
    align-items: center;
}

.search-box-c {
    position: relative;
}

.search-box-c input {
    width: 100%;
    padding: 15px 50px 15px 20px;
    font-size: 16px;
    border: 2px solid #dee2e6;
    border-radius: 8px;
}

.search-box-c i {
    position: absolute;
    right: 20px;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
    font-size: 18px;
}

.quick-stats-c {
    display: flex;
    gap: 20px;
}

.stat-pill-c {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 10px 20px;
    background: #fff;
    border-radius: 50px;
    border: 2px solid #dee2e6;
    white-space: nowrap;
}

.stat-pill-c i {
    font-size: 20px;
    color: #667eea;
}

.stat-pill-c .value {
    font-size: 20px;
    font-weight: bold;
    color: #333;
}

.stat-pill-c .label {
    font-size: 12px;
    color: #6c757d;
}

/* Accordion Panels */
.panel-c {
    background: #fff;
    border-radius: 8px;
    margin-bottom: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
    overflow: hidden;
}

.panel-header-c {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 15px 20px;
    background: #f8f9fa;
    cursor: pointer;
    transition: all 0.2s;
}

.panel-header-c:hover {
    background: #e9ecef;
}

.panel-header-c.active {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
}

.panel-title-c {
    display: flex;
    align-items: center;
    gap: 10px;
    font-weight: bold;
    font-size: 16px;
}

.panel-badge-c {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: bold;
    background: #dc3545;
    color: #fff;
    margin-left: 8px;
}

.panel-toggle-c {
    font-size: 20px;
    transition: transform 0.3s;
}

.panel-toggle-c.expanded {
    transform: rotate(180deg);
}

.panel-body-c {
    padding: 0;
    max-height: 0;
    overflow: hidden;
    transition: max-height 0.3s ease-out;
}

.panel-body-c.expanded {
    max-height: 2000px;
    padding: 20px;
}

/* Products Table (Compact) */
.products-table-c {
    width: 100%;
    font-size: 14px;
}

.products-table-c th {
    background: #f8f9fa;
    padding: 10px;
    font-size: 11px;
    text-transform: uppercase;
    border-bottom: 2px solid #dee2e6;
}

.products-table-c td {
    padding: 10px;
    vertical-align: middle;
    border-bottom: 1px solid #f0f0f0;
}

.product-mini-img-c {
    width: 40px;
    height: 40px;
    object-fit: cover;
    border-radius: 4px;
}

.product-row-c.ok { background: #e8f5e9; }
.product-row-c.under { background: #fff8e1; }
.product-row-c.over { background: #ffebee; }

.compact-input-c {
    width: 70px;
    padding: 5px 8px;
    text-align: center;
}

.compact-select-c {
    width: 90px;
    padding: 5px;
    font-size: 13px;
}

/* Freight Details Grid */
.freight-grid-c {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
}

.freight-box-c {
    background: #f8f9fa;
    padding: 20px;
    border-radius: 8px;
    border-left: 4px solid #667eea;
}

.freight-box-c h6 {
    margin: 0 0 15px 0;
    color: #667eea;
    font-weight: bold;
}

.metric-line-c {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #e0e0e0;
}

.metric-line-c:last-child {
    border-bottom: none;
}

.carrier-choice-c {
    background: #fff;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    padding: 12px;
    margin-bottom: 10px;
    cursor: pointer;
    transition: all 0.2s;
}

.carrier-choice-c:hover {
    border-color: #667eea;
    box-shadow: 0 2px 8px rgba(102, 126, 234, 0.2);
}

.carrier-choice-c.selected {
    border-color: #28a745;
    background: #e8f5e9;
}

.carrier-choice-c .carrier-name {
    font-weight: bold;
    margin-bottom: 3px;
}

.carrier-choice-c .carrier-details {
    font-size: 12px;
    color: #6c757d;
}

.carrier-choice-c .carrier-price {
    font-size: 20px;
    font-weight: bold;
    color: #667eea;
}

/* Tools Grid (Compact) */
.tools-grid-c {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 15px;
}

.tool-btn-c {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 15px;
    background: #f8f9fa;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    cursor: pointer;
    transition: all 0.2s;
}

.tool-btn-c:hover {
    border-color: #667eea;
    background: #fff;
    transform: translateY(-2px);
}

.tool-btn-c i {
    font-size: 24px;
    color: #667eea;
}

.tool-btn-c .tool-text {
    flex: 1;
}

.tool-btn-c .tool-name {
    font-weight: bold;
    font-size: 14px;
    color: #333;
}

.tool-btn-c .tool-hint {
    font-size: 11px;
    color: #6c757d;
}

/* Floating Freight Bar (Bottom) */
.floating-freight-bar-c {
    position: fixed;
    bottom: 0;
    left: 250px; /* Adjust for sidebar */
    right: 0;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    padding: 20px 30px;
    box-shadow: 0 -4px 12px rgba(0,0,0,0.15);
    z-index: 1000;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.freight-summary-c {
    display: flex;
    gap: 40px;
}

.freight-item-c {
    display: flex;
    flex-direction: column;
}

.freight-item-c .label {
    font-size: 11px;
    opacity: 0.9;
    text-transform: uppercase;
}

.freight-item-c .value {
    font-size: 20px;
    font-weight: bold;
}

.freight-actions-c {
    display: flex;
    gap: 10px;
}

/* Responsive */
@media (max-width: 1400px) {
    .freight-grid-c {
        grid-template-columns: 1fr;
    }
}

@media (max-width: 992px) {
    .top-bar-c {
        grid-template-columns: 1fr;
    }

    .quick-stats-c {
        justify-content: space-between;
        width: 100%;
    }

    .tools-grid-c {
        grid-template-columns: 1fr;
    }

    .floating-freight-bar-c {
        left: 0;
        flex-direction: column;
        gap: 15px;
    }
}
</style>

<div class="layout-c-container">
    <!-- Top Bar: Search + Quick Stats -->
    <div class="top-bar-c">
        <div class="search-box-c">
            <input type="text" id="search-c" placeholder="Search products by name, SKU, or barcode... (Press / to focus)" autocomplete="off">
            <i class="fa fa-search"></i>
        </div>

        <div class="quick-stats-c">
            <div class="stat-pill-c">
                <i class="fa fa-box"></i>
                <div>
                    <div class="value">3</div>
                    <div class="label">Boxes</div>
                </div>
            </div>
            <div class="stat-pill-c">
                <i class="fa fa-weight"></i>
                <div>
                    <div class="value">15.7kg</div>
                    <div class="label">Weight</div>
                </div>
            </div>
            <div class="stat-pill-c">
                <i class="fa fa-percentage"></i>
                <div>
                    <div class="value">65%</div>
                    <div class="label">Packed</div>
                </div>
            </div>
        </div>
    </div>

    <!-- Panel 1: Products (Collapsible) -->
    <div class="panel-c">
        <div class="panel-header-c active" onclick="togglePanel(1)">
            <div class="panel-title-c">
                <i class="fa fa-boxes"></i>
                Products to Pack
                <span class="panel-badge-c">11 items</span>
            </div>
            <i class="fa fa-chevron-down panel-toggle-c expanded"></i>
        </div>
        <div class="panel-body-c expanded" id="panel-1">
            <table class="products-table-c">
                <thead>
                    <tr>
                        <th style="width: 50px;"></th>
                        <th>Product</th>
                        <th>SKU</th>
                        <th style="width: 80px;">Planned</th>
                        <th style="width: 80px;">Packed</th>
                        <th style="width: 100px;">Box</th>
                        <th style="width: 60px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="product-row-c ok">
                        <td><img src="/placeholder.jpg" alt="" class="product-mini-img-c"></td>
                        <td><strong>Vaporesso XROS 3 Pod Kit</strong></td>
                        <td><code style="font-size: 12px;">VP-XR3-BLK</code></td>
                        <td><input type="number" class="form-control form-control-sm compact-input-c" value="10" readonly></td>
                        <td><input type="number" class="form-control form-control-sm compact-input-c" value="10"></td>
                        <td>
                            <select class="form-control form-control-sm compact-select-c">
                                <option>Box 1</option>
                                <option>Box 2</option>
                                <option>Box 3</option>
                            </select>
                        </td>
                        <td><button class="btn btn-sm btn-success"><i class="fa fa-check"></i></button></td>
                    </tr>
                    <tr class="product-row-c under">
                        <td><img src="/placeholder.jpg" alt="" class="product-mini-img-c"></td>
                        <td><strong>SMOK Nord 5 Pods</strong></td>
                        <td><code style="font-size: 12px;">SM-N5-POD</code></td>
                        <td><input type="number" class="form-control form-control-sm compact-input-c" value="25" readonly></td>
                        <td><input type="number" class="form-control form-control-sm compact-input-c" value="20"></td>
                        <td>
                            <select class="form-control form-control-sm compact-select-c">
                                <option>Box 1</option>
                                <option>Box 2</option>
                                <option>Box 3</option>
                            </select>
                        </td>
                        <td><button class="btn btn-sm btn-warning"><i class="fa fa-exclamation"></i></button></td>
                    </tr>
                    <tr class="product-row-c over">
                        <td><img src="/placeholder.jpg" alt="" class="product-mini-img-c"></td>
                        <td><strong>Caliburn G2 Coils 0.8Ω</strong></td>
                        <td><code style="font-size: 12px;">UV-G2-08</code></td>
                        <td><input type="number" class="form-control form-control-sm compact-input-c" value="15" readonly></td>
                        <td><input type="number" class="form-control form-control-sm compact-input-c" value="18"></td>
                        <td>
                            <select class="form-control form-control-sm compact-select-c">
                                <option>Box 2</option>
                                <option>Box 1</option>
                                <option>Box 3</option>
                            </select>
                        </td>
                        <td><button class="btn btn-sm btn-danger"><i class="fa fa-times"></i></button></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Panel 2: Freight Details (Collapsible) -->
    <div class="panel-c">
        <div class="panel-header-c" onclick="togglePanel(2)">
            <div class="panel-title-c">
                <i class="fa fa-truck"></i>
                Freight Details
            </div>
            <i class="fa fa-chevron-down panel-toggle-c"></i>
        </div>
        <div class="panel-body-c" id="panel-2">
            <div class="freight-grid-c">
                <!-- Metrics -->
                <div class="freight-box-c">
                    <h6><i class="fa fa-calculator"></i> Metrics</h6>
                    <div class="metric-line-c">
                        <span>Weight:</span>
                        <strong>15.7 kg</strong>
                    </div>
                    <div class="metric-line-c">
                        <span>Volume:</span>
                        <strong>0.045 m³</strong>
                    </div>
                    <div class="metric-line-c">
                        <span>Boxes:</span>
                        <strong>3</strong>
                    </div>
                    <div class="metric-line-c">
                        <span>Distance:</span>
                        <strong>648 km</strong>
                    </div>
                </div>

                <!-- Carrier Selection -->
                <div class="freight-box-c">
                    <h6><i class="fa fa-shipping-fast"></i> Select Carrier</h6>

                    <div class="carrier-choice-c selected">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div class="carrier-name">NZ Post</div>
                                <div class="carrier-details">2-3 days • Recommended</div>
                            </div>
                            <div class="carrier-price">$45.80</div>
                        </div>
                    </div>

                    <div class="carrier-choice-c">
                        <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <div class="carrier-name">GoSweetSpot</div>
                                <div class="carrier-details">3-4 days • Cheapest</div>
                            </div>
                            <div class="carrier-price">$42.50</div>
                        </div>
                    </div>
                </div>

                <!-- Tracking -->
                <div class="freight-box-c">
                    <h6><i class="fa fa-barcode"></i> Tracking Numbers</h6>
                    <textarea class="form-control" rows="4" placeholder="Enter tracking numbers&#10;(1 per line)"></textarea>
                    <small class="text-muted">1 line = 1 parcel</small>
                </div>
            </div>
        </div>
    </div>

    <!-- Panel 3: Productivity Tools (Collapsible) -->
    <div class="panel-c">
        <div class="panel-header-c" onclick="togglePanel(3)">
            <div class="panel-title-c">
                <i class="fa fa-tools"></i>
                Productivity Tools
            </div>
            <i class="fa fa-chevron-down panel-toggle-c"></i>
        </div>
        <div class="panel-body-c" id="panel-3">
            <div class="tools-grid-c">
                <div class="tool-btn-c" onclick="openPackingSlip()">
                    <i class="fa fa-file-alt"></i>
                    <div class="tool-text">
                        <div class="tool-name">Packing Slip</div>
                        <div class="tool-hint">Print with signature</div>
                    </div>
                </div>

                <div class="tool-btn-c" onclick="openEmailSummary()">
                    <i class="fa fa-envelope"></i>
                    <div class="tool-text">
                        <div class="tool-name">Email Summary</div>
                        <div class="tool-hint">Send to destination</div>
                    </div>
                </div>

                <div class="tool-btn-c" onclick="openPhotoUpload()">
                    <i class="fa fa-camera"></i>
                    <div class="tool-text">
                        <div class="tool-name">Photo Evidence</div>
                        <div class="tool-hint">Upload after packing</div>
                    </div>
                </div>

                <div class="tool-btn-c" onclick="toggleAutoAssign()">
                    <i class="fa fa-magic"></i>
                    <div class="tool-text">
                        <div class="tool-name">Auto-Assign</div>
                        <div class="tool-hint">Smart box distribution</div>
                    </div>
                </div>

                <div class="tool-btn-c" onclick="openAIAdvisor()">
                    <i class="fa fa-robot"></i>
                    <div class="tool-text">
                        <div class="tool-name">AI Optimization</div>
                        <div class="tool-hint">Get recommendations</div>
                    </div>
                </div>

                <div class="tool-btn-c" onclick="openSettings()">
                    <i class="fa fa-cog"></i>
                    <div class="tool-text">
                        <div class="tool-name">Settings</div>
                        <div class="tool-hint">Configure preferences</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Floating Freight Bar (Always Visible) -->
<div class="floating-freight-bar-c">
    <div class="freight-summary-c">
        <div class="freight-item-c">
            <div class="label">Total Weight</div>
            <div class="value">15.7 kg</div>
        </div>
        <div class="freight-item-c">
            <div class="label">Carrier</div>
            <div class="value">NZ Post</div>
        </div>
        <div class="freight-item-c">
            <div class="label">Est. Cost</div>
            <div class="value">$45.80</div>
        </div>
        <div class="freight-item-c">
            <div class="label">ETA</div>
            <div class="value">2-3 days</div>
        </div>
    </div>

    <div class="freight-actions-c">
        <button class="btn btn-light btn-lg" onclick="openFreightPreview()">
            <i class="fa fa-eye"></i> Preview
        </button>
        <button class="btn btn-warning btn-lg" onclick="generateLabels()">
            <i class="fa fa-tag"></i> Generate Labels
        </button>
        <button class="btn btn-success btn-lg" onclick="finishPacking()">
            <i class="fa fa-check-circle"></i> Finish & Ship
        </button>
    </div>
</div>

<script>
function togglePanel(panelId) {
    const body = document.getElementById('panel-' + panelId);
    const header = body.previousElementSibling;
    const toggle = header.querySelector('.panel-toggle-c');

    const isExpanded = body.classList.contains('expanded');

    if (isExpanded) {
        body.classList.remove('expanded');
        toggle.classList.remove('expanded');
        header.classList.remove('active');
    } else {
        body.classList.add('expanded');
        toggle.classList.add('expanded');
        header.classList.add('active');
    }
}

function saveDraft() {
    alert('Draft saved!');
}

function openAIAdvisor() {
    alert('AI Advisor: This compact layout maximizes screen space with collapsible panels and a floating action bar.');
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

function openFreightPreview() {
    alert('Opening freight preview with all details...');
}

function generateLabels() {
    alert('Generating 3 thermal box labels (80mm receipt format)...');
}

// Keyboard shortcut
document.addEventListener('keydown', function(e) {
    if (e.key === '/' && !['INPUT', 'TEXTAREA'].includes(e.target.tagName)) {
        e.preventDefault();
        document.getElementById('search-c').focus();
    }
});
</script>

<?php $theme->render('footer'); ?>
