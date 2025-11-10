-- ============================================================================
-- CIS News Aggregator - Database Schema
-- ============================================================================
-- Purpose: Content aggregation system for external vape news, manufacturer
--          updates, local NZ companies, specials, etc.
-- Compatible with: All CIS themes (professional-dark, clean-light, etc.)
-- ============================================================================

-- News Sources (websites/RSS feeds to crawl)
CREATE TABLE IF NOT EXISTS `news_sources` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL COMMENT 'Source name (e.g., "Vaping Post NZ")',
  `url` VARCHAR(500) NOT NULL COMMENT 'Homepage or RSS feed URL',
  `type` ENUM('rss', 'html', 'api') DEFAULT 'rss' COMMENT 'Crawl method',
  `category` VARCHAR(50) NOT NULL COMMENT 'vape-news, manufacturer, local, specials, industry',
  `country` VARCHAR(2) DEFAULT 'NZ' COMMENT 'ISO country code',
  `logo_url` VARCHAR(500) NULL COMMENT 'Source logo/icon',
  `is_active` TINYINT(1) DEFAULT 1 COMMENT 'Enable/disable crawling',
  `crawl_frequency` INT UNSIGNED DEFAULT 3600 COMMENT 'Seconds between crawls',
  `last_crawled_at` DATETIME NULL COMMENT 'Last successful crawl',
  `next_crawl_at` DATETIME NULL COMMENT 'When to crawl next',
  `total_articles` INT UNSIGNED DEFAULT 0 COMMENT 'Total articles scraped',
  `success_rate` DECIMAL(5,2) DEFAULT 100.00 COMMENT 'Crawl success percentage',
  `selector_config` JSON NULL COMMENT 'CSS selectors for HTML scraping',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX idx_active_crawl (`is_active`, `next_crawl_at`),
  INDEX idx_category (`category`, `is_active`),
  INDEX idx_last_crawled (`last_crawled_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Scraped Articles (raw content from external sources)
CREATE TABLE IF NOT EXISTS `news_articles` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `source_id` INT UNSIGNED NOT NULL COMMENT 'FK to news_sources',
  `external_id` VARCHAR(255) NULL COMMENT 'Source article ID (for deduplication)',
  `title` VARCHAR(255) NOT NULL,
  `summary` TEXT NULL COMMENT 'Article excerpt/description',
  `content` LONGTEXT NULL COMMENT 'Full article content',
  `url` VARCHAR(500) NOT NULL COMMENT 'Original article URL',
  `image_url` VARCHAR(500) NULL COMMENT 'Featured image',
  `cached_image` VARCHAR(255) NULL COMMENT 'Local cached image path',
  `author` VARCHAR(100) NULL,
  `published_at` DATETIME NOT NULL COMMENT 'Original publish date',
  `scraped_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `category` VARCHAR(50) NULL COMMENT 'Auto-detected or manual category',
  `tags` JSON NULL COMMENT 'Array of tags',
  `status` ENUM('pending', 'approved', 'rejected', 'hidden') DEFAULT 'pending',
  `moderated_by` INT UNSIGNED NULL COMMENT 'Staff user ID',
  `moderated_at` DATETIME NULL,
  `priority` TINYINT UNSIGNED DEFAULT 5 COMMENT '1=highest, 10=lowest',
  `is_pinned` TINYINT(1) DEFAULT 0 COMMENT 'Pin to top of feed',
  `click_count` INT UNSIGNED DEFAULT 0,
  `view_count` INT UNSIGNED DEFAULT 0,
  FOREIGN KEY (`source_id`) REFERENCES `news_sources`(`id`) ON DELETE CASCADE,
  INDEX idx_source_status (`source_id`, `status`),
  INDEX idx_published (`published_at` DESC),
  INDEX idx_status_priority (`status`, `priority`, `published_at` DESC),
  INDEX idx_external_id (`source_id`, `external_id`),
  INDEX idx_pinned (`is_pinned`, `published_at` DESC),
  UNIQUE KEY uk_source_external (`source_id`, `external_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Unified Feed Items (internal + external content mixed)
CREATE TABLE IF NOT EXISTS `news_feed_items` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `item_type` ENUM('internal', 'external', 'announcement', 'achievement') NOT NULL,
  `source_table` VARCHAR(50) NOT NULL COMMENT 'Source table name',
  `source_id` INT UNSIGNED NOT NULL COMMENT 'ID in source table',
  `title` VARCHAR(255) NOT NULL,
  `content` TEXT NULL,
  `image_url` VARCHAR(500) NULL,
  `author_name` VARCHAR(100) NULL,
  `author_avatar` VARCHAR(500) NULL,
  `published_at` DATETIME NOT NULL,
  `category` VARCHAR(50) NULL,
  `is_visible` TINYINT(1) DEFAULT 1,
  `priority` TINYINT UNSIGNED DEFAULT 5,
  `is_pinned` TINYINT(1) DEFAULT 0,
  `metadata` JSON NULL COMMENT 'Extra data (reactions, source info, etc.)',
  `created_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_visible_published (`is_visible`, `published_at` DESC),
  INDEX idx_type_published (`item_type`, `published_at` DESC),
  INDEX idx_pinned (`is_pinned`, `published_at` DESC),
  INDEX idx_source (`source_table`, `source_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Crawl Log (track crawl success/failures)
CREATE TABLE IF NOT EXISTS `news_crawl_log` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  `source_id` INT UNSIGNED NOT NULL,
  `started_at` DATETIME DEFAULT CURRENT_TIMESTAMP,
  `completed_at` DATETIME NULL,
  `status` ENUM('running', 'success', 'failed', 'timeout') DEFAULT 'running',
  `articles_found` INT UNSIGNED DEFAULT 0,
  `articles_new` INT UNSIGNED DEFAULT 0,
  `articles_updated` INT UNSIGNED DEFAULT 0,
  `error_message` TEXT NULL,
  `execution_time` DECIMAL(10,3) NULL COMMENT 'Seconds',
  FOREIGN KEY (`source_id`) REFERENCES `news_sources`(`id`) ON DELETE CASCADE,
  INDEX idx_source_started (`source_id`, `started_at` DESC),
  INDEX idx_status (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- Sample Data: Popular Vape News Sources
-- ============================================================================

INSERT INTO `news_sources` (`name`, `url`, `type`, `category`, `country`, `crawl_frequency`, `next_crawl_at`) VALUES
('Vaping Post', 'https://www.vapingpost.com/feed/', 'rss', 'vape-news', 'US', 3600, NOW()),
('Planet of the Vapes UK', 'https://www.planetofthevapes.co.uk/news/rss.xml', 'rss', 'vape-news', 'GB', 3600, NOW()),
('Vaping360', 'https://vaping360.com/feed/', 'rss', 'vape-news', 'US', 7200, NOW()),
('ECigIntelligence', 'https://ecigintelligence.com/feed/', 'rss', 'industry', 'GB', 14400, NOW()),
('VOOPOO News', 'https://www.voopoo.com/news', 'html', 'manufacturer', 'CN', 86400, NOW()),
('SMOK Official', 'https://www.smoktech.com/blog', 'html', 'manufacturer', 'CN', 86400, NOW()),
('Vaporesso Blog', 'https://www.vaporesso.com/blog-vape', 'html', 'manufacturer', 'CN', 86400, NOW());

-- ============================================================================
-- End of Schema
-- ============================================================================
