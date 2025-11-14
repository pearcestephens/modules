#!/usr/bin/env bash
set -euo pipefail

BASE="https://staff.vapeshed.co.nz/modules/employee-onboarding/api/onboard.php"
COOKIES="/tmp/onboarding_cookies.txt"

# This script assumes you already have an authenticated session cookie in $COOKIES
# If not, login via browser and export cookies to this file.

RED='\033[0;31m'; GREEN='\033[0;32m'; YELLOW='\033[1;33m'; NC='\033[0m'

req() { curl -sS -b "$COOKIES" -c "$COOKIES" -X POST "$BASE" -F "$1"; }

printf "${YELLOW}==> Testing onboarding API…${NC}\n"

payload=(
  "first_name=Test"
  "last_name=User"
  "email=test.user+$(date +%s)@vapeshed.co.nz"
  "job_title=Store Associate"
  "location_id=1"
  "roles[]=7" # 'staff' role by default
  "sync_xero=on"
  "sync_deputy=on"
  "sync_lightspeed=on"
)

response=$(curl -sS -b "$COOKIES" -c "$COOKIES" -X POST "$BASE" \
  -F "${payload[0]}" -F "${payload[1]}" -F "${payload[2]}" -F "${payload[3]}" \
  -F "${payload[4]}" -F "${payload[5]}" -F "${payload[6]}" -F "${payload[7]}" -F "${payload[8]}")

echo "$response" | jq . || echo "$response"

if echo "$response" | grep -q '"success":true'; then
  printf "${GREEN}✔ API success${NC}\n"
else
  printf "${RED}✖ API failed${NC}\n"
fi
