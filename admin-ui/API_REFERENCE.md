# API Reference - IDE Features (Phase 20)

## 🔌 Validation Engine API

**Location**: `/modules/admin-ui/js/theme-builder/15-validation-engine.js`
**Access**: `window.validationEngine`

### Validation Methods

#### validateHTML(html) → Object
```javascript
const result = validationEngine.validateHTML('<html><body>Test</body></html>');
// Returns: {errors: [...], warnings: [...]}

// Example error:
{
  errors: [
    {line: 1, message: "Missing DOCTYPE declaration", type: "error"},
    {line: 1, message: "Missing meta viewport tag", type: "warning"}
  ],
  warnings: [...]
}
```

#### validateCSS(css) → Object
```javascript
const result = validationEngine.validateCSS('body { color: red }');
// Returns: {errors: [...], warnings: [...]}

// Example:
{
  errors: [
    {line: 1, column: 23, message: "Missing semicolon", type: "error"}
  ]
}
```

#### validateJS(js) → Object
```javascript
const result = validationEngine.validateJS('let x = 5;');
// Returns: {errors: [...], warnings: [...]}

// Example:
{
  errors: [],
  warnings: [
    {line: 1, message: "eval() detected", type: "security"}
  ]
}
```

---

### Formatting Methods

#### formatHTML(html, mode) → String
```javascript
const formatted = validationEngine.formatHTML(html, mode);
// mode: 'pretty' | 'compact' | 'minified'

// Example:
formatHTML('<html><body>Test</body></html>', 'pretty')
// Result:
/*
<html>
    <body>
        Test
    </body>
</html>
*/
```

#### formatCSS(css, mode) → String
```javascript
const formatted = validationEngine.formatCSS(css, 'minified');
// Returns minified CSS without whitespace
```

#### formatJS(js, mode) → String
```javascript
const formatted = validationEngine.formatJS(js, 'compact');
// Returns formatted JavaScript with minimal whitespace
```

---

### Minification Methods

#### minifyCSS(css) → String
```javascript
const minified = validationEngine.minifyCSS('body { color: red; }');
// Returns: "body{color:red;}"
// Typical savings: 40-50% file size reduction
```

#### minifyJS(js) → String
```javascript
const minified = validationEngine.minifyJS('let x = 5; console.log(x);');
// Returns: "let x=5;console.log(x);"
// Typical savings: 40-50% file size reduction
```

---

### Helper Methods

#### getFileStats(code, type) → Object
```javascript
const stats = validationEngine.getFileStats(code, 'js');
// Returns: {lines, chars, bytes, words, complexity}

// Example:
{
  lines: 150,
  chars: 4500,
  bytes: 4823,
  words: 890,
  complexity: {
    functions: 15,
    loops: 8,
    conditionals: 12,
    asyncOperations: 3
  }
}
```

#### calculateComplexity(js) → Object
```javascript
const complexity = validationEngine.calculateComplexity(jsCode);
// Returns: {functions, loops, conditionals, asyncOperations}
```

---

## 📁 File Explorer API

**Location**: `/modules/admin-ui/api/file-explorer-api.php`
**Base URL**: `/modules/admin-ui/api/file-explorer-api.php`

### Actions

#### list - Get Directory Contents
```
GET ?action=list&dir=/path

Response:
{
  "directory": "/path",
  "items": [
    {
      "name": "file.php",
      "path": "/path/file.php",
      "type": "file",
      "ext": "php",
      "size": 2048,
      "modified": "2025-10-30 14:30:45"
    },
    {
      "name": "subfolder",
      "path": "/path/subfolder",
      "type": "directory",
      "modified": "2025-10-30 14:30:45"
    }
  ],
  "count": 25
}
```

---

#### read - Read File Content
```
GET ?action=read&file=/path/to/file.php

Response:
{
  "file": "/path/to/file.php",
  "content": "<?php ... ?>",
  "size": 2048,
  "ext": "php",
  "lines": 45,
  "type": "text"
}

Limits:
- Maximum file size: 5MB
- Binary files: Not supported
```

---

#### write - Write File (with auto-backup)
```
POST ?action=write

Body:
{
  "file": "/path/to/file.php",
  "content": "<?php ... ?>"
}

Response:
{
  "file": "/path/to/file.php",
  "bytes_written": 2048,
  "backup": "/path/to/file.php.backup_2025-10-30_14-30-45",
  "timestamp": "2025-10-30T14:30:45Z"
}

Features:
- Automatic backup before write
- Timestamp preserved
- Can restore from backup
```

