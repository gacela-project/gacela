<?php

declare(strict_types=1);

namespace Gacela\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PHPStan\Analyser\Scope;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;

use function strlen;

/**
 * @implements Rule<Class_>
 */

final class SuffixExtendsRule implements Rule
{
    public function __construct(
        private string $suffix,
        private string $expectedParent,
    ) {
    }

    public function getNodeType(): string
    {
        return Class_::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        if ($node->isAnonymous()) {
            return [];
        }

        $classReflection = $scope->getClassReflection();
        if (!$classReflection instanceof ClassReflection) {
            return [];
        }

        $className = $classReflection->getName();
        $pos = strrpos($className, '\\');
        $shortName = $pos === false ? $className : substr($className, $pos + 1);

        if (substr($shortName, -strlen($this->suffix)) !== $this->suffix) {
            return [];
        }

        if (
            $className !== $this->expectedParent &&
            !$classReflection->isSubclassOf($this->expectedParent)
        ) {
            return [sprintf('Class %s should extend %s', $className, $this->expectedParent)];
        }

        return [];
    }
}
