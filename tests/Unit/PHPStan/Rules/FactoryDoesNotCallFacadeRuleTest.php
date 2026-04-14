<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules;

use Gacela\Framework\AbstractFactory;
use Gacela\PHPStan\Rules\FactoryDoesNotCallFacadeRule;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\Node\Stmt\Namespace_;
use PhpParser\ParserFactory;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPUnit\Framework\TestCase;

use function assert;
use function is_string;
use function sprintf;

final class FactoryDoesNotCallFacadeRuleTest extends TestCase
{
    public function test_reports_new_facade_instantiation(): void
    {
        $errors = $this->runOn('
            final class UserFactory {
                public function createSomething(): void
                {
                    new \App\Module\Shop\ShopFacade();
                }
            }
        ');

        self::assertCount(1, $errors);
        self::assertStringContainsString('must not instantiate a Facade', $errors[0]);
        self::assertStringContainsString('ShopFacade', $errors[0]);
    }

    public function test_reports_get_facade_call_on_this(): void
    {
        $errors = $this->runOn('
            final class UserFactory {
                public function createSomething(): void
                {
                    $this->getFacade()->doStuff();
                }
            }
        ');

        self::assertCount(1, $errors);
        self::assertStringContainsString('must not call $this->getFacade()', $errors[0]);
    }

    public function test_ignores_get_facade_call_on_other_variables(): void
    {
        $errors = $this->runOn('
            final class UserFactory {
                public function createSomething(object $service): void
                {
                    $service->getFacade();
                }
            }
        ');

        self::assertSame([], $errors);
    }

    public function test_ignores_new_non_facade(): void
    {
        $errors = $this->runOn('
            final class UserFactory {
                public function createSomething(): object
                {
                    return new \App\Module\User\Domain\UserService();
                }
            }
        ');

        self::assertSame([], $errors);
    }

    public function test_ignores_dynamic_new_expression(): void
    {
        $errors = $this->runOn('
            final class UserFactory {
                public function createSomething(string $class): object
                {
                    return new $class();
                }
            }
        ');

        self::assertSame([], $errors);
    }

    public function test_detects_multiple_violations(): void
    {
        $errors = $this->runOn('
            final class UserFactory {
                public function a(): void
                {
                    new \App\Module\Shop\ShopFacade();
                }
                public function b(): void
                {
                    $this->getFacade()->doStuff();
                }
            }
        ');

        self::assertCount(2, $errors);
    }

    public function test_skips_non_factory_classes(): void
    {
        $errors = $this->runOn(
            '
            final class UserService {
                public function doIt(): void
                {
                    $this->getFacade()->doStuff();
                }
            }
        ',
            isFactory: false,
        );

        self::assertSame([], $errors);
    }

    public function test_skips_when_class_reflection_is_null(): void
    {
        $rule = new FactoryDoesNotCallFacadeRule();

        $classLike = $this->parseClass('
            final class UserFactory {
                public function a(): void { new \App\Module\Shop\ShopFacade(); }
            }
        ');

        $reflection = $this->createMock(ClassReflection::class);
        $inClassNode = new InClassNode($classLike, $reflection);

        $scope = self::createStub(Scope::class);
        $scope->method('getClassReflection')->willReturn(null);

        self::assertSame([], $rule->processNode($inClassNode, $scope));
    }

    public function test_get_node_type_returns_in_class_node(): void
    {
        $rule = new FactoryDoesNotCallFacadeRule();
        self::assertSame(InClassNode::class, $rule->getNodeType());
    }

    /**
     * @return list<string>
     */
    private function runOn(string $classSource, bool $isFactory = true): array
    {
        $classLike = $this->parseClass($classSource);

        $reflection = $this->createMock(ClassReflection::class);
        $reflection->method('getName')->willReturn('App\Module\User\UserFactory');
        $reflection->method('isSubclassOf')->with(AbstractFactory::class)->willReturn($isFactory);

        $inClassNode = new InClassNode($classLike, $reflection);

        $scope = self::createStub(Scope::class);
        $scope->method('getClassReflection')->willReturn($reflection);

        $rule = new FactoryDoesNotCallFacadeRule();
        $errors = $rule->processNode($inClassNode, $scope);

        return array_map(
            static fn ($error): string => is_string($error) ? $error : $error->getMessage(),
            $errors,
        );
    }

    private function parseClass(string $classSource): ClassLike
    {
        $code = sprintf("<?php\nnamespace App\\Module\\User;\n%s\n", $classSource);
        $parser = (new ParserFactory())->createForHostVersion();
        $ast = $parser->parse($code);
        assert($ast !== null);

        $namespace = $ast[0];
        assert($namespace instanceof Namespace_);
        $classLike = $namespace->stmts[0];
        assert($classLike instanceof ClassLike);

        return $classLike;
    }
}
