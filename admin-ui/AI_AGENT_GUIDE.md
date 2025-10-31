# 🤖 AI Agent Theme Builder - Quick Start Guide

## Overview

The AI Agent Theme Builder allows you to **watch AI agents edit code in real-time** across HTML, CSS, and JavaScript files. You can give natural language commands and watch as the AI makes changes instantly.

## Features

### ✨ Real-Time AI Code Editing
- AI can edit any of the 3 tabs (HTML, CSS, JavaScript)
- Watch code changes happen live with highlighted edits
- 1:1 preview updates automatically

### 💬 Natural Language Commands
- "Add a button component"
- "Change the color to blue"
- "Make the text bigger"
- "Add a navigation bar"
- "Review my code"
- "Optimize the CSS"

### 🎯 Watch Mode
- Toggle watch mode to allow AI to edit automatically
- AI makes changes as you chat with it
- Visual indicators show when AI is editing

### 📋 Edit Queue
- See pending edits before they're applied
- Track what the AI is planning to change
- Review edit history

### 📊 Activity Log
- Real-time log of all AI actions
- Timestamps for every change
- Success/error indicators

## How to Use

### 1. Open the Theme Builder
Navigate to:
```
/modules/admin-ui/ai-theme-builder.php
```

### 2. Open the AI Agent Panel
- Click the **"AI Agent"** button in the top-right
- Or click the floating robot icon on the right side

### 3. Enable Watch Mode (Optional)
- Click the eye icon in the AI panel header
- When enabled, AI can edit code automatically
- When disabled, you control when edits are applied

### 4. Send Commands
Type natural language commands like:
- "Add a card component with a header and body"
- "Change the primary color to #ff0000"
- "Add a button that's blue with white text"
- "Create a navigation bar at the top"
- "Make the heading bigger"

### 5. Watch the Magic
- AI will respond in the chat
- Code edits will appear in the edit queue
- In watch mode, edits apply automatically with highlights
- Preview updates in real-time

## AI Commands Reference

### Adding Components

**Buttons:**
```
"Add a button component"
"Create a blue button"
"Add a primary action button"
```

**Cards:**
```
"Add a card component"
"Create a card with header and body"
```

**Navigation:**
```
"Add a navbar"
"Create a navigation bar at the top"
"Add a menu with home, about, contact"
```

**Forms:**
```
"Add a form with email and password"
"Create a contact form"
```

### Modifying Styles

**Colors:**
```
"Change the color to blue"
"Make the background darker"
"Set the primary color to #8b5cf6"
```

**Sizes:**
```
"Make the text bigger"
"Increase the font size"
"Make the buttons larger"
```

**Layout:**
```
"Center the content"
"Add more padding"
"Make it responsive"
```

### Code Quality

**Review:**
```
"Review my code"
"Check for issues"
"Analyze the CSS"
```

**Optimize:**
```
"Optimize the code"
"Make it faster"
"Improve performance"
```

**Format:**
```
"Format the code"
"Clean up the HTML"
"Beautify the CSS"
```

### Fixing Issues

```
"Fix the layout"
"Correct the syntax errors"
"Fix the broken styles"
```

## Watch Mode Explained

### When Watch Mode is OFF (Default)
- AI suggests edits but doesn't apply them
- You review edits in the queue
- You manually approve each edit
- **Safer for learning and review**

### When Watch Mode is ON
- AI applies edits automatically
- Changes happen in real-time
- Visual highlights show what changed
- **Faster for experienced users**

## Keyboard Shortcuts

| Shortcut | Action |
|----------|--------|
| `Ctrl+S` | Save theme |
| `Ctrl+Z` | Undo |
| `Ctrl+Y` | Redo |
| `Ctrl+/` | Toggle comment |
| `Shift+Alt+F` | Format code |
| `Ctrl+Enter` | Send AI message |
| `Esc` | Close AI panel |

## UI Components

### 1. Top Bar
- **Brand Logo** - Shows AI Agent Theme Builder
- **Theme Button** - Opens theme switcher
- **AI Agent Button** - Opens AI panel

### 2. Editor Section (Left Side)
- **Tab Bar** - Switch between HTML, CSS, JavaScript
- **Monaco Editor** - Professional code editor with syntax highlighting
- **Live Updates** - Changes reflect immediately in preview

### 3. Preview Section (Right Side)
- **Live Preview** - 1:1 rendering of your code
- **Device Controls** - Desktop, tablet, mobile views
- **Auto-Refresh** - Updates as you type or AI edits

### 4. AI Agent Panel (Right Side)
- **Status Indicator** - Shows if AI is active/idle/editing
- **Chat Interface** - Natural language conversation
- **Quick Actions** - Review Code, Optimize buttons
- **Edit Queue** - Shows pending edits
- **Activity Log** - Real-time action history

### 5. Instructions Banner (Bottom)
- **Example Commands** - Quick reference for AI commands
- **Dismissable** - Close when you don't need it

## Technical Details

### Architecture

