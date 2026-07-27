<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm;

use PHPUnit\Framework\TestCase;

use function escapeshellarg;
use function shell_exec;
use function sprintf;

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
        $command = sprintf(
            '%s --config=%s --no-progress --no-cache --output-format=text 2>&1',
            escapeshellarg(self::ROOT . '/vendor/bin/psalm'),
            escapeshellarg(__DIR__ . '/Fixture/psalm-fixture.xml'),
        );

        return (string)shell_exec($command);
    }
}
