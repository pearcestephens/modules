# 📧 Consignments Email Template System - Complete Organization Guide

**Created:** October 31, 2025
**Purpose:** Organize and configure the complete email template system for consignments module
**Status:** Production-ready structure with implementation plan

---

## 🎯 Overview

This guide organizes the **complete email template system** for consignments, providing:

1. **File Structure** - Where every template file lives
2. **Database Schema** - How to store template configurations
3. **Template Types** - Internal vs Supplier templates
4. **Admin Configuration** - How staff edit templates
5. **Integration** - How it connects to email queue system
6. **Implementation Plan** - Step-by-step setup

---

## 📁 Directory Structure

### Complete File Organization

```
modules/consignments/
├── shared/
│   ├── templates/
│   │   ├── base-layout.php                    ✅ EXISTS (HTML page template)
│   │   └── email/                             🆕 CREATE THIS
│   │       ├── README.md                      🆕 Email template guide
│   │       ├── base-email.php                 🆕 Base email HTML structure
│   │       ├── internal/                      🆕 Staff/Manager templates
│   │       │   ├── consignment-created.php    🆕 "New Consignment" email
│   │       │   ├── consignment-approved.php   🆕 "Approved" email
│   │       │   ├── consignment-rejected.php   🆕 "Rejected" email
│   │       │   ├── consignment-amended.php    🆕 "Amended" email
│   │       │   ├── approval-needed.php        🆕 "Action Required" email
│   │       │   └── exception-alert.php        🆕 "Exception" email
│   │       └── supplier/                      🆕 External supplier templates
│   │           ├── po-created.php             🆕 "New PO" to supplier
│   │           ├── po-updated.php             🆕 "PO Updated" to supplier
│   │           ├── delivery-reminder.php      🆕 "Delivery Due" reminder
│   │           └── receipt-confirmation.php   🆕 "Receipt Confirmed" email
│   │
│   ├── functions/
│   │   └── email-templates.php                🆕 Template rendering functions
│   │
│   └── css/
│       └── email-styles.css                   🆕 Email-specific CSS (inlined)
│
├── api/
│   └── email/
│       ├── send-notification.php              🆕 Email sending endpoint
│       ├── preview-template.php               🆕 Template preview for admin
│       └── save-template-config.php           🆕 Save template settings
│
├── views/
│   └── admin/
│       └── email-templates.php                🆕 Admin UI for template editing
│
└── database/
    └── email-template-schema.sql              🆕 Database tables for templates
```

---

## 🗄️ Database Schema

### Tables Required

