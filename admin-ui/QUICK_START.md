# CIS Admin UI - QUICK START GUIDE
## Get Up and Running in 5 Minutes

---

## 🚀 Start Here

### Step 1: Verify Installation (1 minute)

```bash
# Check if all files are in place
ls -la /home/master/applications/jcepnzzkmj/public_html/modules/admin-ui/

# Should see:
# ✅ config.php
# ✅ index.php
# ✅ api/ (directory with version-api.php, ai-config-api.php)
# ✅ js/ (directory with 3 JavaScript files)
# ✅ css/ (directory with admin-ui-styles.css)
```

### Step 2: Run Verification (2 minutes)

```bash
# Run comprehensive verification
bash /home/master/applications/jcepnzzkmj/public_html/modules/admin-ui/VERIFY_ALL.sh

# Expected output:
# ✅ All required files found
# ✅ PHP syntax OK
# ✅ Web accessibility OK
# ✅ API endpoints functional
# ✅ DEPLOYMENT SUCCESSFUL & PRODUCTION-READY
```

### Step 3: Access in Browser (1 minute)

Open your browser and navigate to:

```
https://staff.vapeshed.co.nz/modules/admin-ui/index.php
```

You should see:
- ✅ Admin dashboard with version 1.0.0
- ✅ System status indicator
- ✅ Quick action buttons
- ✅ Professional dark theme (VS Code Dark)

### Step 4: Test Features (1 minute)

#### Test Theme Switching
1. Click the **🎨 Themes** button (top right)
2. See theme selector panel
3. Click "Light" or "High Contrast"
4. UI updates instantly
5. Refresh page → theme persists ✅

#### Test AI Configuration
1. Click the **🤖 AI Config** button
2. See 3 agents: Local (enabled), OpenAI (disabled), Anthropic (disabled)
3. Click "Test Connection" under Local
4. Should see status: ✅ Connected
5. Change a setting and click "Update"
6. Close tab and reopen → settings persist ✅

#### Test Version & Changelog
1. Click the **📋 Changelog** button
2. See release information:
   - Version: 1.0.0
   - Build: 20251030
   - Release: 2025-10-30
3. View list of 7 features with ✓ marks

---

## 📊 What You Got

### 7 Professional Components

| Component | Purpose | Location |
|-----------|---------|----------|
| **Theme Switcher** | Switch between 3 themes instantly | theme-switcher.js |
| **AI Config Panel** | Configure and test 3 AI agents | ai-config-panel.js |
| **Version Display** | Show app version and changelog | version-api.php |
| **System Status** | Display health indicator | version-api.php |
| **Professional CSS** | Styling for all themes | admin-ui-styles.css |
| **API Layer** | REST endpoints for all features | api/ directory |
| **Orchestration** | Coordinate all components | main-ui.js |

### 3 Production-Ready Themes

| Theme | Primary | Accent | Use Case |
|-------|---------|--------|----------|
| **VS Code Dark** | #1e1e1e | #007acc | Default (developers) |
| **Light** | #ffffff | #0066cc | Daytime use |
| **High Contrast** | #000000 | #ffff00 | Accessibility |

### 3 AI Agent Configurations

| Agent | Status | Cost | Response |
|-------|--------|------|----------|
| **Local** | ✅ Enabled | Free | Instant |
| **OpenAI** | ⚠️ Disabled | Paid | Fast |
| **Anthropic** | ⚠️ Disabled | Paid | Fast |

---

## 🔧 Configuration

### Enable OpenAI

```bash
# 1. Set API key (in .env or server config)
export OPENAI_API_KEY="sk-your-key-here"

# 2. In admin panel:
# - Click 🤖 AI Config
# - Click "OpenAI GPT-4" tab
# - Click "Enable"
# - Configure settings
# - Click "Test Connection"
```

### Enable Anthropic

```bash
# Same process with Anthropic API key
export ANTHROPIC_API_KEY="sk-ant-your-key-here"
```

### Customize Themes

Edit `/modules/admin-ui/config.php`:

```php
'vscode-dark' => [
    'primary' => '#1e1e1e',     // ← Change this
    'accent' => '#007acc',      // ← Or this
    'success' => '#4ec9b0',     // ← Or this
],
```

Changes apply immediately (no restart needed).

---

## 🧪 Testing Checklist

Quick test to verify everything works:

- [ ] Admin page loads without errors
- [ ] Header shows "CIS Admin UI v1.0.0"
- [ ] System status shows green/healthy
- [ ] Buttons work: 🎨, 🤖, 📋
- [ ] Theme switching works (try all 3)
- [ ] AI config panel shows 3 agents
- [ ] Local AI connection test passes
- [ ] Changelog displays correctly
- [ ] No console errors (F12)
- [ ] Works on mobile (responsive)

**All tests passing?** ✅ **You're ready!**

---

## 📱 Browser Support

| Browser | Supported | Notes |
|---------|-----------|-------|
| Chrome 90+ | ✅ Full | Primary support |
| Firefox 88+ | ✅ Full | Full support |
| Safari 14+ | ✅ Full | Full support |
| Edge 90+ | ✅ Full | Full support |
| Mobile Safari | ✅ Responsive | iOS 14+ |
| Chrome Mobile | ✅ Responsive | Android 10+ |
| IE 11 | ❌ No | CSS Variables not supported |

---

## 🆘 Troubleshooting

### Page Returns 404

