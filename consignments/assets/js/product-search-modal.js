/**
 * Google-Level Product Search Modal
 * 
 * High-performance product picker with:
 * - Real-time search with debouncing
 * - Bulk selection (Shift+Click)
 * - Keyboard navigation (Arrow keys, Enter, Escape)
 * - Stock level indicators
 * - Responsive design
 * - Accessibility (ARIA labels, screen reader support)
 * 
 * @version 2.0.0
 * @author CIS Transfer System
 */

class ProductSearchModal {
    constructor(options = {}) {
        this.options = {
            endpoint: '/modules/consignments/api/search_products.php',
            debounceMs: 300,
            minSearchLength: 2,
            maxResults: 100,
            outletId: null,
            multiSelect: true,
            ...options
        };
        
        this.searchTimeout = null;
        this.selectedProducts = new Map(); // Map<productId, productData>
        this.filteredProducts = [];
        this.focusedIndex = -1;
        this.isLoading = false;
        this.lastShiftClickIndex = -1;
        
        this.init();
    }
    
    init() {
        this.modal = document.getElementById('productSearchModal');
        this.searchInput = document.getElementById('productSearchInput');
        this.resultsContainer = document.getElementById('productSearchResults');
        this.selectedContainer = document.getElementById('selectedProductsList');
        this.loadingIndicator = document.getElementById('productSearchLoading');
        this.countBadge = document.getElementById('selectedProductsCount');
        this.clearAllBtn = document.getElementById('clearAllProducts');
        this.confirmBtn = document.getElementById('confirmProductSelection');
        
        this.bindEvents();
        this.updateUI();
    }
    
