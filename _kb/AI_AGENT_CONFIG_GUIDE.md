# 🤖 AI Agent Configuration Guide

## ✅ DONE! AI Agent Settings Added to Theme Builder PRO

Your Theme Builder PRO now has a complete **AI Agent Configuration** section!

---

## 📍 Location

**Access it here:**
`https://staff.vapeshed.co.nz/modules/admin-ui/theme-builder-pro.php`

**Scroll down to:** The AI Agent Configuration section (below Density controls)

---

## 🎛️ Configuration Options

### 1. **Enable AI Agent** (Toggle)
- ✅ **ON** - Activates AI assistance
- ❌ **OFF** - Disables AI features

### 2. **API URL / Host** ⭐ IMPORTANT
**What to enter:** Your AI service endpoint URL

**Examples:**
```
OpenAI:
https://api.openai.com/v1/chat/completions

Azure OpenAI:
https://your-resource.openai.azure.com/openai/deployments/gpt-4/chat/completions?api-version=2024-02-15-preview

Anthropic Claude:
https://api.anthropic.com/v1/messages

Custom/Local:
https://your-domain.com/api/ai/chat
http://localhost:8000/api/chat
```

### 3. **API Key** ⭐ IMPORTANT
**What to enter:** Your authentication key

**Examples:**
```
OpenAI: sk-proj-xxxxxxxxxxxxxxxxxxxxx
Azure: xxxxxxxxxxxxxxxxxxxxxxxxxxxxx
Anthropic: sk-ant-xxxxxxxxxxxxxxxxxxxxx
Custom: Your custom API key
```

