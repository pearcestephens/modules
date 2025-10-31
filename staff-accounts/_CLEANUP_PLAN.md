# Staff Accounts Module Cleanup - October 25, 2025

## PRODUCTION FILES (Keep in Root)

### Core Entry Points:
- index.php (main dashboard)
- bootstrap.php (module initialization)

### Production Views:
- views/make-payment.php ✅ (CIS template converted)
- payment-success.php (needs to move to views/)
- staff-list.php (needs to move to views/)
- my-account.php (needs to move to views/)

### Production API Endpoints:
- api/payment.php
- api/process-payment.php
- api/customer-search.php
- api/staff-reconciliation.php
- api/manager-dashboard.php
- api/employee-mapping.php
- api/auto-match-suggestions.php

### Production Libraries (lib/):
- All lib/*.php files are production-ready services

### Production CLI Tools:
- cli/sync-xero-payroll.php

### Production Database Tools:
- database/run-migration.php
- database/get-table-structures.php
- database/create-employee-mappings-table.php

---

## FILES TO ARCHIVE

### DEBUG FILES → _archive/debug-files-20251025/:
- check-db.php
- check-deductions.php
- check-deduction-breakdown.php
- check-payroll-data.php
- check-system-status.php
- verify-schema.php
- verify-real-database.php
- testing-bot-bypass.php
- database/check-errors.sh
- database/debug-curl.sh
- database/find-logs.sh

### TEST FILES → _archive/test-files-20251025/:
- test_api.php
- test_balance_calc.php
- api-endpoint-validator.php
- database/comprehensive-api-test.sh
- database/test-employee-mapping-api.sh
- database-integrity-test.sh

### OLD/DEMO/DEPLOYMENT FILES → _archive/old-versions-20251025/:
- deploy-payment-system.php
- deploy-payment-system.sh
- cleanup-active-staff-only.php
- CLEANUP_AND_AUDIT.sh
- fix-api-tables.php
- fix-employee-mapping.php
- populate-reconciliation.php
- map-existing-deductions.php
- migrate-employee-mappings.php
- final-mapping-summary.php
- manual-mapping-tool.php
- employee-mapping.php (duplicate of views version)
- manager-dashboard.php (duplicate of api version)
- staff-reconciliation.php (duplicate of api version)
- vend-customer-mapping.php

### OLD VIEW VERSIONS → _archive/old-versions-20251025/views/:
- views/make-payment_backup_20251025_165107.php
- views/make-payment-REFACTORED.php
- views/manual-mapping-demo.php
- views/manual-mapping.php
- views/auto-match-review.php
- views/analytics-dashboard.php
- views/employee-mapping.php (use api version instead)

---

## POST-CLEANUP STRUCTURE

```
staff-accounts/
├── index.php                    ⭐ Main dashboard
├── bootstrap.php                ⭐ Module init
│
├── views/                       ⭐ Production views only
│   ├── make-payment.php        ✅ CIS template
│   ├── payment-success.php     ✅ CIS template  
│   ├── staff-list.php          ✅ CIS template
│   └── my-account.php          🔨 Needs CIS conversion
│
├── api/                         ⭐ Production API endpoints
│   ├── payment.php
│   ├── process-payment.php
│   ├── customer-search.php
│   ├── staff-reconciliation.php
│   ├── manager-dashboard.php
│   ├── employee-mapping.php
│   └── auto-match-suggestions.php
│
├── lib/                         ⭐ Production services
│   ├── PaymentService.php
│   ├── StaffAccountService.php
│   ├── XeroPayrollService.php
│   ├── VendApiService.php
│   └── [other services...]
│
├── cli/                         ⭐ Production CLI tools
│   └── sync-xero-payroll.php
│
├── database/                    ⭐ Production DB tools
│   ├── run-migration.php
│   ├── get-table-structures.php
│   └── create-employee-mappings-table.php
│
└── _archive/                    📦 All archived files
    ├── pre-rebuild-20251024/
    ├── debug-files-20251025/
    ├── test-files-20251025/
    └── old-versions-20251025/
```

---

## FILES TO DELETE (NOT ARCHIVE)

None - archive everything for safety

---

## CLEANUP ACTIONS

1. ✅ Create archive directories
2. Move debug files
3. Move test files  
4. Move old/demo files
5. Move old view versions
6. Move payment-success.php → views/
7. Move staff-list.php → views/
8. Move my-account.php → views/
9. Update index.php paths if needed
10. Test all production pages
11. Document final structure

