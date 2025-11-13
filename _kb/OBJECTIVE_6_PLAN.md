# OBJECTIVE 6: Deputy Sync Implementation

## Problem Statement
The `DeputyService` currently calls global functions (`deputyCreateTimeSheet`, `updateDeputyTimeSheet`, `deputyApproveTimeSheet`) that don't exist. This means Deputy sync is non-functional - amendments don't propagate to Deputy timesheets.

## Current State
**File:** `services/DeputyService.php` (758 lines)
- ✅ Excellent architecture: Multi-shift, merge logic, conflict resolution
- ✅ Comprehensive error handling and logging
- ❌ Calls undefined global functions:
  * `deputyCreateTimeSheet()` - Called 2 times
  * `updateDeputyTimeSheet()` - Called 1 time
  * `deputyApproveTimeSheet()` - Called 3 times
  * `getCISUserObjectById()` - Needs verification
  * `calculateDeputyHourBreaksInMinutesBasedOnHoursWorked()` - Needs implementation

## Required Changes

### 1. Create DeputyApiClient Service (NEW FILE)
**Location:** `services/DeputyApiClient.php`
**Purpose:** Real Deputy REST API integration
**Methods:**
- `createTimesheet()` - POST to Deputy API
- `updateTimesheet()` - POST/PUT to Deputy API
- `approveTimesheet()` - POST to Deputy API
- `fetchTimesheetsForEmployee()` - GET from Deputy API

**API Endpoints:**
```
Base URL: https://[subdomain].deputy.com/api/v1/
Auth: Bearer token (from .env)

POST /resource/Timesheet        - Create timesheet
POST /resource/Timesheet/[id]   - Update timesheet
POST /supervise/timesheet/[id]  - Approve timesheet
GET  /resource/Timesheet/QUERY  - Fetch timesheets
```

### 2. Update DeputyService to use DeputyApiClient
**Changes:**
- Inject `DeputyApiClient` into constructor
- Replace `deputyCreateTimeSheet()` → `$this->apiClient->createTimesheet()`
- Replace `updateDeputyTimeSheet()` → `$this->apiClient->updateTimesheet()`
- Replace `deputyApproveTimeSheet()` → `$this->apiClient->approveTimesheet()`

### 3. Add Break Calculation Helper
**Location:** `services/DeputyService.php`
**Method:** `calculateBreakMinutes(float $hours): int`
**Logic:**
- < 4 hours: 0 minutes
- 4-6 hours: 15 minutes
- 6-8 hours: 30 minutes
- > 8 hours: 45 minutes

### 4. Environment Variables (.env.example)
```
# Deputy API Configuration (REQUIRED for payroll sync)
DEPUTY_API_BASE_URL=https://vapeshed.deputy.com/api/v1
DEPUTY_API_TOKEN=[your_api_token_here]
DEPUTY_API_TIMEOUT=45
```

### 5. Error Handling & Retry Logic
- HTTP 401/403: Log auth failure, don't retry
- HTTP 429: Rate limit - exponential backoff (3 retries)
- HTTP 500/502/503: Server error - retry 2 times
- Timeout: Retry 1 time with extended timeout
- Network error: Retry 1 time

### 6. Comprehensive Tests
**File:** `tests/Unit/DeputyApiClientTest.php`
**Tests:**
- API authentication (Bearer token)
- Create timesheet (POST)
- Update timesheet (PUT)
- Approve timesheet (POST)
- Fetch timesheets (GET with query)
- Error handling (401, 429, 500)
- Retry logic (rate limit, timeout)
- Break calculation

## Acceptance Criteria
1. ✅ DeputyApiClient implements all 4 methods with real HTTP calls
2. ✅ Environment variables documented in .env.example
3. ✅ DeputyService uses DeputyApiClient instead of global functions
4. ✅ Break calculation helper implemented
5. ✅ Error handling covers all HTTP status codes
6. ✅ Retry logic with exponential backoff
7. ✅ Comprehensive tests (20+ test cases)
8. ✅ All code passes PHP syntax check
9. ✅ Deputy sync works end-to-end (amendment → API → timesheet)

## Time Estimate: 60 minutes
- DeputyApiClient creation: 25 minutes
- DeputyService refactor: 15 minutes
- Break calculation: 5 minutes
- .env.example update: 5 minutes
- Tests: 15 minutes

## Dependencies
- cURL extension (for HTTP requests)
- DEPUTY_API_TOKEN environment variable
- Deputy API access enabled for account

## Risk Assessment
**LOW RISK** - Adding new functionality, not changing existing behavior
- New files: DeputyApiClient.php, tests
- Modifications: DeputyService.php (inject client), .env.example
- No breaking changes to public API
- Existing code continues to work (graceful degradation if API unavailable)

## Notes
- Deputy API documentation: https://www.deputy.com/api-doc/API/Resource_Calls
- API token can be generated in Deputy Settings → API → Generate Token
- Test credentials should use sandbox environment if available
