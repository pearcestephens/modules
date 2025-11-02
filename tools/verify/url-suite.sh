#!/usr/bin/env bash
# File: tools/verify/url-suite.sh
# Purpose: Run the Phase 1 URL verification suite against a CIS environment.
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
CURL_OPTS=${CURL_OPTS:-"--silent --show-error --fail --location"}

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

main() {
    probe "Health ping (unauthenticated)" "/admin/health/ping" "200"
    probe "phpinfo blocked without auth" "/admin/health/phpinfo" "403"

    if [[ -n "${AUTH_COOKIE}" ]]; then
        probe "phpinfo authorised" "/admin/health/phpinfo" "200"
        probe "Log tail with auth" "/admin/logs/apache-error-tail?lines=5" "200"
    else
        warn "Skipping authenticated probes; AUTH_COOKIE not provided"
    fi

    probe "Router default endpoint" "?endpoint=admin/health/ping" "200"
    probe "SSE traffic feed handshake" "/admin/traffic/live" "200"
}

main "$@"
