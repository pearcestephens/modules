<?php
/**
 * Stock Transfers - Modern List View
 *
 * Enterprise-grade transfer management with live AJAX data and modal interactions.
 * Uses BASE framework render() pattern for consistent UI/UX.
 *
 * @package CIS\Consignments\StockTransfers
 * @version 5.0.0
 * @updated 2025-11-13 (Converted to BASE framework)
 */

declare(strict_types=1);

// Get filter state from URL
$state = isset($_GET['state']) ? (string)$_GET['state'] : '';
$scope = isset($_GET['scope']) ? (string)$_GET['scope'] : '';
$uid = $_SESSION['userID'] ?? null;

// Start output buffering for content
ob_start();
?>

<!-- Hidden data for JS -->
<div id="pageData"
     data-state="<?= htmlspecialchars($state) ?>"
     data-scope="<?= htmlspecialchars($scope) ?>"
     data-user-id="<?= $uid ?>"
     style="display:none;"></div>

<div class="st-wrapper">
    <!-- Filters -->
    <div class="st-filters" id="stFilters">
        <div class="filter-pills" id="filterPills">
            <!-- JS will populate -->
            <div class="text-center py-2">
                <i class="fas fa-spinner fa-spin"></i> Loading filters...
            </div>
        </div>
    </div>

    <!-- Table Container -->
    <div class="st-table-card">
        <table class="st-table" id="transfersTable">
            <thead>
                <tr>
                    <th>Consignment</th>
                    <th>From → To</th>
                    <th>Status</th>
                    <th>Items</th>
                    <th>Progress</th>
                    <th>Updated</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody id="transfersBody">
                <!-- JS will populate -->
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <i class="fas fa-spinner fa-spin fa-2x mb-3" style="color: var(--st-primary);"></i>
                        <div>Loading transfers...</div>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Transfer Detail Modal -->
<div class="modal fade" id="transferModal" tabindex="-1" role="dialog" aria-labelledby="modalTitle">
    <div class="modal-dialog modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="modalTitle">Transfer Details</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body" id="modalBody">
                <div class="text-center py-5">
                    <i class="fas fa-spinner fa-spin fa-3x"></i>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Stock Transfers JavaScript -->
<script src="/modules/consignments/stock-transfers/js/stock-transfers.js?v=<?= time() ?>"></script>

<?php
// Get buffered content and render with BASE template
$content = ob_get_clean();

// Render using BASE framework
render('base', $content, [
    'pageTitle' => 'Stock Transfers',
    'pageSubtitle' => 'Manage inter-outlet inventory transfers',
    'breadcrumbs' => [
        ['title' => 'Consignments', 'url' => '/modules/consignments/'],
        ['title' => 'Stock Transfers', 'url' => null]
    ],
    'headerButtons' => [
        [
            'text' => 'New Transfer',
            'url' => '/modules/consignments/?route=transfer-manager',
            'class' => 'btn-success',
            'icon' => 'fa-plus'
        ]
    ],
    'styles' => [
        '/modules/consignments/assets/css/tokens.css?v=' . time(),
        '/modules/consignments/stock-transfers/css/stock-transfers.css?v=' . time()
    ]
]);
?>
