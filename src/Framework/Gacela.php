<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Bootstrap\SetupGacelaInterface;
use function is_callable;

final class Gacela
{
    /**
     * Define the entry point of Gacela.
     *
     * @param SetupGacelaInterface|callable(GacelaConfig):void|null $setup
     */
    public static function bootstrap(string $appRootDir, $setup = null): void
    {
        if (is_callable($setup)) {
            $setup = SetupGacela::fromCallable($setup);
        }

        Config::getInstance()
            ->setAppRootDir($appRootDir)
            ->setSetup($setup ?? new SetupGacela())
            ->init();
    }
}
