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

<div class="vs-pack-container">
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

        <!-- Delivery Mode Selector -->
        <div class="row mb-4">
            <div class="col-12">
                <h5 class="mb-3">Choose Delivery Method</h5>
                <div class="delivery-modes">
                    <div class="delivery-mode active" data-mode="manual">
                        <i class="fa fa-box delivery-mode-icon"></i>
                        <div class="delivery-mode-title">Manual Mode</div>
                        <div class="delivery-mode-desc">Full control: boxes, tracking, shipping details</div>
                    </div>
                    <div class="delivery-mode" data-mode="pickup">
                        <i class="fa fa-truck delivery-mode-icon"></i>
                        <div class="delivery-mode-title">Pickup Mode</div>
                        <div class="delivery-mode-desc">Simple pickup: who, where, when</div>
                    </div>
                    <div class="delivery-mode" data-mode="dropoff">
                        <i class="fa fa-map-marker-alt delivery-mode-icon"></i>
                        <div class="delivery-mode-title">Drop-off Mode</div>
                        <div class="delivery-mode-desc">Delivery details and instructions</div>
                    </div>
                </div>
            </div>
        </div>

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
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0"><i class="fa fa-box text-primary"></i> Items to Pack</h5>
                            <div>
                                <button type="button" class="btn btn-outline-primary btn-sm mr-2" onclick="openProductSearch()">
                                    <i class="fa fa-plus"></i> Add Product
                                </button>
                                <button type="button" class="btn btn-outline-success btn-sm" onclick="packAllItems()">
                                    <i class="fa fa-check-double"></i> Pack All
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($items)): ?>
                        <form id="packForm" method="post" action="/modules/consignments/api/pack_submit.php">
                            <input type="hidden" name="transfer_id" value="<?= $transferId ?>">
                            <input type="hidden" name="delivery_mode" id="deliveryMode" value="manual">
                            
                            <div class="table-responsive">
                                <table class="table table-hover mb-0">
                                    <thead class="bg-light">
                                        <tr>
                                            <th class="border-0">SKU</th>
                                            <th class="border-0">Product</th>
                                            <th class="border-0 text-center">Requested</th>
                                            <th class="border-0 text-center">Pack Qty</th>
                                            <th class="border-0 text-center">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody id="itemsTableBody">
                                        <?php foreach ($items as $item): 
                                            $maxQty = (int)($item['qty_requested'] ?? 0);
                                        ?>
                                        <tr data-item-id="<?= (int)$item['id'] ?>">
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
                                                <button type="button" class="btn btn-sm btn-outline-danger ml-1 remove-item-btn">
                                                    <i class="fa fa-times"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Delivery Details (show/hide based on mode) -->
                            <div class="card-footer bg-light">
                                <!-- Manual Mode Details -->
                                <div id="manualModeDetails" class="delivery-details">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label class="form-label">Number of Boxes</label>
                                            <input type="number" class="form-control" name="num_boxes" value="1" min="1">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Tracking Number</label>
                                            <input type="text" class="form-control" name="tracking_number" placeholder="e.g. E40-12345678">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Shipping Method</label>
                                            <select class="form-control" name="shipping_method">
                                                <option value="courier">Courier</option>
                                                <option value="pickup">Pickup</option>
                                                <option value="delivery">Delivery</option>
                                            </select>
                                        </div>
                                    </div>
                                    <div class="row mt-3">
                                        <div class="col-12">
                                            <label class="form-label">Packing Notes</label>
                                            <textarea class="form-control" name="packing_notes" rows="2" placeholder="Any special instructions or notes..."></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Pickup Mode Details -->
                                <div id="pickupModeDetails" class="delivery-details" style="display: none;">
                                    <div class="row">
                                        <div class="col-md-4">
                                            <label class="form-label">Who is Picking Up?</label>
                                            <input type="text" class="form-control" name="pickup_person" placeholder="Name of person">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Pickup Location</label>
                                            <input type="text" class="form-control" name="pickup_location" placeholder="Where to pickup">
                                        </div>
                                        <div class="col-md-4">
                                            <label class="form-label">Reason/Purpose</label>
                                            <input type="text" class="form-control" name="pickup_reason" placeholder="Why needed">
                                        </div>
                                    </div>
                                </div>

                                <!-- Drop-off Mode Details -->
                                <div id="dropoffModeDetails" class="delivery-details" style="display: none;">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <label class="form-label">Delivery Address</label>
                                            <textarea class="form-control" name="delivery_address" rows="2" placeholder="Full delivery address"></textarea>
                                        </div>
                                        <div class="col-md-6">
                                            <label class="form-label">Delivery Instructions</label>
                                            <textarea class="form-control" name="delivery_instructions" rows="2" placeholder="Special delivery instructions"></textarea>
                                        </div>
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between align-items-center mt-3">
                                    <div class="text-muted">
                                        <small><i class="fa fa-info-circle"></i> <span id="totalItemsText">Enter quantities for each item to pack</span></small>
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
                        <?php else: ?>
                        <div class="p-4 text-center">
                            <div class="alert alert-info">
                                <h5><i class="fa fa-info-circle"></i> No Items to Pack</h5>
                                <p class="mb-3">This transfer has no items to pack.</p>
                                <button type="button" class="btn btn-primary" onclick="openProductSearch()">
                                    <i class="fa fa-plus"></i> Add Products
                                </button>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>
</div>

<!-- Product Search Modal -->
<div class="modal fade product-search-modal" id="productSearchModal" tabindex="-1" role="dialog">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header product-search-header">
                <h4>Add Products to Transfer</h4>
                <button type="button" class="close" data-dismiss="modal">
                    <i class="fa fa-times"></i>
                </button>
            </div>
            <div class="search-toolbar">
                <div class="search-input-group">
                    <input type="text" class="form-control" id="productSearchInput" placeholder="Search products by name or SKU...">
                    <i class="fa fa-search search-icon"></i>
                </div>
                <button type="button" class="btn btn-outline-primary btn-sm" id="advancedSearchBtn">
                    <i class="fa fa-filter"></i> Filters
                </button>
                <div class="text-muted">
                    <small>Hold <kbd>Shift</kbd> to select multiple</small>
                </div>
            </div>
            <div class="modal-body p-0">
                <div class="product-grid" id="productGrid">
                    <!-- Products will be loaded here via JavaScript -->
                </div>
            </div>
            <div class="modal-footer">
                <div class="d-flex justify-content-between align-items-center w-100">
                    <div>
                        <span class="badge badge-primary" id="selectedCount">0 selected</span>
                    </div>
                    <div>
                        <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                        <button type="button" class="btn btn-primary" id="addSelectedProducts" disabled>
                            <i class="fa fa-plus"></i> Add Selected
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

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

.delivery-details {
    transition: all 0.3s ease;
}

kbd {
    background: #f8f9fa;
    border: 1px solid #dee2e6;
    border-radius: 3px;
    padding: 2px 6px;
    font-size: 0.8rem;
}
</style>