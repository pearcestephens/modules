# CORE Module - Architecture & Design Patterns

**Version:** 2.0.0
**Last Updated:** November 12, 2025
**Status:** In Development

---

## Purpose

The CORE module provides the foundational user management, authentication, and personal settings system for the CIS platform. It leverages the BASE module for shared infrastructure while maintaining clean separation of concerns.

---

## Design Principles

### 1. **Leverage BASE, Don't Duplicate**
- Use `modules/base/` for all shared infrastructure
- Authentication → `base/src/Http/Middleware/Authenticate.php`
- Session → `base/src/Core/Session.php`
- Templates → `base/templates/vape-ultra/`
- Database → `base/src/Database/`
- Services → `base/src/Services/`

### 2. **Eliminate SHARED Redundancy**
- `modules/shared/` is **DEPRECATED**
- All shared functionality belongs in `base/`
- Migrate useful shared code to appropriate locations
- Delete redundant copies

### 3. **Clean MVC Architecture**
```
core/
├── controllers/     → Business logic only
├── models/          → Database entities
├── views/           → Presentation only
├── middleware/      → Request filtering
├── routes/          → URL routing
└── public/          → Entry points
```

### 4. **Single Responsibility**
- Each file has ONE clear purpose
- Controllers handle HTTP requests/responses
- Models handle data persistence
- Views handle presentation
- Services handle business logic

### 5. **DRY (Don't Repeat Yourself)**
- Shared code → `base/src/Support/`
- Shared views → `base/templates/`
- Shared assets → `base/assets/`
- Module-specific → `core/`

---

## Directory Structure

```
modules/core/
├── public/
│   ├── index.php              → Main entry point (dashboard/home)
│   ├── login.php              → Login page entry
│   ├── logout.php             → Logout handler
│   ├── register.php           → Registration page (if enabled)
│   └── assets/                → Module-specific assets (if any)
│       ├── css/
│       ├── js/
│       └── img/
│
├── controllers/
│   ├── AuthController.php     → Login, logout, register
│   ├── DashboardController.php → Home/dashboard
│   ├── ProfileController.php  → User profile + chat features
│   ├── SettingsController.php → Settings + chat settings
│   ├── PreferencesController.php → Preferences + chat prefs
│   └── SecurityController.php → Security + blocking/reporting
│
├── models/
│   ├── User.php               → User entity
│   ├── UserSetting.php        → User settings entity
│   ├── UserPreference.php     → User preferences entity
│   ├── UserBlock.php          → Blocked users entity
│   ├── UserReport.php         → Reported users entity
│   └── TrustedDevice.php      → Trusted devices entity
│
├── middleware/
│   ├── AuthMiddleware.php     → Check authentication
│   ├── GuestMiddleware.php    → Redirect if authenticated
│   └── AdminMiddleware.php    → Check admin role
│
├── views/
│   ├── auth/
│   │   ├── login.php          → Login form
│   │   ├── register.php       → Registration form
│   │   └── forgot-password.php → Password reset
│   ├── dashboard/
│   │   └── index.php          → Dashboard/home page
│   ├── profile.php            → Profile with chat features ✅
│   ├── settings.php           → Settings with chat tab ✅
│   ├── preferences.php        → Preferences with chat ✅
│   └── security.php           → Security with blocking ✅
│
├── routes/
│   └── web.php                → Route definitions
│
├── database/
│   └── migrations/
│       ├── 001_create_users_table.sql
│       ├── 002_create_user_settings_table.sql
│       ├── 003_create_user_preferences_table.sql
│       ├── 004_create_user_blocks_table.sql
│       ├── 005_create_user_reports_table.sql
│       └── 006_create_trusted_devices_table.sql
│
├── config.php                 → Module configuration ✅
├── bootstrap.php              → Module initialization
└── README.md                  → Module documentation
```

---

## Dependency Map

### CORE Depends On:
```
base/
├── src/Core/
│   ├── Application.php        → App container
│   ├── Session.php            → Session management
│   └── Router.php             → Routing
├── src/Database/
│   ├── Connection.php         → Database connection
│   └── QueryBuilder.php       → Query builder
├── src/Http/
│   ├── Request.php            → HTTP request
│   ├── Response.php           → HTTP response
│   └── Middleware/
│       └── Authenticate.php   → Auth middleware
├── src/Security/
│   ├── Csrf.php               → CSRF protection
│   └── Hash.php               → Password hashing
├── src/View/
│   └── View.php               → Template engine
└── templates/vape-ultra/      → UI framework
```

