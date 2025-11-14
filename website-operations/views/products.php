<?php
/**
 * Website Operations - Products Management View
 *
 * Complete product catalog management with inventory, pricing, and bulk operations
 *
 * @package    WebsiteOperations
 * @version    1.0.0
 * @author     Ecigdis Development Team
 * @date       2025-11-14
 */

require_once __DIR__ . '/../../includes/db_config.php';
require_once __DIR__ . '/../services/ProductManagementService.php';
require_once __DIR__ . '/../components/product-card.php';
require_once __DIR__ . '/../components/stat-widget.php';

use Modules\WebsiteOperations\Services\ProductManagementService;

// Check authentication
session_start();
if (!isset($_SESSION['user'])) {
    header('Location: /login.php');
    exit;
}

// Database connection
try {
    $db = getDBConnection();
} catch (Exception $e) {
    die('Database connection failed: ' . $e->getMessage());
}

// Initialize service
$productService = new ProductManagementService($db);

// Handle filters
$filters = [
    'category' => $_GET['category'] ?? 'all',
    'stock_status' => $_GET['stock_status'] ?? 'all',
    'search' => $_GET['search'] ?? '',
    'sort' => $_GET['sort'] ?? 'name',
    'limit' => $_GET['limit'] ?? 50,
    'page' => $_GET['page'] ?? 1
];

// Get products and stats
$products = $productService->getProducts($filters);
$stats = $productService->getProductStats();
$categories = $productService->getCategories();

$pageTitle = "Product Management";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Website Operations</title>
    
    <!-- Styles -->
    <link rel="stylesheet" href="/assets/css/bootstrap.min.css">
    <link rel="stylesheet" href="../assets/css/website-operations.css">
</head>
<body class="website-operations-module">

<!-- Header -->
<div class="webops-header">
    <div class="webops-container">
        <h1 class="webops-header-title">
            🏷️ Product Management
        </h1>
        <p class="webops-header-subtitle">
            Manage product catalog, inventory, and pricing
        </p>
        
        <nav class="webops-nav">
            <a href="dashboard.php" class="webops-nav-link">📊 Dashboard</a>
            <a href="orders.php" class="webops-nav-link">📦 Orders</a>
            <a href="products.php" class="webops-nav-link active">🏷️ Products</a>
            <a href="customers.php" class="webops-nav-link">👥 Customers</a>
            <a href="wholesale.php" class="webops-nav-link">🏢 Wholesale</a>
        </nav>
    </div>
</div>

