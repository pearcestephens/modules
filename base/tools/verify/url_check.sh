#!/usr/bin/env bash

# Simple URL verification script for PHASEs 0-3
# Usage: ./url_check.sh https://example.com/admin/health/ping

URLS=(
  "$1"
  "${2:-http://localhost/?endpoint=admin/health/ping}"
  "${3:-http://localhost/?endpoint=admin/health/checks}"
  "http://localhost/?endpoint=admin/health/phpinfo"
  "http://localhost/?endpoint=admin/traffic/live"
)

for u in "${URLS[@]}"; do
  echo "Checking: $u"
  curl -I -s -S --max-time 10 "$u" | sed -n '1,5p'
  echo "----"
done
