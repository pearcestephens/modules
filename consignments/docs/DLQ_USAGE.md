# Dead Letter Queue (DLQ) Usage Guide

## Purpose

The `consignments_dlq` table captures failed API requests for manual review and replay. When an endpoint encounters an unrecoverable error, it writes the request details to the DLQ instead of silently failing.

## Database Schema

```sql
CREATE TABLE IF NOT EXISTS consignments_dlq (
  id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
  request_id VARCHAR(64) NOT NULL,
  endpoint VARCHAR(255) NOT NULL,
  payload_json TEXT,
  error_code VARCHAR(50),
  error_message TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_endpoint (endpoint),
  INDEX idx_error_code (error_code),
  INDEX idx_created_at (created_at)
);
```

## Replay CLI Tool

### Basic Usage

```bash
# View all DLQ entries
php bin/consignments-replay.php

# Filter by endpoint
php bin/consignments-replay.php --endpoint=/api/consignments/receive

# Filter by error code
php bin/consignments-replay.php --code=429

# Combine filters
php bin/consignments-replay.php --endpoint=/api/consignments/send --code=500
```

### Output Format

```
=== DLQ Entries ===
ID: 123
Request ID: abc123def456
Endpoint: /api/consignments/receive
Error Code: 429
Error: Rate limit exceeded
Created: 2025-11-02 14:30:00
Payload: {"consignment_id":456,"items":[...]}
---
```

### Important Notes

- **Dry-run only**: This tool displays DLQ entries for manual review
- **No network calls**: Does not automatically retry failed requests
- **Manual intervention**: Review errors and decide retry strategy
- **Safe operation**: Read-only, no data modifications

## Integration Example

```php
try {
    // Normal endpoint logic
    $result = processConsignment($data);
    sendJsonResponse(['success' => true, 'data' => $result]);
} catch (\Exception $e) {
    // Write to DLQ on failure
    $stmt = $pdo->prepare("
        INSERT INTO consignments_dlq 
        (request_id, endpoint, payload_json, error_code, error_message)
        VALUES (?, ?, ?, ?, ?)
    ");
    $stmt->execute([
        $_SERVER['HTTP_X_REQUEST_ID'] ?? uniqid(),
        $_SERVER['REQUEST_URI'],
        json_encode($_POST),
        $e->getCode(),
        $e->getMessage()
    ]);
    
    sendJsonResponse(['success' => false, 'error' => 'Request queued for retry'], 503);
}
```

## Monitoring

Query DLQ entries by time period:

```sql
-- Today's failures
SELECT endpoint, error_code, COUNT(*) as count
FROM consignments_dlq
WHERE created_at >= CURDATE()
GROUP BY endpoint, error_code
ORDER BY count DESC;

-- Recent high-priority failures
SELECT * FROM consignments_dlq
WHERE error_code IN ('500', '503')
AND created_at >= NOW() - INTERVAL 1 HOUR
ORDER BY created_at DESC;
```

## Cleanup

Remove successfully retried or obsolete entries:

```sql
-- Delete entries older than 30 days
DELETE FROM consignments_dlq
WHERE created_at < NOW() - INTERVAL 30 DAY;

-- Archive before deletion (recommended)
CREATE TABLE consignments_dlq_archive LIKE consignments_dlq;
INSERT INTO consignments_dlq_archive
SELECT * FROM consignments_dlq
WHERE created_at < NOW() - INTERVAL 30 DAY;
```
