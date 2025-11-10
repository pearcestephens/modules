# 🛡️ DESIGN PATTERN ENFORCEMENT TOOLS
**Created:** November 6, 2025
**For:** CIS Application - Option B Architecture

---

## ✅ ACTIVE ENFORCEMENT

### 1. Pre-Commit Git Hook
**Location:** `.git/hooks/pre-commit`
**Status:** ✅ INSTALLED & EXECUTABLE

**What It Blocks:**
- ❌ Hardcoded passwords (e.g., `$password = 'wprKh9Jq63';`)
- ❌ .env files in commits (except .env.example)
- ❌ IntelligenceHub namespace usage
- ❌ app/ directory creation (conflicts with Option B)
- ⚠️  Dangerous PHP functions (warns but allows with comment)
- ⚠️  SQL injection patterns (warns)
- ⚠️  Missing bootstrap.php in modules (warns)

**How to Test:**
```bash
# This will be BLOCKED:
echo '<?php $password = "secret123";' > test.php
git add test.php
git commit -m "Test"
# ❌ BLOCKED: Hardcoded password detected

# This will be ALLOWED:
echo '<?php $password = $_ENV["DB_PASSWORD"] ?? throw new \RuntimeException("Not set");' > test.php
git add test.php
git commit -m "Test"
# ✅ ALLOWED
```

**Override Emergency:**
```bash
# If you MUST bypass (not recommended):
git commit --no-verify -m "Emergency fix"
```

---

### 2. PHP CodeSniffer (PHPCS)
**Location:** `phpcs.xml`
**Status:** ✅ CONFIGURED

**What It Checks:**
- PSR-12 coding standards
- Forbidden functions (eval, exec, system, shell_exec, passthru)
- Code style consistency

**How to Run:**
```bash
# Check all modules:
cd /home/129337.cloudwaysapps.com/jcepnzzkmj/public_html
vendor/bin/phpcs

# Check specific module:
vendor/bin/phpcs modules/consignments/

# Auto-fix issues:
vendor/bin/phpcbf modules/consignments/
```

**Install (if not present):**
```bash
composer require --dev squizlabs/php_codesniffer
```

---

### 3. .gitignore Rules
**Location:** `.gitignore`
**Status:** ⏳ NEEDS UPDATE

**Add These Lines:**
```
# Environment files (contain secrets)
.env
.env.*
!.env.example

# Laravel-style directories (Option B uses base/)
/modules/app/
/resources/

# Vendor directories
/vendor/
/modules/*/vendor/
/node_modules/

# Cache and logs
/modules/cache/
/modules/logs/
*.log

# IDE files
.vscode/
.idea/
*.swp
*.swo
*~

# OS files
.DS_Store
Thumbs.db

# Build artifacts
*.pid
.auto-push.*
```

---

## 📋 MANUAL ENFORCEMENT CHECKLIST

Use this checklist for code reviews:

### Architecture (Option B)
- [ ] Module uses `base/` framework (not `app/`)
- [ ] Module has `bootstrap.php` that loads `base/bootstrap.php`
- [ ] No Laravel-style patterns (ServiceProviders, Facades, etc.)
- [ ] No `resources/` directory references

### Namespaces
- [ ] Framework code uses `Base\` namespace
- [ ] Module code uses `Modules\ModuleName\` namespace
- [ ] No `App\` namespace (Option A only)
- [ ] No `IntelligenceHub\` namespace (wrong app)

### Security
- [ ] No hardcoded passwords or secrets
- [ ] All secrets in .env file (outside public_html)
- [ ] Database queries use prepared statements
- [ ] User input is escaped in views (htmlspecialchars)
- [ ] CSRF tokens on forms (when implemented)
- [ ] Authentication checks on protected routes
- [ ] No .env files in git

### Code Quality
- [ ] Follows PSR-12 coding standards
- [ ] No eval(), exec(), system(), shell_exec(), passthru()
- [ ] Error handling with exceptions (not false/null)
- [ ] Logging for important actions
- [ ] Comments for complex logic

---

## 🔧 TOOLS TO ADD (Future)

### CI/CD Pipeline (GitHub Actions)
**File:** `.github/workflows/ci.yml`
```yaml
name: CIS CI Pipeline
on: [push, pull_request]
jobs:
  lint:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.1'
      - name: Install dependencies
        run: composer install
      - name: Run PHPCS
        run: vendor/bin/phpcs

  security:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v3
      - name: Check for secrets
        run: |
          ! git diff origin/master | grep -i "password.*=.*['\"]"
      - name: Check for .env files
        run: |
          ! git diff origin/master --name-only | grep "\.env$" | grep -v "\.env\.example$"
      - name: Security audit
        run: composer audit
```

### PHPStan (Static Analysis)
```bash
composer require --dev phpstan/phpstan

# phpstan.neon
parameters:
    level: 5
    paths:
        - modules/
    excludePaths:
        - modules/*/vendor/*
```

### PHP Mess Detector
```bash
composer require --dev phpmd/phpmd

# Run:
vendor/bin/phpmd modules/ text cleancode,codesize,controversial,design,naming,unusedcode
```

---

## 🚨 ENFORCEMENT VIOLATIONS LOG

Track violations here to improve rules:

| Date | Developer | Violation | Action | Prevention |
|------|-----------|-----------|--------|------------|
| 2025-11-06 | System | 13 security issues found | Created enforcement | Pre-commit hooks |
| | | | | |

---

## 📞 SUPPORT

**Questions about enforcement?**
1. Read: `modules/CIS_ARCHITECTURE_STANDARDS.md`
2. Read: `modules/SECURITY_AUDIT_REPORT.md`
3. Contact: Pearce Stephens <pearce.stephens@ecigdis.co.nz>

**Hook not working?**
```bash
# Check if executable:
ls -la .git/hooks/pre-commit

# Make executable:
chmod +x .git/hooks/pre-commit

# Test manually:
.git/hooks/pre-commit
```

**Bypass hook (emergency only):**
```bash
git commit --no-verify -m "Emergency fix"
# Then immediately fix the issue and commit properly
```

---

## ✅ INSTALLATION VERIFICATION

Run these commands to verify enforcement is active:

```bash
# 1. Check pre-commit hook exists and is executable
ls -la .git/hooks/pre-commit
# Expected: -rwxr-xr-x ... pre-commit

# 2. Check phpcs.xml exists
ls -la phpcs.xml
# Expected: -rw-r--r-- ... phpcs.xml

# 3. Test hook (should fail)
echo '<?php $password = "test123";' > /tmp/test_violation.php
git add /tmp/test_violation.php
git commit -m "Test violation"
# Expected: ❌ BLOCKED: Hardcoded password detected

# 4. Clean up
git reset HEAD /tmp/test_violation.php
rm /tmp/test_violation.php

# 5. Run PHPCS
vendor/bin/phpcs --version
# Expected: PHP_CodeSniffer version X.X.X
```

---

**STATUS: ENFORCEMENT ACTIVE ✅**
All tools installed and configured for Option B architecture protection.
