<?php
/**
 * Layout Comparison Page - Choose Your Preferred Design
 * Compare all three layout variations side-by-side
 */

require_once __DIR__ . '/../../base/_templates/themes/cis-classic/theme.php';

$theme = new CISClassicTheme();
$theme->setTitle('Advanced Pack Page - Layout Comparison');
$theme->setPageSubtitle('Choose Your Preferred Design');
$theme->showTimestamps = false;

$theme->addBreadcrumb('Consignments', '/modules/consignments/');
$theme->addBreadcrumb('Stock Transfers', '/modules/consignments/stock-transfers/');
$theme->addBreadcrumb('Layout Comparison', null);
?>

<?php $theme->render('html-head'); ?>
<?php $theme->render('header'); ?>
<?php $theme->render('sidebar'); ?>
<?php $theme->render('main-start'); ?>

<style>
.comparison-container {
    margin-top: 20px;
}

.intro-section {
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    color: #fff;
    padding: 40px;
    border-radius: 12px;
    margin-bottom: 30px;
    text-align: center;
}

.intro-section h2 {
    margin: 0 0 15px 0;
    font-size: 32px;
}

.intro-section p {
    font-size: 18px;
    opacity: 0.95;
    margin: 0;
}

.layouts-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(400px, 1fr));
    gap: 30px;
    margin-bottom: 40px;
}

.layout-card {
    background: #fff;
    border-radius: 12px;
    overflow: hidden;
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
    transition: all 0.3s;
    border: 3px solid transparent;
}

.layout-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 24px rgba(102, 126, 234, 0.3);
    border-color: #667eea;
}

.layout-preview {
    position: relative;
    height: 300px;
    background: #f8f9fa;
    overflow: hidden;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    transition: all 0.3s;
}

.layout-preview:hover {
    background: #e9ecef;
}

.layout-badge {
    position: absolute;
    top: 15px;
    right: 15px;
    background: rgba(102, 126, 234, 0.95);
    color: #fff;
    padding: 8px 15px;
    border-radius: 20px;
    font-weight: bold;
    font-size: 13px;
}

.layout-info {
    padding: 25px;
}

.layout-title {
    font-size: 24px;
    font-weight: bold;
    margin: 0 0 10px 0;
    color: #333;
}

.layout-subtitle {
    font-size: 14px;
    color: #667eea;
    font-weight: 600;
    margin-bottom: 15px;
}

.layout-description {
    color: #6c757d;
    font-size: 14px;
    line-height: 1.6;
    margin-bottom: 15px;
}

.layout-features {
    list-style: none;
    padding: 0;
    margin: 0 0 20px 0;
}

.layout-features li {
    padding: 8px 0;
    font-size: 14px;
    color: #333;
}

.layout-features li i {
    color: #28a745;
    margin-right: 8px;
}

.layout-pros-cons {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 15px;
    margin-bottom: 20px;
}

.pros, .cons {
    font-size: 13px;
}

.pros h6 {
    color: #28a745;
    font-size: 12px;
    text-transform: uppercase;
    margin-bottom: 8px;
}

.cons h6 {
    color: #dc3545;
    font-size: 12px;
    text-transform: uppercase;
    margin-bottom: 8px;
}

.pros ul, .cons ul {
    list-style: none;
    padding: 0;
    margin: 0;
}

.pros li, .cons li {
    padding: 3px 0;
    font-size: 13px;
}

.pros li:before {
    content: "✓ ";
    color: #28a745;
    font-weight: bold;
}

.cons li:before {
    content: "✗ ";
    color: #dc3545;
    font-weight: bold;
}

.layout-actions {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 10px;
}

.btn-view {
    padding: 12px 20px;
    font-weight: bold;
}

.comparison-table {
    background: #fff;
    border-radius: 12px;
    padding: 30px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
    margin-bottom: 30px;
}

.comparison-table h3 {
    margin-bottom: 20px;
    color: #333;
}

.comparison-table table {
    width: 100%;
    border-collapse: collapse;
}

.comparison-table th {
    background: #f8f9fa;
    padding: 15px;
    text-align: left;
    border-bottom: 2px solid #dee2e6;
    font-size: 14px;
}

.comparison-table td {
    padding: 15px;
    border-bottom: 1px solid #f0f0f0;
    font-size: 14px;
}

.feature-check {
    color: #28a745;
    font-size: 18px;
}

.feature-cross {
    color: #dc3545;
    font-size: 18px;
}

.recommendation {
    background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
    color: #fff;
    padding: 30px;
    border-radius: 12px;
    text-align: center;
}

.recommendation h3 {
    margin: 0 0 15px 0;
    font-size: 28px;
}

.recommendation p {
    font-size: 16px;
    margin: 0 0 20px 0;
    opacity: 0.95;
}

.recommendation .btn {
    font-size: 18px;
    padding: 15px 40px;
}
</style>

