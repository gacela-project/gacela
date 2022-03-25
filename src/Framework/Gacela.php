<?php

declare(strict_types=1);

namespace Gacela\Framework;

final class Gacela
{
    /**
     * Define the entry point of Gacela.
     *
     * @param array<string,mixed> $setup
     */
    public static function bootstrap(string $appRootDir, array $setup = []): void
    {
        Config::getInstance()
            ->setAppRootDir($appRootDir)
            ->setSetup($setup)
            ->init();
    }
}
