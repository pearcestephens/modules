# 🔐 Environment Variable Setup Guide

## SendGrid API Key Configuration

The payroll system uses **environment variables** to store sensitive credentials like the SendGrid API key. This keeps secrets out of the codebase and safe from accidental commits to GitHub.

## Setup Instructions

### Option 1: System Environment Variable (Recommended for Production)

1. **Get your SendGrid API Key:**
   - Log in to [SendGrid](https://app.sendgrid.com)
   - Go to Settings → API Keys
   - Create a new API key with "Mail Send" permissions
   - Copy the key (starts with `SG.`)

2. **Set the environment variable:**

   **On Linux/Mac (bash/zsh):**
   ```bash
   export SENDGRID_API_KEY='SG.your_actual_key_here'
   ```

   **To make it permanent, add to `~/.bashrc` or `~/.bash_profile`:**
   ```bash
   echo "export SENDGRID_API_KEY='SG.your_actual_key_here'" >> ~/.bashrc
   source ~/.bashrc
   ```

   **On Cloudways/Server:**
   ```bash
   # Add to Apache environment
   sudo nano /etc/apache2/envvars
   # Add this line:
   export SENDGRID_API_KEY='SG.your_actual_key_here'

   # Restart Apache
   sudo service apache2 restart
   ```

3. **Verify it's set:**
   ```bash
   echo $SENDGRID_API_KEY
   ```

### Option 2: .env File (Development)

1. **Copy the example file:**
   ```bash
   cp .env.example .env
   ```

2. **Edit .env and add your key:**
   ```bash
   nano .env
   # Replace 'your_sendgrid_api_key_here' with actual key
   ```

3. **Load the .env file** (add to your scripts):
   ```php
   <?php
   // At the top of your script
   if (file_exists(__DIR__ . '/.env')) {
       $lines = file(__DIR__ . '/.env', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
       foreach ($lines as $line) {
           if (strpos($line, '#') === 0) continue; // Skip comments
           list($key, $value) = explode('=', $line, 2);
           putenv(trim($key) . '=' . trim($value));
       }
   }
   ```

## Testing

Run the test script to verify your setup:

```bash
php tests/test_env_sendgrid.php
```

**Expected output:**
```
✅ Test 1: SENDGRID_API_KEY found
✅ Test 2: Config loaded successfully
✅ Test 3: Keys match perfectly
✅ ALL TESTS PASSED
```

## Security Best Practices

### ✅ DO:
- Use environment variables for all secrets
- Add `.env` to `.gitignore`
- Commit `.env.example` with placeholder values
- Rotate API keys regularly
- Use different keys for dev/staging/production

### ❌ DON'T:
- Never commit actual `.env` file
- Never hardcode API keys in code
- Never share keys in chat/email
- Never log API keys
- Never expose keys in error messages

## Troubleshooting

### "SENDGRID_API_KEY not found"
- Check if environment variable is set: `echo $SENDGRID_API_KEY`
- Make sure to `source ~/.bashrc` after editing
- Restart your web server after setting Apache envvars
- Check if .env file exists and has correct format

### "Config file not found"
- Make sure you're running from the correct directory
- Check file path in error message
- Verify `config/sendgrid.php` exists

### "Keys don't match"
- Environment variable may have extra quotes or spaces
- Use single quotes when setting: `export VAR='value'`
- Check for trailing whitespace in .env file

## Current Configuration

The SendGrid config file expects:
- **Environment Variable:** `SENDGRID_API_KEY`
- **Format:** Starts with `SG.`
- **Length:** Approximately 70 characters
- **Permissions:** Read access only

Config loads from: `config/sendgrid.php`

## GitHub Safe

✅ This configuration is **safe to commit to GitHub** because:
- No actual secrets in code
- Uses environment variables
- `.env` is gitignored
- Only `.env.example` is committed (with placeholders)

---

**Last Updated:** November 1, 2025
**Status:** Production-Ready 🚀
