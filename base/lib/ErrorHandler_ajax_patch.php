    /**
     * Check if request is AJAX
     */
    private static function isAjaxRequest(): bool {
        return (
            !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
            strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest'
        ) || (
            isset($_SERVER['CONTENT_TYPE']) && 
            strpos($_SERVER['CONTENT_TYPE'], 'application/json') !== false
        ) || (
            isset($_SERVER['HTTP_ACCEPT']) && 
            strpos($_SERVER['HTTP_ACCEPT'], 'application/json') !== false
        );
    }
    
    /**
     * Render JSON error for AJAX
     */
    private static function renderJsonError(string $title, string $message, string $file, int $line, $code = null): void {
        $response = [
            'success' => false,
            'error' => self::$debugMode ? $message : 'An unexpected error occurred. Please try again later.',
            'error_title' => $title
        ];
        
        if (self::$debugMode) {
            $response['error_details'] = sprintf(
                "File: %s\nLine: %d\nCode: %s",
                $file,
                $line,
                $code ?? 'N/A'
            );
        }
        
        echo json_encode($response, JSON_PRETTY_PRINT);
    }
