<?php
/**
 * Reusable Box Optimizer Form Section
 *
 * Include in any packing page:
 * <?php include '../templates/box-optimizer-form.php'; ?>
 *
 * Set options before including:
 * $boxOptimizerConfig = [
 *     'transfer_id' => (int),
 *     'transfer_type' => 'STOCK|JUICE|PO|INTERNAL|RETURN|STAFF',
 *     'carrier' => 'nz_courier',
 *     'show_cost_estimate' => true,
 *     'show_consolidation' => true,
 *     'hazmat_enabled' => false,
 *     'readonly' => false
 * ];
 */

// Merge with defaults
$config = array_merge([
    'transfer_id' => 0,
    'transfer_type' => 'STOCK',
    'carrier' => 'nz_courier',
    'show_cost_estimate' => true,
    'show_consolidation' => true,
    'hazmat_enabled' => false,
    'readonly' => false,
    'collapse_by_default' => false,
], $boxOptimizerConfig ?? []);

$collapseClass = $config['collapse_by_default'] ? 'collapsed' : '';
$collapseTarget = 'boxOptimizer_' . uniqid();

// Determine transfer type label
$typeLabels = [
    'STOCK' => '📦 Stock Transfer',
    'JUICE' => '🧃 Juice Transfer',
    'PO' => '📮 Purchase Order',
    'PURCHASE_ORDER' => '📮 Purchase Order',
    'INTERNAL' => '👥 Internal Transfer',
    'RETURN' => '🔄 Return Package',
    'STAFF' => '👤 Staff Handoff'
];

$typeLabel = $typeLabels[$config['transfer_type']] ?? '📦 Packaging';

?>

<div class="card mt-4 box-optimizer-card" id="box-optimizer-section">
    <div class="card-header cursor-pointer" data-toggle="collapse" data-target="#<?php echo $collapseTarget; ?>">
        <div style="display: flex; justify-content: space-between; align-items: center;">
            <h5 class="mb-0">
                <i class="bi bi-box-seam"></i> Optimize Packaging
                <span class="small text-muted"><?php echo $typeLabel; ?></span>
            </h5>
            <div>
                <span class="badge badge-info" id="box-optimizer-status">Ready</span>
                <i class="fa fa-chevron-down ml-2"></i>
            </div>
        </div>
    </div>

    <div class="card-body collapse show" id="<?php echo $collapseTarget; ?>">

        <!-- Carrier Selection (if applicable) -->
        <?php if (in_array($config['transfer_type'], ['STOCK', 'JUICE', 'PO', 'PURCHASE_ORDER'])): ?>
        <div class="form-group">
            <label class="font-weight-bold small text-uppercase text-muted">Shipping Carrier</label>
            <select name="carrier" class="form-control form-control-sm" id="carrier-select"
                    data-carrier="<?php echo htmlspecialchars($config['carrier']); ?>"
                    <?php echo $config['readonly'] ? 'disabled' : ''; ?>>
                <optgroup label="New Zealand">
                    <option value="nz_courier" <?php echo $config['carrier'] === 'nz_courier' ? 'selected' : ''; ?>>
                        NZ Courier (Standard - 1-2 days)
                    </option>
                    <option value="courier_post" <?php echo $config['carrier'] === 'courier_post' ? 'selected' : ''; ?>>
                        Courier Post (Budget - 2-3 days)
                    </option>
                    <option value="nz_post" <?php echo $config['carrier'] === 'nz_post' ? 'selected' : ''; ?>>
                        NZ Post (Parcel - 3-5 days)
                    </option>
                </optgroup>

                <?php if ($config['transfer_type'] !== 'INTERNAL'): ?>
                <optgroup label="Australia">
                    <option value="au_post" <?php echo $config['carrier'] === 'au_post' ? 'selected' : ''; ?>>
                        Australia Post (3-5 days)
                    </option>
                </optgroup>

                <optgroup label="International">
                    <option value="dhl" <?php echo $config['carrier'] === 'dhl' ? 'selected' : ''; ?>>
                        DHL Express (2-3 days)
                    </option>
                    <option value="fedex" <?php echo $config['carrier'] === 'fedex' ? 'selected' : ''; ?>>
                        FedEx (3-5 days)
                    </option>
                </optgroup>
                <?php endif; ?>
            </select>
        </div>
        <?php endif; ?>

        <!-- Box Dimensions Form -->
        <div class="form-group">
            <label class="font-weight-bold small text-uppercase text-muted">Package Dimensions</label>

            <div class="row">
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small">Length (cm)</label>
                        <input type="number" name="box_length" class="form-control form-control-sm"
                               placeholder="e.g., 30" data-box-dimension="length"
                               min="5" max="200" step="0.1"
                               <?php echo $config['readonly'] ? 'readonly' : ''; ?>>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small">Width (cm)</label>
                        <input type="number" name="box_width" class="form-control form-control-sm"
                               placeholder="e.g., 20" data-box-dimension="width"
                               min="5" max="200" step="0.1"
                               <?php echo $config['readonly'] ? 'readonly' : ''; ?>>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small">Height (cm)</label>
                        <input type="number" name="box_height" class="form-control form-control-sm"
                               placeholder="e.g., 15" data-box-dimension="height"
                               min="5" max="200" step="0.1"
                               <?php echo $config['readonly'] ? 'readonly' : ''; ?>>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="form-group">
                        <label class="small">Weight (kg)</label>
                        <input type="number" name="box_weight" class="form-control form-control-sm"
                               placeholder="e.g., 5.5" data-box-dimension="weight"
                               min="0.1" max="100" step="0.1"
                               <?php echo $config['readonly'] ? 'readonly' : ''; ?>>
                    </div>
                </div>
            </div>

            <!-- Quick Size Buttons (if configured) -->
            <?php if ($config['transfer_type'] === 'INTERNAL' || $config['transfer_type'] === 'STAFF'): ?>
            <div class="mt-3">
                <span class="small text-muted">Quick select:</span>
                <button type="button" class="btn btn-sm btn-outline-secondary ml-2"
                        onclick="boxOptimizer.setQuickSize('small')">
                    Small
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary"
                        onclick="boxOptimizer.setQuickSize('medium')">
                    Medium
                </button>
                <button type="button" class="btn btn-sm btn-outline-secondary"
                        onclick="boxOptimizer.setQuickSize('large')">
                    Large
                </button>
            </div>
            <?php endif; ?>
        </div>

        <!-- Cost Estimate (if enabled) -->
        <?php if ($config['show_cost_estimate']): ?>
        <div class="form-group">
            <label class="font-weight-bold small text-uppercase text-muted">Cost Estimate</label>
            <div data-cost-estimate style="display:none;">
                <!-- Will be populated by box-optimizer-ui.js -->
            </div>
        </div>
        <?php endif; ?>

        <!-- Hazmat Warning (if enabled) -->
        <?php if ($config['hazmat_enabled']): ?>
        <div id="hazmat-warning" style="display:none;">
            <div class="alert alert-warning alert-dismissible fade show">
                <strong><i class="fa fa-exclamation-triangle"></i> Hazmat Shipment</strong>
                <p class="mb-0 small mt-2">This shipment contains hazardous materials and requires:</p>
                <ul class="small mt-2 mb-0">
                    <li>Double-wall packaging</li>
                    <li>Pressure relief venting</li>
                    <li>Hazmat shipping labels</li>
                    <li>Proper documentation</li>
                </ul>
                <button type="button" class="close" data-dismiss="alert">
                    <span>&times;</span>
                </button>
            </div>
        </div>
        <?php endif; ?>

        <!-- Error/Validation Alerts -->
        <div data-optimization-alerts></div>

        <!-- Warnings -->
        <div data-optimization-warnings></div>

        <!-- Optimization Suggestions -->
        <div data-optimization-suggestions style="margin-top: 20px;"></div>

    </div>