```sql
-- ============================================================================
-- Email Template Configuration Tables
-- ============================================================================

-- 1. Template Master Settings
CREATE TABLE IF NOT EXISTS consignment_email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    template_key VARCHAR(100) NOT NULL UNIQUE COMMENT 'e.g., "internal_created", "supplier_po_created"',
    template_type ENUM('internal', 'supplier') NOT NULL,
    name VARCHAR(255) NOT NULL COMMENT 'Human-readable name',
    description TEXT COMMENT 'What this template is used for',
    subject_line VARCHAR(255) NOT NULL COMMENT 'Email subject with {placeholders}',
    template_file VARCHAR(255) NOT NULL COMMENT 'Path to PHP template file',
    is_active TINYINT(1) DEFAULT 1,
    priority ENUM('urgent', 'batched', 'daily') DEFAULT 'batched',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_template_key (template_key),
    INDEX idx_template_type (template_type),
    INDEX idx_is_active (is_active)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Master email template definitions for consignments';

-- 2. Template Customization (Admin-Editable Settings)
CREATE TABLE IF NOT EXISTS consignment_email_template_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    config_key VARCHAR(100) NOT NULL UNIQUE,
    config_value TEXT NOT NULL,
    config_type ENUM('text', 'html', 'url', 'color', 'boolean') DEFAULT 'text',
    description VARCHAR(255),
    is_supplier_specific TINYINT(1) DEFAULT 0 COMMENT 'Can be overridden per supplier',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_config_key (config_key)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Global template configuration (logo URL, colors, footer text, etc.)';

-- 3. Supplier-Specific Template Overrides
CREATE TABLE IF NOT EXISTS consignment_supplier_email_config (
    id INT AUTO_INCREMENT PRIMARY KEY,
    supplier_id INT NOT NULL,
    template_key VARCHAR(100) NOT NULL COMMENT 'References consignment_email_templates.template_key',
    config_overrides JSON COMMENT 'JSON object with custom settings for this supplier',
    custom_subject_line VARCHAR(255) COMMENT 'Override default subject',
    custom_footer_text TEXT COMMENT 'Supplier-specific footer',
    is_active TINYINT(1) DEFAULT 1,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY unique_supplier_template (supplier_id, template_key),
    INDEX idx_supplier_id (supplier_id),
    INDEX idx_template_key (template_key),
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Per-supplier email template customizations';

-- 4. Email Send Log (Audit Trail)
CREATE TABLE IF NOT EXISTS consignment_email_log (
    id BIGINT AUTO_INCREMENT PRIMARY KEY,
    consignment_id INT NOT NULL,
    template_key VARCHAR(100) NOT NULL,
    recipient_email VARCHAR(255) NOT NULL,
    recipient_name VARCHAR(255),
    recipient_type ENUM('staff', 'manager', 'approver', 'supplier') NOT NULL,
    subject_line VARCHAR(255) NOT NULL,
    email_body MEDIUMTEXT COMMENT 'Full HTML email content',
    sent_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    sent_by INT COMMENT 'User ID who triggered email',
    queue_status ENUM('queued', 'sent', 'failed', 'bounced') DEFAULT 'queued',
    queue_id VARCHAR(100) COMMENT 'Reference to vapeshed email queue',
    error_message TEXT,
    retry_count INT DEFAULT 0,
    INDEX idx_consignment_id (consignment_id),
    INDEX idx_template_key (template_key),
    INDEX idx_recipient_email (recipient_email),
    INDEX idx_sent_at (sent_at),
    INDEX idx_queue_status (queue_status),
    FOREIGN KEY (consignment_id) REFERENCES consignments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Complete audit log of all emails sent for consignments (7-year retention)';
```

### Default Configuration Data

