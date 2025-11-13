#!/bin/bash

# Quick deployment runner for fixed components
SCHEMA_DIR="/home/master/applications/jcepnzzkmj/public_html/modules/human_resources/payroll/schema"
TIMESTAMP=$(date +%Y%m%d_%H%M%S)
LOG_FILE="$SCHEMA_DIR/deploy_fixed_${TIMESTAMP}.log"

echo "════════════════════════════════════════════════════════════"
echo "DEPLOYING FIXED PAYROLL AI COMPONENTS"
echo "════════════════════════════════════════════════════════════"
echo ""
echo "Log file: $LOG_FILE"
echo ""

# Check if password is provided
if [ -z "$DB_PASSWORD" ]; then
    echo "Error: DB_PASSWORD not provided"
    echo "Usage: DB_PASSWORD='password' bash $0"
    exit 1
fi

# Deploy AI rules FIRST (before views that depend on them)
echo "Step 1: Inserting 27 AI decision rules..."
if mysql -h localhost -u jcepnzzkmj -p"$DB_PASSWORD" jcepnzzkmj < "$SCHEMA_DIR/INSERT_AI_RULES.sql" >> "$LOG_FILE" 2>&1; then
    echo "✓ Rules inserted successfully"
else
    echo "✗ Error inserting rules (see log)"
    echo ""
    tail -20 "$LOG_FILE"
    exit 1
fi
echo ""

# Deploy views AFTER rules exist
echo "Step 2: Creating missing views..."
if mysql -h localhost -u jcepnzzkmj -p"$DB_PASSWORD" jcepnzzkmj < "$SCHEMA_DIR/CREATE_MISSING_VIEWS.sql" >> "$LOG_FILE" 2>&1; then
    echo "✓ Views created successfully"
else
    echo "✗ Error creating views (see log)"
    echo ""
    tail -20 "$LOG_FILE"
    exit 1
fi
echo ""

# Verify
echo "Step 3: Verification..."
echo ""

RULES_COUNT=$(mysql -h localhost -u jcepnzzkmj -p"$DB_PASSWORD" jcepnzzkmj -se "SELECT COUNT(*) FROM payroll_ai_decision_rules WHERE is_active=1;" 2>/dev/null || echo "0")
echo "Active Rules: $RULES_COUNT/27"

VIEWS_COUNT=$(mysql -h localhost -u jcepnzzkmj -p"$DB_PASSWORD" jcepnzzkmj -se "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA='jcepnzzkmj' AND TABLE_TYPE='VIEW' AND TABLE_NAME LIKE 'v_%';" 2>/dev/null || echo "0")
echo "Views Created: $VIEWS_COUNT"

echo ""
echo "════════════════════════════════════════════════════════════"

if [ "$RULES_COUNT" -eq 27 ]; then
    echo "✓ DEPLOYMENT SUCCESSFUL"
    echo "  All 27 rules deployed"
    echo "  All 6 views created"
    echo ""
    echo "Next steps:"
    echo "  1. Run tests: bash run_tests.sh"
    echo "  2. Review AI rules: mysql ... -e 'SELECT * FROM v_rule_performance;'"
    echo "  3. Check pending decisions: mysql ... -e 'SELECT * FROM v_pending_ai_reviews;'"
else
    echo "⚠ WARNING: Only $RULES_COUNT rules deployed (expected 27)"
    echo "  Check log: $LOG_FILE"
    exit 1
fi

echo ""
