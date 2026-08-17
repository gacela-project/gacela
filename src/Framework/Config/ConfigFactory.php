<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Closure;
use Gacela\Framework\Bootstrap\Package\InstalledPackagesReader;
use Gacela\Framework\Bootstrap\Package\PackageDiscovery;
use Gacela\Framework\Bootstrap\Package\PackageDiscoveryRegistry;
use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Bootstrap\SetupGacelaInterface;
use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\ClassResolver\GlobalKey;
use Gacela\Framework\ClassResolver\ResolvableTypes;
use Gacela\Framework\Config\GacelaFileConfig\Factory\GacelaConfigFromBootstrapFactory;
use Gacela\Framework\Config\GacelaFileConfig\Factory\GacelaConfigUsingGacelaPhpFileFactory;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;
use Gacela\Framework\Config\PathNormalizer\AbsolutePathNormalizer;
use Gacela\Framework\Config\PathNormalizer\WithoutSuffixAbsolutePathStrategy;
use Gacela\Framework\Config\PathNormalizer\WithSuffixAbsolutePathStrategy;
use Gacela\Framework\Event\Bootstrap\PackageConfigMergedEvent;
use Gacela\Framework\Event\Dispatcher\EventDispatchingCapabilities;

use function sprintf;

/**
 * Assembles the configuration during bootstrap, before any module exists.
 *
 * Deliberately not an AbstractFactory: that base is for module factories and
 * pulls in the container, the config resolver and the provider resolvers —
 * none of which are available (or needed) this early.
 */
final class ConfigFactory
{
    use EventDispatchingCapabilities;

    private const GACELA_PHP_CONFIG_FILENAME = 'gacela';

    private const GACELA_PHP_CONFIG_EXTENSION = '.php';

    private static ?GacelaConfigFileInterface $gacelaFileConfig = null;

    /**
     * What the memo above was built from. The memo is keyed on identity rather
     * than served unconditionally, because a second `Gacela::bootstrap()` in
     * one process builds a new setup and used to be handed the first one's
     * merged config — bindings included — unless the caller opted into
     * `resetInMemoryCache()`. Holding the instance rather than a hash of it
     * keeps the comparison exact and rules out `spl_object_id()` reuse.
     */
    private static ?SetupGacelaInterface $memoizedSetup = null;

    private static ?string $memoizedAppRootDir = null;

    /**
     * @param ?Closure(): string $cacheDirProvider where the resolved list of
     *                                             package config files is
     *                                             remembered between boots.
     *                                             Null means it is not: this
     *                                             factory is also built directly,
     *                                             and `Config` is the only thing
     *                                             that knows where the cache
     *                                             directory is.
     */
    public function __construct(
        private readonly string $appRootDir,
        private readonly SetupGacelaInterface $setup,
        private readonly ?Closure $cacheDirProvider = null,
    ) {
    }

    public static function resetCache(): void
    {
        self::$gacelaFileConfig = null;
        self::$memoizedSetup = null;
        self::$memoizedAppRootDir = null;
    }

    public function createConfigLoader(): ConfigLoader
    {
        return new ConfigLoader(
            $this->createGacelaFileConfig(),
            $this->createPathFinder(),
            $this->createPathNormalizer(),
        );
    }

