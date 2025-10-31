<?php
/**
 * Filename: PayslipEmailer.php
 * Purpose : Queue payslip emails with PDF attachment using the central mail queue utility.
 * Author  : GitHub Copilot
 * Last Modified: 2025-10-31
 * Dependencies: PayslipPdfGenerator, queue_enqueue_email()
 */
declare(strict_types=1);

final class PayslipEmailer
{
    /**
     * Queue an email containing the payslip PDF attachment.
     *
     * @param array $payslip Payslip metadata with employee contact details.
     * @param array $lines   Line items belonging to the payslip.
     *
     * @return bool True when the message was queued successfully.
     */
    public static function queueEmail(array $payslip, array $lines): bool
    {
        $recipient = $payslip['email'] ?? null;
        if (!$recipient) {
            return false;
        }

        if (!function_exists('queue_enqueue_email')) {
            error_log('PayslipEmailer: queue_enqueue_email() is undefined.');
            return false;
        }

        $htmlBody = PayslipPdfGenerator::renderHtml($payslip, $lines);
        $pdfBytes = PayslipPdfGenerator::toPdfBytes($htmlBody);

        $subject = sprintf(
            'Payslip %s → %s',
            (string)($payslip['period_start'] ?? ''),
            (string)($payslip['period_end'] ?? '')
        );

        $textBody = "Kia ora,\n\nYour payslip is attached and available in CIS.\n\nNgā mihi,\nThe Vape Shed";

        $attachments = [[
            'filename' => 'payslip.pdf',
            'content' => base64_encode($pdfBytes),
            'mime' => 'application/pdf',
        ]];

        return (bool)queue_enqueue_email($recipient, $subject, $textBody, $attachments);
    }
}
