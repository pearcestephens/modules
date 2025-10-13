<!-- Google-Level Product Search Modal -->
<div class="modal fade" id="productSearchModal" tabindex="-1" role="dialog" aria-labelledby="productSearchTitle" aria-hidden="true">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content product-search-modal">
            <!-- Modal Header -->
            <div class="modal-header search-modal-header">
                <div class="search-header-content">
                    <h5 class="modal-title" id="productSearchTitle">
                        <i class="fa fa-search"></i>
                        Add Products to Transfer
                    </h5>
                    <div class="search-status">
                        <span id="selectedProductsCount" class="badge badge-primary" style="display: none;">0</span>
                        <span class="selected-label">products selected</span>
                    </div>
                </div>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <!-- Search Input Section -->
            <div class="search-input-section">
                <div class="search-input-wrapper">
                    <div class="input-group">
                        <div class="input-group-prepend">
                            <span class="input-group-text">
                                <i class="fa fa-search"></i>
                            </span>
                        </div>
                        <input type="text" 
                               id="productSearchInput" 
                               class="form-control search-input" 
                               placeholder="Search by product name, SKU, brand, or supplier..."
                               autocomplete="off"
                               spellcheck="false">
                        <div class="input-group-append">
                            <button type="button" id="clearSearch" class="btn btn-outline-secondary" title="Clear search">
                                <i class="fa fa-times"></i>
                            </button>
                        </div>
                    </div>
                    <div class="search-hints">
                        <small class="text-muted">
                            <i class="fa fa-lightbulb"></i>
                            Tips: Use <kbd>↑</kbd><kbd>↓</kbd> to navigate, <kbd>Enter</kbd> to select, <kbd>Shift+Click</kbd> for bulk selection
                        </small>
                    </div>
                </div>
                
                <!-- Loading Indicator -->
                <div id="productSearchLoading" class="search-loading" style="display: none;">
                    <div class="loading-spinner">
                        <i class="fa fa-spinner fa-spin"></i>
                        <span>Searching products...</span>
                    </div>
                </div>
            </div>

            <!-- Modal Body -->
            <div class="modal-body search-modal-body">
                <div class="search-layout">
                    <!-- Search Results -->
                    <div class="search-results-section">
                        <div class="results-header">
                            <h6><i class="fa fa-list"></i> Search Results</h6>
                            <div class="results-actions">
                                <button type="button" id="selectAllVisible" class="btn btn-sm btn-outline-primary">
                                    <i class="fa fa-check-square"></i> Select All
                                </button>
                                <button type="button" id="clearAllVisible" class="btn btn-sm btn-outline-secondary">
                                    <i class="fa fa-square"></i> Clear All
                                </button>
                            </div>
                        </div>
                        
                        <div id="productSearchResults" class="search-results" role="listbox" aria-label="Product search results">
                            <!-- Search results will be populated here -->
                        </div>
                    </div>

                    <!-- Selected Products Sidebar -->
                    <div class="selected-products-section">
                        <div class="selected-header">
                            <h6><i class="fa fa-shopping-cart"></i> Selected Products</h6>
                            <button type="button" id="clearAllProducts" class="btn btn-sm btn-outline-danger" style="display: none;">
                                <i class="fa fa-trash"></i> Clear All
                            </button>
                        </div>
                        
                        <div id="selectedProductsList" class="selected-products-list">
                            <p class="no-selection">No products selected</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Modal Footer -->
            <div class="modal-footer search-modal-footer">
                <div class="footer-info">
                    <small class="text-muted">
                        <i class="fa fa-info-circle"></i>
                        Stock levels shown are for the selected outlet. Prices include GST.
                    </small>
                </div>
                <div class="footer-actions">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">
                        <i class="fa fa-times"></i> Cancel
                    </button>
                    <button type="button" id="confirmProductSelection" class="btn btn-primary" disabled>
                        <i class="fa fa-plus"></i> Select Products
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Keyboard Shortcuts Help (Optional) -->
<div class="search-shortcuts-help" style="display: none;">
    <div class="shortcuts-content">
        <h6>Keyboard Shortcuts</h6>
        <div class="shortcut-grid">
            <div class="shortcut-item">
                <kbd>↑</kbd><kbd>↓</kbd>
                <span>Navigate results</span>
            </div>
            <div class="shortcut-item">
                <kbd>Enter</kbd>
                <span>Select/deselect</span>
            </div>
            <div class="shortcut-item">
                <kbd>Shift</kbd> + <kbd>Click</kbd>
                <span>Bulk select</span>
            </div>
            <div class="shortcut-item">
                <kbd>Esc</kbd>
                <span>Close modal</span>
            </div>
            <div class="shortcut-item">
                <kbd>Ctrl</kbd> + <kbd>A</kbd>
                <span>Select all visible</span>
            </div>
        </div>
    </div>
</div>

<script>
// Quick access functions for the modal
function openProductSearchModal(outletId = null) {
    if (typeof productSearchModal !== 'undefined') {
        productSearchModal.open(outletId);
    } else {
        console.error('Product search modal not initialized');
    }
}

function getSelectedProducts() {
    if (typeof productSearchModal !== 'undefined') {
        return productSearchModal.getSelectedProducts();
    }
    return [];
}

function clearProductSelection() {
    if (typeof productSearchModal !== 'undefined') {
        productSearchModal.reset();
    }
}

// Example integration function - customize for your transfer form
function addProductsToTransfer(products) {
    console.log('Adding products to transfer:', products);
    
    // Example: Add to a transfer items table
    const transferItemsContainer = document.getElementById('transferItemsContainer');
    if (transferItemsContainer) {
        products.forEach(product => {
            addTransferItem(product);
        });
    }
    
    // Show success message
    if (products.length > 0) {
        showNotification(`Added ${products.length} product${products.length !== 1 ? 's' : ''} to transfer`, 'success');
    }
}

// Example transfer item addition - customize for your form structure  
function addTransferItem(product) {
    // This would depend on your specific transfer form implementation
    // Example implementation:
    console.log('Adding transfer item:', product);
}

// Utility function for notifications
function showNotification(message, type = 'info') {
    // Integrate with your notification system
    console.log(`${type.toUpperCase()}: ${message}`);
}
</script>