<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm;

use PHPUnit\Framework\TestCase;

use function escapeshellarg;
use function shell_exec;
use function sprintf;

use const E_ALL;
use const E_DEPRECATED;
use const PHP_BINARY;

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
        self::assertStringContainsString(
            'Method GacelaTest\Integration\Psalm\Fixture\ConsumerFacade::typoMethod does not exist',
            $this->analyseFixture(),
            'the accessor must resolve to the facade, so calls made through it are checked',
        );
    }

    public function test_a_valid_call_through_the_accessor_is_not_reported(): void
    {
        self::assertStringNotContainsString('knownMethod', $this->analyseFixture());
    }

    public function test_the_accessor_itself_is_not_reported_as_undefined(): void
    {
        self::assertStringNotContainsString('UndefinedMagicMethod', $this->analyseFixture());
    }

    private function analyseFixture(): string
    {
        // Deprecations are silenced in the subprocess, not filtered afterwards.
        // At --prefer-lowest on PHP 8.5 the amphp packages -- transitive
        // dependencies of infection, nothing to do with Psalm -- emit
        // "Implicitly marking parameter as nullable" notices on load, and with
        // 2>&1 they drown the findings this test asserts on.
        $command = sprintf(
            '%s -d error_reporting=%s %s --config=%s --no-progress --no-cache --output-format=text 2>&1',
            escapeshellarg(PHP_BINARY),
            escapeshellarg((string)(E_ALL & ~E_DEPRECATED)),
            escapeshellarg(self::ROOT . '/vendor/bin/psalm'),
            escapeshellarg(__DIR__ . '/Fixture/psalm-fixture.xml'),
        );

        return (string)shell_exec($command);
    }
}
