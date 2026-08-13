<?php

declare(strict_types=1);

namespace Gacela\Console\Domain\PackageManifest;

use function is_array;

/**
 * Which package provides a namespace.
 *
 * Built from `vendor/composer/installed.json` plus the repository's own
 * manifests, and read without executing an autoloader: the question is what a
 * manifest *promises*, and loading a class only proves what happens to be on
 * this machine's include path today.
 *
 * Longest prefix wins, which is composer's own rule and the one that matters
 * here: a monorepo publishes `Gacela\` and `Gacela\LaravelBridge\` as separate
 * packages, so an import resolved by the shorter prefix would name the wrong
 * package and send a reader to fix the wrong manifest.
 */
final class NamespacePackageMap
{
    /**
     * @param array<string, list<string>> $prefixToPackages
     */
    private function __construct(
        private readonly array $prefixToPackages,
    ) {
    }

    /**
     * @param list<ComposerPackage> $localPackages
     * @param list<mixed> $installedPackages the `packages` array of installed.json
     */
    public static function from(array $localPackages, array $installedPackages): self
    {
        $packages = $localPackages;

        // Installed entries are read through ComposerPackage rather than parsed
        // again here: what counts as an autoload prefix is one decision, and
        // two readers of it drift into disagreeing about which package provides
        // a namespace.
        foreach ($installedPackages as $installed) {
            if (!is_array($installed)) {
                continue;
            }

            $package = ComposerPackage::fromDecodedJson($installed, '', '');

            if ($package instanceof ComposerPackage) {
                $packages[] = $package;
            }
        }

        $map = [];

        foreach ($packages as $package) {
            foreach ($package->autoloadPrefixes as $prefix => $_directory) {
                // Every claimant is kept, not the first: Laravel publishes
                // `Illuminate\Support\` from both illuminate/support and
                // illuminate/collections, and picking one arbitrarily reports a
                // package as missing that the manifest already has.
                $map[$prefix][$package->name] = $package->name;
            }
        }

        $claims = [];

        foreach ($map as $prefix => $claimants) {
            $claims[$prefix] = array_values($claimants);
        }

        return new self($claims);
    }

    /**
     * Every package that could provide this class name: the ones sharing the
     * longest prefix that matches it.
     *
     * @return list<string>
     */
    public function packagesProviding(string $className): array
    {
        $prefix = Psr4Prefixes::longestMatching($this->prefixToPackages, $className);

        if ($prefix === null) {
            return [];
        }

        $packages = $this->prefixToPackages[$prefix];
        sort($packages);

        return $packages;
    }

}
