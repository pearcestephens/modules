<?php
/**
 * BASE Transfer Template - Universal MVP
 * 
 * Enterprise-grade transfer interface supporting all 4 modes:
 * - GENERAL: Standard stock transfers  
 * - JUICE: E-liquid compliance transfers
 * - STAFF: Employee personal orders
 * - SUPPLIER: Purchase order receiving
 * 
 * Features:
 * - Universal HTML structure with mode detection
 * - Basic freight calculation (weight/box estimation)
 * - Enterprise-level UI/UX design
 * - Auto-save functionality
 * - Color-coded validation
 * - Lightspeed integration ready
 * 
 * @author CIS Development Team
 * @version 1.0.0
 * @created 2025-10-12
 */

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';

// Get transfer data
$transfer_id = (int)($_GET['transfer'] ?? $_POST['transfer_id'] ?? 0);
if ($transfer_id <= 0) {
    header('Location: /modules/transfers/');
    exit('Invalid transfer ID');
}

// Load transfer data with full details
$stmt = cis_pdo()->prepare("
    SELECT t.*, 
           vo_from.name as outlet_from_name,
           vo_to.name as outlet_to_name,
           vo_from.physical_address_1 as from_address,
           vo_from.physical_city as from_city,
           vo_to.physical_address_1 as to_address,
           vo_to.physical_city as to_city,
           u.first_name, u.last_name,
           COALESCE(t.mode, 'GENERAL') as transfer_mode
    FROM transfers t
    LEFT JOIN vend_outlets vo_from ON vo_from.id = t.outlet_from
    LEFT JOIN vend_outlets vo_to ON vo_to.id = t.outlet_to  
    LEFT JOIN users u ON u.id = t.created_by
    WHERE t.id = :transfer_id
");
$stmt->execute([':transfer_id' => $transfer_id]);
$transfer = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$transfer) {
    die('Transfer not found');
}

