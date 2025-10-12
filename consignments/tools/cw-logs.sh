#!/usr/bin/env bash
set -euo pipefail

# -------------------------
# Cloudways Logs Helper
# -------------------------
# Usage examples:
#   cw-logs.sh -S apache-error -n 100
#   cw-logs.sh -S apache-access -p "/modules/consignments/transfers/pack" -n 200
#   cw-logs.sh -S nginx-access --status 500 --since 2h
#   ROOT="/home/master/applications/jcepnzzkmj/logs" cw-logs.sh -S php-slow --all
#
# Notes:
# - Auto-detects log root. You can override with env ROOT=/abs/path
# - Sources: apache-error|apache-access|nginx-error|nginx-access|php-access|php-slow
# - Filters: --path, --status, --method, --ip, --grep, --since (e.g. 90m, 2h, 1d, or RFC3339)
# - Use -n to limit results, or --all to show all matching lines (careful!)
#
# Exit codes: 0 ok, 2 no files found, 3 no matches

# ---------- config & params ----------
SOURCE=""
PATH_FILTER=""
STATUS_FILTER=""
METHOD_FILTER=""
IP_FILTER=""
GREP_FILTER=""
SINCE_RAW=""
LIMIT="200"
SHOW_ALL="0"

usage() {
  cat <<EOF
Usage: $0 -S <source> [options]

Sources:
  apache-error   Apache error logs
  apache-access  Apache access logs
  nginx-error    Nginx error logs
  nginx-access   Nginx access logs
  php-access     PHP-FPM access logs (if enabled)
  php-slow       PHP-FPM slow logs (if enabled)

Options:
  -S, --source <src>     One of the sources above
  -p, --path   <substr>  Filter request URI containing substring
      --status <code>    Filter HTTP status (e.g. 404 or 5xx via --grep)
      --method <verb>    Filter HTTP method (GET|POST|...)
      --ip     <addr>    Filter remote IP
      --grep   <text>    Additional grep on raw lines
      --since  <win>     Time window (e.g. 90m, 2h, 1d) or RFC3339 timestamp
  -n, --lines  <N>       Limit output lines (default 200)
      --all              Show all matches (ignore -n)
  -h, --help             This help
EOF
}

# Parse args
while [[ $# -gt 0 ]]; do
  case "$1" in
    -S|--source) SOURCE="${2:-}"; shift 2 ;;
    -p|--path) PATH_FILTER="${2:-}"; shift 2 ;;
    --status) STATUS_FILTER="${2:-}"; shift 2 ;;
    --method) METHOD_FILTER="${2:-}"; shift 2 ;;
    --ip) IP_FILTER="${2:-}"; shift 2 ;;
    --grep) GREP_FILTER="${2:-}"; shift 2 ;;
    --since) SINCE_RAW="${2:-}"; shift 2 ;;
    -n|--lines) LIMIT="${2:-}"; shift 2 ;;
    --all) SHOW_ALL="1"; shift ;;
    -h|--help) usage; exit 0 ;;
    *) echo "Unknown option: $1" >&2; usage; exit 1 ;;
  esac
done

if [[ -z "$SOURCE" ]]; then
  echo "Error: --source is required" >&2
  usage; exit 1
fi

# ---------- resolve ROOT safely ----------
if [[ -n "${ROOT:-}" ]]; then
  ROOT="$(readlink -f "$ROOT" || true)"
else
  # Try Cloudways real paths first; never follow /applications symlink
  CANDS=(
    "/home/master/applications/jcepnzzkmj/logs"
    "/home/master/applications/*/logs"
    "/applications/jcepnzzkmj/logs"  # fallback
    "/applications/*/logs"           # fallback
  )
  for c in "${CANDS[@]}"; do
    # expand wildcards carefully
    for d in $c; do
      if [[ -d "$d" ]]; then ROOT="$(readlink -f "$d")"; break 2; fi
    done
  done
fi

if [[ -z "${ROOT:-}" || ! -d "$ROOT" ]]; then
  echo "No log root found. Set ROOT env or adjust script (looked under Cloudways paths)." >&2
  exit 2
fi

# ---------- fileset per source ----------
# Map of filename globs for each source
case "$SOURCE" in
  apache-error)  GLOBS=( "$ROOT/apache_phpstack-*.cloudwaysapps.com.error.log" "$ROOT/apache_phpstack-*.cloudwaysapps.com.error.log."*.gz );;
  apache-access) GLOBS=( "$ROOT/apache_phpstack-*.cloudwaysapps.com.access.log" "$ROOT/apache_phpstack-*.cloudwaysapps.com.access.log."*.gz );;
  nginx-error)   GLOBS=( "$ROOT/nginx_phpstack-*.cloudwaysapps.com.error.log" "$ROOT/nginx_phpstack-*.cloudwaysapps.com.error.log."*.gz );;
  nginx-access)  GLOBS=( "$ROOT/nginx_phpstack-*.cloudwaysapps.com.access.log" "$ROOT/nginx_phpstack-*.cloudwaysapps.com.access.log."*.gz );;
  php-access)    GLOBS=( "$ROOT/php-app.access.log" "$ROOT/php-app.access.log."*.gz );;
  php-slow)      GLOBS=( "$ROOT/php-app.slow.log" "$ROOT/php-app.slow.log."*.gz );;
  *) echo "Unknown source: $SOURCE" >&2; exit 1 ;;
esac

# Build concrete file list (existing only)
FILES=()
for g in "${GLOBS[@]}"; do
  for f in $g; do
    [[ -f "$f" ]] && FILES+=( "$f" )
  done
