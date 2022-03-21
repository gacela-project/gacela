<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\ClassResolver\Cache\FileCached;

final class Gacela
{
    /**
     * Define the entry point of Gacela.
     *
     * @param array<string,mixed> $globalServices
     */
    public static function bootstrap(string $appRootDir, array $globalServices = []): void
    {
        FileCached::cleanCache();

        Config::getInstance()
            ->setAppRootDir($appRootDir)
            ->setGlobalServices($globalServices)
            ->init();
    }
}
