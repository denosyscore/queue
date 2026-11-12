<?php

declare(strict_types=1);

namespace Tests;

use Denosys\Console\Application;
use Denosys\Queue\Console\QueueWorkCommand;
use Denosys\Container\Container;
use Denosys\Container\ContainerInterface;
use Denosys\Database\Connection\Connection;
use Denosys\Database\Grammar\SqliteGrammar;
use Denosys\Queue\ContainerAwareJob;
use Denosys\Queue\Job;
use Denosys\Queue\QueueManager;
use PDO;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

final class RuntimeRecorder
{
    /** @var list<string> */
    public array $messages = [];
}

final class RuntimeWorkerJob extends ContainerAwareJob
{
    public function __construct(private string $message)
    {
        parent::__construct();
    }

    public function handleWithContainer(ContainerInterface $container): void
    {
        $container->get(RuntimeRecorder::class)->messages[] = $this->message;
    }
}

final class LegacyWorkerJob extends Job
{
    public static bool $handled = false;

    public function handle(): void
    {
        self::$handled = true;
    }
}

final class QueueWorkCommandTest extends TestCase
{
    public function testDatabaseWorkerRunsRuntimeAndLegacyJobs(): void
    {
        $pdo = new PDO('sqlite::memory:');
        $pdo->exec('CREATE TABLE jobs (id INTEGER PRIMARY KEY AUTOINCREMENT, queue TEXT NOT NULL, payload TEXT NOT NULL, attempts INTEGER NOT NULL, reserved_at INTEGER NULL, available_at INTEGER NOT NULL, created_at INTEGER NOT NULL)');
        $connection = new Connection($pdo, new SqliteGrammar());
        $container = new Container();
        $recorder = new RuntimeRecorder();
        $container->bind(Connection::class, static fn (): Connection => $connection);
        $container->bind(RuntimeRecorder::class, static fn (): RuntimeRecorder => $recorder);
        $manager = new QueueManager($container, [
            'default' => 'database',
            'connections' => ['database' => ['driver' => 'database', 'table' => 'jobs']],
        ]);
        $container->bind(QueueManager::class, static fn (): QueueManager => $manager);
        $queue = $manager->connection();

        $queue->push(new RuntimeWorkerJob('runtime'));
        self::assertSame(0, $this->runOnce($container));
        self::assertSame(['runtime'], $recorder->messages);
        self::assertSame(0, $queue->size());

        LegacyWorkerJob::$handled = false;
        $queue->push(new LegacyWorkerJob());
        self::assertSame(0, $this->runOnce($container));
        self::assertTrue(LegacyWorkerJob::$handled);
        self::assertSame(0, $queue->size());
    }

    private function runOnce(Container $container): int
    {
        $console = new Application($container);
        $console->add(new QueueWorkCommand($container));

        return $console->getSymfonyApplication()->run(
            new ArrayInput(['command' => 'queue:work', '--once' => true]),
            new BufferedOutput(),
        );
    }
}
