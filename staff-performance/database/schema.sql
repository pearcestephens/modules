-- ============================================================================
-- STAFF PERFORMANCE & GAMIFICATION MODULE - DATABASE SCHEMA
-- ============================================================================
-- Purpose: Complete schema for staff performance tracking, competitions,
--          achievements, and gamification system
-- Version: 1.0.0
-- Date: 2025-11-05
-- ============================================================================

-- ============================================================================
-- TABLE 1: staff_performance_stats
-- Aggregated performance statistics per staff member
-- ============================================================================
CREATE TABLE IF NOT EXISTS `staff_performance_stats` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` INT(11) NOT NULL COMMENT 'Links to staff_accounts.vend_user_id',
  `month_year` VARCHAR(7) NOT NULL COMMENT 'Format: YYYY-MM',

  -- Performance Metrics
  `google_reviews_count` INT(11) DEFAULT 0,
  `google_reviews_earnings` DECIMAL(10,2) DEFAULT 0.00,
  `vape_drops_count` INT(11) DEFAULT 0,
  `vape_drops_earnings` DECIMAL(10,2) DEFAULT 0.00,
  `total_points` INT(11) DEFAULT 0 COMMENT 'Gamification points',
  `total_earnings` DECIMAL(10,2) DEFAULT 0.00,

  -- Rankings
  `rank_overall` INT(11) DEFAULT NULL COMMENT 'Company-wide rank',
  `rank_store` INT(11) DEFAULT NULL COMMENT 'Store rank',

  -- Timestamps
  `calculated_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_staff_month` (`staff_id`, `month_year`),
  KEY `idx_month` (`month_year`),
  KEY `idx_rank` (`rank_overall`),
  KEY `idx_points` (`total_points`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Monthly aggregated staff performance statistics';

-- ============================================================================
-- TABLE 2: competitions
-- Weekly/monthly competitions and challenges
-- ============================================================================
CREATE TABLE IF NOT EXISTS `competitions` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `name` VARCHAR(100) NOT NULL COMMENT 'Competition name',
  `description` TEXT COMMENT 'Competition description',
  `type` ENUM('weekly','monthly','special') NOT NULL DEFAULT 'weekly',
  `metric` ENUM('google_reviews','vape_drops','sales','combined') NOT NULL COMMENT 'What to measure',
  `metric_unit` VARCHAR(20) DEFAULT 'points' COMMENT 'Unit name for display',

  -- Prizes
  `prize_1st` VARCHAR(255) DEFAULT NULL COMMENT '1st place prize description',
  `prize_2nd` VARCHAR(255) DEFAULT NULL,
  `prize_3rd` VARCHAR(255) DEFAULT NULL,
  `prize_amount_1st` DECIMAL(10,2) DEFAULT 0.00,
  `prize_amount_2nd` DECIMAL(10,2) DEFAULT 0.00,
  `prize_amount_3rd` DECIMAL(10,2) DEFAULT 0.00,

  -- Dates
  `start_date` DATETIME NOT NULL,
  `end_date` DATETIME NOT NULL,
  `status` ENUM('draft','active','completed','cancelled') NOT NULL DEFAULT 'draft',

  -- Metadata
  `created_by` INT(11) DEFAULT NULL COMMENT 'User ID who created',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  KEY `idx_status` (`status`),
  KEY `idx_dates` (`start_date`, `end_date`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Staff performance competitions';

-- ============================================================================
-- TABLE 3: competition_participants
-- Tracks participation and scores in competitions
-- ============================================================================
CREATE TABLE IF NOT EXISTS `competition_participants` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `competition_id` INT(11) UNSIGNED NOT NULL,
  `staff_id` INT(11) NOT NULL,

  -- Performance
  `score` INT(11) DEFAULT 0 COMMENT 'Competition score',
  `rank` INT(11) DEFAULT NULL COMMENT 'Current rank',
  `prize_won` VARCHAR(255) DEFAULT NULL,
  `prize_amount` DECIMAL(10,2) DEFAULT 0.00,
  `prize_paid` TINYINT(1) DEFAULT 0,
  `prize_paid_at` DATETIME DEFAULT NULL,

  -- Timestamps
  `joined_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `last_updated` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_competition_staff` (`competition_id`, `staff_id`),
  KEY `idx_rank` (`rank`),
  KEY `idx_staff` (`staff_id`),
  FOREIGN KEY (`competition_id`) REFERENCES `competitions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Competition participation tracking';

