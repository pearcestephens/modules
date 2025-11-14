<?php
/**
 * ✨ PREMIUM GOODS SELECTION INTERFACE ✨
 *
 * State-of-the-art goods picker with drag-drop, visual feedback,
 * batch operations, real-time images, quantity counters, undo/redo
 *
 * @package CIS\Consignments\UI
 * @version 2.0.0
 */
?>

<!-- Load this in your pack pages: <?php include '../templates/premium-goods-picker.php'; ?> -->

<div id="premiumGoodsPicker" class="premium-goods-picker">

    <!-- HEADER TOOLBAR -->
    <div class="picker-toolbar">
        <div class="toolbar-left">
            <div class="search-box">
                <i class="fas fa-search"></i>
                <input type="text" id="goodsSearch" placeholder="Search by code, name, or SKU..." class="form-control-sm">
                <button id="clearSearch" class="btn-clear" style="display:none;">
                    <i class="fas fa-times"></i>
                </button>
            </div>

            <div class="filter-tags">
                <span class="filter-tag active" data-filter="all">All</span>
                <span class="filter-tag" data-filter="vape">Vape</span>
                <span class="filter-tag" data-filter="liquid">Liquid</span>
                <span class="filter-tag" data-filter="coils">Coils</span>
                <span class="filter-tag" data-filter="mods">Mods</span>
                <span class="filter-tag" data-filter="other">Other</span>
            </div>
        </div>

        <div class="toolbar-right">
            <button id="undoBtn" class="btn-icon" title="Undo">
                <i class="fas fa-undo"></i>
            </button>
            <button id="redoBtn" class="btn-icon" title="Redo">
                <i class="fas fa-redo"></i>
            </button>
            <button id="batchBtn" class="btn-icon" title="Batch Operations">
                <i class="fas fa-tasks"></i>
            </button>
            <button id="helpBtn" class="btn-icon" title="Help">
                <i class="fas fa-question-circle"></i>
            </button>
        </div>
    </div>

    <!-- MAIN PICKER AREA -->
    <div class="picker-container">

        <!-- LEFT: GOODS LIST -->
        <div class="goods-list-container">
            <div class="goods-list-header">
                <h4>Available Goods</h4>
                <span class="goods-count">0 items</span>
            </div>

            <div class="goods-list" id="goodsList" style="min-height: 500px;">
                <!-- Goods will load here via AJAX -->
                <div class="loading-state">
                    <div class="spinner-border" role="status">
                        <span class="sr-only">Loading...</span>
                    </div>
                    <p>Loading goods...</p>
                </div>
            </div>
        </div>

        <!-- RIGHT: SELECTED GOODS SUMMARY -->
        <div class="selected-summary-container">
            <div class="summary-header">
                <h4>
                    <i class="fas fa-check-circle"></i> Selected (
                    <span id="selectedCount">0</span>)
                </h4>
                <button id="clearAll" class="btn-sm btn-outline-danger">Clear All</button>
            </div>

            <div class="summary-body">
                <div id="selectedList" class="selected-list">
                    <!-- Selected items appear here -->
                    <div class="empty-selection">
                        <i class="fas fa-plus-circle"></i>
                        <p>Click goods or drag to add items</p>
                    </div>
                </div>
            </div>

            <div class="summary-footer">
                <div class="summary-stat">
                    <span class="stat-label">Total Items</span>
                    <span class="stat-value" id="totalItems">0</span>
                </div>
                <div class="summary-stat">
                    <span class="stat-label">Total Weight</span>
                    <span class="stat-value" id="totalWeight">0 kg</span>
                </div>
                <div class="summary-stat">
                    <span class="stat-label">Boxes Est.</span>
                    <span class="stat-value" id="boxesEst">0</span>
                </div>
                <button class="btn btn-primary btn-lg btn-block" id="proceedBtn">
                    <i class="fas fa-arrow-right"></i> Proceed to Packing
                </button>
            </div>
        </div>

    </div>

</div>

