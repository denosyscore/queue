<?php

declare(strict_types=1);

namespace Denosys\Queue;

/**
 * Interface marking classes as queueable jobs.
 */
interface ShouldQueue
{
    /**
     * Get the queue name for this job.
     */
    public function queue(): string;

    /**
     * Get the delay in seconds before processing.
     */
    public function delay(): int;

    /**
     * Get the maximum number of attempts.
     */
    public function tries(): int;
}
