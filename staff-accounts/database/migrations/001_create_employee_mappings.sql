-- ============================================================================
-- Employee Mappings Table Migration
-- ============================================================================
-- Purpose: Map Xero employee IDs to Vend customer IDs for automatic payment allocation
-- Created: 2025-10-23
-- Phase: 1 - Critical Fixes
-- ============================================================================

-- Create employee_mappings table
CREATE TABLE IF NOT EXISTS employee_mappings (
  id INT PRIMARY KEY AUTO_INCREMENT,
  
  -- Xero employee data
  xero_employee_id VARCHAR(100) NOT NULL COMMENT 'Xero employee UUID',
  employee_name VARCHAR(255) NOT NULL COMMENT 'Full name from Xero',
  employee_email VARCHAR(255) COMMENT 'Email from Xero payroll',
  
  -- Vend customer data
  vend_customer_id VARCHAR(100) NOT NULL COMMENT 'Vend customer ID',
  vend_customer_name VARCHAR(255) COMMENT 'Name from Vend',
  
  -- Mapping metadata
  mapping_confidence DECIMAL(3,2) DEFAULT 1.00 COMMENT '0.00-1.00 confidence score',
  mapped_by ENUM('manual', 'auto_email', 'auto_name', 'auto_fuzzy') DEFAULT 'manual',
  mapped_by_user_id INT COMMENT 'User who created mapping',
  
  -- Audit trail
  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  -- Constraints
  UNIQUE KEY uk_xero_employee (xero_employee_id),
  INDEX idx_vend_customer (vend_customer_id),
  INDEX idx_email (employee_email),
  INDEX idx_confidence (mapping_confidence),
  INDEX idx_mapped_by (mapped_by)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Maps Xero employees to Vend customers for payment allocation';

-- ============================================================================
-- Verify table creation
-- ============================================================================
SELECT 
    'employee_mappings table created successfully' as status,
    COUNT(*) as existing_mappings
FROM employee_mappings;

-- Show table structure
DESCRIBE employee_mappings;

-- Show indexes
SHOW INDEX FROM employee_mappings;
