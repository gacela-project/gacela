<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap\Package;

/**
 * A package that declared a Gacela config which was not merged, and why.
 *
 * Both reasons are things an operator has to be able to see: an opt-out that is
 * working looks exactly like a package that was never installed, and a
 * declaration pointing at a file that is not there looks exactly like a package
 * that contributes nothing.
 */
final class RefusedPackage
{
    /**
     * @param array<string, list<string>> $sources the package's psr-4 namespaces and
     *                                             directories, kept out of the scan for
     *                                             attribute members
     */
    private function __construct(
        public readonly string $name,
        public readonly string $configFile,
        public readonly PackageRefusal $reason,
        public readonly array $sources = [],
    ) {
    }

    public static function optedOut(PackageConfigDeclaration $declaration): self
    {
        return new self($declaration->name, $declaration->configFile, PackageRefusal::OptedOut, $declaration->sources);
    }

    public static function missingFile(PackageConfigDeclaration $declaration): self
    {
        return new self($declaration->name, $declaration->configFile, PackageRefusal::MissingFile, $declaration->sources);
    }

    public static function notCallable(PackageConfigDeclaration $declaration): self
    {
        return new self($declaration->name, $declaration->configFile, PackageRefusal::NotCallable, $declaration->sources);
    }
}
