# Enterprise Error Handler - Usage Guide

## 🎯 Quick Start

### **Basic Setup (Development)**
```php
<?php
use Modules\Core\ErrorHandler;

// Enable debug mode for development
ErrorHandler::register(
    debugMode: true,
    logPath: '/path/to/logs/errors.log'
);
```

### **Production Setup**
```php
<?php
use Modules\Core\ErrorHandler;

// Disable debug mode for production
ErrorHandler::register(
    debugMode: false,
    logPath: '/var/log/cis/errors.log'
);
```

---

## 🔥 Features

### **1. Beautiful Debug Screen** (Development Mode)
- ✅ Full stack trace with syntax-highlighted code preview
- ✅ Context information (SQL queries, user data, etc.)
- ✅ Request details (method, URI, headers, IP)
- ✅ Environment information (PHP version, memory, etc.)
- ✅ Collapsible sections for easy navigation
- ✅ Dark theme optimized for long debugging sessions
- ✅ Unique error ID for log correlation

### **2. User-Friendly Production Screen**
- ✅ Clean, professional error message
- ✅ No technical details exposed
- ✅ Unique error ID for support tickets
- ✅ "Back to Home" button

### **3. Comprehensive Logging**
- ✅ All errors logged to file with full stack trace
- ✅ Unique error ID links screen to log entry
- ✅ Timestamp, request details, and context included

### **4. Context System**
- ✅ Add custom debugging information
- ✅ SQL queries, user info, API responses
- ✅ Displayed in debug screen

---

## 📖 Usage Examples

### **Example 1: Module Initialization**
```php
<?php
// modules/consignments/index.php

use Modules\Core\ErrorHandler;

// Register error handler
ErrorHandler::register(
    debugMode: getenv('APP_DEBUG') === '1',
    logPath: __DIR__ . '/../../logs/module-errors.log'
);

// Add context for debugging
ErrorHandler::addContext('module', 'consignments');
ErrorHandler::addContext('user_id', $_SESSION['userID'] ?? null);

// Your code...
```

### **Example 2: With Database Context**
```php
<?php
use Modules\Core\ErrorHandler;

ErrorHandler::register(true);

// Add SQL query context before executing
$query = "SELECT * FROM transfers WHERE id = ?";
ErrorHandler::addContext('last_query', $query);
ErrorHandler::addContext('query_params', [123]);

try {
    $stmt = $pdo->prepare($query);
    $stmt->execute([123]);
} catch (\PDOException $e) {
    // Error handler will show the query in context
    throw $e;
}
```

### **Example 3: API Debugging**
```php
<?php
use Modules\Core\ErrorHandler;

ErrorHandler::register(true);

// Add API context
ErrorHandler::addContext('api_endpoint', 'https://api.example.com/transfers');
ErrorHandler::addContext('request_payload', [
    'outlet_from' => 'A1B2',
    'outlet_to' => 'C3D4'
]);

try {
    $response = callVendAPI('/transfers', $data);
    ErrorHandler::addContext('api_response', $response);
} catch (\Exception $e) {
    // Full API context will be shown
    throw $e;
}
```

### **Example 4: Custom Error Pages**
```php
<?php
use Modules\Core\ErrorHandler;

// Register with custom log path
ErrorHandler::register(
    debugMode: $_SERVER['REMOTE_ADDR'] === '127.0.0.1', // Debug only for localhost
    logPath: '/var/log/cis/errors.log'
);
```

---

## 🎨 What You See in Debug Mode

### **Error Header**
```
💥 PDOException
SQLSTATE[42S02]: Base table or view not found: 1146 Table 'db.transfers' doesn't exist
📁 /modules/consignments/controllers/PackController.php
📍 Line 45
🔢 Code 42S02
Error ID: A3F8D9E2B1C4
```

### **Stack Trace**
- Full call stack with file paths and line numbers
- **Code preview** showing 5 lines before/after error
- Syntax highlighting for PHP code
- Function/method calls with class names

### **Context Information** (if added)
```json
{
  "module": "consignments",
  "user_id": 42,
  "last_query": "SELECT * FROM transfers WHERE id = ?",
  "query_params": [123]
}
```

