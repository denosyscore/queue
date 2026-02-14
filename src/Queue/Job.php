<?php

declare(strict_types=1);

namespace CFXP\Core\Queue;

use Ramsey\Uuid\Uuid;

/**
 * Base class for queueable jobs.
 */
abstract class Job implements ShouldQueue
{
    /**
     * The queue this job should be sent to.
     */
    protected string $queue = 'default';

    /**
     * The number of seconds before the job should be processed.
     */
    protected int $delay = 0;

    /**
     * The number of times the job may be attempted.
     */
    protected int $tries = 3;

    /**
     * The number of seconds to wait before retrying.
     */
    protected int $retryAfter = 60;

    /**
     * The unique ID for this job.
     */
    protected string $uuid;

    /**
     * The number of attempts so far.
     */
    protected int $attempts = 0;

    public function __construct()
    {
        $this->uuid = Uuid::uuid4()->toString();
    }

    /**
     * Handle the job.
     */
    abstract public function handle(): void;

    /**
     * Get the queue name.
     */
    public function queue(): string
    {
        return $this->queue;
    }

    /**
     * Get the delay.
     */
    public function delay(): int
    {
        return $this->delay;
    }

    /**
     * Get the max tries.
     */
    public function tries(): int
    {
        return $this->tries;
    }

    /**
     * Get retry delay.
     */
    public function retryAfter(): int
    {
        return $this->retryAfter;
    }

    /**
     * Get the job UUID.
     */
    public function uuid(): string
    {
        return $this->uuid;
    }

    /**
     * Get the display name for this job.
     * Override in subclasses to provide a more descriptive name.
     */
    public function displayName(): string
    {
        return static::class;
    }

    /**
     * Get the number of attempts.
     */
    public function attempts(): int
    {
        return $this->attempts;
    }

    /**
     * Set the queue name.
     */
    public function onQueue(string $queue): static
    {
        $this->queue = $queue;
        return $this;
    }

    /**
     * Set the delay.
     */
    public function withDelay(int $seconds): static
    {
        $this->delay = $seconds;
        return $this;
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        // Override in subclass to handle failures
    }

    /**
     * Serialize the job for storage.
     */
    public function serialize(): string
    {
        return serialize($this);
    }

    /**
     * Unserialize a job from storage.
     */
    public static function unserialize(string $data): static
    {
        return unserialize($data);
    }
}
