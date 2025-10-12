<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules;

use Gacela\Framework\AbstractConfig;
use Gacela\Framework\AbstractFacade;
use Gacela\Framework\AbstractFactory;
use Gacela\Framework\AbstractProvider;
use Gacela\PHPStan\Rules\SuffixExtendsRule;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPUnit\Framework\TestCase;

final class SuffixExtendsRuleTest extends TestCase
{
    public function test_returns_empty_array_for_anonymous_class(): void
    {
        $rule = new SuffixExtendsRule('Facade', AbstractFacade::class);
        $node = self::createStub(Class_::class);
        $node->method('isAnonymous')->willReturn(true);

        $scope = self::createStub(Scope::class);

        $result = $rule->processNode($node, $scope);

        self::assertSame([], $result);
    }

    public function test_returns_empty_array_when_class_reflection_is_null(): void
    {
        $rule = new SuffixExtendsRule('Facade', AbstractFacade::class);
        $node = self::createStub(Class_::class);
        $node->method('isAnonymous')->willReturn(false);

        $scope = self::createStub(Scope::class);
        $scope->method('getClassReflection')->willReturn(null);

        $result = $rule->processNode($node, $scope);

        self::assertSame([], $result);
    }

    public function test_returns_empty_array_when_class_does_not_have_suffix(): void
    {
        $rule = new SuffixExtendsRule('Facade', AbstractFacade::class);
        $node = self::createStub(Class_::class);
        $node->method('isAnonymous')->willReturn(false);

        $classReflection = $this->createMock(ClassReflection::class);
        $classReflection->method('getName')->willReturn('App\Module\Service');

        $scope = self::createStub(Scope::class);
        $scope->method('getClassReflection')->willReturn($classReflection);

        $result = $rule->processNode($node, $scope);

        self::assertSame([], $result);
    }

    public function test_returns_empty_array_when_class_with_suffix_extends_expected_parent(): void
    {
        $rule = new SuffixExtendsRule('Facade', AbstractFacade::class);
        $node = self::createStub(Class_::class);
        $node->method('isAnonymous')->willReturn(false);

        $classReflection = $this->createMock(ClassReflection::class);
        $classReflection->method('getName')->willReturn('App\Module\UserFacade');
        $classReflection->method('isSubclassOf')->with(AbstractFacade::class)->willReturn(true);

        $scope = self::createStub(Scope::class);
        $scope->method('getClassReflection')->willReturn($classReflection);

        $result = $rule->processNode($node, $scope);

        self::assertSame([], $result);
    }

    public function test_returns_empty_array_when_class_is_the_expected_parent_itself(): void
    {
        $rule = new SuffixExtendsRule('Facade', AbstractFacade::class);
        $node = self::createStub(Class_::class);
        $node->method('isAnonymous')->willReturn(false);

        $classReflection = $this->createMock(ClassReflection::class);
        $classReflection->method('getName')->willReturn(AbstractFacade::class);
        $classReflection->method('isSubclassOf')->with(AbstractFacade::class)->willReturn(false);

        $scope = self::createStub(Scope::class);
        $scope->method('getClassReflection')->willReturn($classReflection);

        $result = $rule->processNode($node, $scope);

        self::assertSame([], $result);
    }

    public function test_returns_error_when_class_with_suffix_does_not_extend_expected_parent(): void
    {
        $rule = new SuffixExtendsRule('Facade', AbstractFacade::class);
        $node = self::createStub(Class_::class);
        $node->method('isAnonymous')->willReturn(false);

        $classReflection = $this->createMock(ClassReflection::class);
        $classReflection->method('getName')->willReturn('App\Module\UserFacade');
        $classReflection->method('isSubclassOf')->with(AbstractFacade::class)->willReturn(false);

        $scope = self::createStub(Scope::class);
        $scope->method('getClassReflection')->willReturn($classReflection);

        $result = $rule->processNode($node, $scope);

        self::assertCount(1, $result);
        self::assertSame(
            'Class App\Module\UserFacade should extend ' . AbstractFacade::class,
            $result[0],
        );
    }

