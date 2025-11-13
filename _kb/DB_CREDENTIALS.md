# CIS Database Credentials for Notification & Messenger Schema Deployment
# Stored: November 11, 2025
# MariaDB Version: 10.5.29

## Database Connection Details

**Host:** `127.0.0.1` (or `localhost`)
**Port:** `3306` (default)
**Database:** `jcepnzzkmj`
**Username:** `jcepnzzkmj`
**Password:** `wprKh9Jq63`

---

## Quick Deploy Commands

### Option 1: Using stored password (interactive)
```bash
mysql -u jcepnzzkmj -p jcepnzzkmj < notification_messenger_schema.sql
# When prompted, enter password: wprKh9Jq63
```

### Option 2: Using password in command (not recommended for scripts)
```bash
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj < notification_messenger_schema.sql
```

### Option 3: Using the deployment script (recommended)
```bash
./deploy_schema.sh -u jcepnzzkmj -p 'wprKh9Jq63' -d jcepnzzkmj -h 127.0.0.1
```

---

## Verify Connection

Test the database connection:
```bash
mysql -h 127.0.0.1 -u jcepnzzkmj -p'wprKh9Jq63' -e "SELECT VERSION();"
```

Expected output:
```
VERSION()
10.5.29-MariaDB-1~bullseye
```

---

## After Deployment

### Check Tables Created
```bash
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "SHOW TABLES LIKE 'chat_%' OR LIKE 'notification%';"
```

### Verify Data Types
```bash
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "DESCRIBE notifications;"
```

### Count Indexes
```bash
mysql -u jcepnzzkmj -p'wprKh9Jq63' jcepnzzkmj -e "SELECT TABLE_NAME, COUNT(*) as INDEX_COUNT FROM INFORMATION_SCHEMA.STATISTICS WHERE TABLE_SCHEMA = 'jcepnzzkmj' GROUP BY TABLE_NAME ORDER BY TABLE_NAME;"
```

---

## Security Notes

⚠️ **IMPORTANT:**
- This file contains database credentials
- **NEVER commit this to Git**
- **NEVER send this file to untrusted sources**
- **NEVER hardcode credentials in production code**
- Use environment variables (`.env`) or secrets vault in production

### For Production
Store credentials in:
1. Environment variables
2. Docker secrets
3. Kubernetes secrets
4. AWS Secrets Manager / Parameter Store
5. HashiCorp Vault

---

## API Connection

Once tables are deployed, the APIs will connect using these credentials via the base module's Database class:

```php
// In /modules/base/lib/Database.php
$pdo = new PDO(
    'mysql:host=127.0.0.1;dbname=jcepnzzkmj;charset=utf8mb4',
    'jcepnzzkmj',
    'wprKh9Jq63',
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);
```

---

## Support Files

See also:
- `DEPLOYMENT_GUIDE.md` - Full setup instructions
- `SCHEMA_FIX_SUMMARY.md` - What was fixed
- `deploy_schema.sh` - Automated deployment script
- `notification_messenger_foreign_keys.sql` - FKs (add later)

---

**Ready to deploy?** Run:
```bash
./deploy_schema.sh -u jcepnzzkmj -p 'wprKh9Jq63' -d jcepnzzkmj -h 127.0.0.1
```
