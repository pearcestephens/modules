-- STAFF ACCOUNTS DATABASE SCHEMA
-- Complete schema for staff purchasing portal
-- Version: 1.0.0
-- Date: 2025-11-11

-- ============================================================
-- STAFF ACCOUNTS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS `staff_accounts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `staff_id` INT(11) NOT NULL,
  `balance` DECIMAL(10,2) NOT NULL DEFAULT 0.00 COMMENT 'Current account balance (negative = owed)',
  `credit_limit` DECIMAL(10,2) NOT NULL DEFAULT 500.00,
  `payment_method` ENUM('payroll','direct','mixed') DEFAULT 'payroll',
  `status` ENUM('active','suspended','closed') DEFAULT 'active',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_staff` (`staff_id`),
  CONSTRAINT `fk_staff_accounts_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Staff member account balances and credit limits';

-- ============================================================
-- STAFF TRANSACTIONS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS `staff_transactions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `staff_id` INT(11) NOT NULL,
  `reference` VARCHAR(50) NOT NULL COMMENT 'Unique transaction reference',
  `type` ENUM('purchase','payment','refund','adjustment','credit') NOT NULL,
  `amount` DECIMAL(10,2) NOT NULL COMMENT 'Positive = credit, Negative = debit',
  `balance_before` DECIMAL(10,2) NOT NULL,
  `balance_after` DECIMAL(10,2) NOT NULL,
  `description` TEXT,
  `payment_method` VARCHAR(50),
  `status` ENUM('pending','completed','cancelled','refunded') DEFAULT 'pending',
  `processed_by` INT(11) COMMENT 'Staff ID who processed transaction',
  `notes` TEXT,
  `metadata` JSON COMMENT 'Additional transaction details',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `completed_at` DATETIME,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_reference` (`reference`),
  KEY `idx_staff_date` (`staff_id`, `created_at`),
  KEY `idx_type_status` (`type`, `status`),
  CONSTRAINT `fk_staff_transactions_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='All staff account transactions (purchases, payments, refunds)';

-- ============================================================
-- STAFF PURCHASES TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS `staff_purchases` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `transaction_id` INT(11) NOT NULL,
  `staff_id` INT(11) NOT NULL,
  `purchase_number` VARCHAR(50) NOT NULL COMMENT 'Purchase reference number',
  `discount_type` ENUM('staff','friends','family') NOT NULL,
  `discount_rate` DECIMAL(5,2) NOT NULL COMMENT 'Discount percentage applied',
  `subtotal` DECIMAL(10,2) NOT NULL COMMENT 'Before discount',
  `discount_amount` DECIMAL(10,2) NOT NULL,
  `total` DECIMAL(10,2) NOT NULL COMMENT 'After discount',
  `gst_amount` DECIMAL(10,2) NOT NULL,
  `payment_method` VARCHAR(50),
  `vend_sale_id` VARCHAR(100) COMMENT 'Lightspeed sale ID if synced',
  `receipt_generated` BOOLEAN DEFAULT FALSE,
  `receipt_url` VARCHAR(255),
  `notes` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `completed_at` DATETIME,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_purchase_number` (`purchase_number`),
  KEY `idx_staff_date` (`staff_id`, `created_at`),
  KEY `idx_transaction` (`transaction_id`),
  KEY `idx_vend_sale` (`vend_sale_id`),
  CONSTRAINT `fk_staff_purchases_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `staff_transactions` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_staff_purchases_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Staff purchase orders with discount details';

-- ============================================================
-- STAFF PURCHASE ITEMS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS `staff_purchase_items` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` INT(11) NOT NULL,
  `product_id` VARCHAR(100) COMMENT 'Lightspeed product ID',
  `product_name` VARCHAR(255) NOT NULL,
  `product_sku` VARCHAR(100),
  `quantity` INT(11) NOT NULL,
  `retail_price` DECIMAL(10,2) NOT NULL COMMENT 'Full retail price',
  `staff_price` DECIMAL(10,2) NOT NULL COMMENT 'Price after discount',
  `discount_rate` DECIMAL(5,2) NOT NULL,
  `line_total` DECIMAL(10,2) NOT NULL COMMENT 'quantity * staff_price',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_purchase` (`purchase_id`),
  KEY `idx_product` (`product_id`),
  CONSTRAINT `fk_staff_purchase_items_purchase` FOREIGN KEY (`purchase_id`) REFERENCES `staff_purchases` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Line items for each staff purchase';

