<?php
declare(strict_types=1);

namespace CIS\Base\Http\Controllers;

use CIS\Base\Core\Application;
use CIS\Base\Http\Request;
use CIS\Base\Http\Response;

/**
 * Log viewing controller (Quick-Dial skeleton)
 */
final class LogsController
{
    private Application $app;

    public function __construct(Application $app)
    {
        $this->app = $app;
    }

    public function apacheTail(Request $request): Response
    {
        $lines = (int)$request->query('lines', '200');
        $lines = max(1, min(500, $lines));
        $logFile = '/var/log/apache2/error.log';
        $content = $this->tail($logFile, $lines);
        return Response::success([
            'log' => 'apache-error',
            'path' => $logFile,
            'lines' => $lines,
            'content' => $content,
        ]);
    }

    public function phpFpmTail(Request $request): Response
    {
        $lines = (int)$request->query('lines', '200');
        $lines = max(1, min(500, $lines));
        $logFile = '/var/log/php/php-fpm.log';
        $content = is_readable($logFile) ? $this->tail($logFile, $lines) : '';
        return Response::success([
            'log' => 'php-fpm',
            'path' => $logFile,
            'lines' => $lines,
            'content' => $content,
        ]);
    }

    public function viewer(Request $request): Response
    {
        $html = '<h1>Log Viewer</h1><p>Use /?endpoint=admin/logs/apache-error-tail for JSON tail.</p>';
        return Response::html($html);
    }

    private function tail(string $filepath, int $lines): string
    {
        if (!is_readable($filepath)) { return ''; }
        $f = fopen($filepath, 'rb');
        if (!$f) return '';
        $buffer = '';
        $chunkSize = 4096;
        $pos = -1;
        $lineCount = 0;
        fseek($f, 0, SEEK_END);
        $fileSize = ftell($f);
        while ($fileSize + $pos > 0 && $lineCount <= $lines) {
            $seek = max(0, $fileSize + $pos - $chunkSize);
            $read = $fileSize + $pos - $seek;
            fseek($f, $seek);
            $chunk = fread($f, $read);
            $buffer = $chunk . $buffer;
            $lineCount = substr_count($buffer, "\n");
            $pos -= $chunkSize;
        }
        fclose($f);
        $parts = explode("\n", $buffer);
        $tail  = array_slice($parts, -$lines);
        return implode("\n", $tail);
    }
}
