<?php

declare(strict_types=1);

namespace Gacela\Framework;

final class Gacela
{
    /**
     * @param array<string, mixed> $globalServices
     */
    public static function bootstrap(string $applicationRootDir, array $globalServices = []): void
    {
        Config::getInstance()
            ->setGlobalConfigServices($globalServices)
            ->init($applicationRootDir);
    }
}
