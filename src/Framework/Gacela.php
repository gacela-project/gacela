<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Closure;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Bootstrap\SetupGacelaInterface;
use Gacela\Framework\ClassResolver\AbstractClassResolver;
use Gacela\Framework\ClassResolver\Cache\GacelaFileCache;
use Gacela\Framework\ClassResolver\Cache\InMemoryCache;
use Gacela\Framework\ClassResolver\ClassResolverCache;
use Gacela\Framework\ClassResolver\GlobalInstance\AnonymousGlobal;
use Gacela\Framework\Config\Config;
use Gacela\Framework\Config\ConfigFactory;
use Gacela\Framework\Container\Container;
use Gacela\Framework\Container\Locator;
use Gacela\Framework\DocBlockResolver\DocBlockResolverCache;
use Gacela\Framework\Exception\GacelaNotBootstrappedException;

use RecursiveCallbackFilterIterator;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

use SplFileInfo;

use function is_string;
use function sprintf;

final class Gacela
{
    private const GACELA_PHP_FILENAME = 'gacela.php';

    private static ?Container $mainContainer = null;

    private static ?string $appRootDir = null;

    /**
     * Define the entry point of Gacela.
     *
     * @param null|Closure(GacelaConfig):void $configFn
     */
    public static function bootstrap(string $appRootDir, Closure $configFn = null): void
    {
        self::$appRootDir = $appRootDir;
        self::$mainContainer = null;

        $setup = self::processConfigFnIntoSetup($configFn);

        if ($setup->shouldResetInMemoryCache()) {
            self::resetCache();
        }

        self::addModuleBindingsToSetup($setup);

        $config = Config::createWithSetup($setup);
        $config->setAppRootDir($appRootDir)
            ->init();

        self::runPlugins($config);
    }

    /**
     * @template T
     *
     * @param class-string<T> $className
     *
     * @return T|null
     */
    public static function get(string $className): mixed
    {
        return Locator::getSingleton($className, self::$mainContainer);
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
     * @param object|string $context It can be the string-key (file path) or the class/object itself.
     *                               If empty then the caller's file will be use
     */
    public static function addGlobal(object $resolvedClass, object|string $context = ''): void
    {
        if (is_string($context) && is_file($context)) {
            $context = basename($context, '.php');
        } elseif ($context === '') {
            // Use the caller's file as context
            $context = basename(debug_backtrace()[0]['file'] ?? __FILE__, '.php');
        }

        AnonymousGlobal::addGlobal($context, $resolvedClass);
    }

    public static function overrideExistingResolvedClass(string $className, object $resolvedClass): void
    {
        AnonymousGlobal::overrideExistingResolvedClass($className, $resolvedClass);
    }

    /**
     * @param null|Closure(GacelaConfig):void $configFn
     */
    private static function processConfigFnIntoSetup(Closure $configFn = null): SetupGacelaInterface
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

    private static function resetCache(): void
    {
        AnonymousGlobal::resetCache();
        AbstractFacade::resetCache();
        AbstractFactory::resetCache();
        AbstractClassResolver::resetCache();
        InMemoryCache::resetCache();
        GacelaFileCache::resetCache();
        DocBlockResolverCache::resetCache();
        ClassResolverCache::resetCache();
        ConfigFactory::resetCache();
        Config::resetInstance();
        Locator::resetInstance();
    }

    private static function runPlugins(Config $config): void
    {
        self::$mainContainer = Container::withConfig($config);

        $plugins = $config->getSetupGacela()->getPlugins();

        foreach ($plugins as $plugin) {
            /** @var callable $current */
            $current = is_string($plugin)
                ? self::$mainContainer->get($plugin)
                : $plugin;

            self::$mainContainer->resolve($current);
        }
    }

    private static function addModuleBindingsToSetup(SetupGacelaInterface $setup): void
    {
        $setup->combine(SetupGacela::fromCallable(static function (GacelaConfig $config): void {
            foreach (self::collectBindingsFromProviders() as $k => $v) {
                $config->addBinding($k, $v);
            }
        }));
    }

    /**
     * @return array<class-string, class-string|callable|object>
     */
    private static function collectBindingsFromProviders(): array
    {
        if (self::$appRootDir === null || !is_dir(self::$appRootDir)) {
            return [];
        }

        $result = [];
        /** @var SplFileInfo $file */
        foreach (self::createRecursiveIterator() as $file) {
            if ($file->getExtension() === 'php') {
                $fileContents = (string)file_get_contents($file->getPathname());
                if (preg_match('/namespace\s+([a-zA-Z0-9_\\\\]+)\s*;/', $fileContents, $matches) !== false) {
                    $namespace = $matches[1] ?? ''; // @phpstan-ignore-line
                } else {
                    $namespace = '';
                }

                /** @var string $className */
                $className = pathinfo($file->getFilename(), PATHINFO_FILENAME);
                $fullClassName = $namespace !== ''
                    ? $namespace . '\\' . $className
                    : $className;

                if (class_exists($fullClassName)) {
                    if (is_subclass_of($fullClassName, AbstractProvider::class)) {
                        $result = array_merge($result, (new $fullClassName())->bindings);
                    }
                }
            }
        }
        return $result;
    }

    /**
     * @return RecursiveIteratorIterator<RecursiveDirectoryIterator>
     */
    private static function createRecursiveIterator(): RecursiveIteratorIterator
    {
        $directoryIterator = new RecursiveDirectoryIterator(
            self::$appRootDir ?? '',
            RecursiveDirectoryIterator::SKIP_DOTS,
        );

        $filterIterator = new RecursiveCallbackFilterIterator(
            $directoryIterator,
            static function ($current, $key, $iterator) {
                /** @var SplFileInfo $current */
                if ($iterator->hasChildren() && $current->getFilename() === 'vendor') {
                    // Skip the vendor directory
                    return false;
                }
                return true;
            },
        );

        /** @var RecursiveIteratorIterator<RecursiveDirectoryIterator> */
        return new RecursiveIteratorIterator($filterIterator);
    }
}
