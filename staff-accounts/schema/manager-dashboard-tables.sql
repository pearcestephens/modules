-- Manager Dashboard Support Tables

-- Reminder log (tracks when reminders are sent)
CREATE TABLE IF NOT EXISTS staff_reminder_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    sent_by INT NULL COMMENT 'Manager user ID who sent the reminder',
    reminder_type ENUM('balance_high', 'payment_overdue', 'manual') DEFAULT 'manual',
    INDEX idx_user_id (user_id),
    INDEX idx_sent_at (sent_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (sent_by) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Add manager fields to users table if not exists
ALTER TABLE users 
ADD COLUMN IF NOT EXISTS is_manager BOOLEAN DEFAULT FALSE AFTER role,
ADD INDEX IF NOT EXISTS idx_is_manager (is_manager);

-- Add last_updated to staff_account_balance for tracking
ALTER TABLE staff_account_balance
ADD COLUMN IF NOT EXISTS last_updated DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER updated_at;

-- Create view for manager dashboard quick access
CREATE OR REPLACE VIEW manager_dashboard_summary AS
SELECT 
    (SELECT COUNT(*) FROM users WHERE is_active = 1 AND role IN ('staff', 'manager', 'assistant_manager')) as total_staff,
    (SELECT SUM(current_balance) FROM staff_account_balance) as total_debt,
    (SELECT COUNT(*) FROM staff_account_balance WHERE current_balance > 500 OR (credit_limit > 0 AND current_balance / credit_limit > 0.8)) as high_risk_count,
    (SELECT SUM(amount) FROM staff_allocations WHERE MONTH(allocation_date) = MONTH(CURDATE()) AND YEAR(allocation_date) = YEAR(CURDATE())) as payments_this_month,
    (SELECT COUNT(*) FROM staff_allocations WHERE MONTH(allocation_date) = MONTH(CURDATE()) AND YEAR(allocation_date) = YEAR(CURDATE())) as payment_count_this_month,
    (SELECT AVG(current_balance) FROM staff_account_balance) as avg_balance;

-- Sample queries for manager insights

-- Get staff members who haven't made a payment in 60 days
-- SELECT 
--     u.id, CONCAT(u.first_name, ' ', u.last_name) as name,
--     sab.current_balance,
--     sab.last_payment_date,
--     DATEDIFF(CURDATE(), sab.last_payment_date) as days_since_payment
-- FROM users u
-- JOIN staff_account_balance sab ON u.id = sab.user_id
-- WHERE sab.last_payment_date < DATE_SUB(CURDATE(), INTERVAL 60 DAY)
-- AND sab.current_balance > 100
-- ORDER BY days_since_payment DESC;

-- Get payment trend by month (last 12 months)
-- SELECT 
--     DATE_FORMAT(allocation_date, '%Y-%m') as month,
--     COUNT(*) as payment_count,
--     SUM(amount) as total_amount,
--     payment_method
-- FROM staff_allocations
-- WHERE allocation_date >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
-- GROUP BY DATE_FORMAT(allocation_date, '%Y-%m'), payment_method
-- ORDER BY month DESC;

-- Get department spending comparison
-- SELECT 
--     u.department,
--     COUNT(DISTINCT u.id) as staff_count,
--     SUM(sab.current_balance) as total_balance,
--     AVG(sab.current_balance) as avg_balance,
--     MAX(sab.current_balance) as max_balance
-- FROM users u
-- JOIN staff_account_balance sab ON u.id = sab.user_id
-- WHERE u.is_active = 1 AND u.department IS NOT NULL
-- GROUP BY u.department
-- ORDER BY total_balance DESC;

-- Grant manager role to specific users (update with real user IDs)
-- UPDATE users SET is_manager = 1 WHERE id IN (1, 2, 3);
-- UPDATE users SET is_manager = 1 WHERE role IN ('admin', 'director', 'manager');