<div class="comparison-container">
    <!-- Intro Section -->
    <div class="intro-section">
        <h2><i class="fa fa-rocket"></i> Advanced Transfer Packing - Layout Options</h2>
        <p>Three carefully designed layouts for maximum productivity. Choose the one that fits your workflow best.</p>
    </div>

    <!-- Layouts Grid -->
    <div class="layouts-grid">
        <!-- Layout A -->
        <div class="layout-card">
            <div class="layout-preview" onclick="window.open('pack-advanced-layout-a.php', '_blank')" style="background: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjMwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iNDAwIiBoZWlnaHQ9IjMwMCIgZmlsbD0iI2Y4ZjlmYSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMjQiIGZpbGw9IiM2NjdlZWEiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5DbGljayB0byBWaWV3IExheW91dCBBPC90ZXh0Pjwvc3ZnPg==') center/cover;">
                <div class="layout-badge">LAYOUT A</div>
            </div>
            <div class="layout-info">
                <div class="layout-title">Two Column Split</div>
                <div class="layout-subtitle">Products Left, Console Right</div>
                <div class="layout-description">
                    Classic pack-pro.php style with 70/30 split. Product table on the left, freight console and tools on the right sidebar.
                </div>

                <div class="layout-features">
                    <li><i class="fa fa-check-circle"></i> Hero search with gradient background</li>
                    <li><i class="fa fa-check-circle"></i> Sticky freight console (always visible)</li>
                    <li><i class="fa fa-check-circle"></i> Color-coded product rows</li>
                    <li><i class="fa fa-check-circle"></i> Dedicated productivity tools panel</li>
                </div>

                <div class="layout-pros-cons">
                    <div class="pros">
                        <h6>Pros</h6>
                        <ul>
                            <li>Familiar layout</li>
                            <li>All info visible</li>
                            <li>Easy freight access</li>
                            <li>Good for large screens</li>
                        </ul>
                    </div>
                    <div class="cons">
                        <h6>Cons</h6>
                        <ul>
                            <li>Narrow table on small screens</li>
                            <li>Less space for products</li>
                            <li>Fixed sidebar width</li>
                        </ul>
                    </div>
                </div>

                <div class="layout-actions">
                    <a href="pack-advanced-layout-a.php" target="_blank" class="btn btn-primary btn-view">
                        <i class="fa fa-external-link-alt"></i> View
                    </a>
                    <button class="btn btn-success btn-view" onclick="selectLayout('A')">
                        <i class="fa fa-check"></i> Choose
                    </button>
                </div>
            </div>
        </div>

        <!-- Layout B -->
        <div class="layout-card">
            <div class="layout-preview" onclick="window.open('pack-advanced-layout-b.php', '_blank')" style="background: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjMwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iNDAwIiBoZWlnaHQ9IjMwMCIgZmlsbD0iI2Y4ZjlmYSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMjQiIGZpbGw9IiM2NjdlZWEiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5DbGljayB0byBWaWV3IExheW91dCBCPC90ZXh0Pjwvc3ZnPg==') center/cover;">
                <div class="layout-badge">LAYOUT B</div>
            </div>
            <div class="layout-info">
                <div class="layout-title">Horizontal Tabs</div>
                <div class="layout-subtitle">Wizard-Style Navigation</div>
                <div class="layout-description">
                    Dashboard-style with tab navigation. Progress bar at top, tab switcher for products/freight/tools sections.
                </div>

                <div class="layout-features">
                    <li><i class="fa fa-check-circle"></i> Full-width progress hero</li>
                    <li><i class="fa fa-check-circle"></i> Tab-based organization</li>
                    <li><i class="fa fa-check-circle"></i> Product grid cards</li>
                    <li><i class="fa fa-check-circle"></i> Clean, modern aesthetic</li>
                </div>

                <div class="layout-pros-cons">
                    <div class="pros">
                        <h6>Pros</h6>
                        <ul>
                            <li>Organized workflow</li>
                            <li>Full-width products</li>
                            <li>Beautiful product cards</li>
                            <li>Easy navigation</li>
                        </ul>
                    </div>
                    <div class="cons">
                        <h6>Cons</h6>
                        <ul>
                            <li>Tab switching needed</li>
                            <li>Can't see all at once</li>
                            <li>More clicks required</li>
                        </ul>
                    </div>
                </div>

                <div class="layout-actions">
                    <a href="pack-advanced-layout-b.php" target="_blank" class="btn btn-primary btn-view">
                        <i class="fa fa-external-link-alt"></i> View
                    </a>
                    <button class="btn btn-success btn-view" onclick="selectLayout('B')">
                        <i class="fa fa-check"></i> Choose
                    </button>
                </div>
            </div>
        </div>

        <!-- Layout C -->
        <div class="layout-card">
            <div class="layout-preview" onclick="window.open('pack-advanced-layout-c.php', '_blank')" style="background: url('data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iNDAwIiBoZWlnaHQ9IjMwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iNDAwIiBoZWlnaHQ9IjMwMCIgZmlsbD0iI2Y4ZjlmYSIvPjx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMjQiIGZpbGw9IiM2NjdlZWEiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGR5PSIuM2VtIj5DbGljayB0byBWaWV3IExheW91dCBDPC90ZXh0Pjwvc3ZnPg==') center/cover;">
                <div class="layout-badge">LAYOUT C</div>
            </div>
            <div class="layout-info">
                <div class="layout-title">Compact Dashboard</div>
                <div class="layout-subtitle">Everything Visible with Collapsible Panels</div>
                <div class="layout-description">
                    Space-efficient with collapsible accordion panels and floating freight action bar at bottom. Best of both worlds.
                </div>

                <div class="layout-features">
                    <li><i class="fa fa-check-circle"></i> Collapsible accordion panels</li>
                    <li><i class="fa fa-check-circle"></i> Floating freight action bar</li>
                    <li><i class="fa fa-check-circle"></i> Compact table design</li>
                    <li><i class="fa fa-check-circle"></i> Always-visible quick stats</li>
                </div>

                <div class="layout-pros-cons">
                    <div class="pros">
                        <h6>Pros</h6>
                        <ul>
                            <li>Space efficient</li>
                            <li>Floating action bar</li>
                            <li>Expand what you need</li>
                            <li>Quick overview</li>
                        </ul>
                    </div>
                    <div class="cons">
                        <h6>Cons</h6>
                        <ul>
                            <li>Manual expand/collapse</li>
                            <li>Learning curve</li>
                            <li>Bottom bar covers content</li>
                        </ul>
                    </div>
                </div>

                <div class="layout-actions">
                    <a href="pack-advanced-layout-c.php" target="_blank" class="btn btn-primary btn-view">
                        <i class="fa fa-external-link-alt"></i> View
                    </a>
                    <button class="btn btn-success btn-view" onclick="selectLayout('C')">
                        <i class="fa fa-check"></i> Choose
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Comparison Table -->
    <div class="comparison-table">
        <h3><i class="fa fa-table"></i> Feature Comparison</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Feature</th>
                    <th>Layout A</th>
                    <th>Layout B</th>
                    <th>Layout C</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><strong>All Info Visible</strong></td>
                    <td><i class="fa fa-check-circle feature-check"></i></td>
                    <td><i class="fa fa-times-circle feature-cross"></i></td>
                    <td><i class="fa fa-check-circle feature-check"></i></td>
                </tr>
                <tr>
                    <td><strong>Space Efficient</strong></td>
                    <td><i class="fa fa-times-circle feature-cross"></i></td>
                    <td><i class="fa fa-check-circle feature-check"></i></td>
                    <td><i class="fa fa-check-circle feature-check"></i></td>
                </tr>
                <tr>
                    <td><strong>Mobile Friendly</strong></td>
                    <td><i class="fa fa-times-circle feature-cross"></i></td>
                    <td><i class="fa fa-check-circle feature-check"></i></td>
                    <td><i class="fa fa-check-circle feature-check"></i></td>
                </tr>
                <tr>
                    <td><strong>Quick Freight Access</strong></td>
                    <td><i class="fa fa-check-circle feature-check"></i></td>
                    <td><i class="fa fa-times-circle feature-cross"></i></td>
                    <td><i class="fa fa-check-circle feature-check"></i></td>
                </tr>
                <tr>
                    <td><strong>Product Table Width</strong></td>
                    <td>70%</td>
                    <td>100%</td>
                    <td>100%</td>
                </tr>
                <tr>
                    <td><strong>Navigation Style</strong></td>
                    <td>Scroll</td>
                    <td>Tabs</td>
                    <td>Accordion</td>
                </tr>
                <tr>
                    <td><strong>Best For</strong></td>
                    <td>Large screens, traditional users</td>
                    <td>Organized workflow, clean look</td>
                    <td>Power users, efficiency</td>
                </tr>
            </tbody>
        </table>
    </div>

    <!-- Recommendation -->
    <div class="recommendation">
        <h3><i class="fa fa-lightbulb"></i> Our Recommendation</h3>
        <p>Based on your requirements for thermal labels, AI optimization, and comprehensive freight integration, we recommend <strong>Layout A (Two Column Split)</strong> for its balance of visibility and functionality.</p>
        <a href="pack-advanced-layout-a.php" target="_blank" class="btn btn-light btn-lg">
            <i class="fa fa-rocket"></i> Try Recommended Layout
        </a>
    </div>
</div>

<script>
function selectLayout(layout) {
    if (confirm(`Set Layout ${layout} as your preferred pack page design?`)) {
        // Save preference (you can implement this with localStorage or server-side)
        localStorage.setItem('preferred_pack_layout', layout);

        alert(`✅ Layout ${layout} selected! Opening in new tab...`);

        const urls = {
            'A': 'pack-advanced-layout-a.php',
            'B': 'pack-advanced-layout-b.php',
            'C': 'pack-advanced-layout-c.php'
        };

        window.open(urls[layout], '_blank');
    }
}

// Show saved preference if exists
window.addEventListener('DOMContentLoaded', function() {
    const saved = localStorage.getItem('preferred_pack_layout');
    if (saved) {
        console.log('Previously selected layout:', saved);
    }
});
</script>

<?php $theme->render('footer'); ?>
