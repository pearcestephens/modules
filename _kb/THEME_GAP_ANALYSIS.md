# Theme Platform Gap Analysis (Work-in-Progress)

## 1. Scope
Consolidate Theme Studio v2.0, Professional Dark theme pack, ThemeEngine MVC loader, and legacy generators (Component, Inspiration, Builder PRO) into a unified multi-theme, token-driven system with consistent styling and secure CRUD/API.

## 2. Asset Inventory
- ThemeGenerator.php (color theory engine) - STATUS: stable
- theme-api.php (CRUD/generation) - STATUS: functional but PDO mismatch (escape_string), no auth gating
- 02_theme-customizer-v2.js / .css - STATUS: production-ready UI; needs integration hooks for Component/Inspiration tools
- Professional Dark theme (theme.json, main.css, components.css, main.js, views/*) - STATUS: imported; not mapped to token system
- ThemeEngine.php - STATUS: imported; not yet wired to ThemeManager or API active theme selection
- ThemeManager.php - STATUS: active legacy manager separate from ThemeEngine (naming conflict)
- Legacy Generators (Builder PRO, Component Generator, Inspiration Generator) - STATUS: recovered (not yet modularized)

## 3. Functional Domains & Current State
| Domain | Current Capability | Gaps |
|--------|--------------------|------|
| Theme CRUD | create/update/delete/list/duplicate | SQL injection risk (no prepared statements); lacking auth & audit; no version bumping |
| Active Theme | set/get active via DB | Not propagating to runtime (ThemeManager session vs DB); no cache invalidation |
| Generation | Color theory schemes (6) | Missing palette accessibility metrics (contrast AA/AAA); no adaptive theming (light/dark pair) |
| Manual Editing | Basic color pickers | Lacks full variable set (background/surface/text tokens), no typography variable editing UI yet |
| Import/Export | JSON working | No signature/hash integrity; no schema version validation |
| Multi-Theme Runtime | ThemeManager (legacy) + ThemeEngine (new) | Not unified; duplicate responsibilities; asset path inconsistency |
| Styling Tokens | CSS vars in customizer + theme pack vars | Not normalized to single token namespace; no semantic groups (bg-surface, border, elevation) |
| Components Library | Exists in recovered assets | Not exposed in UI; needs dynamic preview & injection mapping |
| Inspiration/AI | Inspiration generator recovered | Not integrated; no endpoint or UI panel |
| Component Generator | Recovered | Not modular; requires sandbox and naming collision protection |
| Security | Middleware pipeline present | API endpoints unrestricted (CORS *); missing rate limit per user/action |
| Permissions | None | Need role check (admin/design) + CSRF for mutating ops |
| Observability | Basic console logs | Need structured server logs with action + theme_id + user_id (correlation ID) |
| Performance | N/A | No caching for list/load; potential N+1 for future component metadata |

## 4. Priority Gap Remediation (P1-P3)
- P1: Secure DB Layer (PDO prepared), Auth/Permission/CSRF integration, unify active theme runtime.
- P2: Styling token normalization + Professional Dark mapping + ThemeManager <-> ThemeEngine merge plan.
- P3: Modularization & integration of Component + Inspiration generators with sandbox execution.

## 5. Risks & Mitigations
| Risk | Impact | Mitigation |
|------|--------|------------|
| SQL Injection via interpolated strings | Data breach | Immediate PDO refactor + whitelist fields |
| Inconsistent active theme across session vs DB | Visual mismatch | Single source of truth + sync hook on set_active |
| Styling fragmentation | UX inconsistency | Token manifest file + automated mapping script |
| Unauth theme manipulation | Security incident | requireAuth + permission check + rate limit |
| Legacy code path collisions | Runtime errors | Namespace isolation + facade (ThemeManagerUnified) |
| Import of malicious JSON | XSS/theme poisoning | Validate schema, strip script tags, enforce length limits |
| Component generator executing unsafe code | RCE | Sandbox (no eval), template whitelist, output escaping |
| Performance degradation with many themes | Slow UI | Pagination + SELECT projection + caching layer |

## 6. Target Acceptance Criteria (Initial Draft)
1. All theme CRUD operations use prepared statements; security middleware applied; unauthorized request → 401/403 with JSON envelope.
2. Setting active theme updates DB, session, and invalidates cached assets; runtime reflects change on next request.
3. Unified token manifest (`/modules/base/themes/_tokens.json`) drives both Theme Studio UI and theme pack CSS via build step.
4. Professional Dark mapped to tokens; at least 95% variable parity achieved; visual diff < threshold defined.
5. Component & Inspiration generators accessible under Advanced tab with sandboxed, non-blocking loading.
6. Import/export validates version and schema; rejects invalid/missing keys; includes integrity hash.
7. Audit log entries created for create/update/delete/set_active/import/export with user_id + theme_id.
8. RPS under 50ms average for listThemes with up to 100 themes (local test environment).
9. Accessibility: Minimum contrast ratio AA for primary vs background validated in generator output.
10. Zero fatal errors in logs after integration; error panel shows no unhandled exceptions for theme endpoints.

## 7. Next Steps
- Finalize architecture plan (ThemeManagerUnified) linking DB active theme + runtime loader.
- Implement PDO refactor and security hardening.
- Draft token manifest and mapping script.
- Build UI extension panels for Component/Inspiration.
- Add audit logging and structured response wrapper.
- Write automated tests (PHP + JS) for CRUD, generation, import export.

## 8. Change Log
- v0.1: Initial skeleton created.
