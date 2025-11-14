-- ============================================================================
-- UNIVERSAL EMPLOYEE ONBOARDING SYSTEM - DATABASE SCHEMA
-- ============================================================================
-- Purpose: Single source of truth for all employees across all systems
-- Creates employee ONCE, provisions to: CIS, Xero, Deputy, Lightspeed
-- Version: 1.0.0
-- Date: 2025-11-05
-- ============================================================================

-- ============================================================================
-- TABLE 1: users (MASTER EMPLOYEE TABLE)
-- ============================================================================
CREATE TABLE IF NOT EXISTS users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,

    -- Personal Information
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    email VARCHAR(255) NOT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL,
    mobile VARCHAR(20) DEFAULT NULL,
    date_of_birth DATE DEFAULT NULL,

    -- Employment Details
    employee_number VARCHAR(50) UNIQUE DEFAULT NULL COMMENT 'Internal employee number',
    start_date DATE NOT NULL,
    end_date DATE DEFAULT NULL,
    employment_type ENUM('full_time', 'part_time', 'casual', 'contractor') DEFAULT 'full_time',
    job_title VARCHAR(100) DEFAULT NULL,
    department VARCHAR(100) DEFAULT NULL,
    location_id INT UNSIGNED DEFAULT NULL COMMENT 'Primary store/location',
    manager_id INT UNSIGNED DEFAULT NULL COMMENT 'Reports to user ID',

    -- System Access
    username VARCHAR(50) UNIQUE DEFAULT NULL,
    password_hash VARCHAR(255) DEFAULT NULL,
    must_change_password BOOLEAN DEFAULT TRUE,
    last_login DATETIME DEFAULT NULL,
    login_count INT UNSIGNED DEFAULT 0,

    -- Status
    status ENUM('active', 'inactive', 'terminated', 'on_leave', 'pending') DEFAULT 'pending',
    is_admin BOOLEAN DEFAULT FALSE,

    -- Metadata
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_by INT UNSIGNED DEFAULT NULL,
    notes TEXT DEFAULT NULL,

    INDEX idx_email (email),
    INDEX idx_employee_number (employee_number),
    INDEX idx_username (username),
    INDEX idx_status (status),
    INDEX idx_location (location_id),
    INDEX idx_manager (manager_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE 2: roles (ROLE DEFINITIONS)
-- ============================================================================
CREATE TABLE IF NOT EXISTS roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE COMMENT 'e.g., Store Manager, Director, Assistant',
    display_name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    level INT UNSIGNED DEFAULT 0 COMMENT 'Hierarchy level (0=lowest, 100=highest)',
    is_system_role BOOLEAN DEFAULT FALSE COMMENT 'Cannot be deleted',

    -- Approval Limits
    approval_limit DECIMAL(10,2) DEFAULT 0.00 COMMENT 'Max $ can approve',

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    INDEX idx_level (level)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE 3: permissions (PERMISSION DEFINITIONS)
-- ============================================================================
CREATE TABLE IF NOT EXISTS permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE COMMENT 'e.g., payroll.approve, transfers.create',
    display_name VARCHAR(100) NOT NULL,
    description TEXT DEFAULT NULL,
    module VARCHAR(50) NOT NULL COMMENT 'Module/section this permission belongs to',
    category VARCHAR(50) DEFAULT 'general' COMMENT 'Group related permissions',
    is_dangerous BOOLEAN DEFAULT FALSE COMMENT 'Requires extra approval to grant',

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    INDEX idx_module (module),
    INDEX idx_category (category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE 4: role_permissions (ROLE → PERMISSIONS MAPPING)
-- ============================================================================
CREATE TABLE IF NOT EXISTS role_permissions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    role_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    granted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    granted_by INT UNSIGNED DEFAULT NULL,

    UNIQUE KEY uk_role_permission (role_id, permission_id),
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    INDEX idx_role (role_id),
    INDEX idx_permission (permission_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE 5: user_roles (USER → ROLES MAPPING)
-- ============================================================================
CREATE TABLE IF NOT EXISTS user_roles (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    role_id INT UNSIGNED NOT NULL,
    assigned_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    assigned_by INT UNSIGNED DEFAULT NULL,
    expires_at DATETIME DEFAULT NULL COMMENT 'For temporary role assignments',

    UNIQUE KEY uk_user_role (user_id, role_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (role_id) REFERENCES roles(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_role (role_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE 6: external_system_mappings (INTEGRATION IDS)
-- ============================================================================
CREATE TABLE IF NOT EXISTS external_system_mappings (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    system_name ENUM('xero', 'deputy', 'lightspeed', 'vend') NOT NULL,

    -- External System IDs
    external_id VARCHAR(255) NOT NULL COMMENT 'User ID in external system',
    external_username VARCHAR(255) DEFAULT NULL,
    external_email VARCHAR(255) DEFAULT NULL,

    -- Sync Status
    sync_status ENUM('synced', 'pending', 'failed', 'disabled') DEFAULT 'synced',
    last_synced_at DATETIME DEFAULT NULL,
    last_sync_error TEXT DEFAULT NULL,
    sync_attempts INT UNSIGNED DEFAULT 0,

    -- Additional Data (JSON)
    metadata JSON DEFAULT NULL COMMENT 'System-specific data',

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uk_user_system (user_id, system_name),
    UNIQUE KEY uk_system_external_id (system_name, external_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_system (system_name),
    INDEX idx_sync_status (sync_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE 7: onboarding_log (AUDIT TRAIL)
-- ============================================================================
CREATE TABLE IF NOT EXISTS onboarding_log (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    action VARCHAR(100) NOT NULL COMMENT 'create_user, sync_xero, sync_deputy, etc.',
    system_name VARCHAR(50) DEFAULT NULL,
    status ENUM('success', 'failed', 'pending', 'rolled_back') NOT NULL,

    -- Details
    request_data JSON DEFAULT NULL,
    response_data JSON DEFAULT NULL,
    error_message TEXT DEFAULT NULL,

    -- Context
    initiated_by INT UNSIGNED DEFAULT NULL,
    ip_address VARCHAR(45) DEFAULT NULL,
    user_agent VARCHAR(500) DEFAULT NULL,

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_action (action),
    INDEX idx_system (system_name),
    INDEX idx_status (status),
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE 8: sync_queue (RETRY QUEUE FOR FAILED SYNCS)
-- ============================================================================
CREATE TABLE IF NOT EXISTS sync_queue (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    system_name ENUM('xero', 'deputy', 'lightspeed', 'vend') NOT NULL,
    action ENUM('create', 'update', 'delete', 'reactivate') NOT NULL,

    -- Retry Logic
    priority TINYINT UNSIGNED DEFAULT 5 COMMENT '1=highest, 10=lowest',
    attempts INT UNSIGNED DEFAULT 0,
    max_attempts INT UNSIGNED DEFAULT 5,
    next_retry_at DATETIME DEFAULT NULL,

    -- Data
    payload JSON NOT NULL,
    last_error TEXT DEFAULT NULL,

    -- Status
    status ENUM('pending', 'processing', 'completed', 'failed', 'cancelled') DEFAULT 'pending',

    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    completed_at DATETIME DEFAULT NULL,

    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_system (system_name),
    INDEX idx_status (status),
    INDEX idx_next_retry (next_retry_at),
    INDEX idx_priority (priority)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- TABLE 9: user_permissions_override (USER-SPECIFIC PERMISSIONS)
-- ============================================================================
CREATE TABLE IF NOT EXISTS user_permissions_override (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    permission_id INT UNSIGNED NOT NULL,
    type ENUM('grant', 'revoke') NOT NULL COMMENT 'Grant adds, revoke removes',

    reason TEXT DEFAULT NULL,
    granted_by INT UNSIGNED DEFAULT NULL,
    granted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    expires_at DATETIME DEFAULT NULL,

    UNIQUE KEY uk_user_permission (user_id, permission_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (permission_id) REFERENCES permissions(id) ON DELETE CASCADE,
    INDEX idx_user (user_id),
    INDEX idx_permission (permission_id),
    INDEX idx_expires (expires_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- SEED DATA: DEFAULT ROLES
-- ============================================================================
INSERT INTO roles (name, display_name, description, level, is_system_role, approval_limit) VALUES
('director', 'Director', 'Company Director - Full System Access', 100, TRUE, 999999.99),
('retail_ops_manager', 'Retail Operations Manager', 'Oversees all retail operations', 80, TRUE, 5000.00),
('comms_manager', 'Communications Manager', 'Marketing and Communications', 80, TRUE, 5000.00),
('store_manager', 'Store Manager', 'Manages individual store', 60, TRUE, 2000.00),
('assistant_manager', 'Assistant Store Manager', 'Assists store manager', 50, FALSE, 500.00),
('senior_staff', 'Senior Staff Member', 'Experienced retail staff', 40, FALSE, 200.00),
('staff', 'Staff Member', 'Regular retail staff', 30, FALSE, 0.00),
('casual', 'Casual Staff', 'Casual/part-time staff', 20, FALSE, 0.00),
('it_admin', 'IT Administrator', 'System administrator', 90, TRUE, 0.00),
('finance', 'Finance Manager', 'Financial operations', 85, TRUE, 10000.00)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- ============================================================================
-- SEED DATA: DEFAULT PERMISSIONS (COMPREHENSIVE LIST)
-- ============================================================================
INSERT INTO permissions (name, display_name, description, module, category, is_dangerous) VALUES
-- System Admin
('system.admin', 'System Administrator', 'Full system access', 'system', 'admin', TRUE),
('system.view_logs', 'View System Logs', 'Access system audit logs', 'system', 'admin', FALSE),
('system.manage_users', 'Manage Users', 'Create/edit/delete users', 'system', 'users', TRUE),
('system.manage_roles', 'Manage Roles', 'Create/edit/delete roles', 'system', 'users', TRUE),
('system.manage_permissions', 'Manage Permissions', 'Assign permissions to roles', 'system', 'users', TRUE),

-- Payroll
('payroll.view_dashboard', 'View Payroll Dashboard', 'Access payroll overview', 'payroll', 'view', FALSE),
('payroll.approve_amendments', 'Approve Amendments', 'Approve timesheet amendments', 'payroll', 'approve', FALSE),
('payroll.approve_discrepancies', 'Approve Wage Discrepancies', 'Resolve wage discrepancies', 'payroll', 'approve', FALSE),
('payroll.approve_bonuses', 'Approve Bonuses', 'Approve staff bonuses', 'payroll', 'approve', FALSE),
('payroll.approve_vend_payments', 'Approve Vend Payments', 'Approve staff account payments', 'payroll', 'approve', FALSE),
('payroll.approve_leave', 'Approve Leave', 'Approve leave requests', 'payroll', 'approve', FALSE),
('payroll.manage_automation', 'Manage Automation', 'Configure AI automation rules', 'payroll', 'admin', TRUE),
('payroll.xero_admin', 'Xero Administration', 'Manage Xero integration', 'payroll', 'admin', TRUE),
('payroll.deputy_admin', 'Deputy Administration', 'Manage Deputy integration', 'payroll', 'admin', TRUE),

-- Transfers
('transfers.create', 'Create Transfer', 'Create stock transfers', 'transfers', 'create', FALSE),
('transfers.approve_0_2k', 'Approve Transfers $0-2k', 'Approve transfers up to $2000', 'transfers', 'approve', FALSE),
('transfers.approve_2k_5k', 'Approve Transfers $2k-5k', 'Approve transfers $2000-$5000', 'transfers', 'approve', FALSE),
('transfers.approve_5k_plus', 'Approve Transfers $5k+', 'Approve transfers over $5000', 'transfers', 'approve', TRUE),
('transfers.receive', 'Receive Transfer', 'Receive stock at destination', 'transfers', 'receive', FALSE),
('transfers.cancel', 'Cancel Transfer', 'Cancel pending transfers', 'transfers', 'manage', FALSE),

-- Purchase Orders
('po.create', 'Create Purchase Order', 'Create new purchase orders', 'purchase_orders', 'create', FALSE),
('po.approve_0_2k', 'Approve PO $0-2k', 'Approve POs up to $2000', 'purchase_orders', 'approve', FALSE),
('po.approve_2k_5k', 'Approve PO $2k-5k', 'Approve POs $2000-$5000', 'purchase_orders', 'approve', FALSE),
('po.approve_5k_plus', 'Approve PO $5k+', 'Approve POs over $5000', 'purchase_orders', 'approve', TRUE),
('po.receive', 'Receive PO', 'Receive purchase order goods', 'purchase_orders', 'receive', FALSE),

-- Consignments
('consignments.create', 'Create Consignment', 'Create new consignments', 'consignments', 'create', FALSE),
('consignments.approve', 'Approve Consignment', 'Approve consignments', 'consignments', 'approve', FALSE),
('consignments.receive', 'Receive Consignment', 'Receive consignment goods', 'consignments', 'receive', FALSE),

-- Inventory
('inventory.view', 'View Inventory', 'View stock levels', 'inventory', 'view', FALSE),
('inventory.adjust', 'Adjust Inventory', 'Perform stock adjustments', 'inventory', 'manage', TRUE),
('inventory.audit', 'Inventory Audit', 'Perform inventory audits', 'inventory', 'manage', FALSE),

-- Store Reports
('store_reports.create', 'Create Store Report', 'Create store inspection reports', 'store_reports', 'create', FALSE),
('store_reports.view_all', 'View All Reports', 'View reports for all stores', 'store_reports', 'view', FALSE),
('store_reports.approve', 'Approve Reports', 'Approve store reports', 'store_reports', 'approve', FALSE),

-- Staff Accounts
('staff_accounts.view_own', 'View Own Account', 'View own staff account', 'staff_accounts', 'view', FALSE),
('staff_accounts.view_all', 'View All Accounts', 'View all staff accounts', 'staff_accounts', 'view', FALSE),
('staff_accounts.make_payment', 'Make Payment', 'Process staff account payments', 'staff_accounts', 'payment', TRUE),

-- Reports & Analytics
('reports.view_basic', 'View Basic Reports', 'Access standard reports', 'reports', 'view', FALSE),
('reports.view_advanced', 'View Advanced Reports', 'Access detailed analytics', 'reports', 'view', FALSE),
('reports.export', 'Export Reports', 'Export reports to Excel/PDF', 'reports', 'export', FALSE),

-- HR
('hr.view_staff', 'View Staff Directory', 'View staff information', 'hr', 'view', FALSE),
('hr.manage_staff', 'Manage Staff', 'Edit staff details', 'hr', 'manage', TRUE),
('hr.view_payroll', 'View Payroll Data', 'Access payroll information', 'hr', 'view', TRUE),

-- Finance
('finance.view_dashboard', 'View Finance Dashboard', 'Access financial overview', 'finance', 'view', FALSE),
('finance.reconcile', 'Reconcile Accounts', 'Perform account reconciliation', 'finance', 'manage', TRUE),
('finance.approve_payments', 'Approve Payments', 'Approve financial payments', 'finance', 'approve', TRUE)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- ============================================================================
-- SEED DATA: ASSIGN PERMISSIONS TO ROLES
-- ============================================================================

-- DIRECTOR: Everything
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r
CROSS JOIN permissions p
WHERE r.name = 'director'
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;

-- RETAIL OPS MANAGER: Broad operational access
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.name = 'retail_ops_manager'
AND p.name IN (
    'transfers.create', 'transfers.approve_0_2k', 'transfers.approve_2k_5k', 'transfers.receive',
    'po.create', 'po.approve_0_2k', 'po.approve_2k_5k', 'po.receive',
    'consignments.create', 'consignments.approve', 'consignments.receive',
    'inventory.view', 'inventory.adjust', 'inventory.audit',
    'store_reports.create', 'store_reports.view_all', 'store_reports.approve',
    'staff_accounts.view_all',
    'reports.view_advanced', 'reports.export',
    'hr.view_staff', 'hr.manage_staff',
    'payroll.view_dashboard', 'payroll.approve_amendments', 'payroll.approve_discrepancies'
)
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;

-- STORE MANAGER: Store-level operations
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.name = 'store_manager'
AND p.name IN (
    'transfers.create', 'transfers.approve_0_2k', 'transfers.receive',
    'po.create', 'po.approve_0_2k', 'po.receive',
    'consignments.receive',
    'inventory.view', 'inventory.audit',
    'store_reports.create',
    'staff_accounts.view_own',
    'reports.view_basic',
    'hr.view_staff',
    'payroll.view_dashboard'
)
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;

-- STAFF: Basic access
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.name = 'staff'
AND p.name IN (
    'inventory.view',
    'store_reports.create',
    'staff_accounts.view_own',
    'reports.view_basic'
)
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;

-- IT ADMIN: System administration
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.name = 'it_admin'
AND p.name IN (
    'system.admin', 'system.view_logs', 'system.manage_users', 'system.manage_roles', 'system.manage_permissions',
    'payroll.xero_admin', 'payroll.deputy_admin'
)
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;

-- FINANCE MANAGER: Financial operations
INSERT INTO role_permissions (role_id, permission_id)
SELECT r.id, p.id
FROM roles r, permissions p
WHERE r.name = 'finance'
AND p.name IN (
    'finance.view_dashboard', 'finance.reconcile', 'finance.approve_payments',
    'payroll.view_dashboard', 'payroll.approve_amendments', 'payroll.approve_bonuses',
    'staff_accounts.view_all', 'staff_accounts.make_payment',
    'reports.view_advanced', 'reports.export',
    'hr.view_staff', 'hr.view_payroll'
)
ON DUPLICATE KEY UPDATE granted_at = CURRENT_TIMESTAMP;

-- ============================================================================
-- VIEWS: CONSOLIDATED USER DATA
-- ============================================================================

-- View: Complete user with all roles and permissions
CREATE OR REPLACE VIEW vw_users_complete AS
SELECT
    u.id,
    u.first_name,
    u.last_name,
    CONCAT(u.first_name, ' ', u.last_name) as full_name,
    u.email,
    u.phone,
    u.mobile,
    u.employee_number,
    u.start_date,
    u.end_date,
    u.employment_type,
    u.job_title,
    u.department,
    u.location_id,
    u.manager_id,
    u.username,
    u.status,
    u.is_admin,
    u.last_login,

    -- Roles (JSON array)
    JSON_ARRAYAGG(DISTINCT JSON_OBJECT(
        'role_id', r.id,
        'role_name', r.name,
        'display_name', r.display_name,
        'level', r.level,
        'approval_limit', r.approval_limit
    )) as roles,

    -- External system mapping status
    MAX(CASE WHEN esm.system_name = 'xero' THEN esm.external_id END) as xero_id,
    MAX(CASE WHEN esm.system_name = 'deputy' THEN esm.external_id END) as deputy_id,
    MAX(CASE WHEN esm.system_name = 'lightspeed' THEN esm.external_id END) as lightspeed_id,

    MAX(CASE WHEN esm.system_name = 'xero' THEN esm.sync_status END) as xero_sync_status,
    MAX(CASE WHEN esm.system_name = 'deputy' THEN esm.sync_status END) as deputy_sync_status,
    MAX(CASE WHEN esm.system_name = 'lightspeed' THEN esm.sync_status END) as lightspeed_sync_status,

    u.created_at,
    u.updated_at
FROM users u
LEFT JOIN user_roles ur ON u.id = ur.user_id
LEFT JOIN roles r ON ur.role_id = r.id
LEFT JOIN external_system_mappings esm ON u.id = esm.user_id
GROUP BY u.id;

-- ============================================================================
-- STORED PROCEDURES
-- ============================================================================

DELIMITER //

-- Check if user has specific permission
CREATE PROCEDURE IF NOT EXISTS check_user_permission(
    IN p_user_id INT UNSIGNED,
    IN p_permission_name VARCHAR(100),
    OUT p_has_permission BOOLEAN
)
BEGIN
    DECLARE v_count INT DEFAULT 0;

    -- Check if user is admin (has all permissions)
    SELECT COUNT(*) INTO v_count
    FROM users
    WHERE id = p_user_id AND is_admin = TRUE;

    IF v_count > 0 THEN
        SET p_has_permission = TRUE;
    ELSE
        -- Check via roles
        SELECT COUNT(*) INTO v_count
        FROM user_roles ur
        JOIN role_permissions rp ON ur.role_id = rp.role_id
        JOIN permissions p ON rp.permission_id = p.id
        WHERE ur.user_id = p_user_id
        AND p.name = p_permission_name
        AND (ur.expires_at IS NULL OR ur.expires_at > NOW());

        -- Check direct grants
        IF v_count = 0 THEN
            SELECT COUNT(*) INTO v_count
            FROM user_permissions_override upo
            JOIN permissions p ON upo.permission_id = p.id
            WHERE upo.user_id = p_user_id
            AND p.name = p_permission_name
            AND upo.type = 'grant'
            AND (upo.expires_at IS NULL OR upo.expires_at > NOW());
        END IF;

        SET p_has_permission = (v_count > 0);
    END IF;
END//

DELIMITER ;

-- ============================================================================
-- COMPLETE
-- ============================================================================
