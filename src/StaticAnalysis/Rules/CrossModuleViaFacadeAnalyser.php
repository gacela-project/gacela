<?php

declare(strict_types=1);

namespace Gacela\StaticAnalysis\Rules;

use Gacela\StaticAnalysis\AnalysedClassInterface;
use Gacela\StaticAnalysis\ClassAnalyserInterface;
use Gacela\StaticAnalysis\FacadeSurface;
use Gacela\StaticAnalysis\ModuleBoundary;
use Gacela\StaticAnalysis\PublicApiSurface;
use Gacela\StaticAnalysis\ReferencedClasses;
use Gacela\StaticAnalysis\Violation;
use PhpParser\Node\Stmt\ClassLike;

use function sprintf;

/**
 * Module A may only reach module B through B's Facade -- gacela's central
 * architectural claim, checked wherever the source writes the other module's
 * name: expressions, headers, traits, attributes and caught exception types.
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

        foreach (ReferencedClasses::namedIn($node) as $referenced) {
            $refModule = $this->boundary->crossedBy($currentModule, $referenced);
            if ($refModule === null) {
                continue;
            }

            if (FacadeSurface::isFacade($referenced)) {
                continue;
            }

            // The owning module published it, so naming it is not a crossing to
            // justify -- that is what publishing a class means.
            if ($this->boundary->isPublicApi($referenced)) {
                continue;
            }

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

}
