<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules;

use Gacela\PHPStan\Rules\ModuleBoundaryRule;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Name;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPUnit\Framework\TestCase;

final class ModuleBoundaryRuleTest extends TestCase
{
    public function test_allows_same_module_access(): void
    {
        $rule = new ModuleBoundaryRule();

        $node = new New_(new Name('App\Module1\Domain\Service'));

        $classReflection = $this->createMock(ClassReflection::class);
        $classReflection->method('getName')->willReturn('App\Module1\Module1Facade');

        $scope = $this->createMock(Scope::class);
        $scope->method('getClassReflection')->willReturn($classReflection);
        $scope->method('getFile')->willReturn('/path/to/Module1/Module1Facade.php');

        $result = $rule->processNode($node, $scope);

        self::assertSame([], $result);
    }

    public function test_allows_facade_access_across_modules(): void
    {
        $rule = new ModuleBoundaryRule();

        $node = new New_(new Name('App\Module2\Module2Facade'));

        $classReflection = $this->createMock(ClassReflection::class);
        $classReflection->method('getName')->willReturn('App\Module1\Module1Factory');

        $scope = $this->createMock(Scope::class);
        $scope->method('getClassReflection')->willReturn($classReflection);
        $scope->method('getFile')->willReturn('/path/to/Module1/Module1Factory.php');

        $result = $rule->processNode($node, $scope);

        self::assertSame([], $result);
    }

    public function test_prevents_domain_class_access_across_modules(): void
    {
        $rule = new ModuleBoundaryRule();

        $node = new New_(new Name('App\Module2\Domain\Service'));

        $classReflection = $this->createMock(ClassReflection::class);
        $classReflection->method('getName')->willReturn('App\Module1\Module1Factory');

        $scope = $this->createMock(Scope::class);
        $scope->method('getClassReflection')->willReturn($classReflection);
        $scope->method('getFile')->willReturn('/path/to/Module1/Module1Factory.php');

        $result = $rule->processNode($node, $scope);

        self::assertCount(1, $result);
        self::assertStringContainsString('Module boundary violation', $result[0]->getMessage());
        self::assertStringContainsString('Module2', $result[0]->getMessage());
    }

    public function test_prevents_infrastructure_class_access_across_modules(): void
    {
        $rule = new ModuleBoundaryRule();

        $node = new New_(new Name('App\Module2\Infrastructure\Repository'));

        $classReflection = $this->createMock(ClassReflection::class);
        $classReflection->method('getName')->willReturn('App\Module1\Domain\Service');

        $scope = $this->createMock(Scope::class);
        $scope->method('getClassReflection')->willReturn($classReflection);
        $scope->method('getFile')->willReturn('/path/to/Module1/Domain/Service.php');

        $result = $rule->processNode($node, $scope);

        self::assertCount(1, $result);
        self::assertStringContainsString('Module boundary violation', $result[0]->getMessage());
        self::assertStringContainsString('Infrastructure', $result[0]->getMessage());
    }

    public function test_allows_access_from_test_paths(): void
    {
        $rule = new ModuleBoundaryRule(['tests/'], ['Domain', 'Infrastructure']);

        $node = new New_(new Name('App\Module2\Domain\Service'));

        $classReflection = $this->createMock(ClassReflection::class);
        $classReflection->method('getName')->willReturn('App\Module1\Module1Test');

        $scope = $this->createMock(Scope::class);
        $scope->method('getClassReflection')->willReturn($classReflection);
        $scope->method('getFile')->willReturn('/path/tests/Module1Test.php');

        $result = $rule->processNode($node, $scope);

        self::assertSame([], $result);
    }

    public function test_handles_static_calls(): void
    {
        $rule = new ModuleBoundaryRule();

        $node = new StaticCall(
            new Name('App\Module2\Domain\Service'),
            'someMethod',
        );

        $classReflection = $this->createMock(ClassReflection::class);
        $classReflection->method('getName')->willReturn('App\Module1\Module1Factory');

        $scope = $this->createMock(Scope::class);
        $scope->method('getClassReflection')->willReturn($classReflection);
        $scope->method('getFile')->willReturn('/path/to/Module1/Module1Factory.php');

        $result = $rule->processNode($node, $scope);

        self::assertCount(1, $result);
        self::assertStringContainsString('Module boundary violation', $result[0]->getMessage());
    }

    public function test_returns_empty_when_no_class_reflection(): void
    {
        $rule = new ModuleBoundaryRule();

        $node = new New_(new Name('App\Module2\Domain\Service'));

        $scope = $this->createMock(Scope::class);
        $scope->method('getClassReflection')->willReturn(null);
        $scope->method('getFile')->willReturn('/path/to/file.php');

        $result = $rule->processNode($node, $scope);

        self::assertSame([], $result);
    }

    public function test_get_node_type_returns_expr(): void
    {
        $rule = new ModuleBoundaryRule();

        self::assertSame(\PhpParser\Node\Expr::class, $rule->getNodeType());
    }

    public function test_allows_factory_access_within_same_module(): void
    {
        $rule = new ModuleBoundaryRule();

        $node = new New_(new Name('App\User\UserFactory'));

        $classReflection = $this->createMock(ClassReflection::class);
        $classReflection->method('getName')->willReturn('App\User\UserFacade');

        $scope = $this->createMock(Scope::class);
        $scope->method('getClassReflection')->willReturn($classReflection);
        $scope->method('getFile')->willReturn('/path/to/User/UserFacade.php');

        $result = $rule->processNode($node, $scope);

        self::assertSame([], $result);
    }
}
