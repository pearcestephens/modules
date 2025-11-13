<?php
/**
 * Purchase Orders - Create/Edit Page
 *
 * Unified form for creating new POs or editing existing DRAFTs
 *
 * Features:
 * - Dynamic line item management (add/remove rows)
 * - Product search modal with fuzzy matching
 * - Auto-save every 30 seconds
 * - Real-time validation
 * - Calculation of totals
 *
 * Uses dashboard.php layout for clean form presentation
 *
 * @package CIS\Consignments\PurchaseOrders
 * @version 1.0.0
 */

declare(strict_types=1);

require_once $_SERVER['DOCUMENT_ROOT'] . '/app.php';
require_once __DIR__ . '/../lib/Services/PurchaseOrderService.php';

use CIS\Consignments\Services\PurchaseOrderService;

// Check authentication
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

$userId = $_SESSION['user_id'];

// Initialize service
$db = get_db();
$poService = new PurchaseOrderService($db);

// Determine mode: create or edit
$poId = (int)($_GET['id'] ?? 0);
$isEditMode = ($poId > 0);

// Fetch PO if editing
$po = null;
$lineItems = [];

if ($isEditMode) {
    try {
        $po = $poService->get($poId);

        if (!$po) {
            header('Location: index.php?error=not_found');
            exit;
        }

        // Can only edit DRAFT and OPEN states
        if (!in_array($po->state, ['DRAFT', 'OPEN'])) {
            header('Location: view.php?id=' . $poId . '&error=cannot_edit');
            exit;
        }

        $lineItems = $poService->getLineItems($poId);

    } catch (Exception $e) {
        error_log("PO Edit Error: " . $e->getMessage());
        header('Location: index.php?error=load_failed');
        exit;
    }
}

// Fetch suppliers and outlets for dropdowns
try {
    $suppliers = $poService->getSuppliers();
    $outlets = $poService->getOutlets();
} catch (Exception $e) {
    error_log("Dropdown fetch error: " . $e->getMessage());
    $suppliers = [];
    $outlets = [];
}

// Page metadata
$pageTitle = $isEditMode ? 'Edit Purchase Order ' . htmlspecialchars($po->public_id) : 'Create Purchase Order';
$breadcrumbs = [
    ['title' => 'Home', 'url' => '/'],
    ['title' => 'Consignments', 'url' => '/modules/consignments/'],
    ['title' => 'Purchase Orders', 'url' => '/modules/consignments/purchase-orders/'],
    ['title' => $isEditMode ? 'Edit ' . $po->public_id : 'Create', 'url' => null],
];

// Include header
include $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/shared/blocks/header.php';
?>

