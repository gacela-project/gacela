<?php

declare(strict_types=1);

namespace GacelaTest\Unit\PHPStan\Rules;

use Gacela\Framework\AbstractFacade;
use Gacela\PHPStan\Rules\FacadeOnlyDelegatesRule;
use PhpParser\Modifiers;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp\Plus;
use PhpParser\Node\Expr\Closure as ClosureNode;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPUnit\Framework\TestCase;

final class FacadeOnlyDelegatesRuleTest extends TestCase
{
    public function test_returns_no_error_for_single_return_delegating_to_factory(): void
    {
        // return $this->getFactory()->createService()->run();
        $method = $this->publicMethod('doSomething', [
            new Return_($this->delegationChain('getFactory', 'createService', 'run')),
        ]);

        self::assertSame([], $this->runRule($method, isFacade: true));
    }

    public function test_returns_no_error_for_single_expression_delegating_to_factory(): void
    {
        // $this->getFactory()->createService()->run();
        $method = $this->publicMethod('doSomething', [
            new Expression($this->delegationChain('getFactory', 'createService', 'run')),
        ]);

        self::assertSame([], $this->runRule($method, isFacade: true));
    }

    public function test_returns_no_error_for_get_config_delegation(): void
    {
        // return $this->getConfig()->getEndpoint();
        $method = $this->publicMethod('getEndpoint', [
            new Return_($this->delegationChain('getConfig', 'getEndpoint')),
        ]);

        self::assertSame([], $this->runRule($method, isFacade: true));
    }

    public function test_returns_no_error_for_get_provider_delegation(): void
    {
        // return $this->getProvider()->getClient();
        $method = $this->publicMethod('getClient', [
            new Return_($this->delegationChain('getProvider', 'getClient')),
        ]);

        self::assertSame([], $this->runRule($method, isFacade: true));
    }

    public function test_returns_no_error_for_property_access_on_delegate_chain(): void
    {
        // return $this->getFactory()->createThing()->value;
        $method = $this->publicMethod('value', [
            new Return_(new PropertyFetch(
                $this->delegationChain('getFactory', 'createThing'),
                new Identifier('value'),
            )),
        ]);

        self::assertSame([], $this->runRule($method, isFacade: true));
    }

    public function test_returns_no_error_for_nullsafe_chain(): void
    {
        // return $this->getFactory()?->createThing()?->value;
        $factory = new NullsafeMethodCall(new Variable('this'), new Identifier('getFactory'));
        $create = new NullsafeMethodCall($factory, new Identifier('createThing'));
        $prop = new NullsafePropertyFetch($create, new Identifier('value'));
        $method = $this->publicMethod('maybe', [new Return_($prop)]);

        self::assertSame([], $this->runRule($method, isFacade: true));
    }

    public function test_returns_no_error_for_empty_body(): void
    {
        $method = $this->publicMethod('noop', []);

        self::assertSame([], $this->runRule($method, isFacade: true));
    }

    public function test_reports_error_for_multiple_statements(): void
    {
        // $value = $this->getFactory()->createService()->run();
        // return $value;
        $method = $this->publicMethod('compute', [
            new Expression(new Assign(
                new Variable('value'),
                $this->delegationChain('getFactory', 'createService', 'run'),
            )),
            new Return_(new Variable('value')),
        ]);

        $errors = $this->runRule($method, isFacade: true);

        self::assertCount(1, $errors);
        self::assertStringContainsString('compute', $errors[0]);
        self::assertStringContainsString('delegate', $errors[0]);
    }

    public function test_reports_error_for_local_logic_without_delegation(): void
    {
        // return $x + 1;
        $method = $this->publicMethod('compute', [
            new Return_(new Plus(new Variable('x'), new Int_(1))),
        ]);

        $errors = $this->runRule($method, isFacade: true);

        self::assertCount(1, $errors);
    }

    public function test_reports_error_for_control_flow(): void
    {
        // if ($flag) { return $this->getFactory()->createA()->run(); }
        // return $this->getFactory()->createB()->run();
        $method = $this->publicMethod('conditional', [
            new If_(new Variable('flag'), [
                'stmts' => [new Return_($this->delegationChain('getFactory', 'createA', 'run'))],
            ]),
            new Return_($this->delegationChain('getFactory', 'createB', 'run')),
        ]);

        $errors = $this->runRule($method, isFacade: true);

        self::assertCount(1, $errors);
    }

    public function test_reports_error_when_root_is_not_an_allowed_accessor(): void
    {
        // return $this->somethingElse()->run();
        $method = $this->publicMethod('compute', [
            new Return_($this->delegationChain('somethingElse', 'run')),
        ]);

        $errors = $this->runRule($method, isFacade: true);

        self::assertCount(1, $errors);
    }

    public function test_skips_non_public_methods(): void
    {
        $method = $this->buildMethod('helper', Modifiers::PROTECTED, [
            new Expression(new Assign(new Variable('value'), new Int_(42))),
            new Return_(new Variable('value')),
        ]);

        self::assertSame([], $this->runRule($method, isFacade: true));
    }

    public function test_skips_abstract_methods(): void
    {
        $method = new ClassMethod(new Identifier('contract'), [
            'flags' => Modifiers::PUBLIC | Modifiers::ABSTRACT,
            'stmts' => null,
        ]);

        self::assertSame([], $this->runRule($method, isFacade: true));
    }

