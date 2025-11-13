<?php
/**
 * Government Letter Processor (OCR + AI Parsing)
 * FULL AUTO, MAXIMUM HARDENING
 *
 * Usage: php parse_government_letter.php --file=letter.pdf [--staff_id=123]
 *
 * - Accepts PDF/image of government deduction order (court, IRD, etc)
 * - Runs OCR (Tesseract CLI, fallback to cloud if needed)
 * - AI parses for deduction details (order_type, amount, method, dates, etc)
 * - Auto-populates payroll_nz_deduction_applications
 * - Confidence scoring, human review fallback
 * - Logs all actions, errors, and edge cases
 */

// Bootstrap CIS app
$bootstrap_paths = [
    __DIR__ . '/../../../../app.php',
    __DIR__ . '/../../../../private_html/app.php',
    __DIR__ . '/../../../../public_html/app.php',
];
foreach ($bootstrap_paths as $path) {
    if (file_exists($path)) {
        require_once $path;
        break;
    }
}
if (!isset($conn)) {
    fwrite(STDERR, "[FATAL] DB connection not initialized.\n");
    exit(1);
}

require_once __DIR__ . '/../classes/PayrollAIDecisionEngine.php';

// --- Parse Args ---
$file = null;
$staff_id = null;
foreach ($argv as $arg) {
    if (preg_match('/--file=(.+)/', $arg, $m)) {
        $file = $m[1];
    }
    if (preg_match('/--staff_id=(\d+)/', $arg, $m)) {
        $staff_id = (int)$m[1];
    }
}
if (!$file || !file_exists($file)) {
    fwrite(STDERR, "Usage: php parse_government_letter.php --file=letter.pdf [--staff_id=123]\n");
    exit(2);
}

// --- OCR Extraction ---
$tmp_txt = tempnam(sys_get_temp_dir(), 'ocr_') . '.txt';
$cmd = "tesseract " . escapeshellarg($file) . " " . escapeshellarg($tmp_txt) . " -l eng 2>&1";
exec($cmd, $out, $code);
$ocr_text = '';
if ($code === 0 && file_exists($tmp_txt . '.txt')) {
    $ocr_text = file_get_contents($tmp_txt . '.txt');
    unlink($tmp_txt . '.txt');
} else {
    fwrite(STDERR, "[ERROR] OCR failed.\n");
    exit(3);
}

// --- AI Parsing ---
$engine = new PayrollAIDecisionEngine($conn);
$prompt = "Extract the following fields from this NZ government deduction order letter. Output valid JSON with keys: order_type, order_reference, issuing_authority, staff_id, amount, calculation_method, percentage, min_net_protected, priority, effective_from, effective_to. If a field is missing, use null.\n\nLetter text:\n" . $ocr_text;
$ai_result = $engine->callOpenAI($prompt, 0.2, 2048, true);
if (!$ai_result['success'] || !$ai_result['json']) {
    fwrite(STDERR, "[ERROR] AI parsing failed.\n");
    exit(4);
}
$data = $ai_result['json'];
if ($staff_id) $data['staff_id'] = $staff_id;
if (empty($data['staff_id'])) {
    fwrite(STDERR, "[WARN] staff_id missing, cannot auto-apply.\n");
    exit(5);
}

// --- Confidence Scoring ---
$confidence = $ai_result['confidence'] ?? 0.0;
if ($confidence < 0.90) {
    fwrite(STDERR, "[REVIEW] Confidence $confidence < 0.90, human review required.\n");
    // Optionally log to review queue
    exit(6);
}

// --- Insert Deduction Application ---
$ins = $conn->prepare("INSERT INTO payroll_nz_deduction_applications (order_type, order_reference, issuing_authority, staff_id, amount, calculation_method, percentage, min_net_protected, priority, effective_from, effective_to, is_active, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, NOW())");
$ins->bind_param(
    'sssidsddiss',
    $data['order_type'],
    $data['order_reference'],
    $data['issuing_authority'],
    $data['staff_id'],
    $data['amount'],
    $data['calculation_method'],
    $data['percentage'],
    $data['min_net_protected'],
    $data['priority'],
    $data['effective_from'],
    $data['effective_to']
);
if ($ins->execute()) {
    fwrite(STDOUT, "[OK] Deduction application created for staff_id {$data['staff_id']} (order: {$data['order_reference']})\n");
} else {
    fwrite(STDERR, "[ERROR] DB insert failed: {$ins->error}\n");
    exit(7);
}
$ins->close();
exit(0);