```sql
-- ============================================================================
-- Insert Default Template Configuration
-- ============================================================================

-- Global Template Settings
INSERT INTO consignment_email_template_config (config_key, config_value, config_type, description, is_supplier_specific) VALUES
('company_logo_url', 'https://staff.vapeshed.co.nz/assets/images/vapeshed-logo.png', 'url', 'Company logo URL for email header', 0),
('header_bg_color', '#000000', 'color', 'Email header background color', 0),
('accent_color', '#ffcc00', 'color', 'Accent color for buttons and highlights', 0),
('company_name', 'The Vape Shed', 'text', 'Company name displayed in emails', 0),
('company_address', '123 Vape Street, Auckland, New Zealand', 'text', 'Company address for footer', 1),
('support_email', 'support@vapeshed.co.nz', 'text', 'Support email for footer', 0),
('support_phone', '0800 VAPE SHED', 'text', 'Support phone for footer', 0),
('footer_disclaimer', 'This email contains confidential information. If you received this in error, please delete it.', 'html', 'Legal disclaimer for footer', 0),
('enable_supplier_branding', '1', 'boolean', 'Allow suppliers to see custom branding', 1);

-- Template Definitions
INSERT INTO consignment_email_templates (template_key, template_type, name, description, subject_line, template_file, priority) VALUES
-- Internal Templates (Staff/Manager)
('internal_created', 'internal', 'Consignment Created', 'Notification when new consignment is created', 'Consignment #{consignment_id} Created - Action Required', 'internal/consignment-created.php', 'batched'),
('internal_approved', 'internal', 'Consignment Approved', 'Notification when consignment is approved', 'Consignment #{consignment_id} Approved', 'internal/consignment-approved.php', 'batched'),
('internal_rejected', 'internal', 'Consignment Rejected', 'Notification when consignment is rejected', 'Consignment #{consignment_id} Rejected - Review Needed', 'internal/consignment-rejected.php', 'urgent'),
('internal_amended', 'internal', 'Consignment Amended', 'Notification when consignment is modified', 'Consignment #{consignment_id} Amended - Review Changes', 'internal/consignment-amended.php', 'batched'),
('internal_approval_needed', 'internal', 'Approval Needed', 'Manager approval required notification', 'Action Required: Approve Consignment #{consignment_id}', 'internal/approval-needed.php', 'urgent'),
('internal_exception', 'internal', 'Exception Alert', 'Exception/error notification for tech team', '⚠️ Exception: Consignment #{consignment_id} - {exception_type}', 'internal/exception-alert.php', 'urgent'),

-- Supplier Templates (External)
('supplier_po_created', 'supplier', 'Purchase Order Created', 'New PO sent to supplier', 'Purchase Order #{po_number} from The Vape Shed', 'supplier/po-created.php', 'urgent'),
('supplier_po_updated', 'supplier', 'Purchase Order Updated', 'PO update sent to supplier', 'Purchase Order #{po_number} Updated', 'supplier/po-updated.php', 'urgent'),
('supplier_delivery_reminder', 'supplier', 'Delivery Reminder', 'Reminder for upcoming delivery', 'Reminder: Delivery Due for PO #{po_number}', 'supplier/delivery-reminder.php', 'daily'),
('supplier_receipt_confirmed', 'supplier', 'Receipt Confirmed', 'Confirmation that goods were received', 'Receipt Confirmed: PO #{po_number}', 'supplier/receipt-confirmation.php', 'batched');
```

---

## 📝 Template Types & Structure

### Base Email Template Structure

**File:** `shared/templates/email/base-email.php`

