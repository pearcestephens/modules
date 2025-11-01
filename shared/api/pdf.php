<?php
/**
 * PDF Service API - Generate PDFs from HTML
 *
 * Endpoint: /modules/shared/api/pdf.php
 *
 * Methods:
 *   POST /pdf.php?action=generate - Generate PDF from HTML
 *   POST /pdf.php?action=from_url - Generate PDF from URL
 *   GET  /pdf.php?action=status   - Check service status
 *
 * Example:
 *   curl -X POST /api/pdf.php?action=generate \
 *     -H "Content-Type: application/json" \
 *     -d '{"html": "<h1>Test</h1>", "filename": "test.pdf"}'
 *
 * @package CIS\Shared\API
 */

declare(strict_types=1);

header('Content-Type: application/json');

// Load PdfService
require_once __DIR__ . '/../services/PdfService.php';

use CIS\Shared\Services\PdfService;

try {
    $action = $_GET['action'] ?? 'generate';

    switch ($action) {
        case 'status':
            // Check service status
            $status = PdfService::getStatus();
            echo json_encode([
                'success' => true,
                'data' => $status
            ]);
            break;

        case 'generate':
            // Generate PDF from HTML
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
                exit;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $html = $input['html'] ?? '';
            $filename = $input['filename'] ?? 'document.pdf';
            $options = $input['options'] ?? [];
            $outputMode = $input['output'] ?? 'download'; // download, inline, base64, file

            if (empty($html)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'HTML content required']);
                exit;
            }

            $pdf = PdfService::fromHtml($html, $options);

            switch ($outputMode) {
                case 'download':
                    $pdf->download($filename);
                    exit;

                case 'inline':
                    $pdf->inline($filename);
                    exit;

                case 'base64':
                    echo json_encode([
                        'success' => true,
                        'data' => [
                            'base64' => $pdf->toBase64(),
                            'filename' => $filename
                        ]
                    ]);
                    break;

                case 'file':
                    $filepath = $input['filepath'] ?? '/tmp/' . $filename;
                    $saved = $pdf->save($filepath);
                    echo json_encode([
                        'success' => $saved,
                        'data' => ['filepath' => $filepath]
                    ]);
                    break;

                default:
                    // Return binary as response
                    header('Content-Type: application/pdf');
                    header('Content-Disposition: attachment; filename="' . $filename . '"');
                    echo $pdf->output();
                    exit;
            }
            break;

        case 'from_url':
            // Generate PDF from URL (fetch HTML first)
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                http_response_code(405);
                echo json_encode(['success' => false, 'error' => 'Method not allowed']);
                exit;
            }

            $input = json_decode(file_get_contents('php://input'), true);
            $url = $input['url'] ?? '';
            $filename = $input['filename'] ?? 'document.pdf';
            $options = $input['options'] ?? [];

            if (empty($url)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'URL required']);
                exit;
            }

            // Fetch HTML from URL
            $html = file_get_contents($url);
            if ($html === false) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Failed to fetch URL']);
                exit;
            }

            $pdf = PdfService::fromHtml($html, $options);
            $pdf->download($filename);
            exit;

        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
