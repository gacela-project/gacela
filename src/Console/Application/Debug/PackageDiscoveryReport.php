<?php

declare(strict_types=1);

namespace Gacela\Console\Application\Debug;

use Gacela\Framework\Bootstrap\Package\DiscoveredPackage;
use Gacela\Framework\Bootstrap\Package\InstalledPackagesReader;
use Gacela\Framework\Bootstrap\Package\PackageConfigDeclaration;
use Gacela\Framework\Bootstrap\Package\PackageConfigFinder;
use Gacela\Framework\Bootstrap\Package\PackageDiscoveryRegistry;
use Gacela\Framework\Bootstrap\Package\RefusedPackage;

use function array_map;

/**
 * What package discovery did on this boot, and what it was asked to do.
 *
 * Read by `debug:container`, which describes it, and by the `doctor` check,
 * which judges it -- one capture, so the description and the verdict cannot
 * disagree about the same application.
 *
 * The declarations are re-read from `installed.json` rather than taken from the
 * registry alone, because `dontDiscover(['*'])` means nothing was read and a
 * report that then said "no packages declare a config" would be describing the
 * switch instead of the application. Reading the manifest executes nothing: it
 * is the *config files* that are code, and this never opens one.
 */
final class PackageDiscoveryReport
{
    /**
     * @param list<PackageConfigDeclaration> $declarations every declaration on disk, in installed order
     * @param list<DiscoveredPackage>        $discovered   those that were read and merged, in merge order
     * @param list<RefusedPackage>           $refused      those that were not, and why
     * @param list<string>                   $optedOut     the merged `dontDiscover()` list
     */
    public function __construct(
        public readonly bool $hasInstalledJson,
        public readonly bool $discoveryDisabled,
        public readonly array $declarations,
        public readonly array $discovered,
        public readonly array $refused,
        public readonly array $optedOut,
    ) {
    }

    /**
     * @param list<string> $optedOut
     */
    public static function capture(string $appRootDir, array $optedOut): self
    {
        $reader = new InstalledPackagesReader($appRootDir);

        return new self(
            $reader->read() !== null,
            PackageDiscoveryRegistry::isDisabled(),
            (new PackageConfigFinder($reader))->find(),
            PackageDiscoveryRegistry::discovered(),
            PackageDiscoveryRegistry::refused(),
            $optedOut,
        );
    }

    /**
     * @return list<string>
     */
    public function declaredNames(): array
    {
        return array_map(
            static fn (PackageConfigDeclaration $declaration): string => $declaration->name,
            $this->declarations,
        );
    }

    /**
     * @return list<string>
     */
    public function discoveredNames(): array
    {
        return array_map(
            static fn (DiscoveredPackage $package): string => $package->name,
            $this->discovered,
        );
    }
}