    public function createGacelaFileConfig(): GacelaConfigFileInterface
    {
        $memoized = $this->memoized();
        if ($memoized instanceof GacelaConfigFileInterface) {
            return $memoized;
        }

        $gacelaConfigFiles = [];
        $fileIo = $this->createFileIo();

        $gacelaPhpDefaultPath = $this->getGacelaPhpDefaultPath();

        // Bootstrapping without a closure builds the setup by reading this very
        // file, and the base of the merge below is assembled from that setup --
        // so everything it declares is already in. Reading it again as a
        // separate source merges a second copy onto the first: two config items
        // for one addAppConfig(), and a plugin declared once running twice,
        // since a plugin is a class-string or a closure and has none of the
        // identity a plugin *stack* deduplicates by.
        $alreadyInTheBase = $this->setup instanceof SetupGacela
            && $this->setup->wasBuiltFrom($gacelaPhpDefaultPath);

        $projectFactory = !$alreadyInTheBase && $fileIo->existsFile($gacelaPhpDefaultPath)
            ? new GacelaConfigUsingGacelaPhpFileFactory($gacelaPhpDefaultPath, $this->setup, $fileIo)
            : null;

        // Read before anything is merged, and memoized there so the file is
        // still read exactly once: the discovery below runs arbitrary PHP from
        // every installed package that declares a config, and what a project
        // refuses is written in this very file.
        $projectSetup = $projectFactory?->createSetup();

        // First, so the project has the last word: the fold below lets a later
        // source win, and so does the setup merge each of these performs.
        $gacelaConfigFiles = $this->discoverPackages($fileIo, $projectSetup);

        if ($projectFactory instanceof GacelaConfigUsingGacelaPhpFileFactory) {
            $gacelaConfigFiles[] = $projectFactory->createGacelaFileConfig();
        }

        $gacelaPhpPath = $this->getGacelaPhpPathFromEnv();
        if ($fileIo->existsFile($gacelaPhpPath)) {
            $factoryFromGacelaPhp = new GacelaConfigUsingGacelaPhpFileFactory($gacelaPhpPath, $this->setup, $fileIo);
            $gacelaConfigFiles[] = $factoryFromGacelaPhp->createGacelaFileConfig();
        }

        $merged = array_reduce(
            $gacelaConfigFiles,
            static fn (GacelaConfigFileInterface $carry, GacelaConfigFileInterface $item): GacelaConfigFileInterface => $carry->merge($item),
            (new GacelaConfigFromBootstrapFactory($this->setup))->createGacelaFileConfig(),
        );

        // Only the merged file knows every kind the project declared: each
        // source assembles its own, and a kind declared in `gacela.php` is
        // invisible to the bootstrap closure's. So this is also where the
        // ambiguity rule has to run -- two sources can each declare the same
        // suffix for a different kind and both pass their own builder's check.
        $suffixTypes = $merged->getSuffixTypes();
        ResolvableTypes::assertUnambiguous($suffixTypes);

        if (ResolvableTypes::syncFrom($suffixTypes)) {
            // Both memos hold answers about which suffix names which kind.
            GlobalKey::resetCache();
            ClassInfo::resetCache();
        }

        $this->announceDiscoveredPackages();

        return $this->memoize($merged);
    }

    /**
     * The dimensions this project declared, resolved from the environment.
     *
     * @internal
     */
    public function dimensions(): ConfigDimensions
    {
        return ConfigDimensions::fromEnvironment($this->setup->getConfigDimensions());
    }

    /**
     * The configuration every installed package declared, merged before the
     * project's own.
     *
     * The opt-out is read from both places a project can write it: the bootstrap
     * closure, which built the base setup, and `gacela.php`, whose setup was
     * built above without being merged yet. It cannot be read from
     * `gacela-{APP_ENV}.php` -- that file is merged after this returns, by which
     * time the code it would refuse has run. `doctor` reports an opt-out written
     * there rather than letting it look effective.
     *
     * @return list<GacelaConfigFileInterface>
     */
    private function discoverPackages(FileIoInterface $fileIo, ?SetupGacela $projectSetup): array
    {
        // One `is_file()` before anything at all is built, because for most
        // applications the answer is "nothing to discover" and it should cost
        // about that much. There is no manifest under a directory Composer never
        // installed into -- a fixture used as an application root, a checkout
        // with no `vendor/` of its own.
        //
        // Measured, not assumed: without this, `BootstrapBench` came to +12.24%
        // warm on CI against a +/-10% gate, and every bit of it was spent finding
        // out there was nothing to read. An application that does have a manifest
        // still pays for the cached declaration list, which is the honest price
        // of the feature.
        if (!$fileIo->existsFile((new InstalledPackagesReader($this->appRootDir))->path())) {
            PackageDiscoveryRegistry::reset();

            return [];
        }

        $fromBootstrap = $this->setup instanceof SetupGacela ? $this->setup->getDontDiscover() : [];

        $discovery = new PackageDiscovery(
            $this->appRootDir,
            $this->setup,
            $fileIo,
            $this->packageCacheDirProvider(),
        );

        return $discovery->discover([
            ...$fromBootstrap,
            ...($projectSetup?->getDontDiscover() ?? []),
        ]);
    }

