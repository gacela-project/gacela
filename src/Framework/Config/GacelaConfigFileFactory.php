<?php

declare(strict_types=1);

namespace Gacela\Framework\Config;

use Gacela\Framework\AbstractConfigGacela;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFile;
use RuntimeException;
use function is_callable;

final class GacelaConfigFileFactory implements GacelaConfigFileFactoryInterface
{
    private string $applicationRootDir;
    private string $gacelaPhpConfigFilename;

    /** @var array<string, mixed> */
    private array $globalServices;

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
            return $this->createDefaultGacelaPhpConfig();
        }

        /** @var array|callable|mixed $configGacela */
        $configGacela = include $gacelaPhpPath;

        if (!is_callable($configGacela)) {
            throw new RuntimeException('Create a function that returns an anonymous class that extends AbstractConfigGacela');
        }

        /** @var AbstractConfigGacela $configGacelaClass */
        $configGacelaClass = $configGacela($this->globalServices);
        if (!is_subclass_of($configGacelaClass, AbstractConfigGacela::class)) {
            throw new RuntimeException('Your anonymous class must extends AbstractConfigGacela');
        }

        /** @psalm-suppress ArgumentTypeCoercion */
        return GacelaConfigFile::fromArray([
            'config' => $configGacelaClass->config(),
            'mapping-interfaces' => $configGacelaClass->mappingInterfaces(),
        ]);
    }

    private function createDefaultGacelaPhpConfig(): GacelaConfigFile
    {
        if (isset($this->globalServices['config'])) {
            /** @var array{
             *     config: array<array>|array{type:string,path:string,path_local:string},
             *     mapping-interfaces: array<string,string|callable>,
             * } $configFromGlobalServices
             */
            $configFromGlobalServices = $this->globalServices;

            return GacelaConfigFile::fromArray($configFromGlobalServices);
        }

        return GacelaConfigFile::withDefaults();
    }
}
