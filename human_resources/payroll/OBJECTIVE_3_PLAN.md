# 🔒 OBJECTIVE 3: Static File Serving Hardening

**Status:** ANALYZING
**Date:** November 1, 2025
**Priority:** HIGH (Security Critical)

---

## 🎯 Objective Requirements

From hardening plan:
> **3. Static file serving hardening**
> - Harden index.php static file branch
> - Block path traversal (../, absolute paths)
> - Normalize with realpath()
> - Whitelist extensions
> - Add security tests

---

## 🔍 Current State Analysis

### Location
`index.php` lines 268-329

### Current Code (VULNERABLE)
```php
// Check if this is a static asset request
if (preg_match('/\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2|ttf|eot|map)$/i', $cleanUri)) {
    // Extract file path from URI
    $moduleBasePath = '/modules/human_resources/payroll';

    if (strpos($cleanUri, $moduleBasePath) === 0) {
        $relativeFilePath = substr($cleanUri, strlen($moduleBasePath));
    } else {
        $relativeFilePath = $cleanUri;
    }

    $filePath = __DIR__ . $relativeFilePath;

    if (file_exists($filePath) && is_file($filePath)) {
        // Serve file
        readfile($filePath);
        exit;
    }
}
```

---

## 🚨 Security Vulnerabilities Identified

### 1. **Path Traversal Attack** (CRITICAL)
**Vulnerability:**
```php
$filePath = __DIR__ . $relativeFilePath;
```

**Attack Vector:**
```
/modules/human_resources/payroll/../../../config/database.php?x=.css
                                 └─ Path traversal escape
```

**Result:** Can read ANY file on the system (config files, .env, database credentials, etc.)

---

### 2. **Absolute Path Attack** (HIGH)
**Vulnerability:**
```php
// No check for absolute paths
$filePath = __DIR__ . $relativeFilePath;
```

**Attack Vector:**
```
/etc/passwd?x=.css
```

**Result:** Can read system files

---

### 3. **No Realpath Normalization** (HIGH)
**Vulnerability:**
```php
// No realpath() check
if (file_exists($filePath) && is_file($filePath)) {
```

**Attack Vector:**
```
/assets/./././../../../config/database.php?x=.css
```

**Result:** Bypasses basic path checks

---

### 4. **Weak Extension Validation** (MEDIUM)
**Issue:** Extension check on URI, not actual file
```php
preg_match('/\.(css|js...)$/i', $cleanUri)
```

**Attack Vector:**
```
/config/database.php?x=.css
                       └─ Fakes extension
```

**Result:** Serves non-asset files if URI ends with allowed extension

---

### 5. **No Jail Directory Enforcement** (HIGH)
**Issue:** No check that file is within allowed directory
```php
// No check like: strpos(realpath($filePath), realpath(__DIR__ . '/assets/'))
```

**Result:** Can access files anywhere in the module directory

---

### 6. **Information Disclosure** (MEDIUM)
**Issue:** Reveals file paths in error messages
```php
echo "Asset not found: " . htmlspecialchars($relativeFilePath);
```

**Result:** Leaks internal file structure

---

## 🛡️ Hardening Plan

### 1. Path Traversal Protection
```php
// Block ../ sequences
if (strpos($relativeFilePath, '..') !== false) {
    http_response_code(403);
    exit('Forbidden');
}
```

### 2. Absolute Path Protection
```php
// Block absolute paths
if ($relativeFilePath[0] === '/' || preg_match('/^[a-z]:/i', $relativeFilePath)) {
    http_response_code(403);
    exit('Forbidden');
}
```

### 3. Realpath Normalization + Jail
```php
// Normalize and validate within jail
$assetsDir = realpath(__DIR__ . '/assets');
$realFilePath = realpath($filePath);

if (!$realFilePath || strpos($realFilePath, $assetsDir) !== 0) {
    http_response_code(404);
    exit;
}
```

### 4. Strict Extension Whitelist
```php
// Check actual file extension after realpath
$extension = strtolower(pathinfo($realFilePath, PATHINFO_EXTENSION));
$allowedExtensions = ['css', 'js', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'eot', 'map'];

if (!in_array($extension, $allowedExtensions, true)) {
    http_response_code(403);
    exit('Forbidden');
}
```

### 5. Remove Information Disclosure
```php
// Generic error message
http_response_code(404);
exit; // No details
```

### 6. Security Logging
```php
// Log all blocked attempts
PayrollLogger::warning('Static file access blocked', [
    'uri' => $cleanUri,
    'reason' => 'path_traversal',
    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
]);
```

---

## 🧪 Security Test Cases

### Test 1: Path Traversal with ../
```
GET /modules/human_resources/payroll/../../../config/database.php?x=.css
Expected: 403 Forbidden
```

### Test 2: Path Traversal with URL encoding
```
GET /modules/human_resources/payroll/%2e%2e%2f%2e%2e%2f%2e%2e%2fconfig/database.php?x=.css
Expected: 403 Forbidden
```

### Test 3: Absolute path
```
GET /etc/passwd?x=.css
Expected: 403 Forbidden
```

### Test 4: Null byte injection
```
GET /modules/human_resources/payroll/assets/main.css%00.php
Expected: 403 Forbidden
```

### Test 5: Double encoding
```
GET /modules/human_resources/payroll/%252e%252e%252f
Expected: 403 Forbidden
```

### Test 6: Fake extension
```
GET /modules/human_resources/payroll/config/database.php?x=.css
Expected: 403 Forbidden (not in assets/)
```

### Test 7: Valid asset (should work)
```
GET /modules/human_resources/payroll/assets/css/main.css
Expected: 200 OK + file contents
```

### Test 8: Valid JS (should work)
```
GET /modules/human_resources/payroll/assets/js/app.js
Expected: 200 OK + file contents
```

---

## 🎯 Hardened Implementation

### Before (VULNERABLE):
- ❌ No path traversal check
- ❌ No absolute path check
- ❌ No realpath normalization
- ❌ No jail directory enforcement
- ❌ Extension check on URI (not file)
- ❌ Information disclosure in errors

### After (HARDENED):
- ✅ Block ../ sequences
- ✅ Block absolute paths
- ✅ Realpath normalization
- ✅ Jail to /assets/ directory only
- ✅ Extension check on actual file
- ✅ Generic error messages
- ✅ Security logging
- ✅ Comprehensive tests

---

## 📝 Acceptance Criteria

- [ ] Block path traversal (../)
- [ ] Block absolute paths
- [ ] Use realpath() normalization
- [ ] Enforce jail directory (assets/ only)
- [ ] Whitelist extensions on actual file
- [ ] No information disclosure
- [ ] Log security events
- [ ] 8+ security tests pass

---

## 🚀 Implementation Steps

1. Replace static file serving section (lines 268-329)
2. Add security checks in order:
   - Path traversal block
   - Absolute path block
   - Realpath normalization
   - Jail enforcement
   - Extension whitelist
3. Add security logging
4. Create test file: tests/Security/StaticFileSecurityTest.php
5. Run tests
6. Commit

---

## ⏱️ Time Estimate
**Code changes:** 15 minutes
**Testing:** 10 minutes
**Total:** 25 minutes

---

**Next Action:** Implement hardened static file serving with comprehensive security checks
