# Feature Activation Matrix

Feature | Status | Notes
------- | ------ | -----
Theme CRUD | Active (PDO) | Prepared statements + audit
Active Theme Sync | Active | UnifiedThemeContext (DB/session)
Theme Pack Listing | Active | list_packs endpoint
Theme Pack Load | Active | load_pack endpoint (bridge)
Multi-Theme Runtime Switch | Active | switch_runtime endpoint
Color Generation | Active | generate endpoint
Token Manifest | Initial | _tokens.json committed
Component Generator | Pending UI integration | JS module adaptation required
Inspiration Generator | Pending UI integration | Depends on component generator
Import/Export | Active (basic) | Future: integrity hash
Audit Logging | Active | JSON lines to theme_audit.log
Monaco Editor | Planned | Security gating required
Contrast Accessibility | Planned | Generation validator future
Token Build Script | Planned | Will enforce mapping
