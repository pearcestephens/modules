# CIS Modules Refactor - Migration Notes

**Date:** 2025-10-12  
**Migration:** Modular structure with shared base

## What Was Moved

### Shared Base
- `modules/consignments/_shared/` → `modules/_shared/`
- Now serves as foundation for all modules

### Controllers & Views  
- `modules/consignments/transfers/controllers/` → `modules/consignments/controllers/`
- `modules/consignments/transfers/views/` → `modules/consignments/views/`

## What Was Removed/Archived

### Moved to `_legacy/`:
- `modules/CIS TEMPLATE/` → `_legacy/20251012-HHMMSS/CIS_TEMPLATE/`
- `modules/module/` → `_legacy/20251012-HHMMSS/module/` 
- `modules/consignments/pages/` → `_legacy/20251012-HHMMSS/pages/`

### Deleted:
- `modules/consignments/_shared/` (moved to `_shared`)
- `modules/consignments/transfers/` (contents moved to `consignments/`)
- `modules/consignments/transfers/lib/Controller/BaseController.php` (superseded)

## New Paths

### Entry Points (unchanged URLs)
- `/modules/consignments/transfers/pack?transfer=ID` ✅ 
- `/modules/consignments/transfers/receive?transfer=ID` ✅
- `/modules/consignments/transfers/hub` ✅

### Internal Structure
- Controllers: `modules/consignments/controllers/PackController.php`
- Views: `modules/consignments/views/pack/full.php`
- Shared Layout: `modules/_shared/views/layouts/cis-template-bare.php`
- Shared Libraries: `modules/_shared/lib/`

## Namespace Changes

### Old:
```php
use Modules\Consignments\Transfers\controllers\PackController;
```

### New:
```php  
use Modules\Consignments\controllers\PackController;
```

## Layout Changes

### Before:
- Multiple layout files with potential `<body>` conflicts
- Per-module template copies

### After:
- Single canonical layout: `_shared/views/layouts/cis-template-bare.php`
- Includes global CIS chrome from `/assets/template/`
- No duplicate `<body>` tags

## Asset Loading

### Development Mode:
```
?dev=1 → ES modules from /js/pack/init.js
```

### Production Mode:
```
(default) → Bundles from /assets/js/pack.bundle.js
```

## Fixed Issues

1. **Double `<?php` tags** in pack/receive views
2. **Bootstrap consistency** - BS4 + CoreUI v3 only
3. **Layout inheritance** - single shared layout
4. **Namespace organization** - logical module structure

## Rollback Procedure

If issues arise:

1. **Restore from `_legacy/`:**
   ```bash
   mv _legacy/20251012-HHMMSS/CIS_TEMPLATE "CIS TEMPLATE"
   mv _legacy/20251012-HHMMSS/module module
   mv _legacy/20251012-HHMMSS/pages consignments/pages
   ```

2. **Restore Git backup** (if committed):
   ```bash
   git checkout HEAD~1 -- modules/
   ```

## Testing Checklist

- [ ] Pack page loads with CIS chrome
- [ ] Receive page loads with CIS chrome  
- [ ] No duplicate headers/footers
- [ ] Assets load correctly (dev & prod modes)
- [ ] API endpoints still functional
- [ ] CSRF protection intact

## Next Steps

1. **Other modules** can now inherit from `_shared/`
2. **Asset building** via `tools/build_js_bundles.php`
3. **Knowledge base** will auto-update documentation

---

**Questions?** Check `modules/_shared/README.md` for adoption guide.