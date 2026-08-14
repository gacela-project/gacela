<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan;

use PHPUnit\Framework\TestCase;

use function escapeshellarg;
use function exec;
use function getenv;
use function implode;
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

    /** @var array<string, string> */
    private static array $outputs = [];

    final protected function analyseFixture(): string
    {
        return self::$outputs[static::configPath()] ??= $this->runPhpStan();
    }

    /**
     * The config to analyse. One per fixture set, so a set of deliberately
     * broken classes cannot leak its findings into a test about something else.
     */
    protected static function configPath(): string
    {
        return __DIR__ . '/Fixture/phpstan-fixture.neon';
    }

    private function runPhpStan(): string
    {
        // PHPStan keys its result cache on the analysed files, not on the rules
        // that judged them -- so editing a rule and re-running these tests can
        // be answered from before the edit, and a rule that has stopped
        // reporting still looks green. Psalm's harness beside this one passes
        // `--no-cache`; PHPStan has no such flag, so the cache is cleared
        // instead. Once per config, since the analysis below is memoized.
        $clear = sprintf(
            '%s clear-result-cache -c %s 2>&1',
            escapeshellarg(self::ROOT . '/vendor/bin/phpstan'),
            escapeshellarg(static::configPath()),
        );

        // Checked rather than fired and forgotten. The first version of this
        // passed `--no-progress`, which `clear-result-cache` does not accept:
        // it failed, `shell_exec()` swallowed that, and the cache went on
        // answering -- a silent no-op that looked exactly like a working one.
        exec($clear, $clearOutput, $clearStatus);
        self::assertSame(0, $clearStatus, 'could not clear phpstan result cache: ' . implode("\n", $clearOutput));

        $command = sprintf(
            '%s analyse -c %s --memory-limit=1G --no-progress --error-format=raw 2>&1',
            escapeshellarg(self::ROOT . '/vendor/bin/phpstan'),
            escapeshellarg(static::configPath()),
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
