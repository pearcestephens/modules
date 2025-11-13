<?php
/**
 * Staff Accounts Module - CONVERTED TO BASE TEMPLATE
 *
 * Now uses VapeUltra base template system
 *
 * @package CIS\Modules\StaffAccounts
 * @version 4.0.0 - ULTRA EDITION
 */

// Load module bootstrap
require_once __DIR__ . '/bootstrap.php';
require_once __DIR__ . '/../base/Template/Renderer.php';
require_once __DIR__ . '/../base/middleware/MiddlewarePipeline.php';

use App\Template\Renderer;
use App\Middleware\MiddlewarePipeline;
use CIS\Modules\StaffAccounts\StaffAccountService;
use CIS\Modules\StaffAccounts\PaymentService;

// Create authenticated middleware pipeline
$pipeline = MiddlewarePipeline::createAuthenticated();

// Execute pipeline
$pipeline->handle($_REQUEST, function($request) {

    // Resolve database connection
    $pdo = cis_resolve_pdo();

    // Start output buffering for module content
    ob_start();

    // Get all staff accounts with balances
    $stmt = $pdo->query("
        SELECT
            u.id,
            vu.username,
            u.email,
            CONCAT(u.first_name, ' ', u.last_name) as full_name,
            vc.id as vend_customer_id,
            vc.customer_code,
            vc.balance as vend_balance,
            vc.customer_group_id,
            (SELECT COUNT(*) FROM sales_payments WHERE vend_customer_id = vc.id) as payment_count,
            (SELECT MAX(payment_date) FROM sales_payments WHERE vend_customer_id = vc.id) as last_payment_date
        FROM users u
        LEFT JOIN vend_users vu ON u.vend_id = vu.id
        LEFT JOIN vend_customers vc ON u.vend_customer_account = vc.id
        WHERE u.staff_active = 1
        ORDER BY vc.balance DESC
    ");
    $staff_accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Calculate statistics
    $total_balance = array_sum(array_column($staff_accounts, 'vend_balance'));
    $total_accounts = count($staff_accounts);
    $accounts_with_balance = count(array_filter($staff_accounts, fn($a) => $a['vend_balance'] > 0));
    $accounts_with_debt = count(array_filter($staff_accounts, fn($a) => $a['vend_balance'] < 0));

    ?>

    <div class="container-fluid">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h1>Staff Accounts</h1>
                <p class="text-muted">Manage staff customer accounts and payment tracking</p>
            </div>
            <button class="btn btn-primary" onclick="createNewStaffAccount()">
                <i class="bi bi-plus-circle"></i>
                New Account
            </button>
        </div>

        <!-- Statistics Grid -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <p class="text-muted mb-1">Total Accounts</p>
                                <h3 class="mb-0"><?= $total_accounts ?></h3>
                            </div>
                            <div class="text-primary fs-2">
                                <i class="bi bi-people"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <p class="text-muted mb-1">Total Balance</p>
                                <h3 class="mb-0">$<?= number_format($total_balance, 2) ?></h3>
                            </div>
                            <div class="text-success fs-2">
                                <i class="bi bi-cash-coin"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <p class="text-muted mb-1">With Balance</p>
                                <h3 class="mb-0"><?= $accounts_with_balance ?></h3>
                            </div>
                            <div class="text-info fs-2">
                                <i class="bi bi-wallet2"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-3">
                <div class="card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <p class="text-muted mb-1">With Debt</p>
                                <h3 class="mb-0"><?= $accounts_with_debt ?></h3>
                            </div>
                            <div class="text-danger fs-2">
                                <i class="bi bi-exclamation-triangle"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Accounts Table -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">All Staff Accounts</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Customer Code</th>
                                <th>Balance</th>
                                <th>Payments</th>
                                <th>Last Payment</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($staff_accounts as $account): ?>
                            <tr>
                                <td>
                                    <strong><?= htmlspecialchars($account['full_name']) ?></strong>
                                </td>
                                <td><?= htmlspecialchars($account['email']) ?></td>
                                <td>
                                    <span class="badge bg-secondary"><?= htmlspecialchars($account['customer_code'] ?? 'N/A') ?></span>
                                </td>
                                <td>
                                    <span class="badge <?= $account['vend_balance'] < 0 ? 'bg-danger' : 'bg-success' ?>">
                                        $<?= number_format($account['vend_balance'] ?? 0, 2) ?>
                                    </span>
                                </td>
                                <td><?= $account['payment_count'] ?></td>
                                <td><?= $account['last_payment_date'] ? date('M d, Y', strtotime($account['last_payment_date'])) : 'Never' ?></td>
                                <td>
                                    <button class="btn btn-sm btn-primary" onclick="viewAccount(<?= $account['id'] ?>)">
                                        <i class="bi bi-eye"></i>
                                    </button>
                                    <button class="btn btn-sm btn-success" onclick="recordPayment(<?= $account['id'] ?>)">
                                        <i class="bi bi-cash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <?php
    $moduleContent = ob_get_clean();

    // Render with VapeUltra base template
    $renderer = new Renderer();
    $renderer->render($moduleContent, [
        'title' => 'Staff Accounts - Vape Shed CIS Ultra',
        'class' => 'page-staff-accounts',
        'layout' => 'main',
        'scripts' => [
            '/modules/staff-accounts/assets/js/staff-accounts.js',
        ],
        'styles' => [
            '/modules/staff-accounts/assets/css/staff-accounts.css',
        ],
        'inline_scripts' => "
            VapeUltra.Core.registerModule('StaffAccounts', {
                init: function() {
                    console.log('✅ Staff Accounts module initialized');
                }
            });

            function viewAccount(id) {
                window.location.href = '/modules/staff-accounts/view.php?id=' + id;
            }

            function recordPayment(id) {
                window.location.href = '/modules/staff-accounts/payment.php?user_id=' + id;
            }

            function createNewStaffAccount() {
                window.location.href = '/modules/staff-accounts/create.php';
            }
        ",
        'nav_items' => [
            'staff-accounts' => [
                'title' => 'Staff Accounts',
                'items' => [
                    ['icon' => 'house-door', 'label' => 'Dashboard', 'href' => '/modules/staff-accounts/', 'badge' => null],
                    ['icon' => 'people', 'label' => 'All Accounts', 'href' => '/modules/staff-accounts/', 'badge' => $total_accounts],
                    ['icon' => 'cash-coin', 'label' => 'Payments', 'href' => '/modules/staff-accounts/payments.php', 'badge' => null],
                    ['icon' => 'graph-up', 'label' => 'Analytics', 'href' => '/modules/staff-accounts/analytics.php', 'badge' => null],
                ]
            ]
        ]
    ]);

});
