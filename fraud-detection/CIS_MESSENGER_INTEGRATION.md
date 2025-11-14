# CIS Internal Messenger - Fraud Detection Integration Guide

## Overview

This webhook receiver monitors CIS Internal Messenger for suspicious communication patterns that may indicate fraud planning or coordination.

**Status**: Ready for integration (waiting for CIS Messenger to be built)

---

## What It Monitors

### 🚨 Suspicious Keywords
- password, delete, cover up, hide, secret
- "don't tell", "between us", "off the record"
- cash only, no receipt, void it
- discount, refund, inventory, stock, backdoor

### 🔍 Behavioral Patterns
1. **Message Deletion** - Staff deleting messages (potential evidence removal)
2. **After-Hours Communication** - Messages sent before 6 AM or after 10 PM
3. **Suspicious Direct Messages** - Private chats containing sensitive keywords
4. **File Sharing** - Documents/images being shared
5. **High Message Frequency** - More than 50 messages in 10 minutes
6. **Non-Manager Private Chats** - Direct messages between non-managers with sensitive content

---

## Integration Steps (When CIS Messenger is Ready)

### Step 1: Set Webhook Secret

Add to `.env`:
```env
CIS_MESSENGER_WEBHOOK_SECRET=your_random_secret_key_here
```

Generate secret:
```bash
php -r "echo bin2hex(random_bytes(32));"
```

### Step 2: Configure Webhook URL in CIS Messenger

Point your messenger system to send webhooks to:
```
POST https://staff.vapeshed.co.nz/modules/fraud-detection/api/webhooks/cis-messenger.php
```

### Step 3: Webhook Payload Format

Your CIS Messenger should send this JSON structure:

```json
{
  "event_type": "message.created",
  "message_id": "msg_12345",
  "sender_staff_id": 123,
  "recipient_staff_id": 456,
  "channel_id": "general",
  "message_text": "Hello, how are you?",
  "is_direct_message": false,
  "timestamp": "2025-11-14 10:30:00",
  "metadata": {
    "ip_address": "192.168.1.1",
    "user_agent": "Mozilla/5.0..."
  }
}
```

**Event Types**:
- `message.created` - New message sent
- `message.deleted` - Message deleted by user
- `file.shared` - File/image shared

### Step 4: Sign Webhooks (HMAC SHA-256)

Include signature in HTTP header:
```php
$payload = json_encode($webhookData);
$signature = hash_hmac('sha256', $payload, $webhookSecret);

// Add to request headers
$headers = [
    'X-CIS-Signature: ' . $signature,
    'Content-Type: application/json'
];
```

### Step 5: Test the Integration

```bash
# Test webhook endpoint
curl -X POST https://staff.vapeshed.co.nz/modules/fraud-detection/api/webhooks/cis-messenger.php \
  -H "Content-Type: application/json" \
  -H "X-CIS-Signature: YOUR_SIGNATURE" \
  -d '{
    "event_type": "message.created",
    "message_id": "test_001",
    "sender_staff_id": 1,
    "channel_id": "test",
    "message_text": "Test message",
    "is_direct_message": false,
    "timestamp": "'$(date +'%Y-%m-%d %H:%M:%S')'"
  }'
```

Expected response:
```json
{
  "success": true,
  "result": {
    "event_type": "message.created",
    "staff_id": 1,
    "flags": [],
    "fraud_analysis_triggered": false
  }
}
```

---

## Database Tables

### `communication_events`
Stores all messenger activity:
```sql
SELECT * FROM communication_events
WHERE staff_id = 123
ORDER BY received_at DESC
LIMIT 100;
```

### `messenger_channels`
Channel metadata:
```sql
SELECT * FROM messenger_channels
WHERE is_active = 1;
```

### `fraud_analysis_queue`
Triggered fraud analyses:
```sql
SELECT * FROM fraud_analysis_queue
WHERE trigger_source = 'cis_messenger_webhook'
AND status = 'pending';
```

---

## Monitoring

### Check Recent Messages
```sql
SELECT
    ce.staff_id,
    s.name,
    ce.event_type,
    ce.message_text,
    ce.is_direct_message,
    ce.received_at
FROM communication_events ce
JOIN staff s ON ce.staff_id = s.id
WHERE ce.platform = 'cis_messenger'
AND ce.received_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
ORDER BY ce.received_at DESC;
```

