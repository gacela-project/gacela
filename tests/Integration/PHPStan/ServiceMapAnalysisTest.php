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

    public function test_a_declared_accessor_is_never_an_undefined_method(): void
    {
        $errors = $this->analyseFixture();

        // Namespace-qualified on purpose: "Consumer::getFacade()" alone is also a
        // substring of "UndeclaredConsumer::getFacade()", which must be reported.
        self::assertStringNotContainsString('Fixture\Consumer::getFacade()', $errors);
    }

    /**
     * The counterpart, and the reason 2.0 drops the `ignoreErrors` entry that
     * 1.x shipped: a class declaring neither `#[ServiceMap]` nor a `@method`
     * docblock is now reported instead of silenced.
     *
     * A suppressed call was never a typed one -- it evaluated to `mixed`, which
     * switched off checking of everything reached through the accessor. This is
     * the breaking half of the attribute-first move, so it is pinned rather than
     * left as a property of a config file nobody reads.
     */
    public function test_an_undeclared_accessor_is_reported(): void
    {
        $errors = $this->analyseFixture();

        self::assertStringContainsString(
            'Call to an undefined method GacelaTest\Integration\PHPStan\Fixture\UndeclaredConsumer::getFacade().',
            $errors,
        );
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