    bindEvents() {
        // Search input with debouncing
        this.searchInput.addEventListener('input', (e) => {
            this.handleSearch(e.target.value);
        });
        
        // Keyboard navigation
        this.searchInput.addEventListener('keydown', (e) => {
            this.handleKeyNavigation(e);
        });
        
        // Modal controls
        this.clearAllBtn.addEventListener('click', () => {
            this.clearAllProducts();
        });
        
        this.confirmBtn.addEventListener('click', () => {
            this.confirmSelection();
        });
        
        // Close modal events
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape' && this.modal.classList.contains('show')) {
                this.close();
            }
        });
        
        // Click outside to close
        this.modal.addEventListener('click', (e) => {
            if (e.target === this.modal) {
                this.close();
            }
        });
    }
    
    open(outletId = null) {
        this.options.outletId = outletId;
        this.modal.classList.add('show');
        this.modal.style.display = 'flex';
        
        // Focus search input
        setTimeout(() => {
            this.searchInput.focus();
        }, 100);
        
        // Load initial products if no search
        if (!this.searchInput.value.trim()) {
            this.loadProducts('');
        }
    }
    
    close() {
        this.modal.classList.remove('show');
        this.modal.style.display = 'none';
        this.searchInput.value = '';
        this.clearResults();
        this.focusedIndex = -1;
    }
    
    handleSearch(query) {
        clearTimeout(this.searchTimeout);
        
        if (query.length < this.options.minSearchLength && query.length > 0) {
            this.showMessage('Type at least ' + this.options.minSearchLength + ' characters to search');
            return;
        }
        
        this.searchTimeout = setTimeout(() => {
            this.loadProducts(query);
        }, this.options.debounceMs);
    }
    
    async loadProducts(query) {
        this.setLoading(true);
        
        try {
            const params = new URLSearchParams({
                query: query,
                outlet_id: this.options.outletId || '',
                limit: this.options.maxResults
            });
            
            const response = await fetch(`${this.options.endpoint}?${params}`);
            const data = await response.json();
            
            if (data.success) {
                this.displayProducts(data.products || []);
            } else {
                this.showMessage(data.error || 'Search failed');
            }
        } catch (error) {
            console.error('Product search error:', error);
            this.showMessage('Network error. Please try again.');
        } finally {
            this.setLoading(false);
        }
    }
    
    displayProducts(products) {
        this.filteredProducts = products;
        this.focusedIndex = -1;
        
        if (products.length === 0) {
            this.showMessage('No products found');
            return;
        }
        
        const html = products.map((product, index) => {
            const isSelected = this.selectedProducts.has(product.id);
            const stockClass = this.getStockClass(product.inventory_level);
            const stockText = this.getStockText(product.inventory_level);
            
            return `
                <div class="product-item ${isSelected ? 'selected' : ''}" 
                     data-product-id="${product.id}" 
                     data-index="${index}"
                     tabindex="0"
                     role="option"
                     aria-selected="${isSelected}">
                    
                    <div class="product-checkbox">
                        <input type="checkbox" 
                               id="product-${product.id}" 
                               ${isSelected ? 'checked' : ''}
                               tabindex="-1">
                        <label for="product-${product.id}" class="sr-only">
                            Select ${product.name}
                        </label>
                    </div>
                    
                    <div class="product-info">
                        <div class="product-main">
                            <h6 class="product-name">${this.escapeHtml(product.name)}</h6>
                            <div class="product-meta">
                                <span class="product-sku">SKU: ${this.escapeHtml(product.sku || 'N/A')}</span>
                                ${product.brand ? `<span class="product-brand">${this.escapeHtml(product.brand)}</span>` : ''}
                                ${product.supplier_name ? `<span class="product-supplier">${this.escapeHtml(product.supplier_name)}</span>` : ''}
                            </div>
                        </div>
                        
                        <div class="product-details">
                            <div class="product-pricing">
                                ${product.supply_price ? `<span class="supply-price">Cost: $${parseFloat(product.supply_price).toFixed(2)}</span>` : ''}
                                ${product.price_excluding_tax ? `<span class="sell-price">Sell: $${parseFloat(product.price_excluding_tax).toFixed(2)}</span>` : ''}
                            </div>
                            
                            <div class="product-stock">
                                <span class="stock-indicator ${stockClass}" 
                                      title="Current stock level">
                                    <i class="fa fa-box"></i>
                                    ${stockText}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            `;
        }).join('');
        
        this.resultsContainer.innerHTML = html;
        
        // Bind click events
        this.resultsContainer.addEventListener('click', (e) => {
            this.handleProductClick(e);
        });
        
        // Bind keyboard events for items
        this.resultsContainer.addEventListener('keydown', (e) => {
            this.handleProductKeydown(e);
        });
    }
    
    handleProductClick(e) {
        const productItem = e.target.closest('.product-item');
        if (!productItem) return;
        
        const productId = productItem.dataset.productId;
        const index = parseInt(productItem.dataset.index);
        
        // Handle Shift+Click for range selection
        if (e.shiftKey && this.lastShiftClickIndex !== -1) {
            this.selectRange(this.lastShiftClickIndex, index);
        } else {
            this.toggleProduct(productId);
        }
        
        this.lastShiftClickIndex = index;
        e.preventDefault();
    }
    
    handleProductKeydown(e) {
        const productItem = e.target.closest('.product-item');
        if (!productItem) return;
        
        if (e.key === 'Enter' || e.key === ' ') {
            const productId = productItem.dataset.productId;
            this.toggleProduct(productId);
            e.preventDefault();
        }
    }
    
    selectRange(startIndex, endIndex) {
        const start = Math.min(startIndex, endIndex);
        const end = Math.max(startIndex, endIndex);
        
        for (let i = start; i <= end; i++) {
            if (i < this.filteredProducts.length) {
                const product = this.filteredProducts[i];
                this.selectedProducts.set(product.id, product);
            }
        }
        
        this.updateUI();
        this.refreshProductDisplay();
    }
    
    toggleProduct(productId) {
        const product = this.filteredProducts.find(p => p.id == productId);
        if (!product) return;
        
        if (this.selectedProducts.has(productId)) {
            this.selectedProducts.delete(productId);
        } else {
            this.selectedProducts.set(productId, product);
        }
        
        this.updateUI();
        this.refreshProductDisplay();
    }
    
    refreshProductDisplay() {
        const productItems = this.resultsContainer.querySelectorAll('.product-item');
        productItems.forEach(item => {
            const productId = item.dataset.productId;
            const isSelected = this.selectedProducts.has(productId);
            const checkbox = item.querySelector('input[type="checkbox"]');
            
            item.classList.toggle('selected', isSelected);
            if (checkbox) {
                checkbox.checked = isSelected;
            }
            item.setAttribute('aria-selected', isSelected);
        });
    }
    
    handleKeyNavigation(e) {
        const productItems = this.resultsContainer.querySelectorAll('.product-item');
        
        switch (e.key) {
            case 'ArrowDown':
                e.preventDefault();
                this.focusedIndex = Math.min(this.focusedIndex + 1, productItems.length - 1);
                this.updateFocus(productItems);
                break;
                
            case 'ArrowUp':
                e.preventDefault();
                this.focusedIndex = Math.max(this.focusedIndex - 1, -1);
                if (this.focusedIndex === -1) {
                    this.searchInput.focus();
                } else {
                    this.updateFocus(productItems);
                }
                break;
                
            case 'Enter':
                if (this.focusedIndex >= 0 && productItems[this.focusedIndex]) {
                    const productId = productItems[this.focusedIndex].dataset.productId;
                    this.toggleProduct(productId);
                    e.preventDefault();
                }
                break;
        }
    }
    
    updateFocus(productItems) {
        productItems.forEach((item, index) => {
            if (index === this.focusedIndex) {
                item.focus();
                item.scrollIntoView({ block: 'nearest' });
            }
        });
    }
    
    clearAllProducts() {
        this.selectedProducts.clear();
        this.updateUI();
        this.refreshProductDisplay();
    }
    
    updateUI() {
        const count = this.selectedProducts.size;
        
        // Update count badge
        this.countBadge.textContent = count;
        this.countBadge.style.display = count > 0 ? 'inline-block' : 'none';
        
        // Update confirm button
        this.confirmBtn.disabled = count === 0;
        this.confirmBtn.textContent = count > 0 
            ? `Add ${count} Product${count !== 1 ? 's' : ''}` 
            : 'Select Products';
        
        // Update clear button
        this.clearAllBtn.style.display = count > 0 ? 'inline-block' : 'none';
        
        // Update selected products list
        this.displaySelectedProducts();
    }
    
    displaySelectedProducts() {
        if (this.selectedProducts.size === 0) {
            this.selectedContainer.innerHTML = '<p class="no-selection">No products selected</p>';
            return;
        }
        
        const html = Array.from(this.selectedProducts.values()).map(product => {
            const stockClass = this.getStockClass(product.inventory_level);
            const stockText = this.getStockText(product.inventory_level);
            
            return `
                <div class="selected-product" data-product-id="${product.id}">
                    <div class="selected-product-info">
                        <span class="selected-product-name">${this.escapeHtml(product.name)}</span>
                        <span class="selected-product-sku">SKU: ${this.escapeHtml(product.sku || 'N/A')}</span>
                        <span class="selected-stock ${stockClass}">${stockText}</span>
                    </div>
                    <button type="button" 
                            class="remove-product-btn" 
                            onclick="productSearchModal.removeProduct('${product.id}')"
                            title="Remove product">
                        <i class="fa fa-times"></i>
                    </button>
                </div>
            `;
        }).join('');
        
        this.selectedContainer.innerHTML = html;
    }
    
    removeProduct(productId) {
        this.selectedProducts.delete(productId);
        this.updateUI();
        this.refreshProductDisplay();
    }
    
    confirmSelection() {
        const selectedProducts = Array.from(this.selectedProducts.values());
        
        // Trigger custom event with selected products
        const event = new CustomEvent('productsSelected', {
            detail: { products: selectedProducts }
        });
        document.dispatchEvent(event);
        
        this.close();
    }
    
    getStockClass(inventoryLevel) {
        if (inventoryLevel === null || inventoryLevel === undefined) return 'stock-unknown';
        if (inventoryLevel <= 0) return 'stock-out';
        if (inventoryLevel < 5) return 'stock-low';
        if (inventoryLevel < 20) return 'stock-medium';
        return 'stock-high';
    }
    
    getStockText(inventoryLevel) {
        if (inventoryLevel === null || inventoryLevel === undefined) return 'Unknown';
        if (inventoryLevel <= 0) return 'Out of Stock';
        return `${inventoryLevel} in stock`;
    }
    
    showMessage(message) {
        this.resultsContainer.innerHTML = `
            <div class="search-message">
                <i class="fa fa-info-circle"></i>
                <p>${this.escapeHtml(message)}</p>
            </div>
        `;
    }
    
    setLoading(loading) {
        this.isLoading = loading;
        this.loadingIndicator.style.display = loading ? 'block' : 'none';
        this.searchInput.disabled = loading;
        
        if (loading) {
            this.resultsContainer.innerHTML = '';
        }
    }
    
    clearResults() {
        this.resultsContainer.innerHTML = '';
        this.filteredProducts = [];
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // Public API methods
    getSelectedProducts() {
        return Array.from(this.selectedProducts.values());
    }
    
    setSelectedProducts(products) {
        this.selectedProducts.clear();
        products.forEach(product => {
            this.selectedProducts.set(product.id, product);
        });
        this.updateUI();
    }
    
    reset() {
        this.selectedProducts.clear();
        this.searchInput.value = '';
        this.clearResults();
        this.updateUI();
    }
}

// Initialize global instance
let productSearchModal;

document.addEventListener('DOMContentLoaded', function() {
    productSearchModal = new ProductSearchModal({
        endpoint: '/modules/consignments/api/search_products.php',
        debounceMs: 300,
        minSearchLength: 2,
        maxResults: 100
    });
    
    // Listen for product selection events
    document.addEventListener('productsSelected', function(e) {
        const selectedProducts = e.detail.products;
        console.log('Products selected:', selectedProducts);
        
        // Example: Add to transfer form
        if (typeof addProductsToTransfer === 'function') {
            addProductsToTransfer(selectedProducts);
        }
    });
});

// Export for module usage
if (typeof module !== 'undefined' && module.exports) {
    module.exports = ProductSearchModal;
}