-- ============================================================
-- STAFF DISCOUNT RULES TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS `staff_discount_rules` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `discount_type` VARCHAR(50) NOT NULL,
  `access_level` VARCHAR(50) COMMENT 'Staff access level (NULL = applies to all)',
  `discount_rate` DECIMAL(5,2) NOT NULL COMMENT 'Percentage discount',
  `description` TEXT,
  `active` BOOLEAN DEFAULT TRUE,
  `effective_from` DATE,
  `effective_to` DATE,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_type_level` (`discount_type`, `access_level`),
  KEY `idx_active` (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Discount rate rules for different staff types';

-- ============================================================
-- STAFF RECEIPTS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS `staff_receipts` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `purchase_id` INT(11) NOT NULL,
  `receipt_number` VARCHAR(50) NOT NULL,
  `staff_id` INT(11) NOT NULL,
  `receipt_html` LONGTEXT COMMENT 'Rendered HTML receipt',
  `receipt_pdf` LONGBLOB COMMENT 'PDF version (if generated)',
  `viewed_count` INT(11) DEFAULT 0,
  `downloaded_count` INT(11) DEFAULT 0,
  `last_viewed_at` DATETIME,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_receipt_number` (`receipt_number`),
  KEY `idx_purchase` (`purchase_id`),
  KEY `idx_staff` (`staff_id`),
  CONSTRAINT `fk_staff_receipts_purchase` FOREIGN KEY (`purchase_id`) REFERENCES `staff_purchases` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_staff_receipts_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Generated receipts for staff purchases';

-- ============================================================
-- STAFF STATEMENTS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS `staff_statements` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `staff_id` INT(11) NOT NULL,
  `statement_number` VARCHAR(50) NOT NULL,
  `period_from` DATE NOT NULL,
  `period_to` DATE NOT NULL,
  `opening_balance` DECIMAL(10,2) NOT NULL,
  `closing_balance` DECIMAL(10,2) NOT NULL,
  `total_purchases` DECIMAL(10,2) NOT NULL,
  `total_payments` DECIMAL(10,2) NOT NULL,
  `statement_html` LONGTEXT,
  `statement_pdf` LONGBLOB,
  `generated_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `downloaded_at` DATETIME,
  PRIMARY KEY (`id`),
  UNIQUE KEY `idx_statement_number` (`statement_number`),
  KEY `idx_staff_period` (`staff_id`, `period_from`, `period_to`),
  CONSTRAINT `fk_staff_statements_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Monthly/periodic account statements for staff';

-- ============================================================
-- STAFF PAYMENT DEDUCTIONS TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS `staff_payment_deductions` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `staff_id` INT(11) NOT NULL,
  `transaction_id` INT(11),
  `pay_period` VARCHAR(50) NOT NULL COMMENT 'e.g., 2025-11-01 to 2025-11-15',
  `amount` DECIMAL(10,2) NOT NULL COMMENT 'Amount deducted from pay',
  `status` ENUM('scheduled','processed','failed','cancelled') DEFAULT 'scheduled',
  `scheduled_date` DATE NOT NULL,
  `processed_date` DATE,
  `payroll_reference` VARCHAR(100) COMMENT 'Reference from payroll system',
  `notes` TEXT,
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_staff_period` (`staff_id`, `pay_period`),
  KEY `idx_status_scheduled` (`status`, `scheduled_date`),
  KEY `idx_transaction` (`transaction_id`),
  CONSTRAINT `fk_staff_payment_deductions_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_staff_payment_deductions_transaction` FOREIGN KEY (`transaction_id`) REFERENCES `staff_transactions` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Scheduled payroll deductions for staff purchases';

-- ============================================================
-- AUDIT LOG TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS `staff_accounts_audit` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `staff_id` INT(11),
  `action` VARCHAR(50) NOT NULL,
  `table_name` VARCHAR(50),
  `record_id` INT(11),
  `old_values` JSON,
  `new_values` JSON,
  `ip_address` VARCHAR(45),
  `user_agent` VARCHAR(255),
  `performed_by` INT(11) COMMENT 'Staff ID who performed action',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_staff_action` (`staff_id`, `action`),
  KEY `idx_created` (`created_at`),
  CONSTRAINT `fk_staff_accounts_audit_staff` FOREIGN KEY (`staff_id`) REFERENCES `staff` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Complete audit trail for all staff account actions';

-- ============================================================
-- INSERT DEFAULT DISCOUNT RULES
-- ============================================================
INSERT INTO `staff_discount_rules` (`discount_type`, `access_level`, `discount_rate`, `description`, `active`) VALUES
('staff', 'staff', 25.00, 'Standard staff discount', TRUE),
('staff', 'manager', 30.00, 'Manager staff discount', TRUE),
('staff', 'admin', 35.00, 'Admin staff discount', TRUE),
('friends', NULL, 20.00, 'Friends discount (any staff can apply)', TRUE),
('family', NULL, 30.00, 'Family discount (any staff can apply)', TRUE)
ON DUPLICATE KEY UPDATE updated_at = CURRENT_TIMESTAMP;

