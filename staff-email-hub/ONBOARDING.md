# Staff Email Hub - Onboarding Guide

Complete setup guide for the Staff Email Hub module with Rackspace IMAP/SMTP and SendGrid integration.

## Table of Contents

1. [Quick Start](#quick-start)
2. [Prerequisites](#prerequisites)
3. [Step 1: Environment Setup](#step-1-environment-setup)
4. [Step 2: Database Installation](#step-2-database-installation)
5. [Step 3: Email Configuration](#step-3-email-configuration)
6. [Step 4: File Storage Setup](#step-4-file-storage-setup)
7. [Step 5: Load Sample Data](#step-5-load-sample-data)
8. [Verification & Testing](#verification--testing)
9. [Troubleshooting](#troubleshooting)

---

## Quick Start

If you want to get started immediately with demo data:

```bash
# 1. Navigate to module directory
cd /home/master/applications/jcepnzzkmj/public_html/modules/staff-email-hub

# 2. Create required directories
mkdir -p storage/id_uploads storage/email_attachments storage/temp storage/logs

# 3. Import database schema
mysql -u root -p your_database < Database/migrations_staff_email_hub_enhanced.sql

# 4. Access onboarding wizard
# Navigate to: http://your-domain.com/admin/onboarding/wizard
```

---

## Prerequisites

### System Requirements

- **PHP**: 8.0 or higher
- **MySQL**: 5.7 or higher (8.0 recommended)
- **Extensions Required**:
  - PDO & PDO MySQL
  - cURL
  - FileInfo
  - OpenSSL
  - GD (for image processing)
  - IMAP (for Rackspace IMAP integration)

### Email Provider Credentials

You'll need credentials for ONE of:

#### Option A: Rackspace Email (IMAP + SMTP)
```
- IMAP Server: secure.emailsrvr.com
- IMAP Port: 993 (SSL)
- SMTP Server: secure.emailsrvr.com
- SMTP Port: 587 (TLS) or 465 (SSL)
- Username: your-email@yourdomain.com
- Password: Your email password
```

#### Option B: SendGrid API
```
- API Key: You generate this in SendGrid dashboard
- Website: https://sendgrid.com
```

### Vend API Integration (Optional)
- Vend API token
- Vend domain URL

---

## Step 1: Environment Setup

### 1.1 Check PHP Extensions

```bash
# Verify PHP version
php -v

# Check required extensions
php -m | grep -E "PDO|curl|fileinfo|openssl|gd"

# Install missing extensions (Ubuntu/Debian example)
sudo apt-get install php-curl php-gd php-imap
```

### 1.2 Create Environment File

```bash
# Copy example environment file
cp .env.example .env

# Edit with your settings
nano .env
```

### 1.3 Required .env Variables

```env
# Application
APP_NAME="Staff Email Hub"
APP_ENV=production
APP_DEBUG=false

# Database
DB_HOST=localhost
DB_PORT=3306
DB_NAME=staff_email_hub
DB_USER=your_db_user
DB_PASSWORD=your_db_password

# Rackspace Email (IMAP)
RACKSPACE_IMAP_HOST=secure.emailsrvr.com
RACKSPACE_IMAP_PORT=993
RACKSPACE_IMAP_USERNAME=your-email@yourdomain.com
RACKSPACE_IMAP_PASSWORD=your_email_password

# Rackspace Email (SMTP)
RACKSPACE_SMTP_HOST=secure.emailsrvr.com
RACKSPACE_SMTP_PORT=587
RACKSPACE_SMTP_USERNAME=your-email@yourdomain.com
RACKSPACE_SMTP_PASSWORD=your_email_password

# SendGrid (Alternative to Rackspace SMTP)
SENDGRID_API_KEY=SG.xxxxxxxxxxxxxxxxxxxxxxxxxxxxx

# File Upload
MAX_UPLOAD_SIZE=5242880
ALLOWED_FILE_TYPES=pdf,jpg,png,doc,docx

# Features
FEATURE_EMAIL_CLIENT=true
FEATURE_CUSTOMER_HUB=true
FEATURE_ID_VERIFICATION=true
FEATURE_ADVANCED_SEARCH=true
```

### 1.4 Verify Environment

Access the onboarding endpoint:

```bash
curl http://your-domain.com/admin/onboarding/status
```

Expected response:
```json
{
  "success": true,
  "status": {
    "step1_environment": {
      "passed": true,
      "checks": {
        "php_version": true,
        "pdo_available": true,
        "curl_available": true
      }
    }
  }
}
```

---

## Step 2: Database Installation

### 2.1 Create Database

```bash
# Login to MySQL
mysql -u root -p

# Create database
CREATE DATABASE staff_email_hub CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

# Create user
CREATE USER 'email_hub'@'localhost' IDENTIFIED BY 'secure_password';

# Grant permissions
GRANT ALL PRIVILEGES ON staff_email_hub.* TO 'email_hub'@'localhost';
FLUSH PRIVILEGES;

# Exit
EXIT;
```

### 2.2 Import Schema

```bash
# Import the enhanced migration with all tables
mysql -u email_hub -p staff_email_hub < Database/migrations_staff_email_hub_enhanced.sql

# Verify tables were created
mysql -u email_hub -p staff_email_hub -e "SHOW TABLES;"
```

### 2.3 Expected Tables (11 total)

```
✓ module_config
✓ staff_emails
✓ staff_email_templates
✓ email_attachments
✓ email_queue
✓ customer_hub_profile
✓ customer_purchase_history
✓ customer_communication_log
✓ customer_search_index
✓ customer_id_uploads
✓ id_verification_audit_log
✓ email_search_index
✓ email_access_log
✓ imap_sync_log
```

### 2.4 Verify Database Setup

```bash
# Via API
curl http://your-domain.com/admin/onboarding/status | jq '.status.step2_database'

# Via MySQL
mysql -u email_hub -p staff_email_hub -e "
  SELECT COUNT(*) as tables_found
  FROM information_schema.tables
  WHERE table_schema = 'staff_email_hub';
"
```

---

## Step 3: Email Configuration

### 3.1 Configure Rackspace Email (Recommended)

#### Step A: Configure IMAP

```bash
# POST request to configure IMAP
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

#### Step B: Test IMAP Connection

```bash
# Test the IMAP configuration
curl -X POST http://your-domain.com/admin/onboarding/test-email \
  -H "Content-Type: application/json" \
  -d '{
    "provider": "rackspace",
    "test_email": "test@yourdomain.com"
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

### 3.2 Configure SendGrid (Alternative)

#### Step A: Get API Key

1. Go to https://sendgrid.com
2. Sign up or login
3. Navigate to Settings → API Keys
4. Create a new API key
5. Copy the key

#### Step B: Configure SendGrid

```bash
# POST request to configure SendGrid
curl -X POST http://your-domain.com/admin/onboarding/configure-email \
  -H "Content-Type: application/json" \
  -d '{
    "provider": "sendgrid",
    "sendgrid": {
      "api_key": "SG.xxxxxxxxxxxxxxxxxxxxxxxxxxxxx"
    }
  }'
```

#### Step C: Test SendGrid

```bash
# Test the SendGrid configuration
curl -X POST http://your-domain.com/admin/onboarding/test-email \
  -H "Content-Type: application/json" \
  -d '{
    "provider": "sendgrid",
    "test_email": "your-email@yourdomain.com"
  }'
```

### 3.3 Verify Email Configuration

```bash
# Check email config status
curl http://your-domain.com/admin/onboarding/status | jq '.status.step3_email_config'
```

Expected response shows enabled provider:
```json
{
  "passed": true,
  "rackspace_imap": { "enabled": true },
  "rackspace_smtp": { "enabled": true },
  "sendgrid": { "enabled": false },
  "has_email_provider": true
}
```

---

## Step 4: File Storage Setup

### 4.1 Create Storage Directories

```bash
# Navigate to module directory
cd /home/master/applications/jcepnzzkmj/public_html/modules/staff-email-hub

# Create directories
mkdir -p storage/id_uploads
mkdir -p storage/email_attachments
mkdir -p storage/temp
mkdir -p storage/logs
mkdir -p cache

# Set permissions (755 = rwxr-xr-x)
chmod 755 storage
chmod 755 storage/id_uploads
chmod 755 storage/email_attachments
chmod 755 storage/temp
chmod 755 storage/logs
chmod 755 cache

# Make writable for web server
sudo chown -R www-data:www-data storage cache
```

### 4.2 Create Symbolic Links (Optional)

```bash
# Link to public directory if needed
cd /path/to/public
ln -s ../modules/staff-email-hub/storage/id_uploads id_uploads
ln -s ../modules/staff-email-hub/storage/email_attachments email_attachments
```

### 4.3 Verify Storage Setup

```bash
# Via API
curl http://your-domain.com/admin/onboarding/status | jq '.status.step4_file_storage'

# Via filesystem
ls -la /home/master/applications/jcepnzzkmj/public_html/modules/staff-email-hub/storage/
```

---

## Step 5: Load Sample Data

### 5.1 Automatic Data Loading

```bash
# Load sample customers, orders, and emails
curl -X POST http://your-domain.com/admin/onboarding/load-sample-data
```

This will create:
- **5 demo customers** with full profiles
- **10-25 demo orders** with realistic order data
- **15-40 demo emails** with sample correspondence
- **Automatically populated communication logs**

### 5.2 Sample Data Details

The seeder creates realistic demo data:

**Sample Customers:**
```
1. John Smith (VIP) - john.smith@example.com
   - Total Spent: $3,450.50
   - ID Verified: Yes
   - 5+ orders, 8+ communications

2. Sarah Johnson - sarah.johnson@example.com
   - Total Spent: $1,250.00
   - ID Verified: Yes
   - 3+ orders, 5+ communications

3. Mike Williams - mike.w@example.com
   - Total Spent: $850.75
   - ID Verified: No (pending)
   - 2+ orders, 4+ communications

4. Emma Brown (VIP) - emma.brown@example.com
   - Total Spent: $2,890.00
   - ID Verified: Yes
   - 4+ orders, 7+ communications

5. David Taylor - david.taylor@example.com
   - Total Spent: $1,520.25
   - ID Verified: Yes
   - 3+ orders, 5+ communications
```

**Sample Products Included:**
- SMOK Nord 4 ($89.99)
- Vape Liquid 100ML ($24.99)
- Coils Pack ($19.99)
- Vape Pod Kit ($45.50)
- Nicotine Salts ($15.99)
- Battery 18650 ($8.99)
- Starter Kit ($120.00)
- Replacement Coils ($22.50)

**Sample Communications:**
- Order confirmations
- Shipping notifications
- Follow-up calls
- In-person store visits
- Email promotions
- Satisfaction surveys

### 5.3 Verify Sample Data

```bash
# Check sample data loaded
curl http://your-domain.com/admin/onboarding/status | jq '.status.step6_sample_data'

# Via MySQL
mysql -u email_hub -p staff_email_hub -e "
  SELECT
    (SELECT COUNT(*) FROM customer_hub_profile WHERE is_demo_data=true) as demo_customers,
    (SELECT COUNT(*) FROM staff_emails WHERE is_demo_data=true) as demo_emails,
    (SELECT COUNT(*) FROM customer_purchase_history WHERE is_demo_data=true) as demo_orders,
    (SELECT COUNT(*) FROM customer_communication_log WHERE is_demo_data=true) as demo_communications;
"
```

### 5.4 Clear Sample Data (When Ready)

```bash
# Remove all demo data
curl -X POST http://your-domain.com/admin/onboarding/clear-sample-data
```

---

## Verification & Testing

### 6.1 Full Onboarding Status

```bash
# Get complete wizard status
curl http://your-domain.com/admin/onboarding/wizard | jq '.'
```

### 6.2 Test Each Component

```bash
# Test email client functionality
curl http://your-domain.com/api/emails/inbox?staff_id=1&page=1

# Test customer hub
curl http://your-domain.com/api/customers/search?query=smith

# Test search
curl http://your-domain.com/api/search/global?query=vape&page=1

# Test ID verification endpoints
curl http://your-domain.com/api/id-verification/pending
```

### 6.3 Mark Onboarding Complete

```bash
# Mark as complete
curl -X POST http://your-domain.com/admin/onboarding/complete \
  -H "Content-Type: application/json" \
  -d '{
    "completed_by": "admin@yourdomain.com"
  }'
```

---

## Troubleshooting

### Issue: PHP Extensions Missing

**Problem:** `curl` or other extensions not loaded

**Solution:**
```bash
# Ubuntu/Debian
sudo apt-get install php-curl php-gd php-imap php-fileinfo

# CentOS/RHEL
sudo yum install php-curl php-gd php-imap

# Restart PHP-FPM
sudo systemctl restart php-fpm

# Verify
php -m | grep curl
```

### Issue: Database Connection Failed

**Problem:** MySQL connection error

**Solution:**
```bash
# Check MySQL is running
sudo systemctl status mysql

# Verify credentials
mysql -u email_hub -p -h localhost staff_email_hub -e "SELECT 1;"

# Check permissions
mysql -u root -p -e "SHOW GRANTS FOR 'email_hub'@'localhost';"
```

### Issue: File Upload Permissions

**Problem:** Cannot write to storage directories

**Solution:**
```bash
# Check ownership
ls -la /path/to/storage

# Fix permissions
sudo chown -R www-data:www-data /path/to/storage
sudo chmod -R 755 /path/to/storage
sudo chmod -R 644 /path/to/storage/*
```

### Issue: IMAP Connection Failed

**Problem:** `Failed to connect to Rackspace IMAP`

**Solution:**
```bash
# Verify IMAP extension installed
php -m | grep imap

# If not installed
sudo apt-get install php-imap
sudo systemctl restart php-fpm

# Test connection manually
telnet secure.emailsrvr.com 993

# Check .env variables
grep RACKSPACE .env
```

### Issue: SendGrid API Error

**Problem:** `SendGrid API error: HTTP 401`

**Solution:**
1. Verify API key is correct (check trailing spaces)
2. Regenerate key in SendGrid dashboard
3. Ensure account is not in sandbox mode
4. Check IP whitelisting (if enabled)

### Issue: Sample Data Not Loading

**Problem:** Demo data missing after seeding

**Solution:**
```bash
# Manually check seeder
php -r "
  require 'Database/DataSeeder.php';
  \$seeder = new \StaffEmailHub\Database\DataSeeder(\$db, true);
  print_r(\$seeder->seed());
"

# Check for errors in logs
tail -f /path/to/storage/logs/error.log
```

---

## Next Steps

After onboarding is complete:

1. **Configure Email Sync**
   - Set up IMAP sync cron job (optional)
   - Schedule: `*/5 * * * * php /path/to/cron/imap-sync.php`

2. **Import Real Customer Data**
   - Sync Vend customer database
   - Configure Vend API integration
   - Run customer sync command

3. **Train Staff**
   - Review email client features
   - Show customer hub capabilities
   - Demonstrate search functionality

4. **Configure Advanced Features**
   - Set up email automation rules
   - Create custom email templates
   - Configure webhook notifications (optional)

5. **Monitor Performance**
   - Check email queue status
   - Review access logs
   - Monitor IMAP sync status

---

## Support

For issues or questions:
- Check `/storage/logs/` for error details
- Review MySQL error log: `mysql -u root -p -e "SHOW ENGINE INNODB STATUS\G"`
- Contact support: support@vapeshed.co.nz

---

## Security Checklist

Before going live:

- [ ] Change all default passwords
- [ ] Enable SSL/TLS for all connections
- [ ] Configure firewall rules
- [ ] Set up regular database backups
- [ ] Enable audit logging
- [ ] Configure rate limiting
- [ ] Review user permissions
- [ ] Test data encryption
- [ ] Set up error monitoring
- [ ] Document API keys location

---

**Onboarding Version:** 1.0.0
**Last Updated:** November 2025
**Status:** ✅ Ready for Production
