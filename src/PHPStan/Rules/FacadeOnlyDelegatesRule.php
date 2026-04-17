<?php

declare(strict_types=1);

namespace Gacela\PHPStan\Rules;

use Gacela\Framework\AbstractFacade;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Expression;
use PhpParser\Node\Stmt\Return_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

use function count;
use function in_array;
use function sprintf;

/**
 * @implements Rule<ClassMethod>
 */
final class FacadeOnlyDelegatesRule implements Rule
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

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if (!$node->isPublic() || $node->isAbstract() || $node->stmts === null) {
            return [];
        }

        if (in_array($node->name->toString(), self::IGNORED_METHODS, true)) {
            return [];
        }

        $classReflection = $scope->getClassReflection();
        if (!$classReflection instanceof ClassReflection) {
            return [];
        }

        if (!$this->extendsClass($classReflection, AbstractFacade::class)) {
            return [];
        }

        $stmts = $node->stmts;
        if ($stmts === []) {
            return [];
        }

        if (count($stmts) !== 1 || !$this->isDelegateStatement($stmts[0])) {
            return [
                RuleErrorBuilder::message(sprintf(
                    'Facade method %s::%s() must only delegate to $this->getFactory()/getConfig()/getProvider(); no inline logic allowed.',
                    $classReflection->getName(),
                    $node->name->toString(),
                ))
                    ->identifier('gacela.facadeOnlyDelegates')
                    ->build(),
            ];
        }

        return [];
    }

    private function extendsClass(ClassReflection $classReflection, string $parent): bool
    {
        foreach ($classReflection->getParents() as $p) {
            if ($p->getName() === $parent) {
                return true;
            }
        }

        return false;
    }

    private function isDelegateStatement(Node $stmt): bool
    {
        $expr = match (true) {
            $stmt instanceof Return_ => $stmt->expr,
            $stmt instanceof Expression => $stmt->expr,
            default => null,
        };

        if (!$expr instanceof \PhpParser\Node\Expr) {
            return false;
        }

        if ($this->isDelegateChain($expr)) {
            return true;
        }

        return $this->isCachedDelegation($expr);
    }

    private function isDelegateChain(Expr $expr): bool
    {
        $current = $expr;
        while (true) {
            if ($current instanceof MethodCall || $current instanceof NullsafeMethodCall) {
                if (
                    $current->var instanceof Variable
                    && $current->var->name === 'this'
                    && $current->name instanceof Identifier
                    && in_array($current->name->toString(), self::ALLOWED_ROOTS, true)
                ) {
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

        $args = $expr->getArgs();
        if ($args === []) {
            return false;
        }

        $callback = $args[0]->value;

        if ($callback instanceof Expr\ArrowFunction) {
            return $this->isDelegateChain($callback->expr);
        }

        if ($callback instanceof Expr\Closure) {
            if (count($callback->stmts) !== 1) {
                return false;
            }

            return $this->isDelegateStatement($callback->stmts[0]);
        }

        return false;
    }
}
