# Consignments Deployment Guide

**Version:** 2.0.0
**Last Updated:** November 2, 2025

## Prerequisites

### System Requirements
- **PHP:** 8.1 or higher
- **MySQL/MariaDB:** 8.0+ / 10.5+
- **Extensions:** pdo, pdo_mysql, mbstring, json, curl, gd
- **Composer:** 2.x
- **Git:** Any recent version
- **Web Server:** Apache/Nginx with PHP-FPM

### External Services
- **Lightspeed Retail Account** (with API access)
- **SMTP Server** (for notifications)
- **Freight Provider Account** (optional, for shipping)

---

## Step-by-Step Deployment

### 1. Clone Repository
```bash
cd /var/www/
git clone https://github.com/pearcestephens/modules.git
cd modules/consignments
```

### 2. Install Dependencies
```bash
composer install --no-dev --optimize-autoloader
```

### 3. Environment Configuration
```bash
cp .env.example .env
nano .env
```

Configure the following variables:
```env
# Database
DB_HOST=127.0.0.1
DB_NAME=consignments_prod
DB_USER=consignments_user
DB_PASS=your_secure_password

# Lightspeed API
LS_ACCOUNT_ID=your_account_id
LS_CLIENT_ID=your_client_id
LS_CLIENT_SECRET=your_client_secret
LS_WEBHOOK_SECRET=your_webhook_secret_minimum_32_chars

# Application
APP_ENV=production
APP_DEBUG=false
APP_URL=https://staff.vapeshed.co.nz/consignments

# Queue
QUEUE_WORKER_SLEEP=5
QUEUE_MAX_ATTEMPTS=3

# Freight (optional)
FREIGHT_PROVIDER=freight_now
FREIGHT_API_KEY=your_freight_api_key
```

### 4. Database Setup
```bash
# Create database
mysql -u root -p -e "CREATE DATABASE consignments_prod CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Create user
mysql -u root -p -e "CREATE USER 'consignments_user'@'localhost' IDENTIFIED BY 'your_secure_password';"
mysql -u root -p -e "GRANT ALL PRIVILEGES ON consignments_prod.* TO 'consignments_user'@'localhost';"
mysql -u root -p -e "FLUSH PRIVILEGES;"

# Run migrations
mysql -u consignments_user -p consignments_prod < database/schema.sql
mysql -u consignments_user -p consignments_prod < database/o6-queue-infrastructure.sql
mysql -u consignments_user -p consignments_prod < database/o7-webhook-infrastructure.sql
mysql -u consignments_user -p consignments_prod < database/09-receiving-evidence.sql
mysql -u consignments_user -p consignments_prod < database/10-freight-bookings.sql
```

### 5. File Permissions
```bash
# Create upload directories
mkdir -p uploads/receiving
mkdir -p logs

# Set permissions
chown -R www-data:www-data uploads/ logs/
chmod -R 755 uploads/
chmod -R 755 logs/

# Secure .env
chmod 600 .env
```

### 6. Web Server Configuration

#### Apache (.htaccess)
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /consignments/

    # Redirect to public/ for front controller
    RewriteCond %{REQUEST_URI} !^/public/
    RewriteRule ^(.*)$ public/$1 [L]
</IfModule>

# Security headers
<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
    Header set X-Frame-Options "SAMEORIGIN"
    Header set X-XSS-Protection "1; mode=block"
    Header set Referrer-Policy "strict-origin-when-cross-origin"
</IfModule>
```

#### Nginx
```nginx
server {
    listen 443 ssl http2;
    server_name staff.vapeshed.co.nz;

    root /var/www/modules/consignments/public;
    index index.php;

    location /consignments/ {
        alias /var/www/modules/consignments/public/;
        try_files $uri $uri/ /consignments/index.php?$query_string;

        location ~ \.php$ {
            fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
            fastcgi_index index.php;
            include fastcgi_params;
            fastcgi_param SCRIPT_FILENAME $request_filename;
        }
    }

    # Security headers
    add_header X-Content-Type-Options "nosniff";
    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-XSS-Protection "1; mode=block";
}
```

### 7. Queue Worker Setup

#### Systemd Service
```bash
sudo nano /etc/systemd/system/consignments-queue.service
```

```ini
[Unit]
Description=Consignments Queue Worker
After=network.target mysql.service

[Service]
Type=simple
User=www-data
WorkingDirectory=/var/www/modules/consignments
ExecStart=/usr/bin/php bin/queue-worker.php
Restart=always
RestartSec=5

# Logging
StandardOutput=append:/var/www/modules/consignments/logs/queue-worker.log
StandardError=append:/var/www/modules/consignments/logs/queue-worker-error.log

