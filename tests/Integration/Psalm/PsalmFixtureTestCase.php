<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm;

use PHPUnit\Framework\TestCase;

use function escapeshellarg;
use function explode;
use function implode;
use function shell_exec;
use function sprintf;
use function str_contains;
use function substr;

/**
 * Runs the plugin end-to-end through a real `vendor/bin/psalm`, over
 * `Fixture/psalm-fixture.xml`.
 *
 * That is the strongest proof available -- an in-process test drives a hook,
 * this drives Psalm -- but it is also the slowest, so the run is shared: the
 * fixture set and the config are the same for every test here, so the output is
 * too, and analysing once per process is enough.
 */
abstract class PsalmFixtureTestCase extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    private static ?string $output = null;

    final protected function analyseFixture(): string
    {
        return self::$output ??= $this->runPsalm();
    }

    /**
     * Only the findings for one fixture file.
     *
     * Every line of Psalm's text output starts with the absolute path it
     * concerns, so filtering on the basename separates two fixtures without
     * anchoring a test to a line or column that moves whenever the fixture is
     * edited. The separator has to come from `DIRECTORY_SEPARATOR`: Psalm prints
     * `D:\a\gacela\...` on windows, and a hard-coded `/` quietly matched
     * nothing there, which made every *negative* assertion pass against an empty
     * string.
     */
    final protected function errorsIn(string $fixtureBasename): string
    {
        $lines = [];

        foreach (explode("\n", $this->analyseFixture()) as $line) {
            if (str_contains($line, DIRECTORY_SEPARATOR . $fixtureBasename . ':')) {
                $lines[] = $line;
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Psalm converts *any* PHP error into a RuntimeException via its own error
     * handler, so a deprecation raised while autoloading an unrelated vendor
     * class takes the whole run down -- `error_reporting` cannot prevent it.
     *
     * At `--prefer-lowest` on PHP 8.5 the amphp packages (transitive
     * dependencies of infection) do exactly that, so Psalm cannot run in this
     * project on that one matrix cell at all, with or without the plugin.
     *
     * Skipped rather than failed there, but only when the crash is *not* ours:
     * a crash mentioning Gacela is a plugin bug and must still fail.
     */
    final protected function skipIfPsalmCannotRun(string $output): void
    {
        if (!str_contains($output, 'crashed due to an uncaught Throwable')) {
            return;
        }

        if (str_contains($output, 'Gacela\\Psalm')) {
            return;
        }

        // Only the tail: the run emits hundreds of kilobytes of vendor
        // deprecations before the crash, and the crash is the last thing in it.
        self::markTestSkipped('Psalm cannot run in this dependency set: ' . substr($output, -2000));
    }

    private function runPsalm(): string
    {
        // No `-d error_reporting=...` here: Psalm re-execs itself through
        // xdebug-handler before it analyses anything, and the restarted process
        // does not inherit the flag. Deprecation noise is therefore dealt with
        // where it lands -- see skipIfPsalmCannotRun().
        $command = sprintf(
            '%s --config=%s --no-progress --no-cache --output-format=text 2>&1',
            escapeshellarg(self::ROOT . '/vendor/bin/psalm'),
            escapeshellarg(__DIR__ . '/Fixture/psalm-fixture.xml'),
        );

        return (string)shell_exec($command);
    }
}
