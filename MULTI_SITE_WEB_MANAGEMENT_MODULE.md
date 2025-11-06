# 🌐 MULTI-SITE WEB MANAGEMENT MODULE - REQUIREMENTS

## 🎯 THE REAL GOAL

Manage **ALL company websites** from ONE central CIS admin panel:

1. **www.vapeshed.co.nz** - Main retail site
2. **www.ecigdis.co.nz** - Corporate/wholesale site
3. **www.vapingkiwi.co.nz** - Secondary brand site
4. **www.vapehq.co.nz** - Third brand site

---

## 🏗️ MULTI-SITE ARCHITECTURE

### **CORE CONCEPT: Site Selector**

Every page/asset/template/menu belongs to a SITE.

```php
// User selects site at top of Web Dev module
$current_site = $_SESSION['selected_site'] ?? 'vapeshed';

// All operations filtered by site
$pages = $pageManager->getPages(['site' => $current_site]);
```

---

## 📦 REQUIRED FEATURES (REVISED FOR MULTI-SITE)

### **1. SITE MANAGER** ⭐ NEW!
**Central hub to switch between sites**

**Features:**
- Site selector dropdown (visible on every page)
- Add new site
- Site settings:
  - Domain name
  - Site name/title
  - Logo/favicon
  - Primary colors
  - Timezone
  - Language
  - Contact info
  - Google Analytics ID
  - Social media links
- Site status (active/maintenance/offline)
- Clone site (duplicate entire site structure)
- Import/export site

**Database:**
```sql
CREATE TABLE cis_websites (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_key VARCHAR(50) UNIQUE NOT NULL COMMENT 'vapeshed, ecigdis, vapingkiwi',
    site_name VARCHAR(255) NOT NULL,
    domain VARCHAR(255) UNIQUE NOT NULL,
    logo_url VARCHAR(500),
    favicon_url VARCHAR(500),
    primary_color VARCHAR(7) DEFAULT '#007bff',
    secondary_color VARCHAR(7) DEFAULT '#6c757d',
    timezone VARCHAR(50) DEFAULT 'Pacific/Auckland',
    language VARCHAR(10) DEFAULT 'en_NZ',
    status ENUM('active', 'maintenance', 'offline') DEFAULT 'active',
    google_analytics_id VARCHAR(50),
    facebook_url VARCHAR(255),
    instagram_url VARCHAR(255),
    contact_email VARCHAR(255),
    contact_phone VARCHAR(50),
    settings JSON COMMENT 'Additional site-specific settings',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);
```

---

### **2. PAGE MANAGER** (Multi-site aware)
**Manage pages across all sites**

**Features:**
- Site filter at top
- Create page (select which site)
- Page list shows site badge
- Duplicate page to another site
- Bulk operations (publish 5 pages across sites)
- URL preview per site: `vapeshed.co.nz/about`

**Database:**
```sql
CREATE TABLE cis_web_pages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,  -- Which website this page belongs to
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    content LONGTEXT,
    template_id INT,
    status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
    seo_title VARCHAR(255),
    seo_description TEXT,
    seo_keywords VARCHAR(500),
    featured_image VARCHAR(500),
    page_type ENUM('standard', 'home', 'product', 'blog', 'landing') DEFAULT 'standard',
    parent_page_id INT COMMENT 'For hierarchical pages',
    sort_order INT DEFAULT 0,
    custom_css LONGTEXT,
    custom_js LONGTEXT,
    created_by INT,
    updated_by INT,
    published_at DATETIME,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES cis_websites(id) ON DELETE CASCADE,
    UNIQUE KEY unique_site_slug (site_id, slug),
    INDEX idx_site (site_id),
    INDEX idx_status (status),
    INDEX idx_slug (slug)
);
```

---

### **3. TEMPLATE LIBRARY** (Shared or per-site)
**Templates can be global or site-specific**

**Features:**
- Global templates (used across all sites)
- Site-specific templates (only for vapeshed.co.nz)
- Template preview per site
- Variables: `{{site_name}}`, `{{site_logo}}`, `{{contact_email}}`
- Template marketplace (pre-built landing pages)

