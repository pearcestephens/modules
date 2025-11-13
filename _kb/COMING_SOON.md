# 🚧 CONTROL PANEL - COMING SOON

## 🎯 Planned Upgrades & Features

### ✅ **COMPLETED**
- [x] ModuleRegistry service (auto-discovery, versioning)
- [x] ConfigManager service (type-safe config with history)
- [x] BackupManager service (onsite/offsite backups)
- [x] EnvironmentSync service (dev/staging/prod sync)
- [x] DocumentationBuilder service (auto-generated docs)
- [x] Dashboard view (system overview)
- [x] Modules view (module manager)

---

### 🔜 **COMING SOON**

#### **Phase 1: Core Views** (Next 2-3 days)
- [ ] **Config Page** - Visual configuration editor
  - Category-based organization
  - Type-safe input fields (string/int/bool/json)
  - Change history viewer
  - Import/export JSON
  - Search & filter configs
  - Per-module configuration sections

- [ ] **Backups Page** - Backup management interface
  - Create backup button
  - Backup history table
  - Restore from backup
  - Download backups
  - Offsite upload status
  - Automatic cleanup scheduler

- [ ] **Environments Page** - Environment sync controls
  - Visual sync direction selector (prod→staging→dev)
  - Dry-run mode toggle
  - Table selection
  - PII sanitization toggle
  - Schema comparison tool
  - Sync history log

- [ ] **Documentation Page** - Module docs viewer
  - Generate all docs button
  - Module documentation browser
  - API reference
  - Database schema viewer
  - Search documentation
  - Export to PDF

- [ ] **System Info Page** - System diagnostics
  - PHP version & extensions
  - Database connection test
  - Server resources (CPU/RAM/disk)
  - CIS version info
  - Dependency checker
  - Performance metrics

- [ ] **Logs Page** - Log viewer
  - Real-time log tail
  - Filter by level (INFO/ERROR/WARNING)
  - Search logs
  - Download logs
  - Clear old logs
  - Log rotation settings

---

#### **Phase 2: API Endpoints** (Week 2)
- [ ] `/api/modules.php` - Module management API
  - GET: List all modules
  - POST: Enable/disable module
  - PUT: Update module config
  - DELETE: Remove module from registry

- [ ] `/api/config.php` - Configuration API
  - GET: Retrieve config values
  - POST: Set config value
  - DELETE: Remove config
  - GET /history: Config change history

- [ ] `/api/backups.php` - Backup management API
  - GET: List backups
  - POST: Create backup
  - POST /restore: Restore from backup
  - DELETE: Delete backup
  - POST /offsite: Upload to offsite

- [ ] `/api/sync.php` - Environment sync API
  - POST: Trigger sync job
  - GET: Sync job status
  - GET /compare: Schema comparison
  - GET /history: Sync history

---

#### **Phase 3: Advanced Features** (Week 3-4)
- [ ] **File Browser Modal** - View module files in-app
- [ ] **Live Log Streaming** - WebSocket/SSE for real-time logs
- [ ] **Module Marketplace** - Install modules from repository
- [ ] **Backup Scheduler UI** - Visual cron job builder
- [ ] **Config Templates** - Pre-configured setting bundles
- [ ] **Module Dependencies** - Auto-install required modules
- [ ] **Rollback System** - One-click config/backup rollback
- [ ] **Audit Trail** - Who changed what, when
- [ ] **Notifications** - Alerts for backup failures, sync errors
- [ ] **Dark Mode** - Theme switcher

---

#### **Phase 4: Integration** (Month 2)
- [ ] **Vend API Monitor** - Test Vend connectivity from Control Panel
- [ ] **Xero Integration Status** - View sync health
- [ ] **Deputy Integration** - Staff management sync
- [ ] **Email Test** - Send test emails from Control Panel
- [ ] **Cache Management** - Clear Redis/Memcached
- [ ] **Queue Monitor** - View background job status
- [ ] **Security Scanner** - Detect vulnerabilities
- [ ] **Performance Profiler** - Identify slow queries/endpoints

---

### 🎨 **UI/UX Enhancements**
- [ ] Custom CSS theme (CIS branding)
- [ ] Animated transitions
- [ ] Drag-and-drop module ordering
- [ ] Keyboard shortcuts
- [ ] Toast notifications
- [ ] Progress bars for long operations
- [ ] Mobile-responsive improvements
- [ ] Accessibility (ARIA labels, keyboard nav)

---

### 🔐 **Security Features**
- [ ] Two-factor auth for Control Panel access
- [ ] IP whitelist for admin access
- [ ] Action confirmation for destructive operations
- [ ] Encrypted config values (passwords, API keys)
- [ ] Audit log export
- [ ] Session timeout settings
- [ ] Failed login tracking

---

### 📊 **Reporting & Analytics**
- [ ] Module usage statistics
- [ ] Backup success rate graphs
- [ ] Configuration change trends
- [ ] System health dashboard
- [ ] Export reports to PDF/CSV
- [ ] Scheduled reports via email

---

## 💡 **Quick Wins** (Low effort, high value)
1. Add "Refresh" button to all pages
2. Breadcrumb navigation
3. Keyboard shortcut modal (`?` key)
4. Quick search (global search box)
5. Recently viewed modules
6. Favorite/pin modules
7. Module tags/categories
8. Bulk actions (enable/disable multiple modules)

---

## 🚀 **Future Ideas** (Wishlist)
- Visual config editor (drag-and-drop form builder)
- Module versioning & rollback
- A/B testing framework
- Feature flags system
- Multi-tenancy support
- Module analytics (most used, least used)
- Automated testing integration
- CI/CD pipeline triggers
- Slack/Discord notifications
- Custom dashboard widgets

---

**Priority Order:**
1. **Config Page** ⭐⭐⭐ (Most critical - replaces old config system)
2. **Backups Page** ⭐⭐⭐ (Data safety)
3. **System Info Page** ⭐⭐ (Diagnostics)
4. **Logs Page** ⭐⭐ (Debugging)
5. **Environments Page** ⭐ (Advanced feature)
6. **Documentation Page** ⭐ (Nice to have)

---

*Last updated: November 5, 2025*
