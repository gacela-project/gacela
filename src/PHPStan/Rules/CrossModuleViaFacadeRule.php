<?php

declare(strict_types=1);

namespace Gacela\PHPStan\Rules;

use Gacela\StaticAnalysis\Rules\CrossModuleViaFacadeAnalyser;

/**
 * @see CrossModuleViaFacadeAnalyser for what is checked and why
 */
final class CrossModuleViaFacadeRule extends InClassAnalyserRule
{
    /**
     * @param list<string> $sharedNamespaces namespaces exempt from the boundary
     *                                       check (shared kernels): references
     *                                       into them are always allowed, and
     *                                       classes inside them are not checked
     */
    public function __construct(
        string $rootNamespace,
        int $modulePathSegments = 1,
        array $sharedNamespaces = [],
    ) {
        parent::__construct(
            new CrossModuleViaFacadeAnalyser($rootNamespace, $modulePathSegments, $sharedNamespaces),
        );
    }
}
