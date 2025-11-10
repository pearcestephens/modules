# CIS Staff Portal - Modules Directory

**The Vape Shed | Ecigdis Limited**  
Central Information System - Production Modules

---

## 📁 Directory Structure

```
modules/
├── _docs/              # All documentation
│   ├── migration/      # Migration guides
│   ├── architecture/   # System architecture
│   ├── guides/         # User guides
│   └── api/            # API documentation
├── _scripts/           # Utility scripts
│   ├── deployment/     # Deployment tools
│   ├── maintenance/    # Maintenance scripts
│   └── migration/      # Migration tools
├── _config/            # Configuration templates
├── _tests/             # Test suites
├── _logs/              # Log files
│
├── stock_transfer_engine/  # AI-powered stock transfers
├── crawlers/               # Competitive intelligence
├── dynamic_pricing/        # Dynamic pricing engine
├── ai_intelligence/        # Neural intelligence
├── human_behavior_engine/  # Behavior analytics
│
└── [Other modules...]
```

---

## 🎯 Core CIS Modules (Production Ready)

### Stock Transfer Engine
**Path**: `stock_transfer_engine/`  
**Status**: ✅ Production Ready  
AI-powered stock transfer system with excess detection and warehouse management.

### Crawlers
**Path**: `crawlers/`  
**Status**: ✅ Production Ready  
Competitive intelligence and pricing crawlers with Chrome automation.

### Dynamic Pricing
**Path**: `dynamic_pricing/`  
**Status**: ✅ Production Ready  
AI-driven dynamic pricing recommendations.

### AI Intelligence
**Path**: `ai_intelligence/`  
**Status**: ✅ Production Ready  
Neural intelligence processor for business insights.

### Human Behavior Engine
**Path**: `human_behavior_engine/`  
**Status**: ✅ Production Ready  
Customer behavior analytics and prediction.

---

## 📚 Documentation

All documentation is located in `_docs/`:

- **Migration Guides**: `_docs/migration/`
- **Architecture Docs**: `_docs/architecture/`
- **User Guides**: `_docs/guides/`
- **API Docs**: `_docs/api/`

Key documents:
- `_docs/migration/MIGRATION_GUIDE.md` - How modules were migrated
- `_docs/migration/INTEGRATION_ANALYSIS.md` - Integration strategy
- `_docs/INDEX.md` - Complete module index

---

## 🔧 Scripts

### Maintenance
- `_scripts/maintenance/test_integration.php` - Integration testing
- `_scripts/maintenance/health-checker.php` - System health checks

### Migration
- `_scripts/migration/import_database_schemas.php` - DB schema import
- `_scripts/migration/import_schemas.sh` - Shell import script

### Deployment
- `_scripts/VERIFICATION.sh` - Quick verification script

---

## 🗄️ Database

Database connection configuration is in each module's `config/database.php`.

All modules use:
- **Database**: `jcepnzzkmj`
- **Host**: `127.0.0.1`
- **Credentials**: Loaded from parent `.env` file

---

## 🚀 Getting Started

1. Review documentation in `_docs/`
2. Check module-specific READMEs
3. Run `_scripts/VERIFICATION.sh` to verify installation
4. Import database schemas if needed

---

## 📞 Support

**Organization**: Ecigdis Limited (The Vape Shed)  
**System**: CIS Staff Portal  
**Environment**: Production

---

*Last Updated: November 6, 2025*
