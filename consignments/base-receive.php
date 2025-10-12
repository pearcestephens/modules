<?php
/**
 * BASE Receive Template - Universal Receiving Interface
 * 
 * Handles receiving for all transfer modes (GENERAL, JUICE, STAFF, SUPPLIER)
 * with partial deliveries, unexpected products, and enterprise-grade UI.
 * 
 * Features:
 * - Partial delivery support with quantity adjustments
 * - Add unexpected products functionality
 * - Real-time validation and auto-save
 * - Enterprise design with award-winning UI
 * - Complete Lightspeed integration
 * 
 * @package CIS\Consignments\Templates
 * @version 1.0.0
 * @created 2025-10-12
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once __DIR__ . '/../module_bootstrap.php';

use Consignments\Lib\Db;
use Consignments\Lib\Security;
use Consignments\Lib\Validation;
use Consignments\Lib\Log;

// ========================================
// SECURITY & INITIALIZATION
// ========================================

// Verify user authentication
Security::requireAuth();

// Get transfer ID and mode from URL
$transferId = Validation::sanitizeInt($_GET['id'] ?? 0);
$transferMode = Validation::sanitizeString($_GET['mode'] ?? '');

if (!$transferId) {
    header('Location: /modules/consignments/?error=missing_transfer_id');
    exit;
}

// ========================================
// DATA LOADING
// ========================================

$db = Db::getInstance();

try {
    // Load transfer details
    $transfer = $db->fetchRow(
        "SELECT t.*, 
                fo.name as from_outlet_name, fo.vend_outlet_id as from_vend_id,
                too.name as to_outlet_name, too.vend_outlet_id as to_vend_id,
                u.name as created_by_name
         FROM transfers t
         LEFT JOIN vend_outlets fo ON t.from_outlet_id = fo.id
         LEFT JOIN vend_outlets too ON t.to_outlet_id = too.id
         LEFT JOIN users u ON t.created_by = u.id
         WHERE t.id = ? AND t.status IN ('SENT', 'IN_TRANSIT', 'PARTIAL_RECEIVED')",
        [$transferId]
    );
    
    if (!$transfer) {
        header('Location: /modules/consignments/?error=transfer_not_found');
        exit;
    }
    
    // Override mode if provided in URL
    if ($transferMode && in_array($transferMode, ['GENERAL', 'JUICE', 'STAFF', 'SUPPLIER'])) {
        $transfer['mode'] = $transferMode;
    }
    
    // Load transfer items with current receive status
    $transferItems = $db->fetchAll(
        "SELECT ti.*, p.name, p.sku, p.vend_product_id, p.avg_weight_grams,
                p.max_weight_grams, p.fragile, p.contains_nicotine,
                c.name as category_name, c.id as category_id,
                COALESCE(ti.qty_received, 0) as qty_received,
                (ti.qty_requested - COALESCE(ti.qty_received, 0)) as qty_remaining
         FROM transfer_items ti
         LEFT JOIN vend_products p ON ti.product_id = p.id
         LEFT JOIN vend_categories c ON p.category_id = c.id
         WHERE ti.transfer_id = ?
         ORDER BY p.name ASC",
        [$transferId]
    );
    
    // Calculate totals
    $totalRequested = 0;
    $totalReceived = 0;
    $totalWeightRequested = 0;
    $totalWeightReceived = 0;
    $hasFragile = false;
    $hasNicotine = false;
    
    foreach ($transferItems as $item) {
        $totalRequested += $item['qty_requested'];
        $totalReceived += $item['qty_received'];
        
        $weight = $item['avg_weight_grams'] ?: 500; // Default 500g
        $totalWeightRequested += $weight * $item['qty_requested'];
        $totalWeightReceived += $weight * $item['qty_received'];
        
        if ($item['fragile']) $hasFragile = true;
        if ($item['contains_nicotine']) $hasNicotine = true;
    }
    
    // Determine header styling based on mode
    $modeConfig = [
        'GENERAL' => [
            'color' => 'primary',
            'icon' => 'fas fa-exchange-alt',
            'title' => 'Stock Transfer',
            'description' => 'General inventory movement between outlets'
        ],
        'JUICE' => [
            'color' => 'warning',
            'icon' => 'fas fa-tint',
            'title' => 'Juice/E-liquid Transfer',
            'description' => 'Regulated nicotine product movement'
        ],
        'STAFF' => [
            'color' => 'info',
            'icon' => 'fas fa-user-friends',
            'title' => 'Staff Order',
            'description' => 'Employee personal purchase delivery'
        ],
        'SUPPLIER' => [
            'color' => 'success',
            'icon' => 'fas fa-truck',
            'title' => 'Supplier Delivery',
            'description' => 'Incoming stock from external supplier'
        ]
    ];
    
    $config = $modeConfig[$transfer['mode']] ?? $modeConfig['GENERAL'];
    
} catch (Exception $e) {
    Log::error("Failed to load receive data", [
        'transfer_id' => $transferId,
        'error' => $e->getMessage()
    ]);
    
    header('Location: /modules/consignments/?error=data_load_failed');
    exit;
}

// ========================================
// HTML OUTPUT
// ========================================
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receive <?= htmlspecialchars($config['title']) ?> - The Vape Shed</title>
    
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="/modules/consignments/css/base-receive.css" rel="stylesheet">
    
    <!-- Favicon -->
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>
<body>
    
<div class="container-fluid py-4 receive-container" 
     data-transfer-id="<?= $transferId ?>" 
     data-transfer-mode="<?= htmlspecialchars($transfer['mode']) ?>">

    <!-- ========================================
         RECEIVE HEADER CARD
    ======================================== -->
    <div class="card receive-header-card shadow-soft mb-4 animated fadeIn">
        <div class="receive-header bg-gradient-<?= $config['color'] ?> text-white">
            <div class="container-fluid p-4">
                <div class="row align-items-center">
                    
                    <!-- Transfer Icon & Info -->
                    <div class="col-lg-3 text-center text-lg-start mb-3 mb-lg-0">
                        <div class="d-flex align-items-center justify-content-center justify-content-lg-start">
                            <div class="receive-icon glass-effect me-3">
                                <i class="<?= $config['icon'] ?> fa-2x"></i>
                            </div>
                            <div>
                                <h4 class="mb-1 fw-bold">Receive <?= htmlspecialchars($config['title']) ?></h4>
                                <p class="mb-0 opacity-75"><?= htmlspecialchars($config['description']) ?></p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Route Information -->
                    <div class="col-lg-6 mb-3 mb-lg-0">
                        <div class="d-flex align-items-center justify-content-center">
                            <div class="route-point text-center">
                                <div class="fw-bold"><?= htmlspecialchars($transfer['from_outlet_name']) ?></div>
                                <small class="opacity-75">Source</small>
                            </div>
                            <div class="route-arrow mx-3">
                                <i class="fas fa-arrow-right fa-2x opacity-75"></i>
                            </div>
                            <div class="route-point text-center">
                                <div class="fw-bold"><?= htmlspecialchars($transfer['to_outlet_name']) ?></div>
                                <small class="opacity-75">Destination</small>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Stats -->
                    <div class="col-lg-3">
                        <div class="quick-stats">
                            <div class="stat-item">
                                <div class="stat-value"><?= number_format($totalReceived) ?>/<?= number_format($totalRequested) ?></div>
                                <div class="stat-label">Items</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?= number_format($totalWeightReceived / 1000, 1) ?>kg</div>
                                <div class="stat-label">Received</div>
                            </div>
                            <div class="stat-item">
                                <div class="stat-value"><?= number_format((($totalReceived / max($totalRequested, 1)) * 100), 0) ?>%</div>
                                <div class="stat-label">Complete</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Action Toolbar -->
        <div class="action-toolbar border-0">
            <div class="container-fluid p-3">
                <div class="row align-items-center">
                    <div class="col-md-6">
                        <span class="text-muted">
                            <i class="fas fa-info-circle me-2"></i>
                            Transfer #<?= $transferId ?> • Created <?= date('M j, Y', strtotime($transfer['created_at'])) ?>
                        </span>
                    </div>
                    <div class="col-md-6 text-end">
                        <button type="button" class="btn btn-outline-primary btn-sm me-2" id="add-product-btn">
                            <i class="fas fa-plus me-1"></i> Add Product
                        </button>
                        <span class="badge bg-<?= $config['color'] ?> fs-6">
                            Status: <?= ucfirst(str_replace('_', ' ', strtolower($transfer['status']))) ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        
        <!-- ========================================
             TRANSFER ITEMS SECTION
        ======================================== -->
        <div class="col-lg-8">
            <div class="card receive-items-card shadow-soft mb-4 animated fadeIn">
                <div class="card-header bg-light border-0">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-list me-2 text-<?= $config['color'] ?>"></i>
                            Transfer Items
                        </h5>
                        <span class="text-muted">
                            <?= count($transferItems) ?> products • 
                            <?= number_format($totalRequested - $totalReceived) ?> remaining
                        </span>
                    </div>
                </div>
                <div class="card-body p-0">
                    <?php if (empty($transferItems)): ?>
                        <div class="empty-state text-center py-5">
                            <i class="fas fa-inbox fa-4x text-muted mb-3"></i>
                            <h5 class="text-muted">No items in this transfer</h5>
                            <p class="text-muted">Use "Add Product" to include unexpected items.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-hover receive-items-table mb-0">
                                <thead class="sticky-top">
                                    <tr>
                                        <th style="width: 40%">Product</th>
                                        <th style="width: 15%" class="text-center">Requested</th>
                                        <th style="width: 15%" class="text-center">Received</th>
                                        <th style="width: 15%" class="text-center">Remaining</th>
                                        <th style="width: 10%" class="text-center">Weight</th>
                                        <th style="width: 5%" class="text-center">Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($transferItems as $item): ?>
                                        <?php
                                        $qtyRequested = $item['qty_requested'];
                                        $qtyReceived = $item['qty_received'];
                                        $qtyRemaining = $item['qty_remaining'];
                                        $weightGrams = $item['avg_weight_grams'] ?: 500;
                                        
                                        // Determine row styling
                                        $rowClass = 'table-secondary'; // Default (not started)
                                        if ($qtyReceived >= $qtyRequested) {
                                            $rowClass = 'table-success'; // Complete
                                        } elseif ($qtyReceived > 0) {
                                            $rowClass = 'table-warning'; // Partial
                                        }
                                        ?>
                                        <tr class="receive-item-row <?= $rowClass ?>" 
                                            data-item-id="<?= $item['id'] ?>"
                                            data-product-id="<?= $item['product_id'] ?>"
                                            data-weight-grams="<?= $weightGrams ?>"
                                            data-fragile="<?= $item['fragile'] ? '1' : '0' ?>"
                                            data-nicotine="<?= $item['contains_nicotine'] ? '1' : '0' ?>">
                                            
                                            <!-- Product Information -->
                                            <td class="product-cell">
                                                <div class="product-info">
                                                    <div class="product-name fw-semibold">
                                                        <?= htmlspecialchars($item['name']) ?>
                                                    </div>
                                                    <div class="product-meta">
                                                        SKU: <?= htmlspecialchars($item['sku']) ?> • 
                                                        <?= htmlspecialchars($item['category_name'] ?: 'Uncategorized') ?>
                                                        <?php if ($item['fragile']): ?>
                                                            <span class="badge bg-warning ms-1">Fragile</span>
                                                        <?php endif; ?>
                                                        <?php if ($item['contains_nicotine']): ?>
                                                            <span class="badge bg-danger ms-1">Nicotine</span>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </td>
                                            
                                            <!-- Requested Quantity -->
                                            <td class="text-center align-middle">
                                                <div class="qty-display text-primary fw-bold">
                                                    <?= number_format($qtyRequested) ?>
                                                </div>
                                            </td>
                                            
                                            <!-- Received Quantity (Editable) -->
                                            <td class="text-center align-middle">
                                                <div class="qty-input-group">
                                                    <input type="number" 
                                                           class="form-control qty-input text-center" 
                                                           value="<?= $qtyReceived ?>"
                                                           min="0" 
                                                           max="<?= $qtyRequested + 100 ?>"
                                                           data-original="<?= $qtyReceived ?>">
                                                </div>
                                            </td>
                                            
                                            <!-- Remaining Quantity -->
                                            <td class="text-center align-middle">
                                                <div class="qty-display text-muted remaining-qty">
                                                    <?= number_format($qtyRemaining) ?>
                                                </div>
                                            </td>
                                            
                                            <!-- Weight -->
                                            <td class="text-center align-middle">
                                                <div class="weight-display text-muted">
                                                    <?= number_format($weightGrams * $qtyReceived / 1000, 2) ?>kg
                                                </div>
                                            </td>
                                            
                                            <!-- Status Indicator -->
                                            <td class="text-center align-middle">
                                                <div class="status-indicator">
                                                    <?php if ($qtyReceived >= $qtyRequested): ?>
                                                        <i class="fas fa-check-circle text-success" title="Complete"></i>
                                                    <?php elseif ($qtyReceived > 0): ?>
                                                        <i class="fas fa-clock text-warning" title="Partial"></i>
                                                    <?php else: ?>
                                                        <i class="fas fa-circle text-muted" title="Pending"></i>
                                                    <?php endif; ?>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- ========================================
             RECEIVE SUMMARY PANEL
        ======================================== -->
        <div class="col-lg-4">
            
            <!-- Receive Summary -->
            <div class="card receive-summary-card shadow-soft mb-4 animated fadeIn">
                <div class="card-header text-white border-0">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-calculator me-2"></i>
                        Receive Summary
                    </h6>
                </div>
                <div class="card-body">
                    <div class="metric-item d-flex justify-content-between">
                        <span class="metric-label">Total Requested:</span>
                        <span class="metric-value fw-bold"><?= number_format($totalRequested) ?></span>
                    </div>
                    <div class="metric-item d-flex justify-content-between">
                        <span class="metric-label">Total Received:</span>
                        <span class="metric-value fw-bold total-received-display"><?= number_format($totalReceived) ?></span>
                    </div>
                    <div class="metric-item d-flex justify-content-between">
                        <span class="metric-label">Remaining:</span>
                        <span class="metric-value fw-bold total-remaining-display"><?= number_format($totalRequested - $totalReceived) ?></span>
                    </div>
                    <hr>
                    <div class="metric-item d-flex justify-content-between">
                        <span class="metric-label">Weight Received:</span>
                        <span class="metric-value fw-bold total-weight-display"><?= number_format($totalWeightReceived / 1000, 2) ?>kg</span>
                    </div>
                    <div class="metric-item d-flex justify-content-between">
                        <span class="metric-label">Estimated Boxes:</span>
                        <span class="metric-value fw-bold estimated-boxes-display"><?= max(1, ceil($totalWeightReceived / 5000)) ?></span>
                    </div>
                    <hr>
                    <div class="metric-item d-flex justify-content-between">
                        <span class="metric-label">Completion:</span>
                        <span class="metric-value fw-bold completion-percentage"><?= number_format(($totalReceived / max($totalRequested, 1)) * 100, 1) ?>%</span>
                    </div>
                    
                    <!-- Special Requirements -->
                    <div class="special-requirements mt-3">
                        <div class="metric-label mb-2">Special Requirements:</div>
                        <div class="requirement-badge">
                            <?php if ($hasFragile): ?>
                                <span class="badge bg-warning me-1">
                                    <i class="fas fa-exclamation-triangle"></i> Fragile
                                </span>
                            <?php endif; ?>
                            <?php if ($hasNicotine): ?>
                                <span class="badge bg-danger me-1">
                                    <i class="fas fa-ban"></i> Nicotine
                                </span>
                            <?php endif; ?>
                            <?php if (!$hasFragile && !$hasNicotine): ?>
                                <span class="badge bg-success">
                                    <i class="fas fa-check"></i> Standard
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Receive Actions -->
            <div class="card receive-actions-card shadow-soft mb-4 animated fadeIn">
                <div class="card-header bg-light border-0">
                    <h6 class="card-title mb-0">
                        <i class="fas fa-tools me-2"></i>
                        Actions
                    </h6>
                </div>
                <div class="card-body">
                    
                    <!-- Auto-save Indicator -->
                    <div class="auto-save-indicator mb-3">
                        <i class="fas fa-check text-success"></i>
                        <span class="ms-2">Auto-save enabled</span>
                    </div>
                    
                    <!-- Action Buttons -->
                    <div class="d-grid gap-2">
                        <button type="button" class="btn btn-success btn-lg" id="complete-receive">
                            <i class="fas fa-check-double me-2"></i>
                            Complete Receive
                        </button>
                        
                        <button type="button" class="btn btn-warning" id="partial-receive">
                            <i class="fas fa-clock me-2"></i>
                            Save as Partial
                        </button>
                        
                        <button type="button" class="btn btn-outline-primary" id="save-receive">
                            <i class="fas fa-save me-2"></i>
                            Save Progress
                        </button>
                        
                        <button type="button" class="btn btn-outline-secondary" id="reset-receive">
                            <i class="fas fa-undo me-2"></i>
                            Reset Changes
                        </button>
                    </div>
                    
                    <hr>
                    
                    <!-- Status Information -->
                    <div class="status-info">
                        <div class="small text-muted">
                            <div><strong>Transfer ID:</strong> <?= $transferId ?></div>
                            <div><strong>Mode:</strong> <?= htmlspecialchars($transfer['mode']) ?></div>
                            <div><strong>Created:</strong> <?= date('M j, Y g:i A', strtotime($transfer['created_at'])) ?></div>
                            <div><strong>Status:</strong> <?= ucfirst(str_replace('_', ' ', strtolower($transfer['status']))) ?></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ========================================
     ADD PRODUCT MODAL
======================================== -->
<div class="modal fade" id="addProductModal" tabindex="-1" aria-labelledby="addProductModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-gradient-primary text-white">
                <h5 class="modal-title" id="addProductModalLabel">
                    <i class="fas fa-plus-circle me-2"></i>
                    Add Unexpected Product
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <form id="addProductForm">
                    <div class="row">
                        <div class="col-md-8">
                            <label for="productSearch" class="form-label">Search Products</label>
                            <input type="text" class="form-control" id="productSearch" 
                                   placeholder="Start typing product name or SKU...">
                            <div id="productSearchResults" class="mt-2"></div>
                        </div>
                        <div class="col-md-4">
                            <label for="addQuantity" class="form-label">Quantity</label>
                            <input type="number" class="form-control" id="addQuantity" 
                                   min="1" max="9999" value="1">
                        </div>
                    </div>
                    
                    <div id="selectedProductInfo" class="mt-3" style="display: none;">
                        <div class="alert alert-info">
                            <strong>Selected Product:</strong>
                            <div id="selectedProductDetails"></div>
                        </div>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" id="confirmAddProduct" disabled>
                    <i class="fas fa-plus me-1"></i> Add Product
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Bootstrap 5 JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- Custom JavaScript -->
<script src="/modules/consignments/js/base-receive.js"></script>

</body>
</html>