### 4. **Model**
Select the AI model to use:
- **GPT-4** (recommended for quality)
- **GPT-4 Turbo** (faster, cheaper)
- **GPT-3.5 Turbo** (fastest, cheapest)
- **Claude 3 Opus** (Anthropic's best)
- **Claude 3 Sonnet** (balanced)
- **Custom Model** (for your own endpoints)

### 5. **Timeout (seconds)**
- Default: `30` seconds
- Range: 5 - 120 seconds
- How long to wait for AI response before giving up

### 6. **Temperature** (Creativity slider)
- **0.0** - Very focused, deterministic responses
- **0.7** - Balanced (default)
- **1.0** - Very creative, random responses

### 7. **Max Tokens**
- Default: `2000` tokens
- Range: 100 - 8000 tokens
- Maximum length of AI response

---

## 💾 What Happens When You Click "Save AI Config"

1. ✅ Saves to `config/ai-agent-config.json` (permanent file)
2. ✅ Persists across sessions (never lost)
3. ✅ Can be loaded anytime with "Load" button
4. ✅ Auto-loads on page refresh
5. ✅ Used by all AI features in the system

**Save Location:**
```
/modules/admin-ui/config/ai-agent-config.json
```

**Example saved config:**
```json
{
  "enabled": true,
  "api_url": "https://api.openai.com/v1/chat/completions",
  "api_key": "sk-proj-xxxxxxxxxxxxx",
  "model": "gpt-4",
  "timeout": 30,
  "max_tokens": 2000,
  "temperature": 0.7,
  "updated_at": "2025-10-31 16:30:00"
}
```

---

## 🧪 Test Connection Button

**What it does:**
1. Takes your API URL and API Key
2. Sends a test request to verify connection
3. Shows success or error message
4. Displays HTTP status code

**Possible results:**
- ✅ **Success (200-299)** - API is reachable and working
- ❌ **Failed (400-499)** - Authentication error or bad URL
- ❌ **Failed (500-599)** - API server error
- ❌ **Network error** - Can't reach API (CORS, firewall, etc.)

---

## 🔧 Setup Guide

### Step 1: Get Your API Credentials

**For OpenAI:**
1. Go to https://platform.openai.com/api-keys
2. Create new API key
3. Copy the key (starts with `sk-`)

**For Azure OpenAI:**
1. Go to Azure Portal → Your OpenAI resource
2. Copy endpoint URL and key

**For Anthropic Claude:**
1. Go to https://console.anthropic.com/
2. Get API key from settings

**For Custom API:**
1. Deploy your AI endpoint
2. Note the URL and authentication method

### Step 2: Configure in Theme Builder PRO

1. Open Theme Builder PRO
2. Scroll to **AI Agent Configuration** section
3. Toggle **Enable AI Agent** to ON
4. Enter your **API URL**
5. Enter your **API Key**
6. Select your **Model**
7. Adjust **Timeout**, **Temperature**, **Max Tokens** if needed

### Step 3: Test Connection

1. Click **"Test Connection"** button
2. Wait for response (5-10 seconds)
3. Check status message:
   - ✅ Success → You're good to go!
   - ❌ Failed → Check URL and key

### Step 4: Save Configuration

1. Click **"Save AI Config"** button
2. See success notification
3. Configuration is now saved permanently!

### Step 5: Verify Persistence

1. Refresh the page
2. AI config should auto-load
3. Your settings are still there ✅

---

## 🎯 Use Cases

### 1. **AI-Powered Theme Suggestions**
- AI analyzes your brand colors
- Suggests complementary color schemes
- Recommends fonts that match your style

### 2. **Code Generation**
- Generate CSS for custom components
- Create JavaScript for interactions
- Build complete theme templates

### 3. **Accessibility Analysis**
- Check color contrast ratios
- Suggest accessible alternatives
- Validate WCAG compliance

### 4. **Design Feedback**
- Get AI opinions on your theme
- Ask for improvement suggestions
- Compare design trends

---

## 🔒 Security Notes

### API Key Storage
- ✅ **Stored in:** `config/ai-agent-config.json` (server-side only)
- ✅ **NOT in browser** - Keys never exposed to frontend
- ✅ **File permissions** - Readable only by web server
- ⚠️ **Backup safely** - Don't commit to git with keys!

### Best Practices
1. **Use environment variables** for production
2. **Rotate keys** every 90 days
3. **Set spending limits** on AI provider dashboard
4. **Monitor usage** to catch unauthorized access
5. **Use separate keys** for dev/staging/prod

### Add to .gitignore
```
# Don't commit AI config with keys!
modules/admin-ui/config/ai-agent-config.json
```

---

## 🚀 Quick Start Examples

### Example 1: OpenAI GPT-4
```
Enable: ✅ ON
API URL: https://api.openai.com/v1/chat/completions
API Key: sk-proj-abc123...
Model: GPT-4
Timeout: 30
Temperature: 0.7
Max Tokens: 2000
```

### Example 2: Local LLM (LM Studio)
```
Enable: ✅ ON
API URL: http://localhost:1234/v1/chat/completions
API Key: (leave blank or use 'local')
Model: Custom
Timeout: 60
Temperature: 0.8
Max Tokens: 4000
```

### Example 3: Azure OpenAI
```
Enable: ✅ ON
API URL: https://your-resource.openai.azure.com/openai/deployments/gpt-4/chat/completions?api-version=2024-02-15-preview
API Key: your-azure-key-here
Model: GPT-4
Timeout: 30
Temperature: 0.7
Max Tokens: 2000
```

---

## 📊 What Gets Sent to AI

When AI features are used, the system sends:

```json
{
  "model": "gpt-4",
  "messages": [
    {
      "role": "system",
      "content": "You are a theme design assistant..."
    },
    {
      "role": "user",
      "content": "Suggest colors for a tech startup theme"
    }
  ],
  "temperature": 0.7,
  "max_tokens": 2000
}
```

**Privacy:** Only theme-related data is sent, never sensitive user data!

---

## 🛠️ Troubleshooting

### "Connection Failed" Error
**Possible causes:**
1. Wrong API URL (check for typos)
2. Invalid API key (expired or wrong key)
3. CORS issue (if testing from browser)
4. Firewall blocking requests
5. API service down

**Solutions:**
- Double-check URL and key
- Test with curl: `curl -H "Authorization: Bearer YOUR_KEY" YOUR_URL`
- Check API provider status page
- Verify your IP isn't blocked

### "Network Error"
**Possible causes:**
1. No internet connection
2. API endpoint unreachable
3. DNS resolution failed
4. Timeout too short

**Solutions:**
- Check internet connection
- Ping the API domain
- Increase timeout to 60 seconds
- Try with different network

### Config Not Saving
**Possible causes:**
1. No write permissions on config/ directory
2. Disk full
3. PHP errors

**Solutions:**
- Check file permissions: `ls -la config/`
- Check disk space: `df -h`
- Check error logs: `tail -f logs/error.log`

### Config Not Loading
**Possible causes:**
1. JSON file corrupted
2. Wrong file path
3. PHP errors

**Solutions:**
- Validate JSON: `cat config/ai-agent-config.json | jq`
- Check file exists: `ls -la config/ai-agent-config.json`
- Try clicking "Load" button manually

---

## 📈 Future Enhancements

Coming soon:
1. **AI Theme Generator** - Describe theme in words, AI generates it
2. **Smart Color Picker** - AI suggests accessible color combinations
3. **Font Pairing AI** - AI recommends font combinations
4. **Code Assistant** - AI writes CSS/JS for you
5. **A/B Testing** - AI suggests theme variations to test
6. **Brand Analysis** - Upload logo, AI extracts colors

---

## 🎓 Summary

**Q: Where do I configure AI?**
**A:** Theme Builder PRO → Scroll to "AI Agent Configuration" section

**Q: What do I need to provide?**
**A:** API URL (endpoint) and API Key (authentication)

**Q: Where is it saved?**
**A:** `config/ai-agent-config.json` (permanent file)

**Q: Does it persist?**
**A:** ✅ Yes! Auto-loads on every page refresh

**Q: Can I test before saving?**
**A:** ✅ Yes! Use "Test Connection" button

**Q: Is my API key secure?**
**A:** ✅ Yes! Stored server-side only, never exposed to browser

**Q: Can I use local LLMs?**
**A:** ✅ Yes! Point to localhost URL (e.g., LM Studio, Ollama)

---

**Version:** 4.0.0
**Last Updated:** October 31, 2025
**Feature:** AI Agent Configuration
**Status:** ✅ COMPLETE & READY TO USE!
