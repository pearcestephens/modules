# 🎛️ CIS CONTROL PANEL

**Version:** 1.0.0
**Status:** ✅ Core Infrastructure Complete
**Author:** Pearce Stephens <pearce.stephens@ecigdis.co.nz>

---

## 📋 WHAT IS THIS?

The **CIS Control Panel** is the central management system for the entire CIS application. It replaces the old config table with a sophisticated admin interface for:

- 🔧 **System Configuration** - Type-safe settings with version history
- 📦 **Module Management** - Enable/disable/configure all modules
- 💾 **Backup & Restore** - Database backups with offsite storage
- 🔄 **Environment Sync** - Sync data between dev/staging/production
- 📚 **Auto Documentation** - Generate docs for all modules
- 📊 **System Monitoring** - PHP, database, and server stats

---

## ✅ CURRENT STATUS (READY TO USE)

### **Service Layer** (100% Complete)
All core business logic is implemented and tested:

| Service | Status | Purpose |
|---------|--------|---------|
| **ModuleRegistry.php** | ✅ Ready | Auto-discover modules, track versions, file stats |
| **ConfigManager.php** | ✅ Ready | Type-safe config with change history |
| **BackupManager.php** | ✅ Ready | Database backups (local + S3/FTP/SFTP/Rsync) |
| **EnvironmentSync.php** | ✅ Ready | Sync databases between environments |
| **DocumentationBuilder.php** | ✅ Ready | Auto-generate markdown documentation |

### **Views** (40% Complete)
User interface pages:

| View | Status | Description |
|------|--------|-------------|
| **dashboard.php** | ✅ Ready | System overview with stats & quick actions |
| **modules.php** | ✅ Ready | Module inventory with search & filters |
| **config.php** | ⏳ Pending | Configuration editor |
| **backups.php** | ⏳ Pending | Backup management interface |
| **environments.php** | ⏳ Pending | Environment sync controls |
| **documentation.php** | ⏳ Pending | Documentation viewer |
| **system-info.php** | ⏳ Pending | System diagnostics |
| **logs.php** | ⏳ Pending | Log viewer |

### **Infrastructure** (100% Complete)
- ✅ **bootstrap.php** - Module initialization with admin auth
- ✅ **index.php** - Router with 8 page endpoints
- ✅ **.env.example** - Complete configuration template
- ✅ **Database tables** - All schemas created

---

## 🗄️ DATABASE TABLES

The Control Panel creates these tables automatically:

```sql
cis_module_registry        -- Module inventory with versions
cis_configuration          -- System configuration values
cis_configuration_history  -- Config change audit trail
cis_backups                -- Backup inventory and status
cis_sync_jobs              -- Environment sync history
```

---

## 🚀 HOW TO USE

### **1. Access Control Panel**
```
https://staff.vapeshed.co.nz/modules/control-panel/
```

**Authentication:** Admin or Manager role required (checked automatically)

### **2. Dashboard**
- View system statistics (modules, backups, database size)
- Quick actions (scan modules, create backup, generate docs)
- Recent activity (last 5 backups, top 5 modules)

### **3. Module Manager**
- See all installed modules with versions
- Search and filter by status
- Generate documentation per module
- View file counts and sizes

### **4. Configuration** (Coming Soon)
- Edit system settings with type safety
- View change history (who changed what, when)
- Import/export configurations
- Per-module settings

### **5. Backups**
```php
// Create backup programmatically
$backupManager = new BackupManager($pdo);
$result = $backupManager->backupDatabase($userId);

// Backups are automatically uploaded to offsite if enabled
// Local backups are kept for 30 days by default
```

### **6. Environment Sync** (Disabled by default)
```php
// Sync staging from production (with PII sanitization)
$envSync = new EnvironmentSync($pdo);
$result = $envSync->syncDatabase('prod', 'staging', [
    'sanitize' => true,
    'dry_run' => false
]);
```

---

## ⚙️ CONFIGURATION

Copy `.env.example` to your main `.env` file and configure:

### **Required Settings**
```env
APP_ENV=production                 # Current environment
```

### **Backup Settings**
```env
BACKUP_RETENTION_DAYS=30           # Keep backups for 30 days
BACKUP_COMPRESS=true               # Compress backups with gzip
BACKUP_OFFSITE_ENABLED=false       # Offsite disabled by default
```

