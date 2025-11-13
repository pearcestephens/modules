<?php
/**
 * Consignments Module - Freight Management
 *
 * @package CIS\Consignments
 * @version 5.0.0 - Bootstrap 5 + Modern Theme
 * @updated 2025-11-11 - Bootstrap 5 conversion
 */

declare(strict_types=1);

// Modern Theme Setup
$pageTitle = 'Freight Management';
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/', 'icon' => 'bi-house-door'],
    ['label' => 'Consignments', 'url' => '/modules/consignments/', 'icon' => 'bi-box-seam'],
    ['label' => 'Freight Management', 'url' => '/modules/consignments/?route=freight', 'active' => true]
];

$pageCSS = [
    'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css',
    '/modules/admin-ui/css/cms-design-system.css',
    '/modules/shared/css/tokens.css'
];

$pageJS = [];

// Start content capture
ob_start();
?>

<div class="page-header fade-in mb-4">
    <h1 class="page-title mb-2"><i class="bi bi-truck me-2"></i>Freight Management</h1>
    <p class="page-subtitle text-muted mb-0">Carrier management and shipping rates</p>
</div>

<div class="container-fluid">
        </div>
    </div>

/**
 * Freight Management View
 *
 * Manage freight bookings and track shipments.
 *
 * @package CIS\Consignments
 * @version 3.0.0
 */

declare(strict_types=1);

// Page metadata
$pageTitle = 'Freight Management';
$breadcrumbs = [
    ['label' => 'Home', 'url' => '/', 'icon' => 'fa-home'],
    ['label' => 'Consignments', 'url' => '/modules/consignments/'],
    ['label' => 'Freight', 'url' => '', 'active' => true]
];

// Get database connection
$pdo = CIS\Base\Database::pdo();

// Check if freight_bookings table exists
$tableCheck = $pdo->query("SHOW TABLES LIKE 'freight_bookings'");
$freightTableExists = ($tableCheck->rowCount() > 0);

$freightBookings = [];

if ($freightTableExists) {
    // Load recent freight bookings
    $stmt = $pdo->query("
        SELECT
            id,
            consignment_id,
            provider,
            tracking_number,
            status,
            created_at,
            updated_at
        FROM freight_bookings
        ORDER BY created_at DESC
        LIMIT 100
    ");

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $freightBookings[] = $row;
    }
}

// Render directly within CIS template content
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h1 class="h2 mb-1">
            <i class="fas fa-shipping-fast text-primary me-2"></i>
            Freight Management
        </h1>
        <p class="text-muted mb-0">Track and manage freight bookings</p>
    </div>
    <div>
        <a href="/modules/consignments/purchase-orders/freight-quote.php" class="btn btn-success">
            <i class="fas fa-plus me-2"></i>
            Get Freight Quote
        </a>
    </div>
</div>

<?php if (!$freightTableExists): ?>
    <!-- Migration Notice -->
    <div class="alert alert-warning">
        <h4 class="alert-heading"><i class="fas fa-exclamation-triangle me-2"></i>Freight Table Not Found</h4>
        <p class="mb-0">
            The freight_bookings table doesn't exist yet. Run the migration:
            <code>mysql < database/10-freight-bookings.sql</code>
        </p>
    </div>
<?php else: ?>
    <!-- Freight Bookings Table -->
    <div class="card">
        <div class="card-body">
            <?php if (count($freightBookings) > 0): ?>
                <table class="table table-hover" id="freightTable">
                    <thead>
                        <tr>
                            <th>Booking ID</th>
                            <th>Consignment</th>
                            <th>Provider</th>
                            <th>Tracking Number</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Updated</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($freightBookings as $booking): ?>
                            <tr>
                                <td><strong>#<?= $booking['id'] ?></strong></td>
                                <td><?= htmlspecialchars($booking['consignment_id']) ?></td>
                                <td><?= htmlspecialchars($booking['provider']) ?></td>
                                <td>
                                    <?php if ($booking['tracking_number']): ?>
                                        <a href="#" onclick="trackShipment('<?= htmlspecialchars($booking['tracking_number']) ?>'); return false;">
                                            <?= htmlspecialchars($booking['tracking_number']) ?>
                                        </a>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                    $statusBadge = 'secondary';
                                    switch (strtolower($booking['status'])) {
                                        case 'delivered': $statusBadge = 'success'; break;
                                        case 'in_transit': $statusBadge = 'info'; break;
                                        case 'pending': $statusBadge = 'warning'; break;
                                        case 'failed': $statusBadge = 'danger'; break;
                                    }
                                    ?>
                                    <span class="badge bg-<?= $statusBadge ?>">
                                        <?= htmlspecialchars($booking['status']) ?>
                                    </span>
                                </td>
                                <td><?= date('Y-m-d H:i', strtotime($booking['created_at'])) ?></td>
                                <td><?= date('Y-m-d H:i', strtotime($booking['updated_at'])) ?></td>
                                <td>
                                    <a href="/modules/consignments/purchase-orders/tracking.php?id=<?= $booking['id'] ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-map-marker-alt"></i> Track
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                    <h4 class="text-muted">No Freight Bookings</h4>
                    <p class="text-muted">Create your first freight booking to get started</p>
                    <a href="/modules/consignments/purchase-orders/freight-quote.php" class="btn btn-success mt-3">
                        <i class="fas fa-plus me-2"></i>
                        Get Freight Quote
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>
<?php endif; ?>

<script>
    <?php if (count($freightBookings) > 0): ?>
    // Initialize DataTable
    $(document).ready(function() {
        $('#freightTable').DataTable({
            order: [[5, 'desc']], // Sort by created date desc
            pageLength: 25,
            responsive: true
        });
    });
    <?php endif; ?>

    function trackShipment(trackingNumber) {
        Swal.fire({
            title: 'Track Shipment',
            html: `
                <p>Tracking Number: <strong>${trackingNumber}</strong></p>
                <p class="text-muted">Tracking functionality will be integrated with freight provider APIs</p>
            `,
            icon: 'info',
            confirmButtonText: 'Close'
        });
    }
</script>

<?php
// Close container started above and render via CIS template
?>
</div>

<?php
// Capture content
$content = ob_get_clean();

// Load the Modern Theme (Bootstrap 5)
require_once __DIR__ . '/../../base/templates/themes/modern/layouts/dashboard.php';
?>
