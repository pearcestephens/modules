# 🎯 Consignments Module - Quick Reference Card

**Last Updated:** October 12, 2025  
**Print This!** Keep it near your workstation for quick answers.

---

## 📁 File Organization

```
consignments/
├── api/              # JSON endpoints (POST handlers)
├── controllers/      # MVC controllers (PascalCase.php)
├── views/           # HTML templates (snake_case.php)
│   ├── pack/        # Pack transfer views
│   ├── receive/     # Receive transfer views
│   ├── hub/         # Dashboard views
│   └── home/        # Home/landing views
├── components/      # Reusable UI blocks (snake_case.php)
│   ├── pack/        # Pack-specific components
│   ├── receive/     # Receive-specific components
│   └── _*.php       # Shared components
├── lib/             # Business logic classes (PascalCase.php)
├── css/             # Stylesheets (kebab-case.css)
├── js/              # JavaScript modules (kebab-case.js)
├── tools/           # Utility scripts (snake_case.php)
└── docs/            # Documentation (kebab-case.md)
```

---

## 🚨 Must-Follow Patterns

### ✅ Correct Patterns

```php
<?php
/**
 * File: pack_submit.php
 * Purpose: Handle pack transfer submission
 * 
 * @package Consignments\Api
 * @author CIS Development Team
 * @date 2025-10-12
 */
declare(strict_types=1);

namespace Consignments\Api;

use Consignments\Lib\Db;
use Consignments\Lib\Security;

// CSRF check
Security::assertCsrf($_POST['csrf'] ?? '');

// XSS-safe output
echo htmlspecialchars($name ?? '', ENT_QUOTES, 'UTF-8');

// External CSS/JS
<link href="<?= Helpers::url('/css/hub.css') ?>">
<script src="<?= Helpers::url('/js/hub.js') ?>"></script>

// Event delegation (no inline onclick)
<button class="js-create-transfer" data-action="create">
```

### ❌ Wrong Patterns

```php
<?php
// ❌ No docblock
// ❌ No declare(strict_types=1)

// ❌ Wrong namespace
use Transfers\Lib\Db;

// ❌ No CSRF check
if ($_POST['action'] === 'submit') {

// ❌ XSS vulnerability
echo $userInput;

// ❌ Inline CSS
<style>.my-class { color: red; }</style>

// ❌ Inline JS onclick
<button onclick="doThing()">
```

---

## 🔧 Common Tasks

### Add New API Endpoint

```php
<?php
/**
 * File: my_action.php
 * Purpose: Handle my custom action
 * @package Consignments\Api
 */
declare(strict_types=1);

use Consignments\Lib\Db;
use Consignments\Lib\Security;

header('Content-Type: application/json');

try {
  if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit(json_encode(['ok'=>false,'error'=>'Method not allowed']));
  }
  
  Security::assertCsrf($_POST['csrf'] ?? '');
  
  $pdo = Db::pdo();
  // Your logic here
  
  echo json_encode(['ok'=>true, 'data'=>$result]);
  
} catch (Throwable $e) {
  Log::error($e);
  http_response_code(500);
  $msg = (APP_DEBUG ? $e->getMessage() : 'An error occurred');
  echo json_encode(['ok'=>false, 'error'=>$msg]);
}
```

### Add New View Component

```php
<?php
/**
 * File: my_component.php
 * Purpose: Reusable UI component for my feature
 * @package Consignments\Components
 */
?>
<div class="my-component">
  <h3><?= htmlspecialchars($title ?? 'Title', ENT_QUOTES, 'UTF-8') ?></h3>
  <form method="POST">
    <?= csrf_token_input() ?>
    <input name="field" value="<?= htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8') ?>">
    <button type="submit">Submit</button>
  </form>
</div>
```

### Add New Stylesheet

```css
/**
 * File: my-feature.css
 * Purpose: Styles for my custom feature
 * @package Consignments
 */

/* Component Styles */
.my-component {
  padding: 1rem;
  border: 1px solid #ccc;
  border-radius: 8px;
}

.my-component h3 {
  margin-bottom: 0.5rem;
  font-weight: 600;
}

/* Responsive */
@media (max-width: 768px) {
  .my-component {
    padding: 0.5rem;
  }
}
```

