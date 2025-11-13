# CIS Admin UI Module - Template & Design System Manager

**Version:** 2.0.0
**Updated:** October 30, 2025
**Status:** ✅ Production Ready

---

## 🎯 QUICK ACCESS

### **🔥 NEW: Template Showcase** (START HERE!)
**URL:** `https://staff.vapeshed.co.nz/modules/admin-ui/template-showcase.php`

**Interactive demonstration of ALL 5 base template layouts:**
- ✅ Dashboard Layout - Full page with sidebar
- ✅ Table Layout - Data table optimized
- ✅ Card Layout - Centered cards for forms
- ✅ Split Layout - Two-panel resizable
- ✅ Blank Layout - Full control

**Features:**
- Live demos you can click and test
- ASCII visual diagrams
- Feature lists for each layout
- Responsive testing
- Direct links to documentation

**👉 USE THIS TO SEE TEMPLATES BEFORE DECIDING ON PAYROLL REFACTORING!**

---

### Component Showcase
**URL:** `https://staff.vapeshed.co.nz/modules/admin-ui/`

Complete reference library showing:
- Buttons (all types, sizes, colors)
- Forms (inputs, selects, checkboxes)
- Tables (DataTables, responsive)
- Cards (various styles)
- Alerts (all status types)
- Modals (examples)
- Layout components

---

### Theme Builder
**URL:** `https://staff.vapeshed.co.nz/modules/admin-ui/theme-builder.php`

Visual theme editor with:
- Color picker interface
- Real-time preview
- CSS variable system
- Version history/changelog
- AI assistant (if available)

---

## 📁 What This Module Provides

### 1. **Template Showcase** 🆕
- `/template-showcase.php` - **DEFAULT ENTRY POINT**
- Interactive demos of all 5 base layouts
- Live testing environment
- Before deciding on implementation

### 2. **Component Library**
- `/index.php` - Complete UI component reference
- Examples of every Bootstrap component
- Code snippets included
- Best practices documentation

### 3. **Theme System**
- `/theme-builder.php` - Visual theme editor
- `/config/theme-config.php` - Central configuration
- `/lib/ThemeGenerator.php` - CSS generation engine
- `/_templates/css/theme-generated.css` - Auto-generated styles

### 4. **AI Integration** (Optional)
- `/lib/AIThemeAssistant.php` - AI theme suggestions
- `/api/ai-agent.php` - Conversational interface
- Graceful degradation if unavailable

---

## 🚀 RECOMMENDED WORKFLOW

### For Viewing Templates (Before Refactoring)

1. **Go to Template Showcase:**
   ```
   https://staff.vapeshed.co.nz/modules/admin-ui/template-showcase.php
   ```

2. **Click "View Live Demo" for each layout:**
   - Dashboard - See full page with sidebar
   - Table - See data table layout
   - Card - See centered card form
   - Split - See two-panel layout
   - Blank - See minimal wrapper

3. **Test responsive design:**
   - Resize browser window
   - Check mobile view
   - Test sidebar collapse

4. **Review documentation:**
   - Click "Visual Guide Documentation"
   - Click "Before/After Comparison"
   - Review feature lists

5. **Make decision:**
   - Which layout for payroll dashboard?
   - Which layout for pay runs list?
   - Which layout for pay run details?

---

### For Building New Features

1. **Check Component Library:**
   ```
   https://staff.vapeshed.co.nz/modules/admin-ui/
   ```

2. **Choose appropriate components**
3. **Copy code snippets**
4. **Implement in your module**

---

### For Customizing Theme

1. **Open Theme Builder:**
   ```
   https://staff.vapeshed.co.nz/modules/admin-ui/theme-builder.php
   ```

2. **Adjust colors using color pickers**
3. **Preview changes in real-time**
4. **Generate CSS**
5. **Changes apply across all CIS**

---

## 📊 Base Template System Status

**Location:** `/modules/base/_templates/`