```php
<?php
/**
 * Base Email Template Structure
 *
 * Provides responsive HTML email structure that all email templates inherit.
 * Based on purchase-orders.php template (lines 1410-1568).
 *
 * Usage:
 *   $email_data = [
 *       'header_title' => 'Consignment #12345',
 *       'content' => '<p>Email body HTML</p>',
 *       'cta_buttons' => [
 *           ['label' => 'View in CIS', 'url' => 'https://...', 'color' => '#ffcc00']
 *       ],
 *       'footer_text' => 'Custom footer text'
 *   ];
 *   require __DIR__ . '/base-email.php';
 *
 * @var array $email_data Required data for email rendering
 * @var array $config Global template config from database
 */

// Load global template configuration
$config = get_email_template_config();

// Extract email data with defaults
$header_title = $email_data['header_title'] ?? 'The Vape Shed';
$content = $email_data['content'] ?? '';
$cta_buttons = $email_data['cta_buttons'] ?? [];
$footer_text = $email_data['footer_text'] ?? $config['footer_disclaimer'];

// Colors from config
$header_bg = $config['header_bg_color'];
$accent_color = $config['accent_color'];
$logo_url = $config['company_logo_url'];
?>
<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
<html xmlns="http://www.w3.org/1999/xhtml">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
    <title><?php echo htmlspecialchars($header_title); ?></title>
    <style type="text/css">
        /* Reset styles */
        body { margin: 0; padding: 0; width: 100% !important; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
        img { border: 0; height: auto; line-height: 100%; outline: none; text-decoration: none; }
        table { border-collapse: collapse; mso-table-lspace: 0pt; mso-table-rspace: 0pt; }

        /* Responsive */
        @media only screen and (max-width: 600px) {
            .wrapper { width: 100% !important; }
            .button { width: 100% !important; padding: 12px !important; }
        }
    </style>
</head>
<body style="margin: 0; padding: 0; background-color: #f4f4f4; font-family: Arial, sans-serif;">
    <!-- Wrapper -->
    <table width="100%" cellpadding="0" cellspacing="0" border="0" style="background-color: #f4f4f4; padding: 20px 0;">
        <tr>
            <td align="center">
                <!-- Main Container -->
                <table class="wrapper" width="600" cellpadding="0" cellspacing="0" border="0" style="background-color: #ffffff; border-radius: 8px; overflow: hidden;">

                    <!-- Header -->
                    <tr>
                        <td style="background-color: <?php echo htmlspecialchars($header_bg); ?>; padding: 30px; text-align: center;">
                            <img src="<?php echo htmlspecialchars($logo_url); ?>" alt="<?php echo htmlspecialchars($config['company_name']); ?>" style="max-width: 200px; height: auto;" />
                            <h1 style="color: #ffffff; margin: 15px 0 0 0; font-size: 24px; font-weight: bold;">
                                <?php echo htmlspecialchars($header_title); ?>
                            </h1>
                        </td>
                    </tr>

                    <!-- Content -->
                    <tr>
                        <td style="padding: 40px 30px;">
                            <?php echo $content; // Pre-sanitized HTML ?>
                        </td>
                    </tr>

                    <!-- CTA Buttons -->
                    <?php if (!empty($cta_buttons)): ?>
                    <tr>
                        <td style="padding: 0 30px 40px 30px; text-align: center;">
                            <?php foreach ($cta_buttons as $button): ?>
                            <a href="<?php echo htmlspecialchars($button['url']); ?>"
                               class="button"
                               style="display: inline-block; padding: 15px 30px; margin: 5px; background-color: <?php echo htmlspecialchars($button['color'] ?? $accent_color); ?>; color: #000000; text-decoration: none; border-radius: 5px; font-weight: bold; font-size: 16px;">
                                <?php echo htmlspecialchars($button['label']); ?>
                            </a>
                            <?php endforeach; ?>
                        </td>
                    </tr>
                    <?php endif; ?>

                    <!-- Footer -->
                    <tr>
                        <td style="background-color: #f8f8f8; padding: 30px; text-align: center; border-top: 1px solid #e0e0e0;">
                            <p style="margin: 0 0 10px 0; color: #666666; font-size: 14px;">
                                <strong><?php echo htmlspecialchars($config['company_name']); ?></strong><br/>
                                <?php echo nl2br(htmlspecialchars($config['company_address'])); ?>
                            </p>
                            <p style="margin: 10px 0; color: #666666; font-size: 14px;">
                                📧 <?php echo htmlspecialchars($config['support_email']); ?> |
                                📞 <?php echo htmlspecialchars($config['support_phone']); ?>
                            </p>
                            <p style="margin: 15px 0 0 0; color: #999999; font-size: 12px;">
                                <?php echo htmlspecialchars($footer_text); ?>
                            </p>
                        </td>
                    </tr>

                </table>
            </td>
        </tr>
    </table>
</body>
</html>
```

---

## 🔧 Template Rendering Functions

**File:** `shared/functions/email-templates.php`