### **Offsite Backup (Optional)**
```env
BACKUP_OFFSITE_ENABLED=true
BACKUP_OFFSITE_TYPE=s3             # Options: s3, ftp, sftp, rsync
BACKUP_KEEP_LOCAL=true             # Keep local copy after upload

# For S3 (or compatible):
BACKUP_S3_BUCKET=cis-backups
BACKUP_S3_REGION=ap-southeast-2
BACKUP_S3_KEY=your-access-key
BACKUP_S3_SECRET=your-secret-key
```

### **Environment Sync (Optional)**
```env
SYNC_ENABLED=false                 # Disabled by default
SYNC_SANITIZE_PII=true             # Sanitize emails, passwords, etc.
SYNC_EXCLUDE_TABLES=sessions,logs,cache

# Development database
DEV_DB_HOST=localhost
DEV_DB_NAME=cis_dev
DEV_DB_USER=dev_user
DEV_DB_PASS=dev_password

# Staging database
STAGING_DB_HOST=staging.server
STAGING_DB_NAME=cis_staging
STAGING_DB_USER=staging_user
STAGING_DB_PASS=staging_password
```

---

## 🔒 SECURITY

### **Access Control**
- Only users with `role = 'admin'` or `role = 'manager'` can access
- Or users with `control_panel` permission in their permissions JSON
- Automatic redirect to login if unauthorized

### **Production Safety**
- Cannot sync TO production without `force_prod=true` flag
- All database operations use prepared statements
- PII sanitization enabled by default for syncs
- Backup checksums (SHA256) for integrity verification

### **Configuration Security**
- Sensitive values can be flagged (hidden in UI)
- Read-only configs cannot be changed via UI
- Full audit trail of all changes
- JSON import/export for backup/restore

---

## 📊 FEATURES IN DETAIL

### **ModuleRegistry**
- Automatically scans `/modules/` directory
- Extracts version, author, description from files
- Calculates file counts and sizes recursively
- Tracks last modified dates
- Stores metadata as JSON
- Status management (active/inactive/development)

### **ConfigManager**
- 6 data types: string, int, float, bool, json, array
- Automatic type casting and validation
- Version incrementing on changes
- Complete change history with timestamps
- Category organization (general, database, email, etc.)
- Per-module configuration sections
- Export all configs to JSON
- Import configs from JSON

### **BackupManager**
- Database backups via mysqldump
- Gzip compression (optional)
- SHA256 checksums for verification
- Automatic offsite upload (S3/FTP/SFTP/Rsync)
- Configurable retention policy (default 30 days)
- Restore from any backup
- Backup status tracking (pending/in_progress/completed/failed)
- Detailed logging to `backups/backup.log`

### **EnvironmentSync**
- Sync entire database or specific tables
- Source → Target sync (prod→staging, staging→dev, etc.)
- PII sanitization (emails, passwords, phone numbers, etc.)
- Table exclusion (never sync sessions, logs, cache)
- Dry-run mode (preview without changes)
- Schema comparison (detect differences between environments)
- Production protection (requires force flag)
- Complete sync history with timing and stats

### **DocumentationBuilder**
- Generates markdown docs for each module
- Extracts classes, methods, and docblocks
- Discovers API endpoints with HTTP methods
- Parses database schema (CREATE TABLE statements)
- Lists all files with sizes and dates
- Finds dependencies from composer.json
- Extracts configuration options ($_ENV variables)
- Creates INDEX.md linking all modules
- Formatted with tables, code blocks, and emojis

---

## 📁 FILE STRUCTURE

```
/modules/control-panel/
├── bootstrap.php              # Module initialization
├── index.php                  # Router (GET ?page=...)
├── .env.example               # Configuration template
├── README.md                  # This file
├── COMING_SOON.md             # Roadmap
├── CONTROL_PANEL_STATUS.md    # Status summary
│
├── lib/                       # Service classes
│   ├── ModuleRegistry.php     # Module discovery & tracking
│   ├── ConfigManager.php      # Configuration management
│   ├── BackupManager.php      # Backup & restore
│   ├── EnvironmentSync.php    # Environment synchronization
│   └── DocumentationBuilder.php # Auto-generate docs
│
├── views/                     # UI pages
│   ├── dashboard.php          # Main overview
│   ├── modules.php            # Module manager
│   ├── config.php             # [TODO]
│   ├── backups.php            # [TODO]
│   ├── environments.php       # [TODO]
│   ├── documentation.php      # [TODO]
│   ├── system-info.php        # [TODO]
│   └── logs.php               # [TODO]
│
├── api/                       # [TODO] JSON endpoints
├── assets/                    # [TODO] CSS/JS
├── backups/                   # Backup storage
├── docs/                      # Generated documentation
└── logs/                      # Sync/backup logs
```