### Add JavaScript Module

```javascript
/**
 * File: my-feature.js
 * Purpose: Client-side logic for my feature
 * @package Consignments
 */

export function initMyFeature(config) {
  // Event delegation
  document.addEventListener('click', (e) => {
    if (e.target.closest('.js-my-button')) {
      handleClick(e);
    }
  });
}

function handleClick(e) {
  const btn = e.target.closest('.js-my-button');
  const action = btn.dataset.action;
  
  fetch('/api/my_action.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      csrf: config.csrf,
      action: action
    })
  })
  .then(r => r.json())
  .then(data => {
    if (data.ok) {
      console.log('Success:', data);
    }
  });
}
```

---

## 🛡️ Security Checklist

Before committing code, verify:

- [ ] ✅ `declare(strict_types=1)` at top
- [ ] ✅ Docblock with file info
- [ ] ✅ Namespace is `Consignments\*`
- [ ] ✅ CSRF check in POST handlers
- [ ] ✅ All outputs use `htmlspecialchars()`
- [ ] ✅ No inline `<style>` or `<script>`
- [ ] ✅ No inline `onclick=` handlers
- [ ] ✅ Forms have `csrf_token_input()`
- [ ] ✅ Error messages sanitized
- [ ] ✅ No debug statements (`var_dump`, `console.log`)

---

## 🔍 Quick Fixes

### Fix All Code Quality Issues
```bash
php tools/auto_fix.php --dry-run  # Preview
php tools/auto_fix.php --fix      # Apply
```

### Find Missing CSRF Tokens
```bash
grep -r "<form" components/ | grep -v "csrf_token_input"
```

### Find Inline Styles
```bash
grep -r "<style>" views/ components/
```

### Find Inline onclick
```bash
grep -r "onclick=" views/ components/
```

### Find XSS Vulnerabilities
```bash
grep -r "<?=" views/ | grep -v "htmlspecialchars"
```

### Check Namespace Consistency
```bash
grep -r "use Transfers\\" api/ lib/
```

---

## 📊 File Naming Rules

| Type | Convention | Example |
|------|------------|---------|
| API Endpoints | `snake_case.php` | `pack_submit.php` |
| Controllers | `PascalCase.php` | `PackController.php` |
| Views | `snake_case.php` | `full.php` |
| Components | `snake_case.php` | `add_products_modal.php` |
| Lib Classes | `PascalCase.php` | `Security.php` |
| CSS Files | `kebab-case.css` | `transfer-core.css` |
| JS Files | `kebab-case.js` | `pack-init.js` |
| Tools | `snake_case.php` | `auto_fix.php` |
| Docs | `UPPERCASE.md` | `README.md` |

---

## 🎨 HTML Structure Template

```html
<!-- Component: components/pack/my_component.php -->
<section class="vt-block vt-block--my-feature">
  <div class="vt-header">
    <h3 class="vt-title"><?= htmlspecialchars($title ?? '', ENT_QUOTES, 'UTF-8') ?></h3>
  </div>
  
  <div class="vt-body">
    <form class="vt-form" method="POST">
      <?= csrf_token_input() ?>
      
      <div class="form-group">
        <label class="form-label">Field Name</label>
        <input 
          type="text" 
          name="field" 
          class="form-control" 
          value="<?= htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8') ?>"
          required
        >
      </div>
      
      <div class="form-actions">
        <button type="submit" class="btn btn-primary">Submit</button>
        <button type="button" class="btn btn-light" data-dismiss="modal">Cancel</button>
      </div>
    </form>
  </div>
</section>
```

---

## 🔗 Helper Functions

