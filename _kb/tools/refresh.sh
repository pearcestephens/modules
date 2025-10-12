#!/usr/bin/env bash
set -euo pipefail

# CIS Knowledge Base Server Refresh Script
# Auto-refreshes knowledge base on Remote-SSH server

WEBROOT="/home/129337.cloudwaysapps.com/jcepnzzkmj/public_html"
LOGDIR="$WEBROOT/_copilot/logs"
MODULES_DIR="$WEBROOT/modules"

# Ensure log directory exists
mkdir -p "$LOGDIR"

# Change to modules directory
cd "$MODULES_DIR"

# Run the Node.js indexer if present (safe no-op if missing)
if [ -f ".vscode/refresh-kb.js" ]; then
    node .vscode/refresh-kb.js --server-mode >/dev/null 2>&1 || true
fi

# Touch STATUS with timestamp
ts=$(date -Is)
echo "$ts - refresh completed" >> "$WEBROOT/_copilot/STATUS.md"

# Light OPcache reset once (best effort)
php -r 'if(function_exists("opcache_reset")) opcache_reset();' >/dev/null 2>&1 || true

# Success indicator
echo "KB refresh completed at $ts"