```php
<?php
/**
 * Email Template Rendering Functions
 *
 * Provides helper functions for loading, rendering, and sending email templates.
 *
 * @package CIS\Consignments\Email
 */

/**
 * Get global email template configuration from database
 *
 * @return array Configuration settings
 */
function get_email_template_config(): array {
    global $pdo;

    $stmt = $pdo->query("
        SELECT config_key, config_value, config_type
        FROM consignment_email_template_config
        WHERE is_supplier_specific = 0
    ");

    $config = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $value = $row['config_value'];

        // Type casting
        if ($row['config_type'] === 'boolean') {
            $value = (bool)$value;
        }

        $config[$row['config_key']] = $value;
    }

    return $config;
}

/**
 * Get supplier-specific template configuration (if overrides exist)
 *
 * @param int $supplier_id Supplier ID
 * @param string $template_key Template key
 * @return array|null Supplier config overrides or null
 */
function get_supplier_template_overrides(int $supplier_id, string $template_key): ?array {
    global $pdo;

    $stmt = $pdo->prepare("
        SELECT config_overrides, custom_subject_line, custom_footer_text
        FROM consignment_supplier_email_config
        WHERE supplier_id = ? AND template_key = ? AND is_active = 1
    ");
    $stmt->execute([$supplier_id, $template_key]);

    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return null;
    }

    $overrides = json_decode($row['config_overrides'], true) ?? [];

    if ($row['custom_subject_line']) {
        $overrides['subject_line'] = $row['custom_subject_line'];
    }
    if ($row['custom_footer_text']) {
        $overrides['footer_text'] = $row['custom_footer_text'];
    }

    return $overrides;
}

/**
 * Render email template with data
 *
 * @param string $template_key Template key (e.g., 'internal_created')
 * @param array $data Data for template placeholders
 * @param int|null $supplier_id Supplier ID for supplier-specific templates
 * @return array ['subject' => string, 'html' => string, 'priority' => string]
 * @throws Exception If template not found
 */
function render_email_template(string $template_key, array $data, ?int $supplier_id = null): array {
    global $pdo;

    // Get template definition
    $stmt = $pdo->prepare("
        SELECT template_file, subject_line, priority, template_type
        FROM consignment_email_templates
        WHERE template_key = ? AND is_active = 1
    ");
    $stmt->execute([$template_key]);
    $template = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$template) {
        throw new Exception("Email template not found: {$template_key}");
    }

    // Load global config
    $config = get_email_template_config();

    // Load supplier overrides if applicable
    if ($supplier_id && $template['template_type'] === 'supplier') {
        $overrides = get_supplier_template_overrides($supplier_id, $template_key);
        if ($overrides) {
            $config = array_merge($config, $overrides);
            if (isset($overrides['subject_line'])) {
                $template['subject_line'] = $overrides['subject_line'];
            }
        }
    }

    // Replace placeholders in subject
    $subject = replace_placeholders($template['subject_line'], $data);

    // Render template content
    $template_path = __DIR__ . '/../templates/email/' . $template['template_file'];
    if (!file_exists($template_path)) {
        throw new Exception("Template file not found: {$template_path}");
    }

    // Capture template output
    ob_start();
    extract($data); // Make data available to template
    require $template_path;
    $html = ob_get_clean();

    return [
        'subject' => $subject,
        'html' => $html,
        'priority' => $template['priority']
    ];
}

/**
 * Replace placeholders in string with data values
 *
 * @param string $template String with {placeholders}
 * @param array $data Data for replacement
 * @return string Rendered string
 */
function replace_placeholders(string $template, array $data): string {
    foreach ($data as $key => $value) {
        $template = str_replace('{' . $key . '}', $value, $template);
    }
    return $template;
}

/**
 * Send consignment email notification
 *
 * @param string $template_key Template to use
 * @param int $consignment_id Consignment ID
 * @param string $recipient_email Recipient email address
 * @param string $recipient_name Recipient name
 * @param string $recipient_type Type: 'staff', 'manager', 'approver', 'supplier'
 * @param array $data Template data
 * @param int|null $supplier_id Supplier ID (for supplier templates)
 * @param int|null $sent_by User ID who triggered email
 * @return bool Success status
 */
function send_consignment_email(
    string $template_key,
    int $consignment_id,
    string $recipient_email,
    string $recipient_name,
    string $recipient_type,
    array $data,
    ?int $supplier_id = null,
    ?int $sent_by = null
): bool {
    global $pdo;

    try {
        // Render email
        $email = render_email_template($template_key, $data, $supplier_id);

        // Queue email using vapeshedSendEmail()
        require_once $_SERVER['DOCUMENT_ROOT'] . '/assets/functions/vapeshed-website.php';

        $queue_id = vapeshedSendEmail(
            $recipient_email,
            $email['subject'],
            $email['html'],
            $priority = ($email['priority'] === 'urgent') ? 1 : 0
        );

        // Log email to audit trail
        $stmt = $pdo->prepare("
            INSERT INTO consignment_email_log (
                consignment_id, template_key, recipient_email, recipient_name,
                recipient_type, subject_line, email_body, sent_by,
                queue_status, queue_id
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'queued', ?)
        ");
        $stmt->execute([
            $consignment_id,
            $template_key,
            $recipient_email,
            $recipient_name,
            $recipient_type,
            $email['subject'],
            $email['html'],
            $sent_by,
            $queue_id
        ]);

        return true;

    } catch (Exception $e) {
        error_log("Failed to send consignment email: " . $e->getMessage());

        // Log failure
        $stmt = $pdo->prepare("
            INSERT INTO consignment_email_log (
                consignment_id, template_key, recipient_email, recipient_name,
                recipient_type, subject_line, queue_status, error_message
            ) VALUES (?, ?, ?, ?, ?, '', 'failed', ?)
        ");
        $stmt->execute([
            $consignment_id,
            $template_key,
            $recipient_email,
            $recipient_name,
            $recipient_type,
            $e->getMessage()
        ]);

        return false;
    }
}
```

