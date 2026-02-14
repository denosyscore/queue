<?php

declare(strict_types=1);

namespace Denosys\Queue;

use Denosys\Container\ContainerInterface;
use Denosys\Database\Connection\Connection;
use Denosys\Database\Connection\ConnectionManager;
use Denosys\Queue\Driver\ArrayQueue;
use Denosys\Queue\Driver\DatabaseQueue;
use Denosys\Queue\Driver\SyncQueue;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Queue manager for dispatching jobs.
 */
class QueueManager
{
    /** @var array<string, QueueInterface> */
protected array $connections = [];

    /**
     * @param array<string, mixed> $config
     * @param array<string, mixed> $config
     */
    protected string $default = 'sync';

    /** @var array<string, mixed> */
protected array $config = [];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(
        /**
         * @param array<string, mixed> $config
         */
        protected ContainerInterface $container,
        array $config = []
    ) {
        // If no config provided, load from file
        if (empty($config)) {
            $basePath = $container->has('path.base') ? $container->get('path.base') : dirname(__DIR__, 2);
            $configFile = $basePath . '/config/queue.php';
            if (file_exists($configFile)) {
                $config = require $configFile;
            }
        }
        
        $this->config = $config;
        $this->default = $config['default'] ?? 'sync';
    }

    /**
     * Get a queue connection instance.
     */
    public function connection(?string $name = null): QueueInterface
    {
        $name = $name ?? $this->default;

        if (!isset($this->connections[$name])) {
            $this->connections[$name] = $this->resolve($name);
        }

        return $this->connections[$name];
    }

    /**
     * Push a job onto the queue.
     */
    public function push(Job $job): string
    {
        return $this->connection()->push($job);
    }

    /**
     * Push a job with a delay.
     */
    public function later(int $delay, Job $job): string
    {
        return $this->connection()->later($delay, $job);
    }

    /**
     * Dispatch a job.
     */
    public function dispatch(Job $job): string
    {
        if ($job->delay() > 0) {
            return $this->later($job->delay(), $job);
        }

        return $this->push($job);
    }

    /**
     * Get the default driver name.
     */
    public function getDefaultDriver(): string
    {
        return $this->default;
    }

    /**
     * Resolve a queue connection.
     */
    protected function resolve(string $name): QueueInterface
    {
        $config = $this->config['connections'][$name] ?? [];
        $driver = $config['driver'] ?? $name;

        return match ($driver) {
            'sync' => $this->createSyncDriver(),
            'database' => $this->createDatabaseDriver($config),
            'array' => $this->createArrayDriver(),
            default => throw new InvalidArgumentException("Unsupported queue driver: {$driver}"),
        };
    }

    protected function createSyncDriver(): SyncQueue
    {
        return new SyncQueue();
    }

    /**
     * @param array<string, mixed> $config
     */
    protected function createDatabaseDriver(array $config): DatabaseQueue
    {
        $connection = $this->resolveDatabaseConnection();
        
        return new DatabaseQueue(
            $connection,
            $config['table'] ?? 'jobs',
            $config['failed_table'] ?? 'failed_jobs'
        );
    }

    private function resolveDatabaseConnection(): Connection
    {
        try {
            $manager = $this->container->get(ConnectionManager::class);

            if ($manager instanceof ConnectionManager) {
                return $manager->connection();
            }
        } catch (Throwable) {
            // Try direct connection fallback next.
        }

        try {
            $connection = $this->container->get(Connection::class);

            if ($connection instanceof Connection) {
                return $connection;
            }
        } catch (Throwable) {
            // Fall through to clear runtime exception.
        }

        throw new RuntimeException(
            'Database queue driver requires Connection or ConnectionManager to be bound in the container.'
        );
    }

    protected function createArrayDriver(): ArrayQueue
    {
        return new ArrayQueue();
    }
}
