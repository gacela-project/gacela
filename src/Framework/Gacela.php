<?php

declare(strict_types=1);

namespace Gacela\Framework;

use Gacela\Framework\Setup\SetupGacela;
use Gacela\Framework\Setup\SetupGacelaInterface;

final class Gacela
{
    public const CONFIG = 'config';
    public const MAPPING_INTERFACES = 'mapping-interfaces';
    public const SUFFIX_TYPES = 'suffix-types';
    public const EXTERNAL_SERVICES = 'external-services';

    /**
     * Define the entry point of Gacela.
     */
    public static function bootstrap(string $appRootDir, ?SetupGacelaInterface $setup = null): void
    {
        Config::getInstance()
            ->setAppRootDir($appRootDir)
            ->setSetup($setup ?? new SetupGacela())
            ->init();
    }
}
