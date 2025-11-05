<?php
/**
 * Advanced Transfer Packing - LAYOUT A: Professional & Compact
 * Two column split with maximum space efficiency
 */

require_once __DIR__ . '/../../base/_templates/themes/cis-classic/theme.php';

$theme = new CISClassicTheme();
$theme->setTitle('Pack Transfer #12345');
$theme->setPageSubtitle('Layout A: Two Column - Professional');
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

.pack-grid {
    display: grid;
    grid-template-columns: 1fr 320px;
    gap: 12px;
}

/* Compact Search Bar */
.search-bar {
    background: #fff;
    border: 1px solid #d1d5db;
    padding: 8px 12px;
    border-radius: 3px;
    margin-bottom: 12px;
}

.search-bar input {
    width: 100%;
    padding: 6px 10px;
    font-size: 13px;
    border: 1px solid #e1e4e8;
    border-radius: 3px;
}

.search-bar input:focus {
    border-color: #0366d6;
    outline: none;
    box-shadow: 0 0 0 2px rgba(3, 102, 214, 0.1);
}

/* Compact Stats */
.stats-bar {
    background: #fff;
    border: 1px solid #d1d5db;
    padding: 10px 12px;
    border-radius: 3px;
    margin-bottom: 12px;
    display: flex;
    gap: 20px;
    align-items: center;
}

.stat-item {
    display: flex;
    align-items: center;
    gap: 8px;
}

.stat-icon {
    width: 28px;
    height: 28px;
    border-radius: 3px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 13px;
}

