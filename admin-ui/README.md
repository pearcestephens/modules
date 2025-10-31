# CIS Admin UI Module

**Version:** 1.0.0  
**Purpose:** Comprehensive UI Component Library & Theme Management System  
**Location:** `/modules/admin-ui/`

---

## 📋 Overview

The Admin UI module is a complete UI component showcase and theme management system for CIS. It serves as:

1. **Reference Library** - Complete examples of all UI components for developers/bots
2. **Theme Builder** - Professional theme management with programmatic CSS generation
3. **AI Assistant** - Real-time conversational theme editing (with graceful degradation)
4. **Best Practices** - Follow established CIS patterns and standards

---

## 🚀 Quick Start

### Access Points

- **Component Showcase:** `https://staff.vapeshed.co.nz/modules/admin-ui/`
- **Theme Builder:** `https://staff.vapeshed.co.nz/modules/admin-ui/theme-builder.php`

### Installation

1. Module is already set up and configured
2. CSS files are auto-generated from configuration
3. No additional setup required

---

## 📁 Module Structure

```
/modules/admin-ui/
├── bootstrap.php                          # Module initialization (inherits from base)
├── index.php                              # Component showcase (main page)
├── theme-builder.php                      # Visual theme editor
│
├── config/
│   ├── theme-config.php                   # Theme configuration (colors, fonts, spacing)
│   └── theme-changelog.json               # Version history (auto-generated)
│
├── lib/
│   ├── ThemeGenerator.php                 # CSS generation engine
│   └── AIThemeAssistant.php               # AI integration layer
│
├── _templates/
│   ├── components/                        # UI components
│   │   ├── header-v2.php                  # Two-tier header
│   │   ├── sidebar.php                    # Fixed sidebar
│   │   ├── chat-bar.php                   # Facebook-style chat
│   │   └── footer.php                     # Footer
│   │
│   ├── layouts/                           # Page layouts (future)
│   │
│   └── css/
│       ├── theme-generated.css            # Auto-generated foundation CSS
│       └── theme-custom.css               # Manual overrides
│
└── views/                                 # Page views (future expansion)
```

---

## 🎨 Theme System

### How It Works

1. **Configuration** (`config/theme-config.php`)
   - Central PHP array with all theme settings
   - Colors, fonts, spacing, shadows, etc.
   - Version tracked with timestamps

2. **Generation** (`lib/ThemeGenerator.php`)
   - Reads configuration
   - Generates CSS variables (`:root`)
   - Creates foundation styles
   - Writes to `theme-generated.css`
   - Auto-increments version

3. **Customization** (`theme-custom.css`)
   - Separate file for manual overrides
   - Never gets overwritten by generator
   - Load order: generated → custom

### CSS Variables

All styles use CSS variables for easy theming:

```css
:root {
    --cis-primary: #8B5CF6;
    --cis-primary-light: #A78BFA;
    --cis-primary-dark: #7C3AED;
    --cis-success: #10b981;
    --cis-warning: #f59e0b;
    --cis-danger: #ef4444;
    --cis-info: #3b82f6;
    --cis-sidebar-width: 260px;
    --cis-header-height: 60px;
    /* ... and more */
}
```

### Programmatic Updates

```php
require_once 'lib/ThemeGenerator.php';

$generator = new ThemeGenerator();

// Update colors
$generator->updateConfig([
    'primary' => [
        'main' => '#3b82f6', // New blue
    ],
    'success' => '#22c55e',
]);

// CSS is automatically regenerated
// Version is automatically incremented
// Change is logged to changelog
```

---

## 🤖 AI Theme Assistant

### Features

- **Natural Language Editing** - "make primary blue", "change sidebar to dark"
- **Real-time Updates** - See changes as you talk
- **Graceful Degradation** - Hides completely if AI endpoint unavailable
- **Local Fallback** - Pattern matching for basic color requests

### Usage

1. Click floating AI button (bottom-right)
2. Type natural language requests:
   - "Make primary blue"
   - "Change sidebar background to dark gray"
   - "Use green for success color"
3. AI responds and updates preview in real-time
4. Click "Save & Generate" to apply changes

### If AI Unavailable

- AI button automatically hidden
- Manual color pickers still work
- Local pattern matching for simple requests
- Full functionality maintained

---

## 🧩 Component Showcase

The `index.php` page includes comprehensive examples of:

