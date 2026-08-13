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

    /**
     * An interface, a trait and an enum cannot extend a class, so the suffix
     * rule has nothing fixable to say about them. Reporting one leaves a
     * consumer with a baseline entry as the only way out.
     */
    public function test_something_that_cannot_extend_a_pillar_is_not_told_to(): void
    {
        $errors = $this->analyseFixture();
        $this->skipIfPsalmCannotRun($errors);

        self::assertStringContainsString('GacelaSuffixExtends', $errors, 'precondition: the rule ran at all');
        self::assertSame('', $this->errorsIn('StatusConfig.php'));
        self::assertSame('', $this->errorsIn('PaymentFacade.php'));
    }

    /**
     * Nor is a class that already has a parent: PHP has single inheritance, so
     * it cannot extend the pillar too. Outside Gacela the shape is ordinary --
     * a Laravel `ServiceProvider`, an OAuth `GoogleAuthProvider` -- and these
     * rules run inside every consumer's build.
     *
     * Its own file, because the assertion is that Psalm reports *nothing* here
     * and a shared file would let another rule's silence stand in for this one.
     */
    public function test_a_class_that_already_extends_something_is_not_told_to_extend_a_pillar(): void
    {
        $errors = $this->analyseFixture();
        $this->skipIfPsalmCannotRun($errors);

        self::assertStringContainsString('GacelaSuffixExtends', $errors, 'precondition: the rule ran at all');
        self::assertSame('', $this->errorsIn('InheritedNameFacade.php'));
    }

    /**
     * The key decides what the entry is filed under, so one with no `{N}`
     * placeholder is the same string for every call and the first caller's
     * result is served to the rest. Nothing fails; the wrong row is served.
     */
    public function test_a_cacheable_key_must_mention_the_arguments(): void
    {
        $this->skipIfPsalmCannotRun($this->analyseFixture());

        self::assertStringContainsString(
            'GacelaCacheableKeyIgnoresArguments: The #[Cacheable] key "thing" on '
            . RulesFixture\CachedFacade::class . '::bareKey()',
            $this->errorsIn('CachedFacade.php'),
        );
    }

    /**
     * Psalm has no separate channel for a tip, so the correction rides along in
     * the message rather than being dropped.
     */
    public function test_a_finding_carries_the_correction(): void
    {
        $this->skipIfPsalmCannotRun($this->analyseFixture());

        self::assertStringContainsString(
            'Extend Gacela\Framework\AbstractFacade, or rename it so it does not end in Facade.',
            $this->errorsIn('BadFacade.php'),
        );
    }

    protected static function configPath(): string
    {
        return __DIR__ . '/RulesFixture/psalm-rules-fixture.xml';
    }
}
