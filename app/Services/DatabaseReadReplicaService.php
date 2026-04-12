<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;

/**
 * Database Read Replica Service
 * 
 * Manages database read/write splitting for scalability.
 * Routes reporting and analytics queries to read replicas
 * while keeping write operations on the primary database.
 */
class DatabaseReadReplicaService
{
    /**
     * Connection name for read replicas
     */
    private const READ_CONNECTION = 'pgsql_read';
    private const WRITE_CONNECTION = 'pgsql';

    /**
     * Execute query on read replica
     */
    public function read(\Closure $callback)
    {
        return DB::connection(self::READ_CONNECTION)->transaction(function () use ($callback) {
            return $callback(DB::connection(self::READ_CONNECTION));
        });
    }

    /**
     * Execute query on write connection (primary)
     */
    public function write(\Closure $callback)
    {
        return DB::connection(self::WRITE_CONNECTION)->transaction(function () use ($callback) {
            return $callback(DB::connection(self::WRITE_CONNECTION));
        });
    }

    /**
     * Get a model query builder configured for read replica
     */
    public function getReadBuilder(string $modelClass)
    {
        $model = new $modelClass;
        return $model->setConnection(self::READ_CONNECTION)->newQuery();
    }

    /**
     * Get a model query builder configured for write
     */
    public function getWriteBuilder(string $modelClass)
    {
        $model = new $modelClass;
        return $model->setConnection(self::WRITE_CONNECTION)->newQuery();
    }

    /**
     * Check if read replicas are available and healthy
     */
    public function areReadReplicasHealthy(): bool
    {
        try {
            DB::connection(self::READ_CONNECTION)->getPdo();
            return true;
        } catch (\Exception $e) {
            Log::warning('Read replica is not healthy', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get connection to use based on query type
     * Falls back to primary if read replica is unhealthy
     */
    public function getConnection(string $queryType = 'read'): string
    {
        if ($queryType === 'write') {
            return self::WRITE_CONNECTION;
        }

        // For read queries, use replica if healthy
        return $this->areReadReplicasHealthy() 
            ? self::READ_CONNECTION 
            : self::WRITE_CONNECTION;
    }

    /**
     * Configure read replica connections at runtime
     */
    public static function configureReadReplica(array $config): void
    {
        Config::set('database.connections.pgsql_read', [
            'driver'         => 'pgsql',
            'url'            => $config['url'] ?? null,
            'host'           => $config['host'] ?? '127.0.0.1',
            'port'           => $config['port'] ?? 5432,
            'database'       => $config['database'] ?? env('DB_DATABASE', 'laravel'),
            'username'       => $config['username'] ?? env('DB_USERNAME', 'root'),
            'password'       => $config['password'] ?? env('DB_PASSWORD', ''),
            'charset'        => 'utf8',
            'prefix'         => '',
            'prefix_indexes' => true,
            'search_path'    => 'public',
            'sslmode'        => 'prefer',
        ]);
    }

    /**
     * Get replica health status
     */
    public function getReplicaHealth(): array
    {
        $primaryHealthy = $this->isConnectionHealthy(self::WRITE_CONNECTION);
        $replicaHealthy = $this->isConnectionHealthy(self::READ_CONNECTION);

        return [
            'primary' => [
                'healthy' => $primaryHealthy,
                'connection' => self::WRITE_CONNECTION,
            ],
            'replica' => [
                'healthy' => $replicaHealthy,
                'connection' => self::READ_CONNECTION,
            ],
            'using_replica' => $replicaHealthy,
        ];
    }

    /**
     * Check if specific connection is healthy
     */
    private function isConnectionHealthy(string $connection): bool
    {
        try {
            DB::connection($connection)->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }
}
