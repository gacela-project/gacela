<?php

declare(strict_types=1);

namespace Gacela\PHPStan\Rules;

use Gacela\StaticAnalysis\PublicApiSurface;
use Gacela\StaticAnalysis\Rules\CrossModuleViaFacadeAnalyser;

/**
 * @see CrossModuleViaFacadeAnalyser for what is checked and why
 */
final class CrossModuleViaFacadeRule extends InClassAnalyserRule
{
    /**
     * @param list<string> $sharedNamespaces  namespaces exempt from the boundary
     *                                        check (shared kernels): references
     *                                        into them are always allowed, and
     *                                        classes inside them are not checked
     * @param list<string> $publicApiSegments sub-namespace names a module
     *                                        publishes; an empty list turns the
     *                                        convention off, leaving `#[PublicApi]`
     *                                        as the only way to export a class
     */
    public function __construct(
        string $rootNamespace,
        int $modulePathSegments = 1,
        array $sharedNamespaces = [],
        array $publicApiSegments = PublicApiSurface::DEFAULT_SEGMENTS,
    ) {
        parent::__construct(
            new CrossModuleViaFacadeAnalyser(
                $rootNamespace,
                $modulePathSegments,
                $sharedNamespaces,
                $publicApiSegments,
            ),
        );
    }
}
