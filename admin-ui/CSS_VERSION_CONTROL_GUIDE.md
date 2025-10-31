# 🎨 CSS & Theme Version Control System - Complete Guide

## 🎯 What This Is

A **Git-style version control system** for your CSS files + a **component library** that lets you manage, version, and rollback all your stylesheets and reusable HTML blocks.

---

## 📁 Strict CSS Architecture

### The Three-Tier System:

```
css/
├── core/              🔒 LOCKED - Bare minimum, untouchable
│   └── base.css       • CSS resets, normalize, variables
│
├── dependencies/      📦 EXTERNAL - Bootstrap, FontAwesome, etc.
│   ├── bootstrap.css
│   ├── fontawesome.css
│   └── [other-libs].css
│
└── custom/            ✏️ EDITABLE - All your customizations
    ├── theme.css      • Your brand colors, styles
    ├── components.css • Custom component styles
    ├── layout.css     • Grid, containers, spacing
    └── [your-css].css • Any other custom files
```

### Rules:

1. **CORE = Locked**
   - Only system-level resets and CSS variables
   - Never edited directly (admin override only)
   - Examples: normalize.css, CSS custom properties

2. **DEPENDENCIES = Read-Only**
   - External libraries (Bootstrap, FA, etc.)
   - Updated via CDN or package manager
   - Never modified directly

3. **CUSTOM = Your Playground**
   - ALL your customizations go here
   - Version controlled automatically
   - Rollback-enabled
   - Examples: theme.css, components.css, layout.css

---

## ⚡ Features

### 1. Version Control
- **Git-style commits** - Save CSS versions with messages
- **History viewer** - See all past versions
- **Rollback** - Restore any previous version
- **Auto-backup** - Current state backed up before rollback
- **Metadata tracking** - Who saved, when, file size, hash

### 2. Diff Viewer
- **Line-by-line comparison** between versions
- **Color-coded changes**:
  - 🟢 Green = Added lines
  - 🔴 Red = Removed lines
  - 🟡 Yellow = Changed lines
- **Side-by-side view** (coming soon)

### 3. Component Library
- **Store reusable HTML blocks** (buttons, cards, forms, etc.)
- **Categorized** - Organize by type (navigation, forms, alerts, etc.)
- **Preview** - See component before using
- **Copy to clipboard** - One-click copy HTML
- **Version controlled** - Components have update history

### 4. Smart Editor
- **CodeMirror integration** - Syntax highlighting, line numbers
- **CSS validation** - Real-time error checking
- **Autocomplete** - CSS property suggestions
- **Read-only mode** - Prevents editing locked files

---

## 🚀 How to Use

### Getting Started

1. **Open the System:**
   ```
   https://staff.vapeshed.co.nz/modules/admin-ui/css-version-control.php
   ```

2. **First Time Setup:**
   - System auto-creates directories
   - Default files are created in `css/core/` and `css/custom/`

### Editing CSS

1. **Select a file** from the sidebar (left panel)
   - 🔒 Core files (read-only, shows warning)
   - 📦 Dependencies (read-only)
   - ✏️ Custom files (editable)

2. **Edit in CodeMirror** (center panel)
   - Full syntax highlighting
   - Line numbers
   - Auto-save drafts

3. **Save Version**
   - Click **"Save Version"** button
   - Enter commit message (e.g., "Updated button styles")
   - Version is saved with timestamp and metadata

### Version History

1. **Go to "Versions" tab**
2. **See all saved versions:**
   - Message
   - Timestamp
   - Who saved it
   - File size

3. **Rollback:**
   - Click "Rollback" on any version
   - Current state is auto-backed up
   - File reverts to selected version

### Comparing Versions (Diff)

1. **Go to "Diff Viewer" tab**
2. **Select two versions** to compare
3. **Click "Compare"**
4. **See differences:**
   - Lines added (green)
   - Lines removed (red)
   - Lines changed (yellow)

### Component Library

#### Creating Components:

1. **Go to "Components" tab**
2. **Click "New Component"**
3. **Fill in:**
   - Name (e.g., "Primary Button")
   - Category (e.g., "Buttons")
   - HTML code
   - CSS (optional)
   - Description

4. **Save** - Component is now in library

