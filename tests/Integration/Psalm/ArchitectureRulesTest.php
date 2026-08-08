<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm;

/**
 * Runs Psalm for real over a set of deliberately broken modules.
 *
 * The analysers have host-free unit tests of their own; what this proves is the
 * half those cannot -- that the rules are registered, that Psalm's storage
 * answers the seam correctly, and that each finding arrives as its own issue
 * type so a consumer can suppress one without losing the rest.
 */
final class ArchitectureRulesTest extends PsalmFixtureTestCase
{
    public function test_a_pillar_named_class_must_extend_its_base(): void
    {
        $this->skipIfPsalmCannotRun($this->analyseFixture());

        self::assertStringContainsString(
            'GacelaSuffixExtends: Class ' . RulesFixture\BadFacade::class . ' should extend Gacela\Framework\AbstractFacade',
            $this->errorsIn('BadFacade.php'),
        );
    }

    public function test_a_facade_method_may_only_delegate(): void
    {
        $this->skipIfPsalmCannotRun($this->analyseFixture());

        self::assertStringContainsString(
            'GacelaFacadeOnlyDelegates: Facade method ' . RulesFixture\LogicFacade::class . '::doesArithmetic()',
            $this->errorsIn('LogicFacade.php'),
        );
    }

    public function test_a_factory_may_not_instantiate_a_facade(): void
    {
        $this->skipIfPsalmCannotRun($this->analyseFixture());

        self::assertStringContainsString(
            'GacelaFacadeInstantiation: Factory ' . RulesFixture\BadWiringFactory::class,
            $this->errorsIn('BadWiringFactory.php'),
        );
    }

    /**
     * Reported separately from the instantiation above: they are different
     * mistakes with different corrections, so a factory making both hears about
     * both.
     */
    public function test_a_factory_may_not_call_get_facade(): void
    {
        $this->skipIfPsalmCannotRun($this->analyseFixture());

        self::assertStringContainsString(
            'GacelaFactoryFacadeAccess: Factory ' . RulesFixture\BadWiringFactory::class,
            $this->errorsIn('BadWiringFactory.php'),
        );
    }

    public function test_a_public_facade_method_missing_from_its_interface_is_reported(): void
    {
        $this->skipIfPsalmCannotRun($this->analyseFixture());

        self::assertStringContainsString(
            'GacelaFacadeInterfaceDrift: Facade method ' . RulesFixture\DriftedFacade::class . '::forgotten()',
            $this->errorsIn('DriftedFacade.php'),
        );
    }

    /**
     * The finding belongs to the drifted method, not to the class, because the
     * class's own line names no method to go and declare.
     */
    public function test_the_interface_drift_is_located_on_the_method(): void
    {
        $this->skipIfPsalmCannotRun($this->analyseFixture());

        self::assertStringContainsString('DriftedFacade.php:19:', $this->errorsIn('DriftedFacade.php'));
    }

    /**
     * The half that would go unnoticed: a rule that fires on everything is not a
     * rule. Both clean fixtures are as much a module as the broken ones.
     */
    public function test_a_module_that_follows_the_rules_is_silent(): void
    {
        $errors = $this->analyseFixture();
        $this->skipIfPsalmCannotRun($errors);

        self::assertStringContainsString('Gacela', $errors, 'precondition: the rules ran at all');
        self::assertSame('', $this->errorsIn('CleanFacade.php'));
        self::assertSame('', $this->errorsIn('CleanFactory.php'));
    }

    protected static function configPath(): string
    {
        return __DIR__ . '/RulesFixture/psalm-rules-fixture.xml';
    }
}