<!-- Main Content -->
<div class="webops-container">
    
    <!-- Statistics Overview -->
    <div class="webops-mb-lg">
        <?php renderProductStats($stats); ?>
    </div>
    
    <!-- Filters and Search -->
    <div class="webops-card webops-mb-lg">
        <form method="GET" action="" class="webops-grid webops-grid-4">
            <!-- Category Filter -->
            <div class="webops-form-group">
                <label class="webops-label">Category</label>
                <select name="category" class="webops-select" onchange="this.form.submit()">
                    <option value="all" <?php echo $filters['category'] === 'all' ? 'selected' : ''; ?>>All Categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option value="<?php echo htmlspecialchars($category['slug']); ?>" 
                                <?php echo $filters['category'] === $category['slug'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['name']); ?> 
                            (<?php echo $category['count']; ?>)
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <!-- Stock Status Filter -->
            <div class="webops-form-group">
                <label class="webops-label">Stock Status</label>
                <select name="stock_status" class="webops-select" onchange="this.form.submit()">
                    <option value="all" <?php echo $filters['stock_status'] === 'all' ? 'selected' : ''; ?>>All Stock</option>
                    <option value="in_stock" <?php echo $filters['stock_status'] === 'in_stock' ? 'selected' : ''; ?>>In Stock</option>
                    <option value="low_stock" <?php echo $filters['stock_status'] === 'low_stock' ? 'selected' : ''; ?>>Low Stock</option>
                    <option value="out_of_stock" <?php echo $filters['stock_status'] === 'out_of_stock' ? 'selected' : ''; ?>>Out of Stock</option>
                </select>
            </div>
            
            <!-- Sort -->
            <div class="webops-form-group">
                <label class="webops-label">Sort By</label>
                <select name="sort" class="webops-select" onchange="this.form.submit()">
                    <option value="name" <?php echo $filters['sort'] === 'name' ? 'selected' : ''; ?>>Name A-Z</option>
                    <option value="name_desc" <?php echo $filters['sort'] === 'name_desc' ? 'selected' : ''; ?>>Name Z-A</option>
                    <option value="price_asc" <?php echo $filters['sort'] === 'price_asc' ? 'selected' : ''; ?>>Price Low-High</option>
                    <option value="price_desc" <?php echo $filters['sort'] === 'price_desc' ? 'selected' : ''; ?>>Price High-Low</option>
                    <option value="stock" <?php echo $filters['sort'] === 'stock' ? 'selected' : ''; ?>>Stock Level</option>
                </select>
            </div>
            
            <!-- Search -->
            <div class="webops-form-group">
                <label class="webops-label">Search</label>
                <div class="webops-flex webops-gap-sm">
                    <input type="text" name="search" class="webops-input" 
                           placeholder="Product name, SKU..." 
                           value="<?php echo htmlspecialchars($filters['search']); ?>">
                    <button type="submit" class="webops-btn webops-btn-primary">
                        🔍 Search
                    </button>
                </div>
            </div>
        </form>
        
        <!-- Quick Filters -->
        <div class="webops-flex webops-gap-sm webops-mt-md">
            <span class="webops-label">Quick Filters:</span>
            <a href="?stock_status=low_stock" class="webops-btn webops-btn-sm webops-btn-warning">⚠️ Low Stock</a>
            <a href="?stock_status=out_of_stock" class="webops-btn webops-btn-sm webops-btn-danger">❌ Out of Stock</a>
            <a href="?" class="webops-btn webops-btn-sm webops-btn-secondary">🔄 Clear</a>
        </div>
    </div>
    
    <!-- Bulk Actions Bar -->
    <?php renderProductBulkActions(); ?>
    
    <!-- View Toggle and Actions -->
    <div class="webops-flex webops-justify-between webops-items-center webops-mb-md">
        <div class="webops-flex webops-gap-sm">
            <button class="webops-btn webops-btn-secondary" onclick="toggleView('grid')">
                🗂️ Grid
            </button>
            <button class="webops-btn webops-btn-secondary" onclick="toggleView('table')">
                📋 Table
            </button>
        </div>
        
        <div class="webops-flex webops-gap-sm">
            <button class="webops-btn webops-btn-primary" onclick="createNewProduct()">
                ➕ New Product
            </button>
            <button class="webops-btn webops-btn-success" onclick="importProducts()">
                📥 Import
            </button>
            <button class="webops-btn webops-btn-secondary" onclick="exportProducts()">
                📤 Export
            </button>
        </div>
    </div>
    
    <!-- Products Grid View -->
    <div id="products-view-grid">
        <?php if (empty($products)): ?>
            <div class="webops-alert webops-alert-info">
                <strong>No products found</strong>
                <p>Try adjusting your filters or search terms.</p>
            </div>
        <?php else: ?>
            <?php renderProductGrid($products, 3); ?>
        <?php endif; ?>
    </div>
    
    <!-- Products Table View (hidden by default) -->
    <div id="products-view-table" class="webops-hidden">
        <?php renderProductTable($products); ?>
    </div>
    
    <!-- Pagination -->
    <?php if (count($products) >= $filters['limit']): ?>
        <div class="webops-flex webops-justify-between webops-items-center webops-mt-lg">
            <div>
                Showing <?php echo count($products); ?> products
            </div>
            <div class="webops-flex webops-gap-sm">
                <?php if ($filters['page'] > 1): ?>
                    <a href="?page=<?php echo $filters['page'] - 1; ?>&category=<?php echo $filters['category']; ?>" 
                       class="webops-btn webops-btn-secondary">
                        ← Previous
                    </a>
                <?php endif; ?>
                
                <a href="?page=<?php echo $filters['page'] + 1; ?>&category=<?php echo $filters['category']; ?>" 
                   class="webops-btn webops-btn-secondary">
                    Next →
                </a>
            </div>
        </div>
    <?php endif; ?>
    