    public function test_facade_suffix_rule(): void
    {
        $rule = new SuffixExtendsRule('Facade', AbstractFacade::class);
        $node = self::createStub(Class_::class);
        $node->method('isAnonymous')->willReturn(false);

        $classReflection = $this->createMock(ClassReflection::class);
        $classReflection->method('getName')->willReturn('InvalidFacade');
        $classReflection->method('isSubclassOf')->with(AbstractFacade::class)->willReturn(false);

        $scope = self::createStub(Scope::class);
        $scope->method('getClassReflection')->willReturn($classReflection);

        $result = $rule->processNode($node, $scope);

        self::assertCount(1, $result);
        self::assertStringContainsString('should extend', $result[0]);
    }

    public function test_factory_suffix_rule(): void
    {
        $rule = new SuffixExtendsRule('Factory', AbstractFactory::class);
        $node = self::createStub(Class_::class);
        $node->method('isAnonymous')->willReturn(false);

        $classReflection = $this->createMock(ClassReflection::class);
        $classReflection->method('getName')->willReturn('InvalidFactory');
        $classReflection->method('isSubclassOf')->with(AbstractFactory::class)->willReturn(false);

        $scope = self::createStub(Scope::class);
        $scope->method('getClassReflection')->willReturn($classReflection);

        $result = $rule->processNode($node, $scope);

        self::assertCount(1, $result);
        self::assertStringContainsString('InvalidFactory', $result[0]);
        self::assertStringContainsString(AbstractFactory::class, $result[0]);
    }

    public function test_provider_suffix_rule(): void
    {
        $rule = new SuffixExtendsRule('Provider', AbstractProvider::class);
        $node = self::createStub(Class_::class);
        $node->method('isAnonymous')->willReturn(false);

        $classReflection = $this->createMock(ClassReflection::class);
        $classReflection->method('getName')->willReturn('InvalidProvider');
        $classReflection->method('isSubclassOf')->with(AbstractProvider::class)->willReturn(false);

        $scope = self::createStub(Scope::class);
        $scope->method('getClassReflection')->willReturn($classReflection);

        $result = $rule->processNode($node, $scope);

        self::assertCount(1, $result);
        self::assertStringContainsString('InvalidProvider', $result[0]);
        self::assertStringContainsString(AbstractProvider::class, $result[0]);
    }

    public function test_config_suffix_rule(): void
    {
        $rule = new SuffixExtendsRule('Config', AbstractConfig::class);
        $node = self::createStub(Class_::class);
        $node->method('isAnonymous')->willReturn(false);

        $classReflection = $this->createMock(ClassReflection::class);
        $classReflection->method('getName')->willReturn('InvalidConfig');
        $classReflection->method('isSubclassOf')->with(AbstractConfig::class)->willReturn(false);

        $scope = self::createStub(Scope::class);
        $scope->method('getClassReflection')->willReturn($classReflection);

        $result = $rule->processNode($node, $scope);

        self::assertCount(1, $result);
        self::assertStringContainsString('InvalidConfig', $result[0]);
        self::assertStringContainsString(AbstractConfig::class, $result[0]);
    }

    public function test_handles_namespace_extraction_correctly(): void
    {
        $rule = new SuffixExtendsRule('Facade', AbstractFacade::class);
        $node = self::createStub(Class_::class);
        $node->method('isAnonymous')->willReturn(false);

        $classReflection = $this->createMock(ClassReflection::class);
        $classReflection->method('getName')->willReturn('Very\Long\Namespace\Path\UserFacade');
        $classReflection->method('isSubclassOf')->with(AbstractFacade::class)->willReturn(false);

        $scope = self::createStub(Scope::class);
        $scope->method('getClassReflection')->willReturn($classReflection);

        $result = $rule->processNode($node, $scope);

        self::assertCount(1, $result);
        self::assertStringContainsString('Very\Long\Namespace\Path\UserFacade', $result[0]);
    }

    public function test_get_node_type_returns_class(): void
    {
        $rule = new SuffixExtendsRule('Facade', AbstractFacade::class);

        self::assertSame(Class_::class, $rule->getNodeType());
    }
}
