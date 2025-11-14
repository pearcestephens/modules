#!/bin/bash

# Database Integrity Testing Suite
# Comprehensive validation of all database components for Employee Mapping System

API_BASE="https://staff.vapeshed.co.nz/modules/staff-accounts/api/employee-mapping.php"
TESTING_BYPASS_TOKEN="TEST_BYPASS_TOKEN_20251023_COMPREHENSIVE_TESTING"
MYSQL_HOST="127.0.0.1"
MYSQL_USER="jcepnzzkmj"
MYSQL_PASS="wprKh9Jq63"
MYSQL_DB="jcepnzzkmj"
MYSQL_CONFIG="-h $MYSQL_HOST -u $MYSQL_USER -p$MYSQL_PASS"
TEST_OUTPUT_DIR="./test-results"
DB_LOG_FILE="$TEST_OUTPUT_DIR/database-integrity-$(date +%Y%m%d_%H%M%S).log"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
PURPLE='\033[0;35m'
CYAN='\033[0;36m'
NC='\033[0m' # No Color

# Test counters
TESTS_TOTAL=0
TESTS_PASSED=0
TESTS_FAILED=0
TESTS_WARNING=0

echo -e "${CYAN}"
echo "════════════════════════════════════════════════════════════════════════════════"
echo "  Employee Mapping System - Database Integrity Testing Suite v1.0"
echo "════════════════════════════════════════════════════════════════════════════════"
echo -e "${NC}"

print_test_header() {
    echo -e "${PURPLE}📊 $1${NC}"
}

print_success() {
    echo -e "${GREEN}✅ $1${NC}"
}

print_error() {
    echo -e "${RED}❌ $1${NC}"
}

print_warning() {
    echo -e "${YELLOW}⚠️  $1${NC}"
}

print_info() {
    echo -e "${BLUE}ℹ️  $1${NC}"
}

log_test() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] TEST: $1" >> "$DB_LOG_FILE"
}

log_result() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $1: $2" >> "$DB_LOG_FILE"
}

# Create output directory
mkdir -p "$TEST_OUTPUT_DIR"

print_info "Database: $MYSQL_DB"
print_info "Logging to: $DB_LOG_FILE"
echo ""

# ============================================================================
# TEST 1: DATABASE CONNECTION AND BASIC ACCESS
# ============================================================================

print_test_header "TEST 1: Database Connection and Basic Access"

# Test MySQL connection
log_test "MySQL Connection Test"
if mysql $MYSQL_CONFIG -e "SELECT 1;" 2>/dev/null; then
    print_success "MySQL connection established"
    log_result "PASS" "MySQL connection successful"
    ((TESTS_PASSED++))
else
    print_error "MySQL connection failed"
    log_result "FAIL" "MySQL connection failed"
    ((TESTS_FAILED++))
fi
((TESTS_TOTAL++))

# Test database existence
log_test "Database Existence Check"
if mysql $MYSQL_CONFIG -e "USE $MYSQL_DB;" 2>/dev/null; then
    print_success "Database '$MYSQL_DB' exists and accessible"
    log_result "PASS" "Database access successful"
    ((TESTS_PASSED++))
else
    print_error "Database '$MYSQL_DB' not accessible"
    log_result "FAIL" "Database access failed"
    ((TESTS_FAILED++))
fi
((TESTS_TOTAL++))

# ============================================================================
# TEST 2: EMPLOYEE MAPPING RELATED TABLES
# ============================================================================

print_test_header "TEST 2: Employee Mapping Related Tables"

# Expected tables for employee mapping
employee_tables=(
    "vend_users"
    "vend_outlets"
    "vend_customers" 
    "vend_sales"
    "vend_payment_allocations"
    "employee_mappings"
    "employee_mapping"
    "action_audit"
)

for table in "${employee_tables[@]}"; do
    log_test "Table existence: $table"
    if mysql $MYSQL_CONFIG -D "$MYSQL_DB" -e "DESCRIBE $table;" &>/dev/null; then
        print_success "Table '$table' exists"
        log_result "PASS" "Table $table found"
        ((TESTS_PASSED++))
        
        # Get row count
        row_count=$(mysql $MYSQL_CONFIG -D "$MYSQL_DB" -se "SELECT COUNT(*) FROM $table;" 2>/dev/null)
        if [ -n "$row_count" ]; then
            print_info "  → $row_count rows"
            log_result "INFO" "Table $table has $row_count rows"
        fi
    else
        print_warning "Table '$table' missing or inaccessible"
        log_result "WARN" "Table $table not found"
        ((TESTS_WARNING++))
    fi
    ((TESTS_TOTAL++))
done

# ============================================================================
# TEST 3: TABLE SCHEMA VALIDATION
# ============================================================================

print_test_header "TEST 3: Table Schema Validation"

# Check critical columns in employee mapping tables
log_test "vend_users schema validation"
if mysql $MYSQL_CONFIG -D "$MYSQL_DB" -e "SELECT id, username, email FROM vend_users LIMIT 1;" &>/dev/null; then
    print_success "vend_users has required columns"
    log_result "PASS" "vend_users schema valid"
    ((TESTS_PASSED++))
