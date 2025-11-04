#!/bin/bash
# CIS Knowledge Base Organization Script
# Created: November 4, 2025
# Purpose: Organize all markdown documentation into centralized _kb structure

BASE_DIR="/home/master/applications/jcepnzzkmj/public_html/modules"
KB_DIR="$BASE_DIR/_kb"

echo "🗂️  CIS Knowledge Base Organization"
echo "===================================="
echo ""

# Function to copy and categorize files
organize_docs() {
    local src="$1"
    local dest="$2"
    local desc="$3"

    if [ -f "$src" ]; then
        mkdir -p "$(dirname "$dest")"
        cp "$src" "$dest"
        echo "✓ $desc"
    fi
}

# ============================================================================
# ADMIN-UI MODULE
# ============================================================================
echo "📁 Organizing Admin-UI Documentation..."

# Guides
organize_docs "$BASE_DIR/admin-ui/START_HERE.md" "$KB_DIR/admin-ui/guides/START_HERE.md" "Admin-UI Start Here"
organize_docs "$BASE_DIR/admin-ui/README.md" "$KB_DIR/admin-ui/README.md" "Admin-UI README"
organize_docs "$BASE_DIR/admin-ui/GETTING_STARTED.md" "$KB_DIR/admin-ui/guides/GETTING_STARTED.md" "Admin-UI Getting Started"
organize_docs "$BASE_DIR/admin-ui/AI_AGENT_GUIDE.md" "$KB_DIR/admin-ui/guides/AI_AGENT_GUIDE.md" "AI Agent Guide"
organize_docs "$BASE_DIR/admin-ui/AI_AGENT_CONFIG_GUIDE.md" "$KB_DIR/admin-ui/guides/AI_AGENT_CONFIG_GUIDE.md" "AI Agent Config"
organize_docs "$BASE_DIR/admin-ui/QUICK_START.md" "$KB_DIR/admin-ui/guides/QUICK_START.md" "Quick Start"
organize_docs "$BASE_DIR/admin-ui/DEPLOYMENT_GUIDE.md" "$KB_DIR/admin-ui/guides/DEPLOYMENT_GUIDE.md" "Deployment Guide"

# Themes
organize_docs "$BASE_DIR/admin-ui/THEME_SYSTEM_README.md" "$KB_DIR/admin-ui/themes/THEME_SYSTEM_README.md" "Theme System"
organize_docs "$BASE_DIR/admin-ui/THEME_BUILDER_PRO_README.md" "$KB_DIR/admin-ui/themes/THEME_BUILDER_PRO_README.md" "Theme Builder Pro"
organize_docs "$BASE_DIR/admin-ui/THEME_BUILDER_PRO_COMPLETE.md" "$KB_DIR/admin-ui/themes/THEME_BUILDER_PRO_COMPLETE.md" "Theme Builder Complete"
organize_docs "$BASE_DIR/admin-ui/THEME_BUILDER_PRO_ULTIMATE_v4.md" "$KB_DIR/admin-ui/themes/THEME_BUILDER_PRO_ULTIMATE_v4.md" "Theme Builder Ultimate"
organize_docs "$BASE_DIR/admin-ui/ULTIMATE_THEME_BUILDER.md" "$KB_DIR/admin-ui/themes/ULTIMATE_THEME_BUILDER.md" "Ultimate Theme Builder"
organize_docs "$BASE_DIR/admin-ui/THEME_SAVE_EXPLAINED.md" "$KB_DIR/admin-ui/themes/THEME_SAVE_EXPLAINED.md" "Theme Save Explained"
organize_docs "$BASE_DIR/admin-ui/CSS_VERSION_CONTROL_GUIDE.md" "$KB_DIR/admin-ui/themes/CSS_VERSION_CONTROL_GUIDE.md" "CSS Version Control"
organize_docs "$BASE_DIR/admin-ui/CSS_VERSION_CONTROL_README.md" "$KB_DIR/admin-ui/themes/CSS_VERSION_CONTROL_README.md" "CSS Version Control README"
organize_docs "$BASE_DIR/admin-ui/CSS_VERSION_CONTROL_COMPLETE.md" "$KB_DIR/admin-ui/themes/CSS_VERSION_CONTROL_COMPLETE.md" "CSS Version Control Complete"

# Testing
organize_docs "$BASE_DIR/admin-ui/TESTING_READY.md" "$KB_DIR/admin-ui/testing/TESTING_READY.md" "Testing Ready"
organize_docs "$BASE_DIR/admin-ui/QUICK_TEST_REFERENCE.md" "$KB_DIR/admin-ui/testing/QUICK_TEST_REFERENCE.md" "Quick Test Reference"
organize_docs "$BASE_DIR/admin-ui/DESIGN_STUDIO_TEST_PLAN.md" "$KB_DIR/admin-ui/testing/DESIGN_STUDIO_TEST_PLAN.md" "Design Studio Tests"

# Copy test directory
if [ -d "$BASE_DIR/admin-ui/tests" ]; then
    cp -r "$BASE_DIR/admin-ui/tests/"* "$KB_DIR/admin-ui/testing/"
    echo "✓ Admin-UI Test Suite"
fi

# API
organize_docs "$BASE_DIR/admin-ui/API_REFERENCE.md" "$KB_DIR/admin-ui/api/API_REFERENCE.md" "API Reference"