// Load transfer items with weights and dimensions
$stmt = cis_pdo()->prepare("
    SELECT ti.*,
           vp.name as product_name,
           vp.sku,
           vp.handle,
           COALESCE(vp.avg_weight_grams, cw.avg_weight_grams, 500) as unit_weight_g,
           COALESCE(ti.qty_sent_total, ti.qty_requested, 0) as effective_qty,
           CASE 
               WHEN vp.name LIKE '%glass%' OR vp.name LIKE '%fragile%' THEN 1 
               ELSE 0 
           END as is_fragile,
           CASE 
               WHEN vp.name LIKE '%nicotine%' OR vp.name LIKE '%nic %' THEN 1 
               ELSE 0 
           END as contains_nicotine
    FROM transfer_items ti
    LEFT JOIN vend_products vp ON vp.id = ti.product_id
    LEFT JOIN product_classification_unified pcu ON pcu.product_id = vp.id
    LEFT JOIN category_weights cw ON cw.category_id = pcu.category_id
    WHERE ti.transfer_id = :transfer_id
      AND ti.deleted_at IS NULL
    ORDER BY contains_nicotine DESC, is_fragile DESC, vp.name ASC
");
$stmt->execute([':transfer_id' => $transfer_id]);
$transfer_items = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calculate basic freight metrics
$total_weight_g = 0;
$total_items = 0;
$fragile_count = 0;
$nicotine_count = 0;

foreach ($transfer_items as $item) {
    $qty = max(1, (int)$item['effective_qty']);
    $unit_weight = max(50, (int)$item['unit_weight_g']); // Minimum 50g per item
    
    $total_weight_g += ($unit_weight * $qty);
    $total_items += $qty;
    
    if ($item['is_fragile']) $fragile_count += $qty;
    if ($item['contains_nicotine']) $nicotine_count += $qty;
}

// Estimate box requirements (basic algorithm)
$estimated_boxes = max(1, ceil($total_weight_g / 5000)); // 5kg per box average
if ($fragile_count > 0) $estimated_boxes++; // Extra box for fragile
if ($nicotine_count > 0 && ($total_items - $nicotine_count) > 0) $estimated_boxes++; // Separate nicotine

// Mode configuration
$mode_config = [
    'GENERAL' => [
        'title' => 'Stock Transfer',
        'icon' => '📦',
        'color' => 'primary',
        'description' => 'Standard outlet-to-outlet inventory transfer'
    ],
    'JUICE' => [
        'title' => 'Juice Transfer', 
        'icon' => '🧪',
        'color' => 'warning',
        'description' => 'E-liquid transfer with regulatory compliance'
    ],
    'STAFF' => [
        'title' => 'Staff Transfer',
        'icon' => '👤', 
        'color' => 'info',
        'description' => 'Employee personal order with approval workflow'
    ],
    'SUPPLIER' => [
        'title' => 'Purchase Order',
        'icon' => '🏭',
        'color' => 'success', 
        'description' => 'Supplier delivery with evidence capture'
    ]
];

$current_mode = $mode_config[$transfer['transfer_mode']] ?? $mode_config['GENERAL'];

// Page configuration
$page_title = $current_mode['title'] . " #{$transfer_id}";
$page_action = ($_GET['action'] ?? 'pack'); // 'pack' or 'receive'

include_once $_SERVER['DOCUMENT_ROOT'] . '/CIS_TEMPLATE.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($page_title) ?> - The Vape Shed</title>
    
    <!-- BASE Transfer Styles -->
    <link rel="stylesheet" href="/modules/consignments/css/base-transfer.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>

<body class="app header-fixed sidebar-fixed aside-menu-fixed sidebar-lg-show">
    <div class="app-body">
        <main class="main">
            <!-- Breadcrumb Navigation -->
            <ol class="breadcrumb border-0 m-0">
                <li class="breadcrumb-item">
                    <a href="/modules/transfers/">Transfers</a>
                </li>
                <li class="breadcrumb-item">
                    <a href="/modules/transfers/?mode=<?= strtolower($transfer['transfer_mode']) ?>">
                        <?= $current_mode['title'] ?>s
                    </a>
                </li>
                <li class="breadcrumb-item active">
                    <?= $current_mode['icon'] ?> #<?= $transfer_id ?>
                </li>
            </ol>

            <!-- Main Container -->
            <div class="container-fluid px-4 py-3">
                <div class="animated fadeIn">
                    
                    <!-- Transfer Header Card -->
                    <div class="card border-0 shadow-lg mb-4 transfer-header-card">
                        <div class="card-body p-0">
                            <!-- Gradient Header -->
                            <div class="transfer-header bg-gradient-<?= $current_mode['color'] ?> text-white p-4">
                                <div class="row align-items-center">
                                    <div class="col-lg-8">
                                        <div class="d-flex align-items-center mb-2">
                                            <div class="transfer-icon me-3">
                                                <span class="display-6"><?= $current_mode['icon'] ?></span>
                                            </div>
                                            <div>
                                                <h1 class="h2 mb-1 fw-bold">
                                                    <?= $current_mode['title'] ?> #<?= $transfer_id ?>
                                                </h1>
                                                <p class="mb-0 opacity-90">
                                                    <?= htmlspecialchars($current_mode['description']) ?>
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <!-- Transfer Route -->
                                        <div class="transfer-route d-flex align-items-center">
                                            <div class="route-point">
                                                <small class="text-uppercase opacity-75">From</small>
                                                <div class="fw-semibold"><?= htmlspecialchars($transfer['outlet_from_name']) ?></div>
                                                <small class="opacity-90"><?= htmlspecialchars($transfer['from_city']) ?></small>
                                            </div>
                                            <div class="route-arrow mx-4">
                                                <i class="fas fa-arrow-right fa-2x opacity-75"></i>
                                            </div>
                                            <div class="route-point">
                                                <small class="text-uppercase opacity-75">To</small>
                                                <div class="fw-semibold"><?= htmlspecialchars($transfer['outlet_to_name']) ?></div>
                                                <small class="opacity-90"><?= htmlspecialchars($transfer['to_city']) ?></small>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="col-lg-4 text-lg-end">
                                        <!-- Status Badge -->
                                        <div class="mb-3">
                                            <span class="badge bg-white text-<?= $current_mode['color'] ?> px-3 py-2 fs-6">
                                                <?= ucwords(str_replace('_', ' ', $transfer['status'] ?? 'draft')) ?>
                                            </span>
                                        </div>
                                        
                                        <!-- Quick Stats -->
                                        <div class="quick-stats">
                                            <div class="stat-item">
                                                <div class="stat-value"><?= count($transfer_items) ?></div>
                                                <div class="stat-label">Products</div>
                                            </div>
                                            <div class="stat-item">
                                                <div class="stat-value"><?= $total_items ?></div>
                                                <div class="stat-label">Items</div>
                                            </div>
                                            <div class="stat-item">
                                                <div class="stat-value"><?= number_format($total_weight_g / 1000, 1) ?>kg</div>
                                                <div class="stat-label">Weight</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Action Toolbar -->
                            <div class="action-toolbar bg-light border-top p-3">
                                <div class="row align-items-center">
                                    <div class="col-lg-8">
                                        <!-- User & Timestamp -->
                                        <div class="d-flex align-items-center text-muted">
                                            <i class="fas fa-user-circle me-2"></i>
                                            <span>
                                                Created by <strong><?= htmlspecialchars($transfer['first_name'] . ' ' . $transfer['last_name']) ?></strong>
                                            </span>
                                            <span class="mx-2">•</span>
                                            <i class="fas fa-clock me-2"></i>
                                            <span><?= date('M j, Y g:i A', strtotime($transfer['created_at'])) ?></span>
                                        </div>
                                    </div>
                                    
                                    <div class="col-lg-4 text-lg-end">
                                        <!-- Action Buttons -->
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" id="btn-print">
                                                <i class="fas fa-print me-1"></i> Print
                                            </button>
                                            <button type="button" class="btn btn-outline-info btn-sm" id="btn-freight-calc">
                                                <i class="fas fa-shipping-fast me-1"></i> Freight
                                            </button>
                                            <button type="button" class="btn btn-outline-primary btn-sm" id="btn-history">
                                                <i class="fas fa-history me-1"></i> History
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="row">
                        <!-- Main Transfer Items Panel -->
                        <div class="col-lg-8">
                            <div class="card border-0 shadow-sm transfer-items-card">
                                <div class="card-header bg-white border-bottom">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <h5 class="card-title mb-0">
                                            <i class="fas fa-list-ul me-2 text-<?= $current_mode['color'] ?>"></i>
                                            Transfer Items
                                        </h5>
                                        <div class="header-actions">
                                            <button type="button" class="btn btn-outline-success btn-sm" id="btn-add-product">
                                                <i class="fas fa-plus me-1"></i> Add Product
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="card-body p-0">
                                    <!-- Items Table -->
                                    <div class="table-responsive">
                                        <table class="table table-hover mb-0 transfer-items-table" id="transfer-items-table">
                                            <thead class="table-light">
                                                <tr>
                                                    <th class="border-0">Product</th>
                                                    <th class="border-0 text-center" width="120">Requested</th>
                                                    <th class="border-0 text-center" width="120"><?= $page_action === 'pack' ? 'Packed' : 'Received' ?></th>
                                                    <th class="border-0 text-center" width="100">Weight</th>
                                                    <th class="border-0 text-center" width="80">Status</th>
                                                    <th class="border-0 text-center" width="100">Actions</th>
                                                </tr>
                                            </thead>
                                            <tbody id="transfer-items-tbody">
                                                <?php foreach ($transfer_items as $index => $item): ?>
                                                    <?php
                                                    $requested_qty = (int)$item['qty_requested'];
                                                    $actual_qty = (int)$item['effective_qty'];
                                                    $unit_weight = (int)$item['unit_weight_g'];
                                                    $total_weight = $unit_weight * $actual_qty;
                                                    
                                                    // Row status for color coding
                                                    $row_status = '';
                                                    if ($actual_qty === 0) {
                                                        $row_status = 'table-secondary'; // Grey - not processed
                                                    } elseif ($actual_qty === $requested_qty) {
                                                        $row_status = 'table-success'; // Green - matches
                                                    } else {
                                                        $row_status = 'table-warning'; // Yellow - partial/different
                                                    }
                                                    ?>
                                                    <tr class="transfer-item-row <?= $row_status ?>" 
                                                        data-item-id="<?= $item['id'] ?>"
                                                        data-product-id="<?= htmlspecialchars($item['product_id']) ?>"
                                                        data-requested="<?= $requested_qty ?>"
                                                        data-unit-weight="<?= $unit_weight ?>">
                                                        
                                                        <!-- Product Information -->
                                                        <td class="product-cell">
                                                            <div class="product-info">
                                                                <div class="product-name fw-semibold">
                                                                    <?= htmlspecialchars($item['product_name']) ?>
                                                                    <?php if ($item['is_fragile']): ?>
                                                                        <span class="badge bg-warning ms-2">
                                                                            <i class="fas fa-exclamation-triangle"></i> Fragile
                                                                        </span>
                                                                    <?php endif; ?>
                                                                    <?php if ($item['contains_nicotine']): ?>
                                                                        <span class="badge bg-danger ms-2">
                                                                            <i class="fas fa-exclamation-circle"></i> Nicotine
                                                                        </span>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <div class="product-meta text-muted">
                                                                    <small>
                                                                        SKU: <?= htmlspecialchars($item['sku'] ?? 'N/A') ?> 
                                                                        | Weight: <?= $unit_weight ?>g/unit
                                                                    </small>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        
                                                        <!-- Requested Quantity -->
                                                        <td class="text-center align-middle">
                                                            <span class="qty-display fw-bold fs-5">
                                                                <?= $requested_qty ?>
                                                            </span>
                                                        </td>
                                                        
                                                        <!-- Actual Quantity Input -->
                                                        <td class="text-center align-middle">
                                                            <div class="qty-input-group">
                                                                <input type="number" 
                                                                       class="form-control form-control-lg text-center qty-input" 
                                                                       value="<?= $actual_qty ?>"
                                                                       min="0" 
                                                                       max="999"
                                                                       data-item-id="<?= $item['id'] ?>"
                                                                       data-expected="<?= $requested_qty ?>"
                                                                       autocomplete="off">
                                                            </div>
                                                        </td>
                                                        
                                                        <!-- Total Weight -->
                                                        <td class="text-center align-middle">
                                                            <span class="weight-display text-muted">
                                                                <span class="total-weight"><?= number_format($total_weight) ?></span>g
                                                            </span>
                                                        </td>
                                                        
                                                        <!-- Status -->
                                                        <td class="text-center align-middle">
                                                            <span class="status-indicator">
                                                                <?php if ($actual_qty === 0): ?>
                                                                    <i class="fas fa-clock text-secondary" title="Pending"></i>
                                                                <?php elseif ($actual_qty === $requested_qty): ?>
                                                                    <i class="fas fa-check-circle text-success" title="Complete"></i>
                                                                <?php else: ?>
                                                                    <i class="fas fa-exclamation-triangle text-warning" title="Partial"></i>
                                                                <?php endif; ?>
                                                            </span>
                                                        </td>
                                                        
                                                        <!-- Actions -->
                                                        <td class="text-center align-middle">
                                                            <div class="btn-group btn-group-sm" role="group">
                                                                <button type="button" class="btn btn-outline-primary btn-sm" 
                                                                        onclick="editItem(<?= $item['id'] ?>)" 
                                                                        title="Edit">
                                                                    <i class="fas fa-edit"></i>
                                                                </button>
                                                                <button type="button" class="btn btn-outline-danger btn-sm" 
                                                                        onclick="removeItem(<?= $item['id'] ?>)" 
                                                                        title="Remove">
                                                                    <i class="fas fa-trash"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            </tbody>
                                        </table>
                                    </div>
                                    
                                    <!-- Empty State -->
                                    <?php if (empty($transfer_items)): ?>
                                        <div class="empty-state text-center py-5">
                                            <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted">No items in this transfer</h5>
                                            <p class="text-muted">Add products to get started</p>
                                            <button type="button" class="btn btn-<?= $current_mode['color'] ?>" id="btn-add-first-product">
                                                <i class="fas fa-plus me-2"></i> Add First Product
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Sidebar Panels -->
                        <div class="col-lg-4">
                            <!-- Freight Summary Panel -->
                            <div class="card border-0 shadow-sm mb-4 freight-summary-card">
                                <div class="card-header bg-gradient-info text-white border-0">
                                    <h6 class="card-title mb-0">
                                        <i class="fas fa-shipping-fast me-2"></i>
                                        Freight Summary
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <div class="freight-metrics">
                                        <!-- Total Weight -->
                                        <div class="metric-item border-bottom pb-3 mb-3">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <i class="fas fa-weight-hanging text-info me-2"></i>
                                                    <span class="metric-label">Total Weight</span>
                                                </div>
                                                <div class="metric-value">
                                                    <span class="h5 mb-0 fw-bold" id="total-weight-display">
                                                        <?= number_format($total_weight_g / 1000, 2) ?>kg
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Estimated Boxes -->
                                        <div class="metric-item border-bottom pb-3 mb-3">
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <i class="fas fa-boxes text-warning me-2"></i>
                                                    <span class="metric-label">Est. Boxes</span>
                                                </div>
                                                <div class="metric-value">
                                                    <span class="h5 mb-0 fw-bold" id="estimated-boxes-display">
                                                        <?= $estimated_boxes ?>
                                                    </span>
                                                </div>
                                            </div>
                                        </div>
                                        
                                        <!-- Special Requirements -->
                                        <?php if ($fragile_count > 0 || $nicotine_count > 0): ?>
                                            <div class="metric-item">
                                                <div class="special-requirements">
                                                    <div class="mb-2">
                                                        <small class="text-uppercase text-muted fw-semibold">Special Requirements</small>
                                                    </div>
                                                    <?php if ($fragile_count > 0): ?>
                                                        <div class="requirement-badge mb-2">
                                                            <span class="badge bg-warning">
                                                                <i class="fas fa-exclamation-triangle me-1"></i>
                                                                Fragile Items (<?= $fragile_count ?>)
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                    <?php if ($nicotine_count > 0): ?>
                                                        <div class="requirement-badge">
                                                            <span class="badge bg-danger">
                                                                <i class="fas fa-exclamation-circle me-1"></i>
                                                                Nicotine Products (<?= $nicotine_count ?>)
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Advanced Freight Button -->
                                    <div class="mt-3 pt-3 border-top">
                                        <button type="button" class="btn btn-outline-info btn-sm w-100" id="btn-advanced-freight">
                                            <i class="fas fa-calculator me-2"></i>
                                            Advanced Freight Calculator
                                        </button>
                                    </div>
                                </div>
                            </div>

                            <!-- Transfer Actions Panel -->
                            <div class="card border-0 shadow-sm transfer-actions-card">
                                <div class="card-header bg-gradient-<?= $current_mode['color'] ?> text-white border-0">
                                    <h6 class="card-title mb-0">
                                        <i class="fas fa-cogs me-2"></i>
                                        Transfer Actions
                                    </h6>
                                </div>
                                <div class="card-body">
                                    <!-- Auto-save Indicator -->
                                    <div class="auto-save-indicator mb-3" id="auto-save-indicator">
                                        <div class="d-flex align-items-center text-muted">
                                            <i class="fas fa-save me-2"></i>
                                            <span class="small">Auto-saved <span id="last-save-time">just now</span></span>
                                        </div>
                                    </div>
                                    
                                    <!-- Primary Actions -->
                                    <div class="d-grid gap-2">
                                        <button type="button" class="btn btn-<?= $current_mode['color'] ?> btn-lg" id="btn-complete-transfer">
                                            <i class="fas fa-check me-2"></i>
                                            Complete <?= $current_mode['title'] ?>
                                        </button>
                                        
                                        <button type="button" class="btn btn-outline-secondary" id="btn-save-draft">
                                            <i class="fas fa-save me-2"></i>
                                            Save as Draft
                                        </button>
                                    </div>
                                    
                                    <!-- Secondary Actions -->
                                    <div class="mt-3">
                                        <div class="row g-2">
                                            <div class="col-6">
                                                <button type="button" class="btn btn-outline-primary btn-sm w-100" id="btn-print-labels">
                                                    <i class="fas fa-print me-1"></i>
                                                    Print Labels
                                                </button>
                                            </div>
                                            <div class="col-6">
                                                <button type="button" class="btn btn-outline-warning btn-sm w-100" id="btn-export">
                                                    <i class="fas fa-download me-1"></i>
                                                    Export
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Status Information -->
                                    <div class="mt-4 pt-3 border-top">
                                        <div class="status-info">
                                            <div class="mb-2">
                                                <small class="text-uppercase text-muted fw-semibold">Transfer Status</small>
                                            </div>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <span class="text-muted">Current Status:</span>
                                                <span class="badge bg-<?= $current_mode['color'] ?>">
                                                    <?= ucwords(str_replace('_', ' ', $transfer['status'] ?? 'draft')) ?>
                                                </span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </main>
    </div>

    <!-- Hidden form for data submission -->
    <form id="transfer-form" style="display: none;">
        <input type="hidden" name="transfer_id" value="<?= $transfer_id ?>">
        <input type="hidden" name="action" value="<?= $page_action ?>">
        <input type="hidden" name="mode" value="<?= $transfer['transfer_mode'] ?>">
    </form>

    <!-- BASE Transfer JavaScript -->
    <script src="/modules/consignments/js/base-transfer.js"></script>
    
    <!-- Initialize transfer interface -->
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            window.BaseTransfer.init({
                transferId: <?= $transfer_id ?>,
                mode: '<?= $transfer['transfer_mode'] ?>',
                action: '<?= $page_action ?>',
                totalWeightG: <?= $total_weight_g ?>,
                estimatedBoxes: <?= $estimated_boxes ?>,
                autoSaveEnabled: true,
                autoSaveInterval: 2000 // 2 seconds
            });
        });
    </script>
</body>
</html>