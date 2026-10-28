<?php

declare(strict_types=1);

namespace Denosys\Queue\Driver;

use Denosys\Database\Connection\Connection;
use Denosys\Database\Query\Builder;
use Denosys\Queue\Job;
use Denosys\Queue\QueueInterface;
use InvalidArgumentException;

/**
 * Database queue driver.
 */
class DatabaseQueue implements QueueInterface
{
    protected string $table = 'jobs';
    protected string $failedTable = 'failed_jobs';

    public function __construct(
        protected Connection $connection,
        ?string $table = null,
        ?string $failedTable = null,
        protected int $retryAfter = 90,
    ) {
        if ($retryAfter < 1) {
            throw new InvalidArgumentException('The database queue retry_after must be positive.');
        }

        if ($table !== null) {
            $this->table = $table;
        }
        if ($failedTable !== null) {
            $this->failedTable = $failedTable;
        }
    }

    /**
     * Push a job onto the queue.
     */
    public function push(Job $job): string
    {
        return $this->pushToDatabase($job, $job->delay());
    }

    /**
     * Push a job with a delay.
     */
    public function later(int $delay, Job $job): string
    {
        return $this->pushToDatabase($job, $delay);
    }

    /**
     * Pop the next available job from the queue.
     */
    public function pop(string $queue = 'default'): ?array
    {
        for ($claim = 0; $claim < 5; $claim++) {
            $now = time();
            $expiredBefore = $now - $this->retryAfter;
            $job = $this->connection->table($this->table)
                ->where('queue', $queue)
                ->where('available_at', '<=', $now)
                ->where(static function (Builder $query) use ($expiredBefore): void {
                    $query->whereNull('reserved_at')->orWhere('reserved_at', '<=', $expiredBefore);
                })
                ->orderBy('id')
                ->first();

            if ($job === null) {
                return null;
            }

            $job = (array) $job;
            $update = $this->connection->table($this->table)
                ->where('id', $job['id'])
                ->where('queue', $queue)
                ->where('available_at', '<=', $now)
                ->where('attempts', (int) $job['attempts']);

            if ($job['reserved_at'] === null) {
                $update->whereNull('reserved_at');
            } else {
                $update->where('reserved_at', (int) $job['reserved_at'])
                    ->where('reserved_at', '<=', $expiredBefore);
            }

            $attempts = (int) $job['attempts'] + 1;
            if ($update->update(['reserved_at' => $now, 'attempts' => $attempts]) !== 1) {
                continue;
            }

            $payload = json_decode($job['payload'], true);
            $jobInstance = unserialize($payload['data']);

            return ['id' => $job['id'], 'job' => $jobInstance, 'attempts' => $attempts];
        }

        return null;
    }

    /**
     * Delete a processed job.
     */
    public function delete(string|int $id): void
    {
        $this->connection->table($this->table)
            ->where('id', $id)
            ->delete();
    }

    /**
     * Release a job back onto the queue.
     */
    public function release(string|int $id, int $delay = 0): void
    {
        $this->connection->table($this->table)
            ->where('id', $id)
            ->update([
                'reserved_at' => null,
                'available_at' => time() + $delay,
            ]);
    }

    /**
     * Get the size of a queue.
     */
    public function size(string $queue = 'default'): int
    {
        return (int) $this->connection->table($this->table)
            ->where('queue', $queue)
            ->count();
    }

    /**
     * Clear all jobs from a queue.
     */
    public function clear(string $queue = 'default'): void
    {
        $this->connection->table($this->table)
            ->where('queue', $queue)
            ->delete();
    }

    /**
     * Mark a job as failed.
     */
    public function fail(string|int $id, Job $job, \Throwable $exception): void
    {
        $this->connection->table($this->failedTable)->insert([
            'uuid' => $job->uuid(),
            'connection' => 'database',
            'queue' => $job->queue(),
            'payload' => json_encode([
                'uuid' => $job->uuid(),
                'job' => get_class($job),
                'data' => serialize($job),
            ]),
            'exception' => $exception->getMessage() . "\n" . $exception->getTraceAsString(),
            'failed_at' => date('Y-m-d H:i:s'),
        ]);

        $this->delete($id);
    }

    /**
     * Push a job to the database.
     */
    protected function pushToDatabase(Job $job, int $delay = 0): string
    {
        $uuid = $job->uuid();
        $now = time();

        $this->connection->table($this->table)->insert([
            'queue' => $job->queue(),
            'payload' => json_encode([
                'uuid' => $uuid,
                'job' => get_class($job),
                'data' => serialize($job),
            ]),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => $now + $delay,
            'created_at' => $now,
        ]);

        return $uuid;
    }
}
