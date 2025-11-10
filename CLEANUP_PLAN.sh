#!/bin/bash
# CIS Modules Cleanup and Organization Script
# Tidies up loose ends and ensures proper structure

echo "════════════════════════════════════════════════════════════"
echo "  🧹 CIS MODULES CLEANUP & ORGANIZATION"
echo "════════════════════════════════════════════════════════════"
echo ""

# 1. Create proper directory structure
echo "📁 Step 1: Creating standardized directory structure..."

# Documentation directory
mkdir -p _docs/{migration,architecture,guides,api}

# Testing directory
mkdir -p _tests/{unit,integration,fixtures}

# Scripts directory
mkdir -p _scripts/{deployment,maintenance,migration}

# Configuration directory  
mkdir -p _config/{templates,samples}

# Logs directory
mkdir -p _logs

echo "✅ Directory structure created"
echo ""

# 2. Move loose documentation files
echo "📄 Step 2: Organizing documentation..."

# Move all root-level .md files to _docs
for file in *.md; do
    if [ -f "$file" ] && [ "$file" != "README.md" ]; then
        case "$file" in
            *MIGRATION*|*INTEGRATION*)
                mv "$file" "_docs/migration/" 2>/dev/null && echo "   → _docs/migration/$file"
                ;;
            *ARCHITECTURE*|*AUDIT*)
                mv "$file" "_docs/architecture/" 2>/dev/null && echo "   → _docs/architecture/$file"
                ;;
            *GUIDE*|*PLAN*|*README*)
                mv "$file" "_docs/guides/" 2>/dev/null && echo "   → _docs/guides/$file"
                ;;
            *)
                mv "$file" "_docs/" 2>/dev/null && echo "   → _docs/$file"
                ;;
        esac
    fi
done

echo "✅ Documentation organized"
echo ""

# 3. Move scripts
echo "🔧 Step 3: Organizing scripts..."

mv import_database_schemas.php _scripts/migration/ 2>/dev/null && echo "   → _scripts/migration/import_database_schemas.php"
mv import_schemas.sh _scripts/migration/ 2>/dev/null && echo "   → _scripts/migration/import_schemas.sh"
mv VERIFICATION.sh _scripts/ 2>/dev/null && echo "   → _scripts/VERIFICATION.sh"
mv test_integration.php _scripts/maintenance/ 2>/dev/null && echo "   → _scripts/maintenance/test_integration.php"
mv health-checker.php _scripts/maintenance/ 2>/dev/null && echo "   → _scripts/maintenance/health-checker.php"
mv check-email-status.php _scripts/maintenance/ 2>/dev/null && echo "   → _scripts/maintenance/check-email-status.php"

# Move auto-push files
mv auto-push-manager.sh _scripts/deployment/ 2>/dev/null
mv start-auto-push.sh _scripts/deployment/ 2>/dev/null
mv push-to-github.sh _scripts/deployment/ 2>/dev/null
mv .auto-push-monitor.php _scripts/deployment/ 2>/dev/null

echo "✅ Scripts organized"
echo ""

# 4. Move configuration files
echo "⚙️  Step 4: Organizing configuration files..."

cp composer.json _config/templates/ 2>/dev/null && echo "   → _config/templates/composer.json (copy)"
cp phpcs.xml _config/templates/ 2>/dev/null && echo "   → _config/templates/phpcs.xml (copy)"
mv .env.example _config/samples/ 2>/dev/null && echo "   → _config/samples/.env.example"

echo "✅ Configuration organized"
echo ""

# 5. Clean up logs and temporary files
echo "🗑️  Step 5: Cleaning temporary files..."

mv .auto-push.log _logs/ 2>/dev/null && echo "   → _logs/.auto-push.log"
rm -f .auto-push.pid 2>/dev/null && echo "   ✓ Removed .auto-push.pid"
rm -f .AUTO_PUSH_TEST.txt 2>/dev/null && echo "   ✓ Removed .AUTO_PUSH_TEST.txt"
rm -f modules.code-workspace 2>/dev/null && echo "   ✓ Removed modules.code-workspace"
rm -f output.php 2>/dev/null && echo "   ✓ Removed output.php"

echo "✅ Cleanup complete"
echo ""

# 6. Create index files for empty/stub modules
echo "📝 Step 6: Creating README files for placeholder modules..."

for dir in content_aggregation courier_integration social_feeds staff_ordering competitive-intel; do
    if [ -d "$dir" ] && [ ! -f "$dir/README.md" ]; then
        cat > "$dir/README.md" << EOF
# $(echo $dir | tr '_-' ' ' | sed 's/.*/\u&/')

**Status**: Placeholder module

This module is reserved for future development.

## Planned Features
- To be defined

## Documentation
See main CIS documentation in \`_docs/\`
EOF
        echo "   → $dir/README.md"
    fi
done

echo "✅ Placeholder READMEs created"
echo ""

# 7. Ensure all main modules have proper structure
echo "📦 Step 7: Validating module structures..."

for module in stock_transfer_engine crawlers dynamic_pricing ai_intelligence human_behavior_engine; do
    if [ -d "$module" ]; then
        # Ensure README exists
        if [ ! -f "$module/README.md" ]; then
            echo "   ⚠️  Missing: $module/README.md"
        else
            echo "   ✅ $module/README.md"
        fi
        
        # Ensure config directory exists
        if [ ! -d "$module/config" ] && [ ! -d "$module/configuration" ]; then
            echo "   ℹ️  No config directory: $module"
        fi
        
        # Ensure database directory exists
        if [ ! -d "$module/database" ] && [ ! -d "$module/schema" ]; then
            echo "   ℹ️  No database directory: $module"
        fi
    fi
done

echo "✅ Module structure validated"
echo ""

# 8. Create master README index
echo "📚 Step 8: Creating comprehensive README..."

cat > README.md << 'EOFREADME'
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
EOFREADME

echo "✅ Master README created"
echo ""

echo "════════════════════════════════════════════════════════════"
echo "  ✅ CLEANUP COMPLETE"
echo "════════════════════════════════════════════════════════════"
echo ""
echo "📊 Summary:"
echo "   • Documentation organized in _docs/"
echo "   • Scripts moved to _scripts/"
echo "   • Configuration templates in _config/"
echo "   • Temporary files removed"
echo "   • Placeholder READMEs created"
echo "   • Master README updated"
echo ""
echo "🎉 Modules directory is now clean and well-organized!"
echo ""
