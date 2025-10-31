-- Staff Accounts Module - Complete Schema Export
-- Generated: 2025-10-25
-- Critical tables for staff-accounts application

-- Core tables
SHOW CREATE TABLE users;
SHOW CREATE TABLE staff_account_reconciliation;
SHOW CREATE TABLE staff_payment_plans;
SHOW CREATE TABLE staff_payment_plan_installments;
SHOW CREATE TABLE staff_payment_transactions;
SHOW CREATE TABLE staff_saved_cards;
SHOW CREATE TABLE staff_members;

-- Vend integration tables
SHOW CREATE TABLE vend_customers;
SHOW CREATE TABLE vend_sales;
SHOW CREATE TABLE vend_outlets;
SHOW CREATE TABLE cis_staff_vend_map;

-- Xero integration tables  
SHOW CREATE TABLE xero_payroll_deductions;
SHOW CREATE TABLE xero_payrolls;

-- Supporting tables
SHOW CREATE TABLE staff_reminder_log;
SHOW CREATE TABLE staff_preferences;