    public function test_skips_classes_that_are_not_facades(): void
    {
        $method = $this->publicMethod('compute', [
            new Expression(new Assign(new Variable('value'), new Int_(1))),
            new Return_(new Variable('value')),
        ]);

        self::assertSame([], $this->runRule($method, isFacade: false));
    }

    public function test_skips_when_class_reflection_is_null(): void
    {
        $method = $this->publicMethod('compute', [
            new Expression(new Assign(new Variable('value'), new Int_(1))),
            new Return_(new Variable('value')),
        ]);

        $rule = new FacadeOnlyDelegatesRule();
        $scope = self::createStub(Scope::class);
        $scope->method('getClassReflection')->willReturn(null);

        self::assertSame([], $rule->processNode($method, $scope));
    }

    public function test_skips_reset_cache_and_accessor_methods(): void
    {
        foreach (['resetCache', 'getFactory', 'getConfig', 'getProvider', 'getFacade', '__construct'] as $name) {
            $method = $this->publicMethod($name, [
                new Expression(new Assign(new Variable('value'), new Int_(1))),
                new Return_(new Variable('value')),
            ]);

            self::assertSame([], $this->runRule($method, isFacade: true), $name);
        }
    }

    public function test_returns_no_error_for_cached_arrow_function_delegating_to_factory(): void
    {
        // return $this->cached(fn () => $this->getFactory()->createRepository()->fetchData());
        $method = $this->publicMethod('getExpensiveData', [
            new Return_($this->cachedArrowFn(
                $this->delegationChain('getFactory', 'createRepository', 'fetchData'),
            )),
        ]);

        self::assertSame([], $this->runRule($method, isFacade: true));
    }

    public function test_returns_no_error_for_cached_closure_delegating_to_factory(): void
    {
        // return $this->cached(function () { return $this->getFactory()->createRepository()->fetchData(); });
        $method = $this->publicMethod('getExpensiveData', [
            new Return_($this->cachedClosure([
                new Return_($this->delegationChain('getFactory', 'createRepository', 'fetchData')),
            ])),
        ]);

        self::assertSame([], $this->runRule($method, isFacade: true));
    }

    public function test_returns_no_error_for_cached_delegation_to_config(): void
    {
        // return $this->cached(fn () => $this->getConfig()->getEndpoint());
        $method = $this->publicMethod('getCachedEndpoint', [
            new Return_($this->cachedArrowFn(
                $this->delegationChain('getConfig', 'getEndpoint'),
            )),
        ]);

        self::assertSame([], $this->runRule($method, isFacade: true));
    }

    public function test_reports_error_for_cached_with_non_delegation_closure(): void
    {
        // return $this->cached(fn () => $x + 1);
        $method = $this->publicMethod('compute', [
            new Return_($this->cachedArrowFn(
                new Plus(new Variable('x'), new Int_(1)),
            )),
        ]);

        $errors = $this->runRule($method, isFacade: true);

        self::assertCount(1, $errors);
    }

    public function test_reports_error_for_cached_closure_with_multiple_statements(): void
    {
        // return $this->cached(function () { $v = ...; return $v; });
        $method = $this->publicMethod('compute', [
            new Return_($this->cachedClosure([
                new Expression(new Assign(
                    new Variable('value'),
                    $this->delegationChain('getFactory', 'createService', 'run'),
                )),
                new Return_(new Variable('value')),
            ])),
        ]);

        $errors = $this->runRule($method, isFacade: true);

        self::assertCount(1, $errors);
    }

    public function test_get_node_type_returns_class_method(): void
    {
        $rule = new FacadeOnlyDelegatesRule();
        self::assertSame(ClassMethod::class, $rule->getNodeType());
    }

    // -- helpers ----------------------------------------------------------

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
            static fn ($error): string => $error->getMessage(),
            $errors,
        );
    }

    /**
     * @param list<\PhpParser\Node\Stmt> $stmts
     */
    private function publicMethod(string $name, array $stmts): ClassMethod
    {
        return $this->buildMethod($name, Modifiers::PUBLIC, $stmts);
    }

    /**
     * @param list<\PhpParser\Node\Stmt> $stmts
     */
    private function buildMethod(string $name, int $flags, array $stmts): ClassMethod
    {
        return new ClassMethod(new Identifier($name), [
            'flags' => $flags,
            'stmts' => $stmts,
        ]);
    }

    /**
     * Build $this->root()->a()->b()...
     */
    private function delegationChain(string $root, string ...$calls): MethodCall
    {
        $expr = new MethodCall(new Variable('this'), new Identifier($root));
        foreach ($calls as $name) {
            $expr = new MethodCall($expr, new Identifier($name));
        }

        return $expr;
    }

    /**
     * Build $this->cached(fn () => $body)
     */
    private function cachedArrowFn(Expr $body): MethodCall
    {
        return new MethodCall(
            new Variable('this'),
            new Identifier('cached'),
            [new Arg(new ArrowFunction(['expr' => $body]))],
        );
    }

    /**
     * Build $this->cached(function () { ...$stmts })
     *
     * @param list<\PhpParser\Node\Stmt> $stmts
     */
    private function cachedClosure(array $stmts): MethodCall
    {
        return new MethodCall(
            new Variable('this'),
            new Identifier('cached'),
            [new Arg(new ClosureNode(['stmts' => $stmts]))],
        );
    }
}
