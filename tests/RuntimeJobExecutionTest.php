<?php

declare(strict_types=1);

namespace Tests;

use Denosys\Container\Container;
use Denosys\Container\ContainerInterface;
use Denosys\Database\Connection\Connection;
use Denosys\Database\Grammar\SqliteGrammar;
use Denosys\Database\Query\Builder;
use Denosys\Queue\ContainerAwareJob;
use Denosys\Queue\Driver\DatabaseQueue;
use Denosys\Queue\Driver\SyncQueue;
use Denosys\Queue\Job;
use Denosys\Queue\JobRunner;
use Denosys\Queue\QueueManager;
use PHPUnit\Framework\TestCase;
use PDO;

final class DeliveryRecorder
{
    /** @var list<string> */
    public array $messages = [];

    public function record(string $message): void
    {
        $this->messages[] = $message;
    }
}

final class PayloadOnlyJob extends ContainerAwareJob
{
    public function __construct(private string $message)
    {
        parent::__construct();
    }

    public function handleWithContainer(ContainerInterface $container): void
    {
        $container->get(DeliveryRecorder::class)->record($this->message);
    }
}

final class LegacyJob extends Job
{
    public bool $handled = false;

    public function handle(): void
    {
        $this->handled = true;
    }
}

final class CompetingClaimConnection extends Connection
{
    public bool $claimBeforeUpdate = false;

    public function query(): Builder
    {
        return new CompetingClaimBuilder($this);
    }

    public function claimFromAnotherWorker(int $id): void
    {
        if (!$this->claimBeforeUpdate) {
            return;
        }

        $this->claimBeforeUpdate = false;
        $this->getPdo()->prepare('UPDATE jobs SET reserved_at = ?, attempts = attempts + 1 WHERE id = ?')
            ->execute([time(), $id]);
    }
}

final class CompetingClaimBuilder extends Builder
{
    public function first(): ?object
    {
        $row = parent::first();

        if ($row !== null && $this->connection instanceof CompetingClaimConnection) {
            $this->connection->claimFromAnotherWorker((int) $row->id);
        }

        return $row;
    }
}

final class RuntimeJobExecutionTest extends TestCase
{
    public function testSerializedJobResolvesRuntimeServiceAfterRoundTrip(): void
    {
        $container = new Container();
        $recorder = new DeliveryRecorder();
        $container->bind(DeliveryRecorder::class, static fn (): DeliveryRecorder => $recorder);

        $restored = unserialize(serialize(new PayloadOnlyJob('queued message')));
        self::assertInstanceOf(PayloadOnlyJob::class, $restored);
        self::assertStringNotContainsString(DeliveryRecorder::class, serialize($restored));

        (new JobRunner($container))->run($restored);

        self::assertSame(['queued message'], $recorder->messages);
    }

    public function testSynchronousQueueUsesSameRuntimeServiceContract(): void
    {
        $container = new Container();
        $recorder = new DeliveryRecorder();
        $container->bind(DeliveryRecorder::class, static fn (): DeliveryRecorder => $recorder);
        $queue = new QueueManager($container, [
            'default' => 'sync',
            'connections' => ['sync' => ['driver' => 'sync']],
        ]);

        $queue->dispatch(new PayloadOnlyJob('sync message'));

        self::assertSame(['sync message'], $recorder->messages);
    }

    public function testDatabaseQueueRoundTripKeepsServicesOutOfPayload(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE jobs (id INTEGER PRIMARY KEY AUTOINCREMENT, queue TEXT NOT NULL, payload TEXT NOT NULL, attempts INTEGER NOT NULL, reserved_at INTEGER NULL, available_at INTEGER NOT NULL, created_at INTEGER NOT NULL)');
        $connection = new Connection($pdo, new SqliteGrammar());
        $queue = new DatabaseQueue($connection);
        $queue->push(new PayloadOnlyJob('database message'));

        $stored = $pdo->query('SELECT payload FROM jobs')->fetchColumn();
        self::assertIsString($stored);
        self::assertStringNotContainsString(DeliveryRecorder::class, $stored);

        $container = new Container();
        $recorder = new DeliveryRecorder();
        $container->bind(DeliveryRecorder::class, static fn (): DeliveryRecorder => $recorder);
        $popped = $queue->pop();
        self::assertNotNull($popped);
        $queue->release($popped['id']);
        $popped = $queue->pop();
        self::assertNotNull($popped);
        self::assertSame(2, $popped['attempts']);

        (new JobRunner($container))->run($popped['job']);
        $queue->delete($popped['id']);

        self::assertSame(['database message'], $recorder->messages);
        self::assertSame(0, $queue->size());
    }

    public function testExistingHandleJobsStillExecute(): void
    {
        $job = new LegacyJob();
        (new JobRunner(new Container()))->run($job);

        self::assertTrue($job->handled);

        $direct = new LegacyJob();
        (new SyncQueue())->push($direct);

        self::assertTrue($direct->handled);
    }

    public function testExpiredReservationCanBeClaimedAgain(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE jobs (id INTEGER PRIMARY KEY AUTOINCREMENT, queue TEXT NOT NULL, payload TEXT NOT NULL, attempts INTEGER NOT NULL, reserved_at INTEGER NULL, available_at INTEGER NOT NULL, created_at INTEGER NOT NULL)');
        $queue = new DatabaseQueue(new Connection($pdo, new SqliteGrammar()), retryAfter: 30);
        $queue->push(new PayloadOnlyJob('recover me'));

        $first = $queue->pop();
        self::assertNotNull($first);
        self::assertNull($queue->pop());

        $pdo->exec('UPDATE jobs SET reserved_at = ' . (time() - 31));
        $reclaimed = $queue->pop();

        self::assertNotNull($reclaimed);
        self::assertSame($first['id'], $reclaimed['id']);
        self::assertSame(2, $reclaimed['attempts']);
    }

    public function testCompetingClaimCannotReturnTheSameJob(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE jobs (id INTEGER PRIMARY KEY AUTOINCREMENT, queue TEXT NOT NULL, payload TEXT NOT NULL, attempts INTEGER NOT NULL, reserved_at INTEGER NULL, available_at INTEGER NOT NULL, created_at INTEGER NOT NULL)');
        $connection = new CompetingClaimConnection($pdo, new SqliteGrammar());
        $queue = new DatabaseQueue($connection);
        $queue->push(new PayloadOnlyJob('claim once'));
        $connection->claimBeforeUpdate = true;

        self::assertNull($queue->pop());
        self::assertSame(1, (int) $pdo->query('SELECT attempts FROM jobs')->fetchColumn());
    }

    public function testQueueManagerPassesConfiguredReservationLease(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE jobs (id INTEGER PRIMARY KEY AUTOINCREMENT, queue TEXT NOT NULL, payload TEXT NOT NULL, attempts INTEGER NOT NULL, reserved_at INTEGER NULL, available_at INTEGER NOT NULL, created_at INTEGER NOT NULL)');
        $connection = new Connection($pdo, new SqliteGrammar());
        $container = new Container();
        $container->bind(Connection::class, static fn (): Connection => $connection);
        $manager = new QueueManager($container, [
            'default' => 'database',
            'connections' => ['database' => ['driver' => 'database', 'retry_after' => 2]],
        ]);
        $manager->dispatch(new PayloadOnlyJob('leased message'));
        $queue = $manager->connection();

        self::assertNotNull($queue->pop());
        $pdo->exec('UPDATE jobs SET reserved_at = ' . (time() - 3));

        self::assertSame(2, $queue->pop()['attempts'] ?? null);
    }
}
