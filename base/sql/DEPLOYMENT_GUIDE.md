# MariaDB 10.5.29 - Notification & Messenger Schema Deployment Guide

## 📋 Current Status

**MariaDB Version:** `10.5.29`
**Schema File:** `notification_messenger_schema.sql` (UPDATED - READY)
**Foreign Keys:** Separated into `notification_messenger_foreign_keys.sql`

## 🚀 Why We Changed The Approach

### Previous Issue
```
Error Code: 1005 "Foreign key constraint is incorrectly formed"
```

**Root Cause:** The `cis_users` table doesn't exist when the schema runs, so foreign key constraints fail.

### Our Solution
✅ **Tables created WITHOUT foreign keys** (safe, reliable)
✅ **Foreign keys in separate script** (run after cis_users is confirmed)
✅ **DROP statements added at top** (clean slate)
✅ **MariaDB 10.5 optimized** (perfect for your system)

---

## 🔧 Deployment Steps

### Step 1: Get Your Database Credentials

You need to know:
- **DB_HOST** - Usually `localhost`
- **DB_NAME** - Usually `jcepnzzkmj`
- **DB_USER** - Root or admin user
- **DB_PASS** - Your MySQL password

**Option A:** Check your CIS configuration file
```bash
grep -r "DB_" /home/master/applications/jcepnzzkmj/public_html/config/ | head -5
```

**Option B:** Check if there's a `.env` file
```bash
cat /home/master/applications/jcepnzzkmj/public_html/.env | grep DB
```

### Step 2: Deploy The Schema

```bash
# Replace with your actual credentials
mysql -u YOUR_DB_USER -p'YOUR_DB_PASS' YOUR_DB_NAME < \
  /home/master/applications/jcepnzzkmj/public_html/modules/base/sql/notification_messenger_schema.sql
```

**Example:**
```bash
mysql -u root -p'your_password' jcepnzzkmj < \
  /home/master/applications/jcepnzzkmj/public_html/modules/base/sql/notification_messenger_schema.sql
```

### Step 3: Verify Tables Created

```bash
mysql -u YOUR_DB_USER -p'YOUR_DB_PASS' YOUR_DB_NAME -e "SHOW TABLES LIKE 'chat_%' OR LIKE 'notification%';"
```

**Expected Output:**
```
Tables_in_jcepnzzkmj
chat_blocked_users
chat_conversations
chat_group_members
chat_message_read_receipts
chat_messages
chat_typing_indicators
notification_delivery_queue
notification_messenger_links
notification_preferences
notifications
```

### Step 4: (Optional Later) Add Foreign Keys

Once you've confirmed `cis_users` table exists with a `user_id` INT PRIMARY KEY column:

```bash
mysql -u YOUR_DB_USER -p'YOUR_DB_PASS' YOUR_DB_NAME < \
  /home/master/applications/jcepnzzkmj/public_html/modules/base/sql/notification_messenger_foreign_keys.sql
```

---

## 📊 What Gets Created

### 10 Tables

| Table | Purpose | Rows |
|-------|---------|------|
| `notifications` | Sent notifications | User notifications |
| `notification_preferences` | User settings | 1 per user |
| `notification_delivery_queue` | Email/push delivery | Queued items |
| `chat_conversations` | Chat metadata | Groups, DMs, bots |
| `chat_messages` | Individual messages | All messages |
| `chat_message_read_receipts` | Read status | Who read what |
| `chat_group_members` | Group membership | Group members |
| `chat_typing_indicators` | Typing status | Real-time |
| `chat_blocked_users` | User blocks | Block list |
| `notification_messenger_links` | Integration glue | Cross-ref |

### 40+ Indexes
- User lookups (user_id)
- Category filtering (category, priority)
- Time-based queries (created_at)
- Full-text search on messages
- Unique constraints on combinations

### Zero Data
Tables are empty and ready to receive messages!

---

## ✅ Verification Commands

After deployment, run these to verify:

```bash
# List all tables
mysql -u YOUR_USER -p'YOUR_PASS' YOUR_DB -e "SHOW TABLES;"

# Check table structure
mysql -u YOUR_USER -p'YOUR_PASS' YOUR_DB -e "DESCRIBE notifications;"

# Count indexes
mysql -u YOUR_USER -p'YOUR_PASS' YOUR_DB -e "SELECT TABLE_NAME, COUNT(*) as INDEX_COUNT FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = DATABASE() GROUP BY TABLE_NAME;"

# Check for any errors
mysql -u YOUR_USER -p'YOUR_PASS' YOUR_DB -e "SHOW ERRORS;"
```

---

## 🐛 Troubleshooting

### Error: "Access denied for user"
**Solution:** Use correct credentials (check Step 1)

### Error: "Table already exists"
**Solution:** Schema script has DROP statements - should auto-clean. If not:
```bash
mysql -u root -p'password' database -e "DROP TABLE IF EXISTS notifications; DROP TABLE IF EXISTS chat_conversations;"
# Then re-run the schema
```

### Error: "Foreign key constraint"
**Solution:** Don't add foreign keys yet (they're in separate file)
- Tables work fine without them
- Add FKs later once cis_users is confirmed

### Error: "Column doesn't exist"
**Solution:** Tables were partially created - run full DROP and re-deploy:
```sql
SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS notifications;
DROP TABLE IF EXISTS notification_preferences;
DROP TABLE IF EXISTS notification_delivery_queue;
DROP TABLE IF EXISTS chat_conversations;
DROP TABLE IF EXISTS chat_messages;
DROP TABLE IF EXISTS chat_message_read_receipts;
DROP TABLE IF EXISTS chat_group_members;
DROP TABLE IF EXISTS chat_typing_indicators;
DROP TABLE IF EXISTS chat_blocked_users;
DROP TABLE IF EXISTS notification_messenger_links;
SET FOREIGN_KEY_CHECKS = 1;
```

Then re-run the schema script.

---

## 📁 Files You Have

### Main Schema (Run This First)
```
/modules/base/sql/notification_messenger_schema.sql
```
- 10 tables created
- 40+ indexes added
- No foreign keys (safe to run)
- Clean drop statements at top

### Foreign Keys (Run After cis_users confirmed)
```
/modules/base/sql/notification_messenger_foreign_keys.sql
```
- Add all foreign key constraints
- Only if cis_users exists
- Can be run separately later

---

## 🎯 Next Steps

1. **Get your DB credentials** (username, password, database name)
2. **Run the main schema** (10 seconds)
3. **Verify tables exist** (5 seconds)
4. **Test API endpoints** (ready in NotificationEngine.php)
5. **Add FKs later** (when cis_users confirmed)

---

## 💡 Need Help?

Check these resources:
- BACKEND_IMPLEMENTATION_GUIDE.md - Full setup guide
- BACKEND_QUICK_REFERENCE.md - API endpoints
- NotificationEngine.php - Core logic with examples

---

**Status:** ✅ Schema is MariaDB 10.5 compatible and ready to deploy!