</div>

<!-- Scripts -->
<script src="../assets/js/api-client.js"></script>
<script>
// View switching
function toggleView(viewType) {
    const gridView = document.getElementById('products-view-grid');
    const tableView = document.getElementById('products-view-table');
    
    if (viewType === 'grid') {
        gridView.classList.remove('webops-hidden');
        tableView.classList.add('webops-hidden');
    } else {
        gridView.classList.add('webops-hidden');
        tableView.classList.remove('webops-hidden');
    }
}

// Edit product
function editProduct(productId) {
    window.location.href = `product-edit.php?id=${productId}`;
}

// View product analytics
function viewProductAnalytics(productId) {
    window.location.href = `product-analytics.php?id=${productId}`;
}

// Reorder product
async function reorderProduct(productId) {
    if (!confirm('Create reorder for this product?')) {
        return;
    }
    
    try {
        await webOpsAPI.createProduct({ action: 'reorder', product_id: productId });
        webOpsAPI.showToast('Reorder created successfully', 'success');
    } catch (error) {
        webOpsAPI.showToast('Failed to create reorder: ' + error.message, 'danger');
    }
}

// Create new product
function createNewProduct() {
    window.location.href = 'product-create.php';
}

// Import products
function importProducts() {
    window.location.href = 'product-import.php';
}

// Export products
function exportProducts() {
    const params = new URLSearchParams(window.location.search);
    params.set('export', 'csv');
    window.location.href = '?' + params.toString();
}

// Bulk actions
function bulkUpdatePrice() {
    const selected = getSelectedProducts();
    if (selected.length === 0) {
        alert('Please select products first');
        return;
    }
    // TODO: Implement bulk price update
    console.log('Bulk update price for:', selected);
}

function bulkUpdateStock() {
    const selected = getSelectedProducts();
    if (selected.length === 0) {
        alert('Please select products first');
        return;
    }
    // TODO: Implement bulk stock update
    console.log('Bulk update stock for:', selected);
}

function bulkUpdateCategory() {
    const selected = getSelectedProducts();
    if (selected.length === 0) {
        alert('Please select products first');
        return;
    }
    // TODO: Implement bulk category update
    console.log('Bulk update category for:', selected);
}

function bulkDelete() {
    const selected = getSelectedProducts();
    if (selected.length === 0) {
        alert('Please select products first');
        return;
    }
    
    if (!confirm(`Delete ${selected.length} products? This cannot be undone.`)) {
        return;
    }
    
    // TODO: Implement bulk delete
    console.log('Bulk delete:', selected);
}

function clearSelection() {
    document.querySelectorAll('.product-checkbox:checked').forEach(cb => {
        cb.checked = false;
    });
    updateBulkActionsBar();
}

function getSelectedProducts() {
    return Array.from(document.querySelectorAll('.product-checkbox:checked'))
                .map(cb => cb.value);
}

function updateBulkActionsBar() {
    const selected = getSelectedProducts();
    const bar = document.getElementById('bulk-actions-bar');
    const count = document.getElementById('bulk-selected-count');
    
    if (selected.length > 0) {
        bar.style.display = 'block';
        count.textContent = `${selected.length} selected`;
    } else {
        bar.style.display = 'none';
    }
}
</script>

</body>
</html>
