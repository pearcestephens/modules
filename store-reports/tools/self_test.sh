#!/bin/bash
set -euo pipefail
BASE="https://staff.vapeshed.co.nz/modules/store-reports" # Updated host for production staff portal
CLI_FALLBACK=1

echo "[Self-Test] Store Reports Module"

csrf() { echo "$1"; }

echo "1. Create dummy report (manual DB insert recommended) - skipping direct creation (no API)."
echo "2. List dashboard (should be 200)"
HTTP_CODE=$(curl -s -o /dev/null -w '%{http_code}' "$BASE/?action=dashboard" || true)
echo "$HTTP_CODE"
if [ "$HTTP_CODE" != "200" ] && [ "$CLI_FALLBACK" = "1" ]; then
	echo "Dashboard via CLI fallback"
		php tools/cli_call.php action=dashboard dev_no_auth=1 || true
fi

echo "3. Trends endpoint"
TRENDS=$(curl -s "$BASE/?action=api:get-trends" | head -c 300 || true)
if [ -z "$TRENDS" ] && [ "$CLI_FALLBACK" = "1" ]; then
	echo "(HTTP empty) Using CLI fallback"
		php tools/cli_call.php action=api:get-trends dev_no_auth=1 | head -c 300 || true
else
	echo "$TRENDS"
fi

echo "4. Get non-existent report (expect error)"
NR=$(curl -s "$BASE/?action=api:get-report&report_id=999999" | head -c 120 || true)
if [ -z "$NR" ] && [ "$CLI_FALLBACK" = "1" ]; then
	echo "(HTTP empty) CLI fallback"
		php tools/cli_call.php action=api:get-report report_id=999999 dev_no_auth=1 | head -c 120 || true
else
	echo "$NR"
fi

echo "Self-test complete (basic). Extend with auth + creation as needed."
