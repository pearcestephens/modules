# Email Configuration Guide - Rackspace & SendGrid

Comprehensive guide for configuring email providers with the Staff Email Hub module.

## Table of Contents

1. [Overview](#overview)
2. [Rackspace Email Setup](#rackspace-email-setup)
3. [SendGrid Setup](#sendgrid-setup)
4. [IMAP Integration](#imap-integration)
5. [Sending Emails](#sending-emails)
6. [Testing](#testing)
7. [Troubleshooting](#troubleshooting)

---

## Overview

The Staff Email Hub supports two email providers:

| Feature | Rackspace | SendGrid |
|---------|-----------|----------|
| IMAP (Incoming) | ✅ Native | ❌ Not Available |
| SMTP (Outgoing) | ✅ Native | ✅ API |
| Cost | Included | Free (up to 100/day) |
| Setup Time | 10 minutes | 5 minutes |
| Reliability | High (Enterprise) | Very High (Cloud) |
| Bulk Email | Good | Excellent |
| Authentication | Username/Password | API Key |

### Recommended Configuration

**Best Combination:** Rackspace IMAP + SendGrid SMTP

This provides:
- Automatic email sync via IMAP
- Reliable outbound email via SendGrid API
- Cost optimization (use existing Rackspace, add SendGrid)

---

## Rackspace Email Setup

### 1. Access Rackspace Email

1. Log in to Rackspace Control Panel
2. Navigate to **Email Hosting** → **Your Domain**
3. Click on your mailbox
4. Go to **Connect Your Email Client**

### 2. Get IMAP Credentials

You'll find this information:

```
IMAP Settings:
- Incoming Mail Server: secure.emailsrvr.com
- Port: 993 (Recommended - SSL/TLS)
- Secure Connection: SSL/TLS
- Username: your-email@yourdomain.com
- Password: Your mailbox password

Alternative Port: 143 (TLS required)
```

### 3. Get SMTP Credentials

```
SMTP Settings:
- Outgoing Mail Server: secure.emailsrvr.com
- Port: 587 (Recommended - TLS)
- Secure Connection: TLS
- Username: your-email@yourdomain.com
- Password: Your mailbox password

Alternative Port: 465 (SSL)
```

### 4. Configure in Staff Email Hub

#### Via API

```bash
curl -X POST http://your-domain.com/admin/onboarding/configure-email \
  -H "Content-Type: application/json" \
  -d '{
    "provider": "rackspace",
    "rackspace_imap": {
      "host": "secure.emailsrvr.com",
      "port": 993,
      "username": "your-email@yourdomain.com",
      "password": "your_email_password"
    },
    "rackspace_smtp": {
      "host": "secure.emailsrvr.com",
      "port": 587,
      "username": "your-email@yourdomain.com",
      "password": "your_email_password"
    }
  }'
```

#### Via .env File

```env
RACKSPACE_IMAP_HOST=secure.emailsrvr.com
RACKSPACE_IMAP_PORT=993
RACKSPACE_IMAP_USERNAME=your-email@yourdomain.com
RACKSPACE_IMAP_PASSWORD=your_email_password

RACKSPACE_SMTP_HOST=secure.emailsrvr.com
RACKSPACE_SMTP_PORT=587
RACKSPACE_SMTP_USERNAME=your-email@yourdomain.com
RACKSPACE_SMTP_PASSWORD=your_email_password
```

### 5. Test Configuration

```bash
curl -X POST http://your-domain.com/admin/onboarding/test-email \
  -H "Content-Type: application/json" \
  -d '{
    "provider": "rackspace",
    "test_email": "your-email@yourdomain.com"
  }'
```

Expected response:
```json
{
  "success": true,
  "message": "Rackspace IMAP connection successful",
  "provider": "rackspace",
  "type": "imap"
}
```

### 6. Common Rackspace Issues

#### Port 993 Connection Refused
- Firewall blocking SSL connections
- Solution: Use port 143 with TLS, or open port 993
- Test: `telnet secure.emailsrvr.com 993`

#### Authentication Failed
- Wrong password or username
- Username must be full email address
- Solution: Reset password in Rackspace Control Panel

#### Can't Connect to Secure Server
- OpenSSL not enabled in PHP
- Solution: Verify OpenSSL extension: `php -m | grep openssl`

---

## SendGrid Setup

### 1. Create SendGrid Account

1. Go to https://sendgrid.com
2. Sign up (Free tier available)
3. Verify email and domain
4. Complete account setup

### 2. Create API Key

**Method 1: Web Interface**

1. Log in to SendGrid Dashboard
2. Go to **Settings** → **API Keys**
3. Click **Create API Key**
4. Select **Full Access** (or customize permissions)
5. Give it a name: `Staff Email Hub`
6. Click **Create & View**
7. Copy the key (you can only see it once!)

**Method 2: Restricted Key (Recommended)**

For security, create a key with limited permissions:

1. Go to **Settings** → **API Keys**
2. Click **Create API Key**
3. Select **Restricted Access**
4. Enable only:
   - ✅ Mail Send
5. Disable everything else
6. Click **Create & View**
7. Copy the key

### 3. Configure in Staff Email Hub

#### Via API

```bash
curl -X POST http://your-domain.com/admin/onboarding/configure-email \
  -H "Content-Type: application/json" \
  -d '{
    "provider": "sendgrid",
    "sendgrid": {
      "api_key": "SG.xxxxxxxxxxxxxxxxxxxxxxxxxxxxx_xxxx"
    }
  }'
```

#### Via .env File

```env
SENDGRID_API_KEY=SG.xxxxxxxxxxxxxxxxxxxxxxxxxxxxx_xxxx
SENDGRID_FROM_EMAIL=noreply@yourdomain.com
SENDGRID_FROM_NAME=The Vape Shed
```

### 4. Test Configuration

```bash
curl -X POST http://your-domain.com/admin/onboarding/test-email \
  -H "Content-Type: application/json" \
  -d '{
    "provider": "sendgrid",
    "test_email": "your-email@yourdomain.com"
  }'
```

Expected response:
```json
{
  "success": true,
  "message": "SendGrid test email sent successfully",
  "provider": "sendgrid"
}
```

### 5. Verify Email Domain (Optional but Recommended)

To improve deliverability:

1. Go to SendGrid Dashboard
2. **Settings** → **Sender Authentication**
3. **Authenticate Your Domain**
4. Add DNS records to your domain
5. Verify ownership

### 6. Common SendGrid Issues

#### Invalid API Key
- Key might have trailing/leading spaces
- Check if key was copied completely
- Solution: Regenerate new key

#### Authentication Failed (401)
- Key doesn't have Mail Send permission
- Key was revoked
- Solution: Create new key with correct permissions

#### Rate Limited (429)
- Sending too many emails too fast
- SendGrid Free tier: 100/day
- Solution: Use paid tier or space out emails

---

## IMAP Integration

### How IMAP Works in Staff Email Hub

```
Rackspace Email Server
        ↓ (Every 5 minutes via cron)
   IMAP Sync
        ↓
 Parse Email Headers
        ↓
 Match Customer
        ↓
 Store in Database
        ↓
 Staff Email Hub
```

### Set Up IMAP Sync

#### 1. Create Cron Job

```bash
# Edit cron
crontab -e

# Add this line (sync every 5 minutes)
*/5 * * * * php /path/to/modules/staff-email-hub/crons/imap-sync.php

# Or for hourly sync
0 * * * * php /path/to/modules/staff-email-hub/crons/imap-sync.php

# View cron jobs
crontab -l
```

#### 2. Create Cron Script

Save as `crons/imap-sync.php`:

```php
<?php
// Load application
require_once '/path/to/config/database.php';

use StaffEmailHub\Services\ImapService;

// Get database connection
$db = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME,
    DB_USER,
    DB_PASSWORD
);

// Create IMAP service
$imap = new ImapService(
    $db,
    $_ENV['RACKSPACE_IMAP_HOST'] ?? '',
    (int)($_ENV['RACKSPACE_IMAP_PORT'] ?? 993),
    $_ENV['RACKSPACE_IMAP_USERNAME'] ?? '',
    $_ENV['RACKSPACE_IMAP_PASSWORD'] ?? ''
);

// Sync emails (staff_id = 1, limit = 100 per sync)
$result = $imap->syncEmails('INBOX', 1, 100);

// Log result
echo date('Y-m-d H:i:s') . ' - ' . json_encode($result) . "\n";

// Close connection
$imap->disconnect();
?>
```

#### 3. Monitor IMAP Sync

```bash
# Check sync logs
tail -f /path/to/modules/staff-email-hub/storage/logs/imap-sync.log

# Via MySQL
mysql -u email_hub -p staff_email_hub -e "
  SELECT * FROM imap_sync_log
  ORDER BY created_at DESC
  LIMIT 10;
"
```

### IMAP Sync Configuration

```php
// In ImapService constructor

// Settings
$imapService = new ImapService(
    $db,
    'secure.emailsrvr.com',    // Host
    993,                        // Port (993=SSL, 143=TLS)
    'email@domain.com',         // Username
    'password',                 // Password
    true                        // Use SSL (true/false)
);

// Sync from INBOX
$result = $imap->syncEmails('INBOX', $staffId = 1, $limit = 100);

// Sync specific folder
$result = $imap->syncEmails('Sent', $staffId = 1);

// Get all folders
$folders = $imap->getFolders();
```

---

## Sending Emails

### Using Rackspace SMTP

```php
use StaffEmailHub\Services\EmailSenderService;

$sender = new EmailSenderService($db, 'rackspace', [
    'rackspace_smtp_host' => 'secure.emailsrvr.com',
    'rackspace_smtp_port' => 587,
    'rackspace_smtp_username' => 'email@domain.com',
    'rackspace_smtp_password' => 'password',
]);

$result = $sender->send([
    'to' => 'customer@example.com',
    'cc' => 'manager@domain.com',
    'subject' => 'Thank you for your order!',
    'body' => '<h1>Order Confirmed</h1><p>Your order has been confirmed.</p>',
    'from' => 'noreply@yourdomain.com',
]);

// Check result
if ($result['success']) {
    echo "Email sent successfully to " . $result['to'];
} else {
    echo "Email failed: " . $result['error'];
}
```

### Using SendGrid API

```php
use StaffEmailHub\Services\EmailSenderService;

$sender = new EmailSenderService($db, 'sendgrid', [
    'sendgrid_api_key' => 'SG.xxxxxxxxxxxxx',
]);

$result = $sender->send([
    'to' => 'customer@example.com',
    'cc' => ['manager@domain.com'],
    'subject' => 'Thank you for your order!',
    'body' => '<h1>Order Confirmed</h1><p>Your order has been confirmed.</p>',
    'from' => 'noreply@yourdomain.com',
    'attachments' => [
        [
            'name' => 'receipt.pdf',
            'path' => '/path/to/receipt.pdf',
            'type' => 'application/pdf',
        ],
    ],
]);
```

### Send with Retry Logic

```php
// Automatically retry on failure (up to 3 attempts)
$result = $sender->sendWithRetry([
    'to' => 'customer@example.com',
    'subject' => 'Important notification',
    'body' => 'This will retry if it fails...',
]);
```

### Queue for Later Sending

```php
// Queue email for background processing
$result = $sender->queue($emailId = 123, [
    'to' => 'customer@example.com',
    'subject' => 'Delayed email',
    'body' => 'This will be sent later...',
]);

// Later, process queue
$processed = $sender->processQueue($limit = 50);
echo "Processed: " . $processed['processed'] . " emails";
```

---

## Testing

### Test Email Sending

#### Test Rackspace SMTP

```bash
# Simple test
curl -X POST http://your-domain.com/api/emails/send \
  -H "Content-Type: application/json" \
  -d '{
    "to": "test@example.com",
    "subject": "Test email",
    "body": "This is a test from Rackspace SMTP"
  }'
```

#### Test SendGrid API

```bash
# SendGrid test
curl -X POST http://your-domain.com/api/emails/send \
  -H "Content-Type: application/json" \
  -d '{
    "provider": "sendgrid",
    "to": "test@example.com",
    "subject": "Test email",
    "body": "This is a test from SendGrid"
  }'
```

### Test IMAP Sync

```bash
# Trigger IMAP sync
curl -X POST http://your-domain.com/api/emails/sync-imap \
  -H "Content-Type: application/json" \
  -d '{
    "folder": "INBOX",
    "limit": 10
  }'
```

### Monitor Email Queue

```bash
# Check queued emails
curl http://your-domain.com/api/emails/queue/pending

# Process queue
curl -X POST http://your-domain.com/api/emails/queue/process \
  -H "Content-Type: application/json" \
  -d '{
    "limit": 50
  }'
```

---

## Troubleshooting

### IMAP Connection Issues

**Error:** `IMAP extension not loaded`

```bash
# Check if extension is loaded
php -m | grep imap

# Install if missing
sudo apt-get install php-imap
sudo systemctl restart php-fpm
```

**Error:** `Connection timed out`

```bash
# Check firewall
sudo firewall-cmd --list-ports

# Open port 993
sudo firewall-cmd --add-port=993/tcp --permanent
sudo firewall-cmd --reload

# Test connection
telnet secure.emailsrvr.com 993
```

**Error:** `Authentication failed`

```bash
# Verify credentials are correct
echo "IMAP Username:" $RACKSPACE_IMAP_USERNAME
echo "IMAP Password length:" ${#RACKSPACE_IMAP_PASSWORD}

# Test credentials in Thunderbird or Outlook
# If it works there, issue is with module configuration
```

### SMTP Sending Issues

**Error:** `SMTP connection failed`

```bash
# Check if port is open
telnet secure.emailsrvr.com 587

# Try alternate port
# Change RACKSPACE_SMTP_PORT to 465 (SSL) instead of 587 (TLS)
```

**Error:** `Authentication failed`

```bash
# SMTP uses same credentials as IMAP
# Verify credentials are identical
grep RACKSPACE .env | grep -E "USERNAME|PASSWORD"

# Username must be full email address
```

### SendGrid Issues

**Error:** `Invalid API key`

```bash
# Check for trailing spaces
grep SENDGRID_API_KEY .env | od -c

# Regenerate key if needed
# API Key never expires, but can be revoked/recreated
```

**Error:** `Rate limit exceeded`

```bash
# SendGrid limits:
# Free: 100 emails/day
# Paid: Depends on plan

# Solutions:
# 1. Use queue and process in batches
# 2. Upgrade SendGrid plan
# 3. Switch to Rackspace SMTP for high volume
```

### Email Delivery Issues

**Problem:** Emails going to spam

**Solutions:**
1. **Verify domain ownership in SendGrid**
2. **Add SPF record:**
   ```
   v=spf1 include:sendgrid.net ~all
   ```

3. **Add DKIM signature:**
   ```
   Add DNS records from SendGrid dashboard
   ```

4. **Add DMARC record:**
   ```
   v=DMARC1; p=quarantine; rua=mailto:admin@domain.com
   ```

**Problem:** Email header issues

```php
// Ensure proper encoding
$email = [
    'to' => 'customer@example.com',
    'subject' => '=?UTF-8?B?' . base64_encode('Subject with special chars') . '?=',
    'body' => 'Use HTML content type',
];
```

---

## Email Configuration Checklist

- [ ] Rackspace IMAP configured and tested
- [ ] Rackspace SMTP or SendGrid configured and tested
- [ ] IMAP sync cron job scheduled
- [ ] Email queue configured
- [ ] Test email sent successfully
- [ ] Domain authentication set up (SendGrid)
- [ ] SPF/DKIM/DMARC records added
- [ ] Staff trained on email client
- [ ] Error logging configured
- [ ] Backup email provider ready

---

## Reference

### Rackspace Email Support
- Website: https://rackspace.com
- Email Help: https://emailhelp.rackspace.com
- Documentation: https://support.rackspace.com/how-to/

### SendGrid Documentation
- Website: https://sendgrid.com
- API Docs: https://docs.sendgrid.com/api-reference/
- Email Authentication: https://sendgrid.com/docs/ui/account-and-settings/how-to-set-up-domain-authentication/

### Port Reference
- 143: IMAP + TLS (cleartext with upgrade to TLS)
- 993: IMAP + SSL (encrypted from start)
- 25: SMTP (deprecated, don't use)
- 465: SMTP + SSL (encrypted from start)
- 587: SMTP + TLS (cleartext with upgrade to TLS)

---

**Email Configuration Guide - Version 1.0.0**
**Last Updated:** November 2025