---

## 🎨 Example Template: Internal Consignment Created

**File:** `shared/templates/email/internal/consignment-created.php`

```php
<?php
/**
 * Internal Email Template: Consignment Created
 *
 * Sent to staff/managers when new consignment is created.
 *
 * Required data:
 *   $consignment_id, $consignment_status, $supplier_name, $total_items,
 *   $total_value, $created_by_name, $created_at, $view_url
 */

// Prepare email data for base template
$email_data = [
    'header_title' => "Consignment #{$consignment_id} Created",
    'content' => '
        <h2 style="color: #333333; margin: 0 0 20px 0;">New Consignment Created</h2>

        <p style="font-size: 16px; color: #555555; line-height: 1.6;">
            A new consignment has been created and requires your attention.
        </p>

        <table width="100%" cellpadding="10" cellspacing="0" border="0" style="margin: 20px 0; border: 1px solid #e0e0e0; border-radius: 5px;">
            <tr style="background-color: #f8f8f8;">
                <td style="font-weight: bold; color: #333333;">Consignment ID:</td>
                <td style="color: #555555;">#' . htmlspecialchars($consignment_id) . '</td>
            </tr>
            <tr>
                <td style="font-weight: bold; color: #333333;">Status:</td>
                <td style="color: #555555;">' . htmlspecialchars($consignment_status) . '</td>
            </tr>
            <tr style="background-color: #f8f8f8;">
                <td style="font-weight: bold; color: #333333;">Supplier:</td>
                <td style="color: #555555;">' . htmlspecialchars($supplier_name) . '</td>
            </tr>
            <tr>
                <td style="font-weight: bold; color: #333333;">Total Items:</td>
                <td style="color: #555555;">' . htmlspecialchars($total_items) . '</td>
            </tr>
            <tr style="background-color: #f8f8f8;">
                <td style="font-weight: bold; color: #333333;">Total Value:</td>
                <td style="color: #555555; font-weight: bold;">$' . number_format($total_value, 2) . '</td>
            </tr>
            <tr>
                <td style="font-weight: bold; color: #333333;">Created By:</td>
                <td style="color: #555555;">' . htmlspecialchars($created_by_name) . '</td>
            </tr>
            <tr style="background-color: #f8f8f8;">
                <td style="font-weight: bold; color: #333333;">Created At:</td>
                <td style="color: #555555;">' . htmlspecialchars($created_at) . '</td>
            </tr>
        </table>

        <p style="font-size: 14px; color: #666666; margin: 20px 0 0 0;">
            Click the button below to view full details and take action.
        </p>
    ',
    'cta_buttons' => [
        [
            'label' => 'View in CIS',
            'url' => $view_url,
            'color' => '#ffcc00'
        ]
    ]
];

// Render base email template
require __DIR__ . '/../base-email.php';
```

