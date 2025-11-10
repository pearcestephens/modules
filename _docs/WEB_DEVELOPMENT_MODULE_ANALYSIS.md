# 🌐 WEB DEVELOPMENT MODULE - REQUIREMENTS ANALYSIS

## 📊 CURRENT CIS FRONTEND INFRASTRUCTURE

Based on analysis of the codebase, here's what exists:

### **1. TEMPLATE SYSTEM** (`/modules/base/`)
- ✅ **Base layouts** (dashboard, table, card, blank, split)
- ✅ **Components** (header, sidebar, footer, breadcrumbs)
- ✅ **Theme system** (cis-classic theme)
- ✅ **Helpers** (layout-helper.php, menu-helper.php)
- ✅ **Bootstrap 5 + CoreUI framework**

### **2. GLOBAL ASSETS** (`/assets/`)
- ✅ **Template files** (html-header.php, html-footer.php, sidemenu.php, header.php, footer.php)
- ✅ **Functions** (vapeshed-website.php - email queue, utilities)
- ✅ **CSS/JS libraries** (jQuery, Bootstrap, CoreUI, DataTables)
- ✅ **Quick product search** component
- ✅ **Personalisation menu** component

### **3. MODULE-SPECIFIC TEMPLATES**
- ✅ **Consignments** - base-layout.php (shared template)
- ✅ **Payroll** - Custom layouts (being refactored to base)
- ✅ **Admin UI** - Template showcase & theme builder
- ✅ **Staff Performance** - Uses CIS base templates
- ✅ **Control Panel** - Uses Bootstrap 5

### **4. KEY FUNCTIONS IN `vapeshed-website.php`**
Based on references found:
- ✅ **`vapeshedSendEmail()`** - Queue emails for sending
- ✅ **Email queue system** - Background email processing
- ✅ **Template rendering** - HTML email generation
- ⚠️ **Various utility functions** (needs documentation)

---

## 🎯 WEB DEVELOPMENT MODULE - REQUIRED FEATURES

### **CORE FUNCTIONALITY**

#### **1. PAGE BUILDER / CMS**
- Visual page editor (drag-and-drop components)
- Page templates library (landing pages, forms, dashboards)
- Content blocks (text, images, videos, CTAs)
- SEO meta tags editor
- URL/slug management
- Page versioning & history
- Publish/draft status
- Preview before publish

#### **2. TEMPLATE MANAGER**
- View all base templates
- Create custom templates
- Edit template variables
- Template preview
- Component library browser
- CSS/JS asset manager
- Template duplication
- Version control for templates

#### **3. ASSET MANAGER**
- Upload images/files
- Image optimization (resize, compress)
- CDN integration
- Asset organization (folders)
- Search & filter assets
- Usage tracking (where is this image used?)
- Bulk upload/delete
- Image editor (crop, rotate, filters)

#### **4. FRONTEND CODE EDITOR**
- Live CSS editor with preview
- JavaScript editor
- HTML snippet manager
- Code syntax highlighting
- Auto-complete
- Error checking/linting
- Minification on save
- Version history

#### **5. COMPONENT LIBRARY**
- Browse all UI components
- Component documentation
- Usage examples
- Live preview
- Copy code snippets
- Search components
- Component variations
- Bootstrap utilities reference

#### **6. EMAIL TEMPLATE BUILDER**
- Visual email editor
- Responsive email templates
- Dynamic variable insertion
- Preview across devices
- Test send functionality
- Template library
- HTML/text versions
- Integration with `vapeshedSendEmail()`

#### **7. FORM BUILDER**
- Drag-and-drop form creator
- Field types (text, email, select, checkbox, file upload)
- Validation rules
- Conditional logic
- Multi-step forms
- Form submissions viewer
- Export submissions to CSV
- Email notifications on submit
- reCAPTCHA integration

#### **8. NAVIGATION MANAGER**
- Visual menu builder
- Multi-level menus
- Drag-and-drop ordering
- Menu permissions (role-based visibility)
- Icons for menu items
- Badge/notification support
- Active state highlighting
- Mobile menu configuration