# Status Reports
organize_docs "$BASE_DIR/admin-ui/ADMIN-UI-STATUS-REPORT.md" "$KB_DIR/admin-ui/status-reports/ADMIN-UI-STATUS-REPORT.md" "Status Report"
organize_docs "$BASE_DIR/admin-ui/PROJECT_STATUS.md" "$KB_DIR/admin-ui/status-reports/PROJECT_STATUS.md" "Project Status"
organize_docs "$BASE_DIR/admin-ui/PROJECT_COMPLETE.md" "$KB_DIR/admin-ui/status-reports/PROJECT_COMPLETE.md" "Project Complete"
organize_docs "$BASE_DIR/admin-ui/FINAL_STATUS_REPORT.md" "$KB_DIR/admin-ui/status-reports/FINAL_STATUS_REPORT.md" "Final Status"
organize_docs "$BASE_DIR/admin-ui/AUTONOMOUS_BUILD_COMPLETE.md" "$KB_DIR/admin-ui/status-reports/AUTONOMOUS_BUILD_COMPLETE.md" "Autonomous Build"
organize_docs "$BASE_DIR/admin-ui/AI_AGENT_COMPLETE.md" "$KB_DIR/admin-ui/status-reports/AI_AGENT_COMPLETE.md" "AI Agent Complete"
organize_docs "$BASE_DIR/admin-ui/DASHBOARD_MIGRATION_COMPLETE.md" "$KB_DIR/admin-ui/status-reports/DASHBOARD_MIGRATION_COMPLETE.md" "Dashboard Migration"
organize_docs "$BASE_DIR/admin-ui/CSS_PATH_FIX_VERIFICATION.md" "$KB_DIR/admin-ui/status-reports/CSS_PATH_FIX_VERIFICATION.md" "CSS Path Fix"
organize_docs "$BASE_DIR/admin-ui/TEMPLATE_REFACTOR_SUMMARY.md" "$KB_DIR/admin-ui/status-reports/TEMPLATE_REFACTOR_SUMMARY.md" "Template Refactor"
organize_docs "$BASE_DIR/admin-ui/SETUP_COMPLETE.md" "$KB_DIR/admin-ui/status-reports/SETUP_COMPLETE.md" "Setup Complete"

# Other
organize_docs "$BASE_DIR/admin-ui/MASTER_INDEX.md" "$KB_DIR/admin-ui/MASTER_INDEX.md" "Master Index"
organize_docs "$BASE_DIR/admin-ui/HANDOFF_DOCUMENT.md" "$KB_DIR/admin-ui/HANDOFF_DOCUMENT.md" "Handoff Document"
organize_docs "$BASE_DIR/admin-ui/DESIGN_STUDIO_GUIDE.md" "$KB_DIR/admin-ui/guides/DESIGN_STUDIO_GUIDE.md" "Design Studio Guide"
organize_docs "$BASE_DIR/admin-ui/ASSET_CONTROL_CENTER_MASTER_PLAN.md" "$KB_DIR/admin-ui/guides/ASSET_CONTROL_CENTER_MASTER_PLAN.md" "Asset Control Center"

echo ""

# ============================================================================
# PAYROLL MODULE
# ============================================================================
echo "📁 Organizing Payroll Documentation..."

# Main docs
organize_docs "$BASE_DIR/human_resources/payroll/README.md" "$KB_DIR/payroll/README.md" "Payroll README"
organize_docs "$BASE_DIR/human_resources/payroll/START_HERE.md" "$KB_DIR/payroll/guides/START_HERE.md" "Payroll Start Here"

# Quick References
organize_docs "$BASE_DIR/human_resources/payroll/QUICK_REFERENCE.md" "$KB_DIR/payroll/guides/QUICK_REFERENCE.md" "Quick Reference"
organize_docs "$BASE_DIR/human_resources/payroll/QUICK_START.md" "$KB_DIR/payroll/guides/QUICK_START.md" "Quick Start"
organize_docs "$BASE_DIR/human_resources/payroll/QUICK_START_AGENT.md" "$KB_DIR/payroll/guides/QUICK_START_AGENT.md" "Quick Start Agent"
organize_docs "$BASE_DIR/human_resources/payroll/QUICK_REF.md" "$KB_DIR/payroll/guides/QUICK_REF.md" "Quick Ref"

# Implementation Guides
organize_docs "$BASE_DIR/human_resources/payroll/IMPLEMENTATION_GUIDE.md" "$KB_DIR/payroll/guides/IMPLEMENTATION_GUIDE.md" "Implementation Guide"
organize_docs "$BASE_DIR/human_resources/payroll/IMPLEMENTATION_CHECKLIST.md" "$KB_DIR/payroll/guides/IMPLEMENTATION_CHECKLIST.md" "Implementation Checklist"
organize_docs "$BASE_DIR/human_resources/payroll/TESTING_GUIDE.md" "$KB_DIR/payroll/guides/TESTING_GUIDE.md" "Testing Guide"
organize_docs "$BASE_DIR/human_resources/payroll/CLI_TOOLS_REFERENCE.md" "$KB_DIR/payroll/guides/CLI_TOOLS_REFERENCE.md" "CLI Tools"
organize_docs "$BASE_DIR/human_resources/payroll/ENV_SETUP_GUIDE.md" "$KB_DIR/payroll/guides/ENV_SETUP_GUIDE.md" "Environment Setup"
organize_docs "$BASE_DIR/human_resources/payroll/URL_GUIDE.md" "$KB_DIR/payroll/guides/URL_GUIDE.md" "URL Guide"
organize_docs "$BASE_DIR/human_resources/payroll/README_URLS.md" "$KB_DIR/payroll/guides/README_URLS.md" "README URLs"
organize_docs "$BASE_DIR/human_resources/payroll/PAGES_OVERVIEW.md" "$KB_DIR/payroll/guides/PAGES_OVERVIEW.md" "Pages Overview"
organize_docs "$BASE_DIR/human_resources/payroll/BOT_QUICK_START.md" "$KB_DIR/payroll/guides/BOT_QUICK_START.md" "Bot Quick Start"
organize_docs "$BASE_DIR/human_resources/payroll/BOT_DEPLOYMENT_GUIDE.md" "$KB_DIR/payroll/guides/BOT_DEPLOYMENT_GUIDE.md" "Bot Deployment"

