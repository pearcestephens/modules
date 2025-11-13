# OBJECTIVE 8: Router Unification & Standardization

## Problem Statement
The payroll module has 57 routes in `routes.php`, and while the Objective 5 audit showed excellent security (98/100), we need to ensure all routes follow consistent patterns for:
- Naming conventions
- Auth/CSRF flag placement
- Permission definitions
- Route grouping
- Documentation

## Current State
**From Objective 5 Audit:**
- 57 routes total
- 98.2% auth coverage (56/57 routes)
- 95.2% CSRF coverage (54/57 routes on POST)
- Only 1 advisory (print endpoint missing CSRF, non-critical)

**Good Practices Already Present:**
- Router middleware enforces auth/CSRF
- BaseController helpers verify permissions
- Consistent permission key format

**Potential Issues:**
- Route naming consistency unknown (need review)
- Duplicate route definitions possible
- Documentation gaps
- Grouping/organization could be improved

## Goals

### 1. Route Naming Standardization
- Ensure consistent naming: `module.controller.action` format
- Example: `payroll.xero.connect`, `payroll.amendments.approve`
- No arbitrary or legacy names

### 2. Route Organization
- Group related routes together (Xero, Deputy, Amendments, etc.)
- Add section comments for clarity
- Alphabetize within groups

### 3. Eliminate Duplication
- Find any duplicate route definitions
- Consolidate if multiple routes point to same controller

### 4. Documentation
- Add PHPDoc block at top of routes.php
- Document auth/CSRF flags
- Explain permission system

### 5. Pattern Consistency
- All routes use same parameter order
- All routes have comments (purpose, permissions)
- All routes specify auth/CSRF explicitly (no defaults)

## Required Changes

### 1. Audit Current Routes
**File:** `routes.php`
- Read all 57 routes
- Categorize by feature (Xero, Deputy, Amendments, Reports, etc.)
- Identify naming inconsistencies
- Find duplicates

### 2. Standardize Naming
**Before:**
```php
$router->get('xero-connect', ...)  // Inconsistent naming
$router->post('save_amendment', ...)  // Mixed snake_case/kebab-case
```

**After:**
```php
$router->get('payroll.xero.connect', ...)  // Dot notation
$router->post('payroll.amendments.save', ...)  // Consistent format
```

### 3. Add Route Documentation
```php
/**
 * Payroll Module Routes
 *
 * Route Naming Convention: payroll.{feature}.{action}
 *
 * Features:
 * - xero: Xero API integration (OAuth, pay runs, employees)
 * - deputy: Deputy API integration (timesheets, amendments)
 * - amendments: Amendment management (create, approve, reject)
 * - reports: Payroll reports (pay periods, summaries, exports)
 * - admin: Administrative functions (settings, sync)
 *
 * Authentication:
 * - All routes require authentication (auth: true)
 * - POST routes require CSRF token (csrf: true)
 * - Permissions checked in BaseController (requirePermission)
 *
 * Permissions:
 * - payroll.view: View payroll data
 * - payroll.manage: Create/edit pay runs
 * - payroll.approve: Approve amendments
 * - payroll.admin: System configuration
 */
```

### 4. Reorganize Routes by Feature
```php
// ============================================================================
// XERO INTEGRATION ROUTES
// ============================================================================

$router->get('payroll.xero.connect', 'XeroController@connect', [
    'auth' => true,
    'csrf' => false,
    'permission' => 'payroll.admin'
]);

$router->get('payroll.xero.callback', 'XeroController@callback', [
    'auth' => true,
    'csrf' => false,
    'permission' => 'payroll.admin'
]);

// ... (grouped together)

// ============================================================================
// DEPUTY INTEGRATION ROUTES
// ============================================================================

$router->post('payroll.deputy.sync', 'DeputyController@sync', [
    'auth' => true,
    'csrf' => true,
    'permission' => 'payroll.manage'
]);

// ... (grouped together)
```

### 5. Validation Rules
- Every route MUST have `auth` flag (no implicit defaults)
- POST/PUT/DELETE routes MUST have `csrf: true`
- Every route MUST have `permission` key
- Route names MUST follow `payroll.{feature}.{action}` format
- Comments required for complex routes

## Time Estimate: 45 minutes
- Audit routes: 15 minutes
- Standardize naming: 10 minutes
- Reorganize & document: 15 minutes
- Test all routes: 5 minutes

## Acceptance Criteria
1. ✅ All routes follow `payroll.{feature}.{action}` naming
2. ✅ Routes grouped by feature with section headers
3. ✅ Comprehensive PHPDoc at top of routes.php
4. ✅ No duplicate route definitions
5. ✅ All routes explicitly specify auth/csrf/permission
6. ✅ Routes alphabetized within feature groups
7. ✅ Comments explain non-obvious routes
8. ✅ All 57 routes still functional (no breaking changes)

## Testing
- Manual: Click through all routes in browser
- Automated: Route existence tests
- Security: Verify auth/CSRF enforcement unchanged

## Risk Assessment
**LOW RISK** - Refactoring only, no logic changes
- Routes still point to same controllers
- Auth/CSRF/permissions unchanged
- Only naming and organization improved
- Easy rollback (git revert)

## Notes
- This is code quality improvement (maintainability)
- No security fixes needed (already 98/100)
- Makes future route additions easier
- Improves developer experience
