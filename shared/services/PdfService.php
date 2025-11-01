<?php
/**
 * PdfService - Centralized PDF generation service
 *
 * Uses Dompdf (open-source HTML to PDF converter)
 * Available to ALL modules via shared/services
 *
 * Installation: composer require dompdf/dompdf
 *
 * Usage:
 *   $pdf = PdfService::fromHtml($html);
 *   $pdf->download('filename.pdf');
 *   $pdf->output(); // Get bytes
 *
 * @package CIS\Shared\Services
 * @version 1.0.0
 */

declare(strict_types=1);

namespace CIS\Shared\Services;

class PdfService
{
    private $dompdf;
    private string $html;
    private array $options;

    /**
     * Create PDF from HTML
     *
     * @param string $html HTML content
     * @param array $options PDF options
     * @return self
     */
    public static function fromHtml(string $html, array $options = []): self
    {
        $instance = new self();
        $instance->html = $html;
        $instance->options = array_merge([
            'orientation' => 'portrait',
            'paper' => 'a4',
            'dpi' => 96,
            'enable_remote' => false,
            'enable_php' => false
        ], $options);

        $instance->generate();
        return $instance;
    }

    /**
     * Generate PDF using Dompdf
     */
    private function generate(): void
    {
        // Check if Dompdf is available
        if (!class_exists('\Dompdf\Dompdf')) {
            // Fallback: Try to load from vendor
            $vendorPath = $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
            if (file_exists($vendorPath)) {
                require_once $vendorPath;
            }
        }

        if (class_exists('\Dompdf\Dompdf')) {
            // Use Dompdf
            $options = new \Dompdf\Options();
            $options->set('isRemoteEnabled', $this->options['enable_remote']);
            $options->set('isPhpEnabled', $this->options['enable_php']);
            $options->set('dpi', $this->options['dpi']);

            $this->dompdf = new \Dompdf\Dompdf($options);
            $this->dompdf->loadHtml($this->html);
            $this->dompdf->setPaper($this->options['paper'], $this->options['orientation']);
            $this->dompdf->render();
        } else {
            // Fallback: Use simple HTML-to-PDF wrapper
            $this->generateFallback();
        }
    }

    /**
     * Fallback PDF generation (basic wrapper around HTML)
     */
    private function generateFallback(): void
    {
        // Create a simple wrapper that marks this as PDF content
        // This allows the browser to handle it or save as HTML
        $this->dompdf = new \stdClass();
        $this->dompdf->output = $this->wrapHtmlForPdf($this->html);
    }

    /**
     * Wrap HTML in PDF-friendly format
     */
    private function wrapHtmlForPdf(string $html): string
    {
        return "<!DOCTYPE html>\n" .
               "<html>\n" .
               "<head>\n" .
               "  <meta charset='utf-8'>\n" .
               "  <style>\n" .
               "    @media print {\n" .
               "      body { margin: 0; padding: 20px; }\n" .
               "      @page { size: A4; margin: 15mm; }\n" .
               "    }\n" .
               "  </style>\n" .
               "</head>\n" .
               "<body>\n" .
               $html .
               "\n</body>\n" .
               "</html>";
    }

    /**
     * Get PDF as binary string
     *
     * @return string PDF bytes
     */
    public function output(): string
    {
        if ($this->dompdf instanceof \Dompdf\Dompdf) {
            return $this->dompdf->output();
        }

        // Fallback
        return $this->dompdf->output ?? '';
    }

    /**
     * Force download in browser
     *
     * @param string $filename Download filename
     */
    public function download(string $filename = 'document.pdf'): void
    {
        if ($this->dompdf instanceof \Dompdf\Dompdf) {
            $this->dompdf->stream($filename, ['Attachment' => true]);
        } else {
            // Fallback: Output HTML with download headers
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="' . $filename . '"');
            echo $this->output();
        }
    }

    /**
     * Display inline in browser
     *
     * @param string $filename Display filename
     */
    public function inline(string $filename = 'document.pdf'): void
    {
        if ($this->dompdf instanceof \Dompdf\Dompdf) {
            $this->dompdf->stream($filename, ['Attachment' => false]);
        } else {
            // Fallback: Output HTML with inline headers
            header('Content-Type: application/pdf');
            header('Content-Disposition: inline; filename="' . $filename . '"');
            echo $this->output();
        }
    }

    /**
     * Save PDF to file
     *
     * @param string $path File path
     * @return bool Success
     */
    public function save(string $path): bool
    {
        $output = $this->output();
        return file_put_contents($path, $output) !== false;
    }

    /**
     * Get PDF as base64 encoded string (for email attachments)
     *
     * @return string Base64 encoded PDF
     */
    public function toBase64(): string
    {
        return base64_encode($this->output());
    }

    /**
     * Check if Dompdf is available
     *
     * @return bool
     */
    public static function isDompdfAvailable(): bool
    {
        if (class_exists('\Dompdf\Dompdf')) {
            return true;
        }

        $vendorPath = $_SERVER['DOCUMENT_ROOT'] . '/vendor/autoload.php';
        if (file_exists($vendorPath)) {
            require_once $vendorPath;
            return class_exists('\Dompdf\Dompdf');
        }

        return false;
    }

    /**
     * Get service status and recommendations
     *
     * @return array Status information
     */
    public static function getStatus(): array
    {
        $dompdfAvailable = self::isDompdfAvailable();

        return [
            'dompdf_installed' => $dompdfAvailable,
            'fallback_mode' => !$dompdfAvailable,
            'recommendation' => $dompdfAvailable
                ? 'Dompdf is installed and ready'
                : 'Install Dompdf for production-quality PDFs: composer require dompdf/dompdf',
            'version' => '1.0.0',
            'features' => [
                'html_to_pdf' => true,
                'download' => true,
                'inline_display' => true,
                'base64_encoding' => true,
                'save_to_file' => true
            ]
        ];
    }
}
