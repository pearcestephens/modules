# OBJECTIVE 9: Retire Legacy Files & Technical Debt Cleanup

**Status:** 🔄 IN PROGRESS
**Estimated Time:** 30 minutes
**Priority:** HIGH (Security - Minimize Attack Surface)

---

## Objective

Identify and safely remove or archive legacy files with:
- Hard-coded secrets or credentials
- Obsolete implementations replaced by current code
- Unused utilities or dead code
- Old backup files
- Commented-out functionality that's been moved elsewhere

**Goal:** Reduce codebase footprint, eliminate technical debt, minimize attack surface

---

## Search Strategy

### 1. Find Files with Hard-Coded Secrets
**Pattern:** Passwords, tokens, API keys in source code

```bash
# Search for common secret patterns
grep -r "password\s*=\s*['\"]" ./ --include="*.php" | grep -v ".env"
grep -r "api_key\s*=\s*['\"]" ./ --include="*.php"
grep -r "token\s*=\s*['\"]" ./ --include="*.php" | grep -v "csrf"
grep -r "secret\s*=\s*['\"]" ./ --include="*.php"
```

### 2. Find Backup/Old Files
**Pattern:** .bak, .old, .backup, _old, _backup, .disabled

```bash
find . -name "*.php.bak"
find . -name "*.old"
find . -name "*.backup"
find . -name "*_old.php"
find . -name "*_backup.php"
find . -name "*.disabled"
```

### 3. Find Commented-Out Code Blocks
**Pattern:** Large commented sections (> 20 lines)

```bash
# Find files with excessive comments
awk '/^\/\*/{flag=1; count=0} flag{count++} /\*\//{if(count>20) print FILENAME":"NR; flag=0}' *.php
```

### 4. Find Unused Utility Files
**Pattern:** Files never imported/required

```bash
# List all PHP files
find . -name "*.php" -type f > all_files.txt

# Search for each file being included
for file in $(cat all_files.txt); do
    basename=$(basename $file)
    grep -r "require.*$basename" ./ --include="*.php" > /dev/null
    if [ $? -ne 0 ]; then
        grep -r "include.*$basename" ./ --include="*.php" > /dev/null
        if [ $? -ne 0 ]; then
            echo "Potentially unused: $file"
        fi
    fi
done
```

---

## Files to Audit

### High Priority (Security Risk)
- [ ] Files with hard-coded DB credentials
- [ ] Files with API keys/tokens
- [ ] Old OAuth implementations
- [ ] Legacy authentication code

### Medium Priority (Technical Debt)
- [ ] Backup files (*.bak, *.old)
- [ ] Disabled files (*.disabled)
- [ ] Commented-out code blocks (> 50 lines)
- [ ] Duplicate implementations

### Low Priority (Cleanup)
- [ ] Old test files
- [ ] Unused utility functions
- [ ] Obsolete documentation

---

## Decision Matrix

For each legacy file found:

### RETIRE (Delete)
**Criteria:**
- No active imports/requires
- Functionality replaced by current implementation
- No unique logic worth preserving
- Last modified > 6 months ago with no recent access

**Action:**
- Document purpose in LEGACY_FILES_RETIRED.md
- Delete from repository
- Git commit records original content if needed

### ARCHIVE (Move)
**Criteria:**
- Might be needed for reference
- Contains unique business logic
- Historical documentation value
- Uncertain if still in use

**Action:**
- Move to `_archive/` directory
- Document in ARCHIVED_FILES.md
- Keep in repository but not in active paths

### REFACTOR (Modernize)
**Criteria:**
- Still in use
- Contains hard-coded secrets → need to move to .env
- Deprecated patterns → need to update

**Action:**
- Extract secrets to .env
- Update deprecated code
- Add tests
- Keep in active directory

### KEEP (No Change)
**Criteria:**
- Actively used in production
- Well-written, no issues
- Part of current architecture

**Action:**
- Document in code audit
- No changes needed

---

## Safety Checklist

Before retiring any file:
- [ ] Grep entire codebase for imports of this file
- [ ] Check if file is referenced in routes.php
- [ ] Check if file is loaded by autoloader
- [ ] Check git log for recent activity (last 6 months)
- [ ] Verify tests don't depend on it
- [ ] Check if file is called by cron jobs
- [ ] Review for unique business logic worth preserving

---

## Acceptance Criteria

1. ✅ All files with hard-coded secrets identified
2. ✅ All backup/old files cataloged
3. ✅ Decision made for each legacy file (retire/archive/refactor/keep)
4. ✅ Safe retirement plan executed (no breaking changes)
5. ✅ LEGACY_FILES_RETIRED.md document created
6. ✅ Git commit with complete audit trail
7. ✅ Codebase reduced by at least 10% (measured in files)

---

## Execution Plan

**Step 1:** Discover (10 minutes)
- Run grep searches for secrets
- Find backup files
- List all PHP files and check usage

**Step 2:** Audit (10 minutes)
- Review each candidate file
- Apply decision matrix
- Document findings

**Step 3:** Execute (10 minutes)
- Safely delete retired files
- Move archived files to _archive/
- Create documentation
- Git commit

---

## Starting Scan...

Let me begin by searching for legacy files in the payroll module.
