<?php

declare(strict_types=1);

namespace Denosys\Queue;

use Denosys\Container\ContainerInterface;

/**
 * Runs legacy jobs and supplies runtime services to payload-only jobs.
 */
final readonly class JobRunner
{
    public function __construct(private ContainerInterface $container)
    {
    }

    public function run(Job $job): void
    {
        if ($job instanceof ContainerAwareJob) {
            $job->handleWithContainer($this->container);

            return;
        }

        $job->handle();
    }
}
