<?php

declare(strict_types=1);

namespace GacelaTest\Unit\Framework\ClassResolver;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\ClassResolver\Cache\ClassNameCacheInterface;
use Gacela\Framework\ClassResolver\Cache\InMemoryClassNameCache;
use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\ClassResolver\ClassNameFinder\ClassNameFinder;
use Gacela\Framework\ClassResolver\ClassNameFinder\ClassValidatorInterface;
use Gacela\Framework\ClassResolver\ClassNameFinder\Rule\FinderRuleInterface;
use Gacela\Framework\Gacela;
use PHPUnit\Framework\TestCase;

final class ClassNameFinderTest extends TestCase
{
    public function setUp(): void
    {
        Gacela::bootstrap(__DIR__, static function (GacelaConfig $config): void {
            $config->setCacheEnabled(false);
        });
    }

    public function test_no_rules(): void
    {
        $classNameFinder = new ClassNameFinder(
            $this->createMock(ClassValidatorInterface::class),
            [],
            $this->createMock(ClassNameCacheInterface::class),
            []
        );

        $classInfo = new ClassInfo('callerNamespace', 'callerModuleName', 'cacheKey');
        $resolvableTypes = ['A', 'B'];
        $actual = $classNameFinder->findClassName($classInfo, $resolvableTypes);

        self::assertNull($actual);
    }

    public function test_rule_but_no_resolvable_types(): void
    {
        $classValidator = $this->createMock(ClassValidatorInterface::class);
        $classValidator->method('isClassNameValid')
            ->with('\valid\class\name')
            ->willReturn(true);

        $finderRule = $this->createStub(FinderRuleInterface::class);
        $finderRule->method('buildClassCandidate')->willReturn('\valid\class\name');

        $classNameFinder = new ClassNameFinder(
            $classValidator,
            [$finderRule],
            $this->createMock(ClassNameCacheInterface::class),
            []
        );

        $classInfo = new ClassInfo('callerNamespace', 'callerModuleName', 'cacheKey');
        $resolvableTypes = [];
        $actual = $classNameFinder->findClassName($classInfo, $resolvableTypes);

        self::assertNull($actual);
    }

    public function test_rule_returns_invalid_class_name(): void
    {
        $classValidator = $this->createMock(ClassValidatorInterface::class);
        $classValidator->method('isClassNameValid')
            ->with('\valid\class\name')
            ->willReturn(false);

        $finderRule = $this->createStub(FinderRuleInterface::class);
        $finderRule->method('buildClassCandidate')->willReturn('\valid\class\name');

        $classNameFinder = new ClassNameFinder(
            $classValidator,
            [$finderRule],
            $this->createMock(ClassNameCacheInterface::class),
            []
        );

        $classInfo = new ClassInfo('callerNamespace', 'callerModuleName', 'cacheKey');
        $resolvableTypes = ['A', 'B'];
        $actual = $classNameFinder->findClassName($classInfo, $resolvableTypes);

        self::assertNull($actual);
    }

    public function test_rule_returns_valid_class_name(): void
    {
        $classValidator = $this->createMock(ClassValidatorInterface::class);
        $classValidator->method('isClassNameValid')
            ->with('\valid\class\name')
            ->willReturn(true);

        $finderRule = $this->createStub(FinderRuleInterface::class);
        $finderRule->method('buildClassCandidate')->willReturn('\valid\class\name');

        $classNameFinder = new ClassNameFinder(
            $classValidator,
            [$finderRule],
            $this->createMock(ClassNameCacheInterface::class),
            []
        );

        $classInfo = new ClassInfo('callerNamespace', 'callerModuleName', 'cacheKey');
        $resolvableTypes = ['A', 'B'];
        $actual = $classNameFinder->findClassName($classInfo, $resolvableTypes);

        self::assertSame('\valid\class\name', $actual);
    }

    public function test_caching_valid_class_name(): void
    {
        $classValidator = $this->createMock(ClassValidatorInterface::class);
        $classValidator->method('isClassNameValid')->willReturn(true);

        $finderRule = $this->createMock(FinderRuleInterface::class);
        $finderRule->expects(self::once())
            ->method('buildClassCandidate')
            ->willReturn('\valid\class\name');

        $classNameFinder = new ClassNameFinder(
            $classValidator,
            [$finderRule],
            new InMemoryClassNameCache(ClassInfo::class),
            []
        );

        $classInfo = new ClassInfo('callerNamespace', 'callerModuleName', 'cacheKey');
        $resolvableTypes = ['A', 'B'];
        $classNameFinder->findClassName($classInfo, $resolvableTypes);
        $classNameFinder->findClassName($classInfo, $resolvableTypes);
        $classNameFinder->findClassName($classInfo, $resolvableTypes);
    }
}
