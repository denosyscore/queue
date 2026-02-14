<?php

declare(strict_types=1);

namespace Denosys\Queue;

/**
 * Contract for queue drivers.
 */
interface QueueInterface
{
    /**
     * Push a job onto the queue.
     */
    public function push(Job $job): string;

    /**
     * Push a job with a delay.
     */
    public function later(int $delay, Job $job): string;

    /**
     * Pop the next job from the queue.
     * 
     * @return array{id: string|int, job: Job, attempts: int}|null
     */
    public function pop(string $queue = 'default'): ?array;

    /**
     * Delete a job from the queue.
     */
    public function delete(string|int $id): void;

    /**
     * Release a job back onto the queue.
     */
    public function release(string|int $id, int $delay = 0): void;

    /**
     * Get the size of a queue.
     */
    public function size(string $queue = 'default'): int;

    /**
     * Clear all jobs from a queue.
     */
    public function clear(string $queue = 'default'): void;
}
