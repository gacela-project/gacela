<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan;

use PHPUnit\Framework\TestCase;

use function escapeshellarg;
use function shell_exec;
use function sprintf;

/**
 * Runs PHPStan for real against a Factory that asks for a dependency by
 * class-string, using the same `phpstan-gacela.neon` shipped to consumers.
 *
 * `getProvidedDependency()` returns `mixed`, which is why every call site in a
 * real consumer restores the type by hand with a `@var`. When the key is a
 * class-string the type was never unknown, and the point of typing it is not
 * the accessor itself but the calls made on what comes back.
 */
final class ProvidedDependencyAnalysisTest extends TestCase
{
    private const ROOT = __DIR__ . '/../../..';

    public function test_a_call_on_a_class_string_dependency_is_checked(): void
    {
        self::assertStringContainsString(
            'Call to an undefined method GacelaTest\Integration\PHPStan\Fixture\ProvidedClock::nooow().',
            $this->analyseFixture(),
            'the class-string key must resolve to that class, not to mixed',
        );
    }

    public function test_a_valid_call_on_a_class_string_dependency_is_not_reported(): void
    {
        self::assertStringNotContainsString('knownCallOnAClassStringKey', $this->analyseFixture());
    }

    /**
     * A string key resolves to whatever the Provider registered under it, which
     * the type system cannot know. Inventing a type there would be worse than
     * `mixed`: it would be a guess the analyser then trusts.
     */
    public function test_a_string_key_is_left_as_mixed(): void
    {
        self::assertStringNotContainsString('stringKeyStaysMixed', $this->analyseFixture());
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