# Objectives (1-10)
organize_docs "$BASE_DIR/human_resources/payroll/OBJECTIVE_1_COMPLETE.md" "$KB_DIR/payroll/objectives/OBJECTIVE_1_COMPLETE.md" "Objective 1"
organize_docs "$BASE_DIR/human_resources/payroll/OBJECTIVE_2_ASSESSMENT.md" "$KB_DIR/payroll/objectives/OBJECTIVE_2_ASSESSMENT.md" "Objective 2"
organize_docs "$BASE_DIR/human_resources/payroll/OBJECTIVE_3_PLAN.md" "$KB_DIR/payroll/objectives/OBJECTIVE_3_PLAN.md" "Objective 3 Plan"
organize_docs "$BASE_DIR/human_resources/payroll/OBJECTIVE_3_COMPLETE.md" "$KB_DIR/payroll/objectives/OBJECTIVE_3_COMPLETE.md" "Objective 3 Complete"
organize_docs "$BASE_DIR/human_resources/payroll/OBJECTIVE_4_PLAN.md" "$KB_DIR/payroll/objectives/OBJECTIVE_4_PLAN.md" "Objective 4 Plan"
organize_docs "$BASE_DIR/human_resources/payroll/OBJECTIVE_5_PLAN.md" "$KB_DIR/payroll/objectives/OBJECTIVE_5_PLAN.md" "Objective 5 Plan"
organize_docs "$BASE_DIR/human_resources/payroll/OBJECTIVE_6_PLAN.md" "$KB_DIR/payroll/objectives/OBJECTIVE_6_PLAN.md" "Objective 6 Plan"
organize_docs "$BASE_DIR/human_resources/payroll/OBJECTIVE_7_PLAN.md" "$KB_DIR/payroll/objectives/OBJECTIVE_7_PLAN.md" "Objective 7 Plan"
organize_docs "$BASE_DIR/human_resources/payroll/OBJECTIVE_8_PLAN.md" "$KB_DIR/payroll/objectives/OBJECTIVE_8_PLAN.md" "Objective 8 Plan"
organize_docs "$BASE_DIR/human_resources/payroll/OBJECTIVE_9_PLAN.md" "$KB_DIR/payroll/objectives/OBJECTIVE_9_PLAN.md" "Objective 9 Plan"
organize_docs "$BASE_DIR/human_resources/payroll/OBJECTIVE_10_PLAN.md" "$KB_DIR/payroll/objectives/OBJECTIVE_10_PLAN.md" "Objective 10 Plan"
organize_docs "$BASE_DIR/human_resources/payroll/OBJECTIVES_1_2_STATUS.md" "$KB_DIR/payroll/objectives/OBJECTIVES_1_2_STATUS.md" "Objectives 1-2 Status"

# Objectives - Complete docs
organize_docs "$BASE_DIR/human_resources/payroll/docs/OBJECTIVE_4_COMPLETE.md" "$KB_DIR/payroll/objectives/OBJECTIVE_4_COMPLETE.md" "Objective 4 Complete"
organize_docs "$BASE_DIR/human_resources/payroll/docs/OBJECTIVE_5_COMPLETE.md" "$KB_DIR/payroll/objectives/OBJECTIVE_5_COMPLETE.md" "Objective 5 Complete"
organize_docs "$BASE_DIR/human_resources/payroll/docs/OBJECTIVE_6_COMPLETE.md" "$KB_DIR/payroll/objectives/OBJECTIVE_6_COMPLETE.md" "Objective 6 Complete"
organize_docs "$BASE_DIR/human_resources/payroll/docs/OBJECTIVE_7_COMPLETE.md" "$KB_DIR/payroll/objectives/OBJECTIVE_7_COMPLETE.md" "Objective 7 Complete"
organize_docs "$BASE_DIR/human_resources/payroll/docs/OBJECTIVE_8_COMPLETE.md" "$KB_DIR/payroll/objectives/OBJECTIVE_8_COMPLETE.md" "Objective 8 Complete"
organize_docs "$BASE_DIR/human_resources/payroll/docs/OBJECTIVE_9_COMPLETE.md" "$KB_DIR/payroll/objectives/OBJECTIVE_9_COMPLETE.md" "Objective 9 Complete"
organize_docs "$BASE_DIR/human_resources/payroll/docs/OBJECTIVE_10_COMPLETE.md" "$KB_DIR/payroll/objectives/OBJECTIVE_10_COMPLETE.md" "Objective 10 Complete"

