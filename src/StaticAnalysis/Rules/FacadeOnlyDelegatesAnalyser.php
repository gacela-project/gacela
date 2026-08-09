<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis\Rules;

use Gacela\Framework\AbstractFacade;
use Gacela\StaticAnalysis\AnalysedClassInterface;
use Gacela\StaticAnalysis\Violation;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Closure;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Return_;

use function count;
use function in_array;
use function sprintf;

/**
 * A Facade method is a name for something the Factory does. Logic living in it
 * is logic no other module can reach and no test can address directly.
 */
final class FacadeOnlyDelegatesAnalyser
{
    private const ALLOWED_ROOTS = ['getFactory', 'getConfig', 'getProvider'];

    private const IGNORED_METHODS = [
        '__construct',
        'resetCache',
        'getFactory',
        'getConfig',
        'getProvider',
        'getFacade',
    ];

    /**
     * @return list<Violation>
     */
    public function analyse(ClassMethod $method, AnalysedClassInterface $class): array
    {
        if (!$method->isPublic() || $method->isAbstract()) {
            return [];
        }

        // Separate from the guard above: a concrete public method without a body
        // is an interface method, and folding the two into one `||` chain makes
        // an equivalent mutant nothing can distinguish.
        if ($method->stmts === null) {
            return [];
        }

        if (in_array($method->name->toString(), self::IGNORED_METHODS, true)) {
            return [];
        }

        if (!$class->extendsClass(AbstractFacade::class)) {
            return [];
        }

        $stmts = $method->stmts;
        if ($stmts === []) {
            return [];
        }

        if (count($stmts) === 1 && $this->isDelegateStatement($stmts[0])) {
            return [];
        }

        return [
            new Violation(
                sprintf(
                    'Facade method %s::%s() must only delegate to $this->getFactory()/getConfig()/getProvider(); no inline logic allowed.',
                    $class->name(),
                    $method->name->toString(),
                ),
                'gacela.facadeOnlyDelegates',
                'Move the logic into the Factory and have this method call it.',
            ),
        ];
    }

    private function isDelegateStatement(Node $stmt): bool
    {
        $expr = match (true) {
            $stmt instanceof Return_ => $stmt->expr,
            $stmt instanceof Expression => $stmt->expr,
            default => null,
        };

        if (!$expr instanceof Expr) {
            return false;
        }

        if ($this->isDelegateChain($expr)) {
            return true;
        }

        return $this->isCachedDelegation($expr);
    }

    /**
     * Walks back down a chain such as `$this->getFactory()->create()->run()` to
     * whatever it started from, which is the only part this rule judges.
     */
    private function isDelegateChain(Expr $expr): bool
    {
        $current = $expr;
        while (true) {
            if ($current instanceof MethodCall || $current instanceof NullsafeMethodCall) {
                if ($this->isAllowedRoot($current)) {
                    return true;
                }

                $current = $current->var;
                continue;
            }

            if ($current instanceof PropertyFetch || $current instanceof NullsafePropertyFetch) {
                $current = $current->var;
                continue;
            }

            return false;
        }
    }

    private function isAllowedRoot(MethodCall|NullsafeMethodCall $call): bool
    {
        return $call->var instanceof Variable
            && $call->var->name === 'this'
            && $call->name instanceof Identifier
            && in_array($call->name->toString(), self::ALLOWED_ROOTS, true);
    }

    /**
     * Recognise $this->cached(fn () => <delegation>) as a valid pattern.
     */
    private function isCachedDelegation(Expr $expr): bool
    {
        if (!$expr instanceof MethodCall) {
            return false;
        }

        if (
            !$expr->var instanceof Variable
            || $expr->var->name !== 'this'
            || !$expr->name instanceof Identifier
            || $expr->name->toString() !== 'cached'
        ) {
            return false;
        }

        $callback = $expr->getArgs()[0]->value ?? null;

        if ($callback instanceof ArrowFunction) {
            return $this->isDelegateChain($callback->expr);
        }

        if ($callback instanceof Closure) {
            if (count($callback->stmts) !== 1) {
                return false;
            }

            return $this->isDelegateStatement($callback->stmts[0]);
        }

        return false;
    }
}