### **Request Information**
- HTTP Method
- Request URI
- Query String
- User Agent
- IP Address
- Referer
- Timestamp

### **Environment**
- PHP Version
- OS
- Server Software
- Memory Usage
- Peak Memory
- Memory Limit

---

## 🔍 What You See in Production Mode

Clean, user-friendly screen:
```
😔
Oops! Something went wrong

We're sorry, but an unexpected error has occurred. 
Our team has been notified and we're working on fixing this issue.

Error Reference: A3F8D9E2B1C4

[← Back to Home]
```

---

## 📝 Log File Format

```
[2025-10-12 14:32:15] [PDOException] 42S02: SQLSTATE[42S02]: Base table or view not found in /modules/consignments/controllers/PackController.php:45
Stack trace:
#0 /modules/consignments/controllers/PackController.php(45): PDO->prepare()
#1 /modules/consignments/index.php(12): PackController->show()
#2 {main}
Error ID: A3F8D9E2B1C4
--------------------------------------------------------------------------------
```

---

## 🛠️ Configuration

### **Environment Variables**
```bash
# .env file
APP_DEBUG=1                                    # Enable debug mode
ERROR_LOG_PATH=/var/log/cis/errors.log        # Custom log path
```

### **PHP Code**
```php
<?php
// Enable based on environment
$isDebug = getenv('APP_DEBUG') === '1';
$logPath = getenv('ERROR_LOG_PATH') ?: '/default/path/errors.log';

ErrorHandler::register($isDebug, $logPath);
```

---

## 🎯 Best Practices

### **DO:**
✅ Always register ErrorHandler at the top of your entry point  
✅ Use `addContext()` for debugging complex issues  
✅ Add SQL queries and parameters as context  
✅ Add user ID and module name as context  
✅ Use unique error IDs when reporting bugs  
✅ Disable debug mode in production  

### **DON'T:**
❌ Don't show debug screen to end users  
❌ Don't log sensitive data (passwords, tokens)  
❌ Don't catch exceptions without rethrowing  
❌ Don't use `@` error suppression operator  

---

## 🔐 Security Notes

- **Production mode hides all technical details** from end users
- **Debug mode should ONLY be enabled for developers**
- **Error logs may contain sensitive data** - protect with file permissions
- **Unique error IDs** allow support to find logs without exposing details

---

## 🚀 Integration with Existing Code

### **Replace Old Handler**
```php
// OLD (modules/core/ErrorHandler.php)
ErrorHandler::register(false);

// NEW (same file, upgraded)
ErrorHandler::register(
    debugMode: getenv('APP_DEBUG') === '1',
    logPath: __DIR__ . '/../../logs/errors.log'
);
```

### **No Breaking Changes**
- Old `register(bool)` signature still works
- Second parameter is optional
- Backwards compatible

---

## 📊 Performance Impact

- **Negligible overhead** when no errors occur
- **Stack trace generation** only happens on error
- **Code preview** loads files on-demand
- **Log writes** are non-blocking

---

## 🐛 Debugging Tips

### **1. Add Context Early**
```php
ErrorHandler::addContext('debug_checkpoint', 'before_database_query');
```

### **2. Catch and Re-throw**
```php
try {
    $result = dangerousOperation();
} catch (\Exception $e) {
    ErrorHandler::addContext('operation', 'dangerousOperation');
    ErrorHandler::addContext('input', $input);
    throw $e; // Re-throw to trigger handler
}
```

### **3. Use Error IDs**
When user reports bug:
```
User: "I got error A3F8D9E2B1C4"
You: grep "A3F8D9E2B1C4" /var/log/cis/errors.log
```

---

## ✅ Acceptance Criteria

- [x] Full stack traces with code preview
- [x] Context system for custom debugging data
- [x] Beautiful dark-themed debug screen
- [x] User-friendly production screen
- [x] Comprehensive logging with unique IDs
- [x] Request and environment information
- [x] Collapsible sections for long traces
- [x] Backwards compatible
- [x] Zero dependencies
- [x] Production-ready

---

**Status:** ✅ READY TO USE

**Upgrade your error handling to enterprise-grade debugging! 🚀**
