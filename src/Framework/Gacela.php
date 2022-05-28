<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\ClassResolver\AbstractClassResolver;
use Gacela\Framework\Config\Config;

final class Gacela
{
    /**
     * Define the entry point of Gacela.
     *
     * @param null|callable(GacelaConfig):void $configFn
     */
    public static function bootstrap(string $appRootDir, callable $configFn = null): void
    {
        $setup = $configFn !== null
            ? SetupGacela::fromCallable($configFn)
            : new SetupGacela();

        if (!$setup->isClassResolverCached()) {
            AbstractClassResolver::resetCache();
        }

        Config::getInstance()
            ->setAppRootDir($appRootDir)
            ->setSetup($setup)
            ->init();
    }
}
