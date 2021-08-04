<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Config\GacelaFileConfig\GacelaFileConfig;
use Gacela\Framework\Config\GacelaFileConfig\GacelaJsonConfig;
use Gacela\Framework\Config\GacelaFileConfig\GacelaPhpConfig;

final class GacelaConfigFileFactory implements GacelaFileConfigFactoryInterface
{
    private string $applicationRootDir;
    private string $gacelaPhpConfigFilename;
    private string $gacelaJsonConfigFilename;

    public function __construct(
        string $applicationRootDir,
        string $gacelaPhpConfigFilename,
        string $gacelaJsonConfigFilename
    ) {
        $this->applicationRootDir = $applicationRootDir;
        $this->gacelaPhpConfigFilename = $gacelaPhpConfigFilename;
        $this->gacelaJsonConfigFilename = $gacelaJsonConfigFilename;
    }

    public function createGacelaFileConfig(): GacelaFileConfig
    {
        $gacelaPhpPath = $this->applicationRootDir . '/' . $this->gacelaPhpConfigFilename;
        if (is_file($gacelaPhpPath)) {
            /** @psalm-suppress MixedArgument */
            return GacelaPhpConfig::fromArray(
                include $gacelaPhpPath
            );
        }

        /** ☟ DEPRECATED ☟ */
        $gacelaJsonPath = $this->applicationRootDir . '/' . $this->gacelaJsonConfigFilename;
        if (is_file($gacelaJsonPath)) {
            /**
             * @psalm-suppress MixedArgumentTypeCoercion
             * @psalm-suppress DeprecatedClass GacelaJsonConfig
             */
            return GacelaJsonConfig::fromArray(
                (array)json_decode(file_get_contents($gacelaJsonPath), true)
            );
        }
        /** ☝☝☝☝☝☝☝☝☝☝☝☝☝ */

        return GacelaPhpConfig::withDefaults();
    }
}
