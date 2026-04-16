<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules;

use Gacela\Framework\AbstractFacade;
use Gacela\PHPStan\Rules\FacadeOnlyDelegatesRule;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\ParserFactory;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPUnit\Framework\TestCase;

use function assert;
use function is_string;
use function sprintf;

final class FacadeOnlyDelegatesRuleTest extends TestCase
{
    public function test_returns_no_error_for_single_return_delegating_to_factory(): void
    {
        $method = $this->parseMethod('
            public function doSomething(): int
            {
                return $this->getFactory()->createService()->run();
            }
        ');

        self::assertSame([], $this->runRule($method, isFacade: true));
    }

    public function test_returns_no_error_for_single_expression_delegating_to_factory(): void
    {
        $method = $this->parseMethod('
            public function doSomething(): void
            {
                $this->getFactory()->createService()->run();
            }
        ');

        self::assertSame([], $this->runRule($method, isFacade: true));
    }

    public function test_returns_no_error_for_get_config_delegation(): void
    {
        $method = $this->parseMethod('
            public function getEndpoint(): string
            {
                return $this->getConfig()->getEndpoint();
            }
        ');

        self::assertSame([], $this->runRule($method, isFacade: true));
    }

    public function test_returns_no_error_for_get_provider_delegation(): void
    {
        $method = $this->parseMethod('
            public function getClient(): object
            {
                return $this->getProvider()->getClient();
            }
        ');

        self::assertSame([], $this->runRule($method, isFacade: true));
    }

    public function test_returns_no_error_for_property_access_on_delegate_chain(): void
    {
        $method = $this->parseMethod('
            public function value(): int
            {
                return $this->getFactory()->createThing()->value;
            }
        ');

        self::assertSame([], $this->runRule($method, isFacade: true));
    }

    public function test_returns_no_error_for_nullsafe_chain(): void
    {
        $method = $this->parseMethod('
            public function maybe(): ?int
            {
                return $this->getFactory()?->createThing()?->value;
            }
        ');

        self::assertSame([], $this->runRule($method, isFacade: true));
    }

    public function test_returns_no_error_for_empty_body(): void
    {
        $method = $this->parseMethod('
            public function noop(): void
            {
            }
        ');

        self::assertSame([], $this->runRule($method, isFacade: true));
    }

    public function test_reports_error_for_multiple_statements(): void
    {
        $method = $this->parseMethod('
            public function compute(): int
            {
                $value = $this->getFactory()->createService()->run();
                return $value;
            }
        ');

        $errors = $this->runRule($method, isFacade: true);

        self::assertCount(1, $errors);
        self::assertStringContainsString('compute', $errors[0]);
        self::assertStringContainsString('delegate', $errors[0]);
    }

    public function test_reports_error_for_local_logic_without_delegation(): void
    {
        $method = $this->parseMethod('
            public function compute(int $x): int
            {
                return $x + 1;
            }
        ');

        $errors = $this->runRule($method, isFacade: true);

        self::assertCount(1, $errors);
    }

    public function test_reports_error_for_control_flow(): void
    {
        $method = $this->parseMethod('
            public function conditional(bool $flag): int
            {
                if ($flag) {
                    return $this->getFactory()->createA()->run();
                }
                return $this->getFactory()->createB()->run();
            }
        ');

        $errors = $this->runRule($method, isFacade: true);

        self::assertCount(1, $errors);
    }

    public function test_reports_error_when_root_is_not_an_allowed_accessor(): void
    {
        $method = $this->parseMethod('
            public function compute(): int
            {
                return $this->somethingElse()->run();
            }
        ');

        $errors = $this->runRule($method, isFacade: true);

        self::assertCount(1, $errors);
    }

    public function test_skips_non_public_methods(): void
    {
        $method = $this->parseMethod('
            protected function helper(): int
            {
                $value = 42;
                return $value;
            }
        ');

        self::assertSame([], $this->runRule($method, isFacade: true));
    }

    public function test_skips_abstract_methods(): void
    {
        $method = $this->parseMethod(
            '
            abstract public function contract(): int;
        ',
            inAbstractClass: true,
        );

        self::assertSame([], $this->runRule($method, isFacade: true));
    }

    public function test_skips_classes_that_are_not_facades(): void
    {
        $method = $this->parseMethod('
            public function compute(): int
            {
                $value = 1;
                return $value;
            }
        ');

        self::assertSame([], $this->runRule($method, isFacade: false));
    }

    public function test_skips_when_class_reflection_is_null(): void
    {
        $method = $this->parseMethod('
            public function compute(): int
            {
                $value = 1;
                return $value;
            }
        ');

        $rule = new FacadeOnlyDelegatesRule();
        $scope = self::createStub(Scope::class);
        $scope->method('getClassReflection')->willReturn(null);

        self::assertSame([], $rule->processNode($method, $scope));
    }

    public function test_skips_reset_cache_and_accessor_methods(): void
    {
        foreach (['resetCache', 'getFactory', 'getConfig', 'getProvider', 'getFacade', '__construct'] as $name) {
            $method = $this->parseMethod(sprintf(
                '
                public function %s(): int
                {
                    $value = 1;
                    return $value;
                }
            ',
                $name,
            ));

            self::assertSame([], $this->runRule($method, isFacade: true), $name);
        }
    }

    public function test_returns_no_error_for_cached_arrow_function_delegating_to_factory(): void
    {
        $method = $this->parseMethod('
            public function getExpensiveData(int $id): array
            {
                return $this->cached(fn () => $this->getFactory()->createRepository()->fetchData($id));
            }
        ');

        self::assertSame([], $this->runRule($method, isFacade: true));
    }

    public function test_returns_no_error_for_cached_closure_delegating_to_factory(): void
    {
        $method = $this->parseMethod('
            public function getExpensiveData(int $id): array
            {
                return $this->cached(function () use ($id) {
                    return $this->getFactory()->createRepository()->fetchData($id);
                });
            }
        ');

        self::assertSame([], $this->runRule($method, isFacade: true));
    }

    public function test_returns_no_error_for_cached_delegation_to_config(): void
    {
        $method = $this->parseMethod('
            public function getCachedEndpoint(): string
            {
                return $this->cached(fn () => $this->getConfig()->getEndpoint());
            }
        ');

        self::assertSame([], $this->runRule($method, isFacade: true));
    }

    public function test_reports_error_for_cached_with_non_delegation_closure(): void
    {
        $method = $this->parseMethod('
            public function compute(int $x): int
            {
                return $this->cached(fn () => $x + 1);
            }
        ');

        $errors = $this->runRule($method, isFacade: true);

        self::assertCount(1, $errors);
    }

    public function test_reports_error_for_cached_closure_with_multiple_statements(): void
    {
        $method = $this->parseMethod('
            public function compute(): int
            {
                return $this->cached(function () {
                    $value = $this->getFactory()->createService()->run();
                    return $value;
                });
            }
        ');

        $errors = $this->runRule($method, isFacade: true);

        self::assertCount(1, $errors);
    }

    public function test_get_node_type_returns_class_method(): void
    {
        $rule = new FacadeOnlyDelegatesRule();
        self::assertSame(ClassMethod::class, $rule->getNodeType());
    }

    /**
     * @return list<string>
     */
    private function runRule(ClassMethod $method, bool $isFacade): array
    {
        $rule = new FacadeOnlyDelegatesRule();

        $classReflection = $this->createMock(ClassReflection::class);
        $classReflection->method('getName')->willReturn('App\Module\UserFacade');
        $classReflection->method('isSubclassOf')->with(AbstractFacade::class)->willReturn($isFacade);

        $scope = self::createStub(Scope::class);
        $scope->method('getClassReflection')->willReturn($classReflection);

        $errors = $rule->processNode($method, $scope);

        return array_map(
            static fn ($error): string => is_string($error) ? $error : $error->getMessage(),
            $errors,
        );
    }

    private function parseMethod(string $methodSource, bool $inAbstractClass = false): ClassMethod
    {
        $prefix = $inAbstractClass ? 'abstract ' : '';
        $code = sprintf(
            "<?php\nnamespace App\\Module;\n%sclass UserFacade {\n%s\n}\n",
            $prefix,
            $methodSource,
        );

        $parser = (new ParserFactory())->createForHostVersion();
        $ast = $parser->parse($code);
        assert($ast !== null);

        $class = $ast[0];
        assert($class instanceof \PhpParser\Node\Stmt\Namespace_);
        $classLike = $class->stmts[0];
        assert($classLike instanceof \PhpParser\Node\Stmt\Class_);
        $method = $classLike->stmts[0];
        assert($method instanceof ClassMethod);

        return $method;
    }
}
