# 🧪 Design Studio - Comprehensive Testing Plan

## 🎯 Test Objectives
1. Verify all UI components render correctly
2. Test Monaco editor functionality
3. Validate live preview system (all 3 modes)
4. Test smart color generation algorithm
5. Verify save/load functionality
6. Test responsive device frames
7. Validate AI Copilot UI
8. Check for JavaScript errors
9. Test cross-browser compatibility

---

## ✅ PRE-FLIGHT CHECKLIST

### Access & Initial Load
```
URL: https://staff.vapeshed.co.nz/modules/admin-ui/design-studio.php
```

- [ ] **Page loads without errors**
- [ ] **No console errors in browser DevTools** (F12)
- [ ] **All 4 panels visible** (tool sidebar, control panel, editor, preview)
- [ ] **Monaco editor loads** (see code editor with syntax highlighting)
- [ ] **Preview area shows default content** (CIS card component)

---

## 🧪 TEST SUITE 1: UI LAYOUT & RENDERING

### 1.1 - Header Navigation
- [ ] Logo displays: "CIS Design Studio" with palette icon
- [ ] "Save All" button visible and clickable
- [ ] "Export" button visible and clickable
- [ ] "AI Copilot" button visible and clickable
- [ ] Gradient background renders (purple-blue)

### 1.2 - Tool Sidebar (Left Panel, 60px wide)
- [ ] **5 tool icons visible**:
  - [ ] 🎨 Theme Builder icon
  - [ ] 🧩 Components icon
  - [ ] 📜 Version Control icon
  - [ ] 📱 Responsive icon
  - [ ] 🎯 CSS Editor icon
- [ ] Icons have hover effect (scale up, glow)
- [ ] Clicking each icon shows/hides corresponding panel

### 1.3 - Control Panel (400px wide)
- [ ] **Theme Controls visible** (default state):
  - [ ] Primary color picker
  - [ ] Secondary color picker
  - [ ] Accent color picker
  - [ ] Each has paired text input (hex value)
  - [ ] "Smart Color Harmony" button present
- [ ] **Typography selects**:
  - [ ] Heading font dropdown (15 Google Fonts)
  - [ ] Body font dropdown (15 Google Fonts)
- [ ] **Sliders render correctly**:
  - [ ] Border Radius slider (0-2rem)
  - [ ] Spacing Density slider (0.75x-1.5x)
  - [ ] Shadow Depth slider (0-3)
  - [ ] All have visible labels and value displays

### 1.4 - Code Editor Panel (Monaco)
- [ ] Editor container fills available space
- [ ] **3 tabs visible**: HTML | CSS | JavaScript
- [ ] Default HTML code visible (cis-card component)
- [ ] Syntax highlighting works (colored code)
- [ ] Dark theme applied (VS Code style)
- [ ] Line numbers visible
- [ ] Can type and edit code

### 1.5 - Preview Panel (Right side, 50% width)
- [ ] Preview toolbar visible
- [ ] **3 view mode buttons**: Stage | In Context | Responsive
- [ ] "Refresh Preview" button visible
- [ ] Default view is "Stage" mode
- [ ] Component renders in preview area

---

## 🧪 TEST SUITE 2: MONACO EDITOR FUNCTIONALITY

### 2.1 - Editor Initialization
- [ ] Monaco editor loads without errors
- [ ] Default HTML code displays:
  ```html
  <div class="cis-card">
      <div class="cis-card-header">
          <h3>Sample Component</h3>
      </div>
      <div class="cis-card-body">
          <p>Edit this component...</p>
      </div>
  </div>
  ```
- [ ] Syntax highlighting active (tags = blue, attributes = yellow)

### 2.2 - Tab Switching
- [ ] Click "HTML" tab → Shows HTML code
- [ ] Click "CSS" tab → Editor switches to CSS mode
- [ ] Click "JavaScript" tab → Editor switches to JS mode
- [ ] Active tab has purple highlight
- [ ] Syntax highlighting changes per language

### 2.3 - Live Editing
- [ ] **Test: Type in HTML tab**
  - [ ] Add: `<p>Test paragraph</p>`
  - [ ] Preview updates IMMEDIATELY (no manual refresh)
  - [ ] See new paragraph in preview area
- [ ] **Test: Edit CSS tab**
  - [ ] Add: `.cis-card { border: 3px solid red; }`
  - [ ] Preview updates instantly
  - [ ] Card border turns red
- [ ] **Test: Edit JS tab**
  - [ ] Add: `console.log('Test');`
  - [ ] Check browser console → See log message

