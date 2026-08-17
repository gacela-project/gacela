<?php

declare(strict_types=1);

namespace Gacela\Framework\Bootstrap\Package;

use Closure;
use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Bootstrap\SetupGacelaInterface;
use Gacela\Framework\Config\FileIoInterface;
use Gacela\Framework\Config\GacelaFileConfig\Factory\GacelaConfigUsingGacelaPhpFileFactory;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;

use function in_array;

/**
 * Merges the Gacela configuration installed packages declare.
 *
 * A package contributes to an application by being installed, the way a Laravel
 * package registers a provider through `extra.laravel.providers`:
 *
 * ```json
 * { "extra": { "gacela": { "config": "config/gacela.php" } } }
 * ```
 *
 * That file returns a `callable(GacelaConfig): void` -- the same shape a
 * project's own `gacela.php` returns, so there is one config format and a
 * package author has nothing new to learn.
 *
 * ## Order
 *
 * Packages are merged in Composer's installed order, and the project's own
 * configuration is merged after all of them, so the project always has the last
 * word. Between two packages that declare the same thing, the later-installed
 * one wins -- but installed order is Composer's, decided by the dependency
 * graph and by when things were added to `composer.json`, and no application
 * controls it. A package that needs to win must not rely on it; it should give
 * the consuming project something to call.
 *
 * ## Trust
 *
 * A discovered config is arbitrary PHP, run inside `Gacela::bootstrap()`, in the
 * application's own process, with everything the application can reach. That is
 * the same bargain a Laravel provider or a Composer install script already asks
 * for, and `GacelaConfig::dontDiscover()` is the control over it: naming a
 * package there means its file is never opened, and `dontDiscover(['*'])` means
 * no package's file is opened at all.
 */
final class PackageDiscovery
{
    /** Refuses every package, installed now or later. */
    private const string EVERYTHING = '*';

    /**
     * @param ?Closure(): string $cacheDirProvider where to remember the resolved
     *                                             list of declarations. A closure
     *                                             rather than the directory,
     *                                             because asking `Config` for it
     *                                             memoizes it -- and an
     *                                             application with no installed
     *                                             packages must not end up with a
     *                                             materialised cache directory it
     *                                             never writes to. Null means the
     *                                             answer is not remembered.
     */
    public function __construct(
        private readonly string $appRootDir,
        private readonly SetupGacelaInterface $setup,
        private readonly FileIoInterface $fileIo,
        private readonly ?Closure $cacheDirProvider = null,
    ) {
    }

    /**
     * Every discovered package's configuration, merged into the setup and
     * assembled, in merge order.
     *
     * @param list<string> $dontDiscover Composer package names, matched exactly,
     *                                   or `['*']` to read nothing
     *
     * @return list<GacelaConfigFileInterface>
     */
    public function discover(array $dontDiscover): array
    {
        PackageDiscoveryRegistry::reset();

        // Checked before the disk is touched: `dontDiscover(['*'])` is a project
        // saying it wants nothing but its own configuration deciding what runs
        // at boot, and honouring it by reading every declaration first and then
        // throwing them away would be a strange way to agree.
        if (in_array(self::EVERYTHING, $dontDiscover, true)) {
            PackageDiscoveryRegistry::disable();

            return [];
        }

        $configFiles = [];
        $position = 0;

        foreach ($this->declarations() as $declaration) {
            if (in_array($declaration->name, $dontDiscover, true)) {
                PackageDiscoveryRegistry::refuse(RefusedPackage::optedOut($declaration));
                continue;
            }

            // A package that names a file it did not ship contributes nothing
            // and does not stop the boot. `doctor` is where that is reported:
            // `composer require` must never be able to break an application.
            if (!$this->fileIo->existsFile($declaration->configFile)) {
                PackageDiscoveryRegistry::refuse(RefusedPackage::missingFile($declaration));
                continue;
            }

            $factory = new GacelaConfigUsingGacelaPhpFileFactory(
                $declaration->configFile,
                $this->setup,
                $this->fileIo,
            );

            $packageSetup = $factory->createSetup();

            if (!$packageSetup instanceof SetupGacela) {
                PackageDiscoveryRegistry::refuse(RefusedPackage::notCallable($declaration));
                continue;
            }

            // Merges into the base setup and assembles from the memoized setup
            // above, so the file is read once.
            $configFile = $factory->createGacelaFileConfig();
            $configFiles[] = $configFile;

            PackageDiscoveryRegistry::record(new DiscoveredPackage(
                $declaration->name,
                $declaration->configFile,
                ++$position,
                PackageContribution::of($packageSetup, $configFile),
            ));
        }

        return $configFiles;
    }

    /**
     * @return list<PackageConfigDeclaration>
     */
    private function declarations(): array
    {
        $reader = new InstalledPackagesReader($this->appRootDir);
        $fingerprint = $reader->fingerprint();

        // No `installed.json` means nothing was installed through Composer
        // against this root -- a fixture directory used as an application root,
        // a checkout with no install. Silently nothing, exactly as the `doctor`
        // check reading the same file already treats it.
        if ($fingerprint === null) {
            return [];
        }

        // Built here rather than in the constructor, so an application with no
        // `installed.json` never asks where the cache directory is.
        $cache = $this->cache();

        $cached = $cache?->read($fingerprint);

        if ($cached !== null) {
            return $cached;
        }

        $declarations = (new PackageConfigFinder($reader))->find();

        $cache?->write($fingerprint, $declarations);

        return $declarations;
    }

    private function cache(): ?PackageConfigCache
    {
        $provider = $this->cacheDirProvider;

        return $provider instanceof Closure ? new PackageConfigCache($provider()) : null;
    }
}
