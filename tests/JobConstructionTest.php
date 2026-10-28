<?php

declare(strict_types=1);

namespace Tests;

use Denosys\Queue\Job;
use PHPUnit\Framework\TestCase;

final class ConstructableJob extends Job
{
    public function handle(): void
    {
    }
}

final class JobConstructionTest extends TestCase
{
    public function testJobCanBeConstructedWithDeclaredDependencies(): void
    {
        $job = new ConstructableJob();

        self::assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', $job->uuid());
    }
}