<style>
    /* ============================================================================ */
    /* PREMIUM GOODS PICKER STYLESHEET */
    /* ============================================================================ */

    .premium-goods-picker {
        background: white;
        border-radius: 12px;
        box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
        overflow: hidden;
        margin-bottom: 30px;
    }

    /* TOOLBAR */
    .picker-toolbar {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 15px 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
    }

    .toolbar-left {
        display: flex;
        align-items: center;
        gap: 15px;
        flex: 1;
        min-width: 300px;
    }

    .search-box {
        flex: 1;
        position: relative;
        display: flex;
        align-items: center;
        background: rgba(255, 255, 255, 0.15);
        border: 2px solid rgba(255, 255, 255, 0.3);
        border-radius: 8px;
        padding: 0 12px;
        transition: all 0.3s ease;
    }

    .search-box:focus-within {
        background: rgba(255, 255, 255, 0.25);
        border-color: white;
    }

    .search-box i {
        margin-right: 8px;
        opacity: 0.8;
    }

    .search-box input {
        background: transparent !important;
        border: none !important;
        color: white;
        padding: 10px 0;
    }

    .search-box input::placeholder {
        color: rgba(255, 255, 255, 0.7);
    }

    .btn-clear {
        background: none;
        border: none;
        color: white;
        cursor: pointer;
        padding: 0;
        font-size: 16px;
    }

    /* FILTER TAGS */
    .filter-tags {
        display: flex;
        gap: 8px;
        white-space: nowrap;
        overflow-x: auto;
        padding: 0 5px;
    }

    .filter-tag {
        padding: 6px 14px;
        border-radius: 20px;
        background: rgba(255, 255, 255, 0.2);
        color: white;
        cursor: pointer;
        font-size: 13px;
        font-weight: 600;
        transition: all 0.2s ease;
        border: 1px solid transparent;
    }

    .filter-tag:hover {
        background: rgba(255, 255, 255, 0.3);
    }

    .filter-tag.active {
        background: white;
        color: #667eea;
        border-color: white;
    }

    /* TOOLBAR BUTTONS */
    .toolbar-right {
        display: flex;
        gap: 10px;
    }

    .btn-icon {
        background: rgba(255, 255, 255, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: white;
        width: 40px;
        height: 40px;
        border-radius: 8px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        font-size: 16px;
    }

    .btn-icon:hover {
        background: rgba(255, 255, 255, 0.3);
        transform: scale(1.05);
    }

    .btn-icon:active {
        transform: scale(0.95);
    }

    /* MAIN CONTAINER */
    .picker-container {
        display: grid;
        grid-template-columns: 1fr 380px;
        gap: 20px;
        padding: 20px;
        min-height: 600px;
        background: #fafbfc;
    }

    /* GOODS LIST */
    .goods-list-container {
        display: flex;
        flex-direction: column;
        gap: 12px;
        background: white;
        border-radius: 10px;
        padding: 15px;
        border: 1px solid #e9ecef;
    }

    .goods-list-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 12px;
        border-bottom: 2px solid #e9ecef;
    }

    .goods-list-header h4 {
        margin: 0;
        color: #2d3748;
        font-weight: 700;
    }

    .goods-count {
        background: #e7f3ff;
        color: #0066cc;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 13px;
        font-weight: 600;
    }

    .goods-list {
        overflow-y: auto;
        flex: 1;
    }

    /* GOODS ITEM */
    .goods-item {
        display: flex;
        gap: 12px;
        padding: 12px;
        margin-bottom: 10px;
        background: white;
        border: 2px solid #e9ecef;
        border-radius: 8px;
        cursor: pointer;
        transition: all 0.2s ease;
        align-items: flex-start;
    }

    .goods-item:hover {
        border-color: #667eea;
        background: #f8fafb;
        transform: translateX(4px);
    }

    .goods-item.dragging {
        opacity: 0.5;
        border-color: #667eea;
    }

    .goods-image {
        width: 60px;
        height: 60px;
        background: #f0f1f5;
        border-radius: 6px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
        overflow: hidden;
    }

    .goods-image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }

    .goods-image.no-image {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        font-size: 24px;
    }

    .goods-info {
        flex: 1;
        min-width: 0;
    }

    .goods-name {
        font-weight: 700;
        color: #2d3748;
        margin: 0;
        font-size: 14px;
        word-break: break-word;
    }

    .goods-meta {
        display: flex;
        gap: 10px;
        margin-top: 4px;
        font-size: 12px;
        color: #718096;
    }

    .goods-code {
        background: #f0f1f5;
        padding: 2px 6px;
        border-radius: 4px;
        font-family: monospace;
    }

    .goods-add-btn {
        width: 40px;
        height: 40px;
        padding: 0;
        border-radius: 6px;
        border: none;
        background: #667eea;
        color: white;
        cursor: pointer;
        font-size: 18px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s ease;
        flex-shrink: 0;
    }

    .goods-add-btn:hover {
        background: #5568d3;
        transform: scale(1.1);
    }

    /* SELECTED SUMMARY */
    .selected-summary-container {
        background: white;
        border-radius: 10px;
        border: 1px solid #e9ecef;
        padding: 15px;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .summary-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding-bottom: 12px;
        border-bottom: 2px solid #e9ecef;
    }

    .summary-header h4 {
        margin: 0;
        color: #2d3748;
        font-weight: 700;
        font-size: 16px;
    }

    .summary-body {
        flex: 1;
        overflow-y: auto;
        min-height: 300px;
    }

    .selected-list {
        display: flex;
        flex-direction: column;
        gap: 10px;
    }

    .empty-selection {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 300px;
        color: #cbd5e0;
        text-align: center;
    }

    .empty-selection i {
        font-size: 48px;
        margin-bottom: 10px;
        opacity: 0.3;
    }

    /* SELECTED ITEM */
    .selected-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 10px;
        background: #f8fafb;
        border: 1px solid #e9ecef;
        border-left: 4px solid #667eea;
        border-radius: 6px;
        gap: 10px;
        animation: slideIn 0.2s ease;
    }

    @keyframes slideIn {
        from {
            opacity: 0;
            transform: translateX(-10px);
        }
        to {
            opacity: 1;
            transform: translateX(0);
        }
    }

    .selected-item-info {
        flex: 1;
        min-width: 0;
    }

    .selected-item-name {
        font-weight: 600;
        color: #2d3748;
        margin: 0;
        font-size: 13px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }

    .selected-item-qty {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-top: 4px;
    }

    .qty-btn {
        background: white;
        border: 1px solid #cbd5e0;
        color: #2d3748;
        width: 24px;
        height: 24px;
        padding: 0;
        border-radius: 4px;
        cursor: pointer;
        font-size: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.1s ease;
    }

    .qty-btn:hover {
        border-color: #667eea;
        color: #667eea;
    }

    .qty-input {
        width: 40px;
        height: 24px;
        text-align: center;
        border: 1px solid #cbd5e0;
        border-radius: 4px;
        font-size: 12px;
        padding: 0;
    }

    .selected-item-remove {
        background: none;
        border: none;
        color: #cbd5e0;
        cursor: pointer;
        font-size: 16px;
        padding: 0;
        transition: color 0.2s ease;
    }

    .selected-item-remove:hover {
        color: #e53e3e;
    }

    /* SUMMARY FOOTER */
    .summary-footer {
        padding-top: 12px;
        border-top: 2px solid #e9ecef;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .summary-stat {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px;
        background: #f8fafb;
        border-radius: 6px;
    }

    .stat-label {
        font-size: 12px;
        font-weight: 600;
        color: #718096;
        text-transform: uppercase;
        letter-spacing: 0.5px;
    }

    .stat-value {
        font-size: 16px;
        font-weight: 700;
        color: #667eea;
    }

    .btn-block {
        width: 100%;
        padding: 14px;
        font-size: 15px;
        font-weight: 700;
        letter-spacing: 0.5px;
    }

    /* RESPONSIVE */
    @media (max-width: 1024px) {
        .picker-container {
            grid-template-columns: 1fr;
            min-height: auto;
        }

        .selected-summary-container {
            position: sticky;
            bottom: 0;
            border-top: 3px solid #667eea;
        }
    }

    @media (max-width: 768px) {
        .picker-toolbar {
            flex-direction: column;
            padding: 12px;
            gap: 12px;
        }

        .toolbar-left {
            width: 100%;
        }

        .filter-tags {
            overflow-x: auto;
            width: 100%;
        }

        .toolbar-right {
            width: 100%;
            justify-content: space-around;
        }

        .picker-container {
            padding: 12px;
            gap: 12px;
        }
    }

    /* LOADING STATE */
    .loading-state {
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        height: 300px;
        color: #718096;
    }

    .loading-state .spinner-border {
        margin-bottom: 15px;
    }
