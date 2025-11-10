# CIS v2 Theme

Enterprise-grade Bootstrap 5 theme with modern JS libraries, responsive layout, and dynamic sidebar.

Components: `components/head.php`, `header.php`, `sidebar.php`, `scripts.php`
Styles: `css/variables.css` (design tokens), `css/theme.css`
JS: `js/cis-v2.js` (namespace: `window.CISV2`)
Layouts: `layouts/dashboard.php`
Demo: `demo.php`

## Use

In module code that uses `CISTemplate`, switch theme:

```php
$tpl = new CISTemplate();
$tpl->setTheme('cis-v2');
$tpl->setTitle('Dashboard');
$tpl->startContent();
require __DIR__ . '/../../base/templates/themes/cis-v2/layouts/dashboard.php';
$tpl->endContent();
$tpl->render();
```

Or open the demo at `/modules/base/templates/themes/cis-v2/demo.php`.
