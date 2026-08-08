<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm;

use GacelaTest\Integration\Psalm\Fixture\ProvidedClock;

/**
 * Runs Psalm for real against Factories that ask for their dependencies with no
 * `@var` in sight.
 *
 * The unit test proves the hook returns the right type. This proves the thing
 * that actually matters: with a real return type, Psalm checks the calls made
 * *on* the provided dependency. `getProvidedDependency()` is declared `mixed`,
 * and a `mixed` call is not a checked one.
 */
final class ProvidedDependencyPluginTest extends PsalmFixtureTestCase
{
    public function test_a_call_on_a_class_string_dependency_is_checked(): void
    {
        $this->skipIfPsalmCannotRun($this->analyseFixture());

        self::assertStringContainsString(
            'Method ' . ProvidedClock::class . '::typoOnTheClock does not exist',
            $this->errorsIn('ProvidedFactory.php'),
            'the class-string key must resolve to that class, not to mixed',
        );
    }

    /**
     * The other half, and the one that fails without the plugin: an untyped
     * accessor makes every call through it a `MixedMethodCall`, valid or not.
     */
    public function test_a_class_string_dependency_is_not_left_mixed(): void
    {
        $this->skipIfPsalmCannotRun($this->analyseFixture());

        $errors = $this->errorsIn('ProvidedFactory.php');

        // Without this, a filter that matches nothing makes the real assertion
        // below pass against an empty string -- which is how the windows path
        // separator went unnoticed the first time.
        self::assertNotSame('', $errors, 'precondition: psalm reported on this fixture at all');
        self::assertStringNotContainsString('MixedMethodCall', $errors);
    }

    /**
     * Nothing in the type system says what `'some.service'` resolves to. Psalm
     * reporting a `MixedMethodCall` there is the honest answer, and pinning it
     * keeps a future change from quietly inventing a type instead.
     */
    public function test_a_string_key_dependency_stays_mixed(): void
    {
        $this->skipIfPsalmCannotRun($this->analyseFixture());

        self::assertStringContainsString('MixedMethodCall', $this->errorsIn('StringKeyFactory.php'));
    }

    protected static function configPath(): string
    {
        return __DIR__ . '/Fixture/psalm-fixture.xml';
    }
}