-- ============================================================
-- INDEXES FOR PERFORMANCE
-- ============================================================

-- Optimize transaction lookups
ALTER TABLE `staff_transactions`
  ADD INDEX `idx_staff_type_status` (`staff_id`, `type`, `status`),
  ADD INDEX `idx_reference_lookup` (`reference`(20)),
  ADD INDEX `idx_date_range` (`created_at`, `completed_at`);

-- Optimize purchase searches
ALTER TABLE `staff_purchases`
  ADD INDEX `idx_staff_completed` (`staff_id`, `completed_at`),
  ADD INDEX `idx_discount_type` (`discount_type`);

-- Optimize item queries
ALTER TABLE `staff_purchase_items`
  ADD INDEX `idx_product_name` (`product_name`(50)),
  ADD INDEX `idx_sku` (`product_sku`(20));

-- ============================================================
-- VIEWS FOR REPORTING
-- ============================================================

-- Active staff accounts view
CREATE OR REPLACE VIEW `v_staff_accounts_active` AS
SELECT
  sa.*,
  s.first_name,
  s.last_name,
  s.email,
  s.job_title,
  s.employment_status,
  CONCAT(s.first_name, ' ', s.last_name) AS full_name
FROM `staff_accounts` sa
JOIN `staff` s ON sa.staff_id = s.id
WHERE sa.status = 'active' AND s.employment_status = 'active';

-- Monthly purchase summary view
CREATE OR REPLACE VIEW `v_staff_purchases_monthly` AS
SELECT
  sp.staff_id,
  s.first_name,
  s.last_name,
  DATE_FORMAT(sp.created_at, '%Y-%m') AS month,
  COUNT(*) AS purchase_count,
  SUM(sp.subtotal) AS total_subtotal,
  SUM(sp.discount_amount) AS total_discount,
  SUM(sp.total) AS total_spent,
  AVG(sp.discount_rate) AS avg_discount_rate
FROM `staff_purchases` sp
JOIN `staff` s ON sp.staff_id = s.id
WHERE sp.completed_at IS NOT NULL
GROUP BY sp.staff_id, DATE_FORMAT(sp.created_at, '%Y-%m');

-- Outstanding balances view
CREATE OR REPLACE VIEW `v_staff_outstanding_balances` AS
SELECT
  sa.staff_id,
  s.first_name,
  s.last_name,
  s.email,
  sa.balance,
  sa.credit_limit,
  (sa.credit_limit + sa.balance) AS available_credit,
  CASE
    WHEN sa.balance < 0 THEN ABS(sa.balance)
    ELSE 0
  END AS amount_owed
FROM `staff_accounts` sa
JOIN `staff` s ON sa.staff_id = s.id
WHERE sa.balance < 0 AND sa.status = 'active';

-- ============================================================
-- STORED PROCEDURES
-- ============================================================

DELIMITER $$

-- Create new staff account
CREATE PROCEDURE `sp_create_staff_account`(
  IN p_staff_id INT,
  IN p_credit_limit DECIMAL(10,2)
)
BEGIN
  INSERT INTO `staff_accounts` (`staff_id`, `balance`, `credit_limit`, `status`)
  VALUES (p_staff_id, 0.00, IFNULL(p_credit_limit, 500.00), 'active')
  ON DUPLICATE KEY UPDATE
    credit_limit = IFNULL(p_credit_limit, credit_limit),
    updated_at = CURRENT_TIMESTAMP;
END$$

