# Consignment System - Database Tables Reference

**DO NOT FORGET THIS FILE!**

This document contains ALL database tables used by the consignment/transfer system.

## Primary Tables

### `vend_consignments`
Main consignment/transfer table. Links outlets, tracks state, sync status.

**Key Columns:**
- `id` - CIS internal ID (int)
- `vend_transfer_id` - Vend UUID (char 36)
- `outlet_from`, `outlet_to` - Source/destination outlets
- `state` - ENUM: DRAFT, OPEN, PACKING, SENT, RECEIVING, RECEIVED, etc.
- `status` - Legacy status field
- `transfer_category` - STOCK, JUICE, RETURN, PURCHASE_ORDER, etc.
- `created_by` - User ID who created
- `total_boxes`, `total_cost`, `total_count` - Aggregates
- `deleted_at` - Soft delete timestamp

### `vend_consignment_line_items`
Products in each consignment/transfer.

**Key Columns:**
- `id` - Line item ID
- `transfer_id` - FK to vend_consignments.id
- `product_id` - Vend product UUID
- `sku`, `name` - Product details
- `quantity`, `quantity_sent`, `quantity_received` - Quantities
- `status` - pending, sent, received, cancelled, damaged
- `unit_cost`, `total_cost` - Pricing

## Supporting Tables

### `consignment_shipments`
Shipment waves for transfers (courier, internal drive, pickup).

### `consignment_parcels`
Individual boxes/parcels within shipments. Tracking numbers.

### `consignment_parcel_items`
Which products are in which parcels.

### `consignment_notes`
Staff notes attached to transfers.

### `consignment_media`
Photos/videos attached to transfers or parcels.

## Audit & Logging Tables

### `consignment_audit_log`
Comprehensive audit trail for all operations.

### `consignment_logs`
Event logs for transfers, shipments, items.

### `consignment_unified_log`
Modern unified logging with AI decisions, tracing.

### `consignment_queue_log`
Queue operations for Vend sync.

## AI & Analytics Tables

### `consignment_ai_audit_log`
AI decision audit trail with confidence scores.

### `consignment_ai_insights`
AI-generated insights for transfers.

### `consignment_performance_metrics`
Aggregated performance metrics for dashboards.

### `consignment_performance_logs`
Detailed performance event logs.

## Alert & Notification Tables

### `consignment_alert_rules`
Alert escalation rules for critical events.

### `consignment_alerts_log`
Triggered alerts log.

### `consignment_notifications`
Failure notifications and resolution tracking.

### `consignment_notification_queue`
Email notification queue for background processing.

## Email System Tables

### `consignment_email_templates`
Email template master configuration.

### `consignment_email_template_config`
Template configuration (global and per-supplier).

### `consignment_email_log`
Complete audit log of all email activity.

## Carrier & Tracking Tables

### `consignment_carrier_orders`
External carrier orders (NZ Post, GSS, etc).

### `consignment_tracking_events`
Courier tracking events history.

### `consignment_labels`
Shipping labels generated.

## System Tables

### `consignment_config`
System configuration key-value store.

### `consignment_configurations`
Allocation method configurations.

### `consignment_idempotency`
Idempotency keys for duplicate prevention.

### `consignment_pack_locks`
Transfer packing locks.

### `consignment_pack_lock_audit`
Packing lock audit trail.

### `consignment_ui_sessions`
UI state persistence and autosave.

### `consignment_transactions`
Transaction tracking for pack/receive operations.

### `consignment_system_health`
System health check results.

## Vend Integration Tables

### `vend_outlets`
Store locations from Vend/Lightspeed.

### `vend_suppliers`
Supplier records from Vend.

### `vend_products`
Product catalog from Vend.

### `vend_inventory`
Inventory levels per outlet per product.

### `vend_customers`
Customer records from Vend.

### `vend_users`
Vend user accounts.

### `vend_sales` & `vend_sales_line_items`
Sales transaction history.

### `vend_categories`
Product categories.

### `vend_product_qty_history`
Historical inventory quantity changes.

## Archive Tables

### `consignment_log_archive`
Archived logs for 7-year compliance retention.

## Queue Tables

### `vend_consignment_queue`
Background job queue for Vend sync.

### `consignment_queue_metrics`
Queue performance and operational metrics.

## Discrepancy Tables

### `consignment_discrepancies`
Receiving discrepancies and issues.

## Receipt Tables

### `consignment_receipts`
Receive session headers.

### `consignment_receipt_items`
Items received per session.

## Submission Tables

### `consignment_submissions_log`
Transfer submission tracking and metrics.

## Sync Tables

### `consignment_sync_log`
Vend synchronization activity log.

---

**Last Updated:** 2025-11-10
**Total Tables:** 50+
**Primary Schema:** `vend_consignments` + `vend_consignment_line_items`
