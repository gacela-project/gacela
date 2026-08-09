<?php

declare(strict_types=1);

namespace Gacela\PHPStan\Rules;

use Gacela\StaticAnalysis\Rules\FacadeOnlyDelegatesAnalyser;
use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassMethodNode;
use PHPStan\Rules\Rule;

/**
 * @implements Rule<InClassMethodNode>
 *
 * @see FacadeOnlyDelegatesAnalyser for what is checked and why
 */
final class FacadeOnlyDelegatesRule implements Rule
{
    private readonly FacadeOnlyDelegatesAnalyser $analyser;

    public function __construct()
    {
        $this->analyser = new FacadeOnlyDelegatesAnalyser();
    }

    /**
     * `InClassMethodNode` rather than the bare `ClassMethod`: it carries the
     * class reflection with it, and non-nullably. A plain method node left the
     * class to be fetched from the scope, behind a null check that a method
     * inside a class can never fail.
     */
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
