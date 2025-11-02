# URL Verification Suite

This directory contains automation helpers for the Section 11/12 routing contract.

## url-suite.sh

Runs curl-based assertions against the administrative endpoints. Set environment
variables before execution to target non-production hosts or to include session
cookies/CSRF tokens.

```bash
BASE_URL="https://staff.vapeshed.co.nz" \
AUTH_COOKIE="cissession=YOUR_TOKEN" \
CSRF_TOKEN="example-token" \
./tools/verify/url-suite.sh
```

Exit code is non-zero when any probe returns an unexpected status code.
