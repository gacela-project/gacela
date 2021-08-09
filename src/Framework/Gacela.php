<?php

declare(strict_types=1);

namespace Gacela\Framework;

final class Gacela
{
    /**
     * @param array<string, mixed> $globalServices
     */
    public static function init(string $rootDir, array $globalServices = []): void
    {
        Config::getInstance()
            ->addGlobalServices($globalServices)
            ->init($rootDir);
    }
}
