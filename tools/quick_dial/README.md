# Quick Dial Utilities

Helper scripts that power the administrative log inspection endpoints.

## apache_tail.sh

Tails the configured Apache/PHP-FPM error log, writes a gzip snapshot to the
snapshot directory, and echoes the requested lines to STDOUT. Designed for use
by the `/admin/logs/apache-error-tail` endpoint.

Example:

```bash
./tools/quick_dial/apache_tail.sh \
    --log-file "/home/master/applications/jcepnzzkmj/public_html/logs/apache_phpstack-129337-518184.cloudwaysapps.com.error.log" \
    --lines 300 \
    --output-dir "/var/log/cis/snapshots"
```