### Find Suspicious Messages
```sql
SELECT
    ce.staff_id,
    s.name,
    COUNT(*) as flagged_messages,
    GROUP_CONCAT(DISTINCT ce.event_type) as event_types
FROM communication_events ce
JOIN staff s ON ce.staff_id = s.id
JOIN fraud_analysis_queue faq ON faq.staff_id = ce.staff_id
    AND faq.trigger_source = 'cis_messenger_webhook'
WHERE ce.received_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY ce.staff_id
ORDER BY flagged_messages DESC;
```

### After-Hours Communication
```sql
SELECT
    staff_id,
    COUNT(*) as after_hours_messages
FROM communication_events
WHERE platform = 'cis_messenger'
AND (HOUR(received_at) < 6 OR HOUR(received_at) >= 22)
AND received_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY staff_id
HAVING after_hours_messages > 10
ORDER BY after_hours_messages DESC;
```

---

## Customization

### Add Custom Suspicious Keywords

Edit `api/webhooks/cis-messenger.php`:
```php
$receiver = new CISMessengerWebhookReceiver($pdo, [
    'webhook_secret' => getenv('CIS_MESSENGER_WEBHOOK_SECRET'),
    'suspicious_keywords' => [
        'your custom keyword',
        'another keyword',
        // ... more keywords
    ]
]);
```

### Adjust After-Hours Time Range

Current: Before 6 AM or after 10 PM

Edit `isAfterHours()` method:
```php
private function isAfterHours(): bool
{
    $hour = (int)date('G');
    // Change these values:
    return $hour < 7 || $hour >= 21; // 7 AM to 9 PM
}
```

### Change High Frequency Threshold

Current: >50 messages in 10 minutes

Edit `hasHighMessageFrequency()` method:
```php
AND received_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE) // Change interval
...
return $stmt->fetchColumn() > 30; // Change threshold
```

---

## API Methods

### Get Staff Message Statistics
```php
$receiver = new CISMessengerWebhookReceiver($pdo);
$stats = $receiver->getStaffMessageStats($staffId, $days = 30);

// Returns:
// [
//   'total_messages' => 1250,
//   'direct_messages' => 85,
//   'unique_recipients' => 12,
//   'channels_used' => 8,
//   'after_hours_messages' => 23
// ]
```

---

## Security Considerations

1. **Always use HTTPS** for webhook endpoint
2. **Verify signature** on every request
3. **Rotate webhook secret** every 90 days
4. **Monitor webhook log** for unusual patterns
5. **Rate limit** the endpoint (max 1000 req/hour per IP)
6. **Store message text encrypted** if containing sensitive data

---

## Troubleshooting

### Webhook Not Received
```bash
# Check webhook log
mysql -e "SELECT * FROM webhook_log WHERE platform = 'cis_messenger' ORDER BY received_at DESC LIMIT 10" cis

# Check PHP error log
tail -f /var/log/php-fpm/error.log
```

### Signature Verification Failed
```bash
# Verify secret is set
php -r "echo getenv('CIS_MESSENGER_WEBHOOK_SECRET');"

# Test signature generation
php -r "
\$payload = '{\"test\":\"data\"}';
\$secret = 'your_secret';
echo hash_hmac('sha256', \$payload, \$secret);
"
```

### Messages Not Triggering Fraud Analysis
```sql
-- Check if keywords match
SELECT * FROM communication_events
WHERE message_text LIKE '%password%'
OR message_text LIKE '%delete%'
ORDER BY received_at DESC;

-- Check fraud queue
SELECT * FROM fraud_analysis_queue
WHERE trigger_source = 'cis_messenger_webhook';
```

---

## Performance

- **Expected Load**: ~1000 messages/day across all staff
- **Response Time**: <50ms per webhook
- **Storage**: ~1MB per 1000 messages
- **Retention**: Keep communication_events for 90 days, archive older

---

## Next Steps

1. ✅ Build CIS Internal Messenger system
2. ✅ Implement webhook sending in Messenger
3. ✅ Generate and set webhook secret
4. ✅ Test with sample messages
5. ✅ Monitor fraud_analysis_queue for triggers
6. ✅ Adjust keywords/thresholds based on real data

---

**Ready to integrate when you launch CIS Messenger!** 🚀