#### Using Components:

1. **Browse component grid**
2. **Click "Copy"** on any component
3. **HTML is copied to clipboard**
4. **Paste** into your page

---

## 📊 Version Control Flow

### Standard Workflow:

```
1. Select CSS file
   ↓
2. Make changes
   ↓
3. Save Version (with message)
   ↓
4. Version stored in css-versions/
   ↓
5. Metadata saved (timestamp, user, hash)
   ↓
6. Continue editing OR rollback anytime
```

### Rollback Flow:

```
1. Go to Versions tab
   ↓
2. Find version to restore
   ↓
3. Click "Rollback"
   ↓
4. Current state auto-backed up
   ↓
5. File reverts to selected version
   ↓
6. Version history updated
```

---

## 🗂️ File Structure

### Where Versions Are Stored:

```
css-versions/
├── theme/                  # Versions for theme.css
│   ├── 1635789123_a3f5e9c2.css   # Snapshot
│   ├── 1635789123_a3f5e9c2.json  # Metadata
│   ├── 1635789456_b4g6f0d3.css
│   └── 1635789456_b4g6f0d3.json
│
└── components/             # Versions for components.css
    ├── 1635789789_c5h7g1e4.css
    └── 1635789789_c5h7g1e4.json
```

### Version Metadata (JSON):

```json
{
  "id": "1635789123_a3f5e9c2",
  "file": "theme.css",
  "message": "Updated primary button styles",
  "timestamp": "2024-10-31 14:32:03",
  "size": 4523,
  "hash": "a3f5e9c27d8b1f4e...",
  "user": "admin"
}
```

---

## 🔐 Security & Permissions

### File Locking:

- **Core files** - Read-only in UI (admin override via FTP)
- **Dependencies** - Read-only (update via package manager)
- **Custom files** - Full edit access

### Version Limit:

- **Max 50 versions** per file (configurable)
- Oldest versions auto-deleted when limit reached
- Critical versions can be "pinned" (coming soon)

### Backup:

- **Auto-backup before rollback** - Current state saved
- **Manual snapshots** - Save full CSS archive (coming soon)
- **Export/Import** - Download versions as ZIP (coming soon)

---

## 🧩 Component System

### Component Structure:

```json
{
  "id": "comp_1635789123",
  "name": "Primary Button",
  "category": "Buttons",
  "html": "<button class=\"btn btn-primary\">Click Me</button>",
  "css": ".btn-primary { background: #667eea; }",
  "description": "Main call-to-action button",
  "tags": ["button", "cta", "primary"],
  "updated_at": "2024-10-31 14:32:03"
}
```

### Categories:

- **Navigation** - Menus, breadcrumbs, tabs
- **Buttons** - Primary, secondary, icon buttons
- **Forms** - Inputs, selects, checkboxes
- **Cards** - Content cards, product cards
- **Alerts** - Success, error, warning messages
- **Modals** - Dialogs, popups
- **Tables** - Data tables, responsive tables
- **Lists** - Ordered, unordered, custom lists
- **Media** - Images, videos, galleries
- **Utilities** - Spacing, text, colors

### Component Best Practices:

1. **Self-contained** - Include all necessary HTML/CSS
2. **Documented** - Add clear descriptions
3. **Reusable** - Design for multiple contexts
4. **Accessible** - WCAG 2.1 AA compliant
5. **Responsive** - Mobile-first design

---

## 🎨 Theme System Integration

### How It Works with Theme Builder:

1. **Theme Builder** generates CSS variables
2. **CSS Version Control** tracks changes
3. **Components** use CSS variables for consistency
4. **Version history** preserves theme evolution

### Example Workflow:

```
1. Theme Builder: Generate purple-blue gradient theme
   ↓
2. Save as version: "Purple gradient v1"
   ↓
3. Apply to components in library
   ↓
4. Use components throughout site
   ↓
5. Change theme? Rollback CSS versions!
```

---

## 🛠️ Advanced Features

### API Endpoints:

```php
POST css-version-control.php

Actions:
- save_css_version       // Save new version
- get_css_versions       // List all versions
- rollback_css          // Restore old version
- diff_css              // Compare versions
- list_css_files        // Get file tree
- save_component        // Create/update component
- list_components       // Get component library
- get_component         // Get single component
- delete_component      // Remove component
```