<!-- Main Content -->
<div class="container-fluid py-4">

    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col-md-8">
            <h2 class="mb-0">
                <i class="fas fa-<?= $isEditMode ? 'edit' : 'plus-circle' ?> me-2"></i>
                <?= $isEditMode ? 'Edit Purchase Order' : 'Create Purchase Order' ?>
            </h2>
            <?php if ($isEditMode): ?>
                <p class="text-muted mb-0">PO ID: <?= htmlspecialchars($po->public_id) ?></p>
            <?php endif; ?>
        </div>
        <div class="col-md-4 text-end">
            <a href="<?= $isEditMode ? 'view.php?id=' . $poId : 'index.php' ?>" class="btn btn-outline-secondary">
                <i class="fas fa-times me-2"></i> Cancel
            </a>
        </div>
    </div>

    <!-- Auto-save Status -->
    <div id="autosaveStatus" class="alert alert-info alert-dismissible fade show mb-3" style="display: none;">
        <i class="fas fa-save me-2"></i>
        <span id="autosaveMessage">Auto-save enabled</span>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>

    <!-- Main Form -->
    <form id="poForm" autocomplete="off">

        <input type="hidden" id="poId" name="po_id" value="<?= $poId ?>">
        <input type="hidden" id="isEdit" value="<?= $isEditMode ? '1' : '0' ?>">

        <!-- Header Information Card -->
        <div class="card mb-4">
            <div class="card-header bg-light">
                <h5 class="mb-0">
                    <i class="fas fa-info-circle me-2"></i> Purchase Order Information
                </h5>
            </div>
            <div class="card-body">
                <div class="row g-3">

                    <!-- Supplier -->
                    <div class="col-md-6">
                        <label for="supplierId" class="form-label required">Supplier</label>
                        <select
                            class="form-select"
                            id="supplierId"
                            name="supplier_id"
                            required
                            <?= $isEditMode ? 'disabled' : '' ?>
                        >
                            <option value="">Select a supplier...</option>
                            <?php foreach ($suppliers as $supplier): ?>
                                <option
                                    value="<?= htmlspecialchars($supplier->id) ?>"
                                    <?= ($isEditMode && $po->supplier_id === $supplier->id) ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($supplier->name) ?>
                                    <?php if (!empty($supplier->code)): ?>
                                        (<?= htmlspecialchars($supplier->code) ?>)
                                    <?php endif; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($isEditMode): ?>
                            <input type="hidden" name="supplier_id" value="<?= htmlspecialchars($po->supplier_id) ?>">
                            <small class="text-muted">Supplier cannot be changed after creation</small>
                        <?php endif; ?>
                    </div>

                    <!-- Outlet -->
                    <div class="col-md-6">
                        <label for="outletId" class="form-label required">Destination Outlet</label>
                        <select
                            class="form-select"
                            id="outletId"
                            name="outlet_id"
                            required
                        >
                            <option value="">Select an outlet...</option>
                            <?php foreach ($outlets as $outlet): ?>
                                <option
                                    value="<?= htmlspecialchars($outlet->id) ?>"
                                    <?= ($isEditMode && $po->outlet_id === $outlet->id) ? 'selected' : '' ?>
                                >
                                    <?= htmlspecialchars($outlet->name) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Expected Date -->
                    <div class="col-md-6">
                        <label for="expectedDate" class="form-label">Expected Delivery Date</label>
                        <input
                            type="date"
                            class="form-control"
                            id="expectedDate"
                            name="expected_date"
                            value="<?= $isEditMode ? htmlspecialchars($po->expected_date ?? '') : '' ?>"
                            min="<?= date('Y-m-d') ?>"
                        >
                        <small class="text-muted">When do you expect to receive this order?</small>
                    </div>

                    <!-- Supplier Reference -->
                    <div class="col-md-6">
                        <label for="supplierReference" class="form-label">Supplier Reference</label>
                        <input
                            type="text"
                            class="form-control"
                            id="supplierReference"
                            name="supplier_reference"
                            placeholder="Supplier's PO number (if known)"
                            value="<?= $isEditMode ? htmlspecialchars($po->supplier_reference ?? '') : '' ?>"
                            maxlength="50"
                        >
                    </div>

                    <!-- Notes -->
                    <div class="col-12">
                        <label for="notes" class="form-label">Notes</label>
                        <textarea
                            class="form-control"
                            id="notes"
                            name="notes"
                            rows="3"
                            placeholder="Any additional information about this order..."
                        ><?= $isEditMode ? htmlspecialchars($po->notes ?? '') : '' ?></textarea>
                    </div>

                </div>
            </div>
        </div>

        <!-- Line Items Card -->
        <div class="card mb-4">
            <div class="card-header bg-light d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-boxes me-2"></i> Line Items
                    <span class="badge bg-secondary ms-2" id="itemCount">0</span>
                </h5>
                <button type="button" class="btn btn-sm btn-primary" id="addItemBtn">
                    <i class="fas fa-plus me-2"></i> Add Item
                </button>
            </div>
            <div class="card-body">

                <!-- Line Items Table -->
                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="lineItemsTable">
                        <thead class="table-light">
                            <tr>
                                <th style="width: 40px">#</th>
                                <th style="width: 40%">Product</th>
                                <th style="width: 15%">Quantity</th>
                                <th style="width: 15%">Cost</th>
                                <th style="width: 15%">Total</th>
                                <th style="width: 80px">Actions</th>
                            </tr>
                        </thead>
                        <tbody id="lineItemsBody">
                            <!-- Line items will be inserted here via JavaScript -->
                            <tr id="emptyState">
                                <td colspan="6" class="text-center text-muted py-5">
                                    <i class="fas fa-boxes fa-3x mb-3 d-block"></i>
                                    No items added yet. Click "Add Item" to get started.
                                </td>
                            </tr>
                        </tbody>
                        <tfoot class="table-light">
                            <tr>
                                <th colspan="4" class="text-end">Total:</th>
                                <th>
                                    <span class="fs-5 fw-bold" id="grandTotal">$0.00</span>
                                </th>
                                <th></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>

            </div>
        </div>

        <!-- Form Actions -->
        <div class="card">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <button type="button" class="btn btn-outline-secondary" onclick="window.history.back()">
                            <i class="fas fa-arrow-left me-2"></i> Cancel
                        </button>
                    </div>
                    <div class="col-md-6 text-end">
                        <button type="button" class="btn btn-secondary me-2" id="saveDraftBtn">
                            <i class="fas fa-save me-2"></i> Save as Draft
                        </button>
                        <button type="button" class="btn btn-primary" id="saveBtn">
                            <i class="fas fa-check me-2"></i> <?= $isEditMode ? 'Update' : 'Create' ?> Purchase Order
                        </button>
                    </div>
                </div>
            </div>
        </div>

    </form>