### SHARED (DEPRECATED) Migration:
```
shared/ → base/                Action
├── api/                  →    Merge to base/src/Http/
├── functions/            →    Merge to base/src/Support/
├── services/             →    Keep specific, move generic to base
├── blocks/               →    Merge to base/templates/components/
└── lib/                  →    Merge to base/src/Support/
```

---

## Authentication Flow

### Login Process
```
1. User visits /core/public/login.php
2. AuthController@showLogin() renders view
3. User submits credentials
4. AuthController@login() validates
5. Session created via base/src/Core/Session.php
6. Redirect to /core/public/index.php (dashboard)
```

### Authentication Check
```
1. User requests protected page
2. Middleware checks session
3. If authenticated → continue
4. If not → redirect to login
```

### Logout Process
```
1. User clicks logout
2. AuthController@logout() destroys session
3. Redirect to login page
```

---

## Database Schema

### Users Table
```sql
CREATE TABLE users (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    username VARCHAR(20) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    first_name VARCHAR(100),
    last_name VARCHAR(100),
    display_name VARCHAR(255),
    avatar_url VARCHAR(255),
    status_message VARCHAR(100),
    availability_status ENUM('online', 'away', 'offline', 'do_not_disturb') DEFAULT 'online',
    bio TEXT,
    phone VARCHAR(20),
    street_address VARCHAR(255),
    city VARCHAR(100),
    state VARCHAR(100),
    postal_code VARCHAR(20),
    country VARCHAR(100),
    twitter_url VARCHAR(255),
    linkedin_url VARCHAR(255),
    website_url VARCHAR(255),
    bio_visibility ENUM('public', 'contacts', 'private') DEFAULT 'public',
    last_seen_privacy ENUM('everyone', 'contacts', 'private') DEFAULT 'everyone',
    avatar_visibility ENUM('public', 'contacts', 'private') DEFAULT 'public',
    profile_visibility ENUM('public', 'contacts', 'private') DEFAULT 'public',
    email_verified_at TIMESTAMP NULL,
    last_login_at TIMESTAMP NULL,
    last_seen_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    deleted_at TIMESTAMP NULL,
    INDEX idx_username (username),
    INDEX idx_email (email),
    INDEX idx_availability (availability_status),
    INDEX idx_last_seen (last_seen_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

### User Settings Table
```sql
CREATE TABLE user_settings (
    id BIGINT PRIMARY KEY AUTO_INCREMENT,
    user_id BIGINT NOT NULL,
    theme VARCHAR(50) DEFAULT 'vape-ultra',
    language VARCHAR(10) DEFAULT 'en',
    timezone VARCHAR(50) DEFAULT 'Pacific/Auckland',
    dashboard_layout VARCHAR(50) DEFAULT 'default',
    items_per_page INT DEFAULT 25,
    show_online_status BOOLEAN DEFAULT TRUE,
    allow_direct_messages BOOLEAN DEFAULT TRUE,
    delivery_indicators BOOLEAN DEFAULT TRUE,
    read_receipts ENUM('send_and_receive', 'receive_only', 'hide_all') DEFAULT 'send_and_receive',
    typing_indicators BOOLEAN DEFAULT TRUE,
    online_visibility ENUM('everyone', 'contacts', 'hidden') DEFAULT 'everyone',
    audio_calls ENUM('enabled', 'contacts', 'disabled') DEFAULT 'enabled',
    video_calls ENUM('enabled', 'contacts', 'disabled') DEFAULT 'enabled',
    screen_sharing ENUM('enabled', 'contacts', 'disabled') DEFAULT 'enabled',
    message_sound VARCHAR(50) DEFAULT 'default',
    call_sound VARCHAR(50) DEFAULT 'default',
    vibration_enabled BOOLEAN DEFAULT TRUE,
    dnd_enabled BOOLEAN DEFAULT FALSE,
    dnd_start TIME DEFAULT '22:00:00',
    dnd_end TIME DEFAULT '08:00:00',
    dnd_allow_favorites BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_settings (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

---

## Routing Strategy

### Route Definition (`routes/web.php`)
```php
<?php
return [
    // Public routes (guest only)
    'GET /login' => 'AuthController@showLogin',
    'POST /login' => 'AuthController@login',
    'GET /register' => 'AuthController@showRegister',
    'POST /register' => 'AuthController@register',
    'GET /forgot-password' => 'AuthController@showForgotPassword',
    'POST /forgot-password' => 'AuthController@forgotPassword',

    // Protected routes (authenticated)
    'GET /' => 'DashboardController@index',
    'GET /dashboard' => 'DashboardController@index',
    'GET /logout' => 'AuthController@logout',

    // Profile
    'GET /profile' => 'ProfileController@show',
    'POST /profile' => 'ProfileController@update',
    'POST /profile/avatar' => 'ProfileController@uploadAvatar',
    'POST /profile/availability' => 'ProfileController@updateAvailability',
    'POST /profile/status' => 'ProfileController@updateStatusMessage',
    'POST /profile/block' => 'ProfileController@blockUser',
    'POST /profile/unblock' => 'ProfileController@unblockUser',
    'POST /profile/report' => 'ProfileController@reportUser',

    // Settings
    'GET /settings' => 'SettingsController@show',
    'POST /settings/general' => 'SettingsController@updateGeneral',
    'POST /settings/chat' => 'SettingsController@updateChat',
    'POST /settings/password' => 'SettingsController@changePassword',

    // Preferences
    'GET /preferences' => 'PreferencesController@show',
    'POST /preferences' => 'PreferencesController@update',

    // Security
    'GET /security' => 'SecurityController@show',
    'POST /security/2fa' => 'SecurityController@toggle2FA',
    'POST /security/sessions' => 'SecurityController@revokeSessions',
];
```

---

## File Naming Conventions

### Controllers
- **Format:** `{Entity}Controller.php`
- **Examples:** `AuthController.php`, `ProfileController.php`
- **Class:** `class AuthController extends BaseController`

### Models
- **Format:** `{Entity}.php`
- **Examples:** `User.php`, `UserSetting.php`
- **Class:** `class User extends Model`

### Views
- **Format:** `{section}/{page}.php` or `{page}.php`
- **Examples:** `auth/login.php`, `profile.php`
- **Variables:** Use `$variable` not `$_GET/$_POST`

### Middleware
- **Format:** `{Purpose}Middleware.php`
- **Examples:** `AuthMiddleware.php`, `AdminMiddleware.php`
- **Class:** `class AuthMiddleware implements Middleware`

---

## Code Standards

### PSR-12 Compliance
- 4 spaces indentation (no tabs)
- Opening brace on same line for methods
- Type hints on all parameters and returns
- Strict types declaration

### Security
- All user input validated
- All output HTML-escaped
- Prepared statements for queries
- CSRF tokens on all forms
- Session regeneration on login

### Error Handling
```php
try {
    // Operation
} catch (ValidationException $e) {
    // User-friendly error
    return redirect()->back()->with('error', $e->getMessage());
} catch (Exception $e) {
    // Log technical error
    log_error($e);
    return redirect()->back()->with('error', 'An error occurred');
}
```

---

## Migration from SHARED

### Step 1: Audit SHARED
```bash
# Find all files in shared
find modules/shared -name "*.php"

# Categorize:
# - Move generic → base/
# - Keep specific → appropriate module
# - Delete redundant
```

### Step 2: Update Imports
```php
// OLD
require_once __DIR__ . '/../../shared/functions/config.php';

// NEW
use CIS\Base\Support\Config;
```

### Step 3: Delete Empty Directories
```bash
# After migration complete
rm -rf modules/shared
```

---

## Next Steps

1. ✅ Create login/register pages
2. ✅ Create dashboard/home page
3. ✅ Create AuthController
4. ✅ Create DashboardController
5. ✅ Create User model
6. ✅ Create middleware
7. ✅ Create routes
8. ✅ Migrate database schema
9. ⏳ Audit SHARED folder
10. ⏳ Migrate useful SHARED code to BASE
11. ⏳ Delete SHARED folder
12. ✅ Test authentication flow
13. ✅ Document API endpoints

---

**Status:** Architecture defined, ready for implementation
**Next:** Create core structural files