# Testing
organize_docs "$BASE_DIR/human_resources/payroll/TEST_RESULTS.md" "$KB_DIR/payroll/testing/TEST_RESULTS.md" "Test Results"
organize_docs "$BASE_DIR/human_resources/payroll/TEST_RESULTS_ANALYSIS.md" "$KB_DIR/payroll/testing/TEST_RESULTS_ANALYSIS.md" "Test Results Analysis"
organize_docs "$BASE_DIR/human_resources/payroll/TEST_AUDIT_REPORT.md" "$KB_DIR/payroll/testing/TEST_AUDIT_REPORT.md" "Test Audit"
organize_docs "$BASE_DIR/human_resources/payroll/TEST_SUITE_STATUS_REPORT.md" "$KB_DIR/payroll/testing/TEST_SUITE_STATUS_REPORT.md" "Test Suite Status"
organize_docs "$BASE_DIR/human_resources/payroll/TEST_SUITE_TODO.md" "$KB_DIR/payroll/testing/TEST_SUITE_TODO.md" "Test Suite TODO"
organize_docs "$BASE_DIR/human_resources/payroll/CONTROLLER_TESTS_STATUS.md" "$KB_DIR/payroll/testing/CONTROLLER_TESTS_STATUS.md" "Controller Tests Status"
organize_docs "$BASE_DIR/human_resources/payroll/CONTROLLER_TESTS_FINAL_STATUS.md" "$KB_DIR/payroll/testing/CONTROLLER_TESTS_FINAL_STATUS.md" "Controller Tests Final"
organize_docs "$BASE_DIR/human_resources/payroll/API_ENDPOINT_TEST_COMPLETE.md" "$KB_DIR/payroll/testing/API_ENDPOINT_TEST_COMPLETE.md" "API Endpoint Tests"
organize_docs "$BASE_DIR/human_resources/payroll/SECURITY_TEST_SUITE_REPORT.md" "$KB_DIR/payroll/testing/SECURITY_TEST_SUITE_REPORT.md" "Security Tests"
organize_docs "$BASE_DIR/human_resources/payroll/TESTING_IMPLEMENTATION_COMPLETE_PHASE1.md" "$KB_DIR/payroll/testing/TESTING_IMPLEMENTATION_COMPLETE_PHASE1.md" "Testing Phase 1"
organize_docs "$BASE_DIR/human_resources/payroll/PAYROLL_TESTS_100_COMPLETE.md" "$KB_DIR/payroll/testing/PAYROLL_TESTS_100_COMPLETE.md" "Tests 100% Complete"
organize_docs "$BASE_DIR/human_resources/payroll/PAYROLL_MODULE_TEST_INDEX.md" "$KB_DIR/payroll/testing/PAYROLL_MODULE_TEST_INDEX.md" "Test Index"

# Status Reports
organize_docs "$BASE_DIR/human_resources/payroll/CURRENT_STATUS_AND_NEXT_ACTIONS.md" "$KB_DIR/payroll/status-reports/CURRENT_STATUS_AND_NEXT_ACTIONS.md" "Current Status"
organize_docs "$BASE_DIR/human_resources/payroll/FEATURE_STATUS.md" "$KB_DIR/payroll/status-reports/FEATURE_STATUS.md" "Feature Status"
organize_docs "$BASE_DIR/human_resources/payroll/STATUS_COMPLETE.md" "$KB_DIR/payroll/status-reports/STATUS_COMPLETE.md" "Status Complete"
organize_docs "$BASE_DIR/human_resources/payroll/PROGRESS_REPORT.md" "$KB_DIR/payroll/status-reports/PROGRESS_REPORT.md" "Progress Report"
organize_docs "$BASE_DIR/human_resources/payroll/COMPREHENSIVE_STATUS_REPORT.md" "$KB_DIR/payroll/status-reports/COMPREHENSIVE_STATUS_REPORT.md" "Comprehensive Status"
organize_docs "$BASE_DIR/human_resources/payroll/INTEGRATION_STATUS_REPORT.md" "$KB_DIR/payroll/status-reports/INTEGRATION_STATUS_REPORT.md" "Integration Status"
organize_docs "$BASE_DIR/human_resources/payroll/CONTINUATION_STATUS_REPORT.md" "$KB_DIR/payroll/status-reports/CONTINUATION_STATUS_REPORT.md" "Continuation Status"
organize_docs "$BASE_DIR/human_resources/payroll/PAYROLL_R2_STATUS_REPORT.md" "$KB_DIR/payroll/status-reports/PAYROLL_R2_STATUS_REPORT.md" "Payroll R2 Status"
organize_docs "$BASE_DIR/human_resources/payroll/END_OF_DAY_STATUS_REPORT.md" "$KB_DIR/payroll/status-reports/END_OF_DAY_STATUS_REPORT.md" "End of Day Status"
organize_docs "$BASE_DIR/human_resources/payroll/FINAL_SESSION_STATUS.md" "$KB_DIR/payroll/status-reports/FINAL_SESSION_STATUS.md" "Final Session Status"

