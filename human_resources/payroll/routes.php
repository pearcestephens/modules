<?php
/**
 * Payroll API Routes Configuration
 *
 * Define all API endpoints for the payroll module
 *
 * Usage in router:
 * $routes = require __DIR__ . '/routes.php';
 *
 * @package PayrollModule
 * @version 1.0.0
 */

return [

    // =====================================================================
    // AMENDMENT ENDPOINTS
    // =====================================================================

    'POST /api/payroll/amendments/create' => [
        'controller' => 'AmendmentController',
        'action' => 'create',
        'auth' => true,
        'csrf' => true,
        'description' => 'Create a new timesheet amendment'
    ],

    'GET /api/payroll/amendments/:id' => [
        'controller' => 'AmendmentController',
        'action' => 'view',
        'auth' => true,
        'description' => 'Get amendment details by ID'
    ],

    'POST /api/payroll/amendments/:id/approve' => [
        'controller' => 'AmendmentController',
        'action' => 'approve',
        'auth' => true,
        'csrf' => true,
        'permission' => 'payroll.approve_amendments',
        'description' => 'Approve an amendment'
    ],

    'POST /api/payroll/amendments/:id/decline' => [
        'controller' => 'AmendmentController',
        'action' => 'decline',
        'auth' => true,
        'csrf' => true,
        'permission' => 'payroll.approve_amendments',
        'description' => 'Decline an amendment'
    ],

    'GET /api/payroll/amendments/pending' => [
        'controller' => 'AmendmentController',
        'action' => 'pending',
        'auth' => true,
        'description' => 'Get all pending amendments'
    ],

    'GET /api/payroll/amendments/history' => [
        'controller' => 'AmendmentController',
        'action' => 'history',
        'auth' => true,
        'description' => 'Get amendment history for a staff member'
    ],

    // =====================================================================
    // AUTOMATION ENDPOINTS
    // =====================================================================

    'GET /api/payroll/automation/dashboard' => [
        'controller' => 'PayrollAutomationController',
        'action' => 'dashboard',
        'auth' => true,
        'description' => 'Get automation dashboard statistics'
    ],

    'GET /api/payroll/automation/reviews/pending' => [
        'controller' => 'PayrollAutomationController',
        'action' => 'pendingReviews',
        'auth' => true,
        'description' => 'Get pending AI reviews'
    ],

    'POST /api/payroll/automation/process' => [
        'controller' => 'PayrollAutomationController',
        'action' => 'processNow',
        'auth' => true,
        'csrf' => true,
        'permission' => 'payroll.admin',
        'description' => 'Manually trigger automation processing'
    ],

    'GET /api/payroll/automation/rules' => [
        'controller' => 'PayrollAutomationController',
        'action' => 'rules',
        'auth' => true,
        'description' => 'Get active AI rules'
    ],

    'GET /api/payroll/automation/stats' => [
        'controller' => 'PayrollAutomationController',
        'action' => 'stats',
        'auth' => true,
        'description' => 'Get automation statistics'
    ],

    // =====================================================================
    // XERO ENDPOINTS
    // =====================================================================

    'POST /api/payroll/xero/payrun/create' => [
        'controller' => 'XeroController',
        'action' => 'createPayRun',
        'auth' => true,
        'csrf' => true,
        'permission' => 'payroll.create_payruns',
        'description' => 'Create a Xero pay run'
    ],

    'GET /api/payroll/xero/payrun/:id' => [
        'controller' => 'XeroController',
        'action' => 'getPayRun',
        'auth' => true,
        'description' => 'Get Xero pay run details'
    ],

    'POST /api/payroll/xero/payments/batch' => [
        'controller' => 'XeroController',
        'action' => 'createBatchPayments',
        'auth' => true,
        'csrf' => true,
        'permission' => 'payroll.create_payments',
        'description' => 'Create batch bank payments'
    ],

    'GET /api/payroll/xero/oauth/authorize' => [
        'controller' => 'XeroController',
        'action' => 'authorize',
        'auth' => true,
        'permission' => 'payroll.admin',
        'description' => 'Initiate Xero OAuth flow'
    ],

    'GET /api/payroll/xero/oauth/callback' => [
        'controller' => 'XeroController',
        'action' => 'oauthCallback',
        'auth' => false, // Xero will redirect here
        'description' => 'Xero OAuth callback handler'
    ],

    // =====================================================================
    // WAGE DISCREPANCY ENDPOINTS
    // =====================================================================

    'POST /api/payroll/discrepancies/submit' => [
        'controller' => 'WageDiscrepancyController',
        'action' => 'submit',
        'auth' => true,
        'csrf' => true,
        'permission' => 'payroll.submit_discrepancy',
        'description' => 'Submit a new wage discrepancy'
    ],

    'GET /api/payroll/discrepancies/:id' => [
        'controller' => 'WageDiscrepancyController',
        'action' => 'getDiscrepancy',
        'auth' => true,
        'permission' => 'payroll.view_discrepancy',
        'description' => 'Get discrepancy details (staff: own only, admin: all)'
    ],

    'GET /api/payroll/discrepancies/pending' => [
        'controller' => 'WageDiscrepancyController',
        'action' => 'getPending',
        'auth' => true,
        'permission' => 'payroll.manage_discrepancies',
        'description' => 'Get all pending discrepancies (admin only)'
    ],

    'GET /api/payroll/discrepancies/my-history' => [
        'controller' => 'WageDiscrepancyController',
        'action' => 'getMyHistory',
        'auth' => true,
        'permission' => 'payroll.view_discrepancy',
        'description' => 'Get my discrepancy history (staff)'
    ],

    'POST /api/payroll/discrepancies/:id/approve' => [
        'controller' => 'WageDiscrepancyController',
        'action' => 'approve',
        'auth' => true,
        'csrf' => true,
        'permission' => 'payroll.manage_discrepancies',
        'description' => 'Approve a discrepancy (admin only)'
    ],

    'POST /api/payroll/discrepancies/:id/decline' => [
        'controller' => 'WageDiscrepancyController',
        'action' => 'decline',
        'auth' => true,
        'csrf' => true,
        'permission' => 'payroll.manage_discrepancies',
        'description' => 'Decline a discrepancy (admin only)'
    ],

    'POST /api/payroll/discrepancies/:id/upload-evidence' => [
        'controller' => 'WageDiscrepancyController',
        'action' => 'uploadEvidence',
        'auth' => true,
        'csrf' => true,
        'permission' => 'payroll.submit_discrepancy',
        'description' => 'Upload evidence file (staff: own only, admin: all)'
    ],

    'GET /api/payroll/discrepancies/statistics' => [
        'controller' => 'WageDiscrepancyController',
        'action' => 'getStatistics',
        'auth' => true,
        'permission' => 'payroll.manage_discrepancies',
        'description' => 'Get discrepancy statistics (admin only)'
    ],

    // ============================================================================
    // BONUS ROUTES
    // ============================================================================

    'GET /api/payroll/bonuses/pending' => [
        'controller' => 'BonusController',
        'action' => 'getPending',
        'auth' => true,
        'description' => 'Get pending bonuses (admin: all, staff: own)'
    ],

    'GET /api/payroll/bonuses/history' => [
        'controller' => 'BonusController',
        'action' => 'getHistory',
        'auth' => true,
        'description' => 'Get bonus history'
    ],

    'POST /api/payroll/bonuses/create' => [
        'controller' => 'BonusController',
        'action' => 'create',
        'auth' => true,
        'csrf' => true,
        'permission' => 'payroll.create_bonus',
        'description' => 'Create manual bonus'
    ],

    'POST /api/payroll/bonuses/:id/approve' => [
        'controller' => 'BonusController',
        'action' => 'approve',
        'auth' => true,
        'csrf' => true,
        'permission' => 'payroll.approve_bonus',
        'description' => 'Approve bonus'
    ],

    'POST /api/payroll/bonuses/:id/decline' => [
        'controller' => 'BonusController',
        'action' => 'decline',
        'auth' => true,
        'csrf' => true,
        'permission' => 'payroll.approve_bonus',
        'description' => 'Decline bonus'
    ],

    'GET /api/payroll/bonuses/summary' => [
        'controller' => 'BonusController',
        'action' => 'getSummary',
        'auth' => true,
        'description' => 'Get staff bonus summary'
    ],

    'GET /api/payroll/bonuses/vape-drops' => [
        'controller' => 'BonusController',
        'action' => 'getVapeDrops',
        'auth' => true,
        'description' => 'Get vape drops for period'
    ],

    'GET /api/payroll/bonuses/google-reviews' => [
        'controller' => 'BonusController',
        'action' => 'getGoogleReviews',
        'auth' => true,
        'description' => 'Get Google review bonuses for period'
    ],

    // ============================================================================
    // VEND PAYMENT ROUTES
    // ============================================================================

    'GET /api/payroll/vend-payments/pending' => [
        'controller' => 'VendPaymentController',
        'action' => 'getPending',
        'auth' => true,
        'description' => 'Get pending Vend payment requests (admin: all, staff: own)'
    ],

    'GET /api/payroll/vend-payments/history' => [
        'controller' => 'VendPaymentController',
        'action' => 'getHistory',
        'auth' => true,
        'description' => 'Get Vend payment history'
    ],

    'GET /api/payroll/vend-payments/:id/allocations' => [
        'controller' => 'VendPaymentController',
        'action' => 'getAllocations',
        'auth' => true,
        'description' => 'Get payment allocations for a request'
    ],

    'POST /api/payroll/vend-payments/:id/approve' => [
        'controller' => 'VendPaymentController',
        'action' => 'approve',
        'auth' => true,
        'csrf' => true,
        'permission' => 'payroll.approve_vend_payments',
        'description' => 'Approve Vend payment request'
    ],

    'POST /api/payroll/vend-payments/:id/decline' => [
        'controller' => 'VendPaymentController',
        'action' => 'decline',
        'auth' => true,
        'csrf' => true,
        'permission' => 'payroll.approve_vend_payments',
        'description' => 'Decline Vend payment request'
    ],

    'GET /api/payroll/vend-payments/statistics' => [
        'controller' => 'VendPaymentController',
        'action' => 'getStatistics',
        'auth' => true,
        'permission' => 'payroll.admin',
        'description' => 'Get Vend payment statistics (admin only)'
    ],

    // ============================================================================
    // LEAVE ROUTES
    // ============================================================================

    'GET /api/payroll/leave/pending' => [
        'controller' => 'LeaveController',
        'action' => 'getPending',
        'auth' => true,
        'description' => 'Get pending leave requests (admin: all, staff: own)'
    ],

    'GET /api/payroll/leave/history' => [
        'controller' => 'LeaveController',
        'action' => 'getHistory',
        'auth' => true,
        'description' => 'Get leave history'
    ],

    'POST /api/payroll/leave/create' => [
        'controller' => 'LeaveController',
        'action' => 'create',
        'auth' => true,
        'csrf' => true,
        'description' => 'Create leave request'
    ],

    'POST /api/payroll/leave/:id/approve' => [
        'controller' => 'LeaveController',
        'action' => 'approve',
        'auth' => true,
        'csrf' => true,
        'permission' => 'payroll.approve_leave',
        'description' => 'Approve leave request'
    ],

    'POST /api/payroll/leave/:id/decline' => [
        'controller' => 'LeaveController',
        'action' => 'decline',
        'auth' => true,
        'csrf' => true,
        'permission' => 'payroll.approve_leave',
        'description' => 'Decline leave request'
    ],

    'GET /api/payroll/leave/balances' => [
        'controller' => 'LeaveController',
        'action' => 'getBalances',
        'auth' => true,
        'description' => 'Get leave balances for staff member'
    ],

    // ========================================
    // DASHBOARD ROUTES
    // ========================================

    'GET /payroll/dashboard' => [
        'controller' => 'DashboardController',
        'action' => 'index',
        'auth' => true,
        // 'permission' => 'payroll.view_dashboard', // TEMPORARILY DISABLED FOR TESTING
        'description' => 'Main payroll dashboard with all sections'
    ],

    'GET /api/payroll/dashboard/data' => [
        'controller' => 'DashboardController',
        'action' => 'getData',
        'auth' => true,
        // 'permission' => 'payroll.view_dashboard', // TEMPORARILY DISABLED FOR TESTING
        'description' => 'Get aggregated dashboard data'
    ],

    // ========================================
    // PAY RUN ROUTES
    // ========================================

    'GET /payroll/payruns' => [
        'controller' => 'PayRunController',
        'action' => 'index',
        'auth' => true,
        // 'permission' => 'payroll.view_payruns', // TEMPORARILY DISABLED FOR TESTING
        'description' => 'Pay run list view'
    ],

    'GET /payroll/payrun/:periodKey' => [
        'controller' => 'PayRunController',
        'action' => 'view',
        'auth' => true,
        // 'permission' => 'payroll.view_payruns', // TEMPORARILY DISABLED FOR TESTING
        'description' => 'Pay run detail view'
    ],

    'GET /api/payroll/payruns/list' => [
        'controller' => 'PayRunController',
        'action' => 'list',
        'auth' => true,
        'permission' => 'payroll.view_payruns',
        'description' => 'Get pay runs list (AJAX)'
    ],

    'POST /api/payroll/payruns/create' => [
        'controller' => 'PayRunController',
        'action' => 'create',
        'auth' => true,
        'csrf' => true,
        'permission' => 'payroll.create_payruns',
        'description' => 'Create new pay run'
    ],

    'POST /api/payroll/payruns/:periodKey/approve' => [
        'controller' => 'PayRunController',
        'action' => 'approve',
        'auth' => true,
        'csrf' => true,
        'permission' => 'payroll.approve_payruns',
        'description' => 'Approve pay run'
    ],

    'GET /api/payroll/payruns/:periodKey/export' => [
        'controller' => 'PayRunController',
        'action' => 'export',
        'auth' => true,
        'permission' => 'payroll.export_payruns',
        'description' => 'Export pay run to Xero'
    ],

    'POST /api/payroll/payruns/:periodKey/print' => [
        'controller' => 'PayRunController',
        'action' => 'print',
        'auth' => true,
        'permission' => 'payroll.view_payruns',
        'description' => 'Generate printable pay run PDF'
    ],

];