[Install]
WantedBy=multi-user.target
```

```bash
sudo systemctl daemon-reload
sudo systemctl enable consignments-queue.service
sudo systemctl start consignments-queue.service
```

### 8. Poller Setup (Cron)
```bash
crontab -e
```

Add:
```cron
# Poll Lightspeed every 5 minutes
*/5 * * * * cd /var/www/modules/consignments && /usr/bin/php bin/poll-ls-consignments.php >> logs/poller.log 2>&1
```

### 9. Configure Lightspeed Webhooks

In Lightspeed admin panel:
1. Go to Settings → Webhooks
2. Create webhook for: `consignment.created`, `consignment.updated`, `consignment.received`
3. URL: `https://staff.vapeshed.co.nz/consignments/public/webhooks/lightspeed.php`
4. Secret: Use same value as `LS_WEBHOOK_SECRET` in `.env`
5. Enable webhook

### 10. Verify Installation
```bash
# Test database connection
php -r "require 'bootstrap.php'; echo 'DB OK';"

# Test queue worker (once mode)
php bin/queue-worker.php --once

# Test poller
php bin/poll-ls-consignments.php --limit=10

# Check logs
tail -f logs/queue-worker.log
tail -f logs/poller.log
```

---

## Post-Deployment Checklist

### Security
- [ ] `.env` file has correct permissions (600)
- [ ] No sensitive files in public directories
- [ ] HTTPS enabled with valid SSL certificate
- [ ] Security headers configured
- [ ] Firewall rules allow only necessary ports
- [ ] Database user has minimal required permissions

### Functionality
- [ ] Queue worker running (`systemctl status consignments-queue`)
- [ ] Poller cron job scheduled
- [ ] Webhooks configured in Lightspeed
- [ ] Test webhook delivery (check admin dashboard)
- [ ] Test queue processing (create test job)
- [ ] Test transfer creation (PO, stock transfer, RTS)
- [ ] Test receiving workflow with photo upload

### Monitoring
- [ ] Admin dashboard accessible (`/admin/dashboard.php`)
- [ ] Log files rotating properly
- [ ] Queue DLQ empty (or jobs being retried)
- [ ] Webhook success rate > 95%
- [ ] No errors in error log

### Performance
- [ ] Database indexes created (run EXPLAIN on slow queries)
- [ ] PHP OpCache enabled
- [ ] Composer autoloader optimized
- [ ] Upload directory has sufficient space
- [ ] Log rotation configured

---

## Rollback Procedure

If deployment fails:

### 1. Stop Services
```bash
sudo systemctl stop consignments-queue.service
```

### 2. Restore Database
```bash
mysql -u consignments_user -p consignments_prod < backup/database_backup.sql
```

### 3. Revert Code
```bash
git checkout <previous_commit_hash>
composer install --no-dev
```

### 4. Restart Services
```bash
sudo systemctl start consignments-queue.service
```

---

## Troubleshooting

### Queue Worker Not Processing Jobs
```bash
# Check service status
sudo systemctl status consignments-queue.service

# Check logs
tail -100 logs/queue-worker-error.log

# Manually test
php bin/queue-worker.php --once

# Common fixes:
# - Check database connection
# - Verify .env file readable
# - Check file permissions
# - Restart service: sudo systemctl restart consignments-queue.service
```

### Webhooks Not Receiving
```bash
# Check webhook endpoint
curl -X POST https://staff.vapeshed.co.nz/consignments/public/webhooks/lightspeed.php \
  -H "X-Lightspeed-Signature: test" \
  -d '{"event_id":"test"}'

# Check logs
tail -100 logs/webhooks.log

# Common fixes:
# - Verify LS_WEBHOOK_SECRET matches Lightspeed
# - Check firewall allows incoming requests
# - Verify URL accessible from internet
```

### Slow Performance
```bash
# Check slow queries
mysql -u consignments_user -p consignments_prod \
  -e "SELECT * FROM information_schema.processlist WHERE time > 5;"

# Check queue backlog
mysql -u consignments_user -p consignments_prod \
  -e "SELECT status, COUNT(*) FROM queue_jobs GROUP BY status;"

# Add indexes if needed
mysql -u consignments_user -p consignments_prod < database/add-indexes.sql
```

---

## Production Maintenance

### Daily Tasks
- Review admin dashboard for errors
- Check DLQ for failed jobs
- Monitor queue processing times

### Weekly Tasks
- Review slow query log
- Check disk space for uploads/logs
- Verify backup restoration

### Monthly Tasks
- Update dependencies (`composer update`)
- Review and optimize database indexes
- Archive old logs and uploads

---

## Support Contacts

- **System Admin:** Pearce Stephens <pearce.stephens@ecigdis.co.nz>
- **Database Issues:** DBA Team
- **Lightspeed API:** api-support@lightspeedhq.com

---

## Version History

- **2.0.0** (Nov 2, 2025) - Complete refactor with hexagonal architecture
- **1.x** - Legacy monolithic system