# Completion Reports
organize_docs "$BASE_DIR/human_resources/payroll/COMPLETION_REPORT_100_PERCENT.md" "$KB_DIR/payroll/status-reports/COMPLETION_REPORT_100_PERCENT.md" "100% Complete"
organize_docs "$BASE_DIR/human_resources/payroll/110_COMPLETE_AUDIT_REPORT.md" "$KB_DIR/payroll/status-reports/110_COMPLETE_AUDIT_REPORT.md" "110% Audit"
organize_docs "$BASE_DIR/human_resources/payroll/IMPLEMENTATION_COMPLETE.md" "$KB_DIR/payroll/status-reports/IMPLEMENTATION_COMPLETE.md" "Implementation Complete"
organize_docs "$BASE_DIR/human_resources/payroll/PAYROLL_IMPLEMENTATION_COMPLETE.md" "$KB_DIR/payroll/status-reports/PAYROLL_IMPLEMENTATION_COMPLETE.md" "Payroll Implementation"
organize_docs "$BASE_DIR/human_resources/payroll/PAY_RUN_IMPLEMENTATION_COMPLETE.md" "$KB_DIR/payroll/status-reports/PAY_RUN_IMPLEMENTATION_COMPLETE.md" "Pay Run Complete"
organize_docs "$BASE_DIR/human_resources/payroll/NAMESPACE_UNIFICATION_COMPLETE.md" "$KB_DIR/payroll/status-reports/NAMESPACE_UNIFICATION_COMPLETE.md" "Namespace Unification"
organize_docs "$BASE_DIR/human_resources/payroll/BOOTSTRAP_CONFIGURATION_COMPLETE.md" "$KB_DIR/payroll/status-reports/BOOTSTRAP_CONFIGURATION_COMPLETE.md" "Bootstrap Config"
organize_docs "$BASE_DIR/human_resources/payroll/SECURITY_LOCKDOWN_PHASE1_COMPLETE.md" "$KB_DIR/payroll/status-reports/SECURITY_LOCKDOWN_PHASE1_COMPLETE.md" "Security Lockdown"
organize_docs "$BASE_DIR/human_resources/payroll/ALL_DONE.md" "$KB_DIR/payroll/status-reports/ALL_DONE.md" "All Done"
organize_docs "$BASE_DIR/human_resources/payroll/COMPLETE_DATA_STORAGE.md" "$KB_DIR/payroll/status-reports/COMPLETE_DATA_STORAGE.md" "Complete Data Storage"
organize_docs "$BASE_DIR/human_resources/payroll/OPTION_1_COMPLETE.md" "$KB_DIR/payroll/status-reports/OPTION_1_COMPLETE.md" "Option 1 Complete"

# Phase Reports
organize_docs "$BASE_DIR/human_resources/payroll/PHASE_2_COMPLETE.md" "$KB_DIR/payroll/status-reports/PHASE_2_COMPLETE.md" "Phase 2"
organize_docs "$BASE_DIR/human_resources/payroll/PHASE_5_SUMMARY.md" "$KB_DIR/payroll/status-reports/PHASE_5_SUMMARY.md" "Phase 5 Summary"
organize_docs "$BASE_DIR/human_resources/payroll/PHASE_5_COMPLETION_SUMMARY.md" "$KB_DIR/payroll/status-reports/PHASE_5_COMPLETION_SUMMARY.md" "Phase 5 Completion"
organize_docs "$BASE_DIR/human_resources/payroll/PHASE_5_FINAL_EXECUTION_REPORT.md" "$KB_DIR/payroll/status-reports/PHASE_5_FINAL_EXECUTION_REPORT.md" "Phase 5 Final"
organize_docs "$BASE_DIR/human_resources/payroll/PHASE_D_ENDPOINT_TESTING_RESULTS.md" "$KB_DIR/payroll/status-reports/PHASE_D_ENDPOINT_TESTING_RESULTS.md" "Phase D Testing"

# Schema Documentation
organize_docs "$BASE_DIR/human_resources/payroll/schema/QUICK_REFERENCE.md" "$KB_DIR/payroll/schema/QUICK_REFERENCE.md" "Schema Quick Ref"
organize_docs "$BASE_DIR/human_resources/payroll/schema/SCHEMA_RENAME_SUMMARY.md" "$KB_DIR/payroll/schema/SCHEMA_RENAME_SUMMARY.md" "Schema Rename"
organize_docs "$BASE_DIR/human_resources/payroll/schema/COMPLETE_UPDATE_SUMMARY.md" "$KB_DIR/payroll/schema/COMPLETE_UPDATE_SUMMARY.md" "Schema Updates"
organize_docs "$BASE_DIR/human_resources/payroll/schema/DEPLOYMENT_CHECKLIST.md" "$KB_DIR/payroll/schema/DEPLOYMENT_CHECKLIST.md" "Schema Deployment"

# Additional Docs
organize_docs "$BASE_DIR/human_resources/payroll/docs/PAYROLL_HARDENING_MASTER_PLAN.md" "$KB_DIR/payroll/guides/PAYROLL_HARDENING_MASTER_PLAN.md" "Hardening Master Plan"
organize_docs "$BASE_DIR/human_resources/payroll/docs/DEPUTY_ALGORITHM_DOCUMENTATION.md" "$KB_DIR/payroll/guides/DEPUTY_ALGORITHM_DOCUMENTATION.md" "Deputy Algorithm"
organize_docs "$BASE_DIR/human_resources/payroll/docs/PERMISSIONS.md" "$KB_DIR/payroll/guides/PERMISSIONS.md" "Permissions"
organize_docs "$BASE_DIR/human_resources/payroll/docs/INTEGRATION_CHECKLIST.md" "$KB_DIR/payroll/guides/INTEGRATION_CHECKLIST.md" "Integration Checklist"
organize_docs "$BASE_DIR/human_resources/payroll/docs/FEATURE_STATUS_REPORT.md" "$KB_DIR/payroll/status-reports/FEATURE_STATUS_REPORT.md" "Feature Status Report"
organize_docs "$BASE_DIR/human_resources/payroll/docs/DEPLOYMENT_STATUS.md" "$KB_DIR/payroll/status-reports/DEPLOYMENT_STATUS.md" "Deployment Status"
organize_docs "$BASE_DIR/human_resources/payroll/docs/DASHBOARD_IMPLEMENTATION_PLAN.md" "$KB_DIR/payroll/guides/DASHBOARD_IMPLEMENTATION_PLAN.md" "Dashboard Plan"
organize_docs "$BASE_DIR/human_resources/payroll/docs/WAGE_DISCREPANCY_SETUP.md" "$KB_DIR/payroll/guides/WAGE_DISCREPANCY_SETUP.md" "Wage Discrepancy"