#### **9. WIDGET SYSTEM**
- Create custom widgets
- Widget placement zones
- Configuration per widget
- Active/inactive toggle
- Widget permissions
- Drag-and-drop widget ordering
- Pre-built widget library

#### **10. STYLE CUSTOMIZER**
- Live theme editor
- Color palette manager
- Typography settings
- Spacing/layout controls
- Border/shadow controls
- Button styles
- Export custom theme
- Dark mode toggle

---

## 📦 WEB DEVELOPMENT MODULE STRUCTURE

```
/modules/web-development/
├── bootstrap.php                  # Module initialization
├── index.php                      # Router
├── README.md                      # Documentation
│
├── lib/                           # Service classes
│   ├── PageBuilder.php            # Page creation/management
│   ├── TemplateManager.php        # Template CRUD
│   ├── AssetManager.php           # File upload/management
│   ├── ComponentLibrary.php       # Component registry
│   ├── EmailTemplateBuilder.php   # Email templates
│   ├── FormBuilder.php            # Form creation
│   ├── NavigationManager.php      # Menu management
│   ├── WidgetManager.php          # Widget system
│   └── ThemeCustomizer.php        # Style editor
│
├── views/                         # UI pages
│   ├── dashboard.php              # Overview
│   ├── pages.php                  # Page manager
│   ├── templates.php              # Template browser
│   ├── assets.php                 # Asset library
│   ├── components.php             # Component library
│   ├── emails.php                 # Email templates
│   ├── forms.php                  # Form builder
│   ├── navigation.php             # Menu editor
│   ├── widgets.php                # Widget manager
│   ├── styles.php                 # Theme customizer
│   └── code-editor.php            # Live code editor
│
├── api/                           # JSON endpoints
│   ├── pages.php                  # Page CRUD
│   ├── templates.php              # Template operations
│   ├── assets.php                 # Upload/delete assets
│   ├── components.php             # Component data
│   ├── forms.php                  # Form submissions
│   └── preview.php                # Live preview
│
├── assets/                        # Module assets
│   ├── css/
│   │   ├── page-builder.css       # Visual editor styles
│   │   ├── template-manager.css
│   │   └── asset-library.css
│   ├── js/
│   │   ├── page-builder.js        # Drag-and-drop editor
│   │   ├── code-editor.js         # CodeMirror/Monaco integration
│   │   ├── form-builder.js        # Form editor
│   │   └── live-preview.js        # Real-time preview
│   └── images/
│
├── uploads/                       # User-uploaded assets
│   ├── images/
│   ├── documents/
│   └── temp/
│
└── templates/                     # Pre-built templates
    ├── landing-pages/
    ├── dashboards/
    └── forms/
```

---

## 🗄️ DATABASE TABLES NEEDED

