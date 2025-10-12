# File Relationship Map - Modules System

**Auto-Generated:** October 12, 2025  
**Purpose:** Show AI exactly where every component lives and how they connect

---

## 🎯 Quick Lookup - "Where Is...?"

### **Template Components** (Real CIS UI)
```
/assets/template/html-header.php   → <head>, CSS, jQuery
/assets/template/header.php        → Top navbar (logo, user menu)
/assets/template/sidemenu.php      → Left sidebar (navigation)
/assets/template/html-footer.php   → Scripts (Bootstrap, CoreUI)
/assets/template/footer.php        → Copyright footer
```

### **Module Template** (Wrapper Only)
```
modules/base/views/layouts/master.php  → Includes above components
```

### **Global CSS/JS**
```
/assets/css/                       → CoreUI, Bootstrap, custom
/assets/js/                        → jQuery, Moment, CoreUI
```

### **Database**
```
/app.php                          → cis_pdo() factory
modules/consignments/lib/Db.php   → Wrapper: Db::pdo()
```

### **Sessions & Auth**
```
/app.php                          → Session start, user auth
modules/base/lib/Kernel.php       → Kernel::boot() (sessions, CSRF)
```

### **Error Handling**
```
modules/core/ErrorHandler.php     → Upgraded v2.0 (debug screens)
modules/base/lib/ErrorHandler.php → Basic handler
```

---

## 🔗 Module: Consignments

### **Entry Point**
```
modules/consignments/index.php
  ├── Loads: module_bootstrap.php
  ├── Loads: base/lib/ErrorHandler.php
  ├── Loads: base/lib/Kernel.php
  └── Dispatches: Router
```

### **Routing Flow**
```
index.php (defines routes)
  → Router::dispatch('/modules/consignments')
    → HomeController::index()
      → views/home/index.php
        → base/views/layouts/master.php
          → /assets/template/*.php
```

### **Controllers → Views**
```
HomeController::index()
  → views/home/index.php

PackController::index()
  → views/pack/full.php
    → components/pack/add_products_modal.php
    → components/pack/action_footer_pack.php
    → components/_container_open.php
    → components/_container_close.php

ReceiveController::index()
  → views/receive/full.php
    → components/receive/*.php

HubController::index()
  → views/hub/index.php
```

### **API Controllers → Logic**
```
PackApiController::addLine()
  → lib/Db.php → cis_pdo()
  → lib/Validation.php
  → Returns JSON

ReceiveApiController::submit()
  → lib/Queue.php (background jobs)
  → lib/Idempotency.php (duplicate protection)
  → Returns JSON
```

### **CSS/JS Bundles**
```
consignments/assets/css/transfer.css
  → Loaded by: master.php ($moduleCSS array)

consignments/js/pack/bundle.js
  → Built by: tools/build_js_bundles.php
  → Source: js/pack/*.js
  → Loaded by: views/pack/full.php
```

---

## 📊 Data Flow

### **Page Load**
```
URL: /modules/consignments/transfers/pack?transfer=123

1. index.php
   ├── Kernel::boot()
   │   ├── require /app.php
   │   ├── session_start()
   │   └── init DB timezone
   │
   ├── Router::dispatch()
   │   └── PackController::index()
   │       ├── $pdo = Db::pdo() → cis_pdo()
   │       ├── SELECT * FROM transfers WHERE id = 123
   │       └── return $this->view('views/pack/full.php', $data)
   │
   └── master.php
       ├── include /assets/template/html-header.php
       ├── include /assets/template/header.php
       ├── include /assets/template/sidemenu.php
       ├── echo $content (your view HTML)
       ├── include /assets/template/html-footer.php
       └── include /assets/template/footer.php
```

### **API Call**
```
POST /modules/consignments/transfers/api/pack/add-line

1. index.php
   ├── Router matches route
   └── PackApiController::addLine()
       ├── Security::checkCSRF()
       ├── Validation::required(['product_id', 'qty'])
       ├── $pdo = Db::pdo()
       ├── INSERT INTO transfer_items
       └── return json(['success' => true, 'item' => $row])
```

---

## 🧩 Component Hierarchy

### **Base Classes** (Inherited by All)
```
base/lib/Controller/BaseController.php
  ├── base/lib/Controller/PageController.php
  │   └── consignments/controllers/PackController.php
  │   └── consignments/controllers/ReceiveController.php
  │
  └── base/lib/Controller/ApiController.php
      └── consignments/controllers/Api/PackApiController.php
      └── consignments/controllers/Api/ReceiveApiController.php
```

### **Shared Utilities**
```
base/lib/Router.php          → All modules
base/lib/View.php            → Template rendering
base/lib/Validation.php      → Input validation
base/lib/Security.php        → CSRF, auth
base/lib/Helpers.php         → URL helpers
```