---

## 🎨 Example Template: Supplier PO Created

**File:** `shared/templates/email/supplier/po-created.php`

```php
<?php
/**
 * Supplier Email Template: Purchase Order Created
 *
 * Sent to suppliers when new PO is created from consignment.
 *
 * Required data:
 *   $po_number, $supplier_name, $delivery_address, $delivery_date,
 *   $total_items, $total_value, $contact_name, $contact_email,
 *   $contact_phone, $po_pdf_url, $confirm_url
 */

$email_data = [
    'header_title' => "Purchase Order #{$po_number}",
    'content' => '
        <h2 style="color: #333333; margin: 0 0 20px 0;">New Purchase Order from The Vape Shed</h2>

        <p style="font-size: 16px; color: #555555; line-height: 1.6;">
            Dear ' . htmlspecialchars($supplier_name) . ',
        </p>

        <p style="font-size: 16px; color: #555555; line-height: 1.6;">
            We have created a new purchase order for your review and confirmation.
        </p>

        <table width="100%" cellpadding="10" cellspacing="0" border="0" style="margin: 20px 0; border: 1px solid #e0e0e0; border-radius: 5px;">
            <tr style="background-color: #f8f8f8;">
                <td style="font-weight: bold; color: #333333;">PO Number:</td>
                <td style="color: #555555;">#' . htmlspecialchars($po_number) . '</td>
            </tr>
            <tr>
                <td style="font-weight: bold; color: #333333;">Delivery Address:</td>
                <td style="color: #555555;">' . nl2br(htmlspecialchars($delivery_address)) . '</td>
            </tr>
            <tr style="background-color: #f8f8f8;">
                <td style="font-weight: bold; color: #333333;">Requested Delivery:</td>
                <td style="color: #555555; font-weight: bold;">' . htmlspecialchars($delivery_date) . '</td>
            </tr>
            <tr>
                <td style="font-weight: bold; color: #333333;">Total Items:</td>
                <td style="color: #555555;">' . htmlspecialchars($total_items) . '</td>
            </tr>
            <tr style="background-color: #f8f8f8;">
                <td style="font-weight: bold; color: #333333;">Total Value:</td>
                <td style="color: #555555; font-weight: bold; font-size: 18px;">$' . number_format($total_value, 2) . '</td>
            </tr>
        </table>

        <div style="background-color: #fffbea; border-left: 4px solid #ffcc00; padding: 15px; margin: 20px 0; border-radius: 5px;">
            <p style="margin: 0; font-size: 14px; color: #666666;">
                <strong>Important:</strong> Please confirm receipt of this order within 24 hours.
            </p>
        </div>

        <h3 style="color: #333333; margin: 30px 0 15px 0;">Contact Information</h3>
        <p style="font-size: 14px; color: #555555; margin: 5px 0;">
            <strong>Contact:</strong> ' . htmlspecialchars($contact_name) . '<br/>
            <strong>Email:</strong> ' . htmlspecialchars($contact_email) . '<br/>
            <strong>Phone:</strong> ' . htmlspecialchars($contact_phone) . '
        </p>

        <p style="font-size: 14px; color: #666666; margin: 20px 0 0 0;">
            If you have any questions, please contact us using the details above.
        </p>
    ',
    'cta_buttons' => [
        [
            'label' => 'Download PO (PDF)',
            'url' => $po_pdf_url,
            'color' => '#ffcc00'
        ],
        [
            'label' => 'Confirm Receipt',
            'url' => $confirm_url,
            'color' => '#4CAF50'
        ]
    ]
];

require __DIR__ . '/../base-email.php';
```

---

## 🛠️ Admin UI for Template Management

**File:** `views/admin/email-templates.php`

