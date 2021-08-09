<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\AbstractConfigGacela;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFile;

final class GacelaConfigFileFactory implements GacelaConfigFileFactoryInterface
{
    private string $applicationRootDir;
    private array $globalServices;
    private string $gacelaPhpConfigFilename;

    /**
     * @param array<string, mixed> $globalServices
     */
    public function __construct(
        string $applicationRootDir,
        array $globalServices,
        string $gacelaPhpConfigFilename
    ) {
        $this->applicationRootDir = $applicationRootDir;
        $this->globalServices = $globalServices;
        $this->gacelaPhpConfigFilename = $gacelaPhpConfigFilename;
    }

    public function createGacelaFileConfig(): GacelaConfigFile
    {
        $gacelaPhpPath = $this->applicationRootDir . '/' . $this->gacelaPhpConfigFilename;

        if (!is_file($gacelaPhpPath)) {
            return GacelaConfigFile::withDefaults();
        }

        /** @var array|callable|mixed $configGacela */
        $configGacela = include $gacelaPhpPath;

        if (is_callable($configGacela)) {
            /** @var AbstractConfigGacela $configGacelaClass */
            $configGacelaClass = $configGacela($this->globalServices);
            if (!is_subclass_of($configGacelaClass, AbstractConfigGacela::class)) {
                throw new \RuntimeException('Your anon-class must extends AbstractConfigGacela');
            }

            /** @psalm-suppress ArgumentTypeCoercion */
            return GacelaConfigFile::fromArray([
                'config' => $configGacelaClass->config(),
                'mapping-interfaces' => $configGacelaClass->mappingInterfaces(),
            ]);
        }

        if (is_array($configGacela)) {
            trigger_error('You should switch to an anon-class which extends AbstractConfigGacela. Check documentation for more info', E_USER_DEPRECATED);
            /** @psalm-suppress MixedArgumentTypeCoercion */
            return GacelaConfigFile::fromArray($configGacela);
        }

        throw new \RuntimeException('Create a function that returns an anon-class that extends AbstractConfigGacela');
    }
}