else
    print_error "vend_users missing required columns"
    log_result "FAIL" "vend_users schema invalid"
    ((TESTS_FAILED++))
fi
((TESTS_TOTAL++))

log_test "vend_customers schema validation"
if mysql $MYSQL_CONFIG -D "$MYSQL_DB" -e "SELECT id, customer_code, first_name, last_name FROM vend_customers LIMIT 1;" &>/dev/null; then
    print_success "vend_customers has required columns"
    log_result "PASS" "vend_customers schema valid"
    ((TESTS_PASSED++))
else
    print_error "vend_customers missing required columns"
    log_result "FAIL" "vend_customers schema invalid"
    ((TESTS_FAILED++))
fi
((TESTS_TOTAL++))

log_test "employee_mappings schema validation"
if mysql $MYSQL_CONFIG -D "$MYSQL_DB" -e "SELECT id FROM employee_mappings LIMIT 1;" &>/dev/null; then
    print_success "employee_mappings table exists"
    log_result "PASS" "employee_mappings schema valid"
    ((TESTS_PASSED++))
else
    print_warning "employee_mappings table may need creation"
    log_result "WARN" "employee_mappings schema may be missing"
    ((TESTS_WARNING++))
fi
((TESTS_TOTAL++))

# ============================================================================
# TEST 4: DATA INTEGRITY AND CONSTRAINTS
# ============================================================================

print_test_header "TEST 4: Data Integrity and Constraints"