**Database:**
```sql
CREATE TABLE cis_web_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    template_type ENUM('page', 'email', 'component', 'landing') DEFAULT 'page',
    html_content LONGTEXT,
    css_content LONGTEXT,
    js_content LONGTEXT,
    variables JSON COMMENT 'Available variables',
    preview_image VARCHAR(500),
    is_global TINYINT(1) DEFAULT 1 COMMENT '1=all sites, 0=specific site',
    site_id INT COMMENT 'NULL if global',
    category VARCHAR(100),
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES cis_websites(id) ON DELETE CASCADE
);
```

---

### **4. ASSET MANAGER** (Shared library with site folders)
**Organize assets by site**

**Features:**
- Folders: `/vapeshed/products/`, `/ecigdis/team/`, `/vapingkiwi/banners/`
- Shared assets folder (used across all sites)
- Tag assets by site
- Usage tracking (which sites use this image?)
- CDN integration
- Image optimization per site (vapeshed uses WebP, ecigdis uses PNG)

**Database:**
```sql
CREATE TABLE cis_web_assets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    filename VARCHAR(255) NOT NULL,
    original_filename VARCHAR(255),
    file_path VARCHAR(500) NOT NULL,
    file_type VARCHAR(50),
    file_size BIGINT,
    mime_type VARCHAR(100),
    width INT,
    height INT,
    alt_text VARCHAR(255),
    caption TEXT,
    folder VARCHAR(255) DEFAULT 'uncategorized',
    site_id INT COMMENT 'NULL = shared across all sites',
    tags JSON COMMENT 'Searchable tags',
    uploaded_by INT,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES cis_websites(id) ON DELETE SET NULL,
    INDEX idx_site (site_id),
    INDEX idx_folder (folder),
    INDEX idx_type (file_type)
);
```

---

### **5. NAVIGATION MANAGER** (Per-site menus)
**Each site has its own menus**

**Features:**
- Site selector: "Editing vapeshed.co.nz navigation"
- Multiple menus per site (header, footer, mobile)
- Mega menu support
- Conditional visibility (show only on homepage)
- Clone menu to another site
- Menu preview

**Database:**
```sql
CREATE TABLE cis_web_menus (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    menu_key VARCHAR(100) NOT NULL COMMENT 'header, footer, mobile',
    items JSON COMMENT 'Nested menu structure',
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES cis_websites(id) ON DELETE CASCADE,
    UNIQUE KEY unique_site_menu (site_id, menu_key),
    INDEX idx_site (site_id)
);
```

---

### **6. EMAIL TEMPLATES** (Global + per-site)
**Transactional emails branded per site**

**Features:**
- Global templates (order confirmation, password reset)
- Site-specific branding (vapeshed logo, colors)
- Variables: `{{site_name}}`, `{{site_url}}`, `{{customer_name}}`
- Test send to preview
- Integration with `vapeshedSendEmail()`

**Database:**
```sql
CREATE TABLE cis_web_email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(100) NOT NULL,
    name VARCHAR(255) NOT NULL,
    subject VARCHAR(255),
    html_content LONGTEXT,
    text_content LONGTEXT,
    variables JSON,
    site_id INT COMMENT 'NULL = global template',
    category VARCHAR(100),
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES cis_websites(id) ON DELETE CASCADE,
    UNIQUE KEY unique_site_template (site_id, template_key),
    INDEX idx_site (site_id)
);
```

---

### **7. FORM BUILDER** (Per-site forms)
**Contact forms, quote requests, etc.**

**Features:**
- Create form, assign to site
- Form submissions tagged by site
- Email notifications use site branding
- reCAPTCHA per site (different keys)
- Export submissions per site

