<?php

declare(strict_types=1);

namespace GacelaTest\Feature\Console\ProfileReport;

use Gacela\Console\Infrastructure\Command\ProfileReportCommand;
use PHPUnit\Framework\TestCase;

final class ProfileReportCommandTest extends TestCase
{
    public function test_help_describes_manual_instrumentation_not_automatic_tracking(): void
    {
        $help = (new ProfileReportCommand())->getHelp();

        // The framework does not instrument itself, so the help must not
        // promise automatic tracking (which always yields an empty report).
        self::assertStringNotContainsString('automatically tracked', $help);

        // It must show a concrete manual start()/stop() example instead.
        self::assertStringContainsString("\$profiler->start('db-query', 'users')", $help);
        self::assertStringContainsString("\$profiler->stop('db-query', 'users')", $help);
    }
}
