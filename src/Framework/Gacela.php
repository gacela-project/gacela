<?php

declare(strict_types=1);

namespace Gacela\Framework;

final class Gacela
{
    public const CONFIG = 'config';
    public const MAPPING_INTERFACES = 'mapping-interfaces';
    public const SUFFIX_TYPES = 'suffix-types';
    public const GLOBAL_SERVICES = 'global-services';

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