</div>

<!-- Product Search Modal -->
<div class="modal fade" id="productSearchModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-search me-2"></i> Search Products
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">

                <!-- Search Input -->
                <div class="mb-3">
                    <input
                        type="text"
                        class="form-control form-control-lg"
                        id="productSearchInput"
                        placeholder="Search by name, SKU, or supplier code..."
                        autocomplete="off"
                    >
                </div>

                <!-- Search Results -->
                <div id="productSearchResults" style="max-height: 400px; overflow-y: auto;">
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-search fa-3x mb-3"></i>
                        <p>Start typing to search for products</p>
                    </div>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Page JavaScript -->
<script>
// Global state
const lineItems = [];
let lineItemCounter = 0;
let autosaveInterval = null;
let currentRowForProduct = null;

// Initialize from server data (edit mode)
<?php if ($isEditMode && !empty($lineItems)): ?>
    <?php foreach ($lineItems as $item): ?>
        lineItems.push({
            id: <?= $item->id ?? 'null' ?>,
            product_id: '<?= htmlspecialchars($item->product_id) ?>',
            product_name: '<?= htmlspecialchars($item->product_name) ?>',
            product_sku: '<?= htmlspecialchars($item->product_sku ?? '') ?>',
            quantity: <?= $item->quantity ?>,
            cost: <?= $item->cost ?>,
            notes: '<?= htmlspecialchars($item->notes ?? '') ?>'
        });
    <?php endforeach; ?>
<?php endif; ?>