---

## 🔧 TECHNICAL DETAILS

### **Requirements**
- PHP 7.4+
- MySQL/MariaDB
- PDO extension
- JSON extension
- (Optional) AWS SDK for S3 backups
- (Optional) SSH2 extension for SFTP

### **Design Patterns**
- Service layer architecture
- Dependency injection
- PSR-12 coding standards
- Prepared statements (SQL injection prevention)
- Type hints and return types
- Namespaced classes (`CIS\ControlPanel`)

### **Database Design**
- InnoDB engine for ACID compliance
- UTF-8mb4 character set (emoji support)
- Indexed columns for performance
- JSON columns for flexible metadata
- ENUM types for fixed values
- Foreign key relationships where appropriate

---

## 🎯 WHAT'S WORKING RIGHT NOW

You can immediately use:

1. ✅ **View Dashboard** - See system overview
2. ✅ **View Modules** - Browse all installed modules
3. ✅ **Scan Modules** - Discover new/updated modules
4. ✅ **Create Backups** - Run backups programmatically
5. ✅ **Generate Docs** - Create module documentation
6. ✅ **Module Search** - Filter modules by name/status

---

## 🚧 WHAT'S NEXT

Priority order for completion:

1. **Config page** - Visual editor for system settings
2. **Backups page** - UI for backup management
3. **System Info page** - PHP/database diagnostics
4. **Logs page** - View system logs
5. **API endpoints** - RESTful JSON APIs
6. **CSS/JS assets** - Custom styling and interactions

See `COMING_SOON.md` for full roadmap.

---

## 💡 USAGE EXAMPLES

### **Backup Database**
```php
require_once 'bootstrap.php';
$backupManager = new BackupManager($pdo);
$result = $backupManager->backupDatabase($_SESSION['user_id']);

if ($result['success']) {
    echo "Backup created: {$result['filename']}";
    echo "Size: {$result['size']} bytes";
    echo "Checksum: {$result['checksum']}";
}
```

### **Discover Modules**
```php
$moduleRegistry = new ModuleRegistry($pdo);
$result = $moduleRegistry->discoverModules();
echo "Found {$result['discovered']} modules";
```

### **Get Configuration**
```php
$configManager = new ConfigManager($pdo);
$siteName = $configManager->get('site_name', 'Default Site');
$maxUpload = $configManager->get('max_upload_size', 10, 'int');
```

### **Set Configuration**
```php
$configManager->set('site_name', 'The Vape Shed CIS', [
    'category' => 'general',
    'description' => 'Site name shown in header'
]);
```

### **Generate Documentation**
```php
$docBuilder = new DocumentationBuilder($pdo);
$result = $docBuilder->generateModuleDocs('staff-performance');
echo "Generated: {$result['filename']}";
```

---

## 🆘 TROUBLESHOOTING

### **"Access Denied"**
- Ensure user has `role = 'admin'` or `role = 'manager'`
- Check `permissions` JSON for `control_panel` permission

### **"Module Not Found"**
- Run "Scan Modules" from dashboard
- Check that module exists in `/modules/` directory

### **Backups Failing**
- Check database credentials in `.env`
- Ensure `backups/` directory is writable (755 or 775)
- Check `backups/backup.log` for errors

### **Offsite Upload Failing**
- Verify S3/FTP credentials in `.env`
- Test connection manually
- Check firewall rules for outbound connections

---

## 📞 SUPPORT

For questions or issues:
- **Developer:** Pearce Stephens
- **Email:** pearce.stephens@ecigdis.co.nz
- **Docs:** See `COMING_SOON.md` for roadmap

---

**Last Updated:** November 5, 2025
**Status:** ✅ Core Ready for Production Use
