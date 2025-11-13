# Behavior Monitoring & Performance Telemetry

## Overview
The CIS behavior monitoring pipeline captures client-side interaction, performance, and security-relevant signals and stores them in normalized tables for analysis and anomaly detection.

## Components
1. Client Script (`behavior.js`)
   - Buffers events (click, scroll, visibility, perf_timing, suspicious signals).
   - Sends batched JSON via `sendBeacon`/`fetch` to `behavior.php` endpoint.
2. Ingestion Endpoint (`/modules/base/public/behavior.php`)
   - Validates payload size & structure.
   - Persists rows into `cis_user_events` using `CISLogger::behavior()`.
   - Enriches `perf_timing` events by emitting `CISLogger::performance` metrics (LCP, CLS, INP, DCL).
3. Inspection Endpoint (`/modules/base/public/behavior_inspect.php`)
   - Feature-flag gated (`behavior_debug`).
   - Returns last 200 events for current session (JSON or HTML).
4. Aggregation Script (`scripts/aggregate_page_fingerprints.php`)
   - Cron-compatible CLI script.
   - Aggregates last 24h `perf_timing` events into `cis_page_fingerprints` (avg & p95 for LCP/CLS/INP).
5. Logger Enhancements (`CISLogger::behavior`, `CISLogger::performance`)
   - Unified trace/session IDs.
   - Redaction of secrets via `SecretRedactor`.

## Database Tables
### `cis_user_events`
| Column | Description |
|--------|-------------|
| id | PK |
| user_id | Nullable user reference |
| session_id | Browser session identifier |
| event_type | e.g. click, scroll, perf_timing, suspicious_devtools |
| event_data | JSON payload (limited, no PII) |
| page_url | Source page |
| occurred_at_ms | Client timestamp (ms) |
| ip_address | Request IP |
| user_agent | Browser UA |
| trace_id | Correlation trace |
| created_at | Insert time |

### `cis_performance_metrics`
Emitted via `CISLogger::performance` for page metrics generated in `behavior.php`.

### `cis_page_fingerprints`
Aggregated metrics (sample_count, lcp_avg, lcp_p95, cls_avg, cls_p95, inp_avg, inp_p95, last_aggregated_at).

## Event Types Taxonomy (Current)
| Type | Purpose |
|------|---------|
| click | User clicked actionable element |
| scroll | Scroll position sample (throttled) |
| visibility | Tab/window visibility changes |
| perf_timing | Performance metrics (LCP, CLS, INP, DCL) |
| request_fail | Client-side fetch failure |
| network_error | Network unrecoverable error |
| suspicious_devtools | DevTools/open signals |
| suspicious_rapid_click | High-frequency clicking |
| suspicious_scroll_flood | Excessive scroll events |

## Cron Setup
Run every 5 minutes to keep fingerprints fresh:
```
*/5 * * * * /usr/bin/php /path/to/modules/scripts/aggregate_page_fingerprints.php >> /var/log/cis/cron_fingerprints.log 2>&1
```

## Feature Flags
`behavior_debug` (in `config/feature-flags.php`) must be `true` to enable inspection endpoint. Keep `false` in production unless investigating.

## Security & Privacy
1. No raw keystrokes recorded; only metadata and performance metrics.
2. IP & UA stored for anomaly correlation.
3. Secrets redacted in logs (Logger integrates `SecretRedactor`).
4. Size guard prevents oversized payload ingestion (200KB limit).

## Extending Metrics
Add new perf fields client-side (e.g., `ttfb`, `fcp`). In `behavior.php` extend the `$metrics` mapping to emit performance entries.

## Debug Workflow
1. Temporarily enable `behavior_debug`.
2. Visit target pages, perform actions.
3. Open `/modules/base/public/behavior_inspect.php` to verify ingestion.
4. Re-disable flag when complete.

## Auth Simulation (Staff Environment)
Domain: `staff.vapeshed.co.nz`

To run authenticated endpoint tests without real credentials, enable one of:
1. Set `auth_debug` => true in `config/feature-flags.php` (temporary).
2. Export environment variable `FORCE_AUTH_DEBUG=1` for CI/automation (still requires access to server environment).

Then simulate a session:
```
curl -c cookies.txt -s "https://staff.vapeshed.co.nz/?endpoint=login_simulate&user_id=1&token=DEV123"
curl -b cookies.txt -s "https://staff.vapeshed.co.nz/?endpoint=behavior_stats" | jq
```

Flags & security:
- `auth_debug` is false by default (keep that in production).
- Optional token: set `auth_debug_token` in feature flags or `DEV_AUTH_SIM_TOKEN` env; required if non-empty.
- `FORCE_AUTH_DEBUG=1` bypasses flag (use only in controlled CI contexts).

Disable when finished to avoid unintended session creation.

## Future Enhancements
- Anomaly labeling (auto `is_anomaly` in performance table).
- Retention/archival job (monthly partition rollover).
- SSE/WebSocket live feed for admin dashboards.
- Threshold-based alerting (slow LCP or spike in CLS).

## New REST Endpoints
### /modules/base/public/behavior_stats.php
Returns summary statistics for last 5 minutes.
Schema:
```
{
   window_start: ISO8601,
   window_seconds: 300,
   event_counts: { click: 123, perf_timing: 45, ... },
   suspicious_total: 7,
   top_pages: [ { page_url: "/dashboard", cnt: 40 }, ... ],
   restricted: false
}
```

### /modules/base/public/performance_fingerprints.php
Raw aggregated fingerprints ordered by sample count.
```
{
   count: N,
   fingerprints: [ { page_url, sample_count, lcp_avg, lcp_p95, cls_avg, cls_p95, inp_avg, inp_p95, last_aggregated_at }, ... ]
}
```

### /modules/base/public/performance_summary.php
Performance overview + slow pages list.
```
{
   count: N,
   fingerprints: [...],
   slow_pages: [...],
   thresholds: { lcp_p95: 2500, cls_p95: 0.1 }
}
```

## Troubleshooting
| Symptom | Action |
|---------|--------|
| Empty inspect output | Confirm client script loads & beacon fires; check network tab for POST to `behavior.php`. |
| Aggregator zero pages | Ensure `perf_timing` events exist; verify cron window (24h) and event types. |
| DB config error in CLI | Confirm environment variables exported or add `config/database.php`. |
| CLS/LCP values null | Validate client metrics collection code; ensure fields included in event JSON. |

---
Document updated: <?= date('Y-m-d') ?>