document.addEventListener('DOMContentLoaded', function() {

    // Initialize
    if (lineItems.length > 0) {
        lineItems.forEach(item => addLineItemRow(item));
    }

    updateTotals();
    startAutosave();

    // Add Item Button
    document.getElementById('addItemBtn').addEventListener('click', function() {
        addLineItemRow();
    });

    // Save Draft Button
    document.getElementById('saveDraftBtn').addEventListener('click', function() {
        savePO(true);
    });

    // Save Button
    document.getElementById('saveBtn').addEventListener('click', function() {
        savePO(false);
    });

    // Product Search
    const productSearchModal = new bootstrap.Modal(document.getElementById('productSearchModal'));
    const searchInput = document.getElementById('productSearchInput');
    let searchTimeout = null;

    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.trim();

        if (query.length < 2) {
            showSearchPlaceholder();
            return;
        }

        searchTimeout = setTimeout(() => searchProducts(query), 300);
    });

    // Functions

    function addLineItemRow(data = null) {
        const rowId = lineItemCounter++;
        const tbody = document.getElementById('lineItemsBody');

        // Remove empty state if present
        const emptyState = document.getElementById('emptyState');
        if (emptyState) {
            emptyState.remove();
        }

        const row = document.createElement('tr');
        row.id = `row-${rowId}`;
        row.dataset.rowId = rowId;

        if (data) {
            row.dataset.lineItemId = data.id || '';
            row.dataset.productId = data.product_id;
        }

        row.innerHTML = `
            <td class="text-center text-muted">${tbody.children.length + 1}</td>
            <td>
                ${data ?
                    `<div class="fw-bold">${escapeHtml(data.product_name)}</div>
                     ${data.product_sku ? `<small class="text-muted">SKU: ${escapeHtml(data.product_sku)}</small>` : ''}
                     <input type="hidden" class="product-id" value="${escapeHtml(data.product_id)}">`
                    :
                    `<button type="button" class="btn btn-outline-primary btn-sm select-product">
                        <i class="fas fa-search me-2"></i> Select Product
                     </button>
                     <input type="hidden" class="product-id" value="">`
                }
                <input type="text" class="form-control form-control-sm mt-2 item-notes" placeholder="Notes (optional)" value="${data?.notes || ''}">
            </td>
            <td>
                <input
                    type="number"
                    class="form-control item-quantity"
                    min="1"
                    step="1"
                    value="${data?.quantity || 1}"
                    required
                >
            </td>
            <td>
                <div class="input-group">
                    <span class="input-group-text">$</span>
                    <input
                        type="number"
                        class="form-control item-cost"
                        min="0"
                        step="0.01"
                        value="${data?.cost || '0.00'}"
                        required
                    >
                </div>
            </td>
            <td>
                <div class="fw-bold item-total">$0.00</div>
            </td>
            <td class="text-center">
                <button type="button" class="btn btn-sm btn-outline-danger remove-item" title="Remove">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;

        tbody.appendChild(row);

        // Event listeners for this row
        const quantityInput = row.querySelector('.item-quantity');
        const costInput = row.querySelector('.item-cost');
        const removeBtn = row.querySelector('.remove-item');
        const selectProductBtn = row.querySelector('.select-product');

        quantityInput.addEventListener('input', () => calculateRowTotal(row));
        costInput.addEventListener('input', () => calculateRowTotal(row));
        removeBtn.addEventListener('click', () => removeRow(row));

        if (selectProductBtn) {
            selectProductBtn.addEventListener('click', () => openProductSearch(row));
        }

        calculateRowTotal(row);
        updateTotals();
    }

    function calculateRowTotal(row) {
        const quantity = parseFloat(row.querySelector('.item-quantity').value) || 0;
        const cost = parseFloat(row.querySelector('.item-cost').value) || 0;
        const total = quantity * cost;

        row.querySelector('.item-total').textContent = `$${total.toFixed(2)}`;
        updateTotals();
    }

    function updateTotals() {
        const tbody = document.getElementById('lineItemsBody');
        const rows = tbody.querySelectorAll('tr:not(#emptyState)');

        let grandTotal = 0;
        rows.forEach(row => {
            const totalText = row.querySelector('.item-total')?.textContent;
            if (totalText) {
                grandTotal += parseFloat(totalText.replace('$', ''));
            }
        });

        document.getElementById('grandTotal').textContent = `$${grandTotal.toFixed(2)}`;
        document.getElementById('itemCount').textContent = rows.length;
    }

    function removeRow(row) {
        if (!confirm('Remove this item?')) return;
        row.remove();
        updateTotals();
        renumberRows();
    }

    function renumberRows() {
        const tbody = document.getElementById('lineItemsBody');
        const rows = tbody.querySelectorAll('tr:not(#emptyState)');
        rows.forEach((row, index) => {
            row.querySelector('td:first-child').textContent = index + 1;
        });
    }

    function openProductSearch(row) {
        currentRowForProduct = row;
        document.getElementById('productSearchInput').value = '';
        showSearchPlaceholder();
        productSearchModal.show();
    }

    async function searchProducts(query) {
        const resultsDiv = document.getElementById('productSearchResults');
        resultsDiv.innerHTML = '<div class="text-center py-3"><i class="fas fa-spinner fa-spin fa-2x"></i><p>Searching...</p></div>';

        try {
            const response = await fetch(`../api/products/search.php?q=${encodeURIComponent(query)}&limit=20`);
            const result = await response.json();

            if (result.success && result.data.length > 0) {
                displaySearchResults(result.data);
            } else {
                resultsDiv.innerHTML = `
                    <div class="text-center text-muted py-5">
                        <i class="fas fa-search fa-3x mb-3"></i>
                        <p>No products found matching "${escapeHtml(query)}"</p>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Search error:', error);
            resultsDiv.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>
                    Search failed. Please try again.
                </div>
            `;
        }
    }

    function displaySearchResults(products) {
        const resultsDiv = document.getElementById('productSearchResults');

        let html = '<div class="list-group list-group-flush">';

        products.forEach(product => {
            html += `
                <button
                    type="button"
                    class="list-group-item list-group-item-action product-result"
                    data-product-id="${escapeHtml(product.id)}"
                    data-product-name="${escapeHtml(product.name)}"
                    data-product-sku="${escapeHtml(product.sku || '')}"
                    data-product-cost="${product.cost || 0}"
                >
                    <div class="d-flex justify-content-between align-items-start">
                        <div>
                            <div class="fw-bold">${escapeHtml(product.name)}</div>
                            ${product.sku ? `<small class="text-muted">SKU: ${escapeHtml(product.sku)}</small>` : ''}
                        </div>
                        <div class="text-end">
                            <div class="fw-bold">$${parseFloat(product.cost || 0).toFixed(2)}</div>
                            ${product.confidence ? `<small class="badge bg-${product.confidence >= 90 ? 'success' : 'warning'}">${product.confidence}% match</small>` : ''}
                        </div>
                    </div>
                </button>
            `;
        });

        html += '</div>';
        resultsDiv.innerHTML = html;

        // Add click handlers
        resultsDiv.querySelectorAll('.product-result').forEach(btn => {
            btn.addEventListener('click', function() {
                selectProduct({
                    id: this.dataset.productId,
                    name: this.dataset.productName,
                    sku: this.dataset.productSku,
                    cost: parseFloat(this.dataset.productCost)
                });
            });
        });
    }

    function selectProduct(product) {
        if (!currentRowForProduct) return;

        const row = currentRowForProduct;

        // Update product display
        const productCell = row.querySelector('td:nth-child(2)');
        productCell.innerHTML = `
            <div class="fw-bold">${escapeHtml(product.name)}</div>
            ${product.sku ? `<small class="text-muted">SKU: ${escapeHtml(product.sku)}</small>` : ''}
            <input type="hidden" class="product-id" value="${escapeHtml(product.id)}">
            <input type="text" class="form-control form-control-sm mt-2 item-notes" placeholder="Notes (optional)" value="">
        `;

        // Update cost
        row.querySelector('.item-cost').value = product.cost.toFixed(2);

        // Set product ID
        row.dataset.productId = product.id;

        // Recalculate
        calculateRowTotal(row);

        // Close modal
        productSearchModal.hide();
        currentRowForProduct = null;
    }

    function showSearchPlaceholder() {
        document.getElementById('productSearchResults').innerHTML = `
            <div class="text-center text-muted py-5">
                <i class="fas fa-search fa-3x mb-3"></i>
                <p>Start typing to search for products</p>
            </div>
        `;
    }

    async function savePO(isDraft) {
        // Validation
        const supplierId = document.getElementById('supplierId').value;
        const outletId = document.getElementById('outletId').value;

        if (!supplierId || !outletId) {
            alert('Please select both a supplier and destination outlet');
            return;
        }

        const tbody = document.getElementById('lineItemsBody');
        const rows = tbody.querySelectorAll('tr:not(#emptyState)');

        if (rows.length === 0) {
            alert('Please add at least one line item');
            return;
        }

        // Validate all rows have products
        let hasInvalidRows = false;
        rows.forEach(row => {
            const productId = row.dataset.productId;
            if (!productId) {
                hasInvalidRows = true;
                row.classList.add('table-danger');
            } else {
                row.classList.remove('table-danger');
            }
        });

        if (hasInvalidRows) {
            alert('Please select products for all line items (highlighted in red)');
            return;
        }

        // Build line items array
        const items = [];
        rows.forEach(row => {
            items.push({
                id: row.dataset.lineItemId || null,
                product_id: row.dataset.productId,
                quantity: parseFloat(row.querySelector('.item-quantity').value),
                cost: parseFloat(row.querySelector('.item-cost').value),
                notes: row.querySelector('.item-notes').value
            });
        });

        // Build payload
        const payload = {
            po_id: parseInt(document.getElementById('poId').value) || null,
            supplier_id: supplierId,
            outlet_id: outletId,
            expected_date: document.getElementById('expectedDate').value || null,
            supplier_reference: document.getElementById('supplierReference').value || null,
            notes: document.getElementById('notes').value || null,
            line_items: items,
            is_draft: isDraft
        };

        // Disable buttons
        const saveBtn = document.getElementById('saveBtn');
        const saveDraftBtn = document.getElementById('saveDraftBtn');
        const originalSaveHtml = saveBtn.innerHTML;
        const originalDraftHtml = saveDraftBtn.innerHTML;

        saveBtn.disabled = true;
        saveDraftBtn.disabled = true;
        saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin me-2"></i> Saving...';

        try {
            const url = payload.po_id
                ? '../api/purchase-orders/update.php'
                : '../api/purchase-orders/create.php';

            const response = await fetch(url, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (result.success) {
                showAutosaveStatus('Purchase order saved successfully!', 'success');

                // Redirect after 1 second
                setTimeout(() => {
                    window.location.href = 'view.php?id=' + result.data.id;
                }, 1000);
            } else {
                alert('Error: ' + (result.error || 'Failed to save purchase order'));
                saveBtn.disabled = false;
                saveDraftBtn.disabled = false;
                saveBtn.innerHTML = originalSaveHtml;
            }

        } catch (error) {
            console.error('Save error:', error);
            alert('Network error. Please try again.');
            saveBtn.disabled = false;
            saveDraftBtn.disabled = false;
            saveBtn.innerHTML = originalSaveHtml;
        }
    }

    function startAutosave() {
        // Only enable autosave in edit mode
        if (document.getElementById('isEdit').value !== '1') {
            return;
        }

        showAutosaveStatus('Auto-save enabled (every 30 seconds)', 'info');

        autosaveInterval = setInterval(() => {
            performAutosave();
        }, 30000); // 30 seconds
    }

    async function performAutosave() {
        const poId = parseInt(document.getElementById('poId').value);
        if (!poId) return; // Can't autosave without existing PO

        showAutosaveStatus('Auto-saving...', 'info');

        try {
            // Build minimal payload for autosave
            const payload = {
                po_id: poId,
                supplier_id: document.getElementById('supplierId').value,
                outlet_id: document.getElementById('outletId').value,
                expected_date: document.getElementById('expectedDate').value || null,
                supplier_reference: document.getElementById('supplierReference').value || null,
                notes: document.getElementById('notes').value || null,
                line_items: [],
                is_draft: true
            };

            // Add line items
            const rows = document.querySelectorAll('#lineItemsBody tr:not(#emptyState)');
            rows.forEach(row => {
                if (row.dataset.productId) {
                    payload.line_items.push({
                        id: row.dataset.lineItemId || null,
                        product_id: row.dataset.productId,
                        quantity: parseFloat(row.querySelector('.item-quantity').value),
                        cost: parseFloat(row.querySelector('.item-cost').value),
                        notes: row.querySelector('.item-notes').value
                    });
                }
            });

            const response = await fetch('../api/purchase-orders/update.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload)
            });

            const result = await response.json();

            if (result.success) {
                showAutosaveStatus('Auto-saved at ' + new Date().toLocaleTimeString(), 'success');
            } else {
                showAutosaveStatus('Auto-save failed', 'warning');
            }

        } catch (error) {
            console.error('Autosave error:', error);
            showAutosaveStatus('Auto-save failed', 'danger');
        }
    }

    function showAutosaveStatus(message, type) {
        const status = document.getElementById('autosaveStatus');
        const messageSpan = document.getElementById('autosaveMessage');

        status.className = `alert alert-${type} alert-dismissible fade show mb-3`;
        messageSpan.textContent = message;
        status.style.display = 'block';

        // Auto-hide after 5 seconds
        setTimeout(() => {
            status.style.display = 'none';
        }, 5000);
    }

    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

});

// Cleanup on page unload
window.addEventListener('beforeunload', function() {
    if (autosaveInterval) {
        clearInterval(autosaveInterval);
    }
});
</script>

<style>
.required::after {
    content: ' *';
    color: #dc3545;
}

.product-result:hover {
    background-color: #f8f9fa;
}

.table-danger {
    background-color: #f8d7da !important;
}
</style>

<?php
// Include footer
include $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/shared/blocks/footer.php';
?>
