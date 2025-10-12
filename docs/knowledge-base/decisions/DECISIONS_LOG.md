# Project Decisions Log

**Purpose:** Track all architectural and technical decisions with rationale

---

## October 12, 2025

### ✅ DECISION: Use Wrapper Template Approach
**Context:** Template was recreating entire CIS UI (header, sidebar, footer)  
**Decision:** Change to wrapper that includes `/assets/template/` components  
**Rationale:**
- Reduces duplication (418 lines → 100 lines)
- Maintains single source of truth for UI
- Easier to update when CIS UI changes
- Follows DRY principle

**Alternatives Considered:**
- ❌ Keep recreating: Too much duplication
- ❌ Iframe CIS UI: Performance and CSS issues
- ✅ Include approach: Clean, maintainable

**Outcome:** Template now 76% smaller, easier to maintain

---

### ✅ DECISION: Rename `_base/` to `base/`
**Context:** Underscore prefix not standard for directory names  
**Decision:** Rename to `base/` (industry standard)  
**Rationale:**
- PSR-12 doesn't use underscore prefixes for directories
- `AbstractController` / `BaseController` are standard class names
- Underscore prefix only used for PHP magic methods/internal vars

**Alternatives Considered:**
- ❌ Keep `_base/`: Not standard, looks like internal/private
- ❌ Use `shared/`: Ambiguous (shared with who?)
- ✅ Use `base/`: Clear, standard, industry-accepted

**Outcome:** More professional, PSR-12 compliant

---

### ✅ DECISION: Strict Module Detection
**Context:** KB refresh counted `docs/`, `tools/` as modules  
**Decision:** Only directories with `index.php` or `module_bootstrap.php` are modules  
**Rationale:**
- Clear definition of what is a "module"
- Prevents pollution of module docs
- More accurate stats (1 module vs 5 modules)

**Alternatives Considered:**
- ❌ Whitelist approach: Brittle, needs updates
- ❌ Detect by controllers/: Not all modules have them
- ✅ Require entry point: Clear, logical

**Outcome:** Accurate module count (1 vs 5)

---

### ✅ DECISION: Auto-Cleanup in Knowledge Base
**Context:** Orphaned docs, old files, empty dirs accumulate  
**Decision:** Add auto-cleanup on every refresh  
**Rationale:**
- Maintenance-free (runs automatically)
- Keeps repo clean
- Removes noise for AI searches
- Configurable (can disable or adjust retention)

**Features Added:**
- Remove orphaned module docs (modules that no longer exist)
- Delete docs not updated in 30+ days (except protected files)
- Clean logs older than 7 days
- Remove empty directories

**Alternatives Considered:**
- ❌ Manual cleanup: Tedious, error-prone
- ❌ Aggressive (delete everything): Too risky
- ✅ Smart cleanup with protection: Safe, automatic

**Outcome:** 12 empty directories removed on first run

---

### ✅ DECISION: One Master Documentation File
**Context:** Multiple README, summaries, plans caused confusion  
**Decision:** Single `README.md` (850+ lines) with everything  
**Rationale:**
- Single source of truth
- No hunting across multiple files
- Easier to keep updated
- Better for AI indexing

**What's Included:**
- Quick start
- Full project structure
- Database & sessions
- Routing system
- Template architecture
- Error handling
- Building modules
- Knowledge base refresh
- External dependencies

**Alternatives Considered:**
- ❌ Multiple small docs: Scattered, out of sync
- ❌ Wiki approach: Too complex for small project
- ✅ One comprehensive file: Clear, complete

**Outcome:** Everything in one place, no confusion

---

## Decision Template (For Future Use)

```markdown
### ✅/❌ DECISION: [Title]
**Context:** [What problem are we solving?]
**Decision:** [What did we decide?]
**Rationale:**
- [Why this approach?]
- [What benefits?]
- [What risks mitigated?]

**Alternatives Considered:**
- ❌ Option A: [Why not?]
- ❌ Option B: [Why not?]
- ✅ Option C: [Why chosen?]

**Outcome:** [What changed?]
```

---

**Last Updated:** October 12, 2025  
**Total Decisions:** 5