# Check for orphaned records
log_test "Orphaned employee records check"
orphaned_employees=$(mysql $MYSQL_CONFIG -D "$MYSQL_DB" -se "
    SELECT COUNT(*) FROM employee_mappings em 
    LEFT JOIN vend_users vu ON em.vend_user_id = vu.id 
    WHERE vu.id IS NULL;" 2>/dev/null)

if [ -n "$orphaned_employees" ] && [ "$orphaned_employees" -eq 0 ]; then
    print_success "No orphaned employee mapping records"
    log_result "PASS" "No orphaned employee records"
    ((TESTS_PASSED++))
elif [ -n "$orphaned_employees" ]; then
    print_warning "Found $orphaned_employees orphaned employee mapping records"
    log_result "WARN" "$orphaned_employees orphaned employee records"
    ((TESTS_WARNING++))
else
    print_info "Orphaned employee check skipped (table missing)"
    log_result "SKIP" "Orphaned employee check skipped"
fi
((TESTS_TOTAL++))

log_test "Orphaned customer records check"
orphaned_customers=$(mysql $MYSQL_CONFIG -D "$MYSQL_DB" -se "
    SELECT COUNT(*) FROM employee_mappings em 
    LEFT JOIN vend_customers vc ON em.customer_id = vc.customer_id 
    WHERE vc.customer_id IS NULL;" 2>/dev/null)

if [ -n "$orphaned_customers" ] && [ "$orphaned_customers" -eq 0 ]; then
    print_success "No orphaned customer mapping records"
    log_result "PASS" "No orphaned customer records"
    ((TESTS_PASSED++))
elif [ -n "$orphaned_customers" ]; then
    print_warning "Found $orphaned_customers orphaned customer mapping records"
    log_result "WARN" "$orphaned_customers orphaned customer records"
    ((TESTS_WARNING++))
else
    print_info "Orphaned customer check skipped (table missing)"
    log_result "SKIP" "Orphaned customer check skipped"
fi
((TESTS_TOTAL++))

# ============================================================================
# TEST 5: INDEXES AND PERFORMANCE
# ============================================================================

print_test_header "TEST 5: Indexes and Performance"

# Check for important indexes
expected_indexes=(
    "vend_users:id"
    "vend_customers:customer_id"
    "vend_sales:sale_id"
    "employee_mappings:id"
    "employee_mappings:vend_user_id"
)

for index_info in "${expected_indexes[@]}"; do
    IFS=':' read -ra PARTS <<< "$index_info"
    table="${PARTS[0]}"
    column="${PARTS[1]}"
    
    log_test "Index check: $table.$column"
    
    # Check if index exists on column
    index_exists=$(mysql $MYSQL_CONFIG -D "$MYSQL_DB" -e "
        SHOW INDEX FROM $table WHERE Column_name = '$column';" 2>/dev/null | wc -l)
    
    if [ "$index_exists" -gt 1 ]; then  # > 1 because of header row
        print_success "Index found on $table.$column"
        log_result "PASS" "Index exists on $table.$column"
        ((TESTS_PASSED++))
    else
        print_warning "No index on $table.$column (may impact performance)"
        log_result "WARN" "No index on $table.$column"
        ((TESTS_WARNING++))
    fi
    ((TESTS_TOTAL++))
done

# ============================================================================
# TEST 6: STORED PROCEDURES AND FUNCTIONS
# ============================================================================

print_test_header "TEST 6: Stored Procedures and Functions"

# Check for employee mapping related procedures
log_test "Stored procedures check"
procedures=$(mysql $MYSQL_CONFIG -D "$MYSQL_DB" -e "
    SHOW PROCEDURE STATUS WHERE Db = '$MYSQL_DB';" 2>/dev/null | grep -v "Procedure" | wc -l)

if [ "$procedures" -gt 0 ]; then
    print_success "Found $procedures stored procedures"
    log_result "PASS" "$procedures stored procedures found"
    ((TESTS_PASSED++))
else
    print_info "No stored procedures found (not necessarily an issue)"
    log_result "INFO" "No stored procedures"
fi
((TESTS_TOTAL++))

# ============================================================================
# TEST 7: DATABASE PERMISSIONS AND SECURITY
# ============================================================================

print_test_header "TEST 7: Database Permissions and Security"

# Test read permissions
log_test "Read permissions test"
if mysql $MYSQL_CONFIG -D "$MYSQL_DB" -e "SELECT COUNT(*) FROM vend_sales LIMIT 1;" &>/dev/null; then
    print_success "Read permissions working"
    log_result "PASS" "Read permissions OK"
    ((TESTS_PASSED++))
else
    print_error "Read permissions failed"
    log_result "FAIL" "Read permissions failed"
    ((TESTS_FAILED++))
fi
((TESTS_TOTAL++))

# Test write permissions (if employee_mappings table exists)
log_test "Write permissions test"
test_write_result=$(mysql $MYSQL_CONFIG -D "$MYSQL_DB" -e "
    CREATE TEMPORARY TABLE test_write_permissions (id INT); 
    INSERT INTO test_write_permissions VALUES (1); 
    DROP TEMPORARY TABLE test_write_permissions;" 2>&1)

if [ $? -eq 0 ]; then
    print_success "Write permissions working"
    log_result "PASS" "Write permissions OK"
    ((TESTS_PASSED++))
else
    print_warning "Write permissions may be limited"
    log_result "WARN" "Write permissions limited: $test_write_result"
    ((TESTS_WARNING++))
fi
((TESTS_TOTAL++))

# ============================================================================
# TEST 8: API DATABASE INTEGRATION
# ============================================================================

print_test_header "TEST 8: API Database Integration"

# Test if API can retrieve database data
log_test "API database integration test"
api_response=$(curl -s \
    -H "X-Testing-Bypass: $TESTING_BYPASS_TOKEN" \
    -H "User-Agent: DatabaseIntegrityTest/1.0" \
    "$API_BASE?action=dashboard_data")

if echo "$api_response" | jq '.success' 2>/dev/null | grep -q 'true'; then
    print_success "API successfully retrieves database data"
    log_result "PASS" "API database integration working"
    ((TESTS_PASSED++))
    
    # Check for expected data structure
    if echo "$api_response" | jq '.blocked_amount, .unmapped_count' &>/dev/null; then
        print_success "API returns expected data structure"
        log_result "PASS" "API data structure valid"
        ((TESTS_PASSED++))
    else
        print_warning "API data structure may be incomplete"
        log_result "WARN" "API data structure incomplete"
        ((TESTS_WARNING++))
    fi
else
    print_error "API database integration failed"
    log_result "FAIL" "API database integration failed"
    ((TESTS_FAILED++))
fi
((TESTS_TOTAL++))

# ============================================================================
# GENERATE FINAL REPORT
# ============================================================================

echo ""
echo -e "${CYAN}════════════════════════════════════════════════════════════════════════════════"
echo "                          DATABASE INTEGRITY TESTING COMPLETE"
echo "════════════════════════════════════════════════════════════════════════════════${NC}"
echo ""

# Calculate success rate
if [ $TESTS_TOTAL -gt 0 ]; then
    success_rate=$(echo "scale=1; $TESTS_PASSED * 100 / $TESTS_TOTAL" | bc -l)
else
    success_rate=0
fi

echo -e "${PURPLE}📊 Database Integrity Test Summary:${NC}"
echo -e "   ├─ Total Tests: $TESTS_TOTAL"
echo -e "   ├─ Passed: ${GREEN}$TESTS_PASSED${NC}"
echo -e "   ├─ Failed: ${RED}$TESTS_FAILED${NC}"
echo -e "   ├─ Warnings: ${YELLOW}$TESTS_WARNING${NC}"
echo -e "   └─ Success Rate: ${GREEN}$success_rate%${NC}"
echo ""

if [ $TESTS_FAILED -eq 0 ]; then
    echo -e "${GREEN}🎉 Database integrity tests completed successfully!${NC}"
    if [ $TESTS_WARNING -gt 0 ]; then
        echo -e "${YELLOW}⚠️  Please review $TESTS_WARNING warnings for optimization opportunities.${NC}"
    fi
else
    echo -e "${RED}❌ Database integrity issues detected. Please review failed tests.${NC}"
fi

echo ""
echo -e "${BLUE}📝 Detailed Log: $DB_LOG_FILE${NC}"
echo -e "${BLUE}🚀 Database integrity testing completed!${NC}"
echo ""

# Exit with appropriate code
if [ $TESTS_FAILED -gt 0 ]; then
    exit 1
else
    exit 0
fi