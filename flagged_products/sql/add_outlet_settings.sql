-- Add flagged products settings to vend_outlets table
-- Run this to enable the new settings

-- Add flags_enabled column (default 1 = enabled)
ALTER TABLE vend_outlets 
ADD COLUMN IF NOT EXISTS flags_enabled TINYINT(1) DEFAULT 1 COMMENT 'Enable/disable flagged products for this outlet';

-- Add flags_per_day column (default 20 products per day)
ALTER TABLE vend_outlets 
ADD COLUMN IF NOT EXISTS flags_per_day INT DEFAULT 20 COMMENT 'Number of products to flag per day for this outlet';

-- You can customize per outlet like this:
-- UPDATE vend_outlets SET flags_enabled = 0 WHERE name = 'Hamilton Central'; -- Disable flags
-- UPDATE vend_outlets SET flags_per_day = 30 WHERE name = 'Hamilton East'; -- 30 products/day
-- UPDATE vend_outlets SET flags_per_day = 10 WHERE name = 'Tauranga'; -- 10 products/day
