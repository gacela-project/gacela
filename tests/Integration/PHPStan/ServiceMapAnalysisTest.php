<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan;

use PHPUnit\Framework\TestCase;

use function escapeshellarg;
use function shell_exec;
use function sprintf;

/**
 * Runs PHPStan for real against a fixture that declares its pillar with
 * `#[ServiceMap]`, using the same `phpstan-gacela.neon` shipped to consumers.
 *
 * The unit test proves the extension returns the right reflection. This proves
 * the thing that actually matters: with a real return type, PHPStan checks the
 * calls made *on* the resolved facade. Under the old suppression the accessor
 * returned `mixed` and everything behind it went unchecked, so `typoMethod()`
 * produced no error at all.
 */
final class ServiceMapAnalysisTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    public function test_a_call_on_the_resolved_facade_is_checked(): void
    {
        $errors = $this->analyseFixture();

        self::assertStringContainsString(
            'Call to an undefined method GacelaTest\Integration\PHPStan\Fixture\ConsumerFacade::typoMethod().',
            $errors,
            'the accessor must resolve to the facade, not to mixed',
        );
    }

    public function test_a_valid_call_on_the_resolved_facade_is_not_reported(): void
    {
        $errors = $this->analyseFixture();

        self::assertStringNotContainsString('knownMethod', $errors);
    }

    public function test_the_accessor_itself_is_never_an_undefined_method(): void
    {
        $errors = $this->analyseFixture();

        self::assertStringNotContainsString('::getFacade()', $errors);
    }

    private function analyseFixture(): string
    {
        $command = sprintf(
            '%s analyse -c %s --no-progress --error-format=raw 2>&1',
            escapeshellarg(self::ROOT . '/vendor/bin/phpstan'),
            escapeshellarg(__DIR__ . '/Fixture/phpstan-fixture.neon'),
        );

        return (string)shell_exec($command);
    }
}
