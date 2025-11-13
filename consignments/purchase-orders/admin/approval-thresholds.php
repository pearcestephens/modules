<?php
/**
 * Purchase Orders - Approval Threshold Configuration
 *
 * Admin panel for configuring approval workflow thresholds and tier assignments.
 * Allows customization of approval requirements based on purchase order value.
 *
 * Features:
 * - Configure 5 approval tiers with amount ranges
 * - Set required approver count per tier
 * - Assign roles/users to each tier
 * - Outlet-specific threshold overrides
 * - Test calculator to preview approval requirements
 * - Real-time validation
 *
 * @package CIS\Consignments\PurchaseOrders
 * @subpackage Admin
 * @since 1.0.0
 * @author AI Assistant
 * @date 2025-10-31
 */

declare(strict_types=1);

// Bootstrap application
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/bootstrap.php';

use Consignments\Lib\Services\ApprovalService;
use Consignments\Lib\Services\PurchaseOrderService;

// Check authentication and admin role
if (!isset($_SESSION['user_id'])) {
    header('Location: /login.php');
    exit;
}

if (($_SESSION['user_role'] ?? '') !== 'admin') {
    http_response_code(403);
    die('Access denied. Administrator privileges required.');
}

$db = getDB();
$approvalService = new ApprovalService($db);
$poService = new PurchaseOrderService($db);

// Get current configuration
$defaultThresholds = $approvalService->getThresholds();

// Get all outlets for override configuration
$outlets = $poService->getOutlets();

// Get all users with approval capabilities
$approversSQL = "
    SELECT id, username, email, role
    FROM users
    WHERE role IN ('manager', 'admin', 'finance')
    AND deleted_at IS NULL
    ORDER BY username
";
$approvers = $db->query($approversSQL)->fetchAll(PDO::FETCH_ASSOC);

// Handle form submission
$saveMessage = null;
$saveError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if ($_POST['action'] === 'save_default_thresholds') {
            // Save default thresholds
            $newThresholds = [];

            for ($tier = 1; $tier <= 5; $tier++) {
                $minAmount = isset($_POST["tier_{$tier}_min"]) ? floatval($_POST["tier_{$tier}_min"]) : 0;
                $maxAmount = isset($_POST["tier_{$tier}_max"]) ? floatval($_POST["tier_{$tier}_max"]) : PHP_FLOAT_MAX;
                $requiredApprovers = isset($_POST["tier_{$tier}_approvers"]) ? intval($_POST["tier_{$tier}_approvers"]) : 1;
                $roles = isset($_POST["tier_{$tier}_roles"]) ? $_POST["tier_{$tier}_roles"] : [];

                $newThresholds[$tier] = [
                    'min_amount' => $minAmount,
                    'max_amount' => $maxAmount,
                    'required_approvers' => $requiredApprovers,
                    'roles' => $roles
                ];
            }

            // Save to database (using config table or JSON file)
            $configSQL = "
                INSERT INTO system_config (config_key, config_value, updated_by, updated_at)
                VALUES ('approval_thresholds', ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    config_value = VALUES(config_value),
                    updated_by = VALUES(updated_by),
                    updated_at = NOW()
            ";
            $stmt = $db->prepare($configSQL);
            $stmt->execute([
                json_encode($newThresholds),
                $_SESSION['user_id']
            ]);

            $saveMessage = "Default approval thresholds saved successfully!";
            $defaultThresholds = $newThresholds;

        } elseif ($_POST['action'] === 'save_outlet_override') {
            // Save outlet-specific override
            $outletId = $_POST['outlet_id'] ?? null;
            $overrideThresholds = [];

            for ($tier = 1; $tier <= 5; $tier++) {
                $minAmount = isset($_POST["outlet_tier_{$tier}_min"]) ? floatval($_POST["outlet_tier_{$tier}_min"]) : 0;
                $maxAmount = isset($_POST["outlet_tier_{$tier}_max"]) ? floatval($_POST["outlet_tier_{$tier}_max"]) : PHP_FLOAT_MAX;
                $requiredApprovers = isset($_POST["outlet_tier_{$tier}_approvers"]) ? intval($_POST["outlet_tier_{$tier}_approvers"]) : 1;
                $roles = isset($_POST["outlet_tier_{$tier}_roles"]) ? $_POST["outlet_tier_{$tier}_roles"] : [];

                $overrideThresholds[$tier] = [
                    'min_amount' => $minAmount,
                    'max_amount' => $maxAmount,
                    'required_approvers' => $requiredApprovers,
                    'roles' => $roles
                ];
            }

            // Save outlet override
            $overrideSQL = "
                INSERT INTO approval_threshold_overrides (outlet_id, thresholds, created_by, created_at)
                VALUES (?, ?, ?, NOW())
                ON DUPLICATE KEY UPDATE
                    thresholds = VALUES(thresholds),
                    updated_by = VALUES(created_by),
                    updated_at = NOW()
            ";
            $stmt = $db->prepare($overrideSQL);
            $stmt->execute([
                $outletId,
                json_encode($overrideThresholds),
                $_SESSION['user_id']
            ]);

            $saveMessage = "Outlet-specific override saved successfully!";

        } elseif ($_POST['action'] === 'delete_outlet_override') {
            // Delete outlet override
            $outletId = $_POST['outlet_id'] ?? null;

            $deleteSQL = "DELETE FROM approval_threshold_overrides WHERE outlet_id = ?";
            $stmt = $db->prepare($deleteSQL);
            $stmt->execute([$outletId]);

            $saveMessage = "Outlet override deleted successfully!";
        }

    } catch (Exception $e) {
        $saveError = "Error saving configuration: " . $e->getMessage();
        error_log("Threshold config error: " . $e->getMessage());
    }
}

