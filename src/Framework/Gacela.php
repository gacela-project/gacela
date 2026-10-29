<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Closure;
use Composer\InstalledVersions;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Bootstrap\Package\PackageContribution;
use Gacela\Framework\Bootstrap\Package\PackageDiscoveryRegistry;
use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Bootstrap\SetupGacelaInterface;
use Gacela\Framework\Cache\FileCache;
use Gacela\Framework\Cache\WritableDirectory;
use Gacela\Framework\ClassResolver\AbstractClassResolver;
use Gacela\Framework\ClassResolver\Cache\AbstractPhpFileCache;
use Gacela\Framework\ClassResolver\Cache\GacelaFileCache;
use Gacela\Framework\ClassResolver\Cache\InMemoryCache;
use Gacela\Framework\ClassResolver\ClassInfo;
use Gacela\Framework\ClassResolver\ClassNameFinder\ClassValidator;
use Gacela\Framework\ClassResolver\ClassResolverCache;
use Gacela\Framework\ClassResolver\GlobalInstance\AnonymousGlobal;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Config\ConfigFactory;
use Gacela\Framework\Config\PathFinder;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Container\Locator;
use Gacela\Framework\Container\SharedPlanCache;
use Gacela\Framework\Event\Bootstrap\GacelaBootstrapFinishedEvent;
use Gacela\Framework\Event\Bootstrap\GacelaBootstrapStartedEvent;
use Gacela\Framework\Event\Dispatcher\EventDispatchingCapabilities;
use Gacela\Framework\Exception\GacelaNotBootstrappedException;
use Gacela\Framework\Exception\ServiceNotFoundException;
use Gacela\Framework\Health\HealthCheckRegistry;
use Gacela\Framework\Plugins\Membership\AttributeMembership;
use Gacela\Framework\ServiceResolver\DocBlockResolver;
use Gacela\Framework\ServiceResolver\ServiceResolverCache;
use ReflectionClass;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;

use function class_exists;
use function is_a;
use function is_object;
use function is_string;
use function sprintf;

final class Gacela
{
    use EventDispatchingCapabilities;

    private const GACELA_PHP_FILENAME = 'gacela.php';

    private const PACKAGE_NAME = 'gacela-project/gacela';

    private static ?Container $mainContainer = null;

    private static ?string $appRootDir = null;

    /**
     * The installed version of gacela, derived at runtime from Composer's
     * metadata so it never drifts from the actual release being shipped.
     */
    public static function version(): string
    {
        if (!InstalledVersions::isInstalled(self::PACKAGE_NAME)) {
            return 'unknown';
        }

        return InstalledVersions::getPrettyVersion(self::PACKAGE_NAME) ?? 'unknown';
    }

    /**
     * Define the entry point of Gacela.
     *
     * @param  null|Closure(GacelaConfig):void  $configFn
     */
    public static function bootstrap(string $appRootDir, ?Closure $configFn = null): void
    {
        $startTime = hrtime(true);

        self::$appRootDir = $appRootDir;
        self::$mainContainer = null;
        Locator::resetInstance();

        // Clear health checks once per bootstrap, before the config files are
        // read, so checks registered across gacela.php + gacela-{env}.php
        // accumulate within a single bootstrap without leaking across bootstraps.
        HealthCheckRegistry::reset();

        $setup = self::processConfigFnIntoSetup($configFn);
        $resetFirst = $setup->shouldResetInMemoryCache();

        if ($resetFirst) {
            self::resetCache();
        }

        $config = self::createConfig($setup, $appRootDir);

        if (self::shouldDispatch(GacelaBootstrapStartedEvent::class)) {
            self::dispatchEvent(new GacelaBootstrapStartedEvent($appRootDir));
        }

        // With a closure, `gacela.php` is merged into the setup only here, after
        // the caches it may ask to reset were already in use: reset, then build
        // again from the same closure. Memoized, so nothing is read twice
        // unless the reset happens.
        if (!$resetFirst && self::assemblesAReset($config)) {
            self::resetCache();
            HealthCheckRegistry::reset();
            $config = self::createConfig(self::processConfigFnIntoSetup($configFn), $appRootDir);
        }

        // Before init(): the containers it builds take the shared plan cache,
        // so it has to hold what earlier processes planned by then.
        self::persistContainerPlans($config);

        // Batch the file-cache writes produced while resolving classes during
        // bootstrap into a single atomic write per cache file (like cache:warm
        // does), instead of one full-file rewrite per newly discovered key.
        AbstractPhpFileCache::beginBatch();

        try {
            $config->init();
            self::runPlugins($config);
        } finally {
            AbstractPhpFileCache::commitBatch();
        }

        if (self::shouldDispatch(GacelaBootstrapFinishedEvent::class)) {
            self::dispatchEvent(new GacelaBootstrapFinishedEvent((float)(hrtime(true) - $startTime) / 1e6));
        }
    }

