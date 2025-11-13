# Monaco Editor Integration Plan (Theme Studio v2.0)

## Objective
Embed a secure, lazy-loaded code editor (Monaco) into the Theme Studio Advanced tab to allow:
- Editing generated component HTML/CSS/JS snippets
- Viewing and tweaking theme token CSS variable mappings
- Exporting modified snippets to copy/download
- Optional sandbox preview iframe with hot-reload

## Scope
Phase 1 (Read/Modify Component Output):
- Load Monaco only when user opens “Advanced” tab and clicks "Open Code Editor" button
- Provide two models: component.html and component.css (populate from last generator run)
- Basic editor actions: format, copy, download

Phase 2 (Token & Live Theme Variables):
- Third model: tokens.css (generated from `_tokens.json` + current theme overrides)
- Allow editing derived CSS custom properties (scoped preview only; never write directly to `_tokens.json`)

Phase 3 (Sandbox Preview):
- Iframe sandbox with sanitized HTML + scoped CSS
- Live reload on editor change (debounced)
- Error overlay (JS exceptions captured and displayed)

Phase 4 (Persistence & Versioning):
- Optional save of custom component snippet attached to theme record (new DB table `theme_components`)
- Revision history with diff view (store compressed JSON + gzip rows)

## Security & Hardening
- Role gate: `design_admin` OR `admin` only; hide button otherwise
- CSP adjustments: disallow remote script execution from edited JS (strip `<script>` tags unless explicitly allowed in secure mode)
- Sanitize HTML: remove inline event handlers (`on*=`), `<script>`, `<iframe>`, `<object>` before sandbox inject
- Disallow network calls in sandbox: override `fetch`, `XMLHttpRequest` to noop or read-only
- Size limits: reject pastes > 100KB
- Timeouts: debounce live preview apply (300ms); bail if > 100 apply events in 30s

## Lazy Load Strategy
1. Button click triggers: `loadMonacoEditor()`
2. Inject loader script:
   ```html
   <script src="/assets/vendor/monaco/vs/loader.js"></script>
   ```
3. Configure base path:
   ```js
   require.config({ paths: { 'vs': '/assets/vendor/monaco/vs' } });
   ```
4. Load editor:
   ```js
   require(['vs/editor/editor.main'], () => initEditors());
   ```
5. Provide fallback message if load fails (toast + retry)

## File Structure Additions
- `/public_html/assets/vendor/monaco/` (static extracted Monaco distribution)
- `/modules/base/templates/vape-ultra/assets/js/monaco-loader.js` (helper)
- `/modules/base/templates/vape-ultra/assets/css/monaco-overrides.css` (dark theme adjustments to match active tokens)

## Editor Initialization (Pseudo-code)
```js
function initEditors() {
  const htmlModel = monaco.editor.createModel(lastComponent.html || '<div>Component</div>', 'html');
  const cssModel = monaco.editor.createModel(lastComponent.css || '/* component styles */', 'css');
  const tokensModel = monaco.editor.createModel(generateTokenCss(), 'css');
  editors.html = monaco.editor.create(document.getElementById('vu-editor-html'), { model: htmlModel, theme: 'vs-dark', automaticLayout: true });
  editors.css = monaco.editor.create(document.getElementById('vu-editor-css'), { model: cssModel, theme: 'vs-dark', automaticLayout: true });
  editors.tokens = monaco.editor.create(document.getElementById('vu-editor-tokens'), { model: tokensModel, readOnly: false, theme: 'vs-dark' });
}
```

## Token CSS Generation
```js
function generateTokenCss() {
  const t = currentTheme.tokens || currentTheme.theme_data || {}; // normalized
  const colors = t.colors || {}; const lines = [':root {'];
  Object.entries(colors).forEach(([k,v]) => lines.push(`  --vu-${k}: ${v};`));
  lines.push('}');
  return lines.join('\n');
}
```

## Sandbox Preview Flow
1. On change (debounced): get HTML + CSS
2. Sanitize HTML (DOMPurify or basic regex fallback if dependency restricted)
3. Build preview document string:
```html
<!DOCTYPE html><html><head><style>/* tokens */\n${tokensCss}\n/* component */\n${componentCss}</style></head><body>${componentHtml}</body></html>
```
4. Write to iframe via `srcdoc`
5. Catch exceptions + display overlay

## Persistence Extension (Future)
- Table: `theme_components(id, theme_id, name, html, css, created_at, updated_at, version, integrity_hash)`
- API endpoints: `save_component`, `list_components`, `load_component`, `delete_component`
- Integrity: hash of concatenated html+css
- Audit logging: reuse `ThemeAuditLogger`

## Acceptance Criteria
- Monaco loads only after explicit user interaction
- Editor reflects last generated component output
- Token CSS matches active theme tokens (normalized)
- Sandbox updates < 500ms average on edit (debounced)
- Sanitization removes unsafe constructs reliably
- No unauthorized user sees editor button
- Integrity hash generated for saved components (Phase 4)

## Risks & Mitigations
| Risk | Mitigation |
|------|------------|
| Heavy bundle size | Lazy load + gzip + HTTP/2 multiplexing |
| XSS via edited HTML | Sanitization + removal of inline events |
| Performance stalls | Debounce + diff apply instead of full iframe reload (Phase 2) |
| Token mismatch | Always regenerate token CSS on active theme change |

## Next Implementation Steps
1. Vendor Monaco distribution placement.
2. Add loader helper (`monaco-loader.js`).
3. Add Advanced tab button + container markup for editors.
4. Implement `loadMonacoEditor()` + security checks.
5. Generate token CSS and initialize models.
6. Add sandbox iframe + live preview binding.
7. Add basic actions (copy, download, format).
8. Create follow-up API spec for component persistence (optional Phase 4).

---
Prepared: $(date +%F)
Status: Draft ready for execution.
