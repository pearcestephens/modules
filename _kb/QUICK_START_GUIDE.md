# Quick Start Guide - IDE Features

## 🚀 What's New

This session added professional IDE-level features to the theme builder:

| Feature | File | Status |
|---------|------|--------|
| HTML/CSS/JS Validation | 15-validation-engine.js | ✅ Ready |
| UI Integration | 16-validation-ui-integration.js | ✅ Ready |
| PHP Execution Sandbox | sandbox-executor.php | ✅ Ready |
| File Explorer API | file-explorer-api.php | ✅ Ready |

---

## 📋 How to Test Each Feature

### 1. Validation (Immediate)

**Test in Browser Console**:
```javascript
// Create instance
const validator = new ValidationEngine();

// Test HTML validation
const html = '<html><head></head><body></body></html>';
const errors = validator.validateHTML(html);
console.log(errors);

// Test CSS validation
const css = 'body { color: red; }';
const cssErrors = validator.validateCSS(css);
console.log(cssErrors);

// Test JavaScript validation
const js = 'let x = 5;';
const jsErrors = validator.validateJS(js);
console.log(jsErrors);

// Test formatting
const pretty = validator.formatHTML(html, 'pretty');
const minified = validator.formatHTML(html, 'minified');
console.log('Pretty:', pretty);
console.log('Minified:', minified);
```

**Test in UI Panel**:
1. Open theme-builder-pro.php
2. Click "Validation Tools" tab
3. Click "Validate HTML" button
4. Should see validation results (errors highlighted in red)
5. Try "Format Code" → "Pretty" mode
6. Try "Format Code" → "Minified" mode
7. Try "Optimize CSS" to minify CSS
8. Try "Optimize JS" to minify JavaScript

---

### 2. File Explorer API (Immediate)

**Test in Browser Console**:
```javascript
// List files
fetch('/modules/admin-ui/api/file-explorer-api.php?action=list&dir=/modules/admin-ui')
  .then(r => r.json())
  .then(data => console.log('Files:', data.items));

// Read a file
fetch('/modules/admin-ui/api/file-explorer-api.php?action=read&file=/modules/admin-ui/js/theme-builder/15-validation-engine.js')
  .then(r => r.json())
  .then(data => {
    console.log('File size:', data.size, 'bytes');
    console.log('Lines:', data.lines);
    console.log('First 200 chars:', data.content.substring(0, 200));
  });

// Search files
fetch('/modules/admin-ui/api/file-explorer-api.php?action=search&q=validation&dir=/modules/admin-ui&ext=js')
  .then(r => r.json())
  .then(data => console.log('Found:', data.total, 'files'));

// Get directory tree
fetch('/modules/admin-ui/api/file-explorer-api.php?action=tree&dir=/modules/admin-ui&depth=2')
  .then(r => r.json())
  .then(data => console.log('Tree:', data.tree));
```

---

### 3. PHP Execution Sandbox (Immediate)

**Test in Browser Console**:
```javascript
// Execute simple PHP
fetch('/modules/admin-ui/api/sandbox-executor.php', {
  method: 'POST',
  body: JSON.stringify({
    code: '<?php $x = 5 + 3; echo "Result: " . $x; ?>',
    timeout: 5
  })
})
  .then(r => r.json())
  .then(data => {
    console.log('Output:', data.output);
    console.log('Variables:', data.variables);
    console.log('Stats:', data.stats);
  });

// Try different operations
fetch('/modules/admin-ui/api/sandbox-executor.php', {
  method: 'POST',
  body: JSON.stringify({
    code: `<?php
      $arr = [1, 2, 3, 4, 5];
      $sum = array_sum($arr);
      $avg = $sum / count($arr);
      echo "Sum: $sum, Avg: $avg";
      ?>`,
    timeout: 5
  })
})
  .then(r => r.json())
  .then(data => console.log('Array operations:', data.output));
```

**Security Test** (should fail):
```javascript
// Try to execute blocked function (should be blocked)
fetch('/modules/admin-ui/api/sandbox-executor.php', {
  method: 'POST',
  body: JSON.stringify({
    code: '<?php system("ls -la"); ?>',
    timeout: 5
  })
})
  .then(r => r.json())
  .then(data => console.log('Result:', data));  // Should show error
```

---

## 📊 Validation Checks Reference

