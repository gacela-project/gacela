<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\Assign\RemoveUnusedVariableAssignRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveEmptyClassMethodRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveParentDelegatingClassMethodRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedConstructorParamRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPublicMethodParameterRector;
use Rector\DeadCode\Rector\Node\RemoveNonExistingVarAnnotationRector;
use Rector\DeadCode\Rector\Property\RemoveUselessVarTagRector;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;
use Rector\Php83\Rector\ClassMethod\AddOverrideAttributeToOverriddenMethodsRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Privatization\Rector\ClassMethod\PrivatizeFinalClassMethodRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
        __DIR__ . '/symfony-bridge/src',
        __DIR__ . '/symfony-bridge/tests',
        __DIR__ . '/laravel-bridge/src',
        __DIR__ . '/laravel-bridge/tests',
    ]);

    $rectorConfig->skip([
        // Gacela's own cache output, written into the fixture directories by
        // running the suite. It is gitignored, but rector does not read
        // .gitignore, so without this any developer who runs the tests and then
        // commits gets a rector failure on generated files -- and the
        // pre-commit hook runs `composer quality`, which now includes rector.
        __DIR__ . '/tests/**/gacela-class-names*.php',
        __DIR__ . '/tests/**/gacela-custom-services*.php',
        __DIR__ . '/tests/**/gacela-merged-config*.php',
        // The bridges write the same caches into their own fixtures, and their
        // tests are scanned too -- so leaving them out only moved the failure.
        // Spelled out per name rather than `gacela-*.php`: the env-specific
        // `gacela-dev.php` and `gacela-prod.php` fixtures are real files that
        // have to keep being checked.
        __DIR__ . '/symfony-bridge/tests/**/gacela-class-names*.php',
        __DIR__ . '/symfony-bridge/tests/**/gacela-custom-services*.php',
        __DIR__ . '/symfony-bridge/tests/**/gacela-merged-config*.php',
        __DIR__ . '/laravel-bridge/tests/**/gacela-class-names*.php',
        __DIR__ . '/laravel-bridge/tests/**/gacela-custom-services*.php',
        __DIR__ . '/laravel-bridge/tests/**/gacela-merged-config*.php',
        __DIR__ . '/tests/Unit/PHPStan/Rules/Fixture',
        // assert() inside an anonymous class extending a non-TestCase parent
        // must not be rewritten to $this->assertSame().
        __DIR__ . '/tests/Feature/Framework/OverrideExistingResolvedClass/FeatureTest.php',
        PreferPHPUnitThisCallRector::class,
        StringClassNameToClassConstantRector::class => [
            __DIR__ . '/tests/Feature/Framework/ListeningEvents/ClassResolver/GacelaClassResolverGeneralListenerTest.php',
            // Names an `@internal` upstream class on purpose; `::class` makes it a
            // real reference and phpstan-tests then reports classConstant.internalClass.
            __DIR__ . '/tests/Feature/Console/CacheClear/CacheClearCommandTest.php',
        ],
        FlipTypeControlToUseExclusiveTypeRector::class => [
            __DIR__ . '/src/Framework/AbstractFactory.php',
        ],
        // Generic `@var THandler` is required by Psalm to infer the return type of
        // `LazyHandlerRegistry::get()`; rector's RemoveNonExistingVarAnnotationRector
        // does not understand class-level `@template` parameters.
        RemoveNonExistingVarAnnotationRector::class => [
            __DIR__ . '/src/Framework/Plugins/LazyHandlerRegistry.php',
        ],
        // `@var mixed` annotations suppress Psalm's MixedAssignment warnings
        // on values whose type is genuinely unknown at the call site.
        RemoveUselessVarTagRector::class => [
            __DIR__ . '/src/Console/Application/Debug/ConstructorInspector.php',
            __DIR__ . '/src/Console/Infrastructure/Command/DebugContainerCommand.php',
            __DIR__ . '/src/Framework/Attribute/CacheableTrait.php',
            __DIR__ . '/src/Framework/Bootstrap/GacelaConfig.php',
            __DIR__ . '/src/Framework/Container/Container.php',
            __DIR__ . '/src/Framework/Health/HealthCheckRegistry.php',
            __DIR__ . '/src/Framework/Testing/ContainerFixture.php',
            __DIR__ . '/src/Psalm/ServiceMapPseudoMethods.php',
        ],
        // Fixtures and benchmarks are shaped on purpose: an "unused" constructor
        // parameter, an empty method or a discarded assignment is the thing under
        // test. Dead-code removal reads that intent as waste and deletes it:
        // it strips stream_close() and stream_open()'s by-ref $openedPath from a
        // stream wrapper PHP calls by contract, empties the very fixtures named
        // EmptyConstructorService and UntypedAndUnionService, and drops the
        // assignments that stop a benchmark's subject from being optimised away.
        RemoveEmptyClassMethodRector::class => [__DIR__ . '/tests'],
        RemoveUnusedPublicMethodParameterRector::class => [__DIR__ . '/tests'],
        RemoveUnusedConstructorParamRector::class => [__DIR__ . '/tests'],
        RemoveParentDelegatingClassMethodRector::class => [__DIR__ . '/tests'],
        RemoveUnusedVariableAssignRector::class => [__DIR__ . '/tests'],
        // The container writes `#[Inject]` properties through
        // ReflectionProperty::setValue(); readonly would make those fixtures
        // untestable for the mechanism they exist to cover. The laravel-bridge
        // listener does the same to its fixtures.
        ReadOnlyPropertyRector::class => [
            __DIR__ . '/tests',
            __DIR__ . '/laravel-bridge/tests/Fixtures',
        ],
        // `#[Before]`-attributed setup methods are invoked reflectively by PHPUnit;
        // rector sees them as unused. Removing them silently drops test isolation.
        RemoveUnusedPrivateMethodRector::class => [
            __DIR__ . '/tests/Unit/Framework/Testing/ContainerFixtureTest.php',
            // A private #[Inject] setter that exists to be *refused*: no code
            // path may call it, which is exactly what rector notices.
            __DIR__ . '/laravel-bridge/tests/Fixtures/PrivateSetterConsumer.php',
            // A private #[Provides] method that exists to be *reported*: the
            // scanner reads public methods only, so nothing may call it -- and
            // deleting it deletes the fault UnreachableProvidesCheck is asserted
            // against.
            __DIR__ . '/tests/Unit/Console/Application/Doctor/Check/Fixtures/HiddenProvidesProvider.php',
        ],
        PrivatizeFinalClassMethodRector::class => [
            __DIR__ . '/tests/Unit/Framework/Testing/ContainerFixtureTest.php',
            // Its protected method has to stay protected: the check reports the
            // visibility it found, and privatizing it leaves that branch with
            // nothing to report.
            __DIR__ . '/tests/Unit/Console/Application/Doctor/Check/Fixtures/HiddenProvidesProvider.php',
        ],
    ]);

    // One rule out of the 8.3 set, not the set. #[Override] is a compile-time
    // check on the class that declares it, so it constrains no downstream code
    // -- unlike ReadOnlyClassRector below, which is why the level set stays put.
    $rectorConfig->rule(AddOverrideAttributeToOverriddenMethodsRector::class);

    $rectorConfig->sets([
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::DEAD_CODE,
        SetList::PRIVATIZATION,
        SetList::TYPE_DECLARATION,
        SetList::EARLY_RETURN,
        SetList::INSTANCEOF,
        // Deliberately below the >=8.3 floor in composer.json. Going up to 8.3
        // turns 60 changed files into 223, and what it adds is BC-hostile for a
        // framework: ReadOnlyClassRector alone marks 103 classes readonly, which
        // a non-readonly child class may not extend, and that is every downstream
        // AbstractFacade, AbstractFactory, AbstractConfig and AbstractProvider.
        // AddTypeToConstRector types 74 public constants, fixing their type for
        // subclasses that redeclare them. Raise this only as its own decision.
        LevelSetList::UP_TO_PHP_81,
        PHPUnitSetList::PHPUNIT_100,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
    ]);
};
