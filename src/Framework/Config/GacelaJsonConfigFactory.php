<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

final class GacelaJsonConfigFactory implements GacelaJsonConfigFactoryInterface
{
    private string $applicationRootDir;
    private string $gacelaConfigFilename;

    public function __construct(
        string $applicationRootDir,
        string $gacelaConfigFilename
    ) {
        $this->applicationRootDir = $applicationRootDir;
        $this->gacelaConfigFilename = $gacelaConfigFilename;
    }

    public function createGacelaJsonConfig(): GacelaJsonConfig
    {
        $gacelaJsonPath = $this->applicationRootDir . '/' . $this->gacelaConfigFilename;

        if (is_file($gacelaJsonPath)) {
            /** @psalm-suppress MixedArgumentTypeCoercion */
            return GacelaJsonConfig::fromArray(
                (array)json_decode(file_get_contents($gacelaJsonPath), true)
            );
        }

        return GacelaJsonConfig::withDefaults();
    }
}
