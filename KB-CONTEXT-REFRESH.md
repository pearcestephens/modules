# 🔄 CIS Knowledge Base Context Refresh Guide
## Portable Instructions for Existing Projects

**Purpose:** Drop this guide into ANY existing project to add KB refresh capabilities  
**Use Case:** You already have some documentation/tooling, now you want to upgrade it  
**Time to Setup:** 10 minutes  
**Maintenance:** Autonomous after setup  

---

## 🎯 What This Guide Does

This is a **lightweight, portable KB refresh system** you can add to projects that already have:
- ✅ Some documentation (even if incomplete)
- ✅ Existing directory structure
- ✅ Running code you don't want to disturb

**It will NOT:**
- ❌ Restructure your project
- ❌ Move existing files
- ❌ Break current workflows

**It WILL:**
- ✅ Scan your codebase and refresh docs
- ✅ Detect stale/missing documentation
- ✅ Update relationship maps
- ✅ Clean up old artifacts
- ✅ Run autonomously on schedule

---

## 🚀 Quick Start (3 Commands)

```bash
# 1. Download this file to your project root
curl -o kb-refresh.php https://your-cdn.com/kb-refresh.php

# 2. Make it executable
chmod +x kb-refresh.php

# 3. Run initial scan
php kb-refresh.php --init
```

✅ Done! Now you have an auto-refreshing KB.

---

## 📦 What Gets Installed

When you run `php kb-refresh.php --init`, it creates:

```
your-project/
├── _kb/                          # KB directory (if doesn't exist)
│   ├── README.md                 # Project overview
│   ├── FILE_INDEX.md             # Auto-generated file list
│   ├── RELATIONSHIPS.md          # Dependency map
│   ├── STALE_DOCS.md             # Docs that need updating
│   ├── CLEANUP_REPORT.md         # What can be cleaned up
│   └── tools/
│       ├── refresh.php           # Main refresh script
│       ├── cleanup.php           # Cleanup utility
│       └── verify.php            # Verification tool
├── .kb-config.json               # Configuration (gitignored)
└── your existing files...        # UNCHANGED
```

**Total size:** ~50KB  
**Runtime overhead:** None (runs via cron, not on requests)  

---

## ⚙️ Configuration

Edit `.kb-config.json` (auto-created):

```json
{
  "project_name": "My Project",
  "kb_location": "_kb",
  "scan_paths": [
    "src",
    "modules",
    "app"
  ],
  "ignore_paths": [
    "vendor",
    "node_modules",
    ".git",
    "cache",
    "logs"
  ],
  "refresh_frequency": "4h",
  "cleanup_enabled": true,
  "cleanup_older_than": "30d",
  "relationship_tracking": true,
  "stale_doc_threshold": "7d",
  "auto_commit": false
}
```

### Configuration Options Explained

| Setting | Default | Description |
|---------|---------|-------------|
| `project_name` | Auto-detected | Project name for docs |
| `kb_location` | `_kb` | Where to store KB files |
| `scan_paths` | `["."]` | Directories to scan |
| `ignore_paths` | `["vendor",...]` | Directories to skip |
| `refresh_frequency` | `4h` | How often to refresh |
| `cleanup_enabled` | `true` | Auto-cleanup old files |
| `cleanup_older_than` | `30d` | Retention period |
| `relationship_tracking` | `true` | Track file dependencies |
| `stale_doc_threshold` | `7d` | Mark docs stale after X days |
| `auto_commit` | `false` | Git commit KB changes |

---

## 🔄 Refresh Modes

### 1. Quick Refresh (Default)
```bash
php kb-refresh.php
```
**Duration:** 10-30 seconds  
**What it does:**
- Scans for new/modified/deleted files
- Updates FILE_INDEX.md
- Checks for stale docs
- Updates timestamps

**When to use:** Daily automated runs

### 2. Full Refresh
```bash
php kb-refresh.php --full
```
**Duration:** 2-5 minutes  
**What it does:**
- Everything in Quick Refresh +
- Regenerates relationship maps
- Deep scans for broken links
- Performance analysis
- Cleanup suggestions

**When to use:** Weekly automated runs, or after major changes

### 3. Verify Only
```bash
php kb-refresh.php --verify
```
**Duration:** 5-10 seconds  
**What it does:**
- Checks KB integrity
- Validates JSON files
- Reports missing docs
- No modifications

**When to use:** Pre-deployment checks

### 4. Cleanup
```bash
php kb-refresh.php --cleanup
```
**Duration:** 10-20 seconds  
**What it does:**
- Removes old snapshots
- Purges stale cache
- Archives old versions
- Frees disk space

**When to use:** Monthly automated runs

---

## 📅 Recommended Cron Schedule

Add to your crontab (`crontab -e`):

