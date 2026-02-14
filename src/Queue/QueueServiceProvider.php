<?php

declare(strict_types=1);

namespace CFXP\Core\Queue;

use CFXP\Core\Container\ContainerInterface;
use CFXP\Core\ServiceProviderInterface;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Service provider for the queue system.
 */
class QueueServiceProvider implements ServiceProviderInterface
{
    public function register(ContainerInterface $container): void
    {
        $container->singleton(QueueManager::class, function (ContainerInterface $c) {
            // QueueManager will load its own config if not provided
            return new QueueManager($c);
        });
    }

    public function boot(ContainerInterface $container, ?EventDispatcherInterface $dispatcher = null): void
    {
        // Nothing to boot
    }
}

