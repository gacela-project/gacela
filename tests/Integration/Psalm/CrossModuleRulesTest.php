<?php

declare(strict_types=1);

namespace GacelaTest\Integration\Psalm;

/**
 * Runs Psalm for real with the cross-module check turned on from plugin config.
 *
 * Both halves are on together, because they are halves of one thing: one matches
 * the module names a source writes, the other resolves the receivers it does not.
 */
final class CrossModuleRulesTest extends PsalmFixtureTestCase
{
    public function test_a_reference_that_names_another_module_is_reported(): void
    {
        $this->skipIfPsalmCannotRun($this->analyseFixture());

        self::assertStringContainsString(
            'GacelaCrossModuleAccess: Class ' . CrossModuleFixture\User\NamesTheOtherModule::class,
            $this->errorsIn('NamesTheOtherModule.php'),
        );
    }

    /**
     * The headline case: `ShopService` appears once, in the constructor, so the
     * name-matching half sees no crossing at the call site at all.
     */
    public function test_a_call_on_an_injected_dependency_is_reported(): void
    {
        $this->skipIfPsalmCannotRun($this->analyseFixture());

        self::assertStringContainsString(
            'GacelaCrossModuleMethodCall: Class ' . CrossModuleFixture\User\CallsTheOtherModule::class,
            $this->errorsIn('CallsTheOtherModule.php'),
        );
    }

    public function test_going_through_the_facade_is_silent(): void
    {
        $errors = $this->analyseFixture();
        $this->skipIfPsalmCannotRun($errors);

        self::assertStringContainsString('GacelaCrossModule', $errors, 'precondition: the check ran at all');
        self::assertSame('', $this->errorsIn('FacadeConsumer.php'));
    }

    public function test_an_allowlisted_shared_kernel_is_silent(): void
    {
        $errors = $this->analyseFixture();
        $this->skipIfPsalmCannotRun($errors);

        self::assertStringContainsString('GacelaCrossModule', $errors, 'precondition: the check ran at all');
        self::assertSame('', $this->errorsIn('UsesTheSharedKernel.php'));
    }

    /**
     * `Shared` is a raw-string prefix of `SharedFoo` but not a namespace
     * boundary. Matching on the prefix would silently exempt every module whose
     * name merely starts with an allowlisted one.
     */
    public function test_a_namespace_starting_with_a_shared_one_is_still_reported(): void
    {
        $this->skipIfPsalmCannotRun($this->analyseFixture());

        self::assertStringContainsString(
            'GacelaCrossModuleMethodCall',
            $this->errorsIn('UsesANamespaceStartingWithShared.php'),
        );
    }

    protected static function configPath(): string
    {
        return __DIR__ . '/CrossModuleFixture/psalm-cross-module-fixture.xml';
    }
}
