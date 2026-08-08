<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis\Rules;

use Gacela\StaticAnalysis\AnalysedClassInterface;
use Gacela\StaticAnalysis\ClassAnalyserInterface;
use Gacela\StaticAnalysis\ShortName;
use Gacela\StaticAnalysis\Violation;
use PhpParser\Node;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\StaticPropertyFetch;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeFinder;

use function array_slice;
use function count;
use function explode;
use function implode;
use function sprintf;
use function str_ends_with;
use function str_starts_with;
use function strlen;
use function substr;

/**
 * Module A may only reach module B through B's Facade -- gacela's central
 * architectural claim, checked.
 *
 * Nothing in a class name says where a module boundary falls, so the root
 * namespace has to be supplied; that is what keeps this rule opt-in while the
 * pillar rules are on by default.
 */
final class CrossModuleViaFacadeAnalyser implements ClassAnalyserInterface
{
    /**
     * @param list<string> $sharedNamespaces namespaces exempt from the boundary
     *                                       check (shared kernels): references
     *                                       into them are always allowed, and
     *                                       classes inside them are not checked
     */
    public function __construct(
        private readonly string $rootNamespace,
        private readonly int $modulePathSegments = 1,
        private readonly array $sharedNamespaces = [],
    ) {
    }

    /**
     * @return list<Violation>
     */
    public function analyse(ClassLike $node, AnalysedClassInterface $class): array
    {
        $currentClass = $class->name();
        if ($this->isShared($currentClass)) {
            return [];
        }

        $currentModule = $this->moduleOf($currentClass);
        if ($currentModule === null) {
            return [];
        }

        $violations = [];
        $seen = [];

        foreach ($this->referencedClasses($node) as $referenced) {
            $refModule = $this->moduleOf($referenced);
            if ($refModule === null) {
                continue;
            }

            if ($refModule === $currentModule) {
                continue;
            }

            if (str_ends_with(ShortName::of($referenced), 'Facade')) {
                continue;
            }

            if ($this->isShared($referenced)) {
                continue;
            }

            // One class referenced twenty times is one boundary to fix.
            if (isset($seen[$referenced])) {
                continue;
            }

            $seen[$referenced] = true;
            $violations[] = new Violation(
                sprintf(
                    'Class %s references %s from another module (%s). Cross-module access must go through a Facade.',
                    $currentClass,
                    $referenced,
                    $refModule,
                ),
                'gacela.crossModuleWithoutFacade',
            );
        }

        return $violations;
    }

    /**
     * Every place the source writes another class's name.
     *
     * @return list<string>
     */
    private function referencedClasses(ClassLike $node): array
    {
        $refs = (new NodeFinder())->find(
            $node,
            static fn (Node $n): bool => $n instanceof New_
                || $n instanceof StaticCall
                || $n instanceof ClassConstFetch
                || $n instanceof StaticPropertyFetch,
        );

        $names = [];

        foreach ($refs as $ref) {
            /** @var New_|StaticCall|ClassConstFetch|StaticPropertyFetch $ref */
            // `new $class` / `$class::CONST` name nothing to match on.
            if ($ref->class instanceof Name) {
                $names[] = $ref->class->toString();
            }
        }

        return $names;
    }

    private function isShared(string $class): bool
    {
        foreach ($this->sharedNamespaces as $sharedNamespace) {
            if ($class === $sharedNamespace || str_starts_with($class, $sharedNamespace . '\\')) {
                return true;
            }
        }

        return false;
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
}
