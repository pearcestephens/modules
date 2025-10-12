# CIS Modules Quality & Standards

This directory contains coding standards, quality tools, and enforcement configurations.

## 📚 Documentation

- **[CODING_STANDARDS.md](./CODING_STANDARDS.md)** - Complete coding standards guide
- **[ARCHITECTURE.md](./architecture/)** - System architecture documentation
- **[API.md](./api/)** - API endpoint documentation

## 🛠 Quality Tools

### PHP CS Fixer (PSR-12 Enforcement)

```bash
# Check code style violations
vendor/bin/php-cs-fixer fix --dry-run --diff

# Auto-fix code style
vendor/bin/php-cs-fixer fix

# Check specific file
vendor/bin/php-cs-fixer fix path/to/file.php
```

### PHPStan (Static Analysis)

```bash
# Analyze entire codebase (level 5)
vendor/bin/phpstan analyse --level=5

# Analyze specific module
vendor/bin/phpstan analyse --level=5 consignments/

# Generate baseline (ignore existing issues)
vendor/bin/phpstan analyse --level=5 --generate-baseline
```

### Quality Report

```bash
# Generate comprehensive quality report
bash tools/quality-report.sh
```

## 🔍 Pre-commit Hooks

Automatically enforces standards before each commit:

```bash
# Install hook (one-time setup)
chmod +x .git/hooks/pre-commit

# Hook runs automatically on git commit
git commit -m "feat: add new feature"
```

The hook checks:
- ✅ PHP syntax errors
- ✅ PHPStan violations
- ✅ Code style (PSR-12)
- ✅ Security patterns
- ✅ Required declarations

## 📊 Quality Metrics

### Current Targets

| Metric | Target | Current |
|--------|--------|---------|
| PHPStan Level | 5 | Run `phpstan` to check |
| Code Coverage | ≥80% | Run `phpunit --coverage-text` |
| PSR-12 Compliance | 100% | Run `php-cs-fixer` |
| Inline Handlers | 0 | Run `quality-report.sh` |

## 🚀 Quick Start

### 1. Install Dependencies

```bash
composer require --dev phpstan/phpstan
composer require --dev friendsofphp/php-cs-fixer
composer require --dev phpunit/phpunit
```

### 2. Run Quality Checks

```bash
# Full quality suite
bash tools/quality-report.sh

# Individual checks
vendor/bin/phpstan analyse --level=5
vendor/bin/php-cs-fixer fix --dry-run
```

### 3. Fix Issues

```bash
# Auto-fix code style
vendor/bin/php-cs-fixer fix

# Review PHPStan errors manually
vendor/bin/phpstan analyse --level=5 | less
```

## 🔐 Security Standards

### Critical Rules

1. **No inline JavaScript** - Violates CSP
   ```html
   <!-- ❌ BAD -->
   <button onclick="doSomething()">Click</button>
   
   <!-- ✅ GOOD -->
   <button class="js-action" data-action="doSomething">Click</button>
   ```

2. **All user input validated**
   ```php
   // ❌ BAD
   $id = $_GET['id'];
   
   // ✅ GOOD
   $id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
   if (!$id) throw new InvalidArgumentException();
   ```

3. **Output always escaped**
   ```php
   <!-- ❌ BAD -->
   <?= $userInput ?>
   
   <!-- ✅ GOOD -->
   <?= htmlspecialchars($userInput, ENT_QUOTES, 'UTF-8') ?>
   ```

4. **CSRF protection on forms**
   ```html
   <form method="POST">
       <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
   </form>
   ```

## 📝 Git Workflow

### Branch Naming

```
feature/TICKET-123-description
bugfix/fix-csrf-validation
hotfix/critical-security-fix
refactor/extract-view-helpers
```

### Commit Messages

```
type(scope): subject

feat(pack): add bulk quantity update
fix(csrf): validate token in all forms
refactor(views): extract component helper
docs(api): document pack endpoint
test(transfer): add integration tests
```

Types: `feat`, `fix`, `refactor`, `docs`, `test`, `chore`, `perf`, `style`

### Pull Request Checklist

Before merging:
- [ ] All tests passing
- [ ] PHPStan level 5 clean
- [ ] Code coverage ≥80%
- [ ] Peer review approved
- [ ] Documentation updated
- [ ] CHANGELOG.md entry added
- [ ] No security issues
- [ ] Performance validated

## 🐛 Troubleshooting

### PHPStan Errors

```bash
# View detailed error
vendor/bin/phpstan analyse path/to/file.php --level=5

# Generate baseline to track improvements
vendor/bin/phpstan analyse --level=5 --generate-baseline
```

### Code Style Failures

```bash
# See what will be changed
vendor/bin/php-cs-fixer fix --dry-run --diff

# Apply fixes
vendor/bin/php-cs-fixer fix
```

### Pre-commit Hook Blocking

```bash
# Bypass hook for emergency commits only
git commit --no-verify -m "hotfix: critical issue"

# Then fix and commit properly
vendor/bin/php-cs-fixer fix
git add .
git commit -m "style: fix code style violations"
```

## 📖 Resources

- [PSR-12 Specification](https://www.php-fig.org/psr/psr-12/)
- [PHPStan Documentation](https://phpstan.org/user-guide/getting-started)
- [OWASP Secure Coding Practices](https://owasp.org/www-project-secure-coding-practices-quick-reference-guide/)
- [Clean Code Principles](https://github.com/ryanmcdermott/clean-code-php)

## 🆘 Support

**Questions?** Contact: dev@ecigdis.co.nz  
**Wiki:** https://wiki.vapeshed.co.nz/coding-standards  
**Issues:** Open a ticket in Jira or GitHub

---

**Last Updated:** October 12, 2025  
**Version:** 2.0.0
