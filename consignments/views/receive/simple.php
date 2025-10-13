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
                <h1 class="h3 mb-1">Receive Transfer #<?= $transferId ?></h1>
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

            <?php if ($transferId <= 0): ?>
                <div class="alert alert-warning">
                    <h5>No Transfer Selected</h5>
                    <p>Please select a transfer to receive. Add <code>?transfer=ID</code> to the URL.</p>
                </div>
            <?php elseif (!$transfer): ?>
                <div class="alert alert-danger">
                    <h5>Transfer Not Found</h5>
                    <p>Transfer #<?= $transferId ?> could not be found.</p>
                </div>
            <?php else: ?>
                
                <!-- Transfer Info -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <strong>Status:</strong><br>
                                <span class="badge badge-info"><?= htmlspecialchars($transfer['status'] ?? 'Unknown') ?></span>
                            </div>
                            <div class="col-md-3">
                                <strong>Shipped:</strong><br>
                                <?= htmlspecialchars($transfer['shipped_at'] ?? 'Not shipped') ?>
                            </div>
                            <div class="col-md-3">
                                <strong>Items to Receive:</strong><br>
                                <?= count($items) ?> line items
                            </div>
                            <div class="col-md-3">
                                <strong>Reference:</strong><br>
                                <?= htmlspecialchars($transfer['reference'] ?? 'N/A') ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Receive Form -->
                <?php if (!empty($items)): ?>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Items to Receive</h5>
                    </div>
                    <div class="card-body">
                        <form id="receiveForm" method="post" action="/modules/consignments/api/receive_submit.php">
                            <input type="hidden" name="transfer_id" value="<?= $transferId ?>">
                            
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>SKU</th>
                                            <th>Product</th>
                                            <th>Shipped</th>
                                            <th>Receive Qty</th>
                                            <th>Condition</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($items as $item): ?>
                                        <tr>
                                            <td>
                                                <code><?= htmlspecialchars($item['sku'] ?? $item['product_id'] ?? 'N/A') ?></code>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($item['product_name'] ?? $item['name'] ?? 'Unknown Product') ?>
                                            </td>
                                            <td>
                                                <span class="badge badge-primary"><?= (int)($item['qty_shipped'] ?? $item['qty_packed'] ?? 0) ?></span>
                                            </td>
                                            <td>
                                                <input type="number" 
                                                       class="form-control form-control-sm" 
                                                       name="items[<?= (int)$item['id'] ?>][qty_received]" 
                                                       value="0" 
                                                       min="0" 
                                                       max="<?= (int)($item['qty_shipped'] ?? $item['qty_packed'] ?? 0) ?>"
                                                       style="width: 80px;">
                                            </td>
                                            <td>
                                                <select name="items[<?= (int)$item['id'] ?>][condition]" class="form-control form-control-sm" style="width: 120px;">
                                                    <option value="good">Good</option>
                                                    <option value="damaged">Damaged</option>
                                                    <option value="missing">Missing</option>
                                                </select>
                                            </td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-outline-success" 
                                                        onclick="
                                                        const row = this.parentNode.parentNode;
                                                        row.querySelector('input[type=number]').value = <?= (int)($item['qty_shipped'] ?? $item['qty_packed'] ?? 0) ?>;
                                                        row.querySelector('select').value = 'good';
                                                        ">
                                                    Receive All
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <div class="mt-3">
                                <button type="submit" class="btn btn-success">
                                    <i class="fa fa-check"></i> Complete Receiving
                                </button>
                                <button type="button" class="btn btn-secondary ml-2" onclick="history.back()">
                                    <i class="fa fa-arrow-left"></i> Back
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
                <?php else: ?>
                <div class="alert alert-info">
                    <h5>No Items to Receive</h5>
                    <p>This transfer has no items to receive.</p>
                </div>
                <?php endif; ?>
                
            <?php endif; ?>
            
        </div>
    </div>
</div>

<style>
.table th {
    border-top: none;
    font-weight: 600;
    background-color: #f8f9fa;
}
.badge {
    font-size: 0.875em;
}
</style>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Simple form validation
    const form = document.getElementById('receiveForm');
    if (form) {
        form.addEventListener('submit', function(e) {
            const inputs = form.querySelectorAll('input[type="number"]');
            let hasQty = false;
            
            inputs.forEach(input => {
                if (parseInt(input.value) > 0) {
                    hasQty = true;
                }
            });
            
            if (!hasQty) {
                e.preventDefault();
                alert('Please enter quantities for at least one item before submitting.');
                return false;
            }
            
            // Simple confirmation
            return confirm('Are you sure you want to complete receiving for this transfer?');
        });
    }
});
</script>