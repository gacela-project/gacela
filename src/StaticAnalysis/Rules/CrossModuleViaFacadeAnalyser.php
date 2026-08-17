<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis\Rules;

use Gacela\StaticAnalysis\AnalysedClassInterface;
use Gacela\StaticAnalysis\ClassAnalyserInterface;
use Gacela\StaticAnalysis\ModuleBoundary;
use Gacela\StaticAnalysis\PublicApiSurface;
use Gacela\StaticAnalysis\ResolvedName;
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

use function sprintf;
use function str_ends_with;

/**
 * Module A may only reach module B through B's Facade -- gacela's central
 * architectural claim, checked wherever the source writes the other module's
 * name: `new`, a static call, a class constant, a static property.
 *
 * A boundary crossed through an injected dependency writes no name at the call
 * site, so it is invisible here; {@see CrossModuleMethodCallAnalyser} resolves
 * those by type instead.
 */
final class CrossModuleViaFacadeAnalyser implements ClassAnalyserInterface
{
    private readonly ModuleBoundary $boundary;

    /**
     * @param list<string> $sharedNamespaces  namespaces exempt from the boundary
     *                                        check (shared kernels): references
     *                                        into them are always allowed, and
     *                                        classes inside them are not checked
     * @param list<string> $publicApiSegments sub-namespace names a module
     *                                        publishes; an empty list leaves
     *                                        `#[PublicApi]` as the only way to
     *                                        export a class
     */
    public function __construct(
        string $rootNamespace,
        int $modulePathSegments = 1,
        array $sharedNamespaces = [],
        array $publicApiSegments = PublicApiSurface::DEFAULT_SEGMENTS,
    ) {
        $this->boundary = new ModuleBoundary(
            $rootNamespace,
            $modulePathSegments,
            $sharedNamespaces,
            $publicApiSegments,
        );
    }

    /**
     * @return list<Violation>
     */
    public function analyse(ClassLike $node, AnalysedClassInterface $class): array
    {
        $currentClass = $class->name();
        if ($this->boundary->isShared($currentClass)) {
            return [];
        }

        $currentModule = $this->boundary->moduleOf($currentClass);
        if ($currentModule === null) {
            return [];
        }

        $violations = [];
        $seen = [];

        foreach ($this->referencedClasses($node) as $referenced) {
            $refModule = $this->boundary->crossedBy($currentModule, $referenced);
            if ($refModule === null) {
                continue;
            }

            if (str_ends_with(ShortName::of($referenced), 'Facade')) {
                continue;
            }

            // The owning module published it, so naming it is not a crossing to
            // justify -- that is what publishing a class means.
            if ($this->boundary->isPublicApi($referenced)) {
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
                sprintf('Reach %s through its Facade.', $refModule),
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
            /** @var ClassConstFetch|New_|StaticCall|StaticPropertyFetch $ref */
            // `new $class` / `$class::CONST` name nothing to match on.
            if ($ref->class instanceof Name) {
                $names[] = ResolvedName::of($ref->class);
            }
        }

        return $names;
    }
}
