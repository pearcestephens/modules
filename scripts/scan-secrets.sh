#!/usr/bin/env bash
set -euo pipefail

# Simple pattern-based secret scan for CI/local use.
PATTERNS=(
  'lsxs_pt_'              # Vend access token prefix
  'lsxs_rt_'              # Vend refresh token prefix
  'SG\.[A-Za-z0-9_-]{40,}'  # SendGrid key pattern (grep -E quantifier)
  'sk-proj-[0-9]{2}-[A-Za-z0-9_-]{10,}'   # OpenAI project key prefix (extended)
  'AIza[0-9A-Za-z_-]{30,}'  # Google API key prefix
)

FOUND=0
for p in "${PATTERNS[@]}"; do
  if grep -R -E "$p" -n --exclude-dir=vendor --exclude-dir=.git .; then
    echo "[!] Potential secret match for pattern: $p"
    FOUND=1
  fi
done

if [ $FOUND -eq 1 ]; then
  echo "Secret scan failed. Review matches above." >&2
  exit 2
else
  echo "Secret scan passed."
fi
