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
     * @param  null|Closure(GacelaConfig):void  $configFn
     */
    public static function bootstrap(string $appRootDir, ?Closure $configFn = null): void
    {
        self::$appRootDir = $appRootDir;
        self::$mainContainer = null;

        $setup = self::processConfigFnIntoSetup($configFn);

        if ($setup->shouldResetInMemoryCache()) {
            self::resetCache();
        }

        $config = Config::createWithSetup($setup);
        $config->setAppRootDir($appRootDir)
            ->init();

        self::runPlugins($config);
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
     * @param  object|string  $context  It can be the string-key (file path) or the class/object itself.
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
}
