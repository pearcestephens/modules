# Theme Builder IDE - Comprehensive User Flows & Test Scenarios

**Document Version:** 1.0.0
**Date:** October 30, 2025
**Purpose:** Map every user interaction path and test scenario

---

## 📋 TABLE OF CONTENTS

1. [Core User Flows](#core-user-flows)
2. [Validation Flows](#validation-flows)
3. [AI Agent Flows](#ai-agent-flows)
4. [File Operation Flows](#file-operation-flows)
5. [PHP Execution Flows](#php-execution-flows)
6. [Error & Edge Cases](#error--edge-cases)
7. [Performance Scenarios](#performance-scenarios)

---

## 🎯 CORE USER FLOWS

### FLOW 1: Basic HTML/CSS/JS Editing
**User Goal:** Edit code and see live preview

**Steps:**
1. User opens theme builder
2. Navigates to HTML tab
3. Modifies HTML code
4. Preview updates in real-time
5. Switches to CSS tab
6. Modifies styles
7. Preview updates with new styles
8. Switches to JS tab
9. Modifies JavaScript
10. Preview updates with JS behavior

**Success Criteria:**
- ✅ All editors contain correct code
- ✅ Preview reflects changes immediately
- ✅ Tab switching preserves all edits
- ✅ No data loss between tabs
- ✅ Code persists on page refresh (if saving implemented)

**Test Cases:**
- `CORE_1_1`: Empty tab editing
- `CORE_1_2`: Large file editing (10MB+)
- `CORE_1_3`: Special characters in code
- `CORE_1_4`: Unicode characters (emojis, Chinese)
- `CORE_1_5`: Tab switching rapid succession
- `CORE_1_6`: Undo/redo operations

---

### FLOW 2: Code Validation
**User Goal:** Validate code before deployment

**Steps:**
1. User opens validation tools panel
2. Clicks "Validate HTML"
3. Gets error/warning list with line numbers
4. Clicks "Validate CSS"
5. Gets validation results
6. Clicks "Validate JS"
7. Gets validation results
8. Reviews all issues
9. Manually fixes issues
10. Re-validates to confirm fixes

**Success Criteria:**
- ✅ All 18+ validation checks run
- ✅ Line numbers are accurate
- ✅ Error/warning distinction is clear
- ✅ Re-validation reflects fixes
- ✅ No false positives/negatives
- ✅ Validation completes in <20ms

**Test Cases:**
- `VAL_2_1`: Valid HTML5 document
- `VAL_2_2`: Invalid HTML (missing DOCTYPE)
- `VAL_2_3`: Accessibility issues (missing alt tags)
- `VAL_2_4`: Valid CSS
- `VAL_2_5`: CSS syntax errors (missing semicolons)
- `VAL_2_6`: CSS performance issues (!important overuse)
- `VAL_2_7`: Valid JavaScript
- `VAL_2_8`: JavaScript syntax errors
- `VAL_2_9`: Security issues (eval usage)
- `VAL_2_10`: All validators at once

---

### FLOW 3: Code Formatting
**User Goal:** Auto-format code for readability

**Steps:**
1. User opens code with poor formatting
2. Selects "Format Code" button
3. Chooses formatting mode (Pretty/Compact/Minified)
4. Code gets reformatted
5. Preview still works correctly
6. User switches formatting modes
7. Code is reformatted to new mode
8. User can easily read formatted code

**Success Criteria:**
- ✅ Pretty mode: 4-space indentation, fully readable
- ✅ Compact mode: 2-space indentation, reasonably readable
- ✅ Minified mode: No whitespace, 40-50% size reduction
- ✅ Formatting preserves functionality
- ✅ Performance: <5ms per format operation
- ✅ All 3 languages support all 3 modes

**Test Cases:**
- `FMT_3_1`: Single-line HTML to pretty
- `FMT_3_2`: Nested HTML formatting
- `FMT_3_3`: CSS with comments formatting
- `FMT_3_4`: CSS selector combining
- `FMT_3_5`: JS with complex nesting
- `FMT_3_6`: JS string preservation (don't format strings)
- `FMT_3_7`: Large file formatting (100KB)
- `FMT_3_8`: Format → Preview → Format cycle

---

### FLOW 4: Code Minification
**User Goal:** Reduce file size for production

**Steps:**
1. User opens code formatting panel
2. Views current file size
3. Clicks "Minify CSS" or "Minify JS"
4. Gets minified version
5. Sees byte savings (e.g., "50% reduction")
6. Can compare original vs minified
7. Optionally applies minified version
8. Verifies functionality still works

**Success Criteria:**
- ✅ CSS minification: 40-50% typical savings
- ✅ JS minification: 40-50% typical savings
- ✅ Byte savings clearly displayed
- ✅ Minified code is valid
- ✅ Performance not degraded
- ✅ Preview works with minified code

**Test Cases:**
- `MIN_4_1`: Small CSS file minification
- `MIN_4_2`: Large CSS file (50KB) minification
- `MIN_4_3`: CSS with comments minification
- `MIN_4_4`: CSS with media queries minification
- `MIN_4_5`: Small JS file minification
- `MIN_4_6`: Large JS file (100KB) minification
- `MIN_4_7`: JS with comments minification
- `MIN_4_8`: JS with string content minification
- `MIN_4_9`: Verify minified code executes same

---

## 🔍 VALIDATION FLOWS

### FLOW 5: HTML Validation Deep Dive
**User Goal:** Ensure HTML5 compliance

**Validation Checks (7 total):**

1. **DOCTYPE Check**
   - ✅ Present and correct: `<!DOCTYPE html>`
   - ✅ Missing: Warning
   - ✅ Incorrect: Warning

2. **Meta Tags Check**
   - ✅ Charset: `<meta charset="utf-8">`
   - ✅ Viewport: `<meta name="viewport" content="width=device-width">`
   - ✅ Missing either: Warning

3. **Semantic HTML**
   - ✅ Uses: `<header>`, `<nav>`, `<main>`, `<article>`, `<section>`, `<footer>`
   - ✅ Missing semantic tags: Info message

4. **Image Alt Attributes**
   - ✅ Every `<img>` has `alt=""`: Pass
   - ✅ Missing alt on any img: Warning per image

5. **Deprecated Tags**
   - ✅ No: `<font>`, `<center>`, `<b>`, `<i>`, `<u>`
   - ✅ Found deprecated: Error per tag

6. **Unclosed Tags**
   - ✅ All tags properly closed: Pass
   - ✅ Any unclosed: Error with line number

7. **Language Attribute**
   - ✅ `<html lang="en">` present: Pass
   - ✅ Missing: Warning

**Test Cases:**
- `HTML_5_1`: Perfect HTML5 document (0 issues)
- `HTML_5_2`: Missing DOCTYPE only
- `HTML_5_3`: Missing charset only
- `HTML_5_4`: Missing viewport only
- `HTML_5_5`: Missing all meta tags
- `HTML_5_6`: Using deprecated tags (all 5+)
- `HTML_5_7`: Images without alt text (3+ images)
- `HTML_5_8`: Unclosed div tags
- `HTML_5_9`: Mixed unclosed/closed tags
- `HTML_5_10`: Large document (1000+ lines) validation speed

---

### FLOW 6: CSS Validation Deep Dive
**User Goal:** Ensure CSS quality and performance

**Validation Checks (4 total):**

1. **Brace Matching**
   - ✅ All `{` have matching `}`: Pass
   - ✅ Missing closing brace: Error with line

2. **Semicolon Checking**
   - ✅ All declarations end with `;`: Pass
   - ✅ Missing semicolon: Error with line

3. **Vendor Prefix Detection**
   - ✅ Uses `-webkit-`, `-moz-`, `-ms-`: Info
   - ✅ Missing vendor prefixes for broad support: Info

4. **!important Overuse**
   - ✅ < 3 uses: Pass
   - ✅ 3-10 uses: Warning
   - ✅ > 10 uses: Error (specificity issues)

**Test Cases:**
- `CSS_6_1`: Perfect CSS (0 issues)
- `CSS_6_2`: Missing closing braces (1-5 instances)
- `CSS_6_3`: Missing semicolons (1-10 instances)
- `CSS_6_4`: Heavy !important usage (20+)
- `CSS_6_5`: Mix of issues (braces + semicolons + !important)
- `CSS_6_6`: Vendor prefixes present
- `CSS_6_7`: Large CSS file (100KB) validation speed
- `CSS_6_8`: Media queries with issues
- `CSS_6_9`: Nested CSS (SCSS-like)
- `CSS_6_10`: CSS with comments preservation

---

### FLOW 7: JavaScript Validation Deep Dive
**User Goal:** Ensure JS quality and security

**Validation Checks (7 total):**

1. **Syntax Errors**
   - ✅ Valid syntax: Pass
   - ✅ Invalid syntax: Error with details

2. **Eval/Function Usage**
   - ✅ No eval() or Function(): Pass
   - ✅ Found eval/Function: Security error

3. **Undeclared Variables**
   - ✅ All vars declared: Pass
   - ✅ Used before declaration: Error

4. **Console Statements**
   - ✅ None present: Pass
   - ✅ console.log/warn/error: Warning (debug in prod)

5. **Debugger Statements**
   - ✅ None present: Pass
   - ✅ Found debugger: Error (breaks in prod)

6. **Memory Leak Patterns**
   - ✅ No infinite setInterval: Pass
   - ✅ setInterval without clearInterval: Warning

7. **Fetch Error Handling**
   - ✅ All fetch() have .catch(): Pass
   - ✅ Missing .catch(): Error (unhandled rejections)

**Test Cases:**
- `JS_7_1`: Perfect JavaScript (0 issues)
- `JS_7_2`: Syntax error (missing semicolon)
- `JS_7_3`: Eval present (security risk)
- `JS_7_4`: Undeclared variable usage
- `JS_7_5`: Console statements (3+)
- `JS_7_6`: Debugger statement
- `JS_7_7`: setInterval without clear
- `JS_7_8`: fetch without .catch()
- `JS_7_9`: Multiple async/await patterns
- `JS_7_10`: Large file (100KB) validation speed

---

## 🤖 AI AGENT FLOWS

### FLOW 8: AI Add Component
**User Goal:** AI adds a UI component automatically

**Steps:**
1. User types: "Add a button component"
2. AI detects intent: `add_component`, target: `html`
3. AI generates button HTML
4. AI generates button CSS
5. AI queues edits with delays
6. Preview updates with new button
7. User sees button in preview
8. User can modify/accept/reject

**Edit Timeline:**
- 0ms: Add HTML button
- 500ms: Add CSS styles
- 1000ms: Preview updates

**Success Criteria:**
- ✅ Component appears in correct tab
- ✅ Styles applied to component
- ✅ Preview reflects changes
- ✅ User can undo changes
- ✅ Multiple components can coexist
- ✅ Performance: <100ms per edit

**Test Cases:**
- `AI_8_1`: Add button component
- `AI_8_2`: Add card component
- `AI_8_3`: Add navbar component
- `AI_8_4`: Add multiple components
- `AI_8_5`: Add component to existing code
- `AI_8_6`: AI component naming conflicts
- `AI_8_7`: Component with responsive design

---

### FLOW 9: AI Modify Styles
**User Goal:** AI changes colors/sizes/layout

**Steps:**
1. User types: "Change primary color to blue"
2. AI detects intent: `modify_style`
3. AI parses color value: `blue`
4. AI updates CSS color variable
5. Preview updates all elements with that color
6. User confirms change looks good
7. User can request further modifications

**Success Criteria:**
- ✅ Color change applied globally (if using vars)
- ✅ Size changes affect all uses
- ✅ Layout changes preserve content
- ✅ Preview updates immediately
- ✅ Can chain multiple modifications

**Test Cases:**
- `AI_9_1`: Change primary color (named)
- `AI_9_2`: Change primary color (hex)
- `AI_9_3`: Change primary color (rgb)
- `AI_9_4`: Increase font sizes
- `AI_9_5`: Adjust spacing
- `AI_9_6`: Change layout (flex to grid)
- `AI_9_7`: Multiple style changes
- `AI_9_8`: Style changes with validation

---

### FLOW 10: AI Validate and Fix
**User Goal:** AI validates code and suggests fixes

**Steps:**
1. User clicks: "AI Review Code"
2. AI calls validation engine
3. AI gets: 3 HTML warnings, 2 CSS errors, 1 JS warning
4. AI analyzes issues
5. AI suggests fixes with explanations
6. User sees AI suggestions in chat
7. User clicks "Apply Fix"
8. AI applies fixes automatically
9. User re-validates to confirm

**Success Criteria:**
- ✅ All issues detected
- ✅ Suggestions are actionable
- ✅ Fixes don't break code
- ✅ User can preview before applying
- ✅ Fixes are cumulative (multiple fixes)
- ✅ Explanations are clear

**Test Cases:**
- `AI_10_1`: Fix missing DOCTYPE
- `AI_10_2`: Fix missing alt attributes (3 images)
- `AI_10_3`: Fix CSS !important overuse
- `AI_10_4`: Fix missing semicolons (5+)
- `AI_10_5`: Fix security issues (eval)
- `AI_10_6`: Fix multiple issue types
- `AI_10_7`: Fix large file issues
- `AI_10_8`: AI fix accuracy validation

---

### FLOW 11: AI Watch Mode
**User Goal:** AI continuously validates and suggests improvements

**Steps:**
1. User enables "Watch Mode" in AI panel
2. AI starts monitoring code changes
3. User makes edit to HTML
4. AI runs validation after 1 second (debounce)
5. AI finds issues
6. AI updates suggestion panel
7. User makes another edit
8. AI re-validates
9. AI provides new suggestions
10. User continues editing with live feedback

**Success Criteria:**
- ✅ Watch mode toggles on/off
- ✅ Validation runs after edits (debounced)
- ✅ Suggestions update in real-time
- ✅ Performance: <200ms round trip
- ✅ No lag in editor while watching
- ✅ Can disable if distracting

**Test Cases:**
- `AI_11_1`: Enable/disable watch mode
- `AI_11_2`: Edit HTML with watch enabled
- `AI_11_3`: Edit CSS with watch enabled
- `AI_11_4`: Edit JS with watch enabled
- `AI_11_5`: Rapid edits (watch debouncing)
- `AI_11_6`: Large file watch mode
- `AI_11_7`: Watch mode performance impact

---

## 📁 FILE OPERATION FLOWS

### FLOW 12: Browse Files
**User Goal:** Navigate and view application files

**Steps:**
1. User opens File Explorer panel
2. Sees root directory tree
3. Clicks folder to expand
4. Sees sub-files and folders
5. Scrolls through many files
6. Searches for specific file
7. File appears in results
8. Clicks file to view

**Success Criteria:**
- ✅ Directory tree loads quickly (<50ms)
- ✅ All allowed directories shown
- ✅ File icons display correctly
- ✅ Folder expand/collapse works
- ✅ Search returns accurate results
- ✅ Large directories handled (<1000 files)

**Test Cases:**
- `FILE_12_1`: Expand single folder
- `FILE_12_2`: Expand nested folders (3+ levels)
- `FILE_12_3`: Navigate to /modules directory
- `FILE_12_4`: Navigate to /private_html directory
- `FILE_12_5`: Navigate to /conf directory
- `FILE_12_6`: Search for specific filename
- `FILE_12_7`: Search with wildcards
- `FILE_12_8`: Handle 1000+ files in directory
- `FILE_12_9`: Folder with no permission (error)

---

### FLOW 13: Read File
**User Goal:** View file contents

**Steps:**
1. User navigates to file
2. Clicks on file to read
3. File content loads
4. Content displays in editor/panel
5. File size and line count shown
6. User can scroll through content
7. User can search within file
8. User navigates to another file

**Success Criteria:**
- ✅ File loads in <100ms
- ✅ File size displayed
- ✅ Line count accurate
- ✅ Content is readable
- ✅ 5MB file limit enforced
- ✅ Special characters handled
- ✅ Large files don't freeze UI

**Test Cases:**
- `FILE_13_1`: Read small text file (1KB)
- `FILE_13_2`: Read large text file (100KB)
- `FILE_13_3`: Read PHP file with syntax highlighting
- `FILE_13_4`: Read file with special characters
- `FILE_13_5`: Read file with Unicode
- `FILE_13_6`: Read file at 5MB limit
- `FILE_13_7`: Attempt to read >5MB (error)
- `FILE_13_8`: File doesn't exist (error)

---

### FLOW 14: Write/Edit File
**User Goal:** Modify existing file

**Steps:**
1. User opens file in editor
2. Makes edits to content
3. Clicks "Save" button
4. System creates backup (auto-backup)
5. New content written to file
6. Success message shown
7. File remains open for more edits
8. User can undo (restore from backup)

**Success Criteria:**
- ✅ Backup created before write
- ✅ Backup includes timestamp
- ✅ Write operation atomic
- ✅ File restored correctly from backup
- ✅ Performance: <100ms per write
- ✅ Permissions checked before write
- ✅ Path validation prevents traversal

**Test Cases:**
- `FILE_14_1`: Write small edit (10 bytes)
- `FILE_14_2`: Write large edit (100KB)
- `FILE_14_3`: Write with special characters
- `FILE_14_4`: Write with Unicode
- `FILE_14_5`: Overwrite existing content
- `FILE_14_6`: Verify backup created
- `FILE_14_7`: Restore from backup
- `FILE_14_8`: Write to protected path (error)
- `FILE_14_9`: Multiple rapid writes
- `FILE_14_10`: Write with concurrent reads

---

### FLOW 15: Create File
**User Goal:** Create new file

**Steps:**
1. User right-clicks in File Explorer
2. Selects "Create New File"
3. Types filename
4. Confirms
5. New file created (empty)
6. File appears in tree
7. File opens in editor
8. User can edit immediately

**Success Criteria:**
- ✅ File created in correct location
- ✅ Empty initially
- ✅ File appears in explorer
- ✅ Can be edited immediately
- ✅ Correct file extension
- ✅ Unique name if duplicate

**Test Cases:**
- `FILE_15_1`: Create .html file
- `FILE_15_2`: Create .css file
- `FILE_15_3`: Create .js file
- `FILE_15_4`: Create .php file
- `FILE_15_5`: Create duplicate name (handle)
- `FILE_15_6`: Create in /modules
- `FILE_15_7`: Create in /private_html
- `FILE_15_8`: Invalid filename (error)
- `FILE_15_9`: Permission denied (error)

---

### FLOW 16: Delete File (Safe)
**User Goal:** Remove unwanted file

**Steps:**
1. User right-clicks file
2. Selects "Delete"
3. Confirmation dialog shown
4. User confirms
5. File moved to backup folder (safe delete)
6. Success message: "File moved to backups, can restore"
7. File no longer appears in tree
8. User can restore if needed

**Success Criteria:**
- ✅ Confirmation required
- ✅ File moved (not deleted)
- ✅ Backup path includes timestamp
- ✅ Can be restored
- ✅ Original location cleaned
- ✅ File no longer in tree

**Test Cases:**
- `FILE_16_1`: Delete .html file
- `FILE_16_2`: Delete .css file
- `FILE_16_3`: Delete .js file
- `FILE_16_4`: Confirm delete
- `FILE_16_5`: Cancel delete
- `FILE_16_6`: Restore from backup
- `FILE_16_7`: Verify backup file exists
- `FILE_16_8`: Delete non-existent file (error)
- `FILE_16_9`: Delete protected file (error)

---

## ⚙️ PHP EXECUTION FLOWS

### FLOW 17: Execute Simple PHP
**User Goal:** Run PHP code and see output

**Steps:**
1. User opens PHP Execution panel
2. Types PHP code: `<?php echo 5 + 3; ?>`
3. Clicks "Execute"
4. Code sent to sandbox
5. Sandbox validates (no blocklisted functions)
6. Code executes in isolated environment
7. Output captured: `"8"`
8. Output displayed in results panel
9. User sees execution stats (time, memory)

**Success Criteria:**
- ✅ Code executes correctly
- ✅ Output captured completely
- ✅ Execution time shown
- ✅ Memory usage shown
- ✅ Variables displayed
- ✅ Performance: <100ms execution
- ✅ No side effects (no file writes)

**Test Cases:**
- `PHP_17_1`: Simple arithmetic
- `PHP_17_2`: String operations
- `PHP_17_3`: Array operations
- `PHP_17_4`: Loop with echo
- `PHP_17_5`: Conditionals
- `PHP_17_6`: Function calls
- `PHP_17_7`: Class instantiation
- `PHP_17_8`: Variable assignment and extraction
- `PHP_17_9`: Multiple statements
- `PHP_17_10`: Output buffering

---

### FLOW 18: PHP Security (Blocklist)
**User Goal:** Ensure no dangerous operations

**Steps:**
1. User types PHP code with `exec()` function
2. Clicks "Execute"
3. Sandbox scans code
4. Finds `exec` in blocklist
5. Rejects execution
6. Shows error: "exec() is blocked for security"
7. User cannot execute dangerous code

**Blocked Functions (20+):**
- Command execution: exec, shell_exec, system, passthru, proc_open, popen
- Code execution: eval, assert, create_function
- File operations: include, require, file_get_contents, file_put_contents, unlink
- Database: PDO, mysqli_query, mysql_query
- Others: mail, header, setcookie, etc.

**Success Criteria:**
- ✅ All 20+ functions blocked
- ✅ Clear error message
- ✅ No code execution on block
- ✅ Security maintained

**Test Cases:**
- `PHP_18_1`: exec() blocked
- `PHP_18_2`: eval() blocked
- `PHP_18_3`: file_get_contents() blocked
- `PHP_18_4`: include() blocked
- `PHP_18_5`: PDO blocked
- `PHP_18_6`: All 20+ functions tested
- `PHP_18_7`: Safe functions allowed (echo, strlen, etc.)
- `PHP_18_8`: Blocklist bypass attempts (uppercase, etc.)

---

### FLOW 19: PHP Error Handling
**User Goal:** See errors clearly

**Steps:**
1. User types invalid PHP: `<?php echo $undefined; ?>`
2. Clicks "Execute"
3. Code executes but produces warning
4. Error caught and displayed
5. User sees line number and error message
6. User corrects code
7. Re-executes successfully

**Success Criteria:**
- ✅ All PHP warnings displayed
- ✅ All PHP errors displayed
- ✅ Parse errors prevented (pre-check)
- ✅ Error includes context
- ✅ User can debug easily

**Test Cases:**
- `PHP_19_1`: Undefined variable
- `PHP_19_2`: Type mismatch
- `PHP_19_3`: Array access on non-array
- `PHP_19_4`: Division by zero
- `PHP_19_5`: Invalid function call
- `PHP_19_6`: Parse error (missing semicolon)
- `PHP_19_7`: Exception thrown
- `PHP_19_8`: Fatal error

---

### FLOW 20: PHP with Context Variables
**User Goal:** Pass data to PHP code

**Steps:**
1. User provides context: `{name: "John", age: 30}`
2. User writes PHP: `<?php echo $name; ?>`
3. PHP sandbox receives context
4. Variables pre-defined in scope
5. Code executes with access to variables
6. Output shows: `"John"`
7. User can use context variables

**Success Criteria:**
- ✅ Context variables available
- ✅ Variables extracted after execution
- ✅ No variable pollution
- ✅ Context is isolated

**Test Cases:**
- `PHP_20_1`: Single context variable
- `PHP_20_2`: Multiple context variables
- `PHP_20_3`: Array context variable
- `PHP_20_4`: Modify context variable
- `PHP_20_5`: Access non-existent variable
- `PHP_20_6`: Context with special characters

---

## ⚠️ ERROR & EDGE CASES

### FLOW 21: Network Errors
**User Goal:** Handle API failures gracefully

**Scenarios:**
1. API endpoint timeout (>5 seconds)
2. API returns 500 error
3. API returns 403 (forbidden)
4. Network disconnected
5. Malformed JSON response

**Expected Behavior:**
- ✅ User sees clear error message
- ✅ "Retry" button available
- ✅ No data loss
- ✅ UI remains responsive

**Test Cases:**
- `ERR_21_1`: API timeout handling
- `ERR_21_2`: 500 error response
- `ERR_21_3`: 403 forbidden
- `ERR_21_4`: Network down
- `ERR_21_5`: Invalid JSON response

---

### FLOW 22: File Size Edge Cases
**User Goal:** Handle boundary conditions

**Scenarios:**
1. File exactly 5MB (read limit)
2. File just over 5MB (rejected)
3. Empty file (0 bytes)
4. Huge minification request (100MB result)
5. Binary file attempt

**Expected Behavior:**
- ✅ 5MB read exactly
- ✅ >5MB rejected with message
- ✅ Empty files handled
- ✅ Memory limits enforced
- ✅ Binary files rejected

**Test Cases:**
- `ERR_22_1`: File at 5MB limit
- `ERR_22_2`: File just over 5MB
- `ERR_22_3`: Empty file operations
- `ERR_22_4`: Large minification request
- `ERR_22_5`: Binary file detection

---

### FLOW 23: Character Encoding
**User Goal:** Support international characters

**Scenarios:**
1. UTF-8 characters (Chinese, Arabic, etc.)
2. Emojis in code comments
3. Special symbols in strings
4. Mixed encoding detection
5. BOM (Byte Order Mark) handling

**Expected Behavior:**
- ✅ All characters preserved
- ✅ No corruption
- ✅ Display correctly
- ✅ Validation works

**Test Cases:**
- `ERR_23_1`: Chinese characters
- `ERR_23_2`: Arabic characters
- `ERR_23_3`: Emojis in comments
- `ERR_23_4`: Mixed encoding
- `ERR_23_5`: BOM handling

---

### FLOW 24: Permission Denied
**User Goal:** Handle access control

**Scenarios:**
1. Read protected file
2. Write to read-only file
3. Delete system file
4. Create in protected directory

**Expected Behavior:**
- ✅ Clear error message
- ✅ No partial operations
- ✅ System protected

**Test Cases:**
- `ERR_24_1`: Read protected file
- `ERR_24_2`: Write to read-only
- `ERR_24_3`: Delete protected file
- `ERR_24_4`: Create in protected dir

---

## ⚡ PERFORMANCE SCENARIOS

### FLOW 25: Large File Performance
**User Goal:** Work with large files efficiently

**Scenarios:**
1. Load 10MB HTML file
2. Validate 50KB CSS file
3. Format 100KB JavaScript
4. Minify 200KB CSS
5. Search in 500 files

**Performance Targets:**
- ✅ File load: <500ms
- ✅ Validation: <50ms
- ✅ Formatting: <100ms
- ✅ Minification: <200ms
- ✅ Search: <1 second

**Test Cases:**
- `PERF_25_1`: Load 10MB HTML
- `PERF_25_2`: Validate 50KB CSS
- `PERF_25_3`: Format 100KB JS
- `PERF_25_4`: Minify 200KB CSS
- `PERF_25_5`: Measure all operations

---

### FLOW 26: Rapid Operations
**User Goal:** Handle user mashing buttons

**Scenarios:**
1. Click validate 10 times rapidly
2. Format → minify → format cycle
3. Rapid tab switching (10 switches/sec)
4. Rapid file edits with auto-save

**Performance Targets:**
- ✅ No freeze/lag
- ✅ Operations queued/debounced
- ✅ Last operation wins
- ✅ UI remains responsive

**Test Cases:**
- `PERF_26_1`: Rapid validation (10x)
- `PERF_26_2`: Rapid formatting (5x)
- `PERF_26_3`: Tab switching (10x)
- `PERF_26_4`: Rapid AI requests

---

### FLOW 27: Memory Management
**User Goal:** Prevent memory issues

**Scenarios:**
1. Keep editor open for 1 hour
2. Load many files without closing
3. Run many PHP executions
4. Large undo/redo history

**Performance Targets:**
- ✅ Memory usage stable
- ✅ No memory leaks
- ✅ Garbage collection working
- ✅ No browser crash

**Test Cases:**
- `PERF_27_1`: Long session (1 hour)
- `PERF_27_2`: Load 100 files
- `PERF_27_3`: Execute PHP 50 times
- `PERF_27_4`: Large undo history

---

## 🎯 TEST PRIORITIZATION

### CRITICAL (Must Pass)
- CORE_1: Basic editing
- VAL_2: Validation accuracy
- FILE_12-16: File operations
- PHP_17: Basic execution
- PHP_18: Security blocklist
- ERR_21: Network errors

### HIGH (Should Pass)
- FMT_3: Formatting
- MIN_4: Minification
- AI_8-11: AI operations
- PHP_19: Error handling
- PERF_25: Large files

### MEDIUM (Nice to Have)
- VAL_5-7: Deep validation
- FILE duplicate/permission tests
- ERR_22-24: Edge cases
- PERF_26-27: Stress testing

---

## 📊 TEST COVERAGE SUMMARY

| Category | Total Cases | Critical | High | Medium |
|----------|------------|----------|------|--------|
| Core | 6 | 6 | 0 | 0 |
| Validation | 30 | 10 | 15 | 5 |
| AI Agent | 35 | 5 | 25 | 5 |
| File Ops | 30 | 15 | 10 | 5 |
| PHP Exec | 20 | 10 | 5 | 5 |
| Errors | 20 | 5 | 5 | 10 |
| Performance | 10 | 0 | 5 | 5 |
| **TOTAL** | **151** | **51** | **65** | **35** |

---

**Next Step:** Create automated test suite for all critical & high priority cases
