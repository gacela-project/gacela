<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan;

use PHPUnit\Framework\TestCase;

use function escapeshellarg;
use function getenv;
use function putenv;
use function shell_exec;
use function sprintf;

/**
 * Runs the shipped `phpstan-gacela.neon` for real, over `Fixture/`.
 *
 * That is the strongest proof available -- a unit test drives an extension,
 * this drives PHPStan -- but it is also the slowest, so the run is shared: every
 * test here analyses the same fixture set with the same config, so the output is
 * the same and analysing once per process is enough.
 */
abstract class PhpStanFixtureTestCase extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    private static ?string $output = null;

    final protected function analyseFixture(): string
    {
        return self::$output ??= $this->runPhpStan();
    }

    private function runPhpStan(): string
    {
        $command = sprintf(
            '%s analyse -c %s --memory-limit=1G --no-progress --error-format=raw 2>&1',
            escapeshellarg(self::ROOT . '/vendor/bin/phpstan'),
            escapeshellarg(__DIR__ . '/Fixture/phpstan-fixture.neon'),
        );

        // Through putenv rather than a `VAR=value cmd` prefix, which is posix
        // shell syntax that windows does not understand -- there `XDEBUG_MODE`
        // is read as the command itself.
        //
        // It has to be off at all: this is a child process, so under
        // `composer test-coverage` or infection it inherits
        // XDEBUG_MODE=coverage, and PHPStan under coverage exhausts the default
        // memory limit and dies, failing tests that never ran their assertions.
        $previous = getenv('XDEBUG_MODE');
        putenv('XDEBUG_MODE=off');

        try {
            return (string)shell_exec($command);
        } finally {
            putenv($previous === false ? 'XDEBUG_MODE' : 'XDEBUG_MODE=' . $previous);
        }
    }
}
