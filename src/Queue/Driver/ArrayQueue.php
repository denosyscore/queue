<?php

declare(strict_types=1);

namespace CFXP\Core\Queue\Driver;

use CFXP\Core\Queue\Job;
use CFXP\Core\Queue\QueueInterface;

/**
 * In-memory array queue for testing.
 */
class ArrayQueue implements QueueInterface
{
    /** @var array<string, array<int, array{id: int, job: Job, available_at: int, attempts: int}>> */
    /** @var array<string, mixed> */

    protected array $jobs = [];

    protected int $nextId = 1;

    /**
     * Push a job.
     */
    public function push(Job $job): string
    {
        return $this->pushToQueue($job, $job->delay());
    }

    /**
     * Push with delay.
     */
    public function later(int $delay, Job $job): string
    {
        return $this->pushToQueue($job, $delay);
    }

    /**
     * Pop the next available job.
     */
    public function pop(string $queue = 'default'): ?array
    {
        if (!isset($this->jobs[$queue])) {
            return null;
        }

        $now = time();

        foreach ($this->jobs[$queue] as $index => $item) {
            if ($item['available_at'] <= $now) {
                unset($this->jobs[$queue][$index]);
                $this->jobs[$queue] = array_values($this->jobs[$queue]);

                return [
                    'id' => $item['id'],
                    'job' => $item['job'],
                    'attempts' => $item['attempts'] + 1,
                ];
            }
        }

        return null;
    }

    /**
     * Delete a job.
     */
    public function delete(string|int $id): void
    {
        foreach ($this->jobs as $queue => $jobs) {
            foreach ($jobs as $index => $item) {
                if ($item['id'] === $id) {
                    unset($this->jobs[$queue][$index]);
                    $this->jobs[$queue] = array_values($this->jobs[$queue]);
                    return;
                }
            }
        }
    }

    /**
     * Release a job back.
     */
    public function release(string|int $id, int $delay = 0): void
    {
        foreach ($this->jobs as $queue => $jobs) {
            foreach ($jobs as $index => $item) {
                if ($item['id'] === $id) {
                    $this->jobs[$queue][$index]['available_at'] = time() + $delay;
                    return;
                }
            }
        }
    }

    /**
     * Get queue size.
     */
    public function size(string $queue = 'default'): int
    {
        return count($this->jobs[$queue] ?? []);
    }

    /**
     * Clear a queue.
     */
    public function clear(string $queue = 'default'): void
    {
        $this->jobs[$queue] = [];
    }

    /**
     * Push to internal queue.
     */
    protected function pushToQueue(Job $job, int $delay): string
    {
        $queue = $job->queue();
        $id = $this->nextId++;

        if (!isset($this->jobs[$queue])) {
            $this->jobs[$queue] = [];
        }

        $this->jobs[$queue][] = [
            'id' => $id,
            'job' => $job,
            'available_at' => time() + $delay,
            'attempts' => 0,
        ];

        return $job->uuid();
    }
}
