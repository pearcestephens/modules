#!/bin/bash
#
# Age Verification Photo Cleanup CRON Job
#
# Runs daily at 2:00 AM to delete expired ID photos per retention policy
# Add to crontab: 0 2 * * * /path/to/age-verification-cleanup.sh
#

# Configuration
PHP_BIN="/usr/bin/php"
SCRIPT_DIR="/home/master/applications/jcepnzzkmj/public_html/modules/ecommerce-ops/api/age-verification"
LOG_FILE="/home/master/applications/jcepnzzkmj/public_html/modules/ecommerce-ops/logs/cleanup.log"

# Timestamp for logging
TIMESTAMP=$(date '+%Y-%m-%d %H:%M:%S')

echo "[$TIMESTAMP] Starting age verification photo cleanup..." >> "$LOG_FILE"

# Run PHP cleanup script
$PHP_BIN "$SCRIPT_DIR/cleanup-expired-photos.php" >> "$LOG_FILE" 2>&1

EXIT_CODE=$?

if [ $EXIT_CODE -eq 0 ]; then
    echo "[$TIMESTAMP] Cleanup completed successfully" >> "$LOG_FILE"
else
    echo "[$TIMESTAMP] Cleanup failed with exit code $EXIT_CODE" >> "$LOG_FILE"
fi

# Keep log file size manageable (keep last 1000 lines)
tail -n 1000 "$LOG_FILE" > "$LOG_FILE.tmp"
mv "$LOG_FILE.tmp" "$LOG_FILE"

exit $EXIT_CODE
