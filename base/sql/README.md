# SQL Directory - Notification & Messenger System

**Status:** ✅ **PRODUCTION DEPLOYED**
**Date:** November 11, 2025
**Database:** MariaDB 10.5.29

---

## 📁 Files in This Directory

### Schema Files

#### 1. **notification_messenger_schema_clean.sql** ⭐ (DEPLOYED)
- **Status:** ✅ Currently deployed to database
- **Purpose:** Clean, minimal schema creation
- **Tables:** 10 tables created
- **Size:** ~350 lines
- **Deployment:** `mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj < notification_messenger_schema_clean.sql`
- **Result:** All tables created successfully ✅

#### 2. **notification_messenger_schema_integrated.sql**
- **Status:** ⏳ Alternative version (not deployed)
- **Purpose:** Integrates with existing chat infrastructure
- **Use Case:** For systems with pre-existing chat tables
- **Note:** Skipped in favor of clean deployment

#### 3. **notification_messenger_schema.sql**
- **Status:** 📦 Original reference version
- **Purpose:** Full schema with drop statements
- **Note:** Superseded by clean version
- **ForeignKeys:** Commented out in this version

#### 4. **notification_messenger_foreign_keys.sql**
- **Status:** ⏳ Not deployed (optional)
- **Purpose:** Adds foreign key constraints
- **Deploy When:** After `cis_users` table is confirmed
- **Command:** `mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj < notification_messenger_foreign_keys.sql`

---

### Documentation Files

#### 1. **DEPLOYMENT_SUCCESS.md** ⭐ (READ FIRST)
- **Status:** ✅ Current deployment status
- **Content:**
  - Table summary (10 tables, 59 indexes)
  - Database credentials
  - Features ready to use
  - Next steps
  - Performance expectations
  - Success criteria
- **Audience:** Everyone
- **Action:** Read for deployment confirmation

#### 2. **DB_CREDENTIALS.md**
- **Status:** ✅ Secure credential storage
- **Content:**
  - Host, port, database, user, password
  - Quick deploy commands
  - Verification queries
  - Security notes
- **Audience:** Developers, DevOps
- **Action:** Keep secure, don't commit to Git

#### 3. **DEPLOYMENT_GUIDE.md**
- **Status:** ✅ Complete setup guide
- **Content:**
  - Step-by-step deployment
  - Credential collection
  - Deploy commands
  - Verification steps
  - Troubleshooting guide
- **Audience:** DevOps, new developers
- **Action:** Follow for fresh deployments

#### 4. **SCHEMA_FIX_SUMMARY.md**
- **Status:** 📖 Historical reference
- **Content:**
  - Problems encountered
  - Solutions applied
  - Before/after comparison
  - Learning points
- **Audience:** Developers interested in history
- **Action:** Reference if issues arise

---

### Testing Files

#### 1. **test_apis.sh**
- **Status:** ⏳ Ready to use
- **Purpose:** Test all API endpoints
- **Usage:** `./test_apis.sh` (after setting BEARER_TOKEN)
- **Tests:** 15+ API tests
- **Setup:** Update `BEARER_TOKEN` variable with actual token
- **Requirements:**
  - curl installed
  - API server running
  - Valid bearer token

---

## 🚀 Quick Start

### Deploy Database (First Time)
```bash
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj < notification_messenger_schema_clean.sql
```

### Verify Deployment
```bash
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e \
  "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES
   WHERE TABLE_SCHEMA = 'jcepnzzkmj'
   AND (TABLE_NAME LIKE 'chat_%' OR TABLE_NAME LIKE 'notification%');"
```

### Test APIs
```bash
# Edit test_apis.sh and set BEARER_TOKEN
chmod +x test_apis.sh
./test_apis.sh
```

### Add Foreign Keys (Later)
```bash
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj < notification_messenger_foreign_keys.sql
```

---

## 📊 Database Summary

### Tables Created: 10

**Notification System (3 tables):**
- `notifications` - User notifications
- `notification_preferences` - User settings
- `notification_delivery_queue` - Email/push queue

**Messenger System (6 tables):**
- `chat_conversations` - Chat rooms/DMs
- `chat_messages` - Individual messages
- `chat_message_read_receipts` - Read tracking
- `chat_group_members` - Group membership
- `chat_typing_indicators` - Typing status
- `chat_blocked_users` - User blocking

**Integration (1 table):**
- `notification_messenger_links` - Notification/message links

### Total Statistics

- **Tables:** 10
- **Columns:** 180+
- **Indexes:** 59
- **Character Set:** UTF8MB4 (emoji support)
- **Engine:** InnoDB (ACID transactions)
- **Storage:** ~2MB (empty, will grow with data)

---

## 🔐 Credentials

**Host:** 127.0.0.1
**Port:** 3306
**Database:** jcepnzzkmj
**User:** jcepnzzkmj
**Password:** wprKh9Jq63

**⚠️ Security:** Don't commit credentials to Git. Use environment variables in production.

---

## 🎯 What's Ready to Use

✅ **Database:** Deployed and tested
✅ **Tables:** All created
✅ **Indexes:** Optimized for queries
✅ **Backend Code:** In `/modules/base/lib/` and `/modules/base/api/`
✅ **API Endpoints:** 14 endpoints ready
✅ **WebSocket Handler:** Ready for real-time

---

## ⏭️ Next Steps

1. **Test Endpoints** - Use `test_apis.sh` or curl
2. **Connect Frontend** - ChatManager.js to API
3. **Set Up WebSocket** - For real-time messaging
4. **Configure Workers** - Email/push notification delivery
5. **Add Foreign Keys** - Once `cis_users` confirmed

---

## 📚 Related Files

- **Backend Code:** `/modules/base/lib/NotificationEngine.php`
- **API Endpoints:** `/modules/base/api/notifications.php`, `/modules/base/api/messenger.php`
- **Frontend Code:** ChatManager.js, notification UI components
- **Documentation:** BACKEND_IMPLEMENTATION_GUIDE.md, BACKEND_QUICK_REFERENCE.md

---

## 🆘 Troubleshooting

### Connection Failed
```bash
# Check credentials
mysql -h 127.0.0.1 -u jcepnzzkmj -p'wprKh9Jq63' -e "SELECT VERSION();"
```

### Tables Not Found
```bash
# List all tables
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "SHOW TABLES;"

# Count notification/chat tables
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e \
  "SELECT COUNT(*) as table_count FROM INFORMATION_SCHEMA.TABLES
   WHERE TABLE_SCHEMA = 'jcepnzzkmj'
   AND (TABLE_NAME LIKE 'chat_%' OR TABLE_NAME LIKE 'notification%');"
```

### Foreign Key Errors
- Normal if `cis_users` table doesn't exist
- Use `notification_messenger_foreign_keys.sql` later
- Tables work fine without FKs

---

## 📞 Support

For questions or issues:
1. Check DEPLOYMENT_SUCCESS.md
2. Check BACKEND_IMPLEMENTATION_GUIDE.md
3. Review schema in notification_messenger_schema_clean.sql
4. Check database with test queries

---

**Status:** ✅ All files ready for production use.

Last updated: November 11, 2025
