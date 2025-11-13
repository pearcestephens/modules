<?php
/**
 * Advanced Transfer Packing - LAYOUT A: Two Column Split
 * Left: Product Table (70%) | Right: Freight Console + Tools (30%)
 * Classic pack-pro.php style with modern enhancements
 */

// Corrected theme include path (was _templates)
require_once __DIR__ . '/../../base/templates/themes/cis-classic/theme.php';

$theme = new CISClassicTheme();
$theme->setTitle('Pack Transfer #12345 - Layout A');
$theme->setPageSubtitle('Two Column Split - Products Left, Console Right');
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
/* Layout A: Two Column Split */
.layout-a-container {
    max-width: 1400px;
    margin: 0 auto;
    padding: 20px;
    background: #f8f9fa;
    min-height: calc(100vh - 200px);
}

/* Hero Search */
.hero-search-a {
    background: #fff;
    border: 1px solid #dee2e6;
    padding: 20px;
    border-radius: 8px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.hero-search-a input {
    width: 100%;
    padding: 10px 15px;
    font-size: 15px;
    border: 2px solid #dee2e6;
    border-radius: 6px;
    background: #fff;
}

.hero-search-a input:focus {
    border-color: #007bff;
    outline: none;
}
.hero-search-a input:focus {
    border-color: #007bff;
    outline: none;
}

.layout-a-grid {
    display: grid;
    grid-template-columns: 1fr 380px;
    gap: 20px;
}

/* Progress Card */
.progress-card-a {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    padding: 20px;
    margin-bottom: 20px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
}

.kpi-row-a {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px;
    margin-top: 15px;
}

.kpi-card-a {
    text-align: center;
    padding: 12px;
    background: #f8f9fa;
    border-radius: 6px;
    border-left: 3px solid #007bff;
}

.kpi-card-a.success { border-left-color: #28a745; }
.kpi-card-a.warning { border-left-color: #ffc107; }
.kpi-card-a.danger { border-left-color: #dc3545; }

.kpi-value-a {
    font-size: 22px;
    font-weight: bold;
    color: #333;
}

.kpi-label-a {
    font-size: 11px;
    color: #6c757d;
    text-transform: uppercase;
    margin-top: 4px;
}

/* Product Table */
.product-table-a {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    overflow: hidden;
}

.product-table-a table {
    width: 100%;
    margin-bottom: 0;
}

.product-table-a thead {
    background: #f8f9fa;
    position: sticky;
    top: 0;
    z-index: 10;
}

.product-table-a th {
    padding: 10px 12px;
    font-size: 11px;
    text-transform: uppercase;
    font-weight: 600;
    border-bottom: 2px solid #dee2e6;
    color: #495057;
}

.product-table-a td {
    padding: 10px 12px;
    vertical-align: middle;
    font-size: 14px;
}

.product-img-a {
    width: 48px;
    height: 48px;
    object-fit: cover;
    border-radius: 4px;
}

.product-row-ok { background: #e8f5e9; }
.product-row-under { background: #fff8e1; }
.product-row-over { background: #ffebee; }
.product-row-zero { background: #fff; }

/* Freight Console (Right Sidebar) */
.freight-console-a {
    background: #fff;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.05);
    position: sticky;
    top: 20px;
}

.console-header-a {
    background: #f8f9fa;
    border-bottom: 2px solid #dee2e6;
    color: #495057;
    padding: 12px 15px;
    border-radius: 6px 6px 0 0;
    font-weight: 600;
}

.console-tabs-a {
    display: flex;
    border-bottom: 1px solid #dee2e6;
}

.console-tab-a {
    flex: 1;
    padding: 10px;
    text-align: center;
    border: none;
    background: #fff;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 13px;
    color: #6c757d;
}

.console-tab-a.active {
    background: #f8f9fa;
    border-bottom: 2px solid #007bff;
    font-weight: 600;
    color: #007bff;
}

.console-body-a {
    padding: 15px;
}

.freight-metric-a {
    display: flex;
    justify-content: space-between;
    padding: 8px 0;
    border-bottom: 1px solid #f0f0f0;
    font-size: 14px;
}

.freight-metric-a:last-child {
    border-bottom: none;
}

.box-stepper-a {
    display: flex;
    align-items: center;
    gap: 10px;
    margin: 15px 0;
}

.box-stepper-a button {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    border: 2px solid #667eea;
    background: #fff;
    color: #667eea;
    font-size: 20px;
    cursor: pointer;
}

.box-stepper-a input {
    width: 80px;
    text-align: center;
    font-size: 24px;
    font-weight: bold;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    padding: 8px;
}

/* Productivity Tools Panel */
.tools-panel-a {
    background: #fff;
    border-radius: 8px;
    padding: 15px;
    margin-top: 15px;
    box-shadow: 0 2px 4px rgba(0,0,0,0.08);
}

.tools-header-a {
    font-weight: bold;
    margin-bottom: 10px;
    color: #333;
}

.tool-btn-a {
    display: block;
    width: 100%;
    padding: 10px;
    margin-bottom: 8px;
    text-align: left;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    background: #f8f9fa;
    cursor: pointer;
    transition: all 0.2s;
}

.tool-btn-a:hover {
    background: #e9ecef;
    border-color: #667eea;
}

.tool-btn-a i {
    margin-right: 8px;
    width: 20px;
}

/* Responsive */
@media (max-width: 1200px) {
    .layout-a-container {
        grid-template-columns: 1fr;
    }

    .freight-console-a {
        position: relative;
        top: 0;
    }
}
</style>

<div class="layout-a-container">
    <!-- LEFT COLUMN: Product Table -->
    <div class="left-column-a">
        <!-- Hero Search -->
        <div class="hero-search-a">
            <input type="text" id="product-search-a" placeholder="Search products by name, SKU, or barcode... (Press / to focus)" autocomplete="off">
        </div>

        <!-- Progress Overview -->
        <div class="progress-card-a">
            <h5 class="mb-0">Packing Progress</h5>
            <div class="progress mt-3" style="height: 8px;">
                <div class="progress-bar bg-success" role="progressbar" style="width: 65%" aria-valuenow="65" aria-valuemin="0" aria-valuemax="100"></div>
            </div>

            <div class="kpi-row-a">
                <div class="kpi-card-a">
                    <div class="kpi-value-a">3</div>
                    <div class="kpi-label-a">Boxes</div>
                </div>
                <div class="kpi-card-a success">
                    <div class="kpi-value-a">15.7kg</div>
                    <div class="kpi-label-a">Weight</div>
                </div>
                <div class="kpi-card-a warning">
                    <div class="kpi-value-a">65%</div>
                    <div class="kpi-label-a">Packed</div>
                </div>
                <div class="kpi-card-a">
                    <div class="kpi-value-a">$45.80</div>
                    <div class="kpi-label-a">Est. Freight</div>
                </div>
            </div>
        </div>

        <!-- Product Table -->
        <div class="product-table-a">
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th style="width: 60px;">Image</th>
                        <th>Product</th>
                        <th>SKU</th>
                        <th style="width: 100px;">Planned</th>
                        <th style="width: 100px;">Packed</th>
                        <th style="width: 120px;">Box #</th>
                        <th style="width: 80px;">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="product-row-ok">
                        <td><img src="/placeholder.jpg" alt="" class="product-img-a"></td>
                        <td><strong>Vaporesso XROS 3 Pod Kit</strong></td>
                        <td><code>VP-XR3-BLK</code></td>
                        <td><input type="number" class="form-control form-control-sm" value="10" readonly></td>
                        <td><input type="number" class="form-control form-control-sm" value="10"></td>
                        <td>
                            <select class="form-control form-control-sm">
                                <option>Box 1</option>
                                <option>Box 2</option>
                                <option>Box 3</option>
                            </select>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-success"><i class="fa fa-check"></i></button>
                        </td>
                    </tr>
                    <tr class="product-row-under">
                        <td><img src="/placeholder.jpg" alt="" class="product-img-a"></td>
                        <td><strong>SMOK Nord 5 Replacement Pods</strong></td>
                        <td><code>SM-N5-POD</code></td>
                        <td><input type="number" class="form-control form-control-sm" value="25" readonly></td>
                        <td><input type="number" class="form-control form-control-sm" value="20"></td>
                        <td>
                            <select class="form-control form-control-sm">
                                <option>Box 1</option>
                                <option>Box 2</option>
                                <option>Box 3</option>
                            </select>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-warning"><i class="fa fa-exclamation"></i></button>
                        </td>
                    </tr>
                    <tr class="product-row-over">
                        <td><img src="/placeholder.jpg" alt="" class="product-img-a"></td>
                        <td><strong>Caliburn G2 Coils 0.8Ω</strong></td>
                        <td><code>UV-G2-08</code></td>
                        <td><input type="number" class="form-control form-control-sm" value="15" readonly></td>
                        <td><input type="number" class="form-control form-control-sm" value="18"></td>
                        <td>
                            <select class="form-control form-control-sm">
                                <option>Box 2</option>
                                <option>Box 1</option>
                                <option>Box 3</option>
                            </select>
                        </td>
                        <td>
                            <button class="btn btn-sm btn-danger"><i class="fa fa-times"></i></button>
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

    <!-- RIGHT COLUMN: Freight Console + Tools -->
    <div class="right-column-a">
        <!-- Freight Console -->
        <div class="freight-console-a">
            <div class="console-header-a">
                <h5 class="mb-0"><i class="fa fa-truck"></i> Freight Console</h5>
            </div>

            <!-- Mode Tabs -->
            <div class="console-tabs-a">
                <button class="console-tab-a active" onclick="switchMode('manual')">Manual</button>
                <button class="console-tab-a" onclick="switchMode('pickup')">Pickup</button>
                <button class="console-tab-a" onclick="switchMode('dropoff')">Drop-off</button>
            </div>

            <!-- Console Body -->
            <div class="console-body-a">
                <div class="freight-metric-a">
                    <span>Total Weight:</span>
                    <strong>15.7 kg</strong>
                </div>
                <div class="freight-metric-a">
                    <span>Total Volume:</span>
                    <strong>0.045 m³</strong>
                </div>
                <div class="freight-metric-a">
                    <span>Recommended:</span>
                    <strong class="text-success">NZ Post</strong>
                </div>
                <div class="freight-metric-a">
                    <span>Est. Cost:</span>
                    <strong class="text-primary">$45.80</strong>
                </div>

                <hr>

                <!-- Box Stepper -->
                <div class="text-center">
                    <label class="d-block mb-2"><strong>Number of Boxes</strong></label>
                    <div class="box-stepper-a">
                        <button onclick="decreaseBoxes()">−</button>
                        <input type="number" id="box-count-a" value="3" min="1" readonly>
                        <button onclick="increaseBoxes()">+</button>
                    </div>
                </div>

                <hr>

                <!-- Tracking Numbers -->
                <label><strong>Tracking Numbers</strong></label>
                <textarea class="form-control" rows="3" placeholder="Enter tracking numbers (1 per line)"></textarea>
                <small class="text-muted">1 line = 1 parcel</small>

                <hr>

                <!-- Carrier Selection -->
                <label><strong>Carrier</strong></label>
                <select class="form-control mb-3">
                    <option>NZ Post (Recommended - $45.80)</option>
                    <option>GoSweetSpot (Cheapest - $42.50)</option>
                    <option>CourierPost (Fastest - $58.20)</option>
                </select>

                <button class="btn btn-primary btn-block">
                    <i class="fa fa-tag"></i> Generate Labels
                </button>
            </div>
        </div>

        <!-- Productivity Tools -->
        <div class="tools-panel-a">
            <div class="tools-header-a">Productivity Tools</div>
            <button class="tool-btn-a" onclick="openPackingSlip()">
                <i class="fa fa-file-alt"></i> Packing Slip Generator
            </button>
            <button class="tool-btn-a" onclick="openEmailSummary()">
                <i class="fa fa-envelope"></i> Email Summary
            </button>
            <button class="tool-btn-a" onclick="openPhotoUpload()">
                <i class="fa fa-camera"></i> Photo Evidence
            </button>
            <button class="tool-btn-a" onclick="toggleAutoAssign()">
                <i class="fa fa-magic"></i> Auto-Assign Boxes
            </button>
            <button class="tool-btn-a" onclick="openSettings()">
                <i class="fa fa-cog"></i> Settings
            </button>
        </div>
    </div>
</div>

<script>
function switchMode(mode) {
    console.log('Switched to ' + mode + ' mode');
    // Update active tab
    document.querySelectorAll('.console-tab-a').forEach(tab => {
        tab.classList.remove('active');
    });
    event.target.classList.add('active');
}

function increaseBoxes() {
    const input = document.getElementById('box-count-a');
    input.value = parseInt(input.value) + 1;
}

function decreaseBoxes() {
    const input = document.getElementById('box-count-a');
    if (parseInt(input.value) > 1) {
        input.value = parseInt(input.value) - 1;
    }
}

function saveDraft() {
    alert('Draft saved!');
}

function openAIAdvisor() {
    alert('AI Advisor: This layout shows freight console on right side for easy access while packing.');
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

// Keyboard shortcut for search
document.addEventListener('keydown', function(e) {
    if (e.key === '/' && !['INPUT', 'TEXTAREA'].includes(e.target.tagName)) {
        e.preventDefault();
        document.getElementById('product-search-a').focus();
    }
});
</script>

<?php $theme->render('footer'); ?>