### Buttons
- Standard buttons (primary, secondary, success, warning, danger, info)
- Button sizes (lg, default, sm)
- Outline buttons
- Button groups
- Icon buttons
- Loading buttons with spinners

### Forms
- Text inputs (standard, with icons)
- Select dropdowns (single, multiple)
- Checkboxes & radio buttons
- Text areas
- Form validation (valid, invalid states)

### Tables
- Standard tables
- Striped & hover tables
- Responsive tables
- Tables with actions

### Cards
- Basic cards (with header, footer)
- Colored cards (all theme colors)
- Card groups

### Alerts & Badges
- Alert variants (all colors)
- Dismissible alerts
- Badges (standard, pill)

### Modals & Dropdowns
- Modal sizes (small, default, large)
- Dropdown menus
- Split button dropdowns

### Layout
- Grid system (Bootstrap columns)
- Spacing utilities (margin, padding)

---

## 📝 Usage Examples

### Using in Your Pages

```php
<?php
// Include admin-ui bootstrap
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/admin-ui/bootstrap.php';
?>
<!DOCTYPE html>
<html>
<head>
    <!-- Include theme CSS -->
    <link rel="stylesheet" href="<?= ADMIN_UI_TEMPLATES_PATH ?>/css/theme-generated.css">
    <link rel="stylesheet" href="<?= ADMIN_UI_TEMPLATES_PATH ?>/css/theme-custom.css">
</head>
<body>
    <!-- Include header -->
    <?php include ADMIN_UI_COMPONENTS_PATH . '/header-v2.php'; ?>
    
    <!-- Include sidebar -->
    <?php include ADMIN_UI_COMPONENTS_PATH . '/sidebar.php'; ?>
    
    <!-- Your content -->
    <div class="dashboard-main">
        <h1>Your Page</h1>
    </div>
    
    <!-- Include footer -->
    <?php include ADMIN_UI_COMPONENTS_PATH . '/footer.php'; ?>
</body>
</html>
```

### Updating Theme Colors

```php
// In your code
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/admin-ui/lib/ThemeGenerator.php';

$generator = new ThemeGenerator();

$generator->updateConfig([
    'primary' => [
        'main' => '#3b82f6',
        'light' => '#60a5fa',
        'dark' => '#2563eb',
    ]
]);

// Theme is now updated, versioned, and logged
```

### Accessing Changelog

```php
require_once $_SERVER['DOCUMENT_ROOT'] . '/modules/admin-ui/lib/ThemeGenerator.php';

$generator = new ThemeGenerator();
$changelog = $generator->getChangelog();

foreach ($changelog as $entry) {
    echo "Version {$entry['version']}: {$entry['description']}\n";
}
```

---

## 🔧 Configuration

### Theme Config Structure

```php
// config/theme-config.php
return [
    'version' => '1.0.0',
    'last_updated' => '2025-10-28 03:00:00',
    
    'primary' => [
        'main' => '#8B5CF6',
        'light' => '#A78BFA',
        'dark' => '#7C3AED',
        'contrast' => '#ffffff',
    ],
    
    'secondary' => [
        'main' => '#64748B',
        'light' => '#94A3B8',
        'dark' => '#475569',
    ],
    
    'success' => '#10b981',
    'warning' => '#f59e0b',
    'danger' => '#ef4444',
    'info' => '#3b82f6',
    
    'sidebar' => [
        'width' => '260px',
        'bg' => '#495057',
        'text' => '#ffffff',
        'hover' => '#6c757d',
    ],
    
    'header' => [
        'height' => '60px',
        'bg' => '#ffffff',
        'border' => '#e5e7eb',
    ],
    
    'fonts' => [
        'primary' => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif',
        'mono' => '"Courier New", Courier, monospace',
    ],
    
    'spacing' => [
        'unit' => '8px',
    ],
    
    'radius' => [
        'sm' => '6px',
        'md' => '12px',
        'lg' => '16px',
    ],
    
    'shadow' => [
        'sm' => '0 1px 2px rgba(0,0,0,0.05)',
        'md' => '0 4px 6px rgba(0,0,0,0.1)',
        'lg' => '0 10px 15px rgba(0,0,0,0.1)',
    ],
];
```

---

## 🔄 Version Control

### Semantic Versioning

The system uses semantic versioning (MAJOR.MINOR.PATCH):

- **MAJOR** - Breaking changes (manual increment)
- **MINOR** - New features (manual increment)
- **PATCH** - Bug fixes, updates (auto-increment on config changes)

### Changelog Format

