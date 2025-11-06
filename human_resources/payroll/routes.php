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
    // WEB UI ROUTES (Views)
    // =====================================================================

    'GET /' => [
        'controller' => 'DashboardController',
        'action' => 'index',
        'auth' => true,
        'description' => 'Payroll dashboard home page'
    ],

    'GET /dashboard' => [
        'controller' => 'DashboardController',
        'action' => 'index',
        'auth' => true,
        'description' => 'Payroll dashboard home page'
    ],

    'GET /payruns' => [
        'controller' => 'PayRunController',
        'action' => 'index',
        'auth' => true,
        'description' => 'Pay runs list page'
    ],

    'GET /payruns/:id' => [
        'controller' => 'PayRunController',
        'action' => 'view',
        'auth' => true,
        'description' => 'Pay run detail page'
    ],

    'GET /reconciliation' => [
        'controller' => 'ReconciliationController',
        'action' => 'index',
        'auth' => true,
        'description' => 'Reconciliation dashboard view'
    ],

    'GET /health' => [
        'action' => function() {
            header('Content-Type: application/json');
            echo json_encode([
                'status' => 'healthy',
                'module' => 'payroll',
                'timestamp' => date('c'),
                'version' => '3.0'
            ]);
        },
        'auth' => false,
        'description' => 'System health check'
    ],

    // =====================================================================
    // BOT API ENDPOINTS (Token-based auth, NO session required)
    // =====================================================================

    'GET /api/bot/events' => [
        'controller' => 'BotController',
        'action' => 'events',
        'auth' => false,  // Bot token validated in controller
        'description' => 'Bot event polling endpoint'
    ],

    'POST /api/bot/actions' => [
        'controller' => 'BotController',
        'action' => 'executeAction',
        'auth' => false,  // Bot token validated in controller
        'csrf' => false,  // Bot uses token, not CSRF
        'description' => 'Bot action execution endpoint'
    ],

    'GET /api/bot/context' => [
        'controller' => 'BotController',
        'action' => 'getContext',
        'auth' => false,  // Bot token validated in controller
        'description' => 'Bot decision context endpoint'
    ],

    'POST /api/bot/status' => [
        'controller' => 'BotController',
        'action' => 'reportStatus',
        'auth' => false,  // Bot token validated in controller
        'csrf' => false,
        'description' => 'Bot heartbeat/status reporting'
    ],

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

    // Specific routes MUST come before parameterized routes
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

    'GET /api/payroll/discrepancies/statistics' => [
        'controller' => 'WageDiscrepancyController',
        'action' => 'getStatistics',
        'auth' => true,
        'permission' => 'payroll.manage_discrepancies',
        'description' => 'Get discrepancy statistics (admin only)'
    ],

    // Parameterized route comes AFTER specific routes
    'GET /api/payroll/discrepancies/:id' => [
        'controller' => 'WageDiscrepancyController',
        'action' => 'getDiscrepancy',
        'auth' => true,
        'permission' => 'payroll.view_discrepancy',
        'description' => 'Get discrepancy details (staff: own only, admin: all)'
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
        'permission' => 'payroll.view_dashboard',
        'description' => 'Main payroll dashboard with all sections'
    ],

    'GET /api/payroll/dashboard/data' => [
        'controller' => 'DashboardController',
        'action' => 'getData',
        'auth' => true,
        'permission' => 'payroll.view_dashboard',
        'description' => 'Get aggregated dashboard data'
    ],

    // ========================================
    // PAY RUN ROUTES
    // ========================================

    'GET /payroll/payruns' => [
        'controller' => 'PayRunController',
        'action' => 'index',
        'auth' => true,
        'permission' => 'payroll.view_payruns',
        'description' => 'Pay run list view'
    ],

    'GET /payroll/payrun/:periodKey' => [
        'controller' => 'PayRunController',
        'action' => 'view',
        'auth' => true,
        'permission' => 'payroll.view_payruns',
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

    // =====================================================================
    // RECONCILIATION ENDPOINTS
    // =====================================================================

    'GET /payroll/reconciliation' => [
        'controller' => 'ReconciliationController',
        'action' => 'index',
        'auth' => true,
        'permission' => 'payroll.view_reconciliation',
        'description' => 'Reconciliation dashboard view'
    ],

    'GET /api/payroll/reconciliation/dashboard' => [
        'controller' => 'ReconciliationController',
        'action' => 'dashboard',
        'auth' => true,
        'permission' => 'payroll.view_reconciliation',
        'description' => 'Get reconciliation dashboard data'
    ],

    'GET /api/payroll/reconciliation/variances' => [
        'controller' => 'ReconciliationController',
        'action' => 'getVariances',
        'auth' => true,
        'permission' => 'payroll.view_reconciliation',
        'description' => 'Get current variances'
    ],

    'GET /api/payroll/reconciliation/compare/:runId' => [
        'controller' => 'ReconciliationController',
        'action' => 'compareRun',
        'auth' => true,
        'permission' => 'payroll.view_reconciliation',
        'description' => 'Compare CIS vs Xero for specific run'
    ],

    // ========================================
    // VEND CONSIGNMENT MANAGEMENT API
    // ========================================

    // CONSIGNMENT CRUD OPERATIONS
    'POST /api/vend/consignments/create' => [
        'controller' => 'VendConsignmentController',
        'action' => 'create',
        'auth' => true,
        'csrf' => true,
        'permission' => 'payroll.manage_consignments',
        'description' => 'Create new Vend consignment'
    ],

    'GET /api/vend/consignments/:id' => [
        'controller' => 'VendConsignmentController',
        'action' => 'get',
        'auth' => true,
        'permission' => 'payroll.view_consignments',
        'description' => 'Get consignment details with products'
    ],

    'GET /api/vend/consignments/list' => [
        'controller' => 'VendConsignmentController',
        'action' => 'listConsignments',
        'auth' => true,
        'permission' => 'payroll.view_consignments',
        'description' => 'List consignments with filters (type, status, outlet)'
    ],

    'PUT /api/vend/consignments/:id' => [
        'controller' => 'VendConsignmentController',
        'action' => 'update',
        'auth' => true,
        'csrf' => true,
        'permission' => 'payroll.manage_consignments',
        'description' => 'Update consignment details (name, due_at, reference)'
    ],

    'PATCH /api/vend/consignments/:id/status' => [
        'controller' => 'VendConsignmentController',
        'action' => 'updateStatus',
        'auth' => true,
        'csrf' => true,
        'permission' => 'payroll.manage_consignments',
        'description' => 'Update consignment status (OPEN, SENT, RECEIVED, etc.)'
    ],

    'DELETE /api/vend/consignments/:id' => [
        'controller' => 'VendConsignmentController',
        'action' => 'delete',
        'auth' => true,
        'csrf' => true,
        'permission' => 'payroll.manage_consignments',
        'description' => 'Delete consignment'
    ],

    // CONSIGNMENT PRODUCT MANAGEMENT
    'POST /api/vend/consignments/:id/products' => [
        'controller' => 'VendConsignmentController',
        'action' => 'addProduct',
        'auth' => true,
        'csrf' => true,
        'permission' => 'payroll.manage_consignments',
        'description' => 'Add product to consignment'
    ],

    'GET /api/vend/consignments/:id/products' => [
        'controller' => 'VendConsignmentController',
        'action' => 'listProducts',
        'auth' => true,
        'permission' => 'payroll.view_consignments',
        'description' => 'List all products in consignment'
    ],

    'PUT /api/vend/consignments/:id/products/:pid' => [
        'controller' => 'VendConsignmentController',
        'action' => 'updateProduct',
        'auth' => true,
        'csrf' => true,
        'permission' => 'payroll.manage_consignments',
        'description' => 'Update product in consignment (count, cost, received)'
    ],

    'DELETE /api/vend/consignments/:id/products/:pid' => [
        'controller' => 'VendConsignmentController',
        'action' => 'deleteProduct',
        'auth' => true,
        'csrf' => true,
        'permission' => 'payroll.manage_consignments',
        'description' => 'Remove product from consignment'
    ],

    'POST /api/vend/consignments/:id/products/bulk' => [
        'controller' => 'VendConsignmentController',
        'action' => 'bulkAddProducts',
        'auth' => true,
        'csrf' => true,
        'permission' => 'payroll.manage_consignments',
        'description' => 'Bulk add products to consignment'
    ],

    // SYNC OPERATIONS
    'POST /api/vend/consignments/:id/sync' => [
        'controller' => 'VendConsignmentController',
        'action' => 'sync',
        'auth' => true,
        'csrf' => true,
        'permission' => 'payroll.manage_consignments',
        'description' => 'Sync consignment to Lightspeed (async by default)'
    ],

    'GET /api/vend/consignments/:id/sync/status' => [
        'controller' => 'VendConsignmentController',
        'action' => 'syncStatus',
        'auth' => true,
        'permission' => 'payroll.view_consignments',
        'description' => 'Get sync status for consignment'
    ],

    'POST /api/vend/consignments/:id/sync/retry' => [
        'controller' => 'VendConsignmentController',
        'action' => 'syncRetry',
        'auth' => true,
        'csrf' => true,
        'permission' => 'payroll.manage_consignments',
        'description' => 'Retry failed sync operation'
    ],

    // WORKFLOW OPERATIONS
    'POST /api/vend/consignments/:id/send' => [
        'controller' => 'VendConsignmentController',
        'action' => 'send',
        'auth' => true,
        'csrf' => true,
        'permission' => 'payroll.manage_consignments',
        'description' => 'Send consignment (mark as SENT)'
    ],

    'POST /api/vend/consignments/:id/receive' => [
        'controller' => 'VendConsignmentController',
        'action' => 'receive',
        'auth' => true,
        'csrf' => true,
        'permission' => 'payroll.manage_consignments',
        'description' => 'Receive consignment with quantities'
    ],

    'POST /api/vend/consignments/:id/cancel' => [
        'controller' => 'VendConsignmentController',
        'action' => 'cancel',
        'auth' => true,
        'csrf' => true,
        'permission' => 'payroll.manage_consignments',
        'description' => 'Cancel consignment'
    ],

    // REPORTING
    'GET /api/vend/consignments/statistics' => [
        'controller' => 'VendConsignmentController',
        'action' => 'statistics',
        'auth' => true,
        'permission' => 'payroll.view_consignments',
        'description' => 'Get consignment statistics (totals by status, period)'
    ],

    'GET /api/vend/consignments/sync-history' => [
        'controller' => 'VendConsignmentController',
        'action' => 'syncHistory',
        'auth' => true,
        'permission' => 'payroll.view_consignments',
        'description' => 'Get sync history with logs (limit 200)'
    ],

];