**Database:**
```sql
CREATE TABLE cis_web_forms (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    form_key VARCHAR(100) NOT NULL,
    description TEXT,
    fields JSON,
    settings JSON,
    success_message TEXT,
    notification_emails VARCHAR(500),
    status ENUM('active', 'inactive') DEFAULT 'active',
    created_by INT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES cis_websites(id) ON DELETE CASCADE,
    UNIQUE KEY unique_site_form (site_id, form_key),
    INDEX idx_site (site_id)
);

CREATE TABLE cis_web_form_submissions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    form_id INT NOT NULL,
    site_id INT NOT NULL,
    data JSON,
    ip_address VARCHAR(45),
    user_agent TEXT,
    referrer VARCHAR(500),
    submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (form_id) REFERENCES cis_web_forms(id) ON DELETE CASCADE,
    FOREIGN KEY (site_id) REFERENCES cis_websites(id) ON DELETE CASCADE,
    INDEX idx_form (form_id),
    INDEX idx_site (site_id),
    INDEX idx_submitted (submitted_at)
);
```

---

### **8. BLOG/NEWS MANAGER** ⭐ NEW!
**Per-site blogs**

**Features:**
- vapeshed.co.nz/blog/...
- ecigdis.co.nz/news/...
- Categories per site
- Tags
- Featured posts
- Author management
- SEO per post
- RSS feeds per site

**Database:**
```sql
CREATE TABLE cis_web_blog_posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    title VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    excerpt TEXT,
    content LONGTEXT,
    featured_image VARCHAR(500),
    author_id INT,
    category_id INT,
    tags JSON,
    status ENUM('draft', 'published', 'scheduled') DEFAULT 'draft',
    published_at DATETIME,
    seo_title VARCHAR(255),
    seo_description TEXT,
    views INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES cis_websites(id) ON DELETE CASCADE,
    UNIQUE KEY unique_site_slug (site_id, slug),
    INDEX idx_site (site_id),
    INDEX idx_status (status),
    INDEX idx_published (published_at)
);

CREATE TABLE cis_web_blog_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    slug VARCHAR(255) NOT NULL,
    description TEXT,
    FOREIGN KEY (site_id) REFERENCES cis_websites(id) ON DELETE CASCADE,
    UNIQUE KEY unique_site_category (site_id, slug)
);
```

---

### **9. SEO MANAGER** (Per-site SEO)
**Optimize each site independently**

**Features:**
- Site-wide SEO settings (meta tags, schemas)
- Page-level SEO editor
- XML sitemap generator per site
- robots.txt manager per site
- 301 redirects per site
- Google Search Console integration
- SEO audit (missing meta tags, broken links)

**Database:**
```sql
CREATE TABLE cis_web_redirects (
    id INT AUTO_INCREMENT PRIMARY KEY,
    site_id INT NOT NULL,
    from_url VARCHAR(500) NOT NULL,
    to_url VARCHAR(500) NOT NULL,
    redirect_type ENUM('301', '302', '307') DEFAULT '301',
    is_active TINYINT(1) DEFAULT 1,
    hit_count INT DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (site_id) REFERENCES cis_websites(id) ON DELETE CASCADE,
    UNIQUE KEY unique_site_redirect (site_id, from_url),
    INDEX idx_site (site_id),
    INDEX idx_from (from_url)
);
```

---

### **10. ANALYTICS DASHBOARD** ⭐ NEW!
**View stats per site**

**Features:**
- Site selector: "Viewing vapeshed.co.nz analytics"
- Page views per site
- Top pages per site
- Form submissions per site
- User behavior (heatmaps, session recordings)
- Conversion tracking
- Compare sites (vapeshed vs ecigdis traffic)
- Google Analytics integration

---

## 🎨 UI/UX FLOW