```cron
# Quick refresh every 4 hours
0 */4 * * * cd /path/to/project && php kb-refresh.php >> logs/kb.log 2>&1

# Full refresh daily at 2 AM
0 2 * * * cd /path/to/project && php kb-refresh.php --full >> logs/kb-full.log 2>&1

# Cleanup monthly (1st of month, 3 AM)
0 3 1 * * cd /path/to/project && php kb-refresh.php --cleanup >> logs/kb-cleanup.log 2>&1

# Verify before deployments (optional, manual trigger)
# Run manually: php kb-refresh.php --verify
```

**Don't have cron?** Use systemd timers (Linux) or Task Scheduler (Windows).

---

## 🧪 Testing Before Deployment

Before adding to cron, test manually:

```bash
# 1. Dry run (no changes, just show what would happen)
php kb-refresh.php --dry-run

# 2. Quick refresh (safe, fast)
php kb-refresh.php

# 3. Check logs
tail -f logs/kb.log

# 4. Verify output
ls -la _kb/
cat _kb/FILE_INDEX.md

# 5. If all good, add to cron
crontab -e
```

---

## 📊 What Gets Generated

### 1. FILE_INDEX.md
**Auto-generated file inventory**

```markdown
# File Index
Last Updated: 2025-10-12 14:30:00

## Summary
- Total Files: 342
- PHP: 156
- JavaScript: 48
- CSS: 23
- Markdown: 12
- Other: 103

## By Module
### base (45 files)
- controllers/ (8 files)
- models/ (6 files)
- views/ (15 files)
- lib/ (12 files)
- api/ (4 files)

### consignments (67 files)
...

## Recent Changes (Last 7 Days)
- 2025-10-11: Added `pack_submit.php` (api)
- 2025-10-10: Modified `master.php` (views/layouts)
- 2025-10-09: Deleted `old_template.php` (deprecated)
```

### 2. RELATIONSHIPS.md
**Dependency map**

```markdown
# Code Relationships
Last Updated: 2025-10-12 14:30:00

## Include Chains
### modules/consignments/pack.php
Includes:
→ module_bootstrap.php
  → _shared/lib/Kernel.php
  → _shared/lib/Router.php
→ pages/pack.php

Included by:
← index.php

## Cross-Module Dependencies
### base → consignments
- base/views/layouts/master.php includes consignments/module_bootstrap.php

## Circular Dependencies
⚠️ FOUND 2 CIRCULAR DEPENDENCIES:

1. module_a.php → module_b.php → module_a.php
   - Fix: Extract shared code to separate file

2. class_x.php → class_y.php → class_x.php
   - Fix: Use dependency injection
```

### 3. STALE_DOCS.md
**Documentation that needs updating**

```markdown
# Stale Documentation Report
Last Updated: 2025-10-12 14:30:00

## Files Modified Without Doc Updates

### High Priority (code changed > 7 days ago)
- `modules/consignments/pack.php`
  - Last modified: 2025-10-05
  - Doc last updated: 2025-09-28
  - **Action:** Update `_kb/API.md` section on pack endpoint

### Medium Priority (code changed > 3 days ago)
- `modules/base/views/layouts/master.php`
  - Last modified: 2025-10-09
  - Doc last updated: 2025-10-09
  - **Action:** Update template documentation

## Missing Documentation
- `modules/new-feature/handler.php` (created 2025-10-11)
  - **Action:** Create README.md in new-feature module
```

### 4. CLEANUP_REPORT.md
**Cleanup opportunities**

```markdown
# Cleanup Report
Last Updated: 2025-10-12 14:30:00

## Old Snapshots
- `_kb/snapshots/2025-09-01_02-00.tar.gz` (41 days old, 12 MB)
- `_kb/snapshots/2025-09-08_02-00.tar.gz` (34 days old, 11 MB)

**Action:** Run `php kb-refresh.php --cleanup` to remove

## Commented Out Code
- `modules/old/legacy.php` lines 45-78 (34 lines commented)
- `modules/base/deprecated.php` lines 123-189 (67 lines commented)

**Action:** Consider removing or extracting to archive

## Unused Files (Possibly Safe to Delete)
- `modules/test/demo.php` (never included, 6 months old)
- `assets/old-style.css` (not referenced anywhere)

**Action:** Review and archive or delete

## Large Files
- `modules/reports/generator.php` (1,234 lines)
  - **Action:** Consider splitting into multiple files

## Total Cleanup Potential
- Disk space: ~150 MB
- Files: 23
```

---

## 🎛️ Advanced Usage

### Custom Scan Path
```bash
php kb-refresh.php --path=/custom/location
```

### Exclude Specific Directories
```bash
php kb-refresh.php --exclude=tests,docs
```

### Output to Specific Location
```bash
php kb-refresh.php --output=/different/kb/location
```