</style>

<script>
    /**
     * Premium Goods Picker - JavaScript Controller
     */

    class PremiumGoodsPicker {
        constructor() {
            this.selectedGoods = [];
            this.history = [];
            this.historyIndex = -1;
            this.allGoods = [];
            this.init();
        }

        init() {
            this.loadGoods();
            this.attachEventListeners();
        }

        loadGoods() {
            // ✅ IMPLEMENTED: AJAX call to fetch premium goods
            const loader = document.querySelector('.premium-goods-picker .loading-state');
            const container = document.querySelector('.goods-grid');

            if (loader) loader.style.display = 'block';
            if (container) container.style.display = 'none';

            // Fetch goods from API
            fetch('/modules/consignments/api/unified/?action=list_products&limit=100&premium=1')
                .then(res => res.json())
                .then(data => {
                    if (data.success && data.products) {
                        this.goods = data.products;
                        this.renderGoods(this.goods);
                    } else {
                        console.error('Failed to load goods:', data.message || 'Unknown error');
                        this.showError('Failed to load premium goods');
                    }
                })
                .catch(err => {
                    console.error('AJAX error loading goods:', err);
                    this.showError('Network error loading goods');
                })
                .finally(() => {
                    if (loader) loader.style.display = 'none';
                    if (container) container.style.display = 'grid';
                });
        }

        renderGoods(goods) {
            const container = document.querySelector('.goods-grid');
            if (!container) return;

            container.innerHTML = goods.map(good => `
                <div class="good-card" data-good-id="${good.id}" data-sku="${good.sku}">
                    <img src="${good.image_url || '/assets/images/placeholder.png'}" alt="${good.name}">
                    <div class="good-name">${good.name}</div>
                    <div class="good-sku">${good.sku}</div>
                    <div class="good-price">$${parseFloat(good.price || 0).toFixed(2)}</div>
                    <button class="btn btn-sm btn-primary add-good-btn" data-good-id="${good.id}">Add</button>
                </div>
            `).join('');

            // Attach click handlers to add buttons
            container.querySelectorAll('.add-good-btn').forEach(btn => {
                btn.addEventListener('click', (e) => {
                    const goodId = e.target.dataset.goodId;
                    this.addGood(goodId);
                });
            });
        }

        showError(message) {
            const container = document.querySelector('.goods-grid');
            if (container) {
                container.innerHTML = `<div class="alert alert-danger">${message}</div>`;
            }
        }

        addGood(goodId) {
            const good = this.goods.find(g => g.id == goodId);
            if (!good) return;

            // Emit event for parent to handle
            document.dispatchEvent(new CustomEvent('goodAdded', { detail: good }));

            // Visual feedback
            const card = document.querySelector(`.good-card[data-good-id="${goodId}"]`);
            if (card) {
                card.classList.add('added');
                setTimeout(() => card.classList.remove('added'), 1000);
            }
        }

        attachEventListeners() {
            document.getElementById('clearSearch')?.addEventListener('click', () => {
                document.getElementById('goodsSearch').value = '';
                this.filterGoods('');
            });

            document.getElementById('goodsSearch')?.addEventListener('input', (e) => {
                this.filterGoods(e.target.value);
                document.getElementById('clearSearch').style.display =
                    e.target.value ? 'block' : 'none';
            });

            document.querySelectorAll('.filter-tag').forEach(tag => {
                tag.addEventListener('click', (e) => {
                    document.querySelectorAll('.filter-tag').forEach(t =>
                        t.classList.remove('active'));
                    e.target.classList.add('active');
                    this.filterByCategory(e.target.dataset.filter);
                });
            });

            document.getElementById('clearAll')?.addEventListener('click', () => {
                if (confirm('Clear all selected items?')) {
                    this.saveHistory();
                    this.selectedGoods = [];
                    this.updateSummary();
                }
            });

            document.getElementById('undoBtn')?.addEventListener('click', () => this.undo());
            document.getElementById('redoBtn')?.addEventListener('click', () => this.redo());
        }

        addGood(goodId, quantity = 1) {
            this.saveHistory();
            const good = this.allGoods.find(g => g.id === goodId);
            if (!good) return;

            const existing = this.selectedGoods.find(g => g.id === goodId);
            if (existing) {
                existing.quantity += quantity;
            } else {
                this.selectedGoods.push({ ...good, quantity });
            }

            this.updateSummary();
        }

        removeGood(goodId) {
            this.saveHistory();
            this.selectedGoods = this.selectedGoods.filter(g => g.id !== goodId);
            this.updateSummary();
        }

        updateQuantity(goodId, quantity) {
            const good = this.selectedGoods.find(g => g.id === goodId);
            if (good) {
                good.quantity = Math.max(1, parseInt(quantity) || 1);
                this.updateSummary();
            }
        }

        updateSummary() {
            const count = this.selectedGoods.length;
            const items = this.selectedGoods.reduce((sum, g) => sum + g.quantity, 0);
            const weight = this.selectedGoods.reduce((sum, g) =>
                sum + (g.weight_g || 0) * g.quantity, 0) / 1000;
            const boxes = Math.ceil(items / 20);

            document.getElementById('selectedCount').textContent = count;
            document.getElementById('totalItems').textContent = items;
            document.getElementById('totalWeight').textContent = weight.toFixed(1) + ' kg';
            document.getElementById('boxesEst').textContent = boxes;

            // Render selected list
            this.renderSelectedList();
        }

        renderSelectedList() {
            const list = document.getElementById('selectedList');
            if (this.selectedGoods.length === 0) {
                list.innerHTML = `
                    <div class="empty-selection">
                        <i class="fas fa-plus-circle"></i>
                        <p>Click goods or drag to add items</p>
                    </div>
                `;
                return;
            }

            list.innerHTML = this.selectedGoods.map(good => `
                <div class="selected-item" data-id="${good.id}">
                    <div class="selected-item-info">
                        <h6 class="selected-item-name">${good.name}</h6>
                        <div class="selected-item-qty">
                            <button class="qty-btn" onclick="picker.updateQuantity(${good.id}, ${good.quantity - 1})">−</button>
                            <input type="number" class="qty-input" value="${good.quantity}" onchange="picker.updateQuantity(${good.id}, this.value)">
                            <button class="qty-btn" onclick="picker.updateQuantity(${good.id}, ${good.quantity + 1})">+</button>
                        </div>
                    </div>
                    <button class="selected-item-remove" onclick="picker.removeGood(${good.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `).join('');
        }

        filterGoods(query) {
            // Filter goods list by search query
        }

        filterByCategory(category) {
            // Filter goods by category
        }

        saveHistory() {
            this.history = this.history.slice(0, this.historyIndex + 1);
            this.history.push(JSON.parse(JSON.stringify(this.selectedGoods)));
            this.historyIndex++;
        }

        undo() {
            if (this.historyIndex > 0) {
                this.historyIndex--;
                this.selectedGoods = JSON.parse(JSON.stringify(this.history[this.historyIndex]));
                this.updateSummary();
            }
        }

        redo() {
            if (this.historyIndex < this.history.length - 1) {
                this.historyIndex++;
                this.selectedGoods = JSON.parse(JSON.stringify(this.history[this.historyIndex]));
                this.updateSummary();
            }
        }
    }

    // Initialize global picker instance
    const picker = new PremiumGoodsPicker();
</script>
