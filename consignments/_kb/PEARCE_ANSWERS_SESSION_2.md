# 📝 Session 2 Answers - Questions 13-35

**Session:** 3 (Continued Gap Analysis)
**Date Started:** October 31, 2025
**Progress:** 1/23 questions answered

---

## Question 13: Signature Capture & Authentication

**Question:** When a staff member completes a PO receipt, how should signatures be captured and stored?

**Your Answer:**

1. **Capture Method:**
   - ✅ Checkbox + Staff ID Authentication
   - Simple auth mechanism, no complex drawing tools needed

2. **Who Signs:**
   - ✅ Receiving Staff Member Only
   - Supplier/courier involvement not required

3. **Storage Format:**
   - ✅ Audit Trail + PNG File System
   - Audit trail: Database logging of who, what, when
   - Signature image: PNG files stored in file system

4. **Required or Optional:**
   - ✅ Depends on Outlet/Supplier + Configurable
   - Per-outlet configuration flexibility
   - Per-supplier configuration flexibility
   - Staff can disable if not needed for certain scenarios

**Implementation Notes:**

- Signature method: Checkbox + automatic staff_id linkage from session
- Storage:
  - Database: signature_timestamp, staff_id, receipt_id (audit trail)
  - File system: `/var/receipts/signatures/[outlet_id]/[receipt_id]/signature.png`
- Configuration:
  - Add `signature_required` flag to outlets table
  - Add `signature_required` flag to suppliers table
  - Signature field in DB: nullable (can be NULL if not required)
  - Audit log entry: Created for all signature actions regardless of requirement
- Audit Trail Schema:
  ```sql
  signature_audit_log (
    id,
    receipt_id,
    staff_id,
    outlet_id,
    supplier_id,
    signature_timestamp,
    signature_file_path,
    action (created|viewed|verified|rejected),
    created_at
  )
  ```

**Impact:**

- **Database:** Add signature configuration flags, audit trail table
- **File System:** Create signature storage directory structure
- **UI:** Checkbox + confirmation dialog before finalizing receipt
- **API:** POST endpoint to mark receipt as signed

**Status:** ✅ Answered

---

## Question 14: Barcode Scanning Workflow

**Question:** When receiving goods, how should barcode scanning integrate? Should it be required, optional, or flexible?

**Your Answer:**

1. **Scanning Requirement:**
   - ✅ Optional (Not Mandatory)
   - Users can choose to use barcode scanning or not
   - Manual entry must be fully supported as fallback
   - "Not a big deal" - flexibility is key
   - Easy to use BOTH styles (barcode-first and manual-first workflows)

2. **Barcode Format Support:**
   - ✅ Any Barcode Type Supported
   - Not limited to specific formats
   - EAN-13, UPC, Code128, custom suppliers - all work
   - Flexible decoder (accept any valid barcode)

3. **Scan Verification Logic:**
   - ✅ Accept ANY Quantity/Value
   - No blocking on quantity mismatches
   - MUST accept over-receipt without warnings
   - MUST accept under-receipt without warnings
   - Different audio tones for different scenarios:
     - **Tone 1:** When reaching expected QTY (success tone)
     - **Tone 2:** For unexpected products (alert tone)
     - **Tone 3:** For warnings/notices (gentle tone)
     - Additional visual feedback with tones
   - Operator always in control (no forced approval workflows)

**Implementation Notes:**

- Barcode Scanning as Optional Feature:
  - Toggle: "Enable barcode scanning" per outlet
  - UI: Dual-mode interface (manual + scan tabs)
  - Default: Manual entry, scan as enhancement

- Barcode Support:
  - Integration: PHP barcode decoder library (any format)
  - Processing: `barcode_decode(input) → product_sku`
  - Lookup: Match SKU to product in PO
  - Flexible: Accept barcode that doesn't match product (override capable)

- Audio Feedback System:
  ```
  Scenario: Scanned item reaches PO qty → Tone 1 (success beep)
  Scenario: Unexpected product scanned → Tone 2 (alert beep)
  Scenario: System warning/info → Tone 3 (gentle beep)
  ```

- Database:
  - Add `barcode_scanning_enabled` flag to outlets
  - Add `barcode_format_preference` (optional, not enforced)
  - Log: All scans recorded in audit trail with tone/result

- UI/UX:
  - Split interface: Manual entry on left, Scanner on right
  - Both work independently
  - Visual indicator: "Qty Complete" when reaching expected amount
  - Color changes: Green (complete), Yellow (warning), Red (info)
  - No error states - just guidance

**Impact:**

- **Database:** Minimal (configuration flags only)
- **Audio:** Integration with browser audio API or system sounds
- **UX:** Significant (dual-mode entry system with tone feedback)
- **Processing:** Simple barcode parsing (no complex validation)

**Status:** ✅ Answered

---

## Question 15: Email Notifications & Automated Alerts

**Question:** Which email notifications should the system send automatically? Who gets what and when?

**Your Answer:**

1. **Who Receives Emails:**
   - ✅ Supplier receives emails (PO notifications)
   - ✅ Management Team receives weekly automated overview reports
   - ✅ Store Manager receives overview (likely included in management report)
   - Internal outlet staff: No individual emails (use in-app notifications)

2. **When Emails Are Sent:**
   - ✅ Weekly Automated Overview Report
   - Scheduled: Start of week (Monday) or as needed
   - Recipients: Management Team + Store Manager
   - Auto-generated from existing cron system
   - Includes: PO status, receipts, exceptions, metrics

3. **Email System:**
   - ✅ Leverage Existing Cron System
   - Current system has many configuration options
   - Don't reinvent - extend existing functionality
   - Cron handles scheduling & automation
   - Templates: Already built, just customize as needed

**Implementation Notes:**

- Supplier Notifications:
  - Trigger: When PO status changes to ACTIVE
  - Content: PO details, delivery date, expected items
  - Template: Generic "PO Created" email with order summary
  - Include: PO number, items, quantities, delivery address

- Management Weekly Report:
  - Trigger: Cron job (start of week: Monday 6 AM)
  - Recipient: Management team email list + Store Manager
  - Content:
    - Total POs created this week
    - Total receipts completed
    - Over-receipts flagged
    - Missing items summary
    - Supplier performance metrics
    - Any exceptions/alerts
  - Format: HTML table + summary statistics
  - Frequency: Weekly (configurable to on-demand if needed)

- Existing Cron Integration:
  - Location: Existing cron system in `/assets/services/` or similar
  - Function: Collect data → Build report → Send email
  - Configuration: Recipient lists, template options, schedule
  - No new system needed - just data aggregation

- Database:
  - Add `email_recipient_list` table for management team
  - Add `email_schedule_config` (weekly, on-demand, custom)
  - Store sent report history (for audit trail)

- Flexibility:
  - Cron has many options - use them
  - Allow custom email preferences per outlet
  - Template customization for branding
  - Can adjust to "on-demand" reports if needed

**Impact:**

- **Database:** Minimal (recipient lists + config flags)
- **Email:** Integrate with existing cron system
- **UX:** Zero impact on app (automated background process)
- **Suppliers:** Get PO confirmation emails
- **Management:** Get weekly business intelligence

**Status:** ✅ Answered

---

## Question 16: Product Search & Autocomplete

---

## [Questions 16-35 - Template Ready]

All 23 remaining questions ready to be answered following same format.

---

**Auto-Push:** Running (PID: 25193) - All changes will be committed every 5 minutes
