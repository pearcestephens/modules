#!/usr/bin/env bash
# File: modules/tools/verify/pages-suite.sh
# Purpose: Probe page/view endpoints across modules and report HTTP status and <title>
# Author: GitHub Copilot
# Last Modified: 2025-11-02
#
# Usage:
#   BASE_URL="https://staff.vapeshed.co.nz" ./modules/tools/verify/pages-suite.sh
#
# Output:
#   CSV saved to /tmp/pages_all.csv with columns: code,url,title

set -euo pipefail

BASE_URL=${BASE_URL:-"https://staff.vapeshed.co.nz"}
ROOT_DIR="/home/master/applications/jcepnzzkmj/public_html"

cd "$ROOT_DIR"

OUT_CSV="/tmp/pages_all.csv"
TMP_LIST="/tmp/pages_list_raw.txt"

# Build list of candidate pages
# 1) Module root pages
find modules -mindepth 2 -maxdepth 2 -type f -name 'index.php' -print > "$TMP_LIST"
find modules -mindepth 2 -maxdepth 2 -type f -name 'dashboard.php' -print >> "$TMP_LIST"
# 2) Top-level module PHP pages (exclude api, health, tests, vendor, lib, assets)
find modules -mindepth 2 -maxdepth 2 -type f -name '*.php' \
  ! -path '*/api/*' ! -path '*/health/*' ! -path '*/tests/*' ! -path '*/vendor/*' \
  ! -path '*/lib/*' ! -path '*/assets/*' ! -path '*/_kb/*' \
  ! -name 'index.php' ! -name 'dashboard.php' \
  -print >> "$TMP_LIST"
# 3) Transfer/manager specific pages anywhere under modules (non-API)
find modules -type f -name '*transfer*\.php' ! -path '*/api/*' ! -path '*/tests/*' -print >> "$TMP_LIST"
find modules -type f -name '*manager*\.php' ! -path '*/api/*' ! -path '*/tests/*' -print >> "$TMP_LIST"

# De-duplicate and sort
sort -u "$TMP_LIST" -o "$TMP_LIST"

# Prepare CSV header
printf 'code,url,title\n' > "$OUT_CSV"

# Curl helpers
CURL_OPTS=(--silent --show-error --location --connect-timeout 5 --max-time 12)

extract_title() {
  # Read HTML from stdin, extract <title> content (single line, trimmed)
  sed -n 's/.*<title>\([^<]*\)<\/title>.*/\1/ip' | head -n 1 | tr -d '\r' | sed 's/,/ /g' | cut -c1-140
}

probe_page() {
  local rel="$1"
  local url="${BASE_URL%/}/$rel"
  # Fetch headers to get status
  local code
  code=$(curl "${CURL_OPTS[@]}" -o /dev/null -w '%{http_code}' "$url" || echo 000)
  # If 200, try to fetch body and title; otherwise leave title blank
  local title=""
  if [[ "$code" == "200" ]]; then
    # Fetch body with a size cap (first 64KB)
    title=$(curl "${CURL_OPTS[@]}" "$url" | head -c 65536 | extract_title || true)
  fi
  printf '%s,%s,%s\n' "$code" "$url" "$title" >> "$OUT_CSV"
}

# Iterate through pages
while IFS= read -r path; do
  # Normalize leading ./ if present
  rel="${path#./}"
  probe_page "$rel"
done < "$TMP_LIST"

# Print summary
total=$(( $(wc -l < "$OUT_CSV") - 1 ))
ok200=$(grep -E '^200,' "$OUT_CSV" | wc -l || true)
redir=$(grep -E '^(301|302),' "$OUT_CSV" | wc -l || true)
auth=$(grep -E '^(401|403),' "$OUT_CSV" | wc -l || true)
server=$(grep -E '^5[0-9][0-9],' "$OUT_CSV" | wc -l || true)
unknown=$(grep -E '^000,' "$OUT_CSV" | wc -l || true)

echo "TOTAL:$total  OK200:$ok200  REDIR:$redir  AUTH:$auth  SERVER_ERR:$server  UNKNOWN:$unknown"
echo "CSV: $OUT_CSV"