```sql
-- Pages/content management
CREATE TABLE cis_web_pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) UNIQUE NOT NULL,
    content LONGTEXT,
    template_id INT,
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    seo_title VARCHAR(255),
    seo_description TEXT,
    seo_keywords VARCHAR(500),
    featured_image VARCHAR(500),
    created_by INT,
    updated_by INT,
    published_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_slug (slug),
    INDEX idx_status (status)
);

-- Custom templates
CREATE TABLE cis_web_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    template_type ENUM('page', 'email', 'component') DEFAULT 'page',
    html_content LONGTEXT,
    css_content LONGTEXT,
    js_content LONGTEXT,
    variables JSON COMMENT 'Template variables and defaults',
    preview_image VARCHAR(500),
    is_system TINYINT(1) DEFAULT 0,
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Uploaded assets
CREATE TABLE cis_web_assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255),
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(50),
    file_size BIGINT,
    mime_type VARCHAR(100),
    alt_text VARCHAR(255),
    caption TEXT,
    folder VARCHAR(255) DEFAULT 'uncategorized',
    uploaded_by INT,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_folder (folder),
    INDEX idx_type (file_type)
);

-- Email templates
CREATE TABLE cis_web_email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(100) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    subject VARCHAR(255),
    html_content LONGTEXT,
    text_content LONGTEXT,
    variables JSON COMMENT 'Available variables',
    category VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Form builder
CREATE TABLE cis_web_forms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    fields JSON COMMENT 'Form fields configuration',
    settings JSON COMMENT 'Validation, notifications, etc.',
    submit_button_text VARCHAR(100) DEFAULT 'Submit',
    success_message TEXT,
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Form submissions
CREATE TABLE cis_web_form_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_id INT NOT NULL,
    data JSON COMMENT 'Form field values',
    ip_address VARCHAR(45),
    user_agent TEXT,
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (form_id) REFERENCES cis_web_forms(id) ON DELETE CASCADE,
    INDEX idx_form_id (form_id),
    INDEX idx_submitted_at (submitted_at)
);

-- Navigation menus
CREATE TABLE cis_web_menus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    location VARCHAR(100) COMMENT 'header, footer, sidebar',
    items JSON COMMENT 'Menu structure',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Widgets
CREATE TABLE cis_web_widgets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    widget_type VARCHAR(100),
    zone VARCHAR(100) COMMENT 'sidebar, footer, header',
    config JSON COMMENT 'Widget configuration',
    sort_order INT DEFAULT 0,
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_zone (zone),
    INDEX idx_sort (sort_order)
);

-- Theme settings
CREATE TABLE cis_web_theme_settings (
    id INT AUTO_INCREMENT PRIMARY KEY,
    setting_key VARCHAR(100) UNIQUE NOT NULL,
    setting_value LONGTEXT,
    setting_type ENUM('color', 'font', 'spacing', 'custom') DEFAULT 'custom',
    category VARCHAR(100),
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

---

## 🔌 INTEGRATIONS NEEDED

### **1. With `vapeshed-website.php`**
- Use existing `vapeshedSendEmail()` function
- Integrate email template builder with queue system
- Document all available functions

### **2. With Base Template System**
- Read existing templates from `/modules/base/_templates/`
- Allow creating new templates using base components
- Preview with actual header/sidebar/footer

### **3. With Module System**
- Each module can register its own pages/routes
- Module-specific components in library
- Module assets managed centrally

### **4. With Control Panel**
- Link from Control Panel dashboard
- Use same admin authentication
- Shared navigation

---

## 🎨 UI/UX FEATURES

- **Visual Page Builder** - GrapeJS or similar
- **Code Editor** - Monaco Editor (VS Code engine) or CodeMirror
- **Image Editor** - Cropper.js for basic editing
- **Color Picker** - Advanced color palette manager
- **Icon Picker** - FontAwesome 6.7.1 + custom icons
- **Live Preview** - Real-time changes without reload
- **Responsive Preview** - Mobile/tablet/desktop views
- **Undo/Redo** - Action history

---

## ⭐ PRIORITY FEATURES (MVP)

### **Phase 1: Foundation** (Week 1)
1. ✅ Template Manager (view/edit existing templates)
2. ✅ Asset Manager (upload/organize images)
3. ✅ Component Library (browse available components)

### **Phase 2: Content** (Week 2)
4. ✅ Page Builder (basic WYSIWYG editor)
5. ✅ Navigation Manager (menu builder)
6. ✅ Email Template Builder

### **Phase 3: Advanced** (Week 3-4)
7. ✅ Form Builder
8. ✅ Widget System
9. ✅ Code Editor (live CSS/JS editing)
10. ✅ Theme Customizer

---

## 🚀 EXPECTED OUTCOMES

After building this module, developers/admins can:

1. **Create new pages** without writing code
2. **Manage templates** visually
3. **Upload and organize assets** (images, docs)
4. **Build forms** with drag-and-drop
5. **Customize emails** with variables
6. **Edit menus** without touching PHP
7. **Create widgets** for sidebars/footers
8. **Customize theme** colors/fonts live
9. **Preview changes** before publishing
10. **Document all components** in one place

---

**This module will be the central hub for all frontend development in CIS!** 🎨✨

Want me to start building it?