### **Module-Specific**
```
consignments/lib/Db.php          → Database wrapper
consignments/lib/Queue.php       → Background jobs
consignments/lib/Idempotency.php → Duplicate protection
consignments/lib/Log.php         → Logging
```

---

## 🔍 Dependency Chain

### **What Depends on What**
```
Controllers
  ├── Extend: base/lib/Controller/*
  ├── Use: base/lib/Router.php
  ├── Use: base/lib/View.php
  ├── Use: module/lib/*.php
  └── Load: /app.php (via Kernel)

Views
  ├── Use: master.php
  ├── Include: components/*.php
  └── Access: $data from controller

Components
  ├── Standalone partials
  └── No dependencies (pure HTML + inline PHP)

Templates
  ├── master.php includes /assets/template/*.php
  └── No reverse dependencies (only included)

External CIS
  ├── /app.php → cis_pdo(), session, auth
  ├── /assets/template/*.php → UI components
  └── /assets/css|js/ → Styles, scripts
```

---

## 🎯 "Edit Page" Resolution

### **User Says:** "Edit the pack page"

**AI Searches:**
1. `_copilot/SEARCH/index.json` → "pack page"
2. Finds: `consignments/views/pack/full.php`
3. Relationship: `PackController::index() → views/pack/full.php`

**AI Knows:**
- ✅ Controller: `consignments/controllers/PackController.php`
- ✅ View: `consignments/views/pack/full.php`
- ✅ Components: `consignments/components/pack/*.php`
- ✅ CSS: `consignments/assets/css/transfer.css`
- ✅ JS: `consignments/js/pack/bundle.js`
- ✅ Template: `base/views/layouts/master.php` (wrapper)
- ✅ Real UI: `/assets/template/*.php` (DON'T EDIT)

---

## 🎯 "Change Sidebar" Resolution

**User Says:** "Change the sidebar"

**AI Searches:**
1. `_copilot/SEARCH/index.json` → "sidebar"
2. Finds: `/assets/template/sidemenu.php`
3. Relationship: External CIS component

**AI Knows:**
- ✅ File: `/assets/template/sidemenu.php`
- ✅ Location: OUTSIDE modules (CIS core)
- ✅ Used by: `master.php` includes it
- ⚠️ Warning: External dependency (may need CIS team)

---

## 🎯 "Fix Routing" Resolution

**User Says:** "Fix routing for consignments"

**AI Searches:**
1. `_copilot/SEARCH/index.json` → "routing consignments"
2. Finds: `consignments/index.php`, `base/lib/Router.php`

**AI Knows:**
- ✅ Routes defined: `consignments/index.php` (lines 10-25)
- ✅ Router class: `base/lib/Router.php`
- ✅ Dispatch: `$router->dispatch('/modules/consignments')`
- ✅ Controllers: `consignments/controllers/*.php`

---

## 🗺️ Visual Map

```
┌─────────────────────────────────────────────────────────────┐
│                      EXTERNAL CIS SYSTEM                     │
│  /app.php, /assets/template/*, /assets/css/, /assets/js/   │
└────────────────────────┬────────────────────────────────────┘
                         │
                         │ (included by)
                         │
┌────────────────────────▼────────────────────────────────────┐
│                    MODULES SYSTEM (base/)                    │
│  Kernel, Router, ErrorHandler, View, Controllers, master.php│
└────────────────────────┬────────────────────────────────────┘
                         │
                         │ (extended/used by)
                         │
┌────────────────────────▼────────────────────────────────────┐
│              SPECIFIC MODULE (consignments/)                 │
│  index.php → Controllers → Views → Components                │
│  lib/ (Db, Queue, Log, Idempotency, Validation)            │
└─────────────────────────────────────────────────────────────┘
```

---

## 🔄 How This Stays Updated

**Automatic:** Every time you run `node .vscode/refresh-kb.js`

**What Updates:**
- `_copilot/SEARCH/index.json` (searchable file index)
- `_copilot/MODULES/*/` (per-module relationship docs)
- `_copilot/STATUS.md` (lint warnings)

**When to Refresh:**
- After adding new files
- After changing routes
- After refactoring controllers
- Before asking AI to edit something

---

## ✅ AI Confidence Checklist

When user says "edit X", AI must know:

- [x] Exact file path
- [x] What includes/uses this file
- [x] What this file includes/uses
- [x] Parent controller (if view)
- [x] Related components (if page)
- [x] External dependencies (if any)
- [x] Whether it's safe to edit (module vs CIS core)

---

**🎯 Result:** AI can now navigate the entire codebase with surgical precision.