```json
[
    {
        "timestamp": "2025-10-28 03:00:00",
        "action": "generate",
        "description": "Initial theme generation",
        "data": {
            "css_file": "_templates/css/theme-generated.css",
            "size_bytes": 2654
        },
        "version": "1.0.0"
    }
]
```

---

## 🧪 Testing

### Manual Testing Checklist

1. **Component Showcase**
   - [ ] Access index.php - all components visible
   - [ ] Tabs switch correctly
   - [ ] Buttons have hover effects
   - [ ] Forms validate properly
   - [ ] Tables are responsive
   - [ ] Modals open/close
   - [ ] Dropdowns work

2. **Theme Builder**
   - [ ] Access theme-builder.php
   - [ ] Color pickers sync with hex inputs
   - [ ] Live preview updates in real-time
   - [ ] Save & Generate creates new version
   - [ ] Changelog displays changes
   - [ ] AI button shows/hides based on availability
   - [ ] AI chat responds (if available)
   - [ ] Local fallback works (if AI unavailable)

3. **CSS Files**
   - [ ] theme-generated.css exists and loads
   - [ ] theme-custom.css exists
   - [ ] CSS variables are defined
   - [ ] Styles apply correctly

---

## 🐛 Troubleshooting

### CSS Not Loading

```bash
# Check files exist
ls -lah /modules/admin-ui/_templates/css/

# Regenerate if missing
cd /modules/admin-ui
php -r "require_once 'lib/ThemeGenerator.php'; \$g = new ThemeGenerator(); \$g->generatePrimaryTheme();"
```

### AI Button Not Showing

This is normal if:
- `/modules/base/api/ai-request.php` doesn't exist
- AI endpoint is unreachable
- System correctly degraded to manual-only mode

### Theme Changes Not Applying

1. Clear browser cache (Ctrl+Shift+R)
2. Check `theme-generated.css` timestamp
3. Verify config saved: `cat config/theme-config.php`
4. Check changelog: `cat config/theme-changelog.json`

### Permission Errors

```bash
# Fix permissions
sudo chown -R master_anjzctzjhr:www-data /modules/admin-ui
sudo chmod -R 775 /modules/admin-ui
```

---

## 📚 Best Practices

### When to Use Each File

- **theme-generated.css** - Never edit manually (auto-generated)
- **theme-custom.css** - Safe to edit, never overwritten
- **theme-config.php** - Edit via theme-builder.php or programmatically

### Making Theme Changes

**Preferred Method:**
1. Use theme-builder.php visual interface
2. Or update via ThemeGenerator class programmatically

**Manual Method (if needed):**
1. Edit `config/theme-config.php`
2. Run regeneration:
   ```bash
   php -r "require_once 'lib/ThemeGenerator.php'; (new ThemeGenerator())->generatePrimaryTheme();"
   ```

### Adding New Components

1. Add to `_templates/components/` directory
2. Document in index.php showcase
3. Include usage example
4. Update this README

---

## 🔮 Future Enhancements

### Planned Features

- [ ] Theme presets (light, dark, high-contrast)
- [ ] Export/import theme configs
- [ ] Color contrast checker (WCAG compliance)
- [ ] Theme preview thumbnails
- [ ] Undo/redo for theme changes
- [ ] Keyboard shortcuts in theme builder
- [ ] More AI natural language patterns
- [ ] Component templates generator
- [ ] Style guide PDF export

### Module Expansion

- [ ] Additional layout templates
- [ ] Dashboard widgets library
- [ ] Chart/graph components
- [ ] Advanced form builders
- [ ] Data table components
- [ ] File upload components

---

## 📞 Support

### Getting Help

1. Check this README first
2. Review component showcase for examples
3. Check changelog for recent changes
4. Test in theme-builder.php

### Common Questions

**Q: Can I edit theme-generated.css directly?**  
A: No, it gets overwritten. Use theme-custom.css instead.

**Q: How do I add a new color?**  
A: Edit theme-config.php, add to primary/secondary/etc., regenerate.

**Q: Why isn't the AI button showing?**  
A: AI endpoint unavailable - this is normal, use manual controls.

**Q: How do I revert theme changes?**  
A: Check changelog for previous config, manually restore.

---

## 📄 License

Part of CIS (Central Information System) for Ecigdis Ltd / The Vape Shed.  
Internal use only.

---

**Last Updated:** 2025-10-28  
**Maintained By:** CIS Development Team  
**Module Version:** 1.0.0