// Get existing outlet overrides
$overridesSQL = "
    SELECT
        ato.*,
        o.name AS outlet_name
    FROM approval_threshold_overrides ato
    LEFT JOIN vend_outlets o ON ato.outlet_id = o.id
    ORDER BY o.name
";
$outletOverrides = $db->query($overridesSQL)->fetchAll(PDO::FETCH_ASSOC);

// Page metadata
$pageTitle = 'Approval Threshold Configuration';
$pageDescription = 'Configure approval workflow thresholds and tier assignments';

// Include header
include $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/shared/blocks/header.php';
?>

<div class="container-fluid mt-4">
    <!-- Page Header -->
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h1 class="h3 mb-0">
                <i class="fas fa-sliders-h text-primary me-2"></i>
                Approval Threshold Configuration
            </h1>
            <p class="text-muted mb-0">Configure approval requirements based on purchase order value</p>
        </div>
        <div>
            <a href="/modules/consignments/purchase-orders/approvals/dashboard.php" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
            </a>
        </div>
    </div>

    <!-- Success/Error Messages -->
    <?php if ($saveMessage): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i>
            <?= htmlspecialchars($saveMessage) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if ($saveError): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <i class="fas fa-exclamation-circle me-2"></i>
            <?= htmlspecialchars($saveError) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row">
        <!-- Default Thresholds Configuration -->
        <div class="col-lg-8">
            <div class="card mb-4">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-cog me-2"></i>
                        Default Approval Thresholds
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        Configure the default approval requirements for all outlets. These settings apply unless
                        an outlet-specific override is configured.
                    </p>

                    <form method="POST" id="defaultThresholdsForm">
                        <input type="hidden" name="action" value="save_default_thresholds">

                        <?php for ($tier = 1; $tier <= 5; $tier++):
                            $tierData = $defaultThresholds[$tier] ?? [
                                'min_amount' => ($tier - 1) * 1000,
                                'max_amount' => $tier * 1000,
                                'required_approvers' => 1,
                                'roles' => []
                            ];
                        ?>
                            <div class="card mb-3 border-info">
                                <div class="card-header bg-info text-white">
                                    <strong>Tier <?= $tier ?></strong>
                                </div>
                                <div class="card-body">
                                    <div class="row g-3">
                                        <div class="col-md-3">
                                            <label class="form-label">Min Amount ($)</label>
                                            <input type="number"
                                                   name="tier_<?= $tier ?>_min"
                                                   class="form-control"
                                                   step="0.01"
                                                   min="0"
                                                   value="<?= $tierData['min_amount'] ?>"
                                                   required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Max Amount ($)</label>
                                            <input type="number"
                                                   name="tier_<?= $tier ?>_max"
                                                   class="form-control"
                                                   step="0.01"
                                                   min="0"
                                                   value="<?= $tierData['max_amount'] == PHP_FLOAT_MAX ? '' : $tierData['max_amount'] ?>"
                                                   placeholder="No limit">
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Required Approvers</label>
                                            <input type="number"
                                                   name="tier_<?= $tier ?>_approvers"
                                                   class="form-control"
                                                   min="1"
                                                   max="10"
                                                   value="<?= $tierData['required_approvers'] ?>"
                                                   required>
                                        </div>
                                        <div class="col-md-3">
                                            <label class="form-label">Roles</label>
                                            <select name="tier_<?= $tier ?>_roles[]"
                                                    class="form-select"
                                                    multiple
                                                    size="3">
                                                <option value="manager" <?= in_array('manager', $tierData['roles']) ? 'selected' : '' ?>>
                                                    Manager
                                                </option>
                                                <option value="finance" <?= in_array('finance', $tierData['roles']) ? 'selected' : '' ?>>
                                                    Finance
                                                </option>
                                                <option value="admin" <?= in_array('admin', $tierData['roles']) ? 'selected' : '' ?>>
                                                    Admin
                                                </option>
                                            </select>
                                            <small class="text-muted">Hold Ctrl to select multiple</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endfor; ?>

                        <div class="text-end">
                            <button type="submit" class="btn btn-primary btn-lg">
                                <i class="fas fa-save me-2"></i>Save Default Thresholds
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Outlet-Specific Overrides -->
            <div class="card mb-4">
                <div class="card-header bg-warning text-dark">
                    <h5 class="mb-0">
                        <i class="fas fa-building me-2"></i>
                        Outlet-Specific Overrides
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-4">
                        Configure custom approval thresholds for specific outlets. These override the default settings.
                    </p>

                    <!-- Existing Overrides -->
                    <?php if (!empty($outletOverrides)): ?>
                        <div class="mb-4">
                            <h6>Current Overrides:</h6>
                            <div class="list-group">
                                <?php foreach ($outletOverrides as $override): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex justify-content-between align-items-center">
                                            <div>
                                                <strong><?= htmlspecialchars($override['outlet_name']) ?></strong>
                                                <br>
                                                <small class="text-muted">
                                                    Last updated: <?= date('Y-m-d H:i', strtotime($override['updated_at'] ?? $override['created_at'])) ?>
                                                </small>
                                            </div>
                                            <div>
                                                <button class="btn btn-sm btn-outline-primary edit-override-btn"
                                                        data-outlet-id="<?= $override['outlet_id'] ?>"
                                                        data-outlet-name="<?= htmlspecialchars($override['outlet_name']) ?>"
                                                        data-thresholds='<?= htmlspecialchars($override['thresholds']) ?>'>
                                                    <i class="fas fa-edit me-1"></i>Edit
                                                </button>
                                                <form method="POST" class="d-inline"
                                                      onsubmit="return confirm('Delete this outlet override?');">
                                                    <input type="hidden" name="action" value="delete_outlet_override">
                                                    <input type="hidden" name="outlet_id" value="<?= $override['outlet_id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger">
                                                        <i class="fas fa-trash me-1"></i>Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    <?php endif; ?>

                    <!-- Add New Override Button -->
                    <button class="btn btn-success" data-bs-toggle="modal" data-bs-target="#outletOverrideModal">
                        <i class="fas fa-plus me-2"></i>Add Outlet Override
                    </button>
                </div>
            </div>
        </div>

        <!-- Test Calculator -->
        <div class="col-lg-4">
            <div class="card sticky-top" style="top: 20px;">
                <div class="card-header bg-success text-white">
                    <h5 class="mb-0">
                        <i class="fas fa-calculator me-2"></i>
                        Test Calculator
                    </h5>
                </div>
                <div class="card-body">
                    <p class="text-muted mb-3">
                        Enter a purchase order amount to see which approval tier and requirements apply.
                    </p>

                    <div class="mb-3">
                        <label class="form-label">Test Amount ($)</label>
                        <input type="number"
                               id="testAmount"
                               class="form-control form-control-lg"
                               step="0.01"
                               min="0"
                               placeholder="e.g., 2500.00">
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Outlet (optional)</label>
                        <select id="testOutlet" class="form-select">
                            <option value="">Default Settings</option>
                            <?php foreach ($outlets as $outlet): ?>
                                <option value="<?= $outlet['id'] ?>">
                                    <?= htmlspecialchars($outlet['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <button id="calculateBtn" class="btn btn-success w-100 mb-3">
                        <i class="fas fa-play me-2"></i>Calculate
                    </button>

                    <!-- Result Display -->
                    <div id="testResult" class="d-none">
                        <div class="alert alert-info">
                            <h6 class="alert-heading">
                                <i class="fas fa-info-circle me-2"></i>
                                Approval Requirements
                            </h6>
                            <hr>
                            <div id="resultContent"></div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Help Card -->
            <div class="card mt-4">
                <div class="card-header bg-info text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-question-circle me-2"></i>
                        How It Works
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="mb-0">
                        <li class="mb-2">Configure 5 approval tiers based on PO value</li>
                        <li class="mb-2">Set how many approvers needed per tier</li>
                        <li class="mb-2">Assign roles that can approve each tier</li>
                        <li class="mb-2">Create outlet-specific overrides when needed</li>
                        <li>Use test calculator to verify settings</li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Outlet Override Modal -->
<div class="modal fade" id="outletOverrideModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Configure Outlet Override</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="outletOverrideForm">
                <input type="hidden" name="action" value="save_outlet_override">
                <div class="modal-body">
                    <div class="mb-4">
                        <label class="form-label">Select Outlet</label>
                        <select name="outlet_id" class="form-select" required>
                            <option value="">Choose outlet...</option>
                            <?php foreach ($outlets as $outlet): ?>
                                <option value="<?= $outlet['id'] ?>">
                                    <?= htmlspecialchars($outlet['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <?php for ($tier = 1; $tier <= 5; $tier++): ?>
                        <div class="card mb-3 border-warning">
                            <div class="card-header bg-warning">
                                <strong>Tier <?= $tier ?></strong>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label class="form-label">Min Amount ($)</label>
                                        <input type="number"
                                               name="outlet_tier_<?= $tier ?>_min"
                                               class="form-control"
                                               step="0.01"
                                               min="0"
                                               value="<?= ($tier - 1) * 1000 ?>"
                                               required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Max Amount ($)</label>
                                        <input type="number"
                                               name="outlet_tier_<?= $tier ?>_max"
                                               class="form-control"
                                               step="0.01"
                                               min="0"
                                               placeholder="No limit">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Required Approvers</label>
                                        <input type="number"
                                               name="outlet_tier_<?= $tier ?>_approvers"
                                               class="form-control"
                                               min="1"
                                               max="10"
                                               value="1"
                                               required>
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Roles</label>
                                        <select name="outlet_tier_<?= $tier ?>_roles[]"
                                                class="form-select"
                                                multiple
                                                size="3">
                                            <option value="manager">Manager</option>
                                            <option value="finance">Finance</option>
                                            <option value="admin">Admin</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    <?php endfor; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-save me-2"></i>Save Override
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Test Calculator
    const calculateBtn = document.getElementById('calculateBtn');
    const testAmount = document.getElementById('testAmount');
    const testOutlet = document.getElementById('testOutlet');
    const testResult = document.getElementById('testResult');
    const resultContent = document.getElementById('resultContent');

    calculateBtn.addEventListener('click', async function() {
        const amount = parseFloat(testAmount.value);

        if (isNaN(amount) || amount < 0) {
            alert('Please enter a valid amount');
            return;
        }

        // Get current threshold configuration from form
        const thresholds = <?= json_encode($defaultThresholds) ?>;

        // Find matching tier
        let matchingTier = null;
        for (let tier = 1; tier <= 5; tier++) {
            const tierData = thresholds[tier];
            const min = parseFloat(tierData.min_amount);
            const max = tierData.max_amount === null ? Infinity : parseFloat(tierData.max_amount);

            if (amount >= min && amount <= max) {
                matchingTier = {
                    tier: tier,
                    ...tierData
                };
                break;
            }
        }

        if (matchingTier) {
            resultContent.innerHTML = `
                <p class="mb-2"><strong>Tier:</strong> ${matchingTier.tier}</p>
                <p class="mb-2"><strong>Amount Range:</strong> $${matchingTier.min_amount.toFixed(2)} - ${matchingTier.max_amount === null || matchingTier.max_amount == Infinity ? 'No limit' : '$' + matchingTier.max_amount.toFixed(2)}</p>
                <p class="mb-2"><strong>Required Approvers:</strong> ${matchingTier.required_approvers}</p>
                <p class="mb-0"><strong>Approved By:</strong> ${matchingTier.roles.join(', ') || 'Any role'}</p>
            `;
            testResult.classList.remove('d-none');
        } else {
            resultContent.innerHTML = '<p class="mb-0 text-danger">No matching tier found. Please check your threshold configuration.</p>';
            testResult.classList.remove('d-none');
        }
    });

    // Edit override button
    document.querySelectorAll('.edit-override-btn').forEach(btn => {
        btn.addEventListener('click', function() {
            const outletId = this.dataset.outletId;
            const outletName = this.dataset.outletName;
            const thresholds = JSON.parse(this.dataset.thresholds);

            // Populate modal form
            const form = document.getElementById('outletOverrideForm');
            form.querySelector('select[name="outlet_id"]').value = outletId;

            // Populate tier data
            for (let tier = 1; tier <= 5; tier++) {
                const tierData = thresholds[tier] || {};
                form.querySelector(`input[name="outlet_tier_${tier}_min"]`).value = tierData.min_amount || 0;
                form.querySelector(`input[name="outlet_tier_${tier}_max"]`).value = tierData.max_amount === null ? '' : tierData.max_amount;
                form.querySelector(`input[name="outlet_tier_${tier}_approvers"]`).value = tierData.required_approvers || 1;

                // Select roles
                const roleSelect = form.querySelector(`select[name="outlet_tier_${tier}_roles[]"]`);
                Array.from(roleSelect.options).forEach(option => {
                    option.selected = (tierData.roles || []).includes(option.value);
                });
            }

            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('outletOverrideModal'));
            modal.show();
        });
    });
});
</script>

<?php
// Include footer
include $_SERVER['DOCUMENT_ROOT'] . '/modules/consignments/shared/blocks/footer.php';
?>