-- ============================================================================
-- TABLE 4: achievements
-- Achievement/badge definitions
-- ============================================================================
CREATE TABLE IF NOT EXISTS `achievements` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `code` VARCHAR(50) NOT NULL COMMENT 'Unique achievement code',
  `name` VARCHAR(100) NOT NULL,
  `description` TEXT,
  `icon` VARCHAR(50) DEFAULT 'trophy' COMMENT 'Font Awesome icon name',
  `color` VARCHAR(20) DEFAULT 'gold' COMMENT 'Badge color',
  `category` ENUM('reviews','drops','sales','competitions','special') NOT NULL,

  -- Unlock Criteria
  `criteria_type` ENUM('count','streak','milestone','special') NOT NULL,
  `criteria_value` INT(11) DEFAULT NULL COMMENT 'Required value to unlock',
  `criteria_period` ENUM('lifetime','monthly','weekly') DEFAULT 'lifetime',

  -- Rewards
  `points_reward` INT(11) DEFAULT 0,
  `bonus_amount` DECIMAL(10,2) DEFAULT 0.00,

  -- Display
  `tier` ENUM('bronze','silver','gold','platinum','legend') DEFAULT 'bronze',
  `is_active` TINYINT(1) DEFAULT 1,
  `sort_order` INT(11) DEFAULT 0,

  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_code` (`code`),
  KEY `idx_category` (`category`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Achievement/badge definitions';

-- ============================================================================
-- TABLE 5: staff_achievements
-- Tracks which achievements each staff member has earned
-- ============================================================================
CREATE TABLE IF NOT EXISTS `staff_achievements` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` INT(11) NOT NULL,
  `achievement_id` INT(11) UNSIGNED NOT NULL,

  -- Progress
  `progress` INT(11) DEFAULT 0 COMMENT 'Current progress toward unlock',
  `is_unlocked` TINYINT(1) DEFAULT 0,
  `unlocked_at` DATETIME DEFAULT NULL,

  -- Rewards
  `points_earned` INT(11) DEFAULT 0,
  `bonus_earned` DECIMAL(10,2) DEFAULT 0.00,
  `bonus_paid` TINYINT(1) DEFAULT 0,

  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_staff_achievement` (`staff_id`, `achievement_id`),
  KEY `idx_staff` (`staff_id`),
  KEY `idx_unlocked` (`is_unlocked`),
  FOREIGN KEY (`achievement_id`) REFERENCES `achievements`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Staff achievement progress tracking';

-- ============================================================================
-- TABLE 6: leaderboard_history
-- Historical snapshots of leaderboard positions
-- ============================================================================
CREATE TABLE IF NOT EXISTS `leaderboard_history` (
  `id` INT(11) UNSIGNED NOT NULL AUTO_INCREMENT,
  `staff_id` INT(11) NOT NULL,
  `snapshot_date` DATE NOT NULL,
  `snapshot_type` ENUM('daily','weekly','monthly') NOT NULL,

  -- Rankings
  `rank_overall` INT(11) DEFAULT NULL,
  `rank_store` INT(11) DEFAULT NULL,
  `total_points` INT(11) DEFAULT 0,

  -- Stats
  `google_reviews` INT(11) DEFAULT 0,
  `vape_drops` INT(11) DEFAULT 0,
  `competitions_won` INT(11) DEFAULT 0,

  PRIMARY KEY (`id`),
  UNIQUE KEY `unique_snapshot` (`staff_id`, `snapshot_date`, `snapshot_type`),
  KEY `idx_date` (`snapshot_date`),
  KEY `idx_rank` (`rank_overall`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COMMENT='Historical leaderboard snapshots';

-- ============================================================================
-- SEED DATA: Initial Achievements
-- ============================================================================
INSERT INTO `achievements` (`code`, `name`, `description`, `icon`, `color`, `category`, `criteria_type`, `criteria_value`, `tier`, `points_reward`, `bonus_amount`) VALUES
('first_review', 'First Review', 'Get your first Google review mention', 'star', 'bronze', 'reviews', 'count', 1, 'bronze', 50, 0.00),
('review_streak_5', 'Review Streak', 'Get 5 reviews in one month', 'fire', 'silver', 'reviews', 'count', 5, 'silver', 200, 10.00),
('review_champion', 'Review Champion', 'Get 10+ reviews in one month', 'crown', 'gold', 'reviews', 'count', 10, 'gold', 500, 25.00),
('first_drop', 'First Drop', 'Complete your first vape drop', 'box', 'bronze', 'drops', 'count', 1, 'bronze', 25, 0.00),
('drop_master', 'Drop Master', 'Complete 20 vape drops in one month', 'truck', 'gold', 'drops', 'count', 20, 'gold', 300, 20.00),
('competition_winner', 'Competition Winner', 'Win any weekly competition', 'trophy', 'gold', 'competitions', 'special', 1, 'gold', 500, 50.00),
('triple_crown', 'Triple Crown', 'Win 3 competitions in a row', 'crown', 'platinum', 'competitions', 'special', 3, 'platinum', 1000, 100.00),
('perfect_month', 'Perfect Month', '10+ reviews AND 15+ drops in one month', 'gem', 'platinum', 'special', 'special', NULL, 'platinum', 1000, 100.00),
('legend', 'Legend Status', '50+ reviews lifetime', 'star', 'legend', 'reviews', 'count', 50, 'legend', 5000, 250.00);

-- ============================================================================
-- VIEW: current_leaderboard
-- Real-time leaderboard view
-- ============================================================================
CREATE OR REPLACE VIEW `current_leaderboard` AS
SELECT
    sa.vend_user_id as staff_id,
    sa.employee_name,
    o.outlet_name as store_name,
    COUNT(DISTINCT gr.id) as google_reviews_this_month,
    COUNT(DISTINCT vd.id) as vape_drops_this_month,
    (COUNT(DISTINCT gr.id) * 100 + COUNT(DISTINCT vd.id) * 50) as total_points,
    (COUNT(DISTINCT gr.id) * 10 + COUNT(DISTINCT vd.id) * 6) as total_earnings,
    RANK() OVER (ORDER BY (COUNT(DISTINCT gr.id) * 100 + COUNT(DISTINCT vd.id) * 50) DESC) as rank_overall
FROM staff_accounts sa
LEFT JOIN google_reviews gr ON gr.staff_id = sa.vend_user_id
    AND gr.review_date >= DATE_FORMAT(NOW(), '%Y-%m-01')
    AND gr.verified = 1
LEFT JOIN vape_drops vd ON vd.staff_id = sa.vend_user_id
    AND vd.completed_at >= DATE_FORMAT(NOW(), '%Y-%m-01')
    AND vd.completed = 1
LEFT JOIN outlets o ON o.outlet_id = sa.outlet_id
WHERE sa.is_active = 1
GROUP BY sa.vend_user_id
ORDER BY total_points DESC;

-- ============================================================================
-- STORED PROCEDURE: Update Competition Rankings
-- ============================================================================
DELIMITER $$
CREATE PROCEDURE `update_competition_rankings`(IN comp_id INT)
BEGIN
    -- Update ranks for all participants in a competition
    UPDATE competition_participants cp1
    JOIN (
        SELECT
            cp.id,
            @rank := @rank + 1 AS new_rank
        FROM competition_participants cp
        CROSS JOIN (SELECT @rank := 0) r
        WHERE cp.competition_id = comp_id
        ORDER BY cp.score DESC
    ) cp2 ON cp1.id = cp2.id
    SET cp1.rank = cp2.new_rank;

    -- Award prizes to top 3
    UPDATE competition_participants cp
    JOIN competitions c ON c.id = cp.competition_id
    SET
        cp.prize_won = CASE cp.rank
            WHEN 1 THEN c.prize_1st
            WHEN 2 THEN c.prize_2nd
            WHEN 3 THEN c.prize_3rd
            ELSE NULL
        END,
        cp.prize_amount = CASE cp.rank
            WHEN 1 THEN c.prize_amount_1st
            WHEN 2 THEN c.prize_amount_2nd
            WHEN 3 THEN c.prize_amount_3rd
            ELSE 0.00
        END
    WHERE cp.competition_id = comp_id;
END$$
DELIMITER ;

-- ============================================================================
-- INDEXES FOR PERFORMANCE
-- ============================================================================
ALTER TABLE `google_reviews` ADD INDEX IF NOT EXISTS `idx_staff_date` (`staff_id`, `review_date`);
ALTER TABLE `vape_drops` ADD INDEX IF NOT EXISTS `idx_staff_completed` (`staff_id`, `completed_at`);
ALTER TABLE `staff_accounts` ADD INDEX IF NOT EXISTS `idx_active` (`is_active`);

-- ============================================================================
-- GRANTS (if needed)
-- ============================================================================
-- GRANT SELECT, INSERT, UPDATE ON staff_performance.* TO 'app_user'@'localhost';

-- ============================================================================
-- MIGRATION COMPLETE
-- ============================================================================
SELECT 'Staff Performance & Gamification schema created successfully!' as status;
