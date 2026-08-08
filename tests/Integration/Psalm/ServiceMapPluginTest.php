<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm;

use GacelaTest\Integration\Psalm\Fixture\ConsumerFacade;

/**
 * Runs Psalm for real against a fixture that declares its pillar with
 * `#[ServiceMap]` and **no** `@method` docblock.
 *
 * Psalm reads `@method` natively, so a fixture carrying one would pass with or
 * without the plugin and prove nothing. Without the docblock the plugin is the
 * only thing that can type the accessor.
 */
final class ServiceMapPluginTest extends PsalmFixtureTestCase
{
    public function test_a_call_on_the_resolved_facade_is_checked(): void
    {
        $errors = $this->analyseFixture();
        $this->skipIfPsalmCannotRun($errors);

        self::assertStringContainsString(
            'Method ' . ConsumerFacade::class . '::typoMethod does not exist',
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

    protected static function configPath(): string
    {
        return __DIR__ . '/Fixture/psalm-fixture.xml';
    }
}
