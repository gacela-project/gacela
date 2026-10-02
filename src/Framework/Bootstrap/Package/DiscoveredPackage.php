<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap\Package;

/**
 * A package whose declared Gacela config was read and merged.
 */
final class DiscoveredPackage
{
    /**
     * @param int $position 1-based place in the merge order, which is Composer's
     *                      installed order -- see {@see PackageDiscovery}
     * @param array<string, list<string>> $sources its psr-4 namespaces and directories
     */
    public function __construct(
        public readonly string $name,
        public readonly string $configFile,
        public readonly int $position,
        public readonly PackageContribution $contribution,
        public readonly array $sources = [],
    ) {
    }
}
