#!/usr/bin/env bash
set -euo pipefail

LOG_FILE="${1:-/var/log/apache2/error.log}"
LINES="${2:-200}"
SNAPSHOT_DIR="${3:-/var/log/cis/snapshots}"

if [ ! -r "$LOG_FILE" ]; then
  echo "ERROR: Log file not readable: $LOG_FILE" >&2
  exit 1
fi

mkdir -p "$SNAPSHOT_DIR"
STAMP="$(date +%Y%m%d_%H%M%S)"
OUT_FILE="$SNAPSHOT_DIR/errorlog_${STAMP}.log.gz"

tail -n "$LINES" "$LOG_FILE" | gzip -9 > "$OUT_FILE"

echo "Snapshot written: $OUT_FILE"