# Other Important Docs
organize_docs "$BASE_DIR/human_resources/payroll/MASTER_INDEX.md" "$KB_DIR/payroll/MASTER_INDEX.md" "Master Index"
organize_docs "$BASE_DIR/human_resources/payroll/COMPREHENSIVE_AUDIT_REPORT.md" "$KB_DIR/payroll/status-reports/COMPREHENSIVE_AUDIT_REPORT.md" "Comprehensive Audit"
organize_docs "$BASE_DIR/human_resources/payroll/FINAL_VERIFICATION_REPORT.md" "$KB_DIR/payroll/status-reports/FINAL_VERIFICATION_REPORT.md" "Final Verification"
organize_docs "$BASE_DIR/human_resources/payroll/EXECUTIVE_SUMMARY.md" "$KB_DIR/payroll/EXECUTIVE_SUMMARY.md" "Executive Summary"
organize_docs "$BASE_DIR/human_resources/payroll/EXECUTIVE_BRIEFING_RESULTS.md" "$KB_DIR/payroll/EXECUTIVE_BRIEFING_RESULTS.md" "Executive Briefing"
organize_docs "$BASE_DIR/human_resources/payroll/AI_AGENT_HANDOFF.md" "$KB_DIR/payroll/AI_AGENT_HANDOFF.md" "AI Agent Handoff"
organize_docs "$BASE_DIR/human_resources/payroll/AI_AGENT_COMPLETION_BRIEFING.md" "$KB_DIR/payroll/AI_AGENT_COMPLETION_BRIEFING.md" "AI Agent Completion"
organize_docs "$BASE_DIR/human_resources/payroll/AUTONOMOUS_BOT_COMPLETE.md" "$KB_DIR/payroll/status-reports/AUTONOMOUS_BOT_COMPLETE.md" "Autonomous Bot"

# Session Reports
organize_docs "$BASE_DIR/human_resources/payroll/SESSION_SUMMARY.md" "$KB_DIR/payroll/status-reports/SESSION_SUMMARY.md" "Session Summary"
organize_docs "$BASE_DIR/human_resources/payroll/SESSION_FINAL_DELIVERY_SUMMARY.md" "$KB_DIR/payroll/status-reports/SESSION_FINAL_DELIVERY_SUMMARY.md" "Session Final"
organize_docs "$BASE_DIR/human_resources/payroll/NEXT_SESSION_QUICK_START.md" "$KB_DIR/payroll/guides/NEXT_SESSION_QUICK_START.md" "Next Session"
organize_docs "$BASE_DIR/human_resources/payroll/VISUAL_SUMMARY.md" "$KB_DIR/payroll/VISUAL_SUMMARY.md" "Visual Summary"
organize_docs "$BASE_DIR/human_resources/payroll/FULL_SEND_SUMMARY.md" "$KB_DIR/payroll/status-reports/FULL_SEND_SUMMARY.md" "Full Send"
organize_docs "$BASE_DIR/human_resources/payroll/CHANGES_SUMMARY.md" "$KB_DIR/payroll/status-reports/CHANGES_SUMMARY.md" "Changes Summary"
organize_docs "$BASE_DIR/human_resources/payroll/IMPLEMENTATION_SUMMARY.md" "$KB_DIR/payroll/status-reports/IMPLEMENTATION_SUMMARY.md" "Implementation Summary"

# Deployment & Commit
organize_docs "$BASE_DIR/human_resources/payroll/DEPLOYMENT_CHECKLIST.md" "$KB_DIR/payroll/guides/DEPLOYMENT_CHECKLIST.md" "Deployment Checklist"
organize_docs "$BASE_DIR/human_resources/payroll/COMMIT_READY.md" "$KB_DIR/payroll/status-reports/COMMIT_READY.md" "Commit Ready"
organize_docs "$BASE_DIR/human_resources/payroll/COMMIT_NOW.md" "$KB_DIR/payroll/status-reports/COMMIT_NOW.md" "Commit Now"
organize_docs "$BASE_DIR/human_resources/payroll/COMMIT_ISSUE.md" "$KB_DIR/payroll/status-reports/COMMIT_ISSUE.md" "Commit Issue"
organize_docs "$BASE_DIR/human_resources/payroll/SUBMISSION_READY.md" "$KB_DIR/payroll/status-reports/SUBMISSION_READY.md" "Submission Ready"
organize_docs "$BASE_DIR/human_resources/payroll/GITHUB_PR_SUBMISSION.md" "$KB_DIR/payroll/status-reports/GITHUB_PR_SUBMISSION.md" "GitHub PR"
organize_docs "$BASE_DIR/human_resources/payroll/PR_DESCRIPTION.md" "$KB_DIR/payroll/status-reports/PR_DESCRIPTION.md" "PR Description"

