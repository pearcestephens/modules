<?php
/**
 * Email Statement API Endpoint
 *
 * Sends a payment statement email to a staff member
 *
 * @package CIS\Modules\StaffAccounts\API
 * @version 1.0.0
 */

declare(strict_types=1);

require_once __DIR__ . '/../bootstrap.php';
require_once ROOT_PATH . '/assets/functions/config.php';

header('Content-Type: application/json');

// Authentication check
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

// Only POST allowed
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

try {
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);

    if (!$input) {
        throw new Exception('Invalid JSON input');
    }

    $staffName = $input['staff_name'] ?? '';
    $staffEmail = $input['staff_email'] ?? '';
    $customerCode = $input['customer_code'] ?? '';
    $paymentAmount = floatval($input['amount'] ?? 0);
    $currentBalance = floatval($input['current_balance'] ?? 0);
    $paymentDate = $input['payment_date'] ?? '';
    $outletName = $input['outlet_name'] ?? '';
    $receiptNumber = $input['receipt_number'] ?? $input['invoice_number'] ?? 'N/A';
    $paymentId = $input['id'] ?? '';

    // Validate inputs
    if (empty($staffEmail)) {
        throw new Exception('Staff email is required');
    }

    if (!filter_var($staffEmail, FILTER_VALIDATE_EMAIL)) {
        throw new Exception('Invalid email address');
    }

    // Calculate balances
    $currentDebt = abs($currentBalance);
    $newBalance = $currentBalance + $paymentAmount;
    $newDebt = abs($newBalance);
    $debtReduction = $paymentAmount;
    $debtReductionPercent = $currentDebt > 0 ? ($debtReduction / $currentDebt * 100) : 0;

    // Build email HTML
    $emailHTML = "
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset='UTF-8'>
        <style>
            body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
            .container { max-width: 650px; margin: 0 auto; padding: 20px; border: 2px solid #000; }
            .header { text-align: center; border-bottom: 3px double #000; padding-bottom: 20px; margin-bottom: 20px; }
            .header h1 { margin: 0; font-size: 28px; color: #000; }
            .header p { margin: 5px 0; color: #666; }
            table { width: 100%; border-collapse: collapse; margin: 15px 0; }
            table td { padding: 8px; border-bottom: 1px solid #ddd; }
            table td:first-child { font-weight: bold; width: 40%; }
            .summary-box { background: #f8f9fa; border: 2px solid #000; padding: 20px; margin: 20px 0; }
            .summary-box h2 { text-align: center; margin-top: 0; font-size: 20px; }
            .balance-row { background: #e9ecef; font-weight: bold; font-size: 16px; }
            .success-box { background: #d4edda; border: 1px solid #c3e6cb; padding: 15px; margin: 15px 0; border-radius: 5px; text-align: center; color: #155724; }
            .footer { margin-top: 30px; padding-top: 20px; border-top: 1px solid #000; font-size: 12px; text-align: center; color: #666; }
            .amount-positive { color: #28a745; font-weight: bold; }
            .amount-negative { color: #dc3545; font-weight: bold; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>THE VAPE SHED</h1>
                <p style='font-size: 16px;'>Staff Account Payment Statement</p>
                <p>Ecigdis Limited</p>
            </div>

            <p>Dear " . htmlspecialchars($staffName) . ",</p>
            <p>This confirms your recent payment has been processed. Please find your account statement below:</p>

            <table>
                <tr>
                    <td>Staff Member:</td>
                    <td>" . htmlspecialchars($staffName) . "</td>
                </tr>
                <tr>
                    <td>Customer Code:</td>
                    <td>" . htmlspecialchars($customerCode) . "</td>
                </tr>
                <tr>
                    <td>Payment Date:</td>
                    <td>" . date('l, j F Y g:i A', strtotime($paymentDate)) . "</td>
                </tr>
                <tr>
                    <td>Outlet:</td>
                    <td>" . htmlspecialchars($outletName) . "</td>
                </tr>
                <tr>
                    <td>Receipt #:</td>
                    <td>" . htmlspecialchars($receiptNumber) . "</td>
                </tr>
                <tr>
                    <td>Payment ID:</td>
                    <td>#" . htmlspecialchars($paymentId) . "</td>
                </tr>
            </table>

            <div class='summary-box'>
                <h2>ACCOUNT SUMMARY</h2>
                <table>
                    <tr>
                        <td>Previous Balance (Amount Owed):</td>
                        <td style='text-align: right;' class='" . ($currentDebt > 0 ? 'amount-negative' : 'amount-positive') . "'>
                            " . ($currentDebt > 0 ? '-' : '') . "$" . number_format($currentDebt, 2) . "
                        </td>
                    </tr>
                    <tr>
                        <td>Payment Received:</td>
                        <td style='text-align: right;' class='amount-positive'>
                            +$" . number_format($paymentAmount, 2) . "
                        </td>
                    </tr>
                    <tr class='balance-row'>
                        <td>NEW BALANCE:</td>
                        <td style='text-align: right;' class='" . ($newBalance < 0 ? 'amount-negative' : 'amount-positive') . "'>
                            " . ($newBalance < 0 ? '-' : '') . "$" . number_format($newDebt, 2) . "
                        </td>
                    </tr>
                </table>
            </div>";

    if ($currentDebt > 0) {
        $emailHTML .= "
            <div class='success-box'>
                <strong>✓ DEBT REDUCED BY $" . number_format($debtReduction, 2) . " (" . number_format($debtReductionPercent, 1) . "%)</strong>";

        if ($newBalance >= 0) {
            $emailHTML .= "<br><br><strong style='font-size: 18px;'>🎉 ACCOUNT FULLY PAID! 🎉</strong>";
        }

        $emailHTML .= "</div>";
    }

    $emailHTML .= "
            <p style='margin-top: 30px;'><strong>Questions?</strong><br>
            If you have any questions about this statement, please contact accounts@vapeshed.co.nz</p>

            <div class='footer'>
                <p>This is an automated statement from CIS Staff Portal</p>
                <p>Generated: " . date('l, j F Y g:i A') . "</p>
                <p>The Vape Shed | Ecigdis Limited<br>
                www.vapeshed.co.nz</p>
            </div>
        </div>
    </body>
    </html>
    ";

    // Plain text version
    $emailText = "THE VAPE SHED - Staff Account Payment Statement\n\n";
    $emailText .= "Staff Member: $staffName\n";
    $emailText .= "Customer Code: $customerCode\n";
    $emailText .= "Payment Date: " . date('l, j F Y g:i A', strtotime($paymentDate)) . "\n";
    $emailText .= "Outlet: $outletName\n";
    $emailText .= "Receipt #: $receiptNumber\n\n";
    $emailText .= "ACCOUNT SUMMARY\n";
    $emailText .= "===============\n";
    $emailText .= "Previous Balance: " . ($currentDebt > 0 ? '-' : '') . "$" . number_format($currentDebt, 2) . "\n";
    $emailText .= "Payment Received: +$" . number_format($paymentAmount, 2) . "\n";
    $emailText .= "NEW BALANCE: " . ($newBalance < 0 ? '-' : '') . "$" . number_format($newDebt, 2) . "\n\n";

    if ($currentDebt > 0) {
        $emailText .= "Debt reduced by $" . number_format($debtReduction, 2) . " (" . number_format($debtReductionPercent, 1) . "%)\n";
        if ($newBalance >= 0) {
            $emailText .= "ACCOUNT FULLY PAID!\n";
        }
    }

    $emailText .= "\nQuestions? Contact accounts@vapeshed.co.nz\n";

    // Send email using PHPMailer or mail()
    $to = $staffEmail;
    $subject = "Payment Statement - The Vape Shed - " . date('j M Y', strtotime($paymentDate));
    $headers = [
        'From: The Vape Shed Accounts <accounts@vapeshed.co.nz>',
        'Reply-To: accounts@vapeshed.co.nz',
        'Content-Type: text/html; charset=UTF-8',
        'X-Mailer: PHP/' . phpversion(),
        'MIME-Version: 1.0'
    ];

    $mailSent = mail($to, $subject, $emailHTML, implode("\r\n", $headers));

    if ($mailSent) {
        // Log the email
        error_log(sprintf(
            "[STATEMENT EMAILED] To: %s, Staff: %s, Payment: $%s, Sent by: %s",
            $staffEmail,
            $staffName,
            number_format($paymentAmount, 2),
            $_SESSION['user_name'] ?? 'Unknown'
        ));

        echo json_encode([
            'success' => true,
            'message' => 'Statement emailed successfully',
            'email' => $staffEmail
        ]);
    } else {
        throw new Exception('Failed to send email - please check mail configuration');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
