# Phase E: Critical Decision Point

## Current Situation

We've hit a fundamental API complexity issue. Phase E V2 has encountered the following:

### What We Discovered

1. **Vend API has NO direct "add customer credit" endpoint**
   - `/api/2.0/customer_balance_adjustments` doesn't exist (404)
   - `/api/2.0/customer_balance` doesn't exist (404)

2. **The existing `vend_add_payment_strict_auto()` function does the OPPOSITE of what we need**
   - It **pays DOWN existing ONACCOUNT sales** (reduces customer debt)
   - We need to **ADD credit** to customer accounts (increase their balance)
   - The flow is backwards for payroll deductions

3. **Correct Vend Pattern (Complex)**
   ```
   To ADD credit to a customer account:
   - Create a RETURN sale or ONACCOUNT sale with Store Credit payment
   - Requires proper sale structure with line items or total
   - Must use correct register_id and payment_type_id
   - Need to handle sale closure properly
   ```

### Attempted Solutions

**Attempt 1:** Direct API call to `/api/2.0/customer_balance_adjustments`
- Result: HTTP 404 (endpoint doesn't exist)

**Attempt 2:** Use existing `vend_add_payment_strict_auto()` library
- Result: Bootstrap blocking + function does opposite operation (pays debt, doesn't add credit)

**Attempt 3:** Direct POST to `/api/2.0/register_sales`
- Result: HTTP 404 "No route found for POST" (endpoint exists but wrong method or payload)

**Attempt 4 (in progress):** Testing sale creation patterns
- Status: Investigating correct payload structure for credit addition

## Options Moving Forward

### Option A: Manual Process (SAFEST, IMMEDIATE)
1. Export CSV with: employee_name, vend_customer_id, amount, note
2. Use Vend POS interface to manually apply Store Credit to each customer
3. Record the Sale IDs manually and update database
4. **Pros:** No risk of incorrect API calls, guaranteed correct accounting
5. **Cons:** Manual effort for 22 payments (~30 minutes)

### Option B: Consult Vend Documentation / Support (CORRECT, SLOW)
1. Review Vend API v2.0 documentation for "adding store credit" pattern
2. Contact Vend support if documentation unclear
3. Implement correct solution once pattern confirmed
4. **Pros:** Will have correct implementation for future
5. **Cons:** Could take hours/days to get response

### Option C: Deep Dive Existing Code (RISKY, COMPLEX)
1. Find where CIS currently adds store credit (returns, refunds, manual adjustments)
2. Reverse-engineer the exact API pattern used
3. Replicate that pattern for payroll deductions
4. **Pros:** Uses proven internal pattern
5. **Cons:** Time-consuming, may not exist, could break things

### Option D: Alternative Solution - Track in CIS Only (HYBRID)
1. Mark deductions as "allocated" in CIS database
2. Generate report for accounting purposes
3. Apply credits in Vend through normal POS workflow
4. Record Sale IDs back in CIS after manual application
5. **Pros:** Separates automation from manual verification
6. **Cons:** Doesn't achieve full automation goal

## Data Ready for Any Option

**All 22 payments are prepped and validated:**
- ✅ vend_customer_id populated (100% mapped)
- ✅ Amounts validated ($833.48 total)
- ✅ Idempotency keys generated
- ✅ Payment dates set (2025-10-28)
- ✅ Notes formatted ("Payroll deduction - Oct 28, 2025 - Employee Name")

**CSV Export ready:**
```csv
Employee,Vend Customer ID,Amount,Note,Payrun Date
Dylan Steinz,48c08456-9f3e-11e9-f1a9-2f1f68d72a26,50.00,Payroll deduction - Oct 28 2025,2025-10-28
Kiel Newman,02dcd191-ae71-11e9-f336-0ccf77c4cc73,50.00,Payroll deduction - Oct 28 2025,2025-10-28
...
```

## Recommended Path

Given:
- This is payroll (high accuracy required)
- Only 22 payments this payrun
- Unknown Vend API pattern for credits
- Time sensitivity (already spent 2+ hours on automation)

**I recommend Option A (Manual Process) with Option B follow-up:**

### Immediate (Next 30 minutes):
1. Export CSV for manual application
2. Mark as "pending_manual_application" in database
3. Apply via Vend POS with Store Credit
4. Record Sale IDs back in database
5. Verify balances in Vend

### Follow-up (This week):
1. Document the manual process for record
2. Research correct Vend API pattern
3. Build automated solution for next payrun
4. Test with single payment before bulk run

## Your Decision

**What would you like to do?**

A. Proceed with manual application (CSV export ready)
B. Continue debugging API (may take hours)
C. Consult Vend documentation first
D. Hybrid approach (CIS tracking + manual Vend)
E. Something else you have in mind

**Current Time Investment:**
- Phase E attempts: ~2.5 hours
- Remaining work if automated: Unknown (complex API)
- Manual application: ~30 minutes guaranteed completion

The automation is valuable but may require Vend-specific knowledge we don't have immediate access to. The manual path ensures payroll processes correctly while we perfect automation for future runs.

**What's your call?**
