# 🚀 Design Studio - Quick Testing Reference Card

## 📍 ACCESS URL
```
https://staff.vapeshed.co.nz/modules/admin-ui/design-studio.php
```

---

## ⚡ QUICK TEST SEQUENCE (5 Minutes)

### 1️⃣ OPEN & VERIFY (30 seconds)
- [ ] Open URL in Chrome
- [ ] Press **F12** (open DevTools)
- [ ] Click **Console** tab
- [ ] ✅ Should see NO red errors
- [ ] ✅ Should see 4 panels (sidebar, control, editor, preview)

### 2️⃣ TEST EDITOR (1 minute)
- [ ] In Monaco editor (center panel), type:
  ```html
  <h1>TEST</h1>
  ```
- [ ] ✅ Preview updates instantly (right side)
- [ ] ✅ Syntax highlighting works (colorful code)
- [ ] Click **CSS** tab, type:
  ```css
  h1 { color: red; }
  ```
- [ ] ✅ "TEST" heading turns red in preview

### 3️⃣ TEST SMART COLORS (1 minute)
- [ ] Look at left control panel
- [ ] See 3 color pickers (Primary, Secondary, Accent)
- [ ] Click **"Smart Color Harmony"** button
- [ ] ✅ All 3 colors change
- [ ] ✅ Colors look good together (not random/ugly)
- [ ] Click button again (5 times)
- [ ] ✅ Each time generates new harmonious colors

### 4️⃣ TEST VIEW MODES (1 minute)
- [ ] Top-right of preview area, see 3 buttons
- [ ] Click **"Stage"** button
  - [ ] ✅ Component on purple gradient (spotlight effect)
- [ ] Click **"In Context"** button
  - [ ] ✅ Component in white page layout
- [ ] Click **"Responsive"** button
  - [ ] ✅ 3 device frames appear (phone/tablet/desktop)
  - [ ] ✅ All show same component at different sizes

### 5️⃣ TEST SLIDERS (1 minute)
- [ ] Drag **Border Radius** slider left/right
  - [ ] ✅ Component corners change (sharp → rounded)
- [ ] Drag **Shadow Depth** slider
  - [ ] ✅ Component shadow changes (flat → floating)
- [ ] Drag **Spacing Density** slider
  - [ ] ✅ Component spacing changes (compact → spacious)

### 6️⃣ TEST SAVE (30 seconds)
- [ ] Click **"Save All"** button (top-right)
- [ ] Watch DevTools Console
- [ ] ✅ Should see AJAX POST request
- [ ] ✅ Should see success response
- [ ] Check filesystem:
  ```bash
  cat config/active-theme.json
  ```
- [ ] ✅ File exists with your theme data

---

## 🐛 COMMON ISSUES & FIXES

### Issue: "Monaco editor not loading"
**Symptoms:** Blank white box instead of code editor
**Check:** DevTools Console → Look for "Monaco" errors
**Fix:** Check internet connection (Monaco loads from CDN)

### Issue: "Preview not updating"
**Symptoms:** Type in editor, nothing changes in preview
**Check:** DevTools Console → JavaScript errors?
**Fix:** Hard refresh (Ctrl+Shift+R)

### Issue: "Colors all the same after Smart Harmony"
**Symptoms:** Primary/Secondary/Accent all identical
**Check:** HSL algorithm in Console
**Fix:** Already fixed in latest version (v5.0.0)

### Issue: "Save button doesn't work"
**Symptoms:** Click Save, nothing happens
**Check:** DevTools Network tab → POST request sent?
**Fix:** Check file permissions (see below)

### Issue: "Device frames don't appear"
**Symptoms:** Responsive mode shows blank
**Check:** Browser zoom level (should be 100%)
**Fix:** Reset zoom (Ctrl+0)

---

## 🔧 FILE PERMISSIONS FIX

If save fails, check permissions:
```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/admin-ui
chmod 755 config css/custom components css-versions
chmod 644 design-studio.php
```

---

## 📊 WHAT TO LOOK FOR IN DEVTOOLS

### ✅ GOOD (Console Tab)
```
Monaco Editor loaded successfully
Theme applied
Preview refreshed
```

### ❌ BAD (Console Tab - Red Text)
```
Uncaught ReferenceError: monaco is not defined
Failed to fetch
404 Not Found
```

### ✅ GOOD (Network Tab)
```
Status: 200 (green)
Type: XHR
File: design-studio.php?action=save_theme
Response: {"success": true}
```

### ❌ BAD (Network Tab)
```
Status: 500 (red) - Server error
Status: 403 (red) - Permission denied
Status: 404 (red) - File not found
```

---

## 🎯 SUCCESS CRITERIA