### HTML5 Validation (7 checks)
- ✅ DOCTYPE present (`<!DOCTYPE html>`)
- ✅ Meta viewport tag
- ✅ Meta charset tag
- ✅ Semantic tags used (header, nav, main, footer, section, article)
- ✅ Image alt attributes (accessibility)
- ✅ No deprecated tags (font, center, b, i, etc.)
- ✅ Lang attribute on `<html>`

### CSS Validation (4 checks)
- ✅ Brace matching (no unclosed braces)
- ✅ Semicolons on all properties
- ✅ Vendor prefixes (-webkit-, -moz-, -ms-)
- ✅ !important abuse detection

### JavaScript Validation (7 checks)
- ✅ Syntax errors (catches parse errors)
- ✅ Eval/Function constructor detection
- ✅ Undeclared variables
- ✅ Console statements (production check)
- ✅ Debugger statements
- ✅ Memory leak patterns (setInterval without clear)
- ✅ Fetch error handling (missing .catch)

---

## 📁 File Organization

```
/modules/admin-ui/
├── js/theme-builder/
│   ├── 15-validation-engine.js           ← Core validators/formatters
│   ├── 16-validation-ui-integration.js   ← UI panel integration
│   ├── SESSION_PHASE20_SUMMARY.md        ← Full documentation
│   └── QUICK_START_GUIDE.md              ← This file
├── api/
│   ├── sandbox-executor.php              ← PHP execution endpoint
│   ├── file-explorer-api.php             ← File operations API
│   └── ai-agent-handler.php              ← AI agent backend
└── theme-builder-pro.php                 ← Main UI
```

---

## 🔧 API Endpoints Quick Reference

### File Explorer API
```
GET /modules/admin-ui/api/file-explorer-api.php?action=list&dir=/path
GET /modules/admin-ui/api/file-explorer-api.php?action=read&file=/path
GET /modules/admin-ui/api/file-explorer-api.php?action=tree&dir=/path&depth=2
GET /modules/admin-ui/api/file-explorer-api.php?action=search&q=pattern&dir=/path&ext=js

POST /modules/admin-ui/api/file-explorer-api.php?action=write
POST /modules/admin-ui/api/file-explorer-api.php?action=create
POST /modules/admin-ui/api/file-explorer-api.php?action=delete
```

### PHP Sandbox
```
POST /modules/admin-ui/api/sandbox-executor.php
Body: {code: "<?php ... ?>", timeout: 5}
```

---

## 🎯 Next Steps

### For Developers
1. Test each API endpoint (see console examples above)
2. Review validation checks and security blocklist
3. Check minification savings on your CSS/JS files
4. Try file operations (read, write, search)

### For Feature Development
1. Build File Explorer UI complete (tree rendering, context menus)
2. Add PHP execution UI button
3. Integrate with AI agent for auto-validation
4. Build collaborative editing support

### For Production
1. Monitor sandbox execution times (should be < 50ms)
2. Set file size limits based on your needs
3. Configure path restrictions for security
4. Enable logging for audit trail

---

## ⚠️ Important Security Notes

### Protected Paths
Only these directories are accessible:
- `/modules` - Application modules
- `/private_html` - Private files
- `/conf` - Configuration files

**Other paths are blocked!**

### Blocked Functions (20+)
Cannot execute in PHP sandbox:
- Command execution: exec, shell_exec, system
- Code execution: eval, assert
- File operations: file_get_contents, file_put_contents, unlink
- Database: PDO, mysqli, mysql_query
- And 10+ more...

### Safe Delete
When you "delete" a file, it's moved to backup folder with timestamp:
```
/path/file.php → /path/file.php.deleted_2025-10-30_14-30-45
```
**Never permanently deleted!** Can be restored from backup folder.

---

## 📞 Troubleshooting

### Validation Not Working
1. Check browser console for JavaScript errors
2. Verify `window.validationEngine` exists
3. Check that Monaco editors are loaded (`window.htmlEditor`, etc.)

### File API Returns 403
1. Check that path is in allowed list (/modules, /private_html, /conf)
2. Check file permissions (should be readable)
3. Check API logs for error details

### PHP Sandbox Timeout
1. Code is taking > 5 seconds
2. Possible infinite loop in code
3. Try simpler code first to test

### Minification Not Saving Space
1. Code already minified or very small
2. Try larger CSS/JS files
3. Remove comments manually if needed

---

## 📚 Full Documentation

For complete documentation, see: `SESSION_PHASE20_SUMMARY.md`

Contains:
- Detailed API documentation
- Integration guide
- Performance metrics
- Architecture diagrams
- Next steps and roadmap

---

**Ready to test?** Open browser console and try the examples above! 🚀