    /**
     * Null when there is nowhere to remember the answer, which makes every boot
     * read `installed.json` again. `setFileCache(false)` is a project asking for
     * exactly that trade for class resolution, and this is the same trade.
     *
     * @return ?Closure(): string
     */
    private function packageCacheDirProvider(): ?Closure
    {
        return $this->setup->isFileCacheEnabled() ? $this->cacheDirProvider : null;
    }

    /**
     * One event per discovered package, in merge order.
     *
     * After the merge rather than during it: the dispatcher is derived from the
     * merged listeners, so a listener registered in `gacela.php` -- the natural
     * place to log what a boot picked up -- does not exist yet while the packages
     * are being merged.
     */
    private function announceDiscoveredPackages(): void
    {
        if (!self::shouldDispatch(PackageConfigMergedEvent::class)) {
            return;
        }

        foreach (PackageDiscoveryRegistry::discovered() as $package) {
            self::dispatchEvent(new PackageConfigMergedEvent(
                $package->name,
                $package->configFile,
                $package->position,
            ));
        }
    }

    /**
     * The memo, but only when it belongs to this factory's app root and setup.
     */
    private function memoized(): ?GacelaConfigFileInterface
    {
        if (self::$memoizedAppRootDir !== $this->appRootDir || self::$memoizedSetup !== $this->setup) {
            return null;
        }

        return self::$gacelaFileConfig;
    }

    private function memoize(GacelaConfigFileInterface $gacelaFileConfig): GacelaConfigFileInterface
    {
        self::$gacelaFileConfig = $gacelaFileConfig;
        self::$memoizedSetup = $this->setup;
        self::$memoizedAppRootDir = $this->appRootDir;

        return $gacelaFileConfig;
    }

    private function createFileIo(): FileIoInterface
    {
        return new FileIo();
    }

    private function getGacelaPhpDefaultPath(): string
    {
        return sprintf(
            '%s/%s%s',
            $this->appRootDir,
            self::GACELA_PHP_CONFIG_FILENAME,
            self::GACELA_PHP_CONFIG_EXTENSION,
        );
    }

    private function getGacelaPhpPathFromEnv(): string
    {
        return sprintf(
            '%s/%s-%s%s',
            $this->appRootDir,
            self::GACELA_PHP_CONFIG_FILENAME,
            $this->env(),
            self::GACELA_PHP_CONFIG_EXTENSION,
        );
    }

    private function env(): string
    {
        return AppEnv::current();
    }

    private function createPathFinder(): PathFinderInterface
    {
        return new PathFinder();
    }

    private function createPathNormalizer(): PathNormalizerInterface
    {
        $chain = [];
        foreach ($this->dimensions()->suffixChain($this->env()) as $suffix) {
            $chain[] = new WithSuffixAbsolutePathStrategy($this->appRootDir, $suffix);
        }

        return new AbsolutePathNormalizer([
            AbsolutePathNormalizer::WITHOUT_SUFFIX => new WithoutSuffixAbsolutePathStrategy($this->appRootDir),
            AbsolutePathNormalizer::WITH_SUFFIX => new WithSuffixAbsolutePathStrategy($this->appRootDir, $this->env()),
        ], $chain);
    }
}
