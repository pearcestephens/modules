#!/bin/bash
# Find Apache error logs

echo "Looking for Apache error logs..."
echo ""

# Common locations
locations=(
    "/var/log/apache2/error.log"
    "/var/log/httpd/error_log"
    "/home/master/logs/apache_error.log"
    "/home/master/applications/jcepnzzkmj/logs/apache_error.log"
    "/opt/cloudways/logs/apache_error.log"
)

for loc in "${locations[@]}"; do
    if [ -f "$loc" ]; then
        echo "✓ Found: $loc"
        echo "Last 20 lines:"
        tail -20 "$loc" | grep -i "employee\|staff-account\|Fatal\|Parse error" || tail -20 "$loc"
        echo ""
    fi
done

# Also check systemd journal if available
if command -v journalctl &> /dev/null; then
    echo "Checking systemd journal for Apache errors..."
    journalctl -u apache2 -n 20 --no-pager 2>/dev/null || echo "journalctl not available"
fi

# Check if we can access error logs via cloudways
echo ""
echo "Checking application logs directory..."
find /home/master/applications/jcepnzzkmj -name "*error*log" -type f 2>/dev/null | head -10
