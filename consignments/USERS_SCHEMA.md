# USERS Table Schema - SAVED FOR REFERENCE

**Table Name:** `users`

## Primary Key
- `id` int(10) UNSIGNED AUTO_INCREMENT PRIMARY KEY

## Personal Information
- `first_name` mediumtext
- `last_name` mediumtext
- `email` mediumtext
- `phone` mediumtext
- `image` mediumtext
- `nicknames` mediumtext

## Authentication & Security
- `password` mediumtext
- `account_locked` int(11)
- `staff_active` int(11)
- `last_active` timestamp

## Role & Permissions
- `role_id` int(11)
- `default_outlet` mediumtext
- `stored_dashboard_view` mediumtext

## External System Integration
- `xero_id` varchar(45)
- `vend_id` varchar(45)
- `vend_sync_at` timestamp
- `deputy_id` varchar(45)
- `vend_customer_account` varchar(45)

## AI Access
- `gpt_access` tinyint(1)
- `gpt_admin` tinyint(1)

## CRITICAL QUERY PATTERNS
```sql
-- Get active staff only
SELECT * FROM users WHERE staff_active = 1 AND account_locked = 0

-- Get users with GPT access
SELECT * FROM users WHERE gpt_access = 1 AND staff_active = 1
```

**COMPANY RULE: NO ALIASES unless 100% needed - use real column names**