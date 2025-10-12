#!/usr/bin/env bash
set -euo pipefail

# CIS Knowledge Base Cron Setup Script
# Safely adds cron jobs for knowledge base auto-refresh

WEBROOT="/home/129337.cloudwaysapps.com/jcepnzzkmj/public_html"
REFRESH_SCRIPT="$WEBROOT/modules/_kb/tools/refresh.sh"
LOG_FILE="$WEBROOT/_copilot/logs/refresh.log"

echo "🔧 Setting up CIS Knowledge Base cron jobs..."

# Check if refresh script exists
if [ ! -f "$REFRESH_SCRIPT" ]; then
    echo "❌ Error: Refresh script not found at $REFRESH_SCRIPT"
    exit 1
fi

# Make refresh script executable
echo "📝 Making refresh script executable..."
chmod +x "$REFRESH_SCRIPT"

# Create log directory if it doesn't exist
mkdir -p "$(dirname "$LOG_FILE")"

# Define the cron jobs
CRON_DAILY="40 2 * * *  /usr/bin/env bash -lc '$REFRESH_SCRIPT >> $LOG_FILE 2>&1'"
CRON_6HOURLY="5  */6 * * * /usr/bin/env bash -lc '$REFRESH_SCRIPT >> $LOG_FILE 2>&1'"

echo "📋 Cron jobs to add:"
echo "  Daily at 2:40 AM: $CRON_DAILY"
echo "  Every 6 hours at :05: $CRON_6HOURLY"
echo

# Get current crontab (create empty if none exists)
TEMP_CRON=$(mktemp)
crontab -l > "$TEMP_CRON" 2>/dev/null || true

# Check if cron jobs already exist
if grep -q "$REFRESH_SCRIPT" "$TEMP_CRON" 2>/dev/null; then
    echo "⚠️  Knowledge base cron jobs already exist in crontab"
    echo "   Current entries:"
    grep "$REFRESH_SCRIPT" "$TEMP_CRON" || true
    echo
    read -p "   Replace existing entries? (y/N): " -n 1 -r
    echo
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "❌ Aborted"
        rm -f "$TEMP_CRON"
        exit 0
    fi
    
    # Remove existing entries
    grep -v "$REFRESH_SCRIPT" "$TEMP_CRON" > "${TEMP_CRON}.new" || true
    mv "${TEMP_CRON}.new" "$TEMP_CRON"
fi

# Add new cron jobs
echo "# CIS Knowledge Base Auto-Refresh" >> "$TEMP_CRON"
echo "$CRON_DAILY" >> "$TEMP_CRON"
echo "$CRON_6HOURLY" >> "$TEMP_CRON"
echo "" >> "$TEMP_CRON"

# Install new crontab
echo "📥 Installing new crontab..."
crontab "$TEMP_CRON"

# Clean up
rm -f "$TEMP_CRON"

echo "✅ Cron jobs successfully added!"
echo
echo "📊 You can verify with: crontab -l | grep refresh"
echo "📝 Logs will be written to: $LOG_FILE"
echo "🔄 Next run: $(date -d 'today 02:40' '+%Y-%m-%d %H:%M')"
echo
echo "🧪 Test the refresh script manually:"
echo "   $REFRESH_SCRIPT"