-- HR Portal Module Schema
-- Performance reviews, employee tracking, and feedback management

CREATE TABLE IF NOT EXISTS `employee_reviews` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT UNSIGNED NOT NULL,
    `reviewer_id` INT UNSIGNED NOT NULL,
    `review_period_start` DATE NOT NULL,
    `review_period_end` DATE NOT NULL,
    `review_type` ENUM('probation', 'quarterly', 'annual', 'performance_improvement', 'exit') NOT NULL,
    `overall_rating` DECIMAL(3,2) COMMENT 'Average score 1.00-5.00',
    `status` ENUM('draft', 'pending_review', 'completed', 'acknowledged') DEFAULT 'draft',
    `strengths` TEXT,
    `areas_for_improvement` TEXT,
    `goals_set` TEXT,
    `goals_from_last_review` TEXT,
    `goals_achievement_notes` TEXT,
    `training_recommendations` TEXT,
    `promotion_consideration` TINYINT(1) DEFAULT 0,
    `promotion_notes` TEXT,
    `overall_comments` TEXT,
    `employee_comments` TEXT,
    `employee_acknowledged_at` TIMESTAMP NULL,
    `next_review_date` DATE,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    `completed_at` TIMESTAMP NULL,
    INDEX idx_employee (`employee_id`),
    INDEX idx_reviewer (`reviewer_id`),
    INDEX idx_type (`review_type`),
    INDEX idx_status (`status`),
    INDEX idx_period (`review_period_start`, `review_period_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_review_questions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `category` VARCHAR(100) NOT NULL COMMENT 'Technical Skills, Customer Service, Teamwork, etc',
    `question_text` TEXT NOT NULL,
    `question_type` ENUM('rating', 'text', 'yes_no', 'multiple_choice') NOT NULL,
    `rating_scale` INT DEFAULT 5 COMMENT 'For rating questions: 1-5, 1-10, etc',
    `options` JSON COMMENT 'For multiple choice questions',
    `weight` DECIMAL(3,2) DEFAULT 1.00 COMMENT 'Question weight for overall score',
    `applicable_to` JSON COMMENT 'Array of roles this applies to',
    `active` TINYINT(1) DEFAULT 1,
    `display_order` INT DEFAULT 0,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category (`category`),
    INDEX idx_active (`active`),
    INDEX idx_order (`display_order`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_review_responses` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `review_id` INT UNSIGNED NOT NULL,
    `question_id` INT UNSIGNED NOT NULL,
    `rating_value` INT COMMENT 'For rating questions',
    `text_response` TEXT COMMENT 'For text/comment questions',
    `choice_value` VARCHAR(255) COMMENT 'For multiple choice',
    `notes` TEXT,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`review_id`) REFERENCES `employee_reviews`(`id`) ON DELETE CASCADE,
    FOREIGN KEY (`question_id`) REFERENCES `hr_review_questions`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_review_question` (`review_id`, `question_id`),
    INDEX idx_review (`review_id`),
    INDEX idx_question (`question_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_tracking_defs` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `metric_name` VARCHAR(100) NOT NULL UNIQUE,
    `metric_description` TEXT,
    `metric_type` ENUM('sales', 'customer_service', 'attendance', 'productivity', 'quality', 'custom') NOT NULL,
    `data_type` ENUM('numeric', 'percentage', 'currency', 'boolean', 'text') NOT NULL,
    `calculation_method` TEXT COMMENT 'How the metric is calculated',
    `target_value` DECIMAL(12,2) COMMENT 'Target/goal value',
    `unit` VARCHAR(50) COMMENT 'Unit of measurement',
    `frequency` ENUM('daily', 'weekly', 'monthly', 'quarterly', 'annual') NOT NULL,
    `auto_calculated` TINYINT(1) DEFAULT 0,
    `data_source` VARCHAR(100) COMMENT 'Vend, manual, system, etc',
    `active` TINYINT(1) DEFAULT 1,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_type (`metric_type`),
    INDEX idx_active (`active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `hr_tracking_entries` (
    `id` BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `employee_id` INT UNSIGNED NOT NULL,
    `tracking_definition_id` INT UNSIGNED NOT NULL,
    `period_start` DATE NOT NULL,
    `period_end` DATE NOT NULL,
    `metric_value` DECIMAL(12,2) NOT NULL,
    `target_value` DECIMAL(12,2),
    `achievement_percentage` DECIMAL(5,2) COMMENT 'Performance vs target',
    `notes` TEXT,
    `auto_generated` TINYINT(1) DEFAULT 0,
    `verified` TINYINT(1) DEFAULT 0,
    `verified_by` INT UNSIGNED,
    `verified_at` TIMESTAMP NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (`tracking_definition_id`) REFERENCES `hr_tracking_defs`(`id`) ON DELETE CASCADE,
    UNIQUE KEY `unique_employee_metric_period` (`employee_id`, `tracking_definition_id`, `period_start`),
    INDEX idx_employee (`employee_id`),
    INDEX idx_definition (`tracking_definition_id`),
    INDEX idx_period (`period_start`, `period_end`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Insert default review questions
INSERT INTO `hr_review_questions` (`category`, `question_text`, `question_type`, `rating_scale`, `weight`, `applicable_to`, `display_order`) VALUES
('Technical Skills', 'Product Knowledge - Understanding of vaping products, devices, and e-liquids', 'rating', 5, 1.50, '["sales_staff", "store_manager"]', 1),
('Technical Skills', 'Point of Sale System Proficiency', 'rating', 5, 1.00, '["sales_staff", "store_manager"]', 2),
('Customer Service', 'Customer Interaction Quality - Friendliness, helpfulness, professionalism', 'rating', 5, 2.00, '["sales_staff", "store_manager"]', 3),
('Customer Service', 'Problem Resolution - Handling complaints and difficult situations', 'rating', 5, 1.50, '["sales_staff", "store_manager"]', 4),
('Sales Performance', 'Sales Target Achievement', 'rating', 5, 1.50, '["sales_staff", "store_manager"]', 5),
('Sales Performance', 'Upselling and Cross-selling Effectiveness', 'rating', 5, 1.00, '["sales_staff"]', 6),
('Teamwork', 'Collaboration with Team Members', 'rating', 5, 1.00, '["sales_staff", "store_manager", "warehouse"]', 7),
('Reliability', 'Punctuality and Attendance', 'rating', 5, 1.50, '["sales_staff", "store_manager", "warehouse"]', 8),
('Initiative', 'Proactivity and Problem-solving', 'rating', 5, 1.00, '["sales_staff", "store_manager", "warehouse"]', 9),
('Compliance', 'Age Verification and Legal Compliance', 'rating', 5, 2.00, '["sales_staff", "store_manager"]', 10);

-- Insert default tracking metrics
INSERT INTO `hr_tracking_defs` (`metric_name`, `metric_description`, `metric_type`, `data_type`, `target_value`, `unit`, `frequency`, `auto_calculated`, `data_source`) VALUES
('Total Sales', 'Total sales value per period', 'sales', 'currency', 25000.00, 'NZD', 'monthly', 1, 'Vend'),
('Transaction Count', 'Number of completed sales transactions', 'sales', 'numeric', 150.00, 'transactions', 'monthly', 1, 'Vend'),
('Average Transaction Value', 'Average value per transaction', 'sales', 'currency', 166.67, 'NZD', 'monthly', 1, 'Vend'),
('Customer Satisfaction Score', 'Average customer satisfaction rating', 'customer_service', 'numeric', 4.50, 'out of 5', 'monthly', 0, 'manual'),
('Attendance Rate', 'Percentage of scheduled shifts attended', 'attendance', 'percentage', 95.00, '%', 'monthly', 1, 'Deputy');
