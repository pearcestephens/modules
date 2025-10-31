#!/bin/bash
# Extract payment data from webhooks_log

echo "Querying webhooks_log for payment data structure..."
echo ""

mysql -t -e "
SELECT 
    JSON_EXTRACT(payload, '$.payload.payments') as payments_json
FROM webhooks_log 
WHERE event_type = 'sale.update' 
AND payload LIKE '%payments%'
AND JSON_EXTRACT(payload, '$.payload.payments') IS NOT NULL
ORDER BY created_at DESC 
LIMIT 1
" | head -50