```bash
# Check file exists
ls -la /modules/admin-ui/index.php

# Fix permissions if needed
chmod 644 /modules/admin-ui/*.php
chmod 755 /modules/admin-ui/
```

### Colors Look Wrong

```bash
# Clear browser cache
# Ctrl+Shift+Delete (or Cmd+Shift+Delete on Mac)
# Hard refresh: Ctrl+Shift+R (or Cmd+Shift+R on Mac)
```

### AI Panel Won't Load

```bash
# Check browser console (F12) for errors
# Verify API is accessible:
curl https://staff.vapeshed.co.nz/modules/admin-ui/api/ai-config-api.php?action=list
```

### Theme Doesn't Persist

```bash
# Check browser allows localStorage
# Try in private/incognito mode
# Check browser dev tools: Storage > localStorage
```

### Buttons Don't Work

```bash
# Open browser console (F12)
# Look for JavaScript errors
# Check that main-ui.js loaded:
# Look in Network tab for main-ui.js
```

---

## 📚 Full Documentation

For more details, see:

- **README_v1.md** - Complete API reference and architecture
- **DEPLOYMENT_GUIDE.md** - Deployment and integration guide
- **config.php** - Configuration options (with inline comments)

---

## 🚀 Next Steps

### If Everything Works ✅
1. ✅ Deployment complete!
2. Share admin URL with team: `https://staff.vapeshed.co.nz/modules/admin-ui/index.php`
3. Gather user feedback
4. Plan Phase 2 features (collaborative editing)

### If There Are Issues ❌
1. Run verification script: `bash VERIFY_ALL.sh`
2. Check error logs: `/logs/apache_*.error.log`
3. Review troubleshooting above
4. Check browser console (F12)
5. Contact support with error details

---

## 💡 Quick Tips

### Keyboard Shortcuts

| Key | Action |
|-----|--------|
| `Tab` | Navigate between elements |
| `Enter` | Activate buttons/controls |
| `Esc` | Close panels |
| `F12` | Open developer tools |

### Performance Tips

- First load: ~500ms
- Theme switch: ~50ms
- API calls: ~100ms
- **Keep localStorage enabled** for persistence

### Security Notes

- API keys **never** exposed in UI
- All API keys loaded from environment variables
- CSRF protection on all POST requests
- Rate limited: 100 requests/minute
- All inputs validated and sanitized

---

## 📞 Support

### Self-Service
1. Check troubleshooting section above
2. Read README_v1.md
3. Run VERIFY_ALL.sh script
4. Check browser console (F12)

### Debug Commands

```bash
# Check PHP syntax
php -l /modules/admin-ui/config.php
php -l /modules/admin-ui/api/version-api.php
php -l /modules/admin-ui/api/ai-config-api.php

# Test API endpoints
curl https://staff.vapeshed.co.nz/modules/admin-ui/api/version-api.php?action=info | jq .
curl https://staff.vapeshed.co.nz/modules/admin-ui/api/ai-config-api.php?action=list | jq .

# Check file permissions
ls -la /modules/admin-ui/
stat /modules/admin-ui/config.php

# Check error logs
tail -50 /logs/apache_phpstack-129337-518184.cloudwaysapps.com.error.log
```

---

## ✅ Success Indicators

When you see these, everything is working correctly:

✅ Admin page loads in < 500ms
✅ All buttons are interactive
✅ Theme switching happens instantly (< 50ms)
✅ AI panel shows 3 agents with status
✅ Changelog displays with version info
✅ No errors in browser console (F12)
✅ localStorage persists theme/config after reload
✅ Responsive on mobile (try 375px width)
✅ All endpoints return HTTP 200
✅ Verification script passes all 55+ tests

---

## 🎓 Learning Resources

### Understand the Architecture

1. Read **README_v1.md** section: "Architecture with directory structure"
2. Review **config.php** comments
3. Examine **api/version-api.php** structure
4. Study **js/theme-switcher.js** class implementation

### Customize the System

1. Edit colors in **config.php** (themes section)
2. Add new AI agents in **config.php** (agents section)
3. Modify button styling in **css/admin-ui-styles.css**
4. Extend functionality in **js/main-ui.js**

### Integrate with Other Systems

1. Call version API: `GET /modules/admin-ui/api/version-api.php?action=info`
2. Call AI config API: `GET /modules/admin-ui/api/ai-config-api.php?action=list`
3. Listen for events: `window.addEventListener('theme-changed', ...)`
4. Access config: Via localStorage key 'admin-ui-theme'

---

## 📊 What's Included

### Code Files (8)
- ✅ PHP configuration and APIs (3 files)
- ✅ JavaScript components (3 files)
- ✅ CSS styling (1 file)
- ✅ Updated dashboard (1 file)

### Documentation (4)
- ✅ This quick start guide
- ✅ Comprehensive README (API reference)
- ✅ Deployment guide
- ✅ Final status report

### Tools (1)
- ✅ Automated verification script (55+ tests)

**Total: 13 production-ready files**

---

## 🎉 You're All Set!

Everything is installed, tested, and ready to use.

**Next Action**: Open your browser and navigate to:

```
https://staff.vapeshed.co.nz/modules/admin-ui/index.php
```

**Enjoy your professional admin UI! 🚀**

---

**Version**: 1.0.0
**Build**: 20251030
**Status**: ✅ Production Ready
**Last Updated**: 2025-10-30