### 2.4 - Editor Features
- [ ] Auto-complete works (type `<div` → Suggests closing tag)
- [ ] Bracket matching (click `{` → Highlights matching `}`)
- [ ] Multi-cursor (Ctrl+Click → Multiple cursors)
- [ ] Undo/Redo (Ctrl+Z / Ctrl+Y)
- [ ] Find/Replace (Ctrl+F)
- [ ] Code folding (collapse/expand blocks)

---

## 🧪 TEST SUITE 3: LIVE PREVIEW SYSTEM

### 3.1 - Stage Mode (Presentation)
- [ ] Click "Stage" view mode button
- [ ] Component appears **centered**
- [ ] **Purple gradient background** visible (667eea → 764ba2)
- [ ] Component has **elevation shadow**
- [ ] **Entrance animation** plays (fade + scale)
- [ ] Component looks "spotlit" (like car reveal)

**Test Real-Time Updates:**
- [ ] Edit HTML → Stage updates immediately
- [ ] Change colors → Stage reflects new theme
- [ ] Adjust border radius → Stage component updates

### 3.2 - Context Mode (In-Page)
- [ ] Click "In Context" view mode button
- [ ] Component appears in **page layout**
- [ ] Max-width container visible (1200px)
- [ ] White background (not gradient)
- [ ] Scrollable if content exceeds height
- [ ] Looks like "real CIS page"

**Test Real-Time Updates:**
- [ ] Edit HTML → Context view updates
- [ ] Add more content → Scroll appears if needed
- [ ] Theme changes → Context view reflects changes

### 3.3 - Responsive Mode (Multi-Device)
- [ ] Click "Responsive" view mode button
- [ ] **THREE device frames appear**:
  - [ ] **Phone** (375px wide)
    - [ ] Device header shows "iPhone 12 Pro - 375px"
    - [ ] Component renders at mobile size
    - [ ] Frame has phone-like appearance
  - [ ] **Tablet** (768px wide)
    - [ ] Device header shows "iPad - 768px"
    - [ ] Component renders at tablet size
    - [ ] Frame has tablet-like appearance
  - [ ] **Desktop** (1920px wide)
    - [ ] Device header shows "Desktop - 1920px"
    - [ ] Component renders at desktop size
    - [ ] Frame has monitor-like appearance

**Test Simultaneous Updates:**
- [ ] Edit HTML → All 3 devices update at once
- [ ] Change CSS → All 3 devices reflect changes
- [ ] See component behavior at different breakpoints

### 3.4 - Manual Refresh
- [ ] Click "Refresh Preview" button
- [ ] All active preview modes reload
- [ ] No errors or flashing

---

## 🧪 TEST SUITE 4: SMART COLOR GENERATION

### 4.1 - Initial State
- [ ] Primary color picker shows a color
- [ ] Secondary color picker shows a color
- [ ] Accent color picker shows a color
- [ ] All hex text inputs match color pickers

### 4.2 - Smart Harmony Algorithm
- [ ] Click **"Smart Color Harmony"** button
- [ ] **All 3 color pickers update** to new colors
- [ ] **All 3 hex inputs update** to match
- [ ] Colors look **harmonious** (not random/clashing)
- [ ] **Primary ≠ Secondary** (different colors)
- [ ] Colors are **vibrant** (not washed out)

**Test Color Theory:**
- [ ] Click Smart Harmony **5 times**
- [ ] Each time generates **different base hue**
- [ ] Secondary is always **opposite** primary (complementary)
- [ ] Accent is always **near** primary (analogous)
- [ ] No ugly color combinations

### 4.3 - Manual Color Changes
- [ ] Click primary color picker → Choose new color
- [ ] Hex input updates immediately
- [ ] Preview updates with new color
- [ ] Type hex value (e.g., `#ff0000`) in text input
- [ ] Color picker updates to match
- [ ] Preview updates with typed color

### 4.4 - Theme Application
- [ ] Change primary color → Preview uses new primary
- [ ] Change secondary color → Preview uses new secondary
- [ ] Change accent color → Preview uses new accent
- [ ] All CSS variables update (`:root` injection)

---

## 🧪 TEST SUITE 5: TYPOGRAPHY CONTROLS

### 5.1 - Heading Font
- [ ] Click "Heading Font" dropdown
- [ ] See 15 Google Fonts:
  - [ ] Inter
  - [ ] Roboto
  - [ ] Poppins
  - [ ] Montserrat
  - [ ] Open Sans
  - [ ] Lato
  - [ ] Raleway
  - [ ] Playfair Display
  - [ ] Merriweather
  - [ ] Source Sans Pro
  - [ ] Nunito
  - [ ] Work Sans
  - [ ] DM Sans
  - [ ] Plus Jakarta Sans
  - [ ] Space Grotesk
- [ ] Select different font (e.g., Poppins)
- [ ] Preview updates → Headings use new font

