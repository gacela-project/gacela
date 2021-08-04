<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;
use Gacela\Framework\Config\GacelaFileConfig\GacelaJsonConfigFile;
use Gacela\Framework\Config\GacelaFileConfig\GacelaPhpConfigFile;

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

    public function createGacelaFileConfig(): GacelaConfigFileInterface
    {
        $gacelaPhpPath = $this->applicationRootDir . '/' . $this->gacelaPhpConfigFilename;
        if (is_file($gacelaPhpPath)) {
            /** @psalm-suppress MixedArgument */
            return GacelaPhpConfigFile::fromArray(
                include $gacelaPhpPath
            );
        }

        /** ☟ DEPRECATED ☟ */
        $gacelaJsonPath = $this->applicationRootDir . '/' . $this->gacelaJsonConfigFilename;
        if (is_file($gacelaJsonPath)) {
//            trigger_error('gacela.json is deprecated. Use gacela.php instead.',E_USER_DEPRECATED);
            /**
             * @psalm-suppress MixedArgumentTypeCoercion
             * @psalm-suppress DeprecatedClass GacelaJsonConfig
             */
            return GacelaJsonConfigFile::fromArray(
                (array)json_decode(file_get_contents($gacelaJsonPath), true)
            );
        }
        /** ☝☝☝☝☝☝☝☝☝ */

        return GacelaPhpConfigFile::withDefaults();
    }
}