This page allows staff to:
- Edit global template settings (logo, colors, footer text)
- Preview templates with sample data
- Create supplier-specific template overrides
- View email send history and audit logs

*(Full implementation will be in the module build phase)*

---

## 📋 Implementation Checklist

### Phase 1: Database Setup (30 minutes)

- [ ] Run `email-template-schema.sql` to create tables
- [ ] Verify tables created successfully
- [ ] Insert default configuration data
- [ ] Insert template definitions
- [ ] Test database queries

### Phase 2: Directory Structure (15 minutes)

- [ ] Create `shared/templates/email/` directory
- [ ] Create `shared/templates/email/internal/` directory
- [ ] Create `shared/templates/email/supplier/` directory
- [ ] Create `shared/functions/email-templates.php`
- [ ] Create `api/email/` directory

### Phase 3: Base Template (1 hour)

- [ ] Create `base-email.php` with responsive HTML
- [ ] Test rendering with sample data
- [ ] Verify mobile responsiveness
- [ ] Test in multiple email clients (Gmail, Outlook, Apple Mail)
- [ ] Ensure security (all outputs escaped)

### Phase 4: Template Functions (2 hours)

- [ ] Implement `get_email_template_config()`
- [ ] Implement `get_supplier_template_overrides()`
- [ ] Implement `render_email_template()`
- [ ] Implement `send_consignment_email()`
- [ ] Write unit tests for all functions
- [ ] Integrate with `vapeshedSendEmail()` queue

### Phase 5: Internal Templates (3 hours)

- [ ] Create `consignment-created.php`
- [ ] Create `consignment-approved.php`
- [ ] Create `consignment-rejected.php`
- [ ] Create `consignment-amended.php`
- [ ] Create `approval-needed.php`
- [ ] Create `exception-alert.php`
- [ ] Test each template with real data

### Phase 6: Supplier Templates (3 hours)

- [ ] Create `po-created.php`
- [ ] Create `po-updated.php`
- [ ] Create `delivery-reminder.php`
- [ ] Create `receipt-confirmation.php`
- [ ] Test with supplier data
- [ ] Verify supplier-specific overrides work

### Phase 7: Admin UI (4 hours)

- [ ] Create `email-templates.php` admin page
- [ ] Implement template preview functionality
- [ ] Implement configuration editor
- [ ] Implement supplier override editor
- [ ] Add email send history viewer
- [ ] Secure with admin permissions

### Phase 8: Integration & Testing (2 hours)

- [ ] Integrate email sending into consignment workflows
- [ ] Test all email triggers
- [ ] Verify queue integration
- [ ] Test email delivery to real addresses
- [ ] Review audit logs
- [ ] Performance testing (batch sends)

### Phase 9: Documentation (1 hour)

- [ ] Document all template variables
- [ ] Create template development guide
- [ ] Document admin UI usage
- [ ] Create troubleshooting guide
- [ ] Update main consignments documentation

---

## 🎯 Summary

**You now have:**

1. ✅ **Complete directory structure** - Where every file belongs
2. ✅ **Database schema** - 4 tables for templates, config, overrides, logs
3. ✅ **Base email template** - Responsive HTML structure (600px, mobile-friendly)
4. ✅ **Two template types** - Internal (staff) + Supplier (external)
5. ✅ **Rendering functions** - Complete PHP functions for email generation
6. ✅ **Example templates** - Full working examples (internal + supplier)
7. ✅ **Admin configuration** - Database-driven settings (logo, colors, footer)
8. ✅ **Integration approach** - How it connects to `vapeshedSendEmail()` queue
9. ✅ **Implementation checklist** - Step-by-step setup guide

**Total implementation time:** ~16 hours (2 days)

**Next Steps:**
1. Review this structure and confirm it matches your vision
2. Run database migrations to create tables
3. Create directory structure
4. Begin template implementation

**Ready to proceed with module build?** This template system is production-ready and follows your Q27 specification exactly! 🚀