    /**
     * @template T
     *
     * @param  class-string<T>  $className
     *
     * @return T|null
     */
    public static function get(string $className): mixed
    {
        return Locator::getSingleton($className, self::container());
    }

    /**
     * Get a service from the container, throwing an exception if not found.
     * Use this when you expect the service to exist and want type-safe returns.
     *
     * @template T of object
     *
     * @param  class-string<T>  $className
     *
     * @throws ServiceNotFoundException
     *
     * @return T
     */
    public static function getRequired(string $className): object
    {
        return Locator::getRequiredSingleton($className, self::container());
    }

    /**
     * Get the main dependency injection container.
     * This is the actual container created during bootstrap with all runtime bindings and frozen services.
     *
     * @throws GacelaNotBootstrappedException if Gacela has not been bootstrapped yet
     */
    public static function container(): Container
    {
        if (!self::$mainContainer instanceof Container) {
            throw new GacelaNotBootstrappedException();
        }

        return self::$mainContainer;
    }

    /**
     * Get the application root dir set when bootstrapping gacela
     */
    public static function rootDir(): string
    {
        if (self::$appRootDir === null) {
            throw new GacelaNotBootstrappedException();
        }

        return self::$appRootDir;
    }

    /**
     * Add an anonymous class as 'Config', 'Factory' or 'Provider' as a global resource
     * bound to the context that it is passed as second argument.
     *
     * @param  object|string  $context  It can be the string-key (file path) or the class/object itself.
     *                               If empty then the caller's file will be use
     */
    public static function addGlobal(object $resolvedClass, object|string $context = ''): void
    {
        if (is_object($context) || class_exists($context)) {
            $context = (new ReflectionClass($context))->getFileName() ?: $context;
        }

        if (is_string($context) && is_file($context)) {
            $context = ClassInfo::contextFromFile($context);
        } elseif ($context === '') {
            $trace = debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS);
            $callerFile = $trace[0]['file'] ?? __FILE__;
            $context = ClassInfo::contextFromFile($callerFile);
        }

