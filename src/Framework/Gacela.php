<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Closure;
use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\ClassResolver\AbstractClassResolver;
use Gacela\Framework\ClassResolver\ClassNameCache;
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
        $setup = $configFn !== null
            ? SetupGacela::fromCallable($configFn)
            : new SetupGacela();

        if ($setup->isResetCache()) {
            ClassNameCache::resetCachedClassNames();
            AbstractClassResolver::resetCache();
            Config::resetInstance();
        }

        Config::getInstance()
            ->setAppRootDir($appRootDir)
            ->setSetup($setup)
            ->init();
    }
}