Design Studio is **WORKING** if:
- ✅ Page loads without console errors
- ✅ Monaco editor displays code
- ✅ Typing in editor updates preview instantly
- ✅ Smart Color Harmony generates good colors
- ✅ All 3 view modes work (Stage/Context/Responsive)
- ✅ Sliders change component appearance
- ✅ Save button creates files on server

Design Studio is **BROKEN** if:
- ❌ Console shows red errors
- ❌ Monaco editor blank/not loading
- ❌ Preview doesn't update when typing
- ❌ Responsive mode doesn't show devices
- ❌ Save button fails silently

---

## 🚀 BROWSER TEST CHECKLIST

### Desktop Browsers
- [ ] **Chrome 90+** (primary)
- [ ] Firefox 88+
- [ ] Safari 14+ (macOS)
- [ ] Edge 90+

### Mobile Browsers
- [ ] Chrome Android
- [ ] Safari iOS
- [ ] Samsung Internet

### Screen Sizes
- [ ] 1920x1080 (desktop)
- [ ] 1366x768 (laptop)
- [ ] 768x1024 (tablet)
- [ ] 375x667 (phone)

---

## 📞 QUICK COMMANDS

### Check logs
```bash
tail -f /home/master/applications/jcepnzzkmj/public_html/modules/admin-ui/logs/*.log
```

### Check saved theme
```bash
cat /home/master/applications/jcepnzzkmj/public_html/modules/admin-ui/config/active-theme.json | jq
```

### Check generated CSS
```bash
cat /home/master/applications/jcepnzzkmj/public_html/modules/admin-ui/css/custom/generated-theme.css
```

### List versions
```bash
ls -lt /home/master/applications/jcepnzzkmj/public_html/modules/admin-ui/css-versions/ | head -10
```

### Run automated tests
```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/admin-ui
./test-design-studio.sh
```

---

## 🎨 TEST SCENARIOS

### Scenario 1: "Make a Purple Theme"
1. Click Smart Color Harmony until you get purple primary
2. Adjust Secondary to lighter purple
3. Set Accent to gold/yellow (complementary)
4. Increase Shadow Depth to 3 (dramatic)
5. Set Border Radius to 1rem (rounded)
6. Click Save All
7. ✅ Theme should persist on refresh

### Scenario 2: "Design a Button"
1. Clear HTML editor, type:
   ```html
   <button class="btn">Click Me</button>
   ```
2. Switch to CSS tab, type:
   ```css
   .btn {
     background: var(--cis-primary);
     color: white;
     padding: 1rem 2rem;
     border: none;
     border-radius: var(--border-radius);
     cursor: pointer;
   }
   .btn:hover {
     transform: scale(1.05);
   }
   ```
3. Switch to Stage view
4. Hover button in preview
5. ✅ Button should scale up on hover

### Scenario 3: "Test Responsive"
1. Design a card component (HTML + CSS)
2. Switch to Responsive view
3. Add media queries:
   ```css
   @media (max-width: 768px) {
     .card { width: 100%; }
   }
   ```
4. ✅ Should see different layouts on phone/tablet/desktop

---

## 📋 SIGN-OFF CHECKLIST

Before declaring "READY FOR PRODUCTION":

- [ ] ✅ Automated tests pass (./test-design-studio.sh)
- [ ] ✅ Manual browser test complete (5-minute sequence above)
- [ ] ✅ No console errors in Chrome
- [ ] ✅ Monaco editor loads and works
- [ ] ✅ All 3 view modes functional
- [ ] ✅ Smart colors generate harmonious palettes
- [ ] ✅ Save functionality works
- [ ] ✅ Files created on server (config/active-theme.json)
- [ ] ✅ Preview updates in real-time
- [ ] ✅ Sliders control appearance
- [ ] ✅ Tested on at least 2 browsers
- [ ] ✅ Tested on desktop (1920x1080)
- [ ] ✅ No broken UI elements
- [ ] ✅ Responsive mode shows 3 devices
- [ ] ✅ Can complete full workflow (edit → preview → save)

**Signed off by:** __________________
**Date:** __________________
**Status:** ⬜ PASS  ⬜ FAIL

---

## 🎉 AFTER SUCCESSFUL TESTING

What to do next:
1. **Document any bugs found** (add to GitHub Issues)
2. **Gather user feedback** (show to 2-3 users)
3. **Plan Phase 2 features**:
   - AI Copilot backend connection
   - Component library browser
   - Version control UI
   - Gradient editor
   - Animation timeline
4. **Create training materials** (video tutorial)
5. **Deploy to production** (if not already live)

---

**Last Updated:** October 31, 2025
**Version:** 5.0.0
**Status:** Ready for Testing ✅