done

if [[ ${#FILES[@]} -eq 0 ]]; then
  echo "No log files found for source '$SOURCE' under $ROOT" >&2
  exit 2
fi

# Sort files newest->oldest (access/error log formats rotate numerically)
IFS=$'\n' FILES=($(ls -1t "${FILES[@]}" 2>/dev/null || true)); unset IFS

# ---------- time filtering ----------
SINCE_EPOCH=""
now_epoch="$(date +%s)"

parse_since() {
  local s="$1"
  # relative: Nd / Nh / Nm
  if [[ "$s" =~ ^([0-9]+)([mhd])$ ]]; then
    local n="${BASH_REMATCH[1]}"
    local u="${BASH_REMATCH[2]}"
    case "$u" in
      m) echo $(( now_epoch - n*60 ));;
      h) echo $(( now_epoch - n*3600 ));;
      d) echo $(( now_epoch - n*86400 ));;
    esac
    return 0
  fi
  # absolute RFC3339-ish
  if date -d "$s" +%s >/dev/null 2>&1; then
    date -d "$s" +%s
    return 0
  fi
  echo ""
  return 1
}

if [[ -n "$SINCE_RAW" ]]; then
  SINCE_EPOCH="$(parse_since "$SINCE_RAW" || true)"
  if [[ -z "$SINCE_EPOCH" ]]; then
    echo "Warning: could not parse --since '$SINCE_RAW' (use 90m, 2h, 1d, or RFC3339). Ignoring." >&2
  fi
fi

# ---------- helpers ----------
is_gz() { [[ "$1" == *.gz ]]; }
cat_any() { is_gz "$1" && zcat -f -- "$1" || cat -- "$1"; }

# For access logs, try to prettify common formats (very conservative)
pretty_access() {
  awk '
    function color(c,s){ return s }  # color disabled for portability
    {
      print $0
    }
  '
}

# Build grep pipeline dynamically
build_pipeline() {
  local src="$1"
  local cmd="cat_any \"__FILE__\""

  # time filter: we do it lightweight by grepping timestamps if possible,
  # otherwise we post-filter with awk compare (simple, not 100% format aware)
  if [[ -n "$SINCE_EPOCH" ]]; then
    # Post filter with awk that tries to parse date fields for nginx/apache
    # This is heuristic; if it fails, the line still passes.
    cmd+=" | awk -v SINCE=$SINCE_EPOCH '
      function to_epoch_apache(ts,   d,m,y,hh,mm,ss,mon) {
        # format: [10/Oct/2000:13:55:36 +0000]
        gsub(/^\[/,\"\",ts); gsub(/\]$/,\"\",ts);
        split(ts, a, /[\\/ :+]/);
        d=a[1]; mon=a[2]; y=a[3]; hh=a[4]; mm=a[5]; ss=a[6];
        month[\"Jan\"]=1;month[\"Feb\"]=2;month[\"Mar\"]=3;month[\"Apr\"]=4;month[\"May\"]=5;month[\"Jun\"]=6;
        month[\"Jul\"]=7;month[\"Aug\"]=8;month[\"Sep\"]=9;month[\"Oct\"]=10;month[\"Nov\"]=11;month[\"Dec\"]=12;
        m=month[mon]; if(!m) return 0;
        cmd=sprintf(\"date -d \\\"%04d-%02d-%02d %02d:%02d:%02d\\\" +%%s\", y,m,d,hh,mm,ss);
        cmd | getline epoch; close(cmd);
        return epoch?epoch:0;
      }
      function pass_time(line) {
        # try nginx first: first [..] may be at end; try to find [..]
        start=index(line,\"[\"); end=index(line,\"]\");
        if(start && end && end>start) {
          ts=substr(line,start,end-start+1);
          ep=to_epoch_apache(ts);
          if(ep>0) return ep>=SINCE;
        }
        return 1; # if unknown format, keep
      }
      { if(pass_time(\$0)) print \$0 }
    '"
  fi

  # common filters
  if [[ -n "$IP_FILTER" ]]; then
    cmd+=" | grep -F -- \"$IP_FILTER\""
  fi
  if [[ -n "$METHOD_FILTER" ]]; then
    cmd+=" | grep -E -- \"\\b$METHOD_FILTER\\b\""
  fi
  if [[ -n "$STATUS_FILTER" ]]; then
    cmd+=" | grep -E -- \"[[:space:]]$STATUS_FILTER[[:space:]]\""
  fi
  if [[ -n "$PATH_FILTER" ]]; then
    cmd+=" | grep -F -- \"$PATH_FILTER\""
  fi
  if [[ -n "$GREP_FILTER" ]]; then
    cmd+=" | grep -i -- \"$GREP_FILTER\""
  fi

  # access pretty (disabled coloring; still could hook here)
  if [[ "$src" == "apache-access" || "$src" == "nginx-access" || "$src" == "php-access" ]]; then
    cmd+=" | pretty_access"
  fi

  # Limit
  if [[ "$SHOW_ALL" == "0" ]]; then
    cmd+=" | tail -n $LIMIT"
  fi

  echo "$cmd"
}

PIPE="$(build_pipeline "$SOURCE")"

# ---------- run ----------
MATCHES=0
for f in "${FILES[@]}"; do
  # substitute file in pipeline
  RUN="${PIPE/__FILE__/$(printf %q "$f")}"
  # shellcheck disable=SC2086
  eval $RUN || true
  MATCHES=1
done

if [[ $MATCHES -eq 0 ]]; then
  echo "No matching lines." >&2
  exit 3
fi
