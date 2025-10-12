# 🧠 CIS Knowledge Base Master Setup Guide
## The Complete Autonomous Knowledge Management System

**Version:** 2.0.0  
**Last Updated:** October 12, 2025  
**Purpose:** Establish a self-maintaining, performance-optimized knowledge base system that grows with your codebase  
**Maintenance:** Autonomous with scheduled refresh cycles  

---

## 📋 Table of Contents

1. [Quick Start](#quick-start)
2. [Initial Setup Questions](#initial-setup-questions)
3. [Directory Structure](#directory-structure)
4. [File Relationships System](#file-relationships-system)
5. [Cron Jobs & Automation](#cron-jobs--automation)
6. [Knowledge Base Organization](#knowledge-base-organization)
7. [Maintenance Scripts](#maintenance-scripts)
8. [Performance Optimization](#performance-optimization)
9. [Do's and Don'ts](#dos-and-donts)
10. [Module-Specific KB Setup](#module-specific-kb-setup)
11. [Example Code & Templates](#example-code--templates)
12. [Troubleshooting](#troubleshooting)

---

## 🚀 Quick Start

### Prerequisites Checklist
- [ ] PHP 8.0+ with CLI access
- [ ] MySQL/MariaDB 10.5+
- [ ] Cron access (or systemd timers)
- [ ] Write permissions to project root
- [ ] Git repository initialized
- [ ] Minimum 500MB free disk space

### 5-Minute Setup
```bash
# 1. Create directory structure
php setup-kb.php --init

# 2. Answer setup questions (interactive)
php setup-kb.php --configure

# 3. Generate initial knowledge base
php refresh-kb.php --full

# 4. Install cron jobs
php setup-kb.php --install-cron

# 5. Verify installation
php verify-kb.php
```

---

## 🎯 Initial Setup Questions

### The Setup Wizard Will Ask:

#### 1. Project Structure
```
Q: What is your project root directory?
   Default: /home/master/applications/jcepnzzkmj/public_html/
   
Q: Is this a modular architecture?
   Options: [Yes - Multiple modules] / [No - Monolithic]
   
Q: Where are your modules located?
   Default: {project_root}/modules/
   
Q: List module names (comma-separated):
   Example: base,consignments,transfers,inventory,hr,crm
```

#### 2. Knowledge Base Configuration
```
Q: Where should the global KB be stored?
   Default: {project_root}/_kb/
   Recommendation: Outside public_html for security
   
Q: Should each module have its own KB?
   Default: Yes
   Location Pattern: {module_root}/{module_name}/_kb/
   
Q: Enable cross-module relationship tracking?
   Default: Yes (recommended for modular systems)
   
Q: Maximum KB file size before rotation?
   Default: 5MB
   Options: 1MB / 5MB / 10MB / 25MB
```

#### 3. Documentation Standards
```
Q: Primary documentation format?
   Options: Markdown / ReStructuredText / Plain Text
   Default: Markdown
   
Q: Include code examples in docs?
   Default: Yes
   
Q: Auto-generate API documentation?
   Default: Yes (PHPDocumentor or similar)
   
Q: Diagram generation (Mermaid/PlantUML)?
   Default: Yes - Mermaid (GitHub-compatible)
```

#### 4. Performance & Optimization
```
Q: Enable file relationship mapping?
   Default: Yes
   Note: Tracks include/require chains, class dependencies
   
Q: Enable performance profiling?
   Default: Yes
   Note: Tracks slow queries, bottleneck detection
   
Q: Enable dead code detection?
   Default: Yes
   Note: Identifies unused functions, classes, files
   
Q: Cache lifetime for relationship maps?
   Default: 6 hours
   Options: 1h / 6h / 12h / 24h
```

#### 5. Automation & Maintenance
```
Q: Auto-refresh frequency?
   Options: 
   - Hourly (high-activity projects)
   - Every 4 hours (moderate)
   - Daily (low-activity)
   - Weekly (stable production)
   Default: Every 4 hours
   
Q: Run full analysis frequency?
   Default: Daily at 2:00 AM
   
Q: Cleanup old KB snapshots?
   Default: Yes - Keep last 30 days
   
Q: Git integration for KB changes?
   Default: Yes - Auto-commit KB updates
```

#### 6. Alerts & Monitoring
```
Q: Enable performance degradation alerts?
   Default: Yes
   
Q: Enable breaking change detection?
   Default: Yes
   
Q: Notification method?
   Options: Email / Slack / Log file / None
   Default: Log file
   
Q: Email/Slack webhook (if applicable):
   [Enter webhook URL or email]
```

#### 7. Storage & Retention
```
Q: KB snapshot retention period?
   Default: 30 days
   
Q: Archive old snapshots?
   Default: Yes - Compress and move to archive/
   
Q: Maximum total KB storage?
   Default: 1GB (auto-cleanup oldest when exceeded)
```

---

## 📁 Directory Structure

### Global Knowledge Base Layout
```
{project_root}/
├── _kb/                              # Global Knowledge Base (MAIN)
│   ├── index.md                      # Master index with quick links
│   ├── PROJECT_OVERVIEW.md           # High-level project summary
│   ├── ARCHITECTURE.md               # System architecture docs
│   ├── DEPLOYMENT.md                 # Deployment procedures
│   ├── CHANGELOG.md                  # Global change log
│   │
│   ├── modules/                      # Module-specific docs
│   │   ├── _index.md                 # Module index
│   │   ├── base.md                   # Base module docs
│   │   ├── consignments.md           # Consignments module docs
│   │   └── [module-name].md          # Per-module documentation
│   │
│   ├── relationships/                # File relationship maps
│   │   ├── dependency-graph.json     # Full dependency graph
│   │   ├── include-map.json          # Include/require chains
│   │   ├── class-hierarchy.json      # Class inheritance tree
│   │   ├── function-calls.json       # Function call graph
│   │   └── cross-module.json         # Inter-module dependencies
│   │
│   ├── performance/                  # Performance tracking
│   │   ├── benchmarks.json           # Performance benchmarks
│   │   ├── slow-queries.log          # Slow query log analysis
│   │   ├── bottlenecks.json          # Identified bottlenecks
│   │   └── optimization-history.json # Optimization timeline
│   │
│   ├── api/                          # API documentation
│   │   ├── endpoints.md              # All API endpoints
│   │   ├── schemas.md                # Data schemas
│   │   ├── examples/                 # Request/response examples
│   │   └── postman/                  # Postman collections
│   │
│   ├── database/                     # Database documentation
│   │   ├── schema.sql                # Current schema
│   │   ├── erd.mmd                   # Entity relationship diagram (Mermaid)
│   │   ├── migrations/               # Migration history
│   │   ├── indexes.md                # Index documentation
│   │   └── queries.md                # Common query patterns
│   │
│   ├── security/                     # Security documentation
│   │   ├── auth-flow.md              # Authentication flow
│   │   ├── permissions.md            # Permission system
│   │   ├── vulnerabilities.md        # Known issues & fixes
│   │   └── audit-log.md              # Security audit trail
│   │
│   ├── decisions/                    # Architecture Decision Records (ADR)
│   │   ├── 001-template-system.md    # Why we chose this template approach
│   │   ├── 002-modular-architecture.md
│   │   └── [NNN-decision-title].md   # Numbered decision logs
│   │
│   ├── lessons/                      # Lessons learned
│   │   ├── mistakes.md               # What went wrong & fixes
│   │   ├── best-practices.md         # What worked well
│   │   └── gotchas.md                # Common pitfalls
│   │
│   ├── snapshots/                    # Historical KB snapshots
│   │   ├── 2025-10-12_02-00.tar.gz   # Daily snapshot
│   │   └── [YYYY-MM-DD_HH-MM].tar.gz # Timestamped backups
│   │
│   ├── cache/                        # Cached analysis results
│   │   ├── file-hashes.json          # File checksums for change detection
│   │   ├── parsed-structure.json     # Pre-parsed code structure
│   │   └── [analysis-type].cache     # Cached data with timestamps
│   │
│   └── tools/                        # KB maintenance scripts
│       ├── setup-kb.php              # Initial setup wizard
│       ├── refresh-kb.php            # Main refresh script
│       ├── verify-kb.php             # Verification tool
│       ├── cleanup-kb.php            # Cleanup & optimization
│       ├── analyze-performance.php   # Performance analyzer
│       ├── map-relationships.php     # Relationship mapper
│       ├── generate-diagrams.php     # Diagram generator
│       └── detect-dead-code.php      # Dead code detector
│
├── modules/                          # Modules directory
│   ├── base/
│   │   ├── _kb/                      # Module-specific KB
│   │   │   ├── README.md             # Module overview
│   │   │   ├── API.md                # Module API docs
│   │   │   ├── COMPONENTS.md         # Component inventory
│   │   │   ├── DEPENDENCIES.md       # What this module needs
│   │   │   ├── DEPENDENTS.md         # What depends on this module
│   │   │   ├── CHANGELOG.md          # Module change history
│   │   │   ├── relationships/        # Module-specific relationships
│   │   │   └── cache/                # Module cache
│   │   └── [module files...]
│   │
│   ├── consignments/
│   │   ├── _kb/                      # Same structure per module
│   │   └── [module files...]
│   │
│   └── [other-modules]/
│
└── _copilot/                         # AI assistant context (optional)
    ├── MODULES/                      # Module summaries for AI
    ├── SEARCH/                       # Search indexes
    └── logs/                         # AI interaction logs
```

---

## 🔗 File Relationships System

### What Gets Tracked

#### 1. Include/Require Chains
```json
{
  "file": "modules/consignments/pack.php",
  "includes": [
    "module_bootstrap.php",
    "_shared/lib/Kernel.php",
    "_shared/lib/Router.php"
  ],
  "included_by": [
    "index.php"
  ],
  "depth": 2,
  "circular": false
}
```

#### 2. Class Dependencies
```json
{
  "class": "Consignments\\PackController",
  "extends": "BaseController",
  "implements": ["ValidationInterface"],
  "uses": [
    "Consignments\\Lib\\Db",
    "Consignments\\Lib\\Validation",
    "Consignments\\Lib\\Log"
  ],
  "used_by": [
    "Consignments\\Lib\\Router"
  ],
  "namespace": "Consignments"
}
```

#### 3. Function Call Graph
```json
{
  "function": "processPackSubmission",
  "file": "modules/consignments/api/pack_submit.php",
  "calls": [
    "validatePackData",
    "lockTransfer",
    "updateInventory",
    "logAction"
  ],
  "called_by": [
    "pack.php::handleSubmit"
  ],
  "complexity": "medium",
  "lines": 156
}
```

#### 4. Database Table Usage
```json
{
  "file": "modules/consignments/api/pack_submit.php",
  "tables": {
    "read": ["vend_products", "stock_transfers", "vend_outlets"],
    "write": ["stock_transfers", "stock_transfer_items", "logs"],
    "delete": []
  },
  "queries": 12,
  "slow_queries": 0
}
```

### Relationship Mapping Script
Location: `_kb/tools/map-relationships.php`

```php
<?php
/**
 * File Relationship Mapper
 * 
 * Analyzes codebase and builds comprehensive relationship maps
 * Run via cron: php map-relationships.php --output=json
 */

// [Full script provided in Example Code section below]
```

---

## ⏰ Cron Jobs & Automation

### Recommended Cron Schedule

```cron
# CIS Knowledge Base Maintenance
# Add to crontab: crontab -e

# 1. Quick Refresh - Every 4 hours (detects new files, updates indexes)
0 */4 * * * cd /home/master/applications/jcepnzzkmj/public_html/_kb/tools && php refresh-kb.php --quick >> /home/master/applications/jcepnzzkmj/public_html/logs/kb-refresh.log 2>&1

# 2. Full Analysis - Daily at 2 AM (deep scan, relationships, performance)
0 2 * * * cd /home/master/applications/jcepnzzkmj/public_html/_kb/tools && php refresh-kb.php --full >> /home/master/applications/jcepnzzkmj/public_html/logs/kb-full.log 2>&1

# 3. Relationship Mapping - Every 6 hours
0 */6 * * * cd /home/master/applications/jcepnzzkmj/public_html/_kb/tools && php map-relationships.php >> /home/master/applications/jcepnzzkmj/public_html/logs/kb-relationships.log 2>&1

# 4. Performance Analysis - Daily at 3 AM
0 3 * * * cd /home/master/applications/jcepnzzkmj/public_html/_kb/tools && php analyze-performance.php >> /home/master/applications/jcepnzzkmj/public_html/logs/kb-performance.log 2>&1

# 5. Dead Code Detection - Weekly on Sunday at 4 AM
0 4 * * 0 cd /home/master/applications/jcepnzzkmj/public_html/_kb/tools && php detect-dead-code.php >> /home/master/applications/jcepnzzkmj/public_html/logs/kb-deadcode.log 2>&1

# 6. Cleanup Old Snapshots - Weekly on Monday at 1 AM
0 1 * * 1 cd /home/master/applications/jcepnzzkmj/public_html/_kb/tools && php cleanup-kb.php --older-than=30 >> /home/master/applications/jcepnzzkmj/public_html/logs/kb-cleanup.log 2>&1

# 7. Generate Diagrams - Daily at 5 AM
0 5 * * * cd /home/master/applications/jcepnzzkmj/public_html/_kb/tools && php generate-diagrams.php >> /home/master/applications/jcepnzzkmj/public_html/logs/kb-diagrams.log 2>&1

# 8. Verification Check - Every 12 hours
0 */12 * * * cd /home/master/applications/jcepnzzkmj/public_html/_kb/tools && php verify-kb.php >> /home/master/applications/jcepnzzkmj/public_html/logs/kb-verify.log 2>&1
```

### Cron Job Details

#### 1. Quick Refresh (`refresh-kb.php --quick`)
**Frequency:** Every 4 hours  
**Duration:** ~30-60 seconds  
**What it does:**
- Scans for new/modified files (using file hashes)
- Updates file indexes
- Regenerates module lists
- Updates cross-references
- Skips heavy analysis (relationships, performance)

**When to use:**
- Active development environments
- Frequent code changes
- Need up-to-date file lists

#### 2. Full Analysis (`refresh-kb.php --full`)
**Frequency:** Daily at 2 AM  
**Duration:** ~5-15 minutes  
**What it does:**
- Everything in Quick Refresh +
- Deep code analysis (AST parsing)
- Relationship mapping
- Performance profiling
- Documentation generation
- Diagram updates
- Snapshot creation

**When to use:**
- Production environments
- Weekly in staging
- After major releases

#### 3. Relationship Mapping (`map-relationships.php`)
**Frequency:** Every 6 hours  
**Duration:** ~2-5 minutes  
**What it does:**
- Traces include/require chains
- Maps class dependencies
- Builds function call graphs
- Identifies circular dependencies
- Detects cross-module coupling

**Output:** `_kb/relationships/*.json`

#### 4. Performance Analysis (`analyze-performance.php`)
**Frequency:** Daily at 3 AM  
**Duration:** ~3-8 minutes  
**What it does:**
- Parses slow query logs
- Identifies bottlenecks (cyclomatic complexity > 15)
- Tracks file size growth
- Monitors query count trends
- Generates optimization suggestions

**Output:** `_kb/performance/*.json`

#### 5. Dead Code Detection (`detect-dead-code.php`)
**Frequency:** Weekly (Sunday 4 AM)  
**Duration:** ~10-20 minutes  
**What it does:**
- Finds unused functions
- Identifies orphaned files
- Detects unreachable code
- Lists commented-out blocks
- Reports unused classes/methods

**Output:** `_kb/dead-code-report.md`

#### 6. Cleanup (`cleanup-kb.php --older-than=30`)
**Frequency:** Weekly (Monday 1 AM)  
**Duration:** ~1-2 minutes  
**What it does:**
- Removes snapshots older than 30 days
- Compresses old logs
- Purges stale cache entries
- Archives old relationship maps
- Enforces storage limits (1GB default)

#### 7. Diagram Generation (`generate-diagrams.php`)
**Frequency:** Daily at 5 AM  
**Duration:** ~2-4 minutes  
**What it does:**
- Generates Mermaid ERD diagrams
- Creates module dependency graphs
- Builds class hierarchy diagrams
- Renders flow charts for complex functions

**Output:** `_kb/database/erd.mmd`, `_kb/diagrams/*.mmd`

#### 8. Verification (`verify-kb.php`)
**Frequency:** Every 12 hours  
**Duration:** ~30 seconds  
**What it does:**
- Checks KB file integrity
- Validates JSON relationship maps
- Ensures all required files exist
- Tests script executability
- Reports missing documentation

### Alternative: Systemd Timers (Linux)

If cron isn't available or you prefer systemd:

```ini
# /etc/systemd/system/cis-kb-refresh.timer
[Unit]
Description=CIS Knowledge Base Quick Refresh Timer
Requires=cis-kb-refresh.service

[Timer]
OnCalendar=*-*-* 00/4:00:00
Persistent=true

[Install]
WantedBy=timers.target
```

```ini
# /etc/systemd/system/cis-kb-refresh.service
[Unit]
Description=CIS Knowledge Base Quick Refresh
After=network.target

[Service]
Type=oneshot
User=www-data
WorkingDirectory=/home/master/applications/jcepnzzkmj/public_html/_kb/tools
ExecStart=/usr/bin/php refresh-kb.php --quick
StandardOutput=append:/home/master/applications/jcepnzzkmj/public_html/logs/kb-refresh.log
StandardError=append:/home/master/applications/jcepnzzkmj/public_html/logs/kb-refresh.log
```

Enable timers:
```bash
sudo systemctl daemon-reload
sudo systemctl enable --now cis-kb-refresh.timer
sudo systemctl list-timers  # Verify
```

---

## 📚 Knowledge Base Organization

### Document Types & Purposes

#### 1. **README.md** (Per Module)
**Purpose:** Quick orientation for new developers  
**Contents:**
- Module overview (1-2 paragraphs)
- Key features list
- Quick start guide
- File structure tree
- Main entry points
- Common tasks & examples
- Related modules

**Update Frequency:** On major changes  
**Auto-generated:** Partially (file tree, entry points)

#### 2. **API.md** (Per Module)
**Purpose:** API documentation for module  
**Contents:**
- Endpoint list with methods
- Request/response examples
- Authentication requirements
- Error codes & handling
- Rate limits
- Versioning info

**Update Frequency:** On API changes  
**Auto-generated:** Yes (from PHPDoc + route definitions)

#### 3. **COMPONENTS.md** (Per Module)
**Purpose:** Inventory of all components  
**Contents:**
- Controllers list
- Models/entities
- Views/templates
- Libraries/utilities
- Middleware
- Helpers

**Update Frequency:** On file additions/deletions  
**Auto-generated:** Yes

#### 4. **DEPENDENCIES.md**
**Purpose:** What this module requires  
**Contents:**
- PHP extensions
- Composer packages
- Other modules
- Database tables
- External APIs
- Environment variables

**Update Frequency:** On dependency changes  
**Auto-generated:** Partially (from composer.json, code analysis)

#### 5. **DEPENDENTS.md**
**Purpose:** What depends on this module  
**Contents:**
- Modules that import this one
- Files that include this module's files
- APIs that call this module's endpoints
- Impact analysis for changes

**Update Frequency:** Every full refresh  
**Auto-generated:** Yes (from relationship maps)

#### 6. **CHANGELOG.md**
**Purpose:** Historical record of changes  
**Contents:**
- Version history
- Feature additions
- Bug fixes
- Breaking changes
- Migration notes

**Update Frequency:** On releases  
**Auto-generated:** No (manual, but template provided)

---

## 🛠 Maintenance Scripts

### Core Scripts Overview

#### 1. `setup-kb.php` - Initial Setup Wizard
**Purpose:** One-time setup, creates structure, configures  
**Usage:**
```bash
php setup-kb.php --init              # Create directory structure
php setup-kb.php --configure         # Interactive configuration
php setup-kb.php --install-cron      # Add cron jobs
php setup-kb.php --reset             # Reset KB (DANGER: deletes all)
```

**What it creates:**
- All `_kb/` directories
- Module-specific `_kb/` directories
- Configuration file (`_kb/config.json`)
- Initial documentation templates
- Cron job entries (with confirmation)

**Run time:** 2-3 minutes

---

#### 2. `refresh-kb.php` - Main Refresh Script
**Purpose:** Update KB with latest code changes  
**Usage:**
```bash
php refresh-kb.php --quick           # Fast update (files only)
php refresh-kb.php --full            # Deep analysis (slow)
php refresh-kb.php --module=base     # Single module only
php refresh-kb.php --snapshot        # Create snapshot before refresh
php refresh-kb.php --force           # Ignore cache, regenerate all
```

**Modes explained:**

**--quick mode** (30-60 sec):
- Scans file system for changes (using MD5 hashes)
- Updates file indexes
- Regenerates module lists
- Updates cross-reference links
- Uses cached relationship data

**--full mode** (5-15 min):
- All --quick tasks +
- AST parsing for code structure
- Relationship mapping (includes, classes, functions)
- Performance analysis
- Dead code detection
- Documentation generation
- Diagram rendering
- Snapshot creation

**--module=X mode:**
- Limits scope to specific module
- Updates that module's `_kb/` only
- Updates global cross-references for that module
- Faster than full, more thorough than quick for one module

**Run time:**  
- Quick: 30-60 seconds  
- Full: 5-15 minutes  
- Single module: 1-3 minutes

---

Continuing with **Part 2** of the Master Setup Guide...

