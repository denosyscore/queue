# denosyscore/queue

Queue manager, drivers, and job contracts

## Status

Initial extraction snapshot from denosyscore monorepo as of 2026-02-14.

## Installation

composer require denosyscore/queue

## Included Modules

- src/Queue/*

## Runtime services for queued jobs

Extend `ContainerAwareJob` when a job needs a service after it has been read
from a queue. Store only serializable payload in the job. Implement
`handleWithContainer(ContainerInterface $container)` and resolve services at
execution time. `QueueManager` supplies a `JobRunner` to its synchronous
driver. A database queue worker must call `JobRunner::run($job)` after popping
the job; calling `handle()` directly on a container-aware job raises an error.
Existing jobs that implement `handle()` continue to run through the runner.

```php
$runner = new \Denosys\Queue\JobRunner($container);
$runner->run($popped['job']);
```

Do not serialize the container, mailers, connections, or other live services
inside job payloads. A separately constructed `SyncQueue` can accept a
`JobRunner` in its constructor when it must execute container-aware jobs.

## Database reservations

The database driver conditionally claims each available row so competing
workers cannot both take the same reservation. `retry_after` is the reservation
lease in seconds, defaults to 90, and must be positive. A job whose worker
stops before deleting or releasing it becomes available again after its lease
expires. Configure a lease longer than normal job execution and make jobs
idempotent: a worker can finish side effects but stop before deleting its row.

```php
'connections' => [
    'database' => [
        'driver' => 'database',
        'retry_after' => 120,
    ],
],
```

## Development

composer validate --strict
find src -type f -name '*.php' -print0 | xargs -0 -n1 php -l
composer test

## CI Workflows

- CI: composer validation, dependency install, PHPUnit tests, and PHP syntax
  lint on push and pull requests.
- Release: GitHub release publication on semantic version tags.
- Dependabot: weekly Composer dependency update checks.