---

#### create - Create New File
```
POST ?action=create

Body:
{
  "directory": "/path",
  "name": "newfile.php",
  "content": "<?php ... ?>"
}

Response:
{
  "file": "/path/newfile.php",
  "bytes_written": 512,
  "created": "2025-10-30T14:30:45Z"
}
```

---

#### delete - Safe Delete (to backup)
```
POST ?action=delete

Body:
{
  "file": "/path/to/file.php"
}

Response:
{
  "file": "/path/to/file.php",
  "backup": "/path/to/file.php.deleted_2025-10-30_14-30-45",
  "message": "File moved to backup folder",
  "can_restore": true
}

Note: Files are NOT permanently deleted
      Files are moved to backup with timestamp
      Can be restored from backup folder
```

---

#### tree - Get Directory Tree
```
GET ?action=tree&dir=/path&depth=2

Response:
{
  "root": "/path",
  "tree": [
    {
      "name": "subfolder",
      "type": "directory",
      "path": "/path/subfolder",
      "children": [
        {
          "name": "file.php",
          "type": "file",
          "path": "/path/subfolder/file.php",
          "ext": "php",
          "size": 1024
        }
      ]
    },
    {
      "name": "file.js",
      "type": "file",
      "path": "/path/file.js",
      "ext": "js",
      "size": 2048
    }
  ]
}

Parameters:
- dir: Starting directory path
- depth: Recursion depth (default: 3, max: 10)
```

---

#### search - Search Files
```
GET ?action=search&q=pattern&dir=/path&ext=js

Response:
{
  "query": "pattern",
  "directory": "/path",
  "extension_filter": "js",
  "results": [
    {
      "name": "file1.js",
      "path": "/path/file1.js",
      "size": 2048,
      "ext": "js"
    },
    {
      "name": "file2.js",
      "path": "/path/file2.js",
      "size": 3072,
      "ext": "js"
    }
  ],
  "total": 2
}

Parameters:
- q: Search pattern (matches filename)
- dir: Directory to search in
- ext: File extension filter (optional)

Limits:
- Maximum results: 50 (returned)
- Recursive search (searches subdirectories)
```

---

## 🎬 PHP Sandbox API

**Location**: `/modules/admin-ui/api/sandbox-executor.php`
**Method**: POST only
**Content-Type**: application/json

### Execute PHP Code

```
POST /modules/admin-ui/api/sandbox-executor.php

Request:
{
  "code": "<?php $x = 5 + 3; echo $x; ?>",
  "timeout": 5,
  "context": {}
}

Response:
{
  "success": true,
  "output": "8",
  "result": null,
  "error": null,
  "variables": {
    "x": 8
  },
  "stats": {
    "memory_used": 12345,
    "memory_peak": 54321,
    "execution_time": 0.0023
  }
}
```

---

### Request Parameters

| Parameter | Type | Default | Description |
|-----------|------|---------|-------------|
| code | string | required | PHP code to execute (must include <?php ?> tags) |
| timeout | int | 5 | Timeout in seconds (prevents infinite loops) |
| context | object | {} | Pre-defined variables available to code |

---

### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| success | bool | true if execution succeeded without blocking |
| output | string | All echo/print output from code |
| result | any | Return value if code returns something |
| error | string | Error message if execution failed |
| variables | object | All variables created by code |
| stats | object | Memory and timing statistics |

---

### Security Blocklist

**Cannot Execute** (20+ functions):
```
exec, shell_exec, system, passthru, proc_open, popen,
curl_exec, curl_multi_exec, eval, assert, create_function,
include, require, include_once, require_once,
file_get_contents, file_put_contents, fopen, fwrite, unlink,
mkdir, rmdir, mysql_query, mysqli_query, PDO,
mail, header, setcookie, fpassthru, readfile,
serialize, unserialize, and more...
```

**Attempting to use blocked functions will result in error**:
```json
{
  "success": false,
  "error": "Blocked function 'exec' detected",
  "output": "",
  "variables": {}
}
```

---

### Usage Examples

#### Simple Echo
```javascript
fetch('/modules/admin-ui/api/sandbox-executor.php', {
  method: 'POST',
  body: JSON.stringify({
    code: '<?php echo "Hello, World!"; ?>'
  })
})
.then(r => r.json())
.then(d => console.log(d.output));  // "Hello, World!"
```

#### Array Operations
```javascript
const code = `<?php
  $arr = [1, 2, 3, 4, 5];
  $sum = array_sum($arr);
  $avg = $sum / count($arr);
  echo "Sum: $sum, Avg: $avg";
