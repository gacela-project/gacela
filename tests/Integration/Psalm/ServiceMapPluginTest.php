<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm;

use PHPUnit\Framework\TestCase;

use function escapeshellarg;
use function shell_exec;
use function sprintf;
use function str_contains;

/**
 * Runs Psalm for real against a fixture that declares its pillar with
 * `#[ServiceMap]` and **no** `@method` docblock.
 *
 * Psalm reads `@method` natively, so a fixture carrying one would pass with or
 * without the plugin and prove nothing. Without the docblock the plugin is the
 * only thing that can type the accessor.
 */
final class ServiceMapPluginTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    public function test_a_call_on_the_resolved_facade_is_checked(): void
    {
        $errors = $this->analyseFixture();
        $this->skipIfPsalmCannotRun($errors);

        self::assertStringContainsString(
            'Method GacelaTest\Integration\Psalm\Fixture\ConsumerFacade::typoMethod does not exist',
            $errors,
            'the accessor must resolve to the facade, so calls made through it are checked',
        );
    }

    public function test_a_valid_call_through_the_accessor_is_not_reported(): void
    {
        $errors = $this->analyseFixture();
        $this->skipIfPsalmCannotRun($errors);

        self::assertStringNotContainsString('knownMethod', $errors);
    }

    public function test_the_accessor_itself_is_not_reported_as_undefined(): void
    {
        $errors = $this->analyseFixture();
        $this->skipIfPsalmCannotRun($errors);

        self::assertStringNotContainsString('UndefinedMagicMethod', $errors);
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
    private function skipIfPsalmCannotRun(string $output): void
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

    private function analyseFixture(): string
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
