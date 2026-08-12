<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\PackageManifest;

/**
 * One import a package's manifest does not account for.
 */
final class UndeclaredImport
{
    public function __construct(
        public readonly string $package,
        public readonly string $file,
        public readonly string $import,
        public readonly string $providedBy,
    ) {
    }
}
