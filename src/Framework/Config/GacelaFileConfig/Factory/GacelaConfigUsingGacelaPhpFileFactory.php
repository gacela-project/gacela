<?php

declare(strict_types=1);

namespace Gacela\Framework\Config\GacelaFileConfig\Factory;

use Gacela\Framework\Bootstrap\GacelaConfig;
use Gacela\Framework\Bootstrap\SetupGacela;
use Gacela\Framework\Bootstrap\SetupGacelaInterface;
use Gacela\Framework\Config\FileIoInterface;
use Gacela\Framework\Config\GacelaConfigBuilder\AppConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\BindingsBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Config\GacelaConfigFileFactoryInterface;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFile;
use Gacela\Framework\Config\GacelaFileConfig\GacelaConfigFileInterface;
use RuntimeException;

use function is_callable;

final class GacelaConfigUsingGacelaPhpFileFactory implements GacelaConfigFileFactoryInterface
{
    public function __construct(
        private string $gacelaPhpPath,
        private SetupGacelaInterface $bootstrapSetup,
        private FileIoInterface $fileIo,
    ) {
    }

    public function createGacelaFileConfig(): GacelaConfigFileInterface
    {
        $projectGacelaConfig = $this->createGacelaConfig();
        $projectSetupGacela = SetupGacela::fromGacelaConfig($projectGacelaConfig);

        $this->bootstrapSetup->combine($projectSetupGacela);

        $configBuilder = $this->createConfigBuilder($projectSetupGacela);
        $bindingsBuilder = $this->createBindingsBuilder($projectSetupGacela);
        $suffixTypesBuilder = $this->createSuffixTypesBuilder($projectSetupGacela);

        return (new GacelaConfigFile())
            ->setConfigItems($configBuilder->build())
            ->setBindings($bindingsBuilder->build())
            ->setSuffixTypes($suffixTypesBuilder->build());
    }

    private function createGacelaConfig(): GacelaConfig
    {
        $gacelaConfig = new GacelaConfig($this->bootstrapSetup->externalServices());

        /** @var callable(GacelaConfig):void|null $configFn */
        $configFn = $this->fileIo->include($this->gacelaPhpPath);

        if (!is_callable($configFn)) {
            throw new RuntimeException('`gacela.php` file should return a `callable(GacelaConfig)`');
        }

        $configFn($gacelaConfig);

        return $gacelaConfig;
    }

    private function createConfigBuilder(SetupGacelaInterface $setupGacela): AppConfigBuilder
    {
        return $setupGacela->buildAppConfig(new AppConfigBuilder());
    }

    private function createBindingsBuilder(SetupGacelaInterface $setupGacela): BindingsBuilder
    {
        return $setupGacela->buildBindings(new BindingsBuilder(), $this->bootstrapSetup->externalServices());
    }

    private function createSuffixTypesBuilder(SetupGacelaInterface $setupGacela): SuffixTypesBuilder
    {
        return $setupGacela->buildSuffixTypes(new SuffixTypesBuilder());
    }
}