**Layouts Available:**
```
✅ dashboard.php    (224 lines) - Full page layout
✅ table.php        (203 lines) - Data table optimized
✅ card.php         (~90 lines) - Centered card
✅ split.php        (~170 lines) - Two-panel resizable
✅ blank.php        (~60 lines) - Minimal wrapper
```

**Components Available:**
```
✅ header.php       (201 lines) - Top navigation
✅ sidebar.php      (284 lines) - Left navigation
✅ footer.php       (119 lines) - Footer bar
✅ search-bar.php   - AI-powered search
✅ breadcrumbs.php  - Navigation breadcrumbs
```

**Status:** 🟢 **100% COMPLETE & PRODUCTION-READY**

---

## 🎯 Payroll Integration Status

**Current State:**
- ❌ Payroll using custom layouts (WRONG)
- ❌ 1,686 lines of code
- ❌ Duplicate header/footer/sidebar
- ❌ 400+ lines inline CSS

**Target State:**
- ✅ Use base template system (CORRECT)
- ✅ 575 lines of code (66% reduction)
- ✅ No duplication (DRY principle)
- ✅ Consistent UI with all CIS modules

**Recommended Layouts for Payroll:**
- `dashboard.php` → Payroll dashboard
- `table.php` → Pay runs list
- `dashboard.php` → Pay run details
- `card.php` → Login/simple forms (if needed)

**Time to Refactor:** 2-3 hours
**Benefit:** Immediate + long-term maintenance savings

---

## 📖 Documentation

**Created Documents:**
1. `BASE_TEMPLATE_VISUAL_GUIDE.md` - Complete visual guide
2. `TEMPLATE_REFACTORING_COMPARISON.md` - Before/After comparison
3. `COMPREHENSIVE_AUDIT_REPORT.md` - Payroll audit findings
4. `FRONTEND_TEMPLATE_INTEGRATION_AUDIT.md` - Integration analysis

**Location:** See quick links in template showcase

---

## 🛠 Technical Details

### Module Structure
```
/modules/admin-ui/
├── template-showcase.php          ⭐ NEW DEFAULT ENTRY
├── index.php                      Component library
├── theme-builder.php              Theme editor
│
├── _templates/
│   ├── components/                UI components
│   ├── layouts/                   (Future)
│   └── css/
│       ├── theme-generated.css    Auto-generated
│       └── theme-custom.css       Manual overrides
│
├── config/
│   ├── theme-config.php           Central configuration
│   └── theme-changelog.json       Version history
│
├── lib/
│   ├── ThemeGenerator.php         CSS generation
│   └── AIThemeAssistant.php       AI integration
│
└── api/
    └── ai-agent.php               API endpoint
```

### CSS Variables System
```css
:root {
    --cis-primary: #8B5CF6;
    --cis-success: #10b981;
    --cis-warning: #f59e0b;
    --cis-danger: #ef4444;
    /* 50+ more variables */
}
```

All components use these variables for easy theming.

---

## ✅ Success Criteria

**You know the system is working when:**
- ✅ Template showcase loads and shows 5 layouts
- ✅ Each "View Live Demo" button works
- ✅ Demos are responsive (test mobile)
- ✅ Theme builder can change colors
- ✅ Component library shows all examples

---

## 🎉 SUMMARY

**Three Ways to Access:**

1. **🔥 Template Showcase** (Recommended First!)
   - See all 5 layouts live
   - Interactive demos
   - Before/after comparisons
   - **URL:** `/modules/admin-ui/template-showcase.php`

2. **Component Library**
   - See all UI components
   - Get code examples
   - **URL:** `/modules/admin-ui/`

3. **Theme Builder**
   - Customize colors
   - Real-time preview
   - **URL:** `/modules/admin-ui/theme-builder.php`

---

**🎯 For Payroll Decision:**
Start with **Template Showcase** → Test each layout → Review documentation → Make informed decision on refactoring approach.

---

**Last Updated:** October 30, 2025
**Version:** 2.0.0
**Status:** ✅ Complete & Ready
