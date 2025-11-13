-- Migration: Create cis_page_fingerprints table
-- Purpose: Store aggregated page performance fingerprints (rolling metrics)
-- Date: 2025-11-13
-- Future considerations:
--   * Add index on sample_count if sorting frequently by highest samples
--   * Consider lcp_p99 / inp_p95 columns for advanced percentile dashboards
--   * Potential partitioning by HASH(page_url) if table grows very large

CREATE TABLE IF NOT EXISTS `cis_page_fingerprints` (
  `page_url` VARCHAR(255) NOT NULL,
  `sample_count` INT UNSIGNED NOT NULL DEFAULT 0,
  `lcp_avg` DOUBLE NULL,
  `lcp_p95` DOUBLE NULL,
  `cls_avg` DOUBLE NULL,
  `cls_p95` DOUBLE NULL,
  `inp_avg` DOUBLE NULL,
  `inp_p95` DOUBLE NULL,
  `last_aggregated_at` DATETIME NULL,
  `created_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`page_url`),
  KEY `idx_lcp_p95` (`lcp_p95`),
  KEY `idx_cls_p95` (`cls_p95`),
  KEY `idx_updated_at` (`updated_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