# Miscellaneous
organize_docs "$BASE_DIR/human_resources/payroll/AUTHENTICATION_CONTROL.md" "$KB_DIR/payroll/guides/AUTHENTICATION_CONTROL.md" "Authentication Control"
organize_docs "$BASE_DIR/human_resources/payroll/AUTH_STATUS_SUMMARY.md" "$KB_DIR/payroll/status-reports/AUTH_STATUS_SUMMARY.md" "Auth Status"
organize_docs "$BASE_DIR/human_resources/payroll/README_AUTHENTICATION.md" "$KB_DIR/payroll/guides/README_AUTHENTICATION.md" "README Authentication"
organize_docs "$BASE_DIR/human_resources/payroll/TEMPLATE_REFACTORING_COMPARISON.md" "$KB_DIR/payroll/guides/TEMPLATE_REFACTORING_COMPARISON.md" "Template Refactoring"
organize_docs "$BASE_DIR/human_resources/payroll/FRONTEND_TEMPLATE_INTEGRATION_AUDIT.md" "$KB_DIR/payroll/status-reports/FRONTEND_TEMPLATE_INTEGRATION_AUDIT.md" "Frontend Template Audit"
organize_docs "$BASE_DIR/human_resources/payroll/MODULARIZATION_PLAN.md" "$KB_DIR/payroll/guides/MODULARIZATION_PLAN.md" "Modularization Plan"
organize_docs "$BASE_DIR/human_resources/payroll/COMPLETION_ROADMAP.md" "$KB_DIR/payroll/COMPLETION_ROADMAP.md" "Completion Roadmap"
organize_docs "$BASE_DIR/human_resources/payroll/READY_TO_TEST.md" "$KB_DIR/payroll/testing/READY_TO_TEST.md" "Ready to Test"
organize_docs "$BASE_DIR/human_resources/payroll/LIVE_EXECUTION_TRACKER.md" "$KB_DIR/payroll/status-reports/LIVE_EXECUTION_TRACKER.md" "Live Execution Tracker"
organize_docs "$BASE_DIR/human_resources/payroll/COMPLETE_FILEBYFILE_SCAN_RESULTS.md" "$KB_DIR/payroll/status-reports/COMPLETE_FILEBYFILE_SCAN_RESULTS.md" "File-by-File Scan"

echo ""

# ============================================================================
# BASE MODULE
# ============================================================================
echo "📁 Organizing Base Module Documentation..."

organize_docs "$BASE_DIR/base/README.md" "$KB_DIR/base/README.md" "Base README"
organize_docs "$BASE_DIR/base/BASEAPI_USAGE_GUIDE.md" "$KB_DIR/base/BASEAPI_USAGE_GUIDE.md" "BaseAPI Usage"
organize_docs "$BASE_DIR/base/BASEAPI_COMPLETE_SUMMARY.md" "$KB_DIR/base/BASEAPI_COMPLETE_SUMMARY.md" "BaseAPI Complete"
organize_docs "$BASE_DIR/base/AI_INTEGRATION_GUIDE.md" "$KB_DIR/base/AI_INTEGRATION_GUIDE.md" "AI Integration"
organize_docs "$BASE_DIR/base/USAGE_EXAMPLES.md" "$KB_DIR/base/USAGE_EXAMPLES.md" "Usage Examples"
organize_docs "$BASE_DIR/base/QUICK_REFERENCE.md" "$KB_DIR/base/QUICK_REFERENCE.md" "Quick Reference"
organize_docs "$BASE_DIR/base/SERVICES_LIBRARY_COMPLETE.md" "$KB_DIR/base/SERVICES_LIBRARY_COMPLETE.md" "Services Library"
organize_docs "$BASE_DIR/base/LOGGER_INTEGRATION_STATUS.md" "$KB_DIR/base/LOGGER_INTEGRATION_STATUS.md" "Logger Integration"
organize_docs "$BASE_DIR/base/IMPLEMENTATION_STATUS.md" "$KB_DIR/base/IMPLEMENTATION_STATUS.md" "Implementation Status"
organize_docs "$BASE_DIR/base/PROGRESS_TRACKER.md" "$KB_DIR/base/PROGRESS_TRACKER.md" "Progress Tracker"
organize_docs "$BASE_DIR/base/COMPLETION_CHECKLIST.md" "$KB_DIR/base/COMPLETION_CHECKLIST.md" "Completion Checklist"
organize_docs "$BASE_DIR/base/PHASE_2_COMPLETE_SUMMARY.md" "$KB_DIR/base/PHASE_2_COMPLETE_SUMMARY.md" "Phase 2 Complete"
organize_docs "$BASE_DIR/base/PHASE_2_COMPLETION_REPORT.md" "$KB_DIR/base/PHASE_2_COMPLETION_REPORT.md" "Phase 2 Report"
organize_docs "$BASE_DIR/base/REBUILD_MASTER_PLAN.md" "$KB_DIR/base/REBUILD_MASTER_PLAN.md" "Rebuild Master Plan"

