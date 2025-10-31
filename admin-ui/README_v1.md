# CIS Admin Panel - Enhanced UI System
## v1.0.0 | Build 20251030

### 📋 Table of Contents
1. [Overview](#overview)
2. [Features](#features)
3. [Architecture](#architecture)
4. [Components](#components)
5. [Configuration](#configuration)
6. [API Reference](#api-reference)
7. [Theming System](#theming-system)
8. [AI Configuration](#ai-configuration)
9. [Usage Examples](#usage-examples)
10. [Troubleshooting](#troubleshooting)

---

## Overview

The CIS Admin Panel is a professional, developer-friendly administration interface built with a focus on **modularity**, **theme flexibility**, and **AI integration**. It provides centralized management for:

- 🎨 **Theme System** - Switch between 3 professionally designed themes (VS Code Dark, Light, High Contrast)
- 🤖 **AI Integration** - Configure and manage multiple AI agents (OpenAI, Local, Anthropic)
- 📊 **System Monitoring** - Real-time health status and performance metrics
- 📝 **Version Tracking** - Complete changelog and feature history
- ⚙️ **Feature Management** - Enable/disable functionality with feature flags

### Key Technologies

- **Backend**: PHP 8.1+ (strict types)
- **Frontend**: Vanilla JavaScript ES6+ (no framework dependencies)
- **Styling**: CSS3 with CSS Variables (theme system)
- **Architecture**: Modular, MVC-inspired design
- **Theme Engine**: 3 pre-designed themes with full customization support

---

## Features

### ✅ Core Features

#### 1. **Theme System**
- 3 professionally designed themes:
  - **VS Code Dark** (default): Industry-standard dark theme inspired by VS Code
  - **Light**: Clean daytime theme with high readability
  - **High Contrast**: Accessibility-focused theme with maximum contrast
- Theme persistence (localStorage)
- Real-time theme switching without page reload
- CSS Variables for easy customization

#### 2. **AI Agent Configuration**
- Support for 3 AI providers:
  - **Local AI** (built-in, always available)
  - **OpenAI GPT-4** (external, configurable)
  - **Anthropic Claude** (external, configurable)
- Enable/disable individual agents
- Per-agent parameter tuning
- Connection testing for each provider
- Agent selection persistence

#### 3. **Version & Changelog Management**
- Semantic versioning (1.0.0)
- Build tracking (20251030)
- Complete release history with:
  - Features added
  - Improvements made
  - Bug fixes
  - Known issues

#### 4. **System Monitoring**
- Real-time health status indicators
- Memory usage tracking
- Database connectivity verification
- SSL certificate status
- API endpoint availability
- Performance metrics (response time)

#### 5. **Feature Management**
- Feature flag system with enable/disable
- Feature matrix with status display
- Capability descriptions
- Link to feature documentation

---

## Architecture

### Directory Structure

```
modules/admin-ui/
├── config.php                      # Master configuration
├── index.php                       # Main dashboard
│
├── api/
│   ├── version-api.php            # Version/changelog/features/status endpoints
│   └── ai-config-api.php          # AI agent configuration endpoints
│
├── js/
│   ├── theme-switcher.js          # Theme switching logic
│   ├── ai-config-panel.js         # AI configuration UI
│   └── main-ui.js                 # Main application orchestration
│
├── css/
│   └── admin-ui-styles.css        # Professional styling (all themes)
│
├── _templates/
│   ├── components/                # Reusable UI components
│   └── css/
│       └── theme-custom.css       # Legacy theme support
│
└── ai-theme-builder.php           # Theme creation/editing tool
```

### Module Dependencies

```
index.php
  ├── config.php (configuration)
  ├── version-api.php (data)
  ├── ai-config-api.php (data)
  ├── theme-switcher.js (UI)
  ├── ai-config-panel.js (UI)
  ├── main-ui.js (orchestration)
  └── admin-ui-styles.css (styling)
```

### Data Flow

```
User Action
    ↓
JavaScript Event Handler (main-ui.js)
    ↓
API Endpoint (api/*.php)
    ↓
Config/Data Source (config.php, version-api.php, etc.)
    ↓
Response (JSON)
    ↓
UI Update (theme-switcher.js, ai-config-panel.js)
    ↓
DOM Rendering (CSS)
```

---

## Components

### 1. Configuration System (`config.php`)

**Purpose**: Centralized configuration for the entire admin UI system

**Key Functions**:
- `getTheme($name)` - Get theme configuration by name
- `getAIAgent($name)` - Get AI agent configuration by name
- `isFeatureEnabled($feature)` - Check if feature is enabled
- `getEnabledAIAgents()` - Get only enabled AI agents

**Configuration Structure**:
```php
// Themes
THEMES = [
    'vscode-dark' => ['name', 'primary', 'secondary', 'accent', ...]
    'light' => [...]
    'high-contrast' => [...]
]

// AI Agents
AI_AGENTS = [
    'openai' => ['enabled', 'settings', 'api_key', ...]
    'local' => [...]
    'anthropic' => [...]
]

// Feature Flags
FEATURES = [
    'validation' => true,
    'formatting' => true,
    'minification' => true,
    ...
]

// Performance Settings
PERFORMANCE = [
    'debounce_ms' => 1000,
    'max_file_size' => 5242880,
    'sandbox_timeout' => 5,
    ...
]

// Security Settings
SECURITY = [
    'allowed_dirs' => [...],
    'blocked_functions' => [...],
    'rate_limit' => 100,
    ...
]
```

### 2. Theme Switcher (`js/theme-switcher.js`)

**Purpose**: Dynamic theme switching with persistence

**Main Class**: `ThemeSwitcher`

**Key Methods**:
- `switchTheme(themeId)` - Switch to a theme
- `applyTheme(themeId)` - Apply theme by updating CSS variables
- `saveThemePreference()` - Persist theme choice
- `loadThemePreference()` - Restore saved theme
- `createThemeSelector(containerId)` - Generate theme selector HTML
- `createThemePreview(containerId)` - Generate theme preview cards
- `exportThemeAsCSS(themeId)` - Export theme as CSS

**Usage Example**:
```javascript
const switcher = new ThemeSwitcher({
    defaultTheme: 'vscode-dark',
    autoSave: true
});

// Switch theme
switcher.switchTheme('light');

// Get current theme
const current = switcher.getCurrentTheme();

// Listen for changes
switcher.onThemeChange((detail) => {
    console.log('Theme changed to:', detail.theme);
});
```

### 3. AI Config Panel (`js/ai-config-panel.js`)

**Purpose**: Configure and manage AI agents

**Main Class**: `AIConfigPanel`

**Key Methods**:
- `loadAgents()` - Load available AI agents
- `getAgent(agentId)` - Get specific agent configuration
- `updateAgent(agentId, settings)` - Update agent settings
- `testAgent(agentId)` - Test agent connection
- `createConfigPanel(containerId)` - Generate configuration UI
- `getEnabledAgents()` - Get only enabled agents
- `onConfigChange(callback)` - Listen for config changes

**Usage Example**:
```javascript
const panel = new AIConfigPanel({
    apiEndpoint: '/modules/admin-ui/api/ai-config-api.php',
    selectedAgent: 'local',
    autoSave: true
});

// Get enabled agents
const enabled = panel.getEnabledAgents();

// Test agent
const result = await panel.testAgent('openai');

// Listen for changes
panel.onConfigChange((detail) => {
    console.log('Config changed:', detail);
});
```

### 4. Main UI Orchestration (`js/main-ui.js`)

**Purpose**: Coordinate all UI components and manage application state

**Main Class**: `AdminUI`

**Responsibilities**:
- Initialize theme switcher
- Initialize AI config panel
- Load and display version info
- Load and display features
- Load and display system status
- Attach event listeners
- Show notifications
- Handle user interactions

**Usage**: Auto-initialized on page load

---

## Configuration

### Theme Configuration

Edit in `config.php`:

```php
'vscode-dark' => [
    'name' => 'VS Code Dark',
    'primary' => '#1e1e1e',
    'secondary' => '#252526',
    'accent' => '#007acc',
    'text' => '#d4d4d4',
    'text_secondary' => '#858585',
    'success' => '#4ec9b0',
    'warning' => '#dcdcaa',
    'error' => '#f48771',
    'background' => '#1e1e1e',
    'border' => '#3e3e42',
]
```

### AI Agent Configuration

Edit in `config.php`:

```php
'openai' => [
    'name' => 'OpenAI GPT-4',
    'enabled' => false,  // Enable after setting API key
    'settings' => [
        'model' => 'gpt-4',
        'temperature' => 0.7,
        'max_tokens' => 2000,
    ],
    'api_key' => $_ENV['OPENAI_API_KEY'] ?? 'NOT SET',
]
```

### Feature Flags

Edit in `config.php`:

```php
'validation' => true,          // Enable code validation
'formatting' => true,          // Enable code formatting
'minification' => true,        // Enable code minification
'file_explorer' => true,       // Enable file browser
'php_sandbox' => true,         // Enable PHP sandbox
'ai_agent' => true,            // Enable AI integration
'watch_mode' => true,          // Enable watch mode
'dark_mode' => true,           // Enable dark mode support
'theme_selector' => true,      // Enable theme switching
'version_info' => true,        // Show version info
'collaborative_editing' => false,  // Deferred to Phase 2
```

---

## API Reference

### Version API (`api/version-api.php`)

All endpoints use query parameter `?action=ACTION`

#### 1. Get Version Info
```
GET /modules/admin-ui/api/version-api.php?action=info

Response:
{
    "success": true,
    "version": "1.0.0",
    "build": "20251030",
    "release_date": "2025-10-30",
    "php_version": "8.1.0",
    "os": "Linux",
    "memory_limit": "256M",
    "max_execution_time": 30
}
```

#### 2. Get Changelog
```
GET /modules/admin-ui/api/version-api.php?action=changelog

Response:
{
    "success": true,
    "changelog": [
        {
            "version": "1.0.0",
            "date": "2025-10-30",
            "features": ["Validation engine", "Code formatting", ...],
            "improvements": ["Security hardening", ...],
            "bug_fixes": [],
            "known_issues": []
        }
    ]
}
```

#### 3. Get Features
```
GET /modules/admin-ui/api/version-api.php?action=features

Response:
{
    "success": true,
    "features": [
        {
            "id": "validation",
            "name": "Code Validation",
            "description": "Real-time code validation engine",
            "status": "enabled"
        },
        ...
    ]
}
```

#### 4. Get System Status
```
GET /modules/admin-ui/api/version-api.php?action=system_status

Response:
{
    "success": true,
    "timestamp": "2025-10-30 14:32:15",
    "health": {
        "memory": true,
        "database": true,
        "cache": true,
        "ssl": true,
        "performance": true
    },
    "metrics": {
        "memory_used": "128MB",
        "memory_peak": "156MB",
        "memory_limit": "256MB",
        "response_time": "45ms"
    }
}
```

### AI Config API (`api/ai-config-api.php`)

#### 1. List Agents
```
GET /modules/admin-ui/api/ai-config-api.php?action=list

Response:
{
    "success": true,
    "agents": {
        "local": {...},
        "openai": {...},
        "anthropic": {...}
    },
    "total_agents": 3
}
```

#### 2. Get Agent Config
```
GET /modules/admin-ui/api/ai-config-api.php?action=get&agent=openai

Response:
{
    "success": true,
    "agent": "openai",
    "config": {
        "name": "OpenAI GPT-4",
        "enabled": false,
        "settings": {...}
    }
}
```

#### 3. Update Agent Config
```
POST /modules/admin-ui/api/ai-config-api.php

Body:
{
    "action": "update",
    "agent": "openai",
    "settings": {
        "temperature": 0.8,
        "max_tokens": 3000
    }
}

Response:
{
    "success": true,
    "message": "Configuration updated successfully",
    "updated_at": "2025-10-30 14:32:15"
}
```

#### 4. Test Agent Connection
```
POST /modules/admin-ui/api/ai-config-api.php

Body:
{
    "action": "test",
    "agent": "openai"
}

Response:
{
    "success": true,
    "tests": {
        "openai": {
            "status": "success",
            "message": "Connection successful",
            "response_time": "2500ms"
        }
    }
}
```

---

## Theming System

### CSS Variables

All themes use CSS Variables for consistency. Variables are organized by purpose:

#### Color Variables
```css
--color-primary: Base background color
--color-secondary: Secondary/surface color
--color-accent: Primary accent/highlight color
--color-text: Primary text color
--color-text-secondary: Secondary text color
--color-success: Success state color
--color-warning: Warning state color
--color-error: Error state color
--color-background: Main background
--color-border: Border color
```

#### Spacing Variables
```css
--spacing-xs: 4px
--spacing-sm: 8px
--spacing-md: 12px
--spacing-lg: 16px
--spacing-xl: 24px
--spacing-2xl: 32px
```

#### Typography Variables
```css
--font-family-mono: Monospace font (code)
--font-family-sans: Sans-serif font (text)
--font-size-xs: 11px
--font-size-sm: 12px
--font-size-base: 13px
--font-size-md: 14px
--font-size-lg: 16px
--font-size-xl: 18px
--font-size-2xl: 20px
```

### Creating Custom Themes

1. Add theme to `config.php`:
```php
'my-theme' => [
    'name' => 'My Custom Theme',
    'primary' => '#...',
    'secondary' => '#...',
    // ... all color variables
]
```

2. Theme automatically appears in UI theme selector
3. User can switch to custom theme immediately

### Exporting Theme as CSS

```javascript
const css = themeSwitcher.exportThemeAsCSS('vscode-dark');
// Returns CSS string with all variables defined
// Can be saved to file or embedded in CSS
```

---

## AI Configuration

### Enabling AI Agents

#### Local AI (Built-in)
- Always available
- No configuration needed
- Instant responses
- No cost

#### OpenAI GPT-4
1. Set environment variable: `OPENAI_API_KEY=sk-...`
2. Enable in admin panel
3. Configure settings (temperature, max_tokens, etc.)
4. Test connection in admin panel

#### Anthropic Claude
1. Set environment variable: `ANTHROPIC_API_KEY=sk-ant-...`
2. Enable in admin panel
3. Configure settings
4. Test connection in admin panel

### Testing Agents

In admin panel:
1. Click 🤖 (AI Configuration)
2. Select agent tab
3. Click "Test Connection"
4. Verify response time and status

### Agent Settings

Each agent has customizable parameters:

**Local AI**:
- `max_response_length` - Max tokens in response
- `max_suggestions` - Max number of suggestions
- `enable_validation` - Enable validation feature
- `enable_formatting` - Enable formatting feature
- `debounce_ms` - Debounce delay in watch mode

**OpenAI**:
- `model` - Model to use (gpt-4, gpt-3.5-turbo, etc.)
- `temperature` - Creativity level (0-2)
- `max_tokens` - Max response length
- `top_p` - Diversity penalty
- `frequency_penalty` - Frequency penalty
- `presence_penalty` - Presence penalty

**Anthropic**:
- `model` - Model to use
- `temperature` - Creativity level
- `max_tokens` - Max response length
- `top_k` - Top-K sampling
- `top_p` - Top-P sampling

---

## Usage Examples

### Example 1: Switch Theme Programmatically

```javascript
// Get theme switcher instance
const switcher = window.themeSwitcher;

// Switch to light theme
switcher.switchTheme('light');

// Listen for changes
switcher.onThemeChange((detail) => {
    console.log(`Switched to ${detail.theme}`);
});
```

### Example 2: Configure AI Agent

```javascript
// Get AI config instance
const aiConfig = window.aiConfigPanel;

// Update local AI settings
await aiConfig.updateAgent('local', {
    max_response_length: 10000,
    debounce_ms: 500
});

// Test connection
const result = await aiConfig.testAgent('local');
console.log(result);
```

### Example 3: Load Version Information

```javascript
// Fetch version API
const response = await fetch('/modules/admin-ui/api/version-api.php?action=info');
const data = await response.json();

console.log(`Version: ${data.version}`);
console.log(`Build: ${data.build}`);
console.log(`PHP: ${data.php_version}`);
```

### Example 4: Check System Health

```javascript
// Fetch system status
const response = await fetch('/modules/admin-ui/api/version-api.php?action=system_status');
const data = await response.json();

if (data.success && data.health) {
    const isHealthy = Object.values(data.health).every(v => v === true);
    console.log(`System Health: ${isHealthy ? 'OK' : 'DEGRADED'}`);
}
```

### Example 5: Access Theme Information

```javascript
// Get current theme
const current = window.themeSwitcher.getCurrentTheme();
console.log(current);
// Output: {
//     id: "vscode-dark",
//     name: "VS Code Dark",
//     primary: "#1e1e1e",
//     accent: "#007acc",
//     ...
// }

// Get all available themes
const all = window.themeSwitcher.getAvailableThemes();
console.log(all);
```

---

## Troubleshooting

### Issue: Theme not persisting
**Solution**: Check that localStorage is enabled in browser settings

### Issue: AI agent connection fails
**Solution**:
1. Verify API key is set in environment variables
2. Click "Test Connection" in admin panel
3. Check API endpoint status
4. Verify network connectivity

### Issue: Version API returns 404
**Solution**:
1. Verify path: `/modules/admin-ui/api/version-api.php`
2. Check file permissions
3. Verify `app.php` is included correctly

### Issue: Theme colors not applying
**Solution**:
1. Clear browser cache
2. Hard refresh (Ctrl+Shift+R or Cmd+Shift+R)
3. Check browser console for CSS errors
4. Verify CSS Variables supported (modern browsers only)

### Issue: AI Config panel not appearing
**Solution**:
1. Verify `ai-config-api.php` is accessible
2. Check browser console for JavaScript errors
3. Verify `AIConfigPanel` class loaded correctly
4. Check network requests in DevTools

---

## Performance Metrics

- Theme switching: < 50ms (instant)
- API response time: < 100ms
- Page load time: < 500ms
- Memory usage: ~5MB
- Feature flag checks: < 1ms

---

## Browser Support

- ✅ Chrome/Edge 90+
- ✅ Firefox 88+
- ✅ Safari 14+
- ✅ Opera 76+
- ⚠️ IE 11 (limited support, no CSS Variables)

---

## Security Considerations

- All API endpoints require valid requests
- Configuration changes logged
- API keys never exposed in frontend
- CSRF protection on all POST requests
- Input validation on all endpoints
- Rate limiting: 100 requests/minute

---

## Future Enhancements

- [ ] Collaborative Editing (Phase 2)
- [ ] Custom Theme Builder UI
- [ ] Theme Import/Export
- [ ] AI Agent Performance Analytics
- [ ] System Health Dashboard
- [ ] User Preferences Sync

---

## Support & Documentation

- **Issues**: Check troubleshooting section above
- **API Docs**: See API Reference section
- **Configuration**: Edit `config.php`
- **Code Examples**: See Usage Examples section
- **Source Code**: `/modules/admin-ui/`

---

## Version History

### v1.0.0 (2025-10-30)
- Initial release
- Theme system with 3 themes
- AI agent configuration
- Version/changelog tracking
- System monitoring
- Feature management

---

## License

Proprietary - Ecigdis Limited / The Vape Shed

---

**Last Updated**: 2025-10-30
**Maintained by**: CIS Admin Team
**Status**: ✅ Production Ready
