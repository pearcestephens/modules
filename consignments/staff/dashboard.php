<?php
declare(strict_types=1);

/**
 * ✨ PREMIUM STAFF TRANSFER DASHBOARD ✨
 *
 * Unified command center for warehouse staff managing transfers
 * Features: Live status tracking, quick actions, analytics, workflow steps
 *
 * @package CIS\Consignments\Staff
 * @version 2.0.0
 * @created 2025-11-13
 */

// ============================================================================
// INITIALIZATION
// ============================================================================

session_start();

// Require staff authentication
if (empty($_SESSION['userID'])) {
    http_response_code(403);
    die(json_encode(['error' => 'Authentication required']));
}

// Load bootstrap
require_once __DIR__ . '/../bootstrap.php';

$userID = (int)$_SESSION['userID'];
$userRole = $_SESSION['role'] ?? 'staff';

// ============================================================================
// FETCH STAFF'S ACTIVE TRANSFERS
// ============================================================================

try {
    $pdo = cis_resolve_pdo();

    // Get all transfer types the staff is involved with
    $stmt = $pdo->prepare("
        SELECT
            t.id,
            t.transfer_number,
            t.transfer_type,
            t.status,
            t.created_at,
            t.sent_at,
            t.received_at,

            -- From/To outlets
            sf.outlet_name as from_outlet,
            st.outlet_name as to_outlet,

            -- Item counts
            (SELECT COUNT(*) FROM consignment_items ci WHERE ci.consignment_id = t.id) as item_count,
            (SELECT SUM(ci.quantity_sent) FROM consignment_items ci WHERE ci.consignment_id = t.id AND ci.status = 'packed') as packed_count,

            -- Weight summary
            (SELECT SUM(p.weight_g * ci.quantity_sent) / 1000 FROM consignment_items ci
             JOIN products p ON p.id = ci.product_id
             WHERE ci.consignment_id = t.id AND ci.status = 'packed') as weight_kg,

            -- Created by/Assigned to
            COALESCE(CONCAT(u.first_name, ' ', u.last_name), 'System') as created_by,

            -- Box count estimate
            CEIL(COALESCE(
                (SELECT SUM(ci.quantity_sent) FROM consignment_items ci WHERE ci.consignment_id = t.id AND ci.status = 'packed'),
                0
            ) / 20) as box_estimate

        FROM consignments t
        LEFT JOIN outlets sf ON sf.id = t.from_outlet_id
        LEFT JOIN outlets st ON st.id = t.to_outlet_id
        LEFT JOIN users u ON u.id = t.created_by

        WHERE
            (t.status IN ('draft', 'in_progress', 'ready_to_send', 'sent'))
            AND (
                t.from_outlet_id IN (SELECT outlet_id FROM user_outlets WHERE user_id = ?)
                OR t.to_outlet_id IN (SELECT outlet_id FROM user_outlets WHERE user_id = ?)
            )

        ORDER BY
            CASE t.status
                WHEN 'in_progress' THEN 0
                WHEN 'ready_to_send' THEN 1
                WHEN 'draft' THEN 2
                WHEN 'sent' THEN 3
            END,
            t.created_at DESC
        LIMIT 50
    ");

    $stmt->execute([$userID, $userID]);
    $transfers = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Get summary stats
    $summaryStmt = $pdo->prepare("
        SELECT
            COUNT(*) as total_active,
            SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
            SUM(CASE WHEN status = 'ready_to_send' THEN 1 ELSE 0 END) as ready,
            SUM(CASE WHEN status = 'sent' THEN 1 ELSE 0 END) as sent_today,
            SUM(CASE WHEN status = 'draft' THEN 1 ELSE 0 END) as drafts
        FROM consignments
        WHERE (
            from_outlet_id IN (SELECT outlet_id FROM user_outlets WHERE user_id = ?)
            OR to_outlet_id IN (SELECT outlet_id FROM user_outlets WHERE user_id = ?)
        )
        AND status IN ('draft', 'in_progress', 'ready_to_send', 'sent')
    ");

    $summaryStmt->execute([$userID, $userID]);
    $summary = $summaryStmt->fetch(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    error_log("Dashboard error: " . $e->getMessage());
    $transfers = [];
    $summary = ['total_active' => 0, 'in_progress' => 0, 'ready' => 0, 'sent_today' => 0, 'drafts' => 0];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🎯 Transfer Dashboard - Warehouse Operations</title>

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/css/bootstrap.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="/modules/consignments/css/staff-dashboard.css">
    <link rel="stylesheet" href="/modules/consignments/css/box-optimizer.css">

    <style>
        :root {
            --primary-gradient: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            --success-gradient: linear-gradient(135deg, #11998e 0%, #38ef7d 100%);
            --warning-gradient: linear-gradient(135deg, #ff9a56 0%, #ff6a88 100%);
            --info-gradient: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%);
            --danger-gradient: linear-gradient(135deg, #eb3349 0%, #f45c43 100%);
        }

        * {
            box-sizing: border-box;
        }

        body {
            background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
            min-height: 100vh;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            padding: 15px;
        }

        .dashboard-header {
            background: var(--primary-gradient);
            color: white;
            padding: 30px 20px;
            border-radius: 15px;
            margin-bottom: 30px;
            box-shadow: 0 10px 40px rgba(102, 126, 234, 0.2);
        }

        .dashboard-header h1 {
            font-size: 28px;
            font-weight: 700;
            margin: 0;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .dashboard-header p {
            margin: 8px 0 0 0;
            opacity: 0.95;
            font-size: 14px;
        }

        /* STATS CARDS - Colorful, Eye-Catching */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 15px;
            margin-bottom: 30px;
        }

        .stat-card {
            padding: 20px;
            border-radius: 12px;
            color: white;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            position: relative;
            overflow: hidden;
        }

        .stat-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            opacity: 0;
            transition: opacity 0.3s ease;
            z-index: -1;
        }

        .stat-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
        }

        .stat-card.active { background: var(--primary-gradient); }
        .stat-card.ready { background: var(--success-gradient); }
        .stat-card.sent { background: var(--info-gradient); }
        .stat-card.draft { background: var(--warning-gradient); }

        .stat-card-value {
            font-size: 32px;
            font-weight: 700;
            line-height: 1;
            margin-bottom: 8px;
        }

        .stat-card-label {
            font-size: 13px;
            font-weight: 600;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        /* TRANSFER CARDS */
        .transfer-cards {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .transfer-card {
            background: white;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 5px 25px rgba(0,0,0,0.08);
            transition: all 0.3s ease;
            border-left: 5px solid #667eea;
            display: flex;
            flex-direction: column;
        }

        .transfer-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 50px rgba(0,0,0,0.15);
        }

        .transfer-card.status-in-progress {
            border-left-color: #667eea;
        }

        .transfer-card.status-ready {
            border-left-color: #38ef7d;
        }

        .transfer-card.status-sent {
            border-left-color: #00f2fe;
        }

        .transfer-card.status-draft {
            border-left-color: #ff9a56;
        }

        .transfer-card-header {
            padding: 15px;
            background: linear-gradient(135deg, #f5f7fa 0%, #f0f1f5 100%);
            border-bottom: 1px solid #e9ecef;
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
        }

        .transfer-type-badge {
            display: inline-block;
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-stock { background: #e7f3ff; color: #0066cc; }
        .badge-juice { background: #fff4e6; color: #ff9900; }
        .badge-po { background: #f0f5ff; color: #0033cc; }
        .badge-staff { background: #e6f9f0; color: #00a366; }
        .badge-return { background: #ffebee; color: #d32f2f; }

        .transfer-id {
            font-size: 18px;
            font-weight: 700;
            color: #2d3748;
            margin: 0;
        }

        .transfer-card-body {
            padding: 15px;
            flex-grow: 1;
        }

        .transfer-info-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 8px 0;
            font-size: 14px;
            border-bottom: 1px solid #f0f0f0;
        }

        .transfer-info-row:last-child {
            border-bottom: none;
        }

        .transfer-info-label {
            color: #718096;
            font-weight: 500;
        }

        .transfer-info-value {
            color: #2d3748;
            font-weight: 600;
        }

        /* PROGRESS INDICATORS */
        .progress-compact {
            height: 6px;
            background: #e2e8f0;
            border-radius: 3px;
            margin: 8px 0;
            overflow: hidden;
        }

        .progress-bar-compact {
            height: 100%;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
            transition: width 0.3s ease;
        }

        .transfer-card-footer {
            padding: 12px 15px;
            background: #f9fafb;
            border-top: 1px solid #e9ecef;
            display: flex;
            gap: 8px;
            justify-content: space-between;
        }

        .btn-quick-action {
            flex: 1;
            padding: 10px 12px;
            font-size: 13px;
            font-weight: 600;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.2s ease;
            text-align: center;
        }

        .btn-primary-action {
            background: #667eea;
            color: white;
        }

        .btn-primary-action:hover {
            background: #5568d3;
            transform: translateY(-2px);
        }

        .btn-secondary-action {
            background: #e2e8f0;
            color: #2d3748;
        }

        .btn-secondary-action:hover {
            background: #cbd5e0;
        }

        /* STATUS BADGES */
        .status-badge {
            display: inline-block;
            padding: 4px 10px;
            border-radius: 12px;
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.3px;
        }

        .badge-in-progress { background: #fff3cd; color: #856404; }
        .badge-ready { background: #d4edda; color: #155724; }
        .badge-sent { background: #d1ecf1; color: #0c5460; }
        .badge-draft { background: #f8d7da; color: #721c24; }

        /* EMPTY STATE */
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: #718096;
        }

        .empty-state i {
            font-size: 48px;
            opacity: 0.3;
            margin-bottom: 15px;
        }

        /* RESPONSIVE */
        @media (max-width: 768px) {
            .dashboard-header {
                padding: 20px 15px;
                margin-bottom: 20px;
            }

            .dashboard-header h1 {
                font-size: 22px;
            }

            .stats-grid {
                grid-template-columns: repeat(auto-fit, minmax(100px, 1fr));
                gap: 10px;
            }

            .transfer-cards {
                grid-template-columns: 1fr;
            }

            .stat-card-value {
                font-size: 24px;
            }

            .stat-card-label {
                font-size: 11px;
            }

            .transfer-card-footer {
                flex-direction: column;
            }

            .btn-quick-action {
                width: 100%;
            }
        }

        /* ANIMATIONS */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .transfer-card {
            animation: slideIn 0.3s ease forwards;
        }

        .transfer-card:nth-child(1) { animation-delay: 0.05s; }
        .transfer-card:nth-child(2) { animation-delay: 0.1s; }
        .transfer-card:nth-child(3) { animation-delay: 0.15s; }
        .transfer-card:nth-child(n+4) { animation-delay: 0.2s; }

        /* WORKFLOW STEPS */
        .workflow-steps {
            display: flex;
            justify-content: space-between;
            margin: 12px 0;
            font-size: 11px;
        }

        .step {
            flex: 1;
            text-align: center;
            position: relative;
        }

        .step-icon {
            width: 20px;
            height: 20px;
            background: #e2e8f0;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 4px;
            font-size: 10px;
            color: #718096;
        }

        .step.active .step-icon {
            background: #667eea;
            color: white;
        }

        .step.completed .step-icon {
            background: #38ef7d;
            color: white;
        }
    </style>
</head>
<body>

<div class="container-fluid" style="max-width: 1400px;">

    <!-- HERO HEADER -->
    <div class="dashboard-header">
        <h1>
            <i class="fas fa-cubes"></i> Transfer Dashboard
        </h1>
        <p>🎯 Manage all your warehouse transfers, track progress, optimize packing</p>
    </div>

    <!-- STATS CARDS -->
    <div class="stats-grid">
        <div class="stat-card active" onclick="filterTransfers('all')">
            <div class="stat-card-value"><?= (int)$summary['total_active'] ?></div>
            <div class="stat-card-label">Total Active</div>
        </div>

        <div class="stat-card active" onclick="filterTransfers('in_progress')">
            <div class="stat-card-value"><?= (int)$summary['in_progress'] ?></div>
            <div class="stat-card-label">In Progress</div>
        </div>

        <div class="stat-card ready" onclick="filterTransfers('ready')">
            <div class="stat-card-value"><?= (int)$summary['ready'] ?></div>
            <div class="stat-card-label">Ready to Send</div>
        </div>

        <div class="stat-card sent" onclick="filterTransfers('sent')">
            <div class="stat-card-value"><?= (int)$summary['sent_today'] ?></div>
            <div class="stat-card-label">Sent Today</div>
        </div>

        <div class="stat-card draft" onclick="filterTransfers('draft')">
            <div class="stat-card-value"><?= (int)$summary['drafts'] ?></div>
            <div class="stat-card-label">Drafts</div>
        </div>
    </div>

    <!-- TRANSFER CARDS GRID -->
    <?php if (count($transfers) > 0): ?>
        <div class="transfer-cards" id="transferGrid">
            <?php foreach ($transfers as $t):
                $statusClass = 'status-' . str_replace('_', '-', $t['status']);
                $typeClass = 'badge-' . strtolower($t['transfer_type']);
                $progress = $t['item_count'] > 0 ? (int)(($t['packed_count'] ?? 0) / $t['item_count'] * 100) : 0;
                $fromTo = ($t['from_outlet'] ?? 'Unknown') . ' → ' . ($t['to_outlet'] ?? 'Unknown');
            ?>
            <div class="transfer-card <?= $statusClass ?>" data-status="<?= $t['status'] ?>">

                <!-- HEADER -->
                <div class="transfer-card-header">
                    <div>
                        <span class="transfer-type-badge <?= $typeClass ?>">
                            <?= htmlspecialchars($t['transfer_type']) ?>
                        </span>
                        <h5 class="transfer-id">#<?= htmlspecialchars($t['transfer_number']) ?></h5>
                    </div>
                    <span class="status-badge badge-<?= str_replace('_', '-', $t['status']) ?>">
                        <?= ucfirst(str_replace('_', ' ', $t['status'])) ?>
                    </span>
                </div>

                <!-- BODY -->
                <div class="transfer-card-body">

                    <!-- Route -->
                    <div class="transfer-info-row">
                        <span class="transfer-info-label">
                            <i class="fas fa-route"></i> Route
                        </span>
                        <span class="transfer-info-value" title="<?= htmlspecialchars($fromTo) ?>">
                            <?= htmlspecialchars(substr($fromTo, 0, 30)) . (strlen($fromTo) > 30 ? '...' : '') ?>
                        </span>
                    </div>

                    <!-- Items -->
                    <div class="transfer-info-row">
                        <span class="transfer-info-label">
                            <i class="fas fa-cubes"></i> Items
                        </span>
                        <span class="transfer-info-value">
                            <?= (int)($t['packed_count'] ?? 0) ?> / <?= (int)$t['item_count'] ?> packed
                        </span>
                    </div>

                    <!-- Weight -->
                    <div class="transfer-info-row">
                        <span class="transfer-info-label">
                            <i class="fas fa-weight"></i> Weight
                        </span>
                        <span class="transfer-info-value">
                            <?= number_format((float)($t['weight_kg'] ?? 0), 1) ?> kg
                        </span>
                    </div>

                    <!-- Boxes -->
                    <div class="transfer-info-row">
                        <span class="transfer-info-label">
                            <i class="fas fa-box"></i> Boxes
                        </span>
                        <span class="transfer-info-value">
                            ~<?= (int)($t['box_estimate'] ?? 1) ?> estimated
                        </span>
                    </div>

                    <!-- Progress -->
                    <div class="progress-compact">
                        <div class="progress-bar-compact" style="width: <?= $progress ?>%"></div>
                    </div>

                    <!-- Workflow Steps -->
                    <div class="workflow-steps">
                        <div class="step <?= $progress > 0 ? 'active' : '' ?> <?= $progress >= 100 ? 'completed' : '' ?>">
                            <div class="step-icon">1</div>
                            <div>Packing</div>
                        </div>
                        <div class="step <?= $t['status'] === 'ready_to_send' || in_array($t['status'], ['sent', 'received']) ? 'active' : '' ?> <?= in_array($t['status'], ['sent', 'received']) ? 'completed' : '' ?>">
                            <div class="step-icon">2</div>
                            <div>Ready</div>
                        </div>
                        <div class="step <?= in_array($t['status'], ['sent', 'received']) ? 'active' : '' ?> <?= $t['status'] === 'received' ? 'completed' : '' ?>">
                            <div class="step-icon">3</div>
                            <div>Sent</div>
                        </div>
                    </div>

                </div>

                <!-- FOOTER / ACTIONS -->
                <div class="transfer-card-footer">
                    <button class="btn-quick-action btn-primary-action" onclick="openPackPage('<?= (int)$t['id'] ?>', '<?= htmlspecialchars($t['transfer_type']) ?>')">
                        <i class="fas fa-boxes"></i> Pack
                    </button>
                    <button class="btn-quick-action btn-secondary-action" onclick="viewDetails('<?= (int)$t['id'] ?>')">
                        <i class="fas fa-info-circle"></i> Details
                    </button>
                </div>

            </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state">
            <i class="fas fa-inbox"></i>
            <h4>No Active Transfers</h4>
            <p>You don't have any active transfers right now.</p>
            <a href="/modules/consignments/" class="btn btn-primary mt-3">
                <i class="fas fa-arrow-right"></i> Go to Consignments
            </a>
        </div>
    <?php endif; ?>

</div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@4.6.0/dist/js/bootstrap.bundle.min.js"></script>

<script>
    function filterTransfers(status) {
        const grid = document.getElementById('transferGrid');
        if (!grid) return;

        const cards = grid.querySelectorAll('.transfer-card');
        cards.forEach(card => {
            if (status === 'all') {
                card.style.display = 'flex';
            } else {
                card.style.display = card.dataset.status === status ? 'flex' : 'none';
            }
        });
    }

    function openPackPage(transferId, transferType) {
        // Route to appropriate pack page based on transfer type
        const routes = {
            'STOCK': '/modules/consignments/stock-transfers/pack.php?id=',
            'JUICE': '/modules/consignments/juice-transfers/pack.php?id=',
            'PO': '/modules/consignments/purchase-orders/receive.php?id=',
            'STAFF': '/modules/consignments/staff-transfer/pack.php?id=',
            'RETURN': '/modules/consignments/returns/pack.php?id='
        };

        const url = (routes[transferType] || routes['STOCK']) + transferId;
        window.location.href = url;
    }

    function viewDetails(transferId) {
        // ✅ IMPLEMENTED: Full details modal with AJAX load
        fetch(`/modules/consignments/api/unified/?action=get_transfer_detail&id=${transferId}`)
            .then(res => res.json())
            .then(data => {
                if (!data.success || !data.transfer) {
                    alert('Failed to load transfer details');
                    return;
                }

                const t = data.transfer;
                const items = data.items || [];

                // Build items HTML
                let itemsHtml = '<table class="table table-sm"><thead><tr><th>SKU</th><th>Product</th><th>Requested</th><th>Sent</th><th>Received</th></tr></thead><tbody>';
                items.forEach(item => {
                    itemsHtml += `<tr>
                        <td>${item.sku || 'N/A'}</td>
                        <td>${item.product_name || 'Unknown'}</td>
                        <td>${item.qty_requested || 0}</td>
                        <td>${item.qty_sent || 0}</td>
                        <td>${item.qty_received || 0}</td>
                    </tr>`;
                });
                itemsHtml += '</tbody></table>';

                // Show Bootstrap modal
                const modalHtml = `
                    <div class="modal fade" id="detailsModal" tabindex="-1">
                        <div class="modal-dialog modal-lg">
                            <div class="modal-content">
                                <div class="modal-header">
                                    <h5 class="modal-title">Transfer #${t.id} - ${t.transfer_category}</h5>
                                    <button type="button" class="close" data-dismiss="modal">&times;</button>
                                </div>
                                <div class="modal-body">
                                    <dl class="row">
                                        <dt class="col-sm-3">Status:</dt><dd class="col-sm-9"><span class="badge badge-${t.state === 'OPEN' ? 'warning' : t.state === 'SENT' ? 'info' : 'success'}">${t.state}</span></dd>
                                        <dt class="col-sm-3">From:</dt><dd class="col-sm-9">${t.origin_outlet_name || 'N/A'}</dd>
                                        <dt class="col-sm-3">To:</dt><dd class="col-sm-9">${t.dest_outlet_name || 'N/A'}</dd>
                                        <dt class="col-sm-3">Reference:</dt><dd class="col-sm-9">${t.reference_code || 'N/A'}</dd>
                                        <dt class="col-sm-3">Created:</dt><dd class="col-sm-9">${t.created_at || 'N/A'}</dd>
                                    </dl>
                                    <h6 class="mt-3">Items (${items.length}):</h6>
                                    ${itemsHtml}
                                </div>
                                <div class="modal-footer">
                                    <button class="btn btn-primary" onclick="openTransfer(${t.id}, '${t.transfer_category}')">Open Transfer</button>
                                    <button class="btn btn-secondary" data-dismiss="modal">Close</button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;

                // Remove any existing modal
                $('#detailsModal').remove();

                // Append and show
                $('body').append(modalHtml);
                $('#detailsModal').modal('show');

                // Auto-remove on hide
                $('#detailsModal').on('hidden.bs.modal', function() {
                    $(this).remove();
                });
            })
            .catch(err => {
                console.error('Failed to load details:', err);
                alert('Error loading transfer details');
            });
    }
</script>

</body>
</html>
