<?php
/**
 * CIS Modern Dashboard - Complete Demo
 * 
 * Demonstrates the complete modern CIS template with:
 * - Two-tier header (logo, search, notifications, messages, user)
 * - Dark gray sidebar
 * - Purple accent colors throughout
 * - Facebook-style chat bar (toggleable)
 * - Footer
 * - Breadcrumbs and quick actions
 * 
 * @package CIS\Demo
 * @version 2.0.0
 */

session_start();

// Configuration
$CHAT_ENABLED = true; // ENABLED! Set to false to disable chat bar

// Page context
$pageTitle = 'Modern Dashboard';
$pageParent = 'Home';

// Demo data
$_SESSION['user_name'] = 'Pearce Stephens';
$_SESSION['notifications_count'] = 13;
$_SESSION['messages_count'] = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?> - CIS Dashboard</title>
    
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: #f5f5f5;
            color: #343a40;
        }
        
        .dashboard-container {
            display: flex;
            min-height: 100vh;
        }
        
        .dashboard-main {
            flex: 1;
            display: flex;
            flex-direction: column;
            margin-left: 260px; /* THIS PUSHES CONTENT TO THE RIGHT! */
            transition: margin-left 0.3s ease;
        }
        
        .dashboard-content {
            flex: 1;
            padding: 2rem;
            <?= $CHAT_ENABLED ? 'margin-bottom: 60px;' : '' ?>
        }
        
        /* Stats Cards */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .stat-card {
            background: white;
            padding: 1.75rem;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07), 0 1px 3px rgba(0,0,0,0.06);
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            border: 1px solid rgba(0,0,0,0.04);
            position: relative;
            overflow: hidden;
        }
        
        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, #8B5CF6, #EC4899);
            opacity: 0;
            transition: opacity 0.3s;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
            box-shadow: 0 12px 24px rgba(0,0,0,0.12), 0 4px 8px rgba(0,0,0,0.08);
        }
        
        .stat-card:hover::before {
            opacity: 1;
        }
        
        .stat-card-header {
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            margin-bottom: 1.25rem;
        }
        
        .stat-card-icon {
            width: 56px;
            height: 56px;
            background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);
            color: white;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.75rem;
            box-shadow: 0 4px 12px rgba(139, 92, 246, 0.3);
        }
        
        .stat-card-title {
            font-size: 0.875rem;
            color: #6c757d;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .stat-card-value {
            font-size: 2.25rem;
            font-weight: 700;
            color: #1a202c;
            margin-bottom: 0.5rem;
            line-height: 1.2;
        }
        
        .stat-card-change {
            font-size: 0.875rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.25rem 0.625rem;
            border-radius: 6px;
            background: #d4edda;
            color: #155724;
        }
        
        .stat-card-change.negative {
            background: #f8d7da;
            color: #721c24;
        }
        
        /* Content Grid */
        .content-grid {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }
        
        .content-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.07), 0 1px 3px rgba(0,0,0,0.06);
            overflow: hidden;
            border: 1px solid rgba(0,0,0,0.04);
            transition: box-shadow 0.3s;
        }
        
        .content-card:hover {
            box-shadow: 0 8px 16px rgba(0,0,0,0.1), 0 2px 4px rgba(0,0,0,0.06);
        }
        
        .content-card-header {
            padding: 1.5rem 1.75rem;
            border-bottom: 2px solid #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: space-between;
            background: linear-gradient(180deg, #ffffff 0%, #fafbfc 100%);
        }
        
        .content-card-title {
            font-size: 1.25rem;
            font-weight: 700;
            color: #1a202c;
            letter-spacing: -0.025em;
        }
        
        .content-card-body {
            padding: 1.5rem;
        }
        
        /* Table */
        .cis-table {
            width: 100%;
            border-collapse: collapse;
        }
        
        .cis-table thead {
            background: #f8f9fa;
        }
        
        .cis-table th,
        .cis-table td {
            padding: 1rem 1.25rem;
            text-align: left;
            border-bottom: 1px solid #e9ecef;
        }
        
        .cis-table th {
            font-weight: 700;
            font-size: 0.8rem;
            color: #495057;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .cis-table td {
            font-size: 0.95rem;
            color: #343a40;
        }
        
        .cis-table tbody tr {
            transition: background-color 0.15s;
        }
        
        .cis-table tbody tr:hover {
            background: #f8f9fa;
        }
        
        .badge {
            display: inline-block;
            padding: 0.375rem 0.75rem;
            font-size: 0.8rem;
            font-weight: 700;
            border-radius: 6px;
            letter-spacing: 0.3px;
        }
        
        .badge-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        
        .badge-warning {
            background: #fff3cd;
            color: #856404;
            border: 1px solid #ffeaa7;
        }
        
        .badge-danger {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        
        /* Activity Feed */
        .activity-feed {
            list-style: none;
        }
        
        .activity-item {
            padding: 1.25rem 0;
            border-bottom: 1px solid #e9ecef;
            display: flex;
            gap: 1rem;
            transition: background 0.15s;
        }
        
        .activity-item:last-child {
            border-bottom: none;
        }
        
        .activity-item:hover {
            background: #f8f9fa;
            margin: 0 -1.5rem;
            padding: 1.25rem 1.5rem;
        }
        
        .activity-icon {
            width: 44px;
            height: 44px;
            background: linear-gradient(135deg, #8B5CF6 0%, #7C3AED 100%);
            color: white;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            font-size: 1.1rem;
            box-shadow: 0 2px 8px rgba(139, 92, 246, 0.25);
        }
        
        .activity-icon.success {
            background: linear-gradient(135deg, #10b981 0%, #059669 100%);
            box-shadow: 0 2px 8px rgba(16, 185, 129, 0.25);
        }
        
        .activity-icon.warning {
            background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%);
            box-shadow: 0 2px 8px rgba(245, 158, 11, 0.25);
        }
        
        .activity-icon.info {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            box-shadow: 0 2px 8px rgba(59, 130, 246, 0.25);
        }
        
        .activity-content {
            flex: 1;
        }
        
        .activity-title {
            font-weight: 600;
            margin-bottom: 0.25rem;
            color: #1a202c;
            font-size: 0.95rem;
        }
        
        .activity-time {
            font-size: 0.85rem;
            color: #6c757d;
            line-height: 1.4;
        }
        
        @media (max-width: 1200px) {
            .content-grid {
                grid-template-columns: 1fr;
            }
        }
        
        @media (max-width: 768px) {
            .stats-grid {
                grid-template-columns: 1fr;
            }
            
            .dashboard-content {
                padding: 1rem;
            }
            
            .dashboard-main {
                margin-left: 0; /* Remove sidebar push on mobile */
            }
        }
    </style>
</head>
<body>
    <div class="dashboard-container">
        
        <!-- Sidebar -->
        <?php include __DIR__ . '/_templates/components/sidebar.php'; ?>
        
        <div class="dashboard-main">
            
            <!-- Header (Two-Tier) -->
            <?php include __DIR__ . '/_templates/components/header-v2.php'; ?>
            
            <!-- Main Content -->
            <main class="dashboard-content">
                
                <!-- Stats Grid -->
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-card-title">Total Sales</div>
                                <div class="stat-card-value">$124,563</div>
                                <div class="stat-card-change">
                                    <i class="fas fa-arrow-up"></i> 12.5% from last month
                                </div>
                            </div>
                            <div class="stat-card-icon">
                                <i class="fas fa-dollar-sign"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-card-title">Total Orders</div>
                                <div class="stat-card-value">1,429</div>
                                <div class="stat-card-change">
                                    <i class="fas fa-arrow-up"></i> 8.2% from last month
                                </div>
                            </div>
                            <div class="stat-card-icon">
                                <i class="fas fa-shopping-cart"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-card-title">Active Customers</div>
                                <div class="stat-card-value">892</div>
                                <div class="stat-card-change negative">
                                    <i class="fas fa-arrow-down"></i> 2.1% from last month
                                </div>
                            </div>
                            <div class="stat-card-icon">
                                <i class="fas fa-users"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="stat-card">
                        <div class="stat-card-header">
                            <div>
                                <div class="stat-card-title">Stock Value</div>
                                <div class="stat-card-value">$458,921</div>
                                <div class="stat-card-change">
                                    <i class="fas fa-arrow-up"></i> 5.3% from last month
                                </div>
                            </div>
                            <div class="stat-card-icon">
                                <i class="fas fa-boxes"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Content Grid -->
                <div class="content-grid">
                    
                    <!-- Recent Orders -->
                    <div class="content-card">
                        <div class="content-card-header">
                            <h2 class="content-card-title">Recent Orders</h2>
                            <button class="cis-quick-action-btn primary" style="background: #8B5CF6; color: white; padding: 0.5rem 1rem; border: none; border-radius: 6px; cursor: pointer;">
                                View All
                            </button>
                        </div>
                        <div class="content-card-body" style="padding: 0;">
                            <table class="cis-table">
                                <thead>
                                    <tr>
                                        <th>Order ID</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr>
                                        <td>#ORD-2458</td>
                                        <td>John Smith</td>
                                        <td>$142.50</td>
                                        <td><span class="badge badge-success">Completed</span></td>
                                        <td>2 hours ago</td>
                                    </tr>
                                    <tr>
                                        <td>#ORD-2457</td>
                                        <td>Emma Wilson</td>
                                        <td>$89.99</td>
                                        <td><span class="badge badge-warning">Processing</span></td>
                                        <td>4 hours ago</td>
                                    </tr>
                                    <tr>
                                        <td>#ORD-2456</td>
                                        <td>Michael Brown</td>
                                        <td>$256.00</td>
                                        <td><span class="badge badge-success">Completed</span></td>
                                        <td>6 hours ago</td>
                                    </tr>
                                    <tr>
                                        <td>#ORD-2455</td>
                                        <td>Sarah Davis</td>
                                        <td>$178.25</td>
                                        <td><span class="badge badge-danger">Cancelled</span></td>
                                        <td>8 hours ago</td>
                                    </tr>
                                    <tr>
                                        <td>#ORD-2454</td>
                                        <td>James Taylor</td>
                                        <td>$95.50</td>
                                        <td><span class="badge badge-success">Completed</span></td>
                                        <td>1 day ago</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    
                    <!-- Activity Feed -->
                    <div class="content-card">
                        <div class="content-card-header">
                            <h2 class="content-card-title">Recent Activity</h2>
                        </div>
                        <div class="content-card-body" style="padding: 0 1.5rem;">
                            <ul class="activity-feed">
                                <li class="activity-item">
                                    <div class="activity-icon">
                                        <i class="fas fa-box"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title">New stock received</div>
                                        <div class="activity-time">250 units added to inventory</div>
                                        <div class="activity-time">15 minutes ago</div>
                                    </div>
                                </li>
                                <li class="activity-item">
                                    <div class="activity-icon success">
                                        <i class="fas fa-user-plus"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title">New customer registered</div>
                                        <div class="activity-time">Alice Johnson</div>
                                        <div class="activity-time">1 hour ago</div>
                                    </div>
                                </li>
                                <li class="activity-item">
                                    <div class="activity-icon warning">
                                        <i class="fas fa-truck"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title">Shipment dispatched</div>
                                        <div class="activity-time">Order #ORD-2450 shipped</div>
                                        <div class="activity-time">3 hours ago</div>
                                    </div>
                                </li>
                                <li class="activity-item">
                                    <div class="activity-icon info">
                                        <i class="fas fa-chart-line"></i>
                                    </div>
                                    <div class="activity-content">
                                        <div class="activity-title">Sales target achieved</div>
                                        <div class="activity-time">Monthly goal reached</div>
                                        <div class="activity-time">5 hours ago</div>
                                    </div>
                                </li>
                            </ul>
                        </div>
                    </div>
                    
                </div>
                
            </main>
            
            <!-- Footer -->
            <?php include __DIR__ . '/_templates/components/footer.php'; ?>
            
        </div>
        
    </div>
    
    <!-- Chat Bar (if enabled) -->
    <?php if ($CHAT_ENABLED): ?>
        <?php include __DIR__ . '/_templates/components/chat-bar.php'; ?>
    <?php endif; ?>
    
</body>
</html>
