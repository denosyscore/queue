<?php

declare(strict_types=1);

namespace Denosys\Queue\Console;

use Denosys\Console\CommandDefinition;
use Denosys\Console\CommandInterface;
use Denosys\Console\InputInterface;
use Denosys\Console\OutputInterface;
use Denosys\Container\ContainerInterface;
use Denosys\Queue\Driver\DatabaseQueue;
use Denosys\Queue\JobRunner;
use Denosys\Queue\QueueManager;

/**
 * Process jobs from the queue.
 */
class QueueWorkCommand implements CommandInterface
{
    public function __construct(
        protected ContainerInterface $container
    ) {}

    public function getName(): string
    {
        return 'queue:work';
    }

    public function getDescription(): string
    {
        return 'Start processing jobs on the queue';
    }

    public function configure(): CommandDefinition
    {
        return (new CommandDefinition())
            ->addOption('queue', null, CommandDefinition::OPTION_OPTIONAL, 'The queue to process', 'default')
            ->addOption('once', null, CommandDefinition::OPTION_NONE, 'Process only one job then exit')
            ->addOption('sleep', 's', CommandDefinition::OPTION_OPTIONAL, 'Seconds to sleep when no job available', '3')
            ->addOption('tries', 't', CommandDefinition::OPTION_OPTIONAL, 'Max attempts before failing job', '3')
            ->setHelp(<<<HELP
Start processing jobs from the queue.

Examples:
  php core queue:work                    # Process jobs continuously
  php core queue:work --once             # Process one job then exit
  php core queue:work --queue=emails     # Process specific queue
HELP);
    }

    public function execute(InputInterface $input, OutputInterface $output): int
    {
        $queueName = $input->getOption('queue') ?: 'default';
        $once = $input->getOption('once');
        $sleep = (int) ($input->getOption('sleep') ?: 3);
        $maxTries = (int) ($input->getOption('tries') ?: 3);

        $output->info("Processing jobs from queue: {$queueName}");
        $output->comment("Press Ctrl+C to stop");
        $output->newLine();

        $queueManager = $this->container->get(QueueManager::class);
        $queue = $queueManager->connection();
        $runner = new JobRunner($this->container);

        while (true) {
            $jobData = $queue->pop($queueName);

            if ($jobData === null) {
                if ($once) {
                    $output->comment("No jobs available.");
                    return 0;
                }

                sleep($sleep);
                continue;
            }

            $job = $jobData['job'];
            $id = $jobData['id'];
            $attempts = $jobData['attempts'];

            $displayName = $job->displayName();
            $output->write("[" . date('Y-m-d H:i:s') . "] Processing: {$displayName}...");

            try {
                $runner->run($job);
                $queue->delete($id);
                $output->writeln(" <fg=green>DONE</>");
            } catch (\Throwable $e) {
                $output->writeln(" <fg=red>FAILED</>");
                $output->error($e->getMessage());

                if ($attempts >= $maxTries) {
                    $output->comment("Max attempts reached. Moving to failed jobs.");
                    $job->failed($e);
                    
                    if ($queue instanceof DatabaseQueue) {
                        $queue->fail($id, $job, $e);
                    } else {
                        $queue->delete($id);
                    }
                } else {
                    $output->comment("Releasing back to queue. Attempt {$attempts}/{$maxTries}");
                    $queue->release($id, $job->retryAfter());
                }
            }

            if ($once) {
                return 0;
            }
        }
    }
}
