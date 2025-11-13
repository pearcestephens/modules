# ✅ VapeUltra Integration Checklist

Use this checklist when converting pages to VapeUltra or creating new ones.

---

## 🆕 NEW PAGE CHECKLIST

### □ **1. File Setup**
- [ ] Created file in correct module's `views/` directory
- [ ] Added file header comment with module and page name
- [ ] Included `require_once __DIR__ . '/../bootstrap.php';`
- [ ] Started output buffering with `ob_start();`

### □ **2. Content Structure**
- [ ] Created page HTML content
- [ ] Used proper Bootstrap 5 classes
- [ ] Followed design system colors (`var(--vape-primary-500)`, etc.)
- [ ] Used proper spacing (8px grid)
- [ ] Made content responsive (mobile-first)

### □ **3. Navigation Setup**
- [ ] Defined `$breadcrumb` array with proper structure
- [ ] Added icons to breadcrumb items (optional)
- [ ] Defined `$subnav` array (if module has sub-navigation)
- [ ] Set active states correctly

### □ **4. Master Template Render**
- [ ] Captured content with `ob_get_clean()`
- [ ] Called `$renderer->render('master', [...])`
- [ ] Set page title correctly
- [ ] Configured navigation visibility
- [ ] Set layout options correctly

### □ **5. JavaScript (if needed)**
- [ ] Added `<script>` block before `ob_get_clean()`
- [ ] Wrapped in `DOMContentLoaded` event
- [ ] Used VapeUltra components (Ajax, Modal, Toast)
- [ ] Handled errors gracefully

### □ **6. Testing**
- [ ] Page loads without errors
- [ ] Breadcrumb appears correctly
- [ ] Sub-navigation appears correctly (if used)
- [ ] All links work
- [ ] AJAX calls work (if used)
- [ ] Mobile responsive layout works
- [ ] No console errors

### □ **7. Documentation**
- [ ] Added inline comments for complex logic
- [ ] Updated module README if needed

---

## 🔄 EXISTING PAGE CONVERSION CHECKLIST

### □ **1. Preparation**
- [ ] Backed up original file (`.VAPEULTRA_BACKUP` suffix)
- [ ] Identified current template system
- [ ] Noted any custom CSS/JS dependencies
- [ ] Documented any special functionality

### □ **2. Header Conversion**
- [ ] Replaced old template includes with VapeUltra header
- [ ] Removed `$pageTitle`, `$pageCSS`, `$pageJS` variables
- [ ] Kept `require_once __DIR__ . '/../bootstrap.php';`
- [ ] Kept `ob_start();` for content buffering

### □ **3. Content Extraction**
- [ ] Identified content section
- [ ] Removed old theme-specific HTML
- [ ] Cleaned up inline styles (moved to design system)
- [ ] Updated class names to Bootstrap 5 (if needed)
- [ ] Ensured content follows design system

### □ **4. Navigation Migration**
- [ ] Converted old breadcrumbs to new format
- [ ] Created sub-navigation array (if applicable)
- [ ] Set active states correctly
- [ ] Added icons where appropriate

### □ **5. Footer Conversion**
- [ ] Removed old template footer
- [ ] Changed `$content = ob_get_clean()` to `$pageContent = ob_get_clean()`
- [ ] Added `$renderer->render('master', [...])` call
- [ ] Configured all master template options

### □ **6. CSS/JS Migration**
- [ ] Removed old CSS includes (now in master.php)
- [ ] Updated JavaScript to use VapeUltra components
- [ ] Replaced old AJAX calls with `VapeUltra.Ajax`
- [ ] Replaced old modals with `VapeUltra.Modal`
- [ ] Replaced old notifications with `VapeUltra.Toast`

### □ **7. Testing**
- [ ] Page loads without errors
- [ ] All functionality works as before
- [ ] Navigation displays correctly
- [ ] Responsive layout works
- [ ] AJAX calls work
- [ ] No console errors
- [ ] Compared with backed-up version

### □ **8. Cleanup**
- [ ] Removed commented-out old code
- [ ] Removed unused variables
- [ ] Updated inline documentation
- [ ] Committed changes with descriptive message

---

## 🎨 DESIGN SYSTEM COMPLIANCE