### 5.2 - Body Font
- [ ] Click "Body Font" dropdown
- [ ] See same 15 Google Fonts
- [ ] Select different font (e.g., Inter)
- [ ] Preview updates → Body text uses new font

### 5.3 - Font Loading
- [ ] Fonts load from Google Fonts CDN
- [ ] No "flash of unstyled text" (FOUT)
- [ ] Fonts render smoothly

---

## 🧪 TEST SUITE 6: SLIDER CONTROLS

### 6.1 - Border Radius Slider
- [ ] Drag slider from left (0rem) to right (2rem)
- [ ] Value label updates (e.g., "0.75rem")
- [ ] Preview component's border radius changes live
- [ ] **Test extremes**:
  - [ ] 0rem → Sharp corners (90° angles)
  - [ ] 2rem → Very rounded corners (pill-like)

### 6.2 - Spacing Density Slider
- [ ] Drag slider from 0.75x to 1.5x
- [ ] Value label updates (e.g., "1.0x")
- [ ] Preview component spacing changes (padding/margins)
- [ ] **Test extremes**:
  - [ ] 0.75x → Compact (tight spacing)
  - [ ] 1.5x → Spacious (loose spacing)

### 6.3 - Shadow Depth Slider
- [ ] Drag slider from 0 to 3
- [ ] Value label updates (e.g., "2")
- [ ] Preview component shadow depth changes
- [ ] **Test extremes**:
  - [ ] 0 → No shadow (flat)
  - [ ] 3 → Deep shadow (floating effect)

---

## 🧪 TEST SUITE 7: TOOL SIDEBAR SWITCHING

### 7.1 - Theme Builder Tool
- [ ] Click 🎨 Theme Builder icon
- [ ] Control panel shows theme controls
- [ ] Color pickers visible
- [ ] Typography selects visible
- [ ] Sliders visible

### 7.2 - Components Tool
- [ ] Click 🧩 Components icon
- [ ] Control panel switches to component browser
- [ ] (Currently empty - TODO: Load components)

### 7.3 - Version Control Tool
- [ ] Click 📜 Version Control icon
- [ ] Control panel switches to version history
- [ ] (Currently empty - TODO: Show versions)

### 7.4 - Responsive Tool
- [ ] Click 📱 Responsive icon
- [ ] Automatically switches preview to Responsive mode
- [ ] 3 device frames appear

### 7.5 - CSS Editor Tool
- [ ] Click 🎯 CSS Editor icon
- [ ] Control panel switches to CSS file tree
- [ ] (Currently empty - TODO: File browser)

---

## 🧪 TEST SUITE 8: SAVE FUNCTIONALITY

### 8.1 - Save All Button
- [ ] Make changes (colors, fonts, code)
- [ ] Click "Save All" button in header
- [ ] Check browser console for success message
- [ ] Verify AJAX POST to server
- [ ] Check server response (should be JSON success)

### 8.2 - File System Verification
**SSH into server and check files:**
```bash
cd /home/master/applications/jcepnzzkmj/public_html/modules/admin-ui

# Check theme saved
cat config/active-theme.json

# Check CSS generated
cat css/custom/generated-theme.css

# Check version created
ls -la css-versions/
```

- [ ] `config/active-theme.json` exists and updated
- [ ] `css/custom/generated-theme.css` exists
- [ ] New version file in `css-versions/` directory
- [ ] JSON files are valid (no syntax errors)

---

## 🧪 TEST SUITE 9: AI COPILOT UI

### 9.1 - Panel Visibility
- [ ] Click "AI Copilot" button in header
- [ ] Floating panel appears (bottom-right)
- [ ] Panel is 400px wide
- [ ] Has gradient header (purple-blue)
- [ ] Close button visible (X)

### 9.2 - Chat Interface
- [ ] Message history area visible (300px height)
- [ ] Scrollable if messages overflow
- [ ] Input field at bottom
- [ ] Placeholder text: "Ask AI for help..."

### 9.3 - Sending Messages
- [ ] Type message: "Make the button bigger"
- [ ] Press Enter
- [ ] User message appears in chat
- [ ] Loading indicator shows (TODO: Implement)
- [ ] (AI response is placeholder - backend not connected yet)

### 9.4 - Close Panel
- [ ] Click X button
- [ ] Panel closes with smooth animation
- [ ] Click "AI Copilot" button again
- [ ] Panel reopens (previous messages preserved)

---

## 🧪 TEST SUITE 10: BROWSER COMPATIBILITY

### 10.1 - Chrome/Chromium (Primary)
- [ ] All tests above pass
- [ ] Monaco editor loads
- [ ] No console errors
- [ ] Smooth animations

