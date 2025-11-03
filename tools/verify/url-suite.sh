#!/usr/bin/env bash
# File: tools/verify/url-suite.sh
# Purpose: Run the URL verification suite against a CIS environment focusing on live endpoints.
# Author: GitHub Copilot
# Last Modified: 2025-11-02
#
# Usage:
#   BASE_URL="https://staff.vapeshed.co.nz" AUTH_COOKIE="cissession=..." \
#       ./tools/verify/url-suite.sh
#
# Optional environment variables:
#   BASE_URL        Base URL to probe (default: https://staff.vapeshed.co.nz)
#   AUTH_COOKIE     Cookie string for authenticated routes (e.g. "cissession=abc")
#   CSRF_HEADER     CSRF header name (default: X-CSRF-TOKEN)
#   CSRF_TOKEN      Token value for POST/CSRF protected endpoints
#   CURL_OPTS       Extra options appended to curl commands

set -euo pipefail

BASE_URL=${BASE_URL:-"https://staff.vapeshed.co.nz"}
AUTH_COOKIE=${AUTH_COOKIE:-""}
CSRF_HEADER=${CSRF_HEADER:-"X-CSRF-TOKEN"}
CSRF_TOKEN=${CSRF_TOKEN:-""}
# Default curl options with strict timeouts
CURL_OPTS=${CURL_OPTS:-"--silent --show-error --fail --location --connect-timeout 5 --max-time 15"}

function info() {
    printf '\033[0;34m[INFO]\033[0m %s\n' "$1"
}

function ok() {
    printf '\033[0;32m[OK]\033[0m %s\n' "$1"
}

function warn() {
    printf '\033[1;33m[WARN]\033[0m %s\n' "$1"
}

function probe() {
    local description=$1
    local path=$2
    local expect=$3
    local method=${4:-GET}

    local url="${BASE_URL%/}/${path#?/}"
    local status

    info "${description}: ${url}"

    if [[ "${method}" == "HEAD" ]]; then
        status=$(curl ${CURL_OPTS} --head --write-out '%{http_code}' --output /dev/null \
            --cookie "${AUTH_COOKIE}" "${url}")
    else
        status=$(curl ${CURL_OPTS} --request "${method}" --write-out '%{http_code}' --output /dev/null \
            --cookie "${AUTH_COOKIE}" \
            --header "${CSRF_HEADER}: ${CSRF_TOKEN}" \
            "${url}")
    fi

    if [[ "${status}" == "${expect}" ]]; then
        ok "${description} responded with ${status}"
    else
        warn "${description} returned ${status} (expected ${expect})"
        return 1
    fi
}

# Probe a JSON POST endpoint, checking HTTP status only
function probe_post_json() {
    local description=$1
    local path=$2
    local expect=$3
    local json_payload=$4

    local url="${BASE_URL%/}/${path#?/}"
    info "${description}: ${url}"
    status=$(curl ${CURL_OPTS} --request POST --write-out '%{http_code}' --output /dev/null \
        --cookie "${AUTH_COOKIE}" \
        --header 'Content-Type: application/json' \
        --data "${json_payload}" \
        "${url}")

    if [[ "${status}" == "${expect}" ]]; then
        ok "${description} responded with ${status}"
    else
        warn "${description} returned ${status} (expected ${expect})"
        return 1
    fi
}

main() {
    # Admin UI APIs (live verified)
    probe "Admin-UI version info" "/modules/admin-ui/api/version-api.php?action=info" "200"
    probe "Admin-UI changelog" "/modules/admin-ui/api/version-api.php?action=changelog" "200"
    probe "Admin-UI features" "/modules/admin-ui/api/version-api.php?action=features" "200"
    probe "Admin-UI system status" "/modules/admin-ui/api/version-api.php?action=system_status" "200"
    probe "AI-config list" "/modules/admin-ui/api/ai-config-api.php?action=list" "200"
    probe "AI-config config" "/modules/admin-ui/api/ai-config-api.php?action=config" "200"

    # Consignments: log-interaction (POST JSON)
    probe_post_json "Consignments log-interaction (events array)" \
        "/modules/consignments/api/purchase-orders/log-interaction.php" "200" \
        '{"events":[{"type":"modal_opened","page":"receive","po_id":123}]}'

    probe_post_json "Consignments log-interaction (single event)" \
        "/modules/consignments/api/purchase-orders/log-interaction.php" "200" \
        '{"type":"modal_opened","page":"receive","po_id":456}'

    # Optional (commented: not present on live)
    # probe "Health ping (unauthenticated)" "/admin/health/ping" "200"
    # probe "Router default endpoint" "?endpoint=admin/health/ping" "200"
}

main "$@"
