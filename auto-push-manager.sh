#!/bin/bash
# Auto-start auto-push monitor on system boot (Cloudways workaround)
# This can be called from various places to ensure monitor stays running

REPO_PATH="/home/master/applications/jcepnzzkmj/public_html/modules"
MONITOR_SCRIPT="$REPO_PATH/.auto-push-monitor.php"
PID_FILE="$REPO_PATH/.auto-push.pid"
LOG_FILE="$REPO_PATH/.auto-push.log"

# Function to check if monitor is actually running (not just has a PID)
is_monitor_running() {
    if [ ! -f "$PID_FILE" ]; then
        return 1
    fi

    pid=$(cat "$PID_FILE" 2>/dev/null)
    if [ -z "$pid" ]; then
        return 1
    fi

    # Check if process exists
    if ps -p "$pid" > /dev/null 2>&1; then
        return 0
    else
        return 1
    fi
}

# Function to start monitor
start_monitor() {
    if is_monitor_running; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] ✓ Auto-push monitor already running (PID: $(cat $PID_FILE))" >> "$LOG_FILE"
        return 0
    fi

    echo "[$(date '+%Y-%m-%d %H:%M:%S')] Starting auto-push monitor..." >> "$LOG_FILE"
    php "$MONITOR_SCRIPT" start >> "$LOG_FILE" 2>&1

    sleep 2

    if is_monitor_running; then
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] ✓ Auto-push monitor started successfully" >> "$LOG_FILE"
        return 0
    else
        echo "[$(date '+%Y-%m-%d %H:%M:%S')] ✗ Failed to start auto-push monitor" >> "$LOG_FILE"
        return 1
    fi
}

# Main logic
case "${1:-start}" in
    start)
        start_monitor
        ;;
    stop)
        php "$MONITOR_SCRIPT" stop
        ;;
    restart)
        php "$MONITOR_SCRIPT" stop
        sleep 2
        start_monitor
        ;;
    status)
        php "$MONITOR_SCRIPT" status
        ;;
    check-and-restart)
        # Used for watchdog - restart if dead
        if ! is_monitor_running; then
            echo "[$(date '+%Y-%m-%d %H:%M:%S')] Monitor died! Restarting..." >> "$LOG_FILE"
            start_monitor
        fi
        ;;
    *)
        echo "Usage: $0 {start|stop|restart|status|check-and-restart}"
        exit 1
        ;;
esac
