-- Optimize Apply Payments Page Performance
-- Run this to add indexes if they don't already exist

-- Index for fast date-based filtering on sales_payments
CREATE INDEX IF NOT EXISTS idx_payment_date_status
ON sales_payments(payment_date, sale_status);

-- Index for joining sales_payments to vend_customers
CREATE INDEX IF NOT EXISTS idx_vend_customer_id
ON sales_payments(vend_customer_id);

-- Index for joining users to vend_customers
CREATE INDEX IF NOT EXISTS idx_vend_customer_staff_active
ON users(vend_customer_account, staff_active);

-- Check if indexes were created
SHOW INDEX FROM sales_payments WHERE Key_name LIKE 'idx_%';
SHOW INDEX FROM users WHERE Key_name LIKE 'idx_%';
