#!/usr/bin/env bash
set -euo pipefail
BASE_URL="${1:-http://127.0.0.1}"
LIMIT="${2:-5}"
URL="$BASE_URL/modules/consignments/api/transfers_enriched.php?limit=$LIMIT"

echo "GET $URL"
curl -sS "$URL" | jq -r '.success as $s | "success=\($s) count=\(.count)"'
