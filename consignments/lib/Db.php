<?php
declare(strict_types=1);

namespace Transfers\Lib;

use mysqli;
use Exception;

final class Db
{
    /**
     * Return the shared MySQLi handle from CIS connectToSQL()
     * Uses the global $con connection - MUCH SIMPLER!
     */
    public static function mysqli(): mysqli
    {
        global $con;
        
        if (!$con instanceof mysqli) {
            throw new Exception("CIS database connection not established. Call connectToSQL() first.");
        }
        
        return $con;
    }

    /**
     * SIMPLE HELPER: Execute query and return result
     * Since global $con is available everywhere after connectToSQL()
     */
    public static function query(string $sql): mixed
    {
        global $con;
        return $con->query($sql);
    }

    /**
     * SIMPLE HELPER: Prepare statement
     */
    public static function prepare(string $sql): \mysqli_stmt
    {
        global $con;
        return $con->prepare($sql);
    }

    /**
     * SIMPLE HELPER: Escape string
     */
    public static function escape(string $string): string
    {
        global $con;
        return $con->real_escape_string($string);
    }

    public static function ping(): bool
    {
        global $con;
        return $con instanceof mysqli && $con->ping();
    }

    /**
     * Get current session ID from PHPSESSID cookie
     */
    public static function getSessionId(): ?string
    {
        return session_id() ?: null;
    }

    /**
     * Validate session exists in database Session table
     */
    public static function validateSession(string $sessionId): bool
    {
        global $con;
        try {
            $stmt = $con->prepare("SELECT Session_Id FROM Session WHERE Session_Id = ? AND Session_Expires > NOW()");
            $stmt->bind_param('s', $sessionId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            return $result->num_rows > 0;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Load environment variables from .env file if it exists
     */
    public static function loadEnv(): void
    {
        $envFile = $_SERVER['DOCUMENT_ROOT'] . '/.env';
        
        if (file_exists($envFile)) {
            $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            
            foreach ($lines as $line) {
                if (strpos(trim($line), '#') === 0) {
                    continue; // Skip comments
                }
                
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $key = trim($key);
                    $value = trim($value);
                    
                    // Remove quotes if present
                    if (preg_match('/^"(.*)"$/', $value, $matches)) {
                        $value = $matches[1];
                    } elseif (preg_match("/^'(.*)'$/", $value, $matches)) {
                        $value = $matches[1];
                    }
                    
                    $_ENV[$key] = $value;
                    putenv("$key=$value");
                }
            }
        }
    }
}