</div>

<!-- Initialize Box Optimizer -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize if not already done
    if (typeof window.boxOptimizer === 'undefined') {
        window.boxOptimizer = new BoxOptimizerUI({
            transferId: <?php echo (int)$config['transfer_id']; ?>,
            transferType: '<?php echo htmlspecialchars($config['transfer_type']); ?>',
            carrier: '<?php echo htmlspecialchars($config['carrier']); ?>',
            hazmatEnabled: <?php echo $config['hazmat_enabled'] ? 'true' : 'false'; ?>,
            readonly: <?php echo $config['readonly'] ? 'true' : 'false'; ?>,
            apiEndpoint: '/modules/consignments/api/box-optimizer.php'
        });

        // Expose quick size setter for internal/staff transfers
        <?php if ($config['transfer_type'] === 'INTERNAL' || $config['transfer_type'] === 'STAFF'): ?>
        window.boxOptimizer.setQuickSize = function(size) {
            const sizes = {
                'small': { length: 15, width: 15, height: 15, weight: 1 },
                'medium': { length: 25, width: 20, height: 15, weight: 3 },
                'large': { length: 35, width: 25, height: 25, weight: 5 }
            };

            const dims = sizes[size] || sizes['medium'];

            // Set form fields
            document.querySelector('input[name="box_length"]').value = dims.length;
            document.querySelector('input[name="box_width"]').value = dims.width;
            document.querySelector('input[name="box_height"]').value = dims.height;
            document.querySelector('input[name="box_weight"]').value = dims.weight;

            // Trigger analysis
            window.boxOptimizer.analyzeCurrentBoxes();
        };
        <?php endif; ?>
    }
});
</script>

<style>
.box-optimizer-card {
    border-left: 4px solid #007bff;
}

.box-optimizer-card .card-header {
    background-color: #f8f9fa;
    cursor: pointer;
    user-select: none;
    transition: background-color 0.2s;
}

.box-optimizer-card .card-header:hover {
    background-color: #e9ecef;
}

.box-optimizer-card .card-header h5 {
    color: #007bff;
}

#box-optimizer-status {
    font-size: 0.75rem;
    padding: 0.35rem 0.6rem;
}

.box-optimizer-card .form-control:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}
</style>
