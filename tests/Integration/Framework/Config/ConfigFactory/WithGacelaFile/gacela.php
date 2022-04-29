<?php

declare(strict_types=1);

use Gacela\Framework\Config\GacelaConfigBuilder\ConfigBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\MappingInterfacesBuilder;
use Gacela\Framework\Config\GacelaConfigBuilder\SuffixTypesBuilder;
use Gacela\Framework\Setup\SetupGacela;
use GacelaTest\Fixtures\CustomClass;
use GacelaTest\Fixtures\CustomInterface;

return static fn () => (new SetupGacela())
    ->setConfig(static function (ConfigBuilder $builder): void {
        $builder->add('config/from-gacela-file.php');
    })
    ->setExternalServices(['CustomClassFromGacelaFile' => CustomClass::class])
    ->setMappingInterfaces(
        static function (MappingInterfacesBuilder $mappingInterfacesBuilder, array $externalServices): void {
            $mappingInterfacesBuilder->bind(CustomInterface::class, $externalServices['CustomClassFromGacelaFile']);
        },
    )
    ->setSuffixTypes(static function (SuffixTypesBuilder $suffixTypesBuilder): void {
        $suffixTypesBuilder
            ->addFacade('FacadeFromGacelaFile')
            ->addFactory('FactoryFromGacelaFile')
            ->addConfig('ConfigFromGacelaFile')
            ->addDependencyProvider('DependencyProviderFromGacelaFile');
    });
