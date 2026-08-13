<?php

declare(strict_types=1);

namespace Gacela\PHPStan\Rules;

use Gacela\StaticAnalysis\Rules\CacheableKeyIgnoresArgumentsAnalyser;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Rules\Rule;

/**
 * @implements Rule<InClassMethodNode>
 *
 * @see CacheableKeyIgnoresArgumentsAnalyser for what is checked and why
 */
final class CacheableKeyIgnoresArgumentsRule implements Rule
{
    private readonly CacheableKeyIgnoresArgumentsAnalyser $analyser;

    public function __construct()
    {
        $this->analyser = new CacheableKeyIgnoresArgumentsAnalyser();
    }

    public function getNodeType(): string
    {
        return InClassMethodNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        return RuleErrors::from($this->analyser->analyse(
            $node->getOriginalNode(),
            new ReflectionAnalysedClass($node->getClassReflection()),
        ));
    }
}
