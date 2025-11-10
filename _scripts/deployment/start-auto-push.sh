#!/bin/bash
# Auto-start Git Auto-Push Monitor
# Place this in: ~/.bashrc or ~/.bash_profile
# OR run manually: bash start-auto-push.sh

REPO_PATH="/home/master/applications/jcepnzzkmj/public_html/modules"
MONITOR_SCRIPT="$REPO_PATH/.auto-push-monitor.php"

# Check if monitor is running
if ps aux | grep -v grep | grep ".auto-push-monitor.php" > /dev/null; then
    echo "✓ Auto-push monitor already running"
else
    echo "🚀 Starting auto-push monitor..."
    php "$MONITOR_SCRIPT" start
    sleep 2
    php "$MONITOR_SCRIPT" status
fi
