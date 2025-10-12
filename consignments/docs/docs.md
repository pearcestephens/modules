csrf: <token>
nonce: <random-hex>
transfer_id: <int>

# Lines (one per transfer_items.id):
lines[<item_id>][product_id]: <vend product uuid>
lines[<item_id>][qty_planned]: <int>
lines[<item_id>][qty_packed]: <int>

# Shipping
delivery_mode: manual_courier | pickup | dropoff | internal_drive
box_count: <int>=N (>=1)
tracking[0..N-1]: <string>          # REQUIRED PER BOX
weight_grams[0..N-1]: <int>         # optional
dims[i][l|w|h]: <int>               # optional
nicotine_in_shipment: 0|1

# Optional parcel allocations:
parcel_allocations[i][<item_id>] = <qty>

Writes (typical successful pack):

transfer_items.qty_sent_total (bounded by qty_requested)

transfer_shipments (1 row, status='packed', delivery_mode = manual/pickup/dropoff/internal_drive)

transfer_shipment_items (per line)

transfer_parcels (one per box; status='labelled')

transfer_parcel_items (if posted)

transfer_labels (manual tracking snapshot)

transfers.{total_boxes,total_weight_g,state='PACKAGED',status} (move into OPEN/ PACKAGED)

transfer_metrics (aggregation)

transfer_audit_log + transfer_unified_log

transfer_queue_log enqueue (queue_name='vend_consignment_sync', operation='packaged')



csrf: <token>
nonce: <random-hex>
transfer_id: <int>

# Lines (one per transfer_items.id):
lines[<item_id>][qty_received]: <int>
lines[<item_id>][condition]: ok|damaged|other  (optional)
lines[<item_id>][notes]: <string>             (optional)
receive_notes: <string>                       (optional)

Writes (typical receipt):

transfer_receipts (header row)

transfer_receipt_items (per line received)

transfer_items.qty_received_total (bounded by qty_sent_total)

transfer_parcels.status='received', received_at=NOW() (all parcels in shipment)

transfer_shipments.status='received', received_at=NOW(), received_by=current user

transfers.status/state to received/RECEIVED or partial/RECEIVING

transfer_discrepancies (auto “missing” for shortages)

transfer_metrics

transfer_audit_log + transfer_unified_log

transfer_queue_log enqueue (operation='received')

transfer_idempotency


10) LOCKING, AUTOSAVE & QUEUE CRON

Pack lock (pack_lock.php): acquire on page load, heartbeat every minute, release on exit (optional).

Autosave (autosave.php): saves UI snapshot in transfer_ui_sessions (restore endpoint can be added).

Queue Integration: APIs enqueue into transfer_queue_log.
Run your Queue V2 cron using your CRON INSTALLATION GUIDE so the worker picks up pending jobs and performs the downstream sync pipeline.

11) HARDENING & NOTES

Transactions everywhere and idempotency on submit.

Bounds checks against transfer_items constraints.

Manual shipping only (as required) with per‑parcel tracking required.

Comprehensive logging (audit + unified + metrics).

Extend easily to push AI insights, labels, or external carrier orders (e.g., write to transfer_carrier_orders with carrier='MANUAL' and snapshot payload if desired).