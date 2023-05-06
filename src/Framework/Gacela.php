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
use RuntimeException;

final class Gacela
{
    private static ?Container $mainContainer = null;

    /**
     * Define the entry point of Gacela.
     *
     * @param null|Closure(GacelaConfig):void $configFn
     */
    public static function bootstrap(string $appRootDir, Closure $configFn = null): void
    {
        $setup = self::processConfigFnIntoSetup($appRootDir, $configFn);

        if ($setup->shouldResetInMemoryCache()) {
            AbstractFacade::resetCache();
            AnonymousGlobal::resetCache();
            AbstractFactory::resetCache();
            GacelaFileCache::resetCache();
            DocBlockResolverCache::resetCache();
            ClassResolverCache::resetCache();
            InMemoryCache::resetCache();
            AbstractClassResolver::resetCache();
            ConfigFactory::resetCache();
            Config::resetInstance();
        }

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
        if (self::$mainContainer === null) {
            throw new RuntimeException('You forgot to call first `Gacela::bootstrap()`');
        }

        return Locator::getSingleton($className, self::$mainContainer);
    }

    /**
     * @param null|Closure(GacelaConfig):void $configFn
     */
    private static function processConfigFnIntoSetup(string $appRootDir, Closure $configFn = null): SetupGacelaInterface
    {
        if ($configFn !== null) {
            return SetupGacela::fromCallable($configFn);
        }

        $gacelaFilePath = $appRootDir . '/gacela.php';

        if (is_file($gacelaFilePath)) {
            return SetupGacela::fromFile($gacelaFilePath);
        }

        return new SetupGacela();
    }

    private static function runPlugins(Config $config): void
    {
        $plugins = $config->getSetupGacela()->getPlugins();

        if ($plugins === []) {
            return;
        }

        self::$mainContainer = Container::withConfig($config);

        foreach ($plugins as $pluginName) {
            /** @var callable $plugin */
            $plugin = self::$mainContainer->get($pluginName);
            $plugin();
        }
    }
}
