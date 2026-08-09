<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan;

use PHPUnit\Framework\TestCase;

use function escapeshellarg;
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
        // XDEBUG_MODE=off and an explicit memory limit, because this is a child
        // process: under `composer test-coverage` it would otherwise inherit
        // XDEBUG_MODE=coverage, and PHPStan under coverage exhausts the default
        // 128M and dies -- which made the whole coverage run unpassable rather
        // than reporting anything about these tests.
        $command = sprintf(
            'XDEBUG_MODE=off %s analyse -c %s --memory-limit=1G --no-progress --error-format=raw 2>&1',
            escapeshellarg(self::ROOT . '/vendor/bin/phpstan'),
            escapeshellarg(__DIR__ . '/Fixture/phpstan-fixture.neon'),
        );

        return (string)shell_exec($command);
    }
}