        AnonymousGlobal::addGlobal($context, $resolvedClass);
    }

    public static function overrideExistingResolvedClass(string $className, object $resolvedClass): void
    {
        AnonymousGlobal::overrideExistingResolvedClass($className, $resolvedClass);
    }

    /**
     * Between two requests served by one long-running process (FrankenPHP
     * worker mode, Laravel Octane, RoadRunner, Swoole), drop what a request
     * can leave behind: the Factories and every `singleton()` they built, each
     * module's container with what its Provider set, the resolved pillars and
     * what `Gacela::get()` handed out.
     *
     * Everything derived from code and configuration is kept: resolved class
     * names, reflection, the merged config and the containers `gacela.php`
     * configured. So the next request does not walk namespaces or reread
     * config, it only builds again the services it uses. A singleton declared
     * in `gacela.php` lives for the process, as a shared service does in any
     * container; bind anything request-bound in a Provider instead.
     */
    public static function resetRequestState(): void
    {
        AbstractFacade::resetFactories();
        AbstractFactory::resetModuleContainers();
        AbstractClassResolver::resetInstances();
        Locator::resetInstance();
    }

    /**
     * Reset every singleton + in-memory cache registered by Gacela.
     *
     * Exposed publicly so test fixtures (see {@see Testing\ContainerFixture})
     * can share this exact sequence instead of duplicating the list.
     *
     * It clears caches; it does not un-bootstrap. `$appRootDir` and
     * `$mainContainer` survive on purpose: bootstrap() sets the root before it
     * calls this, and Config::getInstance() already reports "not bootstrapped"
     * afterwards. StaticStateCoverageTest records both decisions.
     */
    public static function resetCache(): void
    {
        AnonymousGlobal::resetCache();

        AbstractFacade::resetCache();
        AbstractFactory::resetCache();
        AbstractClassResolver::resetCache();
        InMemoryCache::resetCache();
        GacelaFileCache::resetCache();
        AbstractPhpFileCache::resetCache();
        WritableDirectory::resetCache();
        FileCache::resetWriteFailures();
        ServiceResolverCache::resetCache();
        DocBlockResolver::resetCache();
        ClassResolverCache::resetCache();
        ConfigFactory::resetCache();
        // Kept in step with the line above: the registry describes the merged
        // configuration that memo holds, and one surviving the other would have
        // `debug:container` describing a configuration that is gone.
        PackageDiscoveryRegistry::reset();
        // Same reason, one level along: the baseline it memoizes looks
        // constant and is not. See PackageContribution::resetCache().
        PackageContribution::resetCache();
        PathFinder::resetCache();
        AttributeMembership::resetCache();
        ClassValidator::resetCache();
        // Dropping the shared plan cache is cheap -- one null assignment -- and
        // hands back the memory it holds. Not to be confused with
        // Container::resetStaticCaches(), which is deliberately NOT called
        // here: that one throws away reflection output that must then be
        // rebuilt, measured 15-23% on a seven-module bootstrap, and buys
        // nothing, since only positives are stored and nothing goes stale
        // across a re-bootstrap. cache:clear is where that one belongs.
        SharedPlanCache::resetCache();
        // Resets EventDispatcherProvider too.
        Config::resetInstance();
        Locator::resetInstance();
    }

    private static function persistContainerPlans(Config $config): void
    {
        // With resetInMemoryCache() in the closure, gacela.php is not merged
        // yet: its file-cache switch and directory would be read too early,
        // and the directory memoized for every cache after this one.
        $config->getFactory()->createGacelaFileConfig();

        if (!$config->getSetupGacela()->isFileCacheEnabled()) {
            return;
        }

        $cacheDir = $config->getCacheDir();
        if (!WritableDirectory::isUsable($cacheDir)) {
            return;
        }

        $file = AbstractPhpFileCache::absoluteFilename($cacheDir, SharedPlanCache::FILENAME, $config->getAppRootDir());
        if (is_file($file) && !FileCache::isSafeToRead($file)) {
            return;
        }

        SharedPlanCache::persistIn($file);
    }

    private static function createConfig(SetupGacelaInterface $setup, string $appRootDir): Config
    {
        $config = Config::createWithSetup($setup);
        $config->setAppRootDir($appRootDir);

        return $config;
    }

    private static function assemblesAReset(Config $config): bool
    {
        $config->getFactory()->createGacelaFileConfig();

        return $config->getSetupGacela()->shouldResetInMemoryCache();
    }

    /**
     * @param  null|Closure(GacelaConfig):void  $configFn
     */
    private static function processConfigFnIntoSetup(?Closure $configFn = null): SetupGacelaInterface
    {
        if ($configFn instanceof Closure) {
            return SetupGacela::fromCallable($configFn);
        }

        $gacelaFilePath = sprintf(
            '%s%s%s',
            self::rootDir(),
            DIRECTORY_SEPARATOR,
            self::GACELA_PHP_FILENAME,
        );

        if (is_file($gacelaFilePath)) {
            return SetupGacela::fromFile($gacelaFilePath);
        }

        return new SetupGacela();
    }

    private static function runPlugins(Config $config): void
    {
        self::$mainContainer = Container::withConfig($config);
        AbstractFactory::useAppContainer(self::$mainContainer);

        $plugins = $config->getSetupGacela()->getPlugins();

        foreach ($plugins as $plugin) {
            /** @var callable $current */
            $current = is_string($plugin)
                ? self::instantiatePlugin($plugin, self::$mainContainer)
                : $plugin;

            self::$mainContainer->resolve(
                $current,
                self::containerArguments(new ReflectionFunction(Closure::fromCallable($current)), self::$mainContainer),
            );
        }
    }

    /**
     * Autowiring builds a fresh, empty container for a parameter typed as one,
     * so a plugin asking for the container would read none of the bindings,
     * and its `getLocator()` would answer from that empty one rather than the
     * one `Gacela::get()` reads. Handed the application container instead.
     */
    private static function instantiatePlugin(string $plugin, Container $container): mixed
    {
        if (!class_exists($plugin)) {
            return $container->get($plugin);
        }

        $constructor = (new ReflectionClass($plugin))->getConstructor();
        $arguments = $constructor instanceof ReflectionMethod ? self::containerArguments($constructor, $container) : [];

        return $arguments === [] ? $container->get($plugin) : $container->make($plugin, $arguments);
    }

    /**
     * @return array<string, Container>
     */
    private static function containerArguments(ReflectionFunctionAbstract $function, Container $container): array
    {
        $arguments = [];

        foreach ($function->getParameters() as $parameter) {
            $type = $parameter->getType();
            if ($type instanceof ReflectionNamedType && !$type->isBuiltin() && is_a($container, $type->getName())) {
                $arguments[$parameter->getName()] = $container;
            }
        }

        return $arguments;
    }
}
