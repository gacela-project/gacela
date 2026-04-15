<?php

declare(strict_types=1);

namespace Gacela\PHPStan\Rules;

use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Name;
use PhpParser\NodeFinder;
use PHPStan\Analyser\Scope;
use PHPStan\Node\InClassNode;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Rules\Rule;
use PHPStan\Rules\RuleErrorBuilder;

use function array_slice;
use function count;
use function explode;
use function implode;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function strrpos;
use function substr;

/**
 * @implements Rule<InClassNode>
 */
final class CrossModuleViaFacadeRule implements Rule
{
    public function __construct(
        private readonly string $rootNamespace,
        private readonly int $modulePathSegments = 1,
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

        $currentClass = $classReflection->getName();
        $currentModule = $this->moduleOf($currentClass);
        if ($currentModule === null) {
            return [];
        }

        $classNode = $node->getOriginalNode();
        $nodeFinder = new NodeFinder();
        $errors = [];
        $seen = [];

        $refs = $nodeFinder->find(
            $classNode,
            static fn (Node $n): bool => $n instanceof New_
                || $n instanceof StaticCall
                || $n instanceof ClassConstFetch
                || $n instanceof StaticPropertyFetch,
        );

        foreach ($refs as $ref) {
            /** @var New_|StaticCall|ClassConstFetch|StaticPropertyFetch $ref */
            if (!$ref->class instanceof Name) {
                continue;
            }

            $referenced = $ref->class->toString();
            $refModule = $this->moduleOf($referenced);
            if ($refModule === null) {
                continue;
            }

            if ($refModule === $currentModule) {
                continue;
            }

            if (str_ends_with($this->shortName($referenced), 'Facade')) {
                continue;
            }

            $key = $currentClass . '|' . $referenced;
            if (isset($seen[$key])) {
                continue;
            }

            $seen[$key] = true;
            $errors[] = RuleErrorBuilder::message(sprintf(
                'Class %s references %s from another module (%s). Cross-module access must go through a Facade.',
                $currentClass,
                $referenced,
                $refModule,
            ))
                ->identifier('gacela.crossModuleWithoutFacade')
                ->build();
        }

        return $errors;
    }

    private function moduleOf(string $class): ?string
    {
        $prefix = $this->rootNamespace . '\\';
        if (!str_starts_with($class, $prefix)) {
            return null;
        }

        $remainder = substr($class, strlen($prefix));
        $segments = explode('\\', $remainder);
        if (count($segments) <= $this->modulePathSegments) {
            return null;
        }

        return $this->rootNamespace . '\\' . implode('\\', array_slice($segments, 0, $this->modulePathSegments));
    }

    private function shortName(string $className): string
    {
        $pos = strrpos($className, '\\');
        return $pos === false ? $className : substr($className, $pos + 1);
    }
}
