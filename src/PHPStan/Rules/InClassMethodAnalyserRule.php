<?php

declare(strict_types=1);

namespace Gacela\PHPStan\Rules;

use Gacela\StaticAnalysis\MethodAnalyserInterface;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Rules\Rule;

/**
 * Adapts a {@see MethodAnalyserInterface} to PHPStan, the way
 * {@see InClassAnalyserRule} adapts a whole-class one.
 *
 * Each rule stays a class of its own because that is how a consumer registers
 * and suppresses it -- only the adapting is shared.
 *
 * @implements Rule<InClassMethodNode>
 *
 * @internal
 */
abstract class InClassMethodAnalyserRule implements Rule
{
    public function __construct(
        private readonly MethodAnalyserInterface $analyser,
    ) {
    }

    final public function getNodeType(): string
    {
        return InClassMethodNode::class;
    }

    final public function processNode(Node $node, Scope $scope): array
    {
        return RuleErrors::from($this->analyser->analyse(
            $node->getOriginalNode(),
            new ReflectionAnalysedClass($node->getClassReflection()),
        ));
    }
}
