<?php
declare(strict_types=1);

// Variables expected:
// - int $transferId
// - array|null $transfer
// - array $items
// - int $transferCount

// Fallback defaults
$transferId = (int)($transferId ?? 0);
$transfer = $transfer ?? null;
$items = $items ?? [];
?>

<!-- Page Header -->
<div class="row mb-4">
    <div class="col-12">
        <div class="d-flex justify-content-between align-items-center">
            <div>
                <h1 class="h3 mb-1">Pack Transfer #<?= $transferId ?></h1>
                <?php if ($transfer): ?>
                <p class="text-muted mb-0">
                    <i class="fa fa-arrow-right"></i> 
                    From: <strong><?= htmlspecialchars($transfer['outlet_from'] ?? 'Unknown') ?></strong> 
                    → To: <strong><?= htmlspecialchars($transfer['outlet_to'] ?? 'Unknown') ?></strong>
                </p>
                <?php endif; ?>
            </div>
            <div>
                <button type="button" class="btn btn-outline-secondary" onclick="history.back()">
                    <i class="fa fa-arrow-left"></i> Back
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Status Messages -->
<?php if ($transferId <= 0): ?>
    <div class="row">
        <div class="col-12">
            <div class="alert alert-warning">
                <h5><i class="fa fa-exclamation-triangle"></i> No Transfer Selected</h5>
                <p>Please select a transfer to pack. Add <code>?transfer=ID</code> to the URL.</p>
            </div>
        </div>
    </div>
<?php elseif (!$transfer): ?>
    <div class="row">
        <div class="col-12">
            <div class="alert alert-danger">
                <h5><i class="fa fa-times-circle"></i> Transfer Not Found</h5>
                <p>Transfer #<?= $transferId ?> could not be found.</p>
            </div>
        </div>
    </div>
