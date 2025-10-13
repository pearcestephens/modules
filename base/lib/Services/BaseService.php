<?php
declare(strict_types=1);

namespace Modules\Base\Services;

use Modules\Base\Events\EventDispatcher;
use PDO;

/**
 * Base Service - Foundation for all domain services
 * 
 * Provides common infrastructure including database access,
 * event dispatching, and logging capabilities.
 */
abstract class BaseService
{
    protected PDO $pdo;
    protected EventDispatcher $eventDispatcher;

    public function __construct(?PDO $pdo = null, ?EventDispatcher $eventDispatcher = null)
    {
        $this->pdo = $pdo ?? $this->getDefaultPdo();
        $this->eventDispatcher = $eventDispatcher ?? new EventDispatcher();
    }

    /**
     * Get default PDO connection
     */
    protected function getDefaultPdo(): PDO
    {
        // Use existing CIS PDO factory
        return \cis_pdo();
    }

    /**
     * Begin database transaction
     */
    protected function beginTransaction(): void
    {
        $this->pdo->beginTransaction();
    }

    /**
     * Commit database transaction
     */
    protected function commit(): void
    {
        $this->pdo->commit();
    }

    /**
     * Rollback database transaction
     */
    protected function rollback(): void
    {
        $this->pdo->rollback();
    }

    /**
     * Execute code within a transaction
     */
    protected function transactional(callable $callback): mixed
    {
        $this->beginTransaction();
        
        try {
            $result = $callback();
            $this->commit();
            return $result;
        } catch (\Throwable $e) {
            $this->rollback();
            throw $e;
        }
    }
}