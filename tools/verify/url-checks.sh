#!/usr/bin/env bash
set -euo pipefail

BASE_URL="${1:-http://localhost/modules/base/public/index.php}"

function check() {
  local endpoint="$1"; shift
  echo "Checking: $endpoint"
  http_code=$(curl -s -o /dev/null -w "%{http_code}" "${BASE_URL}?endpoint=${endpoint}")
  echo "HTTP ${http_code} - ${endpoint}"
}

check "admin/health/ping"
check "admin/health/phpinfo"
check "admin/logs/apache-error-tail"

echo "Done."