### Force Regenerate (Ignore Cache)
```bash
php kb-refresh.php --force
```

### Generate HTML Report
```bash
php kb-refresh.php --full --format=html
# Creates: _kb/report.html
```

### Integration with Git
```bash
# Auto-commit KB changes
php kb-refresh.php --auto-commit

# Or manual:
php kb-refresh.php
git add _kb/
git commit -m "chore: refresh knowledge base"
```

---

## 🔧 Integration with CI/CD

### GitHub Actions Example
```yaml
name: Refresh Knowledge Base

on:
  schedule:
    - cron: '0 2 * * *'  # Daily at 2 AM
  workflow_dispatch:      # Manual trigger

jobs:
  refresh-kb:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
      
      - name: Run KB Refresh
        run: php kb-refresh.php --full
      
      - name: Commit Changes
        run: |
          git config user.name "KB Bot"
          git config user.email "bot@example.com"
          git add _kb/
          git diff-index --quiet HEAD || git commit -m "chore: refresh knowledge base"
          git push
```

### GitLab CI Example
```yaml
refresh-kb:
  stage: maintain
  script:
    - php kb-refresh.php --full
    - git add _kb/
    - git commit -m "chore: refresh knowledge base" || true
    - git push || true
  only:
    - schedules
```

---

## 🛡️ Safety Features

### Automatic Backups
Before any destructive operation:
```bash
php kb-refresh.php --cleanup
# Automatically creates: _kb/snapshots/pre-cleanup-TIMESTAMP.tar.gz
```

### Dry Run Mode
Preview changes without applying:
```bash
php kb-refresh.php --dry-run
# Shows: What would be created, updated, deleted
# Does NOT: Actually modify anything
```

### Rollback Support
If something goes wrong:
```bash
php kb-refresh.php --rollback
# Restores from latest snapshot
```

### Integrity Checks
Validates KB health:
```bash
php kb-refresh.php --verify
# Checks:
# ✓ All required files exist
# ✓ JSON files are valid
# ✓ No broken links
# ✓ Timestamps are recent
# ✓ Disk space is adequate
```

---

## 🚨 Troubleshooting

### Problem: "Permission denied"
**Solution:**
```bash
chmod +x kb-refresh.php
chmod 755 _kb/tools/
```

### Problem: "Out of memory"
**Solution:** Increase PHP memory limit
```bash
php -d memory_limit=512M kb-refresh.php
```

### Problem: "Cron job not running"
**Solution:** Check cron logs
```bash
# View cron logs
tail -f /var/log/cron

# Test cron job manually
cd /path/to/project && php kb-refresh.php
```

### Problem: "Stale docs not detected"
**Solution:** Adjust threshold
```json
{
  "stale_doc_threshold": "3d"  // More aggressive
}
```

### Problem: "Too many files to scan"
**Solution:** Optimize scan paths
```json
{
  "scan_paths": ["modules"],  // Don't scan everything
  "ignore_paths": ["vendor", "node_modules", "cache", "logs", "tmp"]
}
```

---

## 📝 Minimal Implementation (Copy-Paste Ready)

If you just want a **super simple** version, here's a 100-line script:

```php
<?php
/**
 * Minimal KB Refresh Script
 * 
 * Usage: php mini-kb-refresh.php
 */

$kbDir = __DIR__ . '/_kb';
$scanDir = __DIR__;

// Create KB directory
if (!is_dir($kbDir)) {
    mkdir($kbDir, 0755, true);
}

// Scan files
$files = [];
$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($scanDir, RecursiveDirectoryIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    if ($file->isFile()) {
        $ext = $file->getExtension();
        if (in_array($ext, ['php', 'js', 'css', 'md'])) {
            $relativePath = str_replace($scanDir . '/', '', $file->getPathname());
            
            // Skip vendor, node_modules, _kb itself
            if (preg_match('#(vendor|node_modules|_kb|\.git)/#', $relativePath)) {
                continue;
            }
            
            $files[] = [
                'path' => $relativePath,
                'type' => $ext,
                'size' => $file->getSize(),
                'modified' => date('Y-m-d H:i:s', $file->getMTime()),
            ];
        }
    }
}

// Sort by path
usort($files, function($a, $b) {
    return strcmp($a['path'], $b['path']);
});

// Generate FILE_INDEX.md
$indexContent = "# File Index\n\n";
$indexContent .= "**Last Updated:** " . date('Y-m-d H:i:s') . "\n";
$indexContent .= "**Total Files:** " . count($files) . "\n\n";

// Group by extension
$byType = [];
foreach ($files as $file) {
    $byType[$file['type']][] = $file;
}

$indexContent .= "## By Type\n\n";
foreach ($byType as $type => $typeFiles) {
    $indexContent .= "### " . strtoupper($type) . " (" . count($typeFiles) . " files)\n\n";
    foreach ($typeFiles as $file) {
        $indexContent .= "- `{$file['path']}` (" . round($file['size'] / 1024, 1) . " KB)\n";
    }
    $indexContent .= "\n";
}

file_put_contents($kbDir . '/FILE_INDEX.md', $indexContent);

// Generate README.md (if doesn't exist)
$readmeFile = $kbDir . '/README.md';
if (!file_exists($readmeFile)) {
    $readmeContent = "# Knowledge Base\n\n";
    $readmeContent .= "Auto-generated documentation for this project.\n\n";
    $readmeContent .= "## Contents\n\n";
    $readmeContent .= "- [FILE_INDEX.md](FILE_INDEX.md) - Complete file inventory\n";
    file_put_contents($readmeFile, $readmeContent);
}

echo "✓ KB refreshed successfully\n";
echo "  Total files indexed: " . count($files) . "\n";
echo "  Output: {$kbDir}/FILE_INDEX.md\n";
```

