# CIS Themes — Professional AI-Assisted Theme Manager

This blueprint defines the end-to-end plan to consolidate themes, editors, runtime, and AI tooling into a single, professional system.

## 1. Objectives
- Single runtime: ThemeManager facade, adapter for cis-themes/theme.json
- Practical editors: tokens, components, layouts with live preview and 3D viewer
- AI-powered page generation and component scaffolding
- Git-backed workflow with safe write whitelist and rollback

## 2. Consolidation Strategy
- Runtime themes: `/modules/base/themes` (first-class)
- External theme packs: `/modules/cis-themes/themes` (consumed via adapter)
- Admin tools: `/modules/admin-ui` (mounted under `/admin-ui`, auth + permission)
- Archive deprecated/experimental content under `/modules/cis-themes/archived/{origin}/...`

## 3. Theme Runtime
- ThemeManager::init():
  - Load base theme (`theme.php`) or cis-themes (`theme.json`) adapter
  - Merge settings: colors, tokens, features
  - Expose to client: `window.CIS_THEME`, body data-*
  - Inject theme assets (styles/scripts) from theme.json
- Component rendering: ThemeManager::component delegates to cis-themes ThemeEngine for cis-themes themes

## 4. Editors & Tools (MVP)
- Tokens Editor
  - Edit color palettes (buttons, primary/secondary, backgrounds), typography scale, spacing
  - Persist to `tokens.json` and compile to CSS variables + SCSS
  - Live swatches preview panel
- Component Editor
  - Monaco editor for HTML/CSS/JS
  - Sandboxed iframe preview (fixtures for content)
  - One-click "Apply tokens" skin
- 3D Viewer
  - CSS transform-based 3D tilt; device frames; zoom
- AI Page Generator (stub)
  - Prompt → choose layout → scaffold page/view/routes → open diff → commit to branch

## 5. Governance & Security
- Permissions: `theme_admin` required
- Write whitelist: `themes/{active}/(views|assets/css|assets/js|components)`
- Git strategy: branch-per-change; PR to merge; rollback button
- Pre-merge checks: PHP lint, asset build, link check, e2e smoke

## 6. Roadmap
- Phase 1 (2–3 weeks)
  - Adapter in ThemeManager for cis-themes
  - Tokens Editor + live preview
  - Component Editor + sandbox
  - 3D viewer (basic)
  - AI page generator (stub)
- Phase 2 (3–5 weeks)
  - Drag-drop Layout Builder
  - Style Guide auto-generation
  - Three.js 3D viewer enhancements
  - CSS/JS version control dashboard
- Phase 3 (ongoing)
  - Component marketplace, theme export/import, multi-brand profiles
  - Performance budgets and regression checks

## 7. Developer Setup
- Ensure PHP 8.1+, node 18+
- `composer install` (root) and `npm install` (in cis-themes if needed)
- Set APP_DEBUG=true for full error overlays
- Ensure `/modules/admin-ui` protected by `requireAuth()` + `requirePermission('theme_admin')`

## 8. Testing
- Run PHP lints and unit tests
- Visual regression (screenshots) optional in Phase 2
- E2E smoke for generated pages

---

This document will be expanded with per-feature specs as they are implemented.