.stat-icon.primary { background: #e3f2fd; color: #0366d6; }
.stat-icon.success { background: #d4edda; color: #28a745; }
.stat-icon.warning { background: #fff3cd; color: #ffc107; }
.stat-icon.danger { background: #f8d7da; color: #dc3545; }

.stat-content {
    display: flex;
    flex-direction: column;
    line-height: 1.2;
}

.stat-label {
    font-size: 10px;
    color: #6a737d;
    text-transform: uppercase;
    font-weight: 600;
}

.stat-value {
    font-size: 16px;
    font-weight: 600;
    color: #24292e;
}

/* Compact Product Table */
.product-table-container {
    background: #fff;
    border: 1px solid #d1d5db;
    border-radius: 3px;
    overflow: hidden;
}

.product-table {
    width: 100%;
    font-size: 13px;
}

.product-table thead {
    background: #fafbfc;
    border-bottom: 2px solid #e1e4e8;
}

.product-table th {
    padding: 8px 10px;
    font-size: 11px;
    font-weight: 600;
    color: #586069;
    text-transform: uppercase;
    text-align: left;
}

.product-table td {
    padding: 8px 10px;
    border-bottom: 1px solid #f0f0f0;
    vertical-align: middle;
}

.product-table tbody tr:hover {
    background: #f6f8fa;
}

.product-img-sm {
    width: 36px;
    height: 36px;
    object-fit: cover;
    border-radius: 3px;
    border: 1px solid #e1e4e8;
}

.product-name {
    font-weight: 500;
    color: #24292e;
    font-size: 13px;
}

.product-sku {
    font-family: 'Courier New', monospace;
    font-size: 11px;
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
    padding: 4px 6px;
    font-size: 12px;
    border: 1px solid #e1e4e8;
    border-radius: 3px;
}

.btn-compact {
    padding: 4px 10px;
    font-size: 12px;
    border-radius: 3px;
}

/* Status Colors */
.row-ok { background: #f0f9f4; }
.row-under { background: #fffbeb; }
.row-over { background: #fef2f2; }

/* Compact Freight Console */
.freight-console {
    background: #fff;
    border: 1px solid #d1d5db;
    border-radius: 3px;
    position: sticky;
    top: 12px;
}

.console-header {
    background: #fafbfc;
    padding: 10px 12px;
    border-bottom: 2px solid #e1e4e8;
    font-weight: 600;
    font-size: 13px;
    color: #24292e;
}

.console-tabs {
    display: flex;
    border-bottom: 1px solid #e1e4e8;
}

.console-tab {
    flex: 1;
    padding: 8px 10px;
    border: none;
    background: none;
    cursor: pointer;
    font-size: 12px;
    color: #6a737d;
    transition: all 0.2s;
}

.console-tab.active {
    background: #fff;
    color: #0366d6;
    border-bottom: 2px solid #0366d6;
    font-weight: 600;
}

.console-body {
    padding: 12px;
}

.metric-row {
    display: flex;
    justify-content: space-between;
    padding: 6px 0;
    font-size: 12px;
    border-bottom: 1px solid #f0f0f0;
}

.metric-label {
    color: #6a737d;
}

.metric-value {
    font-weight: 600;
    color: #24292e;
}

.box-control {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 10px 0;
}

.box-control button {
    width: 32px;
    height: 32px;
    border: 1px solid #e1e4e8;
    background: #fafbfc;
    border-radius: 3px;
    cursor: pointer;
    font-size: 16px;
}

.box-control input {
    width: 60px;
    text-align: center;
    padding: 6px;
    font-size: 16px;
    font-weight: 600;
    border: 1px solid #e1e4e8;
    border-radius: 3px;
}

.tracking-input {
    width: 100%;
    padding: 6px 8px;
    font-size: 12px;
    border: 1px solid #e1e4e8;
    border-radius: 3px;
    resize: vertical;
    font-family: 'Courier New', monospace;
}

.carrier-select {
    width: 100%;
    padding: 8px;
    font-size: 12px;
    border: 1px solid #e1e4e8;
    border-radius: 3px;
    margin: 8px 0;
}

.btn-generate {
    width: 100%;
    padding: 10px;
    background: #0366d6;
    color: #fff;
    border: none;
    border-radius: 3px;
    font-size: 13px;
    font-weight: 600;
    cursor: pointer;
}

.btn-generate:hover {
    background: #0256c7;
}

/* Tools Section */
.tools-section {
    margin-top: 12px;
    padding-top: 12px;
    border-top: 2px solid #e1e4e8;
}

.tools-grid {
    display: grid;
    gap: 8px;
}

.tool-btn {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 10px;
    background: #fafbfc;
    border: 1px solid #e1e4e8;
    border-radius: 3px;
    cursor: pointer;
    transition: all 0.2s;
    font-size: 12px;
}

.tool-btn:hover {
    background: #f3f4f6;
    border-color: #d1d5db;
}

.tool-icon {
    width: 24px;
    text-align: center;
    color: #0366d6;
}

/* Responsive */
@media (max-width: 1200px) {
    .pack-grid {
        grid-template-columns: 1fr;
    }
    .freight-console {
        position: relative;
        top: 0;
    }
}
</style>

<div class="pack-container">
    <!-- Search Bar -->
    <div class="search-bar">
        <input type="text" placeholder="Search products by name, SKU, or barcode... (Press / to focus)" id="search-input">
    </div>

    <!-- Compact Stats -->
    <div class="stats-bar">
        <div class="stat-item">
            <div class="stat-icon primary"><i class="fa fa-box"></i></div>
            <div class="stat-content">
                <span class="stat-label">Boxes</span>
                <span class="stat-value">3</span>
            </div>
        </div>
        <div class="stat-item">
            <div class="stat-icon success"><i class="fa fa-weight"></i></div>
            <div class="stat-content">
                <span class="stat-label">Weight</span>
                <span class="stat-value">15.7kg</span>
            </div>
        </div>
        <div class="stat-item">
            <div class="stat-icon warning"><i class="fa fa-check-circle"></i></div>
            <div class="stat-content">
                <span class="stat-label">Packed</span>
                <span class="stat-value">65%</span>
            </div>
        </div>
        <div class="stat-item">
            <div class="stat-icon primary"><i class="fa fa-dollar-sign"></i></div>
            <div class="stat-content">
                <span class="stat-label">Freight</span>
                <span class="stat-value">$45.80</span>
            </div>
        </div>
    </div>

    <div class="pack-grid">
        <!-- LEFT: Product Table -->
        <div class="product-table-container">
            <table class="product-table">
                <thead>
                    <tr>
                        <th style="width: 40px;"></th>
                        <th>Product</th>
                        <th style="width: 100px;">SKU</th>
                        <th style="width: 70px;">Plan</th>
                        <th style="width: 70px;">Pack</th>
                        <th style="width: 90px;">Box</th>
                        <th style="width: 60px;"></th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="row-ok">
                        <td><img src="/placeholder.jpg" alt="" class="product-img-sm"></td>
                        <td>
                            <div class="product-name">Vaporesso XROS 3 Pod Kit</div>
                            <div class="product-sku">VP-XR3-BLK</div>
                        </td>
                        <td><code>VP-XR3-BLK</code></td>
                        <td><input type="number" class="input-compact" value="10" readonly></td>
                        <td><input type="number" class="input-compact" value="10"></td>
                        <td>
                            <select class="select-compact">
                                <option>Box 1</option>
                                <option>Box 2</option>
                                <option>Box 3</option>
                            </select>
                        </td>
                        <td><button class="btn btn-success btn-compact"><i class="fa fa-check"></i></button></td>
                    </tr>
                    <tr class="row-under">
                        <td><img src="/placeholder.jpg" alt="" class="product-img-sm"></td>
                        <td>
                            <div class="product-name">SMOK Nord 5 Pods</div>
                            <div class="product-sku">SM-N5-POD</div>
                        </td>
                        <td><code>SM-N5-POD</code></td>
                        <td><input type="number" class="input-compact" value="25" readonly></td>
                        <td><input type="number" class="input-compact" value="20"></td>
                        <td>
                            <select class="select-compact">
                                <option>Box 1</option>
                                <option>Box 2</option>
                                <option>Box 3</option>
                            </select>
                        </td>
                        <td><button class="btn btn-warning btn-compact"><i class="fa fa-exclamation"></i></button></td>
                    </tr>
                    <tr class="row-over">
                        <td><img src="/placeholder.jpg" alt="" class="product-img-sm"></td>
                        <td>
                            <div class="product-name">Caliburn G2 Coils 0.8Ω</div>
                            <div class="product-sku">UV-G2-08</div>
                        </td>
                        <td><code>UV-G2-08</code></td>
                        <td><input type="number" class="input-compact" value="15" readonly></td>
                        <td><input type="number" class="input-compact" value="18"></td>
                        <td>
                            <select class="select-compact">
                                <option>Box 2</option>
                                <option>Box 1</option>
                                <option>Box 3</option>
                            </select>
                        </td>
                        <td><button class="btn btn-danger btn-compact"><i class="fa fa-times"></i></button></td>
                    </tr>
                </tbody>
            </table>
        </div>

        <!-- RIGHT: Freight Console -->
        <div>
            <div class="freight-console">
                <div class="console-header">
                    <i class="fa fa-truck"></i> Freight Console
                </div>

                <div class="console-tabs">
                    <button class="console-tab active">Manual</button>
                    <button class="console-tab">Pickup</button>
                    <button class="console-tab">Drop-off</button>
                </div>

                <div class="console-body">
                    <div class="metric-row">
                        <span class="metric-label">Weight:</span>
                        <span class="metric-value">15.7 kg</span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">Volume:</span>
                        <span class="metric-value">0.045 m³</span>
                    </div>
                    <div class="metric-row">
                        <span class="metric-label">Recommended:</span>
                        <span class="metric-value" style="color: #28a745;">NZ Post</span>
                    </div>
                    <div class="metric-row" style="border-bottom: none;">
                        <span class="metric-label">Est. Cost:</span>
                        <span class="metric-value" style="color: #0366d6;">$45.80</span>
                    </div>

                    <hr style="margin: 12px 0; border: none; border-top: 1px solid #e1e4e8;">

                    <!-- Tracking System Alert -->
                    <div style="background: #fff8e1; border: 1px solid #ffc107; border-radius: 3px; padding: 10px; margin-bottom: 12px;">
                        <div style="display: flex; align-items: center; gap: 8px; font-size: 11px; color: #856404; margin-bottom: 6px;">
                            <i class="fa fa-info-circle"></i>
                            <strong>Automated Tracking</strong>
                        </div>
                        <div style="font-size: 10px; color: #856404; line-height: 1.4;">
                            When you click "Generate Labels", the courier API will automatically create <strong>3 tracking numbers</strong> (one per box).
                        </div>
                        <div style="display: flex; align-items: center; justify-content: space-between; margin-top: 8px; padding: 6px 8px; background: #fff; border-radius: 3px;">
                            <div style="display: flex; align-items: center; gap: 6px; font-size: 11px;">
                                <i class="fa fa-boxes" style="color: #0366d6;"></i>
                                <span style="font-weight: 600;">3 Boxes</span>
                            </div>
                            <i class="fa fa-arrow-right" style="color: #6a737d; font-size: 10px;"></i>
                            <div style="display: flex; align-items: center; gap: 6px; font-size: 11px; color: #28a745; font-weight: 600;">
                                <i class="fa fa-check-circle"></i>
                                <span>3 Tracking #s</span>
                            </div>
                        </div>
                    </div>

                    <label style="font-size: 11px; font-weight: 600; color: #586069; text-transform: uppercase;">Boxes</label>
                    <div class="box-control">
                        <button>−</button>
                        <input type="number" value="3" readonly>
                        <button>+</button>
                    </div>

                    <label style="font-size: 11px; font-weight: 600; color: #586069; text-transform: uppercase; display: block; margin-top: 12px;">Carrier</label>
                    <select class="carrier-select">
                        <option>NZ Post (Recommended - $45.80)</option>
                        <option>GoSweetSpot (Cheapest - $42.50)</option>
                        <option>CourierPost (Fastest - $58.20)</option>
                    </select>

                                        <button class="btn-generate" style="background: #6c757d; margin-bottom: 8px;" onclick="window.open('print-box-labels.php?transfer_id=12345', '_blank')">
                        <i class="fa fa-box"></i> Print Box Labels (Internal)
                    </button>

                    <button class="btn-generate">
                        <i class="fa fa-tag"></i> Generate 3 Shipping Labels
                    </button>
                    <small style="font-size: 9px; color: #6a737d; display: block; margin-top: 6px; text-align: center;">
                        Creates: Shipment → Parcel (per box) → Items
                    </small>
                </div>

                <div class="tools-section" style="padding: 0 12px 12px;">
                    <div class="tools-grid">
                        <button class="tool-btn" onclick="window.open('print-box-labels.php?transfer_id=12345', '_blank')">
                            <i class="fa fa-box tool-icon"></i>
                            <span>Box Labels</span>
                        </button>
                        <button class="tool-btn">
                            <i class="fa fa-file-alt tool-icon"></i>
                            <span>Packing Slip</span>
                        </button>
                        <button class="tool-btn">
                            <i class="fa fa-envelope tool-icon"></i>
                            <span>Email</span>
                        </button>
                        <button class="tool-btn">
                            <i class="fa fa-camera tool-icon"></i>
                            <span>Photos</span>
                        </button>
                        <button class="tool-btn">
                            <i class="fa fa-magic tool-icon"></i>
                            <span>Auto-Assign</span>
                        </button>
                        <button class="tool-btn">
                            <i class="fa fa-robot tool-icon"></i>
                            <span>AI Advisor</span>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// Keyboard shortcut
document.addEventListener('keydown', function(e) {
    if (e.key === '/' && !['INPUT', 'TEXTAREA'].includes(e.target.tagName)) {
        e.preventDefault();
        document.getElementById('search-input').focus();
    }
});

function saveDraft() { alert('Draft saved!'); }
function openAIAdvisor() { alert('AI Advisor opened'); }
function finishPacking() {
    if (confirm('Generate thermal labels and finish packing?')) {
        alert('Generating 3 thermal labels (80mm) and sending email summary...');
    }
}
</script>

<?php $theme->render('footer'); ?>