-- Record staff purchase
CREATE PROCEDURE `sp_record_staff_purchase`(
  IN p_staff_id INT,
  IN p_purchase_number VARCHAR(50),
  IN p_discount_type ENUM('staff','friends','family'),
  IN p_discount_rate DECIMAL(5,2),
  IN p_subtotal DECIMAL(10,2),
  IN p_discount_amount DECIMAL(10,2),
  IN p_total DECIMAL(10,2),
  IN p_gst_amount DECIMAL(10,2),
  IN p_payment_method VARCHAR(50),
  IN p_notes TEXT,
  OUT p_transaction_id INT,
  OUT p_purchase_id INT
)
BEGIN
  DECLARE v_balance_before DECIMAL(10,2);
  DECLARE v_balance_after DECIMAL(10,2);
  DECLARE v_reference VARCHAR(50);

  -- Start transaction
  START TRANSACTION;

  -- Get current balance
  SELECT balance INTO v_balance_before
  FROM `staff_accounts`
  WHERE staff_id = p_staff_id
  FOR UPDATE;

  -- Calculate new balance (negative = owed)
  SET v_balance_after = v_balance_before - p_total;

  -- Generate transaction reference
  SET v_reference = CONCAT('PUR-', p_purchase_number);

  -- Create transaction record
  INSERT INTO `staff_transactions` (
    staff_id, reference, type, amount,
    balance_before, balance_after,
    description, payment_method, status
  ) VALUES (
    p_staff_id, v_reference, 'purchase', -p_total,
    v_balance_before, v_balance_after,
    CONCAT('Purchase ', p_purchase_number), p_payment_method, 'completed'
  );

  SET p_transaction_id = LAST_INSERT_ID();

  -- Create purchase record
  INSERT INTO `staff_purchases` (
    transaction_id, staff_id, purchase_number,
    discount_type, discount_rate,
    subtotal, discount_amount, total, gst_amount,
    payment_method, notes, completed_at
  ) VALUES (
    p_transaction_id, p_staff_id, p_purchase_number,
    p_discount_type, p_discount_rate,
    p_subtotal, p_discount_amount, p_total, p_gst_amount,
    p_payment_method, p_notes, NOW()
  );

  SET p_purchase_id = LAST_INSERT_ID();

  -- Update account balance
  UPDATE `staff_accounts`
  SET balance = v_balance_after, updated_at = NOW()
  WHERE staff_id = p_staff_id;

  -- Schedule payroll deduction if method is payroll
  IF p_payment_method = 'payroll' THEN
    INSERT INTO `staff_payment_deductions` (
      staff_id, transaction_id, amount,
      pay_period, scheduled_date, status
    ) VALUES (
      p_staff_id, p_transaction_id, p_total,
      DATE_FORMAT(NOW(), '%Y-%m'), DATE_ADD(DATE(NOW()), INTERVAL 14 DAY), 'scheduled'
    );
  END IF;

  COMMIT;
END$$

-- Get staff account summary
CREATE PROCEDURE `sp_get_staff_account_summary`(
  IN p_staff_id INT
)
BEGIN
  SELECT
    sa.*,
    (SELECT COUNT(*) FROM staff_purchases WHERE staff_id = p_staff_id AND completed_at IS NOT NULL) AS total_purchases,
    (SELECT SUM(total) FROM staff_purchases WHERE staff_id = p_staff_id AND completed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)) AS purchases_last_30_days,
    (SELECT SUM(amount) FROM staff_transactions WHERE staff_id = p_staff_id AND type = 'payment' AND status = 'completed') AS total_payments,
    (sa.credit_limit + sa.balance) AS available_credit
  FROM staff_accounts sa
  WHERE sa.staff_id = p_staff_id;
END$$

DELIMITER ;

-- ============================================================
-- TRIGGERS FOR AUDIT LOGGING
-- ============================================================

DELIMITER $$

CREATE TRIGGER `tr_staff_transactions_insert`
AFTER INSERT ON `staff_transactions`
FOR EACH ROW
BEGIN
  INSERT INTO `staff_accounts_audit` (
    staff_id, action, table_name, record_id, new_values, performed_by
  ) VALUES (
    NEW.staff_id, 'INSERT', 'staff_transactions', NEW.id,
    JSON_OBJECT(
      'type', NEW.type,
      'amount', NEW.amount,
      'status', NEW.status
    ),
    NEW.processed_by
  );
END$$

CREATE TRIGGER `tr_staff_purchases_insert`
AFTER INSERT ON `staff_purchases`
FOR EACH ROW
BEGIN
  INSERT INTO `staff_accounts_audit` (
    staff_id, action, table_name, record_id, new_values
  ) VALUES (
    NEW.staff_id, 'INSERT', 'staff_purchases', NEW.id,
    JSON_OBJECT(
      'purchase_number', NEW.purchase_number,
      'total', NEW.total,
      'discount_type', NEW.discount_type
    )
  );
END$$

DELIMITER ;

-- ============================================================
-- GRANT PERMISSIONS (adjust as needed)
-- ============================================================

-- GRANT SELECT, INSERT, UPDATE ON staff_accounts.* TO 'staff_portal_user'@'localhost';
-- GRANT EXECUTE ON PROCEDURE sp_create_staff_account TO 'staff_portal_user'@'localhost';
-- GRANT EXECUTE ON PROCEDURE sp_record_staff_purchase TO 'staff_portal_user'@'localhost';
-- GRANT EXECUTE ON PROCEDURE sp_get_staff_account_summary TO 'staff_portal_user'@'localhost';

-- ============================================================
-- END OF SCHEMA
-- ============================================================
