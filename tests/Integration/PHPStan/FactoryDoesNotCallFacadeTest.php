<?php

declare(strict_types=1);

namespace GacelaTest\Integration\PHPStan;

use Override;

/**
 * Runs PHPStan for real over a Factory reaching for a Facade.
 *
 * `FactoryDoesNotCallFacadeRule` is registered uncommented in
 * `phpstan-gacela.neon` and is the rule the documentation points at when it
 * explains how a Factory reaches another module -- and nothing drove its
 * PHPStan front end.
 *
 * Found by silencing each analyser in turn and running only the tests that
 * drive PHPStan. That audit has to clear PHPStan's result cache between
 * rounds: without it the child process answers from before the analyser was
 * silenced, and the audit reports whichever verdict the cache happens to hold.
 */
final class FactoryDoesNotCallFacadeTest extends PhpStanFixtureTestCase
{
    public function test_a_factory_calling_get_facade_is_reported(): void
    {
        $errors = $this->analyseFixture();

        self::assertStringContainsString('ReachingFactory must not call $this->getFacade()', $errors);
    }

    /**
     * The finding carries both corrections, because which one applies depends
     * on which module the Factory was reaching for.
     */
    public function test_the_finding_names_both_ways_out(): void
    {
        self::assertStringContainsString(
            'same-module access goes through the Factory itself, cross-module access goes through the Provider',
            $this->analyseFixture(),
        );
    }

    public function test_a_factory_reaching_its_own_config_is_left_alone(): void
    {
        self::assertStringNotContainsString('WellBehavedFactory', $this->analyseFixture());
    }

    #[Override]
    protected static function configPath(): string
    {
        return __DIR__ . '/FactoryFacadeFixture/phpstan-factoryfacadefixture.neon';
    }
}
