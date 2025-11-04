#!/bin/bash
# ============================================================================
# Quick-Dial PHP-FPM Error Log Tail
# Creates gzipped snapshots and returns last N lines
# ============================================================================

LINES="${1:-200}"
LOG_FILE="/var/log/php-fpm/error.log"
SNAPSHOT_DIR="/var/log/cis/snapshots"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
SNAPSHOT_FILE="$SNAPSHOT_DIR/php_fpm_error_$TIMESTAMP.log.gz"

# Create snapshot directory if not exists
mkdir -p "$SNAPSHOT_DIR" 2>/dev/null || true

# Check if log file exists
if [ ! -f "$LOG_FILE" ]; then
    echo "ERROR: Log file not found: $LOG_FILE"
    exit 1
fi

# Tail log and gzip
tail -n "$LINES" "$LOG_FILE" | gzip > "$SNAPSHOT_FILE" 2>/dev/null || {
    # If we can't write to snapshot dir, just output to stdout
    tail -n "$LINES" "$LOG_FILE"
    exit 0
}

# Clean up old snapshots (keep last 50)
ls -t "$SNAPSHOT_DIR"/php_fpm_error_*.log.gz 2>/dev/null | tail -n +51 | xargs rm -f 2>/dev/null || true

# Output the snapshot file path
echo "$SNAPSHOT_FILE"
