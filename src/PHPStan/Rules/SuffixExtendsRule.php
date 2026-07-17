<?php

declare(strict_types=1);

namespace Gacela\PHPStan\Rules;

use PhpParser\Node;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

use function sprintf;

/**
 * @implements Rule<InClassNode>
 */
final class SuffixExtendsRule implements Rule
{
    use ClassReflectionHelperTrait;

    public function __construct(
        private readonly string $suffix,
        private readonly string $expectedParent,
    ) {
    }

    public function getNodeType(): string
    {
        return InClassNode::class;
    }

    public function processNode(Node $node, Scope $scope): array
    {
        $classReflection = $scope->getClassReflection();
        if (!$classReflection instanceof ClassReflection) {
            return [];
        }

        if ($classReflection->isAnonymous()) {
            return [];
        }

        $className = $classReflection->getName();
        $parts = explode('\\', $className);
        /** @var string $shortName */
        $shortName = end($parts);

        if (!str_ends_with($shortName, $this->suffix)) {
            return [];
        }

        if (
            $className !== $this->expectedParent
            && !$this->extendsClass($classReflection, $this->expectedParent)
        ) {
            return [
                RuleErrorBuilder::message(sprintf('Class %s should extend %s', $className, $this->expectedParent))
                    ->identifier('gacela.suffixExtends')
                    ->build(),
            ];
        }

        return [];
    }
}
