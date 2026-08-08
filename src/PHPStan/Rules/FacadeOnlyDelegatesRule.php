<?php

declare(strict_types=1);

namespace Gacela\PHPStan\Rules;

use Gacela\StaticAnalysis\Rules\FacadeOnlyDelegatesAnalyser;
use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;

/**
 * @implements Rule<ClassMethod>
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

    public function getNodeType(): string
    {
        return ClassMethod::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $scope->getClassReflection();
        if (!$classReflection instanceof ClassReflection) {
            return [];
        }

        return RuleErrors::from($this->analyser->analyse(
            $node,
            new ReflectionAnalysedClass($classReflection),
        ));
    }
}
