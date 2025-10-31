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

## Question 14: Signature Technology & UX Details

**Status:** ⏳ Ready to Answer

---

## Question 15: [Ready for continuation]

**Status:** ⏳ Ready to Answer

---

## [Questions 16-35 - Template Ready]

All 23 remaining questions ready to be answered following same format.

---

**Auto-Push:** Running (PID: 25193) - All changes will be committed every 5 minutes