### □ **Colors**
- [ ] Using design system colors only (`--vape-primary-*`, etc.)
- [ ] No arbitrary hex colors (#123456)
- [ ] Proper contrast ratios (WCAG 2.1 AA)
- [ ] Semantic color usage (success=green, error=red, etc.)

### □ **Typography**
- [ ] Using design system font sizes (`text-sm`, `text-lg`, etc.)
- [ ] Proper font weights (`font-medium`, `font-bold`, etc.)
- [ ] Consistent line heights
- [ ] No arbitrary font sizes

### □ **Spacing**
- [ ] Using 8px grid spacing (`m-2`, `p-4`, etc.)
- [ ] No arbitrary margins/paddings
- [ ] Consistent spacing throughout page
- [ ] Proper use of container classes

### □ **Components**
- [ ] Using Bootstrap 5 components
- [ ] Proper card structure (if used)
- [ ] Correct button variants
- [ ] Proper form styling

---

## ♿ ACCESSIBILITY CHECKLIST

### □ **Structure**
- [ ] Semantic HTML5 elements (`<nav>`, `<main>`, `<article>`, etc.)
- [ ] Proper heading hierarchy (H1 → H2 → H3)
- [ ] ARIA labels where needed
- [ ] Alt text for images

### □ **Navigation**
- [ ] Keyboard accessible (TAB navigation)
- [ ] Focus indicators visible
- [ ] Skip links available (if needed)
- [ ] Breadcrumb has proper ARIA

### □ **Forms**
- [ ] Labels associated with inputs
- [ ] Required fields marked
- [ ] Error messages visible
- [ ] Focus states clear

### □ **Interactive Elements**
- [ ] Buttons have descriptive text
- [ ] Links have meaningful text (no "click here")
- [ ] Modals trap focus
- [ ] Toasts have ARIA live regions

---

## 📱 MOBILE RESPONSIVENESS

### □ **Layout**
- [ ] Mobile-first approach used
- [ ] Proper breakpoint usage
- [ ] No horizontal scrolling
- [ ] Touch targets ≥ 44x44px

### □ **Navigation**
- [ ] Breadcrumb collapses on mobile
- [ ] Sub-navigation becomes dropdown on mobile
- [ ] Sidebar collapses on mobile
- [ ] All touch interactions work

### □ **Content**
- [ ] Text readable without zooming
- [ ] Images scale properly
- [ ] Tables responsive or scrollable
- [ ] Cards stack on mobile

---

## 🚀 PERFORMANCE CHECKLIST

### □ **Assets**
- [ ] No duplicate CSS includes
- [ ] No duplicate JS includes
- [ ] Images optimized
- [ ] No inline styles (use design system)

### □ **JavaScript**
- [ ] Event listeners cleaned up
- [ ] No memory leaks
- [ ] Efficient DOM queries
- [ ] Debounced/throttled where needed

### □ **AJAX**
- [ ] Request deduplication enabled
- [ ] Proper error handling
- [ ] Loading states shown
- [ ] Timeouts configured

---

## 🔒 SECURITY CHECKLIST

### □ **Input Handling**
- [ ] User input sanitized
- [ ] XSS prevention applied
- [ ] SQL injection prevention (use prepared statements)
- [ ] CSRF tokens included in forms

### □ **AJAX**
- [ ] CSRF token auto-injected (VapeUltra.Ajax does this)
- [ ] Authentication checked server-side
- [ ] Authorization checked server-side
- [ ] Sensitive data not in URLs

### □ **Output**
- [ ] HTML escaped properly
- [ ] JavaScript escaped in JSON
- [ ] No secrets in client-side code
- [ ] Error messages don't leak info

---

## 📝 CODE QUALITY CHECKLIST

### □ **PHP**
- [ ] Follows PSR-12 standards
- [ ] Proper error handling
- [ ] Type declarations used
- [ ] Docblocks added for complex functions

### □ **JavaScript**
- [ ] Clean, readable code
- [ ] Proper error handling
- [ ] Comments for complex logic
- [ ] No console.log in production

### □ **HTML**
- [ ] Properly indented
- [ ] Valid HTML5
- [ ] Semantic structure
- [ ] No deprecated tags

---

## 🎯 MODULE-SPECIFIC CHECKLISTS

### □ **Sales Module**
- [ ] Currency formatted properly
- [ ] Dates formatted consistently
- [ ] Invoice numbers validated
- [ ] Customer links work

### □ **Inventory Module**
- [ ] Stock levels accurate
- [ ] Product images display
- [ ] Barcode scanning works (if applicable)
- [ ] Transfer buttons functional

### □ **Consignments Module**
- [ ] Lightspeed sync status shown
- [ ] Transfer buttons work
- [ ] AI insights load (if applicable)
- [ ] Status badges correct

### □ **Reports Module**
- [ ] Charts render correctly
- [ ] Data export works
- [ ] Date pickers functional
- [ ] Filters apply correctly

---

## 🧪 TESTING PROTOCOL

### □ **Unit Testing**
- [ ] PHP functions tested
- [ ] JavaScript functions tested
- [ ] Edge cases covered
- [ ] Error conditions tested

### □ **Integration Testing**
- [ ] Page loads correctly
- [ ] AJAX calls work
- [ ] Navigation works
- [ ] Forms submit correctly

### □ **Browser Testing**
- [ ] Chrome (latest)
- [ ] Firefox (latest)
- [ ] Safari (latest)
- [ ] Edge (latest)
- [ ] Mobile browsers

### □ **Device Testing**
- [ ] Desktop (1920×1080)
- [ ] Laptop (1366×768)
- [ ] Tablet (768×1024)
- [ ] Mobile (375×667)

---

## 📤 DEPLOYMENT CHECKLIST

### □ **Pre-Deployment**
- [ ] All tests pass
- [ ] Code reviewed
- [ ] Documentation updated
- [ ] Backup created

### □ **Deployment**
- [ ] Deployed to staging first
- [ ] Smoke tests passed on staging
- [ ] Performance tested
- [ ] No console errors

### □ **Post-Deployment**
- [ ] Production smoke tests
- [ ] Error monitoring checked
- [ ] User feedback collected
- [ ] Documentation published

---

## 🆘 ROLLBACK PLAN

### □ **If Issues Found**
- [ ] Backup files still available
- [ ] Rollback procedure documented
- [ ] Team notified
- [ ] Issues logged

### □ **Rollback Steps**
1. Restore backup file
2. Clear cache
3. Test rollback
4. Notify users (if needed)
5. Document what went wrong

---

## 📞 SUPPORT CONTACTS

- **Development Team Lead:** [Name]
- **VapeUltra Documentation:** `/modules/base/templates/vape-ultra-complete/`
- **Design System:** `DESIGN_SYSTEM.md`
- **Usage Examples:** `USAGE_EXAMPLES.md`
- **Quick Reference:** `QUICK_REFERENCE.md`

---

## 🎉 COMPLETION

When all checkboxes are ticked:

✅ **Page is production-ready!**

Deploy with confidence! 🚀

---

_Last Updated: 2025-11-12_
