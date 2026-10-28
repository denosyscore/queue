<?php

declare(strict_types=1);

namespace Denosys\Queue;

use Denosys\Container\ContainerInterface;
use LogicException;

/**
 * Base for payload-only jobs that resolve services when they execute.
 */
abstract class ContainerAwareJob extends Job
{
    final public function handle(): void
    {
        throw new LogicException('Container-aware jobs must run through JobRunner.');
    }

    abstract public function handleWithContainer(ContainerInterface $container): void;
}