<?php else: ?>
    <!-- Transfer Status Card -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <div class="row">
                        <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                            <div class="d-flex align-items-center">
                                <div class="icon-circle bg-info text-white mr-3">
                                    <i class="fa fa-info-circle"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Status</small><br>
                                    <span class="badge badge-info"><?= htmlspecialchars($transfer['status'] ?? 'Unknown') ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3 mb-lg-0">
                            <div class="d-flex align-items-center">
                                <div class="icon-circle bg-primary text-white mr-3">
                                    <i class="fa fa-calendar"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Created</small><br>
                                    <strong><?= date('M j, Y', strtotime($transfer['created_at'] ?? 'now')) ?></strong>
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6 mb-3 mb-md-0">
                            <div class="d-flex align-items-center">
                                <div class="icon-circle bg-success text-white mr-3">
                                    <i class="fa fa-boxes"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Items to Pack</small><br>
                                    <strong><?= count($items) ?></strong> line items
                                </div>
                            </div>
                        </div>
                        <div class="col-lg-3 col-md-6">
                            <div class="d-flex align-items-center">
                                <div class="icon-circle bg-secondary text-white mr-3">
                                    <i class="fa fa-hashtag"></i>
                                </div>
                                <div>
                                    <small class="text-muted">Reference</small><br>
                                    <strong><?= htmlspecialchars($transfer['reference'] ?? 'N/A') ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Pack Form -->
    <?php if (!empty($items)): ?>
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fa fa-box text-primary"></i> Items to Pack</h5>
                        <button type="button" class="btn btn-sm btn-outline-primary" onclick="packAllItems()">
                            <i class="fa fa-check-double"></i> Pack All Items
                        </button>
                    </div>
                </div>
                <div class="card-body p-0">
                    <form id="packForm" method="post" action="/modules/consignments/api/pack_submit.php">
                        <input type="hidden" name="transfer_id" value="<?= $transferId ?>">
                        
                        <div class="table-responsive">
                            <table class="table table-hover mb-0">
                                <thead class="bg-light">
                                    <tr>
                                        <th class="border-0">SKU</th>
                                        <th class="border-0">Product</th>
                                        <th class="border-0 text-center">Requested</th>
                                        <th class="border-0 text-center">Pack Qty</th>
                                        <th class="border-0 text-center">Quick Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($items as $item): 
                                        $maxQty = (int)($item['qty_requested'] ?? 0);
                                    ?>
                                    <tr>
                                        <td class="align-middle">
                                            <code class="bg-light px-2 py-1 rounded"><?= htmlspecialchars($item['sku'] ?? $item['product_id'] ?? 'N/A') ?></code>
                                        </td>
                                        <td class="align-middle">
                                            <strong><?= htmlspecialchars($item['product_name'] ?? $item['name'] ?? 'Unknown Product') ?></strong>
                                        </td>
                                        <td class="align-middle text-center">
                                            <span class="badge badge-info badge-pill"><?= $maxQty ?></span>
                                        </td>
                                        <td class="align-middle text-center">
                                            <input type="number" 
                                                   class="form-control form-control-sm text-center pack-qty-input" 
                                                   name="items[<?= (int)$item['id'] ?>][qty_packed]" 
                                                   value="0" 
                                                   min="0" 
                                                   max="<?= $maxQty ?>"
                                                   data-max="<?= $maxQty ?>"
                                                   style="width: 80px; margin: 0 auto;">
                                        </td>
                                        <td class="align-middle text-center">
                                            <button type="button" class="btn btn-sm btn-outline-success pack-all-btn" 
                                                    data-max="<?= $maxQty ?>">
                                                <i class="fa fa-check"></i> Pack All
                                            </button>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <div class="card-footer bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <div class="text-muted">
                                    <small><i class="fa fa-info-circle"></i> Enter quantities for each item to pack</small>
                                </div>
                                <div>
                                    <button type="button" class="btn btn-outline-secondary mr-2" onclick="clearAllQuantities()">
                                        <i class="fa fa-undo"></i> Clear All
                                    </button>
                                    <button type="submit" class="btn btn-success">
                                        <i class="fa fa-shipping-fast"></i> Complete Packing
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php else: ?>
    <div class="row">
        <div class="col-12">
            <div class="alert alert-info">
                <h5><i class="fa fa-info-circle"></i> No Items to Pack</h5>
                <p class="mb-0">This transfer has no items to pack.</p>
            </div>
        </div>
    </div>
    <?php endif; ?>
<?php endif; ?>

<style>
.icon-circle {
    width: 40px;
    height: 40px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
}

.table th {
    font-weight: 600;
    font-size: 0.9rem;
}

.pack-qty-input:focus {
    border-color: #007bff;
    box-shadow: 0 0 0 0.2rem rgba(0, 123, 255, 0.25);
}

.badge-pill {
    font-size: 0.85rem;
    padding: 0.4em 0.8em;
}

.card-footer {
    border-top: 1px solid #dee2e6;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Pack All buttons for individual items
    document.querySelectorAll('.pack-all-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const maxQty = this.getAttribute('data-max');
            const input = this.closest('tr').querySelector('.pack-qty-input');
            input.value = maxQty;
            input.focus();
        });
    });
    
    // Form validation
    const form = document.getElementById('packForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const inputs = form.querySelectorAll('.pack-qty-input');
            let totalItems = 0;
            
            inputs.forEach(input => {
                const qty = parseInt(input.value) || 0;
                totalItems += qty;
            });
            
            if (totalItems === 0) {
                e.preventDefault();
                alert('Please enter quantities for at least one item before submitting.');
                return false;
            }
            
            return confirm(`Are you sure you want to pack ${totalItems} items for this transfer?`);
        });
    }
});

// Pack all items function
function packAllItems() {
    document.querySelectorAll('.pack-qty-input').forEach(input => {
        const maxQty = input.getAttribute('data-max');
        input.value = maxQty;
    });
}

// Clear all quantities function
function clearAllQuantities() {
    if (confirm('Clear all pack quantities?')) {
        document.querySelectorAll('.pack-qty-input').forEach(input => {
            input.value = '0';
        });
    }
}
</script>