### 10.2 - Firefox
- [ ] Page loads correctly
- [ ] Monaco editor works
- [ ] Color pickers functional
- [ ] Preview updates work

### 10.3 - Safari (macOS)
- [ ] Layout renders correctly
- [ ] Monaco editor loads
- [ ] Font loading works
- [ ] CSS Grid/Flexbox work

### 10.4 - Mobile Browser (Responsive)
- [ ] Open on phone (viewport < 768px)
- [ ] Layout adapts (columns stack?)
- [ ] Touch interactions work
- [ ] Monaco editor usable (zoom, scroll)

---

## 🧪 TEST SUITE 11: PERFORMANCE

### 11.1 - Page Load Speed
- [ ] Open DevTools Network tab
- [ ] Refresh page
- [ ] **Page load time < 3 seconds**
- [ ] Monaco loads within 2 seconds
- [ ] No blocking resources

### 11.2 - Live Preview Performance
- [ ] Type rapidly in editor (fast keystrokes)
- [ ] Preview updates smoothly (no lag)
- [ ] No frame drops or stuttering
- [ ] CPU usage reasonable (< 80%)

### 11.3 - Memory Usage
- [ ] Open Chrome Task Manager (Shift+Esc)
- [ ] Check memory usage
- [ ] **Should be < 150MB**
- [ ] No memory leaks after 10 minutes

---

## 🧪 TEST SUITE 12: ERROR HANDLING

### 12.1 - Invalid CSS
- [ ] Switch to CSS tab
- [ ] Type invalid CSS: `.test { color: INVALID; }`
- [ ] Check console for errors
- [ ] Preview should handle gracefully (not crash)

### 12.2 - Invalid HTML
- [ ] Switch to HTML tab
- [ ] Type unclosed tag: `<div>`
- [ ] Preview renders what it can
- [ ] No JavaScript errors

### 12.3 - Save Failure
- [ ] Disconnect internet (simulate failure)
- [ ] Click "Save All"
- [ ] Should show error message
- [ ] User is notified (not silent fail)

---

## 🧪 TEST SUITE 13: KEYBOARD SHORTCUTS

### 13.1 - Standard Shortcuts
- [ ] **Ctrl+S** → Triggers Save All
- [ ] **Ctrl+/** → Comments code
- [ ] **Ctrl+D** → Duplicates line
- [ ] **Alt+Up/Down** → Moves line up/down
- [ ] **Ctrl+Enter** → Refreshes preview

### 13.2 - Monaco Shortcuts
- [ ] **Ctrl+Space** → Shows auto-complete
- [ ] **Ctrl+F** → Opens find
- [ ] **Ctrl+H** → Opens find/replace
- [ ] **F2** → Rename symbol

---

## 🐛 BUG TRACKING

### Critical Issues (P0 - Must Fix)
- [ ] None found

### High Priority (P1 - Should Fix)
- [ ]

### Medium Priority (P2 - Nice to Fix)
- [ ]

### Low Priority (P3 - Future Enhancement)
- [ ]

---

## ✅ FINAL CHECKLIST

Before declaring "READY FOR PRODUCTION":

- [ ] All P0 bugs fixed
- [ ] All P1 bugs fixed or documented
- [ ] Performance benchmarks met
- [ ] Cross-browser tested
- [ ] Save functionality works
- [ ] Preview modes all functional
- [ ] No console errors in Chrome
- [ ] Documentation complete
- [ ] User can complete full workflow (design → preview → save)

---

## 🎯 TEST RESULTS SUMMARY

**Date Tested:** _________________
**Tested By:** _________________
**Browser:** _________________
**OS:** _________________

**Overall Status:**
- [ ] ✅ All tests pass - PRODUCTION READY
- [ ] ⚠️ Minor issues - READY with notes
- [ ] ❌ Major issues - NEEDS WORK

**Notes:**
```
(Add any observations, bugs found, or suggestions here)
```

---

## 🚀 NEXT STEPS AFTER TESTING

Once all tests pass:

1. **Fix any P0/P1 bugs found**
2. **Document known issues** (P2/P3 bugs)
3. **Connect AI backend** (real API endpoint)
4. **Load component library** (integrate with components/ directory)
5. **Add version control UI** (show history, rollback)
6. **Create unified navigation** (link all 3 tools)
7. **Deploy to production** (if not already)
8. **Create user training video** (5-minute demo)
9. **Gather user feedback** (what works, what doesn't)
10. **Plan Phase 2 features** (gradient editor, animations, etc.)

---

**Testing Tip:** Use Chrome DevTools (F12) throughout testing. Check:
- **Console** - For JavaScript errors
- **Network** - For failed requests
- **Performance** - For slow operations
- **Application** - For localStorage/session data
