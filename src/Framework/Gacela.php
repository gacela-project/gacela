<?php

declare(strict_types=1);

namespace Gacela\Framework;

final class Gacela
{
    /**
     * Define the entry point of Gacela.
     * Mainly to define the application root directory and optional global services.
     *
     * @param array<string,mixed> $globalServices
     */
    public static function bootstrap(string $appRootDir, array $globalServices = []): void
    {
        Config::getInstance()
            ->setGlobalConfigServices($globalServices)
            ->init($appRootDir);
    }
}
