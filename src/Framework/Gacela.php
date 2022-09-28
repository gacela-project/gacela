<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Closure;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Bootstrap\SetupGacelaInterface;
use Gacela\Framework\ClassResolver\AbstractClassResolver;
use Gacela\Framework\ClassResolver\InMemoryClassNameCache;
use Gacela\Framework\Config\Config;

final class Gacela
{
    /**
     * Define the entry point of Gacela.
     *
     * @param null|Closure(GacelaConfig):void $configFn
     */
    public static function bootstrap(string $appRootDir, Closure $configFn = null): void
    {
        $setup = self::processConfigFnIntoSetup($appRootDir, $configFn);

        if (!$setup->isCacheEnabled()) {
            InMemoryClassNameCache::resetCache();
            AbstractClassResolver::resetCache();
            Config::resetInstance();
        }

        Config::getInstance()
            ->setAppRootDir($appRootDir)
            ->setSetup($setup)
            ->init();
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
}
