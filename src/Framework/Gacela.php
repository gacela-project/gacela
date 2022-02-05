<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\Config\ConfigReaderInterface;

final class Gacela
{
    /**
     * Define the entry point of Gacela.
     *
     * @param array<string,mixed> $globalServices
     * @param array<string,ConfigReaderInterface> $configReaders
     */
    public static function bootstrap(string $appRootDir, array $globalServices = [], array $configReaders = []): void
    {
        Config::getInstance()
            ->setAppRootDir($appRootDir)
            ->setGlobalConfigServices($globalServices)
            ->setConfigReaders($configReaders)
            ->init();
    }
}