**Frontend:**
- Monaco Editor (VS Code's editor)
- Real-time preview with iframe
- WebSocket-style updates (simulated)

**Backend:**
- PHP API endpoint: `/modules/admin-ui/api/ai-agent-handler.php`
- Natural language processing
- Code generation and modification
- Intent detection and execution

**AI Integration:**
- Parses natural language commands
- Detects target (HTML, CSS, or JavaScript)
- Generates appropriate code edits
- Applies with visual highlighting

### API Endpoints

**Main Handler:**
```
POST /modules/admin-ui/api/ai-agent-handler.php
```

**Actions:**
- `process_command` - Handle natural language input
- `review_code` - Analyze code quality
- `generate_code` - Generate new code
- `optimize_code` - Optimize existing code

**Request Format:**
```json
{
  "action": "process_command",
  "message": "Add a button component",
  "context": {
    "html": "...",
    "css": "...",
    "javascript": "..."
  },
  "watchMode": true
}
```

**Response Format:**
```json
{
  "success": true,
  "response": "I'll add a button component for you.",
  "edits": [
    {
      "target": "html",
      "type": "insert",
      "line": 10,
      "content": "<button>Click Me</button>",
      "description": "Add button component",
      "delay": 0
    }
  ],
  "suggestions": [
    "Consider adding hover effects",
    "You might want to add responsive styles"
  ]
}
```

### Edit Types

**Insert:**
```javascript
{
  "type": "insert",
  "line": 10,
  "content": "<div>New content</div>"
}
```

**Replace:**
```javascript
{
  "type": "replace",
  "line": 5,
  "content": "Updated content"
}
```

**Full:**
```javascript
{
  "type": "full",
  "content": "Entire file content"
}
```

## Integration with Existing Tools

### Theme Switcher
- Click "Themes" button to switch between saved themes
- AI agent works with any active theme
- Theme variables automatically applied

### Component Library
- AI can reference existing components
- Consistent with theme system
- Follows established patterns

### Version Control
- All AI edits are tracked
- Undo/redo support
- History timeline available

## Best Practices

### 1. Start with Watch Mode OFF
- Learn how AI interprets commands
- Review edits before applying
- Build confidence in AI suggestions

### 2. Be Specific in Commands
❌ "Make it better"
✅ "Add a card component with a blue header"

❌ "Fix it"
✅ "Fix the button alignment and add padding"

### 3. Use Quick Actions
- "Review Code" for quality checks
- "Optimize" for performance improvements
- Faster than typing full commands

### 4. Check Activity Log
- See what AI has done
- Track changes over time
- Debug issues

### 5. Save Frequently
- Use Ctrl+S to save themes
- Create backups of good versions
- Use version control

## Troubleshooting

### AI Not Responding
1. Check if AI Agent panel is open
2. Verify watch mode status
3. Check browser console for errors
4. Refresh page and try again

### Edits Not Applying
1. Enable watch mode
2. Check edit queue for pending edits
3. Review activity log for errors
4. Verify editor has focus

### Preview Not Updating
1. Check for JavaScript errors in preview
2. Verify CSS syntax is valid
3. Refresh preview manually
4. Check browser console

### Connection Issues
1. Check API endpoint is accessible
2. Verify `/modules/admin-ui/api/ai-agent-handler.php` exists
3. Check server logs for PHP errors
4. Ensure proper permissions

## Advanced Features (Coming Soon)

- [ ] GPT-4 Integration for smarter responses
- [ ] Voice commands
- [ ] Multi-file editing
- [ ] Collaborative editing with multiple AI agents
- [ ] Custom AI training on your codebase
- [ ] Export AI conversation as documentation
- [ ] AI-generated test cases
- [ ] Performance profiling integration

## Examples

### Example 1: Building a Landing Page
```
You: "Add a navigation bar with logo and menu"
AI: Adds navbar HTML + CSS

You: "Add a hero section with title and CTA button"
AI: Adds hero section

You: "Make the button green with hover effect"
AI: Updates button styles

You: "Add three feature cards below"
AI: Adds card grid with three cards

You: "Review the code"
AI: Provides quality analysis
```

### Example 2: Fixing Issues
```
You: "The text is too small on mobile"
AI: Adds responsive typography

You: "The colors don't have enough contrast"
AI: Updates color scheme

You: "The layout breaks on tablet"
AI: Fixes responsive breakpoints
```

### Example 3: Optimization
```
You: "Optimize the CSS"
AI: Combines selectors, removes duplicates

You: "Make the page load faster"
AI: Suggests lazy loading, code splitting

You: "Add accessibility improvements"
AI: Adds ARIA labels, semantic HTML
```

## Support

For issues or questions:
1. Check this guide first
2. Review activity log for clues
3. Check browser and server logs
4. Test with simple commands first

## Version History

**v1.0.0** (October 30, 2025)
- Initial release
- Natural language command processing
- Real-time code editing
- Watch mode
- Multi-tab support (HTML, CSS, JS)
- Activity logging
- Edit queue management
- Theme integration

---

**Ready to build something amazing with AI? Open the theme builder and start chatting!** 🚀