### **Main Dashboard**
```
┌──────────────────────────────────────────────────────────┐
│  🌐 Web Management    [🔽 Site: Vape Shed ▼]   + New    │
├──────────────────────────────────────────────────────────┤
│                                                          │
│  📊 ANALYTICS (Last 30 days)                            │
│  ┌──────────┬──────────┬──────────┬──────────┐         │
│  │ Vape Shed│ Ecigdis  │VapingKiwi│ VapeHQ   │         │
│  │ 45.2K    │ 8.3K     │ 12.1K    │ 6.8K     │         │
│  │ visitors │ visitors │ visitors │ visitors │         │
│  └──────────┴──────────┴──────────┴──────────┘         │
│                                                          │
│  📄 RECENT PAGES                                        │
│  • Homepage Update          Vape Shed    Published      │
│  • About Us                 Ecigdis      Draft          │
│  • New Products             VapingKiwi   Scheduled      │
│                                                          │
│  📝 RECENT FORM SUBMISSIONS                             │
│  • Contact Form            Vape Shed     2 mins ago     │
│  • Quote Request           Ecigdis       15 mins ago    │
└──────────────────────────────────────────────────────────┘
```

### **Site Selector** (Always visible)
```
Currently editing: [🔽 Vape Shed (vapeshed.co.nz) ▼]
                    ├─ Vape Shed (vapeshed.co.nz)
                    ├─ Ecigdis (ecigdis.co.nz)
                    ├─ Vaping Kiwi (vapingkiwi.co.nz)
                    └─ VapeHQ (vapehq.co.nz)
```

---

## 📁 MODULE STRUCTURE (REVISED)

```
/modules/web-management/
├── bootstrap.php
├── index.php
├── README.md
│
├── lib/
│   ├── SiteManager.php          ⭐ NEW - Manage websites
│   ├── PageManager.php           (Multi-site aware)
│   ├── TemplateManager.php       (Global + per-site)
│   ├── AssetManager.php          (Shared + per-site)
│   ├── NavigationManager.php     (Per-site menus)
│   ├── EmailTemplateManager.php  (Per-site branding)
│   ├── FormManager.php           (Per-site forms)
│   ├── BlogManager.php           ⭐ NEW
│   ├── SEOManager.php            ⭐ NEW
│   └── AnalyticsManager.php      ⭐ NEW
│
├── views/
│   ├── dashboard.php             (Show all sites overview)
│   ├── sites.php                 ⭐ NEW - Site manager
│   ├── pages.php                 (Filtered by site)
│   ├── templates.php             (Global + per-site)
│   ├── assets.php                (Shared library + site folders)
│   ├── navigation.php            (Per-site)
│   ├── emails.php                (Per-site)
│   ├── forms.php                 (Per-site)
│   ├── blog.php                  ⭐ NEW
│   ├── seo.php                   ⭐ NEW
│   └── analytics.php             ⭐ NEW
│
├── api/
│   ├── sites.php                 ⭐ NEW
│   ├── pages.php
│   ├── assets.php
│   ├── forms.php
│   ├── blog.php                  ⭐ NEW
│   └── analytics.php             ⭐ NEW
│
└── assets/
    ├── css/
    ├── js/
    └── images/
```

---

## 🚀 DEVELOPMENT PRIORITIES

### **Phase 1: Foundation** (Week 1)
1. ✅ **Site Manager** - Add/edit/switch sites
2. ✅ **Multi-site Pages** - Create pages per site
3. ✅ **Asset Manager** - Shared + per-site folders

### **Phase 2: Content** (Week 2)
4. ✅ **Navigation Manager** - Per-site menus
5. ✅ **Template Library** - Global + per-site
6. ✅ **Email Templates** - Per-site branding

### **Phase 3: Advanced** (Week 3)
7. ✅ **Form Builder** - Per-site forms
8. ✅ **Blog Manager** - Per-site blogs
9. ✅ **SEO Manager** - Redirects, sitemaps

### **Phase 4: Analytics** (Week 4)
10. ✅ **Analytics Dashboard** - Per-site stats
11. ✅ **A/B Testing** - Test variations per site
12. ✅ **Heatmaps** - User behavior tracking

---

## 🎯 EXPECTED OUTCOME

**Manage all 4+ websites from ONE CIS admin panel:**

- Switch between sites with dropdown
- Create pages for any site
- Share assets across sites or keep site-specific
- Customize navigation per site
- Brand emails per site
- Track analytics per site
- SEO optimize each site independently
- Publish blog posts per site
- Manage forms per site

**ONE INTERFACE TO RULE THEM ALL!** 🚀

Ready to build this?
