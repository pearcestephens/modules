# OBJECTIVE 5: Auth & CSRF Consistency

## Goal

**Ensure all routes properly enforce authentication and CSRF protection consistently across the entire payroll module.**

## Problem

While routes are defined with `'auth' => true` and `'csrf' => true`, need to verify:
1. All protected endpoints actually check authentication
2. All POST/PUT/DELETE endpoints check CSRF
3. No bypass opportunities exist
4. CSRF token rotation is secure
5. Authentication middleware is consistently applied

## Current State

**Good Signs:**
- Routes defined with `'auth' => true` flag
- CSRF tokens generated and validated in index.php
- CSRF validation function exists: `payroll_validate_csrf()`
- Router checks `$matchedRoute['csrf']` flag
- BaseController has `requireAuth()` and `verifyCsrf()` helpers

**Risks:**
- Controllers might not call `requireAuth()` consistently
- Some POST endpoints might not have `'csrf' => true` in routes
- CSRF token validation might be skipped in some code paths
- Auth bypass if controller doesn't check before action execution

## Acceptance Criteria

- [ ] All protected routes call `requireAuth()` in controller
- [ ] All POST/PUT/DELETE routes have `'csrf' => true` in routes.php
- [ ] All POST/PUT/DELETE controllers call `verifyCsrf()` or rely on router middleware
- [ ] No authentication bypass opportunities found
- [ ] CSRF token rotation is secure (30 min expiry ✅ already implemented)
- [ ] Tests verify auth enforcement on all protected endpoints
- [ ] Tests verify CSRF enforcement on all state-changing endpoints

## Implementation Plan

### 1. Audit Routes (10 min)
- Read all routes in routes.php
- List all endpoints with `'auth' => true`
- List all POST/PUT/DELETE endpoints
- Verify all POST/PUT/DELETE have `'csrf' => true`
- Identify any missing flags

### 2. Audit Controllers (15 min)
- Check each controller that handles protected routes
- Verify `requireAuth()` is called early in action methods
- Verify BaseController constructor calls `requireAuth()` if applicable
- Check for any auth bypass logic (like `if (!$skipAuth)`)
- List controllers that are missing auth checks

### 3. Fix Missing Auth Checks (10 min)
- Add `$this->requireAuth();` to controllers missing it
- Add `'csrf' => true` to routes missing it
- Ensure consistent pattern across all controllers

### 4. Create Tests (10 min)
- Test auth enforcement (401/403 when not authenticated)
- Test CSRF enforcement (403 when CSRF invalid)
- Test CSRF bypass attempts (no token, wrong token, expired token)
- Test auth bypass attempts (removed session, wrong user ID)

## Files to Check

**Routes:**
- routes.php (all 100+ routes)

**Controllers:**
- AmendmentController.php
- PayrollAutomationController.php
- XeroController.php
- WageDiscrepancyController.php
- BonusController.php
- VendPaymentController.php
- LeaveController.php
- PayRunController.php
- DashboardController.php
- AttendanceController.php
- TimesheetController.php

**Entry Point:**
- index.php (router middleware)

**Base:**
- controllers/BaseController.php (requireAuth, verifyCsrf)

## Security Risks

**HIGH:**
- Authentication bypass on sensitive endpoints (payrun creation, approvals)
- CSRF bypass on state-changing operations (approve, decline, create payments)
- Authorization bypass (staff accessing admin-only endpoints)

**MEDIUM:**
- Inconsistent auth checks across controllers
- Missing CSRF on some POST endpoints
- Session fixation if CSRF not rotated properly

## Expected Fixes

1. Add `requireAuth()` calls to 5-10 controllers
2. Add `'csrf' => true` to 3-5 POST routes
3. Create 15-20 security tests
4. Update BaseController if needed (make requireAuth() easier to use)

## Time Estimate: 45 minutes

- Audit: 25 min
- Fixes: 10 min
- Tests: 10 min
