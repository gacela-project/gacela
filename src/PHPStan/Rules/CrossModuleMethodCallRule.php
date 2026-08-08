<?php

declare(strict_types=1);

namespace Gacela\PHPStan\Rules;

use Gacela\StaticAnalysis\Rules\CrossModuleMethodCallAnalyser;
use PhpParser\Node;
use PhpParser\Node\Expr\MethodCall;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;

/**
 * @implements Rule<MethodCall>
 *
 * @see CrossModuleMethodCallAnalyser for what is checked and why
 */
final class CrossModuleMethodCallRule implements Rule
{
    private readonly CrossModuleMethodCallAnalyser $analyser;

    /**
     * @param list<string> $sharedNamespaces namespaces exempt from the boundary
     *                                       check (shared kernels)
     */
    public function __construct(
        string $rootNamespace,
        int $modulePathSegments = 1,
        array $sharedNamespaces = [],
    ) {
        $this->analyser = new CrossModuleMethodCallAnalyser($rootNamespace, $modulePathSegments, $sharedNamespaces);
    }

    /**
     * A method call rather than the class node, because only the scope at the
     * call site knows what the receiver is. The class-level scope the sibling
     * rule runs in resolves every local expression to `mixed`.
     */
    public function getNodeType(): string
    {
        return MethodCall::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $scope->getClassReflection();
        if (!$classReflection instanceof ClassReflection) {
            return [];
        }

        return RuleErrors::from($this->analyser->analyse(
            $classReflection->getName(),
            // Empty for an untyped or `mixed` receiver, which is not evidence of
            // a violation -- guessing there would turn the rule into noise.
            $scope->getType($node->var)->getObjectClassNames(),
        ));
    }
}
