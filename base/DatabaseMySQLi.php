<?php
declare(strict_types=1);

namespace CIS\Base;

use mysqli;
use mysqli_result;
use mysqli_stmt;

/**
 * ============================================================================
 * CIS Base DatabaseMySQLi Wrapper
 * ============================================================================
 * 
 * Industry-standard MySQLi wrapper with connection pooling, transactions,
 * prepared statements, and comprehensive error handling.
 * 
 * This class provides backward compatibility with existing CIS code that uses
 * MySQLi while offering modern features and safety improvements.
 * 
 * Features:
 * - Connection pooling (persistent connections)
 * - Automatic reconnection on connection loss
 * - Query builder for common operations
 * - Transaction management
 * - Prepared statement handling
 * - Query logging and performance tracking
 * - Backward compatibility with global $con
 * 
 * Usage Examples:
 * 
 * // Basic query
 * $users = DatabaseMySQLi::query("SELECT * FROM users WHERE active = ?", ['i', 1]);
 * 
 * // Single row
 * $user = DatabaseMySQLi::queryOne("SELECT * FROM users WHERE id = ?", ['i', 123]);
 * 
 * // Insert with auto-generated ID
 * $id = DatabaseMySQLi::insert('users', [
 *     'name' => 'John Doe',
 *     'email' => 'john@example.com'
 * ]);
 * 
 * // Get mysqli connection for legacy code
 * $con = DatabaseMySQLi::connection();
 * 
 * // Transactions
 * DatabaseMySQLi::beginTransaction();
 * try {
 *     DatabaseMySQLi::insert('orders', [...]);
 *     DatabaseMySQLi::update('inventory', [...]);
 *     DatabaseMySQLi::commit();
 * } catch (\Exception $e) {
 *     DatabaseMySQLi::rollback();
 * }
 * 
 * @package CIS\Base
 * @version 2.0.0
 * @author Pearce Stephens
 * @created 2025-10-27
 */
class DatabaseMySQLi
{
    /** @var array Connection pool */
    private static array $connections = [];
    
    /** @var string Default connection name */
    private static string $defaultConnection = 'default';
    
    /** @var array Configuration for connections */
    private static array $config = [];
    
    /** @var array Query log for debugging */
    private static array $queryLog = [];
    
    /** @var bool Enable query logging */
    private static bool $logQueries = false;
    
    /** @var int Max queries to keep in log */
    private static int $maxLogSize = 100;
    
    /**
     * Initialize database configuration
     * 
     * @param array $config Configuration array
     * @param string $name Connection name
     * @return void
     */
    public static function configure(array $config, string $name = 'default'): void
    {
        self::$config[$name] = array_merge([
            'host' => '127.0.0.1',
            'port' => 3306,
            'database' => '',
            'username' => '',
            'password' => '',
            'charset' => 'utf8mb4',
            'persistent' => true,
        ], $config);
    }
    
    /**
     * Get MySQLi connection instance
     * 
     * @param string $name Connection name
     * @return mysqli
     * @throws \Exception
     */
    public static function connection(string $name = 'default'): mysqli
    {
        if (!isset(self::$connections[$name])) {
            self::createConnection($name);
        }
        
        // Check if connection is alive
        if (!self::$connections[$name]->ping()) {
            // Reconnect on failure
            self::createConnection($name);
        }
        
        return self::$connections[$name];
    }
    