?>`;

fetch('/modules/admin-ui/api/sandbox-executor.php', {
  method: 'POST',
  body: JSON.stringify({code: code})
})
.then(r => r.json())
.then(d => console.log(d.output));  // "Sum: 15, Avg: 3"
```

#### With Context Variables
```javascript
const code = `<?php
  echo "Name: " . $name . ", Age: " . $age;
?>`;

fetch('/modules/admin-ui/api/sandbox-executor.php', {
  method: 'POST',
  body: JSON.stringify({
    code: code,
    context: {name: "John", age: 30}
  })
})
.then(r => r.json())
.then(d => console.log(d.output));  // "Name: John, Age: 30"
```

#### Error Handling
```javascript
fetch('/modules/admin-ui/api/sandbox-executor.php', {
  method: 'POST',
  body: JSON.stringify({
    code: '<?php undefined_function(); ?>'
  })
})
.then(r => r.json())
.then(d => {
  if (d.success === false) {
    console.error("Execution error:", d.error);
  } else {
    console.log("Output:", d.output);
  }
});
```

---

## 🔐 Allowed Paths

File operations only work with these directories:

```
✅ /modules          - Application modules
✅ /private_html     - Private files
✅ /conf             - Configuration files

❌ /public_html      - BLOCKED
❌ /tmp              - BLOCKED
❌ /etc              - BLOCKED
❌ (any other path)  - BLOCKED
```

Attempting to access blocked paths returns:
```json
{
  "success": false,
  "error": "Path /etc/passwd is not allowed"
}
```

---

## 📊 Performance Benchmarks

| Operation | Typical Duration | Max Duration |
|-----------|-----------------|--------------|
| Validate HTML | 5-10ms | 20ms |
| Validate CSS | 3-8ms | 15ms |
| Validate JS | 10-15ms | 25ms |
| Format code | 2-5ms | 10ms |
| Minify CSS | 5-10ms | 20ms |
| Minify JS | 8-12ms | 25ms |
| File read | 10-50ms | 100ms |
| File write | 20-100ms | 150ms |
| PHP execute | 20-50ms | 100ms |
| Directory list | 10-30ms | 50ms |
| File search | 50-100ms | 200ms |

---

## ❌ Error Responses

### File Not Found
```json
{
  "success": false,
  "error": "File not found: /path/to/missing.php"
}
```

### Permission Denied
```json
{
  "success": false,
  "error": "Permission denied: /path/file.php"
}
```

### Invalid Parameter
```json
{
  "success": false,
  "error": "Missing required parameter: file"
}
```

### PHP Execution Error
```json
{
  "success": false,
  "error": "Undefined function 'nonexistent_function'",
  "output": "",
  "variables": {}
}
```

---

## 🎯 Common Patterns

### Check If File Exists
```javascript
async function fileExists(path) {
  const response = await fetch(
    `/modules/admin-ui/api/file-explorer-api.php?action=read&file=${path}`
  );
  const data = await response.json();
  return data.success !== false;
}
```

### Read and Display File
```javascript
async function readAndDisplay(path) {
  const response = await fetch(
    `/modules/admin-ui/api/file-explorer-api.php?action=read&file=${path}`
  );
  const data = await response.json();
  if (data.success !== false) {
    document.querySelector('#editor').value = data.content;
  }
}
```

### Save With Backup
```javascript
async function saveFile(path, content) {
  const response = await fetch(
    '/modules/admin-ui/api/file-explorer-api.php?action=write',
    {
      method: 'POST',
      body: JSON.stringify({file: path, content: content})
    }
  );
  const data = await response.json();
  console.log('Backup created:', data.backup);
  return data.success !== false;
}
```

### Search and List Results
```javascript
async function searchFiles(pattern, directory) {
  const response = await fetch(
    `/modules/admin-ui/api/file-explorer-api.php?action=search&q=${pattern}&dir=${directory}`
  );
  const data = await response.json();
  return data.results;
}
```

---

## 📚 Documentation Links

- **Full Documentation**: `SESSION_PHASE20_SUMMARY.md`
- **Quick Start**: `QUICK_START_GUIDE.md`
- **Implementation Checklist**: `IMPLEMENTATION_CHECKLIST.md`
- **Validation Engine**: `15-validation-engine.js`
- **UI Integration**: `16-validation-ui-integration.js`

---

**Last Updated**: October 30, 2025
**Version**: 1.0.0
**Status**: Production Ready ✅
