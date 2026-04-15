<?php

declare(strict_types=1);

use Rector\CodeQuality\Rector\Identical\FlipTypeControlToUseExclusiveTypeRector;
use Rector\CodingStyle\Rector\Encapsed\EncapsedStringsToSprintfRector;
use Rector\Config\RectorConfig;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodRector;
use Rector\DeadCode\Rector\Node\RemoveNonExistingVarAnnotationRector;
use Rector\Php55\Rector\String_\StringClassNameToClassConstantRector;
use Rector\PHPUnit\CodeQuality\Rector\Class_\PreferPHPUnitThisCallRector;
use Rector\PHPUnit\Set\PHPUnitSetList;
use Rector\Privatization\Rector\ClassMethod\PrivatizeFinalClassMethodRector;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

    $rectorConfig->skip([
        PreferPHPUnitThisCallRector::class,
        StringClassNameToClassConstantRector::class => [
            __DIR__ . '/tests/Feature/Framework/ListeningEvents/ClassResolver/GacelaClassResolverGeneralListenerTest.php',
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
        // These tests embed PHP source inside heredocs; keeping interpolation makes
        // the embedded snippets readable. sprintf() obscures them for no benefit.
        EncapsedStringsToSprintfRector::class => [
            __DIR__ . '/tests/Unit/Framework/Cache/FileCacheConcurrencyTest.php',
            __DIR__ . '/tests/Unit/Framework/Cache/FileCacheTest.php',
        ],
        // `#[Before]`-attributed setup methods are invoked reflectively by PHPUnit;
        // rector sees them as unused. Removing them silently drops test isolation.
        RemoveUnusedPrivateMethodRector::class => [
            __DIR__ . '/tests/Unit/Framework/Testing/ContainerFixtureTest.php',
        ],
        PrivatizeFinalClassMethodRector::class => [
            __DIR__ . '/tests/Unit/Framework/Testing/ContainerFixtureTest.php',
        ],
    ]);

    $rectorConfig->sets([
        SetList::CODE_QUALITY,
        SetList::CODING_STYLE,
        SetList::DEAD_CODE,
        SetList::PRIVATIZATION,
        SetList::TYPE_DECLARATION,
        SetList::EARLY_RETURN,
        SetList::INSTANCEOF,
        SetList::STRICT_BOOLEANS,
        LevelSetList::UP_TO_PHP_81,
        PHPUnitSetList::PHPUNIT_100,
        PHPUnitSetList::PHPUNIT_CODE_QUALITY,
    ]);
};
