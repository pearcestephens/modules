<?php
/**
 * STAFF SHOP - Product Browsing and Purchase Till
 *
 * Features:
 * - Real-time product search
 * - Automatic staff pricing calculation
 * - Shopping cart with live updates
 * - Friend & Family discount selection
 * - Integration with Lightspeed API
 * - Receipt generation
 *
 * @package StaffAccounts
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/lightspeed-api.php';

if (!isStaffAuthenticated()) {
    header('Location: login.php');
    exit;
}

$staff = getCurrentStaff();
$staffId = $staff['id'];
$staffDiscountRate = getStaffDiscountRate($staff['access_level']);

// Get categories for filtering
$categories = getProductCategories();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Shop - Product Selection</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/staff-accounts.css">

    <style>
        .product-card {
            transition: all 0.3s ease;
            cursor: pointer;
        }
        .product-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 4px 15px rgba(0,0,0,0.15);
        }
        .product-image {
            height: 200px;
            object-fit: cover;
        }
        .price-original {
            text-decoration: line-through;
            color: #999;
        }
        .price-staff {
            color: #28a745;
            font-weight: bold;
            font-size: 1.3em;
        }
        .discount-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 1;
        }
        .cart-sidebar {
            position: fixed;
            right: 0;
            top: 56px;
            height: calc(100vh - 56px);
            width: 350px;
            background: white;
            box-shadow: -2px 0 10px rgba(0,0,0,0.1);
            overflow-y: auto;
            z-index: 1000;
            transform: translateX(350px);
            transition: transform 0.3s ease;
        }
        .cart-sidebar.show {
            transform: translateX(0);
        }
        .cart-toggle {
            position: fixed;
            right: 20px;
            bottom: 20px;
            z-index: 1001;
        }
        .search-box {
            position: relative;
        }
        .search-results {
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            background: white;
            border: 1px solid #ddd;
            border-top: none;
            max-height: 400px;
            overflow-y: auto;
            z-index: 100;
            display: none;
        }
        .search-results.show {
            display: block;
        }
        .search-result-item {
            padding: 10px 15px;
            border-bottom: 1px solid #eee;
            cursor: pointer;
        }
        .search-result-item:hover {
            background: #f8f9fa;
        }
    </style>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php">
                <i class="bi bi-shop"></i> The Vape Shed - Staff Shop
            </a>
            <div class="ms-auto d-flex align-items-center">
                <span class="text-white me-3">
                    <i class="bi bi-person-circle"></i> <?= htmlspecialchars($staff['first_name']) ?>
                </span>
                <a href="index.php" class="btn btn-light btn-sm">
                    <i class="bi bi-arrow-left"></i> Dashboard
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid py-4">
        <div class="row">
            <!-- Products Section -->
            <div class="col-lg-9">
                <!-- Search and Filters -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row g-3">
                            <!-- Search -->
                            <div class="col-md-6">
                                <div class="search-box">
                                    <div class="input-group">
                                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                                        <input type="text"
                                               class="form-control form-control-lg"
                                               id="productSearch"
                                               placeholder="Search products by name, SKU, or barcode..."
                                               autocomplete="off">
                                    </div>
                                    <div class="search-results" id="searchResults"></div>
                                </div>
                            </div>

                            <!-- Category Filter -->
                            <div class="col-md-3">
                                <select class="form-select form-select-lg" id="categoryFilter">
                                    <option value="">All Categories</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?= htmlspecialchars($cat['id']) ?>">
                                            <?= htmlspecialchars($cat['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Discount Type -->
                            <div class="col-md-3">
                                <select class="form-select form-select-lg" id="discountType">
                                    <option value="staff">Staff Discount (<?= $staffDiscountRate ?>%)</option>
                                    <option value="friends">Friends Discount (20%)</option>
                                    <option value="family">Family Discount (30%)</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Products Grid -->
                <div id="productsGrid" class="row g-3">
                    <!-- Products loaded dynamically -->
                    <div class="col-12 text-center py-5">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Loading products...</span>
                        </div>
                        <p class="mt-3 text-muted">Loading products...</p>
                    </div>
                </div>

                <!-- Pagination -->
                <nav aria-label="Product pagination" class="mt-4">
                    <ul class="pagination justify-content-center" id="pagination">
                        <!-- Pagination loaded dynamically -->
                    </ul>
                </nav>
            </div>

            <!-- Cart Sidebar (Desktop) -->
            <div class="col-lg-3 d-none d-lg-block">
                <div class="card sticky-top" style="top: 20px;">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="bi bi-cart3"></i> Shopping Cart</h5>
                    </div>
                    <div class="card-body" id="cartContent">
                        <div class="text-center py-4 text-muted">
                            <i class="bi bi-cart-x" style="font-size: 48px;"></i>
                            <p class="mt-2">Your cart is empty</p>
                        </div>
                    </div>
                    <div class="card-footer" id="cartFooter" style="display: none;">
                        <div class="d-flex justify-content-between mb-2">
                            <span>Subtotal:</span>
                            <span id="cartSubtotal">$0.00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span>Discount:</span>
                            <span id="cartDiscount" class="text-success">-$0.00</span>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <strong>Total:</strong>
                            <strong id="cartTotal">$0.00</strong>
                        </div>
                        <div class="d-grid gap-2">
                            <button class="btn btn-primary btn-lg" onclick="checkout()">
                                <i class="bi bi-check-circle"></i> Checkout
                            </button>
                            <button class="btn btn-outline-danger btn-sm" onclick="clearCart()">
                                <i class="bi bi-trash"></i> Clear Cart
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Cart Toggle Button -->
    <button class="btn btn-primary btn-lg rounded-circle cart-toggle d-lg-none" onclick="toggleMobileCart()">
        <i class="bi bi-cart3"></i>
        <span class="badge bg-danger position-absolute top-0 start-100 translate-middle" id="cartCount">0</span>
    </button>

    <!-- Mobile Cart Sidebar -->
    <div class="cart-sidebar d-lg-none" id="mobileCart">
        <div class="p-3 bg-primary text-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0"><i class="bi bi-cart3"></i> Shopping Cart</h5>
            <button class="btn btn-light btn-sm" onclick="toggleMobileCart()">
                <i class="bi bi-x-lg"></i>
            </button>
        </div>
        <div class="p-3" id="mobileCartContent">
            <!-- Same as desktop cart -->
        </div>
    </div>

    <!-- Checkout Modal -->
    <div class="modal fade" id="checkoutModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title"><i class="bi bi-check-circle"></i> Complete Purchase</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="checkoutSummary"></div>

                    <div class="mt-3">
                        <h6>Purchase Method</h6>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="paymentMethod" id="payrollDeduction" value="payroll" checked>
                            <label class="form-check-label" for="payrollDeduction">
                                Payroll Deduction (Automatically deducted from next pay)
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="paymentMethod" id="directPayment" value="direct">
                            <label class="form-check-label" for="directPayment">
                                Direct Payment (Pay now)
                            </label>
                        </div>
                    </div>

                    <div class="mt-3">
                        <h6>Notes (Optional)</h6>
                        <textarea class="form-control" id="purchaseNotes" rows="3" placeholder="Add any notes about this purchase..."></textarea>
                    </div>

                    <div class="alert alert-info mt-3">
                        <i class="bi bi-info-circle"></i> This purchase will be recorded against your staff account: <strong><?= htmlspecialchars($staffName) ?></strong>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary btn-lg" onclick="completePurchase()">
                        <i class="bi bi-check-lg"></i> Confirm Purchase
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Shopping cart state
        let cart = [];
        let discountType = 'staff';
        let discountRates = {
            'staff': <?= $staffDiscountRate ?>,
            'friends': 20,
            'family': 30
        };
        let currentPage = 1;
        let products = [];
        let searchTimeout = null;

        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            loadProducts();
            loadCartFromStorage();

            // Search functionality
            document.getElementById('productSearch').addEventListener('input', function(e) {
                clearTimeout(searchTimeout);
                const query = e.target.value;

                if (query.length >= 2) {
                    searchTimeout = setTimeout(() => searchProducts(query), 300);
                } else {
                    hideSearchResults();
                }
            });

            // Category filter
            document.getElementById('categoryFilter').addEventListener('change', function() {
                loadProducts();
            });

            // Discount type change
            document.getElementById('discountType').addEventListener('change', function() {
                discountType = this.value;
                updateCartDisplay();
            });
        });

        // Load products from API
        async function loadProducts(page = 1) {
            currentPage = page;
            const category = document.getElementById('categoryFilter').value;

            try {
                const response = await fetch(`api/products.php?page=${page}&category=${category}`);
                const data = await response.json();

                if (data.success) {
                    products = data.products;
                    renderProducts(data.products);
                    renderPagination(data.pagination);
                } else {
                    showError('Failed to load products');
                }
            } catch (error) {
                showError('Error loading products: ' + error.message);
            }
        }

        // Render products grid
        function renderProducts(products) {
            const grid = document.getElementById('productsGrid');

            if (products.length === 0) {
                grid.innerHTML = `
                    <div class="col-12 text-center py-5">
                        <i class="bi bi-inbox" style="font-size: 64px; color: #ccc;"></i>
                        <p class="mt-3 text-muted">No products found</p>
                    </div>
                `;
                return;
            }

            grid.innerHTML = products.map(product => {
                const retailPrice = parseFloat(product.price);
                const discountRate = discountRates[discountType];
                const staffPrice = retailPrice * (1 - discountRate / 100);
                const savings = retailPrice - staffPrice;

                return `
                    <div class="col-md-6 col-lg-4">
                        <div class="card product-card h-100" onclick="addToCart(${product.id})">
                            <div class="position-relative">
                                <img src="${product.image || 'assets/img/no-image.jpg'}"
                                     class="card-img-top product-image"
                                     alt="${product.name}">
                                <span class="badge bg-success discount-badge">
                                    -${discountRate}%
                                </span>
                            </div>
                            <div class="card-body">
                                <h6 class="card-title">${product.name}</h6>
                                ${product.sku ? `<small class="text-muted">SKU: ${product.sku}</small><br>` : ''}
                                <div class="mt-2">
                                    <span class="price-original">$${retailPrice.toFixed(2)}</span>
                                    <div class="price-staff">$${staffPrice.toFixed(2)}</div>
                                    <small class="text-success">Save $${savings.toFixed(2)}</small>
                                </div>
                                ${product.stock_level !== null ? `
                                    <div class="mt-2">
                                        <span class="badge ${product.stock_level > 10 ? 'bg-success' : product.stock_level > 0 ? 'bg-warning' : 'bg-danger'}">
                                            ${product.stock_level > 0 ? `${product.stock_level} in stock` : 'Out of stock'}
                                        </span>
                                    </div>
                                ` : ''}
                            </div>
                            <div class="card-footer">
                                <button class="btn btn-primary w-100" ${product.stock_level === 0 ? 'disabled' : ''}>
                                    <i class="bi bi-cart-plus"></i> Add to Cart
                                </button>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        // Search products
        async function searchProducts(query) {
            try {
                const response = await fetch(`api/products.php?search=${encodeURIComponent(query)}`);
                const data = await response.json();

                if (data.success) {
                    showSearchResults(data.products);
                }
            } catch (error) {
                console.error('Search error:', error);
            }
        }

        // Show search results dropdown
        function showSearchResults(products) {
            const resultsDiv = document.getElementById('searchResults');

            if (products.length === 0) {
                resultsDiv.innerHTML = '<div class="p-3 text-muted">No products found</div>';
            } else {
                resultsDiv.innerHTML = products.slice(0, 10).map(product => {
                    const retailPrice = parseFloat(product.price);
                    const discountRate = discountRates[discountType];
                    const staffPrice = retailPrice * (1 - discountRate / 100);

                    return `
                        <div class="search-result-item" onclick="addToCart(${product.id}); hideSearchResults();">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-semibold">${product.name}</div>
                                    ${product.sku ? `<small class="text-muted">SKU: ${product.sku}</small>` : ''}
                                </div>
                                <div class="text-end">
                                    <div class="price-staff">$${staffPrice.toFixed(2)}</div>
                                    <small class="price-original">$${retailPrice.toFixed(2)}</small>
                                </div>
                            </div>
                        </div>
                    `;
                }).join('');
            }

            resultsDiv.classList.add('show');
        }

        // Hide search results
        function hideSearchResults() {
            document.getElementById('searchResults').classList.remove('show');
        }

        // Add product to cart
        function addToCart(productId) {
            const product = products.find(p => p.id === productId);
            if (!product) return;

            const existingItem = cart.find(item => item.id === productId && item.discountType === discountType);

            if (existingItem) {
                existingItem.quantity++;
            } else {
                cart.push({
                    id: product.id,
                    name: product.name,
                    sku: product.sku,
                    retailPrice: parseFloat(product.price),
                    discountType: discountType,
                    quantity: 1
                });
            }

            saveCartToStorage();
            updateCartDisplay();
            showToast(`Added ${product.name} to cart`);
        }

        // Update cart display
        function updateCartDisplay() {
            const cartContent = document.getElementById('cartContent');
            const cartFooter = document.getElementById('cartFooter');
            const cartCount = document.getElementById('cartCount');

            if (cart.length === 0) {
                cartContent.innerHTML = `
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-cart-x" style="font-size: 48px;"></i>
                        <p class="mt-2">Your cart is empty</p>
                    </div>
                `;
                cartFooter.style.display = 'none';
                cartCount.textContent = '0';
                return;
            }

            let subtotal = 0;
            let totalDiscount = 0;

            cartContent.innerHTML = cart.map(item => {
                const discountRate = discountRates[item.discountType];
                const staffPrice = item.retailPrice * (1 - discountRate / 100);
                const itemTotal = staffPrice * item.quantity;
                const itemDiscount = (item.retailPrice - staffPrice) * item.quantity;

                subtotal += item.retailPrice * item.quantity;
                totalDiscount += itemDiscount;

                return `
                    <div class="cart-item mb-3 pb-3 border-bottom">
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="fw-semibold">${item.name}</div>
                                <small class="text-muted">${item.sku || ''}</small>
                                <div class="small text-success">${discountRate}% discount</div>
                            </div>
                            <button class="btn btn-sm btn-outline-danger" onclick="removeFromCart(${item.id}, '${item.discountType}')">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                        <div class="d-flex justify-content-between align-items-center mt-2">
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-secondary" onclick="updateQuantity(${item.id}, '${item.discountType}', -1)">-</button>
                                <button class="btn btn-outline-secondary" disabled>${item.quantity}</button>
                                <button class="btn btn-outline-secondary" onclick="updateQuantity(${item.id}, '${item.discountType}', 1)">+</button>
                            </div>
                            <div class="fw-bold">$${itemTotal.toFixed(2)}</div>
                        </div>
                    </div>
                `;
            }).join('');

            const total = subtotal - totalDiscount;

            document.getElementById('cartSubtotal').textContent = `$${subtotal.toFixed(2)}`;
            document.getElementById('cartDiscount').textContent = `-$${totalDiscount.toFixed(2)}`;
            document.getElementById('cartTotal').textContent = `$${total.toFixed(2)}`;
            cartFooter.style.display = 'block';
            cartCount.textContent = cart.reduce((sum, item) => sum + item.quantity, 0);
        }

        // Update item quantity
        function updateQuantity(productId, discountType, change) {
            const item = cart.find(i => i.id === productId && i.discountType === discountType);
            if (!item) return;

            item.quantity += change;

            if (item.quantity <= 0) {
                removeFromCart(productId, discountType);
            } else {
                saveCartToStorage();
                updateCartDisplay();
            }
        }

        // Remove item from cart
        function removeFromCart(productId, discountType) {
            cart = cart.filter(item => !(item.id === productId && item.discountType === discountType));
            saveCartToStorage();
            updateCartDisplay();
        }

        // Clear entire cart
        function clearCart() {
            if (confirm('Are you sure you want to clear your cart?')) {
                cart = [];
                saveCartToStorage();
                updateCartDisplay();
            }
        }

        // Save cart to localStorage
        function saveCartToStorage() {
            localStorage.setItem('staffCart', JSON.stringify(cart));
        }

        // Load cart from localStorage
        function loadCartFromStorage() {
            const stored = localStorage.getItem('staffCart');
            if (stored) {
                cart = JSON.parse(stored);
                updateCartDisplay();
            }
        }

        // Checkout
        function checkout() {
            if (cart.length === 0) {
                alert('Your cart is empty');
                return;
            }

            // Generate summary
            let subtotal = 0;
            let totalDiscount = 0;

            cart.forEach(item => {
                const discountRate = discountRates[item.discountType];
                const staffPrice = item.retailPrice * (1 - discountRate / 100);
                subtotal += item.retailPrice * item.quantity;
                totalDiscount += (item.retailPrice - staffPrice) * item.quantity;
            });

            const total = subtotal - totalDiscount;

            document.getElementById('checkoutSummary').innerHTML = `
                <div class="table-responsive">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Item</th>
                                <th>Qty</th>
                                <th>Price</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            ${cart.map(item => {
                                const discountRate = discountRates[item.discountType];
                                const staffPrice = item.retailPrice * (1 - discountRate / 100);
                                const itemTotal = staffPrice * item.quantity;
                                return `
                                    <tr>
                                        <td>${item.name}</td>
                                        <td>${item.quantity}</td>
                                        <td>$${staffPrice.toFixed(2)}</td>
                                        <td>$${itemTotal.toFixed(2)}</td>
                                    </tr>
                                `;
                            }).join('')}
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3">Subtotal:</th>
                                <th>$${subtotal.toFixed(2)}</th>
                            </tr>
                            <tr class="text-success">
                                <th colspan="3">Discount:</th>
                                <th>-$${totalDiscount.toFixed(2)}</th>
                            </tr>
                            <tr class="table-primary">
                                <th colspan="3">Total:</th>
                                <th>$${total.toFixed(2)}</th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            `;

            const modal = new bootstrap.Modal(document.getElementById('checkoutModal'));
            modal.show();
        }

        // Complete purchase
        async function completePurchase() {
            const paymentMethod = document.querySelector('input[name="paymentMethod"]:checked').value;
            const notes = document.getElementById('purchaseNotes').value;

            const purchaseData = {
                staff_id: <?= $staffId ?>,
                items: cart,
                payment_method: paymentMethod,
                notes: notes,
                discount_type: discountType
            };

            try {
                const response = await fetch('api/complete-purchase.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify(purchaseData)
                });

                const data = await response.json();

                if (data.success) {
                    // Clear cart
                    cart = [];
                    saveCartToStorage();
                    updateCartDisplay();

                    // Close modal
                    bootstrap.Modal.getInstance(document.getElementById('checkoutModal')).hide();

                    // Show success and redirect
                    alert('Purchase completed successfully! Receipt #' + data.receipt_number);
                    window.location.href = 'receipt.php?id=' + data.receipt_id;
                } else {
                    alert('Purchase failed: ' + data.message);
                }
            } catch (error) {
                alert('Error completing purchase: ' + error.message);
            }
        }

        // Mobile cart toggle
        function toggleMobileCart() {
            document.getElementById('mobileCart').classList.toggle('show');
        }

        // Render pagination
        function renderPagination(pagination) {
            const paginationEl = document.getElementById('pagination');

            if (pagination.total_pages <= 1) {
                paginationEl.innerHTML = '';
                return;
            }

            let html = '';

            // Previous button
            html += `
                <li class="page-item ${pagination.current_page === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="loadProducts(${pagination.current_page - 1}); return false;">Previous</a>
                </li>
            `;

            // Page numbers
            for (let i = 1; i <= pagination.total_pages; i++) {
                if (i === 1 || i === pagination.total_pages || (i >= pagination.current_page - 2 && i <= pagination.current_page + 2)) {
                    html += `
                        <li class="page-item ${i === pagination.current_page ? 'active' : ''}">
                            <a class="page-link" href="#" onclick="loadProducts(${i}); return false;">${i}</a>
                        </li>
                    `;
                } else if (i === pagination.current_page - 3 || i === pagination.current_page + 3) {
                    html += '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
            }

            // Next button
            html += `
                <li class="page-item ${pagination.current_page === pagination.total_pages ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="loadProducts(${pagination.current_page + 1}); return false;">Next</a>
                </li>
            `;

            paginationEl.innerHTML = html;
        }

        // Show toast notification
        function showToast(message) {
            // Create a simple toast notification
            const toast = document.createElement('div');
            toast.className = 'position-fixed bottom-0 start-50 translate-middle-x mb-3';
            toast.style.zIndex = '9999';
            toast.innerHTML = `
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle"></i> ${message}
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `;
            document.body.appendChild(toast);

            setTimeout(() => {
                toast.remove();
            }, 3000);
        }

        // Show error message
        function showError(message) {
            document.getElementById('productsGrid').innerHTML = `
                <div class="col-12">
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle"></i> ${message}
                    </div>
                </div>
            `;
        }

        // Close search results when clicking outside
        document.addEventListener('click', function(e) {
            if (!e.target.closest('.search-box')) {
                hideSearchResults();
            }
        });
    </script>
</body>
</html>
