#!/bin/bash
# Check recent Apache errors related to staff-accounts

echo "════════════════════════════════════════════════════════"
echo "APACHE ERROR LOG - Last 100 lines (staff-accounts)"
echo "════════════════════════════════════════════════════════"
echo ""

# Try multiple possible log locations
if [ -f "/home/master/applications/jcepnzzkmj/logs/apache_error.log" ]; then
    tail -100 /home/master/applications/jcepnzzkmj/logs/apache_error.log | grep -i "staff-accounts\|employee\|Fatal\|Warning" | tail -50
elif [ -f "/home/master/applications/jcepnzzkmj/public_html/logs/apache_phpstack-129337-518184.cloudwaysapps.com.error.log" ]; then
    tail -100 /home/master/applications/jcepnzzkmj/public_html/logs/apache_phpstack-129337-518184.cloudwaysapps.com.error.log | grep -i "staff-accounts\|employee\|Fatal\|Warning" | tail -50
else
    echo "Log file not found. Trying ls logs/"
    ls -la /home/master/applications/jcepnzzkmj/public_html/logs/ | head -20
fi

echo ""
echo "════════════════════════════════════════════════════════"
echo "Checking for syntax errors in key files..."
echo "════════════════════════════════════════════════════════"
echo ""

# Check PHP syntax
echo "Checking index.php..."
php -l /home/master/applications/jcepnzzkmj/public_html/modules/staff-accounts/index.php

echo ""
echo "Checking bootstrap.php..."
php -l /home/master/applications/jcepnzzkmj/public_html/modules/staff-accounts/bootstrap.php

echo ""
echo "Checking EmployeeMappingService.php..."
php -l /home/master/applications/jcepnzzkmj/public_html/modules/staff-accounts/lib/EmployeeMappingService.php