    /**
     * Create new connection
     * 
     * @param string $name Connection name
     * @return void
     * @throws \Exception
     */
    private static function createConnection(string $name): void
    {
        if (!isset(self::$config[$name])) {
            // Auto-configure from environment or default
            self::configure([
                'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
                'database' => $_ENV['DB_NAME'] ?? 'jcepnzzkmj',
                'username' => $_ENV['DB_USER'] ?? 'jcepnzzkmj',
                'password' => $_ENV['DB_PASS'] ?? '',
            ], $name);
        }
        
        $config = self::$config[$name];
        
        // Use persistent connection if enabled
        $host = $config['persistent'] ? 'p:' . $config['host'] : $config['host'];
        
        mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
        
        try {
            $mysqli = new mysqli(
                $host,
                $config['username'],
                $config['password'],
                $config['database'],
                $config['port']
            );
            
            // Set charset
            $mysqli->set_charset($config['charset']);
            
            // Disable autocommit for explicit transaction control
            $mysqli->autocommit(true);
            
            self::$connections[$name] = $mysqli;
        } catch (\Exception $e) {
            error_log("MySQLi Connection failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    /**
     * Execute query and return all results
     * 
     * @param string $sql SQL query
     * @param array $params Query parameters [types, ...values]
     * @param string $connection Connection name
     * @return array
     */
    public static function query(string $sql, array $params = [], string $connection = 'default'): array
    {
        $result = self::execute($sql, $params, $connection);
        
        if ($result === true || $result === false) {
            return [];
        }
        
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }
        
        $result->free();
        
        return $rows;
    }
    
    /**
     * Execute query and return single row
     * 
     * @param string $sql SQL query
     * @param array $params Query parameters [types, ...values]
     * @param string $connection Connection name
     * @return array|null
     */
    public static function queryOne(string $sql, array $params = [], string $connection = 'default'): ?array
    {
        $result = self::execute($sql, $params, $connection);
        
        if ($result === true || $result === false) {
            return null;
        }
        
        $row = $result->fetch_assoc();
        $result->free();
        
        return $row === null ? null : $row;
    }
    
    /**
     * Execute query and return single value
     * 
     * @param string $sql SQL query
     * @param array $params Query parameters [types, ...values]
     * @param string $connection Connection name
     * @return mixed
     */
    public static function queryValue(string $sql, array $params = [], string $connection = 'default'): mixed
    {
        $result = self::execute($sql, $params, $connection);
        
        if ($result === true || $result === false) {
            return null;
        }
        
        $row = $result->fetch_row();
        $result->free();
        
        return $row ? $row[0] : null;
    }
    
    /**
     * Execute query and return result
     * 
     * @param string $sql SQL query
     * @param array $params Query parameters [types, ...values]
     * @param string $connection Connection name
     * @return mysqli_result|bool
     */
    public static function execute(string $sql, array $params = [], string $connection = 'default'): mysqli_result|bool
    {
        $startTime = microtime(true);
        
        try {
            $mysqli = self::connection($connection);
            
            if (empty($params)) {
                // Simple query without parameters
                $result = $mysqli->query($sql);
            } else {
                // Prepared statement with parameters
                $stmt = $mysqli->prepare($sql);
                
                if (!$stmt) {
                    throw new \Exception("Failed to prepare statement: " . $mysqli->error);
                }
                
                // Extract types and values
                $types = array_shift($params);
                
                if (!empty($params)) {
                    $stmt->bind_param($types, ...$params);
                }
                
                $stmt->execute();
                
                $result = $stmt->get_result();
                
                // If no result set (INSERT/UPDATE/DELETE), return true
                if ($result === false) {
                    $result = true;
                }
            }
            
            if (self::$logQueries) {
                self::logQuery($sql, $params, microtime(true) - $startTime);
            }
            
            return $result;
        } catch (\Exception $e) {
            error_log("Query failed: " . $e->getMessage() . " | SQL: " . $sql);
            throw $e;
        }
    }
    
    /**
     * Insert row and return last insert ID
     * 
     * @param string $table Table name
     * @param array $data Data to insert
     * @param string $connection Connection name
     * @return int Last insert ID
     */
    public static function insert(string $table, array $data, string $connection = 'default'): int
    {
        $columns = array_keys($data);
        $values = array_values($data);
        $placeholders = array_fill(0, count($columns), '?');
        
        $sql = sprintf(
            'INSERT INTO `%s` (`%s`) VALUES (%s)',
            $table,
            implode('`, `', $columns),
            implode(', ', $placeholders)
        );
        
        // Build type string
        $types = '';
        foreach ($values as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        
        $params = array_merge([$types], $values);
        self::execute($sql, $params, $connection);
        
        return (int)self::connection($connection)->insert_id;
    }
    
    /**
     * Update rows
     * 
     * @param string $table Table name
     * @param array $data Data to update
     * @param array $where Where conditions
     * @param string $connection Connection name
     * @return int Number of affected rows
     */
    public static function update(string $table, array $data, array $where, string $connection = 'default'): int
    {
        $setParts = [];
        foreach (array_keys($data) as $column) {
            $setParts[] = "`{$column}` = ?";
        }
        
        $whereParts = [];
        foreach (array_keys($where) as $column) {
            $whereParts[] = "`{$column}` = ?";
        }
        
        $sql = sprintf(
            'UPDATE `%s` SET %s WHERE %s',
            $table,
            implode(', ', $setParts),
            implode(' AND ', $whereParts)
        );
        
        // Build type string and values
        $allValues = array_merge(array_values($data), array_values($where));
        $types = '';
        foreach ($allValues as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        
        $params = array_merge([$types], $allValues);
        self::execute($sql, $params, $connection);
        
        return self::connection($connection)->affected_rows;
    }
    
    /**
     * Delete rows
     * 
     * @param string $table Table name
     * @param array $where Where conditions
     * @param string $connection Connection name
     * @return int Number of affected rows
     */
    public static function delete(string $table, array $where, string $connection = 'default'): int
    {
        $whereParts = [];
        foreach (array_keys($where) as $column) {
            $whereParts[] = "`{$column}` = ?";
        }
        
        $sql = sprintf(
            'DELETE FROM `%s` WHERE %s',
            $table,
            implode(' AND ', $whereParts)
        );
        
        // Build type string and values
        $values = array_values($where);
        $types = '';
        foreach ($values as $value) {
            if (is_int($value)) {
                $types .= 'i';
            } elseif (is_float($value)) {
                $types .= 'd';
            } else {
                $types .= 's';
            }
        }
        
        $params = array_merge([$types], $values);
        self::execute($sql, $params, $connection);
        
        return self::connection($connection)->affected_rows;
    }
    
    /**
     * Begin transaction
     * 
     * @param string $connection Connection name
     * @return void
     */
    public static function beginTransaction(string $connection = 'default'): void
    {
        self::connection($connection)->begin_transaction();
    }
    
    /**
     * Commit transaction
     * 
     * @param string $connection Connection name
     * @return void
     */
    public static function commit(string $connection = 'default'): void
    {
        self::connection($connection)->commit();
    }
    
    /**
     * Rollback transaction
     * 
     * @param string $connection Connection name
     * @return void
     */
    public static function rollback(string $connection = 'default'): void
    {
        self::connection($connection)->rollback();
    }
    
    /**
     * Enable query logging
     * 
     * @param bool $enable Enable logging
     * @return void
     */
    public static function enableQueryLog(bool $enable = true): void
    {
        self::$logQueries = $enable;
    }
    
    /**
     * Get query log
     * 
     * @return array
     */
    public static function getQueryLog(): array
    {
        return self::$queryLog;
    }
    
    /**
     * Clear query log
     * 
     * @return void
     */
    public static function clearQueryLog(): void
    {
        self::$queryLog = [];
    }
    
    /**
     * Log query for debugging
     * 
     * @param string $sql SQL query
     * @param array $params Parameters
     * @param float $time Execution time
     * @return void
     */
    private static function logQuery(string $sql, array $params, float $time): void
    {
        self::$queryLog[] = [
            'sql' => $sql,
            'params' => $params,
            'time' => $time,
            'timestamp' => microtime(true)
        ];
        
        // Keep log size under control
        if (count(self::$queryLog) > self::$maxLogSize) {
            array_shift(self::$queryLog);
        }
    }
    
    /**
     * Get last executed query (for debugging)
     * 
     * @return array|null
     */
    public static function getLastQuery(): ?array
    {
        return empty(self::$queryLog) ? null : end(self::$queryLog);
    }
    
    /**
     * Escape string (for legacy code compatibility)
     * 
     * @param string $value Value to escape
     * @param string $connection Connection name
     * @return string
     */
    public static function escape(string $value, string $connection = 'default'): string
    {
        return self::connection($connection)->real_escape_string($value);
    }
}