# Templates
organize_docs "$BASE_DIR/base/TEMPLATE_README.md" "$KB_DIR/base/templates/TEMPLATE_README.md" "Template README"
organize_docs "$BASE_DIR/base/BASE_TEMPLATE_VISUAL_GUIDE.md" "$KB_DIR/base/templates/BASE_TEMPLATE_VISUAL_GUIDE.md" "Template Visual Guide"
organize_docs "$BASE_DIR/base/MODERN_CIS_TEMPLATE_GUIDE.md" "$KB_DIR/base/templates/MODERN_CIS_TEMPLATE_GUIDE.md" "Modern CIS Template"
organize_docs "$BASE_DIR/base/_templates/themes/cis-classic/README.md" "$KB_DIR/base/templates/CIS_CLASSIC_THEME.md" "CIS Classic Theme"

echo ""

# ============================================================================
# FLAGGED PRODUCTS MODULE
# ============================================================================
echo "📁 Organizing Flagged Products Documentation..."

organize_docs "$BASE_DIR/flagged_products/README.md" "$KB_DIR/flagged-products/README.md" "Flagged Products README"
organize_docs "$BASE_DIR/flagged_products/QUICK_START.md" "$KB_DIR/flagged-products/QUICK_START.md" "Quick Start"
organize_docs "$BASE_DIR/flagged_products/README_ACCESS.md" "$KB_DIR/flagged-products/README_ACCESS.md" "Access Documentation"
organize_docs "$BASE_DIR/flagged_products/DEPLOYMENT.md" "$KB_DIR/flagged-products/DEPLOYMENT.md" "Deployment"
organize_docs "$BASE_DIR/flagged_products/DAILY_GENERATION.md" "$KB_DIR/flagged-products/DAILY_GENERATION.md" "Daily Generation"
organize_docs "$BASE_DIR/flagged_products/COMPLETE.md" "$KB_DIR/flagged-products/COMPLETE.md" "Complete Status"
organize_docs "$BASE_DIR/flagged_products/DEVELOPMENT_COMPLETE.md" "$KB_DIR/flagged-products/DEVELOPMENT_COMPLETE.md" "Development Complete"
organize_docs "$BASE_DIR/flagged_products/MONITORING_FEATURES_COMPLETE.md" "$KB_DIR/flagged-products/MONITORING_FEATURES_COMPLETE.md" "Monitoring Features"
organize_docs "$BASE_DIR/flagged_products/CRON_AUDIT_COMPLETE.md" "$KB_DIR/flagged-products/CRON_AUDIT_COMPLETE.md" "Cron Audit"
organize_docs "$BASE_DIR/flagged_products/docs/HISTORIC_MIGRATION_REPORT.md" "$KB_DIR/flagged-products/HISTORIC_MIGRATION_REPORT.md" "Historic Migration"

echo ""

# ============================================================================
# ARCHITECTURE & PROJECT MANAGEMENT
# ============================================================================
echo "📁 Organizing Architecture & Project Management..."

organize_docs "$BASE_DIR/ARCHITECTURE_REFACTORING_PROPOSAL.md" "$KB_DIR/architecture/ARCHITECTURE_REFACTORING_PROPOSAL.md" "Architecture Refactoring"
organize_docs "$BASE_DIR/FINANCIAL_MODULES_PROFESSIONAL_REBUILD_PLAN.md" "$KB_DIR/architecture/FINANCIAL_MODULES_PROFESSIONAL_REBUILD_PLAN.md" "Financial Rebuild Plan"
organize_docs "$BASE_DIR/COMPREHENSIVE_REALITY_CHECK_AUDIT.md" "$KB_DIR/architecture/COMPREHENSIVE_REALITY_CHECK_AUDIT.md" "Reality Check Audit"
organize_docs "$BASE_DIR/EXECUTIVE_SUMMARY_FINANCIAL_REBUILD.md" "$KB_DIR/architecture/EXECUTIVE_SUMMARY_FINANCIAL_REBUILD.md" "Executive Summary"

organize_docs "$BASE_DIR/AI_AGENT_HANDOFF_PACKAGE.md" "$KB_DIR/project-management/AI_AGENT_HANDOFF_PACKAGE.md" "AI Agent Handoff"
organize_docs "$BASE_DIR/PHASE_1_URGENT_STAFF_PAYMENT_VERIFICATION.md" "$KB_DIR/project-management/PHASE_1_URGENT_STAFF_PAYMENT_VERIFICATION.md" "Phase 1 Urgent"
organize_docs "$BASE_DIR/SETUP_COMPLETE.md" "$KB_DIR/project-management/SETUP_COMPLETE.md" "Setup Complete"

# Other project files
organize_docs "$BASE_DIR/AUTO_PUSH_README.md" "$KB_DIR/project-management/AUTO_PUSH_README.md" "Auto Push README"
organize_docs "$BASE_DIR/TEST_AUTO_PUSH.md" "$KB_DIR/project-management/TEST_AUTO_PUSH.md" "Test Auto Push"
organize_docs "$BASE_DIR/VISUAL_BROWSER_README.md" "$KB_DIR/project-management/VISUAL_BROWSER_README.md" "Visual Browser"

echo ""
echo "✅ Knowledge Base Organization Complete!"
echo ""
echo "📊 Summary:"
find "$KB_DIR" -type f -name "*.md" | wc -l | xargs echo "   Total MD files organized:"
echo ""
echo "📁 View organized documentation at:"
echo "   $KB_DIR"
echo ""
echo "📖 Start with the master index:"
echo "   $KB_DIR/README.md"
