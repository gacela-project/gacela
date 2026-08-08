<?php

declare(strict_types=1);

namespace Gacela\PHPStan\Rules;

use Gacela\StaticAnalysis\ClassAnalyserInterface;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;

/**
 * Adapts a {@see ClassAnalyserInterface} to PHPStan: resolve the class from the
 * scope, hand the analyser the original AST node, map what comes back.
 *
 * Each rule stays a class of its own because that is how a consumer registers
 * and suppresses it -- only the adapting is shared.
 *
 * @implements Rule<InClassNode>
 *
 * @internal
 */
abstract class InClassAnalyserRule implements Rule
{
    public function __construct(
        private readonly ClassAnalyserInterface $analyser,
    ) {
    }

    final public function getNodeType(): string
    {
        return InClassNode::class;
    }

    final public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $scope->getClassReflection();
        if (!$classReflection instanceof ClassReflection) {
            return [];
        }

        return RuleErrors::from($this->analyser->analyse(
            $node->getOriginalNode(),
            new ReflectionAnalysedClass($classReflection),
        ));
    }
}
