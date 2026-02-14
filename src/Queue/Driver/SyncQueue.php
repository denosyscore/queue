<?php

declare(strict_types=1);

namespace Denosys\Queue\Driver;

use Denosys\Queue\Job;
use Denosys\Queue\QueueInterface;

/**
 * Synchronous queue driver.
 * 
 * Executes jobs immediately. Useful for development and testing.
 */
class SyncQueue implements QueueInterface
{
    /**
     * Push a job - executes immediately.
     */
    public function push(Job $job): string
    {
        $this->executeJob($job);
        return $job->uuid();
    }

    /**
     * Push with delay - still executes immediately in sync mode.
     */
    public function later(int $delay, Job $job): string
    {
        return $this->push($job);
    }

    /**
     * Pop returns null - sync queue has no pending jobs.
     */
    public function pop(string $queue = 'default'): ?array
    {
        return null;
    }

    /**
     * Delete is a no-op for sync.
     */
    public function delete(string|int $id): void
    {
        // No-op
    }

    /**
     * Release is a no-op for sync.
     */
    public function release(string|int $id, int $delay = 0): void
    {
        // No-op
    }

    /**
     * Size is always 0 for sync.
     */
    public function size(string $queue = 'default'): int
    {
        return 0;
    }

    /**
     * Clear is a no-op for sync.
     */
    public function clear(string $queue = 'default'): void
    {
        // No-op
    }

    /**
     * Execute a job immediately.
     */
    protected function executeJob(Job $job): void
    {
        try {
            $job->handle();
        } catch (\Throwable $e) {
            $job->failed($e);
            throw $e;
        }
    }
}