Save as `mini-kb-refresh.php` and run:
```bash
php mini-kb-refresh.php
```

---

## 🎁 Bonus: Quick Questions to Ask

When you paste this guide into a new project, ask these questions first:

### 1. Discovery Questions
```
Q: Where is your main code located?
   → Adjust scan_paths in .kb-config.json

Q: What directories should be ignored?
   → Add to ignore_paths

Q: Do you use Git?
   → Set auto_commit: true to version KB changes

Q: What's your deployment frequency?
   → Adjust refresh_frequency (frequent deploys = more frequent refreshes)
```

### 2. Integration Questions
```
Q: Do you have existing documentation?
   → KB will index and link to it, not replace it

Q: Do you use CI/CD?
   → Add KB refresh to pipeline (examples above)

Q: Do you have a cron system?
   → Set up scheduled refreshes

Q: What's your team size?
   → Larger teams = more frequent refreshes
```

### 3. Performance Questions
```
Q: How many files in your project?
   → <1000: Quick refresh every 2h, Full refresh daily
   → 1000-5000: Quick refresh every 4h, Full refresh weekly
   → 5000+: Quick refresh every 6h, Full refresh monthly

Q: How fast is your disk?
   → SSD: All modes work great
   → HDD: Use longer intervals, enable caching
```

---

## ✅ Success Checklist

After setup, verify these:

- [ ] `_kb/` directory exists
- [ ] `_kb/FILE_INDEX.md` is generated and up-to-date
- [ ] `_kb/README.md` exists (even if minimal)
- [ ] `.kb-config.json` is configured for your project
- [ ] Cron job is scheduled (or CI/CD pipeline added)
- [ ] Logs are being written (`logs/kb.log`)
- [ ] First refresh completed without errors
- [ ] Verification passes: `php kb-refresh.php --verify`

---

## 🚀 Next Steps

Once your KB is set up and running:

1. **Review the generated docs** in `_kb/`
2. **Fix any stale docs** listed in `STALE_DOCS.md`
3. **Clean up** old files from `CLEANUP_REPORT.md`
4. **Add custom docs** to `_kb/` (they won't be overwritten)
5. **Monitor the logs** for any issues

---

## 📞 Quick Reference Card

```
┌─────────────────────────────────────────────────────────┐
│  CIS KB Context Refresh - Quick Reference               │
├─────────────────────────────────────────────────────────┤
│  SETUP                                                   │
│  php kb-refresh.php --init                               │
│                                                          │
│  DAILY USE                                               │
│  php kb-refresh.php           # Quick refresh            │
│  php kb-refresh.php --full    # Full refresh             │
│  php kb-refresh.php --verify  # Check integrity          │
│  php kb-refresh.php --cleanup # Remove old files         │
│                                                          │
│  CRON (add to crontab -e)                                │
│  0 */4 * * * php kb-refresh.php                          │
│  0 2 * * * php kb-refresh.php --full                     │
│                                                          │
│  TROUBLESHOOTING                                         │
│  tail -f logs/kb.log          # View logs                │
│  php kb-refresh.php --dry-run # Preview changes          │
│  php kb-refresh.php --rollback # Undo last refresh       │
│                                                          │
│  OUTPUT FILES                                            │
│  _kb/FILE_INDEX.md            # File inventory           │
│  _kb/RELATIONSHIPS.md         # Dependency map           │
│  _kb/STALE_DOCS.md            # Docs needing updates     │
│  _kb/CLEANUP_REPORT.md        # Cleanup suggestions      │
└─────────────────────────────────────────────────────────┘
```

---

**That's it!** This guide is completely self-contained and portable. 

Copy it to any project, run the setup, and you're good to go. The KB will maintain itself from then on.
