#!/usr/bin/env bash
# File: tools/quick_dial/apache_tail.sh
# Purpose: Safe Apache/PHP-FPM log tail helper with gzip snapshot capability.
# Author: GitHub Copilot
# Last Modified: 2025-11-02
#
# Usage:
#   ./tools/quick_dial/apache_tail.sh --log-file "$LOG_PATH/apache.error.log" --lines 200
#
# Flags:
#   --log-file   Absolute path to the log file to tail
#   --lines      Number of lines to capture (default: 200)
#   --output-dir Directory for compressed snapshots (default: /var/log/cis/snapshots)
#
# Output:
#   Prints the requested tail to STDOUT and writes a timestamped gzip snapshot
#   to the output directory.

set -euo pipefail

LOG_FILE=""
LINES=200
OUTPUT_DIR="/var/log/cis/snapshots"

while [[ $# -gt 0 ]]; do
    case "$1" in
        --log-file)
            LOG_FILE="$2"
            shift 2
            ;;
        --lines)
            LINES="$2"
            shift 2
            ;;
        --output-dir)
            OUTPUT_DIR="$2"
            shift 2
            ;;
        *)
            printf 'Unknown argument: %s\n' "$1" >&2
            exit 64
            ;;
    esac
done

if [[ -z "${LOG_FILE}" ]]; then
    printf 'Missing required --log-file parameter.\n' >&2
    exit 64
fi

if [[ ! -r "${LOG_FILE}" ]]; then
    printf 'Log file not readable: %s\n' "${LOG_FILE}" >&2
    exit 74
fi

if ! [[ "${LINES}" =~ ^[0-9]+$ ]]; then
    printf 'Lines parameter must be numeric.\n' >&2
    exit 65
fi

mkdir -p "${OUTPUT_DIR}"

snapshot_name="$(date '+%Y%m%d%H%M%S')-$(basename "${LOG_FILE}").log.gz"
snapshot_path="${OUTPUT_DIR%/}/${snapshot_name}"

tail_output=$(tail -n "${LINES}" "${LOG_FILE}")

printf '%s\n' "${tail_output}" | gzip -c > "${snapshot_path}"
chmod 640 "${snapshot_path}"

printf '%s\n' "${tail_output}"