### Programmatic Usage:

```javascript
// Save CSS version
$.post('css-version-control.php', {
  action: 'save_css_version',
  file: 'custom/theme.css',
  content: cssContent,
  message: 'Updated colors'
});

// Rollback
$.post('css-version-control.php', {
  action: 'rollback_css',
  file: 'custom/theme.css',
  version_id: '1635789123_a3f5e9c2'
});

// Get component
$.post('css-version-control.php', {
  action: 'get_component',
  component_id: 'comp_1635789123'
});
```

---

## 🔄 Migration from Old System

### If You Already Have CSS:

1. **Copy existing CSS** to `css/custom/`
2. **Open CSS Version Control**
3. **Select each file**
4. **Click "Save Version"** with message "Initial commit"
5. **Done!** - Now version controlled

### Organizing Files:

```
OLD:
style.css (5000 lines - everything mixed)

NEW:
css/core/base.css          (100 lines - resets only)
css/dependencies/bootstrap.css  (external)
css/custom/theme.css       (500 lines - colors, variables)
css/custom/components.css  (1000 lines - component styles)
css/custom/layout.css      (800 lines - grid, spacing)
css/custom/utilities.css   (300 lines - helpers)
```

---

## 📈 Best Practices

### 1. Commit Messages

**Good:**
- "Updated button hover states for better UX"
- "Fixed navbar z-index conflict with modals"
- "Added dark mode color variables"

**Bad:**
- "update"
- "asdf"
- "changes"

### 2. When to Save Versions

- ✅ After completing a feature
- ✅ Before making risky changes
- ✅ Before production deploy
- ✅ After major refactoring

- ❌ After every single line change
- ❌ For experimental tests (use drafts)

### 3. File Organization

```
css/custom/
├── theme.css           # Brand colors, fonts, variables
├── layout.css          # Grid, containers, spacing
├── components.css      # Buttons, cards, forms
├── navigation.css      # Headers, menus, breadcrumbs
├── utilities.css       # Helper classes
└── responsive.css      # Media queries
```

### 4. Component Naming

**Good:**
- `primary-button` - Clear and descriptive
- `alert-success` - Indicates type
- `card-product` - Shows context

**Bad:**
- `btn1` - Not descriptive
- `thing` - Too vague
- `new-component` - Not specific

---

## 🚨 Troubleshooting

### "Can't edit this file"
- **Cause:** File is in `core/` or `dependencies/`
- **Fix:** Only `custom/` files are editable

### "Version not found"
- **Cause:** Version was auto-deleted (max limit reached)
- **Fix:** Increase `max_versions` in config

### "Rollback failed"
- **Cause:** Permission issue or file locked
- **Fix:** Check file permissions (755 for dirs, 644 for files)

### "Component preview not showing"
- **Cause:** HTML contains errors or missing CSS
- **Fix:** Validate HTML, ensure CSS is included

---

## 🎯 Next Steps

1. **Initial Setup:**
   - Organize existing CSS into core/dependencies/custom
   - Create first version of each file
   - Set up component library with common elements

2. **Daily Workflow:**
   - Edit custom CSS files
   - Save versions with clear messages
   - Use components for consistent design

3. **Advanced Usage:**
   - Create diff reports before production
   - Build component documentation
   - Integrate with CI/CD pipeline (coming soon)

---

## 📚 Related Documentation

- [Theme Builder PRO Ultimate Guide](THEME_BUILDER_PRO_ULTIMATE_v4.md)
- [AI Agent Configuration](AI_AGENT_CONFIG_GUIDE.md)
- [CIS Brand Guidelines](cis-brand.css)

---

## 💡 Pro Tips

1. **Pin important versions** (coming soon) - Mark stable versions as "production"
2. **Use tags** for organization - "pre-launch", "stable", "experimental"
3. **Document breaking changes** in commit messages
4. **Keep core lean** - Only absolute essentials
5. **Component first** - Build components before full pages

---

**Version:** 1.0.0
**Last Updated:** October 31, 2024
**System:** CSS & Theme Version Control
**Status:** Production Ready ✅