```php
// XSS protection
htmlspecialchars($var ?? '', ENT_QUOTES, 'UTF-8')

// CSRF token (form)
<?= csrf_token_input() ?>

// CSRF token (AJAX)
fetch('/api/endpoint.php', {
  body: JSON.stringify({
    csrf: '<?= csrf_token() ?>',
    // ...
  })
})

// URL generation
<?= Modules\Base\Helpers::url('/css/transfer.css') ?>

// Asset with version
<?= asset_url('/css/hub.css?v=' . ASSET_VERSION) ?>

// User info
get_user_id()
get_user_details()
has_permission('consignments.pack')

// Safe redirect
safe_redirect('/transfers/pack')
```

---

## 📞 Troubleshooting

### CSS not loading?
1. Check file exists: `ls -la css/hub.css`
2. Check URL in HTML: View source, click link
3. Check file permissions: `chmod 644 css/hub.css`
4. Clear browser cache: Ctrl+Shift+R

### JavaScript errors?
1. Open browser console: F12
2. Check for syntax errors
3. Verify file path in Network tab
4. Check CSP headers

### CSRF failures?
1. Check form has `<?= csrf_token_input() ?>`
2. Check API verifies: `Security::assertCsrf($_POST['csrf'])`
3. Check session is started
4. Clear cookies and re-login

### Namespace errors?
1. Run: `grep -r "Transfers\\" api/ lib/`
2. Fix manually or: `php tools/auto_fix.php --fix`
3. Clear opcache: Restart PHP-FPM

### Database errors?
1. Check connection in `lib/Db.php`
2. Verify credentials in `.env`
3. Check DB logs: `tail -f /var/log/mysql/error.log`

---

## 📚 Documentation Files

| File | Purpose | When to Update |
|------|---------|----------------|
| `AUDIT_REPORT.md` | Full code audit | After major changes |
| `FIX_SUMMARY.md` | Implementation summary | After applying fixes |
| `BOOTSTRAP_GUIDE.md` | Bootstrap usage | When bootstrap changes |
| `README.md` | Module overview | Always keep current |
| `CHANGELOG.md` | Version history | Every release |

---

## 🎯 Performance Targets

| Metric | Target | Current | Status |
|--------|--------|---------|--------|
| Page Load | < 2s | ~1.8s | ✅ |
| API Response | < 300ms | ~250ms | ✅ |
| CSS File Size | < 50KB | 15KB | ✅ |
| JS Bundle Size | < 200KB | 180KB | ✅ |
| Lighthouse Score | > 90 | 88 | ⚠️ |

---

## 🚀 Deployment Checklist

Before deploying to production:

- [ ] All tests pass
- [ ] No console errors
- [ ] No PHP warnings/notices
- [ ] CSS/JS minified
- [ ] Asset versions updated
- [ ] Database migrations applied
- [ ] Backups verified
- [ ] Rollback plan ready
- [ ] Monitoring alerts configured
- [ ] Team notified

---

## 💡 Pro Tips

1. **Always dry-run first**: `--dry-run` before `--fix`
2. **Test in staging**: Never test in production
3. **Commit often**: Small commits are easier to revert
4. **Document changes**: Update CHANGELOG.md
5. **Review diffs**: Check what changed before committing
6. **Keep it simple**: Don't over-engineer
7. **Ask for help**: Better to ask than break production
8. **Monitor logs**: Watch logs after deployment
9. **Use version control**: Git is your friend
10. **Write tests**: Future you will thank you

---

## 🎓 Learning Resources

- [PSR-12 Style Guide](https://www.php-fig.org/psr/psr-12/)
- [OWASP XSS Prevention](https://cheatsheetseries.owasp.org/cheatsheets/Cross_Site_Scripting_Prevention_Cheat_Sheet.html)
- [CSRF Protection](https://cheatsheetseries.owasp.org/cheatsheets/Cross-Site_Request_Forgery_Prevention_Cheat_Sheet.html)
- [Content Security Policy](https://developer.mozilla.org/en-US/docs/Web/HTTP/CSP)

---

**Last Updated:** October 12, 2025  
**Version:** 1.0.0  
**Print Date:** _______________

📋 **Printed?** ☐ Yes | **Laminated?** ☐ Yes | **On Wall?** ☐ Yes
