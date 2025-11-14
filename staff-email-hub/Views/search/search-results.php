<!-- Search Results Page - Makes Gmail Look Like Trash -->
<?php include 'universal-search-bar.php'; ?>

<div class="search-results-container">
    <?php if ($aiMode && isset($aiExplanation)): ?>
    <!-- AI Mode Explanation -->
    <div class="ai-explanation">
        <div class="ai-explanation-header">
            🤖 AI Search Results
        </div>
        <div class="ai-explanation-text">
            <?php echo htmlspecialchars($aiExplanation); ?>
        </div>
    </div>
    <?php endif; ?>

    <!-- Results Header -->
    <div class="search-results-header">
        <div>
            <div class="search-query-display">
                Search: <mark><?php echo htmlspecialchars($query); ?></mark>
            </div>
            <div style="color: #666; margin-top: 5px;">
                Found <?php echo number_format($totalResults); ?> results in <?php echo $responseTime; ?>ms
            </div>
        </div>
        <div class="search-actions">
            <button class="action-btn" onclick="window.print()">📄 Export</button>
            <button class="action-btn" onclick="toggleFilters()">⚙️ Filters</button>
            <button class="action-btn" onclick="toggleSort()">↕️ Sort</button>
        </div>
    </div>

    <?php if (!empty($results['emails'])): ?>
    <!-- Email Results Section -->
    <div class="results-section">
        <div class="section-header">
            <div class="section-title">
                <span>📧 Emails</span>
                <span class="section-count"><?php echo count($results['emails']); ?></span>
            </div>
            <a href="/search/emails?q=<?php echo urlencode($query); ?>" class="view-all-link">
                View All →
            </a>
        </div>
        <?php foreach (array_slice($results['emails'], 0, 5) as $email): ?>
        <div class="result-item" onclick="window.location='/emails/<?php echo $email['id']; ?>'">
            <div class="result-header">
                <div>
                    <div class="result-title">
                        <?php if ($email['priority_level'] === 'urgent'): ?>
                            🔴
                        <?php endif; ?>
                        <?php echo htmlspecialchars($email['subject']); ?>
                    </div>
                    <div class="result-meta">
                        <span>From: <?php echo htmlspecialchars($email['from_name'] ?? $email['from_email']); ?></span>
                        <span><?php echo date('M j, Y g:ia', strtotime($email['received_at'])); ?></span>
                        <?php if ($email['attachment_count'] > 0): ?>
                            <span>📎 <?php echo $email['attachment_count']; ?> attachments</span>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <div class="result-snippet">
                <?php echo $email['highlighted_text']['body'] ?? substr(strip_tags($email['body']), 0, 200) . '...'; ?>
            </div>
            <div class="result-actions">
                <button class="result-action-btn" onclick="event.stopPropagation(); replyEmail(<?php echo $email['id']; ?>)">
                    ↩️ Reply
                </button>
                <button class="result-action-btn" onclick="event.stopPropagation(); viewThread(<?php echo $email['conversation_id']; ?>)">
                    💬 View Thread (<?php echo count($email['conversation_thread']); ?>)
                </button>
                <button class="result-action-btn" onclick="event.stopPropagation(); archiveEmail(<?php echo $email['id']; ?>)">
                    📥 Archive
                </button>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($results['products'])): ?>
    <!-- Product Results Section -->
    <div class="results-section">
        <div class="section-header">
            <div class="section-title">
                <span>📦 Products</span>
                <span class="section-count"><?php echo count($results['products']); ?></span>
            </div>
            <a href="/search/products?q=<?php echo urlencode($query); ?>" class="view-all-link">
                View All →
            </a>
        </div>
        <?php foreach (array_slice($results['products'], 0, 5) as $product): ?>
        <div class="result-item" onclick="window.location='/products/<?php echo $product['id']; ?>'">
            <div class="result-header">
                <div>
                    <div class="result-title">
                        <?php echo htmlspecialchars($product['name']); ?>
                    </div>
                    <div class="result-meta">
                        <span>SKU: <?php echo $product['sku']; ?></span>
                        <span>Supplier: <?php echo htmlspecialchars($product['supplier_name']); ?></span>
                        <span class="stock-badge <?php echo $product['stock_status']; ?>">
                            <?php if ($product['stock_status'] === 'in_stock'): ?>
                                ✅ In Stock (<?php echo $product['stock_level']; ?>)
                            <?php elseif ($product['stock_status'] === 'low_stock'): ?>
                                ⚠️ Low Stock (<?php echo $product['stock_level']; ?>)
                            <?php else: ?>
                                ❌ Out of Stock
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
                <div style="font-size: 20px; font-weight: 600; color: #667eea;">
                    $<?php echo number_format($product['price'], 2); ?>
                </div>
            </div>
            <div class="result-snippet">
                <?php echo htmlspecialchars(substr($product['description'], 0, 150)) . '...'; ?>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($results['orders'])): ?>
    <!-- Order Results Section -->
    <div class="results-section">
        <div class="section-header">
            <div class="section-title">
                <span>🛒 Orders</span>
                <span class="section-count"><?php echo count($results['orders']); ?></span>
            </div>
            <a href="/search/orders?q=<?php echo urlencode($query); ?>" class="view-all-link">
                View All →
            </a>
        </div>
        <?php foreach (array_slice($results['orders'], 0, 5) as $order): ?>
        <div class="result-item" onclick="window.location='/orders/<?php echo $order['id']; ?>'">
            <div class="result-header">
                <div>
                    <div class="result-title">
                        Order #<?php echo $order['order_number']; ?>
                        <span class="status-badge <?php echo $order['status']; ?>">
                            <?php echo ucfirst($order['status']); ?>
                        </span>
                    </div>
                    <div class="result-meta">
                        <span>Customer: <?php echo htmlspecialchars($order['customer_name']); ?></span>
                        <span><?php echo date('M j, Y', strtotime($order['created_at'])); ?></span>
                        <span><?php echo $order['item_count']; ?> items</span>
                    </div>
                </div>
                <div style="font-size: 18px; font-weight: 600; color: #28a745;">
                    $<?php echo number_format($order['order_total'], 2); ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (!empty($results['customers'])): ?>
    <!-- Customer Results Section -->
    <div class="results-section">
        <div class="section-header">
            <div class="section-title">
                <span>👥 Customers</span>
                <span class="section-count"><?php echo count($results['customers']); ?></span>
            </div>
            <a href="/search/customers?q=<?php echo urlencode($query); ?>" class="view-all-link">
                View All →
            </a>
        </div>
        <?php foreach (array_slice($results['customers'], 0, 5) as $customer): ?>
        <div class="result-item" onclick="window.location='/customers/<?php echo $customer['id']; ?>'">
            <div class="result-header">
                <div>
                    <div class="result-title">
                        <?php echo htmlspecialchars($customer['name']); ?>
                        <span class="segment-badge <?php echo $customer['segment']; ?>">
                            <?php echo strtoupper($customer['segment']); ?>
                        </span>
                    </div>
                    <div class="result-meta">
                        <span><?php echo htmlspecialchars($customer['email']); ?></span>
                        <span><?php echo htmlspecialchars($customer['phone'] ?? 'No phone'); ?></span>
                        <span><?php echo $customer['order_count']; ?> orders</span>
                    </div>
                </div>
                <div style="font-size: 16px; font-weight: 600; color: #667eea;">
                    LTV: $<?php echo number_format($customer['lifetime_value'], 2); ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if (empty($results) || $totalResults === 0): ?>
    <!-- No Results -->
    <div style="text-align: center; padding: 60px 20px; background: white; border-radius: 15px;">
        <div style="font-size: 64px; margin-bottom: 20px;">🔍</div>
        <h2 style="color: #333; margin-bottom: 10px;">No results found</h2>
        <p style="color: #666;">Try different keywords or use AI Mode for better results</p>
        <button class="action-btn" style="margin-top: 20px;" onclick="document.getElementById('aiModeToggle').click()">
            🤖 Try AI Mode
        </button>
    </div>
    <?php endif; ?>
</div>

<style>
.status-badge, .segment-badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    margin-left: 8px;
}

.status-badge.pending { background: #fff3cd; color: #856404; }
.status-badge.completed { background: #d4edda; color: #155724; }
.status-badge.cancelled { background: #f8d7da; color: #721c24; }

.segment-badge.vip { background: #ffd700; color: #8b6914; }
.segment-badge.regular { background: #d4edda; color: #155724; }
.segment-badge.occasional { background: #d1ecf1; color: #0c5460; }
.segment-badge.prospect { background: #f8d7da; color: #721c24; }

.stock-badge {
    font-weight: 600;
}
</style>
